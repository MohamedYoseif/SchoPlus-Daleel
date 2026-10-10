<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Owner Dashboard (لوحة إدارة المدرسة ومتابعة الاعتماد والمحتوى)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

$currSchool = currentSchool();
if (!$currSchool) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$schoolId = (int)$currSchool['id'];

// Refresh school record
$stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

$successMsg = '';
$errorMsg = '';

// Helper for file upload
function handleUpload(array $file, string $subfolder): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetDir = __DIR__ . '/uploads/' . trim($subfolder, '/') . '/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
    }
    return null;
}

// ----------------------------------------------------
// Handle Form Submissions
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errorMsg = 'انتهت صلاحية الجلسة، يرجى المحاولة ثانية.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. UPDATE PROFILE & DETAILS
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $google_map_url = trim($_POST['google_map_url'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $facebook = trim($_POST['facebook'] ?? '');
            $school_type = trim($_POST['school_type'] ?? 'private');
            $gender_type = trim($_POST['gender_type'] ?? 'mixed');
            $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
            $approx_fees = trim($_POST['approx_fees'] ?? '');
            $working_hours = trim($_POST['working_hours'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $vision = trim($_POST['vision'] ?? '');
            $mission = trim($_POST['mission'] ?? '');

            $stages = isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : [];
            $facilities = isset($_POST['facilities']) && is_array($_POST['facilities']) ? $_POST['facilities'] : [];

            // Upload Logo
            $logoPath = $school['logo_path'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $upLogo = handleUpload($_FILES['logo'], 'logos');
                if ($upLogo) {
                    $logoPath = $upLogo;
                }
            }

            // Upload Cover
            $coverPath = $school['cover_path'];
            if (!empty($_FILES['cover']['tmp_name'])) {
                $upCover = handleUpload($_FILES['cover'], 'covers');
                if ($upCover) {
                    $coverPath = $upCover;
                }
            }

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    name = :name, name_en = :name_en, state = :state, city = :city, district = :district,
                    address = :address, google_map_url = :google_map_url, phone = :phone, whatsapp = :whatsapp,
                    email = :email, website = :website, facebook = :facebook, school_type = :school_type,
                    gender_type = :gender_type, curriculum = :curriculum, approx_fees = :approx_fees,
                    working_hours = :working_hours, description = :description, vision = :vision, mission = :mission,
                    stages = :stages, facilities = :facilities, logo_path = :logo_path, cover_path = :cover_path,
                    updated_at = NOW()
                    WHERE id = :id");

                $upStmt->execute([
                    ':name' => $name,
                    ':name_en' => $name_en ?: null,
                    ':state' => $state,
                    ':city' => $city,
                    ':district' => $district ?: null,
                    ':address' => $address ?: null,
                    ':google_map_url' => $google_map_url ?: null,
                    ':phone' => $phone,
                    ':whatsapp' => $whatsapp ?: null,
                    ':email' => $email ?: null,
                    ':website' => $website ?: null,
                    ':facebook' => $facebook ?: null,
                    ':school_type' => $school_type,
                    ':gender_type' => $gender_type,
                    ':curriculum' => $curriculum,
                    ':approx_fees' => $approx_fees ?: null,
                    ':working_hours' => $working_hours ?: null,
                    ':description' => $description ?: null,
                    ':vision' => $vision ?: null,
                    ':mission' => $mission ?: null,
                    ':stages' => json_encode($stages, JSON_UNESCAPED_UNICODE),
                    ':facilities' => json_encode($facilities, JSON_UNESCAPED_UNICODE),
                    ':logo_path' => $logoPath,
                    ':cover_path' => $coverPath,
                    ':id' => $schoolId,
                ]);

                $successMsg = 'تم حفظ وتحديث بيانات مدرستكم بنجاح!';
                // refresh
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
            }
        }

        // 2. ADD NEWS
        elseif ($action === 'add_news') {
            $newsTitle = trim($_POST['title'] ?? '');
            $newsContent = trim($_POST['content'] ?? '');

            if (!$newsTitle || !$newsContent) {
                $errorMsg = 'يرجى إدخال عنوان وتفاصيل الخبر.';
            } else {
                $imagePath = null;
                if (!empty($_FILES['news_image']['tmp_name'])) {
                    $imagePath = handleUpload($_FILES['news_image'], 'news');
                }

                $nStmt = $pdo->prepare("INSERT INTO daleel_school_news (school_id, title, content, image_path, published_at, is_active, created_at) VALUES (?, ?, ?, ?, NOW(), 1, NOW())");
                $nStmt->execute([$schoolId, $newsTitle, $newsContent, $imagePath]);
                $successMsg = 'تم نشر الخبر الجديد بنجاح!';
            }
        }

        // 3. DELETE NEWS
        elseif ($action === 'delete_news') {
            $newsId = (int)($_POST['news_id'] ?? 0);
            $delStmt = $pdo->prepare("DELETE FROM daleel_school_news WHERE id = ? AND school_id = ?");
            $delStmt->execute([$newsId, $schoolId]);
            $successMsg = 'تم حذف الخبر بنجاح.';
        }

        // 4. REPLY TO REVIEW
        elseif ($action === 'reply_review') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $replyText = trim($_POST['reply'] ?? '');

            if (!$replyText) {
                $errorMsg = 'يرجى كتابة نص الرد.';
            } else {
                $rStmt = $pdo->prepare("UPDATE daleel_school_reviews SET school_reply = ?, school_replied_at = NOW() WHERE id = ? AND school_id = ?");
                $rStmt->execute([$replyText, $reviewId, $schoolId]);
                $successMsg = 'تم حفظ وإرسال رد المدرسة على التقييم بنجاح.';
            }
        }
    }
}

// Fetch School Data
$newsStmt = $pdo->prepare("SELECT * FROM daleel_school_news WHERE school_id = ? ORDER BY id DESC");
$newsStmt->execute([$schoolId]);
$newsList = $newsStmt->fetchAll();

$revStmt = $pdo->prepare("SELECT * FROM daleel_school_reviews WHERE school_id = ? ORDER BY id DESC");
$revStmt->execute([$schoolId]);
$reviewsList = $revStmt->fetchAll();

$inqStmt = $pdo->prepare("SELECT * FROM daleel_school_inquiries WHERE school_id = ? ORDER BY id DESC");
$inqStmt->execute([$schoolId]);
$inquiriesList = $inqStmt->fetchAll();

$currentStages = json_decode($school['stages'] ?? '[]', true) ?: [];
$currentFacilities = json_decode($school['facilities'] ?? '[]', true) ?: [];
$statesWithCities = SudanGeography::statesWithCities();

// Calculate completion score
$completionFields = [
    'name' => 10,
    'state' => 10,
    'city' => 10,
    'phone' => 10,
    'logo_path' => 15,
    'description' => 15,
    'facilities' => 10,
    'approx_fees' => 10,
    'cover_path' => 10
];
$score = 0;
foreach ($completionFields as $field => $points) {
    if (!empty($school[$field]) && $school[$field] !== '[]') {
        $score += $points;
    }
}

$pageTitle = 'لوحة إدارة المدرسة: ' . $school['name'];
require_once __DIR__ . '/header.php';
?>

<div class="dashboard-page py-4">
    <div class="container">

        <!-- Top Header Card -->
        <div class="card border-0 rounded-4 shadow-sm bg-white p-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-light border rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <?php if (!empty($school['logo_path'])): ?>
                            <img src="<?= e($school['logo_path']) ?>" alt="شعار" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <span class="fs-3 fw-bold text-primary"><?= mb_substr($school['name'], 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h4 class="fw-bold mb-0 text-dark"><?= e($school['name']) ?></h4>
                            <?php if ($school['status'] === 'approved'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-check-circle me-1"></i> معتمدة ونشطة بالدليل
                                </span>
                            <?php elseif ($school['status'] === 'pending'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-hourglass-half me-1"></i> بانتظار التأكيد والاعتماد
                                </span>
                            <?php elseif ($school['status'] === 'suspended'): ?>
                                <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-ban me-1"></i> موقوفة / محظورة مؤقتاً
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-times-circle me-1"></i> مرفوضة
                                </span>
                            <?php endif; ?>

                        </div>
                        <div class="small text-muted mt-1">
                            <span><i class="fas fa-user text-secondary me-1"></i> المسؤول: <?= e($school['owner_name']) ?> (<?= e($school['owner_title'] ?: 'مدير') ?>)</span>
                            <span class="ms-3"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= e($school['state']) ?> — <?= e($school['city']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a href="school.php?slug=<?= urlencode($school['slug']) ?>" target="_blank" class="btn-luxury-outline">
                        <i class="fas fa-external-link-alt text-primary"></i>
                        <span>معاينة صفحة المدرسة</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                <i class="fas fa-check-circle me-1"></i> <?= e($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= e($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- SUSPENDED STATUS ALERT -->
        <?php if ($school['status'] === 'suspended'): ?>
            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4" style="background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); border: 1.5px solid #F87171 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger text-white px-3 py-1 rounded-pill fw-bold" style="font-size: 0.8rem;">
                                إيقاف إداري مؤقت
                            </span>
                        </div>
                        <h5 class="fw-bold text-danger mb-1">تم إيقاف ظهور صفحة مدرستكم في دليل المدارس بقرار إداري</h5>
                        <p class="text-secondary small mb-2 lh-base">
                            <?= !empty($school['rejection_reason']) ? '<strong>السبب المحدد:</strong> ' . e($school['rejection_reason']) : 'تم إيقاف نشر بيانات المدرسة مؤقتاً لمراجعة المعايير أو تحديث البيانات. يرجى التواصل مع إدارة دليل SchoPlus لاستيضاح الحالة واستئناف النشر.' ?>
                        </p>
                        <a href="https://wa.me/249912345678?text=<?= urlencode('مرحباً إدارة SchoPlus، استفسر بخصوص إيقاف صفحة مدرستنا في الدليل: ' . $school['name']) ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3">
                            <i class="fab fa-whatsapp me-1"></i> مراجعة الإدارة عبر واتساب
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PENDING STATUS HERO BANNER -->
        <?php if ($school['status'] === 'pending'): ?>
            <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 mb-4" style="background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border: 1.5px solid #FCD34D !important;">
                <div class="row align-items-center g-4">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size: 0.8rem;">
                                <i class="fas fa-clock me-1"></i> قيد المراجعة الفنية
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">طلب انضمام مدرستكم قيد التدقيق والمراجعة حالياً</h4>
                        <p class="text-secondary small mb-3 lh-base">
                            أهلاً بكم في عائلة SchoPlus. يقوم فريقنا الآن بمراجعة بيانات المدرسة المسجلة للتأكد من دقتها.
                            في هذه الأثناء، <strong>يمكنك إكمال ملف مدرستك بالكامل أدناه</strong> (رفع شعار المدرسة، غلاف الواجهة، تحديد المرافق، الرؤية والرسالة، الرسوم الدراسية) بحيث تكون صفحتكم في أبهى صورة فور تفعيلها للجمهور!
                        </p>
                        <!-- Profile completion progress -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 10px; border-radius: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $score ?>%;" aria-valuenow="<?= $score ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <span class="fw-bold text-dark small">اكتمال الملف: <?= $score ?>%</span>
                        </div>
                    </div>

                    <div class="col-md-4 text-md-start">
                        <div class="p-3 bg-white rounded-4 shadow-sm border border-warning-subtle text-center">
                            <small class="text-muted d-block mb-2">لتسريع عملية الاعتماد والتفعيل الفوري:</small>
                            <a href="https://wa.me/249912345678?text=<?= urlencode('مرحباً فريق SchoPlus، قمت بتسجيل مدرستي (' . $school['name'] . ') وأرجو تفعيلها في دليل المدارس.') ?>" target="_blank" class="btn btn-success fw-bold w-100 py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                <i class="fab fa-whatsapp fs-5"></i>
                                <span>تواصل عبر واتساب للتأكيد</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 rounded-4 p-3 bg-white shadow-sm text-center">
                    <span class="text-muted small d-block mb-1"><i class="fas fa-eye text-primary me-1"></i> مشاهدات البروفايل</span>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($school['views_count']) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 rounded-4 p-3 bg-white shadow-sm text-center">
                    <span class="text-muted small d-block mb-1"><i class="fas fa-star text-warning me-1"></i> متوسط التقييم</span>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($school['rating_avg'], 1) ?> <small class="text-muted fs-6">(<?= $school['reviews_count'] ?>)</small></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 rounded-4 p-3 bg-white shadow-sm text-center">
                    <span class="text-muted small d-block mb-1"><i class="fas fa-bullhorn text-success me-1"></i> الأخبار المنشورة</span>
                    <h3 class="fw-bold text-dark mb-0"><?= count($newsList) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 rounded-4 p-3 bg-white shadow-sm text-center">
                    <span class="text-muted small d-block mb-1"><i class="fas fa-inbox text-info me-1"></i> طلبات القبول والاستفسار</span>
                    <h3 class="fw-bold text-dark mb-0"><?= count($inquiriesList) ?></h3>
                </div>
            </div>
        </div>

        <!-- Upgrade to SchoPlus Banner -->
        <?php if (!$school['is_schoplus_client']): ?>
            <div class="card border-0 rounded-4 p-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, #0B2A52 0%, #16467D 100%); color: #ffffff;">
                <div class="row align-items-center g-3">
                    <div class="col-md-8">
                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold mb-2 small">
                            <i class="fas fa-rocket me-1"></i> ترقية مجانية لشريك SchoPlus الذكي
                        </span>
                        <h5 class="fw-bold text-white mb-2">اجعل مدرستك رائدة رقمياً مع نظام SchoPlus السحابي</h5>
                        <p class="small text-light opacity-85 mb-0">
                            احصل على شارة "مدرسة ذكية معتمدة" في الدليل، وامتلك تطبيقات موبايل مخصصة للمدير، المعلمين، وأولياء الأمور لإدارة الحضور والنتائج والرسوم المالية بسهولة فائقة.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-start">
                        <a href="https://schoplus.sd" target="_blank" class="btn btn-warning fw-bold text-dark rounded-pill px-4 py-2 shadow-sm">
                            <i class="fas fa-star me-1"></i> اطلب تجربة النظام مجاناً
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Dashboard Tabs -->
        <div class="card border-0 rounded-4 bg-white shadow-sm p-4">
            
            <ul class="nav nav-pills p-1 bg-light rounded-4 border mb-4" id="dashboardTabs" role="tablist">
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link active rounded-pill fw-bold py-2 w-100" id="dash-profile-tab" data-bs-toggle="tab" data-bs-target="#dash-profile" type="button" role="tab">
                        <i class="fas fa-edit me-1"></i> بيانات وبروفايل المدرسة
                    </button>
                </li>
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link rounded-pill fw-bold py-2 w-100" id="dash-news-tab" data-bs-toggle="tab" data-bs-target="#dash-news" type="button" role="tab">
                        <i class="fas fa-bullhorn me-1"></i> إدارة الأخبار والإعلانات (<?= count($newsList) ?>)
                    </button>
                </li>
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link rounded-pill fw-bold py-2 w-100" id="dash-reviews-tab" data-bs-toggle="tab" data-bs-target="#dash-reviews" type="button" role="tab">
                        <i class="fas fa-comments me-1"></i> التقييمات والردود (<?= count($reviewsList) ?>)
                    </button>
                </li>
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link rounded-pill fw-bold py-2 w-100" id="dash-inquiries-tab" data-bs-toggle="tab" data-bs-target="#dash-inquiries" type="button" role="tab">
                        <i class="fas fa-inbox me-1"></i> طلبات القبول والاستفسارات (<?= count($inquiriesList) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="dashboardTabsContent">
                
                <!-- TAB 1: EDIT PROFILE & MEDIA -->
                <div class="tab-pane fade show active" id="dash-profile" role="tabpanel">
                    <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-4">
                            <!-- Basic Info -->
                            <div class="col-12">
                                <h6 class="fw-bold text-dark pb-2 border-bottom"><i class="fas fa-school text-primary me-2"></i> البيانات الأساسية</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">اسم المدرسة (عربي)</label>
                                <input type="text" name="name" required class="form-control" value="<?= e($school['name']) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">اسم المدرسة (إنجليزي)</label>
                                <input type="text" name="name_en" dir="ltr" class="form-control" value="<?= e($school['name_en'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">الولاية</label>
                                <select name="state" id="dashState" class="form-select">
                                    <?php foreach ($statesWithCities as $stName => $cities): ?>
                                        <option value="<?= e($stName) ?>" <?= $school['state'] === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">المدينة / المحلية</label>
                                <select name="city" id="dashCity" class="form-select">
                                    <option value="<?= e($school['city']) ?>" selected><?= e($school['city']) ?></option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">الحي أو المنطقة</label>
                                <input type="text" name="district" class="form-control" value="<?= e($school['district'] ?? '') ?>">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-secondary">العنوان التفصيلي</label>
                                <input type="text" name="address" class="form-control" value="<?= e($school['address'] ?? '') ?>" placeholder="الشارع، المعالم المجاورة...">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">رابط الموقع على Google Maps</label>
                                <input type="url" name="google_map_url" dir="ltr" class="form-control" value="<?= e($school['google_map_url'] ?? '') ?>" placeholder="https://maps.app.goo.gl/...">
                            </div>

                            <!-- Classification -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">نوع المدرسة</label>
                                <select name="school_type" class="form-select">
                                    <?php foreach (SudanGeography::schoolTypes() as $k => $lbl): ?>
                                        <option value="<?= $k ?>" <?= $school['school_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">نوع الطلاب</label>
                                <select name="gender_type" class="form-select">
                                    <?php foreach (SudanGeography::genderTypes() as $k => $lbl): ?>
                                        <option value="<?= $k ?>" <?= $school['gender_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">المنهج الدراسي</label>
                                <select name="curriculum" class="form-select">
                                    <?php foreach (SudanGeography::curricula() as $k => $lbl): ?>
                                        <option value="<?= $k ?>" <?= $school['curriculum'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Stages -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary d-block">المراحل التعليمية المتوفرة</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach (SudanGeography::stages() as $stgKey => $stgLabel): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="stages[]" value="<?= $stgKey ?>" id="d_stg_<?= $stgKey ?>" <?= in_array($stgKey, $currentStages) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-bold text-dark" for="d_stg_<?= $stgKey ?>"><?= $stgLabel ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Logo & Cover Media -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-dark pb-2 border-bottom"><i class="fas fa-images text-primary me-2"></i> الشعار وصورة الغلاف</h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">شعار المدرسة (Logo)</label>
                                <?php if (!empty($school['logo_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= e($school['logo_path']) ?>" alt="الشعار الحالي" style="height: 60px; object-fit: contain;" class="border p-1 rounded-2 bg-light">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo" accept="image/*" class="form-control">
                                <small class="text-muted" style="font-size: 0.72rem;">يفضل صورة مربعة عالية الدقة بصيغة PNG أو JPG أو SVG.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">صورة الغلاف (Cover Banner)</label>
                                <?php if (!empty($school['cover_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= e($school['cover_path']) ?>" alt="الغلاف الحالي" style="height: 60px; width: 140px; object-fit: cover;" class="border p-1 rounded-2">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="cover" accept="image/*" class="form-control">
                                <small class="text-muted" style="font-size: 0.72rem;">يفضل صورة أفقية عريضة (1200 × 400 بكسل).</small>
                            </div>

                            <!-- About & Mission -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-dark pb-2 border-bottom"><i class="fas fa-align-right text-primary me-2"></i> النبذة التعريفية والرؤية والرسالة</h6>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">نبذة تعريفية شاملة عن المدرسة</label>
                                <textarea name="description" rows="4" class="form-control" placeholder="اكتب نبذة تشرح تاريخ المدرسة، البيئة التعليمية، الكادر التدريسي، والأنشطة..."><?= e($school['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">رؤية المدرسة (Vision)</label>
                                <textarea name="vision" rows="2" class="form-control" placeholder="الريادة والتميز في..."><?= e($school['vision'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">رسالة المدرسة (Mission)</label>
                                <textarea name="mission" rows="2" class="form-control" placeholder="تقديم تعليم نوعي يبني شخصية الطالب..."><?= e($school['mission'] ?? '') ?></textarea>
                            </div>

                            <!-- Facilities -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-dark pb-2 border-bottom"><i class="fas fa-cubes text-primary me-2"></i> المرافق والخدمات المتوفرة</h6>
                            </div>

                            <div class="col-12">
                                <div class="row g-2">
                                    <?php foreach (SudanGeography::facilitiesList() as $facKey => $facData): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <div class="p-2 border rounded-3 bg-light">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="<?= $facKey ?>" id="fac_<?= $facKey ?>" <?= in_array($facKey, $currentFacilities) ? 'checked' : '' ?>>
                                                    <label class="form-check-label small fw-bold text-dark" for="fac_<?= $facKey ?>">
                                                        <i class="<?= $facData['icon'] ?> text-primary me-1"></i> <?= $facData['label'] ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Contacts, Fees & Social -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-dark pb-2 border-bottom"><i class="fas fa-phone-alt text-primary me-2"></i> بيانات التواصل والرسوم</h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">هاتف المدرسة</label>
                                <input type="tel" name="phone" required class="form-control" value="<?= e($school['phone']) ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">رقم واتساب للتواصل</label>
                                <input type="tel" name="whatsapp" class="form-control" value="<?= e($school['whatsapp'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control" value="<?= e($school['email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">متوسط الرسوم الدراسية التقريبية</label>
                                <input type="text" name="approx_fees" class="form-control" value="<?= e($school['approx_fees'] ?? '') ?>" placeholder="مثال: تبدأ من 250,000 ج.س سنوياً">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">أوقات وساعات الدوام</label>
                                <input type="text" name="working_hours" class="form-control" value="<?= e($school['working_hours'] ?? '') ?>" placeholder="مثال: من الأحد إلى الخميس 7:30 ص - 2:30 م">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">الموقع الإلكتروني للمدرسة</label>
                                <input type="url" name="website" dir="ltr" class="form-control" value="<?= e($school['website'] ?? '') ?>" placeholder="https://myschool.com">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">رابط صفحة فيسبوك</label>
                                <input type="url" name="facebook" dir="ltr" class="form-control" value="<?= e($school['facebook'] ?? '') ?>" placeholder="https://facebook.com/myschool">
                            </div>

                            <!-- Submit Button -->
                            <div class="col-12 mt-4 pt-3 border-top text-end">
                                <button type="submit" class="btn-luxury-primary px-5 py-2">
                                    <i class="fas fa-save me-1"></i> حفظ كافة التعديلات
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: NEWS & ANNOUNCEMENTS -->
                <div class="tab-pane fade" id="dash-news" role="tabpanel">
                    <!-- Add News Form -->
                    <div class="p-4 rounded-4 bg-light border mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-plus-circle text-primary me-1"></i> نشر خبر أو إعلان جديد</h6>
                        <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_news">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-secondary">عنوان الخبر / الإعلان</label>
                                    <input type="text" name="title" required class="form-control" placeholder="مثال: بدء التسجيل للعام الدراسي الجديد أو تكريم الأوائل">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">صورة مرفقة (اختياري)</label>
                                    <input type="file" name="news_image" accept="image/*" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">نص الخبر والتفاصيل</label>
                                    <textarea name="content" required rows="3" class="form-control" placeholder="اكتب تفاصيل الخبر هنا..."></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-paper-plane me-1"></i> نشر الخبر الآن
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- News List -->
                    <h6 class="fw-bold text-dark mb-3">الأخبار المنشورة</h6>
                    <?php if (empty($newsList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-newspaper display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لم تقم بنشر أي أخبار بعد. استخدم النموذج أعلاه لنشر أول إعلان لمدرستك!</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($newsList as $item): ?>
                                <div class="p-3 border rounded-3 bg-white shadow-sm d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
                                    <div class="d-flex gap-3">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="<?= e($item['image_path']) ?>" alt="صورة الخبر" style="width: 80px; height: 80px; object-fit: cover;" class="rounded-3 border">
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1"><?= e($item['title']) ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                <i class="far fa-clock me-1"></i> <?= date('Y-m-d H:i', strtotime($item['created_at'])) ?>
                                                • <i class="fas fa-eye me-1 ms-2"></i> <?= number_format($item['views_count']) ?> مشاهدة
                                            </small>
                                            <p class="text-secondary small mb-0 lh-base"><?= e($item['content']) ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <form method="POST" action="dashboard.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا الخبر؟');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_news">
                                            <input type="hidden" name="news_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt me-1"></i> حذف
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 3: REVIEWS & REPLIES -->
                <div class="tab-pane fade" id="dash-reviews" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">تقييمات أولياء الأمور والطلاب</h6>

                    <?php if (empty($reviewsList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-comment-dots display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لا توجد تقييمات مسجلة لمدرستكم بعد.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($reviewsList as $rev): ?>
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                                <?= mb_substr($rev['reviewer_name'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark"><?= e($rev['reviewer_name']) ?></h6>
                                                <small class="text-muted"><?= e($rev['reviewer_role'] === 'parent' ? 'ولي أمر' : 'طالب') ?> • <?= date('Y-m-d', strtotime($rev['created_at'])) ?></small>
                                            </div>
                                        </div>
                                        <div class="text-warning small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $rev['rating'] ? '' : 'text-muted opacity-25' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-secondary small mb-3"><?= e($rev['comment']) ?></p>

                                    <!-- Reply block -->
                                    <?php if (!empty($rev['school_reply'])): ?>
                                        <div class="p-3 bg-white border border-primary-subtle rounded-3 mb-2">
                                            <div class="small fw-bold text-primary mb-1"><i class="fas fa-reply me-1"></i> ردكم الرسمي:</div>
                                            <p class="small text-dark mb-0"><?= e($rev['school_reply']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Reply Form -->
                                    <form method="POST" action="dashboard.php" class="mt-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reply_review">
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <div class="input-group">
                                            <input type="text" name="reply" required class="form-control form-control-sm" placeholder="<?= empty($rev['school_reply']) ? 'اكتب رد المدرسة الرسمي على هذا التقييم...' : 'تعديل الرد الرسمي...' ?>" value="<?= e($rev['school_reply'] ?? '') ?>">
                                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                                <i class="fas fa-check me-1"></i> حفظ الرد
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 4: ADMISSION INQUIRIES -->
                <div class="tab-pane fade" id="dash-inquiries" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">طلبات القبول واستفسارات أولياء الأمور الواصلة</h6>

                    <?php if (empty($inquiriesList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-inbox display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لم تصل أي طلبات استفسار بعد. ستظهر هنا بمجرد إرسالها من زوار صفحة مدرستكم.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border small">
                                <thead class="table-light">
                                    <tr>
                                        <th>اسم ولي الأمر</th>
                                        <th>الهاتف</th>
                                        <th>الصف المطلوب</th>
                                        <th>الرسالة / الملاحظات</th>
                                        <th>التاريخ</th>
                                        <th>إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inquiriesList as $inq): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= e($inq['parent_name']) ?></td>
                                            <td>
                                                <a href="tel:<?= e($inq['parent_phone']) ?>" class="text-decoration-none fw-bold"><?= e($inq['parent_phone']) ?></a>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= e($inq['student_grade'] ?: 'غير محدد') ?></span></td>
                                            <td class="text-secondary"><?= e($inq['message'] ?: '—') ?></td>
                                            <td class="text-muted"><?= date('Y-m-d H:i', strtotime($inq['created_at'])) ?></td>
                                            <td>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $inq['parent_phone']) ?>?text=<?= urlencode('مرحباً بك أستاذ ' . $inq['parent_name'] . '، تواصلنا معك بخصوص طلبك في مدرسة ' . $school['name']) ?>" target="_blank" class="btn btn-sm btn-success px-2 py-1 rounded-2" title="مراسلة عبر واتساب">
                                                    <i class="fab fa-whatsapp me-1"></i> محادثة واتساب
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<script>
    const geoData = <?= json_encode($statesWithCities, JSON_UNESCAPED_UNICODE) ?>;
    const stateSelect = document.getElementById('dashState');
    const citySelect = document.getElementById('dashCity');
    const selectedCity = <?= json_encode($school['city']) ?>;

    function populateCities(selectedState) {
        citySelect.innerHTML = '<option value="">-- اختر المدينة --</option>';
        if (selectedState && geoData[selectedState]) {
            geoData[selectedState].forEach(function(city) {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if (city === selectedCity) {
                    opt.selected = true;
                }
                citySelect.appendChild(opt);
            });
        }
    }

    stateSelect.addEventListener('change', function() {
        populateCities(this.value);
    });

    // Populate on load
    if (stateSelect.value) {
        populateCities(stateSelect.value);
    }
</script>

<?php
require_once __DIR__ . '/footer.php';
