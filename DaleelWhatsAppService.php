<?php
/**
 * SchoPlus Daleel — WhatsApp Server Client Service
 * خدمة الربط والتحكم بسيرفر واتساب دليل المدارس (Port 8085)
 */

require_once __DIR__ . '/config.php';

class DaleelWhatsAppService {

    private static string $baseUrl = 'http://127.0.0.1:8085';
    private static string $token = 'schoplus_daleel_sec_token_9918';

    /**
     * جلب رابط السيرفر الأساسي
     */
    public static function getBaseUrl(): string {
        $db = db();
        try {
            $stmt = $db->prepare("SELECT setting_value FROM daleel_settings WHERE setting_key = 'whatsapp_server_url' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val && trim($val) !== '') {
                return rtrim(trim($val), '/');
            }
        } catch (Throwable $e) {}
        return self::$baseUrl;
    }

    /**
     * إرسال طلب HTTP إلى سيرفر الواتساب
     */
    private static function request(string $endpoint, string $method = 'GET', array $data = []): array {
        $url = self::getBaseUrl() . $endpoint;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::$token,
            'X-Webhook-Token: ' . self::$token
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'تعذر الاتصال بسيرفر الواتساب: ' . $err, 'http_code' => $httpCode];
        }

        $decoded = json_decode((string)$response, true);
        if (is_array($decoded)) {
            $decoded['http_code'] = $httpCode;
            return $decoded;
        }

        return ['success' => false, 'error' => 'استجابة غير متوقعة من السيرفر', 'raw' => $response, 'http_code' => $httpCode];
    }

    /**
     * فحص حالة الاتصال بالواتساب
     */
    public static function getStatus(): array {
        return self::request('/api/daleel_session/check-connection-session');
    }

    /**
     * فحص رقم هاتف: هل لديه حساب واتساب نشط؟
     */
    public static function checkNumber(string $phone): array {
        return self::request('/api/daleel_session/check-number', 'POST', ['phone' => $phone]);
    }

    /**
     * فحص مجموعة أرقام دفعة واحدة
     */
    public static function checkBulkNumbers(array $phones): array {
        return self::request('/api/daleel_session/check-bulk-numbers', 'POST', ['phones' => $phones]);
    }

    /**
     * إرسال رسالة نصية مباشرة لمدرسة أو مستخدم
     */
    public static function sendMessage(string $phone, string $message, ?string $chatId = null): array {
        return self::request('/api/daleel_session/send-message', 'POST', [
            'phone' => $phone,
            'message' => $message,
            'chatId' => $chatId
        ]);
    }

    /**
     * إرسال ملف / صورة / مستند
     */
    public static function sendFile(string $phone, string $fileBase64, string $filename, string $caption = ''): array {
        return self::request('/api/daleel_session/send-file', 'POST', [
            'phone' => $phone,
            'file' => $fileBase64,
            'filename' => $filename,
            'message' => $caption
        ]);
    }

    /**
     * إعادة تشغيل جلسة الواتساب
     */
    public static function restart(): array {
        return self::request('/api/daleel_session/restart', 'POST');
    }

    /**
     * تسجيل الخروج من الواتساب
     */
    public static function logout(): array {
        return self::request('/api/daleel_session/logout-session', 'POST');
    }

    /**
     * تسجيل الرسائل في قاعدة بيانات الدليل
     */
    public static function logMessage(?int $schoolId, string $direction, string $phone, ?string $chatId, string $message, ?string $intent = null): void {
        try {
            $pdo = db();
            // التأكد من وجود جدول سجل رسائل الواتساب
            $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_whatsapp_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                school_id BIGINT UNSIGNED NULL,
                direction ENUM('in', 'out') NOT NULL DEFAULT 'in',
                phone VARCHAR(50) NOT NULL,
                chat_id VARCHAR(100) NULL,
                message TEXT NOT NULL,
                media_url VARCHAR(255) NULL,
                status VARCHAR(50) DEFAULT 'delivered',
                intent VARCHAR(50) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone),
                INDEX idx_school (school_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $stmt = $pdo->prepare("INSERT INTO daleel_whatsapp_messages (school_id, direction, phone, chat_id, message, intent) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$schoolId, $direction, $phone, $chatId, $message, $intent]);
        } catch (Throwable $e) {}
    }
}
