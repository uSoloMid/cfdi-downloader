<?php
  /** @var string $active */
  $active = $active ?? 'inicio'; // inicio|cliente|solicitud|historial|config|ayuda
?>
<header class="header">
  <div class="header-inner">
    <a class="brand" href="index.php" aria-label="Inicio">
      <img class="brand-logo" src="/assets/logo.png" alt="Logo" onerror="this.src='/assets/logo.svg'">
    </a>

    <nav class="nav" aria-label="Navegación">
      <a class="<?= ($active==='inicio') ? 'active' : '' ?>" href="index.php">Inicio</a>
      <a class="<?= ($active==='cliente') ? 'active' : '' ?>" href="cliente.php">Configuración</a>
      <a class="<?= ($active==='ayuda') ? 'active' : '' ?>" href="ayuda.php">Ayuda</a>
    </nav>

    <div class="header-actions">
      <a class="btn btn-soft" href="jobs.php">Historial</a>
      <div class="avatar" title="Local">
        <span>🖥️</span>
      </div>
    </div>
  </div>
</header>
