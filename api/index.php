<?php
/**
 * SchoPlus Schools Directory — Mobile Native REST API
 * Clean JSON API for Native Android Application
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SudanGeography.php';

$pdo = db();
$action = trim($_GET['action'] ?? 'home');

function jsonRes(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function formatSchool(array $s, PDO $pdo): array {
    $baseUrl = 'https://daleel.schoplus.sd';
    $logo = !empty($s['logo_path']) ? $baseUrl . '/' . ltrim($s['logo_path'], '/') : $baseUrl . '/assets/images/default-school-logo.svg';
    $cover = !empty($s['cover_path']) ? $baseUrl . '/' . ltrim($s['cover_path'], '/') : '';

    $stages = !empty($s['stages']) ? json_decode($s['stages'], true) : [];
    $stages = is_array($stages) ? $stages : [];

    $facilities = !empty($s['facilities']) ? json_decode($s['facilities'], true) : [];
    $facilities = is_array($facilities) ? $facilities : [];

    $gallery = !empty($s['gallery']) ? json_decode($s['gallery'], true) : [];
    $galleryUrls = [];
    if (is_array($gallery)) {
        foreach ($gallery as $img) {
            if (is_string($img) && trim($img) !== '') {
                $galleryUrls[] = $baseUrl . '/' . ltrim(trim($img), '/');
            } elseif (is_array($img) && !empty($img['path']) && is_string($img['path'])) {
                $galleryUrls[] = $baseUrl . '/' . ltrim(trim($img['path']), '/');
            } elseif (is_array($img) && !empty($img['url']) && is_string($img['url'])) {
                $galleryUrls[] = $baseUrl . '/' . ltrim(trim($img['url']), '/');
            }
        }
    }

    $branches = !empty($s['branches_data']) ? json_decode($s['branches_data'], true) : [];
    $branches = is_array($branches) ? $branches : [];

    $rank = getSchoolDirectoryRank($pdo, (int)$s['id'], !empty($s['directory_rank']) ? (int)$s['directory_rank'] : null);

    return [
        'id'                  => (int)$s['id'],
        'name'                => (string)$s['name'],
        'name_en'             => (string)($s['name_en'] ?? ''),
        'slug'                => (string)$s['slug'],
        'school_type'         => (string)($s['school_type'] ?? 'خاصة'),
        'gender_type'         => (string)($s['gender_type'] ?? 'مختلط'),
        'curriculum'          => (string)($s['curriculum'] ?? 'سوداني'),
        'established_year'    => !empty($s['established_year']) ? (int)$s['established_year'] : null,
        'state'               => (string)$s['state'],
        'city'                => (string)$s['city'],
        'district'            => (string)($s['district'] ?? ''),
        'address'             => (string)($s['address'] ?? ''),
        'phone'               => (string)($s['phone'] ?? ''),
        'whatsapp'            => (string)($s['whatsapp'] ?? ''),
        'email'               => (string)($s['email'] ?? ''),
        'website'             => (string)($s['website'] ?? ''),
        'google_map_url'      => (string)($s['google_map_url'] ?? ''),
        'logo'                => $logo,
        'cover'               => $cover,
        'description'         => (string)($s['description'] ?? ''),
        'vision'              => (string)($s['vision'] ?? ''),
        'mission'             => (string)($s['mission'] ?? ''),
        'approx_fees'         => (string)($s['approx_fees'] ?? ''),
        'fees_type'           => (string)($s['fees_type'] ?? 'average'),
        'stage_fees'          => !empty($s['stage_fees']) ? (json_decode($s['stage_fees'], true) ?: []) : [],
        'fee_discounts'       => !empty($s['fee_discounts']) ? (json_decode($s['fee_discounts'], true) ?: []) : [],
        'is_schoplus_client'  => (bool)$s['is_schoplus_client'],
        'is_verified'         => (bool)$s['is_verified'],
        'is_featured'         => (bool)$s['is_featured'],
        'rating_avg'          => (float)($s['rating_avg'] ?? 0),
        'reviews_count'       => (int)($s['reviews_count'] ?? 0),
        'views_count'         => (int)($s['views_count'] ?? 0),
        'directory_rank'      => $rank,
        'stages'              => $stages,
        'facilities'          => $facilities,
        'gallery'             => $galleryUrls,
        'branches'            => $branches,
        'share_url'           => $baseUrl . '/school.php?id=' . $s['id']
    ];
}

// -------------------------------------------------------------
// ACTION: HOME
// -------------------------------------------------------------
if ($action === 'home') {
    // 1. Stats
    $totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
    $schoplusCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
    $statesCount = (int)$pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: 18;

    // 2. States list with school counts
    $stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $statesList = [];
    foreach (SudanGeography::states() as $st) {
        $statesList[] = [
            'name'  => $st,
            'count' => (int)($stateCounts[$st] ?? 0)
        ];
    }

    // 3. Featured / SchoPlus Schools
    $schoplusRows = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1 ORDER BY RAND() LIMIT 6")->fetchAll();
    $schoplusSchools = array_map(fn($r) => formatSchool($r, $pdo), $schoplusRows);

    // 4. Top Rated Schools
    $topRatedRows = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND rating_avg >= 4.0 ORDER BY rating_avg DESC, reviews_count DESC LIMIT 6")->fetchAll();
    if (empty($topRatedRows)) {
        $topRatedRows = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY RAND() LIMIT 6")->fetchAll();
    }
    $topRatedSchools = array_map(fn($r) => formatSchool($r, $pdo), $topRatedRows);

    // 5. Latest Schools
    $latestRows = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6")->fetchAll();
    $latestSchools = array_map(fn($r) => formatSchool($r, $pdo), $latestRows);

    // 6. Latest News & Announcements (5 latest)
    $newsRows = $pdo->query("
        SELECT n.*, s.name as school_name, s.logo_path as school_logo
        FROM daleel_school_news n
        JOIN daleel_schools s ON n.school_id = s.id
        WHERE n.is_active = 1 AND s.status = 'approved'
        ORDER BY n.published_at DESC LIMIT 5
    ")->fetchAll();

    $newsList = [];
    $baseUrl = 'https://daleel.schoplus.sd';
    foreach ($newsRows as $nw) {
        $img = !empty($nw['image_path']) ? $baseUrl . '/' . ltrim($nw['image_path'], '/') : '';
        $sLogo = !empty($nw['school_logo']) ? $baseUrl . '/' . ltrim($nw['school_logo'], '/') : $baseUrl . '/assets/images/default-school-logo.svg';
        $newsList[] = [
            'id'           => (int)$nw['id'],
            'school_id'    => (int)$nw['school_id'],
            'school_name'  => (string)$nw['school_name'],
            'school_logo'  => $sLogo,
            'branch_name'  => (string)($nw['branch_name'] ?? ''),
            'title'        => (string)$nw['title'],
            'content'      => strip_tags((string)$nw['content']),
            'image'        => $img,
            'published_at' => (string)$nw['published_at'],
            'views_count'  => (int)$nw['views_count']
        ];
    }

    jsonRes([
        'success' => true,
        'data'    => [
            'stats' => [
                'total_schools'   => $totalSchools,
                'schoplus_schools'=> $schoplusCount,
                'states_count'    => $statesCount
            ],
            'states'           => $statesList,
            'schoplus_schools' => $schoplusSchools,
            'top_rated_schools'=> $topRatedSchools,
            'latest_schools'   => $latestSchools,
            'latest_news'      => $newsList
        ]
    ]);
}

// -------------------------------------------------------------
// ACTION: SCHOOLS (SEARCH & FILTER)
// -------------------------------------------------------------
if ($action === 'schools') {
    $q       = trim($_GET['q'] ?? '');
    $state   = trim($_GET['state'] ?? '');
    $city    = trim($_GET['city'] ?? '');
    $stage   = trim($_GET['stage'] ?? '');
    $type    = trim($_GET['type'] ?? '');
    $curriculum = trim($_GET['curriculum'] ?? '');
    $isSchoplus = !empty($_GET['schoplus_only']);
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = min(50, max(5, (int)($_GET['limit'] ?? 15)));
    $offset  = ($page - 1) * $limit;

    $where = ["status = 'approved'"];
    $params = [];

    if ($q !== '') {
        $where[] = "(name LIKE ? OR name_en LIKE ? OR district LIKE ? OR address LIKE ? OR description LIKE ?)";
        $term = "%{$q}%";
        $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
    }
    if ($state !== '') {
        $where[] = "state = ?";
        $params[] = $state;
    }
    if ($city !== '') {
        $where[] = "city = ?";
        $params[] = $city;
    }
    if ($stage !== '') {
        $where[] = "stages LIKE ?";
        $params[] = "%\"{$stage}\"%";
    }
    if ($type !== '') {
        $where[] = "school_type = ?";
        $params[] = $type;
    }
    if ($curriculum !== '') {
        $where[] = "curriculum = ?";
        $params[] = $curriculum;
    }
    if ($isSchoplus) {
        $where[] = "is_schoplus_client = 1";
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE {$whereClause}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY is_featured DESC, is_schoplus_client DESC, rating_avg DESC, id ASC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $schools = array_map(fn($r) => formatSchool($r, $pdo), $rows);

    jsonRes([
        'success' => true,
        'data'    => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => ceil($total / $limit),
            'schools'     => $schools
        ]
    ]);
}

// -------------------------------------------------------------
// ACTION: SCHOOL DETAILS
// -------------------------------------------------------------
if ($action === 'school') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonRes(['success' => false, 'message' => 'معرف المدرسة غير صالح'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? AND status = 'approved'");
    $stmt->execute([$id]);
    $s = $stmt->fetch();

    if (!$s) {
        jsonRes(['success' => false, 'message' => 'المدرسة غير موجودة أو قيد المراجعة'], 404);
    }

    // Increment views count
    $pdo->prepare("UPDATE daleel_schools SET views_count = views_count + 1 WHERE id = ?")->execute([$id]);

    $school = formatSchool($s, $pdo);

    // Fetch school news
    $newsStmt = $pdo->prepare("SELECT * FROM daleel_school_news WHERE school_id = ? AND is_active = 1 ORDER BY published_at DESC LIMIT 10");
    $newsStmt->execute([$id]);
    $newsRows = $newsStmt->fetchAll();

    $baseUrl = 'https://daleel.schoplus.sd';
    $news = [];
    foreach ($newsRows as $nw) {
        $img = !empty($nw['image_path']) ? $baseUrl . '/' . ltrim($nw['image_path'], '/') : '';
        $news[] = [
            'id'           => (int)$nw['id'],
            'title'        => (string)$nw['title'],
            'branch_name'  => (string)($nw['branch_name'] ?? ''),
            'content'      => strip_tags((string)$nw['content']),
            'html_content' => (string)$nw['content'],
            'image'        => $img,
            'published_at' => (string)$nw['published_at'],
            'views_count'  => (int)$nw['views_count']
        ];
    }
    $school['news'] = $news;

    jsonRes([
        'success' => true,
        'data'    => $school
    ]);
}

// -------------------------------------------------------------
// ACTION: NEWS FEED
// -------------------------------------------------------------
if ($action === 'news') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 15)));
    $offset = ($page - 1) * $limit;

    $count = (int)$pdo->query("
        SELECT COUNT(*)
        FROM daleel_school_news n
        JOIN daleel_schools s ON n.school_id = s.id
        WHERE n.is_active = 1 AND s.status = 'approved'
    ")->fetchColumn();

    $stmt = $pdo->query("
        SELECT n.*, s.name as school_name, s.logo_path as school_logo, s.state, s.city
        FROM daleel_school_news n
        JOIN daleel_schools s ON n.school_id = s.id
        WHERE n.is_active = 1 AND s.status = 'approved'
        ORDER BY n.published_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $rows = $stmt->fetchAll();

    $baseUrl = 'https://daleel.schoplus.sd';
    $newsList = [];
    foreach ($rows as $nw) {
        $img = !empty($nw['image_path']) ? $baseUrl . '/' . ltrim($nw['image_path'], '/') : '';
        $sLogo = !empty($nw['school_logo']) ? $baseUrl . '/' . ltrim($nw['school_logo'], '/') : $baseUrl . '/assets/images/default-school-logo.svg';
        $newsList[] = [
            'id'           => (int)$nw['id'],
            'school_id'    => (int)$nw['school_id'],
            'school_name'  => (string)$nw['school_name'],
            'school_logo'  => $sLogo,
            'state'        => (string)$nw['state'],
            'city'         => (string)$nw['city'],
            'branch_name'  => (string)($nw['branch_name'] ?? ''),
            'title'        => (string)$nw['title'],
            'content'      => strip_tags((string)$nw['content']),
            'html_content' => (string)$nw['content'],
            'image'        => $img,
            'published_at' => (string)$nw['published_at'],
            'views_count'  => (int)$nw['views_count']
        ];
    }

    jsonRes([
        'success' => true,
        'data'    => [
            'total'       => $count,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => ceil($count / $limit),
            'news'        => $newsList
        ]
    ]);
}

// -------------------------------------------------------------
// ACTION: FILTER OPTIONS (STATES, CITIES, STAGES)
// -------------------------------------------------------------
if ($action === 'meta') {
    $statesWithCities = SudanGeography::statesWithCities();
    $stages = SudanGeography::schoolStages();
    $types = SudanGeography::schoolTypes();

    jsonRes([
        'success' => true,
        'data'    => [
            'states_with_cities' => $statesWithCities,
            'stages'             => $stages,
            'types'              => $types
        ]
    ]);
}

// -------------------------------------------------------------
// ACTION: CHECK SLUG AVAILABILITY & SANITIZE
// -------------------------------------------------------------
if ($action === 'check_slug') {
    $rawSlug = trim((string)($_GET['slug'] ?? ''));
    $schoolId = (int)($_GET['school_id'] ?? 0);

    // Sanitize slug (replace spaces, commas, special chars with single hyphens, lowercase)
    $cleanSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($rawSlug));
    $cleanSlug = trim(preg_replace('/-+/', '-', $cleanSlug), '-');

    $reservedSlugs = ['index.php', 'index', 'home', 'schools', 'school', 's', 'admin', 'api', 'dashboard', 'login', 'register', 'logout'];

    if (strlen($cleanSlug) < 3) {
        jsonRes([
            'success'    => true,
            'status'     => 'invalid',
            'available'  => false,
            'clean_slug' => $cleanSlug,
            'message'    => 'يجب أن يتكون الرابط من 3 أحرف إنجليزية أو أرقام على الأقل.'
        ]);
    }

    if (in_array($cleanSlug, $reservedSlugs, true)) {
        jsonRes([
            'success'    => true,
            'status'     => 'reserved',
            'available'  => false,
            'clean_slug' => $cleanSlug,
            'message'    => 'هذا الرابط محجوز للنظام، يرجى اختيار رابط آخر.'
        ]);
    }

    // Check if this slug belongs to the current school
    if ($schoolId > 0) {
        $currChk = $pdo->prepare("SELECT id FROM daleel_schools WHERE id = ? AND slug = ? LIMIT 1");
        $currChk->execute([$schoolId, $cleanSlug]);
        if ($currChk->fetch()) {
            jsonRes([
                'success'    => true,
                'status'     => 'current',
                'available'  => true,
                'clean_slug' => $cleanSlug,
                'message'    => 'هذا هو رابط مدرستكم المعتمد حالياً.'
            ]);
        }
    }

    // Check if taken by another school
    $chk = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? AND id != ? LIMIT 1");
    $chk->execute([$cleanSlug, $schoolId]);
    $taken = (bool)$chk->fetch();

    if ($taken) {
        $counter = 1;
        $suggestion = $cleanSlug . '-' . $counter;
        while (true) {
            $chk2 = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? AND id != ? LIMIT 1");
            $chk2->execute([$suggestion, $schoolId]);
            if (!$chk2->fetch()) break;
            $counter++;
            $suggestion = $cleanSlug . '-' . $counter;
        }

        jsonRes([
            'success'    => true,
            'status'     => 'taken',
            'available'  => false,
            'clean_slug' => $cleanSlug,
            'suggestion' => $suggestion,
            'message'    => 'الرابط (' . $cleanSlug . ') مستخدم بالفعل لمدرسة أخرى.'
        ]);
    }

    jsonRes([
        'success'    => true,
        'status'     => 'available',
        'available'  => true,
        'clean_slug' => $cleanSlug,
        'message'    => 'الرابط متاح للاستخدام!'
    ]);
}

// -------------------------------------------------------------
// ACTION: SUBMIT INQUIRY / ADMISSION REQUEST
// -------------------------------------------------------------
if ($action === 'inquiry') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!is_array($postData)) {
        $postData = $_POST;
    }

    $schoolId     = (int)($postData['school_id'] ?? 0);
    $parentName   = trim((string)($postData['parent_name'] ?? ''));
    $parentPhone  = trim((string)($postData['parent_phone'] ?? ''));
    $parentEmail  = trim((string)($postData['parent_email'] ?? ''));
    $studentName  = trim((string)($postData['student_name'] ?? ''));
    $studentGender= trim((string)($postData['student_gender'] ?? ''));
    $studentStage = trim((string)($postData['student_stage'] ?? ''));
    $studentGrade = trim((string)($postData['student_grade'] ?? ''));
    $branchName   = trim((string)($postData['branch_name'] ?? ''));
    $needsTrans   = !empty($postData['needs_transportation']) ? 1 : 0;
    $message      = trim((string)($postData['message'] ?? ''));

    if ($schoolId <= 0) {
        jsonRes(['success' => false, 'message' => 'يرجى تحديد المدرسة المطلوبة.'], 400);
    }
    if ($parentName === '') {
        jsonRes(['success' => false, 'message' => 'يرجى كتابة اسم ولي الأمر.'], 400);
    }
    if ($parentPhone === '') {
        jsonRes(['success' => false, 'message' => 'يرجى إدخال رقم الهاتف أو الواتساب للتواصل.'], 400);
    }

    // Verify school exists
    $schCheck = $pdo->prepare("SELECT id, name, phone, whatsapp FROM daleel_schools WHERE id = ? AND status = 'approved' LIMIT 1");
    $schCheck->execute([$schoolId]);
    $school = $schCheck->fetch();

    if (!$school) {
        jsonRes(['success' => false, 'message' => 'المدرسة المطلوبة غير موجودة أو غير معتمدة.'], 404);
    }

    $ins = $pdo->prepare("
        INSERT INTO daleel_school_inquiries (
            school_id, parent_name, parent_phone, parent_email,
            student_name, student_gender, student_stage, student_grade,
            branch_name, needs_transportation, message, status, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, 'new', NOW()
        )
    ");
    $ins->execute([
        $schoolId, $parentName, $parentPhone, $parentEmail ?: null,
        $studentName ?: null, $studentGender ?: null, $studentStage ?: null, $studentGrade ?: null,
        $branchName ?: null, $needsTrans, $message ?: null
    ]);

    jsonRes([
        'success' => true,
        'message' => 'تم إرسال طلبكم لإدارة مدرسة ' . $school['name'] . ' بنجاح! سيتم التواصل معكم قريباً.',
        'data'    => [
            'inquiry_id'     => (int)$pdo->lastInsertId(),
            'school_name'    => $school['name'],
            'school_phone'   => $school['phone'],
            'school_whatsapp'=> $school['whatsapp']
        ]
    ]);
}

// ==========================================
// 8. CHECK FOR IN-APP UPDATES (خاصية التحديثات)
// ==========================================
if ($action === 'check_update') {
    $currentVersion = trim((string)($_GET['version'] ?? '1.0.0'));
    $currentCode    = (int)($_GET['version_code'] ?? 1);

    // Read system version
    $sysVerFile = __DIR__ . '/../version.json';
    $sysVerData = file_exists($sysVerFile) ? json_decode(file_get_contents($sysVerFile), true) : [];
    $sysVersion = $sysVerData['version'] ?? '3.7.4';

    // App latest version specs
    $latestAppVersion = '1.2.0';
    $latestAppCode    = 4;
    $hasUpdate = ($currentCode < $latestAppCode) || version_compare($currentVersion, $latestAppVersion, '<');

    $apkPath = __DIR__ . '/../downloads/daleel-schoplus.apk';
    $fileSizeStr = file_exists($apkPath) ? round(filesize($apkPath) / (1024 * 1024), 1) . ' MB' : '5.1 MB';

    jsonRes([
        'success'             => true,
        'has_update'          => $hasUpdate,
        'current_version'     => $currentVersion,
        'latest_version'      => $latestAppVersion,
        'latest_version_code' => $latestAppCode,
        'system_version'      => $sysVersion,
        'release_date'        => '2026-10-17',
        'file_size'           => $fileSizeStr,
        'download_url'        => 'https://daleel.schoplus.sd/downloads/daleel-schoplus.apk',
        'release_notes'       => "• إطلاق تطبيق أندرويد الأصلي فائق الخفة والسرعة (5 ميغابايت فقط).\n• توافق شامل مع أجهزة شاومي وكافة إصدارات أندرويد الحديثة والقديمة.\n• دعم كامل لرفع الشعار والصور، ومراسلات الواتساب، والاتصال المباشر.\n• شاشة بداية سريعة مع فحص الاتصال بالإنترنت وإعادة المحاولة التلقائية.",
        'is_critical'         => false
    ]);
}

// ==========================================
// 9. SCHOOL OWNER LOGIN (تسجيل دخول مدراء المدارس)
// ==========================================
if ($action === 'school_login') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!is_array($postData)) {
        $postData = $_POST;
    }

    $identifier = trim((string)($postData['identifier'] ?? ''));
    $password   = (string)($postData['password'] ?? '');

    if ($identifier === '' || $password === '') {
        jsonRes(['success' => false, 'message' => 'يرجى إدخال رقم الهاتف أو البريد الإلكتروني وكلمة المرور.'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE (owner_phone = ? OR owner_email = ? OR phone = ? OR email = ?) LIMIT 1");
    $stmt->execute([$identifier, $identifier, $identifier, $identifier]);
    $school = $stmt->fetch();

    if (!$school || !password_verify($password, $school['password'])) {
        jsonRes(['success' => false, 'message' => 'بيانات الدخول غير صحيحة. يرجى التحقق من رقم الهاتف وكلمة المرور.'], 401);
    }

    // Generate session token (HMAC)
    $tokenSecret = 'schoplus_daleel_token_sec_2026';
    $tokenPayload = $school['id'] . '|' . $school['slug'] . '|' . time();
    $tokenSig = hash_hmac('sha256', $tokenPayload, $tokenSecret);
    $authToken = base64_encode($tokenPayload . '|' . $tokenSig);

    // Inquiries stats
    $inqStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as unread FROM daleel_school_inquiries WHERE school_id = ?");
    $inqStmt->execute([(int)$school['id']]);
    $inqStats = $inqStmt->fetch() ?: ['total' => 0, 'unread' => 0];

    jsonRes([
        'success'   => true,
        'message'   => 'مرحباً بك! تم تسجيل الدخول بنجاح.',
        'token'     => $authToken,
        'school'    => formatSchool($school, $pdo),
        'stats'     => [
            'total_inquiries' => (int)($inqStats['total'] ?? 0),
            'new_inquiries'   => (int)($inqStats['unread'] ?? 0),
            'views_count'     => (int)($school['views_count'] ?? 0),
            'rating_avg'      => (float)($school['rating_avg'] ?? 0),
            'reviews_count'   => (int)($school['reviews_count'] ?? 0)
        ]
    ]);
}

// Token helper validator
function verifySchoolToken(PDO $pdo): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_REQUEST['token'] ?? '';
    if (str_starts_with($authHeader, 'Bearer ')) {
        $authHeader = substr($authHeader, 7);
    }
    $authHeader = trim($authHeader);
    if ($authHeader === '') {
        jsonRes(['success' => false, 'message' => 'غير مصرح (رمز الدخول مفقود).'], 401);
    }

    $decoded = base64_decode($authHeader);
    $parts = explode('|', $decoded);
    if (count($parts) < 4) {
        jsonRes(['success' => false, 'message' => 'رمز الجلسة غير صالح.'], 401);
    }

    $schoolId = (int)$parts[0];
    $slug = $parts[1];
    $time = (int)$parts[2];
    $sig = $parts[3];

    $tokenSecret = 'schoplus_daleel_token_sec_2026';
    $expected = hash_hmac('sha256', "$schoolId|$slug|$time", $tokenSecret);
    if (!hash_equals($expected, $sig)) {
        jsonRes(['success' => false, 'message' => 'جلسة غير صالحة أو تم التلاعب بها.'], 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? LIMIT 1");
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
    if (!$school) {
        jsonRes(['success' => false, 'message' => 'المدرسة غير موجودة.'], 404);
    }

    return $school;
}

// ==========================================
// 10. SCHOOL PORTAL DATA (لوحة إدارة المدرسة)
// ==========================================
if ($action === 'school_portal') {
    $school = verifySchoolToken($pdo);
    $schoolId = (int)$school['id'];

    // Inquiries
    $inqStmt = $pdo->prepare("SELECT * FROM daleel_school_inquiries WHERE school_id = ? ORDER BY created_at DESC LIMIT 100");
    $inqStmt->execute([$schoolId]);
    $inquiries = $inqStmt->fetchAll(PDO::FETCH_ASSOC);

    // News
    $newsStmt = $pdo->prepare("SELECT * FROM daleel_news WHERE school_id = ? ORDER BY is_pinned DESC, published_at DESC LIMIT 50");
    $newsStmt->execute([$schoolId]);
    $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Reviews
    $revStmt = $pdo->prepare("SELECT * FROM daleel_reviews WHERE school_id = ? ORDER BY created_at DESC LIMIT 50");
    $revStmt->execute([$schoolId]);
    $reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

    // Counts
    $newInqCount = 0;
    foreach ($inquiries as $iq) {
        if (($iq['status'] ?? 'new') === 'new') {
            $newInqCount++;
        }
    }

    jsonRes([
        'success'     => true,
        'school'      => formatSchool($school, $pdo),
        'inquiries'   => array_map(function($iq) {
            return [
                'id'                   => (int)$iq['id'],
                'parent_name'          => (string)$iq['parent_name'],
                'parent_phone'         => (string)$iq['parent_phone'],
                'parent_email'         => (string)($iq['parent_email'] ?? ''),
                'student_name'         => (string)($iq['student_name'] ?? ''),
                'student_gender'       => (string)($iq['student_gender'] ?? ''),
                'student_stage'        => (string)($iq['student_stage'] ?? ''),
                'student_grade'        => (string)($iq['student_grade'] ?? ''),
                'branch_name'          => (string)($iq['branch_name'] ?? ''),
                'needs_transportation' => (bool)$iq['needs_transportation'],
                'message'              => (string)($iq['message'] ?? ''),
                'status'               => (string)($iq['status'] ?? 'new'),
                'created_at'           => (string)$iq['created_at']
            ];
        }, $inquiries),
        'news'        => array_map(function($n) {
            return [
                'id'           => (int)$n['id'],
                'title'        => (string)$n['title'],
                'content'      => (string)strip_tags($n['content']),
                'category'     => (string)($n['category'] ?? 'general'),
                'is_pinned'    => (bool)($n['is_pinned'] ?? false),
                'published_at' => (string)($n['published_at'] ?? $n['created_at'])
            ];
        }, $news),
        'reviews'     => array_map(function($r) {
            return [
                'id'         => (int)$r['id'],
                'author'     => (string)($r['reviewer_name'] ?? 'ولي أمر'),
                'rating'     => (int)($r['rating'] ?? 5),
                'comment'    => (string)($r['comment'] ?? ''),
                'created_at' => (string)$r['created_at']
            ];
        }, $reviews),
        'stats'       => [
            'total_inquiries' => count($inquiries),
            'new_inquiries'   => $newInqCount,
            'total_news'      => count($news),
            'total_reviews'   => count($reviews),
            'views_count'     => (int)($school['views_count'] ?? 0),
            'rating_avg'      => (float)($school['rating_avg'] ?? 0)
        ]
    ]);
}

// ==========================================
// 11. UPDATE SCHOOL PROFILE (تعديل كامل بيانات المدرسة)
// ==========================================
if ($action === 'school_update_profile') {
    $school = verifySchoolToken($pdo);
    $schoolId = (int)$school['id'];

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $name         = trim((string)($data['name'] ?? $school['name']));
    $name_en      = trim((string)($data['name_en'] ?? $school['name_en']));
    $phone        = trim((string)($data['phone'] ?? $school['phone']));
    $whatsapp     = trim((string)($data['whatsapp'] ?? $school['whatsapp']));
    $email        = trim((string)($data['email'] ?? $school['email']));
    $website      = trim((string)($data['website'] ?? $school['website']));
    $state        = trim((string)($data['state'] ?? $school['state']));
    $city         = trim((string)($data['city'] ?? $school['city']));
    $district     = trim((string)($data['district'] ?? $school['district']));
    $address      = trim((string)($data['address'] ?? $school['address']));
    $mapUrl       = trim((string)($data['google_map_url'] ?? $school['google_map_url']));
    $desc         = trim((string)($data['description'] ?? $school['description']));
    $vision       = trim((string)($data['vision'] ?? $school['vision']));
    $mission      = trim((string)($data['mission'] ?? $school['mission']));
    $schoolType   = trim((string)($data['school_type'] ?? $school['school_type']));
    $genderType   = trim((string)($data['gender_type'] ?? $school['gender_type']));
    $curriculum   = trim((string)($data['curriculum'] ?? $school['curriculum']));
    $estYear      = !empty($data['established_year']) ? (int)$data['established_year'] : $school['established_year'];
    $approxFees   = trim((string)($data['approx_fees'] ?? $school['approx_fees']));
    $feesType     = trim((string)($data['fees_type'] ?? $school['fees_type']));

    $stages       = isset($data['stages']) && is_array($data['stages']) ? json_encode($data['stages'], JSON_UNESCAPED_UNICODE) : $school['stages'];
    $facilities   = isset($data['facilities']) && is_array($data['facilities']) ? json_encode($data['facilities'], JSON_UNESCAPED_UNICODE) : $school['facilities'];
    $stageFees    = isset($data['stage_fees']) && is_array($data['stage_fees']) ? json_encode($data['stage_fees'], JSON_UNESCAPED_UNICODE) : $school['stage_fees'];

    if ($name === '') {
        jsonRes(['success' => false, 'message' => 'اسم المدرسة لا يمكن أن يكون فارغاً.'], 400);
    }
    if ($phone === '') {
        jsonRes(['success' => false, 'message' => 'رقم هاتف المدرسة مطلوب.'], 400);
    }

    $upStmt = $pdo->prepare("
        UPDATE daleel_schools SET
            name = ?, name_en = ?, phone = ?, whatsapp = ?, email = ?, website = ?,
            state = ?, city = ?, district = ?, address = ?, google_map_url = ?,
            description = ?, vision = ?, mission = ?, school_type = ?, gender_type = ?,
            curriculum = ?, established_year = ?, approx_fees = ?, fees_type = ?,
            stages = ?, facilities = ?, stage_fees = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $upStmt->execute([
        $name, $name_en ?: null, $phone, $whatsapp ?: null, $email ?: null, $website ?: null,
        $state, $city, $district ?: null, $address ?: null, $mapUrl ?: null,
        $desc ?: null, $vision ?: null, $mission ?: null, $schoolType, $genderType,
        $curriculum, $estYear ?: null, $approxFees ?: null, $feesType ?: 'average',
        $stages, $facilities, $stageFees, $schoolId
    ]);

    // Refetch updated
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ?");
    $stmt->execute([$schoolId]);
    $updated = $stmt->fetch();

    jsonRes([
        'success' => true,
        'message' => 'تم حفظ وتحديث بيانات المدرسة بنجاح!',
        'school'  => formatSchool($updated, $pdo)
    ]);
}

// ==========================================
// 12. UPDATE INQUIRY STATUS (تحديث حالة طلب القبول)
// ==========================================
if ($action === 'school_inquiry_status') {
    $school = verifySchoolToken($pdo);
    $schoolId = (int)$school['id'];

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $inquiryId = (int)($data['inquiry_id'] ?? 0);
    $status    = trim((string)($data['status'] ?? 'contacted'));

    $allowedStatuses = ['new', 'contacted', 'enrolled', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        jsonRes(['success' => false, 'message' => 'حالة غير صالحة.'], 400);
    }

    $up = $pdo->prepare("UPDATE daleel_school_inquiries SET status = ? WHERE id = ? AND school_id = ?");
    $up->execute([$status, $inquiryId, $schoolId]);

    jsonRes([
        'success' => true,
        'message' => 'تم تحديث حالة الطلب بنجاح.',
        'status'  => $status
    ]);
}

// ==========================================
// 13. ADD SCHOOL NEWS (إضافة خبر جديد من الموبايل)
// ==========================================
if ($action === 'school_add_news') {
    $school = verifySchoolToken($pdo);
    $schoolId = (int)$school['id'];

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $title    = trim((string)($data['title'] ?? ''));
    $content  = trim((string)($data['content'] ?? ''));
    $category = trim((string)($data['category'] ?? 'general'));
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;

    if ($title === '') {
        jsonRes(['success' => false, 'message' => 'عنوان الخبر مطلوب.'], 400);
    }
    if ($content === '') {
        jsonRes(['success' => false, 'message' => 'محتوى الخبر مطلوب.'], 400);
    }

    $ins = $pdo->prepare("
        INSERT INTO daleel_news (school_id, title, content, category, is_pinned, published_at, created_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $ins->execute([$schoolId, $title, $content, $category, $isPinned]);

    jsonRes([
        'success' => true,
        'message' => 'تم نشر الخبر بنجاح!',
        'news_id' => (int)$pdo->lastInsertId()
    ]);
}

// ==========================================
// 14. DELETE SCHOOL NEWS (حذف خبر)
// ==========================================
if ($action === 'school_delete_news') {
    $school = verifySchoolToken($pdo);
    $schoolId = (int)$school['id'];

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $newsId = (int)($data['news_id'] ?? 0);
    $del = $pdo->prepare("DELETE FROM daleel_news WHERE id = ? AND school_id = ?");
    $del->execute([$newsId, $schoolId]);

    jsonRes([
        'success' => true,
        'message' => 'تم حذف الخبر بنجاح.'
    ]);
}

jsonRes(['success' => false, 'message' => 'إجراء غير معروف'], 400);

