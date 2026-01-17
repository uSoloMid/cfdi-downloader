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
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Descarga Masiva CFDI (local)</title>
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
          <div class="muted" style="font-size:12px; margin-top:2px">Local • Seguro • Sin nube</div>
        </div>
      </div>

      <nav class="nav">
        <a href="#inicio">Inicio</a>
        <a href="#agregar-cliente">Configuración</a>
        <a href="#ayuda">Ayuda</a>
      </nav>

      <div class="header-actions">
        <a class="btn" href="jobs.php">Historial</a>
      </div>
    </div>
  </header>

  <main class="container" id="inicio">
    <section class="hero card">
      <div>
        <h1>Descarga Masiva de CFDI</h1>
        <ul>
          <li>Descarga rápida y masiva de CFDI desde tu PC</li>
          <li>Seguridad y privacidad garantizada (no se publica en internet)</li>
          <li>Gestiona clientes y solicitudes fácilmente</li>
        </ul>

        <div class="hero-cta">
          <a class="btn btn-primary" href="#pasos">Iniciar proceso</a>
          <a class="btn btn-ghost" href="#ayuda">Más información</a>
        </div>

        <div class="alert alert-warn" style="margin-top:16px">
          <b>Importante:</b> Esto corre local. <b>No lo publiques</b> en internet.
        </div>
      </div>

      <div class="hero-ill" aria-hidden="true">
        CFDI.zip
      </div>
    </section>

    <h2 id="pasos" class="section-title">Descarga tus CFDI en 3 simples pasos:</h2>

    <section class="steps">
      <div class="step card">
        <div class="step-top">
          <div class="pill">1</div><b>Agrega Cliente</b>
        </div>
        <p>Sube la e.firma y guarda los datos del cliente.</p>
        <a class="btn btn-primary" href="#agregar-cliente">Agregar cliente</a>
      </div>

      <div class="step card">
        <div class="step-top">
          <div class="pill">2</div><b>Crea Solicitud</b>
        </div>
        <p>Configura los criterios y envía la solicitud al SAT.</p>
        <a class="btn" href="#crear-solicitud">Crear solicitud</a>
      </div>

      <div class="step card">
        <div class="step-top">
          <div class="pill">3</div><b>Verifica / Descarga</b>
        </div>
        <p>Verifica el estado y descarga los CFDI solicitados.</p>
        <a class="btn" href="#verificar">Verificar / descargar</a>
      </div>
    </section>

    <section class="card" id="agregar-cliente" style="margin-top:18px">
      <div class="section-head">
        <div>
          <h2 style="margin:0">Agregar cliente</h2>
          <div class="muted">Sube la e.firma (.cer + .key). El RFC y nombre se detectan desde el certificado.</div>
        </div>
        <div class="badge">Paso 1</div>
      </div>

      <form method="post" action="run.php" enctype="multipart/form-data" class="form">
        <input type="hidden" name="action" value="addClient">

        <div class="grid2">
          <div>
            <label>RFC (carpeta)</label>
            <input name="newRfc" required placeholder="AAA010101AAA" style="text-transform:uppercase">
          </div>

          <div>
            <label>Nombre del cliente</label>
            <input name="clientName" placeholder="Ej. Ferretería La Paz">
          </div>
        </div>

        <div class="grid2">
          <div>
            <label>Certificado (.cer)</label>
            <input name="cerFile" type="file" accept=".cer" required>
          </div>

          <div>
            <label>Llave privada (.key)</label>
            <input name="keyFile" type="file" accept=".key" required>
          </div>
        </div>

        <div class="grid2">
          <div>
            <label>Contraseña e.firma</label>
            <div style="display:flex; gap:10px; align-items:center">
              <input id="pw" name="password" type="password" required placeholder="Contraseña" style="flex:1">
              <button class="btn btn-ghost" type="button" id="togglePw">Ver</button>
            </div>
            <div class="muted" style="margin-top:8px">Se guardará localmente en <code>clients\RFC\password.txt</code></div>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Guardar cliente</button>
        </div>
      </form>
    </section>

    <section class="card" id="crear-solicitud" style="margin-top:18px">
      <div class="section-head">
        <div>
          <h2 style="margin:0">Crear solicitud (SAT)</h2>
          <div class="muted">Define rango, tipo, estado y envía la solicitud al SAT.</div>
        </div>
        <div class="badge">Paso 2</div>
      </div>

      <?php if (empty($rfcs)): ?>
        <div class="alert alert-warn">
          <b>No encontré RFCs</b> en <code>clients\RFC\</code>.
          Primero agrega un cliente arriba.
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

        <div class="grid2">
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
          <a class="btn" href="jobs.php">Ver historial</a>
        </div>
      </form>
    </section>

    <section class="card" id="verificar" style="margin-top:18px">
      <div class="section-head">
        <div>
          <h2 style="margin:0">Verificar / Descargar</h2>
          <div class="muted">Pega el RequestId y descarga los paquetes.</div>
        </div>
        <div class="badge">Paso 3</div>
      </div>

      <form method="post" action="run.php" class="form">
        <input type="hidden" name="action" value="check">
        <label>RequestId</label>
        <input name="requestId" placeholder="Pega aquí el RequestId que te dio el SAT" required>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Verificar / Descargar</button>
          <a class="btn" href="jobs.php">Ver historial</a>
        </div>
      </form>

      <div class="muted" style="margin-top:10px">
        El SAT puede tardar minutos u horas. Flujo: solicitud → verificación → descarga de paquetes → extracción.
      </div>
    </section>

    <section class="footer" id="ayuda">
      <div>¿Dudas? Revisa el <a href="jobs.php">historial</a> para reintentar descargas sin crear nuevas solicitudes.</div>
      <div>© <?= date('Y') ?> CFDI Downloader</div>
    </section>
  </main>

  <script>
    // Toggle password visibility
    (function(){
      const btn = document.getElementById('togglePw');
      const input = document.getElementById('pw');
      if (!btn || !input) return;
      btn.addEventListener('click', () => {
        input.type = (input.type === 'password') ? 'text' : 'password';
        btn.textContent = (input.type === 'password') ? 'Ver' : 'Ocultar';
      });
    })();

    // Autollenado RFC y nombre desde .cer
    (function(){
      const actionInput = document.querySelector('input[name="action"][value="addClient"]');
      const form = actionInput ? actionInput.closest('form') : null;
      if (!form) return;

      const cerInput = form.querySelector('input[name="cerFile"]');
      const rfcInput = form.querySelector('input[name="newRfc"]');
      const nameInput = form.querySelector('input[name="clientName"]');

      if (rfcInput) {
        rfcInput.addEventListener('input', () => {
          rfcInput.value = (rfcInput.value || '').toUpperCase();
        });
      }

      if (!cerInput) return;
      cerInput.addEventListener('change', async () => {
        if (!cerInput.files || !cerInput.files[0]) return;
        const fd = new FormData();
        fd.append('action', 'probeCer');
        fd.append('cerFile', cerInput.files[0]);

        try {
          const res = await fetch('run.php', { method: 'POST', body: fd });
          const txt = await res.text();
          let data;
          try { data = JSON.parse(txt); } catch (e) { console.log('Respuesta NO JSON:', txt); throw e; }
          if (!data || !data.ok) return;

          if (rfcInput && data.rfc) rfcInput.value = String(data.rfc).toUpperCase();
          if (nameInput && data.name) nameInput.value = String(data.name); // fuerza overwrite
        } catch (e) {
          console.warn(e);
        }
      });
    })();
  </script>
</body>

</html>
