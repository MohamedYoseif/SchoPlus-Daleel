<?php
/**
 * SchoPlus Daleel — Super Admin Master Control Panel
 * لوحة التحكم والإشراف العام على دليل المدارس السودانية
 * Node 6 of SchoPlus Cloud Ecosystem
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';

$pdo = db();

// =========================================================================
// 1. AUTHENTICATION & SESSION MANAGEMENT
// =========================================================================
$masterSecretKey = "schoplus_daleel_deploy_sec_4412f";
$defaultAdminUser = "admin@schoplus.sd";

if (!function_exists('getCountryBadge')) {
    function getCountryBadge(?string $country): string {
        $country = trim((string)$country) ?: 'السودان';
        $flags = [
            'السودان' => '🇸🇩',
            'مصر' => '🇪🇬',
            'المملكة العربية السعودية' => '🇸🇦',
            'السعودية' => '🇸🇦',
            'الإمارات العربية المتحدة' => '🇦🇪',
            'الإمارات' => '🇦🇪',
            'قطر' => '🇶🇦',
            'سلطنة عمان' => '🇴🇲',
            'عمان' => '🇴🇲',
            'الكويت' => '🇰🇼',
            'البحرين' => '🇧🇭',
            'تركيا' => '🇹🇷',
            'أوغندا' => '🇺🇬',
            'تشاد' => '🇹🇩',
            'جنوب السودان' => '🇸🇸',
            'إثيوبيا' => '🇪🇹',
            'ليبيا' => '🇱🇾',
            'المملكة المتحدة' => '🇬🇧',
            'بريطانيا' => '🇬🇧',
            'أمريكا' => '🇺🇸',
        ];
        $flag = $flags[$country] ?? '🌐';
        return "{$flag} {$country}";
    }
}

if (!function_exists('formatWhatsAppUrl')) {
    function formatWhatsAppUrl(?string $phone, string $schoolName, string $schoolSlug = '', string $country = 'السودان'): ?string {
        $raw = trim((string)$phone);
        if ($raw === '') return null;
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (empty($digits)) return null;

        // Normalize Sudanese numbers
        if (str_starts_with($digits, '0') && ($country === 'السودان' || empty($country))) {
            $digits = '249' . substr($digits, 1);
        } elseif ((str_starts_with($digits, '9') || str_starts_with($digits, '1')) && strlen($digits) === 9 && ($country === 'السودان' || empty($country))) {
            $digits = '249' . $digits;
        }

        $schoolUrl = !empty($schoolSlug) ? "https://daleel.schoplus.sd/school/" . urlencode($schoolSlug) : "https://daleel.schoplus.sd";

        $msg = "السلام عليكم ورحمة الله وبركاته،\n"
             . "إدارة مدرسة *{$schoolName}* الموقرة،\n"
             . "تحية طيبة لكم من منصة *دليل المدارس السودانية (SchoPlus Daleel)* 🇸🇩.\n\n"
             . "📌 *الهدف من المنصة والرؤية:*\n"
             . "«دليل المدارس السودانية» هو المنصة الرقمية الموحدة لتوثيق وربط المدارس في كافة الولايات ودول المهجر، بهدف:\n"
             . "• توفير تواجد رقمي رسمي ومجاني لكل مدرسة على الإنترنت.\n"
             . "• تسهيل وصول آلاف أولياء الأمور والطلاب إلى بيانات المدارس، مراحلها، ومواقعها الجغرافية للتواصل المباشر مع الإدارة.\n"
             . "• تعزيز الشفافية والمصداقية ودعم مسيرة التعليم السوداني.\n\n"
             . "🤖 *كيف تمت إضافة مدرستكم؟*\n"
             . "نود إحاطتكم بأنه قد تمت إضافة البيانات الأساسية لمدرستكم مبدئياً وبشكل آلي عبر *محرك استكشاف وبحث ذكي معتمد*، استناداً إلى السجلات التعليمية والإعلانات العامة المتاحة.\n\n"
             . "🔗 *رابط صفحة مدرستكم في الدليل:*\n"
             . "{$schoolUrl}\n\n"
             . "🌟 *المزايا والخيارات المتاحة لكم:*\n"
             . "1️⃣ *توثيق المدرسة واستلام لوحة الإدارة مجاناً*: لإضافة شعار المدرسة، المراحل الدراسية، الرسوم، وصور المباني وأرقام التواصل المباشرة.\n"
             . "2️⃣ *تحديث وتصحيح أي بيانات*: لضمان دقة المعلومات المعروضة لأولياء الأمور.\n"
             . "3️⃣ *طلب الحذف التام*: في حال رغبتم بعدم إدراج مدرستكم في الدليل، يتم الحذف فوراً دون أي شروط.\n\n"
             . "🔒 *اطمئنان وتأكيد أمني (حماية ضد الاحتيال):*\n"
             . "نؤكد لكم أن هذه المبادرة تعليمية رسمية وموثقة، ولن نطلب منكم أي بيانات بنكية أو كلمات سر إطلاقاً.\n"
             . "وللتأكد التام والاطمئنان من مصداقية المنصة والرابط، يمكنكم البحث مباشرة في محرك البحث Google عن عبارة:\n"
             . "« دليل المدارس السودانية »\n"
             . "وستجدون موقعنا الرسمي يتصدر النتائج الأولى:\n"
             . "daleel.schoplus.sd\n\n"
             . "للتوثيق أو التحديث أو طلب الحذف، يمكنكم زيارة رابط صفحتكم والضغط على زر *\"إثبات ملكية المدرسة\"*، أو الرد مباشرة على هذه المحادثة وسيسعد فريقنا بخدمتكم.\n\n"
             . "شاكرين ومقدرين جهودكم ورسالتكم التعليمية النبيلة،\n"
             . "فريق عمل دليل المدارس السودانية — SchoPlus";

        return "https://wa.me/{$digits}?text=" . urlencode($msg);
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['daleel_master_admin']);
    header('Location: admin.php');
    exit;
}

// Handle Login Form Submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $keyOrUser = trim($_POST['identifier'] ?? '');
    $pwd = $_POST['password'] ?? '';

    // 1. Direct Secret Master Key
    if ($keyOrUser === $masterSecretKey || $pwd === $masterSecretKey) {
        $_SESSION['daleel_master_admin'] = true;
        header('Location: admin.php');
        exit;
    }

    // 2. Custom Stored Admin Password or Default
    $storedPwdHash = getDaleelSetting($pdo, 'admin_password_hash');
    $validUser = ($keyOrUser === $defaultAdminUser || $keyOrUser === 'admin');

    if ($validUser) {
        if ($storedPwdHash && password_verify($pwd, $storedPwdHash)) {
            $_SESSION['daleel_master_admin'] = true;
            header('Location: admin.php');
            exit;
        } elseif (!$storedPwdHash && ($pwd === 'schoplus@admin2026' || $pwd === 'admin123')) {
            $_SESSION['daleel_master_admin'] = true;
            header('Location: admin.php');
            exit;
        }
    }

    $loginError = 'بيانات الدخول غير صحيحة. يرجى التحقق من اسم المستخدم أو الرمز السري الرئيسي.';
}

// Check if Logged in as Master Admin
$isMasterAdmin = !empty($_SESSION['daleel_master_admin']);

// If not logged in, render Secure Login Screen
if (!$isMasterAdmin): ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم العليا — دليل المدارس السودانية | SchoPlus</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #F0F4F8;
            color: #0D1B2E;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-login-card {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #DDE4ED;
            box-shadow: 0 16px 40px rgba(11, 42, 82, 0.08);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .admin-login-head {
            background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
            color: #FFFFFF;
            padding: 28px 24px 22px;
            text-align: center;
            border-bottom: 3px solid #F5A020;
        }
        .btn-brand {
            background: #F5A020;
            border: none;
            color: #FFFFFF;
            font-weight: 700;
            border-radius: 8px;
            padding: 10px 18px;
            transition: all 0.2s;
        }
        .btn-brand:hover {
            background: #E08E15;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(245, 160, 32, 0.35);
        }
    </style>
</head>
<body>
    <div class="admin-login-card mx-3">
        <div class="admin-login-head">
            <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(245, 160, 32, 0.2); color: #F5A020; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px; border: 1px solid #F5A020;">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h4 class="fw-bold text-white mb-1" style="font-size: 1.15rem;">لوحة التحكم والإشراف العام</h4>
            <div style="font-size: 0.76rem; color: #CBD5E1;">منظومة دليل المدارس السودانية — SchoPlus</div>
        </div>
        <div class="p-4">
            <?php if ($loginError): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3" style="font-size: 0.76rem;">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?= e($loginError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php">
                <input type="hidden" name="admin_login" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark" style="font-size: 0.76rem;">اسم المستخدم أو المفتاح السري</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0" style="border-color: #DDE4ED;"><i class="fas fa-key"></i></span>
                        <input type="text" name="identifier" class="form-control border-start-0" required placeholder="admin@schoplus.sd أو المفتاح السري" style="border-color: #DDE4ED; font-size: 0.8rem;">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-dark" style="font-size: 0.76rem;">كلمة المرور</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0" style="border-color: #DDE4ED;"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control border-start-0" required placeholder="••••••••" style="border-color: #DDE4ED; font-size: 0.8rem;">
                    </div>
                    <div class="form-text small mt-1" style="font-size: 0.68rem; color: #64748B;">
                        يمكنك الدخول المباشر باستخدام المفتاح السري المعتمد للنود في حقل كلمة المرور.
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100 py-2 fs-6">
                    <i class="fas fa-sign-in-alt me-1"></i> دخول لوحة التحكم
                </button>

                <div class="text-center mt-3 pt-3 border-top">
                    <a href="/" class="text-decoration-none small" style="color: #1E5FA8; font-size: 0.74rem;">
                        <i class="fas fa-arrow-right me-1"></i> العودة إلى الدليل العام
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// =========================================================================
// 2. MASTER ADMIN AJAX ACTIONS HANDLER
// =========================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['ajax_action'];

    try {
        if ($action === 'toggle_verified') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $pdo->prepare("UPDATE daleel_schools SET is_verified = ? WHERE id = ?")->execute([$val, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'is_verified' => $val]);
            exit;
        }

        if ($action === 'toggle_schoplus') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $pdo->prepare("UPDATE daleel_schools SET is_schoplus_client = ? WHERE id = ?")->execute([$val, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'is_schoplus_client' => $val]);
            exit;
        }

        if ($action === 'toggle_historic') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $badge = $val ? 'صرح وطني عريق' : null;
            $pdo->prepare("UPDATE daleel_schools SET is_historic = ?, historic_badge_reason = ? WHERE id = ?")->execute([$val, $badge, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'is_historic' => $val]);
            exit;
        }

        if ($action === 'update_rank') {
            $id = (int)($_POST['id'] ?? 0);
            $dirRank = (int)($_POST['directory_rank'] ?? 0);
            $stRank = (int)($_POST['state_rank'] ?? 0);
            $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?")->execute([$dirRank, $stRank, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'directory_rank' => $dirRank, 'state_rank' => $stRank]);
            exit;
        }

        if ($action === 'update_phone') {
            $id = (int)($_POST['id'] ?? 0);
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $pdo->prepare("UPDATE daleel_schools SET phone = ?, whatsapp = ? WHERE id = ?")->execute([$phone ?: null, $whatsapp ?: null, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'phone' => $phone, 'whatsapp' => $whatsapp, 'message' => 'تم تحديث رقم الهاتف والواتساب بنجاح.']);
            exit;
        }

        if ($action === 'delete_school') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->prepare("DELETE FROM daleel_school_reviews WHERE school_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM daleel_school_news WHERE school_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM daleel_school_inquiries WHERE school_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM daleel_school_claims WHERE school_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM daleel_schools WHERE id = ?")->execute([$id]);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'تم حذف المدرسة بنجاح.']);
            exit;
        }

        if ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);
            $newPwd = trim($_POST['new_password'] ?? '');
            if (empty($newPwd)) $newPwd = bin2hex(random_bytes(4));
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE daleel_schools SET password = ? WHERE id = ?")->execute([$hash, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'new_password' => $newPwd, 'message' => 'تم تعيين كلمة المرور بنجاح.']);
            exit;
        }

        if ($action === 'approve_claim') {
            $claimId = (int)($_POST['claim_id'] ?? 0);
            $claim = $pdo->query("SELECT * FROM daleel_school_claims WHERE id = {$claimId}")->fetch(PDO::FETCH_ASSOC);
            if ($claim) {
                $schoolId = (int)$claim['school_id'];
                $pdo->prepare("UPDATE daleel_schools SET 
                    is_verified = 1,
                    owner_name = ?,
                    owner_title = ?,
                    owner_phone = ?,
                    owner_email = ?
                    WHERE id = ?")
                    ->execute([$claim['claimant_name'], $claim['claimant_title'], $claim['claimant_phone'], $claim['claimant_email'], $schoolId]);

                $pdo->prepare("UPDATE daleel_school_claims SET status = 'approved', reviewed_by = 'Super Admin', reviewed_at = NOW() WHERE id = ?")->execute([$claimId]);
                echo json_encode(['success' => true, 'claim_id' => $claimId, 'message' => 'تم اعتماد التوثيق ونقل ملكية المدرسة بنجاح!']);
                exit;
            }
        }

        if ($action === 'reject_claim') {
            $claimId = (int)($_POST['claim_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'المستندات غير كافية');
            $pdo->prepare("UPDATE daleel_school_claims SET status = 'rejected', rejection_reason = ?, reviewed_by = 'Super Admin', reviewed_at = NOW() WHERE id = ?")->execute([$reason, $claimId]);
            echo json_encode(['success' => true, 'claim_id' => $claimId, 'message' => 'تم رفض الطلب.']);
            exit;
        }

        if ($action === 'save_ad_settings') {
            $enabled = (int)($_POST['ad_enabled'] ?? 1);
            $title = trim($_POST['ad_title'] ?? '');
            $desc = trim($_POST['ad_desc'] ?? '');
            $btnText = trim($_POST['ad_btn_text'] ?? '');
            $waPhone = trim($_POST['ad_wa_phone'] ?? '');

            $adConfig = [
                'enabled' => $enabled,
                'title' => $title,
                'desc' => $desc,
                'btn_text' => $btnText,
                'wa_phone' => $waPhone
            ];

            setDaleelSetting($pdo, 'sidebar_ad_config', $adConfig);
            echo json_encode(['success' => true, 'message' => 'تم حفظ إعدادات المساحة الإعلانية بنجاح.']);
            exit;
        }

        if ($action === 'recalculate_ranks') {
            $historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
            $orderClause = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, id ASC";

            $approvedRows = $pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
            $dirSeq = 0;
            $stateSeqs = [];
            $updRanksStmt = $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?");
            foreach ($approvedRows as $aRow) {
                $dirSeq++;
                $cntryKey = trim((string)($aRow['country'] ?? '')) ?: 'السودان';
                $stKey = trim((string)($aRow['state'] ?? ''));
                $groupKey = $cntryKey . ':::' . $stKey;
                $stateSeqs[$groupKey] = ($stateSeqs[$groupKey] ?? 0) + 1;
                $stSeq = $stateSeqs[$groupKey];
                $updRanksStmt->execute([$dirSeq, $stSeq, (int)$aRow['id']]);
            }
            echo json_encode(['success' => true, 'count' => count($approvedRows), 'message' => "تمت إعادة حساب الترتيب التسلسلي الفعلي بنجاح لـ {$dirSeq} مدرسة في الدليل (الصروح العريقة أولاً ثم بالتتابع)."]);
            exit;
        }

        if ($action === 'export_seed') {
            require_once __DIR__ . '/export_master_seed.php';
            echo json_encode(['success' => true, 'message' => 'تم تصدير قاعدة البيانات المركزية إلى seed_schools_data.php بنجاح.']);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'إجراء غير معروف']);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 3. DATA AGGREGATION & STATISTICS
// =========================================================================
$totalCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$verifiedCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND is_verified = 1")->fetchColumn();
$unverifiedCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND is_verified = 0")->fetchColumn();
$smartCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$historicCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND is_historic = 1")->fetchColumn();
$claimsCount = (int)$pdo->query("SELECT count(*) FROM daleel_school_claims WHERE status = 'pending'")->fetchColumn();
$reviewsCount = (int)$pdo->query("SELECT count(*) FROM daleel_school_reviews")->fetchColumn();
$inquiriesCount = (int)$pdo->query("SELECT count(*) FROM daleel_school_inquiries")->fetchColumn();

// Filters for Schools Table
$filterCountry = trim($_GET['country'] ?? '');
$filterState = trim($_GET['state'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');
$filterSort = trim($_GET['sort'] ?? 'catalog_rank');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = ["status = 'approved'"];
$whereParams = [];

if ($filterCountry !== '') {
    $whereClauses[] = "country = ?";
    $whereParams[] = $filterCountry;
}
if ($filterState !== '') {
    $whereClauses[] = "state = ?";
    $whereParams[] = $filterState;
}
if ($filterStatus === 'verified') {
    $whereClauses[] = "is_verified = 1";
} elseif ($filterStatus === 'unverified') {
    $whereClauses[] = "is_verified = 0";
} elseif ($filterStatus === 'smart') {
    $whereClauses[] = "is_schoplus_client = 1";
} elseif ($filterStatus === 'historic') {
    $whereClauses[] = "is_historic = 1";
}
if ($filterSearch !== '') {
    list($srchSql, $srchParams) = buildUniversalSchoolSearchClause($filterSearch);
    if (!empty($srchSql)) {
        $whereClauses[] = $srchSql;
        $whereParams = array_merge($whereParams, $srchParams);
    }
}

$whereSql = implode(' AND ', $whereClauses);

// Count filtered
$cntStmt = $pdo->prepare("SELECT count(*) FROM daleel_schools WHERE {$whereSql}");
$cntStmt->execute($whereParams);
$totalFiltered = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($totalFiltered / $perPage));

// Dynamic Sorting Order matching Public Catalog
$historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
$catalogOrderSql = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, id ASC";

if ($filterSort === 'id_asc') {
    $orderSql = "id ASC";
} elseif ($filterSort === 'id_desc') {
    $orderSql = "id DESC";
} elseif ($filterSort === 'name') {
    $orderSql = "name ASC";
} elseif ($filterSort === 'views') {
    $orderSql = "views_count DESC, id ASC";
} elseif ($filterSort === 'schoplus') {
    $orderSql = "is_schoplus_client DESC, is_verified DESC, id ASC";
} else {
    // Default: Exact actual directory rank order
    $orderSql = $catalogOrderSql;
}

// Fetch filtered rows with country, phone, whatsapp, established_year
$querySql = "SELECT id, name, name_en, slug, country, state, city, district, address, phone, whatsapp, stages, is_verified, is_schoplus_client, is_historic, directory_rank, state_rank, established_year, created_at 
             FROM daleel_schools 
             WHERE {$whereSql} 
             ORDER BY {$orderSql} 
             LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($querySql);
$stmt->execute($whereParams);
$schoolsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct countries list for filter
$countriesList = $pdo->query("SELECT DISTINCT country FROM daleel_schools WHERE status = 'approved' AND country IS NOT NULL AND country != '' ORDER BY (country = 'السودان') DESC, country ASC")->fetchAll(PDO::FETCH_COLUMN);

// Claims List
$pendingClaims = $pdo->query("SELECT c.*, s.name as school_name, s.slug as school_slug, s.state, s.city 
                              FROM daleel_school_claims c 
                              LEFT JOIN daleel_schools s ON c.school_id = s.id 
                              ORDER BY c.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Ad Settings
$rawAdConfig = getDaleelSetting($pdo, 'sidebar_ad_config');
$adConfig = is_string($rawAdConfig) ? json_decode($rawAdConfig, true) : ($rawAdConfig ?: []);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم العليا للدليل — SchoPlus Master Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1E5FA8;
            --orange: #F5A020;
            --navy: #0B2A52;
            --bg-slate: #F0F4F8;
            --card-border: #DDE4ED;
            --text-heading: #0D1B2E;
            --text-body: #3D5068;
            --text-muted: #7A93AD;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-slate);
            color: var(--text-body);
            font-size: 0.8rem;
            margin: 0;
            padding: 0;
        }

        /* Top Bar */
        .admin-topbar {
            background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
            border-bottom: 2px solid var(--orange);
            color: #FFFFFF;
            padding: 12px 24px;
        }
        .admin-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #FFFFFF;
        }
        .admin-brand:hover { color: #FFFFFF; }
        .admin-brand-icon {
            width: 38px;
            height: 38px;
            background: rgba(245, 160, 32, 0.2);
            border: 1px solid var(--orange);
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--orange);
            font-size: 1.15rem;
        }

        /* Metrics */
        .metric-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(11, 42, 82, 0.04);
            position: relative;
            overflow: hidden;
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            right: 0;
            width: 4px;
        }
        .m-blue::before { background-color: var(--primary); }
        .m-orange::before { background-color: var(--orange); }
        .m-green::before { background-color: #10B981; }
        .m-purple::before { background-color: #8B5CF6; }

        .metric-val {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.2;
        }
        .metric-lbl {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* Navigation Tabs (Style F v2 Mandatory Spec) */
        .sp-main-tabs-wrap {
            overflow-x: auto;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .sp-main-tabs-wrap::-webkit-scrollbar { display: none; }
        .sp-main-tabs {
            display: inline-flex;
            gap: 8px;
            background: #E2E8F0;
            padding: 5px;
            border-radius: 12px;
        }
        .sp-main-tab {
            border: none;
            background: transparent;
            padding: 8px 18px;
            font-size: 0.74rem;
            font-weight: 700;
            color: #475569;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .sp-main-tab:hover {
            color: var(--navy);
        }
        .sp-main-tab.active {
            background: #FFFFFF;
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 42, 82, 0.08);
        }
        .sp-main-tab.active i {
            color: var(--orange);
        }
        .tab-badge {
            background: #F1F5F9;
            color: var(--text-heading);
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
        }
        .sp-main-tab.active .tab-badge {
            background: #EFF6FF;
            color: var(--primary);
        }

        /* Controls & Toolbar */
        .admin-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(11, 42, 82, 0.04);
            margin-bottom: 24px;
        }
        .admin-card-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .admin-card-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--navy);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Interactive Toggles & Loading States */
        .sp-toggle-pill {
            cursor: pointer;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            user-select: none;
            position: relative;
        }
        .sp-toggle-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .sp-toggle-pill.is-loading {
            opacity: 0.7;
            pointer-events: none;
            cursor: wait !important;
            background: #E2E8F0 !important;
            color: #475569 !important;
            border-color: #CBD5E1 !important;
        }
        .sp-toggle-pill.is-success-pulse {
            animation: spPillPulse 1.1s ease-out;
        }
        @keyframes spPillPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { transform: scale(1.08); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .toggle-on-green {
            background: #ECFDF5;
            color: #059669;
            border-color: #A7F3D0;
        }
        .toggle-off-gray {
            background: #F1F5F9;
            color: #64748B;
            border-color: #CBD5E1;
        }
        .toggle-on-blue {
            background: #EFF6FF;
            color: #1E5FA8;
            border-color: #BFDBFE;
        }
        .toggle-on-orange {
            background: #FFFBEB;
            color: #D97706;
            border-color: #FDE68A;
        }

        /* Toast Notifications */
        .sp-admin-toast-container {
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .sp-admin-toast {
            background: #0B2A52;
            color: #FFFFFF;
            padding: 10px 18px;
            border-radius: 10px;
            border-right: 4px solid #F5A020;
            box-shadow: 0 10px 30px rgba(11, 42, 82, 0.28);
            font-size: 0.76rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            pointer-events: auto;
            animation: toastSlideIn 0.3s ease-out;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .sp-admin-toast.toast-success { border-right-color: #10B981; }
        .sp-admin-toast.toast-danger { border-right-color: #EF4444; }
        .sp-admin-toast.toast-warning { border-right-color: #F5A020; }
        @keyframes toastSlideIn {
            from { transform: translateX(-40px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Action Buttons */
        .act-btn {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--card-border);
            background: #FFFFFF;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            transition: all 0.15s;
            text-decoration: none;
        }
        .act-btn:hover {
            background: #F8FAFC;
            color: var(--primary);
            border-color: var(--primary);
        }
        .act-btn.danger:hover {
            background: #FEF2F2;
            color: #DC2626;
            border-color: #DC2626;
        }

        /* Tables */
        .admin-table {
            width: 100%;
            margin-bottom: 0;
            font-size: 0.74rem;
        }
        .admin-table thead th {
            background: linear-gradient(135deg, #0B2A52, #163870);
            color: rgba(255,255,255,0.85);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 10px 12px;
            border: none;
        }
        .admin-table tbody td {
            padding: 10px 12px;
            vertical-align: middle;
            border-color: #F1F5F9;
        }
        .admin-table tbody tr:hover {
            background: #F8FAFC;
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <header class="admin-topbar">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="admin.php" class="admin-brand">
                    <div class="admin-brand-icon"><i class="fas fa-crown"></i></div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.96rem; line-height: 1.2;">لوحة الإدارة العليا للدليل</div>
                        <div style="font-size: 0.65rem; color: #94A3B8;">SchoPlus Daleel Master Control Panel</div>
                    </div>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="/" target="_blank" class="btn btn-sm btn-outline-light" style="font-size: 0.72rem; border-radius: 8px;">
                    <i class="fas fa-external-link-alt me-1"></i> عرض الدليل الحي
                </a>
                <a href="admin.php?action=logout" class="btn btn-sm btn-danger" style="font-size: 0.72rem; border-radius: 8px;">
                    <i class="fas fa-sign-out-alt me-1"></i> خروج
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="container-fluid py-4 px-lg-5">

        <!-- METRICS ROW -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-blue">
                    <div class="metric-lbl">إجمالي المدارس</div>
                    <div class="metric-val"><?= number_format($totalCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">مؤسسة تعليمية</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-green">
                    <div class="metric-lbl">مدارس موثقة</div>
                    <div class="metric-val text-success"><?= number_format($verifiedCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">معتمدة من الإدارات</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-orange">
                    <div class="metric-lbl">مدارس SchoPlus الذكية</div>
                    <div class="metric-val" style="color: var(--orange);"><?= number_format($smartCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">تعمل بالنظام السحابي</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-purple">
                    <div class="metric-lbl">الصروح العريقة</div>
                    <div class="metric-val" style="color: #7C3AED;"><?= number_format($historicCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">إرث وطني وتاريخي</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-orange">
                    <div class="metric-lbl">طلبات التوثيق المعلقة</div>
                    <div class="metric-val text-danger"><?= number_format($claimsCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">بانتظار المراجعة</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="metric-card m-blue">
                    <div class="metric-lbl">التقييمات والاستفسارات</div>
                    <div class="metric-val"><?= number_format($reviewsCount + $inquiriesCount) ?></div>
                    <div class="small text-muted" style="font-size: 0.62rem;">تفاعل أولياء الأمور</div>
                </div>
            </div>
        </div>

        <!-- MAIN TABS (Style F v2 Spec) -->
        <?php $activeTab = $_GET['tab'] ?? 'schools'; ?>
        <div class="sp-main-tabs-wrap">
            <div class="sp-main-tabs">
                <a href="admin.php?tab=schools" class="sp-main-tab <?= $activeTab === 'schools' ? 'active' : '' ?>">
                    <i class="fas fa-school"></i> إدارة المدارس
                    <span class="tab-badge"><?= number_format($totalCount) ?></span>
                </a>
                <a href="admin.php?tab=claims" class="sp-main-tab <?= $activeTab === 'claims' ? 'active' : '' ?>">
                    <i class="fas fa-file-signature"></i> طلبات التوثيق والملكية
                    <span class="tab-badge" style="color: <?= $claimsCount > 0 ? '#DC2626' : 'inherit' ?>;"><?= $claimsCount ?></span>
                </a>
                <a href="admin.php?tab=researcher" class="sp-main-tab <?= $activeTab === 'researcher' ? 'active' : '' ?>">
                    <i class="fas fa-robot"></i> الباحث الآلي المدمج
                    <span class="tab-badge">استكشاف ذكي</span>
                </a>
                <a href="admin.php?tab=ads" class="sp-main-tab <?= $activeTab === 'ads' ? 'active' : '' ?>">
                    <i class="fas fa-ad"></i> المساحة الإعلانية
                    <span class="tab-badge"><?= !empty($adConfig['enabled']) ? 'نشطة' : 'متوقفة' ?></span>
                </a>
                <a href="admin.php?tab=maintenance" class="sp-main-tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
                    <i class="fas fa-tools"></i> أدوات النظام والصيانة
                    <span class="tab-badge">v3.7.90</span>
                </a>
            </div>
        </div>

        <!-- TAB CONTENT: 1. SCHOOLS MANAGEMENT -->
        <?php if ($activeTab === 'schools'): ?>
            <div class="admin-card">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-list-check text-primary"></i> دليل وإدارة كافة المدارس
                        <span class="badge bg-light text-dark border ms-2" style="font-size: 0.68rem;"><?= number_format($totalFiltered) ?> مطابقة</span>
                    </h5>
                    
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-primary py-1 px-3" style="font-size: 0.72rem; border-radius: 8px;" onclick="recalculateRanks()">
                            <i class="fas fa-sync-alt me-1"></i> إعادة ترقيم التسلسل
                        </button>
                    </div>
                </div>

                <!-- Filters Toolbar -->
                <div class="p-3 bg-light border-bottom">
                    <form method="GET" action="admin.php" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="schools">
                        
                        <div class="col-lg-3 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED;"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم، الحي، الهاتف، المدينة..." value="<?= e($filterSearch) ?>" style="border-color: #DDE4ED; font-size: 0.75rem;">
                            </div>
                        </div>

                        <!-- COUNTRY FILTER -->
                        <div class="col-lg-2 col-sm-6 col-12">
                            <select name="country" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem;" onchange="this.form.submit()">
                                <option value="">كافة الدول (المهجر والسودان)</option>
                                <?php foreach ($countriesList as $cntry): ?>
                                    <option value="<?= e($cntry) ?>" <?= $filterCountry === $cntry ? 'selected' : '' ?>><?= getCountryBadge($cntry) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- STATE FILTER -->
                        <div class="col-lg-2 col-sm-6 col-12">
                            <select name="state" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem;" onchange="this.form.submit()">
                                <option value="">كافة الولايات والمناطق</option>
                                <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $filterState === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- STATUS FILTER -->
                        <div class="col-lg-2 col-sm-6 col-12">
                            <select name="status" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem;" onchange="this.form.submit()">
                                <option value="">كافة الحالات</option>
                                <option value="verified" <?= $filterStatus === 'verified' ? 'selected' : '' ?>>موثقة فقط (Verified)</option>
                                <option value="unverified" <?= $filterStatus === 'unverified' ? 'selected' : '' ?>>عامة غير موثقة (Unverified)</option>
                                <option value="smart" <?= $filterStatus === 'smart' ? 'selected' : '' ?>>مدارس SchoPlus الذكية</option>
                                <option value="historic" <?= $filterStatus === 'historic' ? 'selected' : '' ?>>صروح وطنية عريقة</option>
                            </select>
                        </div>

                        <!-- SORT FILTER -->
                        <div class="col-lg-2 col-sm-6 col-12">
                            <select name="sort" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem;" onchange="this.form.submit()">
                                <option value="catalog_rank" <?= $filterSort === 'catalog_rank' ? 'selected' : '' ?>>الترتيب الفعلي بالدليل (افتراضي)</option>
                                <option value="id_asc" <?= $filterSort === 'id_asc' ? 'selected' : '' ?>>المعرف #ID تصاعدي</option>
                                <option value="id_desc" <?= $filterSort === 'id_desc' ? 'selected' : '' ?>>المعرف #ID تنازلي</option>
                                <option value="name" <?= $filterSort === 'name' ? 'selected' : '' ?>>أبجدياً باسم المدرسة</option>
                                <option value="views" <?= $filterSort === 'views' ? 'selected' : '' ?>>الأكثر مشاهدة وزيارة</option>
                                <option value="schoplus" <?= $filterSort === 'schoplus' ? 'selected' : '' ?>>عملاء SchoPlus أولاً</option>
                            </select>
                        </div>

                        <div class="col-lg-1 col-12 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100 py-1" style="font-size: 0.74rem;" title="تطبيق الفلترة">
                                <i class="fas fa-filter"></i>
                            </button>
                            <?php if ($filterSearch || $filterCountry || $filterState || $filterStatus || ($filterSort && $filterSort !== 'catalog_rank')): ?>
                                <a href="admin.php?tab=schools" class="btn btn-sm btn-light border py-1" title="مسح الفلاتر">
                                    <i class="fas fa-undo"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Schools Table -->
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#ID</th>
                                <th>اسم المدرسة والمراحل</th>
                                <th>الدولة</th>
                                <th>الموقع (الولاية / المدينة / الحي)</th>
                                <th style="min-width: 145px;">الهاتف والواتساب</th>
                                <th class="text-center">التوثيق</th>
                                <th class="text-center">SchoPlus</th>
                                <th class="text-center">صرح عريق</th>
                                <th class="text-center" style="width: 145px;" title="الترتيب الفعلي التلقائي في الدليل مع حقول لتخصيص الترتيب العام والولائي">الترتيب الفعلي (عام / ولائي)</th>
                                <th class="text-center pe-3" style="width: 100px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schoolsList)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">لم يتم العثور على أي مدارس مطابقة لشروط البحث.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schoolsList as $sch): ?>
                                    <?php 
                                    $actualDirRank = getSchoolDirectoryRank($pdo, (int)$sch['id'], !empty($sch['directory_rank']) ? (int)$sch['directory_rank'] : null);
                                    $phoneVal = trim((string)($sch['whatsapp'] ?: $sch['phone']));
                                    $waUrl = formatWhatsAppUrl($phoneVal, $sch['name'], $sch['slug'] ?? '', $sch['country'] ?? 'السودان');

                                    // Line type detection: Mobile vs Landline
                                    $cleanDigits = preg_replace('/[^0-9]/', '', $phoneVal);
                                    $isMobile = false;
                                    if (str_starts_with($cleanDigits, '249')) {
                                        $local = substr($cleanDigits, 3);
                                        $isMobile = (str_starts_with($local, '9') || str_starts_with($local, '1')) && strlen($local) === 9;
                                    } elseif (str_starts_with($cleanDigits, '0')) {
                                        $isMobile = (str_starts_with($cleanDigits, '09') || str_starts_with($cleanDigits, '01')) && strlen($cleanDigits) === 10;
                                    } elseif (str_starts_with($cleanDigits, '9665') || str_starts_with($cleanDigits, '201') || str_starts_with($cleanDigits, '9715') || str_starts_with($cleanDigits, '974')) {
                                        $isMobile = true;
                                    }
                                    ?>
                                    <tr id="row-school-<?= $sch['id'] ?>">
                                        <td class="fw-bold text-muted ps-3">#<?= $sch['id'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <a href="/school/<?= urlencode($sch['slug']) ?>" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary" style="font-size: 0.78rem;">
                                                        <?= e($sch['name']) ?>
                                                    </a>
                                                    <?php if (!empty($sch['name_en'])): ?>
                                                        <div class="text-muted" style="font-size: 0.65rem;"><?= e($sch['name_en']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sch['established_year']) && (int)$sch['established_year'] <= 1950): ?>
                                                        <span class="badge bg-info-subtle text-info border border-info mt-1" style="font-size: 0.6rem;">
                                                            <i class="fas fa-landmark me-1"></i>تأسست <?= e($sch['established_year']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- COUNTRY -->
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem;">
                                                <?= getCountryBadge($sch['country'] ?? 'السودان') ?>
                                            </span>
                                        </td>
                                        <!-- LOCATION -->
                                        <td>
                                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                                <span class="badge bg-light text-dark border" style="font-size: 0.65rem;"><?= e($sch['state']) ?></span>
                                                <span class="text-muted small" style="font-size: 0.68rem;"><?= e($sch['city']) ?></span>
                                                <?php if (!empty($sch['district'])): ?>
                                                    <span class="text-muted small" style="font-size: 0.68rem;">— حي <?= e($sch['district']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- PHONE & WHATSAPP -->
                                        <td>
                                            <?php if ($phoneVal !== ''): ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span class="fw-bold text-dark dir-ltr" style="font-size: 0.72rem; font-family: monospace;">
                                                            <?= e($phoneVal) ?>
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-primary" onclick="editSchoolPhone(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>', '<?= e(addslashes($sch['phone'] ?? '')) ?>', '<?= e(addslashes($sch['whatsapp'] ?? '')) ?>')" title="تعديل الهاتف أو الواتساب">
                                                            <i class="fas fa-pencil-alt" style="font-size: 0.62rem;"></i>
                                                        </button>
                                                    </div>
                                                    <div>
                                                        <?php if ($isMobile): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1" style="font-size: 0.58rem;">
                                                                <i class="fab fa-whatsapp me-1"></i>موبايل مهيأ لواتساب
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-secondary border py-0 px-1" style="font-size: 0.58rem;">
                                                                <i class="fas fa-phone-office me-1"></i>هاتف أرضي/ثابت
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 mt-1">
                                                        <?php if ($waUrl): ?>
                                                            <a href="<?= e($waUrl) ?>" target="_blank" class="btn btn-sm text-white d-inline-flex align-items-center gap-1 py-0 px-2" style="font-size: 0.68rem; height: 23px; border-radius: 6px; background-color: #25D366; border-color: #25D366;" title="مراسلة واتساب بالرسالة الترحيبية والتعريفية">
                                                                <i class="fab fa-whatsapp"></i> <span>واتساب</span>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="tel:<?= e($phoneVal) ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center p-0" style="width: 23px; height: 23px; border-radius: 6px;" title="اتصال هاتفي">
                                                            <i class="fas fa-phone-alt" style="font-size: 0.65rem;"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">
                                                        <i class="fas fa-phone-slash me-1"></i>غير متوفر
                                                    </span>
                                                    <button type="button" class="btn btn-sm btn-link p-0 text-primary" onclick="editSchoolPhone(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>', '', '')" title="إضافة رقم هاتف للمدرسة">
                                                        <i class="fas fa-plus-circle" style="font-size: 0.72rem;"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- VERIFIED TOGGLE -->
                                        <td class="text-center">
                                            <span class="sp-toggle-pill <?= $sch['is_verified'] ? 'toggle-on-green' : 'toggle-off-gray' ?>" 
                                                  onclick="toggleSchoolMeta(<?= $sch['id'] ?>, 'toggle_verified', <?= $sch['is_verified'] ? 0 : 1 ?>, this)"
                                                  title="انقر لتغيير حالة التوثيق">
                                                <i class="fas <?= $sch['is_verified'] ? 'fa-check-circle' : 'fa-circle-xmark' ?>"></i>
                                                <span><?= $sch['is_verified'] ? 'موثقة' : 'عامة' ?></span>
                                            </span>
                                        </td>

                                        <!-- SCHOPLUS SMART BADGE TOGGLE -->
                                        <td class="text-center">
                                            <span class="sp-toggle-pill <?= $sch['is_schoplus_client'] ? 'toggle-on-orange' : 'toggle-off-gray' ?>" 
                                                  onclick="toggleSchoolMeta(<?= $sch['id'] ?>, 'toggle_schoplus', <?= $sch['is_schoplus_client'] ? 0 : 1 ?>, this)"
                                                  title="انقر لتعيين كعميل SchoPlus ذكي">
                                                <i class="fas fa-award"></i>
                                                <span><?= $sch['is_schoplus_client'] ? 'ذكي' : 'عادي' ?></span>
                                            </span>
                                        </td>

                                        <!-- HISTORIC BADGE TOGGLE -->
                                        <td class="text-center">
                                            <span class="sp-toggle-pill <?= $sch['is_historic'] ? 'toggle-on-blue' : 'toggle-off-gray' ?>" 
                                                  onclick="toggleSchoolMeta(<?= $sch['id'] ?>, 'toggle_historic', <?= $sch['is_historic'] ? 0 : 1 ?>, this)"
                                                  title="انقر لتعيين كصرح وطني عريق">
                                                <i class="fas fa-landmark"></i>
                                                <span><?= $sch['is_historic'] ? 'عريق' : 'حديث' ?></span>
                                            </span>
                                        </td>

                                        <!-- ACTUAL CATALOG RANK & MANUAL OVERRIDE (EXPLICIT LABELS) -->
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center justify-content-center">
                                                <span class="badge mb-1" style="background: linear-gradient(135deg, #0B2A52, #1E5FA8); color: #FFF; font-size: 0.72rem; padding: 4px 8px;" title="الترتيب الفعلي التلقائي في الدليل">
                                                    #<?= $actualDirRank ?> في الدليل
                                                </span>
                                                <div class="d-flex flex-column gap-1 p-1 bg-light rounded border" style="font-size: 0.65rem; min-width: 125px;">
                                                    <div class="d-flex align-items-center justify-content-between gap-1">
                                                        <span class="text-muted fw-bold" style="font-size: 0.62rem;" title="الترتيب العام المخصص للمدرسة في الدليل">العام:</span>
                                                        <input type="number" class="form-control form-control-sm text-center px-1 py-0 bg-white" style="width: 48px; font-size: 0.68rem; height: 21px;" 
                                                               id="dir-rank-<?= $sch['id'] ?>" value="<?= (int)($sch['directory_rank'] ?: $actualDirRank) ?>" title="أولوية الترتيب العام المخصص">
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between gap-1">
                                                        <span class="text-muted fw-bold" style="font-size: 0.62rem;" title="ترتيب المدرسة داخل ولايتها">الولائي:</span>
                                                        <input type="number" class="form-control form-control-sm text-center px-1 py-0 bg-white" style="width: 48px; font-size: 0.68rem; height: 21px;" 
                                                               id="state-rank-<?= $sch['id'] ?>" value="<?= (int)($sch['state_rank'] ?: 1) ?>" title="أولوية الترتيب داخل الولاية">
                                                        <button type="button" class="btn btn-sm btn-primary p-0 btn-save-rank-<?= $sch['id'] ?>" style="width: 22px; height: 21px; border-radius: 4px;" onclick="saveRanks(<?= $sch['id'] ?>)" title="حفظ الترتيب المخصص">
                                                            <i class="fas fa-save" style="font-size: 0.6rem;"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- ACTIONS -->
                                        <td class="text-center pe-3">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <a href="/school/<?= urlencode($sch['slug']) ?>" target="_blank" class="act-btn" title="معاينة في الدليل">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <button type="button" class="act-btn" onclick="resetPasswordModal(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>')" title="إعادة تعيين كلمة مرور المسؤول">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button type="button" class="act-btn danger" onclick="deleteSchool(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>')" title="حذف المدرسة">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="small text-muted" style="font-size: 0.7rem;">
                            عرض الصفحة <strong><?= $page ?></strong> من أصل <strong><?= $totalPages ?></strong> (إجمالي <?= number_format($totalFiltered) ?> مدرسة)
                        </div>
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="admin.php?tab=schools&page=<?= $page - 1 ?>&search=<?= urlencode($filterSearch) ?>&country=<?= urlencode($filterCountry) ?>&state=<?= urlencode($filterState) ?>&status=<?= urlencode($filterStatus) ?>&sort=<?= urlencode($filterSort) ?>">السابقة</a></li>
                                <?php endif; ?>
                                
                                <li class="page-item active"><span class="page-link" style="background: var(--navy); border-color: var(--navy);"><?= $page ?></span></li>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item"><a class="page-link" href="admin.php?tab=schools&page=<?= $page + 1 ?>&search=<?= urlencode($filterSearch) ?>&country=<?= urlencode($filterCountry) ?>&state=<?= urlencode($filterState) ?>&status=<?= urlencode($filterStatus) ?>&sort=<?= urlencode($filterSort) ?>">التالية</a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

        <!-- TAB CONTENT: 2. CLAIMS QUEUE -->
        <?php elseif ($activeTab === 'claims'): ?>
            <div class="admin-card">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-file-signature text-primary"></i> طلبات إثبات ملكية وتوثيق المدارس
                        <span class="badge bg-danger text-white ms-2" style="font-size: 0.68rem;"><?= count($pendingClaims) ?> طلب</span>
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المدرسة المطلوبة</th>
                                <th>الموقع</th>
                                <th>مقدم الطلب والصفة</th>
                                <th>رقم التواصل</th>
                                <th>المستند / التفويض</th>
                                <th>الحالة</th>
                                <th class="text-center pe-3">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingClaims)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">لا توجد طلبات توثيق معلقة حالياً.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingClaims as $clm): ?>
                                    <tr id="claim-row-<?= $clm['id'] ?>">
                                        <td class="fw-bold ps-3">#<?= $clm['id'] ?></td>
                                        <td>
                                            <a href="/school/<?= urlencode($clm['school_slug'] ?? '') ?>" target="_blank" class="fw-bold text-dark text-decoration-none">
                                                <?= e($clm['school_name'] ?? 'مدرسة #' . $clm['school_id']) ?>
                                            </a>
                                        </td>
                                        <td><?= e($clm['state'] ?? '') ?> — <?= e($clm['city'] ?? '') ?></td>
                                        <td>
                                            <div class="fw-bold"><?= e($clm['claimant_name']) ?></div>
                                            <div class="small text-muted" style="font-size: 0.68rem;"><?= e($clm['claimant_title']) ?></div>
                                        </td>
                                        <td>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $clm['claimant_phone']) ?>" target="_blank" class="text-success text-decoration-none fw-bold">
                                                <i class="fab fa-whatsapp me-1"></i> <?= e($clm['claimant_phone']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($clm['verification_doc_path'])): ?>
                                                <a href="<?= e(assetUrl($clm['verification_doc_path'])) ?>" target="_blank" class="btn btn-sm btn-light border py-1 px-2" style="font-size: 0.7rem;">
                                                    <i class="fas fa-paperclip me-1"></i> فتح المستند
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">لا يوجد ملف</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($clm['status'] === 'approved'): ?>
                                                <span class="badge bg-success">معتمد وموثق</span>
                                            <?php elseif ($clm['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger">مرفوض</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">قيد المراجعة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-3">
                                            <?php if ($clm['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success py-1 px-2 me-1" style="font-size: 0.7rem;" onclick="approveClaim(<?= $clm['id'] ?>)">
                                                    <i class="fas fa-check me-1"></i> اعتماد
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.7rem;" onclick="rejectClaim(<?= $clm['id'] ?>)">
                                                    <i class="fas fa-times me-1"></i> رفض
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">تم التعامل معه</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB CONTENT: 3. AUTONOMOUS RESEARCHER (INTERNAL TOOL) -->
        <?php elseif ($activeTab === 'researcher'): ?>
            <div class="row g-4">
                <div class="col-lg-6 col-12">
                    <div class="admin-card h-100">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title">
                                <i class="fas fa-robot text-primary"></i> استكشاف مدرسة جديدة وإدراجها فوراً
                            </h5>
                        </div>
                        <div class="p-4">
                            <form onsubmit="event.preventDefault(); adminRunDiscover();">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">اسم المدرسة أو المجمع أو الروضة</label>
                                    <input type="text" id="admDiscQuery" class="form-control" placeholder="مثال: مدارس القبس الخاصة كوستي، مدارس المنار بورتسودان..." required style="border-color: #DDE4ED; font-size: 0.8rem;">
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">الولاية (اختياري)</label>
                                        <select id="admDiscState" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem;">
                                            <option value="">استنتاج تلقائي</option>
                                            <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                                <option value="<?= e($st) ?>"><?= e($st) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">المدينة / المحلية (اختياري)</label>
                                        <input type="text" id="admDiscCity" class="form-control form-control-sm" placeholder="مثال: بحري، كوستي، دنقلا..." style="border-color: #DDE4ED; font-size: 0.75rem;">
                                    </div>
                                </div>

                                <button type="submit" id="btnAdminDisc" class="btn btn-sm btn-primary w-100 py-2 fw-bold" style="background: var(--navy); border: none; font-size: 0.78rem;">
                                    <i class="fas fa-search-plus me-1"></i> استكشاف وفهرسة آمنة في الدليل
                                </button>
                            </form>

                            <div id="adminDiscResult" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="admin-card h-100">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title">
                                <i class="fas fa-terminal text-primary"></i> تشغيل المهام المجدولة (Cron Worker CLI)
                            </h5>
                        </div>
                        <div class="p-4">
                            <p class="small text-muted" style="font-size: 0.76rem; line-height: 1.6;">
                                محرك الباحث الآلي مهيأ للتشغيل الصامت في السيرفر دون إشراك الزوار عبر الأمر البرمجي التالي في cPanel Cron Jobs:
                            </p>
                            <div class="p-3 rounded-3 bg-dark text-light font-monospace small mb-3" style="direction: ltr; font-size: 0.72rem;">
                                /usr/local/bin/php /home/wdenfygx/public_html/daleel.schoplus.sd/auto_researcher.php
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary py-2 px-3" onclick="exportMasterSeed()" style="font-size: 0.76rem;">
                                    <i class="fas fa-file-export me-1"></i> تصدير Master Seed الآن
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success py-2 px-3" onclick="recalculateRanks()" style="font-size: 0.76rem;">
                                    <i class="fas fa-sort-numeric-down me-1"></i> تحديث الترتيب التسلسلي
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- TAB CONTENT: 4. ADS CONFIGURATION -->
        <?php elseif ($activeTab === 'ads'): ?>
            <div class="admin-card">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-ad text-primary"></i> إدارة المساحة الإعلانية (أسفل تصفية الولايات)
                    </h5>
                </div>
                <div class="p-4" style="max-width: 650px;">
                    <form onsubmit="event.preventDefault(); saveAdSettings();">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="adEnabled" <?= !empty($adConfig['enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-dark small" for="adEnabled">تفعيل وظهور المساحة الإعلانية في الشريط الجانبي</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">عنوان الإعلان الرئيسي</label>
                            <input type="text" id="adTitle" class="form-control form-control-sm" value="<?= e($adConfig['title'] ?? 'أعلن هنا لمدرستك أو خدماتك') ?>" style="border-color: #DDE4ED; font-size: 0.76rem;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">الوصف والنص الترويجي</label>
                            <textarea id="adDesc" class="form-control form-control-sm" rows="3" style="border-color: #DDE4ED; font-size: 0.76rem;"><?= e($adConfig['desc'] ?? 'فرصة مميزة للوصول المباشر إلى آلاف أولياء الأمور والطلاب في كافة ولايات السودان يومياً.') ?></textarea>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark">نص زر الإجراء (CTA)</label>
                                <input type="text" id="adBtnText" class="form-control form-control-sm" value="<?= e($adConfig['btn_text'] ?? 'احجز مساحتك الإعلانية') ?>" style="border-color: #DDE4ED; font-size: 0.76rem;">
                            </div>
                            <div class="col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark">رقم واتساب الحجز والتواصل</label>
                                <input type="text" id="adWaPhone" class="form-control form-control-sm" value="<?= e($adConfig['wa_phone'] ?? '249912345678') ?>" placeholder="249xxxxxxxxx" style="border-color: #DDE4ED; font-size: 0.76rem;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary py-2 px-4 fw-bold" style="background: var(--navy); border: none; font-size: 0.78rem;">
                            <i class="fas fa-save me-1"></i> حفظ إعدادات الإعلان
                        </button>
                    </form>
                </div>
            </div>

        <!-- TAB CONTENT: 5. MAINTENANCE & SETTINGS -->
        <?php elseif ($activeTab === 'maintenance'): ?>
            <div class="row g-4">
                <div class="col-lg-6 col-12">
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title"><i class="fas fa-shield-alt text-primary"></i> بيانات أمان لوحة التحكم</h5>
                        </div>
                        <div class="p-4">
                            <div class="alert alert-info py-2 px-3 small rounded-3 mb-3" style="font-size: 0.74rem;">
                                يمكنك دائماً الدخول المباشر باستخدام المفتاح السري المعتمد في ملف البيئة أو تعيين كلمة مرور جديدة.
                            </div>
                            <form onsubmit="event.preventDefault(); updateAdminPassword();">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">كلمة مرور لوحة التحكم الجديدة</label>
                                    <input type="password" id="newAdminPassword" class="form-control form-control-sm" placeholder="أدخل كلمة مرور قوية" required style="border-color: #DDE4ED; font-size: 0.76rem;">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary py-2 px-3 fw-bold" style="background: var(--navy); border: none; font-size: 0.78rem;">
                                    <i class="fas fa-lock me-1"></i> تحديث كلمة المرور
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title"><i class="fas fa-database text-primary"></i> سلامة البيانات والمزامنة السحابية</h5>
                        </div>
                        <div class="p-4 d-flex flex-column gap-3">
                            <div>
                                <h6 class="fw-bold mb-1" style="font-size: 0.82rem;">إعادة ترقيم تسلسل الدليل (Rank Sync)</h6>
                                <p class="text-muted small mb-2" style="font-size: 0.72rem;">يضمن ترقيماً متسلسلاً غير منقطع لكافة المدارس بالدليل العام والولائي.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size: 0.74rem;" onclick="recalculateRanks()">
                                    <i class="fas fa-sort-numeric-down me-1"></i> إعادة الترقيم الآن
                                </button>
                            </div>

                            <hr class="my-1">

                            <div>
                                <h6 class="fw-bold mb-1" style="font-size: 0.82rem;">تصدير Master Seed للمستودع</h6>
                                <p class="text-muted small mb-2" style="font-size: 0.72rem;">تحديث ملف `seed_schools_data.php` بكافة المدارس المعتمدة الحالية (1,553 مدرسة).</p>
                                <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 0.74rem;" onclick="exportMasterSeed()">
                                    <i class="fas fa-file-export me-1"></i> تصدير Master Seed
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- JAVASCRIPT MASTER HANDLER -->
    <script>
    function showAdminToast(message, type = 'success') {
        let container = document.getElementById('spToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'spToastContainer';
            container.className = 'sp-admin-toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `sp-admin-toast toast-${type}`;
        let icon = 'check-circle';
        if (type === 'danger') icon = 'exclamation-circle';
        if (type === 'warning') icon = 'info-circle';
        toast.innerHTML = `<i class="fas fa-${icon}"></i> <span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-15px)';
            setTimeout(() => toast.remove(), 350);
        }, 2800);
    }

    function toggleSchoolMeta(schoolId, action, newVal, el) {
        if (el.classList.contains('is-loading')) return;

        const prevHtml = el.innerHTML;
        const prevClass = el.className;

        el.classList.add('is-loading');
        el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>جاري...</span>';

        const fd = new FormData();
        fd.append('ajax_action', action);
        fd.append('id', schoolId);
        fd.append('value', newVal);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            el.classList.remove('is-loading');
            if (data && data.success) {
                if (action === 'toggle_verified') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-green is-success-pulse';
                        el.innerHTML = '<i class="fas fa-check-circle"></i> <span>موثقة</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_verified', 0, this)`);
                        showAdminToast('تم اعتماد وتوثيق المدرسة بنجاح ✅', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-circle-xmark"></i> <span>عامة</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_verified', 1, this)`);
                        showAdminToast('تم تحويل المدرسة إلى عامة (غير موثقة) ℹ️', 'warning');
                    }
                } else if (action === 'toggle_schoplus') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-orange is-success-pulse';
                        el.innerHTML = '<i class="fas fa-award"></i> <span>ذكي</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_schoplus', 0, this)`);
                        showAdminToast('تم تفعيل شارة عميل SchoPlus الذكي 🌟', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-award"></i> <span>عادي</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_schoplus', 1, this)`);
                        showAdminToast('تم إلغاء شارة SchoPlus الذكي ℹ️', 'warning');
                    }
                } else if (action === 'toggle_historic') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-blue is-success-pulse';
                        el.innerHTML = '<i class="fas fa-landmark"></i> <span>عريق</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_historic', 0, this)`);
                        showAdminToast('تم اعتماد المدرسة كصرح وطني عريق 🏛️', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-landmark"></i> <span>حديث</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_historic', 1, this)`);
                        showAdminToast('تم إلغاء شارة الصرح العريق ℹ️', 'warning');
                    }
                }
                setTimeout(() => el.classList.remove('is-success-pulse'), 1200);
            } else {
                el.className = prevClass;
                el.innerHTML = prevHtml;
                showAdminToast('فشل التعديل: ' + (data.error || 'خطأ غير معروف'), 'danger');
            }
        })
        .catch(err => {
            el.classList.remove('is-loading');
            el.className = prevClass;
            el.innerHTML = prevHtml;
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function saveRanks(schoolId) {
        const btn = document.querySelector('.btn-save-rank-' + schoolId);
        const dirRank = document.getElementById('dir-rank-' + schoolId).value;
        const stateRank = document.getElementById('state-rank-' + schoolId).value;

        let prevBtnHtml = '';
        if (btn) {
            prevBtnHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 0.65rem;"></i>';
        }

        const fd = new FormData();
        fd.append('ajax_action', 'update_rank');
        fd.append('id', schoolId);
        fd.append('directory_rank', dirRank);
        fd.append('state_rank', stateRank);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check text-success" style="font-size: 0.65rem;"></i>';
                setTimeout(() => { btn.innerHTML = prevBtnHtml; }, 1800);
            }
            if (data && data.success) {
                showAdminToast(`تم حفظ ترتيب المدرسة (الدليل: #${dirRank} | الولاية: #${stateRank}) بنجاح`, 'success');
            } else {
                showAdminToast('فشل الحفظ: ' + (data.error || 'خطأ غير معروف'), 'danger');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = prevBtnHtml;
            }
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function recalculateRanks() {
        if (!confirm('هل تريد إعادة حساب الترتيب التسلسلي لكافة المدارس بالدليل وفق نظام الترتيب الفعلي المعتمد (المدارس العريقة أولاً ثم بالتتابع)؟')) return;

        showAdminToast('جاري إعادة حساب الترتيب الفعلي لكافة المدارس...', 'warning');

        const fd = new FormData();
        fd.append('ajax_action', 'recalculate_ranks');

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                showAdminToast(data.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showAdminToast('حدث خطأ أثناء إعادة الحساب: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            showAdminToast('حدث خطأ في الاتصال.', 'danger');
        });
    }

    function deleteSchool(schoolId, schoolName) {
        if (!confirm(`هل أنت متأكد من حذف مدرسة "${schoolName}" نهائياً من الدليل؟`)) return;

        const fd = new FormData();
        fd.append('ajax_action', 'delete_school');
        fd.append('id', schoolId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const row = document.getElementById('row-school-' + schoolId);
                if (row) row.remove();
                alert('تم حذف المدرسة بنجاح.');
            } else {
                alert('فشل الحذف: ' + (data.error || 'خطأ غير معروف'));
            }
        });
    }

    function resetPasswordModal(schoolId, schoolName) {
        const newPwd = prompt(`أدخل كلمة المرور الجديدة لمدير مدرسة "${schoolName}" (أو اتركها فارغة لتوليد كلمة مرور عشوائية):`);
        if (newPwd === null) return;

        const fd = new FormData();
        fd.append('ajax_action', 'reset_password');
        fd.append('id', schoolId);
        fd.append('new_password', newPwd);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                prompt('تم تعيين كلمة المرور بنجاح! انسخ كلمة المرور لتزويد المدرسة بها:', data.new_password);
            } else {
                alert('فشل تعيين كلمة المرور.');
            }
        });
    }

    function editSchoolPhone(schoolId, schoolName, currentPhone, currentWa) {
        const newPhone = prompt(`تعديل رقم الهاتف الأساسي لمدرسة "${schoolName}":`, currentPhone || '');
        if (newPhone === null) return;

        const newWa = prompt(`تعديل رقم الواتساب لمدرسة "${schoolName}" (يمكنك تركه مطابقاً للهاتف أو كتابة رقم منفصل):`, currentWa || newPhone);
        if (newWa === null) return;

        const fd = new FormData();
        fd.append('ajax_action', 'update_phone');
        fd.append('id', schoolId);
        fd.append('phone', newPhone.trim());
        fd.append('whatsapp', newWa.trim());

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                showAdminToast(data.message, 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                showAdminToast('فشل التعديل: ' + (data.error || 'خطأ غير معروف'), 'danger');
            }
        })
        .catch(err => showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger'));
    }

    function approveClaim(claimId) {
        if (!confirm('هل أنت متأكد من اعتماد توثيق هذه المدرسة وتسليم إدارتها للمفوض؟')) return;

        const fd = new FormData();
        fd.append('ajax_action', 'approve_claim');
        fd.append('claim_id', claimId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('حدث خطأ: ' + (data.error || ''));
            }
        });
    }

    function rejectClaim(claimId) {
        const reason = prompt('يرجى كتابة سبب رفض طلب التوثيق:', 'المستندات المرفقة غير كافية أو غير معتمدة');
        if (reason === null) return;

        const fd = new FormData();
        fd.append('ajax_action', 'reject_claim');
        fd.append('claim_id', claimId);
        fd.append('reason', reason);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('حدث خطأ: ' + (data.error || ''));
            }
        });
    }

    function recalculateRanks() {
        if (!confirm('هل تريد إعادة حساب الترتيب التسلسلي لكافة المدارس بالدليل الآن؟')) return;

        const fd = new FormData();
        fd.append('ajax_action', 'recalculate_ranks');

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('حدث خطأ أثناء إعادة الحساب.');
            }
        });
    }

    function exportMasterSeed() {
        const fd = new FormData();
        fd.append('ajax_action', 'export_seed');

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert(data.message);
            } else {
                alert('فشل التصدير.');
            }
        });
    }

    function saveAdSettings() {
        const enabled = document.getElementById('adEnabled').checked ? 1 : 0;
        const title = document.getElementById('adTitle').value;
        const desc = document.getElementById('adDesc').value;
        const btnText = document.getElementById('adBtnText').value;
        const waPhone = document.getElementById('adWaPhone').value;

        const fd = new FormData();
        fd.append('ajax_action', 'save_ad_settings');
        fd.append('ad_enabled', enabled);
        fd.append('ad_title', title);
        fd.append('ad_desc', desc);
        fd.append('ad_btn_text', btnText);
        fd.append('ad_wa_phone', waPhone);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert(data.message);
            } else {
                alert('فشل الحفظ.');
            }
        });
    }

    function adminRunDiscover() {
        const q = document.getElementById('admDiscQuery').value.trim();
        const st = document.getElementById('admDiscState').value.trim();
        const ct = document.getElementById('admDiscCity').value.trim();
        const btn = document.getElementById('btnAdminDisc');
        const resBox = document.getElementById('adminDiscResult');

        if (!q) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الاستكشاف والفهرسة...';

        resBox.style.display = 'block';
        resBox.innerHTML = '<div class="alert alert-info py-2 px-3 small"><i class="fas fa-robot me-1"></i> جاري استكشاف ومطابقة المدرسة وفحص التكرار...</div>';

        const fd = new FormData();
        fd.append('query', q);
        fd.append('state', st);
        fd.append('city', ct);
        fd.append('key', 'schoplus_daleel_deploy_sec_4412f');

        fetch('/api_auto_discover.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search-plus me-1"></i> استكشاف وفهرسة آمنة في الدليل';

            if (data && data.success) {
                resBox.innerHTML = `
                    <div class="alert ${data.already_exists ? 'alert-warning' : 'alert-success'} py-2 px-3 small rounded-3">
                        <div class="fw-bold mb-1">${data.message}</div>
                        <div><strong>${data.school.name}</strong> (${data.school.state} — ${data.school.city})</div>
                        <a href="${data.school.url}" target="_blank" class="btn btn-sm btn-dark py-1 px-2 mt-2" style="font-size: 0.72rem;">معاينة المدرسة في الدليل</a>
                    </div>
                `;
            } else {
                resBox.innerHTML = `<div class="alert alert-danger py-2 px-3 small">${data.error || 'فشل الاستكشاف'}</div>`;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search-plus me-1"></i> استكشاف وفهرسة آمنة في الدليل';
            resBox.innerHTML = '<div class="alert alert-danger py-2 px-3 small">حدث خطأ في الاتصال.</div>';
        });
    }
    </script>
</body>
</html>
