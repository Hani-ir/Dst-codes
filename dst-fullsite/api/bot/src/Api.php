<?php
/** The bot API: request handling kept separate from I/O so it can be tested without a web server. */
final class Api
{
    const PERIODS = ['today' => 'فروش امروز', 'yesterday' => 'فروش دیروز', 'week' => 'این هفته', 'month' => 'این ماه'];
    const MSG_UNKNOWN = 'شماره‌ی شما در سامانه ثبت نشده است. برای دریافت دسترسی با مدیریت DST تماس بگیرید.';
    const MSG_FORBIDDEN = 'شما به این گزارش دسترسی ندارید.';
    const MSG_RATE = 'تعداد درخواست‌ها زیاد است؛ چند دقیقه دیگر دوباره تلاش کنید.';

    /** @return array [httpStatus, body] */
    public static function handle($method, $route, $headerKey, $input, $ip, $now = null)
    {
        $cfg = Store::config();
        $window = (int) $cfg['rate_window_seconds'];
        if ($method !== 'POST') { return [405, ['ok' => false, 'code' => 'method_not_allowed', 'text' => 'فقط درخواست POST پذیرفته می‌شود.']]; }
        $hash = (string) $cfg['bot_key_sha256'];
        if ($hash === '' || !hash_equals($hash, hash('sha256', (string) $headerKey))) {
            if (!Store::allow('badkey|' . $ip, 20, $window)) { return [429, ['ok' => false, 'code' => 'too_many_attempts']]; }
            return [401, ['ok' => false, 'code' => 'unauthorized']];
        }
        if (!Store::allow('ip|' . $ip, (int) $cfg['rate_limit_per_ip'], $window)) {
            return [429, ['ok' => false, 'code' => 'rate_limited', 'text' => self::MSG_RATE]];
        }
        $today = Jalali::todayYmd($now);
        if ($route === 'report/daily-recipients') {
            $out = [];
            foreach (Users::all() as $u) {
                if (!empty($u['active']) && !empty($u['daily_report']) && Users::seesAllStations($u)) {
                    $out[] = ['name' => $u['name'], 'phone' => Phone::local($u['phone']), 'phone_international' => $u['phone']];
                }
            }
            AccessLog::add(null, $route, 'ok');
            return [200, ['ok' => true, 'recipients' => $out, 'count' => count($out)]];
        }
        if (!in_array($route, ['whoami', 'report/ceo-daily', 'report/station-sales'], true)) {
            return [404, ['ok' => false, 'code' => 'not_found']];
        }
        $phone = Phone::normalize($input['phone'] ?? '');
        if ($phone === null) { return [200, ['ok' => false, 'code' => 'bad_phone', 'text' => 'شماره‌ی تلفن ارسالی معتبر نیست.']]; }
        if (!Store::allow('phone|' . $phone, (int) $cfg['rate_limit_per_phone'], $window)) {
            AccessLog::add(null, $route, 'rate_limited');
            return [200, ['ok' => false, 'code' => 'rate_limited', 'text' => self::MSG_RATE]];
        }
        $user = Users::findByPhone($phone);
        if (!$user) { AccessLog::add(null, $route, 'unknown_phone'); return [200, ['ok' => false, 'code' => 'unknown_phone', 'text' => self::MSG_UNKNOWN]]; }
        $ds = dst_data_source();
        $stations = [];
        foreach ($ds->stations() as $s) { $stations[$s['id']] = $s; }
        $base = ['date_jalali' => Jalali::long($today), 'sample' => $ds->isSample()];

        if ($route === 'whoami') {
            AccessLog::add($user, $route, 'ok');
            $st = $user['station_id'] && isset($stations[$user['station_id']]) ? $stations[$user['station_id']] : null;
            $text = 'سلام ' . $user['name'] . ' 👋' . "\n" . 'نقش شما: ' . Users::ROLES[$user['role']] . ($st ? "\n" . 'جایگاه: ' . $st['name'] : '');
            return [200, $base + ['ok' => true, 'text' => $text, 'user' => ['name' => $user['name'], 'role' => $user['role'], 'role_label' => Users::ROLES[$user['role']],
                'station' => $st ? ['id' => $st['id'], 'name' => $st['name']] : null, 'daily_report' => !empty($user['daily_report'])], 'buttons' => self::menu($user, $stations)]];
        }

        if ($route === 'report/ceo-daily') {
            if (!Users::seesAllStations($user)) { AccessLog::add($user, $route, 'forbidden'); return [200, $base + ['ok' => false, 'code' => 'forbidden', 'text' => self::MSG_FORBIDDEN]]; }
            AccessLog::add($user, $route, 'ok');
            return [200, $base + ['ok' => true, 'text' => Reports::ceoDaily($ds, $today)]];
        }

        // report/station-sales
        $period = self::period($input['period'] ?? 'today');
        if (Users::seesAllStations($user)) {
            $sid = (string) ($input['station_id'] ?? '');
            if (!isset($stations[$sid])) {
                AccessLog::add($user, $route, 'choose_station');
                $b = [];
                foreach ($stations as $s) { $b[] = ['text' => $s['name'], 'endpoint' => 'report/station-sales', 'period' => $period ?: 'today', 'station_id' => $s['id']]; }
                return [200, $base + ['ok' => true, 'code' => 'choose_station', 'text' => 'کدام جایگاه؟', 'buttons' => $b]];
            }
        } else {
            $sid = (string) $user['station_id']; // a station manager only ever gets their own station; any station_id sent is ignored
            if (!isset($stations[$sid])) { AccessLog::add($user, $route, 'no_station'); return [200, $base + ['ok' => false, 'code' => 'no_station', 'text' => 'برای شما جایگاهی تعریف نشده است؛ با مدیریت DST تماس بگیرید.']]; }
        }
        if ($period === null) {
            AccessLog::add($user, $route, 'bad_period');
            return [200, $base + ['ok' => false, 'code' => 'bad_period', 'text' => 'بازه‌ی زمانی را انتخاب کنید.', 'buttons' => self::periodButtons($sid)]];
        }
        AccessLog::add($user, $route, 'ok');
        return [200, $base + ['ok' => true, 'text' => Reports::stationSales($ds, $stations[$sid], $period, $today), 'buttons' => self::periodButtons($sid)]];
    }

    private static function period($raw)
    {
        $map = ['today' => 'today', 'امروز' => 'today', 'yesterday' => 'yesterday', 'دیروز' => 'yesterday',
                'week' => 'week', 'هفته' => 'week', 'این هفته' => 'week', 'month' => 'month', 'ماه' => 'month', 'این ماه' => 'month'];
        $k = is_string($raw) ? trim($raw) : '';
        return isset($map[$k]) ? $map[$k] : null;
    }

    private static function periodButtons($sid)
    {
        $b = [];
        foreach (self::PERIODS as $p => $label) { $b[] = ['text' => $label, 'endpoint' => 'report/station-sales', 'period' => $p]; }
        return $b;
    }

    private static function menu($user, $stations)
    {
        if (Users::seesAllStations($user)) {
            return [['text' => 'گزارش روزانه', 'endpoint' => 'report/ceo-daily'], ['text' => 'فروش یک جایگاه', 'endpoint' => 'report/station-sales', 'period' => 'today']];
        }
        return self::periodButtons($user['station_id']);
    }
}
