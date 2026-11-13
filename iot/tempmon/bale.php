<?php
/**
 * Bale Messenger — منطق ارسال پیام
 * ---------------------------------------------------------------------------
 * تمام تعامل با پیام‌رسان بله فقط در همین فایل است؛ alarm.php (و هر اسکریپت
 * دیگری) از اینجا پیام می‌فرستد و در صورت تغییر سرویس پیام‌رسان، فقط این
 * فایل عوض می‌شود.
 *
 * استفاده:
 *   require_once __DIR__ . '/bale.php';
 *   tempmon_bale_send($config['bale'], 'متن پیام', $log);
 *
 * بخش bale در config:
 *   'bale' => [
 *       'enabled'   => true,
 *       'bot_token' => '...',   // توکن ربات
 *       'chat_id'   => '...',   // شناسه‌ی چت مقصد
 *   ]
 *
 * سازگار با PHP 7.4 (محیط cPanel پلتفرم).
 */

/**
 * ارسال پیام متنی به بله از طریق Bot API بله (سازگار با API تلگرام).
 *
 * @param array    $bale  بخش bale از config
 * @param string   $text  متن پیام
 * @param callable|null $log  تابع اختیاری لاگ (پیام، موفقیت/خطا را گزارش می‌کند)
 * @return bool    true اگر پیام با موفقیت ارسال شد
 */
function tempmon_bale_send(array $bale, string $text, ?callable $log = null): bool
{
    if (empty($bale['enabled']) || empty($bale['bot_token']) || empty($bale['chat_id'])) {
        if ($log) { $log('BALE: not configured, message skipped: ' . $text); }
        return false;
    }

    $url = 'https://tapi.bale.ai/bot' . $bale['bot_token'] . '/sendMessage';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['chat_id' => $bale['chat_id'], 'text' => $text], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false || $http < 200 || $http >= 300) {
        if ($log) { $log('BALE: send FAILED (http ' . $http . ($err !== '' ? ', ' . $err : '') . '): ' . $text); }
        return false;
    }
    if ($log) { $log('BALE: sent -> ' . $text); }
    return true;
}
