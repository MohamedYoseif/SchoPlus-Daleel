<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Registration Page (تسجيل مدرسة جديدة وإنشاء حساب الدخول)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

// If already logged in, redirect to dashboard
if (currentSchool()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();
$errors = [];
$formData = [];

// Support GET prefill from SchoPlus AI Assistant or direct links
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['name']))        $formData['name'] = trim((string)$_GET['name']);
    if (!empty($_GET['name_en']))     $formData['name_en'] = trim((string)$_GET['name_en']);
    if (!empty($_GET['country']))     $formData['country'] = trim((string)$_GET['country']);
    if (!empty($_GET['state']))       $formData['state'] = trim((string)$_GET['state']);
    if (!empty($_GET['city']))        $formData['city'] = trim((string)$_GET['city']);
    if (!empty($_GET['district']))    $formData['district'] = trim((string)$_GET['district']);
    if (!empty($_GET['phone']))       $formData['phone'] = trim((string)$_GET['phone']);
    if (!empty($_GET['whatsapp']))    $formData['whatsapp'] = trim((string)$_GET['whatsapp']);
    if (!empty($_GET['owner_name']))  $formData['owner_name'] = trim((string)$_GET['owner_name']);
    if (!empty($_GET['owner_phone'])) $formData['owner_phone'] = trim((string)$_GET['owner_phone']);
    if (!empty($_GET['owner_email'])) $formData['owner_email'] = trim((string)$_GET['owner_email']);
    if (!empty($_GET['owner_title'])) $formData['owner_title'] = trim((string)$_GET['owner_title']);
    // Digital Presence Audit Imports
    if (!empty($_GET['website']))        $formData['website'] = trim((string)$_GET['website']);
    if (!empty($_GET['facebook']))       $formData['facebook'] = trim((string)$_GET['facebook']);
    if (!empty($_GET['tiktok']))         $formData['tiktok'] = trim((string)$_GET['tiktok']);
    if (!empty($_GET['instagram']))      $formData['instagram'] = trim((string)$_GET['instagram']);
    if (!empty($_GET['youtube']))        $formData['youtube'] = trim((string)$_GET['youtube']);
    if (!empty($_GET['google_map_url'])) $formData['google_map_url'] = trim((string)$_GET['google_map_url']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'انتهت صلاحية الجلسة، يرجى إعادة إرسال النموذج.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $country = trim($_POST['country'] ?? '') ?: 'السودان';
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $school_type = trim($_POST['school_type'] ?? 'private');
        $gender_type = trim($_POST['gender_type'] ?? 'mixed');
        $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
        $established_year = trim($_POST['established_year'] ?? '');
        $stages = isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : [];
        $phone = trim($_POST['phone'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $facebook = trim($_POST['facebook'] ?? '');
        $tiktok = trim($_POST['tiktok'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        $google_map_url = trim($_POST['google_map_url'] ?? '');

        // Owner & Auth Info
        $owner_name = trim($_POST['owner_name'] ?? '');
        $owner_title = trim($_POST['owner_title'] ?? 'مدير المدرسة');
        $owner_phone = trim($_POST['owner_phone'] ?? '');
        $owner_email = trim($_POST['owner_email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';

        $formData = $_POST;

        // Validation
        if (mb_strlen($name) < 3) {
            $errors[] = 'يرجى إدخال اسم المدرسة بشكل صحيح (3 أحرف على الأقل).';
        }
        if (!$country) {
            $errors[] = 'يرجى اختيار الدولة.';
        }
        if (!$state) {
            $errors[] = 'يرجى اختيار الولاية.';
        }
        if (!$city) {
            $errors[] = 'يرجى اختيار أو كتابة المدينة / المحلية.';
        }
        if (!$phone) {
            $errors[] = 'يرجى إدخال هاتف المدرسة للتواصل.';
        }
        if (mb_strlen($owner_name) < 3) {
            $errors[] = 'يرجى إدخال اسم المسؤول / مدير المدرسة.';
        }
        if (strlen($owner_phone) < 9) {
            $errors[] = 'يرجى إدخال رقم هاتف المسؤول بشكل صحيح (سيستخدم لتسجيل الدخول).';
        }
        if (strlen($password) < 6) {
            $errors[] = 'كلمة المرور يجب أن لا تقل عن 6 أحرف أو أرقام.';
        }
        if ($password !== $password_confirmation) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }

        // Check if phone or email already registered
        if (empty($errors)) {
            $checkStmt = $pdo->prepare("SELECT id FROM daleel_schools WHERE owner_phone = ? LIMIT 1");
            $checkStmt->execute([$owner_phone]);
            if ($checkStmt->fetch()) {
                $errors[] = 'رقم هاتف المسؤول مسجل مسبقاً لمدرسة أخرى. يمكنك تسجيل الدخول به مباشرة.';
            }
        }

        // Generate Slug
        if (empty($errors)) {
            // Slug based on English name or transliteration or unique id
            $baseSlug = '';
            if (!empty($name_en)) {
                $baseSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name_en));
            }
            if (empty($baseSlug) || strlen($baseSlug) < 3) {
                // Generate clean slug with random hex
                $baseSlug = 'school-' . substr(bin2hex(random_bytes(4)), 0, 6);
            }
            $baseSlug = trim($baseSlug, '-');

            $slug = $baseSlug;
            $counter = 1;
            while (true) {
                $slugCheck = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ?");
                $slugCheck->execute([$slug]);
                if (!$slugCheck->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Insert Record
            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stagesJson = json_encode($stages, JSON_UNESCAPED_UNICODE);
                $facilitiesJson = json_encode([], JSON_UNESCAPED_UNICODE);

                $insStmt = $pdo->prepare("INSERT INTO daleel_schools (
                    name, name_en, slug, country, school_type, gender_type, curriculum, established_year, stages,
                    state, city, district, phone, whatsapp, email, website, facebook, tiktok, instagram, youtube, google_map_url, facilities,
                    status, owner_name, owner_title, owner_phone, owner_email, password,
                    rating_avg, reviews_count, views_count, is_schoplus_client, is_verified, created_at, updated_at
                ) VALUES (
                    :name, :name_en, :slug, :country, :school_type, :gender_type, :curriculum, :established_year, :stages,
                    :state, :city, :district, :phone, :whatsapp, :email, :website, :facebook, :tiktok, :instagram, :youtube, :google_map_url, :facilities,
                    'pending', :owner_name, :owner_title, :owner_phone, :owner_email, :password,
                    5.00, 0, 1, 0, 0, NOW(), NOW()
                )");

                $insStmt->execute([
                    ':name' => $name,
                    ':name_en' => $name_en ?: null,
                    ':slug' => $slug,
                    ':country' => $country,
                    ':school_type' => $school_type,
                    ':gender_type' => $gender_type,
                    ':curriculum' => $curriculum,
                    ':established_year' => $established_year ?: null,
                    ':stages' => $stagesJson,
                    ':state' => $state,
                    ':city' => $city,
                    ':district' => $district ?: null,
                    ':phone' => $phone,
                    ':whatsapp' => $whatsapp ?: $phone,
                    ':email' => $owner_email ?: null,
                    ':website' => $website ?: null,
                    ':facebook' => $facebook ?: null,
                    ':tiktok' => $tiktok ?: null,
                    ':instagram' => $instagram ?: null,
                    ':youtube' => $youtube ?: null,
                    ':google_map_url' => $google_map_url ?: null,
                    ':facilities' => $facilitiesJson,
                    ':owner_name' => $owner_name,
                    ':owner_title' => $owner_title,
                    ':owner_phone' => $owner_phone,
                    ':owner_email' => $owner_email ?: null,
                    ':password' => $hashedPassword,
                ]);

                $newSchoolId = (int)$pdo->lastInsertId();

                // Log in immediately
                $_SESSION['daleel_school_id'] = $newSchoolId;
                $_SESSION['flash_msg'] = 'مرحباً بكم! تم تسجيل بيانات مدرستكم بنجاح. الحساب قيد المراجعة والتأكيد، ويمكنكم الآن إكمال كافة تفاصيل الملف التعريفي.';

                header('Location: dashboard.php');
                exit;

            } catch (Exception $e) {
                $errors[] = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
            }
        }
    }
}

$initialStep = 1;
if (!empty($errors)) {
    $errStr = implode(' ', $errors);
    if (strpos($errStr, 'المسؤول') !== false || strpos($errStr, 'كلمة المرور') !== false || strpos($errStr, 'مسجل مسبقاً') !== false) {
        $initialStep = 3;
    } elseif (strpos($errStr, 'هاتف المدرسة') !== false) {
        $initialStep = 2;
    } else {
        $initialStep = 1;
    }
}

$pageTitle = 'تسجيل مدرسة جديدة';
require_once __DIR__ . '/header.php';
$countriesWithStates = SudanGeography::countriesWithStatesAndCities();
$countriesList = SudanGeography::countries();
$selectedCountry = $formData['country'] ?? 'السودان';
$statesWithCities = SudanGeography::statesWithCities($selectedCountry);
?>

<style>
    /* SchoPlus Ocean Brand Design System - Style F v2 Stepper */
    .sp-wizard-stepper {
        position: relative;
        padding: 10px 0 22px;
        margin-bottom: 2rem;
    }
    .stepper-track-wrap {
        position: absolute;
        top: 32px;
        left: 12%;
        right: 12%;
        height: 4px;
        background: #E2E8F0;
        border-radius: 4px;
        z-index: 1;
    }
    .stepper-progress {
        position: absolute;
        top: 0;
        right: 0;
        height: 100%;
        background: linear-gradient(90deg, #1E5FA8 0%, #F5A020 100%);
        border-radius: 4px;
        transition: width 0.35s ease;
    }
    .stepper-steps {
        position: relative;
        z-index: 2;
        display: flex;
        justify-content: space-between;
    }
    .stepper-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        text-align: center;
        background: transparent;
        border: none;
        padding: 0 8px;
        transition: transform 0.2s ease;
        user-select: none;
    }
    .stepper-step:hover {
        transform: translateY(-2px);
    }
    .step-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #FFFFFF;
        border: 2.5px solid #CBD5E1;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.95rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .step-check {
        display: none;
    }
    .step-content {
        margin-top: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .step-eyebrow {
        font-size: 0.65rem;
        color: #94A3B8;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .step-title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #475569;
        transition: color 0.25s ease;
        white-space: nowrap;
    }
    .stepper-step.active .step-circle {
        background: #0B2A52;
        border-color: #F5A020;
        color: #FFFFFF;
        box-shadow: 0 0 0 5px rgba(245, 160, 32, 0.2);
    }
    .stepper-step.active .step-eyebrow {
        color: #F5A020;
    }
    .stepper-step.active .step-title {
        color: #0B2A52;
        font-weight: 800;
    }
    .stepper-step.completed .step-circle {
        background: #10B981;
        border-color: #10B981;
        color: #FFFFFF;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }
    .stepper-step.completed .step-num {
        display: none;
    }
    .stepper-step.completed .step-check {
        display: inline-block;
        font-size: 0.9rem;
    }
    .stepper-step.completed .step-title {
        color: #10B981;
    }

    /* Wizard Panes */
    .wizard-step-pane {
        display: none;
    }
    .wizard-step-pane.active {
        display: block;
        animation: wizardFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes wizardFadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Summary Card */
    .wizard-summary-card {
        background: #F8FAFC;
        border: 1.5px dashed #CBD5E1;
        border-radius: 14px;
        padding: 16px 20px;
    }
    .summary-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        color: #334155;
    }
    .summary-item i {
        width: 18px;
        color: #1E5FA8;
        text-align: center;
    }

    /* Form feedback and invalid cues */
    .form-feedback-msg {
        font-size: 0.72rem;
        color: #EF4444;
        margin-top: 4px;
        display: none;
    }
    .is-invalid + .form-feedback-msg {
        display: block;
    }
    .is-invalid {
        border-color: #EF4444 !important;
        background-color: #FEF2F2 !important;
    }
</style>

<div class="register-page py-5">
    <div class="container" style="max-width: 900px;">
        
        <!-- Header Banner -->
        <div class="text-center mb-4">
            <img src="/assets/images/logo-icon.png" alt="دليل المدارس السودانية" class="mx-auto mb-3 d-block" style="width: 64px; height: 64px; object-fit: contain;">
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold mb-2 shadow-sm" style="font-size: 0.72rem;">
                <i class="fas fa-plus-circle me-1"></i> تسجيل سريع ومباشر
            </span>
            <h2 class="fw-bold text-dark mb-2" style="font-size: 1.25rem;">سجّل مدرستك في دليل المدارس</h2>
            <p class="text-muted" style="max-width: 620px; margin: auto; font-size: 0.76rem; line-height: 1.65;">
                عرّف بمدرستك لأولياء الأمور والطلاب في 3 خطوات بسيطة. يمكنك إدخال البيانات الأساسية الآن، وإكمال التفاصيل والصور والأخبار والمرافق لاحقاً عبر لوحة التحكم في أي وقت.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i> يرجى تصحيح الملاحظات التالية:</h6>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Wizard Stepper Indicator -->
        <div class="sp-wizard-stepper">
            <div class="stepper-track-wrap">
                <div class="stepper-progress" id="wizardProgressBar" style="width: 0%;"></div>
            </div>
            <div class="stepper-steps">
                <div class="stepper-step active" id="stepIndicator1" onclick="jumpToWizardStep(1)">
                    <div class="step-circle">
                        <span class="step-num">1</span>
                        <i class="fas fa-check step-check"></i>
                    </div>
                    <div class="step-content">
                        <span class="step-eyebrow">الخطوة 1</span>
                        <span class="step-title">هوية المدرسة</span>
                    </div>
                </div>
                <div class="stepper-step" id="stepIndicator2" onclick="jumpToWizardStep(2)">
                    <div class="step-circle">
                        <span class="step-num">2</span>
                        <i class="fas fa-check step-check"></i>
                    </div>
                    <div class="step-content">
                        <span class="step-eyebrow">الخطوة 2</span>
                        <span class="step-title">الاتصال والتواجد</span>
                    </div>
                </div>
                <div class="stepper-step" id="stepIndicator3" onclick="jumpToWizardStep(3)">
                    <div class="step-circle">
                        <span class="step-num">3</span>
                        <i class="fas fa-check step-check"></i>
                    </div>
                    <div class="step-content">
                        <span class="step-eyebrow">الخطوة 3</span>
                        <span class="step-title">حساب الإدارة والتأكيد</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card border-0 rounded-4 shadow-sm bg-white p-4 p-md-5">
            <form method="POST" action="register.php" id="schoolRegisterForm" novalidate>
                <?= csrf_field() ?>

                <!-- STEP 1: IDENTITY & BASIC INFO -->
                <div class="wizard-step-pane active" id="wizardStep1">
                    <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            1
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">بيانات المدرسة والموقع</h5>
                            <small class="text-muted">الاسم الرسمي للمدرسة والموقع الجغرافي والبيانات الأكاديمية</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-secondary">اسم المدرسة باللغة العربية <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($formData['name'] ?? '') ?>" placeholder="مثال: مدارس المنار النموذجية الخاصة">
                            <div class="form-feedback-msg" id="err_name">يرجى إدخال اسم المدرسة (3 أحرف على الأقل).</div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary">اسم المدرسة باللغة الإنجليزية (اختياري)</label>
                            <input type="text" name="name_en" id="regNameEn" dir="ltr" class="form-control" value="<?= e($formData['name_en'] ?? '') ?>" placeholder="e.g. Al-Manar Model Schools">
                            <div class="form-text mt-1 d-flex align-items-center gap-1" style="font-size: 0.74rem;">
                                <i class="fas fa-link text-primary"></i>
                                <span>الرابط العام المخصص:</span>
                                <code id="regSlugPreview" class="text-primary fw-bold dir-ltr">daleel.schoplus.sd/school.php?slug=...</code>
                            </div>
                        </div>

                        <!-- Country, State, City & District -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">الدولة <span class="text-danger">*</span></label>
                            <select name="country" id="regCountry" class="form-select">
                                <?php foreach ($countriesList as $cntry): ?>
                                    <option value="<?= e($cntry) ?>" <?= $selectedCountry === $cntry ? 'selected' : '' ?>>
                                        <?= e($cntry) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-feedback-msg" id="err_country">يرجى اختيار الدولة.</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">الولاية / المنطقة <span class="text-danger">*</span></label>
                            <select name="state" id="regState" class="form-select">
                                <option value="">-- اختر الولاية --</option>
                                <?php foreach ($statesWithCities as $stName => $cities): ?>
                                    <option value="<?= e($stName) ?>" <?= ($formData['state'] ?? '') === $stName ? 'selected' : '' ?>>
                                        <?= e($stName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-feedback-msg" id="err_state">يرجى اختيار الولاية.</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">المدينة / المحلية <span class="text-danger">*</span></label>
                            <select name="city" id="regCity" class="form-select">
                                <option value="">-- اختر المدينة --</option>
                            </select>
                            <div class="form-feedback-msg" id="err_city">يرجى اختيار أو تحديد المدينة.</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">الحي / المنطقة</label>
                            <input type="text" name="district" class="form-control" value="<?= e($formData['district'] ?? '') ?>" placeholder="مثال: الرياض، المعمورة، الثورة">
                        </div>

                        <!-- Type, Gender, Curriculum & Foundation -->
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">نوع المدرسة</label>
                            <select name="school_type" class="form-select">
                                <?php foreach (SudanGeography::schoolTypes() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['school_type'] ?? 'private') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">نوع الطلاب</label>
                            <select name="gender_type" class="form-select">
                                <?php foreach (SudanGeography::genderTypes() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['gender_type'] ?? 'mixed') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">المنهج الدراسي</label>
                            <select name="curriculum" class="form-select">
                                <?php foreach (SudanGeography::curricula() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['curriculum'] ?? 'sudanese') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">
                                <i class="fas fa-landmark text-muted me-1"></i> سنة / تاريخ التأسيس
                            </label>
                            <input type="text" name="established_year" class="form-control" value="<?= e($formData['established_year'] ?? '') ?>" placeholder="مثال: 2012 أو عام 1998">
                        </div>

                        <!-- Stages -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary d-block">المراحل التعليمية المتوفرة بالمدرسة</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php 
                                $selectedStages = $formData['stages'] ?? ['primary', 'intermediate', 'secondary'];
                                foreach (SudanGeography::stages() as $stgKey => $stgLabel): 
                                ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="stages[]" value="<?= $stgKey ?>" id="stg_<?= $stgKey ?>" <?= in_array($stgKey, $selectedStages) ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-bold text-dark" for="stg_<?= $stgKey ?>">
                                            <?= $stgLabel ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1 Nav Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                        <div class="text-muted small">
                            <i class="fas fa-info-circle text-primary me-1"></i> الحقول المعلمة بـ (<span class="text-danger">*</span>) إلزامية
                        </div>
                        <button type="button" class="btn-luxury-primary px-4 py-2" onclick="nextWizardStep(1)">
                            <span>متابعة: الاتصال والتواجد</span>
                            <i class="fas fa-arrow-left ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: CONTACT & DIGITAL PRESENCE -->
                <div class="wizard-step-pane" id="wizardStep2">
                    <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            2
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">قنوات الاتصال والوجود الرقمي</h5>
                            <small class="text-muted">أرقام التواصل مع أولياء الأمور وروابط المنصات الرقمية وموقع المدرسة</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- School Phone & WhatsApp -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم هاتف المدرسة للتواصل مع أولياء الأمور <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($formData['phone'] ?? '') ?>" placeholder="09xxxxxxx أو 01xxxxxxx">
                            <div class="form-feedback-msg" id="err_phone">يرجى إدخال هاتف المدرسة للتواصل.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم واتساب المدرسة (للاستفسارات المباشرة)</label>
                            <input type="tel" name="whatsapp" class="form-control" value="<?= e($formData['whatsapp'] ?? '') ?>" placeholder="09xxxxxxx">
                        </div>

                        <!-- Digital Presence & Social Links -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold mb-3 d-inline-block" style="font-size: 0.68rem;">
                                <i class="fas fa-satellite-dish me-1"></i> الوجود الرقمي وشبكات التواصل (مستورد أو اختياري)
                            </span>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">الموقع الإلكتروني للمدرسة</label>
                                    <input type="url" name="website" class="form-control form-control-sm" value="<?= e($formData['website'] ?? '') ?>" placeholder="https://example.edu.sd">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">رابط صفحة الفيسبوك</label>
                                    <input type="url" name="facebook" class="form-control form-control-sm" value="<?= e($formData['facebook'] ?? '') ?>" placeholder="https://facebook.com/SchoolName">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">حساب تيك توك</label>
                                    <input type="text" name="tiktok" class="form-control form-control-sm" value="<?= e($formData['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@school">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">انستغرام / يوتيوب</label>
                                    <input type="text" name="instagram" class="form-control form-control-sm" value="<?= e($formData['instagram'] ?? ($formData['youtube'] ?? '')) ?>" placeholder="رابط انستغرام أو يوتيوب">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">رابط خريطة جوجل</label>
                                    <input type="url" name="google_map_url" class="form-control form-control-sm" value="<?= e($formData['google_map_url'] ?? '') ?>" placeholder="https://maps.google.com/?q=...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 Nav Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                        <button type="button" class="btn-luxury-outline px-4 py-2" onclick="prevWizardStep(2)">
                            <i class="fas fa-arrow-right me-2"></i>
                            <span>السابق: هوية المدرسة</span>
                        </button>
                        <button type="button" class="btn-luxury-primary px-4 py-2" onclick="nextWizardStep(2)">
                            <span>متابعة: حساب الإدارة</span>
                            <i class="fas fa-arrow-left ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: OWNER CREDENTIALS & CONFIRMATION -->
                <div class="wizard-step-pane" id="wizardStep3">
                    <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            3
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">بيانات المسؤول وحساب الدخول</h5>
                            <small class="text-muted">ستستخدم هذه البيانات لتسجيل الدخول للوحة إدارة المدرسة وتحديث الملف التعريفي</small>
                        </div>
                    </div>

                    <!-- Live Summary Card -->
                    <div class="wizard-summary-card mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-secondary-subtle">
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;">
                                <i class="fas fa-clipboard-check text-success me-1"></i> ملخص بيانات التسجيل
                            </span>
                            <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none" onclick="jumpToWizardStep(1)" style="font-size: 0.74rem;">
                                <i class="fas fa-edit me-1"></i> تعديل بيانات المدرسة
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="summary-item">
                                    <i class="fas fa-school"></i>
                                    <span class="text-muted">المدرسة:</span>
                                    <strong id="summarySchoolName" class="text-dark">--</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="text-muted">الموقع:</span>
                                    <strong id="summaryLocation" class="text-dark">--</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-item">
                                    <i class="fas fa-phone"></i>
                                    <span class="text-muted">هاتف المدرسة:</span>
                                    <strong id="summaryPhone" class="text-dark" dir="ltr">--</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-item">
                                    <i class="fas fa-user-shield"></i>
                                    <span class="text-muted">المسؤول:</span>
                                    <strong id="summaryAdminName" class="text-dark">--</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">اسم المسؤول / المدير <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" class="form-control" value="<?= e($formData['owner_name'] ?? '') ?>" placeholder="مثال: أ. عبد الله إبراهيم">
                            <div class="form-feedback-msg" id="err_owner_name">يرجى إدخال اسم المسؤول / مدير المدرسة.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">الصفة أو المسمى الوظيفي</label>
                            <input type="text" name="owner_title" class="form-control" value="<?= e($formData['owner_title'] ?? 'مدير المدرسة') ?>" placeholder="مثال: المدير العام / مدير المرحلة">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم هاتف المسؤول (اسم المستخدم للدخول) <span class="text-danger">*</span></label>
                            <input type="tel" name="owner_phone" class="form-control" value="<?= e($formData['owner_phone'] ?? '') ?>" placeholder="09xxxxxxx">
                            <small class="text-muted" style="font-size: 0.72rem;">سيتم استخدامه كاسم مستخدم عند تسجيل الدخول.</small>
                            <div class="form-feedback-msg" id="err_owner_phone">يرجى إدخال رقم هاتف المسؤول بشكل صحيح (9 أرقام على الأقل).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">البريد الإلكتروني للمسؤول (اختياري)</label>
                            <input type="email" name="owner_email" class="form-control" value="<?= e($formData['owner_email'] ?? '') ?>" placeholder="admin@school.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="6 خانات على الأقل">
                            <div class="form-feedback-msg" id="err_password">كلمة المرور يجب أن لا تقل عن 6 أحرف أو أرقام.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" placeholder="أعد إدخال كلمة المرور">
                            <div class="form-feedback-msg" id="err_password_confirmation">كلمتا المرور غير متطابقتين.</div>
                        </div>
                    </div>

                    <!-- Info & Usage Policy Box -->
                    <div class="p-3 rounded-4 bg-light border border-light-subtle my-4">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-info-circle text-primary mt-1"></i>
                            <small class="text-secondary lh-base">
                                <strong>سياسة الاستخدام والتسجيل:</strong> هذا الدليل هو خدمة رقمية مقدمة من <strong>منظومة SchoPlus الذكية</strong>، وليس دليلاً رسمياً تابعاً لوزارة التربية والتعليم. جميع البيانات والصور والرسوم المنشورة تتم إضافتها وإدارتها من قِبل إدارة المدرسة وعلى مسؤوليتها. بمجرد التسجيل يمكنك الدخول مباشرة للوحة التحكم وإكمال ملف مدرستك.
                            </small>
                        </div>
                    </div>

                    <!-- Step 3 Nav Actions -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                        <button type="button" class="btn-luxury-outline px-4 py-2 order-2 order-sm-1" onclick="prevWizardStep(3)">
                            <i class="fas fa-arrow-right me-2"></i>
                            <span>السابق: قنوات الاتصال</span>
                        </button>
                        <button type="submit" class="btn-luxury-primary px-5 py-3 fs-6 order-1 order-sm-2 w-100 w-sm-auto shadow-sm">
                            <i class="fas fa-check-circle me-2"></i>
                            <span>تأكيد التسجيل وإنشاء الحساب</span>
                        </button>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <span class="text-muted small">لديك حساب مسجل بالفعل؟</span>
                    <a href="login.php" class="text-primary fw-bold small text-decoration-none ms-1">تسجيل الدخول من هنا</a>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
    const allGeo = <?= json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE) ?>;
    const countrySelect = document.getElementById('regCountry');
    const stateSelect = document.getElementById('regState');
    const citySelect = document.getElementById('regCity');
    const selectedStateVal = <?= json_encode($formData['state'] ?? '') ?>;
    const selectedCity = <?= json_encode($formData['city'] ?? '') ?>;

    function populateStates(countryName) {
        stateSelect.innerHTML = '<option value="">-- اختر الولاية / المنطقة --</option>';
        citySelect.innerHTML = '<option value="">-- اختر المدينة --</option>';
        const statesObj = allGeo[countryName] || {};
        Object.keys(statesObj).forEach(function(st) {
            const opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            if (st === selectedStateVal) {
                opt.selected = true;
            }
            stateSelect.appendChild(opt);
        });
        if (stateSelect.value) {
            populateCities(stateSelect.value);
        }
    }

    function populateCities(selectedState) {
        citySelect.innerHTML = '<option value="">-- اختر المدينة --</option>';
        const countryName = countrySelect.value || 'السودان';
        const statesObj = allGeo[countryName] || {};
        if (selectedState && statesObj[selectedState]) {
            statesObj[selectedState].forEach(function(city) {
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

    countrySelect.addEventListener('change', function() {
        populateStates(this.value);
    });

    stateSelect.addEventListener('change', function() {
        populateCities(this.value);
    });

    if (stateSelect.value) {
        populateCities(stateSelect.value);
    }

    // Live slug preview from English name
    const regNameEn = document.getElementById('regNameEn');
    const regSlugPreview = document.getElementById('regSlugPreview');
    if (regNameEn && regSlugPreview) {
        function updateRegSlug() {
            let val = regNameEn.value.toLowerCase()
                .replace(/[\s,،_./\\+=?!@#$%^&*()~`'"[\]{}|<>:;]+/g, '-')
                .replace(/[^a-z0-9-]/g, '')
                .replace(/-+/g, '-')
                .replace(/^-+|-+$/g, '');
            regSlugPreview.textContent = val ? 'daleel.schoplus.sd/school.php?slug=' + val : 'daleel.schoplus.sd/...';
        }
        regNameEn.addEventListener('input', updateRegSlug);
        updateRegSlug();
    }

    // ==========================================
    // 3-STEP WIZARD LOGIC
    // ==========================================
    let currentWizardStep = <?= (int)$initialStep ?>;

    function setWizardStep(step) {
        currentWizardStep = step;

        // Update Panes & Stepper
        for (let i = 1; i <= 3; i++) {
            const pane = document.getElementById('wizardStep' + i);
            const ind = document.getElementById('stepIndicator' + i);
            if (pane) {
                pane.classList.toggle('active', i === step);
            }
            if (ind) {
                ind.classList.remove('active', 'completed');
                if (i < step) {
                    ind.classList.add('completed');
                } else if (i === step) {
                    ind.classList.add('active');
                }
            }
        }

        // Update Progress Bar
        const progress = document.getElementById('wizardProgressBar');
        if (progress) {
            if (step === 1) progress.style.width = '0%';
            else if (step === 2) progress.style.width = '50%';
            else if (step === 3) progress.style.width = '100%';
        }

        if (step === 3) {
            updateSummaryCard();
        }

        // Scroll smoothly to form top if out of view
        const formCard = document.getElementById('schoolRegisterForm');
        if (formCard) {
            const rect = formCard.getBoundingClientRect();
            if (rect.top < 60) {
                window.scrollTo({
                    top: window.pageYOffset + rect.top - 80,
                    behavior: 'smooth'
                });
            }
        }
    }

    function clearValidationErrors(step) {
        const pane = document.getElementById('wizardStep' + step);
        if (pane) {
            pane.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        }
    }

    function markInvalid(inputEl) {
        if (!inputEl) return;
        inputEl.classList.add('is-invalid');
        inputEl.addEventListener('input', function onInput() {
            inputEl.classList.remove('is-invalid');
            inputEl.removeEventListener('input', onInput);
        });
        inputEl.addEventListener('change', function onChange() {
            inputEl.classList.remove('is-invalid');
            inputEl.removeEventListener('change', onChange);
        });
    }

    function validateStep(step) {
        clearValidationErrors(step);
        let isValid = true;
        let firstInvalid = null;

        if (step === 1) {
            const nameInput = document.querySelector('input[name="name"]');
            if (!nameInput || nameInput.value.trim().length < 3) {
                markInvalid(nameInput);
                isValid = false;
                if (!firstInvalid) firstInvalid = nameInput;
            }

            const countryInput = document.getElementById('regCountry');
            if (!countryInput || !countryInput.value.trim()) {
                markInvalid(countryInput);
                isValid = false;
                if (!firstInvalid) firstInvalid = countryInput;
            }

            const stateInput = document.getElementById('regState');
            if (!stateInput || !stateInput.value.trim()) {
                markInvalid(stateInput);
                isValid = false;
                if (!firstInvalid) firstInvalid = stateInput;
            }

            const cityInput = document.getElementById('regCity');
            if (!cityInput || !cityInput.value.trim()) {
                markInvalid(cityInput);
                isValid = false;
                if (!firstInvalid) firstInvalid = cityInput;
            }
        } else if (step === 2) {
            const phoneInput = document.querySelector('input[name="phone"]');
            if (!phoneInput || phoneInput.value.trim().length < 7) {
                markInvalid(phoneInput);
                isValid = false;
                if (!firstInvalid) firstInvalid = phoneInput;
            }
        } else if (step === 3) {
            const ownerName = document.querySelector('input[name="owner_name"]');
            if (!ownerName || ownerName.value.trim().length < 3) {
                markInvalid(ownerName);
                isValid = false;
                if (!firstInvalid) firstInvalid = ownerName;
            }

            const ownerPhone = document.querySelector('input[name="owner_phone"]');
            if (!ownerPhone || ownerPhone.value.trim().length < 9) {
                markInvalid(ownerPhone);
                isValid = false;
                if (!firstInvalid) firstInvalid = ownerPhone;
            }

            const pass = document.querySelector('input[name="password"]');
            if (!pass || pass.value.length < 6) {
                markInvalid(pass);
                isValid = false;
                if (!firstInvalid) firstInvalid = pass;
            }

            const passConf = document.querySelector('input[name="password_confirmation"]');
            if (!passConf || passConf.value !== (pass ? pass.value : '')) {
                markInvalid(passConf);
                isValid = false;
                if (!firstInvalid) firstInvalid = passConf;
            }
        }

        if (!isValid && firstInvalid) {
            firstInvalid.focus();
        }

        return isValid;
    }

    function nextWizardStep(fromStep) {
        if (!validateStep(fromStep)) {
            return false;
        }
        setWizardStep(fromStep + 1);
    }

    function prevWizardStep(fromStep) {
        setWizardStep(fromStep - 1);
    }

    function jumpToWizardStep(targetStep) {
        if (targetStep === currentWizardStep) return;
        if (targetStep < currentWizardStep) {
            setWizardStep(targetStep);
        } else {
            for (let s = currentWizardStep; s < targetStep; s++) {
                if (!validateStep(s)) {
                    return;
                }
            }
            setWizardStep(targetStep);
        }
    }

    function updateSummaryCard() {
        const name = document.querySelector('input[name="name"]')?.value.trim() || '--';
        const country = document.getElementById('regCountry')?.value.trim() || '';
        const state = document.getElementById('regState')?.value.trim() || '';
        const city = document.getElementById('regCity')?.value.trim() || '';
        const location = [city, state, country].filter(Boolean).join(' - ') || '--';
        const phone = document.querySelector('input[name="phone"]')?.value.trim() || '--';
        const ownerName = document.querySelector('input[name="owner_name"]')?.value.trim() || '--';
        const ownerTitle = document.querySelector('input[name="owner_title"]')?.value.trim();

        const sumName = document.getElementById('summarySchoolName');
        const sumLoc = document.getElementById('summaryLocation');
        const sumPhone = document.getElementById('summaryPhone');
        const sumAdmin = document.getElementById('summaryAdminName');

        if (sumName) sumName.textContent = name;
        if (sumLoc) sumLoc.textContent = location;
        if (sumPhone) sumPhone.textContent = phone;
        if (sumAdmin) sumAdmin.textContent = ownerName + (ownerTitle ? ' (' + ownerTitle + ')' : '');
    }

    // Attach form submit validation across all steps
    const regForm = document.getElementById('schoolRegisterForm');
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            if (!validateStep(1)) {
                e.preventDefault();
                setWizardStep(1);
                return false;
            }
            if (!validateStep(2)) {
                e.preventDefault();
                setWizardStep(2);
                return false;
            }
            if (!validateStep(3)) {
                e.preventDefault();
                setWizardStep(3);
                return false;
            }
        });
    }

    // Initialize with initial step
    setWizardStep(currentWizardStep);
</script>

<?php
require_once __DIR__ . '/footer.php';
