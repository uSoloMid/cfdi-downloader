<?php
declare(strict_types=1);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$jobsDir = dirname(__DIR__) . '/storage/jobs';
$all = isset($_GET['all']) && $_GET['all'] === '1';
$limit = $all ? 5000 : 10;

$jobs = [];
if (is_dir($jobsDir)) {
    foreach (glob($jobsDir . '/*.json') as $file) {
        $data = json_decode((string)file_get_contents($file), true) ?: [];
        $data['_requestId'] = basename($file, '.json');
        $jobs[] = $data;
    }
}

usort($jobs, fn($a,$b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));

$jobs = array_slice($jobs, 0, $limit);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial de solicitudes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Arial;max-width:1100px;margin:24px auto;padding:0 12px}
    .card{border:1px solid #ddd;border-radius:12px;padding:16px;margin:12px 0}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #eee;padding:10px;vertical-align:top}
    th{text-align:left}
    code{background:#f6f6f6;padding:2px 6px;border-radius:8px}
    .muted{color:#666}
    button{padding:10px 12px;border:0;border-radius:10px;cursor:pointer}
    input{padding:10px;border:1px solid #ccc;border-radius:10px;width:110px}
    a{color:#5a2ca0;text-decoration:none}
    a:hover{text-decoration:underline}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  </style>
</head>
<body>
  <p><a href="index.php">&larr; Volver</a></p>
  <h1>Historial de solicitudes</h1>
  <p class="muted">
    Aquí ves tus <b>RequestId</b>. Lo ideal es <b>reintentar Verificar/Descargar</b> antes de crear una nueva solicitud.
    Si de plano necesitas recrear el periodo, usa <b>Reintentar (+segundos)</b>.
  </p>

  <?php if (!$all): ?>
    <p><a href="jobs.php?all=1">Mostrar más</a></p>
  <?php else: ?>
    <p><a href="jobs.php">Mostrar menos</a></p>
  <?php endif; ?>

  <?php if (!$jobs): ?>
    <div class="card">No hay solicitudes guardadas aún.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>RequestId</th>
          <th>RFC</th>
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
                <button type="submit">Verificar / Descargar</button>
              </form>

              <form method="post" action="run.php" style="margin:0">
                <input type="hidden" name="action" value="recreate">
                <input type="hidden" name="oldRequestId" value="<?= h($rid) ?>">
                <label class="muted">+seg</label>
                <input type="number" name="offsetSeconds" value="1" min="-3600" max="3600">
                <button type="submit">Reintentar (+segundos)</button>
              </form>
            </div>
            <div class="muted" style="margin-top:6px">
              * “Reintentar” crea una NUEVA solicitud moviendo el inicio por segundos (para evitar bloqueo por mismo periodo).
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
