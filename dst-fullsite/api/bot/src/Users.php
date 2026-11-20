<?php
/** Bot users: a messenger phone number mapped to a role and (for station managers) one station. */
final class Users
{
    const ROLES = ['ceo' => 'مدیرعامل', 'senior' => 'مدیریت ارشد', 'station_manager' => 'مدیر جایگاه'];

    public static function all() { return Store::read('users.json', []); }
    public static function save($users) { Store::write('users.json', array_values($users)); }

    public static function findByPhone($canonical)
    {
        foreach (self::all() as $u) {
            if (!empty($u['active']) && isset($u['phone']) && $u['phone'] === $canonical) { return $u; }
        }
        return null;
    }

    public static function seesAllStations($user) { return in_array($user['role'], ['ceo', 'senior'], true); }

    /** @return array [cleanUser|null, errors[]] */
    public static function validate($in, $existing, $stationIds, $editingId)
    {
        $err = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) { $err[] = 'نام را وارد کنید (حداکثر ۸۰ نویسه).'; }
        $phone = Phone::normalize($in['phone'] ?? '');
        if ($phone === null) { $err[] = 'شماره‌ی موبایل معتبر نیست (مثل ۰۹۱۲۱۲۳۴۵۶۷).'; }
        $role = (string) ($in['role'] ?? '');
        if (!isset(self::ROLES[$role])) { $err[] = 'نقش نامعتبر است.'; }
        $station = $role === 'station_manager' ? (string) ($in['station_id'] ?? '') : null;
        if ($role === 'station_manager' && !in_array($station, $stationIds, true)) { $err[] = 'برای مدیر جایگاه، یک جایگاه معتبر انتخاب کنید.'; }
        foreach ($existing as $u) {
            if ($phone !== null && $u['phone'] === $phone && $u['id'] !== $editingId) { $err[] = 'این شماره قبلاً برای کاربر دیگری ثبت شده است.'; }
        }
        if ($err) { return [null, $err]; }
        return [[
            'id' => $editingId ?: bin2hex(random_bytes(6)),
            'name' => $name, 'phone' => $phone, 'role' => $role, 'station_id' => $station,
            'daily_report' => in_array($role, ['ceo', 'senior'], true) && !empty($in['daily_report']),
            'active' => !empty($in['active']),
        ], []];
    }
}

/** Access log: who (internal id), when, which report, outcome. No phone numbers, no report text. */
final class AccessLog
{
    public static function add($user, $endpoint, $code)
    {
        $f = Store::path('logs/access.log');
        if (!is_dir(dirname($f))) { mkdir(dirname($f), 0750, true); }
        if (is_file($f) && filesize($f) > 1000000) { @rename($f, $f . '.1'); }
        $line = json_encode(['t' => (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s'),
                             'user' => $user ? $user['id'] : null, 'role' => $user ? $user['role'] : null,
                             'endpoint' => $endpoint, 'code' => $code], JSON_UNESCAPED_UNICODE);
        file_put_contents($f, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function tail($n = 50)
    {
        $f = Store::path('logs/access.log');
        if (!is_file($f)) { return []; }
        $rows = array_filter(array_map(function ($l) { return json_decode($l, true); }, array_slice(file($f, FILE_IGNORE_NEW_LINES), -$n)));
        return array_reverse(array_values($rows));
    }
}
