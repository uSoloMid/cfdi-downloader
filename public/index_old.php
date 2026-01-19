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
  <p><a href="jobs.php">📌 Historial de solicitudes (pendientes/completadas)</a></p>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
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
  <hr style="margin:18px 0">

  <h2>Agregar cliente (subir e.firma)</h2>
  <form method="post" action="run.php" enctype="multipart/form-data" class="card">
    <input type="hidden" name="action" value="addClient">

    <div class="row">
      <div>
        <label>RFC (carpeta)</label>
        <input name="newRfc" required placeholder="AAA010101AAA" style="text-transform:uppercase">
      </div>

      <div>
        <label>Nombre del cliente (opcional)</label>
        <input name="clientName" placeholder="Ej. Ferretería La Paz">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Certificado (.cer)</label>
        <input name="cerFile" type="file" accept=".cer" required>
      </div>

      <div>
        <label>Llave privada (.key)</label>
        <input name="keyFile" type="file" accept=".key" required>
      </div>
    </div>

    <div class="row">
      <div style="min-width:260px">
        <label>Contraseña e.firma</label>
        <div style="display:flex; gap:10px; align-items:center">
          <input id="pw" name="password" type="password" required placeholder="Contraseña" style="flex:1">
          <button type="button" onclick="
          const i=document.getElementById('pw');
          i.type = (i.type==='password') ? 'text' : 'password';
          this.textContent = (i.type==='password') ? 'Ver' : 'Ocultar';
        ">Ver</button>
        </div>
        <div class="muted" style="margin-top:6px">Se guardará localmente en <code>clients\RFC\password.txt</code></div>
      </div>
    </div>

    <button type="submit">Guardar cliente</button>
  </form>

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
          <label>Mes inicio</label>
          <select name="startMonth" required>
            <option value="01">Enero</option>
            <option value="02">Febrero</option>
            <option value="03">Marzo</option>
            <option value="04">Abril</option>
            <option value="05">Mayo</option>
            <option value="06">Junio</option>
            <option value="07">Julio</option>
            <option value="08">Agosto</option>
            <option value="09">Septiembre</option>
            <option value="10">Octubre</option>
            <option value="11">Noviembre</option>
            <option value="12">Diciembre</option>
          </select>
        </div>

        <div>
          <label>Año inicio</label>
          <input name="startYear" type="number" min="2019" max="2100" value="2025" required>
        </div>
      </div>

      <div class="row">
        <div>
          <label>Mes fin</label>
          <select name="endMonth" required>
            <option value="01">Enero</option>
            <option value="02">Febrero</option>
            <option value="03">Marzo</option>
            <option value="04">Abril</option>
            <option value="05">Mayo</option>
            <option value="06">Junio</option>
            <option value="07">Julio</option>
            <option value="08">Agosto</option>
            <option value="09">Septiembre</option>
            <option value="10">Octubre</option>
            <option value="11">Noviembre</option>
            <option value="12" selected>Diciembre</option>
          </select>
        </div>

        <div>
          <label>Año fin</label>
          <input name="endYear" type="number" min="2019" max="2100" value="2025" required>
        </div>
      </div>

      <p class="muted">
        Se convertirá automáticamente a: <b>01 del mes inicio 00:01</b> → <b>último día del mes fin 23:59</b>.
      </p>


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
  <script>
    // Encuentra el FORM que agrega clientes (el que tiene action=addClient)
    const addClientActionInput = document.querySelector('input[name="action"][value="addClient"]');
    const addClientForm = addClientActionInput ? addClientActionInput.closest('form') : null;

    if (addClientForm) {
      const cerInput = addClientForm.querySelector('input[name="cerFile"]');
      const rfcInput = addClientForm.querySelector('input[name="newRfc"]');
      const nameInput = addClientForm.querySelector('input[name="clientName"]');

      // Mantener RFC en mayúsculas si lo teclean
      if (rfcInput) {
        rfcInput.addEventListener('input', () => {
          rfcInput.value = (rfcInput.value || '').toUpperCase();
        });
      }

      // Al elegir .cer -> pedir RFC/Nombre a run.php y autollenar
      if (cerInput) {
        cerInput.addEventListener('change', async () => {
          if (!cerInput.files || !cerInput.files[0]) return;

          const fd = new FormData();
          fd.append('action', 'probeCer');
          fd.append('cerFile', cerInput.files[0]);

          try {
            const res = await fetch('run.php', {
              method: 'POST',
              body: fd
            });

            const txt = await res.text();
            let data;
            try {
              data = JSON.parse(txt);
            } catch (e) {
              console.log("Respuesta NO JSON:", txt);
              throw e;
            }


            if (!data || !data.ok) return;

            if (rfcInput && data.rfc) rfcInput.value = String(data.rfc).toUpperCase();

            // Solo llena nombre si está vacío, para no pisar lo que teclees
            if (nameInput && data.name) {
              nameInput.value = String(data.name);
            }

          } catch (e) {
            // Si falla, no pasa nada: el usuario puede teclear manualmente
            console.warn(e);
          }
        });
      }
    }
  </script>

</body>

</html>