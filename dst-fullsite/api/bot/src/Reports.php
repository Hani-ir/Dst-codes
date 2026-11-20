<?php
/** Builds the Persian, messenger-ready texts. Plain text only; Persian digits; Jalali dates. */
final class Reports
{
    const SAMPLE_NOTE = '⚠️ داده‌ی نمونه (آزمایشی) — هنوز به داده‌ی واقعی وصل نیست';

    public static function num($n)
    {
        return Jalali::fa(str_replace(',', '٬', number_format((int) round($n), 0, '.', ',')));
    }

    public static function levelText($st, $lvl)
    {
        if (!$lvl) { return 'داده‌ی مخزن دریافت نشده'; }
        $pct = $st['capacity_l'] > 0 ? round($lvl['level_l'] * 100 / $st['capacity_l']) : 0;
        return Jalali::fa((int) $pct) . '٪ (' . self::num($lvl['level_l']) . ' از ' . self::num($st['capacity_l']) . ' لیتر)';
    }

    public static function alarms($st, $lvl, $today)
    {
        $t = Store::config()['thresholds'];
        if (!$lvl) { return ['⚪ داده‌ی مخزن دریافت نشده']; }
        $out = [];
        $pct = $st['capacity_l'] > 0 ? $lvl['level_l'] * 100 / $st['capacity_l'] : 0;
        if ($pct < $t['critical_level_pct']) { $out[] = '🔴 سطح مخزن بحرانی (' . Jalali::fa((int) round($pct)) . '٪)'; }
        elseif ($pct < $t['low_level_pct']) { $out[] = '🟠 سطح مخزن پایین (' . Jalali::fa((int) round($pct)) . '٪)'; }
        if ($lvl['temperature_c'] !== null && $lvl['temperature_c'] > $t['temp_high_c']) { $out[] = '🌡 دمای بالا (' . Jalali::fa((int) round($lvl['temperature_c'])) . ' درجه)'; }
        if ($lvl['temperature_c'] !== null && $lvl['temperature_c'] < $t['temp_low_c']) { $out[] = '🌡 دمای پایین (' . Jalali::fa((int) round($lvl['temperature_c'])) . ' درجه)'; }
        if (!empty($lvl['service_overdue'])) { $out[] = '🔧 سرویس دوره‌ای معوق'; }
        if (!empty($lvl['updated']) && strtotime($lvl['updated']) < time() - 86400) { $out[] = '⏱ داده‌ی مخزن بیش از یک روز قدیمی است'; }
        return $out;
    }

    private static function amount($st, $liters)
    {
        return isset($st['price_toman_per_liter']) && $liters !== null ? ' — ' . self::num($liters * $st['price_toman_per_liter']) . ' تومان' : '';
    }

    public static function ceoDaily($ds, $today)
    {
        $yesterday = Jalali::addDays($today, -1);
        $lines = ['📊 گزارش روزانه DST', Jalali::long($today)];
        if ($ds->isSample()) { $lines[] = self::SAMPLE_NOTE; }
        $totalLevel = $totalCap = $totalSales = 0;
        $alarmCount = 0;
        $anySales = false;
        foreach ($ds->stations() as $st) {
            $lvl = $ds->level($st['id'], $today);
            $sold = $ds->salesLiters($st['id'], $yesterday, $today);
            $al = self::alarms($st, $lvl, $today);
            $alarmCount += count($al);
            $lines[] = '';
            $lines[] = '⛽ ' . $st['name'] . ' — ' . $st['city'];
            $lines[] = 'موجودی: ' . self::levelText($st, $lvl);
            $lines[] = 'فروش دیروز: ' . ($sold === null ? 'ثبت نشده' : self::num($sold) . ' لیتر' . self::amount($st, $sold));
            foreach ($al as $a) { $lines[] = $a; }
            if ($lvl) { $totalLevel += $lvl['level_l']; $totalCap += $st['capacity_l']; }
            if ($sold !== null) { $totalSales += $sold; $anySales = true; }
        }
        $lines[] = '';
        $lines[] = '━━━━━━━━━━';
        $lines[] = 'جمع کل (' . Jalali::fa(count($ds->stations())) . ' جایگاه)';
        $lines[] = 'موجودی: ' . self::num($totalLevel) . ' از ' . self::num($totalCap) . ' لیتر' . ($totalCap > 0 ? ' (' . Jalali::fa((int) round($totalLevel * 100 / $totalCap)) . '٪)' : '');
        $lines[] = 'فروش دیروز: ' . ($anySales ? self::num($totalSales) . ' لیتر' : 'ثبت نشده');
        $lines[] = 'تعداد هشدارها: ' . Jalali::fa($alarmCount);
        return implode("\n", $lines);
    }

    public static function periodRange($period, $today)
    {
        switch ($period) {
            case 'today': return [$today, $today, 'امروز'];
            case 'yesterday': $y = Jalali::addDays($today, -1); return [$y, $y, 'دیروز'];
            case 'week': return [Jalali::weekStart($today), $today, 'این هفته'];
            case 'month': return [Jalali::monthStart($today), $today, 'این ماه'];
        }
        return null;
    }

    public static function stationSales($ds, $st, $period, $today)
    {
        list($from, $to, $label) = self::periodRange($period, $today);
        $lines = ['📍 ' . $st['name'] . ' — ' . $st['city']];
        if ($ds->isSample()) { $lines[] = self::SAMPLE_NOTE; }
        $lines[] = '🗓 ' . $label . ($from === $to ? ' (' . Jalali::long($from) . ')' : ' (' . Jalali::short($from) . ' تا ' . Jalali::short($to) . ')');
        $total = 0; $days = 0; $rows = [];
        for ($d = $from; $d <= $to; $d = Jalali::addDays($d, 1)) {
            $s = $ds->salesLiters($st['id'], $d, $today);
            if ($s !== null) { $total += $s; $days++; }
            $rows[$d] = $s;
        }
        $partial = ($to === $today && $period !== 'yesterday') ? ' (تا این لحظه)' : '';
        if ($days === 0) {
            $lines[] = '⛽ فروشی برای این بازه ثبت نشده است.';
        } else {
            $lines[] = '⛽ فروش' . $partial . ': ' . self::num($total) . ' لیتر' . self::amount($st, $total);
            if ($days > 1) { $lines[] = 'میانگین روزانه: ' . self::num($total / $days) . ' لیتر'; }
        }
        if ($period === 'week' || $period === 'month') {
            $show = $period === 'week' ? $rows : array_slice($rows, -7, 7, true);
            if ($period === 'month' && count($rows) > 7) { $lines[] = 'هفت روز آخر:'; } elseif ($period === 'week') { $lines[] = 'به تفکیک روز:'; }
            foreach ($show as $d => $s) { $lines[] = '• ' . Jalali::WEEKDAYS[Jalali::weekdayIndex($d)] . ' ' . Jalali::short($d) . ': ' . ($s === null ? '—' : self::num($s) . ' لیتر'); }
        }
        $lvl = $ds->level($st['id'], $today);
        $lines[] = '';
        $lines[] = '🛢 موجودی فعلی مخزن: ' . self::levelText($st, $lvl);
        foreach (self::alarms($st, $lvl, $today) as $a) { $lines[] = $a; }
        return implode("\n", $lines);
    }
}
