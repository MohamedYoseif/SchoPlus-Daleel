<?php
/**
 * SchoPlus Schools Directory — Mobile App Shell
 * Matches the prestigious UI/UX design of SchoPlus 5 Mobile Apps
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SudanGeography.php';

$pdo = db();

// Ensure session is started for authentication & notifications
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$noticeSuccess = '';
$noticeError = '';

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['daleel_school_id']);
    header('Location: /mobile/?tab=login');
    exit;
}

// ══════════════════════════════════════════════════════════════
// POST HANDLERS (Native Database Forms)
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // 1. Admission Inquiry / Submission Form
    if ($action === 'add_inquiry') {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $parentName = trim($_POST['parent_name'] ?? '');
        $parentPhone = trim($_POST['parent_phone'] ?? '');
        $parentEmail = trim($_POST['parent_email'] ?? '');
        $studentName = trim($_POST['student_name'] ?? '');
        $studentGender = trim($_POST['student_gender'] ?? 'male');
        $studentStage = trim($_POST['student_stage'] ?? '');
        $studentGrade = trim($_POST['student_grade'] ?? '');
        $previousSchool = trim($_POST['previous_school'] ?? '');
        $branchName = trim($_POST['branch_name'] ?? '');
        $needsTransportation = !empty($_POST['needs_transportation']) ? 1 : 0;
        $message = trim($_POST['message'] ?? '');

        if (!$schoolId || mb_strlen($parentName) < 3 || strlen($parentPhone) < 7) {
            $noticeError = 'يرجى إدخال اسم ولي الأمر ورقم الهاتف بشكل صحيح.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO daleel_school_inquiries (
                    school_id, parent_name, parent_phone, parent_email,
                    student_name, student_gender, student_stage, student_grade,
                    previous_school, branch_name, needs_transportation, message,
                    status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'new', NOW(), NOW()
                )");
                $stmt->execute([
                    $schoolId, $parentName, $parentPhone, $parentEmail ?: null,
                    $studentName ?: null, $studentGender ?: null, $studentStage ?: null, $studentGrade ?: null,
                    $previousSchool ?: null, $branchName ?: null, $needsTransportation, $message ?: null
                ]);
                $noticeSuccess = 'تم إرسال طلب القبول والتسجيل بنجاح! ستتواصل إدارة المدرسة معكم عبر الهاتف.';
            } catch (\Exception $e) {
                $noticeError = 'حدث خطأ أثناء حفظ الطلب، يرجى المحاولة لاحقاً.';
            }
        }
    }

    // 2. School Review Submission
    elseif ($action === 'add_review') {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $reviewerName = trim($_POST['reviewer_name'] ?? '');
        $reviewerPhone = trim($_POST['reviewer_phone'] ?? '');
        $reviewerRole = trim($_POST['reviewer_role'] ?? 'parent');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim($_POST['comment'] ?? '');

        if (!$schoolId || mb_strlen($reviewerName) < 3 || mb_strlen($comment) < 5) {
            $noticeError = 'يرجى كتابة الاسم والتقييم بوضوح.';
        } else {
            try {
                $revStmt = $pdo->prepare("INSERT INTO daleel_school_reviews (
                    school_id, reviewer_name, reviewer_phone, reviewer_role, rating, comment, is_approved, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $revStmt->execute([$schoolId, $reviewerName, $reviewerPhone ?: null, $reviewerRole, $rating, $comment]);

                // Update school rating stats
                $statStmt = $pdo->prepare("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM daleel_school_reviews WHERE school_id = ? AND is_approved = 1");
                $statStmt->execute([$schoolId]);
                $stats = $statStmt->fetch();
                $pdo->prepare("UPDATE daleel_schools SET reviews_count = ?, rating_avg = ? WHERE id = ?")->execute([
                    (int)($stats['cnt'] ?? 1),
                    round((float)($stats['avg_rating'] ?? 5.0), 2),
                    $schoolId
                ]);

                $noticeSuccess = 'شكراً لتقييمك! تم نشر التقييم بنجاح.';
            } catch (\Exception $e) {
                $noticeError = 'تعذر إضافة التقييم حالياً.';
            }
        }
    }

    // 3. School Login
    elseif ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$identifier || !$password) {
            $noticeError = 'يرجى إدخال رقم الهاتف أو البريد الإلكتروني وكلمة المرور.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE (owner_phone = ? OR owner_email = ?) LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $sch = $stmt->fetch();

            if ($sch && password_verify($password, $sch['password'])) {
                $_SESSION['daleel_school_id'] = (int)$sch['id'];
                $noticeSuccess = 'تم تسجيل الدخول بنجاح! مرحباً بك في لوحة مدرستك.';
            } else {
                $noticeError = 'بيانات الدخول غير صحيحة، يرجى التأكد من رقم الهاتف وكلمة المرور.';
            }
        }
    }

    // 4. Register New School
    elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $stateVal = trim($_POST['state'] ?? '');
        $cityVal = trim($_POST['city'] ?? '');
        $districtVal = trim($_POST['district'] ?? '');
        $schoolTypeVal = trim($_POST['school_type'] ?? 'private');
        $genderTypeVal = trim($_POST['gender_type'] ?? 'mixed');
        $curriculumVal = trim($_POST['curriculum'] ?? 'sudanese');
        $establishedYearVal = trim($_POST['established_year'] ?? '');
        $stagesArr = isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : ['kindergarten', 'primary'];
        $phoneVal = trim($_POST['phone'] ?? '');
        $whatsappVal = trim($_POST['whatsapp'] ?? '');
        $ownerNameVal = trim($_POST['owner_name'] ?? '');
        $ownerTitleVal = trim($_POST['owner_title'] ?? 'مدير المدرسة');
        $ownerPhoneVal = trim($_POST['owner_phone'] ?? '');
        $ownerEmailVal = trim($_POST['owner_email'] ?? '');
        $pwd = $_POST['password'] ?? '';
        $pwdConfirm = $_POST['password_confirmation'] ?? '';

        if (mb_strlen($name) < 3) {
            $noticeError = 'يرجى إدخال اسم المدرسة بشكل صحيح (3 أحرف على الأقل).';
        } elseif (!$stateVal || !$cityVal) {
            $noticeError = 'يرجى اختيار الولاية والمدينة.';
        } elseif (!$phoneVal) {
            $noticeError = 'يرجى إدخال هاتف المدرسة للتواصل.';
        } elseif (mb_strlen($ownerNameVal) < 3) {
            $noticeError = 'يرجى إدخال اسم المسؤول / مدير المدرسة.';
        } elseif (strlen($ownerPhoneVal) < 7) {
            $noticeError = 'يرجى إدخال رقم هاتف المسؤول بشكل صحيح.';
        } elseif (strlen($pwd) < 6) {
            $noticeError = 'كلمة المرور يجب ألا تقل عن 6 أحرف أو أرقام.';
        } elseif ($pwd !== $pwdConfirm) {
            $noticeError = 'كلمتا المرور غير متطابقتين.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM daleel_schools WHERE owner_phone = ? LIMIT 1");
            $checkStmt->execute([$ownerPhoneVal]);
            if ($checkStmt->fetch()) {
                $noticeError = 'رقم هاتف المسؤول مسجل مسبقاً لمدرسة أخرى. يمكنك تسجيل الدخول به مباشرة.';
            } else {
                $baseSlug = !empty($name_en) ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($name_en)) : 'school-' . substr(bin2hex(random_bytes(4)), 0, 6);
                $baseSlug = trim($baseSlug, '-');
                $finalSlug = $baseSlug;
                $c = 1;
                while (true) {
                    $slugCheck = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ?");
                    $slugCheck->execute([$finalSlug]);
                    if (!$slugCheck->fetch()) break;
                    $finalSlug = $baseSlug . '-' . $c;
                    $c++;
                }

                try {
                    $insStmt = $pdo->prepare("INSERT INTO daleel_schools (
                        name, name_en, slug, school_type, gender_type, curriculum, established_year, stages,
                        state, city, district, phone, whatsapp, email, facilities,
                        status, owner_name, owner_title, owner_phone, owner_email, password,
                        rating_avg, reviews_count, views_count, is_schoplus_client, is_verified, created_at, updated_at
                    ) VALUES (
                        :name, :name_en, :slug, :school_type, :gender_type, :curriculum, :established_year, :stages,
                        :state, :city, :district, :phone, :whatsapp, :email, :facilities,
                        'pending', :owner_name, :owner_title, :owner_phone, :owner_email, :password,
                        5.00, 0, 1, 0, 0, NOW(), NOW()
                    )");
                    $insStmt->execute([
                        ':name' => $name,
                        ':name_en' => $name_en ?: null,
                        ':slug' => $finalSlug,
                        ':school_type' => $schoolTypeVal,
                        ':gender_type' => $genderTypeVal,
                        ':curriculum' => $curriculumVal,
                        ':established_year' => $establishedYearVal ?: null,
                        ':stages' => json_encode($stagesArr, JSON_UNESCAPED_UNICODE),
                        ':state' => $stateVal,
                        ':city' => $cityVal,
                        ':district' => $districtVal ?: null,
                        ':phone' => $phoneVal,
                        ':whatsapp' => $whatsappVal ?: $phoneVal,
                        ':email' => $ownerEmailVal ?: null,
                        ':facilities' => json_encode(['smart_classes', 'computer_lab', 'library', 'sports_court'], JSON_UNESCAPED_UNICODE),
                        ':owner_name' => $ownerNameVal,
                        ':owner_title' => $ownerTitleVal,
                        ':owner_phone' => $ownerPhoneVal,
                        ':owner_email' => $ownerEmailVal ?: null,
                        ':password' => password_hash($pwd, PASSWORD_BCRYPT),
                    ]);
                    $newSchoolId = (int)$pdo->lastInsertId();
                    $_SESSION['daleel_school_id'] = $newSchoolId;
                    $noticeSuccess = 'تم إرسال طلب انضمام مدرستكم بنجاح! سيتم مراجعة الطلب وتفعيله، كما يمكنك إدارة بيانات المدرسة الآن.';
                } catch (\Exception $e) {
                    $noticeError = 'حدث خطأ أثناء تسجيل المدرسة: ' . $e->getMessage();
                }
            }
        }
    }
}

// Filter inputs
$q        = trim($_GET['q'] ?? '');
$state    = trim($_GET['state'] ?? '');
$city     = trim($_GET['city'] ?? '');
$stage    = trim($_GET['stage'] ?? '');
$quick    = trim($_GET['quick'] ?? ''); // smart, top_rated, latest
$tab      = trim($_GET['tab'] ?? 'home'); // home, search, schoplus, news, manage, login, register
$schoolId = (int)($_GET['school_id'] ?? 0);

// Single School Detail View
$selectedSchool = null;
if ($schoolId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? AND status = 'approved'");
    $stmt->execute([$schoolId]);
    $selectedSchool = $stmt->fetch();
    if ($selectedSchool) {
        $pdo->prepare("UPDATE daleel_schools SET views_count = views_count + 1 WHERE id = ?")->execute([$schoolId]);
    }
}

// Stats
$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$schoplusCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$statesCount = (int)$pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: 18;

// States distribution
$stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
$statesWithCities = SudanGeography::statesWithCities();
$schoolStages = SudanGeography::schoolStages();
$allSudanStates = SudanGeography::states();

// Autocomplete suggestions list (school names, cities, states)
$searchSuggestions = [];
try {
    $names = $pdo->query("SELECT DISTINCT name FROM daleel_schools WHERE status = 'approved' LIMIT 40")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $cities = $pdo->query("SELECT DISTINCT city FROM daleel_schools WHERE status = 'approved' AND city IS NOT NULL LIMIT 20")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $searchSuggestions = array_unique(array_merge($names, $cities, $allSudanStates));
} catch (\Exception $e) {}

// Query schools based on tab & filters
$where = ["status = 'approved'"];
$params = [];

if ($tab === 'schoplus' || $quick === 'smart') {
    $where[] = "is_schoplus_client = 1";
}
if ($q !== '') {
    $where[] = "(name LIKE ? OR name_en LIKE ? OR district LIKE ? OR address LIKE ? OR description LIKE ? OR city LIKE ? OR state LIKE ?)";
    $term = "%{$q}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
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

// Order clause
$orderBy = "is_featured DESC, is_schoplus_client DESC, rating_avg DESC, id ASC";
if ($quick === 'top_rated') {
    $orderBy = "rating_avg DESC, reviews_count DESC, id ASC";
} elseif ($quick === 'latest') {
    $orderBy = "created_at DESC, id DESC";
}

$whereClause = implode(' AND ', $where);
$schoolsStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY {$orderBy} LIMIT 60");
$schoolsStmt->execute($params);
$schools = $schoolsStmt->fetchAll();

// News items
$newsStmt = $pdo->query("
    SELECT n.*, s.name as school_name, s.logo_path as school_logo
    FROM daleel_school_news n
    JOIN daleel_schools s ON n.school_id = s.id
    WHERE n.is_active = 1 AND s.status = 'approved'
    ORDER BY n.published_at DESC LIMIT 30
");
$allNews = $newsStmt->fetchAll();

// School reviews for single view
$schoolReviews = [];
if ($selectedSchool) {
    $revStmt = $pdo->prepare("SELECT * FROM daleel_school_reviews WHERE school_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 20");
    $revStmt->execute([$selectedSchool['id']]);
    $schoolReviews = $revStmt->fetchAll();
}

// Check logged in school session
$mySchool = null;
if (!empty($_SESSION['daleel_school_id'])) {
    $myStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ?");
    $myStmt->execute([(int)$_SESSION['daleel_school_id']]);
    $mySchool = $myStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>دليل المدارس السودانية — SchoPlus Mobile</title>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0B2A52">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Cairo Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        :root {
            /* ── SchoPlus Mobile Apps Design Tokens ── */
            --m-brand-navy: #0B2A52;
            --m-brand-blue: #1E5FA8;
            --m-brand-gold: #F5A020;
            --m-brand-gold-dark: #D97706;
            --m-text: #0F172A;
            --m-muted: #64748B;
            --m-border: #E2E8F0;
            --m-border-strong: #CBD5E1;
            --m-card-bg: #FFFFFF;
            --m-bg: #F8FAFC;
            --m-radius: 18px;
            --m-radius-sm: 12px;
            --m-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            --m-shadow-hover: 0 10px 28px -4px rgba(15, 23, 42, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--m-bg);
            color: var(--m-text);
            min-height: 100vh;
            padding-bottom: 95px;
            overflow-x: hidden;
            user-select: none;
            position: relative;
        }

        /* ── Header: Matching SchoPlus 5 Apps ── */
        .m-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--m-border);
            padding: 10px 16px;
            padding-top: max(10px, env(safe-area-inset-top));
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        }
        .m-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--m-text);
            min-width: 0;
            flex: 1;
        }
        .m-brand-logo {
            height: 36px;
            width: 36px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .m-brand-text {
            display: flex;
            flex-direction: column;
            justify-content: center;
            line-height: 1.2;
            min-width: 0;
        }
        .m-brand-title {
            font-weight: 900;
            font-size: 0.92rem;
            color: var(--m-brand-navy);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .m-brand-subtitle {
            font-size: 0.68rem;
            color: var(--m-muted);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .m-header-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .m-btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: #ffffff;
            color: var(--m-brand-navy);
            border: 1px solid var(--m-border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .m-btn-icon:active {
            transform: scale(0.92);
            background: #F1F5F9;
        }

        /* ── Hero Search & Interactive Filter Card (Parity with Web Hero) ── */
        .m-hero-filter-card {
            background: linear-gradient(165deg, #FFFDF9 0%, #F8F3EA 60%, #EFE4D4 100%);
            border: 1.5px solid #E2D7C7;
            border-radius: var(--m-radius);
            padding: 16px 14px 14px;
            margin: 14px 14px 12px;
            box-shadow: 0 8px 24px -4px rgba(11, 42, 82, 0.08);
            position: relative;
            overflow: hidden;
        }
        .m-hero-filter-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(30, 95, 168, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .m-hero-badge-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .m-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #E2D5C3;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--m-brand-navy);
            box-shadow: 0 2px 6px rgba(11, 42, 82, 0.04);
        }
        .m-hero-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--m-brand-gold);
            box-shadow: 0 0 0 2px rgba(245, 160, 32, 0.25);
        }
        .m-hero-stat-pill {
            font-size: 0.68rem;
            color: var(--m-brand-blue);
            font-weight: 800;
            background: rgba(30, 95, 168, 0.08);
            padding: 3px 8px;
            border-radius: 999px;
        }
        .m-hero-headline {
            font-weight: 900;
            font-size: 1.05rem;
            color: var(--m-brand-navy);
            line-height: 1.35;
            margin-bottom: 2px;
        }
        .m-hero-subheadline {
            font-size: 0.74rem;
            color: #64748B;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        /* Hero Search Form */
        .m-filter-form {
            background: #ffffff;
            border: 1.5px solid #E2D7C7;
            border-radius: 16px;
            padding: 12px 10px;
            box-shadow: 0 4px 16px rgba(11, 42, 82, 0.05);
        }
        .m-filter-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        .m-filter-select-wrap {
            flex: 1;
            position: relative;
        }
        .m-filter-select {
            width: 100%;
            border: 1px solid #DCE4EE;
            border-radius: 10px;
            background: #F8FAFC;
            color: var(--m-text);
            font-size: 0.76rem;
            font-weight: 700;
            font-family: 'Cairo', sans-serif;
            padding: 7px 10px;
            outline: none;
            transition: all 0.2s ease;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='%2364748B'%3E%3Cpath%20d='M7%2010l5%205%205-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 8px center;
            background-size: 16px;
            padding-left: 26px;
        }
        .m-filter-select:focus {
            border-color: var(--m-brand-blue);
            background-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(30, 95, 168, 0.12);
        }
        .m-search-input-wrap {
            display: flex;
            align-items: center;
            background: #F8FAFC;
            border: 1px solid #DCE4EE;
            border-radius: 10px;
            padding: 1px 10px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        .m-search-input-wrap:focus-within {
            border-color: var(--m-brand-blue);
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(30, 95, 168, 0.12);
        }
        .m-search-input-wrap input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.80rem;
            font-weight: 600;
            font-family: 'Cairo', sans-serif;
            color: var(--m-text);
            width: 100%;
            padding: 8px 6px;
        }
        .m-search-submit-btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, var(--m-brand-navy) 0%, var(--m-brand-blue) 100%);
            color: #ffffff;
            font-size: 0.84rem;
            font-weight: 800;
            font-family: 'Cairo', sans-serif;
            padding: 9px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(11, 42, 82, 0.22);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .m-search-submit-btn:active {
            transform: scale(0.97);
        }



        /* ── States Horizontal Pills Slider ── */
        .m-states-bar {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 2px 14px 10px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .m-states-bar::-webkit-scrollbar { display: none; }

        .m-state-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1.5px solid var(--m-border);
            padding: 6px 14px;
            border-radius: 50px;
            text-decoration: none;
            color: #334155;
            font-size: 0.76rem;
            font-weight: 700;
            white-space: nowrap;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
            flex-shrink: 0;
        }
        .m-state-pill:active {
            transform: scale(0.96);
        }
        .m-state-pill.active {
            background: var(--m-brand-blue);
            color: #ffffff;
            border-color: var(--m-brand-blue);
            box-shadow: 0 4px 14px rgba(30, 95, 168, 0.25);
        }
        .m-state-pill-badge {
            font-size: 0.65rem;
            background: rgba(0,0,0,0.06);
            padding: 1px 6px;
            border-radius: 999px;
        }
        .m-state-pill.active .m-state-pill-badge {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }

        /* ── Section Header ── */
        .m-sec-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 16px 8px;
        }
        .m-sec-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--m-text);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .m-sec-badge {
            font-size: 0.72rem;
            color: var(--m-muted);
            font-weight: 700;
        }

        /* ── School Card ── */
        .m-school-card {
            background: var(--m-card-bg);
            border: 1px solid var(--m-border);
            border-radius: var(--m-radius);
            padding: 15px;
            margin: 0 14px 12px;
            box-shadow: var(--m-shadow);
            text-decoration: none;
            color: var(--m-text);
            display: block;
            position: relative;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .m-school-card:active {
            transform: scale(0.985);
            box-shadow: var(--m-shadow-hover);
            border-color: var(--m-brand-blue);
        }
        .m-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .m-card-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #F8FAFC;
            border: 1px solid var(--m-border);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            flex-shrink: 0;
        }
        .m-card-avatar img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .m-card-info {
            flex: 1;
            min-width: 0;
        }
        .m-card-name-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .m-card-name {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--m-text);
            line-height: 1.3;
        }
        .m-card-schoplus-tag {
            font-size: 0.65rem;
            font-weight: 800;
            background: #FEF3C7;
            color: #B45309;
            padding: 1.5px 7px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .m-card-meta {
            font-size: 0.72rem;
            color: var(--m-muted);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .m-card-est {
            color: #059669;
            font-weight: 700;
        }
        .m-card-rating {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #F8FAFC;
            border: 1px solid var(--m-border);
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--m-text);
            flex-shrink: 0;
        }

        .m-card-desc {
            font-size: 0.74rem;
            color: var(--m-muted);
            margin-top: 10px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Card Action Row */
        .m-card-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--m-border);
        }
        .m-card-tag {
            font-size: 0.70rem;
            font-weight: 700;
            color: var(--m-brand-blue);
            background: #EFF6FF;
            padding: 3px 10px;
            border-radius: 999px;
        }
        .m-card-btns {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .m-act-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .m-act-wa {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .m-act-wa:active {
            background: #059669;
            color: #ffffff;
        }
        .m-act-call {
            background: #EFF6FF;
            color: #1E5FA8;
            border: 1px solid #BFDBFE;
        }
        .m-act-call:active {
            background: #1E5FA8;
            color: #ffffff;
        }

        /* ── News Card ── */
        .m-news-card {
            background: #ffffff;
            border: 1px solid var(--m-border);
            border-radius: var(--m-radius);
            padding: 16px;
            margin: 0 14px 12px;
            box-shadow: var(--m-shadow);
        }
        .m-news-title {
            font-size: 0.90rem;
            font-weight: 800;
            color: var(--m-text);
            margin: 8px 0 6px;
            line-height: 1.4;
        }
        .m-news-text {
            font-size: 0.74rem;
            color: var(--m-muted);
            line-height: 1.55;
        }

        /* ── Floating Bottom Navigation: 100% SchoPlus Style ── */
        .m-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid var(--m-border);
            padding: 6px 12px max(8px, env(safe-area-inset-bottom));
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.06);
        }
        .m-nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--m-muted);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
            transition: all 0.15s ease;
            position: relative;
        }
        .m-nav-link i {
            font-size: 1.15rem;
            margin-bottom: 2px;
            transition: transform 0.15s ease;
        }
        .m-nav-link.active {
            color: var(--m-brand-blue);
            background: #EFF6FF;
        }
        .m-nav-link.active i {
            color: var(--m-brand-blue);
            transform: translateY(-1px);
        }
        .m-nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--m-brand-gold);
        }

        /* Prominent Center Tab */
        .m-nav-center-btn {
            position: relative;
            top: -14px;
            background: var(--m-brand-navy);
            border: 3px solid #ffffff;
            border-radius: 50%;
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 16px rgba(11, 42, 82, 0.28);
            text-decoration: none;
            transition: transform 0.15s ease;
        }
        .m-nav-center-btn i {
            font-size: 1.3rem;
            color: var(--m-brand-gold);
        }
        .m-nav-center-btn:active {
            transform: scale(0.92) translateY(-2px);
        }
        .m-nav-center-btn.active {
            background: var(--m-brand-blue);
        }

        /* ── School Details Modal / View ── */
        .m-detail-sheet {
            padding: 16px 14px;
        }
        .m-detail-header-card {
            background: #ffffff;
            border: 1px solid var(--m-border);
            border-radius: var(--m-radius);
            padding: 18px;
            text-align: center;
            margin-bottom: 14px;
            box-shadow: var(--m-shadow);
        }
        .m-detail-logo {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: #F8FAFC;
            border: 1.5px solid var(--m-border);
            padding: 8px;
            margin: 0 auto 10px;
            object-fit: contain;
        }
        .m-detail-name {
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--m-brand-navy);
        }
        .m-detail-loc {
            font-size: 0.78rem;
            color: var(--m-muted);
            margin-top: 4px;
        }
        .m-info-block {
            background: #ffffff;
            border: 1px solid var(--m-border);
            border-radius: var(--m-radius);
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--m-shadow);
        }
        .m-info-title {
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--m-brand-navy);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .m-info-content {
            font-size: 0.76rem;
            color: var(--m-text);
            line-height: 1.6;
        }
        .m-tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            color: #334155;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.74rem;
            font-weight: 700;
            margin: 3px 4px 5px 0;
        }
        .m-tag-pill i {
            color: var(--m-brand-blue);
            font-size: 0.85rem;
        }

        /* Action Bottom Sheet (إدارة الصفحة / طلبات الانضمام) */
        .m-bottom-sheet-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1050;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .m-bottom-sheet-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }
        .m-bottom-sheet {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #ffffff;
            border-radius: 24px 24px 0 0;
            padding: 20px 18px max(24px, env(safe-area-inset-bottom));
            z-index: 1060;
            transform: translateY(100%);
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
        }
        .m-bottom-sheet.active {
            transform: translateY(0);
        }
        .m-sheet-bar {
            width: 44px;
            height: 4px;
            border-radius: 99px;
            background: #CBD5E1;
            margin: 0 auto 16px;
        }
        .m-sheet-option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid var(--m-border);
            text-decoration: none;
            color: var(--m-text);
            margin-bottom: 10px;
            background: #FFFFFF;
            transition: all 0.15s ease;
        }
        .m-sheet-option:active {
            background: #F8FAFC;
            transform: scale(0.98);
        }
        .m-sheet-opt-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .m-sheet-opt-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--m-brand-navy);
            margin-bottom: 2px;
        }
        .m-sheet-opt-desc {
            font-size: 0.70rem;
            color: var(--m-muted);
            font-weight: 600;
            line-height: 1.35;
        }

        /* Gallery Grid */
        .m-gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 8px;
        }
        .m-gallery-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--m-border);
            background: #F1F5F9;
        }
        .m-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Fees Table */
        .m-fees-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--m-border);
            border-radius: 12px;
            overflow: hidden;
            font-size: 0.74rem;
        }
        .m-fees-table th {
            background: #F8FAFC;
            padding: 8px 10px;
            font-weight: 800;
            color: #475569;
            border-bottom: 1px solid var(--m-border);
        }
        .m-fees-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #F1F5F9;
            color: var(--m-text);
        }
        .m-fees-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>

    <!-- ── Header ── -->
    <header class="m-header">
        <a href="/mobile/" class="m-brand">
            <img src="/assets/images/logo-icon.png" alt="SchoPlus" class="m-brand-logo">
            <div class="m-brand-text">
                <span class="m-brand-title">دليل المدارس السودانية</span>
                <span class="m-brand-subtitle">خدمة رقمية من منظومة ايسكو بلص</span>
            </div>
        </a>
        <div class="m-header-actions">
            <?php if ($selectedSchool || $tab !== 'home' || $q !== '' || $state !== ''): ?>
                <a href="/mobile/" class="m-btn-icon" title="الرئيسية">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php else: ?>
                <a href="/mobile/" class="m-btn-icon" title="تحديث">
                    <i class="fas fa-redo-alt text-warning"></i>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Notification Alerts -->
    <?php if ($noticeSuccess): ?>
        <div class="mx-3 mt-3 alert alert-success rounded-4 border-0 shadow-sm d-flex align-items-center gap-2 py-2 px-3 fw-bold" style="font-size:0.78rem;">
            <i class="fas fa-check-circle fa-lg text-success"></i>
            <div><?= e($noticeSuccess) ?></div>
        </div>
    <?php endif; ?>
    <?php if ($noticeError): ?>
        <div class="mx-3 mt-3 alert alert-danger rounded-4 border-0 shadow-sm d-flex align-items-center gap-2 py-2 px-3 fw-bold" style="font-size:0.78rem;">
            <i class="fas fa-exclamation-circle fa-lg text-danger"></i>
            <div><?= e($noticeError) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($selectedSchool): ?>
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW: DETAILED SCHOOL PROFILE (NATIVE MOBILE VIEW)       -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <?php
            $s = $selectedSchool;
            $sLogo = !empty($s['logo_path']) ? '/' . ltrim($s['logo_path'], '/') : '/assets/images/default-school-logo.svg';
            $stages = !empty($s['stages']) ? json_decode($s['stages'], true) : [];
            $facilities = !empty($s['facilities']) ? json_decode($s['facilities'], true) : [];
            $stageFees = !empty($s['stage_fees']) ? json_decode($s['stage_fees'], true) : [];
            $feeDiscounts = !empty($s['fee_discounts']) ? json_decode($s['fee_discounts'], true) : [];
            $gallery = !empty($s['gallery']) ? json_decode($s['gallery'], true) : [];
            $branches = !empty($s['branches_data']) ? json_decode($s['branches_data'], true) : [];
            $admStatus = $s['admission_status'] ?? 'open';
            $admReqs = !empty($s['admission_requirements']) ? json_decode($s['admission_requirements'], true) : [];

            $admLabels = [
                'open'    => ['label' => 'باب القبول والتسجيل مفتوح', 'badge' => 'متاح', 'color' => '#10B981', 'icon' => 'fa-check-circle'],
                'limited' => ['label' => 'مقاعد محدودة متبقية', 'badge' => 'محدود', 'color' => '#F59E0B', 'icon' => 'fa-exclamation-circle'],
                'early'   => ['label' => 'فتح باب التسجيل المبكر', 'badge' => 'مبكر', 'color' => '#0284C7', 'icon' => 'fa-clock'],
                'closed'  => ['label' => 'القبول والتسجيل مغلق مؤقتاً', 'badge' => 'مغلق', 'color' => '#6B7280', 'icon' => 'fa-lock']
            ];
            $currAdm = $admLabels[$admStatus] ?? $admLabels['open'];
        ?>
        <div class="m-detail-sheet">
            
            <!-- School Hero Card -->
            <div class="m-detail-header-card">
                <img src="<?= e($sLogo) ?>" alt="<?= e($s['name']) ?>" class="m-detail-logo">
                <h1 class="m-detail-name"><?= e($s['name']) ?></h1>
                <?php if (!empty($s['name_en'])): ?>
                    <div class="text-muted small fw-bold" style="font-size:0.75rem;"><?= e($s['name_en']) ?></div>
                <?php endif; ?>

                <div class="m-detail-loc">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                    ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= $s['district'] ? '— ' . e($s['district']) : '' ?>
                </div>

                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                    <?php if ($s['is_schoplus_client']): ?>
                        <span class="m-card-schoplus-tag">⚡ تستخدم نظام ايسكو بلص</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 fw-bold" style="font-size:0.68rem;">
                            <i class="fas fa-robot me-1"></i> ذكاء صناعي وأتمتة المهام
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold" style="font-size:0.72rem;">
                        <?= SudanGeography::schoolTypeLabel($s['school_type']) ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 fw-bold" style="font-size:0.72rem;">
                        <?= SudanGeography::genderTypeLabel($s['gender_type']) ?>
                    </span>
                    <?php if (!empty($s['established_year'])): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold" style="font-size:0.72rem;">
                            تأسست: <?= e($s['established_year']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="m-card-rating">
                        <i class="fas fa-star text-warning"></i> <?= number_format((float)($s['rating_avg'] ?? 5.0), 1) ?>
                    </span>
                </div>

                <!-- Call & WhatsApp Action Row -->
                <div class="d-flex justify-content-center gap-2 mt-3 pt-3 border-top">
                    <?php if (!empty($s['whatsapp']) || !empty($s['phone'])): ?>
                        <?php $wa = $s['whatsapp'] ?: $s['phone']; ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $wa) ?>" target="_blank" class="m-act-btn m-act-wa flex-fill justify-content-center py-2">
                            <i class="fab fa-whatsapp me-1"></i> واتساب الإدارة
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($s['phone'])): ?>
                        <a href="tel:<?= e($s['phone']) ?>" class="m-act-btn m-act-call flex-fill justify-content-center py-2">
                            <i class="fas fa-phone-alt me-1"></i> اتصال هاتفي
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admission Status Banner -->
            <div class="m-info-block" style="border-right: 4px solid <?= $currAdm['color'] ?>;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="m-info-title mb-0" style="color: <?= $currAdm['color'] ?>;">
                        <i class="fas <?= $currAdm['icon'] ?>"></i> <?= $currAdm['label'] ?>
                    </div>
                    <span class="badge text-white" style="background: <?= $currAdm['color'] ?>; font-size:0.70rem;">
                        <?= $currAdm['badge'] ?>
                    </span>
                </div>
                <div class="m-info-content">
                    <?php if (!empty($s['whatsapp'])): ?>
                        يمكنكم التقديم والاستفسار المباشر عن شواغر ومقاعد الطلاب عبر مكتب القبول.
                        <a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $s['whatsapp']) ?>?text=<?= urlencode('مرحباً، أود الاستفسار عن القبول والتسجيل في ' . $s['name']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill mt-2 w-100 fw-bold" style="font-size:0.75rem;">
                            <i class="fab fa-whatsapp me-1"></i> الاستفسار والتقديم عبر الواتساب
                        </a>
                    <?php else: ?>
                        يرجى مراجعة إدارة المدرسة أو الاتصال المباشر للاستفسار عن متطلبات التسجيل.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($s['description'])): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-info-circle text-primary"></i> نبذة عن الصرح التعليمي</div>
                    <div class="m-info-content"><?= nl2br(e($s['description'])) ?></div>
                </div>
            <?php endif; ?>

            <!-- Vision & Mission -->
            <?php if (!empty($s['vision']) || !empty($s['mission'])): ?>
                <div class="m-info-block">
                    <?php if (!empty($s['vision'])): ?>
                        <div class="m-info-title"><i class="fas fa-eye text-warning"></i> الرؤية التربوية</div>
                        <div class="m-info-content mb-3"><?= nl2br(e($s['vision'])) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($s['mission'])): ?>
                        <div class="m-info-title"><i class="fas fa-bullseye text-danger"></i> الرسالة والأهداف</div>
                        <div class="m-info-content"><?= nl2br(e($s['mission'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Stages -->
            <?php if (!empty($stages)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-layer-group text-warning"></i> المراحل التعليمية</div>
                    <div>
                        <?php
                            $allStages = SudanGeography::schoolStages();
                            foreach ((array)$stages as $stg):
                                $stgName = $allStages[$stg] ?? $stg;
                        ?>
                            <span class="m-tag-pill"><i class="fas fa-check-circle text-success"></i><?= e($stgName) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Facilities (Mapped with Arabic Labels and FontAwesome Icons) -->
            <?php if (!empty($facilities)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-cubes text-info"></i> المرافق والخدمات والتجهيزات</div>
                    <div>
                        <?php foreach ((array)$facilities as $facKey): ?>
                            <?php
                                $facLabel = SudanGeography::facilityLabel($facKey);
                                $facIcon = SudanGeography::facilityIcon($facKey);
                            ?>
                            <span class="m-tag-pill">
                                <i class="<?= e($facIcon) ?>"></i>
                                <?= e($facLabel) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Fees & Discounts -->
            <?php if (!empty($stageFees) || !empty($s['approx_fees']) || !empty($feeDiscounts)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-coins text-success"></i> الرسوم الدراسية والمنح</div>
                    
                    <?php if (!empty($s['approx_fees'])): ?>
                        <div class="p-2 mb-3 rounded-3 bg-light border text-muted small d-flex align-items-center justify-content-between">
                            <span>متوسط الرسوم: <strong class="text-dark"><?= e($s['approx_fees']) ?></strong></span>
                            <span class="badge bg-white text-secondary border">تقديري</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($stageFees)): ?>
                        <table class="m-fees-table mb-3">
                            <thead>
                                <tr>
                                    <th>المرحلة</th>
                                    <th>البيان</th>
                                    <th>القيمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ((array)$stageFees as $fItem): ?>
                                    <tr>
                                        <td><strong><?= e($fItem['stage'] ?? 'عام') ?></strong></td>
                                        <td><?= e($fItem['title'] ?? '') ?></td>
                                        <td class="text-success fw-bold"><?= e($fItem['amount'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($feeDiscounts)): ?>
                        <div class="mt-2 pt-2 border-top">
                            <span class="fw-bold small text-purple d-block mb-2" style="color:#7E22CE;">
                                <i class="fas fa-gift me-1"></i> العروض والخصومات المتاحة:
                            </span>
                            <?php foreach ((array)$feeDiscounts as $disc): ?>
                                <div class="p-2 mb-2 rounded-3 bg-light border" style="border-right:3px solid #9333EA !important;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold small text-dark"><?= e($disc['title'] ?? '') ?></span>
                                        <span class="badge bg-purple text-white" style="background:#9333EA;"><?= e($disc['value'] ?? '') ?></span>
                                    </div>
                                    <?php if (!empty($disc['applies_to'])): ?>
                                        <div class="text-muted" style="font-size:0.68rem; margin-top:2px;"><?= e($disc['applies_to']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Campus Photos Gallery -->
            <?php if (!empty($gallery)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-camera text-primary"></i> صور من مرافق وفعاليات المدرسة</div>
                    <div class="m-gallery-grid">
                        <?php foreach ((array)$gallery as $photo): ?>
                            <?php
                                $photoPath = is_array($photo) ? ($photo['path'] ?? '') : $photo;
                                $photoCaption = is_array($photo) ? ($photo['caption'] ?? '') : '';
                                if (!$photoPath) continue;
                                $photoUrl = '/' . ltrim($photoPath, '/');
                            ?>
                            <div class="m-gallery-item">
                                <img src="<?= e($photoUrl) ?>" alt="<?= e($photoCaption ?: $s['name']) ?>" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Branches -->
            <?php if (!empty($branches)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-code-branch text-secondary"></i> الفروع والمجمعات الأخرى</div>
                    <?php foreach ((array)$branches as $br): ?>
                        <div class="p-2 mb-2 rounded-3 bg-light border">
                            <div class="fw-bold small text-dark"><?= e($br['name'] ?? $br['branch_name'] ?? 'فرع المدرسة') ?></div>
                            <div class="text-muted" style="font-size:0.70rem;"><?= e($br['address'] ?? $br['city'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Address & Location -->
            <?php if (!empty($s['address']) || !empty($s['google_map_url'])): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-map-marked-alt text-danger"></i> العنوان والموقع على الخريطة</div>
                    <div class="m-info-content"><?= e($s['address'] ?: 'ولاية ' . $s['state'] . ' — ' . $s['city']) ?></div>
                    <?php if (!empty($s['google_map_url'])): ?>
                        <a href="<?= e($s['google_map_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill mt-2 w-100 fw-bold" style="font-size:0.74rem;">
                            <i class="fas fa-location-arrow me-1"></i> فتح الخريطة (Google Maps)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Native Admission Form (Direct Database Storage into daleel_school_inquiries) -->
            <div class="m-info-block" style="border-top: 3px solid #10B981;">
                <div class="m-info-title" style="color: #059669;">
                    <i class="fas fa-file-signature"></i> تقديم طلب قبول وتسجيل إلكتروني
                </div>
                <p class="text-muted small mb-3" style="font-size:0.72rem;">
                    سجل بيانات الطالب للتقديم وحجز المقعد في المدرسة مباشرة وحفظه بالنظام:
                </p>

                <form action="/mobile/?school_id=<?= (int)$s['id'] ?>" method="POST">
                    <input type="hidden" name="action" value="add_inquiry">
                    <input type="hidden" name="school_id" value="<?= (int)$s['id'] ?>">

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">اسم ولي الأمر كاملاً *</label>
                        <input type="text" name="parent_name" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="الاسم ثلاثي أو رباعي..." required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">رقم هاتف ولي الأمر للتواصل *</label>
                        <input type="tel" name="parent_phone" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="09xxxxxxxx أو 01xxxxxxxx..." required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">اسم الطالب / الطالبة</label>
                        <input type="text" name="student_name" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="اسم الطالب المراد تسجيله...">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">النوع</label>
                            <select name="student_gender" class="form-select form-select-sm rounded-3" style="font-size:0.78rem;">
                                <option value="male">بنين</option>
                                <option value="female">بنات</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">المرحلة</label>
                            <select name="student_stage" class="form-select form-select-sm rounded-3" style="font-size:0.78rem;">
                                <option value="">اختر المرحلة</option>
                                <?php foreach ($schoolStages as $stgK => $stgL): ?>
                                    <option value="<?= e($stgK) ?>"><?= e($stgL) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">الصف الدراسي</label>
                        <input type="text" name="student_grade" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="مثال: الصف الأول، الصف الرابع...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.72rem;">ملاحظات أو استفسار إضافي</label>
                        <textarea name="message" class="form-control form-control-sm rounded-3" rows="2" style="font-size:0.78rem;" placeholder="أي ملاحظات تخص الطالب أو متطلبات خاصة..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success rounded-pill w-100 fw-bold py-2 shadow-sm" style="font-size:0.80rem; background:#10B981; border-color:#10B981;">
                        <i class="fas fa-paper-plane me-1"></i> إرسال طلب القبول لإدارة المدرسة
                    </button>
                </form>
            </div>

            <!-- School Reviews Section -->
            <div class="m-info-block">
                <div class="m-info-title"><i class="fas fa-comments text-warning"></i> تقييمات أولياء الأمور (<?= count($schoolReviews) ?>)</div>
                
                <?php if (empty($schoolReviews)): ?>
                    <p class="text-muted small mb-3" style="font-size:0.72rem;">كن أول من يقيّم هذه المدرسة ويشارك تجربته مع المجتمع التعليمي.</p>
                <?php else: ?>
                    <?php foreach ($schoolReviews as $rev): ?>
                        <div class="p-2 mb-2 rounded-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-dark"><?= e($rev['reviewer_name']) ?></span>
                                <span class="text-warning small">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= (int)$rev['rating'] ? '' : 'text-muted opacity-25' ?>" style="font-size:0.65rem;"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div class="text-muted small" style="font-size:0.72rem;"><?= nl2br(e($rev['comment'])) ?></div>
                            <?php if (!empty($rev['school_reply'])): ?>
                                <div class="mt-2 p-2 rounded-2 bg-white border small" style="border-right:3px solid var(--m-brand-blue) !important; font-size:0.70rem;">
                                    <strong class="text-primary d-block">رد إدارة المدرسة:</strong>
                                    <?= nl2br(e($rev['school_reply'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Add Review Form -->
                <form action="/mobile/?school_id=<?= (int)$s['id'] ?>" method="POST" class="mt-3 pt-3 border-top">
                    <input type="hidden" name="action" value="add_review">
                    <input type="hidden" name="school_id" value="<?= (int)$s['id'] ?>">

                    <div class="fw-bold text-dark mb-2" style="font-size:0.76rem;">أضف تقييمك للمدرسة:</div>
                    <div class="mb-2">
                        <input type="text" name="reviewer_name" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="اسمك الكريم *" required>
                    </div>
                    <div class="mb-2">
                        <select name="rating" class="form-select form-select-sm rounded-3" style="font-size:0.78rem;">
                            <option value="5">⭐⭐⭐⭐⭐ ممتاز جداً (5/5)</option>
                            <option value="4">⭐⭐⭐⭐ جيد جداً (4/5)</option>
                            <option value="3">⭐⭐⭐ متوسط (3/5)</option>
                            <option value="2">⭐⭐ مقبول (2/5)</option>
                            <option value="1">⭐ ضعيف (1/5)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <textarea name="comment" class="form-control form-control-sm rounded-3" rows="2" style="font-size:0.78rem;" placeholder="اكتب تقييمك وملاحظاتك..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill w-100 fw-bold" style="font-size:0.76rem;">
                        <i class="fas fa-star me-1"></i> إرسال التقييم
                    </button>
                </form>
            </div>

        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VIEW 2: SEARCH TAB (Smart, Comprehensive & Autocomplete)    -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'search'): ?>
        <div class="px-3 pt-3">
            <div class="m-sec-head px-0">
                <span class="m-sec-title"><i class="fas fa-search text-warning"></i> البحث الذكي والشامل</span>
                <span class="m-sec-badge"><?= $totalSchools ?> مدرسة مسجلة</span>
            </div>

            <!-- Smart Single Input Search with Autocomplete Datalist -->
            <form action="/mobile/" method="GET" class="m-filter-form mt-2">
                <input type="hidden" name="tab" value="search">

                <div class="m-search-input-wrap mb-3" style="padding: 4px 10px;">
                    <i class="fas fa-search text-muted me-2"></i>
                    <input type="text" name="q" value="<?= e($q) ?>" list="searchOptions" placeholder="اكتب اسم المدرسة، المدينة، الولاية، أو الحي..." autofocus autocomplete="off" style="font-size:0.86rem;">
                    <datalist id="searchOptions">
                        <?php foreach ($searchSuggestions as $sug): ?>
                            <option value="<?= e($sug) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <?php if ($q !== ''): ?>
                        <a href="/mobile/?tab=search" class="text-muted"><i class="fas fa-times-circle"></i></a>
                    <?php endif; ?>
                </div>

                <button type="submit" class="m-search-submit-btn">
                    <i class="fas fa-search"></i>
                    <span>ابحث الآن في المدارس</span>
                </button>
            </form>

            <!-- Results -->
            <div class="m-sec-head px-0 mt-3">
                <span class="m-sec-title">نتائج البحث <?= $q ? 'عن "' . e($q) . '"' : '' ?></span>
                <span class="m-sec-badge"><?= count($schools) ?> مدرسة</span>
            </div>

            <?php if (empty($schools)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="fw-bold" style="font-size: 0.85rem;">لا توجد مدارس مطابقة للبحث</p>
                    <a href="/mobile/?tab=search" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold">عرض جميع المدارس</a>
                </div>
            <?php else: ?>
                <?php foreach ($schools as $sc): ?>
                    <?php $scLogo = !empty($sc['logo_path']) ? '/' . ltrim($sc['logo_path'], '/') : '/assets/images/default-school-logo.svg'; ?>
                    <div class="m-school-card mx-0">
                        <div class="m-card-top">
                            <div class="m-card-avatar">
                                <img src="<?= e($scLogo) ?>" alt="<?= e($sc['name']) ?>">
                            </div>
                            <div class="m-card-info">
                                <div class="m-card-name-row">
                                    <div class="m-card-name"><?= e($sc['name']) ?></div>
                                    <?php if ($sc['is_schoplus_client']): ?>
                                        <span class="m-card-schoplus-tag">⚡ SchoPlus</span>
                                    <?php endif; ?>
                                </div>
                                <div class="m-card-meta">
                                    <span><i class="fas fa-map-marker-alt text-muted me-1"></i><?= e($sc['state']) ?> — <?= e($sc['city']) ?></span>
                                    <?php if (!empty($sc['established_year'])): ?>
                                        <span class="m-card-est">• تأسست: <?= e($sc['established_year']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="m-card-rating">
                                <i class="fas fa-star text-warning"></i> <?= number_format((float)($sc['rating_avg'] ?? 5.0), 1) ?>
                            </div>
                        </div>

                        <?php if ($sc['is_schoplus_client']): ?>
                            <div class="p-2 my-2 rounded-2 small fw-bold" style="background:#EFF6FF; color:#1E5FA8; border-right:3px solid #1E5FA8; font-size:0.70rem;">
                                <i class="fas fa-bolt text-warning me-1"></i> تستخدم نظام ايسكو بلص • ذكاء صناعي وأتمتة المهام
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($sc['description'])): ?>
                            <div class="m-card-desc"><?= e($sc['description']) ?></div>
                        <?php endif; ?>

                        <div class="m-card-actions">
                            <span class="m-card-tag"><?= SudanGeography::schoolTypeLabel($sc['school_type'] ?? 'private') ?></span>
                            <div class="m-card-btns">
                                <a href="/mobile/?school_id=<?= (int)$sc['id'] ?>" class="m-act-btn m-act-details" style="background:#0B2A52; color:#fff;">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
                                <?php if (!empty($sc['whatsapp']) || !empty($sc['phone'])): ?>
                                    <?php $waNum = $sc['whatsapp'] ?: $sc['phone']; ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $waNum) ?>" target="_blank" class="m-act-btn m-act-wa">
                                        <i class="fab fa-whatsapp"></i> واتساب
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($sc['phone'])): ?>
                                    <a href="tel:<?= e($sc['phone']) ?>" class="m-act-btn m-act-call">
                                        <i class="fas fa-phone-alt"></i> اتصال
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VIEW 3: SCHOPLUS SMART SCHOOLS TAB                         -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'schoplus'): ?>
        <div class="pt-2">
            <!-- SchoPlus Smart Banner -->
            <div class="p-3 mx-3 mb-3 rounded-4 text-white" style="background: linear-gradient(135deg, #0B2A52 0%, #163870 50%, #1E5FA8 100%); box-shadow: 0 8px 24px -4px rgba(11, 42, 82, 0.25);">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold" style="font-size:1.02rem;"><i class="fas fa-bolt text-warning"></i> المدارس الذكية المعتمدة</span>
                    <span class="badge" style="background:#F5A020; color:#0B2A52; font-size:0.68rem;">SchoPlus Powered</span>
                </div>
                <div class="small opacity-75 mb-2" style="font-size:0.74rem;">
                    المدارس التي تدير كافة عملياتها الإدارية، المالية، والأكاديمية بأحدث المنظومات السحابية الذكية.
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2 py-1" style="font-size:0.68rem;">
                        <i class="fas fa-check-circle text-success me-1"></i> تستخدم نظام ايسكو بلص
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2 py-1" style="font-size:0.68rem;">
                        <i class="fas fa-robot text-warning me-1"></i> ذكاء صناعي وأتمتة المهام
                    </span>
                </div>
            </div>

            <div class="m-sec-head">
                <span class="m-sec-title"><i class="fas fa-crown text-warning"></i> مدارس تطبق التحول الرقمي</span>
                <span class="m-sec-badge"><?= count($schools) ?> مدرسة</span>
            </div>

            <?php if (empty($schools)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-school fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="fw-bold" style="font-size: 0.85rem;">لا توجد مدارس ذكية مسجلة حالياً</p>
                </div>
            <?php else: ?>
                <?php foreach ($schools as $sc): ?>
                    <?php $scLogo = !empty($sc['logo_path']) ? '/' . ltrim($sc['logo_path'], '/') : '/assets/images/default-school-logo.svg'; ?>
                    <div class="m-school-card">
                        <div class="m-card-top">
                            <div class="m-card-avatar">
                                <img src="<?= e($scLogo) ?>" alt="<?= e($sc['name']) ?>">
                            </div>
                            <div class="m-card-info">
                                <div class="m-card-name-row">
                                    <div class="m-card-name"><?= e($sc['name']) ?></div>
                                    <span class="m-card-schoplus-tag">⚡ SchoPlus</span>
                                </div>
                                <div class="m-card-meta">
                                    <span><i class="fas fa-map-marker-alt text-muted me-1"></i><?= e($sc['state']) ?> — <?= e($sc['city']) ?></span>
                                    <?php if (!empty($sc['established_year'])): ?>
                                        <span class="m-card-est">• تأسست: <?= e($sc['established_year']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="m-card-rating">
                                <i class="fas fa-star text-warning"></i> <?= number_format((float)($sc['rating_avg'] ?? 5.0), 1) ?>
                            </div>
                        </div>

                        <!-- User Mandatory Directives -->
                        <div class="p-2 my-2 rounded-2 small fw-bold" style="background:#EFF6FF; color:#1E5FA8; border-right:3px solid #1E5FA8; font-size:0.70rem;">
                            <i class="fas fa-bolt text-warning me-1"></i> تستخدم نظام ايسكو بلص • ذكاء صناعي وأتمتة المهام
                        </div>

                        <?php if (!empty($sc['description'])): ?>
                            <div class="m-card-desc"><?= e($sc['description']) ?></div>
                        <?php endif; ?>

                        <div class="m-card-actions">
                            <span class="m-card-tag"><?= SudanGeography::schoolTypeLabel($sc['school_type'] ?? 'private') ?></span>
                            <div class="m-card-btns">
                                <a href="/mobile/?school_id=<?= (int)$sc['id'] ?>" class="m-act-btn m-act-details" style="background:#0B2A52; color:#fff;">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
                                <?php if (!empty($sc['whatsapp']) || !empty($sc['phone'])): ?>
                                    <?php $waNum = $sc['whatsapp'] ?: $sc['phone']; ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $waNum) ?>" target="_blank" class="m-act-btn m-act-wa">
                                        <i class="fab fa-whatsapp"></i> واتساب
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($sc['phone'])): ?>
                                    <a href="tel:<?= e($sc['phone']) ?>" class="m-act-btn m-act-call">
                                        <i class="fas fa-phone-alt"></i> اتصال
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VIEW 4: NEWS & EVENTS TAB                                  -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'news'): ?>
        <div class="pt-3">
            <div class="m-sec-head">
                <span class="m-sec-title"><i class="fas fa-bullhorn text-warning"></i> أخبار وفعاليات المدارس</span>
                <span class="m-sec-badge"><?= count($allNews) ?> خبر</span>
            </div>

            <?php if (empty($allNews)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-newspaper fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="fw-bold" style="font-size: 0.85rem;">لا توجد أخبار منشورة حالياً</p>
                </div>
            <?php else: ?>
                <?php foreach ($allNews as $n): ?>
                    <?php $nLogo = !empty($n['school_logo']) ? '/' . ltrim($n['school_logo'], '/') : '/assets/images/default-school-logo.svg'; ?>
                    <div class="m-news-card">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e($nLogo) ?>" alt="" style="width:28px; height:28px; border-radius:8px; object-fit:contain;">
                            <span class="fw-bold text-dark" style="font-size:0.78rem;"><?= e($n['school_name']) ?></span>
                            <span class="ms-auto text-muted small" style="font-size:0.68rem;"><?= date('Y/m/d', strtotime($n['published_at'])) ?></span>
                        </div>
                        <h3 class="m-news-title"><?= e($n['title']) ?></h3>
                        <p class="m-news-text"><?= nl2br(e(strip_tags($n['content']))) ?></p>
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                            <a href="/mobile/?school_id=<?= (int)$n['school_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size:0.70rem;">
                                زيارة المدرسة <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                            <span class="text-muted small" style="font-size:0.68rem;"><i class="fas fa-eye me-1"></i><?= (int)$n['views_count'] ?> مشاهدة</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VIEW 5: MANAGE / LOGIN / REGISTER (NATIVE EMBEDDED)       -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'manage' || $tab === 'login' || $tab === 'register'): ?>
        <div class="px-3 pt-3">
            <?php if ($mySchool): ?>
                <!-- Logged in State -->
                <div class="m-info-block text-center py-4">
                    <img src="<?= !empty($mySchool['logo_path']) ? '/' . ltrim($mySchool['logo_path'], '/') : '/assets/images/default-school-logo.svg' ?>" style="width:68px; height:68px; border-radius:18px; object-fit:contain; border:2px solid #F1F5F9;" class="mb-2">
                    <h4 class="fw-bold text-dark mb-1" style="font-size:1.05rem;"><?= e($mySchool['name']) ?></h4>
                    <p class="text-muted small mb-3">حساب إدارة المدرسة مفعل</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="/mobile/?school_id=<?= (int)$mySchool['id'] ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" style="font-size:0.78rem;">
                            <i class="fas fa-eye me-1"></i> عرض صفحة مدرستي
                        </a>
                        <a href="/mobile/?logout=1" class="btn btn-outline-danger rounded-pill px-3 py-2 fw-bold" style="font-size:0.78rem;">
                            تسجيل الخروج
                        </a>
                    </div>
                </div>

            <?php elseif ($tab === 'register'): ?>
                <!-- Register New School Form -->
                <div class="m-info-block">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="m-info-title mb-0"><i class="fas fa-plus-circle text-warning"></i> طلب انضمام وتسجيل مدرسة جديدة</div>
                        <a href="/mobile/?tab=login" class="text-primary small fw-bold" style="font-size:0.72rem;">لديك حساب؟ تسجيل الدخول</a>
                    </div>
                    <p class="text-muted small mb-3" style="font-size:0.72rem;">سجل بيانات مدرستك لتظهر في دليل المدارس السودانية المعتمدة:</p>

                    <form action="/mobile/?tab=register" method="POST">
                        <input type="hidden" name="action" value="register">

                        <div class="fw-bold small text-dark mb-1">البيانات الأساسية للمدرسة:</div>
                        <input type="text" name="name" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="اسم المدرسة بالعربية *" required>
                        <input type="text" name="name_en" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="School Name in English">

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="state" class="form-select form-select-sm rounded-3" style="font-size:0.78rem;" required>
                                    <option value="">الولاية *</option>
                                    <?php foreach ($allSudanStates as $stName): ?>
                                        <option value="<?= e($stName) ?>">ولاية <?= e($stName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="text" name="city" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="المدينة / المحلية *" required>
                            </div>
                        </div>

                        <input type="text" name="district" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="الحي أو المنطقة">
                        <input type="tel" name="phone" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="هاتف المدرسة الرسمي للتواصل *" required>
                        <input type="tel" name="whatsapp" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="رقم واتساب المدرسة">

                        <div class="fw-bold small text-dark mb-1 mt-3">بيانات مدير المدرسة / صاحب الحساب:</div>
                        <input type="text" name="owner_name" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="اسم المسؤول / المدير *" required>
                        <input type="tel" name="owner_phone" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="هاتف المسؤول (اسم المستخدم للدخول) *" required>
                        <input type="email" name="owner_email" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="البريد الإلكتروني (اختياري)">
                        <input type="password" name="password" class="form-control form-control-sm rounded-3 mb-2" style="font-size:0.78rem;" placeholder="كلمة المرور (6 خانات على الأقل) *" required>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm rounded-3 mb-3" style="font-size:0.78rem;" placeholder="تأكيد كلمة المرور *" required>

                        <button type="submit" class="btn btn-warning text-dark rounded-pill w-100 fw-bold py-2 shadow-sm" style="font-size:0.80rem; background:#F5A020; border-color:#F5A020;">
                            <i class="fas fa-check-circle me-1"></i> إرسال طلب الانضمام
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- School Owner Login Form -->
                <div class="m-info-block text-center py-4">
                    <img src="/assets/images/logo-icon.png" style="width:58px; height:58px; object-fit:contain;" class="mb-2">
                    <h3 class="fw-bold text-dark mb-1" style="font-size:1.10rem;">دخول أصحاب وإدارات المدارس</h3>
                    <p class="text-muted small mb-4" style="font-size:0.74rem;">إدارة بيانات مدرستك، تحديث الرسوم، واستقبال طلبات القبول</p>

                    <form action="/mobile/?tab=login" method="POST" class="text-start">
                        <input type="hidden" name="action" value="login">

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.74rem;">رقم الهاتف أو البريد الإلكتروني:</label>
                            <input type="text" name="identifier" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="09xxxxxxxx أو email@..." required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1" style="font-size:0.74rem;">كلمة المرور:</label>
                            <input type="password" name="password" class="form-control form-control-sm rounded-3" style="font-size:0.78rem;" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold py-2 shadow-sm" style="font-size:0.80rem; background:#0B2A52; border-color:#0B2A52;">
                            <i class="fas fa-sign-in-alt me-1"></i> تسجيل الدخول
                        </button>
                    </form>

                    <div class="mt-4 pt-3 border-top">
                        <div class="text-muted small mb-2" style="font-size:0.74rem;">ليس لديك حساب مسجل لمدرستك؟</div>
                        <a href="/mobile/?tab=register" class="btn btn-outline-warning text-dark rounded-pill w-100 fw-bold" style="font-size:0.78rem; border-color:#F5A020;">
                            <i class="fas fa-plus-circle me-1 text-warning"></i> طلب انضمام مدرسة جديدة
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- VIEW 6: MAIN HOME VIEW (DEFAULT)                         -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <?php else: ?>

        <!-- HERO SEARCH CARD (Parity with Web Hero Design & Capabilities) -->
        <div class="m-hero-filter-card">
            <div class="m-hero-badge-wrap">
                <span class="m-hero-badge">
                    <span class="m-hero-badge-dot"></span>
                    دليل المدارس السودانية المعتمدة
                </span>
                <span class="m-hero-stat-pill">
                    <?= number_format($totalSchools) ?>+ مدرسة
                </span>
            </div>

            <h1 class="m-hero-headline">
                ابحث وتعرف على المدارس في السودان
            </h1>
            <p class="m-hero-subheadline">
                اختر الولاية والمدينة والمرحلة للوصول إلى بيانات المدارس المعتمدة والتواصل المباشر.
            </p>

            <!-- SMART INTERACTIVE FILTER FORM (With working Search Button) -->
            <form action="/mobile/" method="GET" class="m-filter-form" id="mobileSearchForm">
                <input type="hidden" name="tab" value="home">

                <!-- State & City Row -->
                <div class="m-filter-row">
                    <div class="m-filter-select-wrap">
                        <select name="state" class="m-filter-select" id="mStateSelect">
                            <option value="">جميع الولايات (الكل)</option>
                            <?php foreach ($statesWithCities as $stName => $citiesList): ?>
                                <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>>ولاية <?= e($stName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="m-filter-select-wrap">
                        <select name="city" class="m-filter-select" id="mCitySelect">
                            <option value="">جميع المدن / المحليات</option>
                            <?php if ($state && isset($statesWithCities[$state])): ?>
                                <?php foreach ($statesWithCities[$state] as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Stage Select -->
                <div class="m-filter-row">
                    <div class="m-filter-select-wrap">
                        <select name="stage" class="m-filter-select" id="mStageSelect">
                            <option value="">جميع المراحل الدراسية</option>
                            <?php foreach ($schoolStages as $sKey => $sLbl): ?>
                                <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Keyword / School Name Input -->
                <div class="m-search-input-wrap">
                    <i class="fas fa-search text-muted me-1" style="font-size:0.85rem;"></i>
                    <input type="text" name="q" value="<?= e($q) ?>" list="homeSearchOptions" placeholder="ابحث باسم المدرسة، الحي، أو المنطقة..." autocomplete="off">
                    <datalist id="homeSearchOptions">
                        <?php foreach ($searchSuggestions as $sug): ?>
                            <option value="<?= e($sug) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <?php if ($q !== '' || $state !== '' || $city !== '' || $stage !== ''): ?>
                        <a href="/mobile/" class="text-muted text-decoration-none px-2" title="إعادة تعيين">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Working Submit Search Button -->
                <button type="submit" class="m-search-submit-btn" id="mSearchBtn">
                    <i class="fas fa-search"></i>
                    <span>تطبيق البحث وعرض المدارس</span>
                </button>
            </form>
        </div>

        <!-- 4-Cards-at-Once States Carousel with Interactive Arrows (User Directive) -->
        <div class="mx-3 mb-3 p-3 bg-white rounded-4 border shadow-sm position-relative">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-bold small text-dark" style="font-size:0.78rem;">
                    <i class="fas fa-map-marked-alt text-primary me-1"></i>
                    تصفح حسب الولايات (<?= count($allSudanStates) ?> ولاية)
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-light border rounded-2 px-2 py-1" onclick="slideStates('prev')" title="السابق">
                        <i class="fas fa-chevron-right" style="font-size:0.70rem;"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light border rounded-2 px-2 py-1" onclick="slideStates('next')" title="التالي">
                        <i class="fas fa-chevron-left" style="font-size:0.70rem;"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex gap-2 overflow-x-auto pb-1" id="statesSlider" style="scroll-behavior:smooth; scrollbar-width:none;">
                <a href="/mobile/" class="text-center text-decoration-none p-2 rounded-3 border flex-shrink-0 <?= $state === '' ? 'bg-primary text-white border-primary' : 'bg-light text-dark' ?>" style="width:calc((100% - 24px) / 4); min-width:calc((100% - 24px) / 4);">
                    <i class="fas fa-globe-africa d-block mb-1 text-warning" style="font-size:1.1rem;"></i>
                    <div class="fw-bold text-truncate" style="font-size:0.68rem;">كل الولايات</div>
                    <div class="small opacity-75" style="font-size:0.58rem;"><?= $totalSchools ?> مدرسة</div>
                </a>
                <?php foreach ($allSudanStates as $stName): ?>
                    <?php $cnt = (int)($stateCounts[$stName] ?? 0); ?>
                    <a href="/mobile/?state=<?= urlencode($stName) ?>" class="text-center text-decoration-none p-2 rounded-3 border flex-shrink-0 <?= $state === $stName ? 'bg-primary text-white border-primary' : 'bg-light text-dark' ?>" style="width:calc((100% - 24px) / 4); min-width:calc((100% - 24px) / 4);">
                        <i class="fas fa-landmark d-block mb-1 <?= $state === $stName ? 'text-white' : 'text-primary' ?>" style="font-size:1.1rem;"></i>
                        <div class="fw-bold text-truncate" style="font-size:0.68rem;"><?= e($stName) ?></div>
                        <div class="small opacity-75" style="font-size:0.58rem;"><?= $cnt > 0 ? $cnt . ' مدرسة' : 'دليل الولاية' ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Schools Section Header -->
        <div class="m-sec-head">
            <span class="m-sec-title">
                <?php if ($state !== ''): ?>
                    <i class="fas fa-map-marker-alt text-danger"></i> مدارس ولاية <?= e($state) ?> <?= $city ? '— ' . e($city) : '' ?>
                <?php elseif ($q !== ''): ?>
                    <i class="fas fa-search text-primary"></i> نتائج البحث عن "<?= e($q) ?>"
                <?php else: ?>
                    <i class="fas fa-school text-primary"></i> المدارس المعتمدة بالدليل
                <?php endif; ?>
            </span>
            <span class="m-sec-badge"><?= count($schools) ?> مدرسة</span>
        </div>

        <!-- Schools Cards Grid -->
        <?php if (empty($schools)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                <p class="fw-bold" style="font-size: 0.85rem;">لا توجد مدارس مطابقة للبحث</p>
                <a href="/mobile/" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold mt-1" style="font-size:0.75rem;">
                    عرض كافة المدارس
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($schools as $sc): ?>
                <?php $scLogo = !empty($sc['logo_path']) ? '/' . ltrim($sc['logo_path'], '/') : '/assets/images/default-school-logo.svg'; ?>
                <div class="m-school-card">
                    <div class="m-card-top">
                        <div class="m-card-avatar">
                            <img src="<?= e($scLogo) ?>" alt="<?= e($sc['name']) ?>">
                        </div>
                        <div class="m-card-info">
                            <div class="m-card-name-row">
                                <div class="m-card-name"><?= e($sc['name']) ?></div>
                                <?php if ($sc['is_schoplus_client']): ?>
                                    <span class="m-card-schoplus-tag">⚡ SchoPlus</span>
                                <?php endif; ?>
                            </div>
                            <div class="m-card-meta">
                                <span><i class="fas fa-map-marker-alt text-muted me-1"></i><?= e($sc['state']) ?> — <?= e($sc['city']) ?></span>
                                <?php if (!empty($sc['established_year'])): ?>
                                    <span class="m-card-est">• تأسست: <?= e($sc['established_year']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="m-card-rating">
                            <i class="fas fa-star text-warning"></i> <?= number_format((float)($sc['rating_avg'] ?? 5.0), 1) ?>
                        </div>
                    </div>

                    <?php if ($sc['is_schoplus_client']): ?>
                        <div class="p-2 my-2 rounded-2 small fw-bold" style="background:#EFF6FF; color:#1E5FA8; border-right:3px solid #1E5FA8; font-size:0.70rem;">
                            <i class="fas fa-bolt text-warning me-1"></i> تستخدم نظام ايسكو بلص • ذكاء صناعي وأتمتة المهام
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sc['description'])): ?>
                        <div class="m-card-desc"><?= e($sc['description']) ?></div>
                    <?php endif; ?>

                    <div class="m-card-actions">
                        <span class="m-card-tag"><?= SudanGeography::schoolTypeLabel($sc['school_type'] ?? 'private') ?></span>

                        <div class="m-card-btns">
                            <!-- Mandatory Details Button Requested by User -->
                            <a href="/mobile/?school_id=<?= (int)$sc['id'] ?><?= $state ? '&state='.urlencode($state) : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>" class="m-act-btn m-act-details" style="background:#0B2A52; color:#fff;">
                                <i class="fas fa-eye"></i> عرض التفاصيل
                            </a>

                            <?php if (!empty($sc['whatsapp']) || !empty($sc['phone'])): ?>
                                <?php $waNum = $sc['whatsapp'] ?: $sc['phone']; ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $waNum) ?>" target="_blank" class="m-act-btn m-act-wa">
                                    <i class="fab fa-whatsapp"></i> واتساب
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($sc['phone'])): ?>
                                <a href="tel:<?= e($sc['phone']) ?>" class="m-act-btn m-act-call">
                                    <i class="fas fa-phone-alt"></i> اتصال
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ── Floating Bottom Navigation: Matching SchoPlus 5 Apps ── -->
    <nav class="m-bottom-nav">
        <a href="/mobile/?tab=home" class="m-nav-link <?= ($tab === 'home' && !$selectedSchool) ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>الرئيسية</span>
        </a>

        <a href="/mobile/?tab=schoplus" class="m-nav-link <?= $tab === 'schoplus' ? 'active' : '' ?>">
            <i class="fas fa-bolt"></i>
            <span>مدارس ذكية</span>
        </a>

        <!-- Prominent Center Tab: Search -->
        <a href="/mobile/?tab=search" class="m-nav-center-btn" title="البحث الذكي">
            <i class="fas fa-search"></i>
        </a>

        <a href="/mobile/?tab=news" class="m-nav-link <?= $tab === 'news' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span>الأخبار</span>
        </a>

        <!-- Button 5: إدارة المدرسة وطلبات الانضمام -->
        <a href="/mobile/?tab=manage" class="m-nav-link <?= in_array($tab, ['manage', 'login', 'register'], true) ? 'active' : '' ?>">
            <i class="fas fa-school"></i>
            <span>إدارة المدرسة</span>
        </a>
    </nav>

    <!-- ── JavaScript: City Cascading & Slider Controls ── -->
    <script>
        const statesData = <?= json_encode($statesWithCities, JSON_UNESCAPED_UNICODE) ?>;
        const stateSelect = document.getElementById('mStateSelect');
        const citySelect = document.getElementById('mCitySelect');

        if (stateSelect && citySelect) {
            stateSelect.addEventListener('change', function() {
                const st = this.value;
                citySelect.innerHTML = '<option value="">جميع المدن / المحليات</option>';
                if (st && statesData[st]) {
                    statesData[st].forEach(function(c) {
                        const opt = document.createElement('option');
                        opt.value = c;
                        opt.textContent = c;
                        citySelect.appendChild(opt);
                    });
                }
            });
        }

        // Horizontal Carousel Scroll Buttons for 4 Cards at once
        function slideStates(direction) {
            const slider = document.getElementById('statesSlider');
            if (!slider) return;
            const scrollDistance = slider.clientWidth * 0.75;
            if (direction === 'next') {
                slider.scrollBy({ left: -scrollDistance, behavior: 'smooth' });
            } else {
                slider.scrollBy({ left: scrollDistance, behavior: 'smooth' });
            }
        }
    </script>

</body>
</html>

