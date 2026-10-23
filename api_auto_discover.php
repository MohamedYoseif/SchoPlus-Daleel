<?php
/**
 * SchoPlus Daleel — Autonomous School Discovery API Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';

$pdo = db();

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

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
