# CFDI Downloader (SAT Descarga Masiva) — Local

Herramienta web **local** (corre en tu PC) para solicitar, verificar y descargar CFDI usando el servicio de **Descarga Masiva** del SAT mediante la librería `phpcfdi/sat-ws-descarga-masiva`.

> ⚠️ Seguridad: **NO subas** e.firmas ni contraseñas a GitHub. Este repo ignora `clients/`, `downloads/`, `storage/` y `vendor/`.

---

## Requisitos
- Windows
- PHP 8.3+ (ej. Laragon)
- Composer
- Extensión PHP: `zip` habilitada

Verifica:
```bash
php -v
composer -V
php -m | findstr zip
