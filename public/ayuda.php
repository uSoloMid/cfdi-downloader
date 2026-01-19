<?php
  declare(strict_types=1);
  $active = 'ayuda';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ayuda</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php require __DIR__ . '/partials/header.php'; ?>

<div class="container">
  <div class="card" style="padding:18px;">
    <h2 style="margin-top:0">Ayuda rápida</h2>

    <div class="alert alert-warn">
      <b>Recuerda:</b> esta app está pensada para usarse <b>solo en local</b>. No la publiques en internet.
    </div>

    <h3>Flujo recomendado</h3>
    <ol>
      <li><b>Configuración</b>: agrega el cliente (e.firma .cer + .key + contraseña).</li>
      <li><b>Solicitud</b>: crea la solicitud al SAT (periodo / emitidas o recibidas / XML o metadata).</li>
      <li><b>Historial</b>: verifica y descarga desde el RequestId.</li>
    </ol>

    <h3 id="soporte">Errores comunes</h3>
    <ul>
      <li><b>404 assets</b>: asegúrate de arrancar el server con <code>php -S localhost:8081 -t public</code>.</li>
      <li><b>autoload.php</b>: ejecuta <code>composer install</code> para generar la carpeta <code>vendor/</code>.</li>
      <li><b>Certificado inválido</b>: debe ser e.firma/FIEL, no CSD. Revisa vigencia.</li>
    </ul>

    <h3 id="politica">Descargas</h3>
    <p class="muted">Los XML se guardan por año/mes para que sea fácil navegar:</p>
    <p><code>downloads/RFC/Emitidas/2023/2023-01/uuid.xml</code></p>

    <div class="form-actions">
      <a class="btn btn-primary" href="index.php">Volver al inicio</a>
      <a class="btn btn-soft" href="jobs.php">Ir al historial</a>
    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</div>

</body>
</html>
