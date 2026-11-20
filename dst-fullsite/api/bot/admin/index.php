<?php
// Admin page for senior management: who may use the bot, their messenger phone, role/station, who gets the daily report.
foreach (['Jalali', 'Phone', 'Store', 'DataSource', 'Users'] as $m) { require __DIR__ . '/../src/' . $m . '.php'; }

header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('dstbot_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/api/bot/admin', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$cfg = Store::config();
$admin = Store::read('admin.json', []);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$msg = []; $err = [];
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($post && !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(400); exit('درخواست نامعتبر (CSRF).'); }

if ($post && ($_POST['action'] ?? '') === 'login') {
    if (!Store::allow('adminlogin|' . $ip, 8, 600)) {
        $err[] = 'تلاش‌های ناموفق زیاد بود؛ ۱۰ دقیقه بعد دوباره امتحان کنید.';
    } elseif (!empty($admin['password_hash']) && hash_equals((string) $admin['username'], (string) ($_POST['username'] ?? '')) && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $err[] = 'نام کاربری یا رمز نادرست است.';
    }
}
$in = !empty($_SESSION['admin']);
if ($in && $post) {
    $users = Users::all();
    $ds = dst_data_source();
    $stationIds = array_map(function ($s) { return $s['id']; }, $ds->stations());
    $a = $_POST['action'] ?? '';
    if ($a === 'logout') { $_SESSION = []; session_destroy(); header('Location: ' . $_SERVER['REQUEST_URI']); exit; }
    if ($a === 'save_user') {
        $editing = (string) ($_POST['id'] ?? '') ?: null;
        list($clean, $e) = Users::validate($_POST, $users, $stationIds, $editing);
        if ($e) { $err = $e; }
        else {
            $found = false;
            foreach ($users as $i => $u) { if ($u['id'] === $clean['id']) { $users[$i] = $clean; $found = true; } }
            if (!$found) { $users[] = $clean; }
            Users::save($users);
            $msg[] = 'ذخیره شد.';
        }
    } elseif ($a === 'delete_user') {
        $users = array_values(array_filter($users, function ($u) { return $u['id'] !== (string) ($_POST['id'] ?? ''); }));
        Users::save($users);
        $msg[] = 'کاربر حذف شد.';
    } elseif ($a === 'change_password') {
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['old_password'] ?? ''), $admin['password_hash'])) { $err[] = 'رمز فعلی درست نیست.'; }
        elseif (mb_strlen($new) < 12) { $err[] = 'رمز جدید باید دست‌کم ۱۲ نویسه باشد.'; }
        else { $admin['password_hash'] = password_hash($new, PASSWORD_DEFAULT); Store::write('admin.json', $admin); $msg[] = 'رمز عوض شد.'; }
    }
}
?><!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>مدیریت ربات پیام‌رسان — DST</title>
<style>body{font-family:Tahoma,sans-serif;background:#f6f3ee;margin:0;padding:16px;color:#222}main{max-width:980px;margin:auto}
h1{font-size:1.3rem}h2{font-size:1.05rem;margin-top:28px}table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden}
th,td{padding:8px 10px;border-bottom:1px solid #eee;text-align:right;font-size:.88rem}th{background:#efe7d8}
form.card,.card{background:#fff;border-radius:10px;padding:14px;margin:10px 0}label{display:block;margin:8px 0 3px;font-size:.85rem}
input[type=text],input[type=password],select{width:100%;padding:8px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
button{padding:8px 16px;border:0;border-radius:8px;background:#e5a323;font-weight:bold;cursor:pointer}button.sec{background:#ddd}
.ok{background:#e3f4e8;padding:8px;border-radius:8px;margin:8px 0}.bad{background:#fde6e3;padding:8px;border-radius:8px;margin:8px 0}.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}
.mono{direction:ltr;unicode-bidi:plaintext}</style></head><body><main>
<h1>مدیریت ربات پیام‌رسان DST</h1>
<?php foreach ($msg as $m): ?><div class="ok"><?= h($m) ?></div><?php endforeach; foreach ($err as $e): ?><div class="bad"><?= h($e) ?></div><?php endforeach; ?>
<?php if (!$in): ?>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login">
<label>نام کاربری</label><input type="text" name="username" autocomplete="username" required>
<label>رمز عبور</label><input type="password" name="password" autocomplete="current-password" required>
<p><button>ورود</button></p></form>
<?php else:
$users = Users::all(); $ds = dst_data_source(); $stations = $ds->stations();
$edit = ['id' => '', 'name' => '', 'phone' => '', 'role' => 'station_manager', 'station_id' => '', 'daily_report' => false, 'active' => true];
if (isset($_GET['edit'])) { foreach ($users as $u) { if ($u['id'] === (string) $_GET['edit']) { $edit = $u; $edit['phone'] = Phone::local($u['phone']); } } } ?>
<p>منبع داده: <b><?= $ds->isSample() ? 'نمونه (آزمایشی)' : 'واقعی (فایل)' ?></b>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="sec">خروج</button></form></p>
<h2>کاربران ربات</h2>
<table><tr><th>نام</th><th>شماره</th><th>نقش</th><th>جایگاه</th><th>گزارش روزانه</th><th>فعال</th><th></th></tr>
<?php foreach ($users as $u): ?><tr><td><?= h($u['name']) ?></td><td class="mono"><?= h(Phone::local($u['phone'])) ?></td><td><?= h(Users::ROLES[$u['role']] ?? $u['role']) ?></td>
<td><?php foreach ($stations as $s) { if ($s['id'] === $u['station_id']) { echo h($s['name']); } } ?></td><td><?= !empty($u['daily_report']) ? '✔' : '—' ?></td><td><?= !empty($u['active']) ? '✔' : '✖' ?></td>
<td><a href="?edit=<?= h($u['id']) ?>">ویرایش</a> <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= h($u['id']) ?>"><button class="sec">حذف</button></form></td></tr>
<?php endforeach; if (!$users): ?><tr><td colspan="7">هنوز کاربری ثبت نشده است.</td></tr><?php endif; ?></table>
<h2>افزودن یا ویرایش کاربر</h2>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= h($edit['id']) ?>">
<div class="row"><div><label>نام</label><input type="text" name="name" required maxlength="80" value="<?= h($edit['name']) ?>"></div>
<div><label>شماره‌ی موبایل پیام‌رسان</label><input type="text" name="phone" class="mono" placeholder="09121234567" required value="<?= h($edit['phone']) ?>"></div>
<div><label>نقش</label><select name="role"><?php foreach (Users::ROLES as $k => $v): ?><option value="<?= h($k) ?>"<?= $edit['role'] === $k ? ' selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
<div><label>جایگاه (فقط برای مدیر جایگاه)</label><select name="station_id"><option value="">—</option><?php foreach ($stations as $s): ?><option value="<?= h($s['id']) ?>"<?= $edit['station_id'] === $s['id'] ? ' selected' : '' ?>><?= h($s['name'] . ' — ' . $s['city']) ?></option><?php endforeach; ?></select></div></div>
<label><input type="checkbox" name="daily_report" value="1"<?= !empty($edit['daily_report']) ? ' checked' : '' ?>> دریافت گزارش روزانه‌ی صبح (فقط مدیرعامل و مدیریت ارشد)</label>
<label><input type="checkbox" name="active" value="1"<?= !empty($edit['active']) ? ' checked' : '' ?>> فعال</label><p><button>ذخیره</button></p></form>
<h2>آخرین دسترسی‌ها</h2>
<table><tr><th>زمان</th><th>کاربر (شناسه)</th><th>گزارش</th><th>نتیجه</th></tr>
<?php foreach (AccessLog::tail(40) as $r): ?><tr><td class="mono"><?= h($r['t'] ?? '') ?></td><td class="mono"><?= h($r['user'] ?? '—') ?></td><td class="mono"><?= h($r['endpoint'] ?? '') ?></td><td><?= h($r['code'] ?? '') ?></td></tr><?php endforeach; ?></table>
<h2>تغییر رمز مدیر</h2>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="change_password">
<div class="row"><div><label>رمز فعلی</label><input type="password" name="old_password" required></div><div><label>رمز جدید (حداقل ۱۲ نویسه)</label><input type="password" name="new_password" required></div></div><p><button>تغییر رمز</button></p></form>
<?php endif; ?></main></body></html>
