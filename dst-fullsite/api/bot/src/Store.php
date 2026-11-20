<?php
/** File-based JSON storage outside the web root, with locking and atomic writes. */
final class Store
{
    private static $cfg = null;
    public static function resetConfig() { self::$cfg = null; }

    public static function dir()
    {
        $d = getenv('DST_BOT_DATA');
        if (!$d && is_file(__DIR__ . '/../data_path.php')) { $d = include __DIR__ . '/../data_path.php'; } // optional override: <?php return '/home/USER/dst-bot-data';
        if (!$d) { $d = dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/dst-bot-data'; }
        return rtrim((string) $d, '/');
    }

    /** True when the data directory would be reachable from the web (it must never be). */
    public static function insideWebRoot()
    {
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $dir = realpath(self::dir());
        return $root && $dir && strpos($dir . '/', rtrim($root, '/') . '/') === 0;
    }

    public static function config()
    {
        if (self::$cfg === null) {
            $file = self::dir() . '/config.php';
            $loaded = is_file($file) ? include $file : [];
            self::$cfg = array_replace_recursive([
                'bot_key_sha256' => '',
                'data_source' => 'sample',
                'rate_limit_per_phone' => 30,
                'rate_limit_per_ip' => 120,
                'rate_window_seconds' => 600,
                'thresholds' => ['low_level_pct' => 20, 'critical_level_pct' => 10, 'temp_high_c' => 40, 'temp_low_c' => -5],
            ], is_array($loaded) ? $loaded : []);
        }
        return self::$cfg;
    }

    public static function path($name) { return self::dir() . '/' . ltrim($name, '/'); }

    public static function read($name, $default = [])
    {
        $f = self::path($name);
        if (!is_file($f)) { return $default; }
        $fh = fopen($f, 'rb');
        if (!$fh) { return $default; }
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : $default;
    }

    public static function write($name, $data)
    {
        $f = self::path($name);
        if (!is_dir(dirname($f))) { mkdir(dirname($f), 0750, true); }
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        chmod($tmp, 0640);
        rename($tmp, $f);
    }

    /** Sliding-window counter. Returns true when the call is allowed. */
    public static function allow($bucket, $limit, $windowSeconds, $now = null)
    {
        $now = $now ?: time();
        $f = self::path('ratelimit/' . sha1($bucket) . '.json');
        if (!is_dir(dirname($f))) { mkdir(dirname($f), 0750, true); }
        $fh = fopen($f, 'c+');
        if (!$fh) { return true; }
        flock($fh, LOCK_EX);
        $hits = json_decode((string) stream_get_contents($fh), true);
        $hits = is_array($hits) ? array_values(array_filter($hits, function ($t) use ($now, $windowSeconds) { return $t > $now - $windowSeconds; })) : [];
        $ok = count($hits) < $limit;
        if ($ok) { $hits[] = $now; }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($hits));
        flock($fh, LOCK_UN);
        fclose($fh);
        return $ok;
    }
}
