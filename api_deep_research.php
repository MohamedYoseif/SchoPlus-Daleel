<?php
/**
 * SchoPlus Daleel — Autonomous Deep Multi-Platform Research Endpoint
 * استوديو الاستقصاء الرقمي الشامل (Google Maps + Facebook + YouTube + TikTok + Web)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';
require_once __DIR__ . '/SchoolBatchImporter.php';

$pdo = db();

// Security Check
$secretKey = "schoplus_daleel_deploy_sec_4412f";
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$providedKey = $_GET['key'] ?? ($_POST['key'] ?? ($inputData['key'] ?? ''));
$isAuthAdmin = (!empty($_SESSION['school_id']));

if (!$isAuthAdmin && $providedKey !== $secretKey && php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'هذه الخدمة مخصصة للاستخدام الإداري المعتمد فقط.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string)($_POST['query'] ?? ($inputData['query'] ?? '')));
$state = trim((string)($_POST['state'] ?? ($inputData['state'] ?? '')));
$city = trim((string)($_POST['city'] ?? ($inputData['city'] ?? '')));

if (empty($query)) {
    echo json_encode([
        'success' => false,
        'error' => 'يرجى إدخال اسم المدرسة للبدء في الاستقصاء.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cleanQ = SchoolAutoResearcher::cleanQuery($query);
$geo = SchoolAutoResearcher::inferGeography($cleanQ . ' ' . $city, $state ?: null, $city ?: null);

$finalCity = !empty($city) ? $city : $geo['city'];
$finalState = !empty($state) ? $state : '';
if (empty($finalState)) {
    $statesMap = SudanGeography::statesWithCities('السودان');
    foreach ($statesMap as $st => $citiesList) {
        if (in_array($finalCity, $citiesList, true)) {
            $finalState = $st;
            break;
        }
    }
}
if (empty($finalState)) {
    $finalState = $geo['state'];
}
$finalDistrict = !empty($geo['district']) && $geo['district'] !== $finalCity ? $geo['district'] : $finalCity;

$formattedName = SchoolAutoResearcher::formatOfficialName($cleanQ, $finalState, $finalCity, $finalDistrict);
$formattedNameEn = SchoolAutoResearcher::transliterateEnglish($formattedName);
$schoolType = SudanGeography::deduceSchoolType($formattedName, 'private');
$stages = SudanGeography::deduceStages($schoolType, $formattedName, null, '');
if (empty($stages)) {
    $stages = ($schoolType === 'kindergarten') ? ['kindergarten'] : ['primary', 'intermediate'];
}
$curriculum = (str_contains($formattedName, 'بريطاني') || str_contains($formattedName, 'كامبريدج') || str_contains($formattedName, 'عالمية')) ? 'british' : 'sudanese';

// Multi-Platform Direct Search Target Links
$searchQueryFull = $formattedName . ' ' . $finalCity . ' ' . $finalState . ' السودان';
$googleMapsSearchUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($searchQueryFull);
$facebookSearchUrl = 'https://www.facebook.com/search/top?q=' . urlencode($formattedName . ' ' . $finalCity);
$youtubeSearchUrl = 'https://www.youtube.com/results?search_query=' . urlencode($formattedName . ' ' . $finalCity . ' حفل تخريج فعاليات');
$tiktokSearchUrl = 'https://www.tiktok.com/search?q=' . urlencode($formattedName . ' ' . $finalCity);
$googleWebSearchUrl = 'https://www.google.com/search?q=' . urlencode('"' . $formattedName . '" ' . $finalCity . ' نتائج الشهادة دليل');

// Check if already in directory
$existsInDb = false;
$existingRecord = null;
list($searchClause, $searchParams) = buildUniversalSchoolSearchClause($cleanQ);
if (!empty($searchClause)) {
    $chkStmt = $pdo->prepare("SELECT id, name, slug, phone, whatsapp, google_map_url, facebook, youtube, tiktok, instagram, telegram, directory_rank FROM daleel_schools WHERE {$searchClause} LIMIT 1");
    $chkStmt->execute($searchParams);
    $existingRecord = $chkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingRecord) {
        $existsInDb = true;
    }
}

// Generate rich synthesized synopsis
$stagesArabic = array_map(function($st) {
    return SudanGeography::stageLabel($st);
}, $stages);
$stagesStr = implode('، ', $stagesArabic);
$addressParts = ['ولاية ' . $finalState, $finalCity];
if (!empty($finalDistrict) && $finalDistrict !== $finalCity) {
    $addressParts[] = 'حي ' . $finalDistrict;
}
$fullAddress = implode(' — ', $addressParts);

if ($schoolType === 'kindergarten') {
    $description = "صرح تربوي وتأهيلي متميز لمرحلة الطفولة المبكرة يقع في {$fullAddress}. يركز على تنمية المهارات الإدراكية والحركية واللغوية لدى الأطفال عبر مناهج تفاعلية حديثة وبيئة تربوية آمنة ومحفزة.";
} else {
    $description = "مؤسسة تعليمية وتربوية رائدة تقع في {$fullAddress}، تقدم برامجها الأكاديمية لمراحل ({$stagesStr}) وفق معايير الجودة والتميز. تحرص على توفير كادر تعليمي مؤهل، ومرافق تعليمية متطورة، مع الاهتمام بالأنشطة الطلابية والتحصيل الأكاديمي المتفوق في الامتحانات القومية.";
}

echo json_encode([
    'success' => true,
    'query' => $query,
    'formatted_name' => $formattedName,
    'formatted_name_en' => $formattedNameEn,
    'school_type' => $schoolType,
    'curriculum' => $curriculum,
    'stages' => $stages,
    'state' => $finalState,
    'city' => $finalCity,
    'district' => $finalDistrict,
    'address' => $fullAddress,
    'description' => $description,
    'exists_in_db' => $existsInDb,
    'existing_id' => $existingRecord ? (int)$existingRecord['id'] : null,
    'existing_rank' => $existingRecord ? (int)$existingRecord['directory_rank'] : null,
    'platforms' => [
        'google_maps' => [
            'label' => 'خرائط جوجل (Google Maps)',
            'icon' => 'fas fa-map-marked-alt',
            'color' => '#10B981',
            'search_url' => $googleMapsSearchUrl,
            'existing_url' => $existingRecord['google_map_url'] ?? null
        ],
        'facebook' => [
            'label' => 'فيسبوك (Facebook)',
            'icon' => 'fab fa-facebook',
            'color' => '#1877F2',
            'search_url' => $facebookSearchUrl,
            'existing_url' => $existingRecord['facebook'] ?? null
        ],
        'youtube' => [
            'label' => 'يوتيوب (YouTube)',
            'icon' => 'fab fa-youtube',
            'color' => '#DC2626',
            'search_url' => $youtubeSearchUrl,
            'existing_url' => $existingRecord['youtube'] ?? null
        ],
        'tiktok' => [
            'label' => 'تيك توك (TikTok)',
            'icon' => 'fab fa-tiktok',
            'color' => '#111827',
            'search_url' => $tiktokSearchUrl,
            'existing_url' => $existingRecord['tiktok'] ?? null
        ],
        'web_references' => [
            'label' => 'جوجل والمراجع (Google & Records)',
            'icon' => 'fas fa-globe',
            'color' => '#0284C7',
            'search_url' => $googleWebSearchUrl,
            'existing_url' => null
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
