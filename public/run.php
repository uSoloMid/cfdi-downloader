<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;

use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\MetadataPackageReader;

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function baseDir(): string
{
    return dirname(__DIR__);
}
function clientsDir(): string
{
    return baseDir() . '/clients';
}
function storageDir(): string
{
    return baseDir() . '/storage';
}
function downloadsDir(): string
{
    return baseDir() . '/downloads';
}

function ensureDir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("No se pudo crear carpeta: $path");
    }
}
function pemFromDer(string $der): string
{
    return "-----BEGIN CERTIFICATE-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END CERTIFICATE-----\n";
}

function extractRfcAndNameFromCerContent(string $cerDer): array
{
    if (!function_exists('openssl_x509_parse')) {
        throw new RuntimeException("OpenSSL no está habilitado en PHP. Activa la extensión openssl.");
    }

    $pem = pemFromDer($cerDer);
    $parsed = openssl_x509_parse($pem);
    if (!$parsed || !isset($parsed['subject'])) {
        throw new RuntimeException("No pude leer el certificado (.cer).");
    }

    $subject = $parsed['subject'];

    // Nombre común (generalmente razón social / nombre)
    $name = (string)($subject['CN'] ?? '');

    // El RFC suele venir en serialNumber, pero puede variar.
    $candidate = '';
    foreach (['serialNumber', 'x500UniqueIdentifier', 'UID'] as $k) {
        if (!empty($subject[$k])) {
            $candidate .= ' ' . $subject[$k];
        }
    }
    $candidate .= ' ' . $name;

    // RFC persona moral/física (tolerante a Ñ y &)
    if (!preg_match('/\b([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\b/u', strtoupper($candidate), $m)) {
        throw new RuntimeException("No encontré RFC dentro del certificado. Revisa que sea e.firma (FIEL) y no esté dañado.");
    }

    $rfc = strtoupper($m[1]);

    return ['rfc' => $rfc, 'name' => $name, 'rawSubject' => $subject];
}
function readClientFiles(string $rfc): array
{
    $dir  = clientsDir() . '/' . $rfc;
    $cer  = $dir . '/certificado.cer';
    $key  = $dir . '/llave.key';
    $pass = $dir . '/password.txt';

    foreach ([$dir, $cer, $key, $pass] as $p) {
        if (!file_exists($p)) {
            throw new RuntimeException("Falta archivo: $p");
        }
    }

    return [
        'cer'  => file_get_contents($cer),
        'key'  => file_get_contents($key),
        'pass' => trim((string)file_get_contents($pass)),
    ];
}

function makeService(string $rfc): Service
{
    $files = readClientFiles($rfc);
    $fiel = Fiel::create($files['cer'], $files['key'], $files['pass']);

    if (!$fiel->isValid()) {
        throw new RuntimeException("La e.firma no es válida/vigente o es CSD (RFC $rfc).");
    }

    $webClient = new GuzzleWebClient();
    $requestBuilder = new FielRequestBuilder($fiel);
    return new Service($requestBuilder, $webClient);
}

function normalizePeriod(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    if ($end <= $start) $end = $start->modify('+2 seconds');
    $diff = $end->getTimestamp() - $start->getTimestamp();
    if ($diff < 2) $end = $start->modify('+2 seconds');
    return [$start, $end];
}

function saveJob(string $requestId, array $job): void
{
    ensureDir(storageDir() . '/jobs');
    file_put_contents(
        storageDir() . "/jobs/$requestId.json",
        json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function loadJob(string $requestId): array
{
    $file = storageDir() . "/jobs/$requestId.json";
    if (!file_exists($file)) throw new RuntimeException("No encuentro el job local: $file");
    return json_decode((string)file_get_contents($file), true) ?: [];
}

function render(string $title, string $bodyHtml): void
{
    echo "<!doctype html><html lang='es'><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . h($title) . "</title>";
    echo "<style>
        body{font-family:system-ui,Segoe UI,Arial;max-width:900px;margin:24px auto;padding:0 12px}
        .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin:12px 0}
        code{background:#f6f6f6;padding:2px 6px;border-radius:8px}
        .ok{color:#0a7} .warn{color:#b60} .err{color:#c00}
    </style>";
    echo "<body><p><a href='index.php'>&larr; Volver</a></p><h1>" . h($title) . "</h1>";
    echo $bodyHtml;
    echo "</body></html>";
}

date_default_timezone_set('America/Mexico_City');

$action = (string)($_POST['action'] ?? '');
if ($action === '') {
    header('Location: index.php');
    exit;
}
// ✅ PEGA AQUÍ (AQUÍ MISMO) esta función:
function extractClientInfoFromCerTmp(string $cerTmpPath): array
{
    if (!extension_loaded('openssl')) {
        throw new RuntimeException("Falta la extensión OpenSSL en PHP.");
    }

    $cert = openssl_x509_read('file://' . $cerTmpPath);
    if (!$cert) {
        throw new RuntimeException("No pude leer el certificado .cer (¿archivo inválido?).");
    }

    $data = openssl_x509_parse($cert, false);
    $subject = $data['subject'] ?? [];

    $name = trim((string)($subject['CN'] ?? $subject['O'] ?? ''));

    $hay = strtoupper(
        implode(' ', array_map('strval', $subject)) . ' ' .
            (string)($subject['serialNumber'] ?? '') . ' ' .
            (string)($subject['x500UniqueIdentifier'] ?? '')
    );

    if (!preg_match('/\b([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\b/u', $hay, $m)) {
        throw new RuntimeException("No pude detectar el RFC dentro del certificado (.cer).");
    }

    return ['rfc' => $m[1], 'name' => $name, 'subject' => $subject];
}
function monthFolderFromCfdiXml(string $xml, string $fallback = 'unknown-month'): string
{
    try {
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $root = $dom->documentElement;
        if ($root) {
            $fecha = $root->getAttribute('Fecha') ?: $root->getAttribute('fecha');
            if ($fecha) {
                $ym = substr($fecha, 0, 7);
                if (preg_match('/^\d{4}-\d{2}$/', $ym)) return $ym;
            }
        }
    } catch (Throwable $e) {
        // seguimos con regex
    }

    if (preg_match('/\bFecha="(\d{4}-\d{2})-\d{2}T/', $xml, $m)) {
        return $m[1];
    }

    return $fallback;
}

try {
    switch ($action) {
        case 'probeCer': {
                // Endpoint JSON para autollenar RFC/Nombre desde .cer

                // Asegura que no salga HTML de warnings
                $prevDisplay = ini_get('display_errors');
                ini_set('display_errors', '0');

                // Si ya se imprimió algo, lo limpiamos para que el JSON salga puro
                if (ob_get_length()) {
                    ob_clean();
                }

                header('Content-Type: application/json; charset=utf-8');

                // Convierte warnings/notices en excepciones atrapables
                set_error_handler(function ($severity, $message, $file, $line) {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                });

                try {
                    if (!isset($_FILES['cerFile'])) {
                        throw new RuntimeException("Falta archivo .cer");
                    }

                    $cer = $_FILES['cerFile'];

                    if (($cer['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException("Error subiendo .cer (code " . (int)($cer['error'] ?? -1) . ").");
                    }

                    // Leer el .cer y extraer RFC/Nombre con la función que ya tienes
                    $der = (string)file_get_contents((string)$cer['tmp_name']);
                    $info = extractRfcAndNameFromCerContent($der);

                    echo json_encode([
                        'ok' => true,
                        'rfc' => strtoupper((string)$info['rfc']),
                        'name' => (string)$info['name'],
                    ], JSON_UNESCAPED_UNICODE);

                    exit;
                } catch (Throwable $e) {
                    http_response_code(400);
                    echo json_encode([
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                } finally {
                    restore_error_handler();
                    ini_set('display_errors', (string)$prevDisplay);
                }
            }



        case 'create': {
                $rfc = trim((string)($_POST['rfc'] ?? ''));
                $startMonth = (string)($_POST['startMonth'] ?? '');
                $startYear  = (string)($_POST['startYear'] ?? '');
                $endMonth   = (string)($_POST['endMonth'] ?? '');
                $endYear    = (string)($_POST['endYear'] ?? '');

                if ($startMonth === '' || $startYear === '' || $endMonth === '' || $endYear === '') {
                    throw new RuntimeException("Fecha inválida: faltan mes/año.");
                }

                $tz = new DateTimeZone('America/Mexico_City');

                // Inicio: 01 del mes inicio a las 00:01:00 (con TZ)
                $start = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    sprintf('%04d-%02d-01 00:01:00', (int)$startYear, (int)$startMonth),
                    $tz
                );
                if (!$start) throw new RuntimeException("Fecha inválida (inicio).");

                // Fin base: 01 del mes fin, luego último día 23:59:00
                $endBase = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    sprintf('%04d-%02d-01 00:00:00', (int)$endYear, (int)$endMonth),
                    $tz
                );
                if (!$endBase) throw new RuntimeException("Fecha inválida (fin).");

                $end = $endBase->modify('last day of this month')->setTime(23, 59, 0);

                // Recorte anti-futuro: ahora - 10 min (para evitar "fecha final inválida")
                $now = new DateTimeImmutable('now', $tz);
                $safeEnd = $now->modify('-10 minutes');
                if ($end > $safeEnd) $end = $safeEnd;

                if ($end <= $start) throw new RuntimeException("Rango inválido: el fin debe ser mayor al inicio.");
                [$start, $end] = normalizePeriod($start, $end);

                $downloadType = (string)($_POST['downloadType'] ?? 'issued');
                $requestType  = (string)($_POST['requestType'] ?? 'xml');
                $status       = (string)($_POST['status'] ?? 'undefined');

                $service = makeService($rfc);

                $qp = QueryParameters::create()
                    ->withPeriod(DateTimePeriod::createFromValues(
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s')
                    ))
                    ->withDownloadType($downloadType === 'received' ? DownloadType::received() : DownloadType::issued())
                    ->withRequestType($requestType === 'metadata' ? RequestType::metadata() : RequestType::xml());

                if ($status === 'active') $qp = $qp->withDocumentStatus(DocumentStatus::active());
                if ($status === 'cancelled') $qp = $qp->withDocumentStatus(DocumentStatus::cancelled());

                $warning = "";
                if ($downloadType === 'received' && $requestType === 'xml' && $status !== 'active') {
                    $qp = $qp->withDocumentStatus(DocumentStatus::active());
                    $warning = "<p class='warn'><b>Ajuste automático:</b> Para <b>Recibidos + XML</b>, forzamos <b>Vigentes</b>.</p>";
                }

                $query = $service->query($qp);
                if (!$query->getStatus()->isAccepted()) {
                    throw new RuntimeException("Fallo al presentar consulta: " . $query->getStatus()->getMessage());
                }

                $requestId = $query->getRequestId();
                $job = [
                    'rfc' => $rfc,
                    'start' => $start->format(DateTimeInterface::ATOM),
                    'end' => $end->format(DateTimeInterface::ATOM),
                    'downloadType' => $downloadType,
                    'requestType' => $requestType,
                    'status' => $status,
                    'createdAt' => (new DateTimeImmutable('now', $tz))->format(DateTimeInterface::ATOM),
                    'packagesDownloaded' => [],
                ];
                saveJob($requestId, $job);

                render("Solicitud creada", "
                <div class='card'>
                  $warning
                  <p class='ok'><b>Listo.</b> El SAT aceptó la solicitud.</p>
                  <p><b>RequestId:</b> <code>" . h($requestId) . "</code></p>
                  <p>Ahora ve a <b>Verificar / Descargar</b> y pega ese RequestId.</p>
                </div>
            ");
                exit;
            }

        case 'check': {
                $requestId = trim((string)($_POST['requestId'] ?? ''));
                if ($requestId === '') throw new RuntimeException("RequestId vacío");

                $job = loadJob($requestId);
                $rfc = (string)($job['rfc'] ?? '');
                if ($rfc === '') throw new RuntimeException("Job sin RFC.");

                $service = makeService($rfc);

                $verify = $service->verify($requestId);
                if (!$verify->getStatus()->isAccepted()) {
                    throw new RuntimeException("Fallo al verificar: " . $verify->getStatus()->getMessage());
                }
                if (!$verify->getCodeRequest()->isAccepted()) {
                    throw new RuntimeException("Solicitud rechazada: " . $verify->getCodeRequest()->getMessage());
                }

                $statusReq = $verify->getStatusRequest()->getValue();
                $isFinished = $verify->getStatusRequest()->isFinished();

                $job['lastCheckedAt'] = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
                $job['lastStatus'] = $statusReq;
                $job['lastPackagesCount'] = (int)$verify->countPackages();
                saveJob($requestId, $job);

                $html = "<div class='card'>
                <p><b>RequestId:</b> <code>" . h($requestId) . "</code></p>
                <p><b>Estatus SAT:</b> <code>" . h($statusReq) . "</code></p>
                <p><b>Paquetes:</b> " . (int)$verify->countPackages() . "</p>
            </div>";

                if (!$isFinished) {
                    $html .= "<div class='card'><p class='warn'>Aún no está listo. Reintentando automáticamente cada 30 segundos…</p></div>";
                    $html .= "<script>setTimeout(()=>location.reload(), 30000);</script>";
                    render("Verificación", $html);
                    exit;
                }

                $packages = $verify->getPackagesIds();
                ensureDir(storageDir() . "/zips/$requestId");
                ensureDir(downloadsDir());

                $downloadType = (string)($job['downloadType'] ?? 'issued');
                $requestType  = (string)($job['requestType'] ?? 'xml');

                $savedCount = 0;
                $metadataRows = 0;

                foreach ($packages as $packageId) {
                    if (in_array($packageId, (array)($job['packagesDownloaded'] ?? []), true)) continue;

                    $download = $service->download($packageId);
                    if (!$download->getStatus()->isAccepted()) {
                        $html .= "<div class='card'><p class='err'>No pude descargar paquete " . h($packageId) . ": " . h($download->getStatus()->getMessage()) . "</p></div>";
                        continue;
                    }

                    $zipFile = storageDir() . "/zips/$requestId/$packageId.zip";
                    file_put_contents($zipFile, $download->getPackageContent());

                    if ($requestType === 'metadata') {
                        $reader = MetadataPackageReader::createFromFile($zipFile);
                        $outCsv = downloadsDir() . "/$rfc/metadata_$requestId.csv";
                        ensureDir(dirname($outCsv));

                        $isNew = !file_exists($outCsv);
                        $fh = fopen($outCsv, 'ab');
                        if ($isNew) fwrite($fh, "uuid,fechaEmision,rfcEmisor,rfcReceptor,total,estado\n");

                        foreach ($reader->metadata() as $uuid => $m) {
                            $metadataRows++;
                            $line = [
                                $m->uuid ?? (string)$uuid,
                                $m->fechaEmision ?? '',
                                $m->rfcEmisor ?? '',
                                $m->rfcReceptor ?? '',
                                $m->total ?? '',
                                $m->estado ?? '',
                            ];
                            fwrite($fh, '"' . implode('","', array_map(fn($x) => str_replace('"', '""', (string)$x), $line)) . '"' . "\n");
                        }
                        fclose($fh);
                    } else {
                        $reader = CfdiPackageReader::createFromFile($zipFile);

                        $fallbackMonth = (new DateTimeImmutable((string)$job['start']))->format('Y-m');
                        $baseDir = downloadsDir() . "/$rfc/" . ($downloadType === 'received' ? 'Recibidas' : 'Emitidas');

                        foreach ($reader->cfdis() as $uuid => $xml) {
                            $savedCount++;
                            $monthFolder = monthFolderFromCfdiXml($xml, $fallbackMonth);
                            $targetDir = $baseDir . "/$monthFolder";
                            ensureDir($targetDir);
                            file_put_contents($targetDir . "/" . $uuid . ".xml", $xml);
                        }
                    }

                    $job['packagesDownloaded'][] = $packageId;
                    saveJob($requestId, $job);
                }

                $html .= "<div class='card'>
                <p class='ok'><b>Descarga completada.</b></p>
                <p><b>Guardado en:</b> <code>" . h(downloadsDir() . "/$rfc") . "</code></p>
                <p><b>XML guardados:</b> " . (int)$savedCount . "</p>
                <p><b>Filas metadata:</b> " . (int)$metadataRows . "</p>
            </div>";

                render("Descarga", $html);
                exit;
            }

        case 'recreate': {
                $oldRequestId = trim((string)($_POST['oldRequestId'] ?? ''));
                $offsetSeconds = (int)($_POST['offsetSeconds'] ?? 1);
                if ($offsetSeconds === 0) $offsetSeconds = 1;

                $old = loadJob($oldRequestId);
                $rfc = (string)($old['rfc'] ?? '');
                if ($rfc === '') throw new RuntimeException("Job viejo sin RFC.");

                $downloadType = (string)($old['downloadType'] ?? 'issued');
                $requestType  = (string)($old['requestType'] ?? 'xml');
                $status       = (string)($old['status'] ?? 'undefined');

                $start = new DateTimeImmutable((string)$old['start']);
                $end   = new DateTimeImmutable((string)$old['end']);

                $start = $start->modify(($offsetSeconds >= 0 ? '+' : '') . $offsetSeconds . ' seconds');
                [$start, $end] = normalizePeriod($start, $end);

                $service = makeService($rfc);

                $qp = QueryParameters::create()
                    ->withPeriod(DateTimePeriod::createFromValues(
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s')
                    ))
                    ->withDownloadType($downloadType === 'received' ? DownloadType::received() : DownloadType::issued())
                    ->withRequestType($requestType === 'metadata' ? RequestType::metadata() : RequestType::xml());

                if ($status === 'active') $qp = $qp->withDocumentStatus(DocumentStatus::active());
                if ($status === 'cancelled') $qp = $qp->withDocumentStatus(DocumentStatus::cancelled());

                if ($downloadType === 'received' && $requestType === 'xml' && $status !== 'active') {
                    $qp = $qp->withDocumentStatus(DocumentStatus::active());
                }

                $query = $service->query($qp);
                if (!$query->getStatus()->isAccepted()) {
                    throw new RuntimeException("Fallo al presentar consulta: " . $query->getStatus()->getMessage());
                }

                $requestId = $query->getRequestId();
                $job = [
                    'rfc' => $rfc,
                    'start' => $start->format(DateTimeInterface::ATOM),
                    'end' => $end->format(DateTimeInterface::ATOM),
                    'downloadType' => $downloadType,
                    'requestType' => $requestType,
                    'status' => $status,
                    'createdAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                    'packagesDownloaded' => [],
                    'recreatedFrom' => $oldRequestId,
                    'offsetSeconds' => $offsetSeconds,
                ];
                saveJob($requestId, $job);

                render("Solicitud recreada", "
                <div class='card'>
                  <p class='ok'><b>Listo.</b> Se creó una nueva solicitud ajustando el inicio en {$offsetSeconds} segundos.</p>
                  <p><b>Nuevo RequestId:</b> <code>" . h($requestId) . "</code></p>
                  <p><b>Basada en:</b> <code>" . h($oldRequestId) . "</code></p>
                  <p>Ahora verifica/descarga con el nuevo RequestId.</p>
                </div>
            ");
                exit;
            }

        case 'addClient': {
                $password = (string)($_POST['password'] ?? '');

                // 1) RFC y nombre vienen del formulario
                $rfc = strtoupper(trim((string)($_POST['newRfc'] ?? '')));
                $clientName = trim((string)($_POST['clientName'] ?? ''));

                if (!preg_match('/^[A-Z0-9&Ñ]{12,13}$/u', $rfc)) {
                    throw new RuntimeException("RFC inválido. Debe tener 12 o 13 caracteres (ej. AAA010101AAA).");
                }
                if ($password === '') throw new RuntimeException("Contraseña requerida.");

                if (!isset($_FILES['cerFile'], $_FILES['keyFile'])) {
                    throw new RuntimeException("Faltan archivos .cer y .key.");
                }

                $cer = $_FILES['cerFile'];
                $key = $_FILES['keyFile'];

                if ($cer['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Error subiendo .cer (code {$cer['error']}).");
                if ($key['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Error subiendo .key (code {$key['error']}).");

                // 2) Validar que el RFC del certificado coincida con el que tecleaste
                $info = extractClientInfoFromCerTmp((string)$cer['tmp_name']);
                $certRfc = strtoupper((string)$info['rfc']);
                $certName = trim((string)$info['name']);

                if ($certRfc !== '' && $certRfc !== $rfc) {
                    throw new RuntimeException("El RFC tecleado ($rfc) no coincide con el RFC del certificado ($certRfc).");
                }

                // 3) Si no tecleaste nombre, usa el del certificado
                if ($clientName === '' && $certName !== '') {
                    $clientName = $certName;
                }

                $cerName = strtolower((string)($cer['name'] ?? ''));
                $keyName = strtolower((string)($key['name'] ?? ''));
                if (!str_ends_with($cerName, '.cer')) throw new RuntimeException("El certificado debe ser .cer");
                if (!str_ends_with($keyName, '.key')) throw new RuntimeException("La llave debe ser .key");

                $targetDir = clientsDir() . DIRECTORY_SEPARATOR . $rfc;
                if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
                    throw new RuntimeException("No pude crear carpeta del cliente: $targetDir");
                }

                $cerPath = $targetDir . DIRECTORY_SEPARATOR . 'certificado.cer';
                $keyPath = $targetDir . DIRECTORY_SEPARATOR . 'llave.key';
                $pwPath  = $targetDir . DIRECTORY_SEPARATOR . 'password.txt';

                if (!move_uploaded_file($cer['tmp_name'], $cerPath)) throw new RuntimeException("No pude guardar certificado.cer");
                if (!move_uploaded_file($key['tmp_name'], $keyPath)) throw new RuntimeException("No pude guardar llave.key");

                file_put_contents($pwPath, $password);

                // Guardar SIEMPRE client.json
                $meta = [
                    'name' => $clientName,
                    'rfc' => $rfc,
                    'savedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                ];
                file_put_contents(
                    $targetDir . DIRECTORY_SEPARATOR . 'client.json',
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );

                render("Cliente agregado", "
                <div class='card'>
                  <p class='ok'><b>Listo.</b> Se guardó el cliente <code>" . h($rfc) . "</code>.</p>
                  <ul>
                    <li><code>clients/$rfc/certificado.cer</code></li>
                    <li><code>clients/$rfc/llave.key</code></li>
                    <li><code>clients/$rfc/password.txt</code></li>
                    <li><code>clients/$rfc/client.json</code></li>
                  </ul>
                  <p>Regresa y recarga (F5) para que aparezca en el listado.</p>
                </div>
            ");
                exit;
            }

        default:
            throw new RuntimeException("Acción inválida.");
    }
} catch (Throwable $e) {
    render("Error", "<div class='card'><p class='err'><b>Error:</b> " . h($e->getMessage()) . "</p></div>");
}
