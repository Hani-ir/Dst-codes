<?php
/** Iranian mobile numbers: every common spelling -> canonical 98912XXXXXXX. */
final class Phone
{
    public static function normalize($raw)
    {
        if (!is_string($raw) && !is_int($raw)) { return null; }
        $s = strtr((string) $raw, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $s = preg_replace('/[^0-9]/', '', $s);
        if (strpos($s, '0098') === 0) { $s = substr($s, 2); }
        if (strpos($s, '98') === 0 && strlen($s) === 12) { /* already international */ }
        elseif (strpos($s, '0') === 0 && strlen($s) === 11) { $s = '98' . substr($s, 1); }
        elseif (strlen($s) === 10 && $s[0] === '9') { $s = '98' . $s; }
        return preg_match('/^989\d{9}$/', $s) ? $s : null;
    }

    /** 98912... -> 0912... (what people read) */
    public static function local($canonical) { return '0' . substr($canonical, 2); }

    /** 0912***4567 for logs and admin lists */
    public static function mask($canonical) { return '0' . substr($canonical, 2, 3) . '****' . substr($canonical, -3); }
}
