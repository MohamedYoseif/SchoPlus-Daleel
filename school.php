<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Profile Page (صفحة الملف التعريفي للمدرسة)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

$pdo = db();
$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if (!$slug && !$id) {
    header('Location: index.php');
    exit;
}

// Fetch school
if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE slug = ?");
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ?");
    $stmt->execute([$id]);
}
$school = $stmt->fetch();

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
            <a href="index.php" class="btn-luxury-primary"><i class="fas fa-home me-2"></i> العودة لدليل المدارس</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// Check permission if pending/rejected: only owner can view
$currSchool = currentSchool();
$isOwner = $currSchool && ($currSchool['id'] === $school['id']);
if ($school['status'] === 'suspended') {
    http_response_code(403);
    $pageTitle = 'المدرسة موقوفة مؤقتاً';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="p-5 bg-white rounded-4 border border-danger shadow-sm my-5" style="max-width: 600px; margin: auto;">
            <div class="display-3 text-danger mb-3"><i class="fas fa-ban"></i></div>
            <h3 class="fw-bold mb-3">تم إيقاف ظهور المدرسة مؤقتاً</h3>
            <p class="text-muted mb-4"><?= e($school['rejection_reason'] ?: 'تم إيقاف ظهور صفحة هذه المدرسة مؤقتاً بقرار إداري. للمراجعة يرجى التواصل مع إدارة دليل المدارس.') ?></p>
            <a href="index.php" class="btn-luxury-outline"><i class="fas fa-arrow-right me-2"></i> تصفح المدارس المعتمدة</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($school['status'] !== 'approved' && !$isOwner) {
    http_response_code(403);
    $pageTitle = 'المدرسة بانتظار التأكيد';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="p-5 bg-white rounded-4 border border-warning shadow-sm my-5" style="max-width: 600px; margin: auto;">
            <div class="display-3 text-warning mb-3"><i class="fas fa-hourglass-half"></i></div>
            <h3 class="fw-bold mb-3">ملف المدرسة بانتظار الاعتماد</h3>
            <p class="text-muted mb-4">هذه المدرسة مسجلة لدينا وحالياً قيد المراجعة والتدقيق بواسطة فريق الدليل وسوف تظهر للجمهور فور اكتمال الاعتماد.</p>
            <a href="index.php" class="btn-luxury-outline"><i class="fas fa-arrow-right me-2"></i> تصفح المدارس المعتمدة</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// Increment view count
$pdo->prepare("UPDATE daleel_schools SET views_count = views_count + 1 WHERE id = ?")->execute([$school['id']]);

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
        $studentGrade = trim($_POST['student_grade'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$parentName || !$parentPhone) {
            $inquiryError = 'يرجى كتابة اسم ولي الأمر ورقم الهاتف.';
        } else {
            $inqStmt = $pdo->prepare("INSERT INTO daleel_school_inquiries 
                (school_id, parent_name, parent_phone, student_grade, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'new', NOW())");
            $inqStmt->execute([$school['id'], $parentName, $parentPhone, $studentGrade, $message]);
            $inquirySuccess = true;
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

// Facilities, Stages & Campus Gallery
$facilities = json_decode($school['facilities'] ?? '[]', true) ?: [];
$stages = json_decode($school['stages'] ?? '[]', true) ?: [];
$galleryList = json_decode($school['gallery'] ?? '[]', true) ?: [];

$pageTitle = $school['name'];
$ogTitle = $school['name'] . ' | دليل المدارس الذكي';
$ogDesc = mb_substr(strip_tags(($school['description'] ?? '') ?: ($school['name'] . ' — ' . $school['state'] . '، ' . $school['city'] . '. تصفح الرسوم، المرافق، وتواصل مباشرة.')), 0, 160);
$ogImage = !empty($school['logo_path']) ? (str_starts_with($school['logo_path'], 'http') ? $school['logo_path'] : 'https://daleel.schoplus.sd/' . ltrim($school['logo_path'], '/')) : (!empty($school['cover_path']) ? (str_starts_with($school['cover_path'], 'http') ? $school['cover_path'] : 'https://daleel.schoplus.sd/' . ltrim($school['cover_path'], '/')) : 'https://daleel.schoplus.sd/logo.png');
$ogUrl = 'https://daleel.schoplus.sd/school/' . urlencode($school['slug']);
require_once __DIR__ . '/header.php';
?>

<style>
/* ===== SCHOOL PROFILE — LUXURY REDESIGN ===== */
.sp-hero-cover {
    height: 280px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0B2A52 0%, #1E5FA8 60%, #D97706 130%);
    border-radius: 24px 24px 0 0;
}
.sp-hero-cover img.cover-img {
    width: 100%; height: 100%; object-fit: cover;
    position: absolute; inset: 0;
}
.sp-hero-cover .cover-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(11,42,82,0.15) 0%, rgba(11,42,82,0.75) 100%);
}
.sp-hero-cover .cover-pattern {
    position: absolute; inset: 0; opacity: 0.05;
    background-image: repeating-linear-gradient(45deg, #fff 0, #fff 1px, transparent 0, transparent 40%);
    background-size: 16px 16px;
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
@media (max-width: 767px) {
    .sp-hero-cover { height: 190px; border-radius: 16px 16px 0 0; }
    .sp-profile-card { padding: 0 16px 20px; border-radius: 0 0 16px 16px; }
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
    background: linear-gradient(135deg, var(--brand-navy), var(--brand-blue));
    display: flex; align-items: center; justify-content: center;
    font-size: 2.6rem; font-weight: 900; color: #FFFFFF;
}

.sp-quick-stats {
    display: flex; align-items: center; gap: 20px;
    flex-wrap: wrap;
    padding: 14px 0 0;
    border-top: 1px solid var(--border-subtle);
    margin-top: 18px;
}
.sp-stat-item {
    display: flex; align-items: center; gap: 7px;
    font-size: 0.84rem; color: var(--text-muted); font-weight: 600;
}
.sp-stat-item strong { color: var(--text-main); font-weight: 800; }

.sp-tabs-nav {
    background: #FFFFFF;
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    padding: 6px;
    box-shadow: 0 2px 14px rgba(11,42,82,0.04);
    display: flex; gap: 6px;
    position: sticky; top: 78px; z-index: 40;
}
.sp-tab-btn {
    flex: 1;
    background: none; border: none;
    padding: 10px 10px;
    border-radius: 12px;
    font-weight: 700; font-size: 0.86rem;
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
    box-shadow: 0 4px 14px rgba(11,42,82,0.22);
}
.sp-tab-btn .tab-count {
    background: rgba(255,255,255,0.25);
    color: inherit;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 0.72rem;
    font-weight: 800;
}
.sp-tab-btn:not(.active) .tab-count {
    background: var(--bg-surface-soft);
    color: var(--text-muted);
}

.sp-section {
    background: #FFFFFF;
    border: 1px solid var(--border-subtle);
    border-radius: 20px;
    box-shadow: 0 3px 18px rgba(11,42,82,0.035);
    overflow: hidden;
    margin-bottom: 22px;
}
.sp-section-head {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border-subtle);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    flex-wrap: wrap;
}
.sp-section-title {
    display: flex; align-items: center; gap: 10px;
    font-size: 1.05rem; font-weight: 800; color: var(--brand-navy);
}
.sp-section-title .title-icon {
    width: 36px; height: 36px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; flex-shrink: 0;
}
.sp-section-body { padding: 24px; }
@media (max-width: 767px) {
    .sp-section-head, .sp-section-body { padding: 16px; }
}

.vm-block {
    border-radius: 16px;
    padding: 18px 20px;
    position: relative; overflow: hidden;
}
.vm-vision { background: #EFF6FF; border: 1px solid #DBEAFE; border-right: 4px solid var(--brand-blue); }
.vm-mission { background: #FFFBEB; border: 1px solid #FEF3C7; border-right: 4px solid var(--brand-gold); }

.facility-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: #FFFFFF;
    border: 1.5px solid var(--border-subtle);
    border-radius: 12px;
    padding: 9px 16px;
    font-size: 0.84rem; font-weight: 700; color: var(--text-body);
    transition: all 0.2s ease;
}
.facility-chip:hover {
    background: #EFF6FF;
    border-color: #BFDBFE;
    color: var(--brand-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30,95,168,0.1);
}
.facility-chip i { color: var(--brand-blue); font-size: 0.95rem; }

.sp-inquiry-card {
    background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
    border: 1.5px solid #BFDBFE;
    border-radius: 20px; padding: 26px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(30,95,168,0.05);
}
.sp-inquiry-card .form-control, .sp-inquiry-card .form-select {
    border: 1.5px solid var(--border-subtle);
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 0.9rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.sp-inquiry-card .form-control:focus, .sp-inquiry-card .form-select:focus {
    border-color: var(--brand-blue);
    box-shadow: 0 0 0 3px rgba(30,95,168,0.12);
}

.sp-gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
@media (max-width: 576px) { .sp-gallery-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; } }
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
    background: linear-gradient(to top, rgba(11,42,82,0.85) 0%, rgba(11,42,82,0.1) 60%, transparent 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
    display: flex; align-items: flex-end; padding: 14px;
}
.sp-gallery-item:hover .sp-gallery-overlay { opacity: 1; }
.sp-gallery-caption { color: #FFFFFF; font-size: 0.8rem; font-weight: 700; line-height: 1.35; }
.sp-gallery-zoom {
    position: absolute; top: 10px; left: 10px;
    width: 34px; height: 34px;
    background: rgba(255,255,255,0.92);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; color: var(--brand-navy);
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
    background: linear-gradient(to bottom, var(--brand-blue), var(--brand-gold));
    border-radius: 0 16px 16px 0;
}
.sp-news-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(11,42,82,0.07); }
.sp-news-date {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--bg-surface-soft);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.76rem; font-weight: 700; color: var(--text-muted);
}

.sp-review-card {
    border-radius: 16px;
    border: 1px solid var(--border-subtle);
    padding: 20px 22px;
    background: #FFFFFF;
    transition: all 0.22s ease;
}
.sp-review-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11,42,82,0.06); }
.sp-reviewer-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 1.15rem; color: #FFFFFF;
    flex-shrink: 0;
}
.sp-stars { display: inline-flex; gap: 3px; }
.sp-stars i { font-size: 0.85rem; }
.sp-school-reply {
    border-radius: 14px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-right: 4px solid var(--brand-blue);
    padding: 14px 16px;
    margin-top: 14px;
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
    padding: 16px 22px;
    background: var(--bg-surface-tint);
    border-bottom: 1px solid var(--border-subtle);
    display: flex; align-items: center; gap: 8px;
    font-size: 0.92rem; font-weight: 800; color: var(--brand-navy);
}
.sp-info-card-body { padding: 18px 22px; }

.sp-fact-row {
    display: flex; align-items: center; gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-subtle);
}
.sp-fact-row:last-child { border-bottom: none; padding-bottom: 0; }
.sp-fact-icon {
    width: 36px; height: 36px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; flex-shrink: 0;
}
.sp-fact-label { font-size: 0.76rem; color: var(--text-muted); font-weight: 700; display: block; }
.sp-fact-value { font-size: 0.9rem; color: var(--text-main); font-weight: 800; }

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
    background: linear-gradient(135deg, #0B2A52 0%, #16467D 100%);
    border-radius: 20px; padding: 24px 22px; text-align: center; color: #FFFFFF;
    position: relative; overflow: hidden;
    box-shadow: 0 8px 28px rgba(11,42,82,0.18);
}
.sp-upgrade-cta::before {
    content: '';
    position: absolute; top: -30px; right: -30px;
    width: 110px; height: 110px; border-radius: 50%;
    background: rgba(245,160,32,0.16);
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
.rating-bar-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, #F59E0B, #D97706); }

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
</style>

<?php
// Rating distribution
$ratingDist = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
foreach ($reviewsList as $rev) { $ratingDist[(int)$rev['rating']]++; }
$totalRev = max(1, count($reviewsList));
?>

<div class="school-profile-page" style="padding-bottom: 60px;">

    <!-- Breadcrumb -->
    <div class="container" style="padding-top: 20px; padding-bottom: 8px;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent p-0 mb-0" style="font-size: 0.82rem;">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color: var(--text-muted);"><i class="fas fa-home me-1"></i> دليل المدارس</a></li>
                <li class="breadcrumb-item"><a href="index.php?state=<?= urlencode($school['state']) ?>" class="text-decoration-none" style="color: var(--text-muted);"><?= e($school['state']) ?></a></li>
                <li class="breadcrumb-item"><a href="index.php?state=<?= urlencode($school['state']) ?>&city=<?= urlencode($school['city']) ?>" class="text-decoration-none" style="color: var(--text-muted);"><?= e($school['city']) ?></a></li>
                <li class="breadcrumb-item active fw-bold" style="color: var(--brand-navy);" aria-current="page"><?= e($school['name']) ?></li>
            </ol>
        </nav>
    </div>

    <?php if ($isOwner && $school['status'] === 'pending'): ?>
    <div class="container mb-3">
        <div class="d-flex align-items-center gap-3 p-3 rounded-4 border" style="background: #FFFBEB; border-color: #FCD34D !important;">
            <i class="fas fa-hourglass-half text-warning fs-4"></i>
            <div class="flex-grow-1">
                <span class="fw-bold text-dark small">معاينة أولية — هذا الملف بانتظار مراجعة الإدارة</span>
                <span class="text-muted small d-none d-sm-inline ms-2">سيظهر للجمهور فور اكتمال التدقيق والموافقة</span>
            </div>
            <a href="dashboard.php" class="btn-luxury-primary btn-sm py-1 px-3 text-nowrap"><i class="fas fa-edit me-1"></i> لوحة الإدارة</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- HERO COVER + PROFILE CARD -->
    <div class="container">
        <div class="sp-hero-cover mb-0">
            <?php if (!empty($school['cover_path'])): ?>
                <img src="<?= e($school['cover_path']) ?>" alt="غلاف <?= e($school['name']) ?>" class="cover-img">
            <?php endif; ?>
            <div class="cover-overlay"></div>
            <div class="cover-pattern"></div>

            <!-- Top Badges on Cover -->
            <div style="position: absolute; top: 18px; right: 22px; display: flex; gap: 8px; z-index: 3; flex-wrap: wrap;">
                <?php if ($school['is_schoplus_client']): ?>
                    <span class="badge-schoplus"><i class="fas fa-bolt"></i> مدرسة SchoPlus الذكية</span>
                <?php endif; ?>
                <?php if ($school['is_verified']): ?>
                    <span class="badge-verified"><i class="fas fa-check-circle"></i> معتمدة رسمياً</span>
                <?php endif; ?>
            </div>

            <!-- Top Rating on Cover -->
            <?php if ($school['reviews_count'] > 0): ?>
            <div style="position: absolute; top: 18px; left: 22px; z-index: 3; background: rgba(11,42,82,0.65); backdrop-filter: blur(8px); border-radius: 12px; padding: 6px 14px; color: #FFFFFF; display: flex; align-items: center; gap: 6px; font-size: 0.86rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15);">
                <i class="fas fa-star text-warning"></i>
                <?= number_format($school['rating_avg'], 1) ?>
                <span style="opacity: 0.8; font-weight: 400; font-size: 0.78rem;">(<?= $school['reviews_count'] ?> تقييم)</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="sp-profile-card">
            <div class="d-flex flex-column flex-md-row align-items-md-end gap-3 justify-content-between">
                <div class="d-flex align-items-end gap-3">
                    <!-- Logo -->
                    <div class="sp-logo-wrap">
                        <?php if (!empty($school['logo_path'])): ?>
                            <img src="<?= e($school['logo_path']) ?>" alt="<?= e($school['name']) ?>">
                        <?php else: ?>
                            <div class="sp-logo-placeholder"><?= mb_substr($school['name'], 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <!-- Title & Details -->
                    <div style="padding-bottom: 4px;">
                        <h1 class="fw-bold mb-1" style="font-size: clamp(1.25rem, 3.5vw, 1.85rem); color: var(--brand-navy); line-height: 1.25;"><?= e($school['name']) ?></h1>
                        <?php if (!empty($school['name_en'])): ?>
                            <div style="font-size: 0.88rem; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;"><?= e($school['name_en']) ?></div>
                        <?php endif; ?>
                        <div style="font-size: 0.84rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-map-marker-alt text-danger"></i>
                            <span><?= e($school['state']) ?> — <?= e($school['city']) ?><?= !empty($school['district']) ? ' (' . e($school['district']) . ')' : '' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2 pb-1" style="margin-bottom: 2px;">
                    <?php if (!empty($school['whatsapp'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>?text=<?= urlencode('مرحباً، رأيت مدرستكم على دليل SchoPlus وأود الاستفسار.') ?>" target="_blank" class="btn-luxury-primary px-3 py-2" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); box-shadow: 0 4px 14px rgba(22,163,74,0.24);">
                            <i class="fab fa-whatsapp"></i> <span>واتساب</span>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($school['phone'])): ?>
                        <a href="tel:<?= e($school['phone']) ?>" class="btn-luxury-outline px-3 py-2">
                            <i class="fas fa-phone-alt"></i> <span class="d-none d-sm-inline">اتصال</span>
                        </a>
                    <?php endif; ?>
                    <a href="#inquiry-form-section" class="btn-luxury-gold px-3 py-2">
                        <i class="fas fa-pen-alt"></i> <span>طلب قبول</span>
                    </a>
                    <button type="button" class="btn-luxury-outline px-3 py-2" onclick="openShareModal()" title="مشاركة الرابط والباركود">
                        <i class="fas fa-share-alt text-primary"></i>
                    </button>
                    <?php if ($isOwner): ?>
                        <a href="dashboard.php" class="btn-luxury-outline px-3 py-2" title="لوحة إدارة المدرسة">
                            <i class="fas fa-cog text-muted"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats Bar -->
            <div class="sp-quick-stats">
                <div class="sp-stat-item">
                    <i class="fas fa-eye" style="color: var(--brand-blue);"></i>
                    <span><strong><?= number_format($school['views_count']) ?></strong> مشاهدة</span>
                </div>
                <div class="sp-stat-item">
                    <i class="fas fa-star" style="color: #F59E0B;"></i>
                    <span><strong><?= number_format($school['rating_avg'], 1) ?></strong> / 5 (<?= $school['reviews_count'] ?> تقييم)</span>
                </div>
                <div class="sp-stat-item">
                    <i class="fas fa-building" style="color: var(--brand-gold);"></i>
                    <span><?= SudanGeography::schoolTypeLabel($school['school_type']) ?></span>
                </div>
                <?php if (!empty($school['approx_fees'])): ?>
                <div class="sp-stat-item">
                    <i class="fas fa-coins" style="color: #059669;"></i>
                    <span>الرسوم: <strong><?= e($school['approx_fees']) ?></strong></span>
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

    <?php if ($school['is_schoplus_client']): ?>
    <!-- SchoPlus Smart Banner -->
    <div class="container mt-4">
        <div style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); border-radius: 20px; padding: 20px 26px; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; box-shadow: 0 8px 28px rgba(11,42,82,0.18);">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245,160,32,0.18); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fas fa-bolt text-warning"></i>
                </div>
                <div>
                    <div class="fw-bold" style="font-size: 1rem;">مدرسة ذكية معتمدة — تعمل بنظام SchoPlus السحابي</div>
                    <div style="font-size: 0.8rem; opacity: 0.85; margin-top: 3px;">بوابة إلكترونية لأولياء الأمور · متابعة الدرجات والغياب فورياً · إشعارات واتساب لحظية</div>
                </div>
            </div>
            <a href="https://schoplus.sd" target="_blank" class="btn-luxury-gold btn-sm px-4 py-2" style="white-space: nowrap;">
                <i class="fas fa-external-link-alt"></i> تعرف على SchoPlus
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT LAYOUT -->
    <div class="container mt-4">
        <div class="row g-4">

            <!-- LEFT: TABS CONTENT -->
            <div class="col-lg-8">

                <!-- Tab Navigation -->
                <div class="sp-tabs-nav mb-4">
                    <button class="sp-tab-btn active" data-target="tab-overview" onclick="switchTab(this, 'tab-overview')">
                        <i class="fas fa-info-circle"></i>
                        <span class="d-none d-sm-inline">نبذة ومعلومات</span>
                        <span class="d-sm-none">نبذة</span>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-gallery" onclick="switchTab(this, 'tab-gallery')">
                        <i class="fas fa-images"></i>
                        <span class="d-none d-sm-inline">معرض الصور</span>
                        <span class="d-sm-none">صور</span>
                        <?php if (count($galleryList)): ?><span class="tab-count"><?= count($galleryList) ?></span><?php endif; ?>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-news" onclick="switchTab(this, 'tab-news')">
                        <i class="fas fa-bullhorn"></i>
                        <span class="d-none d-sm-inline">الأخبار</span>
                        <span class="d-sm-none">أخبار</span>
                        <?php if (count($newsList)): ?><span class="tab-count"><?= count($newsList) ?></span><?php endif; ?>
                    </button>
                    <button class="sp-tab-btn" data-target="tab-reviews" onclick="switchTab(this, 'tab-reviews')">
                        <i class="fas fa-star"></i>
                        <span class="d-none d-sm-inline">التقييمات</span>
                        <span class="d-sm-none">تقييم</span>
                        <?php if (count($reviewsList)): ?><span class="tab-count"><?= count($reviewsList) ?></span><?php endif; ?>
                    </button>
                </div>

                <!-- TAB: OVERVIEW -->
                <div id="tab-overview" class="sp-tab-pane">

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
                                <p style="color: var(--text-body); line-height: 1.85; white-space: pre-line; font-size: 0.94rem; margin: 0;"><?= e($school['description']) ?></p>
                            <?php else: ?>
                                <p class="text-muted fst-italic mb-0">لم تضف المدرسة وصفاً تعريفياً بعد.</p>
                            <?php endif; ?>

                            <?php if (!empty($school['vision']) || !empty($school['mission'])): ?>
                                <div class="row g-3 mt-3">
                                    <?php if (!empty($school['vision'])): ?>
                                        <div class="col-md-6">
                                            <div class="vm-block vm-vision">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <i class="fas fa-eye" style="color: var(--brand-blue);"></i>
                                                    <span class="fw-bold" style="font-size: 0.9rem; color: var(--brand-blue);">رؤية المدرسة</span>
                                                </div>
                                                <p style="font-size: 0.86rem; color: #374151; margin: 0; line-height: 1.7;"><?= e($school['vision']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($school['mission'])): ?>
                                        <div class="col-md-6">
                                            <div class="vm-block vm-mission">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <i class="fas fa-bullseye" style="color: var(--brand-gold);"></i>
                                                    <span class="fw-bold" style="font-size: 0.9rem; color: var(--brand-gold);">رسالة المدرسة</span>
                                                </div>
                                                <p style="font-size: 0.86rem; color: #374151; margin: 0; line-height: 1.7;"><?= e($school['mission']) ?></p>
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
                                <div class="title-icon" style="background: #F0FDF4; color: #16a34a;"><i class="fas fa-cubes"></i></div>
                                المرافق والخدمات المتاحة
                            </div>
                            <?php if (!empty($facilities)): ?>
                                <span class="badge" style="background: #F0FDF4; color: #15803d; border: 1px solid #BBF7D0; border-radius: 20px; padding: 5px 14px; font-size: 0.76rem; font-weight: 800;"><?= count($facilities) ?> مرافق</span>
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

                    <!-- Inquiry Form -->
                    <div class="sp-inquiry-card" id="inquiry-form-section">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div style="width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg, var(--brand-blue), var(--brand-navy)); display: flex; align-items: center; justify-content: center; color: #FFFFFF; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 14px rgba(30,95,168,0.25);">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0" style="color: var(--brand-navy); font-size: 1.05rem;">طلب قبول أو استفسار مباشر</h5>
                                <p class="mb-0" style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">أرسل بياناتك وسيقوم فريق التسجيل بالمدرسة بالتواصل معك مباشرة</p>
                            </div>
                        </div>

                        <?php if ($inquirySuccess): ?>
                            <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-3" style="background: #F0FDF4; border: 1.5px solid #BBF7D0;">
                                <i class="fas fa-check-circle text-success fs-3"></i>
                                <div>
                                    <div class="fw-bold text-success">تم إرسال طلبكم بنجاح!</div>
                                    <div class="small text-muted">تم استلام طلبكم في لوحة إدارة المدرسة وسيتواصلون معكم قريباً</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($inquiryError): ?>
                            <div class="alert alert-danger border-0 rounded-4 py-2 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <?= e($inquiryError) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="school.php?slug=<?= urlencode($school['slug']) ?>#inquiry-form-section">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_inquiry">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">اسم ولي الأمر <span class="text-danger">*</span></label>
                                    <input type="text" name="parent_name" required class="form-control" placeholder="مثال: محمد أحمد علي">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">رقم الهاتف / واتساب <span class="text-danger">*</span></label>
                                    <input type="tel" name="parent_phone" required class="form-control" placeholder="09xxxxxxx أو 01xxxxxxx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">الصف / المرحلة المطلوبة</label>
                                    <input type="text" name="student_grade" class="form-control" placeholder="مثال: الصف الثالث أساس / أولى ثانوي">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-secondary">أية ملاحظات إضافية</label>
                                    <input type="text" name="message" class="form-control" placeholder="استفسار عن الرسوم أو المواصلات...">
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-paper-plane"></i> إرسال الطلب الآن
                                    </button>
                                </div>
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
                                        <div class="sp-gallery-item" onclick="spLightboxOpen('<?= e($photo['path']) ?>', '<?= e(addslashes($photo['caption'] ?: $school['name'])) ?>')">
                                            <img src="<?= e($photo['path']) ?>" alt="<?= e($photo['caption'] ?: $school['name']) ?>" loading="lazy">
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
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($newsList as $item): ?>
                                        <div class="sp-news-card">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                                <h6 class="fw-bold mb-0" style="color: var(--brand-navy); font-size: 0.98rem;"><?= e($item['title']) ?></h6>
                                                <span class="sp-news-date">
                                                    <i class="far fa-calendar-alt"></i>
                                                    <?= date('d / m / Y', strtotime($item['published_at'] ?: $item['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 0.88rem; color: var(--text-body); line-height: 1.75; white-space: pre-line; margin: 0;"><?= e($item['content']) ?></p>
                                            <?php if (!empty($item['image_path'])): ?>
                                                <div class="mt-3">
                                                    <img src="<?= e($item['image_path']) ?>" alt="صورة الخبر" loading="lazy" class="img-fluid rounded-3" style="max-height: 280px; object-fit: cover; width: 100%;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
                                <div class="col-md-3 text-center border-start">
                                    <div style="font-size: 3.5rem; font-weight: 900; color: var(--brand-navy); line-height: 1;"><?= number_format($school['rating_avg'], 1) ?></div>
                                    <div class="sp-stars my-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= round($school['rating_avg']) ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700;"><?= $school['reviews_count'] ?> تقييم موثق</div>
                                </div>
                                <div class="col-md-9">
                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                        <div class="rating-bar-wrap">
                                            <span style="font-size: 0.8rem; color: var(--text-muted); width: 14px; text-align: left; font-weight: 700;"><?= $star ?></span>
                                            <i class="fas fa-star text-warning" style="font-size: 0.72rem;"></i>
                                            <div class="rating-bar flex-grow-1">
                                                <div class="rating-bar-fill" style="width: <?= $totalRev > 0 ? round($ratingDist[$star] / $totalRev * 100) : 0 ?>%;"></div>
                                            </div>
                                            <span style="font-size: 0.78rem; color: var(--text-muted); width: 28px; font-weight: 700;"><?= $ratingDist[$star] ?></span>
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
                            <form method="POST" action="school.php?slug=<?= urlencode($school['slug']) ?>#reviews">
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
                                            <option value="5">⭐⭐⭐⭐⭐ ممتاز (5)</option>
                                            <option value="4">⭐⭐⭐⭐ جيد جداً (4)</option>
                                            <option value="3">⭐⭐⭐ متوسط (3)</option>
                                            <option value="2">⭐⭐ دون المتوسط (2)</option>
                                            <option value="1">⭐ ضعيف (1)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-secondary">تعليقك وتجربتك <span class="text-danger">*</span></label>
                                        <textarea name="comment" required rows="3" class="form-control" placeholder="اكتب تقييمك بصدق عن البيئة التعليمية، الكادر، والاهتمام بالطلاب..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn-luxury-primary px-4 py-2">
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
                            $avatarColors = ['#1E5FA8','#059669','#D97706','#7C3AED','#DB2777','#0B2A52'];
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
                                            <div class="fw-bold" style="color: var(--text-main); font-size: 0.95rem;"><?= e($rev['reviewer_name']) ?></div>
                                            <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">
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
                                <p style="font-size: 0.9rem; color: var(--text-body); line-height: 1.75; margin: 0;"><?= e($rev['comment']) ?></p>
                                <?php if (!empty($rev['school_reply'])): ?>
                                    <div class="sp-school-reply">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge" style="background: #DBEAFE; color: #1d4ed8; font-size: 0.74rem; padding: 4px 10px; border-radius: 20px; font-weight: 800;">
                                                <i class="fas fa-reply me-1"></i> رد إدارة المدرسة
                                            </span>
                                            <?php if (!empty($rev['school_replied_at'])): ?>
                                                <span style="font-size: 0.74rem; color: var(--text-muted); font-weight: 600;"><?= date('d / m / Y', strtotime($rev['school_replied_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="font-size: 0.86rem; color: var(--text-body); margin: 0; line-height: 1.7;"><?= e($rev['school_reply']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

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
                            <div class="sp-fact-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-building"></i></div>
                            <div>
                                <span class="sp-fact-label">نوع المدرسة</span>
                                <span class="sp-fact-value"><?= SudanGeography::schoolTypeLabel($school['school_type']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #FDF4FF; color: #9333ea;"><i class="fas fa-user-friends"></i></div>
                            <div>
                                <span class="sp-fact-label">نوع الطلاب</span>
                                <span class="sp-fact-value"><?= SudanGeography::genderTypeLabel($school['gender_type']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #F0FDF4; color: #16a34a;"><i class="fas fa-graduation-cap"></i></div>
                            <div>
                                <span class="sp-fact-label">المنهج الدراسي</span>
                                <span class="sp-fact-value"><?= SudanGeography::curriculumLabel($school['curriculum']) ?></span>
                            </div>
                        </div>
                        <div class="sp-fact-row">
                            <div class="sp-fact-icon" style="background: #FFFBEB; color: #d97706;"><i class="fas fa-coins"></i></div>
                            <div>
                                <span class="sp-fact-label">متوسط الرسوم السنوية</span>
                                <span class="sp-fact-value" style="color: #16a34a;"><?= e($school['approx_fees'] ?: 'تحدد مع الإدارة') ?></span>
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

                <!-- Contact Box -->
                <?php if (!empty($school['phone']) || !empty($school['whatsapp']) || !empty($school['email']) || !empty($school['address'])): ?>
                <div class="sp-info-card">
                    <div class="sp-info-card-head">
                        <i class="fas fa-phone-alt" style="color: var(--brand-blue);"></i>
                        بيانات الاتصال والتواصل
                    </div>
                    <div class="sp-info-card-body" style="padding-top: 8px; padding-bottom: 8px;">
                        <?php if (!empty($school['phone'])): ?>
                            <a href="tel:<?= e($school['phone']) ?>" class="sp-contact-row text-decoration-none">
                                <div class="sp-contact-icon" style="background: #EFF6FF; color: var(--brand-blue);"><i class="fas fa-phone"></i></div>
                                <div>
                                    <span style="font-size: 0.74rem; color: var(--text-muted); display: block; font-weight: 700;">هاتف المدرسة</span>
                                    <span class="fw-bold" style="color: var(--text-main); font-size: 0.92rem;"><?= e($school['phone']) ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($school['whatsapp'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>" target="_blank" class="sp-contact-row text-decoration-none">
                                <div class="sp-contact-icon" style="background: #F0FDF4; color: #16a34a;"><i class="fab fa-whatsapp"></i></div>
                                <div>
                                    <span style="font-size: 0.74rem; color: var(--text-muted); display: block; font-weight: 700;">واتساب مباشر</span>
                                    <span class="fw-bold" style="color: #16a34a; font-size: 0.92rem;"><?= e($school['whatsapp']) ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($school['email'])): ?>
                            <a href="mailto:<?= e($school['email']) ?>" class="sp-contact-row text-decoration-none">
                                <div class="sp-contact-icon" style="background: #F0F9FF; color: #0284c7;"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <span style="font-size: 0.74rem; color: var(--text-muted); display: block; font-weight: 700;">البريد الإلكتروني</span>
                                    <span style="color: var(--text-body); font-size: 0.88rem;"><?= e($school['email']) ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($school['address'])): ?>
                            <div class="sp-contact-row">
                                <div class="sp-contact-icon" style="background: #FFF1F2; color: #e11d48;"><i class="fas fa-map-pin"></i></div>
                                <div>
                                    <span style="font-size: 0.74rem; color: var(--text-muted); display: block; font-weight: 700;">العنوان الفعلي</span>
                                    <span style="color: var(--text-body); font-size: 0.88rem;"><?= e($school['address']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($school['google_map_url'])): ?>
                        <div style="padding: 0 16px 16px;">
                            <a href="<?= e($school['google_map_url']) ?>" target="_blank" class="btn-luxury-outline justify-content-center w-100" style="font-size: 0.84rem;">
                                <i class="fas fa-map-marked-alt text-danger"></i> فتح في خرائط Google
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Social Links -->
                <?php if (!empty($school['facebook']) || !empty($school['website'])): ?>
                <div class="sp-info-card">
                    <div class="sp-info-card-head">
                        <i class="fas fa-globe" style="color: var(--brand-blue);"></i>
                        الحضور الرقمي للمدرسة
                    </div>
                    <div class="sp-info-card-body d-flex flex-column gap-2">
                        <?php if (!empty($school['facebook'])): ?>
                            <a href="<?= e($school['facebook']) ?>" target="_blank" class="btn-luxury-outline justify-content-between" style="font-size: 0.86rem;">
                                <span><i class="fab fa-facebook me-2 text-primary"></i> صفحة فيسبوك</span>
                                <i class="fas fa-external-link-alt text-muted small"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($school['website'])): ?>
                            <a href="<?= e($school['website']) ?>" target="_blank" class="btn-luxury-outline justify-content-between" style="font-size: 0.86rem;">
                                <span><i class="fas fa-globe me-2 text-muted"></i> الموقع الإلكتروني</span>
                                <i class="fas fa-external-link-alt text-muted small"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SchoPlus Upgrade CTA (for non-clients) -->
                <?php if (!$school['is_schoplus_client']): ?>
                <div class="sp-upgrade-cta">
                    <div style="position: relative; z-index: 1;">
                        <div style="width: 52px; height: 52px; border-radius: 16px; background: rgba(245,160,32,0.22); border: 1.5px solid rgba(245,160,32,0.35); display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 1.4rem;">
                            <i class="fas fa-rocket text-warning"></i>
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size: 1rem;">حوّل مدرستك لمدرسة ذكية!</h6>
                        <p style="font-size: 0.82rem; opacity: 0.85; line-height: 1.65; margin-bottom: 18px;">انضم لمنظومة SchoPlus السحابية — إدارة أكاديمية ومالية متكاملة، تطبيق أولياء الأمور، وإشعارات واتساب فورية.</p>
                        <a href="https://schoplus.sd" target="_blank" class="btn-luxury-gold justify-content-center w-100">
                            <i class="fas fa-arrow-left"></i> تعرف على SchoPlus
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>
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
    btn.classList.add('active');
    const pane = document.getElementById(targetId);
    if (pane) pane.style.display = 'block';
    if (window.innerWidth < 768) {
        document.querySelector('.sp-tabs-nav').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

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
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') spLightboxClose();
});
</script>

<?php require_once __DIR__ . '/footer.php';
