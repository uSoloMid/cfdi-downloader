<?php
declare(strict_types=1);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function clientNameByRfc(string $rfc): string {
  $rfc = trim($rfc);
  if ($rfc === '') return '';
  $file = dirname(__DIR__) . "/clients/$rfc/client.json";
  if (!is_file($file)) return '';
  $data = json_decode((string)file_get_contents($file), true) ?: [];
  return (string)($data['name'] ?? '');
}

$jobsDir = dirname(__DIR__) . '/storage/jobs';
$all = isset($_GET['all']) && $_GET['all'] === '1';
$limit = $all ? 5000 : 10;

$jobs = [];
if (is_dir($jobsDir)) {
  foreach (glob($jobsDir . '/*.json') as $file) {
    $data = json_decode((string)file_get_contents($file), true) ?: [];
    $data['_requestId'] = basename($file, '.json');
    $rfc = (string)($data['rfc'] ?? '');
    $data['_clientName'] = clientNameByRfc($rfc);
    $jobs[] = $data;
  }
}

// --- Ordenamiento del historial ---
$sort = (string)($_GET['sort'] ?? 'date'); // date | rfc | client

usort($jobs, function ($a, $b) use ($sort) {
  if ($sort === 'rfc') {
    $c = strcmp((string)($a['rfc'] ?? ''), (string)($b['rfc'] ?? ''));
    if ($c !== 0) return $c;
  }
  if ($sort === 'client') {
    $c = strcmp((string)($a['_clientName'] ?? ''), (string)($b['_clientName'] ?? ''));
    if ($c !== 0) return $c;
  }

  $ca = (string)($a['createdAt'] ?? '');
  $cb = (string)($b['createdAt'] ?? '');
  if ($ca !== '' && $cb !== '') {
    return strcmp($cb, $ca); // más reciente primero
  }
  return strcmp((string)($b['_requestId'] ?? ''), (string)($a['_requestId'] ?? ''));
});

$jobs = array_slice($jobs, 0, $limit);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial de solicitudes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>
  <header class="header">
    <div class="header-inner">
      <div class="brand">
        <div class="logo" aria-hidden="true"></div>
        <div>
          <div style="font-weight:900; line-height:1">CFDI Downloader</div>
          <div class="muted" style="font-size:12px; margin-top:2px">Historial de solicitudes</div>
        </div>
      </div>

      <nav class="nav">
        <a href="index.php">Inicio</a>
        <a href="index.php#crear-solicitud">Crear solicitud</a>
        <a href="index.php#verificar">Verificar</a>
      </nav>

      <div class="header-actions">
        <a class="btn" href="index.php">&larr; Volver</a>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="card" style="padding:18px">
      <h1 style="margin:0 0 8px">Historial de solicitudes</h1>
      <div class="muted">
        Aquí ves tus <b>RequestId</b>. Lo ideal es <b>reintentar Verificar/Descargar</b> antes de crear una nueva solicitud.
        Si necesitas recrear el periodo, usa <b>Reintentar (+segundos)</b>.
      </div>

      <form method="get" class="row" style="margin-top:14px">
        <label for="sort" class="muted" style="font-weight:700">Ordenar por:</label>
        <select id="sort" name="sort" style="min-width:200px">
          <option value="date" <?= (($_GET['sort'] ?? 'date') === 'date') ? 'selected' : '' ?>>Más recientes</option>
          <option value="rfc" <?= (($_GET['sort'] ?? '') === 'rfc') ? 'selected' : '' ?>>RFC</option>
          <option value="client" <?= (($_GET['sort'] ?? '') === 'client') ? 'selected' : '' ?>>Cliente</option>
        </select>

        <?php if ($all): ?>
          <input type="hidden" name="all" value="1">
        <?php endif; ?>

        <button type="submit" class="btn">Aplicar</button>

        <?php if (!$all): ?>
          <a class="btn btn-ghost" href="jobs.php?all=1&sort=<?= h($sort) ?>">Mostrar más</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="jobs.php?sort=<?= h($sort) ?>">Mostrar menos</a>
        <?php endif; ?>
      </form>
    </section>

    <?php if (!$jobs): ?>
      <div class="card" style="padding:16px; margin-top:16px">No hay solicitudes guardadas aún.</div>
    <?php else: ?>
      <section class="card" style="padding:0; margin-top:16px">
        <div style="overflow:auto">
          <table class="table">
            <thead>
              <tr>
                <th>RequestId</th>
                <th>RFC</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Periodo</th>
                <th>Último estado</th>
                <th style="min-width:340px">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($jobs as $j): ?>
              <?php
                $rid = (string)($j['_requestId'] ?? '');
                $rfc = (string)($j['rfc'] ?? '');
                $clientName = (string)($j['_clientName'] ?? '');
                $tipo = ((string)($j['downloadType'] ?? 'issued') === 'received' ? 'Recibidas' : 'Emitidas')
                      . ' / ' . ((string)($j['requestType'] ?? 'xml') === 'metadata' ? 'Metadata' : 'XML')
                      . ' / ' . ((string)($j['status'] ?? 'undefined'));
                $periodo = (string)($j['start'] ?? '') . " → " . (string)($j['end'] ?? '');
                $lastStatus = $j['lastStatus'] ?? '—';
                $lastCheckedAt = $j['lastCheckedAt'] ?? '—';
                $pk = $j['lastPackagesCount'] ?? '—';
              ?>
              <tr>
                <td><code><?= h($rid) ?></code></td>
                <td><?= h($rfc) ?></td>
                <td><?= h($clientName) ?></td>
                <td><?= h($tipo) ?></td>
                <td class="muted"><?= h($periodo) ?></td>
                <td>
                  <div><b><?= h((string)$lastStatus) ?></b> <span class="muted">(pk: <?= h((string)$pk) ?>)</span></div>
                  <div class="muted"><?= h((string)$lastCheckedAt) ?></div>
                </td>
                <td>
                  <div class="row" style="gap:10px">
                    <form method="post" action="run.php" style="margin:0">
                      <input type="hidden" name="action" value="check">
                      <input type="hidden" name="requestId" value="<?= h($rid) ?>">
                      <button class="btn btn-primary" type="submit">Verificar / Descargar</button>
                    </form>

                    <form method="post" action="run.php" style="margin:0; display:flex; gap:8px; align-items:center; flex-wrap:wrap">
                      <input type="hidden" name="action" value="recreate">
                      <input type="hidden" name="oldRequestId" value="<?= h($rid) ?>">
                      <span class="muted" style="font-weight:700">+seg</span>
                      <input type="number" name="offsetSeconds" value="1" min="-3600" max="3600" style="width:110px">
                      <button class="btn" type="submit">Reintentar</button>
                    </form>
                  </div>
                  <div class="muted" style="margin-top:6px">
                    * “Reintentar” crea una nueva solicitud moviendo el inicio por segundos.
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <section class="footer" style="margin-top:18px">
      <div>Tip: si el SAT tarda, reintenta "Verificar/Descargar" desde aquí sin crear una nueva solicitud.</div>
      <div>© <?= date('Y') ?> CFDI Downloader</div>
    </section>
  </main>
</body>
</html>
