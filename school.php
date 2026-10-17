<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Profile Page (صفحة الملف التعريفي للمدرسة)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SudanHistoricSchools.php';

$pdo = db();
$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

// Reserved slugs and file patterns that should never be treated as school profile
$reservedSlugs = [
    'index', 'index.php', 'home', 'schools', 'school', 'daleel', 'admin', 'api',
    'login', 'login.php', 'register', 'register.php', 'dashboard', 'dashboard.php',
    'logout', 'logout.php', 'search', 'favicon.ico', 'robots.txt', 'sitemap.xml',
    'deploy.php', 'config.php'
];

if (in_array(strtolower($slug), $reservedSlugs, true) || str_ends_with(strtolower($slug), '.php')) {
    header('Location: /');
    exit;
}

if (!$slug && !$id) {
    header('Location: /');
    exit;
}

// Fetch school
if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE slug = ?");
    $stmt->execute([$slug]);
    $school = $stmt->fetch();
    if (!$school) {
        // Smart alias lookup for renamed/updated slugs or Arabic queries
        $cleanSlug = str_replace('-', ' ', $slug);
        $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE slug LIKE ? OR name LIKE ? OR name_en LIKE ? LIMIT 1");
        $stmt->execute(["%{$slug}%", "%{$cleanSlug}%", "%{$cleanSlug}%"]);
        $school = $stmt->fetch();
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ?");
    $stmt->execute([$id]);
    $school = $stmt->fetch();
}

if (!$school) {
    http_response_code(404);
    $pageTitle = 'المدرسة غير موجودة';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="p-5 bg-white rounded-4 border border-light shadow-sm my-5" style="max-width: 600px; margin: auto;">
            <div class="display-1 text-muted mb-3"><i class="fas fa-school-flag"></i></div>
            <h2 class="fw-bold mb-3">عذراً، المدرسة غير موجودة</h2>
            <p class="text-muted mb-4">قد يكون الرابط الذي اتبعته غير صحيح أو تم إيقاف صفحة المدرسة مؤقتاً.</p>
            <a href="/" class="btn-luxury-primary"><i class="fas fa-home me-2"></i> العودة لدليل المدارس</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// Check permission if pending/rejected: only owner or CP admin preview can view
$currSchool = currentSchool();
$isOwner = $currSchool && ($currSchool['id'] === $school['id']);
$isAdminPreview = (isset($_GET['admin_preview']) && in_array($_GET['admin_preview'], ['schoplus_cp_secret', '1'], true));

if ($school['status'] === 'suspended' && !$isAdminPreview) {
    http_response_code(403);
    $pageTitle = 'المدرسة موقوفة مؤقتاً';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="p-5 bg-white rounded-4 border border-danger shadow-sm my-5" style="max-width: 600px; margin: auto;">
            <div class="display-3 text-danger mb-3"><i class="fas fa-ban"></i></div>
            <h3 class="fw-bold mb-3">تم إيقاف ظهور المدرسة مؤقتاً</h3>
            <p class="text-muted mb-4"><?= e($school['rejection_reason'] ?: 'تم إيقاف ظهور صفحة هذه المدرسة مؤقتاً بقرار إداري. للمراجعة يرجى التواصل مع إدارة دليل المدارس.') ?></p>
            <a href="/" class="btn-luxury-outline"><i class="fas fa-arrow-right me-2"></i> تصفح دليل المدارس</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($school['status'] !== 'approved' && !$isOwner && !$isAdminPreview) {
    http_response_code(403);
    $pageTitle = 'المدرسة بانتظار التأكيد';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="p-5 bg-white rounded-4 border border-warning shadow-sm my-5" style="max-width: 600px; margin: auto;">
            <div class="display-3 text-warning mb-3"><i class="fas fa-hourglass-half"></i></div>
            <h3 class="fw-bold mb-3">ملف المدرسة بانتظار الاعتماد</h3>
            <p class="text-muted mb-4">هذه المدرسة مسجلة لدينا وحالياً قيد المراجعة والتدقيق بواسطة فريق الدليل وسوف تظهر للجمهور فور اكتمال الاعتماد.</p>
            <a href="/" class="btn-luxury-outline"><i class="fas fa-arrow-right me-2"></i> تصفح دليل المدارس</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// Increment view count
$pdo->prepare("UPDATE daleel_schools SET views_count = views_count + 1 WHERE id = ?")->execute([$school['id']]);

// Check Historic Heritage Status & Dossier
$isHistoricSchool = SudanHistoricSchools::isHistoric($school);
$historicDossier = $isHistoricSchool ? SudanHistoricSchools::getDossier($school) : null;

// Handle Add Review
$reviewSuccess = false;
$reviewError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    if (!csrf_verify()) {
        $reviewError = 'انتهت صلاحية الجلسة، يرجى المحاولة مرة أخرى.';
    } else {
        $reviewerName = trim($_POST['reviewer_name'] ?? '');
        $reviewerPhone = trim($_POST['reviewer_phone'] ?? '');
        $reviewerRole = trim($_POST['reviewer_role'] ?? 'parent');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim($_POST['comment'] ?? '');

        if (!$reviewerName || !$comment) {
            $reviewError = 'يرجى كتابة الاسم والتعليق كاملاً.';
        } else {
            $revStmt = $pdo->prepare("INSERT INTO daleel_school_reviews 
                (school_id, reviewer_name, reviewer_phone, reviewer_role, rating, comment, is_approved, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
            $revStmt->execute([$school['id'], $reviewerName, $reviewerPhone, $reviewerRole, $rating, $comment]);

            // Update school rating avg & count
            $statStmt = $pdo->prepare("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM daleel_school_reviews WHERE school_id = ? AND is_approved = 1");
            $statStmt->execute([$school['id']]);
            $stats = $statStmt->fetch();
            
            $pdo->prepare("UPDATE daleel_schools SET reviews_count = ?, rating_avg = ? WHERE id = ?")->execute([
                (int)$stats['cnt'],
                round((float)$stats['avg_rating'], 2),
                $school['id']
            ]);

            $reviewSuccess = true;
            // refresh school data
            $school['reviews_count'] = (int)$stats['cnt'];
            $school['rating_avg'] = round((float)$stats['avg_rating'], 2);
        }
    }
}

// Handle Add Inquiry / Admission Request
$inquirySuccess = false;
$inquiryError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inquiry') {
    if (!csrf_verify()) {
        $inquiryError = 'انتهت صلاحية الجلسة، يرجى المحاولة مرة أخرى.';
    } else {
        $parentName = trim($_POST['parent_name'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $studentName = trim($_POST['student_name'] ?? '');
        $studentGender = trim($_POST['student_gender'] ?? '');
        $studentStage = trim($_POST['student_stage'] ?? '');
        $studentGrade = trim($_POST['student_grade'] ?? '');
        $previousSchool = trim($_POST['previous_school'] ?? '');
        $branchName = trim($_POST['branch_name'] ?? '');
        $needsTransportation = !empty($_POST['needs_transportation']) ? 1 : 0;
        $message = trim($_POST['message'] ?? '');

        if (!$parentName || !$parentPhone) {
            $inquiryError = 'يرجى كتابة اسم ولي الأمر ورقم الهاتف للتواصل.';
        } else {
            $inqStmt = $pdo->prepare("INSERT INTO daleel_school_inquiries 
                (school_id, parent_name, parent_phone, parent_email, student_name, student_gender, student_stage, student_grade, previous_school, branch_name, needs_transportation, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
            $inqStmt->execute([
                $school['id'], $parentName, $parentPhone, $parentEmail ?: null,
                $studentName ?: null, $studentGender ?: null, $studentStage ?: null, $studentGrade ?: null,
                $previousSchool ?: null, $branchName ?: null, $needsTransportation, $message ?: null
            ]);
            $inquirySuccess = true;
        }
    }
}

// Handle Historic School Community Vote (تصويت الأهالي والمجتمع لترقية المدرسة لصرح عريق)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote_historic') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'message' => 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مجدداً.']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    $sessionId = session_id();

    // 1. Check if voted via 1-year Cookie: sp_voted_historic_{schoolId}
    $cookieName = 'sp_voted_historic_' . $school['id'];
    $hasVotedCookie = !empty($_COOKIE[$cookieName]);

    // 2. Check if voted in the last 1 year (or 365 days) from same IP or Session in DB
    $checkVote = $pdo->prepare("SELECT id FROM daleel_school_historic_votes 
        WHERE school_id = ? AND (session_id = ? OR (ip_address IS NOT NULL AND ip_address = ?))
        AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY) LIMIT 1");
    $checkVote->execute([$school['id'], $sessionId, $clientIp]);

    if ($hasVotedCookie || $checkVote->fetch()) {
        // Refresh cookie for 1 year if missing
        if (!$hasVotedCookie) {
            setcookie($cookieName, '1', time() + (365 * 24 * 3600), '/', '', false, true);
        }

        echo json_encode([
            'success' => false,
            'message' => 'لقد قمت بالتصويت بالفعل لهذه المدرسة مسبقاً (التصويت متاح مرة واحدة سنوياً). شكراً لمشاركتك ووفائك!',
            'already_voted' => true,
            'votes_count' => (int)($school['historic_votes'] ?? 0),
            'is_historic' => isSchoolHistoric($school)
        ]);
        exit;
    }

    // Insert vote
    $insVote = $pdo->prepare("INSERT INTO daleel_school_historic_votes (school_id, ip_address, user_agent, session_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $insVote->execute([$school['id'], $clientIp, $userAgent, $sessionId]);

    // Set 1-Year persistent Cookie (365 days)
    setcookie($cookieName, '1', time() + (365 * 24 * 3600), '/', '', false, true);

    // Update school votes count
    $updSchool = $pdo->prepare("UPDATE daleel_schools SET historic_votes = historic_votes + 1 WHERE id = ?");
    $updSchool->execute([$school['id']]);

    // Re-fetch updated votes and check threshold (500)
    $vStmt = $pdo->prepare("SELECT historic_votes, is_historic, established_year FROM daleel_schools WHERE id = ?");
    $vStmt->execute([$school['id']]);
    $updatedRow = $vStmt->fetch();
    $newVotes = (int)($updatedRow['historic_votes'] ?? 0);

    // If votes hit 500+, automatically award historic tag
    if ($newVotes >= 500 && empty($updatedRow['is_historic'])) {
        $pdo->prepare("UPDATE daleel_schools SET is_historic = 1, historic_badge_reason = 'تصويت مجتمعي تجاوز 500 صوت' WHERE id = ?")
            ->execute([$school['id']]);
        $updatedRow['is_historic'] = 1;
    }

    // Update current $school in-memory for this request
    $school['historic_votes'] = $newVotes;
    if (!empty($updatedRow['is_historic'])) {
        $school['is_historic'] = 1;
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم تسجيل تصويتك بنجاح كصرح تعليمي عريق! شكراً لمساهمتك في توثيق التاريخ التعليمي.',
        'votes_count' => $newVotes,
        'is_historic' => isSchoolHistoric($school)
    ]);
    exit;
}

// Handle Claim School (طلب توثيق ملكية المدرسة المضافة مسبقاً)
$claimSuccess = false;
$claimError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_school') {
    if (!csrf_verify()) {
        $claimError = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
    } else {
        $claimantName = trim($_POST['claimant_name'] ?? '');
        $claimantTitle = trim($_POST['claimant_title'] ?? '');
        $claimantPhone = trim($_POST['claimant_phone'] ?? '');
        $claimantEmail = trim($_POST['claimant_email'] ?? '');
        $claimNotes = trim($_POST['notes'] ?? '');

        if (!$claimantName || !$claimantTitle || !$claimantPhone) {
            $claimError = 'يرجى إكمال اسم المسؤول، المسمى الوظيفي، ورقم الهاتف للتواصل.';
        } else {
            $claimDocPath = null;
            if (!empty($_FILES['verification_doc']['name']) && $_FILES['verification_doc']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['verification_doc']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                if (in_array($ext, $allowed, true)) {
                    $upDir = __DIR__ . '/uploads/claims';
                    if (!is_dir($upDir)) { @mkdir($upDir, 0755, true); }
                    $fileName = 'claim_' . $school['id'] . '_' . time() . '.' . $ext;
                    $dest = $upDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['verification_doc']['tmp_name'], $dest)) {
                        $claimDocPath = 'uploads/claims/' . $fileName;
                    }
                }
            }

            $claimStmt = $pdo->prepare("INSERT INTO daleel_school_claims 
                (school_id, claimant_name, claimant_title, claimant_phone, claimant_email, verification_doc_path, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $claimStmt->execute([
                $school['id'], $claimantName, $claimantTitle, $claimantPhone,
                $claimantEmail ?: null, $claimDocPath, $claimNotes ?: null
            ]);
            $claimSuccess = true;
        }
    }
}

// Fetch News
$newsStmt = $pdo->prepare("SELECT * FROM daleel_school_news WHERE school_id = ? AND is_active = 1 ORDER BY published_at DESC, id DESC");
$newsStmt->execute([$school['id']]);
$newsList = $newsStmt->fetchAll();

// Fetch Reviews
$reviewsStmt = $pdo->prepare("SELECT * FROM daleel_school_reviews WHERE school_id = ? AND is_approved = 1 ORDER BY id DESC");
$reviewsStmt->execute([$school['id']]);
$reviewsList = $reviewsStmt->fetchAll();

// Facilities, Stages, Gallery & Optional Tabs (Branches, Activities, Teachers)
$facilities = json_decode($school['facilities'] ?? '[]', true) ?: [];
$stages = json_decode($school['stages'] ?? '[]', true) ?: [];
$stageFeesList = json_decode($school['stage_fees'] ?? '[]', true) ?: [];
$feesType = $school['fees_type'] ?? 'average';
$feeDiscountsList = json_decode($school['fee_discounts'] ?? '[]', true) ?: [];
$galleryList = json_decode($school['gallery'] ?? '[]', true) ?: [];
$branchesList = json_decode($school['branches_data'] ?? '[]', true) ?: [];
$activitiesList = json_decode($school['activities_data'] ?? '[]', true) ?: [];
$teachersList = json_decode($school['teachers_data'] ?? '[]', true) ?: [];

$optionalTabsCfg = json_decode($school['optional_tabs'] ?? '{}', true);
if (!is_array($optionalTabsCfg)) $optionalTabsCfg = [];
$showBranchesTab = (isset($optionalTabsCfg['branches']) ? (bool)$optionalTabsCfg['branches'] : true) && !empty($branchesList);
$showActivitiesTab = (isset($optionalTabsCfg['activities']) ? (bool)$optionalTabsCfg['activities'] : true) && !empty($activitiesList);
$showTeachersTab = (isset($optionalTabsCfg['teachers']) ? (bool)$optionalTabsCfg['teachers'] : true) && !empty($teachersList);

// Admission Configuration (حالة وبنود وإجراءات القبول والتسجيل)
$admStatus = $school['admission_status'] ?? 'open';
$admStatusLabels = [
    'open'    => ['label' => 'متاح', 'badge' => 'باب القبول والتسجيل مفتوح', 'color' => '#1E5FA8', 'icon' => 'fa-check-circle'],
    'limited' => ['label' => 'مقاعد محدودة', 'badge' => 'مقاعد محدودة متبقية', 'color' => '#F59E0B', 'icon' => 'fa-exclamation-circle'],
    'early'   => ['label' => 'تسجيل مبكر', 'badge' => 'فتح باب التسجيل المبكر', 'color' => '#0284C7', 'icon' => 'fa-clock'],
    'closed'  => ['label' => 'مغلق', 'badge' => 'القبول والتسجيل مغلق مؤقتاً', 'color' => '#6B7280', 'icon' => 'fa-lock']
];
$currAdmCfg = $admStatusLabels[$admStatus] ?? $admStatusLabels['open'];

$admReqs = json_decode($school['admission_requirements'] ?? '[]', true);
if (!is_array($admReqs) || empty($admReqs)) {
    $admReqs = [
        'أصل وصورة شهادة الميلاد أو الرقم الوطني للطالب.',
        'شهادة النقل أو آخر تقرير أكاديمي معتمد (للصفوف المنقولة).',
        'عدد 4 صور فوتوغرافية حديثة بحجم جواز السفر.',
        'كرت التطعيم والشهادة الصحية (لمرحلة رياض الأطفال).'
    ];
}

$admSteps = json_decode($school['admission_steps'] ?? '[]', true);
if (!is_array($admSteps) || empty($admSteps)) {
    $admSteps = [
        'تقديم طلب القبول الإلكتروني بالاستمارة أدناه.',
        'تواصل فريق القبول وتحديد موعد المقابلة واختبار المستوى.',
        'إكمال تسليم المستندات واعتماد التسجيل وسداد الرسوم.',
        'استلام الزي المدرسي والجدول وتوزيع الفصول.'
    ];
}
$admNotes = trim($school['admission_notes'] ?? '');

// Non-empty social links only
$socialLinks = [];
if (!empty(trim($school['website'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['website']), 'label' => 'الموقع الإلكتروني', 'icon' => 'fas fa-globe', 'color' => '#475569'];
}
if (!empty(trim($school['facebook'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['facebook']), 'label' => 'صفحة فيسبوك', 'icon' => 'fab fa-facebook', 'color' => '#1877F2'];
}
if (!empty(trim($school['twitter'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['twitter']), 'label' => 'منصة إكس (تويتر)', 'icon' => 'fab fa-x-twitter', 'color' => '#0F172A'];
}
if (!empty(trim($school['youtube'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['youtube']), 'label' => 'قناة يوتيوب', 'icon' => 'fab fa-youtube', 'color' => '#DC2626'];
}
if (!empty(trim($school['telegram'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['telegram']), 'label' => 'قناة تيليجرام', 'icon' => 'fab fa-telegram', 'color' => '#0284C7'];
}
if (!empty(trim($school['instagram'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['instagram']), 'label' => 'حساب انستغرام', 'icon' => 'fab fa-instagram', 'color' => '#E1306C'];
}
if (!empty(trim($school['tiktok'] ?? ''))) {
    $socialLinks[] = ['url' => trim($school['tiktok']), 'label' => 'حساب تيك توك', 'icon' => 'fab fa-tiktok', 'color' => '#111827'];
}

$pageTitle = $school['name'];
$ogTitle = $school['name'] . ' | دليل المدارس الذكي';
$descSource = (!empty($school['description'])) ? $school['description'] : (!empty($historicDossier['summary']) ? $historicDossier['summary'] : ($school['name'] . ' — ' . $school['state'] . '، ' . $school['city'] . '. تصفح الرسوم، المرافق، وتواصل مباشرة.'));
$ogDesc = mb_substr(strip_tags($descSource), 0, 160);
$ogImage = !empty($school['logo_path']) ? (str_starts_with($school['logo_path'], 'http') ? $school['logo_path'] : 'https://daleel.schoplus.sd/' . ltrim($school['logo_path'], '/')) : (!empty($school['cover_path']) ? (str_starts_with($school['cover_path'], 'http') ? $school['cover_path'] : 'https://daleel.schoplus.sd/' . ltrim($school['cover_path'], '/')) : 'https://daleel.schoplus.sd/logo.png');
$ogUrl = 'https://daleel.schoplus.sd/school/' . urlencode($school['slug']);
require_once __DIR__ . '/header.php';
?>

<style>
/* ===== SCHOOL PROFILE — LUXURY REDESIGN ===== */
.sp-hero-cover {
    height: 290px;
    position: relative;
    overflow: hidden;
    background: #0B2A52;
    border-radius: 24px 24px 0 0;
}
.sp-hero-cover img.cover-img {
    width: 100%; height: 100%; object-fit: cover;
    position: absolute; inset: 0;
}
.sp-hero-cover .cover-overlay {
    position: absolute; inset: 0;
    background: rgba(11, 42, 82, 0.45);
    z-index: 1;
}
.sp-hero-cover .cover-pattern {
    display: none;
}

/* Safe Top Cover Badges Container */
.sp-cover-badges-bar {
    position: absolute;
    top: 18px; right: 24px; left: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    z-index: 3;
}
.sp-cover-rating-badge {
    background: rgba(11,42,82,0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 12px;
    padding: 6px 14px;
    color: #FFFFFF;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid rgba(255,255,255,0.18);
    white-space: nowrap;
}

/* Cover Identity Bar (Logo + School Name inside Cover) */
.sp-cover-identity-bar {
    position: absolute;
    bottom: 20px;
    right: 28px;
    left: 28px;
    z-index: 3;
}
.sp-cover-logo-box {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    background: #FFFFFF;
    padding: 6px;
    box-shadow: 0 10px 28px rgba(0,0,0,0.3);
    border: 3.5px solid rgba(255,255,255,0.95);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sp-cover-logo-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 14px;
}
.sp-cover-title-box {
    color: #FFFFFF;
}
.sp-cover-school-name {
    font-size: clamp(1.25rem, 2.4vw, 1.7rem);
    color: #FFFFFF;
    line-height: 1.25;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0,0,0,0.45);
}
.sp-cover-school-en {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.88);
    font-weight: 600;
    text-shadow: 0 1px 4px rgba(0,0,0,0.35);
}
.sp-cover-school-meta {
    font-size: 0.76rem;
    color: rgba(255,255,255,0.92);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    text-shadow: 0 1px 4px rgba(0,0,0,0.35);
}

.sp-profile-card {
    background: #FFFFFF;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 8px 32px rgba(11,42,82,0.06);
    border: 1px solid var(--border-subtle);
    border-top: none;
    padding: 0 32px 28px;
    position: relative;
}

.sp-logo-wrap {
    width: 116px; height: 116px;
    border-radius: 22px;
    background: #FFFFFF;
    padding: 6px;
    box-shadow: 0 10px 28px rgba(11,42,82,0.15);
    border: 3.5px solid #FFFFFF;
    margin-top: -58px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    position: relative; z-index: 5;
}
.sp-logo-wrap img { width: 100%; height: 100%; object-fit: contain; border-radius: 16px; }
.sp-logo-placeholder {
    width: 100%; height: 100%;
    border-radius: 16px;
    background: #0B2A52;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.6rem; font-weight: 900; color: #FFFFFF;
}

.sp-school-title {
    font-size: clamp(1.05rem, 2.2vw, 1.45rem);
    color: var(--brand-navy);
    line-height: 1.25;
    font-weight: 700;
}
.sp-school-subtitle {
    font-size: 0.74rem;
    color: var(--text-muted);
    font-weight: 600;
}
.sp-school-loc {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex; align-items: center; gap: 6px;
}

.sp-action-btns-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.sp-action-btns-wrap .sp-btn-action {
    padding: 6px 12px;
    font-size: 0.76rem;
}

.sp-quick-stats {
    display: flex; align-items: center; gap: 18px;
    flex-wrap: wrap;
    padding: 10px 0 0;
    border-top: 1px solid var(--border-subtle);
    margin-top: 14px;
}
.sp-stat-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.72rem; color: var(--text-muted); font-weight: 600;
}
.sp-stat-item strong { color: var(--text-main); font-weight: 700; }

.sp-tabs-nav {
    background: #FFFFFF;
    border: 1px solid var(--border-subtle);
    border-radius: 14px;
    padding: 4px;
    box-shadow: 0 2px 12px rgba(11,42,82,0.03);
    display: flex; gap: 5px;
    position: sticky; top: 70px; z-index: 40;
}
.sp-tab-btn {
    flex: 1;
    background: none; border: none;
    padding: 8px 8px;
    border-radius: 10px;
    font-weight: 600; font-size: 0.75rem;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.22s ease;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    white-space: nowrap;
}
.sp-tab-btn:hover { background: var(--bg-surface-soft); color: var(--brand-navy); }
.sp-tab-btn.active {
    background: var(--brand-navy);
    color: #FFFFFF;
    box-shadow: 0 3px 10px rgba(11,42,82,0.18);
}
.sp-tab-btn .tab-count {
    background: rgba(255,255,255,0.25);
    color: inherit;
    border-radius: 20px;
    padding: 2px 7px;
    font-size: 0.68rem;
    font-weight: 700;
}
.sp-tab-btn:not(.active) .tab-count {
    background: var(--bg-surface-soft);
    color: var(--text-muted);
}

.sp-section {
    background: #FFFFFF;
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    box-shadow: 0 2px 14px rgba(11,42,82,0.03);
    overflow: hidden;
    margin-bottom: 18px;
}
.sp-section-head {
    padding: 14px 18px 12px;
    border-bottom: 1px solid var(--border-subtle);
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    flex-wrap: wrap;
}
.sp-section-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.88rem; font-weight: 700; color: var(--brand-navy);
}
.sp-section-title .title-icon {
    width: 30px; height: 30px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; flex-shrink: 0;
}
.sp-section-body { padding: 18px; }

.vm-block {
    border-radius: 16px;
    padding: 16px 18px;
    position: relative; overflow: hidden;
}
.vm-vision { background: #EFF6FF; border: 1px solid #DBEAFE; border-right: 4px solid var(--brand-blue); }
.vm-mission { background: #FFFBEB; border: 1px solid #FEF3C7; border-right: 4px solid var(--brand-gold); }

.facility-chip {
    display: inline-flex; align-items: center; gap: 7px;
    background: #FFFFFF;
    border: 1.5px solid var(--border-subtle);
    border-radius: 12px;
    padding: 7px 14px;
    font-size: 0.74rem; font-weight: 700; color: var(--text-body);
    transition: all 0.2s ease;
}
.facility-chip:hover {
    background: #EFF6FF;
    border-color: #BFDBFE;
    color: var(--brand-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30,95,168,0.1);
}
.facility-chip i { color: var(--brand-blue); font-size: 0.88rem; }

.sp-inquiry-card {
    background: #FFFFFF;
    border: 1.5px solid #BFDBFE;
    border-radius: 20px; padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(30,95,168,0.05);
}
.sp-inquiry-card .form-control, .sp-inquiry-card .form-select {
    border: 1.5px solid var(--border-subtle);
    border-radius: 12px;
    padding: 7px 12px;
    font-size: 0.76rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.sp-inquiry-card .form-control:focus, .sp-inquiry-card .form-select:focus {
    border-color: var(--brand-blue);
    box-shadow: 0 0 0 3px rgba(30,95,168,0.12);
}

.sp-gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.sp-gallery-item {
    border-radius: 16px; overflow: hidden;
    aspect-ratio: 4/3;
    position: relative; cursor: pointer;
    background: var(--bg-surface-soft);
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.sp-gallery-item:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(11,42,82,0.14); }
.sp-gallery-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
.sp-gallery-item:hover img { transform: scale(1.08); }
.sp-gallery-overlay {
    position: absolute; inset: 0;
    background: rgba(11,42,82,0.8);
    opacity: 0;
    transition: opacity 0.25s ease;
    display: flex; align-items: flex-end; padding: 14px;
}
.sp-gallery-item:hover .sp-gallery-overlay { opacity: 1; }
.sp-gallery-caption { color: #FFFFFF; font-size: 0.72rem; font-weight: 700; line-height: 1.35; }
.sp-gallery-zoom {
    position: absolute; top: 10px; left: 10px;
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.92);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; color: var(--brand-navy);
    opacity: 0; transition: opacity 0.25s ease;
    backdrop-filter: blur(4px);
}
.sp-gallery-item:hover .sp-gallery-zoom { opacity: 1; }

.sp-news-card {
    border-radius: 16px;
    border: 1px solid var(--border-subtle);
    padding: 20px 22px;
    background: #FFFFFF;
    transition: all 0.22s ease;
    position: relative;
    overflow: hidden;
}
.sp-news-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    width: 4px; height: 100%;
    background: var(--brand-blue);
    border-radius: 0 16px 16px 0;
}
.sp-news-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(11,42,82,0.07); }
.sp-news-date {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--bg-surface-soft);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    padding: 3px 8px;
    font-size: 0.68rem; font-weight: 700; color: var(--text-muted);
}
.sp-news-content {
    font-size: 0.8rem;
    color: var(--text-body);
    line-height: 1.8;
}
.sp-news-content p { margin-bottom: 0.6rem; }
.sp-news-content h1, .sp-news-content h2, .sp-news-content h3, .sp-news-content h4 {
    color: var(--brand-navy);
    font-weight: 700;
    margin-top: 0.8rem;
    margin-bottom: 0.4rem;
}
.sp-news-content ul, .sp-news-content ol { padding-right: 1.4rem; margin-bottom: 0.6rem; }
.sp-news-content blockquote {
    border-right: 3px solid var(--brand-gold);
    padding-right: 12px;
    margin: 10px 0;
    color: #4B5563;
    font-style: italic;
    background: #F8FAFC;
    padding: 8px 12px;
    border-radius: 0 8px 8px 0;
}
.sp-news-content a { color: var(--brand-blue); text-decoration: underline; }
.sp-news-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }


.sp-review-card {
    border-radius: 16px;
    border: 1px solid var(--border-subtle);
    padding: 18px 20px;
    background: #FFFFFF;
    transition: all 0.22s ease;
}
.sp-review-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11,42,82,0.06); }
.sp-reviewer-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 1.05rem; color: #FFFFFF;
    flex-shrink: 0;
}
.sp-stars { display: inline-flex; gap: 3px; }
.sp-stars i { font-size: 0.78rem; }
.sp-school-reply {
    border-radius: 14px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-right: 4px solid var(--brand-blue);
    padding: 12px 14px;
    margin-top: 12px;
}

.sp-info-card {
    background: #FFFFFF;
    border: 1px solid var(--border-subtle);
    border-radius: 20px;
    box-shadow: 0 3px 18px rgba(11,42,82,0.035);
    overflow: hidden;
    margin-bottom: 20px;
}
.sp-info-card-head {
    padding: 14px 18px;
    background: var(--bg-surface-tint);
    border-bottom: 1px solid var(--border-subtle);
    display: flex; align-items: center; gap: 8px;
    font-size: 0.84rem; font-weight: 800; color: var(--brand-navy);
}
.sp-info-card-body { padding: 16px 18px; }

.sp-fact-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-subtle);
}
.sp-fact-row:last-child { border-bottom: none; padding-bottom: 0; }
.sp-fact-icon {
    width: 32px; height: 32px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; flex-shrink: 0;
}
.sp-fact-label { font-size: 0.68rem; color: var(--text-muted); font-weight: 700; display: block; }
.sp-fact-value { font-size: 0.78rem; color: var(--text-main); font-weight: 700; }

.sp-contact-row {
    display: flex; align-items: center; gap: 14px;
    padding: 11px 12px;
    border-bottom: 1px solid var(--border-subtle);
    text-decoration: none;
    border-radius: 12px;
    margin: 2px 0;
    transition: all 0.2s ease;
}
.sp-contact-row:last-child { border-bottom: none; }
.sp-contact-row:hover { background: var(--bg-surface-soft); transform: translateX(-3px); }
.sp-contact-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}

.sp-upgrade-cta {
    background: #0B2A52;
    border-radius: 20px; padding: 24px 22px; text-align: center; color: #FFFFFF;
    position: relative; overflow: hidden;
    box-shadow: 0 8px 28px rgba(11,42,82,0.18);
}
.sp-upgrade-cta::before {
    content: '';
    position: absolute; top: -30px; right: -30px;
    width: 110px; height: 110px; border-radius: 50%;
    background: rgba(245,160,32,0.22);
}
.sp-upgrade-cta::after {
    content: '';
    position: absolute; bottom: -20px; left: -20px;
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(255,255,255,0.06);
}

.sp-empty-state { text-align: center; padding: 48px 20px; }
.sp-empty-state .empty-icon {
    width: 74px; height: 74px; border-radius: 50%;
    background: var(--bg-surface-soft);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.7rem; color: var(--text-muted);
}
.sp-empty-state h6 { font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.sp-empty-state p { font-size: 0.85rem; color: var(--text-muted); margin: 0; }

.rating-bar-wrap { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.rating-bar { flex: 1; height: 7px; background: #EAE3D8; border-radius: 6px; overflow: hidden; }
.rating-bar-fill { height: 100%; border-radius: 6px; background: #F5A020; }

/* Custom Lightbox */
#spLightbox {
    position: fixed; inset: 0; background: rgba(8,16,32,0.94);
    z-index: 9999; display: none; align-items: center; justify-content: center;
    backdrop-filter: blur(8px);
}
#spLightbox.open { display: flex; }
#spLightbox img { max-width: 90vw; max-height: 82vh; border-radius: 16px; object-fit: contain; box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
#spLightbox .lb-caption { text-align: center; color: rgba(255,255,255,0.9); font-size: 0.92rem; font-weight: 700; margin-top: 14px; }
#spLightboxClose {
    position: absolute; top: 24px; left: 24px;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    color: #FFFFFF; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.2s ease;
}
#spLightboxClose:hover { background: rgba(255,255,255,0.28); }

/* ===== SPECIFIC MOBILE ENHANCEMENTS ===== */
.sp-mobile-bottom-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid #EAE3D8;
    padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -4px 20px rgba(11,42,82,0.12);
    z-index: 1000;
}
.sp-mob-bottom-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.76rem;
    text-decoration: none;
    padding: 10px 10px;
    border: none;
    transition: transform 0.15s ease;
}
.sp-mob-bottom-btn:active {
    transform: scale(0.96);
}
.sp-mob-wa {
    background: #0B2A52;
    color: #FFFFFF !important;
    flex: 1.1 1 auto;
}
.sp-mob-call {
    background: #FFFFFF;
    color: var(--brand-blue) !important;
    border: 1.5px solid var(--brand-blue) !important;
    flex: 0.9 1 auto;
}
.sp-mob-inquiry {
    background: #F5A020;
    color: #FFFFFF !important;
    flex: 1.2 1 auto;
}
.sp-mob-share {
    background: #F8FAFC;
    color: var(--text-main);
    border: 1.5px solid var(--border-subtle) !important;
    width: 42px; height: 42px;
    padding: 0; flex: 0 0 42px;
}

@media (max-width: 767px) {
    .school-profile-page {
        padding-bottom: 95px !important;
    }
    .sp-hero-cover {
        height: 250px !important;
        border-radius: 16px 16px 0 0 !important;
    }
    .sp-cover-badges-bar {
        top: 10px !important;
        right: 12px !important;
        left: 12px !important;
    }
    .badge-schoplus, .badge-verified, .sp-cover-rating-badge {
        font-size: 0.7rem !important;
        padding: 4px 8px !important;
    }
            .sp-cover-identity-bar {
        bottom: 12px !important;
        right: 14px !important;
        left: 14px !important;
    }
    .sp-cover-logo-box {
        width: 76px !important;
        height: 76px !important;
        border-radius: 16px !important;
        padding: 4px !important;
        border-width: 2.5px !important;
    }
    .sp-cover-school-name {
        font-size: 1.15rem !important;
        line-height: 1.3 !important;
    }
    .sp-cover-school-en {
        font-size: 0.72rem !important;
    }
    .sp-cover-school-meta {
        font-size: 0.7rem !important;
    }
.sp-profile-card {
        padding: 0 16px 20px !important;
        border-radius: 0 0 18px 18px !important;
    }
    .sp-logo-wrap {
        width: 88px !important;
        height: 88px !important;
        margin-top: -44px !important;
        border-radius: 18px !important;
        padding: 4px !important;
    }
    .sp-logo-placeholder {
        font-size: 2rem !important;
        border-radius: 14px !important;
    }
    .sp-school-title {
        font-size: 1.12rem !important;
        line-height: 1.3 !important;
        font-weight: 700 !important;
    }
    .sp-school-subtitle {
        font-size: 0.72rem !important;
    }
    .sp-school-loc {
        font-size: 0.7rem !important;
    }

    .sp-action-btns-wrap {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
        width: 100% !important;
        margin-top: 12px !important;
    }
    .sp-action-btns-wrap .sp-btn-action {
        width: 100% !important;
        justify-content: center !important;
        font-size: 0.75rem !important;
        padding: 8px 10px !important;
    }
    .sp-quick-stats {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
        padding-top: 12px !important;
        margin-top: 12px !important;
    }
    .sp-stat-item {
        background: #F8FAFC !important;
        border: 1px solid #DDE4ED !important;
        border-radius: 12px !important;
        padding: 8px 10px !important;
        font-size: 0.72rem !important;
        justify-content: flex-start !important;
    }
    .sp-tabs-nav {
        top: 60px !important;
        padding: 4px !important;
        border-radius: 14px !important;
        overflow-x: auto !important;
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: none !important;
        gap: 4px !important;
    }
    .sp-tabs-nav::-webkit-scrollbar { display: none !important; }
    .sp-tab-btn {
        flex: 0 0 auto !important;
        padding: 8px 13px !important;
        font-size: 0.74rem !important;
        border-radius: 10px !important;
    }
    .sp-section-head, .sp-section-body {
        padding: 16px !important;
    }
    .sp-inquiry-card {
        padding: 18px !important;
    }
    .sp-inquiry-card .form-control, .sp-inquiry-card .form-select {
        font-size: 16px !important;
    }
    .sp-gallery-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
    .sp-reviews-stat-col {
        border-inline-start: none !important;
        border-bottom: 1px solid var(--border-subtle) !important;
        padding-bottom: 18px !important;
        margin-bottom: 14px !important;
    }
}
</style>

<?php
// Rating distribution
$ratingDist = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
foreach ($reviewsList as $rev) { $ratingDist[(int)$rev['rating']]++; }
$totalRev = max(1, count($reviewsList));
?>

<div class="school-profile-page">

    <!-- Breadcrumb -->
    <div class="container px-3" style="padding-top: 16px; padding-bottom: 8px;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent p-0 mb-0 d-flex flex-nowrap overflow-x-auto text-nowrap" style="font-size: 0.72rem; scrollbar-width: none;">
                <li class="breadcrumb-item"><a href="/" class="text-decoration-none" style="color: var(--text-muted);"><i class="fas fa-home me-1"></i> دليل المدارس</a></li>
                <li class="breadcrumb-item"><a href="/?country=<?= urlencode($school['country'] ?? 'السودان') ?>" class="text-decoration-none" style="color: var(--text-muted);"><?= e($school['country'] ?? 'السودان') ?></a></li>
                <li class="breadcrumb-item"><a href="/?country=<?= urlencode($school['country'] ?? 'السودان') ?>&state=<?= urlencode($school['state']) ?>" class="text-decoration-none" style="color: var(--text-muted);"><?= e($school['state']) ?></a></li>
                <li class="breadcrumb-item"><a href="/?country=<?= urlencode($school['country'] ?? 'السودان') ?>&state=<?= urlencode($school['state']) ?>&city=<?= urlencode($school['city']) ?>" class="text-decoration-none" style="color: var(--text-muted);"><?= e($school['city']) ?></a></li>
                <li class="breadcrumb-item active fw-bold" style="color: var(--brand-navy);" aria-current="page"><?= e($school['name']) ?></li>
            </ol>
        </nav>
    </div>

    <?php if ($claimSuccess): ?>
    <div class="container px-3 mb-3" id="claimResultAlert">
        <div class="d-flex align-items-center gap-3 p-3 rounded-4 border shadow-sm" style="background: #ECFDF5; border-color: #A7F3D0 !important;">
            <i class="fas fa-check-circle text-success fs-3"></i>
            <div>
                <h6 class="fw-bold text-success mb-1" style="font-size: 0.85rem;">تم استلام طلب توثيق ملكية المدرسة بنجاح!</h6>
                <p class="text-muted mb-0" style="font-size: 0.75rem;">سيتواصل معك فريق التحقق عبر الهاتف أو واتساب للتحقق ونقل لوحة التحكم لحسابك المعتمد.</p>
            </div>
        </div>
    </div>
    <script>document.addEventListener("DOMContentLoaded", function() { document.getElementById("claimResultAlert")?.scrollIntoView({behavior:"smooth", block:"center"}); });</script>
    <?php elseif ($claimError): ?>
    <div class="container px-3 mb-3" id="claimResultAlert">
        <div class="d-flex align-items-center gap-3 p-3 rounded-4 border shadow-sm" style="background: #FEF2F2; border-color: #FECACA !important;">
            <i class="fas fa-exclamation-triangle text-danger fs-3"></i>
            <div>
                <h6 class="fw-bold text-danger mb-1" style="font-size: 0.85rem;">خطأ أثناء إرسال طلب التوثيق</h6>
                <p class="text-danger mb-0" style="font-size: 0.75rem; font-weight: 700;"><?= e($claimError) ?></p>
            </div>
        </div>
    </div>
    <script>document.addEventListener("DOMContentLoaded", function() { 
        document.getElementById("claimResultAlert")?.scrollIntoView({behavior:"smooth", block:"center"});
        var modalEl = document.getElementById("claimSchoolModal");
        if (modalEl && typeof bootstrap !== "undefined") {
            new bootstrap.Modal(modalEl).show();
        }
    });</script>
    <?php endif; ?>

    <?php if ($isOwner && $school['status'] === 'pending'): ?>
    <div class="container px-3 mb-3">
        <div class="d-flex align-items-center gap-3 p-3 rounded-4 border" style="background: #FFFBEB; border-color: #FCD34D !important;">
            <i class="fas fa-hourglass-half text-warning fs-4"></i>
            <div class="flex-grow-1">
                <span class="fw-bold text-dark small">معاينة أولية — هذا الملف بانتظار مراجعة الإدارة</span>
                <span class="text-muted small d-none d-sm-inline ms-2">سيظهر للجمهور فور اكتمال التدقيق والموافقة</span>
            </div>
            <a href="/dashboard.php" class="btn-luxury-primary btn-sm py-1 px-3 text-nowrap"><i class="fas fa-edit me-1"></i> لوحة الإدارة</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAdminPreview): ?>
    <div class="container px-3 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 rounded-4 border shadow-sm" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border-color: #93C5FD !important;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:36px;height:36px;border-radius:10px;background:#1E5FA8;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-size:16px">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <span class="fw-bold text-dark small">معاينة إدارية مباشرة من لوحة التحكم (SchoPlus CP)</span>
                    <span class="badge bg-<?= $school['status'] === 'approved' ? 'success' : ($school['status'] === 'pending' ? 'warning text-dark' : 'danger') ?> ms-2 small">
                        <?= $school['status'] === 'approved' ? 'معتمدة' : ($school['status'] === 'pending' ? 'قيد المراجعة والاعتماد' : 'موقوفة / مرفوضة') ?>
                    </span>
                    <span class="text-muted small d-none d-md-inline ms-2">هذه المعاينة خاصة بفريق الإدارة للمطابقة والتدقيق</span>
                </div>
            </div>
            <a href="https://cp.schoplus.sd/daleel_schools.php" class="btn btn-sm btn-primary py-1 px-3 fw-bold rounded-3" style="background:var(--brand-navy);border-color:var(--brand-navy)">
                <i class="fas fa-arrow-left me-1"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- HERO COVER + PROFILE CARD -->
    <div class="container px-2 px-md-3">
        <div class="sp-hero-cover mb-0">
            <?php if (!empty($school['cover_path'])): ?>
                <img src="<?= e(assetUrl($school['cover_path'])) ?>" alt="غلاف <?= e($school['name']) ?>" class="cover-img">
            <?php endif; ?>
            <div class="cover-overlay"></div>

            <!-- Top Header Badges Container -->
            <div class="sp-cover-badges-bar">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if (isSchoolHistoric($school)): ?>
                        <?php $hBadge = getSchoolHistoricBadge($school); ?>
                        <span class="badge" style="background: linear-gradient(135deg, #78350F 0%, #B45309 100%); color: #FEF3C7; border: 1.5px solid #FCD34D; font-size: 0.72rem; padding: 5px 12px; border-radius: 999px; font-weight: 800; box-shadow: 0 4px 12px rgba(180, 83, 9, 0.45); display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-landmark text-warning"></i> <?= e($hBadge['label']) ?> <span class="d-none d-sm-inline" style="opacity: 0.88; font-weight: 600;">(<?= e($hBadge['sub']) ?>)</span>
                        </span>
                    <?php endif; ?>
                    <?php if ($school['is_schoplus_client']): ?>
                        <span class="badge-schoplus"><i class="fas fa-bolt"></i> <span class="d-none d-sm-inline">مدرسة</span> SchoPlus الذكية</span>
                    <?php endif; ?>
                    <?php if ($school['is_verified']): ?>
                        <span class="badge-verified"><i class="fas fa-check-circle text-white"></i> موثقة بالدليل</span>
                    <?php else: ?>
                        <span class="badge bg-secondary text-white border border-light" style="font-size: 0.72rem; padding: 5px 10px; border-radius: 999px; backdrop-filter: blur(8px); background: rgba(100, 116, 139, 0.85) !important;">
                            <i class="fas fa-info-circle me-1"></i> بيانات عامة (غير موثقة)
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($school['reviews_count'] > 0): ?>
                <div class="sp-cover-rating-badge">
                    <i class="fas fa-star text-warning"></i>
                    <span><?= number_format($school['rating_avg'], 1) ?></span>
                    <span class="d-none d-sm-inline" style="opacity: 0.8; font-weight: 500;">(<?= $school['reviews_count'] ?> تقييم)</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- School Identity inside Cover (Logo + Title + Location) -->
            <div class="sp-cover-identity-bar">
                <div class="d-flex align-items-end gap-3 flex-wrap flex-md-nowrap">
                    <!-- Logo -->
                    <div class="sp-cover-logo-box">
                        <img src="<?= e(defaultSchoolLogo($school['logo_path'] ?? null)) ?>" alt="<?= e($school['name']) ?>">
                    </div>
                    <!-- Title & Details -->
                    <div class="sp-cover-title-box pb-1">
                        <h1 class="sp-cover-school-name mb-1"><?= e($school['name']) ?></h1>
                        <?php if (!empty($school['name_en'])): ?>
                            <div class="sp-cover-school-en mb-1"><?= e($school['name_en']) ?></div>
                        <?php endif; ?>
                        <div class="sp-cover-school-meta">
                            <i class="fas fa-map-marker-alt text-warning"></i>
                            <span><?= e($school['country'] ?? 'السودان') ?> — <?= e($school['state']) ?> — <?= e($school['city']) ?><?= !empty($school['district']) ? ' (' . e($school['district']) . ')' : '' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-profile-card">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 py-2">
                <!-- Action Buttons (Left side in RTL) -->
                <div class="sp-action-btns-wrap order-lg-2">
                    <?php if (!$school['is_verified']): ?>
                        <button type="button" class="btn btn-warning fw-bold sp-btn-action text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#claimSchoolModal" style="background: linear-gradient(135deg, #F5A020 0%, #D97706 100%); color: #FFFFFF !important; border: none;">
                            <i class="fas fa-id-badge"></i> <span>وثّق مدرستك</span>
                        </button>
                    <?php endif; ?>
                    <?php if (($school['is_verified'] || !empty($school['is_schoplus_client'])) && !empty($school['whatsapp'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>?text=<?= urlencode('مرحباً، رأيت مدرستكم على دليل SchoPlus وأود الاستفسار.') ?>" target="_blank" class="btn-luxury-primary sp-btn-action" style="background: #0B2A52; color: #FFFFFF;">
                            <i class="fab fa-whatsapp"></i> <span>واتساب</span>
                        </a>
                    <?php endif; ?>
                    <?php if (($school['is_verified'] || !empty($school['is_schoplus_client'])) && !empty($school['phone'])): ?>
                        <a href="tel:<?= e($school['phone']) ?>" class="btn-luxury-outline sp-btn-action">
                            <i class="fas fa-phone-alt"></i> <span>اتصال</span>
                        </a>
                    <?php endif; ?>
                    <a href="#tab-admission" class="btn-luxury-gold sp-btn-action" onclick="switchTab(document.querySelector('[data-target=\'tab-admission\']'), 'tab-admission')">
                        <i class="fas fa-user-graduate"></i> <span>طلب قبول</span>
                    </a>
                    <button type="button" class="btn-luxury-outline sp-btn-action" data-compare-id="<?= $school['id'] ?>" onclick="toggleSchoolCompare({id: '<?= $school['id'] ?>', name: '<?= addslashes(e($school['name'])) ?>', logo: '<?= addslashes(defaultSchoolLogo($school['logo_path'] ?? null)) ?>', slug: '<?= addslashes($school['slug']) ?>'});" title="إضافة إلى المقارنة">
                        <i class="fas fa-balance-scale"></i> <span class="compare-lbl">مقارنة</span>
                    </button>
                    <button type="button" class="btn-luxury-outline sp-btn-action" onclick="openShareModal()" title="مشاركة الرابط والباركود">
                        <i class="fas fa-share-alt text-primary"></i> <span class="d-inline d-md-none">مشاركة</span>
                    </button>
                    <?php if ($isOwner): ?>
                        <a href="/dashboard.php" class="btn-luxury-outline sp-btn-action" title="لوحة إدارة المدرسة">
                            <i class="fas fa-cog text-muted"></i> <span class="d-inline d-md-none">الإدارة</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Quick Stats Bar (Right side in RTL) -->
                <?php
                $schoolRank = getSchoolDirectoryRank($pdo, (int)$school['id'], !empty($school['directory_rank']) ? (int)$school['directory_rank'] : null);
                $schoolStateRank = getSchoolStateRank($pdo, (int)$school['id'], (string)($school['state'] ?? ''), !empty($school['state_rank']) ? (int)$school['state_rank'] : null);
                ?>
                <div class="sp-quick-stats order-lg-1 mt-0 pt-0 border-0 mb-0">
                    <div class="sp-stat-item">
                        <i class="fas fa-hashtag" style="color: var(--brand-gold);"></i>
                        <span>الترتيب في الدليل: <strong style="color: var(--brand-navy);">#<?= $schoolRank ?></strong></span>
                    </div>
                    <div class="sp-stat-item">
                        <i class="fas fa-map-marker-alt" style="color: var(--brand-blue);"></i>
                        <span>الترتيب في ولاية <?= e($school['state']) ?>: <strong style="color: var(--brand-navy);">#<?= $schoolStateRank ?></strong></span>
                    </div>
                    <?php if (!empty($school['established_year'])): ?>
                    <div class="sp-stat-item">
                        <i class="fas fa-landmark" style="color: var(--brand-gold);"></i>
                        <span>تأسست: <strong><?= e($school['established_year']) ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isSchoolHistoric($school)): ?>
                        <?php $hBadgeQuick = getSchoolHistoricBadge($school); ?>
                        <div class="sp-stat-item">
                            <i class="fas fa-crown text-warning"></i>
                            <span style="color: #B45309;">صرح عريق: <strong><?= e($hBadgeQuick['sub']) ?></strong></span>
                        </div>
                    <?php elseif ((int)($school['historic_votes'] ?? 0) > 0): ?>
                        <div class="sp-stat-item">
                            <i class="fas fa-vote-yea text-primary"></i>
                            <span>أصوات الصرح العريق: <strong id="quickHistoricVotesCount"><?= number_format((int)$school['historic_votes']) ?> / 500</strong></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($school['created_at'])): ?>
                    <div class="sp-stat-item">
                        <i class="far fa-calendar-check" style="color: #1E5FA8;"></i>
                        <span>انضمت للدليل: <strong><?= arabicDate($school['created_at']) ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <div class="sp-stat-item">
                        <i class="fas fa-eye" style="color: var(--brand-blue);"></i>
                        <span><strong><?= number_format($school['views_count']) ?></strong> مشاهدة</span>
                    </div>
                    <div class="sp-stat-item">
                        <i class="fas fa-star" style="color: #F5A020;"></i>
                        <span><strong><?= number_format($school['rating_avg'], 1) ?></strong> (<?= $school['reviews_count'] ?> تقييم)</span>
                    </div>
                    <?php $sTypeBadge = SudanGeography::schoolTypeBadge($school['school_type'] ?? ''); ?>
                    <div class="sp-stat-item">
                        <i class="<?= $sTypeBadge['icon'] ?>" style="color: var(--brand-blue);"></i>
                        <span><?= $sTypeBadge['label'] ?></span>
                    </div>
                    <?php if ($feesType === 'detailed' && !empty($stageFeesList)): ?>
                    <div class="sp-stat-item">
                        <i class="fas fa-coins" style="color: #1E5FA8;"></i>
                        <span>الرسوم: <strong>مفصلة حسب المراحل</strong></span>
                    </div>
                    <?php elseif (!empty($school['approx_fees'])): ?>
                    <div class="sp-stat-item">
                        <i class="fas fa-coins" style="color: #1E5FA8;"></i>
                        <span>الرسوم: <strong><?= e($school['approx_fees']) ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($feeDiscountsList)): ?>
                    <div class="sp-stat-item">
                        <i class="fas fa-tag" style="color: #F5A020;"></i>
                        <span>تتوفر عروض وخصومات (<?= count($feeDiscountsList) ?>)</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($stages)): ?>
                    <div class="sp-stat-item d-none d-md-flex">
                        <i class="fas fa-layer-group" style="color: var(--brand-navy);"></i>
                        <span><?= implode(' · ', array_map(fn($k) => SudanGeography::stageLabel($k), $stages)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($school['is_schoplus_client']): ?>
    <!-- SchoPlus Smart Banner -->
    <div class="container px-2 px-md-3 mt-4">
        <div style="background: #0B2A52; border-radius: 20px; padding: 18px 22px; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; box-shadow: 0 8px 28px rgba(11,42,82,0.18);">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 46px; height: 46px; border-radius: 14px; background: rgba(245,160,32,0.18); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
                    <i class="fas fa-bolt text-warning"></i>
                </div>
                <div>
                    <div class="fw-bold" style="font-size: 0.96rem;">مدرسة ذكية — تعمل بنظام SchoPlus السحابي</div>
                    <div style="font-size: 0.78rem; opacity: 0.85; margin-top: 2px;">بوابة أولياء الأمور · متابعة الدرجات والغياب فورياً · إشعارات واتساب لحظية</div>
                </div>
            </div>
            <a href="https://schoplus.sd" target="_blank" class="btn-luxury-gold btn-sm px-4 py-2" style="white-space: nowrap;">
                <i class="fas fa-external-link-alt"></i> تعرف على SchoPlus
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT LAYOUT -->
    <div class="container px-2 px-md-3 mt-4">
        <div class="row g-4">

            <!-- LEFT: TABS CONTENT -->
            <div class="col-lg-8">

                <!-- Tab Navigation (Smooth horizontal scroll on mobile) -->
                <div class="sp-tabs-nav mb-4">
                    <button class="sp-tab-btn active" data-target="tab-overview" onclick="switchTab(this, 'tab-overview')">
                        <span>نبذة ومعلومات</span>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-fees" id="tabBtnFees" onclick="switchTab(this, 'tab-fees')">
                        <span>الرسوم الدراسية</span>
                        <?php if (!empty($feeDiscountsList)): ?>
                            <span class="tab-count" style="background: #F5A020; color: #fff;">عروض (<?= count($feeDiscountsList) ?>)</span>
                        <?php elseif ($feesType === 'detailed' && !empty($stageFeesList)): ?>
                            <span class="tab-count" style="background: #1E5FA8; color: #fff;"><?= count($stageFeesList) ?> مراحل</span>
                        <?php elseif (!empty($school['approx_fees'])): ?>
                            <span class="tab-count" style="background: #1E5FA8; color: #fff;">محددة</span>
                        <?php endif; ?>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-admission" id="tabBtnAdmission" onclick="switchTab(this, 'tab-admission')">
                        <span>القبول والتسجيل</span>
                        <span class="tab-count" style="background: <?= $currAdmCfg['color'] ?>; color: #fff;"><?= $currAdmCfg['label'] ?></span>
                    </button>
                    <?php if ($showBranchesTab): ?>
                    <button class="sp-tab-btn" data-target="tab-branches" onclick="switchTab(this, 'tab-branches')">
                        <span>الفروع</span>
                        <span class="tab-count"><?= count($branchesList) ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($showActivitiesTab): ?>
                    <button class="sp-tab-btn" data-target="tab-activities" onclick="switchTab(this, 'tab-activities')">
                        <span>المناشط</span>
                        <span class="tab-count"><?= count($activitiesList) ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($showTeachersTab): ?>
                    <button class="sp-tab-btn" data-target="tab-teachers" onclick="switchTab(this, 'tab-teachers')">
                        <span>المعلمون</span>
                        <span class="tab-count"><?= count($teachersList) ?></span>
                    </button>
                    <?php endif; ?>
                    <button class="sp-tab-btn" data-target="tab-gallery" onclick="switchTab(this, 'tab-gallery')">
                        <span>معرض الصور</span>
                        <?php if (count($galleryList)): ?><span class="tab-count"><?= count($galleryList) ?></span><?php endif; ?>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-news" onclick="switchTab(this, 'tab-news')">
                        <span>الأخبار</span>
                        <?php if (count($newsList)): ?><span class="tab-count"><?= count($newsList) ?></span><?php endif; ?>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-reviews" onclick="switchTab(this, 'tab-reviews')">
                        <span>التقييمات</span>
                        <?php if (count($reviewsList)): ?><span class="tab-count"><?= count($reviewsList) ?></span><?php endif; ?>
                    </button>
                </div>

                <!-- TAB: OVERVIEW -->
                <div id="tab-overview" class="sp-tab-pane">

                    <?php if (!empty($historicDossier)): ?>
                    <!-- Heritage Historic Dossier Card (السجل التاريخي والوثائقي للصرح) -->
                    <div class="sp-section sp-historic-dossier-card mb-4" style="background: linear-gradient(180deg, #FFFDF5 0%, #FFFFFF 100%); border: 1.5px solid #FCD34D; border-radius: 18px; box-shadow: 0 4px 20px rgba(180, 83, 9, 0.08); overflow: hidden;">
                        <!-- Header -->
                        <div style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); padding: 18px 24px; color: #FFFFFF; border-bottom: 2px solid #F5A020;">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2.5">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; background: rgba(245, 160, 32, 0.2); border: 1.5px solid #F5A020; border-radius: 10px; color: #F5A020; font-size: 1.2rem;">
                                        <i class="fas fa-landmark"></i>
                                    </span>
                                    <div>
                                        <h5 class="mb-0 fw-bold" style="font-size: 0.98rem; color: #FFFFFF;">
                                            السجل التاريخي والأكاديمي للصرح
                                        </h5>
                                        <span style="font-size: 0.72rem; color: #FDE68A; font-weight: 600;">
                                            <?= e($historicDossier['era_tag'] ?? 'صرح تعليمي وتاريخي وطني') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <?php if (!empty($historicDossier['legacy_years'])): ?>
                                        <span class="badge" style="background: linear-gradient(135deg, #F5A020 0%, #D97706 100%); color: #0B2A52; font-weight: 800; font-size: 0.74rem; padding: 6px 14px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                                            <i class="fas fa-history me-1"></i> <?= $historicDossier['legacy_years'] ?> عاماً من العطاء والريادة
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.15); color: #FFFFFF; font-size: 0.72rem; padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.25);">
                                        <i class="fas fa-award text-warning me-1"></i> صرح وطني معتمد
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Body Content -->
                        <div class="p-3.5 p-md-4">
                            <!-- Quick Tiles -->
                            <div class="row g-2 mb-3.5">
                                <div class="col-6 col-md-3">
                                    <div class="p-2.5 rounded-3 text-center" style="background: #FFFBEB; border: 1px solid #FEF3C7;">
                                        <div style="font-size: 0.65rem; color: #92400E; font-weight: 700;">سنة التأسيس</div>
                                        <div class="fw-bold" style="color: #78350F; font-size: 0.95rem;"><?= e($historicDossier['established_year'] ?: 'عهد الرواد') ?>م</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-2.5 rounded-3 text-center" style="background: #EFF6FF; border: 1px solid #DBEAFE;">
                                        <div style="font-size: 0.65rem; color: #1E40AF; font-weight: 700;">الموقع والحاضرة</div>
                                        <div class="fw-bold" style="color: #1E3A8A; font-size: 0.82rem;"><?= e($historicDossier['city'] ? $historicDossier['city'] . ' — ' . $historicDossier['state'] : $historicDossier['state']) ?></div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-2.5 rounded-3 text-center" style="background: #F0FDF4; border: 1px solid #DCFCE7;">
                                        <div style="font-size: 0.65rem; color: #166534; font-weight: 700;">التصنيف الوطني</div>
                                        <div class="fw-bold" style="color: #14532D; font-size: 0.82rem;">صرح قومي عريق</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-2.5 rounded-3 text-center" style="background: #FAF5FF; border: 1px solid #F3E8FF;">
                                        <div style="font-size: 0.65rem; color: #6B21A8; font-weight: 700;">مسيرة التخريج</div>
                                        <div class="fw-bold" style="color: #581C87; font-size: 0.82rem;">أجيال وقادة الوطن</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Lead -->
                            <div class="p-3 rounded-3 mb-3" style="background: rgba(245, 160, 32, 0.07); border-right: 3px solid #F5A020;">
                                <p class="mb-0 fw-semibold" style="font-size: 0.78rem; line-height: 1.75; color: #1F2937;">
                                    <?= e($historicDossier['summary']) ?>
                                </p>
                            </div>

                            <!-- Section 1: النشأة والتأسيس والبيئة التاريخية -->
                            <div class="mb-3">
                                <h6 class="fw-bold d-flex align-items-center gap-2 mb-2" style="font-size: 0.84rem; color: var(--brand-navy);">
                                    <i class="fas fa-feather-alt text-warning"></i> النشأة والتأسيس والبيئة التاريخية
                                </h6>
                                <p style="font-size: 0.76rem; line-height: 1.8; color: var(--text-body); margin-bottom: 0; text-align: justify;">
                                    <?= e($historicDossier['founding_narrative']) ?>
                                </p>
                            </div>

                            <!-- Section 2: الطراز المعماري ومرافق الحرم المدرسي -->
                            <div class="mb-3">
                                <h6 class="fw-bold d-flex align-items-center gap-2 mb-2" style="font-size: 0.84rem; color: var(--brand-navy);">
                                    <i class="fas fa-university" style="color: var(--brand-blue);"></i> الطراز المعماري ومرافق الحرم المدرسي
                                </h6>
                                <p style="font-size: 0.76rem; line-height: 1.8; color: var(--text-body); margin-bottom: 0; text-align: justify;">
                                    <?= e($historicDossier['campus_and_architecture']) ?>
                                </p>
                            </div>

                            <!-- Section 3: المساهمة الوطنية وتخريج الرواد والقادة -->
                            <div class="mb-3">
                                <h6 class="fw-bold d-flex align-items-center gap-2 mb-2" style="font-size: 0.84rem; color: var(--brand-navy);">
                                    <i class="fas fa-user-graduate" style="color: #059669;"></i> المساهمة الوطنية وتخريج الرواد والقادة
                                </h6>
                                <p style="font-size: 0.76rem; line-height: 1.8; color: var(--text-body); margin-bottom: 0; text-align: justify;">
                                    <?= e($historicDossier['national_legacy']) ?>
                                </p>
                            </div>

                            <?php if (!empty($historicDossier['alumni_highlights'])): ?>
                            <div class="p-2.5 px-3 rounded-3 mt-3" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                <div style="font-size: 0.72rem; color: #475569; font-weight: 700; margin-bottom: 2px;">
                                    <i class="fas fa-star text-warning me-1"></i> من أعلام وخريجي الصرح:
                                </div>
                                <div style="font-size: 0.75rem; color: var(--brand-navy); font-weight: 600; line-height: 1.6;">
                                    <?= e($historicDossier['alumni_highlights']) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($historicDossier['key_badges'])): ?>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-3 pt-2.5 border-top">
                                <span style="font-size: 0.68rem; color: #64748B; font-weight: 700;">الوسوم التاريخية:</span>
                                <?php foreach ($historicDossier['key_badges'] as $b): ?>
                                    <span class="badge" style="background: #FFFBEB; color: #92400E; border: 1px solid #FCD34D; font-size: 0.68rem; padding: 5px 12px; border-radius: 999px;">
                                        <i class="fas fa-tag me-1" style="font-size: 0.6rem; opacity: 0.7;"></i> <?= e($b) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- About -->
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-book-open"></i></div>
                                عن المدرسة
                            </div>
                        </div>
                        <div class="sp-section-body">
                            <?php if (!empty($school['description'])): ?>
                                <p style="color: var(--text-body); line-height: 1.7; white-space: pre-line; font-size: 0.76rem; margin: 0;"><?= e($school['description']) ?></p>
                            <?php else: ?>
                                <p class="text-muted fst-italic mb-0" style="font-size: 0.72rem;">لم تضف المدرسة وصفاً تعريفياً بعد.</p>
                            <?php endif; ?>

                            <?php if (!empty($school['vision']) || !empty($school['mission'])): ?>
                                <div class="row g-3 mt-3">
                                    <?php if (!empty($school['vision'])): ?>
                                        <div class="col-md-6">
                                            <div class="vm-block vm-vision">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <i class="fas fa-eye" style="color: var(--brand-blue);"></i>
                                                    <span class="fw-bold" style="font-size: 0.78rem; color: var(--brand-blue);">رؤية المدرسة</span>
                                                </div>
                                                <p style="font-size: 0.74rem; color: #374151; margin: 0; line-height: 1.65;"><?= e($school['vision']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($school['mission'])): ?>
                                        <div class="col-md-6">
                                            <div class="vm-block vm-mission">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <i class="fas fa-bullseye" style="color: var(--brand-gold);"></i>
                                                    <span class="fw-bold" style="font-size: 0.78rem; color: var(--brand-gold);">رسالة المدرسة</span>
                                                </div>
                                                <p style="font-size: 0.74rem; color: #374151; margin: 0; line-height: 1.65;"><?= e($school['mission']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Facilities -->
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-cubes text-warning"></i></div>
                                المرافق والخدمات المتاحة
                            </div>
                            <?php if (!empty($facilities)): ?>
                                <span class="badge" style="background: #F0F4F8; color: #1E5FA8; border: 1px solid #DDE4ED; border-radius: 20px; padding: 5px 14px; font-size: 0.76rem; font-weight: 800;"><?= count($facilities) ?> مرافق</span>
                            <?php endif; ?>
                        </div>
                        <div class="sp-section-body">
                            <?php if (!empty($facilities)): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php foreach ($facilities as $facKey): ?>
                                        <span class="facility-chip">
                                            <i class="<?= SudanGeography::facilityIcon($facKey) ?>"></i>
                                            <?= SudanGeography::facilityLabel($facKey) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="sp-empty-state" style="padding: 32px;">
                                    <div class="empty-icon"><i class="fas fa-cubes"></i></div>
                                    <h6>لم تحدد المدرسة مرافقها بعد</h6>
                                    <p>يمكن لإدارة المدرسة تحديد المرافق المتاحة من لوحة الإدارة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Uploaded Campus Photos Preview in Main Overview (Appears automatically when photos exist) -->
                    <?php if (!empty($galleryList)): ?>
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #FFF7ED; color: #ea580c;"><i class="fas fa-camera"></i></div>
                                صور من مرافق وفعاليات المدرسة
                            </div>
                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold" style="font-size: 0.72rem; color: var(--brand-navy);" onclick="switchTab(document.querySelector('[data-target=\'tab-gallery\']'), 'tab-gallery')">
                                <i class="fas fa-images text-warning me-1"></i> كل الصور (<?= count($galleryList) ?>)
                            </button>
                        </div>
                        <div class="sp-section-body">
                            <div class="sp-gallery-grid">
                                <?php foreach ($galleryList as $idx => $photo): ?>
                                    <?php $photoUrl = assetUrl($photo['path'] ?? ''); if (!$photoUrl) continue; ?>
                                    <div class="sp-gallery-item" onclick="spLightboxOpen('<?= e($photoUrl) ?>', '<?= e(addslashes($photo['caption'] ?: $school['name'])) ?>')">
                                        <img src="<?= e($photoUrl) ?>" alt="<?= e($photo['caption'] ?: $school['name']) ?>" loading="lazy">
                                        <div class="sp-gallery-overlay">
                                            <?php if (!empty($photo['caption'])): ?>
                                                <div class="sp-gallery-caption"><?= e($photo['caption']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sp-gallery-zoom"><i class="fas fa-expand-alt"></i></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- MOBILE-ONLY COMPACT FACTS & CONTACT CARD (Visible on phones without scrolling to bottom) -->
                    <div class="sp-section d-lg-none">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-id-card"></i></div>
                                معلومات المدرسة والتواصل
                            </div>
                        </div>
                        <div class="sp-section-body p-3">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">نوع المدرسة</small>
                                        <span class="fw-bold small" style="color: <?= (($school['school_type'] ?? '') === 'government') ? '#059669' : '#1E40AF'; ?>;">
                                            <i class="<?= $sTypeBadge['icon'] ?> me-1"></i> <?= $sTypeBadge['label'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">نوع الطلاب</small>
                                        <span class="fw-bold text-dark small"><?= SudanGeography::genderTypeLabel($school['gender_type']) ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">المنهج التعليمي</small>
                                        <span class="fw-bold text-dark small"><?= SudanGeography::curriculumLabel($school['curriculum']) ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">ساعات الدوام</small>
                                        <span class="fw-bold text-dark small"><?= e($school['working_hours'] ?: '7:30 ص — 2:30 م') ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($school['address'])): ?>
                                <div class="p-2 px-3 rounded-3 bg-light border mb-2 d-flex align-items-center gap-2">
                                    <i class="fas fa-map-pin text-danger"></i>
                                    <small class="text-dark fw-bold"><?= e($school['address']) ?></small>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($school['google_map_url'])): ?>
                                <a href="<?= e($school['google_map_url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100 rounded-3 py-2 fw-bold mb-2">
                                    <i class="fas fa-map-marked-alt text-danger me-1"></i> فتح الموقع في خرائط Google
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($socialLinks)): ?>
                                <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
                                    <?php foreach ($socialLinks as $sl): ?>
                                        <a href="<?= e($sl['url']) ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                            <i class="<?= e($sl['icon']) ?>" style="color: <?= e($sl['color']) ?>;"></i>
                                            <span><?= e($sl['label']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB: FEES (بوابة الرسوم الدراسية والتسهيلات المالية) -->
                <div id="tab-fees" class="sp-tab-pane" style="display: none;">
                    
                    <!-- Detailed Fees Table or Overview -->
                    <?php if (($feesType === 'detailed' && !empty($stageFeesList)) || !empty($school['approx_fees'])): ?>
                    <div class="sp-section" id="school-fees-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-receipt text-warning"></i></div>
                                جدول الرسوم حسب المرحلة والصف الدراسي
                            </div>
                            <?php if ($feesType === 'detailed' && !empty($stageFeesList)): ?>
                                <span class="badge" style="background: #F0F4F8; color: #1E5FA8; border: 1px solid #DDE4ED; border-radius: 20px; padding: 5px 12px; font-size: 0.74rem; font-weight: 800;">
                                    <?= count($stageFeesList) ?> مراحل معتمدة
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="sp-section-body">
                            <?php if ($feesType === 'detailed' && !empty($stageFeesList)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden;">
                                    <thead style="background: #F8FAFC;">
                                        <tr>
                                            <th style="font-size: 0.75rem; color: #475569; padding: 10px 14px;">المرحلة / القسم</th>
                                            <th style="font-size: 0.75rem; color: #475569; padding: 10px 14px;">البيان أو الصف الدراسي</th>
                                            <th style="font-size: 0.75rem; color: #475569; padding: 10px 14px;">قيمة الرسوم</th>
                                            <th style="font-size: 0.75rem; color: #475569; padding: 10px 14px;">طريقة السداد / ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stageFeesList as $item): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #0F172A; font-size: 0.78rem; padding: 12px 14px;">
                                                <span class="badge bg-light text-dark border"><?= e($item['stage'] ?? 'عام') ?></span>
                                            </td>
                                            <td style="font-size: 0.78rem; color: #334155; padding: 12px 14px;">
                                                <?= e($item['title'] ?? '') ?>
                                            </td>
                                            <td style="font-size: 0.85rem; font-weight: 800; color: #0B2A52; padding: 12px 14px;">
                                                <?= e($item['amount'] ?? '') ?>
                                            </td>
                                            <td style="font-size: 0.74rem; color: #64748B; padding: 12px 14px;">
                                                <?= e($item['notes'] ?? '—') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($school['approx_fees'])): ?>
                            <div class="<?= ($feesType === 'detailed' && !empty($stageFeesList)) ? 'mt-3' : '' ?> p-3 rounded-3 bg-light border text-muted small d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 0.76rem;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-info-circle text-primary"></i>
                                    <span>متوسط الرسوم السنوية: <strong class="text-dark"><?= e($school['approx_fees']) ?></strong></span>
                                </div>
                                <span class="badge bg-white text-secondary border">ملخص تقديري</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="sp-section" id="school-fees-section">
                        <div class="sp-section-body text-center p-5">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: #F0F4F8; color: #1E5FA8; font-size: 1.5rem; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">الرسوم الدراسية تحدد مع إدارة المدرسة</h6>
                            <p class="text-muted small mb-3" style="max-width: 440px; margin: 0 auto 16px;">
                                لم يتم نشر جدول الرسوم التفصيلي للمدرسة حتى الآن. يمكنكم التواصل المباشر مع إدارة التسجيل أو تقديم طلب استفسار لمعرفة الرسوم وطرق السداد.
                            </p>
                            <?php if (($school['is_verified'] || !empty($school['is_schoplus_client'])) && (!empty($school['phone']) || !empty($school['whatsapp']))): ?>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <?php if (!empty($school['whatsapp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>?text=<?= urlencode('مرحباً بكم، أود الاستفسار عن تفاصيل الرسوم الدراسية وطرق السداد في ' . $school['name']) ?>" target="_blank" class="btn btn-sm btn-success px-3 py-2 fw-bold rounded-3">
                                    <i class="fab fa-whatsapp me-1"></i> استفسار واتساب
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($school['phone'])): ?>
                                <a href="tel:<?= e($school['phone']) ?>" class="btn btn-sm btn-outline-primary px-3 py-2 fw-bold rounded-3">
                                    <i class="fas fa-phone-alt me-1"></i> اتصال: <?= e($school['phone']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 3. Special Offers & Discounts Section -->
                    <?php if (!empty($feeDiscountsList)): ?>
                    <div class="sp-section mt-4">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-gift text-warning"></i></div>
                                العروض والخصومات والمنح الدراسية
                            </div>
                            <span class="badge" style="background: #F0F4F8; color: #1E5FA8; border: 1px solid #DDE4ED; border-radius: 20px; padding: 5px 12px; font-size: 0.74rem; font-weight: 800;">
                                <?= count($feeDiscountsList) ?> عروض نشطة
                            </span>
                        </div>
                        <div class="sp-section-body">
                            <div class="row g-3">
                                <?php foreach ($feeDiscountsList as $disc): ?>
                                <div class="col-md-6">
                                    <div class="card h-100 border rounded-3 p-3 position-relative overflow-hidden" style="background: #FFFFFF; border: 1.5px solid #1E5FA8 !important;">
                                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                            <h6 class="fw-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                <i class="fas fa-tag me-1" style="color: #F5A020;"></i>
                                                <?= e($disc['title'] ?? '') ?>
                                            </h6>
                                            <span class="badge px-3 py-1 rounded-pill fw-bold" style="background: #F5A020; color: #FFFFFF; font-size: 0.8rem;">
                                                <?= e($disc['value'] ?? '') ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($disc['applies_to'])): ?>
                                        <div class="mb-2" style="font-size: 0.74rem; color: #1E5FA8; font-weight: 600;">
                                            <i class="fas fa-user-check me-1"></i>
                                            الفئة المستفيدة: <span class="text-dark fw-normal"><?= e($disc['applies_to']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($disc['notes'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem; line-height: 1.6;">
                                            <i class="fas fa-info-circle me-1 text-secondary"></i>
                                            <?= e($disc['notes']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                </div>

                <!-- TAB: ADMISSION (بوابة القبول والتسجيل المنفصلة) -->
                <div id="tab-admission" class="sp-tab-pane" style="display: none;">
                    
                    <!-- Admission Status Info Bar -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge" style="background: <?= $currAdmCfg['color'] ?>; color: #ffffff; font-size: 0.74rem; font-weight: 700; padding: 5px 12px; border-radius: 20px;">
                                <i class="fas <?= $currAdmCfg['icon'] ?> me-1"></i> <?= $currAdmCfg['badge'] ?>
                            </span>
                            <span class="badge" style="background: #F0F4F8; color: #1E5FA8; font-size: 0.74rem; padding: 5px 12px; border-radius: 20px; border: 1px solid #DDE4ED;">
                                <i class="fas fa-calendar-alt me-1"></i> العام الدراسي <?= e($school['academic_year'] ?? '') ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($admStatus === 'closed'): ?>
                        <div class="alert alert-warning border-0 rounded-4 p-3 mb-4 d-flex align-items-center gap-3">
                            <i class="fas fa-lock text-warning fs-3"></i>
                            <div>
                                <div class="fw-bold text-dark">باب التسجيل مغلق مؤقتاً للعام الحالي</div>
                                <div class="small text-secondary">يمكنكم ترك استفساركم أو بياناتكم أدناه ليتواصل معكم قسم القبول والتسجيل فور توفر مقاعد شاغرة أو فتح باب التسجيل مجدداً.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. Admission Guidelines & Requirements (Cards Grid) -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100 border rounded-4 p-3 shadow-sm bg-white">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div style="width: 34px; height: 34px; border-radius: 10px; background: #EFF6FF; color: var(--brand-blue); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.88rem;">المستندات المطلوبة للتسجيل</h6>
                                </div>
                                <ul class="list-unstyled mb-0 pe-2" style="font-size: 0.74rem; color: #475569; line-height: 1.8;">
                                    <?php foreach ($admReqs as $rItem): ?>
                                    <li><i class="fas fa-check me-1" style="color: #1E5FA8;"></i> <?= e($rItem) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border rounded-4 p-3 shadow-sm bg-white">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div style="width: 34px; height: 34px; border-radius: 10px; background: #F0F4F8; color: #1E5FA8; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.88rem;">خطوات ومراحل القبول</h6>
                                </div>
                                <div class="d-flex flex-column gap-2" style="font-size: 0.73rem; color: #475569;">
                                    <?php foreach ($admSteps as $sIdx => $sItem): ?>
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.68rem; flex-shrink: 0; background: #0B2A52; color: #ffffff;"><?= $sIdx + 1 ?></span>
                                        <span><?= e($sItem) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($admNotes)): ?>
                        <div class="card border rounded-4 p-3 shadow-sm bg-white mb-4" style="border-right: 4px solid var(--brand-gold) !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: #FFFBEB; color: var(--brand-gold); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.88rem;">تعليمات وشروط القبول الإضافية</h6>
                            </div>
                            <div class="small text-secondary pe-2 lh-base" style="white-space: pre-line;">
                                <?= e($admNotes) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. Online Admission Form Card -->
                    <div class="sp-inquiry-card" id="inquiry-form-section">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                            <div style="width: 48px; height: 48px; border-radius: 14px; background: #0B2A52; display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 1.2rem; flex-shrink: 0;">
                                <i class="fas fa-file-signature text-warning"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0" style="color: var(--brand-navy); font-size: 1.08rem;">استمارة تقديم طلب القبول والتسجيل</h5>
                                <p class="mb-0" style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">
                                    يرجى تعبئة بيانات ولي الأمر والطالب، وسيقوم مسؤول التسجيل بالتواصل معكم هاتفياً أو عبر الواتساب فور استلام الطلب.
                                </p>
                            </div>
                        </div>

                        <?php if ($inquirySuccess): ?>
                            <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-4" style="background: #F0FDF4; border: 1.5px solid #BBF7D0;">
                                <i class="fas fa-check-circle text-success fs-2"></i>
                                <div>
                                    <div class="fw-bold text-success fs-6">تم إرسال طلب القبول والتسجيل بنجاح!</div>
                                    <div class="small text-muted mt-1">تم إشعار إدارة المدرسة بطلبكم وسيتواصل معكم قسم القبول والتسجيل قريباً لاستكمال الإجراءات.</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($inquiryError): ?>
                            <div class="alert alert-danger border-0 rounded-4 py-2 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <?= e($inquiryError) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="/school.php?slug=<?= urlencode($school['slug']) ?>#tab-admission">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_inquiry">

                            <!-- Section 1: Guardian Info -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span class="badge bg-primary text-white rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;">1</span>
                                <span>بيانات ولي الأمر (الأب / الأم / الوصي):</span>
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold small text-secondary">اسم ولي الأمر بالكامل <span class="text-danger">*</span></label>
                                    <input type="text" name="parent_name" required class="form-control" placeholder="مثال: محمد أحمد علي عثمان">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-secondary">رقم الهاتف / واتساب <span class="text-danger">*</span></label>
                                    <input type="tel" name="parent_phone" required class="form-control" placeholder="09xxxxxxx أو 01xxxxxxx">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small text-secondary">البريد الإلكتروني (اختياري)</label>
                                    <input type="email" name="parent_email" class="form-control" placeholder="parent@gmail.com">
                                </div>
                            </div>

                            <!-- Section 2: Student Info -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span class="badge bg-primary text-white rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;">2</span>
                                <span>بيانات الطالب / الطالبة المراد تسجيله:</span>
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold small text-secondary">اسم الطالب / الطالبة الكامل</label>
                                    <input type="text" name="student_name" class="form-control" placeholder="مثال: أحمد محمد أحمد">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small text-secondary">النوع / الجنس</label>
                                    <select name="student_gender" class="form-select">
                                        <option value="">-- اختر --</option>
                                        <option value="بنين">بنين (ذكر)</option>
                                        <option value="بنات">بنات (أنثى)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-secondary">المرحلة الدراسية المطلوبة</label>
                                    <select name="student_stage" class="form-select">
                                        <option value="">-- اختر المرحلة --</option>
                                        <?php if (!empty($stages)): ?>
                                            <?php foreach ($stages as $stg): ?>
                                                <option value="<?= e(SudanGeography::stageLabel($stg)) ?>"><?= e(SudanGeography::stageLabel($stg)) ?></option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="رياض الأطفال">رياض الأطفال</option>
                                            <option value="المرحلة الابتدائية">المرحلة الابتدائية</option>
                                            <option value="المرحلة المتوسطة">المرحلة المتوسطة</option>
                                            <option value="المرحلة الثانوية">المرحلة الثانوية</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">الصف الدراسي المطلوب</label>
                                    <input type="text" name="student_grade" class="form-control" placeholder="مثال: الصف الأول أساس / الثالث متوسط / ثاني ثانوي">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">المدرسة السابقة (للطلاب المحولين)</label>
                                    <input type="text" name="previous_school" class="form-control" placeholder="اسم المدرسة السابقة إن وجدت">
                                </div>
                            </div>

                            <!-- Section 3: Preferences & Notes -->
                            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <span class="badge bg-primary text-white rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;">3</span>
                                <span>تفضيلات واستفسارات إضافية:</span>
                            </h6>
                            <div class="row g-3 mb-4">
                                <?php if (!empty($branchesList)): ?>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">الفرع المفضل</label>
                                    <select name="branch_name" class="form-select">
                                        <option value="">الفرع الرئيسي</option>
                                        <?php foreach ($branchesList as $br): ?>
                                            <option value="<?= e($br['name'] ?? '') ?>"><?= e($br['name'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="<?= !empty($branchesList) ? 'col-md-6' : 'col-md-12' ?>">
                                    <label class="form-label fw-bold small text-secondary">خدمة الترحيل والمواصلات المدرسية</label>
                                    <div class="d-flex align-items-center gap-3 p-2 bg-light rounded-3 border">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="needs_transportation" value="1" id="inqNeedsTrans">
                                            <label class="form-check-label small fw-bold text-dark" for="inqNeedsTrans">
                                                <i class="fas fa-bus text-primary me-1"></i> نعم، أرغب في توفير خدمة الترحيل المدرسي للطالب
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold small text-secondary">أية استفسارات أو ملاحظات صحية أو تفاصيل إضافية</label>
                                    <textarea name="message" rows="3" class="form-control" placeholder="أية ملاحظات تود إبلاغ إدارة المدرسة بها أو استفسار عن الرسوم أو السداد..."></textarea>
                                </div>
                            </div>

                            <div class="pt-2 text-start">
                                <button type="submit" class="btn-luxury-primary px-5 py-3 fs-6">
                                    <i class="fas fa-paper-plane me-2"></i> إرسال طلب القبول والتسجيل الآن
                                </button>
                                <span class="text-muted small ms-3 d-inline-block mt-2">
                                    <i class="fas fa-shield-alt text-success me-1"></i> بياناتكم محفوظة ومحترمة وتُرسل مباشرة لإدارة المدرسة
                                </span>
                            </div>
                        </form>
                    </div>

                </div>

                <!-- TAB: GALLERY -->
                <div id="tab-gallery" class="sp-tab-pane" style="display: none;">
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #FFF7ED; color: #ea580c;"><i class="fas fa-camera"></i></div>
                                معرض صور مرافق وفعاليات المدرسة
                            </div>
                            <?php if (!empty($galleryList)): ?>
                                <span class="badge" style="background: #FFF7ED; color: #c2410c; border: 1px solid #FDBA74; border-radius: 20px; padding: 5px 14px; font-size: 0.76rem; font-weight: 800;"><?= count($galleryList) ?> صور</span>
                            <?php endif; ?>
                        </div>
                        <div class="sp-section-body">
                            <?php if (empty($galleryList)): ?>
                                <div class="sp-empty-state">
                                    <div class="empty-icon"><i class="fas fa-camera"></i></div>
                                    <h6>المعرض فارغ حالياً</h6>
                                    <p>لم تقم المدرسة بإضافة صور لمرافقها بعد</p>
                                </div>
                            <?php else: ?>
                                <div class="sp-gallery-grid">
                                    <?php foreach ($galleryList as $idx => $photo): ?>
                                        <?php $photoUrl = assetUrl($photo['path'] ?? ''); if (!$photoUrl) continue; ?>
                                        <div class="sp-gallery-item" onclick="spLightboxOpen('<?= e($photoUrl) ?>', '<?= e(addslashes($photo['caption'] ?: $school['name'])) ?>')">
                                            <img src="<?= e($photoUrl) ?>" alt="<?= e($photo['caption'] ?: $school['name']) ?>" loading="lazy">
                                            <div class="sp-gallery-overlay">
                                                <?php if (!empty($photo['caption'])): ?>
                                                    <div class="sp-gallery-caption"><?= e($photo['caption']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="sp-gallery-zoom"><i class="fas fa-expand-alt"></i></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB: NEWS -->
                <div id="tab-news" class="sp-tab-pane" style="display: none;">
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #F0F9FF; color: #0284c7;"><i class="fas fa-newspaper"></i></div>
                                آخر الأخبار والإعلانات الأكاديمية
                            </div>
                            <?php if (!empty($newsList)): ?>
                                <span class="badge" style="background: #F0F9FF; color: #0369a1; border: 1px solid #BAE6FD; border-radius: 20px; padding: 5px 14px; font-size: 0.76rem; font-weight: 800;"><?= count($newsList) ?> إعلانات</span>
                            <?php endif; ?>
                        </div>
                        <div class="sp-section-body">
                            <?php if (empty($newsList)): ?>
                                <div class="sp-empty-state">
                                    <div class="empty-icon"><i class="fas fa-newspaper"></i></div>
                                    <h6>لا توجد أخبار منشورة حالياً</h6>
                                    <p>لم تنشر المدرسة أي إعلانات أو أخبار بعد</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                    $newsDataStore = [];
                                    foreach ($newsList as $item) {
                                        $nImg = defaultNewsImage(
                                            $item['image_path'] ?? null, 
                                            $item['title'] ?? '', 
                                            $school['name'] ?? '', 
                                            $item['branch_name'] ?? ''
                                        );
                                        $newsDataStore[$item['id']] = [
                                            'id' => (int)$item['id'],
                                            'title' => (string)$item['title'],
                                            'branch' => (string)($item['branch_name'] ?? ''),
                                            'date' => date('d / m / Y', strtotime($item['published_at'] ?: $item['created_at'])),
                                            'image' => $nImg,
                                            'content' => sanitizeHtml($item['content'])
                                        ];
                                    }
                                ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($newsList as $item): ?>
                                        <?php 
                                            $newsImgUrl = $newsDataStore[$item['id']]['image'];
                                        ?>
                                        <div class="sp-news-card" id="news-item-<?= $item['id'] ?>">
                                            <div class="d-flex flex-column flex-md-row gap-3 align-items-start">
                                                <!-- صورة مصغرة للخبر -->
                                                <div class="flex-shrink-0" style="cursor: pointer;" onclick="openNewsReaderModal(<?= $item['id'] ?>)" title="انقر لقراءة الخبر">
                                                    <img src="<?= e($newsImgUrl) ?>" alt="صورة الخبر" loading="lazy" class="rounded-3 shadow-xs border" style="width: 140px; height: 95px; object-fit: cover; max-width: 100%;">
                                                </div>

                                                <!-- تفاصيل وعنوان الخبر وبداية النص -->
                                                <div class="flex-grow-1 w-100">
                                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.02rem; cursor: pointer;" onclick="openNewsReaderModal(<?= $item['id'] ?>)">
                                                                <?= e($item['title']) ?>
                                                            </h5>
                                                            <?php if (!empty($item['branch_name'])): ?>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small" style="font-size: 0.72rem;">
                                                                    <i class="fas fa-code-branch me-1"></i> <?= e($item['branch_name']) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="sp-news-date">
                                                            <i class="far fa-calendar-alt"></i>
                                                            <?= date('d / m / Y', strtotime($item['published_at'] ?: $item['created_at'])) ?>
                                                        </span>
                                                    </div>

                                                    <!-- بداية نص الخبر -->
                                                    <div class="text-secondary small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.65; cursor: pointer;" onclick="openNewsReaderModal(<?= $item['id'] ?>)">
                                                        <?= mb_substr(strip_tags($item['content']), 0, 180) ?>...
                                                    </div>

                                                    <!-- إجراءات: عرض المزيد لفتح الخبر ومشاركة -->
                                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                                        <button type="button" class="btn btn-sm btn-luxury-primary py-1 px-3 fw-bold" onclick="openNewsReaderModal(<?= $item['id'] ?>)">
                                                            <i class="fas fa-book-open me-1"></i> عرض المزيد
                                                        </button>

                                                        <button type="button" class="btn btn-sm btn-light border py-1 px-2 text-muted" onclick="copyNewsLink(<?= $item['id'] ?>)" title="مشاركة رابط الخبر">
                                                            <i class="fas fa-share-alt me-1"></i> مشاركة
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <script>
                                    window.newsDataStore = window.newsDataStore || {};
                                    Object.assign(window.newsDataStore, <?= json_encode($newsDataStore, JSON_UNESCAPED_UNICODE) ?>);
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB: REVIEWS -->
                <div id="tab-reviews" class="sp-tab-pane" style="display: none;">

                    <!-- Rating Summary -->
                    <?php if (!empty($reviewsList)): ?>
                    <div class="sp-section mb-4">
                        <div class="sp-section-body">
                            <div class="row align-items-center g-4">
                                <div class="col-md-3 text-center sp-reviews-stat-col border-start">
                                    <div style="font-size: 2.1rem; font-weight: 800; color: var(--brand-navy); line-height: 1;"><?= number_format($school['rating_avg'], 1) ?></div>
                                    <div class="sp-stars my-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= round($school['rating_avg']) ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700;"><?= $school['reviews_count'] ?> تقييم موثق</div>
                                </div>
                                <div class="col-md-9">
                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                        <div class="rating-bar-wrap">
                                            <span style="font-size: 0.72rem; color: var(--text-muted); width: 14px; text-align: left; font-weight: 700;"><?= $star ?></span>
                                            <i class="fas fa-star text-warning" style="font-size: 0.72rem;"></i>
                                            <div class="rating-bar flex-grow-1">
                                                <div class="rating-bar-fill" style="width: <?= $totalRev > 0 ? round($ratingDist[$star] / $totalRev * 100) : 0 ?>%;"></div>
                                            </div>
                                            <span style="font-size: 0.72rem; color: var(--text-muted); width: 28px; font-weight: 700;"><?= $ratingDist[$star] ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add Review Form -->
                    <div class="sp-section mb-4">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #FFFBEB; color: #d97706;"><i class="fas fa-star"></i></div>
                                شارك برأيك وتقييمك للمدرسة
                            </div>
                        </div>
                        <div class="sp-section-body">
                            <?php if ($reviewSuccess): ?>
                                <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-3" style="background: #F0FDF4; border: 1.5px solid #BBF7D0;">
                                    <i class="fas fa-check-circle text-success fs-3"></i>
                                    <div>
                                        <div class="fw-bold text-success">شكراً لك! تم نشر تقييمك بنجاح</div>
                                        <div class="small text-muted">تقييمك يساعد الأسر الأخرى في اختيار المدرسة الأنسب</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($reviewError): ?>
                                <div class="alert alert-danger border-0 rounded-4 py-2 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <?= e($reviewError) ?></div>
                            <?php endif; ?>
                            <form method="POST" action="/school.php?slug=<?= urlencode($school['slug']) ?>#reviews">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_review">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold small text-secondary">الاسم الكريم <span class="text-danger">*</span></label>
                                        <input type="text" name="reviewer_name" required class="form-control" placeholder="اسمك">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-secondary">صفتك</label>
                                        <select name="reviewer_role" class="form-select">
                                            <option value="parent">ولي أمر طالب</option>
                                            <option value="student">طالب / خريج</option>
                                            <option value="visitor">زائر / مهتم</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">التقييم</label>
                                        <select name="rating" class="form-select">
                                            <option value="5">5 من 5 — ممتاز</option>
                                            <option value="4">4 من 5 — جيد جداً</option>
                                            <option value="3">3 من 5 — متوسط</option>
                                            <option value="2">2 من 5 — دون المتوسط</option>
                                            <option value="1">1 من 5 — ضعيف</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-secondary">تعليقك وتجربتك <span class="text-danger">*</span></label>
                                        <textarea name="comment" required rows="3" class="form-control" placeholder="اكتب تقييمك بصدق عن البيئة التعليمية، الكادر، والاهتمام بالطلاب..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn-luxury-primary w-100 w-md-auto px-4 py-2">
                                            <i class="fas fa-check"></i> نشر التقييم الآن
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Reviews List -->
                    <?php if (empty($reviewsList)): ?>
                        <div class="sp-section">
                            <div class="sp-empty-state">
                                <div class="empty-icon"><i class="fas fa-comment-dots"></i></div>
                                <h6>لا توجد تقييمات منشورة بعد</h6>
                                <p>كن أول من يقيّم هذه المدرسة ويساعد أولياء الأمور الآخرين</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php
                            $avatarColors = ['#1E5FA8', '#0B2A52', '#F5A020', '#3D5068'];
                            $ci = 0;
                            foreach ($reviewsList as $rev):
                                $avatarColor = $avatarColors[$ci++ % count($avatarColors)];
                                $roleLabel = $rev['reviewer_role'] === 'parent' ? 'ولي أمر' : ($rev['reviewer_role'] === 'student' ? 'طالب / خريج' : 'زائر');
                            ?>
                            <div class="sp-review-card">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="sp-reviewer-avatar" style="background: <?= $avatarColor ?>;">
                                            <?= mb_substr($rev['reviewer_name'], 0, 1) ?>
                                        </div>
                                         <div>
                                            <div class="fw-bold" style="color: var(--text-main); font-size: 0.84rem;"><?= e($rev['reviewer_name']) ?></div>
                                            <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;">
                                                <?= $roleLabel ?> · <?= date('d / m / Y', strtotime($rev['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sp-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $rev['rating'] ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: var(--text-body); line-height: 1.65; margin: 0;"><?= e($rev['comment']) ?></p>
                                <?php if (!empty($rev['school_reply'])): ?>
                                    <div class="sp-school-reply">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge" style="background: #DBEAFE; color: #1d4ed8; font-size: 0.7rem; padding: 3px 9px; border-radius: 20px; font-weight: 800;">
                                                <i class="fas fa-reply me-1"></i> رد إدارة المدرسة
                                            </span>
                                            <?php if (!empty($rev['school_replied_at'])): ?>
                                                <span style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;"><?= date('d / m / Y', strtotime($rev['school_replied_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="font-size: 0.74rem; color: var(--text-body); margin: 0; line-height: 1.6;"><?= e($rev['school_reply']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- TAB: OPTIONAL — BRANCHES (الفروع) -->
                <?php if ($showBranchesTab): ?>
                <div id="tab-branches" class="sp-tab-pane" style="display: none;">
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-code-branch"></i></div>
                                فروع المدرسة
                            </div>
                            <span class="badge" style="background: #EFF6FF; color: var(--brand-blue); border: 1px solid #BFDBFE; border-radius: 20px; padding: 5px 14px; font-size: 0.74rem; font-weight: 700;"><?= count($branchesList) ?> فروع</span>
                        </div>
                        <div class="sp-section-body">
                            <div class="row g-3">
                                <?php foreach ($branchesList as $br): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-4 border h-100 d-flex flex-column justify-content-between" style="background: #F8FAFC; border-color: var(--border-subtle) !important;">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <div style="width: 34px; height: 34px; border-radius: 10px; background: #EFF6FF; color: var(--brand-blue); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                        <i class="fas fa-school"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-0" style="color: var(--brand-navy); font-size: 0.88rem;"><?= e($br['name'] ?? '') ?></h6>
                                                </div>
                                                <?php if (!empty($br['address'])): ?>
                                                    <p class="mb-2 d-flex align-items-start gap-2" style="font-size: 0.75rem; color: var(--text-body);">
                                                        <i class="fas fa-map-marker-alt text-danger mt-1"></i>
                                                        <span><?= e($br['address']) ?></span>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
                                                <?php if (!empty($br['phone'])): ?>
                                                    <a href="tel:<?= e($br['phone']) ?>" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; color: var(--brand-navy);">
                                                        <i class="fas fa-phone-alt text-primary"></i> <?= e($br['phone']) ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($br['whatsapp'])): ?>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $br['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; color: var(--brand-navy);">
                                                        <i class="fab fa-whatsapp"></i> واتساب الفرع
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($br['map_url'])): ?>
                                                    <a href="<?= e($br['map_url']) ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; color: #dc2626;">
                                                        <i class="fas fa-map-marked-alt"></i> الخريطة
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB: OPTIONAL — ACTIVITIES (المناشط) -->
                <?php if ($showActivitiesTab): ?>
                <div id="tab-activities" class="sp-tab-pane" style="display: none;">
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #FFFBEB; color: #d97706;"><i class="fas fa-trophy"></i></div>
                                المناشط والأنشطة اللاصفية بالمدرسة
                            </div>
                            <span class="badge" style="background: #FFFBEB; color: #b45309; border: 1px solid #FDE68A; border-radius: 20px; padding: 5px 14px; font-size: 0.74rem; font-weight: 700;"><?= count($activitiesList) ?> مناشط</span>
                        </div>
                        <div class="sp-section-body">
                            <div class="row g-3">
                                <?php foreach ($activitiesList as $act): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-4 border h-100 d-flex flex-column justify-content-between" style="background: #FFFFFF; border-color: var(--border-subtle) !important;">
                                            <div>
                                                <?php if (!empty($act['image_path'])): ?>
                                                    <?php $actImgUrl = assetUrl($act['image_path']); ?>
                                                    <div class="rounded-3 overflow-hidden mb-3" style="height: 150px; cursor: pointer;" onclick="spLightboxOpen('<?= e($actImgUrl) ?>', '<?= e(addslashes($act['title'] ?? '')) ?>')">
                                                        <img src="<?= e($actImgUrl) ?>" alt="<?= e($act['title'] ?? '') ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                                                    <h6 class="fw-bold mb-0" style="color: var(--brand-navy); font-size: 0.88rem;">
                                                        <i class="fas fa-star text-warning me-1"></i> <?= e($act['title'] ?? '') ?>
                                                    </h6>
                                                    <?php if (!empty($act['category'])): ?>
                                                        <span class="badge rounded-pill" style="background: #EFF6FF; color: var(--brand-blue); border: 1px solid #BFDBFE; font-size: 0.68rem;"><?= e($act['category']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mb-0" style="font-size: 0.75rem; color: var(--text-body); line-height: 1.65;"><?= e($act['description'] ?? '') ?></p>
                                            </div>
                                            <?php if (!empty($act['schedule'])): ?>
                                                <div class="mt-2 pt-2 border-top d-flex align-items-center gap-1" style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">
                                                    <i class="far fa-clock text-warning"></i>
                                                    <span>الموعد: <?= e($act['schedule']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB: OPTIONAL — TEACHERS (المعلمون) -->
                <?php if ($showTeachersTab): ?>
                <div id="tab-teachers" class="sp-tab-pane" style="display: none;">
                    <div class="sp-section">
                        <div class="sp-section-head">
                            <div class="sp-section-title">
                                <div class="title-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-chalkboard-teacher text-warning"></i></div>
                                نخبة المعلمين والكادر التدريسي
                            </div>
                            <span class="badge" style="background: #F0F4F8; color: #1E5FA8; border: 1px solid #DDE4ED; border-radius: 20px; padding: 5px 14px; font-size: 0.74rem; font-weight: 700;"><?= count($teachersList) ?> معلم</span>
                        </div>
                        <div class="sp-section-body">
                            <div class="row g-3">
                                <?php
                                $tchColors = ['#1E5FA8', '#0B2A52', '#F5A020', '#3D5068'];
                                $tIdx = 0;
                                foreach ($teachersList as $tch):
                                    $tchColor = $tchColors[$tIdx++ % count($tchColors)];
                                ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-4 border d-flex align-items-center gap-3 h-100" style="background: #F8FAFC; border-color: var(--border-subtle) !important;">
                                            <div style="width: 44px; height: 44px; border-radius: 12px; background: <?= $tchColor ?>; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; flex-shrink: 0;">
                                                <?= mb_substr($tch['name'] ?? 'م', 0, 1) ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold mb-1" style="color: var(--brand-navy); font-size: 0.84rem;"><?= e($tch['name'] ?? '') ?></div>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if (!empty($tch['subject'])): ?>
                                                        <span class="badge rounded-pill" style="background: #EFF6FF; color: var(--brand-blue); border: 1px solid #BFDBFE; font-size: 0.68rem;">
                                                            <i class="fas fa-book me-1"></i> <?= e($tch['subject']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($tch['stage'])): ?>
                                                        <span class="badge rounded-pill" style="background: #FFFFFF; color: var(--text-body); border: 1px solid var(--border-subtle); font-size: 0.68rem;">
                                                            <i class="fas fa-layer-group text-muted me-1"></i> <?= e($tch['stage']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($tch['class_name'])): ?>
                                                        <span class="badge rounded-pill" style="background: #FFFBEB; color: #b45309; border: 1px solid #FDE68A; font-size: 0.68rem;">
                                                            <i class="fas fa-door-open me-1"></i> <?= e($tch['class_name']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="col-lg-4">

                <!-- School Facts Box -->
                <div class="sp-info-card">
                    <div class="sp-info-card-head">
                        <i class="fas fa-id-card" style="color: var(--brand-blue);"></i>
                        بطاقة معلومات المدرسة
                    </div>
                    <div class="sp-info-card-body">
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #FFFBEB; color: #F5A020;"><i class="fas fa-hashtag"></i></div>
                            <div>
                                <span class="sp-fact-label">الترتيب في الدليل</span>
                                <span class="sp-fact-value" style="color: var(--brand-navy);">#<?= $schoolRank ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <span class="sp-fact-label">الترتيب في ولاية <?= e($school['state']) ?></span>
                                <span class="sp-fact-value" style="color: var(--brand-navy);">#<?= $schoolStateRank ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-globe-africa"></i></div>
                            <div>
                                <span class="sp-fact-label">الدولة والولاية</span>
                                <span class="sp-fact-value fw-bold" style="color: var(--brand-navy);"><?= e($school['country'] ?? 'السودان') ?> — <?= e($school['state']) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($school['established_year'])): ?>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #FEF3C7; color: #b45309;"><i class="fas fa-landmark"></i></div>
                            <div>
                                <span class="sp-fact-label">سنة التأسيس</span>
                                <span class="sp-fact-value fw-bold" style="color: var(--brand-navy);"><?= e($school['established_year']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($school['created_at'])): ?>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="far fa-calendar-check"></i></div>
                            <div>
                                <span class="sp-fact-label">تاريخ الانضمام للدليل</span>
                                <span class="sp-fact-value fw-bold" style="color: var(--brand-navy);"><?= arabicDate($school['created_at']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-building"></i></div>
                            <div>
                                <span class="sp-fact-label">نوع المدرسة</span>
                                <span class="sp-fact-value fw-bold" style="color: <?= (($school['school_type'] ?? '') === 'government') ? '#059669' : '#1E40AF'; ?>;"><?= SudanGeography::schoolTypeLabel($school['school_type']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-user-friends"></i></div>
                            <div>
                                <span class="sp-fact-label">نوع الطلاب</span>
                                <span class="sp-fact-value"><?= SudanGeography::genderTypeLabel($school['gender_type']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-graduation-cap"></i></div>
                            <div>
                                <span class="sp-fact-label">المنهج الدراسي</span>
                                <span class="sp-fact-value"><?= SudanGeography::curriculumLabel($school['curriculum']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fas fa-coins"></i></div>
                            <div>
                                <span class="sp-fact-label">الرسوم الدراسية</span>
                                <?php if ($feesType === 'detailed' && !empty($stageFeesList)): ?>
                                    <span class="sp-fact-value fw-bold" style="color: var(--brand-navy);">مفصلة حسب المراحل</span>
                                    <?php if (!empty($school['approx_fees'])): ?>
                                        <small class="text-muted d-block" style="font-size: 0.68rem;">(متوسط: <?= e($school['approx_fees']) ?>)</small>
                                    <?php endif; ?>
                                <?php elseif (!empty($school['approx_fees'])): ?>
                                    <span class="sp-fact-value fw-bold" style="color: var(--brand-navy);"><?= e($school['approx_fees']) ?></span>
                                <?php else: ?>
                                    <span class="sp-fact-value" style="color: var(--text-muted);">تحدد مع الإدارة</span>
                                <?php endif; ?>
                                <?php if (!empty($feeDiscountsList)): ?>
                                    <span class="badge mt-1 d-inline-block" style="background: #F0F4F8; color: #1E5FA8; border: 1px solid #DDE4ED; font-size: 0.68rem; font-weight: 700;">
                                        <i class="fas fa-tag me-1" style="color: #F5A020;"></i> تتوفر عروض وخصومات (<?= count($feeDiscountsList) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F8FAFC; color: var(--text-muted);"><i class="far fa-clock"></i></div>
                            <div>
                                <span class="sp-fact-label">ساعات الدوام</span>
                                <span class="sp-fact-value"><?= e($school['working_hours'] ?: '7:30 ص — 2:30 م') ?></span>
                            </div>
                        </div>
                        <?php if (!empty($stages)): ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="sp-fact-label mb-2"><i class="fas fa-layer-group me-1"></i> المراحل الدراسية المتاحة</div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($stages as $stgKey): ?>
                                    <span style="background: #EFF6FF; color: var(--brand-blue); border: 1px solid #BFDBFE; border-radius: 8px; padding: 4px 10px; font-size: 0.76rem; font-weight: 700;">
                                        <?= SudanGeography::stageLabel($stgKey) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historic School Status & Community Voting Card -->
                <?php 
                $isHistoricSchool = isSchoolHistoric($school);
                $historicVotes = (int)($school['historic_votes'] ?? 0);
                $historicBadgeInfo = getSchoolHistoricBadge($school);
                $estYear = (int)($school['established_year'] ?? 0);

                // Check if user has already voted within 1 year via Cookie or DB
                $userVotedCookie = 'sp_voted_historic_' . $school['id'];
                $hasUserVoted = !empty($_COOKIE[$userVotedCookie]);
                if (!$hasUserVoted) {
                    $uIp = $_SERVER['REMOTE_ADDR'] ?? null;
                    $uSess = session_id();
                    $uCheck = $pdo->prepare("SELECT id FROM daleel_school_historic_votes 
                        WHERE school_id = ? AND (session_id = ? OR (ip_address IS NOT NULL AND ip_address = ?))
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY) LIMIT 1");
                    $uCheck->execute([$school['id'], $uSess, $uIp]);
                    if ($uCheck->fetch()) {
                        $hasUserVoted = true;
                    }
                }
                ?>
                <div class="sp-info-card mb-3" id="historicSchoolCard" style="border: 1.5px solid <?= $isHistoricSchool ? '#FCD34D' : '#E2E8F0' ?>; background: <?= $isHistoricSchool ? 'linear-gradient(180deg, #FFFBEB 0%, #FFFFFF 100%)' : '#FFFFFF' ?>;">
                    <div class="sp-info-card-head d-flex align-items-center justify-content-between" style="background: <?= $isHistoricSchool ? 'linear-gradient(135deg, #78350F 0%, #B45309 100%)' : '#0B2A52' ?>; color: #FFFFFF;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-landmark text-warning"></i>
                            <span class="fw-bold" style="font-size: 0.82rem;"><?= $isHistoricSchool ? 'صرح تعليمي عريق معتمد' : 'مبادرة توثيق المدارس العريقة' ?></span>
                        </div>
                        <?php if ($isHistoricSchool): ?>
                            <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem; border-radius: 999px;">
                                <i class="fas fa-check-circle me-1"></i> معتمدة
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="sp-info-card-body p-3">
                        <?php if ($isHistoricSchool): ?>
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div style="width: 44px; height: 44px; border-radius: 12px; background: #FEF3C7; color: #B45309; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; border: 1px solid #FDE68A;">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1" style="color: #78350F; font-size: 0.86rem;"><?= e($school['name']) ?></h6>
                                    <p class="text-muted small mb-0" style="font-size: 0.72rem; line-height: 1.5;">
                                        تم تصنيف هذه المدرسة كـ <strong>صرح عريق</strong> في السودان بناءً على:
                                        <span class="text-dark fw-bold d-block mt-0.5"><?= e($historicBadgeInfo['sub'] ?? 'الإرث التاريخي والتعليمي') ?></span>
                                    </p>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-3 mb-2" style="background: #FFFBEB; border: 1px dashed #FCD34D; font-size: 0.72rem; color: #92400E;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span><i class="fas fa-users text-warning me-1"></i> أصوات المجتمع الداعمة:</span>
                                    <strong class="fs-6" id="historicVotesTotalBadge"><?= number_format($historicVotes) ?> صوت</strong>
                                </div>
                                <div class="progress" style="height: 6px; background: #FDE68A; border-radius: 999px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, max(15, ($historicVotes / 500) * 100)) ?>%;"></div>
                                </div>
                            </div>

                            <?php if ($hasUserVoted): ?>
                                <button type="button" class="btn btn-sm w-100 rounded-pill py-2 fw-bold text-white shadow-xs" disabled style="background: #059669; border: 1px solid #047857; font-size: 0.75rem;">
                                    <i class="fas fa-check-circle me-1"></i> صوتك مسجل ومحفوظ لمدة عام ✓
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm w-100 rounded-pill py-2 fw-bold text-dark shadow-xs" id="btnVoteHistoric" onclick="submitHistoricVote()" style="background: linear-gradient(135deg, #FDE68A 0%, #F59E0B 100%); border: 1px solid #FCD34D; font-size: 0.75rem;">
                                    <i class="fas fa-heart text-danger me-1"></i> دعم وتأكيد عراقة المدرسة (+1)
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mb-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.68rem; font-weight: 700;">
                                        <i class="fas fa-landmark text-warning me-1"></i> المعيار: تأسست قبل 1950 أو 500+ صوت
                                    </span>
                                </div>
                                <p class="text-muted small mb-2" style="font-size: 0.73rem; line-height: 1.55;">
                                    هل تُعد <strong><?= e($school['name']) ?></strong> من المدارس القديمة والتاريخية في منطقتكم؟ صوّت لها الآن! عند وصول الأصوات إلى <strong>500 صوت</strong> تُمنح المدرسة وسام <strong>"صرح عريق"</strong> رسمياً.
                                </p>
                                <div class="p-2.5 rounded-3 bg-light border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.73rem;">
                                        <span class="text-muted">التقدم نحو الوسام:</span>
                                        <strong style="color: #0B2A52;"><span id="historicVotesCurrentCount"><?= number_format($historicVotes) ?></span> / 500 صوت</strong>
                                    </div>
                                    <div class="progress" style="height: 7px; border-radius: 999px; background: #E2E8F0;">
                                        <div class="progress-bar" id="historicProgressBar" role="progressbar" style="width: <?= min(100, ($historicVotes / 500) * 100) ?>%; background: linear-gradient(90deg, #1E5FA8, #F5A020);"></div>
                                    </div>
                                </div>

                                <?php if ($hasUserVoted): ?>
                                    <button type="button" class="btn btn-sm w-100 rounded-pill py-2 fw-bold text-white shadow-xs" disabled style="background: #059669; border: 1px solid #047857; font-size: 0.75rem;">
                                        <i class="fas fa-check-circle me-1"></i> صوتك مسجل ومحفوظ لمدة عام ✓
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" id="btnVoteHistoric" onclick="submitHistoricVote()" style="background: linear-gradient(135deg, #1E5FA8 0%, #0B2A52 100%); border: none; font-size: 0.76rem;">
                                        <i class="fas fa-vote-yea text-warning me-1"></i> صوّت لاعتمادها كـ "صرح عريق"
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div id="historicVoteMsg" class="mt-2 text-center" style="display: none; font-size: 0.72rem; font-weight: 700;"></div>
                    </div>
                </div>

                <!-- Contact Box -->
                <div class="sp-info-card">
                    <div class="sp-info-card-head">
                        <i class="fas fa-phone-alt" style="color: var(--brand-blue);"></i>
                        بيانات الاتصال والتواصل
                    </div>
                    <div class="sp-info-card-body" style="padding-top: 8px; padding-bottom: 8px;">
                        <?php if ($school['is_verified'] || !empty($school['is_schoplus_client'])): ?>
                            <?php if (!empty($school['phone'])): ?>
                                <a href="tel:<?= e($school['phone']) ?>" class="sp-contact-row text-decoration-none">
                                    <div class="sp-contact-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-phone"></i></div>
                                    <div>
                                        <span style="font-size: 0.68rem; color: var(--text-muted); display: block; font-weight: 700;">هاتف المدرسة المعتمد</span>
                                        <span class="fw-bold" style="color: var(--text-main); font-size: 0.82rem;"><?= e($school['phone']) ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($school['whatsapp'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>" target="_blank" class="sp-contact-row text-decoration-none">
                                    <div class="sp-contact-icon" style="background: #F0F4F8; color: #1E5FA8;"><i class="fab fa-whatsapp"></i></div>
                                    <div>
                                        <span style="font-size: 0.68rem; color: var(--text-muted); display: block; font-weight: 700;">واتساب مباشر معتمد</span>
                                        <span class="fw-bold" style="color: var(--brand-navy); font-size: 0.82rem;"><?= e($school['whatsapp']) ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <?php if (empty($school['phone']) && empty($school['whatsapp'])): ?>
                                <div class="sp-contact-row" style="background: #F8FAFC; border: 1px dashed #CBD5E1;">
                                    <div class="sp-contact-icon" style="background: #F1F5F9; color: #64748B;"><i class="fas fa-phone-slash"></i></div>
                                    <div>
                                        <span style="font-size: 0.68rem; color: var(--text-muted); display: block; font-weight: 700;">هاتف المدرسة</span>
                                        <div style="font-size: 0.74rem; color: #64748B; line-height: 1.4;">غير محدد حالياً</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="p-3 my-2 rounded-3 text-center" style="background: #F8FAFC; border: 1.5px dashed #CBD5E1;">
                                <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 36px; height: 36px; border-radius: 50%; background: #EFF6FF; color: #1E5FA8;">
                                    <i class="fas fa-lock" style="font-size: 0.95rem;"></i>
                                </div>
                                <div class="fw-bold mb-1" style="color: #0B2A52; font-size: 0.78rem;">أرقام التواصل تظهر بعد التوثيق</div>
                                <p class="text-muted small mb-2" style="font-size: 0.69rem; line-height: 1.45;">
                                    لحماية خصوصية المدارس وموثوقية التواصل، تظهر أرقام الهواتف والواتساب حصراً بعد اعتماد وتوثيق المدرسة من قبل إدارتها الرسمية.
                                </p>
                                <button type="button" class="btn btn-sm w-100 fw-bold rounded-pill py-1 shadow-2xs" data-bs-toggle="modal" data-bs-target="#claimSchoolModal" style="background: linear-gradient(135deg, #F5A020 0%, #D97706 100%); color: #FFFFFF !important; border: none; font-size: 0.72rem;">
                                    <i class="fas fa-id-badge me-1"></i> وثّق مدرستك الآن
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($school['email'])): ?>
                            <a href="mailto:<?= e($school['email']) ?>" class="sp-contact-row text-decoration-none">
                                <div class="sp-contact-icon" style="background: #F0F9FF; color: #0284c7;"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <span style="font-size: 0.68rem; color: var(--text-muted); display: block; font-weight: 700;">البريد الإلكتروني</span>
                                    <span style="color: var(--text-body); font-size: 0.78rem;"><?= e($school['email']) ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($school['address'])): ?>
                            <div class="sp-contact-row">
                                <div class="sp-contact-icon" style="background: #FFF1F2; color: #e11d48;"><i class="fas fa-map-pin"></i></div>
                                <div>
                                    <span style="font-size: 0.68rem; color: var(--text-muted); display: block; font-weight: 700;">العنوان الفعلي</span>
                                    <span style="color: var(--text-body); font-size: 0.78rem;"><?= e($school['address']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($school['google_map_url'])): ?>
                        <div style="padding: 0 16px 16px;">
                            <a href="<?= e($school['google_map_url']) ?>" target="_blank" class="btn-luxury-outline justify-content-center w-100" style="font-size: 0.76rem;">
                                <i class="fas fa-map-marked-alt text-danger"></i> فتح في خرائط Google
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Social Links (Only non-empty links appear) -->
                <?php if (!empty($socialLinks)): ?>
                <div class="sp-info-card">
                    <div class="sp-info-card-head">
                        <i class="fas fa-share-alt" style="color: var(--brand-blue);"></i>
                        الحضور الرقمي ومنصات التواصل
                    </div>
                    <div class="sp-info-card-body d-flex flex-column gap-2">
                        <?php foreach ($socialLinks as $sl): ?>
                            <a href="<?= e($sl['url']) ?>" target="_blank" class="btn-luxury-outline justify-content-between" style="font-size: 0.76rem;">
                                <span><i class="<?= e($sl['icon']) ?> me-2" style="color: <?= e($sl['color']) ?>;"></i> <?= e($sl['label']) ?></span>
                                <i class="fas fa-external-link-alt text-muted small"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Data Disclaimer Note (Conditional: AI-Sourced vs Officially Verified) -->
                <?php if ($school['is_verified']): ?>
                    <div class="p-3 rounded-4 mb-3" style="background: #F0FDF4; border: 1px solid #BBF7D0; font-size: 0.72rem; color: #166534; line-height: 1.7;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fas fa-check-circle text-success" style="font-size: 0.95rem;"></i>
                            <strong style="color: #14532D; font-weight: 800;">بيانات موثقة ومعتمدة رسمياً:</strong>
                        </div>
                        تم توثيق وتدقيق هذه الصفحة رسمياً وتحديث بياناتها مباشرة بواسطة إدارة المدرسة وعلى مسؤوليتها. دليل المدارس هو منصة رقمية مستقلة مقدمة من <strong>منظومة SchoPlus الذكية</strong>.
                    </div>
                <?php else: ?>
                    <div class="p-3 rounded-4 mb-3" style="background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%); border: 1.5px dashed #93C5FD; font-size: 0.72rem; color: #1E3A8A; line-height: 1.7; position: relative;">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width: 22px; height: 22px; border-radius: 6px; background: #DBEAFE; color: #1E5FA8; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                    <i class="fas fa-robot"></i>
                                </span>
                                <strong style="color: #0B2A52; font-weight: 800;">فهرسة آلية بالذكاء الاصطناعي:</strong>
                            </div>
                            <span class="badge" style="background: #FEF3C7; color: #92400E; font-size: 0.65rem; font-weight: 700; border: 1px solid #FCD34D;">بانتظار إثبات الملكية</span>
                        </div>
                        تم إدراج وفهرسة بيانات هذه المدرسة تلقائياً عبر <strong>خوارزميات الذكاء الاصطناعي والمصادر والمؤشرات العامة</strong> لتسهيل وصول أولياء الأمور والطلاب. إذا كنت ممثلاً رسمياً للمدرسة يمكنك <a href="#claimSchoolModal" data-bs-toggle="modal" class="fw-bold text-decoration-underline" style="color: #F5A020;">إثبات الملكية وتوثيق الصفحة الآن مجاناً</a> للتحكم بكافة البيانات واستقبال طلبات القبول.
                    </div>
                <?php endif; ?>

                <!-- SchoPlus Upgrade CTA (for non-clients) -->
                <?php if (!$school['is_schoplus_client']): ?>
                <div class="sp-upgrade-cta" style="background: linear-gradient(145deg, #0B2A52 0%, #163870 55%, #1E5FA8 100%); border: 1.5px solid rgba(245,160,32,0.3); box-shadow: 0 12px 32px rgba(11,42,82,0.22);">
                    <div style="position: relative; z-index: 1;">
                        <div style="width: 56px; height: 56px; border-radius: 18px; background: linear-gradient(135deg, rgba(245,160,32,0.25) 0%, rgba(245,160,32,0.1) 100%); border: 1.5px solid rgba(245,160,32,0.45); display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 1.5rem; box-shadow: 0 4px 16px rgba(245,160,32,0.2);">
                            <i class="fas fa-rocket" style="color: #F5A020;"></i>
                        </div>
                        <span style="display: inline-block; background: rgba(245,160,32,0.18); color: #FCD34D; border: 1px solid rgba(245,160,32,0.35); padding: 3px 12px; border-radius: 999px; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.3px; margin-bottom: 8px;">
                            نظام مدرسي سحابي ذكي
                        </span>
                        <h6 class="fw-bold mb-2 text-white" style="font-size: 1rem; line-height: 1.35;">حوّل مدرستك إلى مدرسة ذكية متكاملة!</h6>
                        <p style="font-size: 0.76rem; color: rgba(255,255,255,0.85); line-height: 1.65; margin-bottom: 18px;">
                            انضم لمنظومة <strong>SchoPlus</strong> السحابية — إدارة أكاديمية ومالية شاملة، تطبيق أولياء الأمور والطلاب، وإشعارات واتساب فورية مؤتمتة.
                        </p>
                        <a href="https://schoplus.sd" target="_blank" class="btn-luxury-gold justify-content-center w-100 shadow-sm" style="font-size: 0.8rem; font-weight: 800; padding: 10px 16px; background: linear-gradient(135deg, #F5A020 0%, #E68A00 100%); color: #0B2A52; border: none; border-radius: 12px;">
                            <span>تعرف على منظومة SchoPlus</span> <i class="fas fa-arrow-left ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<!-- FLOATING MOBILE BOTTOM ACTION BAR (Native App feel) -->
<div class="sp-mobile-bottom-bar d-md-none">
    <div class="d-flex align-items-center gap-2">
        <?php if ($school['is_verified'] || !empty($school['is_schoplus_client'])): ?>
            <?php if (!empty($school['whatsapp'])): ?>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>?text=<?= urlencode('مرحباً، رأيت مدرستكم على دليل SchoPlus وأود الاستفسار.') ?>" target="_blank" class="sp-mob-bottom-btn sp-mob-wa">
                    <i class="fab fa-whatsapp"></i> <span>واتساب</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($school['phone'])): ?>
                <a href="tel:<?= e($school['phone']) ?>" class="sp-mob-bottom-btn sp-mob-call">
                    <i class="fas fa-phone-alt"></i> <span>اتصال</span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="#claimSchoolModal" data-bs-toggle="modal" class="sp-mob-bottom-btn" style="background: linear-gradient(135deg, #F5A020 0%, #D97706 100%); color: #FFFFFF; border: none;">
                <i class="fas fa-id-badge"></i> <span>وثّق مدرستك</span>
            </a>
        <?php endif; ?>
        <a href="#tab-admission" class="sp-mob-bottom-btn sp-mob-inquiry" onclick="switchTab(document.querySelector('[data-target=\'tab-admission\']'), 'tab-admission')">
            <i class="fas fa-paper-plane"></i> <span>طلب قبول</span>
        </a>
        <button type="button" class="sp-mob-bottom-btn sp-mob-share" onclick="openShareModal()" title="مشاركة">
            <i class="fas fa-share-alt"></i>
        </button>
    </div>
</div>

<!-- SHARE MODAL -->
<div class="modal fade" id="shareSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-share-alt text-primary me-2"></i> مشاركة صفحة المدرسة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-3 rounded-4 d-inline-block mb-3 border" style="background: var(--bg-surface-soft);">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" alt="QR" class="img-fluid rounded-3" style="width: 140px; height: 140px;">
                    <div class="small text-muted mt-2 fw-medium">امسح للوصول المباشر</div>
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-muted">الرابط المختصر الأنيق:</label>
                    <div class="input-group">
                        <input type="text" id="shortUrlInput" class="form-control font-monospace" style="direction:ltr;" value="https://daleel.schoplus.sd/s/<?= e($school['slug']) ?>" readonly>
                        <button class="btn btn-primary fw-bold px-3" type="button" onclick="copyShortUrl()">
                            <i class="fas fa-copy me-1"></i><span id="copyBtnText">نسخ</span>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("تعرف على " . $school['name'] . ":\nhttps://daleel.schoplus.sd/s/" . $school['slug']) ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 fw-bold"><i class="fab fa-whatsapp me-1"></i> واتساب</a>
                    <a href="https://t.me/share/url?url=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>&text=<?= urlencode($school['name']) ?>" target="_blank" class="btn btn-info text-white btn-sm rounded-pill px-3 fw-bold"><i class="fab fa-telegram me-1"></i> تيليجرام</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold"><i class="fab fa-facebook me-1"></i> فيسبوك</a>
                </div>
                <div class="mt-3">
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" download="QR-<?= e($school['slug']) ?>.png" target="_blank" class="btn btn-sm btn-link text-muted">
                        <i class="fas fa-download me-1"></i> تحميل الباركود للطباعة
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CLAIM SCHOOL MODAL (المطالبة بملكية المدرسة وتوثيقها) -->
<div class="modal fade" id="claimSchoolModal" tabindex="-1" aria-labelledby="claimSchoolModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header px-4 py-3" style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); color: #FFFFFF; border-bottom: 2px solid #F5A020;">
                <div>
                    <h5 class="modal-title fw-bold fs-6 mb-1 text-white" id="claimSchoolModalTitle">
                        <i class="fas fa-id-badge text-warning me-2"></i> طلب توثيق ملكية المدرسة
                    </h5>
                    <span class="small" style="color: rgba(255,255,255,0.85); font-size: 0.72rem;">هل أنت مفوض رسمي لإدارة <?= e($school['name']) ?>؟ وثّق صفحتك الآن لتتحكم بالبيانات.</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="claim_school">
                <div class="modal-body p-4">
                    <?php if ($claimError): ?>
                        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-circle text-danger"></i>
                            <span><?= e($claimError) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="p-3 rounded-3 mb-3" style="background: #FFFBEB; border: 1px solid #FCD34D; font-size: 0.74rem; color: #92400E; line-height: 1.6;">
                        <i class="fas fa-shield-alt text-warning me-1"></i>
                        هذه المدرسة مدرجة كبيانات عامة لتسهيل وصول أولياء الأمور. لتوثيق ملكيتها ونقل صلاحيات التعديل لحسابك، يرجى تزويدنا ببيانات المسؤول.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">اسم المسؤول الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="claimant_name" class="form-control form-control-sm rounded-3" placeholder="مثال: د. محمد أحمد يوسف" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">المسمى الوظيفي <span class="text-danger">*</span></label>
                            <input type="text" name="claimant_title" class="form-control form-control-sm rounded-3" placeholder="مثال: المدير العام / مدير المرحلة" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">رقم الهاتف / واتساب <span class="text-danger">*</span></label>
                            <input type="tel" name="claimant_phone" class="form-control form-control-sm rounded-3" placeholder="01xxxxxxx" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">البريد الإلكتروني الرسمي</label>
                        <input type="email" name="claimant_email" class="form-control form-control-sm rounded-3" placeholder="admin@school.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">مستند إثبات وتفويض (اختياري)</label>
                        <input type="file" name="verification_doc" class="form-control form-control-sm rounded-3" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text" style="font-size: 0.68rem;">يمكنك إرفاق تفويض رسمي أو ترخيص المدرسة لتسريع اعتماد التوثيق.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark">ملاحظات إضافية</label>
                        <textarea name="notes" class="form-control form-control-sm rounded-3" rows="2" placeholder="أي بيانات أو روابط تساعدنا في سرعة التحقق"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-3 px-4 fw-bold" style="background: #0B2A52; border-color: #0B2A52;">
                        <i class="fas fa-check-circle me-1"></i> إرسال طلب التوثيق
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- NEWS READER MODAL (فتح الخبر كاملاً بتنسيقه الكامل) -->
<div class="modal fade" id="newsReaderModal" tabindex="-1" aria-labelledby="newsReaderModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="w-100 me-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-white text-primary border px-2 py-1 rounded-pill small" id="newsReaderModalDate"></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill small" id="newsReaderModalBranch" style="display: none;"></span>
                        </div>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill small px-2 py-1">
                            <i class="fas fa-bullhorn me-1"></i> إعلان مدرسي
                        </span>
                    </div>
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="newsReaderModalTitle" style="line-height: 1.45;"></h5>
                </div>
                <button type="button" class="btn-close align-self-start mt-1" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Frame for News Banner Image (منسقة بشكل فاخر وجذاب دون قص) -->
                <div id="newsReaderModalImgWrap" class="mb-4 text-center" style="display: none;">
                    <div style="display: inline-block; max-width: 520px; width: 100%;">
                        <img id="newsReaderModalImg" src="" alt="صورة الخبر أو الإعلان" class="img-fluid rounded-4 shadow-sm" style="width: 100%; height: auto; max-height: 290px; object-fit: contain; display: block; margin: 0 auto; border: 1px solid rgba(11,42,82,0.08); cursor: pointer;" onclick="spLightboxOpen(this.src, document.getElementById('newsReaderModalTitle').innerText)" title="انقر لتكبير الصورة">
                    </div>
                </div>

                <!-- Rich text content formatted from Quill -->
                <div id="newsReaderModalContent" class="sp-news-content p-2" style="font-size: 0.98rem; line-height: 1.95; color: #334155;"></div>
            </div>
            <div class="modal-footer bg-light border-top px-4 py-2 justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="newsReaderShareBtn" onclick="copyCurrentNewsLink()">
                        <i class="fas fa-link me-1"></i> نسخ رابط الخبر
                    </button>
                </div>
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- CUSTOM LIGHTBOX -->
<div id="spLightbox" onclick="spLightboxClose()">
    <div style="text-align: center;" onclick="event.stopPropagation()">
        <button id="spLightboxClose" onclick="spLightboxClose()"><i class="fas fa-times"></i></button>
        <img id="spLightboxImg" src="" alt="">
        <div id="spLightboxCaption" class="lb-caption"></div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(btn, targetId) {
    document.querySelectorAll('.sp-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.sp-tab-pane').forEach(p => p.style.display = 'none');
    if (btn) btn.classList.add('active');
    const pane = document.getElementById(targetId);
    if (pane) pane.style.display = 'block';
    if (window.innerWidth < 768) {
        document.querySelector('.sp-tabs-nav').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// News Reader Modal
let currentReadingNewsId = 0;
function openNewsReaderModal(itemOrId) {
    let item = null;
    if (typeof itemOrId === 'object' && itemOrId !== null) {
        item = itemOrId;
    } else if (window.newsDataStore && window.newsDataStore[itemOrId]) {
        item = window.newsDataStore[itemOrId];
    }
    if (!item) return;

    currentReadingNewsId = item.id;
    document.getElementById('newsReaderModalTitle').innerText = item.title || '';
    document.getElementById('newsReaderModalDate').innerText = item.date || '';
    document.getElementById('newsReaderModalContent').innerHTML = item.content || '';

    const branchBadge = document.getElementById('newsReaderModalBranch');
    if (branchBadge) {
        if (item.branch) {
            branchBadge.innerHTML = '<i class="fas fa-code-branch me-1"></i> ' + item.branch;
            branchBadge.style.display = 'inline-block';
        } else {
            branchBadge.style.display = 'none';
        }
    }

    const imgWrap = document.getElementById('newsReaderModalImgWrap');
    const imgElem = document.getElementById('newsReaderModalImg');
    if (item.image) {
        imgElem.src = item.image;
        imgWrap.style.display = 'block';
    } else {
        imgWrap.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('newsReaderModal')).show();
}

function copyNewsLink(newsId) {
    const url = window.location.origin + window.location.pathname + '#news-item-' + newsId;
    navigator.clipboard.writeText(url).then(function() {
        alert('تم نسخ رابط الخبر بنجاح!');
    });
}

function copyCurrentNewsLink() {
    if (currentReadingNewsId) {
        copyNewsLink(currentReadingNewsId);
    }
}

// Auto open tab or item if hash exists in URL or after inquiry submit
document.addEventListener('DOMContentLoaded', function() {
    const hasInquiryResult = <?= ($inquirySuccess || $inquiryError) ? 'true' : 'false' ?>;
    if (hasInquiryResult) {
        const admBtn = document.querySelector('[data-target="tab-admission"]');
        if (admBtn) {
            switchTab(admBtn, 'tab-admission');
            setTimeout(() => {
                const formEl = document.getElementById('inquiry-form-section');
                if (formEl) formEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 250);
        }
        return;
    }

    if (window.location.hash) {
        const hash = window.location.hash;
        if (hash === '#tab-admission' || hash === '#inquiry-form-section') {
            const admBtn = document.querySelector('[data-target="tab-admission"]');
            if (admBtn) {
                switchTab(admBtn, 'tab-admission');
                setTimeout(() => {
                    const targetEl = document.getElementById(hash.replace('#', '')) || document.getElementById('inquiry-form-section');
                    if (targetEl) targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 250);
            }
        } else if (hash === '#tab-fees' || hash === '#school-fees-section') {
            const feesBtn = document.querySelector('[data-target="tab-fees"]');
            if (feesBtn) {
                switchTab(feesBtn, 'tab-fees');
                setTimeout(() => {
                    const targetEl = document.getElementById('school-fees-section');
                    if (targetEl) targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 250);
            }
        } else if (hash.startsWith('#tab-')) {
            const targetTab = hash.substring(1);
            const btn = document.querySelector('[data-target="' + targetTab + '"]');
            if (btn) switchTab(btn, targetTab);
        } else if (hash.startsWith('#news-item-')) {
            const newsBtn = document.querySelector('[data-target="tab-news"]');
            if (newsBtn) {
                switchTab(newsBtn, 'tab-news');
                setTimeout(() => {
                    const targetEl = document.querySelector(hash);
                    if (targetEl) targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        }
    }
});

// Share Modal
function openShareModal() {
    new bootstrap.Modal(document.getElementById('shareSchoolModal')).show();
}
function copyShortUrl() {
    var input = document.getElementById('shortUrlInput');
    input.select(); input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = document.getElementById('copyBtnText');
        btn.innerText = 'تم النسخ!';
        setTimeout(function() { btn.innerText = 'نسخ'; }, 2500);
    });
}

// Lightbox
function spLightboxOpen(src, caption) {
    document.getElementById('spLightboxImg').src = src;
    document.getElementById('spLightboxCaption').innerText = caption || '';
    document.getElementById('spLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function spLightboxClose() {
    document.getElementById('spLightbox').classList.remove('open');
    document.getElementById('spLightboxImg').src = '';
    document.body.style.overflow = '';
}
// Historic School Community Voting Handler
function submitHistoricVote() {
    const btn = document.getElementById('btnVoteHistoric');
    const msgEl = document.getElementById('historicVoteMsg');
    if (!btn) return;

    btn.disabled = true;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري تسجيل التصويت...';

    const formData = new FormData();
    formData.append('action', 'vote_historic');
    formData.append('_csrf_token', '<?= csrf_token() ?>');

    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        msgEl.style.display = 'block';
        if (data.success) {
            msgEl.style.color = '#047857';
            msgEl.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> تم تسجيل تصويتك!';
            btn.style.background = '#10B981';
            btn.style.color = '#ffffff';

            // Update UI Counters
            const totalBadge = document.getElementById('historicVotesTotalBadge');
            if (totalBadge) totalBadge.innerText = (data.votes_count || 0) + ' صوت';

            const curCount = document.getElementById('historicVotesCurrentCount');
            if (curCount) curCount.innerText = data.votes_count || 0;

            const progBar = document.getElementById('historicProgressBar');
            if (progBar) {
                const pct = Math.min(100, ((data.votes_count || 0) / 500) * 100);
                progBar.style.width = pct + '%';
            }

            const qCount = document.getElementById('quickHistoricVotesCount');
            if (qCount) qCount.innerText = (data.votes_count || 0) + ' / 500';

            // If became historic after this vote, reload page to refresh badges
            if (data.is_historic) {
                setTimeout(() => { window.location.reload(); }, 1800);
            }
        } else {
            msgEl.style.color = data.already_voted ? '#B45309' : '#DC2626';
            msgEl.innerHTML = (data.already_voted ? '<i class="fas fa-info-circle me-1"></i> ' : '<i class="fas fa-exclamation-circle me-1"></i> ') + data.message;
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        }
    })
    .catch(err => {
        msgEl.style.display = 'block';
        msgEl.style.color = '#DC2626';
        msgEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> تعذر الاتصال بالخادم، يرجى المحاولة لاحقاً.';
        btn.innerHTML = oldHtml;
        btn.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/footer.php';
