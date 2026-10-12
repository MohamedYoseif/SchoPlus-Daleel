<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * API Direct Registration (تسجيل وتوثيق فوري للمدارس دون مغادرة المحادثة الذكية)
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

$pdo = db();

$raw = file_get_contents('php://input');
if ($raw) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
}
$input = json_decode($raw, true) ?: $_POST;

$name = trim((string)($input['name'] ?? ''));
$state = trim((string)($input['state'] ?? 'ولاية الخرطوم'));
$city = trim((string)($input['city'] ?? $state));
$phone = trim((string)($input['phone'] ?? ''));
$whatsapp = trim((string)($input['whatsapp'] ?? $phone));
$owner_name = trim((string)($input['owner_name'] ?? 'مدير المدرسة'));
$owner_title = trim((string)($input['owner_title'] ?? 'مدير المدرسة'));

if (mb_strlen($name, 'UTF-8') < 3) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'اسم المدرسة مطلوب (3 أحرف على الأقل).'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($phone) < 7) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'رقم الهاتف/الواتساب مطلوب للمتابعة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Check if already registered by phone or name
$checkStmt = $pdo->prepare("SELECT id, name, slug, state, city, owner_name, owner_phone, status FROM daleel_schools WHERE owner_phone = ? OR phone = ? OR name = ? LIMIT 1");
$checkStmt->execute([$phone, $phone, $name]);
$existing = $checkStmt->fetch();

if ($existing) {
    $slug = $existing['slug'];
    $profileUrl = "https://daleel.schoplus.sd/school/{$slug}";
    $shortUrl = "https://daleel.schoplus.sd/s/{$slug}";
    $loginUrl = "https://daleel.schoplus.sd/login.php";

    echo json_encode([
        'success' => true,
        'already_registered' => true,
        'message' => 'مدرستكم مسجلة وموثقة مسبقاً في دليل المدارس السودانية المعتمدة!',
        'school' => [
            'id' => (int)$existing['id'],
            'name' => $existing['name'],
            'slug' => $slug,
            'profile_url' => $profileUrl,
            'short_url' => $shortUrl,
            'login_url' => $loginUrl,
            'owner_name' => $existing['owner_name'],
            'owner_phone' => $existing['owner_phone'],
            'temp_password' => null
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Generate clean unique slug
$baseSlug = 'school-' . substr(bin2hex(random_bytes(4)), 0, 6);
$slug = $baseSlug;
$counter = 1;
while (true) {
    $sCheck = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ?");
    $sCheck->execute([$slug]);
    if (!$sCheck->fetch()) {
        break;
    }
    $slug = $baseSlug . '-' . $counter;
    $counter++;
}

// 3. Generate initial temporary password
$tempPassword = 'sp' . (strlen($phone) >= 4 ? substr($phone, -4) : rand(1000, 9999));
$hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

$country = trim((string)($input['country'] ?? 'السودان')) ?: 'السودان';

try {
    $insStmt = $pdo->prepare("INSERT INTO daleel_schools (
        name, slug, country, school_type, gender_type, curriculum, stages,
        state, city, phone, whatsapp,
        status, owner_name, owner_title, owner_phone, password,
        rating_avg, reviews_count, views_count, is_schoplus_client, is_verified, created_at, updated_at
    ) VALUES (
        :name, :slug, :country, 'private', 'mixed', 'sudanese', '[\"kindergarten\",\"primary\",\"intermediate\",\"secondary\"]',
        :state, :city, :phone, :whatsapp,
        'pending', :owner_name, :owner_title, :owner_phone, :password,
        5.00, 0, 1, 0, 0, NOW(), NOW()
    )");

    $insStmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':country' => $country,
        ':state' => $state,
        ':city' => $city,
        ':phone' => $phone,
        ':whatsapp' => $whatsapp ?: $phone,
        ':owner_name' => $owner_name,
        ':owner_title' => $owner_title,
        ':owner_phone' => $phone,
        ':password' => $hashedPassword,
    ]);

    $newId = (int)$pdo->lastInsertId();
    $profileUrl = "https://daleel.schoplus.sd/school/{$slug}";
    $shortUrl = "https://daleel.schoplus.sd/s/{$slug}";
    $loginUrl = "https://daleel.schoplus.sd/login.php";

    echo json_encode([
        'success' => true,
        'already_registered' => false,
        'pending_approval' => true,
        'message' => 'تم استلام طلب تسجيل مدرستكم بنجاح! يتم التدقيق والاعتماد الرسمي خلال 24 ساعة، وبعدها يمكنكم مشاركة الصفحة واستقبال الاستفسارات وطلبات التسجيل وإضافة الأخبار والفعاليات.',
        'school' => [
            'id' => $newId,
            'name' => $name,
            'slug' => $slug,
            'profile_url' => $profileUrl,
            'short_url' => $shortUrl,
            'login_url' => $loginUrl,
            'owner_name' => $owner_name,
            'owner_phone' => $phone,
            'temp_password' => $tempPassword
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء حفظ بيانات المدرسة: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
