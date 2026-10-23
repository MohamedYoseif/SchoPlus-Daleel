<?php
/**
 * SchoPlus Daleel — WhatsApp Webhook Endpoint
 * نقطة استقبال الرسائل الواردة من سيرفر واتساب دليل المدارس
 */

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../DaleelBotService.php';

$expectedToken = 'schoplus_daleel_sec_token_9918';

// التحقق من هوية السيرفر عبر الـ Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$provided = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : $authHeader;
if ($provided === '') {
    $provided = (string)($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? ''));
}

if (!hash_equals($expectedToken, $provided)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Webhook Token']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON Payload']);
    exit;
}

$phone = trim((string)($data['from'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$chatId = $data['chatId'] ?? null;
$fromMe = !empty($data['fromMe']);

// تجاهل رسائل الحالة أو الرسائل المرسلة من نفس البوت لتجنب الحلقات اللانهائية
if ($fromMe || $phone === '' || $phone === 'status@broadcast' || $chatId === 'status@broadcast') {
    echo json_encode(['status' => 'ignored']);
    exit;
}

try {
    DaleelBotService::handleIncoming($phone, $message, $chatId, $data);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
