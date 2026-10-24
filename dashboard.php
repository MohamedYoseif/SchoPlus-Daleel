<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Owner Dashboard (لوحة إدارة المدرسة ومتابعة الاعتماد والمحتوى)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

// Allow Master Admin to manage any school directly
if (!empty($_SESSION['daleel_master_admin']) && isset($_GET['admin_manage_school'])) {
    $targetId = (int)$_GET['admin_manage_school'];
    if ($targetId > 0) {
        $_SESSION['daleel_school_id'] = $targetId;
        $_SESSION['daleel_admin_impersonating'] = true;
        header('Location: dashboard.php?from_admin=1');
        exit;
    }
}

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
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return null;
    }
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/jpg',
        'image/svg+xml', 'image/gif', 'image/bmp', 'image/avif',
        'image/x-icon', 'image/vnd.microsoft.icon'
    ];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'bmp', 'avif', 'jfif'];

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'jpg');
    if ($ext === 'jfif') $ext = 'jpg';

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)@finfo_file($finfo, $file['tmp_name']);
            @finfo_close($finfo);
        }
    }
    if (!$mime && function_exists('getimagesize')) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!empty($imgInfo['mime'])) {
            $mime = $imgInfo['mime'];
        }
    }

    $isValidMime = in_array($mime, $allowedMimes, true) || str_starts_with($mime, 'image/');
    $isValidExt = in_array($ext, $allowedExts, true);

    if (!$isValidMime && !$isValidExt) {
        return null;
    }
    if (!in_array($ext, $allowedExts, true)) {
        $ext = 'jpg';
    }

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $cleanSubfolder = trim($subfolder, '/');
    $targetDir = __DIR__ . '/uploads/' . $cleanSubfolder . '/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . $filename;

    if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0644);
        return '/uploads/' . $cleanSubfolder . '/' . $filename;
    }
    return null;
}

// ----------------------------------------------------
// Handle Form Submissions
// ----------------------------------------------------
$activeDashTab = $_GET['tab'] ?? 'profile';
$activeSubTab = $_GET['subtab'] ?? 'basic';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        $errorMsg = 'انتهت صلاحية الجلسة، يرجى المحاولة ثانية.';
    } else {
        $action = $_POST['action'] ?? '';
        if (!empty($_POST['subtab'])) {
            $activeSubTab = trim($_POST['subtab']);
        }

        // 1. UPDATE PROFILE & DETAILS (FULL LEGACY FALLBACK)
        if ($action === 'update_profile') {
            $activeDashTab = 'profile';
            $name = trim($_POST['name'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $rawSlug = trim($_POST['slug'] ?? '');
            $country = trim($_POST['country'] ?? '') ?: ($school['country'] ?? 'السودان');
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
            $twitter = trim($_POST['twitter'] ?? '');
            $youtube = trim($_POST['youtube'] ?? '');
            $telegram = trim($_POST['telegram'] ?? '');
            $instagram = trim($_POST['instagram'] ?? '');
            $tiktok = trim($_POST['tiktok'] ?? '');
            $school_type = trim($_POST['school_type'] ?? 'private');
            $gender_type = trim($_POST['gender_type'] ?? 'mixed');
            $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
            $established_year = trim($_POST['established_year'] ?? '');
            $approx_fees = trim($_POST['approx_fees'] ?? '');
            $fees_type = in_array(trim($_POST['fees_type'] ?? ''), ['average', 'detailed'], true) ? trim($_POST['fees_type']) : ($school['fees_type'] ?? 'average');
            $working_hours = trim($_POST['working_hours'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $vision = trim($_POST['vision'] ?? '');
            $mission = trim($_POST['mission'] ?? '');

            $stages = isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : [];
            $facilities = isset($_POST['facilities']) && is_array($_POST['facilities']) ? $_POST['facilities'] : [];

            // Parse detailed stage fees if submitted
            $stageFeesJson = $school['stage_fees'] ?? null;
            if (isset($_POST['stage_fee_stage']) && is_array($_POST['stage_fee_stage'])) {
                $stageFees = [];
                $stagesArr = $_POST['stage_fee_stage'] ?? [];
                $titlesArr = $_POST['stage_fee_title'] ?? [];
                $amountsArr = $_POST['stage_fee_amount'] ?? [];
                $notesArr = $_POST['stage_fee_notes'] ?? [];
                foreach ($stagesArr as $i => $stg) {
                    $stgVal = trim($stg ?? '');
                    $titleVal = trim($titlesArr[$i] ?? '');
                    $amtVal = trim($amountsArr[$i] ?? '');
                    $notesVal = trim($notesArr[$i] ?? '');
                    if ($stgVal !== '' || $titleVal !== '' || $amtVal !== '') {
                        $stageFees[] = [
                            'stage'  => $stgVal,
                            'title'  => $titleVal,
                            'amount' => $amtVal,
                            'notes'  => $notesVal,
                        ];
                    }
                }
                $stageFeesJson = !empty($stageFees) ? json_encode($stageFees, JSON_UNESCAPED_UNICODE) : null;
            }

            // Parse discounts & offers if submitted
            $feeDiscountsJson = $school['fee_discounts'] ?? null;
            if (isset($_POST['discount_title']) && is_array($_POST['discount_title'])) {
                $discounts = [];
                $discTitles = $_POST['discount_title'] ?? [];
                $discValues = $_POST['discount_value'] ?? [];
                $discAppliesTo = $_POST['discount_applies_to'] ?? [];
                $discNotes = $_POST['discount_notes'] ?? [];
                foreach ($discTitles as $i => $dTitle) {
                    $dTitleVal = trim($dTitle ?? '');
                    $dValVal = trim($discValues[$i] ?? '');
                    $dAppliesVal = trim($discAppliesTo[$i] ?? '');
                    $dNotesVal = trim($discNotes[$i] ?? '');
                    if ($dTitleVal !== '' || $dValVal !== '') {
                        $discounts[] = [
                            'title'      => $dTitleVal,
                            'value'      => $dValVal,
                            'applies_to' => $dAppliesVal,
                            'notes'      => $dNotesVal,
                        ];
                    }
                }
                $feeDiscountsJson = !empty($discounts) ? json_encode($discounts, JSON_UNESCAPED_UNICODE) : null;
            }

            // Validate & sanitize custom slug
            $newSlug = $school['slug'];
            if ($rawSlug !== '' && $rawSlug !== $school['slug']) {
                $cleanSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($rawSlug));
                $cleanSlug = trim(preg_replace('/-+/', '-', $cleanSlug), '-');
                $reservedSlugs = ['index.php', 'index', 'home', 'schools', 'school', 's', 'admin', 'api', 'dashboard', 'login', 'register', 'logout'];
                if (strlen($cleanSlug) < 3) {
                    $errorMsg = 'يجب أن يتكون الرابط المخصص (Slug) من 3 أحرف إنجليزية أو أرقام على الأقل.';
                } elseif (in_array($cleanSlug, $reservedSlugs, true)) {
                    $errorMsg = 'هذا الرابط محجوز للنظام، يرجى اختيار رابط مخصص آخر.';
                } else {
                    $slugChk = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? AND id != ? LIMIT 1");
                    $slugChk->execute([$cleanSlug, $schoolId]);
                    if ($slugChk->fetch()) {
                        $errorMsg = 'الرابط المخصص (' . $cleanSlug . ') مستخدم بالفعل لمدرسة أخرى، يرجى اختيار رابط آخر.';
                    } else {
                        $newSlug = $cleanSlug;
                    }
                }
            }

            // Upload Logo
            $logoPath = $school['logo_path'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $upLogo = handleUpload($_FILES['logo'], 'logos');
                if ($upLogo) {
                    $logoPath = $upLogo;
                }
            } elseif (isset($_FILES['logo']['error']) && in_array($_FILES['logo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم صورة الشعار كبير جداً، يرجى اختيار صورة بحجم أقل.';
            }

            // Upload Cover
            $coverPath = $school['cover_path'];
            if (!empty($_FILES['cover']['tmp_name'])) {
                $upCover = handleUpload($_FILES['cover'], 'covers');
                if ($upCover) {
                    $coverPath = $upCover;
                }
            } elseif (isset($_FILES['cover']['error']) && in_array($_FILES['cover']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم صورة الغلاف كبير جداً، يرجى اختيار صورة بحجم أقل.';
            }

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    name = :name, name_en = :name_en, slug = :slug, country = :country, state = :state, city = :city, district = :district,
                    address = :address, google_map_url = :google_map_url, phone = :phone, whatsapp = :whatsapp,
                    email = :email, website = :website, facebook = :facebook,
                    twitter = :twitter, youtube = :youtube, telegram = :telegram, instagram = :instagram, tiktok = :tiktok,
                    school_type = :school_type, gender_type = :gender_type, curriculum = :curriculum, established_year = :established_year,
                    approx_fees = :approx_fees, fees_type = :fees_type, stage_fees = :stage_fees, fee_discounts = :fee_discounts,
                    working_hours = :working_hours, description = :description, vision = :vision, mission = :mission,
                    stages = :stages, facilities = :facilities, logo_path = :logo_path, cover_path = :cover_path,
                    updated_at = NOW()
                    WHERE id = :id");

                $upStmt->execute([
                    ':name' => $name,
                    ':name_en' => $name_en ?: null,
                    ':slug' => $newSlug,
                    ':country' => $country,
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
                    ':twitter' => $twitter ?: null,
                    ':youtube' => $youtube ?: null,
                    ':telegram' => $telegram ?: null,
                    ':instagram' => $instagram ?: null,
                    ':tiktok' => $tiktok ?: null,
                    ':school_type' => $school_type,
                    ':gender_type' => $gender_type,
                    ':curriculum' => $curriculum,
                    ':established_year' => $established_year ?: null,
                    ':approx_fees' => $approx_fees ?: null,
                    ':fees_type' => $fees_type,
                    ':stage_fees' => $stageFeesJson,
                    ':fee_discounts' => $feeDiscountsJson,
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

                if (!$errorMsg) {
                    $successMsg = 'تم حفظ وتحديث بيانات مدرستكم والروابط الرقمية بنجاح!';
                }
                // refresh
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
            }
        }

        // 1.1 SUB-TAB: BASIC INFO & CLASSIFICATION
        elseif ($action === 'update_profile_basic') {
            $activeDashTab = 'profile';
            $activeSubTab = 'basic';
            $name = trim($_POST['name'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $rawSlug = trim($_POST['slug'] ?? '');
            $school_type = trim($_POST['school_type'] ?? 'private');
            $gender_type = trim($_POST['gender_type'] ?? 'mixed');
            $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
            $established_year = trim($_POST['established_year'] ?? '');

            if (!$name) {
                $errorMsg = 'يرجى إدخال اسم المدرسة باللغة العربية.';
            } else {
                $newSlug = $school['slug'];
                if ($rawSlug !== '' && $rawSlug !== $school['slug']) {
                    $cleanSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($rawSlug));
                    $cleanSlug = trim(preg_replace('/-+/', '-', $cleanSlug), '-');
                    $reservedSlugs = ['index.php', 'index', 'home', 'schools', 'school', 's', 'admin', 'api', 'dashboard', 'login', 'register', 'logout'];
                    if (strlen($cleanSlug) < 3) {
                        $errorMsg = 'يجب أن يتكون الرابط المخصص (Slug) من 3 أحرف إنجليزية أو أرقام على الأقل.';
                    } elseif (in_array($cleanSlug, $reservedSlugs, true)) {
                        $errorMsg = 'هذا الرابط محجوز للنظام، يرجى اختيار رابط مخصص آخر.';
                    } else {
                        $slugChk = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? AND id != ? LIMIT 1");
                        $slugChk->execute([$cleanSlug, $schoolId]);
                        if ($slugChk->fetch()) {
                            $errorMsg = 'الرابط المخصص (' . $cleanSlug . ') مستخدم بالفعل لمدرسة أخرى، يرجى اختيار رابط آخر.';
                        } else {
                            $newSlug = $cleanSlug;
                        }
                    }
                }

                if (!$errorMsg) {
                    try {
                        $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                            name = :name, name_en = :name_en, slug = :slug,
                            school_type = :school_type, gender_type = :gender_type,
                            curriculum = :curriculum, established_year = :established_year,
                            updated_at = NOW()
                            WHERE id = :id");
                        $upStmt->execute([
                            ':name' => $name,
                            ':name_en' => $name_en ?: null,
                            ':slug' => $newSlug,
                            ':school_type' => $school_type,
                            ':gender_type' => $gender_type,
                            ':curriculum' => $curriculum,
                            ':established_year' => $established_year ?: null,
                            ':id' => $schoolId,
                        ]);
                        $successMsg = 'تم حفظ وتحديث بيانات الهوية والتصنيف بنجاح!';
                        $stmt->execute([$schoolId]);
                        $school = $stmt->fetch();
                    } catch (Exception $e) {
                        $errorMsg = 'حدث خطأ أثناء حفظ بيانات الهوية: ' . $e->getMessage();
                    }
                }
            }
        }

        // 1.2 SUB-TAB: LOCATION & ADDRESS
        elseif ($action === 'update_profile_location') {
            $activeDashTab = 'profile';
            $activeSubTab = 'location';
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $google_map_url = trim($_POST['google_map_url'] ?? '');

            if (!$state || !$city) {
                $errorMsg = 'يرجى تحديد الولاية والمدينة.';
            } else {
                try {
                    $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                        state = :state, city = :city, district = :district,
                        address = :address, google_map_url = :google_map_url,
                        updated_at = NOW()
                        WHERE id = :id");
                    $upStmt->execute([
                        ':state' => $state,
                        ':city' => $city,
                        ':district' => $district ?: null,
                        ':address' => $address ?: null,
                        ':google_map_url' => $google_map_url ?: null,
                        ':id' => $schoolId,
                    ]);
                    $successMsg = 'تم حفظ بيانات الموقع والعنوان بنجاح!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } catch (Exception $e) {
                    $errorMsg = 'حدث خطأ أثناء حفظ الموقع: ' . $e->getMessage();
                }
            }
        }

        // 1.3 SUB-TAB: ACADEMIC STAGES & FACILITIES
        elseif ($action === 'update_profile_academic') {
            $activeDashTab = 'profile';
            $activeSubTab = 'academic';
            $stages = isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : [];
            $facilities = isset($_POST['facilities']) && is_array($_POST['facilities']) ? $_POST['facilities'] : [];

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    stages = :stages, facilities = :facilities,
                    updated_at = NOW()
                    WHERE id = :id");
                $upStmt->execute([
                    ':stages' => json_encode($stages, JSON_UNESCAPED_UNICODE),
                    ':facilities' => json_encode($facilities, JSON_UNESCAPED_UNICODE),
                    ':id' => $schoolId,
                ]);
                $successMsg = 'تم حفظ المراحل التعليمية والمرافق المدرسية بنجاح!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ المراحل والمرافق: ' . $e->getMessage();
            }
        }

        // 1.4 SUB-TAB: CONTACT & WORKING HOURS (in Profile)
        elseif ($action === 'update_profile_contact' || ($action === 'update_profile_fees_contact' && !isset($_POST['fees_type']))) {
            $activeDashTab = 'profile';
            $activeSubTab = 'contact';
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $working_hours = trim($_POST['working_hours'] ?? '');

            if (!$phone) {
                $errorMsg = 'يرجى إدخال هاتف المدرسة للتواصل.';
            } else {
                try {
                    $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                        phone = :phone, whatsapp = :whatsapp, email = :email,
                        working_hours = :working_hours,
                        updated_at = NOW()
                        WHERE id = :id");
                    $upStmt->execute([
                        ':phone' => $phone,
                        ':whatsapp' => $whatsapp ?: null,
                        ':email' => $email ?: null,
                        ':working_hours' => $working_hours ?: null,
                        ':id' => $schoolId,
                    ]);
                    $successMsg = 'تم حفظ بيانات قنوات التواصل وأوقات الدوام بنجاح!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } catch (Exception $e) {
                    $errorMsg = 'حدث خطأ أثناء حفظ بيانات التواصل: ' . $e->getMessage();
                }
            }
        }

        // 1.5 DEDICATED MAIN TAB: TUITION FEES & DISCOUNTS
        elseif ($action === 'update_school_fees' || $action === 'update_profile_fees_contact') {
            $activeDashTab = 'fees';
            $fees_type = in_array(trim($_POST['fees_type'] ?? ''), ['average', 'detailed'], true) ? trim($_POST['fees_type']) : 'average';
            $approx_fees = trim($_POST['approx_fees'] ?? '');

            // Parse detailed stage fees
            $stageFees = [];
            $stagesArr = $_POST['stage_fee_stage'] ?? [];
            $titlesArr = $_POST['stage_fee_title'] ?? [];
            $amountsArr = $_POST['stage_fee_amount'] ?? [];
            $notesArr = $_POST['stage_fee_notes'] ?? [];

            if (is_array($stagesArr)) {
                foreach ($stagesArr as $i => $stg) {
                    $stgVal = trim($stg ?? '');
                    $titleVal = trim($titlesArr[$i] ?? '');
                    $amtVal = trim($amountsArr[$i] ?? '');
                    $notesVal = trim($notesArr[$i] ?? '');
                    if ($stgVal !== '' || $titleVal !== '' || $amtVal !== '') {
                        $stageFees[] = [
                            'stage'  => $stgVal,
                            'title'  => $titleVal,
                            'amount' => $amtVal,
                            'notes'  => $notesVal,
                        ];
                    }
                }
            }
            $stageFeesJson = !empty($stageFees) ? json_encode($stageFees, JSON_UNESCAPED_UNICODE) : null;

            // Parse discounts & offers
            $discounts = [];
            $discTitles = $_POST['discount_title'] ?? [];
            $discValues = $_POST['discount_value'] ?? [];
            $discAppliesTo = $_POST['discount_applies_to'] ?? [];
            $discNotes = $_POST['discount_notes'] ?? [];

            if (is_array($discTitles)) {
                foreach ($discTitles as $i => $dTitle) {
                    $dTitleVal = trim($dTitle ?? '');
                    $dValVal = trim($discValues[$i] ?? '');
                    $dAppliesVal = trim($discAppliesTo[$i] ?? '');
                    $dNotesVal = trim($discNotes[$i] ?? '');
                    if ($dTitleVal !== '' || $dValVal !== '') {
                        $discounts[] = [
                            'title'      => $dTitleVal,
                            'value'      => $dValVal,
                            'applies_to' => $dAppliesVal,
                            'notes'      => $dNotesVal,
                        ];
                    }
                }
            }
            $feeDiscountsJson = !empty($discounts) ? json_encode($discounts, JSON_UNESCAPED_UNICODE) : null;

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    fees_type = :fees_type,
                    approx_fees = :approx_fees,
                    stage_fees = :stage_fees,
                    fee_discounts = :fee_discounts,
                    updated_at = NOW()
                    WHERE id = :id");
                $upStmt->execute([
                    ':fees_type' => $fees_type,
                    ':approx_fees' => $approx_fees ?: null,
                    ':stage_fees' => $stageFeesJson,
                    ':fee_discounts' => $feeDiscountsJson,
                    ':id' => $schoolId,
                ]);
                $successMsg = 'تم حفظ وتحديث الرسوم الدراسية والعروض والخصومات بنجاح!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ الرسوم: ' . $e->getMessage();
            }
        }

        // 1.5 SUB-TAB: ABOUT, VISION & MISSION
        elseif ($action === 'update_profile_about') {
            $activeDashTab = 'profile';
            $activeSubTab = 'about';
            $description = trim($_POST['description'] ?? '');
            $vision = trim($_POST['vision'] ?? '');
            $mission = trim($_POST['mission'] ?? '');

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    description = :description, vision = :vision, mission = :mission,
                    updated_at = NOW()
                    WHERE id = :id");
                $upStmt->execute([
                    ':description' => $description ?: null,
                    ':vision' => $vision ?: null,
                    ':mission' => $mission ?: null,
                    ':id' => $schoolId,
                ]);
                $successMsg = 'تم حفظ النبذة والرؤية والرسالة بنجاح!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ النبذة: ' . $e->getMessage();
            }
        }

        // 1.6 SUB-TAB: SOCIAL MEDIA LINKS
        elseif ($action === 'update_profile_social') {
            $activeDashTab = 'profile';
            $activeSubTab = 'social';
            $website = trim($_POST['website'] ?? '');
            $facebook = trim($_POST['facebook'] ?? '');
            $twitter = trim($_POST['twitter'] ?? '');
            $youtube = trim($_POST['youtube'] ?? '');
            $telegram = trim($_POST['telegram'] ?? '');
            $instagram = trim($_POST['instagram'] ?? '');
            $tiktok = trim($_POST['tiktok'] ?? '');

            try {
                $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                    website = :website, facebook = :facebook, twitter = :twitter,
                    youtube = :youtube, telegram = :telegram, instagram = :instagram, tiktok = :tiktok,
                    updated_at = NOW()
                    WHERE id = :id");
                $upStmt->execute([
                    ':website' => $website ?: null,
                    ':facebook' => $facebook ?: null,
                    ':twitter' => $twitter ?: null,
                    ':youtube' => $youtube ?: null,
                    ':telegram' => $telegram ?: null,
                    ':instagram' => $instagram ?: null,
                    ':tiktok' => $tiktok ?: null,
                    ':id' => $schoolId,
                ]);
                $successMsg = 'تم حفظ روابط المنصات الرقمية بنجاح!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ الروابط: ' . $e->getMessage();
            }
        }

        // 1.7 SUB-TAB: MEDIA (LOGO & COVER)
        elseif ($action === 'update_profile_media') {
            $activeDashTab = 'profile';
            $activeSubTab = 'media';

            // Upload Logo
            $logoPath = $school['logo_path'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $upLogo = handleUpload($_FILES['logo'], 'logos');
                if ($upLogo) {
                    $logoPath = $upLogo;
                }
            } elseif (isset($_FILES['logo']['error']) && in_array($_FILES['logo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم صورة الشعار كبير جداً، يرجى اختيار صورة بحجم أقل.';
            }

            // Upload Cover
            $coverPath = $school['cover_path'];
            if (!empty($_FILES['cover']['tmp_name'])) {
                $upCover = handleUpload($_FILES['cover'], 'covers');
                if ($upCover) {
                    $coverPath = $upCover;
                }
            } elseif (isset($_FILES['cover']['error']) && in_array($_FILES['cover']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم صورة الغلاف كبير جداً، يرجى اختيار صورة بحجم أقل.';
            }

            if (!$errorMsg) {
                try {
                    $upStmt = $pdo->prepare("UPDATE daleel_schools SET 
                        logo_path = :logo_path, cover_path = :cover_path,
                        updated_at = NOW()
                        WHERE id = :id");
                    $upStmt->execute([
                        ':logo_path' => $logoPath,
                        ':cover_path' => $coverPath,
                        ':id' => $schoolId,
                    ]);
                    $successMsg = 'تم حفظ وتحديث الشعار والغلاف بنجاح!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } catch (Exception $e) {
                    $errorMsg = 'حدث خطأ أثناء تحديث الصور: ' . $e->getMessage();
                }
            }
        }

        // 2. ADD NEWS
        elseif ($action === 'add_news') {
            $activeDashTab = 'news';
            $newsTitle = trim($_POST['title'] ?? '');
            $branchName = trim($_POST['branch_name'] ?? '');
            $newsContent = sanitizeHtml($_POST['content'] ?? '');

            if (!$newsTitle || !$newsContent) {
                $errorMsg = 'يرجى إدخال عنوان وتفاصيل الخبر.';
            } else {
                $nStmt = $pdo->prepare("INSERT INTO daleel_school_news (school_id, title, branch_name, content, image_path, published_at, is_active, created_at) VALUES (?, ?, ?, ?, NULL, NOW(), 1, NOW())");
                $nStmt->execute([$schoolId, $newsTitle, $branchName ?: null, $newsContent]);
                $successMsg = 'تم نشر الخبر الجديد بنجاح!';
            }
        }

        // 2.1 EDIT NEWS
        elseif ($action === 'edit_news') {
            $activeDashTab = 'news';
            $newsId = (int)($_POST['news_id'] ?? 0);
            $newsTitle = trim($_POST['title'] ?? '');
            $branchName = trim($_POST['branch_name'] ?? '');
            $newsContent = sanitizeHtml($_POST['content'] ?? '');

            if (!$newsId || !$newsTitle || !$newsContent) {
                $errorMsg = 'يرجى إدخال عنوان وتفاصيل الخبر.';
            } else {
                $uStmt = $pdo->prepare("UPDATE daleel_school_news SET title = ?, branch_name = ?, content = ?, updated_at = NOW() WHERE id = ? AND school_id = ?");
                $uStmt->execute([$newsTitle, $branchName ?: null, $newsContent, $newsId, $schoolId]);
                $successMsg = 'تم تحديث وتعديل الخبر بنجاح!';
            }
        }

        // 3. DELETE NEWS
        elseif ($action === 'delete_news') {
            $activeDashTab = 'news';
            $newsId = (int)($_POST['news_id'] ?? 0);
            $delStmt = $pdo->prepare("DELETE FROM daleel_school_news WHERE id = ? AND school_id = ?");
            $delStmt->execute([$newsId, $schoolId]);
            $successMsg = 'تم حذف الخبر بنجاح.';
        }

        // 4. REPLY TO REVIEW
        elseif ($action === 'reply_review') {
            $activeDashTab = 'reviews';
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

        // 5. UPLOAD / DELETE LOGO
        elseif ($action === 'upload_logo') {
            $activeDashTab = 'media';
            if (!empty($_FILES['logo']['tmp_name'])) {
                $upLogo = handleUpload($_FILES['logo'], 'logos');
                if ($upLogo) {
                    $pdo->prepare("UPDATE daleel_schools SET logo_path = ? WHERE id = ?")->execute([$upLogo, $schoolId]);
                    $successMsg = 'تم تحديث ورفع شعار المدرسة بنجاح!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } else {
                    $errorMsg = 'فشل رفع الشعار، يرجى التأكد من أن الملف صورة صالحة (PNG أو JPG أو WebP).';
                }
            } elseif (isset($_FILES['logo']['error']) && in_array($_FILES['logo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم ملف الشعار كبير جداً، يرجى اختيار صورة بحجم أقل.';
            } else {
                $errorMsg = 'يرجى اختيار صورة الشعار أولاً.';
            }
        }
        elseif ($action === 'delete_logo') {
            $activeDashTab = 'media';
            $pdo->prepare("UPDATE daleel_schools SET logo_path = NULL WHERE id = ?")->execute([$schoolId]);
            $successMsg = 'تم حذف شعار المدرسة بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 6. UPLOAD / DELETE COVER
        elseif ($action === 'upload_cover') {
            $activeDashTab = 'media';
            if (!empty($_FILES['cover']['tmp_name'])) {
                $upCover = handleUpload($_FILES['cover'], 'covers');
                if ($upCover) {
                    $pdo->prepare("UPDATE daleel_schools SET cover_path = ? WHERE id = ?")->execute([$upCover, $schoolId]);
                    $successMsg = 'تم تحديث ورفع صورة الغلاف بنجاح!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } else {
                    $errorMsg = 'فشل رفع الغلاف، يرجى التأكد من أن الملف صورة صالحة.';
                }
            } elseif (isset($_FILES['cover']['error']) && in_array($_FILES['cover']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم صورة الغلاف كبير جداً، يرجى اختيار صورة بحجم أقل.';
            } else {
                $errorMsg = 'يرجى اختيار صورة الغلاف أولاً.';
            }
        }
        elseif ($action === 'delete_cover') {
            $activeDashTab = 'media';
            $pdo->prepare("UPDATE daleel_schools SET cover_path = NULL WHERE id = ?")->execute([$schoolId]);
            $successMsg = 'تم حذف صورة الغلاف بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 7. ADD GALLERY PHOTO (Max 8)
        elseif ($action === 'add_gallery_photo') {
            $activeDashTab = 'media';
            $currGallery = json_decode($school['gallery'] ?? '[]', true) ?: [];
            if (count($currGallery) >= 8) {
                $errorMsg = 'الحد الأقصى لمعرض الصور هو 8 صور. يمكنك حذف صورة سابقة لإفساح المجال لصورة جديدة.';
            } elseif (!empty($_FILES['gallery_photo']['tmp_name'])) {
                $upPhoto = handleUpload($_FILES['gallery_photo'], 'gallery');
                if ($upPhoto) {
                    $caption = trim($_POST['caption'] ?? '');
                    $currGallery[] = [
                        'id' => uniqid('gal_', true),
                        'path' => $upPhoto,
                        'caption' => $caption,
                        'created_at' => date('Y-m-d H:i')
                    ];
                    $pdo->prepare("UPDATE daleel_schools SET gallery = ? WHERE id = ?")->execute([
                        json_encode($currGallery, JSON_UNESCAPED_UNICODE),
                        $schoolId
                    ]);
                    $successMsg = 'تمت إضافة الصورة بنجاح إلى معرض مرافق المدرسة!';
                    $stmt->execute([$schoolId]);
                    $school = $stmt->fetch();
                } else {
                    $errorMsg = 'فشل رفع الصورة، يرجى التأكد من اختيار ملف صورة صالح.';
                }
            } elseif (isset($_FILES['gallery_photo']['error']) && in_array($_FILES['gallery_photo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $errorMsg = 'حجم الصورة كبير جداً، يرجى اختيار صورة بحجم أقل.';
            } else {
                $errorMsg = 'يرجى تحديد ملف الصورة المراد إضافتها.';
            }
        }

        // 8. DELETE GALLERY PHOTO
        elseif ($action === 'delete_gallery_photo') {
            $activeDashTab = 'media';
            $photoId = trim($_POST['photo_id'] ?? '');
            $currGallery = json_decode($school['gallery'] ?? '[]', true) ?: [];
            $filtered = [];
            foreach ($currGallery as $item) {
                if ($item['id'] === $photoId) {
                    $relPath = ltrim($item['path'] ?? '', '/');
                    if ($relPath && file_exists(__DIR__ . '/' . $relPath)) {
                        @unlink(__DIR__ . '/' . $relPath);
                    }
                } else {
                    $filtered[] = $item;
                }
            }
            $pdo->prepare("UPDATE daleel_schools SET gallery = ? WHERE id = ?")->execute([
                json_encode($filtered, JSON_UNESCAPED_UNICODE),
                $schoolId
            ]);
            $successMsg = 'تم حذف الصورة من المعرض بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 9. TOGGLE OPTIONAL TAB VISIBILITY (branches / activities / teachers)
        elseif ($action === 'toggle_optional_tab') {
            $tabKey = trim($_POST['tab_key'] ?? '');
            if (in_array($tabKey, ['branches', 'activities', 'teachers'], true)) {
                $activeDashTab = $tabKey;
                $optTabs = json_decode($school['optional_tabs'] ?? '{}', true);
                if (!is_array($optTabs)) $optTabs = [];
                $currentState = isset($optTabs[$tabKey]) ? (bool)$optTabs[$tabKey] : true;
                $optTabs[$tabKey] = !$currentState;
                $pdo->prepare("UPDATE daleel_schools SET optional_tabs = ? WHERE id = ?")->execute([
                    json_encode($optTabs, JSON_UNESCAPED_UNICODE),
                    $schoolId
                ]);
                $tabNames = ['branches' => 'تبويب الفروع', 'activities' => 'تبويب المناشط', 'teachers' => 'تبويب المعلمين'];
                $successMsg = $optTabs[$tabKey]
                    ? 'تم تفعيل إظهار ' . $tabNames[$tabKey] . ' في صفحة المدرسة العامة.'
                    : 'تم إخفاء ' . $tabNames[$tabKey] . ' مؤقتاً من صفحة المدرسة العامة.';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            }
        }

        // 10. ADD / DELETE BRANCH (الفروع)
        elseif ($action === 'add_branch') {
            $activeDashTab = 'branches';
            $branchName = trim($_POST['branch_name'] ?? '');
            $branchAddress = trim($_POST['branch_address'] ?? '');
            $branchPhone = trim($_POST['branch_phone'] ?? '');
            $branchWhatsapp = trim($_POST['branch_whatsapp'] ?? '');
            $branchMapUrl = trim($_POST['branch_map_url'] ?? '');

            if (!$branchName || !$branchAddress) {
                $errorMsg = 'يرجى إدخال اسم الفرع والعنوان التفصيلي.';
            } else {
                $currBranches = json_decode($school['branches_data'] ?? '[]', true) ?: [];
                $currBranches[] = [
                    'id' => uniqid('br_', true),
                    'name' => $branchName,
                    'address' => $branchAddress,
                    'phone' => $branchPhone,
                    'whatsapp' => $branchWhatsapp,
                    'map_url' => $branchMapUrl,
                    'created_at' => date('Y-m-d H:i')
                ];
                $optTabs = json_decode($school['optional_tabs'] ?? '{}', true);
                if (!is_array($optTabs)) $optTabs = [];
                $optTabs['branches'] = true;

                $pdo->prepare("UPDATE daleel_schools SET branches_data = ?, optional_tabs = ? WHERE id = ?")->execute([
                    json_encode($currBranches, JSON_UNESCAPED_UNICODE),
                    json_encode($optTabs, JSON_UNESCAPED_UNICODE),
                    $schoolId
                ]);
                $successMsg = 'تمت إضافة الفرع الجديد بنجاح إلى تبويب فروع المدرسة!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            }
        }
        elseif ($action === 'delete_branch') {
            $activeDashTab = 'branches';
            $branchId = trim($_POST['branch_id'] ?? '');
            $currBranches = json_decode($school['branches_data'] ?? '[]', true) ?: [];
            $filtered = array_values(array_filter($currBranches, fn($b) => ($b['id'] ?? '') !== $branchId));
            $pdo->prepare("UPDATE daleel_schools SET branches_data = ? WHERE id = ?")->execute([
                json_encode($filtered, JSON_UNESCAPED_UNICODE),
                $schoolId
            ]);
            $successMsg = 'تم حذف الفرع بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 11. ADD / DELETE ACTIVITY (المناشط)
        elseif ($action === 'add_activity') {
            $activeDashTab = 'activities';
            $actTitle = trim($_POST['activity_title'] ?? '');
            $actCategory = trim($_POST['activity_category'] ?? 'ثقافي وأدبي');
            $actSchedule = trim($_POST['activity_schedule'] ?? '');
            $actDesc = trim($_POST['activity_description'] ?? '');

            if (!$actTitle || !$actDesc) {
                $errorMsg = 'يرجى إدخال اسم المنشط ووصفه.';
            } else {
                $actImage = null;
                if (!empty($_FILES['activity_image']['tmp_name'])) {
                    $actImage = handleUpload($_FILES['activity_image'], 'activities');
                }
                $currActivities = json_decode($school['activities_data'] ?? '[]', true) ?: [];
                $currActivities[] = [
                    'id' => uniqid('act_', true),
                    'title' => $actTitle,
                    'category' => $actCategory,
                    'schedule' => $actSchedule,
                    'description' => $actDesc,
                    'image_path' => $actImage,
                    'created_at' => date('Y-m-d H:i')
                ];
                $optTabs = json_decode($school['optional_tabs'] ?? '{}', true);
                if (!is_array($optTabs)) $optTabs = [];
                $optTabs['activities'] = true;

                $pdo->prepare("UPDATE daleel_schools SET activities_data = ?, optional_tabs = ? WHERE id = ?")->execute([
                    json_encode($currActivities, JSON_UNESCAPED_UNICODE),
                    json_encode($optTabs, JSON_UNESCAPED_UNICODE),
                    $schoolId
                ]);
                $successMsg = 'تمت إضافة المنشط بنجاح إلى تبويب المناشط والأنشطة!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            }
        }
        elseif ($action === 'delete_activity') {
            $activeDashTab = 'activities';
            $actId = trim($_POST['activity_id'] ?? '');
            $currActivities = json_decode($school['activities_data'] ?? '[]', true) ?: [];
            $filtered = [];
            foreach ($currActivities as $item) {
                if (($item['id'] ?? '') === $actId) {
                    $relPath = ltrim($item['image_path'] ?? '', '/');
                    if ($relPath && file_exists(__DIR__ . '/' . $relPath)) {
                        @unlink(__DIR__ . '/' . $relPath);
                    }
                } else {
                    $filtered[] = $item;
                }
            }
            $pdo->prepare("UPDATE daleel_schools SET activities_data = ? WHERE id = ?")->execute([
                json_encode($filtered, JSON_UNESCAPED_UNICODE),
                $schoolId
            ]);
            $successMsg = 'تم حذف المنشط بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 12. ADD / DELETE TEACHER (المعلمون)
        elseif ($action === 'add_teacher') {
            $activeDashTab = 'teachers';
            $tName = trim($_POST['teacher_name'] ?? '');
            $tSubject = trim($_POST['teacher_subject'] ?? '');
            $tStage = trim($_POST['teacher_stage'] ?? '');
            $tClass = trim($_POST['teacher_class'] ?? '');

            if (!$tName || !$tSubject) {
                $errorMsg = 'يرجى إدخال اسم المعلم والمادة الدراسية.';
            } else {
                $currTeachers = json_decode($school['teachers_data'] ?? '[]', true) ?: [];
                $currTeachers[] = [
                    'id' => uniqid('tch_', true),
                    'name' => $tName,
                    'subject' => $tSubject,
                    'stage' => $tStage,
                    'class_name' => $tClass,
                    'created_at' => date('Y-m-d H:i')
                ];
                $optTabs = json_decode($school['optional_tabs'] ?? '{}', true);
                if (!is_array($optTabs)) $optTabs = [];
                $optTabs['teachers'] = true;

                $pdo->prepare("UPDATE daleel_schools SET teachers_data = ?, optional_tabs = ? WHERE id = ?")->execute([
                    json_encode($currTeachers, JSON_UNESCAPED_UNICODE),
                    json_encode($optTabs, JSON_UNESCAPED_UNICODE),
                    $schoolId
                ]);
                $successMsg = 'تمت إضافة المعلم بنجاح إلى تبويب الكادر التدريسي!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            }
        }
        elseif ($action === 'delete_teacher') {
            $activeDashTab = 'teachers';
            $tId = trim($_POST['teacher_id'] ?? '');
            $currTeachers = json_decode($school['teachers_data'] ?? '[]', true) ?: [];
            $filtered = array_values(array_filter($currTeachers, fn($t) => ($t['id'] ?? '') !== $tId));
            $pdo->prepare("UPDATE daleel_schools SET teachers_data = ? WHERE id = ?")->execute([
                json_encode($filtered, JSON_UNESCAPED_UNICODE),
                $schoolId
            ]);
            $successMsg = 'تم حذف بيانات المعلم بنجاح.';
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
        }

        // 13. UPDATE INQUIRY STATUS (تحديث حالة طلب القبول)
        elseif ($action === 'update_inquiry_status') {
            $activeDashTab = 'inquiries';
            $inqId = (int)($_POST['inquiry_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? 'new');
            if (in_array($newStatus, ['new', 'contacted', 'accepted', 'rejected'], true) && $inqId > 0) {
                $pdo->prepare("UPDATE daleel_school_inquiries SET status = ?, updated_at = NOW() WHERE id = ? AND school_id = ?")->execute([
                    $newStatus, $inqId, $schoolId
                ]);
                $statusLabels = [
                    'new' => 'جديد',
                    'contacted' => 'تم التواصل',
                    'accepted' => 'تم القبول',
                    'rejected' => 'مرفوض/ملغي'
                ];
                $successMsg = 'تم تحديث حالة طلب القبول إلى "' . ($statusLabels[$newStatus] ?? $newStatus) . '" بنجاح.';
            }
        }

        // 14. DELETE INQUIRY (حذف طلب القبول)
        elseif ($action === 'delete_inquiry') {
            $activeDashTab = 'inquiries';
            $inqId = (int)($_POST['inquiry_id'] ?? 0);
            if ($inqId > 0) {
                $pdo->prepare("DELETE FROM daleel_school_inquiries WHERE id = ? AND school_id = ?")->execute([
                    $inqId, $schoolId
                ]);
                $successMsg = 'تم حذف طلب القبول بنجاح.';
            }
        }

        // 15. UPDATE ADMISSION SETTINGS (إعدادات وشروط القبول والتسجيل)
        elseif ($action === 'update_admission_settings') {
            $activeDashTab = 'inquiries';
            $admissionStatus = trim($_POST['admission_status'] ?? 'open');
            if (!in_array($admissionStatus, ['open', 'limited', 'early', 'closed'], true)) {
                $admissionStatus = 'open';
            }
            $admissionNotes = trim($_POST['admission_notes'] ?? '');

            // Parse requirements (array of strings)
            $reqsArr = $_POST['admission_requirements'] ?? [];
            $cleanReqs = [];
            if (is_array($reqsArr)) {
                foreach ($reqsArr as $req) {
                    $rVal = trim($req ?? '');
                    if ($rVal !== '') {
                        $cleanReqs[] = $rVal;
                    }
                }
            }
            $reqsJson = !empty($cleanReqs) ? json_encode($cleanReqs, JSON_UNESCAPED_UNICODE) : null;

            // Parse steps (array of strings)
            $stepsArr = $_POST['admission_steps'] ?? [];
            $cleanSteps = [];
            if (is_array($stepsArr)) {
                foreach ($stepsArr as $step) {
                    $sVal = trim($step ?? '');
                    if ($sVal !== '') {
                        $cleanSteps[] = $sVal;
                    }
                }
            }
            $stepsJson = !empty($cleanSteps) ? json_encode($cleanSteps, JSON_UNESCAPED_UNICODE) : null;

            try {
                $pdo->prepare("UPDATE daleel_schools SET 
                    admission_status = :status,
                    admission_requirements = :reqs,
                    admission_steps = :steps,
                    admission_notes = :notes,
                    updated_at = NOW()
                    WHERE id = :id")->execute([
                        ':status' => $admissionStatus,
                        ':reqs' => $reqsJson,
                        ':steps' => $stepsJson,
                        ':notes' => $admissionNotes ?: null,
                        ':id' => $schoolId,
                    ]);
                $successMsg = 'تم حفظ وتحديث إعدادات وشروط القبول والتسجيل بنجاح!';
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } catch (Exception $e) {
                $errorMsg = 'حدث خطأ أثناء حفظ إعدادات القبول: ' . $e->getMessage();
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

$galleryList = json_decode($school['gallery'] ?? '[]', true) ?: [];
$branchesList = json_decode($school['branches_data'] ?? '[]', true) ?: [];
$activitiesList = json_decode($school['activities_data'] ?? '[]', true) ?: [];
$teachersList = json_decode($school['teachers_data'] ?? '[]', true) ?: [];
$optionalTabsCfg = json_decode($school['optional_tabs'] ?? '{}', true);
if (!is_array($optionalTabsCfg)) $optionalTabsCfg = [];
$isBranchesTabEnabled = isset($optionalTabsCfg['branches']) ? (bool)$optionalTabsCfg['branches'] : true;
$isActivitiesTabEnabled = isset($optionalTabsCfg['activities']) ? (bool)$optionalTabsCfg['activities'] : true;
$isTeachersTabEnabled = isset($optionalTabsCfg['teachers']) ? (bool)$optionalTabsCfg['teachers'] : true;

$currentStages = json_decode($school['stages'] ?? '[]', true) ?: [];
$currentFacilities = json_decode($school['facilities'] ?? '[]', true) ?: [];
$currentFeesType = $school['fees_type'] ?? 'average';
$currentStageFees = json_decode($school['stage_fees'] ?? '[]', true) ?: [];
$currentFeeDiscounts = json_decode($school['fee_discounts'] ?? '[]', true) ?: [];

$currentAdmissionStatus = $school['admission_status'] ?? 'open';
$currentAdmissionReqs = json_decode($school['admission_requirements'] ?? '[]', true);
if (!is_array($currentAdmissionReqs) || empty($currentAdmissionReqs)) {
    $currentAdmissionReqs = [
        'أصل وصورة شهادة الميلاد أو الرقم الوطني للطالب.',
        'شهادة النقل أو آخر تقرير أكاديمي معتمد (للصفوف المنقولة).',
        'عدد 4 صور فوتوغرافية حديثة بحجم جواز السفر.',
        'كرت التطعيم والشهادة الصحية (لمرحلة رياض الأطفال).'
    ];
}
$currentAdmissionSteps = json_decode($school['admission_steps'] ?? '[]', true);
if (!is_array($currentAdmissionSteps) || empty($currentAdmissionSteps)) {
    $currentAdmissionSteps = [
        'تقديم طلب القبول الإلكتروني بالاستمارة أدناه.',
        'تواصل فريق القبول وتحديد موعد المقابلة واختبار المستوى.',
        'إكمال تسليم المستندات واعتماد التسجيل وسداد الرسوم.',
        'استلام الزي المدرسي والجدول وتوزيع الفصول.'
    ];
}
$currentAdmissionNotes = $school['admission_notes'] ?? '';

$schoolCountry = $school['country'] ?? 'السودان';
$countriesWithStates = SudanGeography::countriesWithStatesAndCities();
$countriesList = SudanGeography::countries();
$statesWithCities = SudanGeography::statesWithCities($schoolCountry);

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
    if ($field === 'approx_fees') {
        if (!empty($school['approx_fees']) || !empty($currentStageFees) || !empty($currentFeeDiscounts)) {
            $score += $points;
        }
    } elseif (!empty($school[$field]) && $school[$field] !== '[]') {
        $score += $points;
    }
}

$pageTitle = 'لوحة إدارة المدرسة: ' . $school['name'];
require_once __DIR__ . '/header.php';
?>

<style>
/* Dashboard Tabs Navigation (Horizontal Smooth Scroll) */
.dash-tabs-scroll-wrap {
    position: relative;
    margin-bottom: 22px;
}
.dash-tabs-scroll {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding: 6px 4px 12px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
    border-bottom: 2px solid #DDE4ED;
}
.dash-tabs-scroll::-webkit-scrollbar {
    height: 5px;
}
.dash-tabs-scroll::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}
.dash-tab-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-body);
    background: #FFFFFF;
    border: 1.5px solid #E2D7C8;
    white-space: nowrap;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}
.dash-tab-link:hover {
    background: #FAF6F0;
    border-color: #D4C3AC;
    color: var(--brand-navy);
}
.dash-tab-link.active {
    background: #0B2A52 !important;
    color: #FFFFFF !important;
    border-color: #0B2A52 !important;
    box-shadow: 0 4px 12px rgba(11, 42, 82, 0.18);
}
.dash-tab-link.active i {
    color: #F5A020 !important;
}
.dash-tab-link .dash-tab-badge {
    background: #EFF6FF;
    color: var(--brand-blue);
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 999px;
}
.dash-tab-link.active .dash-tab-badge {
    background: rgba(255, 255, 255, 0.2);
    color: #FFFFFF;
}

/* Quick Anchor Bar inside Edit Profile */
.dash-subnav-bar {
    position: sticky;
    top: 72px;
    z-index: 10;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid #E2D7C8;
    border-radius: 16px;
    padding: 10px 14px;
    margin-bottom: 22px;
    box-shadow: 0 8px 20px -6px rgba(11, 42, 82, 0.08);
}
.dash-subnav-links {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    flex-wrap: nowrap;
}
.dash-subnav-links::-webkit-scrollbar {
    display: none;
}
.dash-subnav-btn {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--text-body);
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 999px;
    padding: 6px 14px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: all 0.2s ease;
    cursor: pointer;
}
.dash-subnav-btn:hover {
    background: var(--brand-navy);
    color: #FFFFFF !important;
    border-color: var(--brand-navy);
}
.dash-subnav-btn:hover i {
    color: #F5A020 !important;
}
.dash-subnav-btn.active {
    background: var(--brand-navy) !important;
    color: #FFFFFF !important;
    border-color: var(--brand-navy) !important;
    box-shadow: 0 4px 12px rgba(11, 42, 82, 0.16);
}
.dash-subnav-btn.active i {
    color: #F5A020 !important;
}
.fee-mode-card {
    transition: all 0.2s ease;
    background: #FFFFFF;
}
.fee-mode-card:hover {
    border-color: var(--brand-blue) !important;
    box-shadow: 0 4px 12px rgba(30, 95, 168, 0.08);
}
.fee-mode-card:has(input:checked) {
    border-color: var(--brand-blue) !important;
    background: #F0F7FF !important;
}

/* Form Section Cards */
.dash-card-section {
    background: #FFFFFF;
    border: 1px solid #E6DCCD;
    border-radius: 18px;
    padding: 24px 26px;
    margin-bottom: 22px;
    box-shadow: 0 4px 14px -3px rgba(11, 42, 82, 0.04);
    scroll-margin-top: 140px;
}
.dash-card-section:hover {
    border-color: #D4C3AC;
}
.dash-sec-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding-bottom: 14px;
    margin-bottom: 18px;
    border-bottom: 1.5px solid #F1EAE0;
}
.dash-sec-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.dash-sec-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.dash-sec-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--brand-navy);
    margin: 0;
}
.dash-sec-desc {
    font-size: 0.73rem;
    color: #64748B;
    margin: 2px 0 0;
}

/* Stage check pills */
.stage-check-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #F8FAFC;
    border: 1.5px solid #E2D7C8;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-main);
    transition: all 0.2s ease;
    user-select: none;
}
.stage-check-pill:hover {
    background: #EFF6FF;
    border-color: var(--brand-blue);
}
.stage-check-pill input:checked ~ span {
    color: var(--brand-navy);
    font-weight: 800;
}

/* Facility check cards */
.fac-check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--text-main);
    transition: all 0.2s ease;
    user-select: none;
}
.fac-check-item:hover {
    background: #FFFFFF;
    border-color: var(--brand-blue);
    box-shadow: 0 2px 8px rgba(30, 95, 168, 0.08);
}
.fac-check-item input:checked ~ span {
    font-weight: 800;
    color: var(--brand-navy);
}

/* Form input enhancements */
.dash-card-section .form-label {
    margin-bottom: 6px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #475569;
}
.dash-card-section .form-control,
.dash-card-section .form-select {
    border-radius: 10px;
    border: 1px solid #CBD5E1;
    font-size: 0.78rem;
    padding: 9px 12px;
    transition: all 0.2s ease;
}
.dash-card-section .form-control:focus,
.dash-card-section .form-select:focus {
    border-color: var(--brand-blue);
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}
</style>

<div class="dashboard-page py-4">
    <div class="container">

        <?php if (!empty($_SESSION['daleel_admin_impersonating'])): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #FFF7ED, #FFEDD5); border: 1.5px solid #F5A020 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background: #F5A020;">
                        <i class="fas fa-user-shield fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">أنت تتصفح وتدير هذه المدرسة بصفتك المشرف العام (Master Admin)</div>
                        <div class="small text-muted" style="font-size: 0.74rem;">يمكنك إجراء أي تعديل على بيانات المدرسة والمراحل والمرافق والأخبار والقبول مباشرة.</div>
                    </div>
                </div>
                <div>
                    <a href="admin.php?tab=schools" class="btn btn-sm btn-dark fw-bold px-3 py-2 rounded-3 shadow-sm" style="font-size: 0.78rem;">
                        <i class="fas fa-arrow-right me-1"></i> العودة للوحة التحكم المركزية
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Header Card -->
        <div class="card border-0 rounded-4 shadow-sm bg-white p-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-light border rounded-3 p-1 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <img src="<?= e(defaultSchoolLogo($school['logo_path'] ?? null)) ?>" alt="شعار المدرسة" style="max-width: 100%; max-height: 100%; object-fit: contain;" class="rounded-2">
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php $dashRank = getSchoolDirectoryRank($pdo, (int)$school['id'], !empty($school['directory_rank']) ? (int)$school['directory_rank'] : null); $dashStateRank = getSchoolStateRank($pdo, (int)$school['id'], (string)($school['state'] ?? ''), !empty($school['state_rank']) ? (int)$school['state_rank'] : null); ?>
                            <?php if ($school['status'] === 'approved'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-check-circle me-1"></i> معتمدة ونشطة بالدليل
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-award me-1"></i> الترتيب في الدليل: #<?= $dashRank ?></span> <span class="badge rounded-pill px-3 py-1 fw-bold small" style="background: #EFF6FF; color: #0B2A52; border: 1px solid #BFDBFE;"><i class="fas fa-map-marker-alt me-1" style="color: #F5A020;"></i> الترتيب في ولاية <?= e($school['state']) ?>: #<?= $dashStateRank ?>
                                </span>
                            <?php elseif ($school['status'] === 'pending'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-bold small">
                                    <i class="fas fa-hourglass-half me-1"></i> بانتظار التأكيد والاعتماد (الترتيب المتوقع بالدليل: #<?= $dashRank ?> — بالولاية: #<?= $dashStateRank ?>)
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

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="/school/<?= urlencode($school['slug']) ?>" target="_blank" class="btn-luxury-outline">
                        <i class="fas fa-external-link-alt text-primary"></i>
                        <span>معاينة صفحة المدرسة</span>
                    </a>
                    <button type="button" class="btn-luxury-primary" onclick="openShareModal()">
                        <i class="fas fa-share-nodes"></i>
                        <span>مشاركة الرابط والباركود</span>
                    </button>
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
            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4" style="background: #FEF2F2; border: 1.5px solid #EF4444 !important;">
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
            <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 mb-4" style="background: #FFFBEB; border: 1.5px solid #F5A020 !important;">
                <div class="row align-items-center g-4">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size: 0.8rem;">
                                <i class="fas fa-clock me-1"></i> قيد المراجعة الفنية
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">طلب تسجيل مدرستكم في الدليل قيد التدقيق والمراجعة حالياً</h4>
                        <p class="text-secondary small mb-3 lh-base">
                            أهلاً بكم في منصة دليل المدارس. يقوم فريقنا الآن بمراجعة بيانات المدرسة المسجلة للتأكد من دقتها واعتماد ظهورها العام.
                            في هذه الأثناء، <strong>يمكنك إكمال ملف مدرستك بالكامل أدناه</strong> (رفع شعار المدرسة، غلاف الواجهة، تحديد المرافق، الرؤية والرسالة، الرسوم الدراسية) بحيث تكون صفحتكم في أبهى صورة فور تفعيلها للجمهور!
                        </p>
                        <!-- Profile completion progress -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 10px; border-radius: 6px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $score ?>%; background: #F5A020;" aria-valuenow="<?= $score ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <span class="fw-bold text-dark small">اكتمال الملف: <?= $score ?>%</span>
                        </div>
                    </div>

                    <div class="col-md-4 text-md-start">
                        <div class="p-3 bg-white rounded-4 shadow-sm border border-warning-subtle text-center">
                            <small class="text-muted d-block mb-2">لتسريع تفعيل ظهور الصفحة في الدليل:</small>
                            <a href="https://wa.me/249912345678?text=<?= urlencode('مرحباً فريق SchoPlus، قمت بتسجيل مدرستي (' . $school['name'] . ') وأرجو تفعيلها في دليل المدارس.') ?>" target="_blank" class="btn btn-primary fw-bold w-100 py-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background: #1E5FA8; border-color: #1E5FA8;">
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
                    <span class="text-muted small d-block mb-1"><i class="fas fa-bullhorn text-primary me-1"></i> الأخبار المنشورة</span>
                    <h3 class="fw-bold text-dark mb-0"><?= count($newsList) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 rounded-4 p-3 bg-white shadow-sm text-center">
                    <span class="text-muted small d-block mb-1"><i class="fas fa-inbox text-primary me-1"></i> طلبات القبول والاستفسار</span>
                    <h3 class="fw-bold text-dark mb-0"><?= count($inquiriesList) ?></h3>
                </div>
            </div>
        </div>

        <!-- Upgrade to SchoPlus Banner -->
        <?php if (!$school['is_schoplus_client']): ?>
            <div class="card border-0 rounded-4 p-4 mb-4 shadow-sm" style="background: #0B2A52; color: #ffffff; border: 1px solid #1E5FA8;">
                <div class="row align-items-center g-3">
                    <div class="col-md-8">
                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold mb-2 small">
                            <i class="fas fa-rocket me-1"></i> ترقية لشريك SchoPlus الذكي
                        </span>
                        <h5 class="fw-bold text-white mb-2">اجعل مدرستك رائدة رقمياً مع نظام SchoPlus السحابي</h5>
                        <p class="small text-light opacity-85 mb-0">
                            احصل على شارة "مدرسة ذكية بنظام SchoPlus" في الدليل، وامتلك تطبيقات موبايل مخصصة للمدير، المعلمين، وأولياء الأمور لإدارة الحضور والنتائج والرسوم المالية بسهولة فائقة.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-start">
                        <a href="https://schoplus.sd" target="_blank" class="btn btn-warning fw-bold text-dark rounded-pill px-4 py-2 shadow-sm">
                            <i class="fas fa-star me-1"></i> اطلب تجربة النظام الآن
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Dashboard Tabs -->
        <div class="card border-0 rounded-4 bg-white shadow-sm p-3 p-md-4">
            
            <div class="dash-tabs-scroll-wrap">
                <div class="dash-tabs-scroll" id="dashboardTabs" role="tablist">
                    <button class="dash-tab-link <?= $activeDashTab === 'profile' ? 'active' : '' ?>" id="dash-profile-tab" data-bs-toggle="tab" data-bs-target="#dash-profile" type="button" role="tab" aria-selected="<?= $activeDashTab === 'profile' ? 'true' : 'false' ?>">
                        <i class="fas fa-edit"></i>
                        <span>تعديل بيانات المدرسة</span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'fees' ? 'active' : '' ?>" id="dash-fees-tab" data-bs-toggle="tab" data-bs-target="#dash-fees" type="button" role="tab" aria-selected="<?= $activeDashTab === 'fees' ? 'true' : 'false' ?>">
                        <i class="fas fa-coins" style="color: #F59E0B;"></i>
                        <span>الرسوم والعروض</span>
                        <span class="dash-tab-badge">
                            <?php if (!empty($currentFeeDiscounts)): ?>
                                <?= count($currentFeeDiscounts) ?> عروض
                            <?php elseif ($currentFeesType === 'detailed' && !empty($currentStageFees)): ?>
                                <?= count($currentStageFees) ?> بنود
                            <?php elseif (!empty($school['approx_fees'])): ?>
                                محددة
                            <?php else: ?>
                                غير محددة
                            <?php endif; ?>
                        </span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'media' ? 'active' : '' ?>" id="dash-media-tab" data-bs-toggle="tab" data-bs-target="#dash-media" type="button" role="tab" aria-selected="<?= $activeDashTab === 'media' ? 'true' : 'false' ?>">
                        <i class="fas fa-images"></i>
                        <span>الشعار والمعرض</span>
                        <span class="dash-tab-badge"><?= count($galleryList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'branches' ? 'active' : '' ?>" id="dash-branches-tab" data-bs-toggle="tab" data-bs-target="#dash-branches" type="button" role="tab" aria-selected="<?= $activeDashTab === 'branches' ? 'true' : 'false' ?>">
                        <i class="fas fa-code-branch"></i>
                        <span>الفروع</span>
                        <span class="dash-tab-badge"><?= count($branchesList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'activities' ? 'active' : '' ?>" id="dash-activities-tab" data-bs-toggle="tab" data-bs-target="#dash-activities" type="button" role="tab" aria-selected="<?= $activeDashTab === 'activities' ? 'true' : 'false' ?>">
                        <i class="fas fa-trophy"></i>
                        <span>المناشط والأنشطة</span>
                        <span class="dash-tab-badge"><?= count($activitiesList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'teachers' ? 'active' : '' ?>" id="dash-teachers-tab" data-bs-toggle="tab" data-bs-target="#dash-teachers" type="button" role="tab" aria-selected="<?= $activeDashTab === 'teachers' ? 'true' : 'false' ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>الكادر التعليمي</span>
                        <span class="dash-tab-badge"><?= count($teachersList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'news' ? 'active' : '' ?>" id="dash-news-tab" data-bs-toggle="tab" data-bs-target="#dash-news" type="button" role="tab" aria-selected="<?= $activeDashTab === 'news' ? 'true' : 'false' ?>">
                        <i class="fas fa-bullhorn"></i>
                        <span>الأخبار والإعلانات</span>
                        <span class="dash-tab-badge"><?= count($newsList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'reviews' ? 'active' : '' ?>" id="dash-reviews-tab" data-bs-toggle="tab" data-bs-target="#dash-reviews" type="button" role="tab" aria-selected="<?= $activeDashTab === 'reviews' ? 'true' : 'false' ?>">
                        <i class="fas fa-comments"></i>
                        <span>تقييمات الأهالي</span>
                        <span class="dash-tab-badge"><?= count($reviewsList) ?></span>
                    </button>
                    <button class="dash-tab-link <?= $activeDashTab === 'inquiries' ? 'active' : '' ?>" id="dash-inquiries-tab" data-bs-toggle="tab" data-bs-target="#dash-inquiries" type="button" role="tab" aria-selected="<?= $activeDashTab === 'inquiries' ? 'true' : 'false' ?>">
                        <i class="fas fa-inbox"></i>
                        <span>طلبات القبول</span>
                        <span class="dash-tab-badge"><?= count($inquiriesList) ?></span>
                    </button>
                </div>
            </div>

            <div class="tab-content" id="dashboardTabsContent">
                
                <!-- TAB 1: EDIT PROFILE & DATA (MODULAR SUB-TABS) -->
                <div class="tab-pane fade <?= $activeDashTab === 'profile' ? 'show active' : '' ?>" id="dash-profile" role="tabpanel">
                    
                    <!-- Quick Sub-Tabs Navigation Bar (Only Selected Tab Content Shown) -->
                    <div class="dash-subnav-bar">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="dash-subnav-links" id="profileSubTabs" role="tablist">
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'basic' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-basic" role="tab" aria-controls="subtab-basic" aria-selected="<?= $activeSubTab === 'basic' ? 'true' : 'false' ?>">
                                    <i class="fas fa-school text-primary"></i> 1. الهوية والتصنيف
                                </button>
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'location' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-location" role="tab" aria-controls="subtab-location" aria-selected="<?= $activeSubTab === 'location' ? 'true' : 'false' ?>">
                                    <i class="fas fa-map-marked-alt text-danger"></i> 2. الموقع والعنوان
                                </button>
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'academic' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-academic" role="tab" aria-controls="subtab-academic" aria-selected="<?= $activeSubTab === 'academic' ? 'true' : 'false' ?>">
                                    <i class="fas fa-cubes text-success"></i> 3. المراحل والمرافق
                                </button>
                                <button type="button" class="dash-subnav-btn <?= in_array($activeSubTab, ['contact', 'fees_contact']) ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-contact" role="tab" aria-controls="subtab-contact" aria-selected="<?= in_array($activeSubTab, ['contact', 'fees_contact']) ? 'true' : 'false' ?>">
                                    <i class="fas fa-phone-alt text-info"></i> 4. قنوات الاتصال والدوام
                                </button>
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'about' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-about" role="tab" aria-controls="subtab-about" aria-selected="<?= $activeSubTab === 'about' ? 'true' : 'false' ?>">
                                    <i class="fas fa-align-right text-info"></i> 5. النبذة والرؤية
                                </button>
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'social' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-social" role="tab" aria-controls="subtab-social" aria-selected="<?= $activeSubTab === 'social' ? 'true' : 'false' ?>">
                                    <i class="fas fa-share-alt text-secondary"></i> 6. المنصات الرقمية
                                </button>
                                <button type="button" class="dash-subnav-btn <?= $activeSubTab === 'media' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#subtab-media" role="tab" aria-controls="subtab-media" aria-selected="<?= $activeSubTab === 'media' ? 'true' : 'false' ?>">
                                    <i class="fas fa-images text-danger"></i> 7. الشعار والغلاف
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="/school/<?= urlencode($school['slug']) ?>" target="_blank" class="btn-luxury-outline btn-sm px-3 py-1">
                                    <i class="fas fa-external-link-alt me-1"></i> معاينة البروفايل
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Tabs Content: ONLY the selected active subtab is displayed -->
                    <div class="tab-content" id="profileSubTabsContent">
                        
                        <!-- SUBTAB 1: BASIC INFO & CLASSIFICATION -->
                        <div class="tab-pane fade <?= $activeSubTab === 'basic' ? 'show active' : '' ?>" id="subtab-basic" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabBasic">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_basic">
                                <input type="hidden" name="subtab" value="basic">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #EFF6FF; color: var(--brand-blue);">
                                                <i class="fas fa-school"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">1. الهوية والتصنيف الدراسي للمدرسة</h5>
                                                <p class="dash-sec-desc">الاسم الرسمي للمدرسة، الرابط التعريفي المخصص، سنة التأسيس، نوع المدرسة، والمناهج المعتمدة.</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill small fw-bold">البيانات الأساسية</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">اسم المدرسة باللغة العربية <span class="text-danger">*</span></label>
                                            <input type="text" name="name" required class="form-control" value="<?= e($school['name']) ?>" placeholder="اكتب اسم المدرسة كاملاً">
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label mb-0">اسم المدرسة باللغة الإنجليزية (اختياري)</label>
                                                <small class="text-muted" style="font-size: 0.68rem;"><i class="fas fa-magic text-warning me-1"></i> يُولّد الرابط تلقائياً</small>
                                            </div>
                                            <input type="text" name="name_en" id="schoolNameEn" dir="ltr" class="form-control" value="<?= e($school['name_en'] ?? '') ?>" placeholder="Official school name in English">
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-1">
                                                <label class="form-label mb-0">
                                                    <i class="fas fa-link text-primary me-1"></i> الرابط المخصص لصفحة المدرسة (Slug) <span class="text-danger">*</span>
                                                </label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-primary fw-bold" id="btnAutoGenerateSlug" style="font-size: 0.72rem;">
                                                        <i class="fas fa-wand-magic-sparkles text-warning me-1"></i> توليد تلقائي من الاسم
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="input-group" dir="ltr">
                                                <span class="input-group-text bg-light text-muted font-monospace" style="font-size: 0.74rem;">https://daleel.schoplus.sd/school/</span>
                                                <input type="text" name="slug" id="schoolSlugInput" required pattern="[a-zA-Z0-9\-]{3,100}" class="form-control font-monospace fw-bold text-primary" value="<?= e($school['slug']) ?>" placeholder="school-slug-name" autocomplete="off">
                                                <span class="input-group-text bg-white" id="slugStatusIcon" style="min-width: 44px; text-align: center;">
                                                    <i class="fas fa-check-circle text-success" title="الرابط متاح"></i>
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-1">
                                                <small class="text-muted d-block" style="font-size: 0.7rem;">
                                                    يُكتب بالأحرف الإنجليزية والأرقام والشرطة الوسطية (-) فقط. الرابط المختصر: <span dir="ltr" class="text-primary fw-bold">daleel.schoplus.sd/s/<span id="slugPreviewText"><?= e($school['slug']) ?></span></span>
                                                </small>
                                                <div id="slugFeedback" class="small fw-bold" style="font-size: 0.72rem;"></div>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">نوع المدرسة</label>
                                            <select name="school_type" class="form-select">
                                                <?php foreach (SudanGeography::schoolTypes() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= $school['school_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">نوع الطلاب</label>
                                            <select name="gender_type" class="form-select">
                                                <?php foreach (SudanGeography::genderTypes() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= $school['gender_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">المنهج الدراسي</label>
                                            <select name="curriculum" class="form-select">
                                                <?php foreach (SudanGeography::curricula() as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= $school['curriculum'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <i class="fas fa-landmark text-warning me-1"></i> سنة / تاريخ التأسيس
                                            </label>
                                            <input type="text" name="established_year" class="form-control" value="<?= e($school['established_year'] ?? '') ?>" placeholder="مثال: 2012 أو عام 1998">
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ بيانات الهوية والتصنيف</h6>
                                        <small class="text-muted">يتم تطبيق التحديثات على الاسم والنوع والمناهج وسنة التأسيس مباشرة</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ بيانات الهوية والتصنيف
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 2: LOCATION & ADDRESS -->
                        <div class="tab-pane fade <?= $activeSubTab === 'location' ? 'show active' : '' ?>" id="subtab-location" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabLocation">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_location">
                                <input type="hidden" name="subtab" value="location">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #FEF2F2; color: #DC2626;">
                                                <i class="fas fa-map-marked-alt"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">2. الموقع الجغرافي والعنوان على الخريطة</h5>
                                                <p class="dash-sec-desc">حدد ولاية ومدينة ومقر المدرسة بدقة لسهولة وصول أولياء الأمور والطلاب.</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill small fw-bold">العنوان والخرائط</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">الدولة <span class="text-danger">*</span></label>
                                            <select name="country" id="dashCountry" class="form-select">
                                                <?php foreach ($countriesList as $cntry): ?>
                                                    <option value="<?= e($cntry) ?>" <?= ($school['country'] ?? 'السودان') === $cntry ? 'selected' : '' ?>><?= e($cntry) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">الولاية <span class="text-danger">*</span></label>
                                            <select name="state" id="dashState" class="form-select">
                                                <?php foreach ($statesWithCities as $stName => $cities): ?>
                                                    <option value="<?= e($stName) ?>" <?= $school['state'] === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">المدينة / المحلية <span class="text-danger">*</span></label>
                                            <select name="city" id="dashCity" class="form-select">
                                                <option value="<?= e($school['city']) ?>" selected><?= e($school['city']) ?></option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">الحي أو المنطقة</label>
                                            <input type="text" name="district" class="form-control" value="<?= e($school['district'] ?? '') ?>" placeholder="مثال: الرياض، المعمورة، الثورة، الصافية">
                                        </div>

                                        <div class="col-md-7">
                                            <label class="form-label">العنوان التفصيلي للمقر</label>
                                            <input type="text" name="address" class="form-control" value="<?= e($school['address'] ?? '') ?>" placeholder="اسم الشارع، المعالم البارزة المجاورة...">
                                        </div>

                                        <div class="col-md-5">
                                            <label class="form-label">رابط الموقع على Google Maps</label>
                                            <input type="url" name="google_map_url" dir="ltr" class="form-control" value="<?= e($school['google_map_url'] ?? '') ?>" placeholder="https://maps.app.goo.gl/...">
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ بيانات الموقع والعنوان</h6>
                                        <small class="text-muted">يتم تحديث الولاية والمدينة والعنوان ورابط الخريطة فوراً</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ بيانات الموقع والعنوان
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 3: ACADEMIC STAGES & FACILITIES -->
                        <div class="tab-pane fade <?= $activeSubTab === 'academic' ? 'show active' : '' ?>" id="subtab-academic" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabAcademic">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_academic">
                                <input type="hidden" name="subtab" value="academic">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                                <i class="fas fa-cubes"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">3. المراحل التعليمية المتوفرة والمرافق المدرسية</h5>
                                                <p class="dash-sec-desc">حدد المراحل الدراسية والخدمات والتجهيزات المتاحة بمدرستكم لإبرازها في بطاقة المدرسة.</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill small fw-bold">المراحل والخدمات</span>
                                    </div>

                                    <!-- Stages Sub-block -->
                                    <div class="mb-4">
                                        <label class="form-label d-block fw-bold mb-2 text-dark">
                                            <i class="fas fa-layer-group text-primary me-1"></i> المراحل التعليمية المتوفرة بالمدرسة:
                                        </label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach (SudanGeography::stages() as $stgKey => $stgLabel): ?>
                                                <label class="stage-check-pill" for="d_stg_<?= $stgKey ?>">
                                                    <input class="form-check-input mt-0" type="checkbox" name="stages[]" value="<?= $stgKey ?>" id="d_stg_<?= $stgKey ?>" <?= in_array($stgKey, $currentStages) ? 'checked' : '' ?>>
                                                    <span><?= $stgLabel ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <hr class="my-4" style="border-color: #F1EAE0;">

                                    <!-- Facilities Sub-block -->
                                    <div>
                                        <label class="form-label d-block fw-bold mb-2 text-dark">
                                            <i class="fas fa-check-circle text-success me-1"></i> المرافق والخدمات والتجهيزات المتاحة:
                                        </label>
                                        <div class="row g-2">
                                            <?php foreach (SudanGeography::facilitiesList() as $facKey => $facData): ?>
                                                <div class="col-sm-6 col-md-4">
                                                    <label class="fac-check-item w-100" for="fac_<?= $facKey ?>">
                                                        <input class="form-check-input mt-0" type="checkbox" name="facilities[]" value="<?= $facKey ?>" id="fac_<?= $facKey ?>" <?= in_array($facKey, $currentFacilities) ? 'checked' : '' ?>>
                                                        <i class="<?= $facData['icon'] ?> text-primary"></i>
                                                        <span><?= $facData['label'] ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ المراحل والمرافق المدرسية</h6>
                                        <small class="text-muted">يتم تحديث المراحل التعليمية والتجهيزات المتاحة فوراً</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ المراحل والمرافق
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 4: CONTACT & WORKING HOURS -->
                        <div class="tab-pane fade <?= in_array($activeSubTab, ['contact', 'fees_contact']) ? 'show active' : '' ?>" id="subtab-contact" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabContact">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_contact">
                                <input type="hidden" name="subtab" value="contact">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #E0F2FE; color: #0284C7;">
                                                <i class="fas fa-phone-alt"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">4. قنوات الاتصال والدوام المدرسي</h5>
                                                <p class="dash-sec-desc">أرقام الهواتف الرسمية، واتساب للتواصل السريع، البريد الإلكتروني، وساعات وأيام العمل بالمدرسة.</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill small fw-bold">الاتصال والدوام</span>
                                    </div>

                                    

                                    <!-- Contacts and Working Hours Fields -->
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">هاتف المدرسة للتواصل <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" required class="form-control" value="<?= e($school['phone']) ?>" placeholder="0912345678">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><i class="fab fa-whatsapp text-success me-1"></i> رقم واتساب للتواصل السريع</label>
                                            <input type="tel" name="whatsapp" class="form-control" value="<?= e($school['whatsapp'] ?? '') ?>" placeholder="0912345678">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">البريد الإلكتروني الرسمي</label>
                                            <input type="email" name="email" class="form-control" value="<?= e($school['email'] ?? '') ?>" placeholder="info@school.sd">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">أوقات وساعات الدوام الرسمي</label>
                                            <input type="text" name="working_hours" class="form-control" value="<?= e($school['working_hours'] ?? '') ?>" placeholder="مثال: من الأحد إلى الخميس 7:30 ص - 2:30 م">
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ بيانات قنوات التواصل والدوام</h6>
                                        <small class="text-muted">يتم تطبيق تحديثات أرقام الهاتف والواتساب والبريد وساعات الدوام مباشرة</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ قنوات الاتصال والدوام
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 5: ABOUT, VISION & MISSION -->
                        <div class="tab-pane fade <?= $activeSubTab === 'about' ? 'show active' : '' ?>" id="subtab-about" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabAbout">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_about">
                                <input type="hidden" name="subtab" value="about">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #F0F9FF; color: #0284C7;">
                                                <i class="fas fa-align-right"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">5. النبذة التعريفية والرؤية والرسالة</h5>
                                                <p class="dash-sec-desc">المحتوى الترويجي والتربوي الذي يقرأه أولياء الأمور للتعرف على أهداف وقيم وبيئة المدرسة.</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-1 rounded-pill small fw-bold">النبذة والرؤية</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">نبذة تعريفية شاملة عن المدرسة</label>
                                            <textarea name="description" rows="4" class="form-control" placeholder="اكتب نبذة تشرح تاريخ المدرسة، البيئة التعليمية، الكادر التدريسي، والأنشطة والبرامج الخاصة..."><?= e($school['description'] ?? '') ?></textarea>
                                            <small class="text-muted d-block mt-1" style="font-size: 0.71rem;">يظهر هذا النص كبطاقة تعريفية رئيسية في مقدمة صفحة مدرستكم بالدليل.</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">رؤية المدرسة (Vision)</label>
                                            <textarea name="vision" rows="3" class="form-control" placeholder="الريادة والتميز في تقديم بيئة تربوية رائدة..."><?= e($school['vision'] ?? '') ?></textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">رسالة المدرسة (Mission)</label>
                                            <textarea name="mission" rows="3" class="form-control" placeholder="تقديم تعليم نوعي يبني شخصية الطالب ويواكب متطلبات العصر..."><?= e($school['mission'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ النبذة والرؤية والرسالة</h6>
                                        <small class="text-muted">يتم تحديث النبذة التعريفية ورؤية ورسالة المدرسة فوراً</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ النبذة والرؤية
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 6: SOCIAL MEDIA & DIGITAL LINKS -->
                        <div class="tab-pane fade <?= $activeSubTab === 'social' ? 'show active' : '' ?>" id="subtab-social" role="tabpanel">
                            <form method="POST" action="dashboard.php" id="formSubtabSocial">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_social">
                                <input type="hidden" name="subtab" value="social">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                                <i class="fas fa-share-alt"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">6. المنصات الرقمية وروابط التواصل الاجتماعي</h5>
                                                <p class="dash-sec-desc">روابط صفحات وحسابات مدرستكم الرسمية على شبكات التواصل (الحقول الفارغة لا تظهر للزوار).</p>
                                            </div>
                                        </div>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill small fw-bold">المنصات الرقمية</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-globe text-primary me-1"></i> الموقع الإلكتروني للمدرسة</label>
                                            <input type="url" name="website" dir="ltr" class="form-control" value="<?= e($school['website'] ?? '') ?>" placeholder="https://myschool.com">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-facebook text-primary me-1"></i> رابط صفحة فيسبوك (Facebook)</label>
                                            <input type="url" name="facebook" dir="ltr" class="form-control" value="<?= e($school['facebook'] ?? '') ?>" placeholder="https://facebook.com/myschool">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><i class="fab fa-x-twitter text-dark me-1"></i> منصة إكس / تويتر (X / Twitter)</label>
                                            <input type="url" name="twitter" dir="ltr" class="form-control" value="<?= e($school['twitter'] ?? '') ?>" placeholder="https://x.com/myschool">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><i class="fab fa-youtube text-danger me-1"></i> قناة يوتيوب (YouTube)</label>
                                            <input type="url" name="youtube" dir="ltr" class="form-control" value="<?= e($school['youtube'] ?? '') ?>" placeholder="https://youtube.com/@myschool">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label"><i class="fab fa-telegram text-info me-1"></i> قناة تيليجرام (Telegram)</label>
                                            <input type="url" name="telegram" dir="ltr" class="form-control" value="<?= e($school['telegram'] ?? '') ?>" placeholder="https://t.me/myschool">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-instagram text-danger me-1"></i> حساب انستغرام (Instagram)</label>
                                            <input type="url" name="instagram" dir="ltr" class="form-control" value="<?= e($school['instagram'] ?? '') ?>" placeholder="https://instagram.com/myschool">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-tiktok text-dark me-1"></i> حساب تيك توك (TikTok)</label>
                                            <input type="url" name="tiktok" dir="ltr" class="form-control" value="<?= e($school['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@myschool">
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ روابط المنصات الرقمية</h6>
                                        <small class="text-muted">يتم تحديث روابط التواصل والموقع الإلكتروني مباشرة</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ المنصات الرقمية
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTAB 7: QUICK MEDIA (LOGO & COVER) -->
                        <div class="tab-pane fade <?= $activeSubTab === 'media' ? 'show active' : '' ?>" id="subtab-media" role="tabpanel">
                            <form method="POST" action="dashboard.php" enctype="multipart/form-data" id="formSubtabMedia">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_profile_media">
                                <input type="hidden" name="subtab" value="media">

                                <div class="dash-card-section">
                                    <div class="dash-sec-header">
                                        <div class="dash-sec-title-wrap">
                                            <div class="dash-sec-icon" style="background: #FDF2F8; color: #DB2777;">
                                                <i class="fas fa-images"></i>
                                            </div>
                                            <div>
                                                <h5 class="dash-sec-title">7. الهوية البصرية السريعة (الشعار والغلاف)</h5>
                                                <p class="dash-sec-desc">تحديث سريع لشعار وغلاف المدرسة، أو الانتقال لتبويب المعرض لإدارة صور المدرسة وفصولها بالكامل.</p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" onclick="document.getElementById('dash-media-tab').click()">
                                            <i class="fas fa-camera-retro me-1"></i> إدارة معرض الصور بالكامل (<?= count($galleryList) ?> صور)
                                        </button>
                                    </div>

                                    <div class="row g-4 align-items-center">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">شعار المدرسة الرسمي (Logo)</label>
                                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 border">
                                                <div style="width: 60px; height: 60px; flex-shrink: 0;" class="bg-white rounded-2 border p-1 d-flex align-items-center justify-content-center">
                                                    <img src="<?= e(defaultSchoolLogo($school['logo_path'] ?? null)) ?>" alt="" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <input type="file" name="logo" accept="image/*" class="form-control form-control-sm">
                                                    <small class="text-muted d-block mt-1" style="font-size: 0.68rem;">صورة مربعة واضحة (PNG أو JPG)</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">صورة غلاف الواجهة (Cover Banner)</label>
                                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 border">
                                                <div style="width: 80px; height: 50px; flex-shrink: 0;" class="bg-dark rounded-2 border overflow-hidden d-flex align-items-center justify-content-center">
                                                    <?php if (!empty($school['cover_path'])): ?>
                                                        <img src="<?= e(assetUrl($school['cover_path'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <i class="fas fa-image text-white-50"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <input type="file" name="cover" accept="image/*" class="form-control form-control-sm">
                                                    <small class="text-muted d-block mt-1" style="font-size: 0.68rem;">صورة أفقية عريضة (1200 × 400 بكسل)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ وتحديث الشعار والغلاف</h6>
                                        <small class="text-muted">يتم رفع وتطبيق الشعار وصورة الغلاف فوراً</small>
                                    </div>
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-save me-1"></i> حفظ وتحديث الشعار والغلاف
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

                <!-- TAB: FEES & DISCOUNTS (الرسوم الدراسية والعروض والخصومات) -->
                <div class="tab-pane fade <?= $activeDashTab === 'fees' ? 'show active' : '' ?>" id="dash-fees" role="tabpanel">
                    
                    <form method="POST" action="dashboard.php" id="formMainFees">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_school_fees">
                        <input type="hidden" name="tab" value="fees">

                        <!-- Header & Quick Preview Action -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #EFF6FF; color: var(--brand-blue);">
                                        <i class="fas fa-coins fs-5"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0">الرسوم الدراسية والتسهيلات المالية</h5>
                                        <span class="text-muted small">إدارة وتحديد جداول الرسوم للمراحل التعليمية والعروض الترويجية والمنح</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="/school/<?= urlencode($school['slug']) ?>#tab-fees" target="_blank" class="btn-luxury-outline btn-sm px-3 py-1">
                                    <i class="fas fa-external-link-alt me-1"></i> صفحة الرسوم العامة
                                </a>
                            </div>
                        </div>

                        <!-- 2. Mode Selection & Approx Fees -->
                        <div class="dash-card-section">
                            <div class="dash-sec-header">
                                <div class="dash-sec-title-wrap">
                                    <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                        <i class="fas fa-sliders-h"></i>
                                    </div>
                                    <div>
                                        <h5 class="dash-sec-title">1. نظام وطريقة عرض الرسوم</h5>
                                        <p class="dash-sec-desc">اختر ما إذا كنت ترغب في عرض رقم متوسط سريع، أو جدول تفصيلي كامل لكل مرحلة وصف دراسي.</p>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark border px-3 py-1 rounded-pill small fw-bold">نظام الرسوم</span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="card h-100 p-3 border rounded-3 cursor-pointer fee-mode-card" style="cursor: pointer;">
                                        <div class="d-flex align-items-start gap-3">
                                            <input class="form-check-input mt-1" type="radio" name="fees_type" value="average" id="feesTypeAvg" <?= $currentFeesType === 'average' ? 'checked' : '' ?> onchange="toggleFeesMode('average')">
                                            <div>
                                                <div class="fw-bold text-dark"><i class="fas fa-calculator text-primary me-1"></i> الخيار 1: متوسط تقريبي عام</div>
                                                <small class="text-muted d-block mt-1">عرض رقم أو جملة توضح متوسط الرسوم الإجمالية للمدرسة (مناسب للبساطة والسرعة).</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="card h-100 p-3 border rounded-3 cursor-pointer fee-mode-card" style="cursor: pointer;">
                                        <div class="d-flex align-items-start gap-3">
                                            <input class="form-check-input mt-1" type="radio" name="fees_type" value="detailed" id="feesTypeDet" <?= $currentFeesType === 'detailed' ? 'checked' : '' ?> onchange="toggleFeesMode('detailed')">
                                            <div>
                                                <div class="fw-bold text-dark"><i class="fas fa-list-ol text-success me-1"></i> الخيار 2: تفصيل الرسوم حسب المراحل والبنود</div>
                                                <small class="text-muted d-block mt-1">تحديد رسوم كل مرحلة أو صف دراسي بشكل جدول منظم مع توضيح شروط السداد والأقساط.</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Average Fees Input Box -->
                            <div id="feesAverageBox" class="mb-2">
                                <label class="form-label fw-bold">
                                    <span id="approxFeesLabel"><?= $currentFeesType === 'detailed' ? 'متوسط الرسوم السنوية الإجمالي (ملخص لكروت البحث):' : 'متوسط الرسوم الدراسية السنوية:' ?></span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-tag text-muted"></i></span>
                                    <input type="text" name="approx_fees" class="form-control" value="<?= e($school['approx_fees'] ?? '') ?>" placeholder="مثال: تبدأ من 250,000 ج.س سنوياً (أو 400,000 ج.س)">
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                    يظهر هذا النص كملخص سريع في بطاقات المدرسة بالصفحة الرئيسية ونتائج البحث.
                                </small>
                            </div>
                        </div>

                        <!-- 3. Detailed Stage Fees Builder Box -->
                        <div class="dash-card-section" id="feesDetailedBox" style="<?= $currentFeesType === 'detailed' ? '' : 'display: none;' ?>">
                            <div class="dash-sec-header">
                                <div class="dash-sec-title-wrap">
                                    <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                        <i class="fas fa-table-list"></i>
                                    </div>
                                    <div>
                                        <h5 class="dash-sec-title">2. جدول بنود ورسوم المراحل والصفوف الدراسية</h5>
                                        <p class="dash-sec-desc">أضف بنود الرسوم لكل مرحلة تعليمية أو صف دراسي، مع تحديد المبلغ وطريقة السداد والأقساط.</p>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary fw-bold px-3 rounded-pill" style="background: #1E5FA8; border-color: #1E5FA8;" onclick="addStageFeeRow()">
                                    <i class="fas fa-plus me-1"></i> إضافة بند دراسي جديد
                                </button>
                            </div>

                            <div class="table-responsive bg-white rounded-3 border">
                                <table class="table table-hover align-middle mb-0" id="stageFeesTable">
                                    <thead class="table-light">
                                        <tr style="font-size: 0.73rem;">
                                            <th style="min-width: 160px; padding: 10px;">المرحلة الدراسية / البند</th>
                                            <th style="min-width: 180px; padding: 10px;">البيان أو الصف الدراسي</th>
                                            <th style="min-width: 140px; padding: 10px;">قيمة الرسوم</th>
                                            <th style="min-width: 170px; padding: 10px;">طريقة السداد / ملاحظات</th>
                                            <th style="width: 45px; text-align: center; padding: 10px;">حذف</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stageFeesTbody">
                                        <?php if (!empty($currentStageFees)): ?>
                                            <?php foreach ($currentStageFees as $item): ?>
                                            <tr class="fee-row-item">
                                                <td style="min-width: 160px; padding: 8px 10px;">
                                                    <select name="stage_fee_stage[]" class="form-select form-select-sm">
                                                        <?php 
                                                        $stgVal = $item['stage'] ?? '';
                                                        $optList = ['رياض الأطفال', 'المرحلة الابتدائية', 'المرحلة المتوسطة', 'المرحلة الثانوية', 'رسوم التسجيل والزي', 'رسوم الترحيل والمواصلات', 'أخرى / بند مخصص'];
                                                        foreach ($optList as $opt):
                                                        ?>
                                                        <option value="<?= e($opt) ?>" <?= $stgVal === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td style="min-width: 180px; padding: 8px 10px;">
                                                    <input type="text" name="stage_fee_title[]" value="<?= e($item['title'] ?? '') ?>" class="form-control form-control-sm" placeholder="مثال: الصفوف 1 إلى 6">
                                                </td>
                                                <td style="min-width: 140px; padding: 8px 10px;">
                                                    <input type="text" name="stage_fee_amount[]" value="<?= e($item['amount'] ?? '') ?>" class="form-control form-control-sm fw-bold text-success" placeholder="مثال: 350,000 ج.س">
                                                </td>
                                                <td style="min-width: 170px; padding: 8px 10px;">
                                                    <input type="text" name="stage_fee_notes[]" value="<?= e($item['notes'] ?? '') ?>" class="form-control form-control-sm" placeholder="مثال: على 3 أقساط">
                                                </td>
                                                <td style="width: 45px; text-align: center; padding: 8px 10px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-2" onclick="this.closest('tr').remove()" title="حذف البند">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">
                                <i class="fas fa-info-circle text-primary me-1"></i> يمكنك إضافة رسوم كل مرحلة دراسية، أو إضافة بنود إضافية مثل رسوم التسجيل، الزي المدرسي، أو الترحيل.
                            </small>
                        </div>

                        <!-- 4. Discounts & Special Offers Builder -->
                        <div class="dash-card-section" style="background: #FFFFFF;">
                            <div class="dash-sec-header">
                                <div class="dash-sec-title-wrap">
                                    <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                        <i class="fas fa-tags"></i>
                                    </div>
                                    <div>
                                        <h5 class="dash-sec-title">3. العروض والخصومات والتسهيلات المالية (اختياري)</h5>
                                        <p class="dash-sec-desc">أضف الخصومات والمنح التي تقدمها مدرستك لجذب أولياء الأمور (مثل: خصم الإخوة، السداد المبكر، حفظة القرآن، أو إعفاء رسوم التسجيل).</p>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm fw-bold px-3 rounded-pill" style="color: #1E5FA8; border: 1.5px solid #1E5FA8; background: #FFFFFF;" onclick="addDiscountRow()">
                                    <i class="fas fa-plus me-1"></i> إضافة عرض / خصم جديد
                                </button>
                            </div>

                            <!-- Quick Presets -->
                            <div class="mb-3 p-3 rounded-3 bg-white border d-flex flex-wrap gap-2 align-items-center">
                                <span class="text-muted fw-bold d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-magic text-warning"></i> نماذج سريعة جاهزة:
                                </span>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2 text-dark" style="font-size: 0.72rem;" onclick="addQuickDiscount('خصم الإخوة والأشقاء', '15%', 'الأخ الثاني فما فوق', 'يطبق على الرسوم الدراسية السنوية')">
                                    <i class="fas fa-users text-primary me-1"></i> خصم الإخوة (15%)
                                </button>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2 text-dark" style="font-size: 0.72rem;" onclick="addQuickDiscount('خصم السداد المبكر (كاش)', '10%', 'جميع المراحل', 'عند سداد كامل الرسوم دفعة واحدة قبل بدء العام')">
                                    <i class="fas fa-bolt text-warning me-1"></i> سداد مبكر كاش (10%)
                                </button>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2 text-dark" style="font-size: 0.72rem;" onclick="addQuickDiscount('منحة التفوق وحفظ القرآن', '25% - 50%', 'الطلاب المتفوقين', 'بناءً على النتيجة والشهادة المعتمدة')">
                                    <i class="fas fa-award text-success me-1"></i> منحة التفوق والقرآن
                                </button>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2 text-dark" style="font-size: 0.72rem;" onclick="addQuickDiscount('خصم أبناء الكوادر التعليمية', '20%', 'أبناء المعلمين والعاملين', 'تسهيلات مخصصة لأبناء الكادر التعليمي')">
                                    <i class="fas fa-chalkboard-teacher text-info me-1"></i> أبناء المعلمين (20%)
                                </button>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2 text-dark" style="font-size: 0.72rem;" onclick="addQuickDiscount('عرض التسجيل المبكر', 'إعفاء رسوم التسجيل', 'الطلاب الجدد', 'يسري للطلبات المسجلة قبل بداية الشهر')">
                                    <i class="fas fa-gift text-danger me-1"></i> إعفاء رسوم التسجيل
                                </button>
                            </div>

                            <!-- Discounts Table -->
                            <div class="table-responsive bg-white rounded-3 border">
                                <table class="table table-hover align-middle mb-0" id="discountsTable">
                                    <thead class="table-light">
                                        <tr style="font-size: 0.73rem;">
                                            <th style="min-width: 170px; padding: 10px;">عنوان العرض / الخصم <span class="text-danger">*</span></th>
                                            <th style="min-width: 130px; padding: 10px;">قيمة الخصم (نسبة أو مبلغ) <span class="text-danger">*</span></th>
                                            <th style="min-width: 150px; padding: 10px;">الفئة المستفيدة / ينطبق على</th>
                                            <th style="min-width: 180px; padding: 10px;">الشروط والملاحظات</th>
                                            <th style="width: 45px; text-align: center; padding: 10px;">حذف</th>
                                        </tr>
                                    </thead>
                                    <tbody id="discountsTbody">
                                        <?php if (!empty($currentFeeDiscounts)): ?>
                                            <?php foreach ($currentFeeDiscounts as $disc): ?>
                                            <tr class="discount-row-item">
                                                <td style="min-width: 170px; padding: 8px 10px;">
                                                    <input type="text" name="discount_title[]" value="<?= e($disc['title'] ?? '') ?>" required class="form-control form-control-sm fw-bold text-dark" placeholder="مثال: خصم الإخوة والأشقاء">
                                                </td>
                                                <td style="min-width: 130px; padding: 8px 10px;">
                                                    <input type="text" name="discount_value[]" value="<?= e($disc['value'] ?? '') ?>" required class="form-control form-control-sm fw-bold" style="color: #1E5FA8;" placeholder="مثال: 15% أو 50,000 ج.س">
                                                </td>
                                                <td style="min-width: 150px; padding: 8px 10px;">
                                                    <input type="text" name="discount_applies_to[]" value="<?= e($disc['applies_to'] ?? '') ?>" class="form-control form-control-sm" placeholder="مثال: الأخ الثاني فما فوق">
                                                </td>
                                                <td style="min-width: 180px; padding: 8px 10px;">
                                                    <input type="text" name="discount_notes[]" value="<?= e($disc['notes'] ?? '') ?>" class="form-control form-control-sm" placeholder="مثال: يطبق عند سداد القسط كاملاً">
                                                </td>
                                                <td style="width: 45px; text-align: center; padding: 8px 10px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-2" onclick="this.closest('tr').remove()" title="حذف العرض">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">
                                <i class="fas fa-info-circle text-primary me-1"></i> تظهر هذه العروض في كروت مميزة في تبويب الرسوم بصفحة المدرسة لتشجيع أولياء الأمور على التقديم.
                            </small>
                        </div>

                        <!-- Save Card -->
                        <div class="dash-card-section bg-light border p-3 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-circle text-success me-1"></i> حفظ وتحديث الرسوم الدراسية والعروض والخصومات</h6>
                                <small class="text-muted">يتم تطبيق جدول الرسوم والعروض فوراً على صفحة المدرسة بالدليل</small>
                            </div>
                            <button type="submit" class="btn-luxury-primary px-4 py-2">
                                <i class="fas fa-save me-1"></i> حفظ وتحديث الرسوم والعروض
                            </button>
                        </div>

                    </form>
                </div>

                <!-- TAB: MEDIA & PHOTO GALLERY -->
                <div class="tab-pane fade <?= $activeDashTab === 'media' ? 'show active' : '' ?>" id="dash-media" role="tabpanel">
                    
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 bg-light rounded-4 border">
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><i class="fas fa-camera-retro text-primary me-2"></i> الهوية البصرية ومعرض صور مرافق المدرسة</h6>
                            <small class="text-muted">قم برفع وتحديث شعار مدرستك وغلاف الواجهة وصور المرافق والفصول والأنشطة لجذب أولياء الأمور.</small>
                        </div>
                        <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold" style="font-size:0.85rem">معرض الصور: <?= count($galleryList) ?> / 8 صور</span>
                    </div>

                    <!-- Row 1: Logo & Cover Cards -->
                    <div class="row g-4 mb-4">
                        <!-- Card 1: Official Logo -->
                        <div class="col-lg-5">
                            <div class="card border rounded-4 shadow-sm p-4 h-100 bg-white">
                                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center justify-content-between">
                                    <span><i class="fas fa-shield-alt text-primary me-2"></i> الشعار الرسمي للمدرسة (Logo)</span>
                                    <?php if (!empty($school['logo_path'])): ?>
                                        <span class="badge bg-success-subtle text-success small fw-bold">مرفوع</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning small fw-bold">غير مرفوع</span>
                                    <?php endif; ?>
                                </h6>

                                <div class="text-center my-3 p-3 bg-light rounded-3 border">
                                    <?php if (!empty($school['logo_path'])): ?>
                                        <img src="<?= e(assetUrl($school['logo_path'])) ?>" alt="الشعار" style="max-height: 110px; max-width: 100%; object-fit: contain;">
                                    <?php else: ?>
                                        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm text-primary fw-bold" style="width: 80px; height: 80px; font-size: 2rem;">
                                            <?= mb_substr($school['name'], 0, 1) ?>
                                        </div>
                                        <div class="small text-muted mt-2">لا يوجد شعار حالياً</div>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="mt-auto">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="upload_logo">
                                    <label class="form-label small fw-bold text-secondary">اختر ملف الشعار (PNG / JPG / WebP):</label>
                                    <div class="input-group mb-2">
                                        <input type="file" name="logo" accept="image/*" required class="form-control form-control-sm">
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                            <i class="fas fa-upload me-1"></i> رفع الشعار
                                        </button>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">يفضل مقاس مربع لا يقل عن 300 × 300 بكسل بخلفية شفافة أو بيضاء.</small>
                                </form>

                                <?php if (!empty($school['logo_path'])): ?>
                                    <form method="POST" action="dashboard.php" class="mt-2 text-start" onsubmit="return confirm('هل أنت متأكد من حذف الشعار؟');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_logo">
                                        <button type="submit" class="btn btn-link text-danger p-0 small text-decoration-none">
                                            <i class="fas fa-trash-alt me-1"></i> حذف الشعار الحالي
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card 2: Cover Photo Banner -->
                        <div class="col-lg-7">
                            <div class="card border rounded-4 shadow-sm p-4 h-100 bg-white">
                                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center justify-content-between">
                                    <span><i class="fas fa-panorama text-primary me-2"></i> صورة غلاف الواجهة (Cover Banner)</span>
                                    <?php if (!empty($school['cover_path'])): ?>
                                        <span class="badge bg-success-subtle text-success small fw-bold">مرفوع</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary small fw-bold">غلاف افتراضي</span>
                                    <?php endif; ?>
                                </h6>

                                <div class="my-3 rounded-3 border overflow-hidden position-relative" style="height: 110px; background: #0B2A52;">
                                    <?php if (!empty($school['cover_path'])): ?>
                                        <img src="<?= e(assetUrl($school['cover_path'])) ?>" alt="الغلاف" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50 small">
                                            <i class="fas fa-image me-2"></i> غلاف الواجهة البانورامي الافتراضي
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="mt-auto">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="upload_cover">
                                    <label class="form-label small fw-bold text-secondary">اختر صورة الغلاف العريضة:</label>
                                    <div class="input-group mb-2">
                                        <input type="file" name="cover" accept="image/*" required class="form-control form-control-sm">
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                            <i class="fas fa-upload me-1"></i> رفع الغلاف
                                        </button>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">المقاس المثالي: 1200 × 400 بكسل لمظهر جذاب على كافة شاشات الحواسيب والهواتف.</small>
                                </form>

                                <?php if (!empty($school['cover_path'])): ?>
                                    <form method="POST" action="dashboard.php" class="mt-2 text-start" onsubmit="return confirm('هل أنت متأكد من حذف الغلاف؟');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_cover">
                                        <button type="submit" class="btn btn-link text-danger p-0 small text-decoration-none">
                                            <i class="fas fa-trash-alt me-1"></i> حذف الغلاف والعودة للافتراضي
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Campus Photo Gallery Card -->
                    <div class="card border rounded-4 shadow-sm p-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-images text-primary me-2"></i> معرض صور مرافق وفعاليات المدرسة (حتى 8 صور)</h6>
                                <p class="small text-muted mb-0">أظهر مباني المدرسة، الفصول الذكية، المعامل، الملاعب، والمسارح لإعطاء ولي الأمر تجربة بصرية واقعية.</p>
                            </div>
                            <span class="badge bg-secondary px-3 py-2 rounded-pill fw-bold">
                                المتبقي: <?= max(0, 8 - count($galleryList)) ?> صور
                            </span>
                        </div>

                        <!-- Add Photo Form (if < 8) -->
                        <?php if (count($galleryList) < 8): ?>
                            <div class="p-3 bg-light rounded-4 border mb-4">
                                <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_gallery_photo">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-secondary">اختر صورة المرفق / الفعالية</label>
                                            <input type="file" name="gallery_photo" accept="image/*" required class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-secondary">عنوان أو وصف الصورة (اختياري)</label>
                                            <input type="text" name="caption" class="form-control form-control-sm" placeholder="مثال: معمل العلوم والفيزياء، المسبح المدرسي، الفصول الذكية">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-2 d-flex align-items-center justify-content-center gap-1">
                                                <i class="fas fa-plus-circle"></i>
                                                <span>إضافة للمعرض</span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info border-0 rounded-3 small mb-4 py-2">
                                <i class="fas fa-info-circle me-1"></i> لقد أضفت الحد الأقصى (8 صور). إذا أردت استبدال أي صورة، قم بحذف إحداها أولاً.
                            </div>
                        <?php endif; ?>

                        <!-- Current Photos Grid -->
                        <?php if (empty($galleryList)): ?>
                            <div class="text-center py-5 bg-light rounded-4 border my-2">
                                <div class="display-6 text-muted mb-2"><i class="fas fa-images"></i></div>
                                <h6 class="fw-bold text-dark mb-1">المعرض فارغ حالياً</h6>
                                <p class="small text-muted mb-0">ابدأ الآن بإضافة صور من واقع مدرستك لجذب آلاف أولياء الأمور الذين يتصفحون الدليل يومياً.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($galleryList as $photo): ?>
                                    <div class="col-sm-6 col-md-3">
                                        <div class="card border rounded-3 overflow-hidden h-100 shadow-sm position-relative group-hover">
                                            <div style="height: 150px; overflow: hidden; background: #f8fafc;">
                                                <img src="<?= e(assetUrl($photo['path'])) ?>" alt="<?= e($photo['caption'] ?: 'مرفق مدرسي') ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                            <div class="p-2 bg-white d-flex align-items-center justify-content-between">
                                                <small class="text-dark fw-bold text-truncate" style="max-width: 130px;" title="<?= e($photo['caption'] ?: 'بدون عنوان') ?>">
                                                    <?= e($photo['caption'] ?: 'مرفق مدرسي') ?>
                                                </small>
                                                <form method="POST" action="dashboard.php" style="display:inline" onsubmit="return confirm('تأكيد حذف هذه الصورة من المعرض؟');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_gallery_photo">
                                                    <input type="hidden" name="photo_id" value="<?= e($photo['id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-2" style="font-size: 0.75rem;" title="حذف الصورة">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- TAB: OPTIONAL — BRANCHES (الفروع) -->
                <div class="tab-pane fade <?= $activeDashTab === 'branches' ? 'show active' : '' ?>" id="dash-branches" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 bg-light rounded-4 border">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-code-branch text-primary me-2"></i> إدارة فروع المدرسة</h6>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small">تبويب اختياري</span>
                                <?php if ($isBranchesTabEnabled && count($branchesList) > 0): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small"><i class="fas fa-eye me-1"></i> ظاهر للزوار</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill small"><i class="fas fa-eye-slash me-1"></i> مخفي عن الزوار<?= empty($branchesList) ? ' (فارغ)' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">أضف فروع مدرستك (اسم الفرع، العنوان، أرقام التواصل، ورابط الخريطة). إذا كانت القائمة فارغة أو معطلة لن يظهر التبويب في صفحة المدرسة العامة.</small>
                        </div>
                        <form method="POST" action="dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_optional_tab">
                            <input type="hidden" name="tab_key" value="branches">
                            <button type="submit" class="btn btn-sm <?= $isBranchesTabEnabled ? 'btn-outline-secondary' : 'btn-success' ?> rounded-pill px-3 fw-bold">
                                <i class="fas <?= $isBranchesTabEnabled ? 'fa-eye-slash' : 'fa-eye' ?> me-1"></i>
                                <?= $isBranchesTabEnabled ? 'إخفاء التبويب مؤقتاً' : 'تفعيل وإظهار التبويب' ?>
                            </button>
                        </form>
                    </div>

                    <!-- Add Branch Form -->
                    <div class="p-4 rounded-4 bg-light border mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-plus-circle text-primary me-1"></i> إضافة فرع جديد للمدرسة</h6>
                        <form method="POST" action="dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_branch">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">اسم الفرع <span class="text-danger">*</span></label>
                                    <input type="text" name="branch_name" required class="form-control" placeholder="مثال: الفرع الرئيسي - الخرطوم / فرع البنين - بحري">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">العنوان التفصيلي للفرع <span class="text-danger">*</span></label>
                                    <input type="text" name="branch_address" required class="form-control" placeholder="المدينة، الحي، الشارع، المعالم القريبة...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">أرقام التواصل والهاتف</label>
                                    <input type="text" name="branch_phone" class="form-control" placeholder="مثال: 0912345678 - 0123456789">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">رقم واتساب الفرع (اختياري)</label>
                                    <input type="text" name="branch_whatsapp" class="form-control" placeholder="مثال: +249912345678">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">رابط الخريطة (Google Maps)</label>
                                    <input type="url" name="branch_map_url" dir="ltr" class="form-control" placeholder="https://maps.app.goo.gl/...">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-plus-circle me-1"></i> إضافة الفرع
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Branches List -->
                    <h6 class="fw-bold text-dark mb-3">الفروع المضافة (<?= count($branchesList) ?>)</h6>
                    <?php if (empty($branchesList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-code-branch display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لم تقم بإضافة أي فروع بعد. لا يظهر تبويب الفروع للزوار طالما كان فارغاً.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($branchesList as $br): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-4 bg-white shadow-sm h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-building text-primary me-1"></i> <?= e($br['name'] ?? '') ?></h6>
                                                <form method="POST" action="dashboard.php" onsubmit="return confirm('تأكيد حذف هذا الفرع؟');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_branch">
                                                    <input type="hidden" name="branch_id" value="<?= e($br['id'] ?? '') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف الفرع">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <p class="text-secondary small mb-2"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= e($br['address'] ?? '') ?></p>
                                            <div class="d-flex flex-wrap gap-2 small">
                                                <?php if (!empty($br['phone'])): ?>
                                                    <span class="badge bg-light text-dark border"><i class="fas fa-phone-alt text-primary me-1"></i> <?= e($br['phone']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($br['whatsapp'])): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fab fa-whatsapp me-1"></i> <?= e($br['whatsapp']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($br['map_url'])): ?>
                                                    <a href="<?= e($br['map_url']) ?>" target="_blank" class="badge bg-primary-subtle text-primary text-decoration-none border border-primary-subtle">
                                                        <i class="fas fa-map-marked-alt me-1"></i> موقع الفرع على الخريطة
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB: OPTIONAL — ACTIVITIES (المناشط) -->
                <div class="tab-pane fade <?= $activeDashTab === 'activities' ? 'show active' : '' ?>" id="dash-activities" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 bg-light rounded-4 border">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-trophy text-primary me-2"></i> إدارة المناشط والأنشطة اللاصفية</h6>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small">تبويب اختياري</span>
                                <?php if ($isActivitiesTabEnabled && count($activitiesList) > 0): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small"><i class="fas fa-eye me-1"></i> ظاهر للزوار</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill small"><i class="fas fa-eye-slash me-1"></i> مخفي عن الزوار<?= empty($activitiesList) ? ' (فارغ)' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">استعرض المناشط الثقافية، الرياضية، العلمية، والفنية بالمدرسة. إذا كانت القائمة فارغة لن يظهر التبويب للزوار.</small>
                        </div>
                        <form method="POST" action="dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_optional_tab">
                            <input type="hidden" name="tab_key" value="activities">
                            <button type="submit" class="btn btn-sm <?= $isActivitiesTabEnabled ? 'btn-outline-secondary' : 'btn-success' ?> rounded-pill px-3 fw-bold">
                                <i class="fas <?= $isActivitiesTabEnabled ? 'fa-eye-slash' : 'fa-eye' ?> me-1"></i>
                                <?= $isActivitiesTabEnabled ? 'إخفاء التبويب مؤقتاً' : 'تفعيل وإظهار التبويب' ?>
                            </button>
                        </form>
                    </div>

                    <!-- Add Activity Form -->
                    <div class="p-4 rounded-4 bg-light border mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-plus-circle text-primary me-1"></i> إضافة منشط مدرسي جديد</h6>
                        <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_activity">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-secondary">اسم المنشط / النشاط <span class="text-danger">*</span></label>
                                    <input type="text" name="activity_title" required class="form-control" placeholder="مثال: الجمعية الأدبية والخطابة / نادي الروبوت / النشاط الرياضي">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">تصنيف المنشط</label>
                                    <select name="activity_category" class="form-select">
                                        <option value="ثقافي وأدبي">ثقافي وأدبي</option>
                                        <option value="رياضي وبدني">رياضي وبدني</option>
                                        <option value="علمي وتقني">علمي وتقني</option>
                                        <option value="فني وإبداعي">فني وإبداعي</option>
                                        <option value="ديني واجتماعي">ديني واجتماعي</option>
                                        <option value="رحلات وكشافة">رحلات وكشافة</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-secondary">موعد أو دورية النشاط (اختياري)</label>
                                    <input type="text" name="activity_schedule" class="form-control" placeholder="مثال: كل يوم خميس / أسبوعي">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-secondary">وصف وتفاصيل المنشط <span class="text-danger">*</span></label>
                                    <textarea name="activity_description" required rows="2" class="form-control" placeholder="اشرح أهداف المنشط والمهارات التي يكتسبها الطلاب..."></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">صورة للمنشط (اختياري)</label>
                                    <input type="file" name="activity_image" accept="image/*" class="form-control">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-plus-circle me-1"></i> إضافة المنشط
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Activities List -->
                    <h6 class="fw-bold text-dark mb-3">المناشط المضافة (<?= count($activitiesList) ?>)</h6>
                    <?php if (empty($activitiesList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-trophy display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لم تقم بإضافة أي مناشط بعد. لا يظهر تبويب المناشط للزوار طالما كان فارغاً.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($activitiesList as $act): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-4 bg-white shadow-sm h-100 d-flex gap-3 align-items-start justify-content-between">
                                        <div class="d-flex gap-3 align-items-start">
                                            <?php if (!empty($act['image_path'])): ?>
                                                <img src="<?= e(assetUrl($act['image_path'])) ?>" alt="<?= e($act['title'] ?? '') ?>" style="width: 72px; height: 72px; object-fit: cover;" class="rounded-3 border flex-shrink-0">
                                            <?php else: ?>
                                                <div class="rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px;">
                                                    <i class="fas fa-trophy fs-5"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                    <h6 class="fw-bold text-dark mb-0"><?= e($act['title'] ?? '') ?></h6>
                                                    <?php if (!empty($act['category'])): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"><?= e($act['category']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($act['schedule'])): ?>
                                                        <span class="badge bg-light text-muted border rounded-pill"><i class="far fa-clock me-1"></i> <?= e($act['schedule']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-secondary small mb-0"><?= e($act['description'] ?? '') ?></p>
                                            </div>
                                        </div>
                                        <form method="POST" action="dashboard.php" onsubmit="return confirm('تأكيد حذف هذا المنشط؟');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_activity">
                                            <input type="hidden" name="activity_id" value="<?= e($act['id'] ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف المنشط">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB: OPTIONAL — TEACHERS (المعلمون) -->
                <div class="tab-pane fade <?= $activeDashTab === 'teachers' ? 'show active' : '' ?>" id="dash-teachers" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 bg-light rounded-4 border">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> إدارة قائمة المعلمين والكادر التدريسي</h6>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small">تبويب اختياري</span>
                                <?php if ($isTeachersTabEnabled && count($teachersList) > 0): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small"><i class="fas fa-eye me-1"></i> ظاهر للزوار</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill small"><i class="fas fa-eye-slash me-1"></i> مخفي عن الزوار<?= empty($teachersList) ? ' (فارغ)' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">أضف نخبة معلمي المدرسة (اسم المعلم، المادة، المرحلة، والفصل). إذا كانت القائمة فارغة لن يظهر التبويب للزوار.</small>
                        </div>
                        <form method="POST" action="dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_optional_tab">
                            <input type="hidden" name="tab_key" value="teachers">
                            <button type="submit" class="btn btn-sm <?= $isTeachersTabEnabled ? 'btn-outline-secondary' : 'btn-success' ?> rounded-pill px-3 fw-bold">
                                <i class="fas <?= $isTeachersTabEnabled ? 'fa-eye-slash' : 'fa-eye' ?> me-1"></i>
                                <?= $isTeachersTabEnabled ? 'إخفاء التبويب مؤقتاً' : 'تفعيل وإظهار التبويب' ?>
                            </button>
                        </form>
                    </div>

                    <!-- Add Teacher Form -->
                    <div class="p-4 rounded-4 bg-light border mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-user-plus text-primary me-1"></i> إضافة معلم إلى الكادر التدريسي</h6>
                        <form method="POST" action="dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_teacher">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-secondary">اسم المعلم <span class="text-danger">*</span></label>
                                    <input type="text" name="teacher_name" required class="form-control" placeholder="مثال: أ. محمد عبد الله">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-secondary">المادة الدراسية <span class="text-danger">*</span></label>
                                    <input type="text" name="teacher_subject" required class="form-control" placeholder="مثال: الرياضيات / اللغة العربية">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-secondary">المرحلة الدراسية</label>
                                    <select name="teacher_stage" class="form-select">
                                        <option value="المرحلة الثانوية">المرحلة الثانوية</option>
                                        <option value="المرحلة المتوسطة">المرحلة المتوسطة</option>
                                        <option value="المرحلة الابتدائية">المرحلة الابتدائية</option>
                                        <option value="رياض الأطفال">رياض الأطفال</option>
                                        <option value="جميع المراحل">جميع المراحل</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-secondary">الفصل الدراسي</label>
                                    <input type="text" name="teacher_class" class="form-control" placeholder="مثال: الصف الثالث ثانوي - أ">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-luxury-primary px-4 py-2">
                                        <i class="fas fa-plus-circle me-1"></i> إضافة المعلم للقائمة
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Teachers Table / List -->
                    <h6 class="fw-bold text-dark mb-3">الكادر التدريسي المضاف (<?= count($teachersList) ?>)</h6>
                    <?php if (empty($teachersList)): ?>
                        <div class="text-center py-4 text-muted border rounded-3 bg-light">
                            <i class="fas fa-chalkboard-teacher display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small">لم تقم بإضافة معلمين بعد. لا يظهر تبويب المعلمين للزوار طالما كان فارغاً.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border small">
                                <thead class="table-light">
                                    <tr>
                                        <th>اسم المعلم</th>
                                        <th>المادة الدراسية</th>
                                        <th>المرحلة</th>
                                        <th>الفصل</th>
                                        <th class="text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachersList as $tch): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><i class="fas fa-user-tie text-primary me-1"></i> <?= e($tch['name'] ?? '') ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($tch['subject'] ?? '') ?></span></td>
                                            <td><?= e($tch['stage'] ?: '—') ?></td>
                                            <td><?= e($tch['class_name'] ?: '—') ?></td>
                                            <td class="text-center">
                                                <form method="POST" action="dashboard.php" style="display:inline;" onsubmit="return confirm('تأكيد حذف المعلم من القائمة؟');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_teacher">
                                                    <input type="hidden" name="teacher_id" value="<?= e($tch['id'] ?? '') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: NEWS & ANNOUNCEMENTS -->
                <div class="tab-pane fade <?= $activeDashTab === 'news' ? 'show active' : '' ?>" id="dash-news" role="tabpanel">
                    
                    <!-- Header with Add News Modal Trigger Button -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 bg-light rounded-4 border">
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><i class="fas fa-bullhorn text-primary me-2"></i> الأخبار والإعلانات المدرسية</h6>
                            <small class="text-muted">انشر مواعيد التسجيل، الفعاليات، تكريم الأوائل، والأنشطة بتنسيق Word متقدم لإبقاء أولياء الأمور على اطلاع دائم.</small>
                        </div>
                        <button type="button" class="btn btn-primary px-3 py-2 fw-bold d-inline-flex align-items-center gap-2 rounded-3 shadow-sm" onclick="openAddNewsModal()">
                            <i class="fas fa-plus-circle"></i> <span>إضافة خبر أو إعلان جديد</span>
                        </button>
                    </div>



                    <!-- News List Header -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold text-dark mb-0">الأخبار والإعلانات المنشورة (<?= count($newsList) ?>)</h6>
                        <span class="text-muted small">يمكنك تعديل أي خبر، تغيير عنوانه أو صوره وتنسيقه في أي وقت</span>
                    </div>

                    <?php if (empty($newsList)): ?>
                        <div class="text-center py-5 text-muted border rounded-3 bg-light">
                            <i class="fas fa-newspaper display-6 mb-2 text-secondary opacity-50"></i>
                            <p class="mb-0 small fw-bold">لم تقم بنشر أي أخبار أو إعلانات بعد.</p>
                            <span class="text-muted small">استخدم محرر النصوص المتقدم أعلاه لكتابة وتنسيق أول خبر لمدرستك!</span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($newsList as $item): ?>
                                <div class="p-3 border rounded-3 bg-white shadow-sm d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
                                    <div class="d-flex gap-3 flex-grow-1">
                                        <img src="<?= e(defaultNewsImage($item['image_path'] ?? null, $item['title'] ?? '', $school['name'] ?? '', $item['branch_name'] ?? '')) ?>" alt="صورة الخبر" style="width: 100px; height: 75px; object-fit: cover; flex-shrink: 0;" class="rounded-3 border shadow-xs">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"><?= e($item['title']) ?></h6>
                                                <span class="badge bg-light text-secondary border small">#<?= $item['id'] ?></span>
                                                <?php if (!empty($item['branch_name'])): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                                        <i class="fas fa-code-branch me-1"></i> <?= e($item['branch_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted d-block mb-2">
                                                <i class="far fa-calendar-alt me-1"></i> <?= date('Y-m-d H:i', strtotime($item['created_at'])) ?>
                                                • <i class="fas fa-eye me-1 ms-2"></i> <?= number_format($item['views_count']) ?> مشاهدة
                                            </small>
                                            <div class="text-secondary small mb-0 lh-base line-clamp-2" style="max-height: 48px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                                <?= strip_tags($item['content']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action buttons: View, Edit, Delete -->
                                    <div class="d-flex align-items-center gap-2 align-self-end align-self-md-start flex-shrink-0">
                                        <a href="/school/<?= urlencode($school['slug']) ?>#tab-news" target="_blank" class="btn btn-sm btn-outline-primary" title="معاينة في صفحة المدرسة">
                                            <i class="fas fa-external-link-alt me-1"></i> عرض
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="openEditNewsModal(<?= htmlspecialchars(json_encode([
                                            'id' => $item['id'],
                                            'title' => $item['title'],
                                            'branch_name' => $item['branch_name'] ?? '',
                                            'content' => $item['content'],
                                            'image_path' => assetUrl($item['image_path'])
                                        ]), ENT_QUOTES, 'UTF-8') ?>)" title="تعديل الخبر">
                                            <i class="fas fa-edit me-1"></i> تعديل
                                        </button>
                                        <form method="POST" action="dashboard.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا الخبر نهائياً؟');" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_news">
                                            <input type="hidden" name="news_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف الخبر">
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
                <div class="tab-pane fade <?= $activeDashTab === 'reviews' ? 'show active' : '' ?>" id="dash-reviews" role="tabpanel">
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
                <div class="tab-pane fade <?= $activeDashTab === 'inquiries' ? 'show active' : '' ?>" id="dash-inquiries" role="tabpanel">
                    <?php
                    $inqTotal = count($inquiriesList);
                    $inqNew = count(array_filter($inquiriesList, fn($i) => ($i['status'] ?? 'new') === 'new'));
                    $inqContacted = count(array_filter($inquiriesList, fn($i) => ($i['status'] ?? '') === 'contacted'));
                    $inqAccepted = count(array_filter($inquiriesList, fn($i) => ($i['status'] ?? '') === 'accepted'));
                    $inqRejected = count(array_filter($inquiriesList, fn($i) => ($i['status'] ?? '') === 'rejected'));
                    ?>

                    <!-- Header & Quick Action -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: #EFF6FF; color: var(--brand-blue);">
                                    <i class="fas fa-user-graduate fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">طلبات القبول والتسجيل</h5>
                                    <span class="text-muted small">متابعة وإدارة طلبات أولياء الأمور الواردة من صفحة المدرسة العامة في الدليل</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="/school/<?= urlencode($school['slug']) ?>#tab-admission" target="_blank" class="btn-luxury-outline btn-sm px-3 py-1">
                                <i class="fas fa-external-link-alt me-1"></i> صفحة القبول العامة
                            </a>
                        </div>
                    </div>

                    <!-- Sub-View Switcher: Inquiries List vs Admission Settings -->
                    <div class="d-flex align-items-center gap-2 mb-4 p-1 rounded-3 bg-light border d-inline-flex">
                        <button type="button" class="btn btn-sm fw-bold rounded-2 px-3 inq-view-toggle btn-white shadow-sm active" id="btnViewInquiriesList" onclick="switchInquiryView('list')">
                            <i class="fas fa-inbox me-1"></i> طلبات القبول المستلمة (<?= $inqTotal ?>)
                        </button>
                        <button type="button" class="btn btn-sm fw-bold rounded-2 px-3 text-muted inq-view-toggle" id="btnViewInquiriesSettings" onclick="switchInquiryView('settings')">
                            <i class="fas fa-sliders-h me-1 text-warning"></i> إعدادات وشروط القبول والمستندات
                        </button>
                    </div>

                    <!-- VIEW 1: INQUIRIES LIST & METRICS -->
                    <div id="inquiryViewList">
                        <!-- Metrics Cards -->
                        <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-4 p-3 bg-white shadow-sm h-100" style="border-right: 4px solid var(--brand-navy) !important;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-bold">إجمالي الطلبات</span>
                                    <span class="badge bg-light text-dark border rounded-pill"><?= $inqTotal ?></span>
                                </div>
                                <h3 class="fw-bold mb-0" style="color: var(--brand-navy);"><?= $inqTotal ?></h3>
                                <span class="text-muted" style="font-size: 0.72rem;">كافة الطلبات المسجلة</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-4 p-3 bg-white shadow-sm h-100" style="border-right: 4px solid #F59E0B !important;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-bold">طلبات جديدة</span>
                                    <span class="badge bg-warning text-dark rounded-pill"><?= $inqNew ?></span>
                                </div>
                                <h3 class="fw-bold mb-0 text-warning"><?= $inqNew ?></h3>
                                <span class="text-muted" style="font-size: 0.72rem;">بحاجة للمتابعة الأولى</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-4 p-3 bg-white shadow-sm h-100" style="border-right: 4px solid #0284C7 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-bold">تم التواصل</span>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill"><?= $inqContacted ?></span>
                                </div>
                                <h3 class="fw-bold mb-0" style="color: #0284C7;"><?= $inqContacted ?></h3>
                                <span class="text-muted" style="font-size: 0.72rem;">تمت المتابعة مع ولي الأمر</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-4 p-3 bg-white shadow-sm h-100" style="border-right: 4px solid #10B981 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-bold">تم القبول</span>
                                    <span class="badge bg-success text-white rounded-pill"><?= $inqAccepted ?></span>
                                </div>
                                <h3 class="fw-bold mb-0 text-success"><?= $inqAccepted ?></h3>
                                <span class="text-muted" style="font-size: 0.72rem;">مسجلين ومعتمدين</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Pills -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold inq-filter-btn active" data-filter="all" onclick="filterInquiries('all', this)">
                                الكل (<?= $inqTotal ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold inq-filter-btn" data-filter="new" onclick="filterInquiries('new', this)">
                                <i class="fas fa-sparkles me-1"></i> جديدة (<?= $inqNew ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold inq-filter-btn" data-filter="contacted" onclick="filterInquiries('contacted', this)">
                                <i class="fas fa-phone-volume me-1"></i> تم التواصل (<?= $inqContacted ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold inq-filter-btn" data-filter="accepted" onclick="filterInquiries('accepted', this)">
                                <i class="fas fa-check-circle me-1"></i> تم القبول (<?= $inqAccepted ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold inq-filter-btn" data-filter="rejected" onclick="filterInquiries('rejected', this)">
                                ملغية / مرفوضة (<?= $inqRejected ?>)
                            </button>
                        </div>
                        <div class="text-muted small">
                            <span id="inqVisibleCount"><?= $inqTotal ?></span> طلب ظاهر
                        </div>
                    </div>

                    <?php if (empty($inquiriesList)): ?>
                        <div class="text-center py-5 text-muted border rounded-4 bg-light">
                            <div class="rounded-circle bg-white shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                                <i class="fas fa-inbox text-muted fs-3 opacity-50"></i>
                            </div>
                            <h6 class="fw-bold text-dark">لا توجد طلبات قبول حتى الآن</h6>
                            <p class="mb-0 small text-muted" style="max-width: 420px; margin: 0 auto;">ستظهر هنا أي طلبات قبول أو استفسارات تسجيل يرسلها أولياء الأمور مباشرة من صفحة مدرستكم في دليل SchoPlus.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive rounded-4 border bg-white shadow-sm">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0; font-size: 0.76rem;" class="text-secondary">
                                    <tr>
                                        <th class="py-3 px-3"># / التاريخ</th>
                                        <th class="py-3">بيانات الطالب/ة</th>
                                        <th class="py-3">المرحلة والصف</th>
                                        <th class="py-3">ولي الأمر والتواصل</th>
                                        <th class="py-3">الملاحظات</th>
                                        <th class="py-3">حالة الطلب</th>
                                        <th class="py-3 px-3 text-center">إجراءات المتابعة</th>
                                    </tr>
                                </thead>
                                <tbody id="inquiriesTableBody">
                                    <?php foreach ($inquiriesList as $idx => $inq): 
                                        $status = $inq['status'] ?? 'new';
                                        $genderIcon = ($inq['student_gender'] === 'female') ? '<i class="fas fa-venus text-danger" title="أنثى"></i>' : (($inq['student_gender'] === 'male') ? '<i class="fas fa-mars text-primary" title="ذكر"></i>' : '<i class="fas fa-user-graduate text-muted"></i>');
                                        
                                        $cleanPhone = preg_replace('/[^0-9]/', '', $inq['parent_phone']);
                                        $waStudentPart = !empty($inq['student_name']) ? ' للطالب/ة (' . $inq['student_name'] . ')' : '';
                                        $waGradePart = !empty($inq['student_grade']) ? ' للصف ' . $inq['student_grade'] : (!empty($inq['student_stage']) ? ' بمرحلة ' . $inq['student_stage'] : '');
                                        $waMsg = "مرحباً بك أستاذ " . $inq['parent_name'] . "،\n" .
                                                 "نتواصل معك من إدارة مدرسة " . $school['name'] . " بخصوص طلب القبول والتسجيل المقدم" . $waStudentPart . $waGradePart . ".\n" .
                                                 "يسعدنا خدمتكم والإجابة على أي استفسارات لديكم لاستكمال إجراءات التسجيل.";
                                    ?>
                                        <tr class="inquiry-row" data-status="<?= e($status) ?>">
                                            <td class="px-3">
                                                <div class="fw-bold text-dark font-monospace">#<?= $inq['id'] ?></div>
                                                <div class="text-muted" style="font-size: 0.72rem; white-space: nowrap;">
                                                    <i class="far fa-calendar-alt me-1"></i><?= date('Y-m-d', strtotime($inq['created_at'])) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.68rem;">
                                                    <i class="far fa-clock me-1"></i><?= date('h:i A', strtotime($inq['created_at'])) ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fs-6"><?= $genderIcon ?></span>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">
                                                            <?= e($inq['student_name'] ?: '— غير مدون —') ?>
                                                        </div>
                                                        <?php if (!empty($inq['previous_school'])): ?>
                                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                                <i class="fas fa-history text-secondary me-1"></i> من: <?= e($inq['previous_school']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php if (!empty($inq['student_stage'])): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle align-self-start" style="font-size: 0.72rem;">
                                                            <?= e($inq['student_stage']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;">
                                                        <?= e($inq['student_grade'] ?: 'غير محدد') ?>
                                                    </div>

                                                    <?php if (!empty($inq['branch_name'])): ?>
                                                        <div class="text-secondary" style="font-size: 0.72rem;">
                                                            <i class="fas fa-code-branch me-1 text-muted"></i> فرع: <?= e($inq['branch_name']) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($inq['needs_transportation'])): ?>
                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle align-self-start" style="font-size: 0.68rem;">
                                                            <i class="fas fa-bus-alt me-1"></i> يحتاج ترحيل
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="fw-bold text-dark"><?= e($inq['parent_name']) ?></div>
                                                <div class="mt-1">
                                                    <a href="tel:<?= e($inq['parent_phone']) ?>" class="text-decoration-none fw-bold text-primary font-monospace" style="font-size: 0.78rem;">
                                                        <i class="fas fa-phone-alt me-1"></i><?= e($inq['parent_phone']) ?>
                                                    </a>
                                                </div>
                                                <?php if (!empty($inq['parent_email'])): ?>
                                                    <div class="text-muted" style="font-size: 0.72rem;">
                                                        <i class="far fa-envelope me-1"></i><?= e($inq['parent_email']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <td style="max-width: 220px;">
                                                <?php if (!empty($inq['message'])): ?>
                                                    <div class="text-secondary lh-base" style="font-size: 0.76rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= e($inq['message']) ?>">
                                                        <?= e($inq['message']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">— لا توجد ملاحظات —</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <form method="POST" action="dashboard.php" class="d-inline" id="statusForm_<?= $inq['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_inquiry_status">
                                                    <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                    <select name="status" class="form-select form-select-sm fw-bold border shadow-sm" style="font-size: 0.74rem; border-radius: 8px; width: auto; background-color: <?= $status === 'new' ? '#FFFBEB' : ($status === 'contacted' ? '#EFF6FF' : ($status === 'accepted' ? '#F0F4F8' : '#F8FAFC')) ?>; color: <?= $status === 'new' ? '#D97706' : ($status === 'contacted' ? '#1E5FA8' : ($status === 'accepted' ? '#0B2A52' : '#64748B')) ?>; border-color: <?= $status === 'new' ? '#F5A020' : ($status === 'contacted' ? '#1E5FA8' : ($status === 'accepted' ? '#0B2A52' : '#CBD5E1')) ?> !important;" onchange="this.form.submit()">
                                                        <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>جديد</option>
                                                        <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>تم التواصل</option>
                                                        <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>تم القبول</option>
                                                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>ملغي / مرفوض</option>
                                                    </select>
                                                </form>
                                            </td>

                                            <td class="px-3 text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <!-- WhatsApp Direct -->
                                                    <?php if ($cleanPhone): ?>
                                                        <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= urlencode($waMsg) ?>" target="_blank" class="btn btn-sm btn-success px-2 py-1 rounded-2 shadow-sm" title="محادثة واتساب سريعة">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Direct Call -->
                                                    <a href="tel:<?= e($inq['parent_phone']) ?>" class="btn btn-sm btn-outline-primary px-2 py-1 rounded-2" title="اتصال هاتفي">
                                                        <i class="fas fa-phone-alt"></i>
                                                    </a>

                                                    <!-- View Details Modal Trigger -->
                                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1 rounded-2" title="عرض التفاصيل الكاملة" onclick='viewInquiryDetails(<?= json_encode($inq, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <form method="POST" action="dashboard.php" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب نهائياً؟');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_inquiry">
                                                        <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1 rounded-2" title="حذف الطلب">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    </div> <!-- /#inquiryViewList -->

                    <!-- VIEW 2: ADMISSION SETTINGS & REQUIREMENTS FORM -->
                    <div id="inquiryViewSettings" style="display: none;">
                        <form method="POST" action="dashboard.php" id="formAdmissionSettings">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_admission_settings">

                            <!-- 1. Admission Status & General Notes -->
                            <div class="dash-card-section mb-4">
                                <div class="dash-sec-header">
                                    <div class="dash-sec-title-wrap">
                                        <div class="dash-sec-icon" style="background: #F0F4F8; color: #1E5FA8;">
                                            <i class="fas fa-toggle-on"></i>
                                        </div>
                                        <div>
                                            <h6 class="dash-sec-title">حالة بوابة القبول والتسجيل</h6>
                                            <p class="dash-sec-desc">حدد ما إذا كان باب التسجيل مفتوحاً حالياً أو مغلقاً، أو إذا كانت هناك مقاعد محدودة متبقية</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">حالة التسجيل للعام الدراسي:</label>
                                        <select name="admission_status" class="form-select fw-bold" id="admStatusSelect">
                                            <option value="open" <?= $currentAdmissionStatus === 'open' ? 'selected' : '' ?>>مفتوح ومتاح للجميع (الوضع الافتراضي)</option>
                                            <option value="limited" <?= $currentAdmissionStatus === 'limited' ? 'selected' : '' ?>>مقاعد محدودة متبقية (تنبيه أولياء الأمور)</option>
                                            <option value="early" <?= $currentAdmissionStatus === 'early' ? 'selected' : '' ?>>فتح باب التسجيل المبكر</option>
                                            <option value="closed" <?= $currentAdmissionStatus === 'closed' ? 'selected' : '' ?>>التسجيل مغلق مؤقتاً للعام الحالي</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">ملاحظات وشروط سن القبول أو معايير خاصة:</label>
                                        <textarea name="admission_notes" rows="2" class="form-control" placeholder="مثال: يشترط بلوغ سن 6 سنوات بحلول 1 سبتمبر للصف الأول الابتدائي، أو شروط الزي والسداد..."><?= e($currentAdmissionNotes) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Admission Requirements (Dynamic Repeater) -->
                            <div class="dash-card-section mb-4">
                                <div class="dash-sec-header justify-content-between flex-wrap gap-2">
                                    <div class="dash-sec-title-wrap">
                                        <div class="dash-sec-icon" style="background: #EFF6FF; color: var(--brand-blue);">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div>
                                            <h6 class="dash-sec-title">المستندات والأوراق المطلوبة للتسجيل</h6>
                                            <p class="dash-sec-desc">المستندات التي يجب على ولي الأمر إحضارها لمكتب القبول (شهادة ميلاد، تقارير، صور شخصية...)</p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="restoreDefaultRequirements()">
                                        <i class="fas fa-undo me-1"></i> استعادة القالب الافتراضي
                                    </button>
                                </div>

                                <div id="admissionReqsContainer" class="d-flex flex-column gap-2 mb-3">
                                    <?php foreach ($currentAdmissionReqs as $rIdx => $reqText): ?>
                                        <div class="input-group input-group-sm admission-req-row">
                                            <span class="input-group-text bg-light text-muted"><i class="fas fa-check text-success"></i></span>
                                            <input type="text" name="admission_requirements[]" class="form-control" value="<?= e($reqText) ?>" placeholder="أدخل المستند المطلوب...">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeAdmissionRow(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addAdmissionReqRow()">
                                    <i class="fas fa-plus me-1"></i> إضافة مستند مطلوب
                                </button>
                            </div>

                            <!-- 3. Admission Steps (Dynamic Repeater) -->
                            <div class="dash-card-section mb-4">
                                <div class="dash-sec-header justify-content-between flex-wrap gap-2">
                                    <div class="dash-sec-title-wrap">
                                        <div class="dash-sec-icon" style="background: #FFFBEB; color: #D97706;">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                        <div>
                                            <h6 class="dash-sec-title">خطوات ومراحل إجراءات القبول</h6>
                                            <p class="dash-sec-desc">المسار الذي يمر به الطالب من تقديم الطلب حتى الجلوس في الفصل الدراسي</p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="restoreDefaultSteps()">
                                        <i class="fas fa-undo me-1"></i> استعادة الخطوات الافتراضية
                                    </button>
                                </div>

                                <div id="admissionStepsContainer" class="d-flex flex-column gap-2 mb-3">
                                    <?php foreach ($currentAdmissionSteps as $sIdx => $stepText): ?>
                                        <div class="input-group input-group-sm admission-step-row">
                                            <span class="input-group-text bg-light fw-bold text-primary step-num-badge"><?= $sIdx + 1 ?></span>
                                            <input type="text" name="admission_steps[]" class="form-control" value="<?= e($stepText) ?>" placeholder="اكتب مرحلة أو خطوة القبول...">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeAdmissionRow(this, true)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="addAdmissionStepRow()">
                                    <i class="fas fa-plus me-1"></i> إضافة خطوة جديدة
                                </button>
                            </div>

                            <!-- Submit Save Button -->
                            <div class="d-flex justify-content-end mb-2">
                                <button type="submit" class="btn-luxury-primary px-4 py-2">
                                    <i class="fas fa-check-circle me-1"></i> حفظ إعدادات وشروط القبول
                                </button>
                            </div>
                        </form>
                    </div> <!-- /#inquiryViewSettings -->
                </div>

            </div>

        </div>

    </div>
</div>

<script>
    const allGeo = <?= json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE) ?>;
    const countrySelect = document.getElementById('dashCountry');
    const selectedStateVal = <?= json_encode($school['state']) ?>;
    const stateSelect = document.getElementById('dashState');
    const citySelect = document.getElementById('dashCity');
    const selectedCity = <?= json_encode($school['city']) ?>;

    function populateStates(countryName) {
        if (!stateSelect) return;
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
    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            populateStates(this.value);
        });
    }

    function populateCities(selectedState) {
        citySelect.innerHTML = '<option value="">-- اختر المدينة --</option>';
        const countryName = (countrySelect && countrySelect.value) ? countrySelect.value : 'السودان'; const statesObj = allGeo[countryName] || {}; if (selectedState && statesObj[selectedState]) {
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

    stateSelect.addEventListener('change', function() {
        populateCities(this.value);
    });

    // Populate on load
    if (stateSelect.value) {
        populateCities(stateSelect.value);
    }
</script>

<!-- SHARE & QR CODE MODAL FOR OWNER -->
<div class="modal fade" id="shareSchoolModal" tabindex="-1" aria-labelledby="shareSchoolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="shareSchoolModalLabel">
                    <i class="fas fa-share-nodes text-primary me-2"></i> مشاركة صفحة مدرستكم
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- QR Code Box -->
                <div class="p-3 bg-light rounded-4 d-inline-block mb-3 border">
                    <img id="schoolQrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" alt="QR Code" class="img-fluid rounded-3" style="width: 150px; height: 150px;">
                    <div class="small text-muted mt-2 fw-medium">امسح الباركود لفتح صفحة المدرسة فوراً</div>
                </div>

                <!-- Short URL Input & Copy -->
                <div class="mb-3 text-start">
                    <label class="form-label small fw-bold text-muted">الرابط المختصر الأنيق لمدرستكم:</label>
                    <div class="input-group">
                        <input type="text" id="shortUrlInput" class="form-control font-monospace text-start" style="direction:ltr" value="https://daleel.schoplus.sd/s/<?= e($school['slug']) ?>" readonly>
                        <button class="btn btn-primary fw-bold px-3" type="button" onclick="copyShortUrl()">
                            <i class="fas fa-copy me-1"></i> <span id="copyBtnText">نسخ</span>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">يمكنك أيضاً مشاركة الرابط المباشر: <code>https://daleel.schoplus.sd/school/<?= e($school['slug']) ?></code></small>
                </div>

                <!-- Direct Social Sharing Buttons -->
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mt-3">
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("زوروا صفحة " . $school['name'] . " على دليل المدارس الذكي SchoPlus:\nhttps://daleel.schoplus.sd/s/" . $school['slug']) ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
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
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>&text=<?= urlencode('صفحة ' . $school['name'] . ' على دليل المدارس') ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                        <i class="fab fa-x-twitter fs-5"></i> تويتر (X)
                    </a>
                </div>

                <!-- Download QR link -->
                <div class="mt-3">
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode('https://daleel.schoplus.sd/s/' . $school['slug']) ?>" download="QR-<?= e($school['slug']) ?>.png" target="_blank" class="btn btn-sm btn-link text-muted text-decoration-none">
                        <i class="fas fa-download me-1"></i> تحميل الباركود بدقة عالية لطباعته في المدرسة أو الإعلانات
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ADD NEWS MODAL (Word-like WYSIWYG Editor - يفتح عند الضغط على إضافة خبر) -->
<div class="modal fade" id="addNewsModal" tabindex="-1" aria-labelledby="addNewsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: #EFF6FF; color: var(--brand-blue); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="addNewsModalLabel">إضافة خبر أو إعلان جديد لمدرستكم</h5>
                        <small class="text-muted">استخدم محرر Word لتنسيق تفاصيل الخبر (تُعتمد صورة إعلان افتراضية تلقائياً بحجم مدمج)</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST" action="dashboard.php" id="addNewsForm" onsubmit="syncQuillToAddNews()">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_news">
                <input type="hidden" name="content" id="addNewsContentInput">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">
                                عنوان الخبر / الإعلان <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" id="addNewsTitleInput" required class="form-control form-control-lg fs-6" placeholder="مثال: بدء التسجيل للعام الدراسي الجديد أو تكريم الطلاب المتفوقين...">
                        </div>
                        <?php if (!empty($branchesList)): ?>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">
                                    <i class="fas fa-code-branch text-primary me-1"></i> الفرع التابع له الخبر (اختياري)
                                </label>
                                <select name="branch_name" class="form-select">
                                    <option value="">المركز الرئيسي (كافة الفروع / عام)</option>
                                    <?php foreach ($branchesList as $br): ?>
                                        <?php if (!empty($br['name'])): ?>
                                            <option value="<?= e($br['name']) ?>"><?= e($br['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">إذا تم تحديد فرع، سيظهر اسم الفرع في بطاقة وصورة الخبر</small>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">
                                    نص وتفاصيل الخبر (محرر Word متقدم) <span class="text-danger">*</span>
                                </label>
                                <span class="badge bg-light text-secondary border small">
                                    <i class="fas fa-magic text-warning me-1"></i> يدعم العناوين، الألوان، والقوائم
                                </span>
                            </div>
                            <!-- Quill Word-like Editor Container -->
                            <div id="addNewsQuillEditor" style="min-height: 250px; background: #FFFFFF; border-radius: 0 0 10px 10px; font-size: 0.95rem; line-height: 1.8;"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3 justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm rounded-3">
                        <i class="fas fa-paper-plane"></i>
                        <span>حفظ ونشر الخبر الآن</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT NEWS MODAL (Word-like WYSIWYG Editor) -->
<div class="modal fade" id="editNewsModal" tabindex="-1" aria-labelledby="editNewsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="editNewsModalLabel">
                    <i class="fas fa-edit text-warning me-2"></i> تعديل الخبر أو الإعلان
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST" action="dashboard.php" id="editNewsForm" onsubmit="syncQuillToEditNews()">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_news">
                <input type="hidden" name="news_id" id="editNewsId">
                <input type="hidden" name="content" id="editNewsContentInput">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">عنوان الخبر / الإعلان <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editNewsTitle" required class="form-control form-control-lg fs-6" placeholder="عنوان الخبر...">
                        </div>
                        <?php if (!empty($branchesList)): ?>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">
                                    <i class="fas fa-code-branch text-primary me-1"></i> الفرع التابع له الخبر (اختياري)
                                </label>
                                <select name="branch_name" id="editNewsBranch" class="form-select">
                                    <option value="">المركز الرئيسي (كافة الفروع / عام)</option>
                                    <?php foreach ($branchesList as $br): ?>
                                        <?php if (!empty($br['name'])): ?>
                                            <option value="<?= e($br['name']) ?>"><?= e($br['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">إذا تم تحديد فرع، سيظهر اسم الفرع في بطاقة وصورة الخبر</small>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">نص وتفاصيل الخبر (محرر Word متقدم)</label>
                            <!-- Quill Edit Editor Container -->
                            <div id="editNewsQuillEditor" style="min-height: 220px; background: #FFFFFF; border-radius: 0 0 10px 10px; font-size: 0.95rem; line-height: 1.8;"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn-luxury-primary btn-sm px-4">
                        <i class="fas fa-check-circle me-1"></i> حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- INQUIRY DETAILS MODAL (تفاصيل طلب القبول) -->
<div class="modal fade" id="inquiryDetailsModal" tabindex="-1" aria-labelledby="inquiryDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0" id="inquiryDetailsModalTitle">تفاصيل طلب القبول</h5>
                        <span class="text-muted small" id="inquiryDetailsModalDate" style="font-size: 0.72rem;"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body p-4" id="inquiryDetailsModalBody">
                <!-- dynamic content populated by JS -->
            </div>
            <div class="modal-footer bg-light border-top px-4 py-2 justify-content-between">
                <div id="inquiryDetailsModalActions" class="d-flex gap-2"></div>
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterInquiries(status, btn) {
    document.querySelectorAll('.inq-filter-btn').forEach(b => {
        b.classList.remove('active', 'btn-dark');
        if (b.dataset.filter === 'new') b.classList.add('btn-outline-warning');
        else if (b.dataset.filter === 'contacted') b.classList.add('btn-outline-info');
        else if (b.dataset.filter === 'accepted') b.classList.add('btn-outline-success');
        else if (b.dataset.filter === 'rejected') b.classList.add('btn-outline-secondary');
        else b.classList.add('btn-outline-dark');
    });

    btn.classList.add('active', 'btn-dark');
    btn.classList.remove('btn-outline-warning', 'btn-outline-info', 'btn-outline-success', 'btn-outline-secondary', 'btn-outline-dark');

    const rows = document.querySelectorAll('.inquiry-row');
    let visibleCount = 0;
    rows.forEach(r => {
        if (status === 'all' || r.dataset.status === status) {
            r.style.display = '';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });
    const counter = document.getElementById('inqVisibleCount');
    if (counter) counter.textContent = visibleCount;
}

function viewInquiryDetails(inq) {
    if (!inq) return;
    document.getElementById('inquiryDetailsModalTitle').innerText = 'طلب قبول #' + inq.id + ' — ' + (inq.student_name || inq.parent_name);
    document.getElementById('inquiryDetailsModalDate').innerText = 'تاريخ التقديم: ' + (inq.created_at || '');

    const genderText = inq.student_gender === 'female' ? '<i class="fas fa-venus text-danger me-1"></i> أنثى' : (inq.student_gender === 'male' ? '<i class="fas fa-mars text-primary me-1"></i> ذكر' : 'غير محدد');
    const transText = inq.needs_transportation == 1 ? '<span class="text-warning fw-bold"><i class="fas fa-bus-alt me-1"></i> نعم، يحتاج إلى ترحيل مدرسي</span>' : '<span class="text-muted">لا يحتاج ترحيل</span>';

    const statusLabels = {
        'new': '<span class="badge px-2 py-1" style="background:#F5A020; color:#FFFFFF;">جديد</span>',
        'contacted': '<span class="badge px-2 py-1" style="background:#1E5FA8; color:#FFFFFF;">تم التواصل</span>',
        'accepted': '<span class="badge px-2 py-1" style="background:#0B2A52; color:#FFFFFF;">تم القبول</span>',
        'rejected': '<span class="badge px-2 py-1" style="background:#64748B; color:#FFFFFF;">ملغي / مرفوض</span>'
    };

    let html = `
        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
            <div>
                <span class="text-muted small d-block mb-1">حالة الطلب:</span>
                ${statusLabels[inq.status] || inq.status}
            </div>
            <div class="text-end">
                <span class="text-muted small d-block mb-1">الفرع المطلوب:</span>
                <strong class="text-dark">${inq.branch_name ? inq.branch_name : 'الفرع الرئيسي'}</strong>
            </div>
        </div>

        <div class="mb-3 p-3 rounded-3 bg-light border">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 0.85rem;"><i class="fas fa-user-graduate text-primary me-2"></i> بيانات الطالب</h6>
            <div class="row g-2" style="font-size: 0.8rem;">
                <div class="col-6"><span class="text-muted">اسم الطالب:</span> <strong class="text-dark">${inq.student_name || '— غير مدون —'}</strong></div>
                <div class="col-6"><span class="text-muted">الجنس:</span> <strong>${genderText}</strong></div>
                <div class="col-6"><span class="text-muted">المرحلة:</span> <strong>${inq.student_stage || 'غير محدد'}</strong></div>
                <div class="col-6"><span class="text-muted">الصف:</span> <strong>${inq.student_grade || 'غير محدد'}</strong></div>
                <div class="col-12"><span class="text-muted">المدرسة السابقة:</span> <strong>${inq.previous_school || 'لا توجد / تسجيل جديد'}</strong></div>
                <div class="col-12"><span class="text-muted">خدمة الترحيل:</span> ${transText}</div>
            </div>
        </div>

        <div class="mb-3 p-3 rounded-3 bg-light border">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 0.85rem;"><i class="fas fa-user text-success me-2"></i> بيانات ولي الأمر</h6>
            <div class="row g-2" style="font-size: 0.8rem;">
                <div class="col-12"><span class="text-muted">اسم ولي الأمر:</span> <strong class="text-dark">${inq.parent_name || '—'}</strong></div>
                <div class="col-6"><span class="text-muted">رقم الهاتف:</span> <a href="tel:${inq.parent_phone}" class="fw-bold text-decoration-none">${inq.parent_phone || '—'}</a></div>
                <div class="col-6"><span class="text-muted">البريد الإلكتروني:</span> <strong>${inq.parent_email || 'غير مدون'}</strong></div>
            </div>
        </div>

        ${inq.message ? `
        <div class="p-3 rounded-3 bg-light border">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 0.85rem;"><i class="fas fa-comment-alt text-info me-2"></i> رسالة وملاحظات ولي الأمر</h6>
            <p class="mb-0 text-secondary lh-base" style="font-size: 0.82rem; white-space: pre-line;">${inq.message}</p>
        </div>
        ` : ''}
    `;

    document.getElementById('inquiryDetailsModalBody').innerHTML = html;

    const cleanPhone = (inq.parent_phone || '').replace(/[^0-9]/g, '');
    let actionsHtml = '';
    if (cleanPhone) {
        const studentPart = inq.student_name ? ' للطالب/ة (' + inq.student_name + ')' : '';
        const gradePart = inq.student_grade ? ' للصف ' + inq.student_grade : '';
        const waMsg = encodeURIComponent("مرحباً بك أستاذ " + (inq.parent_name || '') + "،\nنتواصل معك من إدارة مدرسة <?= addslashes($school['name']) ?> بخصوص طلب القبول والتسجيل المقدم" + studentPart + gradePart + ".");
        actionsHtml += `<a href="https://wa.me/${cleanPhone}?text=${waMsg}" target="_blank" class="btn btn-success btn-sm fw-bold"><i class="fab fa-whatsapp me-1"></i> محادثة واتساب</a>`;
        actionsHtml += `<a href="tel:${inq.parent_phone}" class="btn btn-outline-primary btn-sm fw-bold"><i class="fas fa-phone-alt me-1"></i> اتصال</a>`;
    }
    document.getElementById('inquiryDetailsModalActions').innerHTML = actionsHtml;

    new bootstrap.Modal(document.getElementById('inquiryDetailsModal')).show();
}

// Switch between Inquiries List and Admission Settings Form
function switchInquiryView(mode) {
    const listBtn = document.getElementById('btnViewInquiriesList');
    const setBtn = document.getElementById('btnViewInquiriesSettings');
    const listView = document.getElementById('inquiryViewList');
    const setView = document.getElementById('inquiryViewSettings');

    if (mode === 'settings') {
        if (listBtn) { listBtn.classList.remove('active', 'btn-white', 'shadow-sm'); listBtn.classList.add('text-muted'); }
        if (setBtn) { setBtn.classList.add('active', 'btn-white', 'shadow-sm'); setBtn.classList.remove('text-muted'); }
        if (listView) listView.style.display = 'none';
        if (setView) setView.style.display = 'block';
    } else {
        if (setBtn) { setBtn.classList.remove('active', 'btn-white', 'shadow-sm'); setBtn.classList.add('text-muted'); }
        if (listBtn) { listBtn.classList.add('active', 'btn-white', 'shadow-sm'); listBtn.classList.remove('text-muted'); }
        if (setView) setView.style.display = 'none';
        if (listView) listView.style.display = 'block';
    }
}

function addAdmissionReqRow(text = '') {
    const container = document.getElementById('admissionReqsContainer');
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm admission-req-row';
    div.innerHTML = `
        <span class="input-group-text bg-light text-muted"><i class="fas fa-check text-success"></i></span>
        <input type="text" name="admission_requirements[]" class="form-control" value="${text}" placeholder="أدخل المستند المطلوب...">
        <button type="button" class="btn btn-outline-danger" onclick="removeAdmissionRow(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

function addAdmissionStepRow(text = '') {
    const container = document.getElementById('admissionStepsContainer');
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm admission-step-row';
    div.innerHTML = `
        <span class="input-group-text bg-light fw-bold text-primary step-num-badge">1</span>
        <input type="text" name="admission_steps[]" class="form-control" value="${text}" placeholder="اكتب مرحلة أو خطوة القبول...">
        <button type="button" class="btn btn-outline-danger" onclick="removeAdmissionRow(this, true)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
    reindexAdmissionSteps();
}

function removeAdmissionRow(btn, isStep = false) {
    const row = btn.closest('.admission-req-row') || btn.closest('.admission-step-row');
    if (row) {
        row.remove();
        if (isStep) reindexAdmissionSteps();
    }
}

function reindexAdmissionSteps() {
    const badges = document.querySelectorAll('#admissionStepsContainer .step-num-badge');
    badges.forEach((b, idx) => {
        b.textContent = (idx + 1);
    });
}

function restoreDefaultRequirements() {
    if (!confirm('هل تريد استعادة قائمة المستندات الافتراضية النموذجية؟')) return;
    const defaults = [
        'أصل وصورة شهادة الميلاد أو الرقم الوطني للطالب.',
        'شهادة النقل أو آخر تقرير أكاديمي معتمد (للصفوف المنقولة).',
        'عدد 4 صور فوتوغرافية حديثة بحجم جواز السفر.',
        'كرت التطعيم والشهادة الصحية (لمرحلة رياض الأطفال).'
    ];
    const container = document.getElementById('admissionReqsContainer');
    container.innerHTML = '';
    defaults.forEach(item => addAdmissionReqRow(item));
}

function restoreDefaultSteps() {
    if (!confirm('هل تريد استعادة خطوات ومراحل القبول النموذجية الافتراضية؟')) return;
    const defaults = [
        'تقديم طلب القبول الإلكتروني بالاستمارة أدناه.',
        'تواصل فريق القبول وتحديد موعد المقابلة واختبار المستوى.',
        'إكمال تسليم المستندات واعتماد التسجيل وسداد الرسوم.',
        'استلام الزي المدرسي والجدول وتوزيع الفصول.'
    ];
    const container = document.getElementById('admissionStepsContainer');
    container.innerHTML = '';
    defaults.forEach(item => addAdmissionStepRow(item));
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

// ==========================================
// QUILL WORD-LIKE RICH TEXT EDITORS SETUP
// ==========================================
let addNewsQuill = null;
let editNewsQuill = null;

const quillToolbarOptions = [
    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
    [{ 'font': [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ 'color': [] }, { 'background': [] }],
    [{ 'align': [] }],
    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
    [{ 'indent': '-1'}, { 'indent': '+1' }],
    ['blockquote'],
    ['link', 'clean']
];

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Add News Editor
    const addEditorElem = document.getElementById('addNewsQuillEditor');
    if (addEditorElem && typeof Quill !== 'undefined') {
        addNewsQuill = new Quill('#addNewsQuillEditor', {
            theme: 'snow',
            placeholder: 'اكتب نص الخبر هنا ونسقه بالطريقة التي تناسبك...',
            modules: {
                toolbar: quillToolbarOptions
            }
        });
    }

    // 2. Initialize Edit News Editor
    const editEditorElem = document.getElementById('editNewsQuillEditor');
    if (editEditorElem && typeof Quill !== 'undefined') {
        editNewsQuill = new Quill('#editNewsQuillEditor', {
            theme: 'snow',
            placeholder: 'تعديل تفاصيل الخبر والتنسيقات...',
            modules: {
                toolbar: quillToolbarOptions
            }
        });
    }
});

function syncQuillToAddNews() {
    if (addNewsQuill) {
        document.getElementById('addNewsContentInput').value = addNewsQuill.root.innerHTML;
    }
    return true;
}

function syncQuillToEditNews() {
    if (editNewsQuill) {
        document.getElementById('editNewsContentInput').value = editNewsQuill.root.innerHTML;
    }
    return true;
}

function openAddNewsModal() {
    // Reset form fields
    const titleInput = document.getElementById('addNewsTitleInput');
    if (titleInput) titleInput.value = '';
    if (addNewsQuill) addNewsQuill.root.innerHTML = '';
    const contentInput = document.getElementById('addNewsContentInput');
    if (contentInput) contentInput.value = '';

    const modal = new bootstrap.Modal(document.getElementById('addNewsModal'));
    modal.show();
}

function openEditNewsModal(item) {
    document.getElementById('editNewsId').value = item.id;
    document.getElementById('editNewsTitle').value = item.title;
    
    // Set branch selection if branch select exists
    const branchSelect = document.getElementById('editNewsBranch');
    if (branchSelect) {
        branchSelect.value = item.branch_name || '';
    }

    // Set content into Quill
    if (editNewsQuill) {
        editNewsQuill.root.innerHTML = item.content || '';
    } else {
        document.getElementById('editNewsContentInput').value = item.content || '';
    }

    // Existing image preview (if element exists)
    const imgWrap = document.getElementById('editNewsCurrentImageWrap');
    const imgElem = document.getElementById('editNewsCurrentImage');
    if (imgWrap && imgElem) {
        if (item.image_path) {
            imgElem.src = item.image_path;
            imgWrap.style.display = 'block';
        } else {
            imgWrap.style.display = 'none';
        }
    }

    const modal = new bootstrap.Modal(document.getElementById('editNewsModal'));
    modal.show();
}

// Ensure active tab is stored in URL hash and restored properly
document.addEventListener('DOMContentLoaded', function() {
    const tabTriggers = document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]');
    tabTriggers.forEach(trigger => {
        trigger.addEventListener('shown.bs.tab', function(e) {
            const targetId = e.target.getAttribute('data-bs-target');
            if (targetId) {
                history.replaceState(null, null, targetId);
            }
        });
    });

    // Check if server set a specific active tab (e.g. after adding media/news/photo)
    const serverActiveTab = '<?= e($activeDashTab) ?>';
    const serverTabBtn = document.getElementById('dash-' + serverActiveTab + '-tab');

    // If server active tab is NOT profile, or there is no URL hash, show server tab and set hash
    if (serverActiveTab && serverActiveTab !== 'profile' && serverTabBtn) {
        new bootstrap.Tab(serverTabBtn).show();
        history.replaceState(null, null, '#dash-' + serverActiveTab);
    } else if (window.location.hash) {
        // Otherwise if URL has hash on load, switch to that tab
        const hashTab = document.querySelector('#dashboardTabs [data-bs-target="' + window.location.hash + '"]');
        if (hashTab) {
            new bootstrap.Tab(hashTab).show();
        }
    }

    // Sync active sub-tab into URL params
    document.querySelectorAll('#profileSubTabs [data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target) {
                const subtabName = target.replace('#subtab-', '');
                const url = new URL(window.location);
                url.searchParams.set('tab', 'profile');
                url.searchParams.set('subtab', subtabName);
                window.history.replaceState({}, '', url);
            }
        });
    });

    // Check if URL has subtab param on load
    const urlParams = new URLSearchParams(window.location.search);
    const subtabParam = urlParams.get('subtab');
    if (subtabParam) {
        const subtabBtn = document.querySelector('#profileSubTabs [data-bs-target="#subtab-' + subtabParam + '"]');
        if (subtabBtn) {
            new bootstrap.Tab(subtabBtn).show();
        }
    }
});

// Detailed Fees Mode & Stage Fee Rows Logic
function toggleFeesMode(mode) {
    const detBox = document.getElementById('feesDetailedBox');
    const lbl = document.getElementById('approxFeesLabel');
    if (mode === 'detailed') {
        if (detBox) detBox.style.display = 'block';
        if (lbl) lbl.textContent = 'متوسط الرسوم السنوية الإجمالي (ملخص لكروت البحث):';
        const tbody = document.getElementById('stageFeesTbody');
        if (tbody && tbody.children.length === 0) {
            addStageFeeRow('رياض الأطفال', 'تمهيدي وروضة', '', 'على قسطين');
            addStageFeeRow('المرحلة الابتدائية', 'الصفوف من 1 إلى 6', '', 'على 3 أقساط');
        }
    } else {
        if (detBox) detBox.style.display = 'none';
        if (lbl) lbl.textContent = 'متوسط الرسوم الدراسية السنوية:';
    }
}

function addStageFeeRow(stage = 'المرحلة الابتدائية', title = '', amount = '', notes = '') {
    const tbody = document.getElementById('stageFeesTbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'fee-row-item';
    
    const stages = ['رياض الأطفال', 'المرحلة الابتدائية', 'المرحلة المتوسطة', 'المرحلة الثانوية', 'رسوم التسجيل والزي', 'رسوم الترحيل والمواصلات', 'أخرى / بند مخصص'];
    let stageOpts = '';
    stages.forEach(st => {
        stageOpts += `<option value="${st}" ${st === stage ? 'selected' : ''}>${st}</option>`;
    });

    tr.innerHTML = `
        <td style="min-width: 150px; padding: 8px 10px;">
            <select name="stage_fee_stage[]" class="form-select form-select-sm">
                ${stageOpts}
            </select>
        </td>
        <td style="min-width: 170px; padding: 8px 10px;">
            <input type="text" name="stage_fee_title[]" value="${escapeHtml(title)}" class="form-control form-control-sm" placeholder="مثال: الصفوف 1 إلى 6">
        </td>
        <td style="min-width: 140px; padding: 8px 10px;">
            <input type="text" name="stage_fee_amount[]" value="${escapeHtml(amount)}" class="form-control form-control-sm fw-bold text-success" placeholder="مثال: 350,000 ج.س">
        </td>
        <td style="min-width: 160px; padding: 8px 10px;">
            <input type="text" name="stage_fee_notes[]" value="${escapeHtml(notes)}" class="form-control form-control-sm" placeholder="مثال: على 3 أقساط">
        </td>
        <td style="width: 45px; text-align: center; padding: 8px 10px;">
            <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-2" onclick="this.closest('tr').remove()" title="حذف البند">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function addDiscountRow(title = '', value = '', appliesTo = '', notes = '') {
    const tbody = document.getElementById('discountsTbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'discount-row-item';
    tr.innerHTML = `
        <td style="min-width: 170px; padding: 8px 10px;">
            <input type="text" name="discount_title[]" value="${escapeHtml(title)}" required class="form-control form-control-sm fw-bold text-dark" placeholder="مثال: خصم الإخوة والأشقاء">
        </td>
        <td style="min-width: 130px; padding: 8px 10px;">
            <input type="text" name="discount_value[]" value="${escapeHtml(value)}" required class="form-control form-control-sm fw-bold" style="color: #1E5FA8;" placeholder="مثال: 15% أو 50,000 ج.س">
        </td>
        <td style="min-width: 150px; padding: 8px 10px;">
            <input type="text" name="discount_applies_to[]" value="${escapeHtml(appliesTo)}" class="form-control form-control-sm" placeholder="مثال: الأخ الثاني فما فوق">
        </td>
        <td style="min-width: 180px; padding: 8px 10px;">
            <input type="text" name="discount_notes[]" value="${escapeHtml(notes)}" class="form-control form-control-sm" placeholder="مثال: يطبق عند سداد القسط كاملاً">
        </td>
        <td style="width: 45px; text-align: center; padding: 8px 10px;">
            <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-2" onclick="this.closest('tr').remove()" title="حذف العرض">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function addQuickDiscount(title, value, appliesTo, notes) {
    addDiscountRow(title, value, appliesTo, notes);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ========================================================
// Automatic Slug Generation & Live Formatting & Availability Check
// ========================================================
document.addEventListener('DOMContentLoaded', function() {
    const nameEnInput = document.getElementById('schoolNameEn');
    const slugInput = document.getElementById('schoolSlugInput');
    const slugIcon = document.getElementById('slugStatusIcon');
    const slugFeedback = document.getElementById('slugFeedback');
    const slugPreview = document.getElementById('slugPreviewText');
    const btnAuto = document.getElementById('btnAutoGenerateSlug');
    const schoolId = <?= (int)$schoolId ?>;
    const initialSlug = <?= json_encode((string)$school['slug']) ?>;

    if (!slugInput) return;

    let checkTimeout = null;
    let isUserManuallyTypingSlug = false;

    // Helper: Sanitize string into valid kebab-case slug
    function sanitizeSlug(raw) {
        if (!raw) return '';
        let s = raw.toLowerCase();
        // Replace spaces, commas, Arabic commas, underscores, slashes, punctuation with hyphens
        s = s.replace(/[\s,،_./\\+=?!@#$%^&*()~`'"[\]{}|<>:;]+/g, '-');
        // Remove non-alphanumeric and non-hyphen
        s = s.replace(/[^a-z0-9-]/g, '');
        // Collapse multiple hyphens
        s = s.replace(/-+/g, '-');
        return s;
    }

    // Live Availability Check via API
    function checkSlug(slug) {
        if (!slug || slug.length < 3) {
            if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-minus-circle text-muted"></i>';
            if (slugFeedback) slugFeedback.innerHTML = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i> يتكون الرابط من 3 أحرف إنجليزية أو أرقام على الأقل</span>';
            slugInput.classList.remove('is-valid', 'is-invalid');
            return;
        }

        if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
        if (slugFeedback) slugFeedback.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i> جاري فحص توفر الرابط...</span>';

        fetch(`/api/index.php?action=check_slug&slug=${encodeURIComponent(slug)}&school_id=${schoolId}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'available') {
                    if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-check-circle text-success" title="متاح"></i>';
                    if (slugFeedback) slugFeedback.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> الرابط متاح ومناسب للاستخدام!</span>';
                    slugInput.classList.remove('is-invalid');
                    slugInput.classList.add('is-valid');
                } else if (res.status === 'current') {
                    if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-check-circle text-success" title="معتمد"></i>';
                    if (slugFeedback) slugFeedback.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> هذا هو الرابط الحالي المعتمد لمدرستكم.</span>';
                    slugInput.classList.remove('is-invalid', 'is-valid');
                } else if (res.status === 'taken') {
                    if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-times-circle text-danger" title="مستخدم"></i>';
                    let sugHtml = '';
                    if (res.suggestion) {
                        sugHtml = ` <button type="button" class="btn btn-sm btn-link p-0 text-primary text-decoration-underline fw-bold" onclick="window.applySlugSuggestion('${res.suggestion}')">استخدم المقترح (${res.suggestion})</button>`;
                    }
                    if (slugFeedback) slugFeedback.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i> ${res.message}${sugHtml}</span>`;
                    slugInput.classList.remove('is-valid');
                    slugInput.classList.add('is-invalid');
                } else {
                    if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
                    if (slugFeedback) slugFeedback.innerHTML = `<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> ${res.message}</span>`;
                    slugInput.classList.remove('is-valid');
                    slugInput.classList.add('is-invalid');
                }
            })
            .catch(() => {
                if (slugIcon) slugIcon.innerHTML = '<i class="fas fa-check text-muted"></i>';
                if (slugFeedback) slugFeedback.innerHTML = '';
            });
    }

    window.applySlugSuggestion = function(suggestion) {
        slugInput.value = suggestion;
        if (slugPreview) slugPreview.textContent = suggestion;
        checkSlug(suggestion);
    };

    // Auto-generate from English Name on typing
    if (nameEnInput) {
        nameEnInput.addEventListener('input', function() {
            // Auto-update slug if user hasn't manually edited slug, or slug is empty, or matches current initial
            if (!isUserManuallyTypingSlug || slugInput.value === '' || slugInput.value === initialSlug) {
                let generated = sanitizeSlug(this.value).replace(/^-+|-+$/g, '');
                if (generated) {
                    slugInput.value = generated;
                    if (slugPreview) slugPreview.textContent = generated;
                    clearTimeout(checkTimeout);
                    checkTimeout = setTimeout(() => checkSlug(generated), 350);
                }
            }
        });
    }

    // Auto-generate button click
    if (btnAuto) {
        btnAuto.addEventListener('click', function(e) {
            e.preventDefault();
            const source = (nameEnInput && nameEnInput.value.trim()) ? nameEnInput.value : '';
            if (source) {
                let clean = sanitizeSlug(source).replace(/^-+|-+$/g, '');
                slugInput.value = clean;
                if (slugPreview) slugPreview.textContent = clean;
                isUserManuallyTypingSlug = false;
                checkSlug(clean);
            } else {
                alert('يرجى كتابة اسم المدرسة باللغة الإنجليزية أولاً ليتم توليد الرابط منه تلقائياً.');
                if (nameEnInput) nameEnInput.focus();
            }
        });
    }

    // On Slug Input: live auto-correct / format (spaces, commas, uppercase -> clean lowercase with single dashes)
    slugInput.addEventListener('input', function() {
        isUserManuallyTypingSlug = true;
        const originalVal = this.value;

        // Auto-correct: convert spaces, commas, uppercase to lowercase and single dashes
        let clean = this.value.toLowerCase()
            .replace(/[\s,،_./\\+=?!@#$%^&*()~`'"[\]{}|<>:;]+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-');

        // Only update value if different to keep cursor position natural
        if (clean !== originalVal) {
            this.value = clean;
        }

        if (slugPreview) slugPreview.textContent = clean || '...';

        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            const trimmed = clean.replace(/^-+|-+$/g, '');
            checkSlug(trimmed);
        }, 350);
    });

    // On blur: trim leading & trailing dashes
    slugInput.addEventListener('blur', function() {
        const trimmed = this.value.replace(/^-+|-+$/g, '');
        if (trimmed !== this.value) {
            this.value = trimmed;
            if (slugPreview) slugPreview.textContent = trimmed || '...';
        }
        checkSlug(trimmed);
    });

    // Initial check on load
    if (slugInput.value.trim()) {
        checkSlug(slugInput.value.trim());
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';

