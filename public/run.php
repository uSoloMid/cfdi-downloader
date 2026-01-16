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
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
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

function readClientFiles(string $rfc): array
{
    $dir = clientsDir() . '/' . $rfc;
    $cer = $dir . '/certificado.cer';
    $key = $dir . '/llave.key';
    $pass = $dir . '/password.txt';

    foreach ([$dir, $cer, $key, $pass] as $p) {
        if (!file_exists($p)) throw new RuntimeException("Falta archivo: $p");
    }

    return [
        'cer' => file_get_contents($cer),
        'key' => file_get_contents($key),
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

function dtFromLocal(string $s): DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $s);
    if (!$dt) throw new RuntimeException("Fecha inválida: $s");
    return $dt;
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
    file_put_contents(storageDir() . "/jobs/$requestId.json", json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
    echo "<style>body{font-family:system-ui,Segoe UI,Arial;max-width:900px;margin:24px auto;padding:0 12px} .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin:12px 0} code{background:#f6f6f6;padding:2px 6px;border-radius:8px} .ok{color:#0a7} .warn{color:#b60} .err{color:#c00}</style>";
    echo "<body><p><a href='index.php'>&larr; Volver</a></p><h1>" . h($title) . "</h1>";
    echo $bodyHtml;
    echo "</body></html>";
}

$action = $_POST['action'] ?? '';
try {
    if ($action === 'create') {
        $rfc = trim((string)($_POST['rfc'] ?? ''));
        $startDate = (string)($_POST['startDate'] ?? '');
        $startTime = (string)($_POST['startTime'] ?? '00:00');
        $endDate   = (string)($_POST['endDate'] ?? '');
        $endTime   = (string)($_POST['endTime'] ?? '23:59');

        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', "$startDate $startTime");
        $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i', "$endDate $endTime");

        if (!$start || !$end) {
            throw new RuntimeException("Fecha/hora inválida.");
        }

        [$start, $end] = normalizePeriod($start, $end);

        $downloadType = (string)($_POST['downloadType'] ?? 'issued');
        $requestType = (string)($_POST['requestType'] ?? 'xml');
        $status = (string)($_POST['status'] ?? 'undefined');

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
            'createdAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
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

    if ($action === 'check') {
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
        $requestType = (string)($job['requestType'] ?? 'xml');

        $savedCount = 0;
        $metadataRows = 0;

        foreach ($packages as $packageId) {
            if (in_array($packageId, (array)($job['packagesDownloaded'] ?? []), true)) {
                continue;
            }

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
                $monthFolder = (new DateTimeImmutable($job['start']))->format('Y-m');
                $targetDir = downloadsDir() . "/$rfc/" . ($downloadType === 'received' ? 'Recibidas' : 'Emitidas') . "/$monthFolder";
                ensureDir($targetDir);

                foreach ($reader->cfdis() as $uuid => $xml) {
                    $savedCount++;
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

    throw new RuntimeException("Acción inválida.");
} catch (Throwable $e) {
    render("Error", "<div class='card'><p class='err'><b>Error:</b> " . h($e->getMessage()) . "</p></div>");
}
