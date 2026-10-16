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

jsonRes(['success' => false, 'message' => 'إجراء غير معروف'], 400);
