<?php
// lang.php - مدیریت زبان و ترجمه‌ها

// مسیر فایل JSON حاوی ترجمه‌ها
$langFile = __DIR__ . '/languages.json';
$translations = [];

// بررسی وجود فایل و بارگذاری ترجمه‌ها
if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true);
    // بررسی خطاهای JSON در هنگام تجزیه فایل
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("خطا در تجزیه فایل languages.json: " . json_last_error_msg());
        $translations = []; // در صورت خطا، آرایه ترجمه‌ها را خالی می‌کنیم
    }
} else {
    // در صورت عدم یافتن فایل، یک پیام خطا در لاگ ثبت می‌شود
    error_log("فایل languages.json یافت نشد در: " . $langFile);
}

/**
 * تابعی برای دریافت متن ترجمه شده بر اساس کد زبان و کلید متن
 * @param string $key کلید متن مورد نظر (مثلاً 'welcome_message')
 * @param string $langCode کد زبان (مثلاً 'en', 'fa')
 * @param array $placeholders آرایه‌ای از جایگزین‌ها برای متن (اختیاری، مثلاً ['name' => 'John'])
 * @return string متن ترجمه شده یا کلید در صورت عدم یافتن ترجمه (برای اشکال‌زدایی)
 */
function getTranslation($key, $langCode, $placeholders = []) {
    global $translations;

    // اگر ترجمه برای زبان مورد نظر و کلید موجود باشد
    if (isset($translations[$langCode][$key])) {
        $text = $translations[$langCode][$key];
        // جایگزینی placeholderها در متن (مثلاً %progress_status% با مقدار واقعی)
        foreach ($placeholders as $placeholder => $value) {
            $text = str_replace("%" . $placeholder . "%", $value, $text);
        }
        return $text;
    }

    // اگر ترجمه برای زبان مورد نظر یافت نشد، تلاش برای یافتن در زبان انگلیسی (پیش‌فرض)
    if (isset($translations['en'][$key])) {
        $text = $translations['en'][$key];
        foreach ($placeholders as $placeholder => $value) {
            $text = str_replace("%" . $placeholder . "%", $value, $text);
        }
        return $text;
    }

    // در صورت عدم یافتن ترجمه در هیچ زبانی، خود کلید را برمی‌گرداند (برای اشکال‌زدایی)
    return $key;
}
?>
