<?php
  declare(strict_types=1);
  $active = 'cliente';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agregar cliente</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php require __DIR__ . '/partials/header.php'; ?>

<div class="container">
  <div class="card" style="padding:18px;">
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
          <input name="newRfc" id="newRfc" required placeholder="AAA010101AAA" style="text-transform:uppercase" autocomplete="off">
        </div>

        <div>
          <label>Nombre del cliente</label>
          <input name="clientName" id="clientName" placeholder="Ej. Ferretería La Paz" autocomplete="on">
        </div>
      </div>

      <div class="grid2">
        <div>
          <label>Certificado (.cer)</label>
          <input name="cerFile" id="cerFile" type="file" accept=".cer" required>
        </div>

        <div>
          <label>Llave privada (.key)</label>
          <input name="keyFile" type="file" accept=".key" required>
        </div>
      </div>

      <div class="grid2">
        <div>
          <label>Contraseña e.firma</label>
          <div class="pw-row">
            <input id="pw" name="password" type="password" required placeholder="Contraseña" autocomplete="current-password">
            <button class="btn btn-soft" type="button" id="togglePw">Ver</button>
          </div>
          <div class="muted" style="margin-top:8px">Se guardará localmente en <code>clients\RFC\password.txt</code></div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Guardar cliente</button>
        <a class="btn btn-soft" href="index.php">Volver</a>
      </div>

      <div id="probeMsg" class="muted" style="margin-top:10px"></div>
    </form>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</div>

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

  // Auto-llenar RFC y Nombre desde .cer
  (function(){
    const cerInput = document.getElementById('cerFile');
    const rfcInput = document.getElementById('newRfc');
    const nameInput = document.getElementById('clientName');
    const msg = document.getElementById('probeMsg');

    if (rfcInput) {
      rfcInput.addEventListener('input', () => {
        rfcInput.value = (rfcInput.value || '').toUpperCase();
      });
    }

    if (!cerInput) return;
    cerInput.addEventListener('change', async () => {
      if (!cerInput.files || !cerInput.files[0]) return;

      if (msg) msg.textContent = 'Leyendo certificado…';

      const fd = new FormData();
      fd.append('action', 'probeCer');
      fd.append('cerFile', cerInput.files[0]);

      try {
        const res = await fetch('run.php', { method: 'POST', body: fd });
        const txt = await res.text();
        let data;
        try { data = JSON.parse(txt); } catch (e) {
          console.log('Respuesta NO JSON:', txt);
          throw e;
        }

        if (!data || !data.ok) {
          if (msg) msg.textContent = (data && data.error) ? data.error : 'No se pudo leer el certificado.';
          return;
        }

        if (rfcInput && data.rfc) rfcInput.value = String(data.rfc).toUpperCase();
        if (nameInput && data.name) nameInput.value = String(data.name); // fuerza overwrite

        if (msg) msg.textContent = 'RFC y nombre detectados ✅';
      } catch (e) {
        if (msg) msg.textContent = 'No se pudo leer el certificado (ver consola).';
        console.warn(e);
      }
    });
  })();
</script>

</body>
</html>
