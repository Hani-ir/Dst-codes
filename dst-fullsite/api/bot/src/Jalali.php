<?php
/** Jalali (Persian) calendar. Port of the public-domain jalaali-js algorithm; verified against Python jdatetime in tests. PHP >= 7.4. */
final class Jalali
{
    const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه']; // Saturday first

    private static function div($a, $b) { return intdiv($a, $b); }

    private static function cal($jy)
    {
        $bl = count(self::BREAKS);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = self::BREAKS[0];
        $jump = 0;
        if ($jy < $jp || $jy >= self::BREAKS[$bl - 1]) {
            throw new InvalidArgumentException('Invalid Jalali year');
        }
        for ($i = 1; $i < $bl; $i++) {
            $jm = self::BREAKS[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) { break; }
            $leapJ += self::div($jump, 33) * 8 + self::div($jump % 33, 4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leapJ += self::div($n, 33) * 8 + self::div($n % 33 + 3, 4);
        if ($jump % 33 === 4 && $jump - $n === 4) { $leapJ += 1; }
        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;
        if ($jump - $n < 6) { $n = $n - $jump + self::div($jump + 4, 33) * 33; }
        $leap = (($n + 1) % 33 - 1) % 4;
        if ($leap === -1) { $leap = 4; }
        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function g2d($gy, $gm, $gd)
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4) + self::div(153 * (($gm + 9) % 12) + 2, 5) + $gd - 34840408;
        return $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;
    }

    private static function d2g($jdn)
    {
        $j = 4 * $jdn + 139361631;
        $j += self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div($j % 1461, 4) * 5 + 308;
        $gd = self::div($i % 153, 5) + 1;
        $gm = (self::div($i, 153) % 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);
        return [$gy, $gm, $gd];
    }

    /** Gregorian [y,m,d] -> Jalali [y,m,d] */
    public static function fromGregorian($gy, $gm, $gd)
    {
        $jdn = self::g2d($gy, $gm, $gd);
        $jy = self::d2g($jdn)[0] - 621;
        $r = self::cal($jy);
        $jdn1f = self::g2d($r['gy'], 3, $r['march']);
        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) { return [$jy, 1 + self::div($k, 31), $k % 31 + 1]; }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) { $k += 1; }
        }
        return [$jy, 7 + self::div($k, 30), $k % 30 + 1];
    }

    /** Jalali [y,m,d] -> Gregorian [y,m,d] */
    public static function toGregorian($jy, $jm, $jd)
    {
        $r = self::cal($jy);
        $jdn = self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
        return self::d2g($jdn);
    }

    /** 'Y-m-d' -> [jy,jm,jd] */
    public static function fromYmd($ymd)
    {
        list($y, $m, $d) = array_map('intval', explode('-', $ymd));
        return self::fromGregorian($y, $m, $d);
    }

    public static function toYmd($jy, $jm, $jd)
    {
        list($y, $m, $d) = self::toGregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** Index in the Iranian week (0 = Saturday ... 6 = Friday) for a 'Y-m-d'. */
    public static function weekdayIndex($ymd)
    {
        $w = (int) gmdate('w', strtotime($ymd . ' 12:00:00 UTC')); // 0 = Sunday
        return ($w + 1) % 7;
    }

    public static function addDays($ymd, $days)
    {
        return gmdate('Y-m-d', strtotime($ymd . ' 12:00:00 UTC') + $days * 86400);
    }

    public static function weekStart($ymd) { return self::addDays($ymd, -self::weekdayIndex($ymd)); }

    public static function monthStart($ymd)
    {
        list($jy, $jm) = self::fromYmd($ymd);
        return self::toYmd($jy, $jm, 1);
    }

    public static function fa($value)
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** "29 آبان 1405" in Persian digits */
    public static function short($ymd)
    {
        list($jy, $jm, $jd) = self::fromYmd($ymd);
        return self::fa($jd) . ' ' . self::MONTHS[$jm - 1] . ' ' . self::fa($jy);
    }

    /** "جمعه ۲۹ آبان ۱۴۰۵" */
    public static function long($ymd) { return self::WEEKDAYS[self::weekdayIndex($ymd)] . ' ' . self::short($ymd); }

    /** Today's date in Iran as 'Y-m-d' (Asia/Tehran), or the override used by tests. */
    public static function todayYmd($now = null)
    {
        $dt = $now ? new DateTime('@' . $now) : new DateTime('now');
        $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
        return $dt->format('Y-m-d');
    }
}
