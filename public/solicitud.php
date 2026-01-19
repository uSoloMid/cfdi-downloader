<?php
declare(strict_types=1);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function listRfcs(string $clientsDir): array
{
  if (!is_dir($clientsDir)) return [];
  $items = array_filter(scandir($clientsDir) ?: [], fn($x) => $x !== '.' && $x !== '..');
  $rfcs = [];
  foreach ($items as $it) {
    if (is_dir($clientsDir . DIRECTORY_SEPARATOR . $it)) $rfcs[] = $it;
  }
  sort($rfcs);
  return $rfcs;
}

$clientsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clients';
$rfcs = listRfcs($clientsDir);
$active = 'solicitud';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear solicitud (SAT)</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php require __DIR__ . '/partials/header.php'; ?>

<div class="container">
  <div class="card" style="padding:18px;">
    <div class="section-head">
      <div>
        <h2 style="margin:0">Crear solicitud (SAT)</h2>
        <div class="muted">Define rango, tipo, estado y envía la solicitud al SAT.</div>
      </div>
      <div class="badge">Paso 2</div>
    </div>

    <?php if (empty($rfcs)): ?>
      <div class="alert alert-warn">
        <b>No encontré RFCs</b> en <code>clients\RFC\</code>. Primero agrega un cliente.
      </div>
    <?php endif; ?>

    <form method="post" action="run.php" class="form">
      <input type="hidden" name="action" value="create">

      <label>RFC</label>
      <select name="rfc" required>
        <?php foreach ($rfcs as $rfc): ?>
          <option value="<?= h($rfc) ?>"><?= h($rfc) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="grid2">
        <div>
          <label>Mes inicio</label>
          <select name="startMonth" required>
            <option value="01">Enero</option><option value="02">Febrero</option><option value="03">Marzo</option>
            <option value="04">Abril</option><option value="05">Mayo</option><option value="06">Junio</option>
            <option value="07">Julio</option><option value="08">Agosto</option><option value="09">Septiembre</option>
            <option value="10">Octubre</option><option value="11">Noviembre</option><option value="12">Diciembre</option>
          </select>
        </div>
        <div>
          <label>Año inicio</label>
          <input name="startYear" type="number" min="2019" max="2100" value="2025" required>
        </div>
      </div>

      <div class="grid2">
        <div>
          <label>Mes fin</label>
          <select name="endMonth" required>
            <option value="01">Enero</option><option value="02">Febrero</option><option value="03">Marzo</option>
            <option value="04">Abril</option><option value="05">Mayo</option><option value="06">Junio</option>
            <option value="07">Julio</option><option value="08">Agosto</option><option value="09">Septiembre</option>
            <option value="10">Octubre</option><option value="11">Noviembre</option><option value="12" selected>Diciembre</option>
          </select>
        </div>
        <div>
          <label>Año fin</label>
          <input name="endYear" type="number" min="2019" max="2100" value="2025" required>
        </div>
      </div>

      <div class="muted" style="margin:10px 0 0">
        Se convertirá automáticamente a: <b>01 del mes inicio 00:01</b> → <b>último día del mes fin 23:59</b>.
      </div>

      <div class="grid2">
        <div>
          <label>Emitidas / Recibidas</label>
          <select name="downloadType" required>
            <option value="issued">Emitidas</option>
            <option value="received">Recibidas</option>
          </select>
        </div>
        <div>
          <label>Qué quieres</label>
          <select name="requestType" required>
            <option value="xml">XML</option>
            <option value="metadata">Metadata (CSV)</option>
          </select>
        </div>
      </div>

      <label>Estado</label>
      <select name="status">
        <option value="undefined">Todos (sin filtro)</option>
        <option value="active">Vigentes</option>
        <option value="cancelled">Cancelados</option>
      </select>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Crear solicitud</button>
        <a class="btn btn-soft" href="jobs.php">Ver historial</a>
        <a class="btn btn-soft" href="index.php">Volver</a>
      </div>
    </form>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</div>

</body>
</html>
