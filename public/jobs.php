<?php

declare(strict_types=1);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function clientNameByRfc(string $rfc): string
{
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

// Ordenamiento
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
  if ($ca !== '' && $cb !== '') return strcmp($cb, $ca);
  return strcmp((string)($b['_requestId'] ?? ''), (string)($a['_requestId'] ?? ''));
});

$jobs = array_slice($jobs, 0, $limit);
$active = 'historial';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Historial de solicitudes</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php require __DIR__ . '/partials/header.php'; ?>

<div class="container">
  <div class="card" style="padding:18px;">
    <div class="section-head">
      <div>
        <h2 style="margin:0">Historial de solicitudes</h2>
        <div class="muted">Aquí ves tus <b>RequestId</b>. Reintenta <b>Verificar/Descargar</b> antes de crear una nueva solicitud.</div>
      </div>
      <div class="badge">Paso 3</div>
    </div>

    <form method="get" class="row" style="margin: 12px 0;">
      <label for="sort"><b>Ordenar por:</b></label>
      <select id="sort" name="sort">
        <option value="date" <?= (($sort) === 'date') ? 'selected' : '' ?>>Más recientes</option>
        <option value="rfc" <?= (($sort) === 'rfc') ? 'selected' : '' ?>>RFC</option>
        <option value="client" <?= (($sort) === 'client') ? 'selected' : '' ?>>Cliente</option>
      </select>

      <?php foreach ($_GET as $k => $v): if ($k === 'sort') continue; ?>
        <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
      <?php endforeach; ?>

      <button class="btn btn-soft" type="submit">Aplicar</button>

      <?php if (!$all): ?>
        <a class="btn btn-soft" href="jobs.php?all=1&sort=<?= h($sort) ?>">Mostrar más</a>
      <?php else: ?>
        <a class="btn btn-soft" href="jobs.php?sort=<?= h($sort) ?>">Mostrar menos</a>
      <?php endif; ?>
    </form>

    <?php if (!$jobs): ?>
      <div class="alert">No hay solicitudes guardadas aún.</div>
    <?php else: ?>
      <div style="overflow:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>RequestId</th>
              <th>RFC</th>
              <th>Cliente</th>
              <th>Tipo</th>
              <th>Periodo</th>
              <th>Último estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $j): ?>
              <?php
                $rid = (string)($j['_requestId'] ?? '');
                $rfc = (string)($j['rfc'] ?? '');
                $client = (string)($j['_clientName'] ?? '');
                $tipo = ((string)($j['downloadType'] ?? 'issued') === 'received' ? 'Recibidas' : 'Emitidas')
                  . ' / ' . ((string)($j['requestType'] ?? 'xml') === 'metadata' ? 'Metadata' : 'XML')
                  . ' / ' . ((string)($j['status'] ?? 'undefined'));
                $periodo = (string)($j['start'] ?? '') . " → " . (string)($j['end'] ?? '');
                $lastStatus = (string)($j['lastStatus'] ?? '—');
                $lastCheckedAt = (string)($j['lastCheckedAt'] ?? '—');
                $pk = (string)($j['lastPackagesCount'] ?? '—');
              ?>
              <tr>
                <td><code><?= h($rid) ?></code></td>
                <td><?= h($rfc) ?></td>
                <td><?= h($client) ?></td>
                <td><?= h($tipo) ?></td>
                <td class="muted"><?= h($periodo) ?></td>
                <td>
                  <div><b><?= h($lastStatus) ?></b> <span class="muted">(pk: <?= h($pk) ?>)</span></div>
                  <div class="muted"><?= h($lastCheckedAt) ?></div>
                </td>
                <td>
                  <div class="row">
                    <form method="post" action="run.php" style="margin:0">
                      <input type="hidden" name="action" value="check">
                      <input type="hidden" name="requestId" value="<?= h($rid) ?>">
                      <button class="btn btn-primary" type="submit">Verificar / Descargar</button>
                    </form>

                    <form method="post" action="run.php" style="margin:0">
                      <input type="hidden" name="action" value="recreate">
                      <input type="hidden" name="oldRequestId" value="<?= h($rid) ?>">
                      <label class="muted">+seg</label>
                      <input class="input-small" type="number" name="offsetSeconds" value="1" min="-3600" max="3600">
                      <button class="btn btn-soft" type="submit">Reintentar</button>
                    </form>
                  </div>
                  <div class="muted" style="margin-top:6px">
                    * “Reintentar” crea una NUEVA solicitud moviendo el inicio por segundos.
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="muted" style="margin-top:12px">
      Tip: Tus descargas se guardan por año/mes: <code>downloads/RFC/Emitidas/2023/2023-01/uuid.xml</code>
    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</div>

</body>
</html>
