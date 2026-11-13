# agent.md — راهنمای کار با ریپو iotplatformHardLab

این فایل برای ایجنت‌ها (و توسعه‌دهنده‌ها) است تا ساختار، قراردادها و نکات مهم پروژه را سریع بفهمند.

## پروژه چیست؟

پلتفرم ثبت و نمایش داده‌ی IoT با **PHP (mysqli) + MySQL/MariaDB**.
دستگاه‌ها (ESP32/ESP8266 و مشابه) با درخواست **GET** داده می‌فرستند، سرور ذخیره می‌کند و صفحات وب (جدول، نمودار، مانیتورینگ) نمایش می‌دهند. فقط HTTP است — MQTT/socket نیست (هاست اشتراکی cPanel).

- Forkی از `honprogram/iotplatformHardLab` + سیستم دیپلوی خودکار cPanel.
- پرونده به پرونده قدیمی است؛ روی PHP 7.4 اجرا می‌شده، روی 8.1+ تست نشده.
- زبان UI فارسی، جهت RTL، ولی نام متغیرها و خروجی‌های JSON انگلیسی.

## ساختار پوشه‌ها

```
.
├── .github/workflows/deploy.yml   # دیپلوی خودکار: محتوای iot/ را با FTP به cPanel می‌برد
├── iot/                           # ← کل اپلیکیشن؛ فقط همین پوشه دیپلوی می‌شود
│   ├── classes/
│   │   ├── Database.php           # کلاس اتصال mysqli؛ مقادیر را از config.local.php Override می‌کند
│   │   └── autoload.php           # spl_autoload_register ساده (کلاس = نام فایل در classes/)
│   ├── tempmon/                   # ⬅ مانیتور دما (جدید): cron هر ۵ دقیقه + هشدار بله (README داخلی را ببینید)
│   │   ├── alarm.php              # نقطه‌ی ورود cron؛ polling، ذخیره در temp_log، تشخیص خطا و هشدار
│   │   ├── bale.php               # منطق ارسال پیام به بله (جدا از منطق مانیتورینگ)
│   │   └── config.example.php     # تنظیمات (دستگاه‌ها، آستانه‌ها، بله)؛ سرور: config.local.php
│   ├── insert.php                 # API اصلی ثبت داده
│   ├── loglastjson.php            # API: آخرین ردیف یک دستگاه (JSON)
│   ├── arrayjson.php              # API: مقادیر یک ستون برای N ردیف آخر (JSON)
│   ├── createID.php               # API: ساخت دستگاه جدید (بدون احراز هویت!)
│   ├── active.php                 # API: فعال‌سازی همه‌ی کاربران (توکن ادمین هاردکد!)
│   ├── controlPanel.php           # صفحه: جدول همه‌ی کاربران/توکن‌ها
│   ├── logusertable.php           # صفحه: جدول کاربران ساده‌تر
│   ├── logidtable.php             # صفحه: جدول داده‌های یک دستگاه
│   ├── multi-link.php             # صفحه: لینک‌های ادمین (به دامنه‌های قدیمی اشاره می‌کند)
│   ├── config.local.example.php   # نمونه‌ی تنظیمات DB → روی سرور به config.local.php کپی شود
│   ├── .htaccess                  # بستن دسترسی HTTP به config/error_log + غیرفعال‌کردن Indexes
│   ├── chart/                     # نمودارها: charts.php (Highcharts)، chartdb.php (amCharts)
│   │   └── asset/amcharts4/       # کتابخانه‌ی amCharts به‌صورت local (فایل‌های زیاد؛ دست نزنید)
│   ├── monitoring/                # مانیتورینگ زنده (monitoring.php + asset/js/script.js)
│   └── asset/css/                 # bootstrap.css و ...
└── sql/
    ├── hivaindb_alldata.sql       # دیتابیس اصلی برنامه (جدول user + جدول‌های id_*) — فقط همین ایمپورت می‌شود
    ├── tempmon.sql                # جداول مانیتور دما (temp_log، temp_state) — alarm.php خودش هم می‌سازد
    └── hivaindb_db.sql            # استفاده‌نشده (فقط برای مرجع)
```

## مدل داده

- جدول `user`: هر دستگاه یک `clientID` + توکن ۲۰ کاراکتری (`token`) دارد.
- هر دستگاه جدول اختصاصی `id_<clientID>` دارد (نام جدول از ورودی کاربر ساخته می‌شود).
- هر ردیف: `clientID`, `ip`, `timestamp`, `time_date`,
  - ۸ ورودی عددی: `ina` … `inh`
  - ۸ وضعیت on/off: `sena` … `senh`

## قراردادهای کد

- همه‌ی endpointها **GET** و خروجی JSON با کلید `status`.
- احراز هویت دستگاه: جفت پارامتر `id` + `tkn` (چک دستی با query به جدول `user`).
- پارامترهای ارسال‌نشده در `insert.php` از آخرین ردیف **همان روز** همان دستگاه کپی می‌شوند؛ اگر ردیفی نباشد عددی‌ها `0` و وضعیت‌ها `off`.
- سقف ثبت: ۱۰۰۰ ردیف در روز برای هر دستگاه (پیام خطا به‌اشتباه می‌گوید ۵۰۰).
- هیچ فریم‌ورک/composer/build tool وجود ندارد. اسکریپت: `php -S localhost:8000` از داخل `iot/` (به دیتابیس واقعی نیاز دارد؛ بدون `config.local.php` با `root/بدون رمز` وصل می‌شود).
- سبک کد: PHP رویه‌ای/ترکیبی HTML+PHP، بدون template engine، بدون prepared statement. تغییرات را با همین سبک ساده نگه دارید مگر آنکه هدف بازنویسی باشد.

## تنظیمات و محیط

- `iot/config.local.php` (در git نیست، در deploy هم exclude است) با ساختار:
  ```php
  return ['host'=>..., 'database'=>..., 'username'=>..., 'password'=>...];
  ```
  نبودش یعنی اتصال با مقادیر پیش‌فرض قدیمی (`root`/رمز خالی) → روی هاست واقعی کار نمی‌کند.
- محلی: XAMPP/Laragon + ایمپورت `sql/hivaindb_alldata.sql` + ساخت `config.local.php` کنار `insert.php`.

## دیپلوی (مهم!)

- هر push به `main` که فایلی در `iot/**` یا خود workflow را عوض کند → GitHub Actions اول `php -l` روی همه‌ی PHPها، بعد آپلود محتوای `iot/` با FTP/FTPS به cPanel.
- دیپلوی هرگز دیتابیس و `config.local.php` را نمی‌سازد/دست نمی‌زند — آن‌ها دستی و یک‌بار انجام می‌شوند.
- از دیپلوی exclude شده: `error_log`، `*.map`، `config.local.php`، `.git*`.
- Secrets لازم: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Variables: `FTP_TARGET_DIR` (پیش‌فرض `/public_html/iot/`)، `FTP_PROTOCOL` (`ftps`)، `FTP_PORT` (`21`).
- **همیشه مسیرها را نسبت به ریشه‌ی `iot/` در نظر بگیرید** (مثلاً `classes/autoload.php` نه `iot/classes/...`).

## مشکلات شناخته‌شده (قبل از هر تغییر امنیتی، این‌ها را در نظر بگیرید)

1. **SQL Injection همه‌جا:** `id` (نام جدول می‌سازد)، `in` (نام ستون)، `row` و بقیه‌ی پارامترها مستقیم در query می‌روند. اصلاح = اعتبارسنجی (فقط ارقام برای `id`) + لیست سفید ستون‌ها + prepared statement.
2. **صفحات/APIهای بدون احراز هویت:** `createID.php`، `controlPanel.php`، `logusertable.php` همه‌ی توکن‌ها را نشان می‌دهند.
3. **توکن ادمین هاردکد** در `active.php` (`EOQmeqYqUuq1ZoSx17AS`) و ریپو عمومی است → باید به `config.local.php` منتقل و عوض شود.
4. **داده‌ی واقعی در ریپو:** `sql/hivaindb_alldata.sql` توکن‌های واقعی دستگاه‌ها را دارد.
5. **وابستگی به سرور مبدأ:** `monitoring/asset/js/script.js` از `http://hoshikala.ir/iot/wil/loglastjson.php` می‌خواند — باید به `../loglastjson.php` (نسبی) تغییر کند. لینک‌های `multi-link.php` به `hivaind.ir` و صفحات `crud/` و `property/` که در این ریپو نیستند اشاره می‌کنند.
6. ناسازگاری پیام خطا (۵۰۰ در برابر سقف واقعی ۱۰۰۰) در `insert.php`.
7. `iot/error_log` و `iot/chart/error_log` اشتباهاً commit شده‌اند؛ به آن‌ها اعتماد نکنید و دوباره commitشان نکنید.

## مانیتور دما (iot/tempmon) — قابلیت جدید

هر ۵ دقیقه با Cron در cPanel به `https://hoshiserver.ir/class/data.php?id=..&token=..` درخواست می‌زند و:

- `inputA/inputB/inputC` را می‌خواند (÷100 = درجه‌ی سانتی‌گراد) و در جدول `temp_log` ذخیره می‌کند (پلتفرم DB همان دیتابیس خود پلتفرم است).
- وضعیت لحظه‌ای/هشدارها را در جدول `temp_state` نگه می‌دارد. رفتار پیام: هشدار **دما** در هر برداشت که دما بالای آستانه است ارسال می‌شود (در هر اجرای cron) + یک پیام «برگشت به حالت عادی»؛ هشدارهای **قطع سنسور/دستگاه** لبه‌ای هستند (یک پیام در شروع خطا و یک پیام در رفع آن).
- خطاها و هشدارها به **بله** ارسال می‌شوند: دمای بالا (آستانه در config)، قطع سنسور (مقدار برگشتی با `-99` شروع شود)، قطع دستگاه (بیش از `offline_after_minutes` بدون پاسخ موفق) + پیام‌های برگشت به حالت عادی.
- سهمیه‌ی API: ۳۰۰ درخواست/روز → cron باید دقیقاً هر ۵ دقیقه باشد (۲۸۸/روز). مانیتور مانده‌ی سهمیه را لاگ می‌کند.
- کانفیگ: `iot/tempmon/config.local.php` روی سرور (الگو: `config.example.php` شامل دستگاه `id=1112`). مشخصات DB از `iot/config.local.php` همان پلتفرم می‌آید.
- ساختار: `alarm.php` (نقطه‌ی ورود cron) و `bale.php` (منطق ارسال پیام، جدا نگه داشته شده). راه‌اندازی کامل (Cron command، ساخت ربات بله، پرس‌وجوهای نمونه): `iot/tempmon/README.md`.
- ⚠️ آستانه‌های فعلی (`13/15/2.5`) عمداً برای تمرین دانشگاهی پایین گذاشته شده‌اند تا با دیتای واقعی دستگاه هشدار فعال شود؛ برای محیط واقعی به ~80 تغییر دهند.

## چیزهایی که نباید دست بزنید (بدون دلیل قوی)

- `iot/chart/asset/amcharts4/` — کتابخانه‌ی vendored، صدها فایل. فقط فایل‌های `charts.php`/`chartdb.php` و CSSهای خود پروژه را ویرایش کنید.
- `sql/hivaindb_db.sql` — استفاده نمی‌شود.
- `config.local.php` — وجود ندارد و نباید commit شود (در `.gitignore` است).

## تست سریع

```
https://<host>/iot/insert.php                      → {"status":"Invalid information entered"}
https://<host>/iot/insert.php?id=ID&tkn=TOKEN&ina=25&sena=on
https://<host>/iot/loglastjson.php?id=ID&tkn=TOKEN
```
(403 روی ریشه طبیعی است — index وجود ندارد و Indexes بسته است.)
