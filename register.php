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
    /* ========================================================
       SchoPlus Ocean Brand Design System — Style F v2 Wizard
       ======================================================== */
    .sp-register-wrap {
        max-width: 840px;
        margin: 0 auto;
    }

    /* Main Card */
    .sp-wizard-card {
        background: #FFFFFF;
        border: 1px solid #DDE4ED;
        border-radius: 20px;
        box-shadow: 0 12px 36px rgba(11, 42, 82, 0.05);
        overflow: hidden;
    }

    /* Stepper Header */
    .sp-wizard-header {
        background: linear-gradient(180deg, #F8FAFC 0%, #FFFFFF 100%);
        border-bottom: 1px solid #E2E8F0;
        padding: 24px 28px 18px;
    }
    .sp-stepper-nav {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }
    .sp-step-tab {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: #FFFFFF;
        border: 1.5px solid #E2E8F0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.25s ease;
        user-select: none;
    }
    .sp-step-tab .step-badge {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: #F1F5F9;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        flex-shrink: 0;
        transition: all 0.25s ease;
    }
    .sp-step-tab .step-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .sp-step-tab .step-eyebrow {
        font-size: 0.64rem;
        color: #94A3B8;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .sp-step-tab .step-title {
        font-size: 0.82rem;
        font-weight: 800;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.25s ease;
    }

    /* Active & Completed States */
    .sp-step-tab.active {
        background: #FFFFFF;
        border-color: #1E5FA8;
        box-shadow: 0 4px 14px rgba(30, 95, 168, 0.1);
    }
    .sp-step-tab.active .step-badge {
        background: #0B2A52;
        color: #F5A020;
    }
    .sp-step-tab.active .step-eyebrow {
        color: #1E5FA8;
    }
    .sp-step-tab.active .step-title {
        color: #0B2A52;
    }

    .sp-step-tab.completed {
        border-color: #10B981;
        background: #F0FDF4;
    }
    .sp-step-tab.completed .step-badge {
        background: #10B981;
        color: #FFFFFF;
    }
    .sp-step-tab.completed .step-title {
        color: #15803D;
    }

    /* Progress Track */
    .sp-progress-track {
        height: 4px;
        background: #E2E8F0;
        width: 100%;
        position: relative;
    }
    .sp-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #1E5FA8 0%, #F5A020 100%);
        transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Form Body & Panes */
    .sp-wizard-body {
        padding: 32px 32px 36px;
    }
    @media (max-width: 768px) {
        .sp-wizard-header { padding: 16px; }
        .sp-wizard-body { padding: 20px 16px; }
        .sp-stepper-nav { grid-template-columns: 1fr; gap: 8px; }
    }

    .wizard-step-pane {
        display: none;
    }
    .wizard-step-pane.active {
        display: block;
        animation: spFadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes spFadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Clean Form Sub-Sections */
    .sp-form-section {
        background: #FBFDFF;
        border: 1px solid #E8EEF5;
        border-radius: 14px;
        padding: 20px 22px;
        margin-bottom: 22px;
    }
    .sp-form-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #EEF3F8;
    }
    .sp-section-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: #EFF6FF;
        color: #1E5FA8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.88rem;
        flex-shrink: 0;
    }
    .sp-section-title {
        font-size: 0.88rem;
        font-weight: 800;
        color: #0B2A52;
        margin: 0;
    }
    .sp-section-desc {
        font-size: 0.72rem;
        color: #64748B;
        margin: 0;
    }

    /* Input Controls */
    .sp-field {
        margin-bottom: 14px;
    }
    .sp-field:last-child {
        margin-bottom: 0;
    }
    .sp-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 6px;
    }
    .sp-label .req {
        color: #EF4444;
        margin-right: 2px;
    }
    .sp-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }
    .sp-input-icon {
        position: absolute;
        right: 14px;
        color: #94A3B8;
        font-size: 0.92rem;
        pointer-events: none;
        z-index: 2;
        transition: color 0.2s ease;
    }
    .sp-control {
        width: 100%;
        height: 46px;
        background: #FFFFFF;
        border: 1.5px solid #DDE4ED;
        border-radius: 10px;
        padding: 0 42px 0 14px;
        font-size: 0.88rem;
        color: #1E293B;
        transition: all 0.2s ease;
    }
    .sp-control:focus {
        border-color: #1E5FA8;
        background: #FFFFFF;
        box-shadow: 0 0 0 4px rgba(30, 95, 168, 0.1);
        outline: none;
    }
    .sp-input-wrap:focus-within .sp-input-icon {
        color: #1E5FA8;
    }
    .sp-control.is-invalid {
        border-color: #EF4444 !important;
        background-color: #FEF2F2 !important;
    }
    .sp-feedback-error {
        font-size: 0.72rem;
        color: #EF4444;
        margin-top: 4px;
        display: none;
        font-weight: 600;
    }
    .sp-control.is-invalid ~ .sp-feedback-error,
    .is-invalid + .sp-feedback-error {
        display: block;
    }

    /* Eye toggle button inside password */
    .sp-pwd-toggle {
        position: absolute;
        left: 10px;
        background: none;
        border: none;
        color: #94A3B8;
        cursor: pointer;
        padding: 6px;
        font-size: 0.88rem;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sp-pwd-toggle:hover {
        color: #1E5FA8;
    }

    /* Slug preview box */
    .sp-slug-preview-box {
        margin-top: 6px;
        background: #F1F5F9;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.73rem;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #475569;
        overflow-x: auto;
    }

    /* Stage Selection Chips */
    .sp-stage-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
    }
    .sp-stage-chip-label {
        display: block;
        cursor: pointer;
        margin: 0;
    }
    .sp-stage-chip-label input {
        display: none;
    }
    .sp-stage-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: #FFFFFF;
        border: 1.5px solid #DDE4ED;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .sp-stage-chip-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #F8FAFC;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .sp-stage-chip-name {
        font-size: 0.82rem;
        font-weight: 700;
        color: #334155;
        flex: 1;
    }
    .sp-stage-chip-check {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #F1F5F9;
        color: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .sp-stage-chip-label:hover .sp-stage-chip {
        border-color: #CBD5E1;
        background: #F8FAFC;
    }
    .sp-stage-chip-label input:checked + .sp-stage-chip {
        border-color: #1E5FA8;
        background: #EFF6FF;
        box-shadow: 0 4px 12px rgba(30, 95, 168, 0.08);
    }
    .sp-stage-chip-label input:checked + .sp-stage-chip .sp-stage-chip-icon {
        background: #1E5FA8;
        color: #FFFFFF;
    }
    .sp-stage-chip-label input:checked + .sp-stage-chip .sp-stage-chip-name {
        color: #1E5FA8;
    }
    .sp-stage-chip-label input:checked + .sp-stage-chip .sp-stage-chip-check {
        background: #1E5FA8;
        color: #FFFFFF;
    }

    /* Executive Preview Card (Step 3) */
    .sp-executive-preview {
        background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
        border-radius: 14px;
        padding: 22px 24px;
        color: #FFFFFF;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .sp-executive-preview::after {
        content: '';
        position: absolute;
        top: -30px;
        left: -30px;
        width: 130px;
        height: 130px;
        background: radial-gradient(circle, rgba(245, 160, 32, 0.28) 0%, transparent 70%);
        border-radius: 50%;
    }
    .sp-exec-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .sp-exec-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: #FFFFFF;
        margin: 0;
    }
    .sp-exec-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .sp-exec-badge {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(4px);
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.74rem;
        color: #F8FAFC;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sp-exec-badge i {
        color: #F5A020;
    }
    .sp-exec-edit-btn {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #FFFFFF;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sp-exec-edit-btn:hover {
        background: rgba(255, 255, 255, 0.28);
        color: #FFFFFF;
    }

    /* Navigation Action Buttons */
    .sp-wizard-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding-top: 24px;
        border-top: 1px solid #E2E8F0;
        margin-top: 24px;
    }
</style>

<div class="register-page py-5">
    <div class="container sp-register-wrap">
        
        <!-- Header Title Banner -->
        <div class="text-center mb-4">
            <img src="/assets/images/logo-icon.png" alt="دليل المدارس" class="mx-auto mb-2 d-block" style="width: 56px; height: 56px; object-fit: contain;">
            <h2 class="fw-bold text-dark mb-1" style="font-size: 1.35rem;">تسجيل مدرسة جديدة في الدليل</h2>
            <p class="text-muted" style="max-width: 580px; margin: auto; font-size: 0.8rem; line-height: 1.6;">
                أدخل بيانات مدرستك عبر 3 خطوات ميسرة، لإنشاء الملف التعريفي والوصول المباشر للوحة التحكم.
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

        <!-- Wizard Card -->
        <div class="sp-wizard-card">
            
            <!-- Stepper Header -->
            <div class="sp-wizard-header">
                <div class="sp-stepper-nav">
                    <div class="sp-step-tab active" id="stepIndicator1" onclick="jumpToWizardStep(1)">
                        <div class="step-badge">1</div>
                        <div class="step-info">
                            <span class="step-eyebrow">الخطوة الأولى</span>
                            <span class="step-title">هوية المدرسة والموقع</span>
                        </div>
                    </div>
                    <div class="sp-step-tab" id="stepIndicator2" onclick="jumpToWizardStep(2)">
                        <div class="step-badge">2</div>
                        <div class="step-info">
                            <span class="step-eyebrow">الخطوة الثانية</span>
                            <span class="step-title">الاتصال والتواجد الرقمي</span>
                        </div>
                    </div>
                    <div class="sp-step-tab" id="stepIndicator3" onclick="jumpToWizardStep(3)">
                        <div class="step-badge">3</div>
                        <div class="step-info">
                            <span class="step-eyebrow">الخطوة الثالثة</span>
                            <span class="step-title">حساب الإدارة والأمان</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="sp-progress-track">
                <div class="sp-progress-bar" id="wizardProgressBar" style="width: 33.33%;"></div>
            </div>

            <!-- Wizard Form -->
            <form method="POST" action="register.php" id="schoolRegisterForm" novalidate>
                <?= csrf_field() ?>

                <div class="sp-wizard-body">

                    <!-- ==============================================
                         STEP 1: IDENTITY & BASIC INFO
                         ============================================== -->
                    <div class="wizard-step-pane active" id="wizardStep1">
                        
                        <!-- 1. School Identity Section -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-school"></i></div>
                                <div>
                                    <h6 class="sp-section-title">اسم وهوية المدرسة</h6>
                                    <p class="sp-section-desc">الاسم الرسمي المعتمد والرابط العام المخصص في الدليل</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <div class="sp-field">
                                        <label class="sp-label">اسم المدرسة باللغة العربية <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-building sp-input-icon"></i>
                                            <input type="text" name="name" class="sp-control" value="<?= e($formData['name'] ?? '') ?>" placeholder="مثال: مدارس المنار النموذجية الخاصة">
                                        </div>
                                        <div class="sp-feedback-error" id="err_name">يرجى إدخال اسم المدرسة بشكل صحيح (3 أحرف على الأقل).</div>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <div class="sp-field">
                                        <label class="sp-label">اسم المدرسة بالإنجليزية (اختياري)</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-globe sp-input-icon"></i>
                                            <input type="text" name="name_en" id="regNameEn" dir="ltr" class="sp-control" value="<?= e($formData['name_en'] ?? '') ?>" placeholder="e.g. Al-Manar Model Schools">
                                        </div>
                                        <div class="sp-slug-preview-box">
                                            <i class="fas fa-link text-primary"></i>
                                            <span>الرابط المخصص:</span>
                                            <strong id="regSlugPreview" class="text-primary dir-ltr">daleel.schoplus.sd/school.php?slug=...</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Geographic Location Section -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <div>
                                    <h6 class="sp-section-title">الموقع والتوزيع الجغرافي</h6>
                                    <p class="sp-section-desc">مكان تواجد المدرسة لتسهيل عثور أولياء الأمور عليها</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">الدولة <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-flag sp-input-icon"></i>
                                            <select name="country" id="regCountry" class="sp-control">
                                                <?php foreach ($countriesList as $cntry): ?>
                                                    <option value="<?= e($cntry) ?>" <?= $selectedCountry === $cntry ? 'selected' : '' ?>>
                                                        <?= e($cntry) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="sp-feedback-error" id="err_country">يرجى تحديد الدولة.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">الولاية / المنطقة <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-map sp-input-icon"></i>
                                            <select name="state" id="regState" class="sp-control">
                                                <option value="">-- اختر الولاية --</option>
                                                <?php foreach ($statesWithCities as $stName => $cities): ?>
                                                    <option value="<?= e($stName) ?>" <?= ($formData['state'] ?? '') === $stName ? 'selected' : '' ?>>
                                                        <?= e($stName) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="sp-feedback-error" id="err_state">يرجى اختيار الولاية.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">المدينة / المحلية <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-city sp-input-icon"></i>
                                            <select name="city" id="regCity" class="sp-control">
                                                <option value="">-- اختر المدينة --</option>
                                            </select>
                                        </div>
                                        <div class="sp-feedback-error" id="err_city">يرجى تحديد المدينة أو المحلية.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">الحي / المنطقة أو الشارع</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-map-pin sp-input-icon"></i>
                                            <input type="text" name="district" class="sp-control" value="<?= e($formData['district'] ?? '') ?>" placeholder="مثال: الرياض، المعمورة، الثورة، شارع الستين">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Classification & Curriculum Section -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-book-open"></i></div>
                                <div>
                                    <h6 class="sp-section-title">التصنيف الأكاديمي وسنة التأسيس</h6>
                                    <p class="sp-section-desc">نوع المؤسسة والمنهج الدراسي المعتمد وتصنيف الطلاب</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">نوع المدرسة</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-layer-group sp-input-icon"></i>
                                            <select name="school_type" class="sp-control">
                                                <?php foreach (SudanGeography::schoolTypes() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= ($formData['school_type'] ?? 'private') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">المنهج الدراسي</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-scroll sp-input-icon"></i>
                                            <select name="curriculum" class="sp-control">
                                                <?php foreach (SudanGeography::curricula() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= ($formData['curriculum'] ?? 'sudanese') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">نوع الطلاب</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-users sp-input-icon"></i>
                                            <select name="gender_type" class="sp-control">
                                                <?php foreach (SudanGeography::genderTypes() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= ($formData['gender_type'] ?? 'mixed') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">سنة التأسيس</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-calendar-alt sp-input-icon"></i>
                                            <input type="text" name="established_year" class="sp-control" value="<?= e($formData['established_year'] ?? '') ?>" placeholder="مثال: 2012 أو عام 1998">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Educational Stages Section -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-graduation-cap"></i></div>
                                <div>
                                    <h6 class="sp-section-title">المراحل التعليمية المتاحة</h6>
                                    <p class="sp-section-desc">حدد المراحل المتوفرة بالمدرسة بالنقر المباشر عليها</p>
                                </div>
                            </div>

                            <div class="sp-stage-grid">
                                <?php 
                                $selectedStages = $formData['stages'] ?? ['primary', 'intermediate', 'secondary'];
                                $stageIcons = [
                                    'kindergarten' => 'fa-shapes',
                                    'primary' => 'fa-book-reader',
                                    'intermediate' => 'fa-graduation-cap',
                                    'secondary' => 'fa-user-graduate',
                                ];
                                foreach (SudanGeography::stages() as $stgKey => $stgLabel): 
                                    $icon = $stageIcons[$stgKey] ?? 'fa-school';
                                ?>
                                    <label class="sp-stage-chip-label">
                                        <input type="checkbox" name="stages[]" value="<?= $stgKey ?>" id="stg_<?= $stgKey ?>" <?= in_array($stgKey, $selectedStages) ? 'checked' : '' ?>>
                                        <div class="sp-stage-chip">
                                            <div class="sp-stage-chip-icon"><i class="fas <?= $icon ?>"></i></div>
                                            <span class="sp-stage-chip-name"><?= $stgLabel ?></span>
                                            <span class="sp-stage-chip-check"><i class="fas fa-check"></i></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Step 1 Actions -->
                        <div class="sp-wizard-actions">
                            <div class="text-muted small">
                                <i class="fas fa-shield-alt text-primary me-1"></i> الخطوة 1 من 3
                            </div>
                            <button type="button" class="btn-luxury-primary px-4 py-2.5 fs-6" onclick="nextWizardStep(1)">
                                <span>متابعة: الاتصال والتواجد</span>
                                <i class="fas fa-arrow-left ms-2"></i>
                            </button>
                        </div>

                    </div>

                    <!-- ==============================================
                         STEP 2: CONTACT & DIGITAL PRESENCE
                         ============================================== -->
                    <div class="wizard-step-pane" id="wizardStep2">
                        
                        <!-- 1. Direct Contact Phone & WhatsApp -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-headset"></i></div>
                                <div>
                                    <h6 class="sp-section-title">أرقام التواصل المباشر</h6>
                                    <p class="sp-section-desc">أرقام التواصل الرسمية التي تظهر لأولياء الأمور في صفحة مدرستك</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">رقم هاتف المدرسة الرسمي <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-phone-alt text-primary sp-input-icon"></i>
                                            <input type="tel" name="phone" class="sp-control" value="<?= e($formData['phone'] ?? '') ?>" placeholder="09xxxxxxx أو 01xxxxxxx">
                                        </div>
                                        <div class="sp-feedback-error" id="err_phone">يرجى إدخال هاتف المدرسة للتواصل مع أولياء الأمور.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">رقم واتساب المدرسة (للاستفسارات الفورية)</label>
                                        <div class="sp-input-wrap">
                                            <i class="fab fa-whatsapp text-success sp-input-icon"></i>
                                            <input type="tel" name="whatsapp" class="sp-control" value="<?= e($formData['whatsapp'] ?? '') ?>" placeholder="09xxxxxxx">
                                        </div>
                                        <small class="text-muted" style="font-size: 0.72rem;">يظهر كزر محادثة واتساب مباشر وسريع في بطاقة المدرسة.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Digital Platforms & Social Media -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-satellite-dish"></i></div>
                                <div>
                                    <h6 class="sp-section-title">المنصات الرقمية وموقع الخريطة</h6>
                                    <p class="sp-section-desc">روابط المواقع وشبكات التواصل وخريطة الوصول للمدرسة (اختيارية)</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">الموقع الإلكتروني الرسمي</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-globe text-primary sp-input-icon"></i>
                                            <input type="url" name="website" class="sp-control" value="<?= e($formData['website'] ?? '') ?>" placeholder="https://school-name.edu.sd">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">رابط صفحة الفيسبوك</label>
                                        <div class="sp-input-wrap">
                                            <i class="fab fa-facebook text-primary sp-input-icon"></i>
                                            <input type="url" name="facebook" class="sp-control" value="<?= e($formData['facebook'] ?? '') ?>" placeholder="https://facebook.com/SchoolName">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">حساب تيك توك</label>
                                        <div class="sp-input-wrap">
                                            <i class="fab fa-tiktok text-dark sp-input-icon"></i>
                                            <input type="text" name="tiktok" class="sp-control" value="<?= e($formData['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@school">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">انستغرام أو يوتيوب</label>
                                        <div class="sp-input-wrap">
                                            <i class="fab fa-instagram text-danger sp-input-icon"></i>
                                            <input type="text" name="instagram" class="sp-control" value="<?= e($formData['instagram'] ?? ($formData['youtube'] ?? '')) ?>" placeholder="رابط حساب انستغرام أو يوتيوب">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="sp-field">
                                        <label class="sp-label">رابط موقع المدرسة على خرائط جوجل (Google Maps)</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-map-location-dot text-warning sp-input-icon"></i>
                                            <input type="url" name="google_map_url" class="sp-control" value="<?= e($formData['google_map_url'] ?? '') ?>" placeholder="https://maps.google.com/?q=...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 Actions -->
                        <div class="sp-wizard-actions">
                            <button type="button" class="btn-luxury-outline px-4 py-2.5 fs-6" onclick="prevWizardStep(2)">
                                <i class="fas fa-arrow-right me-2"></i>
                                <span>السابق: هوية المدرسة</span>
                            </button>
                            <button type="button" class="btn-luxury-primary px-4 py-2.5 fs-6" onclick="nextWizardStep(2)">
                                <span>متابعة: حساب الإدارة</span>
                                <i class="fas fa-arrow-left ms-2"></i>
                            </button>
                        </div>

                    </div>

                    <!-- ==============================================
                         STEP 3: OWNER CREDENTIALS & CONFIRMATION
                         ============================================== -->
                    <div class="wizard-step-pane" id="wizardStep3">
                        
                        <!-- 1. Executive Summary Preview Card -->
                        <div class="sp-executive-preview">
                            <div class="sp-exec-head">
                                <div>
                                    <span class="d-block text-white-50 small mb-1">ملخص بيانات المدرسة</span>
                                    <h4 class="sp-exec-title" id="summarySchoolName">--</h4>
                                </div>
                                <button type="button" class="sp-exec-edit-btn" onclick="jumpToWizardStep(1)">
                                    <i class="fas fa-edit"></i>
                                    <span>تعديل البيانات</span>
                                </button>
                            </div>
                            <div class="sp-exec-badges">
                                <div class="sp-exec-badge">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span id="summaryLocation">--</span>
                                </div>
                                <div class="sp-exec-badge">
                                    <i class="fas fa-phone-alt"></i>
                                    <span id="summaryPhone" dir="ltr">--</span>
                                </div>
                                <div class="sp-exec-badge">
                                    <i class="fas fa-user-shield"></i>
                                    <span id="summaryAdminName">--</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Account Administrator Profile -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-user-tie"></i></div>
                                <div>
                                    <h6 class="sp-section-title">بيانات المسؤول ومدير الحساب</h6>
                                    <p class="sp-section-desc">المسؤول المخول بإدارة المدرسة والرد على طلبات أولياء الأمور</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">اسم المسؤول / مدير المدرسة <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-user-check sp-input-icon"></i>
                                            <input type="text" name="owner_name" class="sp-control" value="<?= e($formData['owner_name'] ?? '') ?>" placeholder="مثال: أ. عبد الله إبراهيم">
                                        </div>
                                        <div class="sp-feedback-error" id="err_owner_name">يرجى إدخال اسم المسؤول بشكل صحيح.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">الصفة أو المسمى الوظيفي</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-id-badge sp-input-icon"></i>
                                            <input type="text" name="owner_title" class="sp-control" value="<?= e($formData['owner_title'] ?? 'مدير المدرسة') ?>" placeholder="مثال: المدير العام / مدير المرحلة">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Security & Login Credentials -->
                        <div class="sp-form-section">
                            <div class="sp-form-section-head">
                                <div class="sp-section-icon"><i class="fas fa-shield-alt"></i></div>
                                <div>
                                    <h6 class="sp-section-title">بيانات تسجيل الدخول والأمان</h6>
                                    <p class="sp-section-desc">رقم هاتف الدخول وكلمة المرور الخاصة بلوحة التحكم</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">رقم هاتف المسؤول (اسم المستخدم للدخول) <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-mobile-screen sp-input-icon"></i>
                                            <input type="tel" name="owner_phone" class="sp-control" value="<?= e($formData['owner_phone'] ?? '') ?>" placeholder="09xxxxxxx">
                                        </div>
                                        <div class="sp-feedback-error" id="err_owner_phone">يرجى إدخال رقم هاتف المسؤول بشكل صحيح (9 أرقام على الأقل).</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">البريد الإلكتروني للمسؤول (اختياري)</label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-envelope sp-input-icon"></i>
                                            <input type="email" name="owner_email" class="sp-control" value="<?= e($formData['owner_email'] ?? '') ?>" placeholder="admin@school.com">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">كلمة المرور الجديدة <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-lock sp-input-icon"></i>
                                            <input type="password" name="password" id="regPassword" class="sp-control" placeholder="6 خانات على الأقل">
                                            <button type="button" class="sp-pwd-toggle" onclick="togglePasswordVisibility('regPassword', this)" title="إظهار / إخفاء">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="sp-feedback-error" id="err_password">كلمة المرور يجب أن لا تقل عن 6 أحرف أو أرقام.</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sp-field">
                                        <label class="sp-label">تأكيد كلمة المرور <span class="req">*</span></label>
                                        <div class="sp-input-wrap">
                                            <i class="fas fa-lock-open sp-input-icon"></i>
                                            <input type="password" name="password_confirmation" id="regPasswordConf" class="sp-control" placeholder="أعد إدخال كلمة المرور">
                                            <button type="button" class="sp-pwd-toggle" onclick="togglePasswordVisibility('regPasswordConf', this)" title="إظهار / إخفاء">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="sp-feedback-error" id="err_password_confirmation">كلمتا المرور غير متطابقتين.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info & Policy Note -->
                        <div class="p-3 rounded-3 bg-light border border-light-subtle my-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-info-circle text-primary mt-1"></i>
                                <small class="text-secondary" style="font-size: 0.73rem; line-height: 1.65;">
                                    <strong>سياسة الاستخدام:</strong> هذا الدليل خدمة رقمية متطورة مقدمة من <strong>منظومة SchoPlus الذكية</strong>. البيانات المدخلة تقع تحت إدارة ومسؤولية المدرسة، ويمكنكم تحديث الصور والتفاصيل وتفعيل مزايا النظام الذكي في أي وقت.
                                </small>
                            </div>
                        </div>

                        <!-- Step 3 Actions -->
                        <div class="sp-wizard-actions">
                            <button type="button" class="btn-luxury-outline px-4 py-2.5 fs-6" onclick="prevWizardStep(3)">
                                <i class="fas fa-arrow-right me-2"></i>
                                <span>السابق: قنوات الاتصال</span>
                            </button>
                            <button type="submit" class="btn-luxury-primary px-5 py-3 fs-6 shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>
                                <span>تأكيد تسجيل المدرسة وإنشاء الحساب</span>
                            </button>
                        </div>

                    </div>

                </div>

            </form>

            <div class="text-center py-3 bg-light border-top">
                <span class="text-muted small">لديك حساب مسجل بالفعل؟</span>
                <a href="login.php" class="text-primary fw-bold small text-decoration-none ms-1">تسجيل الدخول من هنا</a>
            </div>

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

    // Password visibility toggle
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
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
            if (step === 1) progress.style.width = '33.33%';
            else if (step === 2) progress.style.width = '66.66%';
            else if (step === 3) progress.style.width = '100%';
        }

        if (step === 3) {
            updateSummaryCard();
        }

        // Smooth scroll to card top if needed
        const wizardCard = document.querySelector('.sp-wizard-card');
        if (wizardCard) {
            const rect = wizardCard.getBoundingClientRect();
            if (rect.top < 40) {
                window.scrollTo({
                    top: window.pageYOffset + rect.top - 60,
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

            const pass = document.getElementById('regPassword');
            if (!pass || pass.value.length < 6) {
                markInvalid(pass);
                isValid = false;
                if (!firstInvalid) firstInvalid = pass;
            }

            const passConf = document.getElementById('regPasswordConf');
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
        const name = document.querySelector('input[name="name"]')?.value.trim() || 'اسم المدرسة غير محدد';
        const country = document.getElementById('regCountry')?.value.trim() || '';
        const state = document.getElementById('regState')?.value.trim() || '';
        const city = document.getElementById('regCity')?.value.trim() || '';
        const location = [city, state, country].filter(Boolean).join(' - ') || 'الموقع غير محدد';
        const phone = document.querySelector('input[name="phone"]')?.value.trim() || 'هاتف المدرسة غير محدد';
        const ownerName = document.querySelector('input[name="owner_name"]')?.value.trim() || 'اسم المسؤول غير محدد';
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
