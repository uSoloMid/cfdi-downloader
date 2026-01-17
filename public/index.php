<?php
$active = 'inicio';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Descarga Masiva CFDI</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php require __DIR__ . '/partials/header.php'; ?>

<div class="container">

  <div class="hero card">
    <div class="hero-left">
      <h1>Descarga Masiva de CFDI</h1>

      <ul class="hero-list">
        <li>Descarga rápida y masiva de CFDI desde tu PC</li>
        <li>Seguridad y privacidad garantizada</li>
        <li>Gestiona clientes y solicitudes fácilmente</li>
      </ul>

      <div class="hero-cta">
        <a class="btn btn-primary" href="#pasos">Iniciar Proceso</a>
        <a class="btn btn-link" href="ayuda.php">Más información →</a>
      </div>
    </div>

    <div class="hero-right">
      <<img class="hero-img" src="/assets/hero.png" alt="Ilustración CFDI">
    </div>
  </div>

  <h2 id="pasos" class="section-title">Descarga tus CFDI en 3 simples pasos:</h2>

  <div class="steps">
    <div class="step card">
      <div class="step-head">
        <div class="pill">1</div>
        <div>
          <div class="step-title">Agrega Cliente</div>
          <div class="step-sub">Sube la e.firma y guarda los datos de tu cliente</div>
        </div>
      </div>
      <a class="btn btn-primary" href="cliente.php">Agregar cliente</a>
    </div>

    <div class="step card">
      <div class="step-head">
        <div class="pill">2</div>
        <div>
          <div class="step-title">Crea Solicitud</div>
          <div class="step-sub">Configura los criterios y envía la solicitud al SAT</div>
        </div>
      </div>
      <a class="btn btn-soft" href="solicitud.php">Crear solicitud</a>
    </div>

    <div class="step card">
      <div class="step-head">
        <div class="pill">3</div>
        <div>
          <div class="step-title">Verifica / Descarga</div>
          <div class="step-sub">Verifica el estado y descarga los CFDI solicitados</div>
        </div>
      </div>
      <a class="btn btn-soft" href="jobs.php">Verificar / descargar</a>
    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>

</div>

</body>
</html>
