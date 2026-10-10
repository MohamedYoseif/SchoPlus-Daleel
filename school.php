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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inquiry') {
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

<div class="school-profile-page py-4">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb bg-transparent p-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i> دليل المدارس</a></li>
                <li class="breadcrumb-item"><a href="index.php?state=<?= urlencode($school['state']) ?>" class="text-decoration-none text-muted"><?= e($school['state']) ?></a></li>
                <li class="breadcrumb-item"><a href="index.php?state=<?= urlencode($school['state']) ?>&city=<?= urlencode($school['city']) ?>" class="text-decoration-none text-muted"><?= e($school['city']) ?></a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page"><?= e($school['name']) ?></li>
            </ol>
        </nav>

        <?php if ($isOwner && $school['status'] === 'pending'): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-warning text-dark rounded-circle p-2 px-3 fw-bold"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">معاينة أولية — هذا الملف بانتظار التأكيد</h6>
                        <small class="text-muted">أنت تشاهد صفحة مدرستك كمالك لها، ستظهر للجمهور بعد موافقة الإدارة.</small>
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn-sm btn-dark rounded-pill px-3"><i class="fas fa-edit me-1"></i> تعديل البيانات في لوحة الإدارة</a>
            </div>
        <?php endif; ?>

        <!-- Hero School Header Card -->
        <div class="card border-0 rounded-4 shadow-sm overflow-hidden mb-4 bg-white">
            <!-- Cover image / Header gradient -->
            <div style="height: 220px; background: linear-gradient(135deg, #0B2A52 0%, #1E5FA8 70%, #D97706 140%); position: relative;">
                <?php if (!empty($school['cover_path'])): ?>
                    <img src="<?= e($school['cover_path']) ?>" alt="غلاف المدرسة" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white" style="opacity: 0.15; font-size: 5rem;">
                        <i class="fas fa-school"></i>
                    </div>
                <?php endif; ?>
                
                <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 80px; background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);"></div>
            </div>

            <!-- Profile Info Bar -->
            <div class="card-body p-4 pt-0" style="position: relative;">
                <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3" style="margin-top: -65px;">
                    <!-- Logo + Title -->
                    <div class="d-flex align-items-end gap-3 flex-wrap">
                        <div class="bg-white p-2 rounded-4 shadow-md border" style="width: 120px; height: 120px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; z-index: 2;">
                            <?php if (!empty($school['logo_path'])): ?>
                                <img src="<?= e($school['logo_path']) ?>" alt="<?= e($school['name']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px;">
                            <?php else: ?>
                                <div class="w-100 h-100 rounded-3 d-flex align-items-center justify-content-center bg-light text-primary" style="font-size: 2.8rem; font-weight: 800;">
                                    <?= mb_substr($school['name'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <h2 class="fw-bold mb-0 text-dark" style="font-size: 1.6rem;"><?= e($school['name']) ?></h2>
                                <?php if ($school['is_schoplus_client']): ?>
                                    <span class="badge-schoplus shadow-sm" style="font-size: 0.78rem;">
                                        <i class="fas fa-shield-check"></i> شريك SchoPlus الذكي
                                    </span>
                                <?php endif; ?>
                                <?php if ($school['is_verified']): ?>
                                    <span class="badge-verified" style="font-size: 0.75rem;">
                                        <i class="fas fa-check-circle"></i> معتمدة
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($school['name_en'])): ?>
                                <div class="text-muted small fw-medium"><?= e($school['name_en']) ?></div>
                            <?php endif; ?>
                            <div class="d-flex align-items-center gap-3 text-muted small mt-1 flex-wrap">
                                <span><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= e($school['state']) ?> — <?= e($school['city']) ?> <?= $school['district'] ? '، ' . e($school['district']) : '' ?></span>
                                <span><i class="fas fa-eye text-secondary me-1"></i> <?= number_format($school['views_count']) ?> مشاهدة</span>
                                <span>
                                    <span class="text-warning fw-bold"><i class="fas fa-star me-1"></i><?= number_format($school['rating_avg'], 1) ?></span>
                                    <span class="text-muted">(<?= $school['reviews_count'] ?> تقييم)</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <?php if (!empty($school['whatsapp'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>?text=<?= urlencode('مرحباً بكم، رأيت مدرستكم على دليل المدارس الذكي SchoPlus وأود الاستفسار عن التسجيل.') ?>" target="_blank" class="btn btn-success fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                                <i class="fab fa-whatsapp fs-5"></i>
                                <span>واتساب المدرسة</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($school['phone'])): ?>
                            <a href="tel:<?= e($school['phone']) ?>" class="btn btn-outline-primary fw-bold px-3 py-2 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                                <i class="fas fa-phone-alt"></i>
                                <span>اتصال: <?= e($school['phone']) ?></span>
                            </a>
                        <?php endif; ?>

                        <a href="#inquiry-form-section" class="btn-luxury-gold px-3 py-2 rounded-3 fw-bold d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>طلب قبول / استفسار</span>
                        </a>

                        <button type="button" class="btn btn-outline-dark fw-bold px-3 py-2 rounded-3 d-flex align-items-center gap-2 shadow-sm" style="font-size: 0.9rem; background:#fff" onclick="openShareModal()">
                            <i class="fas fa-share-nodes text-primary"></i>
                            <span>مشاركة الرابط والباركود</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($school['is_schoplus_client']): ?>
            <!-- SchoPlus Smart Badge Banner -->
            <div class="card border-0 rounded-4 p-3 p-md-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, #0B2A52 0%, #16467D 100%); color: #ffffff;">
                <div class="row align-items-center g-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size: 0.78rem;">
                                <i class="fas fa-sparkles me-1"></i> مدرسة ذكية معتمدة تقنياً
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2 text-white">هذه المدرسة تعمل بنظام SchoPlus السحابي لإدارة التعليم الذكي</h5>
                        <p class="mb-0 small text-light opacity-85">
                            يتمتع أولياء الأمور والطلاب بتطبيق مخصص لمتابعة الحضور والغياب، النتائج والشهادات، الواجبات اليومية، ودفع الرسوم عبر الموبايل مباشرة.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-start">
                        <a href="https://schoplus.sd" target="_blank" class="btn btn-light fw-bold text-primary rounded-pill px-4 py-2 shadow-sm">
                            <i class="fas fa-external-link-alt me-1"></i> تعرف على SchoPlus
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Content Layout: Main Tabs & Sidebar -->
        <div class="row g-4">
            <!-- Left Main Column (Tabs) -->
            <div class="col-lg-8">
                
                <!-- Nav Tabs -->
                <ul class="nav nav-pills p-1 bg-white rounded-4 border shadow-sm mb-4" id="schoolProfileTabs" role="tablist">
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link active rounded-pill fw-bold py-2 w-100" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="fas fa-info-circle me-1"></i> نبذة ومعلومات
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link rounded-pill fw-bold py-2 w-100" id="gallery-tab" data-bs-toggle="tab" data-bs-target="#gallery" type="button" role="tab">
                            <i class="fas fa-images me-1"></i> معرض الصور
                            <span class="badge bg-secondary ms-1"><?= count($galleryList) ?></span>
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link rounded-pill fw-bold py-2 w-100" id="news-tab" data-bs-toggle="tab" data-bs-target="#news" type="button" role="tab">
                            <i class="fas fa-bullhorn me-1"></i> آخر الأخبار
                            <span class="badge bg-secondary ms-1"><?= count($newsList) ?></span>
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link rounded-pill fw-bold py-2 w-100" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                            <i class="fas fa-star text-warning me-1"></i> التقييمات والآراء
                            <span class="badge bg-secondary ms-1"><?= count($reviewsList) ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="schoolProfileTabsContent">
                    
                    <!-- TAB 1: OVERVIEW -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <!-- About -->
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary"><i class="fas fa-book-open"></i></span>
                                <span>عن المدرسة</span>
                            </h5>
                            <?php if (!empty($school['description'])): ?>
                                <p class="text-secondary lh-lg mb-0" style="white-space: pre-line;"><?= e($school['description']) ?></p>
                            <?php else: ?>
                                <p class="text-muted fst-italic mb-0">لم تقم المدرسة بإضافة وصف تعريفي بعد.</p>
                            <?php endif; ?>

                            <?php if (!empty($school['vision']) || !empty($school['mission'])): ?>
                                <hr class="my-4 border-light">
                                <div class="row g-3">
                                    <?php if (!empty($school['vision'])): ?>
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3 bg-light border-start border-primary border-3">
                                                <h6 class="fw-bold text-primary mb-1"><i class="fas fa-eye me-1"></i> الرؤية</h6>
                                                <p class="small text-muted mb-0"><?= e($school['vision']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($school['mission'])): ?>
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3 bg-light border-start border-warning border-3">
                                                <h6 class="fw-bold text-warning mb-1"><i class="fas fa-bullseye me-1"></i> الرسالة</h6>
                                                <p class="small text-muted mb-0"><?= e($school['mission']) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Facilities -->
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary"><i class="fas fa-cubes"></i></span>
                                <span>المرافق والخدمات المتاحة</span>
                            </h5>
                            <?php if (!empty($facilities)): ?>
                                <div class="row g-3">
                                    <?php foreach ($facilities as $facKey): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <div class="d-flex align-items-center gap-2 p-2 px-3 rounded-3 bg-light border">
                                                <i class="<?= SudanGeography::facilityIcon($facKey) ?> text-primary"></i>
                                                <span class="small fw-bold text-dark"><?= SudanGeography::facilityLabel($facKey) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted fst-italic mb-0">لم تحدد المدرسة مرافقها بعد.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Inquiry & Contact Section -->
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4" id="inquiry-form-section">
                            <h5 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <span class="text-primary"><i class="fas fa-paper-plane"></i></span>
                                <span>طلب قبول / استفسار مباشر لإدارة المدرسة</span>
                            </h5>
                            <p class="text-muted small mb-4">أرسل استفسارك أو رغبتك في تسجيل ابنك/ابنتك وسيقوم فريق التسجيل بالمدرسة بالتواصل معك مباشرة.</p>

                            <?php if ($inquirySuccess): ?>
                                <div class="alert alert-success border-0 shadow-sm rounded-3">
                                    <i class="fas fa-check-circle me-1"></i> تم إرسال طلبكم بنجاح إلى إدارة المدرسة، وسيتواصلون معكم قريباً.
                                </div>
                            <?php endif; ?>

                            <?php if ($inquiryError): ?>
                                <div class="alert alert-danger border-0 shadow-sm rounded-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i> <?= e($inquiryError) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="school.php?slug=<?= urlencode($school['slug']) ?>#inquiry-form-section">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_inquiry">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">اسم ولي الأمر <span class="text-danger">*</span></label>
                                        <input type="text" name="parent_name" required class="form-control" placeholder="مثال: محمد أحمد علي">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">رقم الهاتف / واتساب <span class="text-danger">*</span></label>
                                        <input type="tel" name="parent_phone" required class="form-control" placeholder="09xxxxxxx أو 01xxxxxxx">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">المرحلة / الصف المراد التسجيل فيه</label>
                                        <input type="text" name="student_grade" class="form-control" placeholder="مثال: الصف الثالث أساس / أولى ثانوي">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">رسالة أو استفسار إضافي</label>
                                        <input type="text" name="message" class="form-control" placeholder="أية ملاحظات أخرى">
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn-luxury-primary px-4 py-2">
                                            <i class="fas fa-paper-plane me-1"></i> إرسال الطلب الآن
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TAB: CAMPUS & FACILITIES GALLERY -->
                    <div class="tab-pane fade" id="gallery" role="tabpanel">
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-2 border-bottom">
                                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <span class="text-primary"><i class="fas fa-images"></i></span>
                                    <span>معرض صور مرافق وفعاليات المدرسة</span>
                                </h5>
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small fw-bold">
                                    <?= count($galleryList) ?> صور متوفرة
                                </span>
                            </div>

                            <?php if (empty($galleryList)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-camera display-4 mb-3 text-secondary opacity-50"></i>
                                    <h6 class="fw-bold text-dark mb-1">لا توجد صور في المعرض حالياً</h6>
                                    <p class="small text-muted mb-0">لم تقم المدرسة بإضافة صور لمرافقها بعد.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($galleryList as $idx => $photo): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <div class="card border rounded-3 overflow-hidden shadow-sm h-100 position-relative group-hover" style="cursor: pointer;" onclick="openLightbox('<?= e($photo['path']) ?>', '<?= e(addslashes($photo['caption'] ?: $school['name'])) ?>')">
                                                <div style="height: 190px; overflow: hidden; background: #f8fafc;">
                                                    <img src="<?= e($photo['path']) ?>" alt="<?= e($photo['caption'] ?: $school['name']) ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.3s ease;">
                                                </div>
                                                <?php if (!empty($photo['caption'])): ?>
                                                    <div class="p-2 bg-white border-top text-center">
                                                        <small class="text-dark fw-bold d-block text-truncate"><?= e($photo['caption']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="position-absolute top-0 end-0 m-2 bg-dark bg-opacity-50 text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.85rem;">
                                                    <i class="fas fa-search-plus"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 2: NEWS -->
                    <div class="tab-pane fade" id="news" role="tabpanel">
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <h5 class="fw-bold text-dark mb-4 d-flex align-items-center gap-2">
                                <span class="text-primary"><i class="fas fa-bullhorn"></i></span>
                                <span>أحدث الإعلانات والأخبار الأكاديمية</span>
                            </h5>

                            <?php if (empty($newsList)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-newspaper display-4 mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">لا توجد أخبار منشورة حالياً لهذه المدرسة.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($newsList as $item): ?>
                                        <div class="p-3 rounded-3 border bg-light">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                <h6 class="fw-bold text-dark mb-0 fs-6"><?= e($item['title']) ?></h6>
                                                <span class="badge bg-white text-muted border px-2 py-1 small">
                                                    <i class="far fa-calendar-alt me-1"></i> <?= date('Y-m-d', strtotime($item['published_at'] ?: $item['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-secondary small mb-2 lh-base" style="white-space: pre-line;"><?= e($item['content']) ?></p>
                                            <?php if (!empty($item['image_path'])): ?>
                                                <div class="mt-2 mb-2">
                                                    <img src="<?= e($item['image_path']) ?>" alt="صورة الخبر" class="rounded-3 img-fluid" style="max-height: 250px;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 3: REVIEWS -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <!-- Add Review Form -->
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <h5 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                                <span class="text-warning"><i class="fas fa-star"></i></span>
                                <span>أضف تقييمك ورأيك في المدرسة</span>
                            </h5>
                            <p class="text-muted small mb-3">تقييمك يساعد أولياء الأمور والطلاب الآخرين في اتخاذ القرار المناسب.</p>

                            <?php if ($reviewSuccess): ?>
                                <div class="alert alert-success border-0 shadow-sm rounded-3">
                                    <i class="fas fa-check-circle me-1"></i> شكراً لك! تم تسجيل تقييمك بنجاح.
                                </div>
                            <?php endif; ?>

                            <?php if ($reviewError): ?>
                                <div class="alert alert-danger border-0 shadow-sm rounded-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i> <?= e($reviewError) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="school.php?slug=<?= urlencode($school['slug']) ?>#reviews">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_review">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold text-secondary">الاسم الكريم <span class="text-danger">*</span></label>
                                        <input type="text" name="reviewer_name" required class="form-control" placeholder="اسمك">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-secondary">صفتك</label>
                                        <select name="reviewer_role" class="form-select">
                                            <option value="parent">ولي أمر طالب</option>
                                            <option value="student">طالب / خريج</option>
                                            <option value="visitor">زائر / مهتم</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold text-secondary">التقييم</label>
                                        <select name="rating" class="form-select">
                                            <option value="5">⭐⭐⭐⭐⭐ ممتاز (5)</option>
                                            <option value="4">⭐⭐⭐⭐ جيد جداً (4)</option>
                                            <option value="3">⭐⭐⭐ متوسط (3)</option>
                                            <option value="2">⭐⭐ دون المتوسط (2)</option>
                                            <option value="1">⭐ ضعيف (1)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-secondary">تعليقك وتجربتك <span class="text-danger">*</span></label>
                                        <textarea name="comment" required rows="3" class="form-control" placeholder="اكتب تقييمك بصدق وأمانة عن تعاملك مع المدرسة والبيئة التعليمية..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn-luxury-primary px-4 py-2">
                                            <i class="fas fa-check me-1"></i> نشر التقييم
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Reviews List -->
                        <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                            <h5 class="fw-bold text-dark mb-4">تقييمات وآراء المجتمع (<?= count($reviewsList) ?>)</h5>
                            
                            <?php if (empty($reviewsList)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-comment-dots display-4 mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">كن أول من يقيّم هذه المدرسة!</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($reviewsList as $rev): ?>
                                        <div class="p-3 rounded-3 border bg-light">
                                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                                                        <?= mb_substr($rev['reviewer_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold mb-0 text-dark"><?= e($rev['reviewer_name']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= $rev['reviewer_role'] === 'parent' ? 'ولي أمر' : ($rev['reviewer_role'] === 'student' ? 'طالب' : 'زائر') ?>
                                                            • <?= date('Y-m-d', strtotime($rev['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="text-warning small">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $rev['rating'] ? '' : 'text-muted opacity-25' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <p class="text-secondary small mb-2 lh-base"><?= e($rev['comment']) ?></p>

                                            <?php if (!empty($rev['school_reply'])): ?>
                                                <!-- Official School Reply -->
                                                <div class="mt-3 p-3 rounded-3 bg-white border border-primary-subtle border-start border-4 border-primary">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="badge bg-primary text-white fw-bold"><i class="fas fa-reply me-1"></i> رد إدارة المدرسة</span>
                                                        <?php if (!empty($rev['school_replied_at'])): ?>
                                                            <small class="text-muted"><?= date('Y-m-d', strtotime($rev['school_replied_at'])) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="small text-dark mb-0"><?= e($rev['school_reply']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Sidebar Column (Details Card) -->
            <div class="col-lg-4">
                
                <!-- School Facts Box -->
                <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="fas fa-id-card-alt text-primary me-2"></i> بطاقة معلومات المدرسة</h6>

                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 small">
                        <li class="d-flex align-items-center justify-content-between">
                            <span class="text-muted"><i class="fas fa-building me-1"></i> نوع المدرسة:</span>
                            <span class="fw-bold text-dark"><?= SudanGeography::schoolTypeLabel($school['school_type']) ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between">
                            <span class="text-muted"><i class="fas fa-venus-mars me-1"></i> نوع الطلاب:</span>
                            <span class="fw-bold text-dark"><?= SudanGeography::genderTypeLabel($school['gender_type']) ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between">
                            <span class="text-muted"><i class="fas fa-graduation-cap me-1"></i> المنهج التعليمي:</span>
                            <span class="fw-bold text-dark"><?= SudanGeography::curriculumLabel($school['curriculum']) ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between">
                            <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i> متوسط الرسوم:</span>
                            <span class="fw-bold text-success"><?= e($school['approx_fees'] ?: 'تحدد مع الإدارة') ?></span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between">
                            <span class="text-muted"><i class="far fa-clock me-1"></i> ساعات الدوام:</span>
                            <span class="fw-bold text-dark"><?= e($school['working_hours'] ?: '7:30 ص — 2:30 م') ?></span>
                        </li>
                    </ul>

                    <?php if (!empty($stages)): ?>
                        <div class="mt-3 pt-3 border-top">
                            <span class="text-muted small d-block mb-2"><i class="fas fa-layer-group me-1"></i> المراحل الدراسية المتاحة:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($stages as $stgKey): ?>
                                    <span class="badge bg-light text-dark border px-2 py-1 small">
                                        <?= SudanGeography::stageLabel($stgKey) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Contact & Social Links Box -->
                <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="fas fa-address-book text-primary me-2"></i> بيانات الاتصال والموقع</h6>

                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 small">
                        <?php if (!empty($school['phone'])): ?>
                            <li class="d-flex align-items-center gap-2">
                                <div class="bg-light p-2 rounded-circle text-primary"><i class="fas fa-phone"></i></div>
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">هاتف المدرسة</span>
                                    <a href="tel:<?= e($school['phone']) ?>" class="fw-bold text-dark text-decoration-none"><?= e($school['phone']) ?></a>
                                </div>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($school['whatsapp'])): ?>
                            <li class="d-flex align-items-center gap-2">
                                <div class="bg-light p-2 rounded-circle text-success"><i class="fab fa-whatsapp"></i></div>
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">واتساب مباشر</span>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $school['whatsapp']) ?>" target="_blank" class="fw-bold text-success text-decoration-none"><?= e($school['whatsapp']) ?></a>
                                </div>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($school['email'])): ?>
                            <li class="d-flex align-items-center gap-2">
                                <div class="bg-light p-2 rounded-circle text-info"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">البريد الإلكتروني</span>
                                    <a href="mailto:<?= e($school['email']) ?>" class="text-dark text-decoration-none"><?= e($school['email']) ?></a>
                                </div>
                            </li>
                        <?php endif; ?>

                        <?php if (!empty($school['address'])): ?>
                            <li class="d-flex align-items-start gap-2">
                                <div class="bg-light p-2 rounded-circle text-danger mt-1"><i class="fas fa-map-pin"></i></div>
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">العنوان الفعلي</span>
                                    <span class="text-dark"><?= e($school['address']) ?></span>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <?php if (!empty($school['google_map_url'])): ?>
                        <div class="mt-3 pt-3 border-top">
                            <a href="<?= e($school['google_map_url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100 rounded-3">
                                <i class="fas fa-map-marked-alt text-danger me-1"></i> فتح الموقع في خرائط Google
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Social Links -->
                <?php if (!empty($school['facebook']) || !empty($school['website'])): ?>
                    <div class="card border-0 rounded-4 p-4 bg-white shadow-sm mb-4">
                        <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="fas fa-globe text-primary me-2"></i> الروابط الإلكترونية</h6>
                        <div class="d-flex flex-column gap-2">
                            <?php if (!empty($school['facebook'])): ?>
                                <a href="<?= e($school['facebook']) ?>" target="_blank" class="btn btn-light border text-primary fw-bold btn-sm d-flex align-items-center justify-content-between p-2 px-3 rounded-3">
                                    <span><i class="fab fa-facebook me-2 text-primary"></i> صفحة فيسبوك</span>
                                    <i class="fas fa-external-link-alt small"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($school['website'])): ?>
                                <a href="<?= e($school['website']) ?>" target="_blank" class="btn btn-light border text-dark fw-bold btn-sm d-flex align-items-center justify-content-between p-2 px-3 rounded-3">
                                    <span><i class="fas fa-globe me-2 text-secondary"></i> الموقع الإلكتروني</span>
                                    <i class="fas fa-external-link-alt small"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Owner CTA banner if non-schoplus -->
                <?php if (!$school['is_schoplus_client']): ?>
                    <div class="card border-0 rounded-4 p-4 text-center shadow-sm" style="background: linear-gradient(135deg, #FAF4EB 0%, #F5ECE0 100%); border: 1px dashed #D97706 !important;">
                        <div class="display-6 text-warning mb-2"><i class="fas fa-graduation-cap"></i></div>
                        <h6 class="fw-bold text-dark mb-1">هل أنت مسؤول في هذه المدرسة؟</h6>
                        <p class="small text-muted mb-3">انضم لمنظومة SchoPlus وحوّل مدرستك إلى مدرسة ذكية بالكامل مع تطبيق خاص لأولياء الأمور.</p>
                        <a href="https://schoplus.sd" target="_blank" class="btn-luxury-primary btn-sm w-100 justify-content-center">
                            <i class="fas fa-rocket me-1"></i> ترقية المدرسة لنظام SchoPlus
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<!-- SHARE & QR CODE MODAL -->
<div class="modal fade" id="shareSchoolModal" tabindex="-1" aria-labelledby="shareSchoolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="shareSchoolModalLabel">
                    <i class="fas fa-share-nodes text-primary me-2"></i> مشاركة صفحة المدرسة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- QR Code Box -->
                <div class="p-3 bg-light rounded-4 d-inline-block mb-3 border">
                    <img id="schoolQrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" alt="QR Code" class="img-fluid rounded-3" style="width: 150px; height: 150px;">
                    <div class="small text-muted mt-2 fw-medium">امسح الباركود للوصول المباشر</div>
                </div>

                <!-- Short URL Input & Copy -->
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-muted">الرابط المختصر الأنيق (جاهز للمشاركة):</label>
                    <div class="input-group">
                        <input type="text" id="shortUrlInput" class="form-control font-monospace text-start" style="direction:ltr" value="https://daleel.schoplus.sd/s/<?= e($school['slug']) ?>" readonly>
                        <button class="btn btn-primary fw-bold px-3" type="button" onclick="copyShortUrl()">
                            <i class="fas fa-copy me-1"></i> <span id="copyBtnText">نسخ</span>
                        </button>
                    </div>
                </div>

                <!-- Direct Social Sharing Buttons -->
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mt-3">
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("تعرف على " . $school['name'] . " عبر دليل المدارس الذكي SchoPlus:\nhttps://daleel.schoplus.sd/s/" . $school['slug']) ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="fab fa-whatsapp fs-5"></i> واتساب
                    </a>
                    
                    <!-- Telegram -->
                    <a href="https://t.me/share/url?url=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>&text=<?= urlencode($school['name'] . ' — دليل المدارس الذكي') ?>" target="_blank" class="btn btn-info text-white btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="fab fa-telegram fs-5"></i> تيليجرام
                    </a>

                    <!-- Facebook -->
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="fab fa-facebook fs-5"></i> فيسبوك
                    </a>

                    <!-- Twitter / X -->
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>&text=<?= urlencode($school['name'] . ' على دليل المدارس') ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="fab fa-x-twitter fs-5"></i> تويتر (X)
                    </a>
                </div>

                <!-- Download QR link -->
                <div class="mt-3">
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" download="QR-<?= e($school['slug']) ?>.png" target="_blank" class="btn btn-sm btn-link text-muted text-decoration-none">
                        <i class="fas fa-download me-1"></i> تحميل الباركود بدقة عالية للطباعة
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PHOTO LIGHTBOX MODAL -->
<div class="modal fade" id="photoLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 text-center">
                <img id="lightboxImage" src="" alt="" class="img-fluid rounded-4 shadow-lg mb-2" style="max-height: 80vh; object-fit: contain;">
                <div>
                    <div id="lightboxCaption" class="text-white fw-bold py-2 bg-dark bg-opacity-75 rounded-pill px-4 d-inline-block small"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openLightbox(src, caption) {
    document.getElementById('lightboxImage').src = src;
    const capEl = document.getElementById('lightboxCaption');
    if (caption && caption.trim()) {
        capEl.innerText = caption;
        capEl.style.display = 'inline-block';
    } else {
        capEl.style.display = 'none';
    }
    const modal = new bootstrap.Modal(document.getElementById('photoLightboxModal'));
    modal.show();
}

function openShareModal() {
    const modal = new bootstrap.Modal(document.getElementById('shareSchoolModal'));
    modal.show();
}

function copyShortUrl() {
    const input = document.getElementById('shortUrlInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btnText = document.getElementById('copyBtnText');
        btnText.innerText = 'تم النسخ!';
        setTimeout(() => { btnText.innerText = 'نسخ'; }, 2500);
    });
}
</script>

<?php
require_once __DIR__ . '/footer.php';
