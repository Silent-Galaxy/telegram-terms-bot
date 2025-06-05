<?php

// اطلاعات اتصال به دیتابیس را از متغیرهای محیطی می‌خوانیم
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

// بررسی اینکه آیا متغیرهای محیطی با موفقیت خوانده شده‌اند
if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    error_log("خطا: اطلاعات دیتابیس از متغیرهای محیطی خوانده نشد. لطفاً SetEnv را در .htaccess بررسی کنید.");
}

/**
 * تابعی برای اتصال به دیتابیس
 * @return mysqli|false شیء mysqli در صورت موفقیت، یا false در صورت شکست
 */
function connectDb() {
    global $dbHost, $dbUser, $dbPass, $dbName;
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        error_log("خطا در اتصال به دیتابیس: " . $conn->connect_error);
        return false;
    }
    // تنظیم charset و collation برای دیتابیس برای پشتیبانی از زبان فارسی
    $conn->set_charset('utf8mb4');
    $conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_persian_ci'");
    return $conn;
}

/**
 * تابعی برای ایجاد جدول users (در صورت عدم وجود)
 */
function createUsersTable() {
    $conn = connectDb();
    if (!$conn) return;

    // تعریف ساختار جدول users
    $sqlUsers = "
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
    ";
    // اجرای کوئری ایجاد جدول
    if (!$conn->query($sqlUsers)) {
        error_log("خطا در ایجاد جدول users: " . $conn->error);
    }
    $conn->close();
}

/**
 * تابعی برای دریافت اطلاعات کاربر بر اساس ID تلگرام
 * @param int $telegramUserId ID کاربر تلگرام
 * @return array|false اطلاعات کاربر یا false در صورت عدم وجود
 */
function getUser($telegramUserId) {
    $conn = connectDb();
    if (!$conn) return false;
    $stmt = $conn->prepare("SELECT * FROM users WHERE telegram_user_id = ?");
    $stmt->bind_param("i", $telegramUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

/**
 * تابعی برای ایجاد کاربر جدید در دیتابیس
 * @param int $telegramUserId ID کاربر تلگرام
 * @return bool true در صورت موفقیت، false در صورت شکست
 */
function createUser($telegramUserId) {
    $conn = connectDb();
    if (!$conn) return false;
    $stmt = $conn->prepare("INSERT INTO users (telegram_user_id) VALUES (?)");
    $stmt->bind_param("i", $telegramUserId);
    $success = $stmt->execute();
    if (!$success) {
        error_log("خطا در ایجاد کاربر: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

/**
 * تابعی برای به‌روزرسانی کد زبان کاربر
 * @param int $telegramUserId ID کاربر تلگرام
 * @param string $languageCode کد زبان جدید
 * @return bool true در صورت موفقیت، false در صورت شکست
 */
function updateUserLanguage($telegramUserId, $languageCode) {
    $conn = connectDb();
    if (!$conn) return false;
    $stmt = $conn->prepare("UPDATE users SET language_code = ? WHERE telegram_user_id = ?");
    $stmt->bind_param("si", $languageCode, $telegramUserId);
    $success = $stmt->execute();
    if (!$success) {
        error_log("خطا در به‌روزرسانی زبان کاربر: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

/**
 * تابعی برای به‌روزرسانی وضعیت پذیرش قوانین کاربر
 * @param int $telegramUserId ID کاربر تلگرام
 * @param string $status وضعیت جدید (initial, read_rules, accepted)
 * @return bool true در صورت موفقیت، false در صورت شکست
 */
function updateTermsStatus($telegramUserId, $status) {
    $conn = connectDb();
    if (!$conn) return false;
    $acceptedAt = null;
    if ($status === 'accepted') {
        $acceptedAt = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE users SET terms_status = ?, accepted_at = ? WHERE telegram_user_id = ?");
        $stmt->bind_param("ssi", $status, $acceptedAt, $telegramUserId);
    } else {
        // اگر وضعیت accepted نباشد، accepted_at را NULL می‌کنیم.
        $stmt = $conn->prepare("UPDATE users SET terms_status = ?, accepted_at = NULL WHERE telegram_user_id = ?");
        $stmt->bind_param("si", $status, $telegramUserId);
    }
    $success = $stmt->execute();
    if (!$success) {
        error_log("خطا در به‌روزرسانی وضعیت قوانین کاربر: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

/**
 * تابعی برای به‌روزرسانی ID آخرین پیام ربات به کاربر
 * @param int $telegramUserId ID کاربر تلگرام
 * @param int $messageId ID پیام
 * @return bool true در صورت موفقیت، false در صورت شکست
 */
function updateLastBotMessageId($telegramUserId, $messageId) {
    $conn = connectDb();
    if (!$conn) return false;
    $stmt = $conn->prepare("UPDATE users SET last_bot_message_id = ? WHERE telegram_user_id = ?");
    $stmt->bind_param("ii", $messageId, $telegramUserId);
    $success = $stmt->execute();
    if (!$success) {
        error_log("خطا در به‌روزرسانی last_bot_message_id: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

// برای اطمینان از وجود جداول در اولین اجرای اسکریپت
createUsersTable();

?>
