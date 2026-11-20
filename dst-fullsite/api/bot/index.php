<?php
// DST bot API front controller. Secrets and data live OUTSIDE the web root (see Store::dir()).
declare(strict_types=0);
foreach (['Jalali', 'Phone', 'Store', 'DataSource', 'Reports', 'Users', 'Api'] as $m) { require __DIR__ . '/src/' . $m . '.php'; }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function dst_header_key()
{
    if (!empty($_SERVER['HTTP_X_BOT_KEY'])) { return $_SERVER['HTTP_X_BOT_KEY']; }
    if (function_exists('getallheaders')) { foreach (getallheaders() as $k => $v) { if (strtolower($k) === 'x-bot-key') { return $v; } } }
    return '';
}

if (Store::insideWebRoot()) { // refuse to run with secrets/data under the web root
    http_response_code(500);
    echo json_encode(['ok' => false, 'code' => 'misconfigured', 'text' => 'پوشه‌ی داده باید بیرون از public_html باشد.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$route = preg_match('#/api/bot/(.+?)/?$#', $path, $m) ? $m[1] : '';
$raw = file_get_contents('php://input', false, null, 0, 20000);
$input = [];
if ($raw !== '' && $raw !== false) {
    $j = json_decode($raw, true);
    $input = is_array($j) ? $j : $_POST;
} else {
    $input = $_POST;
}
list($status, $body) = Api::handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $route, dst_header_key(), $input, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', getenv('DST_BOT_NOW') ?: null);
http_response_code($status);
echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
