<?php

declare(strict_types=1);

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
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Descarga Masiva CFDI (local)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: system-ui, Segoe UI, Arial;
      max-width: 900px;
      margin: 24px auto;
      padding: 0 12px
    }

    .card {
      border: 1px solid #ddd;
      border-radius: 12px;
      padding: 16px;
      margin: 12px 0
    }

    label {
      display: block;
      margin-top: 10px;
      font-weight: 600
    }

    input,
    select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 10px;
      margin-top: 6px
    }

    button {
      margin-top: 14px;
      padding: 12px 14px;
      border: 0;
      border-radius: 10px;
      cursor: pointer
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .muted {
      color: #666
    }

    code {
      background: #f6f6f6;
      padding: 2px 6px;
      border-radius: 8px
    }
  </style>
</head>

<body>
  <h1>Descarga Masiva CFDI (en tu PC)</h1>
  <p class="muted">Esto corre local. No lo publiques en internet.</p>

  <div class="card">
    <h2>1) Crear solicitud (SAT)</h2>

    <?php if (empty($rfcs)): ?>
      <p><b>No encontré RFCs</b> en <code>clients\RFC\</code>. Crea una carpeta por RFC y agrega <code>certificado.cer</code>, <code>llave.key</code>, <code>password.txt</code>.</p>
    <?php endif; ?>

    <form method="post" action="run.php">
      <input type="hidden" name="action" value="create">

      <label>RFC</label>
      <select name="rfc" required>
        <?php foreach ($rfcs as $rfc): ?>
          <option value="<?= htmlspecialchars($rfc) ?>"><?= htmlspecialchars($rfc) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="row">
        <div>
          <label>Inicio (fecha)</label>
          <input name="startDate" type="date" required>
        </div>
        <div>
          <label>Inicio (hora)</label>
          <input name="startTime" type="time" required value="00:00">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Fin (fecha)</label>
          <input name="endDate" type="date" required>
        </div>
        <div>
          <label>Fin (hora)</label>
          <input name="endTime" type="time" required value="23:59">
        </div>
      </div>

      <div class="row">
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

      <button type="submit">Crear solicitud</button>
    </form>
  </div>

  <div class="card">
    <h2>2) Verificar / Descargar</h2>
    <form method="post" action="run.php">
      <input type="hidden" name="action" value="check">
      <label>RequestId</label>
      <input name="requestId" placeholder="Pega aquí el RequestId que te dio el SAT" required>
      <button type="submit">Verificar / Descargar</button>
    </form>
    <p class="muted">
      El SAT puede tardar minutos u horas. Esto funciona en 4 pasos: solicitud → verificación → descarga de paquetes → extraer.
    </p>
  </div>
</body>

</html>