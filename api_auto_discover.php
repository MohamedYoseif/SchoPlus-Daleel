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
$isAuthAdmin = (!empty($_SESSION['daleel_master_admin']) || !empty($_SESSION['school_id']) || !empty($_SESSION['daleel_admin_authorized']));

if (!$isAuthAdmin && $providedKey !== $secretKey && php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'هذه الخدمة مخصصة للاستخدام الآلي في الخلفية فقط وغير متاحة للزوار.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_POST['action'] ?? ($inputData['action'] ?? 'single')));

if ($action === 'batch' || !empty($_POST['schools']) || !empty($inputData['schools'])) {
    $rawSchools = $_POST['schools'] ?? ($inputData['schools'] ?? []);
    if (is_string($rawSchools)) {
        $rawSchools = json_decode($rawSchools, true) ?: [];
    }
    if (empty($rawSchools)) {
        echo json_encode([
            'success' => false,
            'error' => 'قائمة المدارس فارغة أو غير صالحة للاستيراد بالدفعة.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $result = SchoolAutoResearcher::importVerifiedSchoolBatch($pdo, $rawSchools);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'حدث خطأ أثناء استيراد الدفعة: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$query = trim((string)($_POST['query'] ?? ($inputData['query'] ?? '')));
$state = trim((string)($_POST['state'] ?? ($inputData['state'] ?? '')));
$city = trim((string)($_POST['city'] ?? ($inputData['city'] ?? '')));
$district = trim((string)($_POST['district'] ?? ($inputData['district'] ?? '')));
$address = trim((string)($_POST['address'] ?? ($inputData['address'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ($inputData['phone'] ?? '')));
$whatsapp = trim((string)($_POST['whatsapp'] ?? ($inputData['whatsapp'] ?? $phone)));
$googleMapUrl = trim((string)($_POST['google_map_url'] ?? ($inputData['google_map_url'] ?? '')));
$facebook = trim((string)($_POST['facebook'] ?? ($inputData['facebook'] ?? '')));
$youtube = trim((string)($_POST['youtube'] ?? ($inputData['youtube'] ?? '')));
$tiktok = trim((string)($_POST['tiktok'] ?? ($inputData['tiktok'] ?? '')));
$instagram = trim((string)($_POST['instagram'] ?? ($inputData['instagram'] ?? '')));
$telegram = trim((string)($_POST['telegram'] ?? ($inputData['telegram'] ?? '')));
$website = trim((string)($_POST['website'] ?? ($inputData['website'] ?? '')));
$description = trim((string)($_POST['description'] ?? ($inputData['description'] ?? '')));
$schoolType = trim((string)($_POST['school_type'] ?? ($inputData['school_type'] ?? '')));
$establishedYear = trim((string)($_POST['established_year'] ?? ($inputData['established_year'] ?? '')));

if (empty($query)) {
    echo json_encode([
        'success' => false,
        'error' => 'يرجى إدخال اسم المدرسة للبحث والاستكشاف.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = SchoolAutoResearcher::autoDiscoverAndRegister(
        $pdo,
        $query,
        $state,
        $city,
        $schoolType ?: null,
        $phone ?: null,
        $whatsapp ?: null,
        $googleMapUrl ?: null,
        $district ?: null,
        $address ?: null,
        $description ?: null,
        $facebook ?: null,
        $website ?: null,
        $establishedYear ?: null,
        $youtube ?: null,
        $tiktok ?: null,
        $instagram ?: null,
        $telegram ?: null
    );

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
