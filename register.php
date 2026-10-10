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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'انتهت صلاحية الجلسة، يرجى إعادة إرسال النموذج.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $school_type = trim($_POST['school_type'] ?? 'private');
        $gender_type = trim($_POST['gender_type'] ?? 'mixed');
        $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
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
                    name, name_en, slug, school_type, gender_type, curriculum, stages,
                    state, city, district, phone, whatsapp, email, facilities,
                    status, owner_name, owner_title, owner_phone, owner_email, password,
                    rating_avg, reviews_count, views_count, is_schoplus_client, is_verified, created_at, updated_at
                ) VALUES (
                    :name, :name_en, :slug, :school_type, :gender_type, :curriculum, :stages,
                    :state, :city, :district, :phone, :whatsapp, :email, :facilities,
                    'pending', :owner_name, :owner_title, :owner_phone, :owner_email, :password,
                    5.00, 0, 1, 0, 0, NOW(), NOW()
                )");

                $insStmt->execute([
                    ':name' => $name,
                    ':name_en' => $name_en ?: null,
                    ':slug' => $slug,
                    ':school_type' => $school_type,
                    ':gender_type' => $gender_type,
                    ':curriculum' => $curriculum,
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
$statesWithCities = SudanGeography::statesWithCities();
?>

<div class="register-page py-5">
    <div class="container" style="max-width: 900px;">
        
        <!-- Header Banner -->
        <div class="text-center mb-5">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-2 shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> انضمام مجاني وسريع
            </span>
            <h2 class="fw-bold text-dark mb-2">سجّل مدرستك في دليل المدارس الذكي</h2>
            <p class="text-muted" style="max-width: 650px; margin: auto;">
                عرّف بمدرستك لأولياء الأمور والطلاب في كافة مدن السودان. يمكنك إدخال البيانات الأساسية الآن، وإكمال التفاصيل والصور والأخبار والمرافق لاحقاً عبر لوحة التحكم في أي وقت.
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
                            <label class="form-label small fw-bold text-secondary">اسم المدرسة الرسمي باللغة العربية <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control" value="<?= e($formData['name'] ?? '') ?>" placeholder="مثال: مدارس المنار النموذجية الخاصة">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary">اسم المدرسة باللغة الإنجليزية (اختياري)</label>
                            <input type="text" name="name_en" dir="ltr" class="form-control" value="<?= e($formData['name_en'] ?? '') ?>" placeholder="e.g. Al-Manar Model Schools">
                        </div>

                        <!-- State & City -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">الولاية <span class="text-danger">*</span></label>
                            <select name="state" id="regState" required class="form-select">
                                <option value="">-- اختر الولاية --</option>
                                <?php foreach ($statesWithCities as $stName => $cities): ?>
                                    <option value="<?= e($stName) ?>" <?= ($formData['state'] ?? '') === $stName ? 'selected' : '' ?>>
                                        <?= e($stName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">المدينة / المحلية <span class="text-danger">*</span></label>
                            <select name="city" id="regCity" required class="form-select">
                                <option value="">-- اختر المدينة --</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">الحي / المنطقة</label>
                            <input type="text" name="district" class="form-control" value="<?= e($formData['district'] ?? '') ?>" placeholder="مثال: الرياض، المعمورة، الثورة">
                        </div>

                        <!-- Type & Curriculum -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">نوع المدرسة</label>
                            <select name="school_type" class="form-select">
                                <?php foreach (SudanGeography::schoolTypes() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['school_type'] ?? 'private') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">نوع الطلاب</label>
                            <select name="gender_type" class="form-select">
                                <?php foreach (SudanGeography::genderTypes() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['gender_type'] ?? 'mixed') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">المنهج الدراسي</label>
                            <select name="curriculum" class="form-select">
                                <?php foreach (SudanGeography::curricula() as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= ($formData['curriculum'] ?? 'sudanese') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
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

                <!-- Info Box -->
                <div class="p-3 rounded-4 bg-light border border-light-subtle mb-4">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-info-circle text-primary mt-1"></i>
                        <small class="text-secondary lh-base">
                            بمجرد الضغط على زر التسجيل، سيتم إنشاء حسابكم مباشرة والانتقال للوحة التحكم. ستكون حالة مدرستكم <strong>"بانتظار التأكيد"</strong> بينما يقوم فريقنا بمراجعتها والتأكد من صحة البيانات، وخلال هذا الوقت يمكنك رفع شعار المدرسة، صور المرافق، وتحديد الرسوم والأخبار.
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
    const geoData = <?= json_encode($statesWithCities, JSON_UNESCAPED_UNICODE) ?>;
    const stateSelect = document.getElementById('regState');
    const citySelect = document.getElementById('regCity');
    const selectedCity = <?= json_encode($formData['city'] ?? '') ?>;

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
