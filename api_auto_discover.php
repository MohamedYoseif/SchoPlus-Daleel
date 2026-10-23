<?php
/**
 * SchoPlus Daleel — Autonomous School Discovery API Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';

$pdo = db();

// Background / Admin Security Check (Not accessible to public visitors)
$secretKey = "schoplus_daleel_deploy_sec_4412f";
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$providedKey = $_GET['key'] ?? ($_POST['key'] ?? ($inputData['key'] ?? ''));
$isAuthAdmin = (!empty($_SESSION['school_id']));

if (!$isAuthAdmin && $providedKey !== $secretKey && php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'هذه الخدمة مخصصة للاستخدام الآلي في الخلفية فقط وغير متاحة للزوار.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string)($_POST['query'] ?? ($inputData['query'] ?? '')));
$state = trim((string)($_POST['state'] ?? ($inputData['state'] ?? '')));
$city = trim((string)($_POST['city'] ?? ($inputData['city'] ?? '')));

if (empty($query)) {
    echo json_encode([
        'success' => false,
        'error' => 'يرجى إدخال اسم المدرسة للبحث والاستكشاف.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = SchoolAutoResearcher::autoDiscoverAndRegister($pdo, $query, $state, $city);

    // If a new school was created, export master seed in background
    if (!empty($result['created'])) {
        @exec('php ' . escapeshellarg(__DIR__ . '/export_master_seed.php') . ' > /dev/null 2>&1 &');
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ أثناء الاستكشاف الآلي: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
