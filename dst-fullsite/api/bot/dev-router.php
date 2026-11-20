<?php
// Development only (php -S ... dev-router.php): behaves like the .htaccess front controller.
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($p, '/api/bot/admin') === 0) { return false; }
if (strpos($p, '/api/bot/') === 0 && substr($p, -4) !== '.php') { require __DIR__ . '/index.php'; return true; }
return false;
