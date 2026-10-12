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
                    state, city, district, phone, whatsapp, email, facilities,
                    status, owner_name, owner_title, owner_phone, owner_email, password,
                    rating_avg, reviews_count, views_count, is_schoplus_client, is_verified, created_at, updated_at
                ) VALUES (
                    :name, :name_en, :slug, :country, :school_type, :gender_type, :curriculum, :established_year, :stages,
                    :state, :city, :district, :phone, :whatsapp, :email, :facilities,
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

$pageTitle = 'تسجيل مدرسة جديدة';
require_once __DIR__ . '/header.php';
$countriesWithStates = SudanGeography::countriesWithStatesAndCities();
$countriesList = SudanGeography::countries();
$selectedCountry = $formData['country'] ?? 'السودان';
$statesWithCities = SudanGeography::statesWithCities($selectedCountry);
?>

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
                عرّف بمدرستك لأولياء الأمور والطلاب. يمكنك إدخال البيانات الأساسية الآن، وإكمال التفاصيل والصور والأخبار والمرافق لاحقاً عبر لوحة التحكم في أي وقت.
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

        <!-- Form Card -->
        <div class="card border-0 rounded-4 shadow-sm bg-white p-4 p-md-5">
            <form method="POST" action="register.php" id="schoolRegisterForm">
                <?= csrf_field() ?>

                <!-- SECTION 1: SCHOOL INFO -->
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            1
                        </div>
                        <h5 class="fw-bold text-dark mb-0">بيانات المدرسة الأساسية</h5>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-secondary">اسم المدرسة باللغة العربية <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control" value="<?= e($formData['name'] ?? '') ?>" placeholder="مثال: مدارس المنار النموذجية الخاصة">
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
                            <select name="country" id="regCountry" required class="form-select">
                                <?php foreach ($countriesList as $cntry): ?>
                                    <option value="<?= e($cntry) ?>" <?= $selectedCountry === $cntry ? 'selected' : '' ?>>
                                        <?= e($cntry) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">الولاية / المنطقة <span class="text-danger">*</span></label>
                            <select name="state" id="regState" required class="form-select">
                                <option value="">-- اختر الولاية --</option>
                                <?php foreach ($statesWithCities as $stName => $cities): ?>
                                    <option value="<?= e($stName) ?>" <?= ($formData['state'] ?? '') === $stName ? 'selected' : '' ?>>
                                        <?= e($stName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">المدينة / المحلية <span class="text-danger">*</span></label>
                            <select name="city" id="regCity" required class="form-select">
                                <option value="">-- اختر المدينة --</option>
                            </select>
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

                        <!-- School Phone & WhatsApp -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم هاتف المدرسة للتواصل مع أولياء الأمور <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" required class="form-control" value="<?= e($formData['phone'] ?? '') ?>" placeholder="09xxxxxxx أو 01xxxxxxx">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم واتساب المدرسة (للاستفسارات المباشرة)</label>
                            <input type="tel" name="whatsapp" class="form-control" value="<?= e($formData['whatsapp'] ?? '') ?>" placeholder="09xxxxxxx">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: OWNER LOGIN CREDENTIALS -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            2
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">بيانات المسؤول وحساب الدخول</h5>
                            <small class="text-muted">ستستخدم هذه البيانات لتسجيل الدخول للوحة إدارة المدرسة وتحديث البيانات لاحقاً</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">اسم المسؤول / المدير <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" required class="form-control" value="<?= e($formData['owner_name'] ?? '') ?>" placeholder="مثال: أ. عبد الله إبراهيم">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">الصفة أو المسمى الوظيفي</label>
                            <input type="text" name="owner_title" class="form-control" value="<?= e($formData['owner_title'] ?? 'مدير المدرسة') ?>" placeholder="مثال: المدير العام / مدير المرحلة">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">رقم هاتف المسؤول (اسم المستخدم للدخول) <span class="text-danger">*</span></label>
                            <input type="tel" name="owner_phone" required class="form-control" value="<?= e($formData['owner_phone'] ?? '') ?>" placeholder="09xxxxxxx">
                            <small class="text-muted" style="font-size: 0.72rem;">سيتم استخدامه كاسم مستخدم عند تسجيل الدخول.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">البريد الإلكتروني للمسؤول (اختياري)</label>
                            <input type="email" name="owner_email" class="form-control" value="<?= e($formData['owner_email'] ?? '') ?>" placeholder="admin@school.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                            <input type="password" name="password" required class="form-control" placeholder="6 خانات على الأقل">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" required class="form-control" placeholder="أعد إدخال كلمة المرور">
                        </div>
                    </div>
                </div>

                <!-- Info & Usage Policy Box -->
                <div class="p-3 rounded-4 bg-light border border-light-subtle mb-4">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-info-circle text-primary mt-1"></i>
                        <small class="text-secondary lh-base">
                            <strong>سياسة الاستخدام والتسجيل:</strong> هذا الدليل هو خدمة رقمية مقدمة من منظومة <strong>ايسكو بلص (SchoPlus)</strong>، وليس دليلاً رسمياً تابعاً لوزارة التربية والتعليم. جميع البيانات والصور والرسوم المنشورة تتم إضافتها وإدارتها من قِبل إدارة المدرسة وعلى مسؤوليتها. بمجرد التسجيل يمكنك الدخول مباشرة للوحة التحكم وإكمال ملف مدرستك.
                        </small>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center pt-2">
                    <button type="submit" class="btn-luxury-primary px-5 py-3 fs-6 w-100 w-md-auto">
                        <i class="fas fa-check-circle me-2"></i> تأكيد التسجيل وإنشاء الحساب
                    </button>
                    <div class="mt-3">
                        <span class="text-muted small">لديك حساب مسجل بالفعل؟</span>
                        <a href="login.php" class="text-primary fw-bold small text-decoration-none">تسجيل الدخول من هنا</a>
                    </div>
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
</script>

<?php
require_once __DIR__ . '/footer.php';
