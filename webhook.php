<?php

// بارگذاری فایل‌های مورد نیاز
require_once 'db.php';
require_once 'lang.php'; // شامل تابع getTranslation

// برای اطمینان از وجود جدول users در اولین اجرا
createUsersTable();

// توکن ربات از متغیرهای محیطی
$botToken = getenv('BOT_TOKEN');
// لینک ربات نهایی از متغیرهای محیطی
$nextBotLink = getenv('NEXT_BOT_LINK');

// URL API تلگرام
const TELEGRAM_API_URL = 'https://api.telegram.org/bot';


/**
 * تابعی برای ارسال درخواست به API تلگرام
 * @param string $method متد API (مثلاً 'sendMessage', 'answerCallbackQuery')
 * @param array $params پارامترهای درخواست
 * @return array|false پاسخ API تلگرام به صورت آرایه یا false در صورت خطا
 */
function telegramApiRequest($method, $params = []) {
    global $botToken;
    $url = TELEGRAM_API_URL . $botToken . '/' . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("خطا در درخواست cURL: " . curl_error($ch));
        return false;
    }
    return json_decode($response, true);
}

/**
 * تابعی برای ارسال پیام به کاربر و ذخیره ID پیام
 * @param int $chatId ID چت
 * @param string $text متن پیام
 * @param array $replyMarkup کیبورد اینلاین (اختیاری)
 * @param int|null $replyToMessageId ID پیام برای پاسخ دادن (اختیاری)
 * @param bool $disableWebPagePreview آیا پیش‌نمایش وب‌پیج غیرفعال شود؟ (اختیاری)
 * @return array|false پاسخ API
 */
function sendMessage($chatId, $text, $replyMarkup = [], $replyToMessageId = null, $disableWebPagePreview = false) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => $disableWebPagePreview
    ];
    if (!empty($replyMarkup)) {
        $params['reply_markup'] = $replyMarkup;
    }
    if ($replyToMessageId !== null) {
        $params['reply_to_message_id'] = $replyToMessageId;
    }

    $response = telegramApiRequest('sendMessage', $params);

    // ذخیره ID پیام ربات برای استفاده در ویرایش‌های بعدی
    if (isset($response['ok']) && $response['ok'] && isset($response['result']['message_id'])) {
        updateLastBotMessageId($chatId, $response['result']['message_id']);
        error_log("sendMessage موفقیت‌آمیز برای چت ID: " . $chatId . " پیام ID: " . $response['result']['message_id']);
    } else {
        error_log("خطا در sendMessage برای چت ID: " . $chatId . ". پاسخ تلگرام: " . json_encode($response));
    }
    return $response;
}

/**
 * تابعی برای ویرایش پیام موجود
 * @param int $chatId ID چت
 * @param int $messageId ID پیام برای ویرایش
 * @param string $text متن جدید پیام
 * @param array $replyMarkup کیبورد اینلاین جدید (اختیاری)
 * @param bool $disableWebPagePreview آیا پیش‌نمایش وب‌پیج غیرفعال شود؟ (اختیاری)
 * @return array|false پاسخ API
 */
function editMessageText($chatId, $messageId, $text, $replyMarkup = [], $disableWebPagePreview = false) {
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => $disableWebPagePreview
    ];
    if (!empty($replyMarkup)) {
        $params['reply_markup'] = $replyMarkup;
    }
    $response = telegramApiRequest('editMessageText', $params);

    if (isset($response['ok']) && $response['ok']) {
        error_log("editMessageText موفقیت‌آمیز برای چت ID: " . $chatId . " پیام ID: " . $messageId);
        // اگر پیام با موفقیت ویرایش شد، ID آن را به عنوان آخرین پیام ربات ذخیره می‌کنیم
        updateLastBotMessageId($chatId, $messageId); // ID پیام پس از ویرایش تغییر نمی‌کند
    } else {
        $errorDescription = $response['description'] ?? 'نامشخص';
        $errorCode = $response['error_code'] ?? 'نامشخص';
        error_log("خطا در editMessageText برای چت ID: " . $chatId . " پیام ID: " . $messageId . ". کد خطا: " . $errorCode . "، توضیحات: " . $errorDescription);
        error_log("پارامترهای ارسال شده: " . json_encode($params));
        error_log("پاسخ کامل تلگرام: " . json_encode($response));
    }
    return $response;
}

/**
 * تابعی برای حذف پیام
 * @param int $chatId ID چت
 * @param int $messageId ID پیام برای حذف
 * @return array|false پاسخ API
 */
function deleteMessage($chatId, $messageId) {
    if ($messageId === null) {
        error_log("خطا: تلاش برای حذف پیام با Message ID null برای چت ID: " . $chatId);
        return false; // اگر پیام وجود ندارد، حذف نمی‌کنیم
    }
    $response = telegramApiRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ]);
    if (isset($response['ok']) && $response['ok']) {
        error_log("deleteMessage موفقیت‌آمیز برای چت ID: " . $chatId . " پیام ID: " . $messageId);
    } else {
        error_log("خطا در deleteMessage برای چت ID: " . $chatId . " پیام ID: " . $messageId . ". پاسخ تلگرام: " . json_encode($response));
    }
    return $response;
}

/**
 * تابعی برای نمایش منوی اصلی قوانین (قبل از پذیرش)
 * @param int $chatId ID چت
 * @param int $userId ID کاربر تلگرام
 * @param string $langCode کد زبان کاربر
 * @param int|null $currentMessageId ID پیام فعلی برای ویرایش (در صورت کال‌بک کوئری)
 */
function showTermsMenu($chatId, $userId, $langCode, $currentMessageId = null) {
    $user = getUser($userId);
    $termsStatus = $user['terms_status'] ?? 'initial';

    $keyboard = [];

    // دکمه قبول قوانین فقط اگر هنوز قبول نشده باشد
    if ($termsStatus !== 'accepted') {
        $keyboard[] = [['text' => getTranslation('button_accept_rules', $langCode), 'callback_data' => 'accept_rules']];
    }

    // دکمه تغییر زبان
    $keyboard[] = [['text' => getTranslation('button_change_language', $langCode), 'callback_data' => 'change_language']];

    // اگر قوانین قبلاً پذیرفته شده‌اند، دکمه بازگشت به منوی پذیرفته شده را اضافه کنید
    if ($termsStatus === 'accepted') {
        $keyboard[] = [['text' => getTranslation('button_back_to_accepted_menu', $langCode), 'callback_data' => 'back_to_accepted_menu']];
    }

    $replyMarkup = ['inline_keyboard' => $keyboard];

    $progressStatusText = getTranslation('status_' . $termsStatus, $langCode);
    $welcomeMessage = getTranslation('welcome_message', $langCode, ['progress_status' => $progressStatusText]);

    if ($currentMessageId) {
        editMessageText($chatId, $currentMessageId, $welcomeMessage, $replyMarkup, true);
    } else {
        // اگر currentMessageId وجود ندارد، یک پیام جدید ارسال می‌کنیم
        sendMessage($chatId, $welcomeMessage, $replyMarkup, null, true);
    }
}

/**
 * تابعی برای نمایش منوی پس از پذیرش قوانین
 * @param int $chatId ID چت
 * @param int $userId ID کاربر تلگرام
 * @param string $langCode کد زبان کاربر
 * @param int|null $currentMessageId ID پیام فعلی برای ویرایش (در صورت کال‌بک کوئری)
 */
function showAcceptedMenu($chatId, $userId, $langCode, $currentMessageId = null) {
    global $nextBotLink; // استفاده از لینک ربات نهایی از متغیر محیطی
    $user = getUser($userId);

    $acceptedAt = new DateTime($user['accepted_at']);
    $acceptedDate = $acceptedAt->format('Y/m/d');
    $acceptedTime = $acceptedAt->format('H:i:s');

    $rulesAcceptedMessage = getTranslation('rules_accepted_message', $langCode, [
        'accepted_date' => $acceptedDate,
        'accepted_time' => $acceptedTime
    ]);
    $proceedMessage = getTranslation('proceed_message', $langCode);

    $keyboard = [
        [['text' => getTranslation('button_change_language', $langCode), 'callback_data' => 'change_language']],
        [['text' => getTranslation('button_review_accepted_rules', $langCode), 'callback_data' => 'review_rules']],
        [['text' => getTranslation('button_proceed_to_next_bot', $langCode), 'url' => $nextBotLink]]
    ];
    $replyMarkup = ['inline_keyboard' => $keyboard];

    $fullMessage = $rulesAcceptedMessage . "\n\n" . $proceedMessage;

    if ($currentMessageId) {
        editMessageText($chatId, $currentMessageId, $fullMessage, $replyMarkup);
    } else {
        // اگر currentMessageId وجود ندارد، یک پیام جدید ارسال می‌کنیم
        sendMessage($chatId, $fullMessage, $replyMarkup);
    }
}

/**
 * تابعی برای نمایش منوی انتخاب زبان
 * @param int $chatId ID چت
 * @param int $messageId ID پیام برای ویرایش
 * @param string $langCode کد زبان فعلی کاربر
 */
function showLanguageMenu($chatId, $messageId, $langCode) {
    // در این تابع، پیام قبلی حذف نمی‌شود، بلکه مستقیماً ویرایش می‌شود.
    $languageButtons = [
        ['text' => 'English 🇬🇧', 'callback_data' => 'set_lang_en'],
        ['text' => 'فارسی 🇮🇷', 'callback_data' => 'set_lang_fa'],
        ['text' => 'Español 🇪🇸', 'callback_data' => 'set_lang_es'],
        ['text' => 'Deutsch 🇩🇪', 'callback_data' => 'set_lang_de'],
        ['text' => 'Français 🇫🇷', 'callback_data' => 'set_lang_fr'],
        ['text' => '中文 🇨🇳', 'callback_data' => 'set_lang_zh'],
        ['text' => 'العربية 🇸🇦', 'callback_data' => 'set_lang_ar'],
        ['text' => 'Русский 🇷🇺', 'callback_data' => 'set_lang_ru'],
        ['text' => 'Português 🇵🇹', 'callback_data' => 'set_lang_pt'],
        ['text' => 'Bahasa Indonesia 🇮🇩', 'callback_data' => 'set_lang_id'],
        ['text' => 'Türkçe 🇹🇷', 'callback_data' => 'set_lang_tr'],
        ['text' => 'Italiano 🇮🇹', 'callback_data' => 'set_lang_it']
    ];

    $keyboard = [];
    $row = [];
    $buttonsPerRow = 2;

    foreach ($languageButtons as $button) {
        $row[] = $button;
        if (count($row) === $buttonsPerRow) {
            $keyboard[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $keyboard[] = $row;
    }

    $replyMarkup = ['inline_keyboard' => $keyboard];
    editMessageText($chatId, $messageId, getTranslation('select_language_prompt', $langCode), $replyMarkup);
}


// دریافت ورودی از تلگرام
$update = json_decode(file_get_contents('php://input'), true);

// اگر هیچ به‌روزرسانی دریافت نشد، اسکریپت را متوقف کن
if (!$update) {
    die('No update received.');
}

$chatId = null;
$userId = null;
$messageText = null;
$callbackData = null;
$messageId = null;
$callbackQueryId = null; // اضافه کردن برای ذخیره callback_query_id

// بررسی نوع به‌روزرسانی (پیام یا کال‌بک کوئری)
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $messageText = $message['text'] ?? '';
    $messageId = $message['message_id'];
    // هرگاه یک پیام جدید دریافت شود، آخرین پیام ربات را حذف می‌کنیم تا چت تمیز بماند
    // این کار از شلوغی چت جلوگیری می‌کند.
    $user = getUser($userId);
    if ($user && $user['last_bot_message_id'] !== null) {
        deleteMessage($chatId, $user['last_bot_message_id']);
    }
} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $callbackData = $callbackQuery['data'];
    $messageId = $callbackQuery['message']['message_id'];
    $callbackQueryId = $callbackQuery['id']; // ذخیره callback_query_id
    // پاسخ به کال‌بک کوئری برای حذف نشانگر لودینگ
    telegramApiRequest('answerCallbackQuery', ['callback_query_id' => $callbackQueryId, 'show_alert' => false]);
} else {
    // اگر نوع به‌روزرسانی پشتیبانی نمی‌شود، اسکریپت را متوقف کن
    die('Unsupported update type.');
}

// دریافت اطلاعات کاربر از دیتابیس یا ایجاد کاربر جدید
$user = getUser($userId);
if (!$user) {
    createUser($userId);
    $user = getUser($userId); // مجدداً اطلاعات کاربر را پس از ایجاد دریافت می‌کنیم
}
$userLang = $user['language_code'];
$userTermsStatus = $user['terms_status'];


// منطق اصلی ربات بر اساس وضعیت پذیرش قوانین کاربر
if ($userTermsStatus === 'accepted') {
    // اگر کاربر قوانین را پذیرفته باشد
    if ($messageText === '/start') {
        showAcceptedMenu($chatId, $userId, $userLang);
    } elseif ($callbackData === 'change_language') {
        showLanguageMenu($chatId, $messageId, $userLang);
    } elseif (strpos($callbackData, 'set_lang_') === 0) {
        $newLangCode = substr($callbackData, 9);
        updateUserLanguage($userId, $newLangCode);
        $userLang = $newLangCode;
        // پس از تغییر زبان، منوی پذیرفته شده را دوباره نمایش می‌دهیم
        showAcceptedMenu($chatId, $userId, $userLang, $messageId);
    } elseif ($callbackData === 'review_rules') {
        // بازگرداندن کاربر به منوی قوانین بدون تغییر وضعیت پذیرش
        showTermsMenu($chatId, $userId, $userLang, $messageId);
    } elseif ($callbackData === 'back_to_accepted_menu') {
        // اگر کاربر از منوی قوانین به منوی پذیرفته شده بازگردد
        showAcceptedMenu($chatId, $userId, $userLang, $messageId);
    }
    // پیام‌های متنی اضافی از کاربر در وضعیت Accepted را حذف می‌کنیم
    elseif ($messageText) {
        deleteMessage($chatId, $messageId);
    }
    die(); // پس از پردازش، اسکریپت را متوقف کن
}

// اگر کاربر هنوز قوانین را نپذیرفته است (وضعیت initial یا read_rules)
if ($callbackData) {
    if ($callbackData === 'accept_rules') {
        updateTermsStatus($userId, 'accepted'); // مستقیماً به وضعیت پذیرفته شده تغییر می دهیم
        showAcceptedMenu($chatId, $userId, $userLang, $messageId);
    } elseif ($callbackData === 'change_language') {
        showLanguageMenu($chatId, $messageId, $userLang);
    } elseif (strpos($callbackData, 'set_lang_') === 0) {
        $newLangCode = substr($callbackData, 9);
        updateUserLanguage($userId, $newLangCode);
        $userLang = $newLangCode;
        // پس از تغییر زبان، منوی قوانین را دوباره نمایش می‌دهیم
        showTermsMenu($chatId, $userId, $userLang, $messageId);
    } elseif ($callbackData === 'back_to_accepted_menu') {
        // این حالت نباید در وضعیت initial یا read_rules رخ دهد، اما برای احتیاط:
        showAcceptedMenu($chatId, $userId, $userLang, $messageId);
    }
} elseif ($messageText) {
    if ($messageText === '/start') {
        if ($userTermsStatus === 'initial') {
            updateTermsStatus($userId, 'read_rules');
        }
        showTermsMenu($chatId, $userId, $userLang);
    } else {
        // حذف پیام‌های متنی غیر از /start برای جلوگیری از اسپم و ارسال اخطار
        deleteMessage($chatId, $messageId);
        $progressStatusText = getTranslation('status_' . $userTermsStatus, $userLang);
        sendMessage($chatId, getTranslation('message_deleted', $userLang, ['progress_status' => $progressStatusText]));
        // پس از حذف پیام، منوی قوانین را دوباره نمایش می‌دهیم تا کاربر بتواند ادامه دهد
        showTermsMenu($chatId, $userId, $userLang);
    }
}
?>
