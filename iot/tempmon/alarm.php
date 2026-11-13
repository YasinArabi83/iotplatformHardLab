<?php
/**
 * Temperature Monitor — alarm endpoint
 * ---------------------------------------------------------------------------
 * هر بار اجرا (هر ۵ دقیقه از طریق Cron در cPanel):
 *   1. برای هر دستگاهِ تعریف‌شده در config، به data.php?id=..&token=.. درخواست می‌زند.
 *   2. inputA/inputB/inputC را می‌خواند (÷100 = درجه‌ی سانتی‌گراد) و در temp_log ذخیره می‌کند.
 *   3. خطاها را تشخیص می‌دهد و از طریق bale.php پیام به «بله» می‌فرستد:
 *        - قطع دستگاه:       تا بعد از offline_after_minutes هیچ پاسخ موفقی نرسد
 *        - قطع سنسور:        مقدار برگشتی با "-99" شروع شود
 *        - دمای بالا:        مقدار از آستانه‌ی تعریف‌شده در config بیشتر شود
 *   4. رفتار پیام‌ها:
 *        - همه‌ی هشدارهای یک دوره (یک اجرای cron) در قالب **یک پیام** تجمیع
 *          و ارسال می‌شوند؛ همین‌طور پیام‌های «برگشت به حالت عادی».
 *        - هشدار دما در هر برداشت که دما بالای آستانه است داخل همان پیام دوره می‌آید.
 *        - قطع سنسور/دستگاه: لبه‌ای (یک پیام در شروع خطا و یک پیام در رفع آن).
 *
 * اجرا:
 *   CLI  : php /path/to/iot/tempmon/alarm.php
 *   Web  : alarm.php?key=<web_key>   (اگر web_key در config خالی باشد غیرفعال است)
 *
 * منطق ارسال پیام در فایل جداگانه‌ی bale.php است.
 * سازگار با PHP 7.4 (همان محیط cPanel پلتفرم).
 */

date_default_timezone_set('Asia/Tehran');

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
$cfgFile = __DIR__ . '/config.local.php';
$config  = is_readable($cfgFile) ? require $cfgFile : require __DIR__ . '/config.example.php';

// اجرای از مرورگر فقط با کلید مجاز است
$isCli = (PHP_SAPI === 'cli' || defined('STDIN'));
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $webKey = (string)($config['web_key'] ?? '');
    if ($webKey === '' || !hash_equals($webKey, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ---------------------------------------------------------------------------
// جلوگیری از اجرای هم‌زمان دو instance (تداخل cron)
// ---------------------------------------------------------------------------
$lockFp = fopen(__DIR__ . '/alarm.lock', 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    if (!$isCli) { echo json_encode(['status' => 'already running'], JSON_UNESCAPED_UNICODE); }
    exit;
}

$report = [];
$log = function (string $msg) use (&$report, $isCli) {
    if ($isCli) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; }
    $report[] = $msg;
};

// منطق ارسال پیام (بله) — فایل جداگانه
require_once __DIR__ . '/bale.php';

// ---------------------------------------------------------------------------
// Database (همان کلاس پلتفرم؛ مشخصات از iot/config.local.php خوانده می‌شود)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../classes/autoload.php';
$db = new Database;
if (!$db->conn) {
    $log('ERROR: database connection failed');
    flock($lockFp, LOCK_UN); fclose($lockFp);
    if (!$isCli) { echo json_encode(['status' => 'db error'], JSON_UNESCAPED_UNICODE); }
    exit;
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------
if (!empty($config['auto_create_tables'])) {
    $db->conn->query("
        CREATE TABLE IF NOT EXISTS `temp_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `device_id` VARCHAR(100) NOT NULL,
            `ts` DATETIME NOT NULL,
            `ts_unix` INT UNSIGNED NOT NULL,
            `temp_a` DECIMAL(8,2) DEFAULT NULL,
            `temp_b` DECIMAL(8,2) DEFAULT NULL,
            `temp_c` DECIMAL(8,2) DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'ok',
            `raw` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_device_ts` (`device_id`, `ts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->conn->query("
        CREATE TABLE IF NOT EXISTS `temp_state` (
            `device_id` VARCHAR(100) NOT NULL,
            `last_seen` DATETIME DEFAULT NULL,
            `last_seen_unix` INT UNSIGNED DEFAULT 0,
            `conn_status` ENUM('on','off') NOT NULL DEFAULT 'on',
            `conn_alert_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `temp_alert_a` TINYINT(1) NOT NULL DEFAULT 0,
            `temp_alert_b` TINYINT(1) NOT NULL DEFAULT 0,
            `temp_alert_c` TINYINT(1) NOT NULL DEFAULT 0,
            `sensor_alert_a` TINYINT(1) NOT NULL DEFAULT 0,
            `sensor_alert_b` TINYINT(1) NOT NULL DEFAULT 0,
            `sensor_alert_c` TINYINT(1) NOT NULL DEFAULT 0,
            `updated` DATETIME NOT NULL,
            PRIMARY KEY (`device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** درخواست GET ساده با cURL؛ [body, error] برمی‌گرداند. */
function tempmon_http_get(string $url, int $timeout = 25): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'IoT-TempMon/1.0 (+cron)',
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) { return [null, 'curl error #' . $errno]; }
    if ($http < 200 || $http >= 300) { return [null, 'http status ' . $http]; }
    return [$body, null];
}

/** آیا مقدار (به‌صورت رشته) نشانه‌ی قطع سنسور است؟ (شروع با "-99") */
function tempmon_sensor_off($value, string $prefix): bool
{
    if ($value === null) { return true; } // فیلد اصلاً نیامده = بی‌داده
    return strpos(trim((string)$value), $prefix) === 0;
}

/** خواندن/ساخت state دستگاه */
function tempmon_get_state(mysqli $conn, string $deviceId): array
{
    $st = $conn->prepare('SELECT last_seen_unix, conn_status, conn_alert_sent,
                                 temp_alert_a, temp_alert_b, temp_alert_c,
                                 sensor_alert_a, sensor_alert_b, sensor_alert_c
                          FROM temp_state WHERE device_id = ?');
    $st->bind_param('s', $deviceId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) { return $row; }

    return [
        'last_seen_unix'  => 0,
        'conn_status'     => 'on',
        'conn_alert_sent' => 0,
        'temp_alert_a'    => 0, 'temp_alert_b' => 0, 'temp_alert_c' => 0,
        'sensor_alert_a'  => 0, 'sensor_alert_b' => 0, 'sensor_alert_c' => 0,
    ];
}

/** ذخیره‌ی state دستگاه (upsert) */
function tempmon_save_state(mysqli $conn, string $deviceId, array $s, ?string $lastSeenDt): void
{
    $st = $conn->prepare(
        'INSERT INTO temp_state
            (device_id, last_seen, last_seen_unix, conn_status, conn_alert_sent,
             temp_alert_a, temp_alert_b, temp_alert_c,
             sensor_alert_a, sensor_alert_b, sensor_alert_c, updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            last_seen = VALUES(last_seen), last_seen_unix = VALUES(last_seen_unix),
            conn_status = VALUES(conn_status), conn_alert_sent = VALUES(conn_alert_sent),
            temp_alert_a = VALUES(temp_alert_a), temp_alert_b = VALUES(temp_alert_b),
            temp_alert_c = VALUES(temp_alert_c),
            sensor_alert_a = VALUES(sensor_alert_a), sensor_alert_b = VALUES(sensor_alert_b),
            sensor_alert_c = VALUES(sensor_alert_c), updated = NOW()'
    );
    $st->bind_param(
        'ssisiiiiiii',
        $deviceId,
        $lastSeenDt,
        $s['last_seen_unix'],
        $s['conn_status'],
        $s['conn_alert_sent'],
        $s['temp_alert_a'],
        $s['temp_alert_b'],
        $s['temp_alert_c'],
        $s['sensor_alert_a'],
        $s['sensor_alert_b'],
        $s['sensor_alert_c']
    );
    $st->execute();
    $st->close();
}

/** درج یک ردیف در temp_log */
function tempmon_insert_log(mysqli $conn, string $deviceId, ?float $a, ?float $b, ?float $c, string $status, ?string $raw): void
{
    $st = $conn->prepare('INSERT INTO temp_log
        (device_id, ts, ts_unix, temp_a, temp_b, temp_c, status, raw)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)');
    $ts = time();
    $st->bind_param('sidddss', $deviceId, $ts, $a, $b, $c, $status, $raw);
    $st->execute();
    $st->close();
}

// ---------------------------------------------------------------------------
// Main: پردازش هر دستگاه
// ---------------------------------------------------------------------------
$interval    = (int)($config['poll_interval_minutes'] ?? 5);
$offlineMin  = (int)($config['offline_after_minutes'] ?? ($interval * 2 + 2));
$divisor     = (float)($config['temp_divisor'] ?: 100);
$senPrefix   = (string)($config['sensor_off_prefix'] ?? '-99');
$channels    = (array)($config['channels'] ?? ['a', 'b', 'c']);
$thresholds  = (array)($config['thresholds'] ?? []);
$devices     = (array)($config['devices'] ?? []);
$baleConfig  = (array)($config['bale'] ?? []);

foreach ($devices as $dev) {
    $deviceId = (string)($dev['id'] ?? '');
    $devName  = (string)($dev['name'] ?? $deviceId);
    if ($deviceId === '') { $log('SKIP: device without id'); continue; }

    $log('--- device ' . $devName . ' (' . $deviceId . ') ---');

    // 1) درخواست به API
    $url = $config['api_base_url'] . '?' . http_build_query(['id' => $deviceId, 'token' => $dev['token']]);
    list($body, $err) = tempmon_http_get($url);

    $data = null;
    if ($err === null) {
        $data = json_decode((string)$body, true);
        if (!is_array($data) || !array_key_exists('inputA', $data)) {
            $err = 'invalid json response';
            $data = null;
        }
    }

    $state = tempmon_get_state($db->conn, $deviceId);

    // 2) دستگاه پاسخ نداد → قطعی دستگاه
    if ($err !== null) {
        $log('DEVICE: no response (' . $err . ')');
        tempmon_insert_log($db->conn, $deviceId, null, null, null, 'no_response', $err);

        $ageMin = $state['last_seen_unix'] > 0 ? (time() - (int)$state['last_seen_unix']) / 60 : INF;
        if ($ageMin >= $offlineMin && $state['conn_status'] !== 'off') {
            $state['conn_status'] = 'off';
            $state['conn_alert_sent'] = 1;
            tempmon_bale_send($baleConfig,
                "❌ قطع ارتباط دستگاه\n" .
                "دستگاه: {$devName} (id={$deviceId})\n" .
                "بیش از " . (int)$offlineMin . " دقیقه پاسخی دریافت نشد.\n" .
                'زمان: ' . date('Y-m-d H:i:s'), $log);
        } elseif ($state['conn_status'] === 'off') {
            $log('DEVICE: still offline (alert already sent)');
        }
        tempmon_save_state($db->conn, $deviceId, $state, null);
        continue;
    }

    // 3) پاسخ موفق → اگر قبلاً «قطع» اعلام شده بود، خبر وصل‌شدن بده
    if ($state['conn_status'] === 'off' || $state['conn_alert_sent'] == 1) {
        tempmon_bale_send($baleConfig,
            "✅ وصل شدن دستگاه\n" .
            "دستگاه: {$devName} (id={$deviceId})\n" .
            'زمان: ' . date('Y-m-d H:i:s'), $log);
    }
    $state['conn_status'] = 'on';
    $state['conn_alert_sent'] = 0;
    $state['last_seen_unix'] = time();

    // یادداشت سهمیه‌ی روزانه (۳۰۰ درخواست در روز)
    $remaining = $data['limit']['remaining_requests_today'] ?? null;
    if ($remaining !== null && (int)$remaining <= 0) {
        $log('WARN: API daily quota exhausted');
    }

    // 4) خواندن کانال‌های دما (a, b, c)
    //    هشدارها/برگشت‌های همه‌ی کانال‌ها جمع می‌شوند و در «یک پیام» ارسال می‌گردند.
    $temps = ['a' => null, 'b' => null, 'c' => null];
    $statusFlags = [];
    $alertLines    = []; // خط‌های هشدار این دوره
    $recoveryLines = []; // خط‌های برگشت به حالت عادی این دوره

    foreach ($channels as $ch) {
        $key = 'input' . strtoupper($ch);
        $rawVal = $data[$key] ?? null;

        // قطع سنسور: مقدار با -99 شروع شود (یا اصلاً نیامده باشد)
        if (tempmon_sensor_off($rawVal, $senPrefix)) {
            $statusFlags[] = 'sensor_' . $ch;
            $flag = 'sensor_alert_' . $ch;
            if (empty($state[$flag])) {
                $state[$flag] = 1;
                $shown = ($rawVal === null) ? 'داده دریافت نشد' : (string)$rawVal;
                $alertLines[] = "• کانال " . strtoupper($ch) . " — قطعی سنسور (مقدار: {$shown})";
            }
            continue;
        }

        // سنسور سالم → اگر قبلاً قطع بود، خبر برگشت بده
        $flag = 'sensor_alert_' . $ch;
        if (!empty($state[$flag])) {
            $state[$flag] = 0;
            $recoveryLines[] = "• کانال " . strtoupper($ch) . " — سنسور وصل شد";
        }

        // تبدیل به سانتی‌گراد و بررسی آستانه
        $tempC = ((float)$rawVal) / $divisor;
        $temps[$ch] = $tempC;

        $limit = isset($thresholds[$ch]) ? (float)$thresholds[$ch] : null;
        if ($limit !== null) {
            $tflag = 'temp_alert_' . $ch;
            if ($tempC > $limit) {
                // دما بالای آستانه است → خط هشدارِ این دوره (پیامِ تجمیعی)
                $state[$tflag] = 1;
                $alertLines[] = "• کانال " . strtoupper($ch) . " — دمای بالا: " .
                    number_format($tempC, 2, '.', '') . " °C (آستانه: " .
                    number_format($limit, 2, '.', '') . ")";
            } elseif (!empty($state[$tflag])) {
                // دما برگشت زیر آستانه → خط «برگشت» در پیام تجمیعی
                $state[$tflag] = 0;
                $recoveryLines[] = "• کانال " . strtoupper($ch) . " — دما برگشت: " .
                    number_format($tempC, 2, '.', '') . " °C";
            }
        }
    }

    // 5) ارسال پیام‌های تجمیعی این دوره (اگر چیزی داشته باشند)
    if ($alertLines) {
        tempmon_bale_send($baleConfig,
            "🚨 هشدار دستگاه {$devName} (id={$deviceId})\n" .
            "زمان: " . date('Y-m-d H:i:s') . "\n" .
            implode("\n", $alertLines), $log);
    }
    if ($recoveryLines) {
        tempmon_bale_send($baleConfig,
            "✅ برگشت به حالت عادی — دستگاه {$devName} (id={$deviceId})\n" .
            "زمان: " . date('Y-m-d H:i:s') . "\n" .
            implode("\n", $recoveryLines), $log);
    }

    // 6) ذخیره در دیتابیس
    $status = $statusFlags ? implode(',', $statusFlags) : 'ok';
    tempmon_insert_log(
        $db->conn,
        $deviceId,
        $temps['a'], $temps['b'], $temps['c'],
        $status,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );
    tempmon_save_state($db->conn, $deviceId, $state, date('Y-m-d H:i:s'));

    $log('DATA: a=' . var_export($temps['a'], true) .
         ' b=' . var_export($temps['b'], true) .
         ' c=' . var_export($temps['c'], true) .
         ' status=' . $status .
         ($remaining !== null ? ' | quota remaining today: ' . $remaining : ''));
}

flock($lockFp, LOCK_UN);
fclose($lockFp);

if (!$isCli) {
    echo json_encode(['status' => 'done', 'log' => $report], JSON_UNESCAPED_UNICODE);
}
