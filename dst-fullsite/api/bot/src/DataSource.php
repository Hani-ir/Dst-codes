<?php
/**
 * Where tank levels and sales come from. Phase 1 ships a deterministic SAMPLE source (clearly labelled in every report)
 * and a FILE source (data/stations.json, levels.json, sales.json) that real data can be written into later,
 * e.g. by an endpoint fed from the tank-SMS collector. The reports only talk to this interface.
 */
interface DataSource
{
    /** [['id','name','city','capacity_l', 'price_toman_per_liter'?], ...] */
    public function stations();
    /** ['level_l'=>float,'temperature_c'=>?float,'service_overdue'=>bool,'updated'=>?string 'c'] or null when unknown */
    public function level($stationId, $todayYmd);
    /** liters sold on that Tehran date, or null when nothing is recorded */
    public function salesLiters($stationId, $ymd, $todayYmd);
    public function isSample();
}

final class SampleDataSource implements DataSource
{
    private $rows = [
        ['id' => 'isfahan-sepehr', 'name' => 'پارتاک دیزل سپهر', 'city' => 'اصفهان', 'capacity_l' => 6000, 'base' => 110, 'level' => 84, 'temp' => 22, 'overdue' => false],
        ['id' => 'tehran-markazi', 'name' => 'جایگاه نمونه مرکزی', 'city' => 'تهران', 'capacity_l' => 4000, 'base' => 380, 'level' => 62, 'temp' => 26, 'overdue' => false],
        ['id' => 'mashhad-sharq', 'name' => 'جایگاه نمونه شرق', 'city' => 'مشهد', 'capacity_l' => 4000, 'base' => 300, 'level' => 17, 'temp' => 31, 'overdue' => false],
        ['id' => 'bandarabbas-jonub', 'name' => 'جایگاه نمونه جنوب', 'city' => 'بندرعباس', 'capacity_l' => 4000, 'base' => 260, 'level' => 45, 'temp' => 44, 'overdue' => false],
        ['id' => 'tabriz-gharb', 'name' => 'جایگاه نمونه غرب', 'city' => 'تبریز', 'capacity_l' => 4000, 'base' => 220, 'level' => 71, 'temp' => 19, 'overdue' => true],
        ['id' => 'kerman-madan', 'name' => 'جایگاه نمونه معدن', 'city' => 'کرمان', 'capacity_l' => 4000, 'base' => 520, 'level' => 9, 'temp' => 37, 'overdue' => false],
    ];
    private $weekday = [1.0, 1.0, 1.05, 1.0, 0.95, 0.8, 0.4]; // Saturday ... Friday

    private function row($id) { foreach ($this->rows as $r) { if ($r['id'] === $id) { return $r; } } return null; }
    public function isSample() { return true; }

    public function stations()
    {
        return array_map(function ($r) { return ['id' => $r['id'], 'name' => $r['name'], 'city' => $r['city'], 'capacity_l' => $r['capacity_l']]; }, $this->rows);
    }

    public function level($id, $today)
    {
        $r = $this->row($id);
        if (!$r) { return null; }
        $wobble = (crc32($id . '|' . $today) % 3) - 1; // -1..+1 points: the day-to-day look, alarm states stay stable
        $pct = max(1, min(100, $r['level'] + $wobble));
        return ['level_l' => round($r['capacity_l'] * $pct / 100), 'temperature_c' => $r['temp'], 'service_overdue' => $r['overdue'], 'updated' => null];
    }

    public function salesLiters($id, $ymd, $today)
    {
        $r = $this->row($id);
        if (!$r || $ymd > $today) { return null; }
        $full = $r['base'] * $this->weekday[Jalali::weekdayIndex($ymd)] * (0.85 + (crc32($id . '|' . $ymd) % 31) / 100);
        if ($ymd === $today) { // today is still going: a share of the day, by Tehran time
            $hour = (int) (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('G');
            $full *= min(1, ($hour + 1) / 20);
        }
        return (float) round($full);
    }
}

final class FileDataSource implements DataSource
{
    public function isSample() { return false; }
    public function stations() { return Store::read('data/stations.json', []); }

    public function level($id, $today)
    {
        $all = Store::read('data/levels.json', []);
        $l = isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
        if (!$l || !isset($l['level_l'])) { return null; }
        return ['level_l' => (float) $l['level_l'], 'temperature_c' => isset($l['temperature_c']) ? (float) $l['temperature_c'] : null,
                'service_overdue' => !empty($l['service_overdue']), 'updated' => isset($l['updated']) ? (string) $l['updated'] : null];
    }

    public function salesLiters($id, $ymd, $today)
    {
        $all = Store::read('data/sales.json', []);
        return isset($all[$id][$ymd]) ? (float) $all[$id][$ymd] : null;
    }
}

function dst_data_source()
{
    return Store::config()['data_source'] === 'files' ? new FileDataSource() : new SampleDataSource();
}
