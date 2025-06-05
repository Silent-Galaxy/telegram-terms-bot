در ادامه، ساختار پایگاه داده MySQL برای جدول users که توسط فایل db.php استفاده می‌شود، آورده شده است. این ساختار با توجه به تنظیمات charset و collation درخواستی شما (utf8mb4_persian_ci) به‌روزرسانی شده است.

CREATE DATABASE IF NOT EXISTS `telegram_bot_db`
CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;

USE `telegram_bot_db`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_user_id` BIGINT UNIQUE NOT NULL,
    `language_code` VARCHAR(10) DEFAULT 'en' NOT NULL,
    `terms_status` ENUM('initial', 'read_rules', 'accepted') DEFAULT 'initial' NOT NULL,
    `accepted_at` DATETIME NULL,
    `last_bot_message_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;

توضیحات جدول users:

id: شناسه منحصر به فرد برای هر رکورد کاربر. این یک کلید اصلی با افزایش خودکار است.

telegram_user_id: شناسه منحصر به فرد کاربر تلگرام (BIGINT برای ذخیره IDهای بزرگ تلگرام). این فیلد باید منحصر به فرد باشد تا هر کاربر تلگرام فقط یک بار ثبت شود.

language_code: کد زبان انتخاب شده توسط کاربر (مثلاً 'en', 'fa'). مقدار پیش‌فرض آن 'en' (انگلیسی) است.

terms_status: وضعیت فعلی کاربر در فرآیند پذیرش قوانین. این فیلد از نوع ENUM است و اکنون فقط می‌تواند یکی از مقادیر زیر را داشته باشد:

initial: وضعیت اولیه، کاربر هنوز شروع نکرده است.

read_rules: کاربر قوانین را خوانده است.

accepted: کاربر قوانین را پذیرفته است.

accepted_at: تاریخ و زمان دقیق پذیرش نهایی قوانین توسط کاربر. این فیلد می‌تواند NULL باشد تا زمانی که کاربر قوانین را نپذیرفته است.

last_bot_message_id: شناسه آخرین پیامی که ربات به این کاربر ارسال کرده است. این فیلد برای مدیریت حذف پیام‌های قبلی و جلوگیری از اسپم استفاده می‌شود.

created_at: تاریخ و زمان ایجاد رکورد کاربر. مقدار پیش‌فرض آن زمان فعلی سیستم است.

updated_at: تاریخ و زمان آخرین به‌روزرسانی رکورد کاربر. این فیلد به طور خودکار در هر به‌روزرسانی، به زمان فعلی سیستم تغییر می‌کند.

این ساختار پایگاه داده، تمام اطلاعات لازم برای مدیریت وضعیت کاربران و فرآیند پذیرش قوانین در ربات شما را فراهم می‌کند و به طور خاص برای پشتیبانی از کاراکترهای utf8mb4 با ترتیب‌بندی فارسی (utf8mb4_persian_ci) پیکربندی شده است.
