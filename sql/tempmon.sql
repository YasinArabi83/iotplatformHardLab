-- ---------------------------------------------------------------
-- جداول مانیتور دما (iot/tempmon) — در همان دیتابیس پلتفرم ایمپورت شود
-- نکته: alarm.php اگر auto_create_tables فعال باشد خودش این جداول را
-- می‌سازد؛ این فایل برای ایمپورت دستی از طریق phpMyAdmin است.
-- ---------------------------------------------------------------

-- تاریخچه‌ی کامل هر برداشت (هر ۵ دقیقه یک ردیف برای هر دستگاه)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- وضعیت لحظه‌ای هر دستگاه (آخرین بار دیده‌شدن + وضعیت هشدارها برای جلوگیری از اسپم)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
