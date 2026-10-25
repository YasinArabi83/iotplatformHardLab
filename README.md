# IoT Platform (HardLab)

یک پلتفرم ساده‌ی **ثبت و نمایش داده‌ی اینترنت اشیاء (IoT)** با **PHP و MySQL**.
دستگاه‌ها (مثلاً ESP32 / ESP8266) با درخواست `GET` داده‌هایشان را به سرور می‌فرستند، سرور آن‌ها را در دیتابیس ذخیره می‌کند و از طریق API و چند صفحه‌ی وب (جدول، نمودار، مانیتورینگ) قابل مشاهده است.

> این ریپو fork ـی از [honprogram/iotplatformHardLab](https://github.com/honprogram/iotplatformHardLab) است و یک سیستم دیپلوی خودکار روی هاست cPanel به آن اضافه شده است.

## این پروژه چیست؟

- هر **دستگاه** یک `clientID` و یک **توکن** ۲۰ کاراکتری دارد (جدول `user`).
- هر دستگاه یک جدول اختصاصی به نام `id_<clientID>` دارد.
- در هر ردیف داده:
  - **۸ ورودی عددی:** `ina` تا `inh`
  - **۸ وضعیت روشن/خاموش:** `sena` تا `senh` (مقدار `on` یا `off`)
  - IP دستگاه، `timestamp` و `time_date`
- اگر دستگاه پارامتری را نفرستد، مقدار همان پارامتر از یکی از ردیف‌های قبلی **همان روز** کپی می‌شود (و اگر ردیفی نبود، `0` یا `off`).
- احراز هویت دستگاه‌ها با جفت `id` + `tkn` (توکن) انجام می‌شود.

**تکنولوژی:** PHP (mysqli)، MySQL/MariaDB، Bootstrap، DataTables، Highcharts و amCharts (از CDN یا فایل‌های محلی). این پروژه روی PHP 7.4 اجرا می‌شده است.

## ساختار ریپو

```
.
├── .github/workflows/deploy.yml   # دیپلوی خودکار روی cPanel (FTP/FTPS)
├── iot/                           # خود برنامه؛ فقط محتوای این پوشه دیپلوی می‌شود
│   ├── classes/                   # Database.php و autoload.php
│   ├── chart/                     # صفحات نمودار (Highcharts / amCharts)
│   ├── monitoring/                # صفحه‌ی مانیتورینگ زنده
│   ├── asset/                     # CSS
│   ├── insert.php, loglastjson.php, arrayjson.php, createID.php, active.php   # API
│   ├── controlPanel.php, logusertable.php, logidtable.php, multi-link.php     # صفحات وب
│   ├── config.local.example.php   # نمونه‌ی تنظیمات دیتابیس (روی سرور کپی می‌شود)
│   └── .htaccess                  # دسترسی به فایل‌های حساس را می‌بندد
└── sql/
    ├── hivaindb_alldata.sql       # دیتابیس مورد نیاز برنامه (جدول user و جدول‌های id_*)
    └── hivaindb_db.sql            # دیتابیس دیگر (در کد این ریپو استفاده نشده)
```

---

## دیپلوی روی cPanel

با هر push به شاخه‌ی `main` که فایلی در `iot/` را تغییر بدهد، GitHub Actions ابتدا سینتکس PHP را بررسی می‌کند و بعد محتوای `iot/` را با FTP/FTPS روی هاست آپلود می‌کند. دیتابیس و فایل تنظیمات **دستی و فقط یک‌بار** ساخته می‌شوند و دیپلوی هرگز به آن‌ها دست نمی‌زند.

### پیش‌نیازها

- هاست cPanel با PHP (ترجیحاً 7.4 یا 8.0) و MySQL
- دسترسی FTP (اکانت FTP در cPanel)
- این ریپو روی GitHub‌ِ خودتان (fork) و فعال بودن Actions

### مرحله ۱: ساخت دیتابیس

cPanel ← **MySQL® Databases**:

1. یک دیتابیس بسازید و نام کامل آن (با پیشوند اکانت، مثل `cpuser_iotdb`) را یادداشت کنید.
2. یک کاربر دیتابیس با رمز قوی بسازید (مثل `cpuser_iotuser`).
3. کاربر را به دیتابیس اضافه کنید و **ALL PRIVILEGES** بدهید.

### مرحله ۲: ایمپورت دیتابیس

cPanel ← **phpMyAdmin** ← دیتابیس را از ستون کناری انتخاب کنید ← تب **Import** ← فایل `sql/hivaindb_alldata.sql` ← **Go**.

- فقط همین یک فایل لازم است؛ `hivaindb_db.sql` توسط کد این ریپو استفاده نمی‌شود.
- فایل حدود ۱۲ مگابایت است. اگر خطای حجم داد، آن را zip کنید.
- فایل شامل `CREATE DATABASE` نیست، پس در هر دیتابیسی ایمپورت می‌شود.

### مرحله ۳: پوشه‌ی مقصد روی هاست

یکی از دو حالت:

- **زیرپوشه (پیشنهادی):** در File Manager داخل `public_html` پوشه‌ی `iot` بسازید. آدرس برنامه می‌شود `https://yourdomain.com/iot/...`.
- **ریشه‌ی دامنه یا ساب‌دامین:** مستقیم روی `public_html` (یا پوشه‌ی ساب‌دامین). **احتیاط:** فایل `.htaccess` موجود در آن پوشه با نسخه‌ی این پروژه جایگزین می‌شود.

### مرحله ۴: فایل تنظیمات دیتابیس

فایل `iot/config.local.example.php` را با نام **`config.local.php`** در **ریشه‌ی پوشه‌ی مقصد** (کنار `insert.php` و `.htaccess`، نه داخل `classes/`) روی سرور بسازید و مقادیر را پر کنید:

```php
<?php
return [
    'host'     => 'localhost',
    'database' => 'cpuser_iotdb',
    'username' => 'cpuser_iotuser',
    'password' => 'رمز-دیتابیس',
];
```

این فایل داخل git نیست، دیپلوی آن را آپلود یا پاک نمی‌کند و `.htaccess` دسترسی HTTP به آن را می‌بندد. اگر این فایل نباشد، برنامه با مقادیر پیش‌فرض قدیمی (`root` بدون رمز) تلاش می‌کند وصل شود و روی هاست کار نخواهد کرد.

### مرحله ۵: اکانت FTP

cPanel ← **FTP Accounts**:

1. یک اکانت جدید بسازید و **Directory** را روی پوشه‌ی مقصد بگذارید (مثلاً `public_html/iot`). اکانت فقط به همین پوشه دسترسی خواهد داشت.
2. نام کاربری کامل (مثل `deploy@yourdomain.com`) و رمز را یادداشت کنید.
3. پایین همان صفحه، **Configure FTP Client** را باز کنید و آدرس FTP server را بردارید.

### مرحله ۶: تنظیم GitHub

ریپو ← **Settings** ← **Secrets and variables** ← **Actions**.

**Secrets** (تب Secrets ← New repository secret؛ نام‌ها باید دقیقاً همین باشند):

| نام | مقدار |
|---|---|
| `FTP_SERVER` | آدرس سرور FTP، بدون `ftp://` (مثل `ftp.yourdomain.com`) |
| `FTP_USERNAME` | نام کاربری کامل اکانت FTP |
| `FTP_PASSWORD` | رمز اکانت FTP |

**Variables** (تب Variables ← New repository variable):

| نام | پیش‌فرض | توضیح |
|---|---|---|
| `FTP_TARGET_DIR` | `/public_html/iot/` | مسیر مقصد **نسبت به ریشه‌ی اکانت FTP**. اگر اکانت را روی خود پوشه‌ی مقصد قفل کرده‌اید (مرحله ۵)، مقدار را `/` بگذارید. با `/` نگذاشتن، فایل‌ها در پوشه‌های تو در تو آپلود می‌شوند. |
| `FTP_PROTOCOL` | `ftps` | `ftps` / `ftp` / `ftps-legacy`. اگر اتصال FTPS خطا داد، `ftp` را امتحان کنید. |
| `FTP_PORT` | `21` | پورت FTP |

### مرحله ۷: فعال کردن Actions (فقط برای fork)

تب **Actions** ← اگر پیام «Workflows aren't being run on this forked repository» دیدید، دکمه‌ی **I understand my workflows, go ahead and enable them** را بزنید.

### مرحله ۸: اولین اجرا

- با یک push به `main` (که فایلی در `iot/` را عوض کند)، یا
- از تب **Actions** ← **Deploy to cPanel (FTP)** ← **Run workflow**.

اجرای موفق سبز می‌شود. تب Actions فقط workflowهایی را نشان می‌دهد که روی شاخه‌ی پیش‌فرض (`main`) باشند.

### مرحله ۹: تست

آدرس `https://yourdomain.com/iot/insert.php` را باز کنید. اگر PHP درست اجرا می‌شود، باید `{"status":"Invalid information entered"}` ببینید. بعد با یک `id` و `tkn` واقعی از جدول `user` تست کنید:

```
https://yourdomain.com/iot/insert.php?id=ID&tkn=TOKEN&ina=25&sena=on
```

### دیپلوی دستی (بدون GitHub Actions)

محتوای پوشه‌ی `iot/` را (به جز `error_log` و `*.map`) با File Manager یا FTP در پوشه‌ی مقصد آپلود کنید و مراحل ۱ تا ۴ را انجام دهید.

---

## مرجع Endpointها

آدرس پایه: `https://yourdomain.com/iot/` (یا ریشه‌ی دامنه، بسته به محل دیپلوی). همه با `GET`.

### API (خروجی JSON)

| Endpoint | پارامترها | عملکرد | احراز هویت |
|---|---|---|---|
| `insert.php` | `id`، `tkn`؛ اختیاری: `ina`..`inh`، `sena`..`senh` | ثبت یک ردیف داده برای دستگاه. پارامترهای ارسال‌نشده از یک ردیف قبلی همان روز کپی می‌شوند. | `id` + `tkn` |
| `loglastjson.php` | `id`، `tkn` | آخرین ردیف ثبت‌شده‌ی دستگاه | `id` + `tkn` |
| `arrayjson.php` | `id`، `tkn`، `in` (نام ستون، مثل `ina`)، `row` (تعداد ردیف) | مقدارهای یک ستون برای `row` ردیف آخر | `id` + `tkn` |
| `createID.php` | `id` | ساخت دستگاه جدید: جدول `id_<id>`، کاربر `user<id>` و توکن تصادفی (در پاسخ برگردانده می‌شود) | ندارد |
| `active.php` | `tkn` (توکن ادمین) | فیلد `active` همه‌ی کاربران را `1` می‌کند | توکن ادمین (داخل کد) |

### صفحات وب

| آدرس | پارامترها | توضیح |
|---|---|---|
| `controlPanel.php` | ندارد | جدول همه‌ی کاربران (clientID، username، token، expire) با جستجو |
| `logusertable.php` | ندارد | همان جدول کاربران، ساده‌تر |
| `logidtable.php` | `id`، `tkn`، `row` | جدول داده‌های `row` ردیف آخر یک دستگاه |
| `chart/charts.php` | `id`، `tkn`، `row`، `in1`..`in4` | نمودار Highcharts از چهار ستون |
| `chart/chartdb.php` | `id`، `tkn`، `in`، `row` | نمودار amCharts از یک ستون |
| `monitoring/monitoring.php` | `id`، `tkn`؛ اختیاری: `refresh` | مانیتورینگ زنده |
| `multi-link.php` | ندارد | صفحه‌ی لینک‌های ادمین (به سرور مبدأ اشاره می‌کند، بخش «نکات» را ببینید) |

---

## نکات و مشکلات شناخته‌شده

این‌ها در کد اصلی وجود دارند و هنوز رفع نشده‌اند.

**امنیت**

- **SQL Injection:** پارامترهای `GET` (از جمله `id` که نام جدول را می‌سازد، و `in`، `row`) بدون sanitize مستقیم در queryها استفاده می‌شوند. قبل از قرار دادن برنامه روی اینترنت باز باید با prepared statement و اعتبارسنجی ورودی (مثلاً فقط ارقام برای `id`، لیست سفید برای ستون‌ها) اصلاح شود.
- **صفحات بدون احراز هویت:** `createID.php` (ساخت دستگاه)، `controlPanel.php` و `logusertable.php` (نمایش همه‌ی توکن‌ها) هیچ محافظتی ندارند. پیشنهاد: Directory Privacy در cPanel یا یک لایه‌ی رمز ادمین.
- **توکن ادمین در کد:** توکن `active.php` مستقیم در سورس نوشته شده و چون ریپو عمومی است، فاش‌شده محسوب می‌شود. باید به `config.local.php` منتقل و عوض شود.
- **داده‌ی واقعی در ریپو عمومی:** `sql/hivaindb_alldata.sql` شامل جدول `user` با توکن‌های واقعی دستگاه‌هاست. در صورت نیاز، توکن‌ها را عوض و ریپو را private کنید (fork از ریپوی عمومی را نمی‌شود private کرد؛ ریپوی جدید بسازید).
- فایل‌های `iot/error_log` و `iot/chart/error_log` در ریپو commit شده‌اند و از دیپلوی کنار گذاشته می‌شوند.

**وابستگی به سرور مبدأ**

- `monitoring/asset/js/script.js` داده را از `http://hoshikala.ir/iot/wil/loglastjson.php` می‌گیرد، نه از سرور شما. باید به `loglastjson.php` (آدرس نسبی) تغییر کند.
- فرم‌ها و لینک‌های `multi-link.php` به `hivaind.ir` اشاره می‌کنند و صفحات `crud/` و `property/` که به آن‌ها لینک داده شده در این ریپو وجود ندارند.

**موارد دیگر**

- محدودیت روزانه‌ی ثبت داده: کد `insert.php` ردیف‌های همان روز را با سقف ۱۰۰۰ مقایسه می‌کند، ولی پیام خطا «۵۰۰ داده در روز» می‌گوید.
- نسخه‌ی PHP: کد روی 7.4 اجرا می‌شده است. روی 8.1 به بالا تست نشده و ممکن است هشدار یا خطای جدید بدهد.
- پروتکل‌هایی مثل MQTT یا اتصال دائمی (socket) روی هاست اشتراکی cPanel معمولاً کار نمی‌کنند؛ این پروژه فقط با HTTP کار می‌کند.

## عیب‌یابی

| مشکل | علت و راه‌حل |
|---|---|
| تب Actions خالی است | workflow باید روی `main` باشد؛ در fork دکمه‌ی فعال‌سازی Actions را هم بزنید. |
| Action قرمز: `Secret ... is not set` | نام Secretها را دقیق (حروف بزرگ) بررسی کنید. |
| Action قرمز: خطای اتصال یا گواهی | متغیر `FTP_PROTOCOL` را `ftp` بگذارید، یا `FTP_SERVER` را با آی‌پی سرور امتحان کنید. |
| فایل‌ها در پوشه‌های تو در تو آپلود شدند | `FTP_TARGET_DIR` را با توجه به ریشه‌ی اکانت FTP تنظیم کنید (معمولاً `/`). |
| ۴۰۳ Forbidden روی آدرس ریشه | طبیعی است: پروژه فایل `index` ندارد و `.htaccess` لیست پوشه را می‌بندد. آدرس endpointها را مستقیم باز کنید. |
| `Database could not be connected` | `config.local.php` ساخته نشده، در پوشه‌ی اشتباه است، یا مشخصات (با پیشوند cPanel) درست نیست. |
| صفحه‌ی سفید | نسخه‌ی PHP را عوض کنید (7.4 یا 8.0) و `error_log` پوشه را ببینید. |
| `.htaccess` قبلی سایت پاک شد | دیپلوی روی ریشه‌ی دامنه آن را جایگزین می‌کند؛ از زیرپوشه یا ساب‌دامین استفاده کنید. |
