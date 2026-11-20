<?php
// php make-data.php <data-dir> <secrets-dir>
// Creates the OUTSIDE-web-root data directory (config.php, admin.json, users.json) with fresh random secrets.
// The bot key and admin password are written ONLY to <secrets-dir> (keep it out of git); nothing secret is printed.
if (PHP_SAPI !== 'cli') { exit(1); }
list(, $data, $sec) = $argv + [null, null, null];
if (!$data || !$sec) { fwrite(STDERR, "usage: php make-data.php <data-dir> <secrets-dir>\n"); exit(2); }
foreach ([$data, $sec] as $d) { if (!is_dir($d)) { mkdir($d, 0700, true); } }
if (file_exists($data . '/config.php')) { fwrite(STDERR, "config.php already exists; refusing to overwrite.\n"); exit(3); }
$key = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$adminPass = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
file_put_contents($data . '/config.php', "<?php\nreturn [\n    'bot_key_sha256' => '" . hash('sha256', $key) . "',\n    'data_source' => 'sample', // later: 'files'\n    'rate_limit_per_phone' => 30,\n    'rate_limit_per_ip' => 120,\n    'rate_window_seconds' => 600,\n];\n");
file_put_contents($data . '/admin.json', json_encode(['username' => 'admin', 'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT)], JSON_PRETTY_PRINT));
// Two obviously fake TEST users so the live API can be curl-tested before real numbers are entered. Delete them in the admin page.
file_put_contents($data . '/users.json', json_encode([
    ['id' => 'test-ceo', 'name' => 'آزمایشی — مدیرعامل (حذف شود)', 'phone' => '989900000001', 'role' => 'ceo', 'station_id' => null, 'daily_report' => false, 'active' => true],
    ['id' => 'test-mgr', 'name' => 'آزمایشی — مدیر جایگاه (حذف شود)', 'phone' => '989900000002', 'role' => 'station_manager', 'station_id' => 'isfahan-sepehr', 'daily_report' => false, 'active' => true],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach (['config.php', 'admin.json', 'users.json'] as $f) { chmod($data . '/' . $f, 0640); }
file_put_contents($sec . '/BOT_KEY.txt', $key . "\n");
file_put_contents($sec . '/ADMIN_PASSWORD.txt', "username: admin\npassword: " . $adminPass . "\n");
chmod($sec . '/BOT_KEY.txt', 0600); chmod($sec . '/ADMIN_PASSWORD.txt', 0600);
echo "created $data (config.php, admin.json, users.json); secrets saved in $sec\n";
