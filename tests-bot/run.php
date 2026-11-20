<?php
// php tests-bot/run.php   (PHP >= 7.4, no dependencies). Uses a temporary data directory; touches nothing else.
$root = dirname(__DIR__) . '/dst-fullsite/api/bot/src/';
$tmp = sys_get_temp_dir() . '/dst-bot-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0750, true);
putenv('DST_BOT_DATA=' . $tmp);
foreach (['Jalali', 'Phone', 'Store', 'DataSource', 'Reports', 'Users', 'Api'] as $m) { require $root . $m . '.php'; }

$pass = 0; $fail = 0;
function check($cond, $name) { global $pass, $fail; if ($cond) { $pass++; } else { $fail++; echo "FAIL: $name\n"; } }
function has($hay, $needle) { return strpos($hay, $needle) !== false; }

// ---- Jalali against the Python jdatetime fixture ----
$fx = json_decode(file_get_contents(__DIR__ . '/jalali-fixture.json'), true);
$bad = 0;
foreach ($fx as $r) {
    list($ymd, $jy, $jm, $jd, $wd) = $r;
    $got = Jalali::fromYmd($ymd);
    if ($got !== [$jy, $jm, $jd] || Jalali::toYmd($jy, $jm, $jd) !== $ymd || Jalali::weekdayIndex($ymd) !== $wd) { $bad++; if ($bad < 4) { echo "  mismatch $ymd got " . implode('-', $got) . " want $jy-$jm-$jd\n"; } }
}
check($bad === 0, 'Jalali matches jdatetime on ' . count($fx) . ' dates (' . $bad . ' mismatches)');
check(Jalali::long('2026-11-20') === 'جمعه ۲۹ آبان ۱۴۰۵', 'long date text');
check(Jalali::weekStart('2026-11-20') === '2026-11-14', 'week starts on Saturday');
check(Jalali::monthStart('2026-11-20') === '2026-10-23', 'Jalali month start');
check(Jalali::fa('0123456789') === '۰۱۲۳۴۵۶۷۸۹', 'Persian digits');

// ---- phone numbers ----
foreach (['09121234567', '+989121234567', '989121234567', '00989121234567', '9121234567', '۰۹۱۲۱۲۳۴۵۶۷', '0912 123-4567', '+98 (912) 123 4567'] as $p) {
    check(Phone::normalize($p) === '989121234567', "phone $p");
}
foreach (['', '0912', '12345678901', 'abc', '08121234567', null, []] as $p) { check(Phone::normalize($p) === null, 'bad phone ' . json_encode($p)); }
check(Phone::local('989121234567') === '09121234567' && Phone::mask('989121234567') === '0912****567', 'phone local/mask');

// ---- API with a temp data dir ----
$KEY = 'test-key-' . bin2hex(random_bytes(6));
file_put_contents($tmp . '/config.php', "<?php return ['bot_key_sha256'=>'" . hash('sha256', $KEY) . "','data_source'=>'sample','rate_limit_per_phone'=>12,'rate_limit_per_ip'=>500];");
$mk = function ($id, $name, $phone, $role, $station, $daily, $active = true) { return ['id' => $id, 'name' => $name, 'phone' => $phone, 'role' => $role, 'station_id' => $station, 'daily_report' => $daily, 'active' => $active]; };
Users::save([
    $mk('c1', 'مدیرعامل آزمون', '989120000001', 'ceo', null, true),
    $mk('s1', 'مدیر ارشد آزمون', '989120000002', 'senior', null, false),
    $mk('m1', 'مدیر اصفهان', '989120000003', 'station_manager', 'isfahan-sepehr', false),
    $mk('m2', 'مدیر مشهد', '989120000004', 'station_manager', 'mashhad-sharq', false),
    $mk('x1', 'غیرفعال', '989120000005', 'ceo', null, true, false),
    $mk('m3', 'بدون جایگاه', '989120000006', 'station_manager', 'does-not-exist', false),
]);
$NOW = strtotime('2026-11-20 10:00:00 Asia/Tehran');
$call = function ($route, $in = [], $key = null, $method = 'POST') use ($KEY, $NOW) { return Api::handle($method, $route, $key === null ? $KEY : $key, $in, '203.0.113.9', $NOW); };

check($call('whoami', ['phone' => '09120000001'], null, 'GET')[0] === 405, 'GET refused');
check($call('whoami', ['phone' => '09120000001'], 'wrong')[0] === 401, 'wrong key -> 401');
check($call('whoami', ['phone' => '09120000001'], '')[0] === 401, 'missing key -> 401');
check($call('nothing', ['phone' => '09120000001'])[0] === 404, 'unknown route -> 404');
$r = $call('whoami', ['phone' => '09129999999'])[1];
check($r['ok'] === false && $r['code'] === 'unknown_phone' && has($r['text'], 'ثبت نشده'), 'unknown phone gets the polite message');
check($call('whoami', ['phone' => '09120000005'])[1]['code'] === 'unknown_phone', 'inactive user is treated as unknown');
check($call('whoami', ['phone' => 'abc'])[1]['code'] === 'bad_phone', 'bad phone');
$w = $call('whoami', ['phone' => '+989120000003'])[1];
check($w['ok'] && $w['user']['role'] === 'station_manager' && $w['user']['station']['id'] === 'isfahan-sepehr' && count($w['buttons']) === 4, 'whoami of a station manager');
check($w['date_jalali'] === 'جمعه ۲۹ آبان ۱۴۰۵', 'whoami carries the Jalali date');

// CEO daily
$d = $call('report/ceo-daily', ['phone' => '09120000001'])[1];
check($d['ok'] === true, 'ceo can read daily report');
$names = ['پارتاک دیزل سپهر', 'جایگاه نمونه مرکزی', 'جایگاه نمونه شرق', 'جایگاه نمونه جنوب', 'جایگاه نمونه غرب', 'جایگاه نمونه معدن'];
foreach ($names as $n) { check(has($d['text'], $n), "daily has $n"); }
check(has($d['text'], 'جمع کل') && has($d['text'], 'فروش دیروز') && has($d['text'], 'جمعه ۲۹ آبان ۱۴۰۵') && has($d['text'], 'داده‌ی نمونه'), 'daily has totals, date and the sample label');
check(has($d['text'], 'سطح مخزن بحرانی') && has($d['text'], 'سطح مخزن پایین') && has($d['text'], 'دمای بالا') && has($d['text'], 'سرویس دوره‌ای معوق'), 'daily has all alarm kinds');
check(preg_match('/[0-9]/', $d['text']) === 0, 'daily text has Persian digits only');
check($call('report/ceo-daily', ['phone' => '09120000002'])[1]['ok'] === true, 'senior can read daily report');
$f = $call('report/ceo-daily', ['phone' => '09120000003'])[1];
check($f['ok'] === false && $f['code'] === 'forbidden', 'station manager cannot read the CEO report');

// station manager isolation
foreach (['today', 'yesterday', 'week', 'month'] as $p) {
    $s = $call('report/station-sales', ['phone' => '09120000003', 'period' => $p])[1];
    check($s['ok'] === true && has($s['text'], 'پارتاک دیزل سپهر') && has($s['text'], 'موجودی فعلی مخزن'), "manager sales $p");
    foreach (array_slice($names, 1) as $n) { check(!has($s['text'], $n), "manager sees no other station ($p / $n)"); }
}
$s = $call('report/station-sales', ['phone' => '09120000003', 'period' => 'today', 'station_id' => 'mashhad-sharq'])[1];
check(has($s['text'], 'پارتاک دیزل سپهر') && !has($s['text'], 'جایگاه نمونه شرق'), 'a station_id sent by a manager is ignored');
$m = $call('report/station-sales', ['phone' => '09120000004', 'period' => 'month'])[1]['text'];
check(has($m, 'جایگاه نمونه شرق') && has($m, '۱ آبان ۱۴۰۵ تا ۲۹ آبان ۱۴۰۵') && has($m, 'میانگین روزانه'), 'month report uses the Jalali month');
$wk = $call('report/station-sales', ['phone' => '09120000004', 'period' => 'week'])[1]['text'];
check(has($wk, '۲۳ آبان ۱۴۰۵ تا ۲۹ آبان ۱۴۰۵') && has($wk, 'شنبه ۲۳ آبان ۱۴۰۵'), 'week report starts on Saturday');
check($call('report/station-sales', ['phone' => '09120000004', 'period' => 'امروز'])[1]['ok'] === true, 'Persian period names accepted');
check($call('report/station-sales', ['phone' => '09120000004', 'period' => 'nonsense'])[1]['code'] === 'bad_period', 'bad period asks for a choice');
check($call('report/station-sales', ['phone' => '09120000006'])[1]['code'] === 'no_station', 'manager of an unknown station');
$ch = $call('report/station-sales', ['phone' => '09120000001'])[1];
check($ch['code'] === 'choose_station' && count($ch['buttons']) === 6, 'CEO must pick a station');
check(has($call('report/station-sales', ['phone' => '09120000001', 'station_id' => 'tabriz-gharb', 'period' => 'week'])[1]['text'], 'جایگاه نمونه غرب'), 'CEO can read any station');

// recipients and log
$rc = $call('report/daily-recipients')[1];
check($rc['count'] === 1 && $rc['recipients'][0]['phone'] === '09120000001', 'daily recipients = active senior users with the flag');
$log = file_get_contents($tmp . '/logs/access.log');
check(!has($log, '0912') && !has($log, '98912') && !has($log, 'پارتاک') && has($log, '"user":"m1"'), 'access log has no phone numbers or report text');

// rate limit per phone (limit 12)
$last = null;
for ($i = 0; $i < 14; $i++) { $last = $call('whoami', ['phone' => '09120000002'])[1]; }
check($last['code'] === 'rate_limited' && has($last['text'], 'تعداد درخواست'), 'per-phone rate limit');
check($call('whoami', ['phone' => '09120000001'])[1]['ok'] === true, 'rate limit is per phone');

// validation
$ids = ['isfahan-sepehr', 'mashhad-sharq'];
list($okU, $e) = Users::validate(['name' => 'ع', 'phone' => '09125550000', 'role' => 'station_manager', 'station_id' => 'isfahan-sepehr', 'active' => '1'], Users::all(), $ids, null);
check($okU && $okU['phone'] === '989125550000' && $okU['daily_report'] === false, 'valid user');
check(Users::validate(['name' => 'ع', 'phone' => '09120000001', 'role' => 'ceo'], Users::all(), $ids, null)[0] === null, 'duplicate phone refused');
check(Users::validate(['name' => 'ع', 'phone' => '09125550000', 'role' => 'station_manager', 'station_id' => 'zzz'], Users::all(), $ids, null)[0] === null, 'unknown station refused');
check(Users::validate(['name' => '', 'phone' => '1', 'role' => 'x'], [], $ids, null)[0] === null, 'empty form refused');

// file data source: amounts appear only when a price is set
file_put_contents($tmp . '/config.php', "<?php return ['bot_key_sha256'=>'" . hash('sha256', $KEY) . "','data_source'=>'files','rate_limit_per_phone'=>600,'rate_limit_per_ip'=>600];");
Store::resetConfig();
Store::write('data/stations.json', [['id' => 'f1', 'name' => 'جایگاه فایل', 'city' => 'قم', 'capacity_l' => 5000, 'price_toman_per_liter' => 1000]]);
Store::write('data/levels.json', ['f1' => ['level_l' => 900, 'temperature_c' => 20, 'service_overdue' => false, 'updated' => gmdate('c')]]);
Store::write('data/sales.json', ['f1' => ['2026-11-19' => 250, '2026-11-20' => 100]]);
Users::save([$mk('c1', 'مدیرعامل آزمون', '989120000001', 'ceo', null, true), $mk('f', 'مدیر فایل', '989120000009', 'station_manager', 'f1', false)]);
$fd = $call('report/ceo-daily', ['phone' => '09120000001'])[1]['text'];
check(!has($fd, 'داده‌ی نمونه') && has($fd, 'جایگاه فایل') && has($fd, '۲۵۰ لیتر — ۲۵۰٬۰۰۰ تومان') && has($fd, 'سطح مخزن پایین'), 'file source: real data, amount shown when a price is set, no sample label');
$fs = $call('report/station-sales', ['phone' => '09120000009', 'period' => 'yesterday'])[1]['text'];
check(has($fs, '۲۵۰ لیتر') && has($fs, '۱۸٪'), 'file source: station sales and level');
check(has($call('report/station-sales', ['phone' => '09120000009', 'period' => 'week'])[1]['text'], '—'), 'days without data show a dash');

// ---- clean up and report ----
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
