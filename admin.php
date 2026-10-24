<?php
/**
 * SchoPlus Daleel — Super Admin Master Control Panel
 * لوحة التحكم والإشراف العام على دليل المدارس السودانية
 * Node 6 of SchoPlus Cloud Ecosystem
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';
require_once __DIR__ . '/DaleelWhatsAppService.php';
require_once __DIR__ . '/DaleelSyncService.php';

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

if (!function_exists('buildWhatsAppMessageText')) {
    function buildWhatsAppMessageText(string $schoolName, string $schoolSlug = ''): string {
        $schoolUrl = !empty($schoolSlug) ? "https://daleel.schoplus.sd/school/" . $schoolSlug : "https://daleel.schoplus.sd";

        $paragraphs = [
            "السلام عليكم ورحمة الله وبركاته،",
            "إدارة مدرسة *{$schoolName}* الموقرة،",
            "تحية طيبة لكم من منصة *دليل المدارس السودانية*.",
            "",
            "◆ *الهدف من المنصة والرؤية:*",
            "«دليل المدارس السودانية» هو المنصة الرقمية الموحدة لتوثيق وربط المدارس في كافة الولايات ودول المهجر، بهدف:",
            "• توفير تواجد رقمي رسمي ومجاني لكل مدرسة على الإنترنت.",
            "• تسهيل وصول آلاف أولياء الأمور والطلاب إلى بيانات المدارس، مراحلها، ومواقعها للتواصل المباشر مع الإدارة.",
            "• تعزيز الشفافية والمصداقية ودعم مسيرة التعليم السوداني.",
            "",
            "◆ *كيف تمت إضافة مدرستكم؟*",
            "نود إحاطتكم بأنه قد تمت إضافة البيانات الأساسية لمدرستكم مبدئياً وبشكل آلي عبر *محرك استكشاف وبحث ذكي معتمد*، استناداً إلى السجلات التعليمية والإعلانات العامة المتاحة.",
            "",
            "◆ *رابط صفحة مدرستكم في الدليل:*",
            "{$schoolUrl}",
            "",
            "◆ *المزايا والخيارات المتاحة لكم:*",
            "[1] *توثيق المدرسة واستلام لوحة الإدارة مجاناً*: لإضافة شعار المدرسة، المراحل الدراسية، الرسوم، وصور المباني وأرقام التواصل المباشرة.",
            "[2] *تحديث وتصحيح أي بيانات*: لضمان دقة المعلومات المعروضة لأولياء الأمور.",
            "[3] *طلب الحذف التام*: في حال رغبتم بعدم إدراج مدرستكم في الدليل، يتم الحذف فوراً دون أي شروط.",
            "",
            "◆ *اطمئنان وتأكيد أمني (حماية ضد الاحتيال):*",
            "نؤكد لكم أن هذه المبادرة تعليمية وتوثيقية رسمية، ولن نطلب منكم أي بيانات بنكية أو كلمات سر إطلاقاً.",
            "وللتأكد التام والاطمئنان من مصداقية المنصة والرابط، يمكنكم البحث مباشرة في محرك البحث Google عن عبارة:",
            "« دليل المدارس السودانية »",
            "وستجدون موقعنا الرسمي يتصدر النتائج الأولى: daleel.schoplus.sd",
            "",
            "للتوثيق أو التحديث أو طلب الحذف، يمكنكم زيارة رابط صفحتكم والضغط على زر *\"إثبات ملكية المدرسة\"*، أو الرد مباشرة على هذه المحادثة وسيسعد فريقنا بخدمتكم.",
            "",
            "شاكرين ومقدرين جهودكم ورسالتكم التعليمية النبيلة،",
            "*فريق عمل دليل المدارس السودانية — SchoPlus*"
        ];

        return implode("\r\n", $paragraphs);
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

        $msgText = buildWhatsAppMessageText($schoolName, $schoolSlug);
        return "https://wa.me/{$digits}?text=" . rawurlencode($msgText);
    }
}

if (!function_exists('getSchoolTypeLabel')) {
    function getSchoolTypeLabel(?string $type, ?string $stages, ?string $name): array {
        $type = strtolower(trim((string)$type));
        $stages = (string)$stages;
        $name = (string)$name;
        
        if (strpos($stages, 'kindergarten') !== false || $type === 'kindergarten' || mb_strpos($name, 'روضة') !== false || mb_strpos($name, 'حضانة') !== false) {
            return ['label' => 'روضة / حضانة', 'badge' => 'bg-warning-subtle text-warning border border-warning-subtle', 'icon' => 'fa-shapes'];
        }
        if ($type === 'government' || mb_strpos($name, 'حكومية') !== false || mb_strpos($name, 'حكومي') !== false) {
            return ['label' => 'حكومية', 'badge' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'fa-building-columns'];
        }
        if ($type === 'international') {
            return ['label' => 'عالمية / دولية', 'badge' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'fa-globe'];
        }
        if ($type === 'community') {
            return ['label' => 'جالية / مهجر', 'badge' => 'bg-info-subtle text-info border border-info-subtle', 'icon' => 'fa-users'];
        }
        return ['label' => 'خاصة', 'badge' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'fa-school'];
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
<?php 
exit; 
endif;

// =========================================================================
// 2. MASTER ADMIN AJAX ACTIONS HANDLER
// =========================================================================
$rawAjaxInput = file_get_contents('php://input');
$jsonAjaxInput = !empty($rawAjaxInput) ? @json_decode($rawAjaxInput, true) : null;
$action = $_REQUEST['ajax_action'] ?? ($jsonAjaxInput['ajax_action'] ?? null);

if ($action !== null) {
    header('Content-Type: application/json; charset=utf-8');
    if (is_array($jsonAjaxInput) && empty($_POST)) {
        $_POST = $jsonAjaxInput;
    }

    try {
        if ($action === 'toggle_verified') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $pdo->prepare("UPDATE daleel_schools SET is_verified = ?, updated_at = NOW() WHERE id = ?")->execute([$val, $id]);
            
            // Recalculate ranks so verified schools are promoted right after historic schools
            recalculateDaleelRanks($pdo);
            $newDirRank = getSchoolDirectoryRank($pdo, $id);

            echo json_encode([
                'success' => true,
                'id' => $id,
                'is_verified' => $val,
                'new_rank' => $newDirRank,
                'message' => $val 
                    ? "تم توثيق المدرسة وترقية ترتيبها في الدليل مباشرة بعد الصروح العريقة (#{$newDirRank}) بنجاح ⭐" 
                    : "تم إلغاء توثيق المدرسة وإعادة ضبط ترتيبها في الدليل (#{$newDirRank}) ℹ️"
            ]);
            exit;
        }

        if ($action === 'toggle_schoplus') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $pdo->prepare("UPDATE daleel_schools SET is_schoplus_client = ? WHERE id = ?")->execute([$val, $id]);
            recalculateDaleelRanks($pdo);
            $newDirRank = getSchoolDirectoryRank($pdo, $id);
            echo json_encode(['success' => true, 'id' => $id, 'is_schoplus_client' => $val, 'new_rank' => $newDirRank]);
            exit;
        }

        if ($action === 'toggle_historic') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $badge = $val ? 'صرح وطني عريق' : null;
            $pdo->prepare("UPDATE daleel_schools SET is_historic = ?, historic_badge_reason = ? WHERE id = ?")->execute([$val, $badge, $id]);
            recalculateDaleelRanks($pdo);
            $newDirRank = getSchoolDirectoryRank($pdo, $id);
            echo json_encode(['success' => true, 'id' => $id, 'is_historic' => $val, 'new_rank' => $newDirRank]);
            exit;
        }

        if ($action === 'toggle_whatsapp_sent') {
            $id = (int)($_POST['id'] ?? 0);
            $val = (int)($_POST['value'] ?? 0);
            $sentAt = $val ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE daleel_schools SET whatsapp_sent = ?, whatsapp_sent_at = ? WHERE id = ?")->execute([$val, $sentAt, $id]);
            echo json_encode([
                'success' => true,
                'id' => $id,
                'whatsapp_sent' => $val,
                'whatsapp_sent_at' => $sentAt ? date('j/n H:i', strtotime($sentAt)) : null,
                'message' => $val ? 'تم تحديد المدرسة: تم إرسال رسالة الواتساب بنجاح 📲' : 'تم إلغاء علامة إرسال الواتساب للمدرسة ℹ️'
            ]);
            exit;
        }

        if ($action === 'send_school_whatsapp') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $sch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sch) {
                echo json_encode(['success' => false, 'error' => 'المدرسة غير موجودة']);
                exit;
            }
            $phone = trim((string)($sch['whatsapp'] ?: $sch['phone']));
            if ($phone === '') {
                echo json_encode(['success' => false, 'error' => 'لا يوجد رقم هاتف مسجل لهذه المدرسة']);
                exit;
            }
            $msg = buildWhatsAppMessageText($sch['name'], $sch['slug'] ?? '');
            $res = DaleelWhatsAppService::sendMessage($phone, $msg);
            if (!empty($res['success'])) {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE daleel_schools SET whatsapp_sent = 1, whatsapp_sent_at = ? WHERE id = ?")->execute([$now, $id]);
                DaleelWhatsAppService::logMessage($id, 'out', $phone, $res['chatId'] ?? null, $msg, 'school_outreach');
                echo json_encode([
                    'success' => true,
                    'id' => $id,
                    'sent_at' => date('j/n H:i', strtotime($now)),
                    'message' => 'تم إرسال رسالة الواتساب بنجاح إلى ' . htmlspecialchars($sch['name']) . ' 📲'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $res['error'] ?? 'تعذر إرسال الرسالة، تأكد من اتصال خادم الواتساب (Port 8085)'
                ]);
            }
            exit;
        }

        if ($action === 'check_school_whatsapp') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name, phone, whatsapp FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $sch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sch) {
                echo json_encode(['success' => false, 'error' => 'المدرسة غير موجودة']);
                exit;
            }
            $phone = trim((string)($sch['whatsapp'] ?: $sch['phone']));
            if ($phone === '') {
                echo json_encode(['success' => false, 'error' => 'لا يوجد رقم هاتف مسجل لهذه المدرسة']);
                exit;
            }
            $res = DaleelWhatsAppService::checkNumber($phone);
            if (isset($res['exists'])) {
                $exists = (bool)$res['exists'];
                DaleelWhatsAppService::updateSchoolWhatsAppStatus($id, $exists, $res['jid'] ?? null);
                echo json_encode([
                    'success' => true,
                    'id' => $id,
                    'phone' => $phone,
                    'exists' => $exists,
                    'status' => $exists ? 'valid' : 'invalid',
                    'jid' => $res['jid'] ?? null,
                    'message' => $exists ? 'الرقم مسجل في الواتساب ولديه حساب نشط 🟢' : 'الرقم ليس لديه حساب واتساب (هاتف أرضي أو غير مفعل) 🔴'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $res['error'] ?? 'تعذر فحص الرقم من خادم الواتساب'
                ]);
            }
            exit;
        }

        if ($action === 'bulk_check_whatsapp_numbers') {
            @set_time_limit(180);
            $state = trim($_POST['state'] ?? '');
            $limit = (int)($_POST['limit'] ?? 20);
            $limit = max(1, min(50, $limit));
            $onlyUnverified = !empty($_POST['only_unverified']);

            $sql = "SELECT id, name, phone, whatsapp, whatsapp_status FROM daleel_schools WHERE (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '') AND deleted_at IS NULL";
            $params = [];
            if ($state !== '') {
                $sql .= " AND state = ?";
                $params[] = $state;
            }
            if ($onlyUnverified) {
                $sql .= " AND (whatsapp_status IS NULL OR whatsapp_status = 'unverified')";
            }
            $sql .= " ORDER BY (whatsapp_status IS NULL OR whatsapp_status = 'unverified') DESC, id ASC LIMIT " . $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            $validCount = 0;
            $invalidCount = 0;

            foreach ($schools as $sch) {
                $phone = trim((string)($sch['whatsapp'] ?: $sch['phone']));
                $check = DaleelWhatsAppService::checkNumber($phone);
                $exists = !empty($check['exists']);
                DaleelWhatsAppService::updateSchoolWhatsAppStatus((int)$sch['id'], $exists, $check['jid'] ?? null);

                if ($exists) $validCount++;
                else $invalidCount++;

                $results[] = [
                    'id' => (int)$sch['id'],
                    'name' => $sch['name'],
                    'phone' => $phone,
                    'exists' => $exists,
                    'status' => $exists ? 'valid' : 'invalid'
                ];
            }

            echo json_encode([
                'success' => true,
                'checked_count' => count($results),
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'schools' => $results
            ]);
            exit;
        }

        if ($action === 'simulate_bot_message') {
            $phone = trim($_POST['phone'] ?? '249111970220');
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') {
                echo json_encode(['success' => false, 'error' => 'الرسالة فارغة']);
                exit;
            }
            $res = DaleelBotService::simulate($phone, $msg);
            echo json_encode($res);
            exit;
        }

        if ($action === 'reset_bot_simulation') {
            $phone = trim($_POST['phone'] ?? '249111970220');
            try {
                $pdo->prepare("DELETE FROM daleel_whatsapp_sessions WHERE phone = ?")->execute([$phone]);
            } catch (Throwable $e) {}
            echo json_encode(['success' => true, 'message' => 'تمت إعادة تعيين محادثة البوت بنجاح 🔄']);
            exit;
        }

        if ($action === 'run_batch_whatsapp_campaign') {
            @set_time_limit(120);
            $state = trim($_POST['state'] ?? '');
            $limit = (int)($_POST['batch_size'] ?? 5);
            $limit = max(1, min(10, $limit));

            $sql = "SELECT id, name, slug, phone, whatsapp, country FROM daleel_schools 
                    WHERE (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '')
                      AND (whatsapp_sent = 0 OR whatsapp_sent IS NULL)
                      AND (whatsapp_status = 'valid' OR whatsapp_status IS NULL OR whatsapp_status = 'unverified')
                      AND deleted_at IS NULL";
            $params = [];
            if ($state !== '') {
                $sql .= " AND state = ?";
                $params[] = $state;
            }
            $sql .= " ORDER BY (whatsapp_status = 'valid') DESC, id ASC LIMIT " . $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sent = [];
            $failed = [];

            foreach ($targets as $sch) {
                $phone = trim((string)($sch['whatsapp'] ?: $sch['phone']));
                $msgText = buildWhatsAppMessageText($sch['name'], $sch['slug'] ?? '');
                $res = DaleelWhatsAppService::sendMessage($phone, $msgText);

                if (!empty($res['success'])) {
                    $pdo->prepare("UPDATE daleel_schools SET whatsapp_sent = 1, whatsapp_sent_at = NOW(), whatsapp_status = 'valid' WHERE id = ?")->execute([$sch['id']]);
                    DaleelWhatsAppService::logMessage((int)$sch['id'], 'out', $phone, $res['chatId'] ?? null, $msgText, 'school_campaign');
                    $sent[] = ['id' => $sch['id'], 'name' => $sch['name'], 'phone' => $phone];
                } else {
                    $failed[] = ['id' => $sch['id'], 'name' => $sch['name'], 'phone' => $phone, 'error' => $res['error'] ?? 'فشل'];
                }
            }

            // إحصائيات المتبقي
            $remainingSql = "SELECT COUNT(*) FROM daleel_schools WHERE (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '') AND (whatsapp_sent = 0 OR whatsapp_sent IS NULL) AND deleted_at IS NULL";
            $remaining = (int)$pdo->query($remainingSql)->fetchColumn();

            echo json_encode([
                'success' => true,
                'sent_count' => count($sent),
                'failed_count' => count($failed),
                'sent' => $sent,
                'failed' => $failed,
                'remaining' => $remaining
            ]);
            exit;
        }

        if ($action === 'get_whatsapp_server_status') {
            $status = DaleelWhatsAppService::getStatus();
            echo json_encode($status);
            exit;
        }

        if ($action === 'restart_whatsapp_server') {
            $res = DaleelWhatsAppService::restart();
            echo json_encode($res);
            exit;
        }

        if ($action === 'start_whatsapp_server') {
            $serverDir = __DIR__ . '/whatsapp-server';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $vbs = $serverDir . '/run_whatsapp_hidden.vbs';
                if (file_exists($vbs)) {
                    pclose(popen("wscript.exe \"{$vbs}\"", "r"));
                } else {
                    pclose(popen("start /B cmd /c \"cd /d \"{$serverDir}\" && node index.js > server.log 2>&1\"", "r"));
                }
            } else {
                exec("cd " . escapeshellarg($serverDir) . " && node index.js > server.log 2>&1 &");
            }
            sleep(2);
            $status = DaleelWhatsAppService::getStatus();
            echo json_encode(['success' => true, 'message' => 'تم بدء تشغيل الخادم بنجاح 🚀', 'status' => $status]);
            exit;
        }

        if ($action === 'stop_whatsapp_server') {
            $serverDir = __DIR__ . '/whatsapp-server';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $bat = $serverDir . '/stop_whatsapp.bat';
                pclose(popen("start /B cmd /c \"{$bat}\"", "r"));
            } else {
                exec("fuser -k 8085/tcp >/dev/null 2>&1");
            }
            echo json_encode(['success' => true, 'message' => 'تم إيقاف خادم الواتساب بنجاح ⏹️']);
            exit;
        }

        if ($action === 'logout_whatsapp_server') {
            $res = DaleelWhatsAppService::logout();
            echo json_encode($res);
            exit;
        }

        if ($action === 'send_direct_whatsapp') {
            $phone = trim($_POST['phone'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if ($phone === '' || $message === '') {
                echo json_encode(['success' => false, 'error' => 'يرجى إدخال رقم الهاتف ونص الرسالة']);
                exit;
            }
            $res = DaleelWhatsAppService::sendMessage($phone, $message);
            if (!empty($res['success'])) {
                DaleelWhatsAppService::logMessage(null, 'out', $phone, $res['chatId'] ?? null, $message, 'direct_admin');
                echo json_encode(['success' => true, 'message' => 'تم إرسال الرسالة بنجاح 📲']);
            } else {
                echo json_encode(['success' => false, 'error' => $res['error'] ?? 'فشل الإرسال']);
            }
            exit;
        }


        if ($action === 'get_school_quick_details') {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $sch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sch) {
                echo json_encode(['success' => false, 'error' => 'المدرسة غير موجودة']);
                exit;
            }
            $actualDirRank = getSchoolDirectoryRank($pdo, $id, !empty($sch['directory_rank']) ? (int)$sch['directory_rank'] : null);
            $actualStateRank = getSchoolStateRank($pdo, $id, (string)($sch['state'] ?? ''), !empty($sch['state_rank']) ? (int)$sch['state_rank'] : null);

            echo json_encode([
                'success' => true,
                'school' => [
                    'id' => (int)$sch['id'],
                    'name' => $sch['name'],
                    'name_en' => $sch['name_en'] ?? '',
                    'slug' => $sch['slug'],
                    'phone' => $sch['phone'] ?? '',
                    'whatsapp' => $sch['whatsapp'] ?? '',
                    'school_type' => $sch['school_type'] ?? 'private',
                    'gender_type' => $sch['gender_type'] ?? 'mixed',
                    'curriculum' => $sch['curriculum'] ?? 'sudanese',
                    'country' => $sch['country'] ?? 'السودان',
                    'state' => $sch['state'] ?? '',
                    'city' => $sch['city'] ?? '',
                    'district' => $sch['district'] ?? '',
                    'address' => $sch['address'] ?? '',
                    'stages' => $sch['stages'] ?? '',
                    'established_year' => $sch['established_year'] ?? '',
                    'is_verified' => (int)($sch['is_verified'] ?? 0),
                    'is_schoplus_client' => (int)($sch['is_schoplus_client'] ?? 0),
                    'is_historic' => (int)($sch['is_historic'] ?? 0),
                    'historic_badge_reason' => $sch['historic_badge_reason'] ?? '',
                    'directory_rank' => (int)($sch['directory_rank'] ?: $actualDirRank),
                    'state_rank' => (int)($sch['state_rank'] ?: $actualStateRank),
                    'actual_directory_rank' => $actualDirRank,
                    'actual_state_rank' => $actualStateRank,
                    'status' => $sch['status'] ?? 'pending',
                    'owner_name' => $sch['owner_name'] ?? '',
                    'owner_phone' => $sch['owner_phone'] ?? '',
                    'owner_title' => $sch['owner_title'] ?? '',
                    'logo_path' => $sch['logo_path'] ?? '',
                    'views_count' => (int)($sch['views_count'] ?? 0),
                    'rating_avg' => (float)($sch['rating_avg'] ?? 5.0),
                    'reviews_count' => (int)($sch['reviews_count'] ?? 0)
                ]
            ]);
            exit;
        }

        if ($action === 'quick_save_school') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'المدرسة غير موجودة']);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $schoolType = trim($_POST['school_type'] ?? 'private');
            $country = trim($_POST['country'] ?? 'السودان');
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $stages = trim($_POST['stages'] ?? '');
            $establishedYear = trim($_POST['established_year'] ?? '');
            $isVerified = !empty($_POST['is_verified']) ? 1 : 0;
            $isSchoplus = !empty($_POST['is_schoplus_client']) ? 1 : 0;
            $isHistoric = !empty($_POST['is_historic']) ? 1 : 0;
            $dirRank = (int)($_POST['directory_rank'] ?? 0);
            $stRank = (int)($_POST['state_rank'] ?? 0);
            $status = trim($_POST['status'] ?? 'approved');

            if ($name === '') {
                echo json_encode(['success' => false, 'error' => 'اسم المدرسة مطلوب']);
                exit;
            }

            $upd = $pdo->prepare("UPDATE daleel_schools SET 
                name = ?,
                name_en = ?,
                phone = ?,
                whatsapp = ?,
                school_type = ?,
                country = ?,
                state = ?,
                city = ?,
                district = ?,
                stages = ?,
                established_year = ?,
                is_verified = ?,
                is_schoplus_client = ?,
                is_historic = ?,
                directory_rank = ?,
                state_rank = ?,
                status = ?,
                updated_at = NOW()
                WHERE id = ?");
            $upd->execute([
                $name,
                $nameEn ?: null,
                $phone,
                $whatsapp ?: null,
                $schoolType,
                $country,
                $state,
                $city,
                $district ?: null,
                $stages ?: null,
                $establishedYear ?: null,
                $isVerified,
                $isSchoplus,
                $isHistoric,
                $dirRank ?: null,
                $stRank ?: null,
                $status,
                $id
            ]);

            recalculateDaleelRanks($pdo);
            $newDirRank = getSchoolDirectoryRank($pdo, $id);

            echo json_encode([
                'success' => true,
                'id' => $id,
                'name' => $name,
                'new_rank' => $newDirRank,
                'message' => 'تم حفظ تحديثات المدرسة بنجاح ✅'
            ]);
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
                recalculateDaleelRanks($pdo);
                $newDirRank = getSchoolDirectoryRank($pdo, $schoolId);
                echo json_encode(['success' => true, 'claim_id' => $claimId, 'new_rank' => $newDirRank, 'message' => "تم اعتماد التوثيق ونقل ملكية المدرسة وترقية ترتيبها في الدليل مباشرة بعد الصروح العريقة (#{$newDirRank}) بنجاح!"]);
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

        if ($action === 'update_inquiry_status') {
            $inqId = (int)($_POST['id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? 'new');
            $stmt = $pdo->prepare("UPDATE daleel_school_inquiries SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $inqId]);
            echo json_encode(['success' => true, 'id' => $inqId, 'status' => $newStatus, 'message' => 'تم تحديث حالة الاستفسار بنجاح.']);
            exit;
        }

        if ($action === 'delete_inquiry') {
            $inqId = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM daleel_school_inquiries WHERE id = ?");
            $stmt->execute([$inqId]);
            echo json_encode(['success' => true, 'id' => $inqId, 'message' => 'تم حذف الاستفسار بنجاح.']);
            exit;
        }

        if ($action === 'toggle_review_approval') {
            $revId = (int)($_POST['id'] ?? 0);
            $isApproved = (int)($_POST['is_approved'] ?? 0);
            $stmt = $pdo->prepare("UPDATE daleel_school_reviews SET is_approved = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$isApproved, $revId]);
            echo json_encode(['success' => true, 'id' => $revId, 'is_approved' => $isApproved, 'message' => $isApproved ? 'تم اعتماد ونشر التقييم بنجاح ⭐' : 'تم إخفاء التقييم عن الدليل ℹ️']);
            exit;
        }

        if ($action === 'delete_review') {
            $revId = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM daleel_school_reviews WHERE id = ?");
            $stmt->execute([$revId]);
            echo json_encode(['success' => true, 'id' => $revId, 'message' => 'تم حذف التقييم بنجاح.']);
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
            $dirSeq = recalculateDaleelRanks($pdo);
            echo json_encode(['success' => true, 'count' => $dirSeq, 'message' => "تمت إعادة حساب الترتيب التسلسلي الفعلي بنجاح لـ {$dirSeq} مدرسة في الدليل (الصروح العريقة أولاً، ثم المدارس الموثقة والذكية، ثم بقية المدارس)."]);
            exit;
        }

        if ($action === 'export_seed') {
            require_once __DIR__ . '/export_master_seed.php';
            $cnt = exportMasterSeed($pdo);
            echo json_encode(['success' => true, 'count' => $cnt, 'message' => "تم تصدير قاعدة البيانات المركزية ({$cnt} مؤسسة) إلى seed_schools_data.php بنجاح."]);
            exit;
        }

        if ($action === 'run_autonomous_maps_worker') {
            @set_time_limit(180);
            @ini_set('memory_limit', '512M');

            $scopeState = trim($_POST['state'] ?? '');
            $scopeLocality = trim($_POST['locality'] ?? ($_POST['city'] ?? ''));
            $scopeType = trim($_POST['school_type'] ?? 'all');
            $targetCount = (int)($_POST['target_count'] ?? 20);
            $schoolName = trim($_POST['school_name'] ?? '');
            $autoRegister = !empty($_POST['auto_register']) && $_POST['auto_register'] !== '0' && $_POST['auto_register'] !== 'false';

            $result = SchoolAutoResearcher::runAutonomousMapsDiscovery(
                $pdo,
                !empty($scopeState) ? $scopeState : null,
                $targetCount,
                $scopeType,
                !empty($schoolName) ? $schoolName : null,
                $autoRegister,
                $logs,
                !empty($scopeLocality) ? $scopeLocality : null
            );

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'quick_approve_discovered_school') {
            $rawPayload = file_get_contents('php://input');
            $data = json_decode($rawPayload, true) ?: $_POST;

            $name = trim($data['name'] ?? '');
            $state = trim($data['state'] ?? '');
            $city = trim($data['city'] ?? '');
            $district = trim($data['district'] ?? '');
            $schoolType = trim($data['school_type'] ?? 'private');
            $phone = trim($data['phone'] ?? '');
            $gmap = trim($data['google_map_url'] ?? '');
            $fb = trim($data['facebook_url'] ?? ($data['facebook'] ?? ''));
            $desc = trim($data['description'] ?? '');

            $res = SchoolAutoResearcher::autoDiscoverAndRegister(
                $pdo,
                $name,
                !empty($state) ? $state : null,
                !empty($city) ? $city : null,
                !empty($schoolType) ? $schoolType : null,
                !empty($phone) ? $phone : null,
                !empty($phone) ? $phone : null,
                !empty($gmap) ? $gmap : null,
                !empty($district) ? $district : null,
                null,
                !empty($desc) ? $desc : null,
                !empty($fb) ? $fb : null
            );

            if (!empty($res['created'])) {
                recalculateDaleelRanks($pdo);
                if (class_exists('DaleelSyncService') && DaleelSyncService::isLocal()) {
                    if (!empty($res['school']['id'])) {
                        DaleelSyncService::pushSchoolById((int)$res['school']['id']);
                    } else {
                        DaleelSyncService::autoSyncDelta();
                    }
                }
                require_once __DIR__ . '/export_master_seed.php';
                exportMasterSeed($pdo);
            }

            $totalNow = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
            $res['total_schools_now'] = $totalNow;

            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'batch_save_discovered_schools') {
            $rawPayload = file_get_contents('php://input');
            $data = json_decode($rawPayload, true) ?: $_POST;
            $rawSchools = $data['schools'] ?? [];
            if (is_string($rawSchools)) {
                $rawSchools = json_decode($rawSchools, true) ?: [];
            }

            if (empty($rawSchools) || !is_array($rawSchools)) {
                echo json_encode(['success' => false, 'error' => 'قائمة المدارس فارغة أو غير صالحة للاستيراد بالدفعة.']);
                exit;
            }

            $result = SchoolAutoResearcher::importVerifiedSchoolBatch($pdo, $rawSchools);

            if (!empty($result['created'])) {
                recalculateDaleelRanks($pdo);
                if (class_exists('DaleelSyncService') && DaleelSyncService::isLocal()) {
                    DaleelSyncService::autoSyncDelta();
                }
                require_once __DIR__ . '/export_master_seed.php';
                exportMasterSeed($pdo);
            }

            $totalNow = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
            $result['total_schools_now'] = $totalNow;

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ─── CLOUD SYNC ENDPOINTS (OPTION 1: DIRECT CLOUD SYNC) ──────────────
        if ($action === 'get_cloud_sync_stats') {
            $cloudUrl = 'https://daleel.schoplus.sd/api_sync.php?action=stats&key=schoplus_daleel_deploy_sec_4412f';
            $ch = curl_init($cloudUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SchoPlus-Daleel-Sync/3.8');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            $cloudData = $res ? json_decode($res, true) : null;

            // Local Stats
            $localTotal = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE deleted_at IS NULL")->fetchColumn();
            $localApproved = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND deleted_at IS NULL")->fetchColumn();
            $localWithPhone = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE phone IS NOT NULL AND phone != '' AND deleted_at IS NULL")->fetchColumn();
            $localWaSent = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE whatsapp_sent = 1 AND deleted_at IS NULL")->fetchColumn();

            echo json_encode([
                'success' => true,
                'cloud' => $cloudData ?: ['success' => false, 'error' => $err ?: 'تعذر الاتصال بالسحابة'],
                'local' => [
                    'total_schools' => $localTotal,
                    'approved_schools' => $localApproved,
                    'with_phone' => $localWithPhone,
                    'whatsapp_sent_count' => $localWaSent
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'auto_sync_delta') {
            $res = DaleelSyncService::autoSyncDelta();
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'trigger_cloud_push') {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            // 1. Collect all local approved schools
            $stmt = $pdo->query("SELECT name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
                                       stages, state, city, district, address, google_map_url, phone, whatsapp, whatsapp_sent, whatsapp_sent_at,
                                       email, website, source_url, source_type, facebook, youtube, tiktok, description
                                FROM daleel_schools 
                                WHERE status = 'approved' AND deleted_at IS NULL");
            $localSchools = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Collect local WhatsApp sent records
            $waStmt = $pdo->query("SELECT name, slug, phone, whatsapp_sent_at FROM daleel_schools WHERE whatsapp_sent = 1 AND deleted_at IS NULL");
            $waUpdates = $waStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Collect local messages if table exists
            $localMessages = [];
            try {
                $msgStmt = $pdo->query("SELECT m.*, s.name as school_name FROM daleel_whatsapp_messages m LEFT JOIN daleel_schools s ON m.school_id = s.id ORDER BY m.id DESC LIMIT 500");
                $localMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}

            // 4. Send via cURL to https://daleel.schoplus.sd/api_sync.php
            $syncUrl = 'https://daleel.schoplus.sd/api_sync.php?action=push&key=schoplus_daleel_deploy_sec_4412f';
            $payload = [
                'action' => 'push',
                'key' => 'schoplus_daleel_deploy_sec_4412f',
                'schools' => $localSchools,
                'whatsapp_updates' => $waUpdates,
                'messages' => $localMessages
            ];

            $ch = curl_init($syncUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Sync-Key: schoplus_daleel_deploy_sec_4412f'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SchoPlus-Daleel-Sync/3.8');
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                echo json_encode(['success' => false, 'error' => 'تعذر الاتصال بالسحابة: ' . $err], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $decoded = json_decode((string)$response, true);
            if (is_array($decoded)) {
                echo json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'استجابة غير صالحة من السحابة: ' . substr($response, 0, 200)], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if ($action === 'trigger_cloud_pull') {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $cloudUrl = 'https://daleel.schoplus.sd/api_sync.php?action=pull&limit=3000&key=schoplus_daleel_deploy_sec_4412f';
            $ch = curl_init($cloudUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SchoPlus-Daleel-Sync/3.8');
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err || !$res) {
                echo json_encode(['success' => false, 'error' => 'تعذر جلب البيانات من السحابة: ' . $err], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $data = json_decode($res, true);
            if (empty($data['success']) || empty($data['schools'])) {
                echo json_encode(['success' => false, 'error' => 'لم يتم استلام أي مدارس من السحابة.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $imported = 0;
            $updated = 0;
            $chkStmt = $pdo->prepare("SELECT id FROM daleel_schools WHERE name = ? OR slug = ? LIMIT 1");
            $rndPass = password_hash('schoplus123', PASSWORD_BCRYPT);

            $insStmt = $pdo->prepare("INSERT INTO daleel_schools (
                name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
                stages, state, city, district, address, google_map_url, phone, whatsapp, whatsapp_sent, whatsapp_sent_at,
                email, website, source_url, source_type, facebook, twitter, youtube, telegram, instagram, tiktok,
                description, status, is_verified, is_schoplus_client, is_featured, rating_avg, reviews_count, views_count,
                owner_name, owner_title, owner_phone, password, created_at, updated_at
            ) VALUES (
                :name, :name_en, :slug, :school_type, :gender_type, :curriculum, 'السودان', :established_year,
                :stages, :state, :city, :district, :address, :google_map_url, :phone, :whatsapp, :whatsapp_sent, :whatsapp_sent_at,
                :email, :website, :source_url, :source_type, :facebook, :twitter, :youtube, :telegram, :instagram, :tiktok,
                :description, 'approved', :is_verified, :is_schoplus_client, :is_featured, :rating_avg, :reviews_count, :views_count,
                :owner_name, 'مدير المدرسة / المفوض', :owner_phone, :password, NOW(), NOW()
            )");

            foreach ($data['schools'] as $s) {
                $name = trim($s['name'] ?? '');
                $slug = trim($s['slug'] ?? '');
                if ($name === '') continue;

                $chkStmt->execute([$name, $slug]);
                $existsId = $chkStmt->fetchColumn();

                if (!$existsId) {
                    $stagesVal = $s['stages'] ?? ['primary'];
                    if (is_array($stagesVal)) $stagesVal = json_encode($stagesVal, JSON_UNESCAPED_UNICODE);

                    $insStmt->execute([
                        ':name' => $name,
                        ':name_en' => $s['name_en'] ?? null,
                        ':slug' => $slug ?: createSlug($name),
                        ':school_type' => $s['school_type'] ?? 'private',
                        ':gender_type' => $s['gender_type'] ?? 'mixed',
                        ':curriculum' => $s['curriculum'] ?? 'sudanese',
                        ':established_year' => $s['established_year'] ?? null,
                        ':stages' => $stagesVal,
                        ':state' => $s['state'] ?? 'الخرطوم',
                        ':city' => $s['city'] ?? 'المركز',
                        ':district' => $s['district'] ?? ($s['city'] ?? ''),
                        ':address' => $s['address'] ?? '',
                        ':google_map_url' => $s['google_map_url'] ?? null,
                        ':phone' => $s['phone'] ?? null,
                        ':whatsapp' => $s['whatsapp'] ?? ($s['phone'] ?? null),
                        ':whatsapp_sent' => !empty($s['whatsapp_sent']) ? 1 : 0,
                        ':whatsapp_sent_at' => $s['whatsapp_sent_at'] ?? null,
                        ':email' => $s['email'] ?? null,
                        ':website' => $s['website'] ?? null,
                        ':source_url' => $s['source_url'] ?? null,
                        ':source_type' => $s['source_type'] ?? 'google_maps',
                        ':facebook' => $s['facebook'] ?? null,
                        ':twitter' => $s['twitter'] ?? null,
                        ':youtube' => $s['youtube'] ?? null,
                        ':telegram' => $s['telegram'] ?? null,
                        ':instagram' => $s['instagram'] ?? null,
                        ':tiktok' => $s['tiktok'] ?? null,
                        ':description' => $s['description'] ?? null,
                        ':is_verified' => !empty($s['is_verified']) ? 1 : 0,
                        ':is_schoplus_client' => !empty($s['is_schoplus_client']) ? 1 : 0,
                        ':is_featured' => !empty($s['is_featured']) ? 1 : 0,
                        ':rating_avg' => $s['rating_avg'] ?? 5.00,
                        ':reviews_count' => $s['reviews_count'] ?? 0,
                        ':views_count' => $s['views_count'] ?? 1,
                        ':owner_name' => 'إدارة ' . $name,
                        ':owner_phone' => $s['phone'] ?? null,
                        ':password' => $rndPass
                    ]);
                    $imported++;
                } else {
                    if (!empty($s['whatsapp_sent']) || !empty($s['phone'])) {
                        $updParts = [];
                        $updParams = [];
                        if (!empty($s['phone'])) {
                            $updParts[] = "phone = COALESCE(phone, ?)";
                            $updParams[] = $s['phone'];
                        }
                        if (!empty($s['whatsapp_sent'])) {
                            $updParts[] = "whatsapp_sent = 1, whatsapp_sent_at = COALESCE(whatsapp_sent_at, ?)";
                            $updParams[] = $s['whatsapp_sent_at'] ?? date('Y-m-d H:i:s');
                        }
                        if (!empty($updParts)) {
                            $updParams[] = $existsId;
                            $pdo->prepare("UPDATE daleel_schools SET " . implode(', ', $updParts) . " WHERE id = ?")->execute($updParams);
                            $updated++;
                        }
                    }
                }
            }

            recalculateDaleelRanks($pdo);
            if (file_exists(__DIR__ . '/export_master_seed.php')) {
                require_once __DIR__ . '/export_master_seed.php';
                exportMasterSeed($pdo);
            }

            $localTotalNow = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND deleted_at IS NULL")->fetchColumn();

            echo json_encode([
                'success' => true,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'total_local_now' => $localTotalNow,
                'message' => "تم استيراد ومطابقة ({$imported}) مدرسة جديدة في القاعدة المحلية من السحابة بنجاح! الإجمالي المحلي الآن: {$localTotalNow} مؤسسة."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'run_batch_researcher') {
            @set_time_limit(180);
            @ini_set('memory_limit', '512M');

            $scopeState = trim($_POST['state'] ?? '');
            $scopeType = trim($_POST['school_type'] ?? 'all');
            $targetCount = (int)($_POST['target_count'] ?? 20);
            $batchText = trim($_POST['batch_text'] ?? '');

            $logs = [];

            if (!empty($batchText)) {
                $logs[] = "[" . date('H:i:s') . "] ▶ بدء تشغيل محرك الاستكشاف والمسح الآلي المدمج للدليل...";
                $lines = preg_split('/\r\n|\r|\n/', $batchText);
                $cleanLines = array_values(array_filter(array_map('trim', $lines)));
                $logs[] = "[" . date('H:i:s') . "] 📋 استلام قائمة مجمعة تضم " . count($cleanLines) . " مدرسة للفهرسة والاستكشاف والتدقيق...";

                $createdCount = 0;
                $alreadyExistsCount = 0;
                $errorsCount = 0;

                foreach ($cleanLines as $idx => $line) {
                    if (mb_strlen($line, 'UTF-8') < 2) continue;
                    try {
                        $phoneInLine = null;
                        if (preg_match('/(\+?249[0-9]{9}|0[0-9]{9})/', $line, $mPhone)) {
                            $phoneInLine = $mPhone[1];
                            $cleanSchoolName = trim(preg_replace('/(\+?249[0-9]{9}|0[0-9]{9})/', '', $line));
                            $cleanSchoolName = trim($cleanSchoolName, " ,-—:|");
                        } else {
                            $cleanSchoolName = $line;
                        }

                        $forcedType = ($scopeType !== 'all') ? $scopeType : null;
                        $res = SchoolAutoResearcher::autoDiscoverAndRegister($pdo, $cleanSchoolName, !empty($scopeState) ? $scopeState : null, null, $forcedType, $phoneInLine, $phoneInLine);
                        if (!empty($res['created'])) {
                            $createdCount++;
                            $sch = $res['school'];
                            $srcMeta = getSchoolVerificationSourceUrl($sch);
                            $logs[] = "[" . date('H:i:s') . "] ✨ [مؤسسة جديدة مؤكدة #{$createdCount}] استكشاف وفهرسة: \"{$sch['name']}\" ({$sch['state']} — {$sch['city']}) بنجاح. [SOURCE_LINK|{$srcMeta['url']}|{$srcMeta['label']} ↗] [SOURCE_LINK|{$sch['url']}|معاينة بالدليل ↗]";
                        } elseif (!empty($res['already_exists'])) {
                            $alreadyExistsCount++;
                            $ex = $res['school'];
                            $exPhone = !empty($ex['phone']) ? $ex['phone'] : (!empty($ex['whatsapp']) ? $ex['whatsapp'] : 'غير مسجل');
                            $exAddr = !empty($ex['address']) ? $ex['address'] : ($ex['state'] . ' — ' . $ex['city']);
                            $srcMeta = getSchoolVerificationSourceUrl($ex);
                            $schoolUrl = '/school/' . urlencode($ex['slug'] ?? '');
                            $logs[] = "[" . date('H:i:s') . "] 🛡️ [مطابقة وموجودة مسبقاً] فحص: \"{$line}\" ← مطابقة مدرسة قائمة: \"{$ex['name']}\" ({$ex['state']} — {$ex['city']}) | الهاتف: {$exPhone} | العنوان: {$exAddr} — [تم التجاهل لمنع التكرار Deduplicated] [SOURCE_LINK|{$schoolUrl}|عرض بالدليل ↗] [SOURCE_LINK|{$srcMeta['url']}|{$srcMeta['label']} ↗]";
                        } else {
                            $errorsCount++;
                            $logs[] = "[" . date('H:i:s') . "] ⚠️ تعذر إدراج: {$line} (" . ($res['error'] ?? 'خطأ غير معروف') . ")";
                        }
                    } catch (Throwable $e) {
                        $errorsCount++;
                        $logs[] = "[" . date('H:i:s') . "] ❌ خطأ في معالجة ({$line}): " . $e->getMessage();
                    }
                }

                if ($createdCount > 0) {
                    require_once __DIR__ . '/export_master_seed.php';
                    exportMasterSeed($pdo);
                    $logs[] = "[" . date('H:i:s') . "] 💾 تم تحديث وحفظ ملف التصدير المركزي Master Seed تلقائياً.";
                }

                $totalNow = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
                $logs[] = "[" . date('H:i:s') . "] 🏁 اكتملت دورة المحرك بنجاح! مضاف جديد: {$createdCount} | مطابق ومتجاهل: {$alreadyExistsCount} | الإجمالي العام: " . number_format($totalNow) . " مؤسسة.";

                echo json_encode([
                    'success' => true,
                    'created_count' => $createdCount,
                    'already_exists_count' => $alreadyExistsCount,
                    'errors_count' => $errorsCount,
                    'total_schools' => $totalNow,
                    'logs' => $logs,
                    'message' => "تم استكشاف {$createdCount} مدرسة جديدة، ومطابقة وتجاهل {$alreadyExistsCount} مدرسة قائمة لمنع التكرار ✅"
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                // Autonomous exploration based on Target Count and Scope Type
                $res = SchoolAutoResearcher::runAutonomousExploration($pdo, !empty($scopeState) ? $scopeState : null, $targetCount, $logs, $scopeType);
                echo json_encode([
                    'success' => true,
                    'created_count' => $res['created_count'],
                    'already_exists_count' => $res['skipped_count'],
                    'scanned_count' => $res['scanned_count'],
                    'errors_count' => 0,
                    'total_schools' => $res['total_now'],
                    'logs' => $res['logs'],
                    'message' => "تم استكشاف {$res['created_count']} مؤسسة جديدة، ومطابقة وتجاهل {$res['skipped_count']} مؤسسة قائمة لمنع التكرار ✅"
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
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
$waSentCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND whatsapp_sent = 1")->fetchColumn();
$waUnsentCount = max(0, $totalCount - $waSentCount);
$waPercent = $totalCount > 0 ? round(($waSentCount / $totalCount) * 100, 1) : 0;
$waValidCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND whatsapp_status = 'valid'")->fetchColumn();
$waInvalidCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND whatsapp_status = 'invalid'")->fetchColumn();
$waPendingCheckCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '') AND (whatsapp_status IS NULL OR whatsapp_status = 'unverified')")->fetchColumn();
$waSchoolsWithPhone = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved' AND (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '')")->fetchColumn();
$isLocalStation = DaleelWhatsAppService::isLocalStation();

// Filters for Schools Table
$filterCountry = trim($_GET['country'] ?? '');
$filterState = trim($_GET['state'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterWaStatus = trim($_GET['wa_status'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');
$filterSort = trim($_GET['sort'] ?? 'catalog_rank');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$hasActiveFilters = ($filterSearch !== '' || $filterCountry !== '' || $filterState !== '' || $filterType !== '' || $filterWaStatus !== '' || $filterStatus !== '' || ($filterSort !== '' && $filterSort !== 'catalog_rank'));

if (!function_exists('buildAdminFilterUrl')) {
    function buildAdminFilterUrl(array $overrides = []): string {
        $params = $_GET;
        $params['tab'] = 'schools';
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        unset($params['page']);
        return 'admin.php?' . http_build_query($params);
    }
}

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

// Educational Category Filter: الحكومية، الخاصة، رياض الأطفال والحضانات، العالمية، الجاليات
if ($filterType === 'government') {
    $whereClauses[] = "(school_type = 'government' OR name LIKE '%حكومية%' OR name LIKE '%حكومي%')";
} elseif ($filterType === 'private') {
    $whereClauses[] = "(school_type = 'private' AND stages != '[\"kindergarten\"]' AND name NOT LIKE '%روضة%' AND name NOT LIKE '%حضانة%')";
} elseif ($filterType === 'kindergarten') {
    $whereClauses[] = "(school_type = 'kindergarten' OR stages LIKE '%kindergarten%' OR name LIKE '%روضة%' OR name LIKE '%حضانة%' OR name LIKE '%براعم%')";
} elseif ($filterType === 'international') {
    $whereClauses[] = "school_type = 'international'";
} elseif ($filterType === 'community') {
    $whereClauses[] = "(school_type = 'community' OR country != 'السودان')";
}

// WhatsApp Outreach & Verification Status Filter
if ($filterWaStatus === 'sent') {
    $whereClauses[] = "whatsapp_sent = 1";
} elseif ($filterWaStatus === 'unsent') {
    $whereClauses[] = "(whatsapp_sent = 0 OR whatsapp_sent IS NULL)";
} elseif ($filterWaStatus === 'valid') {
    $whereClauses[] = "whatsapp_status = 'valid'";
} elseif ($filterWaStatus === 'invalid') {
    $whereClauses[] = "whatsapp_status = 'invalid'";
} elseif ($filterWaStatus === 'unverified_phone') {
    $whereClauses[] = "(whatsapp_status IS NULL OR whatsapp_status = 'unverified') AND (phone IS NOT NULL AND phone != '')";
} elseif ($filterWaStatus === 'has_phone') {
    $whereClauses[] = "(phone IS NOT NULL AND phone != '')";
} elseif ($filterWaStatus === 'no_phone') {
    $whereClauses[] = "(phone IS NULL OR phone = '')";
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
$catalogOrderSql = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, (is_verified = 1 OR is_schoplus_client = 1) DESC, is_featured DESC, rating_avg DESC, id ASC";

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

// Fetch filtered rows with country, phone, whatsapp, whatsapp_sent, whatsapp_sent_at, school_type
$querySql = "SELECT id, name, name_en, slug, school_type, country, state, city, district, address, phone, whatsapp, whatsapp_sent, whatsapp_sent_at, stages, is_verified, is_schoplus_client, is_historic, directory_rank, state_rank, established_year, created_at 
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

$activeTab = $_GET['tab'] ?? 'schools';
// ─── MANAGE SCHOOL FULL PAGE LOGIC ───────────────────────────────────────
$manageSchoolId = (int)($_GET['id'] ?? 0);
$managedSchool = null;
$manageSaveSuccess = '';
$manageSaveError = '';

if ($activeTab === 'manage_school') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_managed_school'])) {
        $name = trim($_POST['name'] ?? '');
        $nameEn = trim($_POST['name_en'] ?? '');
        $schoolType = trim($_POST['school_type'] ?? 'private');
        $genderType = trim($_POST['gender_type'] ?? 'mixed');
        $curriculum = trim($_POST['curriculum'] ?? 'sudanese');
        $country = trim($_POST['country'] ?? 'السودان');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $googleMapUrl = trim($_POST['google_map_url'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $establishedYear = trim($_POST['established_year'] ?? '');
        $status = trim($_POST['status'] ?? 'approved');
        $approxFees = trim($_POST['approx_fees'] ?? '');
        $workingHours = trim($_POST['working_hours'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $vision = trim($_POST['vision'] ?? '');
        $mission = trim($_POST['mission'] ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $ownerTitle = trim($_POST['owner_title'] ?? '');
        $ownerPhone = trim($_POST['owner_phone'] ?? '');
        $ownerEmail = trim($_POST['owner_email'] ?? '');

        $isVerified = !empty($_POST['is_verified']) ? 1 : 0;
        $isSchoplus = !empty($_POST['is_schoplus_client']) ? 1 : 0;
        $isHistoric = !empty($_POST['is_historic']) ? 1 : 0;
        $dirRank = !empty($_POST['directory_rank']) ? (int)$_POST['directory_rank'] : null;
        $stateRank = !empty($_POST['state_rank']) ? (int)$_POST['state_rank'] : null;

        // Stages array
        $stagesArr = $_POST['stages_arr'] ?? [];
        if (is_array($stagesArr)) {
            $stagesStr = implode(', ', array_filter(array_map('trim', $stagesArr)));
        } else {
            $stagesStr = trim((string)($_POST['stages'] ?? ''));
        }

        // Facilities array
        $facilitiesArr = $_POST['facilities'] ?? [];
        $facilitiesStr = is_array($facilitiesArr) ? implode(',', $facilitiesArr) : '';

        // Reset password if provided
        $newPassword = trim($_POST['new_password'] ?? '');

        if ($name === '' || $phone === '') {
            $manageSaveError = 'يرجى إدخال اسم المدرسة ورقم الهاتف الأساسي.';
        } else {
            try {
                if ($manageSchoolId > 0) {
                    $params = [
                        $name, $nameEn ?: null, $schoolType, $genderType, $curriculum,
                        $country, $state, $city, $district ?: null, $address ?: null, $googleMapUrl ?: null,
                        $phone, $whatsapp ?: null, $email ?: null, $website ?: null,
                        $establishedYear ?: null, $stagesStr ?: null, $facilitiesStr ?: null,
                        $approxFees ?: null, $workingHours ?: null, $description ?: null, $vision ?: null, $mission ?: null,
                        $isVerified, $isSchoplus, $isHistoric, $dirRank, $stateRank, $status,
                        $ownerName ?: null, $ownerTitle ?: null, $ownerPhone ?: null, $ownerEmail ?: null,
                        $manageSchoolId
                    ];

                    $sql = "UPDATE daleel_schools SET 
                        name = ?, name_en = ?, school_type = ?, gender_type = ?, curriculum = ?,
                        country = ?, state = ?, city = ?, district = ?, address = ?, google_map_url = ?,
                        phone = ?, whatsapp = ?, email = ?, website = ?,
                        established_year = ?, stages = ?, facilities = ?,
                        approx_fees = ?, working_hours = ?, description = ?, vision = ?, mission = ?,
                        is_verified = ?, is_schoplus_client = ?, is_historic = ?, directory_rank = ?, state_rank = ?, status = ?,
                        owner_name = ?, owner_title = ?, owner_phone = ?, owner_email = ?,
                        updated_at = NOW()
                        WHERE id = ?";

                    $pdo->prepare($sql)->execute($params);

                    if (!empty($newPassword)) {
                        $pwdHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $pdo->prepare("UPDATE daleel_schools SET password = ? WHERE id = ?")->execute([$pwdHash, $manageSchoolId]);
                    }
                } else {
                    $slug = createSlug($name);
                    $chkSlug = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE slug = ?");
                    $chkSlug->execute([$slug]);
                    if ($chkSlug->fetchColumn() > 0) {
                        $slug .= '-' . rand(100, 999);
                    }
                    $rndPass = !empty($newPassword) ? password_hash($newPassword, PASSWORD_BCRYPT) : password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);

                    $sql = "INSERT INTO daleel_schools (
                        name, name_en, slug, school_type, gender_type, curriculum,
                        country, state, city, district, address, google_map_url,
                        phone, whatsapp, email, website,
                        established_year, stages, facilities,
                        approx_fees, working_hours, description, vision, mission,
                        is_verified, is_schoplus_client, is_historic, directory_rank, state_rank, status,
                        owner_name, owner_title, owner_phone, owner_email,
                        password, rating_avg, reviews_count, views_count,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, 5.00, 0, 1,
                        NOW(), NOW()
                    )";
                    $insParams = [
                        $name, $nameEn ?: null, $slug, $schoolType, $genderType, $curriculum,
                        $country, $state, $city, $district ?: null, $address ?: null, $googleMapUrl ?: null,
                        $phone, $whatsapp ?: null, $email ?: null, $website ?: null,
                        $establishedYear ?: null, $stagesStr ?: null, $facilitiesStr ?: null,
                        $approxFees ?: null, $workingHours ?: null, $description ?: null, $vision ?: null, $mission ?: null,
                        $isVerified, $isSchoplus, $isHistoric, $dirRank, $stateRank, $status,
                        $ownerName ?: ('إدارة ' . $name), $ownerTitle ?: 'مدير المدرسة / المفوض', $ownerPhone ?: $phone, $ownerEmail ?: null,
                        $rndPass
                    ];
                    $pdo->prepare($sql)->execute($insParams);
                    $manageSchoolId = (int)$pdo->lastInsertId();
                }

                recalculateDaleelRanks($pdo);

                // مزامنة فورية تلقائية مع السحابة بدون أزرار
                if (class_exists('DaleelSyncService') && DaleelSyncService::isLocal()) {
                    DaleelSyncService::pushSchoolById($manageSchoolId);
                    $manageSaveSuccess = 'تم حفظ بيانات المدرسة ومزامنتها لحظياً مع السحابة بنجاح 🟢';
                } else {
                    $manageSaveSuccess = 'تم حفظ وتحديث بيانات المدرسة وترتيبها بنجاح ✅';
                }
            } catch (Throwable $e) {
                $manageSaveError = 'حدث خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        }
    }

    if ($manageSchoolId > 0) {
        $mStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? LIMIT 1");
        $mStmt->execute([$manageSchoolId]);
        $managedSchool = $mStmt->fetch(PDO::FETCH_ASSOC);

        if (!$managedSchool) {
            $activeTab = 'schools';
        }
    } else {
        $managedSchool = [
            'id' => 0,
            'name' => '',
            'name_en' => '',
            'slug' => '',
            'school_type' => 'private',
            'gender_type' => 'mixed',
            'curriculum' => 'sudanese',
            'country' => 'السودان',
            'state' => 'الخرطوم',
            'city' => '',
            'district' => '',
            'address' => '',
            'google_map_url' => '',
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'website' => '',
            'established_year' => '',
            'stages' => '["primary"]',
            'facilities' => '',
            'approx_fees' => '',
            'working_hours' => '',
            'description' => '',
            'vision' => '',
            'mission' => '',
            'owner_name' => '',
            'owner_title' => 'مدير المدرسة / المفوض',
            'owner_phone' => '',
            'owner_email' => '',
            'status' => 'approved',
            'is_verified' => 0,
            'is_schoplus_client' => 0,
            'is_historic' => 0,
            'directory_rank' => null,
            'state_rank' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}


// Fetch inquiries & reviews list for Cloud CRM
$inquiriesList = [];
$reviewsList = [];
try {
    $inqStmt = $pdo->query("SELECT i.*, s.name as school_name, s.slug as school_slug, s.state, s.city 
                            FROM daleel_school_inquiries i 
                            LEFT JOIN daleel_schools s ON i.school_id = s.id 
                            ORDER BY i.id DESC LIMIT 100");
    $inquiriesList = $inqStmt ? $inqStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $inquiriesList = [];
}

try {
    $revStmt = $pdo->query("SELECT r.*, s.name as school_name, s.slug as school_slug, s.state, s.city 
                            FROM daleel_school_reviews r 
                            LEFT JOIN daleel_schools s ON r.school_id = s.id 
                            ORDER BY r.id DESC LIMIT 100");
    $reviewsList = $revStmt ? $revStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $reviewsList = [];
}
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
            --primary-dark: #0B2A52;
            --primary-light: #2563EB;
            --orange: #F5A020;
            --navy: #0B2A52;
            --slate-dark: #09192F;
            --bg-slate: #F8FAFC;
            --card-border: #E2E8F0;
            --text-heading: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-slate);
            color: var(--text-body);
            font-size: 0.8rem;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Bar */
        .admin-topbar {
            background: linear-gradient(135deg, #09192F 0%, #0F2547 50%, #163870 100%);
            border-bottom: 2px solid var(--orange);
            color: #FFFFFF;
            padding: 12px 28px;
            box-shadow: 0 4px 20px rgba(9, 25, 47, 0.25);
            position: sticky;
            top: 0;
            z-index: 1030;
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
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 14px rgba(0,0,0,0.18);
        }


        /* Metrics Cards */
        .metric-card {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
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
        .m-red::before { background-color: #EF4444; }
        .m-cyan::before { background-color: #06B6D4; }

        .metric-val {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        .metric-lbl {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Navigation Tabs */
        .sp-main-tabs-wrap {
            overflow-x: auto;
            margin-bottom: 24px;
            scrollbar-width: none;
        }
        .sp-main-tabs-wrap::-webkit-scrollbar { display: none; }
        .sp-main-tabs {
            display: inline-flex;
            gap: 8px;
            background: #E2E8F0;
            padding: 5px;
            border-radius: 14px;
        }
        .sp-main-tab {
            border: none;
            background: transparent;
            padding: 9px 18px;
            font-size: 0.76rem;
            font-weight: 700;
            color: #475569;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        .sp-main-tab:hover {
            color: var(--navy);
            background: rgba(255, 255, 255, 0.45);
        }
        .sp-main-tab.active {
            background: #FFFFFF;
            color: var(--primary);
            box-shadow: 0 4px 14px rgba(11, 42, 82, 0.08);
        }
        .sp-main-tab.active i {
            color: var(--orange);
        }
        .tab-badge {
            background: #F1F5F9;
            color: var(--text-heading);
            padding: 2px 8px;
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
            overflow: hidden;
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
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="admin.php" class="admin-brand">
                    <div class="admin-brand-icon" style="background: <?= $isLocalStation ? 'linear-gradient(135deg, #10B981, #059669)' : 'linear-gradient(135deg, #F5A020, #D97706)' ?>;">
                        <i class="fas <?= $isLocalStation ? 'fa-desktop text-white' : 'fa-crown text-white' ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.98rem; line-height: 1.2;">
                            <?= $isLocalStation ? 'محطة العمليات المركزية (Master Local HQ)' : 'لوحة التحكم السحابية لدليل المدارس' ?>
                        </div>
                        <div style="font-size: 0.65rem; color: #94A3B8;">
                            <?= $isLocalStation ? '🖥️ التحكم الميداني الشامل والأتمتة وسيرفر الواتساب والمزامنة' : '☁️ SchoPlus Daleel Cloud Production Platform' ?>
                        </div>
                    </div>
                </a>

                <?php if ($isLocalStation): ?>
                    <span class="badge bg-success-subtle text-success border border-success d-none d-lg-inline-flex align-items-center gap-1 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 8px;">
                        <i class="fas fa-bolt text-warning"></i> المحطة المحلية المستقلة (كامل المميزات والتحكم)
                    </span>
                <?php else: ?>
                    <span class="badge bg-primary-subtle text-white border border-primary-subtle d-none d-lg-inline-flex align-items-center gap-1.5 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 8px; background: rgba(37, 99, 235, 0.25) !important;">
                        <span class="spinner-grow spinner-grow-sm text-success" style="width: 7px; height: 7px;"></span> سحابة الإنتاج المباشرة (daleel.schoplus.sd)
                    </span>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isLocalStation): ?>
                    <!-- LOCAL STATION ONLY: LIVE SEAMLESS BACKGROUND SYNC INDICATOR (ZERO BUTTONS) -->
                    <div id="liveSyncStatusPill" class="d-none d-md-inline-flex align-items-center gap-1.5 px-3 py-1 text-white shadow-2xs" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.45); border-radius: 999px; font-size: 0.72rem;" title="المزامنة المتزامنة التلقائية بين السحابة والمحطة المحلية تعمل في الخلفية لحظياً وبدون أي أزرار">
                        <span class="spinner-grow spinner-grow-sm text-success" style="width: 7px; height: 7px;"></span>
                        <span id="liveSyncStatusText">مزامنة تلقائية متزامنة 🟢</span>
                    </div>

                    <!-- LOCAL STATION ONLY: WHATSAPP GATEWAY STATUS WIDGET -->
                    <button type="button" id="btnWaGatewayWidget" onclick="openWaGatewayModal()" class="btn btn-sm text-white d-inline-flex align-items-center gap-2" style="background: rgba(15,92,62,0.85); border: 1px solid rgba(255,255,255,0.25); font-size: 0.72rem; border-radius: 8px;" title="حالة خادم واتساب الدليل ورمز QR">
                        <i class="fab fa-whatsapp text-warning fs-6"></i>
                        <span id="waWidgetStatusText">جاري فحص الواتساب...</span>
                    </button>
                <?php endif; ?>

                <a href="https://daleel.schoplus.sd/" target="_blank" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; border-radius: 8px;" title="فتح الدليل العام المباشر على السحابة في نافذة جديدة">
                    <i class="fas fa-external-link-alt"></i> عرض الدليل الحي
                </a>
                <a href="admin.php?action=logout" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; border-radius: 8px;">
                    <i class="fas fa-sign-out-alt"></i> خروج
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="container-fluid py-4 px-lg-5">

        <!-- METRICS ROW -->
        <div class="row g-3 mb-4">
            <?php if (!$isLocalStation): ?>
                <!-- CLOUD PRODUCTION KPI METRICS -->
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="metric-card m-blue">
                        <div class="metric-lbl"><i class="fas fa-school text-primary"></i> إجمالي المدارس</div>
                        <div class="metric-val metric-val-total-schools"><?= number_format($totalCount) ?></div>
                        <div class="small text-muted" style="font-size: 0.62rem;">مؤسسة بالدليل العام</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=claims" class="text-decoration-none">
                        <div class="metric-card <?= $claimsCount > 0 ? 'm-red' : 'm-orange' ?>">
                            <div class="metric-lbl"><i class="fas fa-file-signature <?= $claimsCount > 0 ? 'text-danger' : 'text-warning' ?>"></i> طلبات التوثيق</div>
                            <div class="metric-val <?= $claimsCount > 0 ? 'text-danger' : 'text-dark' ?>"><?= number_format($claimsCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;"><?= $claimsCount > 0 ? 'بانتظار اعتماد الإدارة' : 'لا توجد طلبات معلقة' ?></div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=inquiries" class="text-decoration-none">
                        <div class="metric-card m-green">
                            <div class="metric-lbl"><i class="fas fa-comments text-success"></i> استفسارات أولياء الأمور</div>
                            <div class="metric-val text-success"><?= number_format($inquiriesCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;">طلب تسجيل واستفسار</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=inquiries#reviews-section" class="text-decoration-none">
                        <div class="metric-card m-orange">
                            <div class="metric-lbl"><i class="fas fa-star text-warning"></i> تقييمات الزوار</div>
                            <div class="metric-val" style="color: var(--orange);"><?= number_format($reviewsCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;">آراء وتجارب مجتمعية</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="metric-card m-purple">
                        <div class="metric-lbl"><i class="fas fa-shield-alt text-purple" style="color: #8B5CF6;"></i> مدارس موثقة معتمدة</div>
                        <div class="metric-val" style="color: #7C3AED;"><?= number_format($verifiedCount) ?></div>
                        <div class="small text-muted" style="font-size: 0.62rem;">تحت إشراف مباشر</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="metric-card m-cyan">
                        <div class="metric-lbl"><i class="fas fa-crown text-info"></i> مدارس SchoPlus الذكية</div>
                        <div class="metric-val text-info"><?= number_format($smartCount) ?></div>
                        <div class="small text-muted" style="font-size: 0.62rem;">تعمل بالنظام السحابي</div>
                    </div>
                </div>
            <?php else: ?>
                <!-- LOCAL MASTER HQ KPI METRICS -->
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="metric-card m-blue">
                        <div class="metric-lbl"><i class="fas fa-school text-primary"></i> إجمالي المدارس</div>
                        <div class="metric-val metric-val-total-schools"><?= number_format($totalCount) ?></div>
                        <div class="small text-muted" style="font-size: 0.62rem;">مؤسسة تعليمية</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=schools&wa_status=sent" class="text-decoration-none">
                        <div class="metric-card m-green">
                            <div class="metric-lbl"><i class="fab fa-whatsapp me-1 text-success"></i>حملة مراسلة الواتساب</div>
                            <div class="metric-val text-success"><?= number_format($waSentCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;"><?= number_format($waUnsentCount) ?> متبقية (<?= $waPercent ?>%)</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=schools&wa_status=valid" class="text-decoration-none">
                        <div class="metric-card m-green">
                            <div class="metric-lbl"><i class="fas fa-check-double text-success"></i>أرقام واتساب مؤكدة</div>
                            <div class="metric-val text-success"><?= number_format($waValidCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;">حسابات مفحوصة ونشطة</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=schools&wa_status=unverified_phone" class="text-decoration-none">
                        <div class="metric-card m-orange">
                            <div class="metric-lbl"><i class="fas fa-clock text-warning"></i>بانتظار الفحص الآلي</div>
                            <div class="metric-val" style="color: var(--orange);"><?= number_format($waPendingCheckCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;">أرقام هواتف غير مفحوصة</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="admin.php?tab=claims" class="text-decoration-none">
                        <div class="metric-card m-orange">
                            <div class="metric-lbl"><i class="fas fa-file-signature text-danger"></i>طلبات التوثيق</div>
                            <div class="metric-val text-danger"><?= number_format($claimsCount) ?></div>
                            <div class="small text-muted" style="font-size: 0.62rem;">بانتظار المراجعة</div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="metric-card m-cyan">
                        <div class="metric-lbl"><i class="fas fa-crown text-info"></i>مدارس SchoPlus الذكية</div>
                        <div class="metric-val text-info"><?= number_format($smartCount) ?></div>
                        <div class="small text-muted" style="font-size: 0.62rem;">تعمل بالنظام السحابي</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- MAIN TABS (Conditional for Cloud vs Local) -->
        <div class="sp-main-tabs-wrap">
            <div class="sp-main-tabs">
                <?php if ($activeTab === 'manage_school' && $managedSchool): ?>
                    <a href="admin.php?tab=manage_school&id=<?= $managedSchool['id'] ?>" class="sp-main-tab active" style="background: #F5A020; color: #0B2A52;">
                        <i class="fas fa-sliders-h"></i> إدارة: <?= e(mb_substr($managedSchool['name'], 0, 22)) ?>
                    </a>
                <?php endif; ?>
                <a href="admin.php?tab=schools" class="sp-main-tab <?= $activeTab === 'schools' ? 'active' : '' ?>">
                    <i class="fas fa-school"></i> المدارس
                    <span class="tab-badge" id="tabSchoolsCountBadge"><?= number_format($totalCount) ?></span>
                </a>
                <a href="admin.php?tab=claims" class="sp-main-tab <?= $activeTab === 'claims' ? 'active' : '' ?>">
                    <i class="fas fa-file-signature"></i> طلبات التوثيق
                    <span class="tab-badge" style="color: <?= $claimsCount > 0 ? '#DC2626' : 'inherit' ?>;"><?= $claimsCount ?></span>
                </a>

                <?php if (!$isLocalStation): ?>
                    <!-- CLOUD PRODUCTION TAB: INQUIRIES & REVIEWS -->
                    <a href="admin.php?tab=inquiries" class="sp-main-tab <?= $activeTab === 'inquiries' ? 'active' : '' ?>">
                        <i class="fas fa-comments text-success"></i> الاستفسارات والتقييمات
                        <span class="tab-badge"><?= ($inquiriesCount + $reviewsCount) ?></span>
                    </a>
                <?php else: ?>
                    <!-- LOCAL MASTER HQ ONLY: AUTONOMOUS WORKER -->
                    <a href="admin.php?tab=researcher" class="sp-main-tab <?= $activeTab === 'researcher' ? 'active' : '' ?>">
                        <i class="fas fa-bolt text-warning"></i> محرك الاستكشاف
                        <span class="tab-badge">آلي</span>
                    </a>
                <?php endif; ?>

                <a href="admin.php?tab=ads" class="sp-main-tab <?= $activeTab === 'ads' ? 'active' : '' ?>">
                    <i class="fas fa-ad"></i> الإعلانات
                    <span class="tab-badge"><?= !empty($adConfig['enabled']) ? 'نشطة' : 'متوقفة' ?></span>
                </a>

                <?php if ($isLocalStation): ?>
                    <!-- LOCAL MASTER HQ ONLY: WHATSAPP MISSION CONTROL & DAEMON -->
                    <a href="admin.php?tab=whatsapp" class="sp-main-tab <?= $activeTab === 'whatsapp' ? 'active' : '' ?>">
                        <i class="fab fa-whatsapp text-success"></i> الواتساب
                        <span class="tab-badge" id="tabWaMainBadge">خادم 8085</span>
                    </a>
                <?php endif; ?>

                <a href="admin.php?tab=sync" class="sp-main-tab <?= $activeTab === 'sync' ? 'active' : '' ?>">
                    <i class="fas <?= $isLocalStation ? 'fa-cloud-upload-alt text-primary' : 'fa-key text-primary' ?>"></i> المزامنة
                    <span class="tab-badge"><?= $isLocalStation ? 'سحب &amp; رفع' : 'API' ?></span>
                </a>

                <a href="admin.php?tab=maintenance" class="sp-main-tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
                    <i class="fas <?= $isLocalStation ? 'fa-tools' : 'fa-shield-alt text-warning' ?>"></i> الصيانة
                    <span class="tab-badge">v<?= e($systemVersion) ?></span>
                </a>
            </div>
        </div>

        <!-- TAB CONTENT: 1. SCHOOLS MANAGEMENT -->
        <?php if ($activeTab === 'manage_school' && $managedSchool): ?>
            <?php 
            $mSchool = $managedSchool;
            $actualDirRank = getSchoolDirectoryRank($pdo, (int)$mSchool['id'], !empty($mSchool['directory_rank']) ? (int)$mSchool['directory_rank'] : null);
            $actualStateRank = getSchoolStateRank($pdo, (int)$mSchool['id'], (string)($mSchool['state'] ?? ''), !empty($mSchool['state_rank']) ? (int)$mSchool['state_rank'] : null);
            $typeMeta = getSchoolTypeLabel($mSchool['school_type'] ?? '', $mSchool['stages'] ?? '', $mSchool['name'] ?? '');
            $selectedFacilities = !empty($mSchool['facilities']) ? explode(',', $mSchool['facilities']) : [];
            $curStagesList = array_map('trim', explode(',', str_replace('،', ',', (string)($mSchool['stages'] ?? ''))));
            ?>
            <div class="admin-card mb-4">
                <!-- Header with Back Button and Quick Links -->
                <div class="admin-card-head d-flex align-items-center justify-content-between flex-wrap gap-3 py-3" style="background: linear-gradient(135deg, #0B2A52, #163870); color: #fff; border-radius: 14px 14px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <a href="admin.php?tab=schools" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1.5 px-3 py-1.5" style="border-radius: 8px; font-size: 0.78rem;">
                            <i class="fas fa-arrow-right"></i> <span>العودة لقائمة المدارس</span>
                        </a>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h4 class="mb-0 fw-bold" style="font-size: 1.15rem; color: #FFF;">
                                    <?= $mSchool['id'] > 0 ? e($mSchool['name']) : '➕ إضافة مدرسة جديدة إلى الدليل' ?>
                                </h4>
                                <?php if ($mSchool['id'] > 0): ?>
                                    <span class="badge rounded-pill" style="background: #F5A020; color: #0B2A52; font-size: 0.7rem;">#<?= $mSchool['id'] ?></span>
                                    <span class="badge" style="background: rgba(255,255,255,0.15); font-size: 0.7rem;">ترتيب الدليل: #<?= $actualDirRank ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success" style="font-size: 0.7rem;">مؤسسة جديدة</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($mSchool['id'] > 0): ?>
                                <div class="small opacity-75 mt-0.5 dir-ltr text-end" style="font-size: 0.74rem; font-family: monospace;">daleel.schoplus.sd/school/<?= e($mSchool['slug']) ?></div>
                            <?php else: ?>
                                <div class="small opacity-75 mt-0.5" style="font-size: 0.74rem;">سيتم حفظ المدرسة في القاعدة المحلية ورفعها فورياً ومباشرة إلى السحابة 🟢</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($mSchool['id'] > 0): ?>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="https://daleel.schoplus.sd/school/<?= urlencode($mSchool['slug']) ?>" target="_blank" class="btn btn-sm btn-light fw-bold text-dark px-3 py-1.5" style="border-radius: 8px; font-size: 0.78rem;">
                            <i class="fas fa-external-link-alt text-primary me-1"></i> فتح صفحة الدليل بالسحابة
                        </a>
                        <button type="button" class="btn btn-sm btn-danger px-3 py-1.5" onclick="deleteSchool(<?= $mSchool['id'] ?>, '<?= e(addslashes($mSchool['name'])) ?>')" style="border-radius: 8px; font-size: 0.78rem;">
                            <i class="fas fa-trash-alt me-1"></i> حذف المدرسة
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Alert Messages -->
                <div class="p-3 pb-0">
                    <?php if ($manageSaveSuccess): ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-3 py-2 px-3 small d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle fs-5"></i>
                            <div><strong>تم بنجاح!</strong> <?= e($manageSaveSuccess) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($manageSaveError): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 px-3 small d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-triangle fs-5"></i>
                            <div><strong>تنبيه:</strong> <?= e($manageSaveError) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Complete School Management Form -->
                <form method="POST" action="admin.php?tab=manage_school&id=<?= $mSchool['id'] ?>" class="p-3 p-md-4">
                    <input type="hidden" name="save_managed_school" value="1">

                    <div class="row g-4">
                        <!-- Left Column: Core Data & Location -->
                        <div class="col-lg-8">
                            <!-- 1. Basic Identification -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-school text-primary"></i> 1. البيانات الأساسية والهوية
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold text-dark">اسم المدرسة باللغة العربية *</label>
                                        <input type="text" class="form-control" name="name" value="<?= e($mSchool['name']) ?>" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold text-dark">اسم المدرسة بالإنجليزية (EN)</label>
                                        <input type="text" class="form-control dir-ltr" name="name_en" value="<?= e($mSchool['name_en'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">نوع المدرسة</label>
                                        <select class="form-select" name="school_type">
                                            <option value="private" <?= ($mSchool['school_type'] ?? '') === 'private' ? 'selected' : '' ?>>مدرسة خاصة / أهلية</option>
                                            <option value="government" <?= ($mSchool['school_type'] ?? '') === 'government' ? 'selected' : '' ?>>مدرسة حكومية</option>
                                            <option value="international" <?= ($mSchool['school_type'] ?? '') === 'international' ? 'selected' : '' ?>>مدرسة دولية / عالمية</option>
                                            <option value="kindergarten" <?= ($mSchool['school_type'] ?? '') === 'kindergarten' ? 'selected' : '' ?>>روضة / رياض أطفال</option>
                                            <option value="community" <?= ($mSchool['school_type'] ?? '') === 'community' ? 'selected' : '' ?>>مدرسة جالية سودانية</option>
                                            <option value="language" <?= ($mSchool['school_type'] ?? '') === 'language' ? 'selected' : '' ?>>مدرسة لغات</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">نوع الطلاب / الجنس</label>
                                        <select class="form-select" name="gender_type">
                                            <option value="mixed" <?= ($mSchool['gender_type'] ?? '') === 'mixed' ? 'selected' : '' ?>>مختلط (بنين وبنات)</option>
                                            <option value="boys" <?= ($mSchool['gender_type'] ?? '') === 'boys' ? 'selected' : '' ?>>بنين فقط</option>
                                            <option value="girls" <?= ($mSchool['gender_type'] ?? '') === 'girls' ? 'selected' : '' ?>>بنات فقط</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">المنهج الدراسي</label>
                                        <select class="form-select" name="curriculum">
                                            <option value="sudanese" <?= ($mSchool['curriculum'] ?? '') === 'sudanese' ? 'selected' : '' ?>>المنهج السوداني القومي</option>
                                            <option value="british" <?= ($mSchool['curriculum'] ?? '') === 'british' ? 'selected' : '' ?>>المنهج البريطاني (IGCSE)</option>
                                            <option value="american" <?= ($mSchool['curriculum'] ?? '') === 'american' ? 'selected' : '' ?>>المنهج الأمريكي</option>
                                            <option value="arabic" <?= ($mSchool['curriculum'] ?? '') === 'arabic' ? 'selected' : '' ?>>منهج عربي دولي</option>
                                            <option value="mixed" <?= ($mSchool['curriculum'] ?? '') === 'mixed' ? 'selected' : '' ?>>منهج مزدوج</option>
                                        </select>
                                    </div>

                                    <div class="col-md-8">
                                        <label class="form-label small fw-bold text-dark">المراحل التعليمية المتوفرة</label>
                                        <div class="d-flex flex-wrap gap-3 p-2.5 rounded-3 border bg-light">
                                            <?php foreach (['حضانه', 'روضه', 'ابتدائي', 'متوسط', 'ثانوي'] as $stg): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="stages_arr[]" value="<?= $stg ?>" id="stg_<?= $stg ?>" <?= in_array($stg, $curStagesList, true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label small fw-bold" for="stg_<?= $stg ?>"><?= $stg ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">سنة التأسيس</label>
                                        <input type="text" class="form-control text-center" name="established_year" value="<?= e($mSchool['established_year'] ?? '') ?>" placeholder="مثال: 1998">
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Location & Contact -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-map-marked-alt text-success"></i> 2. الموقع الجغرافي ومعلومات التواصل
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">الدولة</label>
                                        <select class="form-select" name="country">
                                            <?php foreach (SudanGeography::countries() as $cName): ?>
                                                <option value="<?= e($cName) ?>" <?= ($mSchool['country'] ?? 'السودان') === $cName ? 'selected' : '' ?>><?= e($cName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">الولاية *</label>
                                        <input type="text" class="form-control" name="state" value="<?= e($mSchool['state']) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">المدينة *</label>
                                        <input type="text" class="form-control" name="city" value="<?= e($mSchool['city']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">الحي / المنطقة</label>
                                        <input type="text" class="form-control" name="district" value="<?= e($mSchool['district'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">العنوان بالتفصيل</label>
                                        <input type="text" class="form-control" name="address" value="<?= e($mSchool['address'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">رقم الهاتف الأساسي *</label>
                                        <input type="text" class="form-control dir-ltr fw-bold" name="phone" value="<?= e($mSchool['phone'] ?? '') ?>" required style="font-family: monospace;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">رقم الواتساب الرسمي</label>
                                        <input type="text" class="form-control dir-ltr fw-bold" name="whatsapp" value="<?= e($mSchool['whatsapp'] ?? '') ?>" placeholder="اتركه فارغاً إن كان مطابقاً للهاتف" style="font-family: monospace;">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">البريد الإلكتروني الرسمي</label>
                                        <input type="email" class="form-control dir-ltr" name="email" value="<?= e($mSchool['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">الموقع الإلكتروني</label>
                                        <input type="url" class="form-control dir-ltr" name="website" value="<?= e($mSchool['website'] ?? '') ?>" placeholder="https://...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">رابط خرائط جوجل (Google Maps)</label>
                                        <input type="url" class="form-control dir-ltr" name="google_map_url" value="<?= e($mSchool['google_map_url'] ?? '') ?>" placeholder="https://maps.google.com/...">
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Facilities & About Text -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-list-check text-info"></i> 3. المرافق والنبذة والرسالة
                                </h6>
                                
                                <label class="form-label small fw-bold text-dark mb-2">المرافق المتوفرة بالمدرسة</label>
                                <div class="row g-2 mb-4 p-3 rounded-3 border bg-light">
                                    <?php foreach (SudanGeography::availableFacilities() as $fKey => $fData): ?>
                                        <div class="col-6 col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="facilities[]" value="<?= $fKey ?>" id="fac_<?= $fKey ?>" <?= in_array($fKey, $selectedFacilities, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="fac_<?= $fKey ?>">
                                                    <i class="<?= e($fData['icon']) ?> text-secondary me-1"></i> <?= e($fData['label']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">متوسط الرسوم السنوية التقريبية</label>
                                        <input type="text" class="form-control" name="approx_fees" value="<?= e($mSchool['approx_fees'] ?? '') ?>" placeholder="مثال: 500,000 جنيه سوداني">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">أوقات وساعات الدوام</label>
                                        <input type="text" class="form-control" name="working_hours" value="<?= e($mSchool['working_hours'] ?? '') ?>" placeholder="الأحد - الخميس (7:30 ص - 2:30 م)">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-dark">نبذة تعريفية عن المدرسة</label>
                                        <textarea class="form-control" name="description" rows="3"><?= e($mSchool['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">رؤية المدرسة</label>
                                        <textarea class="form-control" name="vision" rows="2"><?= e($mSchool['vision'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">رسالة المدرسة</label>
                                        <textarea class="form-control" name="mission" rows="2"><?= e($mSchool['mission'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Badges, Ranks, Status, Account Management -->
                        <div class="col-lg-4">
                            <!-- 1. Badges & Accreditation -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white" style="border-top: 4px solid #1E5FA8 !important;">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-award text-warning"></i> الشارات والاعتماد
                                </h6>
                                
                                <div class="d-flex flex-column gap-3">
                                    <div class="p-3 rounded-3 border bg-light">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="m_is_verified" role="switch" <?= !empty($mSchool['is_verified']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold text-success small" for="m_is_verified">مدرسة موثقة رسمياً ⭐</label>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.68rem;">شارة توثيق خضراء وترقية أولوية الظهور في نتائج البحث.</div>
                                    </div>

                                    <div class="p-3 rounded-3 border bg-light">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" name="is_schoplus_client" value="1" id="m_is_schoplus" role="switch" <?= !empty($mSchool['is_schoplus_client']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold text-warning-emphasis small" for="m_is_schoplus">شريك SchoPlus الذكي 🏆</label>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.68rem;">مدرسة تعتمد نظام SchoPlus السحابي لإدارة المدارس.</div>
                                    </div>

                                    <div class="p-3 rounded-3 border bg-light">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" name="is_historic" value="1" id="m_is_historic" role="switch" <?= !empty($mSchool['is_historic']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold text-primary small" for="m_is_historic">صرح تعليمي عريق 🏛️</label>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.68rem;">أولوية تصدر قصوى في صدارة الدليل الوطني للمدارس العريقة.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Status & Ranking -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white" style="border-top: 4px solid #F5A020 !important;">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-sort-amount-down text-primary"></i> النشر والترتيب
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">حالة المدرسة بالدليل</label>
                                    <select class="form-select" name="status">
                                        <option value="approved" <?= ($mSchool['status'] ?? '') === 'approved' ? 'selected' : '' ?>>معتمدة ونشطة بالدليل 🟢</option>
                                        <option value="pending" <?= ($mSchool['status'] ?? '') === 'pending' ? 'selected' : '' ?>>قيد المراجعة والتدقيق 🟡</option>
                                        <option value="suspended" <?= ($mSchool['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>معلقة أو موقوفة إدارياً 🔴</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">
                                        الترتيب العام المخصص
                                        <span class="badge bg-primary text-white ms-1">الفعلي: #<?= $actualDirRank ?></span>
                                    </label>
                                    <input type="number" class="form-control text-center fw-bold" name="directory_rank" value="<?= e($mSchool['directory_rank'] ?? '') ?>" placeholder="تلقائي حسب المعايير">
                                    <div class="text-muted small mt-1" style="font-size: 0.68rem;">اتركه فارغاً لاعتماد الترتيب التلقائي للدليل.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">
                                        الترتيب الولائي المخصص
                                        <span class="badge bg-secondary text-white ms-1">الفعلي: #<?= $actualStateRank ?></span>
                                    </label>
                                    <input type="number" class="form-control text-center fw-bold" name="state_rank" value="<?= e($mSchool['state_rank'] ?? '') ?>" placeholder="تلقائي داخل الولاية">
                                </div>
                            </div>

                            <!-- 3. Principal Account & Credentials -->
                            <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white" style="border-top: 4px solid #10B981 !important;">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-user-lock text-success"></i> حساب المدير والاعتماد
                                </h6>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-dark">اسم المفوض / المدير</label>
                                    <input type="text" class="form-control form-control-sm" name="owner_name" value="<?= e($mSchool['owner_name'] ?? '') ?>" placeholder="اسم المدير">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-dark">الصفة / المنصب</label>
                                    <input type="text" class="form-control form-control-sm" name="owner_title" value="<?= e($mSchool['owner_title'] ?? '') ?>" placeholder="المدير العام / الوكيل">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-dark">هاتف المدير الشخصي</label>
                                    <input type="text" class="form-control form-control-sm dir-ltr" name="owner_phone" value="<?= e($mSchool['owner_phone'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">بريد المدير</label>
                                    <input type="email" class="form-control form-control-sm dir-ltr" name="owner_email" value="<?= e($mSchool['owner_email'] ?? '') ?>">
                                </div>

                                <div class="p-3 rounded-3 bg-light border">
                                    <label class="form-label small fw-bold text-danger mb-1"><i class="fas fa-key me-1"></i> إعادة تعيين كلمة المرور</label>
                                    <input type="text" class="form-control form-control-sm dir-ltr" name="new_password" placeholder="اتركه فارغاً لعدم التغيير">
                                    <div class="text-muted small mt-1" style="font-size: 0.65rem;">إذا كتبت كلمة مرور هنا، سيتم تشفيرها وتعيينها للمدير فوراً.</div>
                                </div>
                            </div>

                            <!-- Action Buttons Fixed Box -->
                            <div class="card border-0 rounded-4 shadow-lg p-3 bg-white sticky-top" style="top: 20px; z-index: 10;">
                                <button type="submit" class="btn btn-primary fw-bold w-100 py-2.5 mb-2 shadow-sm" style="background: #0B2A52; border-color: #0B2A52; font-size: 0.88rem; border-radius: 10px;">
                                    <i class="fas fa-save me-1"></i> <?= $mSchool['id'] > 0 ? 'حفظ كافة التغييرات والترتيب' : 'حفظ وإدراج المدرسة ورفعها للسحابة ➕' ?>
                                </button>
                                <a href="admin.php?tab=schools" class="btn btn-outline-secondary w-100 py-2" style="font-size: 0.8rem; border-radius: 10px;">
                                    إلغاء والعودة للقائمة
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($activeTab === 'schools'): ?>
            <div class="admin-card">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-list-check text-primary"></i> قائمة المدارس
                        <span class="badge bg-light text-dark border ms-2" style="font-size: 0.68rem;"><?= number_format($totalFiltered) ?> مطابقة</span>
                    </h5>
                    
                    <div class="d-flex align-items-center gap-2">
                        <a href="admin.php?tab=manage_school&id=0" class="btn btn-sm btn-success py-1.5 px-3 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" style="font-size: 0.75rem; border-radius: 8px;">
                            <i class="fas fa-plus-circle"></i> إضافة مدرسة جديدة
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary py-1.5 px-3" style="font-size: 0.72rem; border-radius: 8px;" onclick="recalculateRanks()">
                            <i class="fas fa-sync-alt me-1"></i> إعادة ترقيم التسلسل
                        </button>
                    </div>
                </div>

                <!-- Filters Toolbar -->
                <div class="p-3 bg-light border-bottom">
                    <form method="GET" action="admin.php" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="schools">
                        
                        <!-- 1. SEARCH INPUT -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم، الحي، الهاتف، المدينة..." value="<?= e($filterSearch) ?>" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;">
                            </div>
                        </div>

                        <!-- 2. CATEGORY / SCHOOL TYPE FILTER -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-graduation-cap text-muted"></i></span>
                                <select name="type" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="">كافة التصنيفات التعليمية</option>
                                    <option value="private" <?= $filterType === 'private' ? 'selected' : '' ?>>مدارس خاصة (أساس / ثانوي)</option>
                                    <option value="government" <?= $filterType === 'government' ? 'selected' : '' ?>>مدارس حكومية</option>
                                    <option value="kindergarten" <?= $filterType === 'kindergarten' ? 'selected' : '' ?>>رياض أطفال وحضانات</option>
                                    <option value="international" <?= $filterType === 'international' ? 'selected' : '' ?>>مدارس عالمية ودولية</option>
                                    <option value="community" <?= $filterType === 'community' ? 'selected' : '' ?>>مدارس الجاليات والمهجر</option>
                                </select>
                            </div>
                        </div>

                        <!-- 3. COUNTRY FILTER -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-globe text-muted"></i></span>
                                <select name="country" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="">كافة الدول (المهجر والسودان)</option>
                                    <?php foreach ($countriesList as $cntry): ?>
                                        <option value="<?= e($cntry) ?>" <?= $filterCountry === $cntry ? 'selected' : '' ?>><?= getCountryBadge($cntry) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 4. STATE FILTER -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <select name="state" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="">كافة الولايات والمناطق</option>
                                    <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $filterState === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 5. STATUS / VERIFICATION FILTER -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-shield-alt text-muted"></i></span>
                                <select name="status" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="">حالة التوثيق والشارات (الكل)</option>
                                    <option value="verified" <?= $filterStatus === 'verified' ? 'selected' : '' ?>>موثقة فقط (Verified)</option>
                                    <option value="unverified" <?= $filterStatus === 'unverified' ? 'selected' : '' ?>>عامة غير موثقة (Unverified)</option>
                                    <option value="smart" <?= $filterStatus === 'smart' ? 'selected' : '' ?>>مدارس SchoPlus الذكية</option>
                                    <option value="historic" <?= $filterStatus === 'historic' ? 'selected' : '' ?>>صروح وطنية عريقة</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($isLocalStation): ?>
                        <!-- 6. WHATSAPP OUTREACH STATUS FILTER (LOCAL HQ ONLY) -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fab fa-whatsapp text-success"></i></span>
                                <select name="wa_status" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="">حالة مراسلة وتدقيق الواتساب (الكل)</option>
                                    <option value="sent" <?= $filterWaStatus === 'sent' ? 'selected' : '' ?>>✅ تم إرسال الرسالة فقط</option>
                                    <option value="unsent" <?= $filterWaStatus === 'unsent' ? 'selected' : '' ?>>⏳ لم يتم الإرسال بعد (المتبقي)</option>
                                    <option value="valid" <?= $filterWaStatus === 'valid' ? 'selected' : '' ?>>🟢 واتساب نشط ومؤكد (مفحوص)</option>
                                    <option value="invalid" <?= $filterWaStatus === 'invalid' ? 'selected' : '' ?>>🔴 رقم أرضي / غير مفعل بالواتساب</option>
                                    <option value="unverified_phone" <?= $filterWaStatus === 'unverified_phone' ? 'selected' : '' ?>>⚪ هواتف بانتظار الفحص الآلي</option>
                                    <option value="has_phone" <?= $filterWaStatus === 'has_phone' ? 'selected' : '' ?>>📞 يتوفر رقم هاتف / واتساب</option>
                                    <option value="no_phone" <?= $filterWaStatus === 'no_phone' ? 'selected' : '' ?>>⚠️ بدون رقم هاتف</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 7. SORT FILTER -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white" style="border-color: #DDE4ED; width: 36px; justify-content: center;"><i class="fas fa-sort-amount-down text-muted"></i></span>
                                <select name="sort" class="form-select form-select-sm" style="border-color: #DDE4ED; font-size: 0.75rem; height: 34px;" onchange="this.form.submit()">
                                    <option value="catalog_rank" <?= $filterSort === 'catalog_rank' ? 'selected' : '' ?>>الترتيب الفعلي بالدليل (افتراضي)</option>
                                    <option value="id_asc" <?= $filterSort === 'id_asc' ? 'selected' : '' ?>>المعرف #ID تصاعدي</option>
                                    <option value="id_desc" <?= $filterSort === 'id_desc' ? 'selected' : '' ?>>المعرف #ID تنازلي</option>
                                    <option value="name" <?= $filterSort === 'name' ? 'selected' : '' ?>>أبجدياً باسم المدرسة</option>
                                    <option value="views" <?= $filterSort === 'views' ? 'selected' : '' ?>>الأكثر مشاهدة وزيارة</option>
                                    <option value="schoplus" <?= $filterSort === 'schoplus' ? 'selected' : '' ?>>عملاء SchoPlus أولاً</option>
                                </select>
                            </div>
                        </div>

                        <!-- 8. ACTIONS: FILTER & RESET -->
                        <div class="col-xl-3 col-md-6 col-12">
                            <div class="d-flex align-items-center gap-1" style="height: 34px;">
                                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 d-flex align-items-center justify-content-center gap-1" style="height: 34px; font-size: 0.74rem; font-weight: 600; border-radius: 6px;" title="تطبيق الفلترة">
                                    <i class="fas fa-filter"></i> تطبيق الفلترة
                                </button>
                                <?php if ($hasActiveFilters): ?>
                                    <a href="admin.php?tab=schools" class="btn btn-sm btn-outline-danger px-3 d-flex align-items-center justify-content-center gap-1" style="height: 34px; font-size: 0.74rem; font-weight: 600; border-radius: 6px;" title="مسح كافة الفلاتر">
                                        <i class="fas fa-undo"></i> مسح
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-light border px-3 text-muted" style="height: 34px; font-size: 0.74rem; border-radius: 6px;" disabled title="لا توجد فلاتر نشطة">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ACTIVE FILTER CHIPS -->
                        <?php if ($hasActiveFilters): ?>
                            <div class="col-12 mt-2 pt-2 border-top d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted fw-bold" style="font-size: 0.7rem;"><i class="fas fa-sliders-h text-primary me-1"></i> الفلاتر النشطة:</span>
                                
                                <?php if ($filterSearch !== ''): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-search text-muted"></i> "<?= e($filterSearch) ?>"
                                        <a href="<?= e(buildAdminFilterUrl(['search' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterType !== ''): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-graduation-cap text-muted"></i>
                                        <?php 
                                            $typeLabels = [
                                                'private' => 'خاصة',
                                                'government' => 'حكومية',
                                                'kindergarten' => 'رياض أطفال',
                                                'international' => 'دولية وعالمية',
                                                'community' => 'جاليات ومهجر'
                                            ];
                                            echo e($typeLabels[$filterType] ?? $filterType);
                                        ?>
                                        <a href="<?= e(buildAdminFilterUrl(['type' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterCountry !== ''): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-globe text-muted"></i> <?= e($filterCountry) ?>
                                        <a href="<?= e(buildAdminFilterUrl(['country' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterState !== ''): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-map-marker-alt text-muted"></i> <?= e($filterState) ?>
                                        <a href="<?= e(buildAdminFilterUrl(['state' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterStatus !== ''): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-shield-alt text-muted"></i>
                                        <?php
                                            $statusLabels = [
                                                'verified' => 'موثقة فقط',
                                                'unverified' => 'عامة غير موثقة',
                                                'smart' => 'عملاء SchoPlus',
                                                'historic' => 'صروح عريقة'
                                            ];
                                            echo e($statusLabels[$filterStatus] ?? $filterStatus);
                                        ?>
                                        <a href="<?= e(buildAdminFilterUrl(['status' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterWaStatus !== '' && $isLocalStation): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fab fa-whatsapp text-success"></i>
                                        <?php
                                            $waLabels = [
                                                'sent' => 'تم إرسال الواتساب',
                                                'unsent' => 'لم يُرسل بعد',
                                                'valid' => 'واتساب نشط ومؤكد 🟢',
                                                'invalid' => 'أرضي / غير مفعل 🔴',
                                                'unverified_phone' => 'بانتظار الفحص ⚪',
                                                'has_phone' => 'يتوفر هاتف',
                                                'no_phone' => 'بدون هاتف'
                                            ];
                                            echo e($waLabels[$filterWaStatus] ?? $filterWaStatus);
                                        ?>
                                        <a href="<?= e(buildAdminFilterUrl(['wa_status' => ''])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filterSort !== '' && $filterSort !== 'catalog_rank'): ?>
                                    <span class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-sort text-muted"></i>
                                        <?php
                                            $sortLabels = [
                                                'id_asc' => 'المعرف تصاعدي',
                                                'id_desc' => 'المعرف تنازلي',
                                                'name' => 'أبجدياً',
                                                'views' => 'الأكثر مشاهدة',
                                                'schoplus' => 'عملاء SchoPlus'
                                            ];
                                            echo e($sortLabels[$filterSort] ?? $filterSort);
                                        ?>
                                        <a href="<?= e(buildAdminFilterUrl(['sort' => 'catalog_rank'])) ?>" class="text-danger ms-1 text-decoration-none fw-bold" title="إزالة">&times;</a>
                                    </span>
                                <?php endif; ?>

                                <a href="admin.php?tab=schools" class="badge bg-danger-subtle text-danger border border-danger-subtle text-decoration-none py-1 px-2 ms-auto" style="font-size: 0.7rem;">
                                    <i class="fas fa-times-circle me-1"></i> مسح كافة الفلاتر
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Schools Table (Redesigned: Clean, essential info + Manage Button) -->
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 55px;" class="ps-3">#ID</th>
                                <th>اسم المدرسة والمراحل</th>
                                <th>الولاية والمدينة</th>
                                <th style="min-width: 140px;">الهاتف والاتصال</th>
                                <th class="text-center" style="width: 150px;">الترتيب والحالة</th>
                                <th class="text-center pe-3" style="width: 150px;">إدارة المدرسة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schoolsList)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">لم يتم العثور على أي مدارس مطابقة لشروط البحث.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schoolsList as $sch): ?>
                                    <?php 
                                    $actualDirRank = getSchoolDirectoryRank($pdo, (int)$sch['id'], !empty($sch['directory_rank']) ? (int)$sch['directory_rank'] : null);
                                    $typeMeta = getSchoolTypeLabel($sch['school_type'] ?? '', $sch['stages'] ?? '', $sch['name'] ?? '');
                                    $phoneVal = trim((string)($sch['whatsapp'] ?: $sch['phone']));
                                    $waUrl = formatWhatsAppUrl($phoneVal, $sch['name'], $sch['slug'] ?? '', $sch['country'] ?? 'السودان');
                                    ?>
                                    <tr id="row-school-<?= $sch['id'] ?>">
                                        <td class="fw-bold text-muted ps-3">#<?= $sch['id'] ?></td>
                                        
                                        <!-- 1. SCHOOL NAME & BASIC INFO -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <a href="https://daleel.schoplus.sd/school/<?= urlencode($sch['slug']) ?>" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary" style="font-size: 0.84rem;" title="فتح المدرسة على السحابة المباشرة">
                                                            <?= e($sch['name']) ?>
                                                        </a>
                                                        <?php if (!empty($sch['is_verified'])): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1" style="font-size: 0.62rem;" title="مدرسة موثقة رسمياً">
                                                                <i class="fas fa-check-circle"></i> موثقة
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($sch['is_schoplus_client'])): ?>
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0 px-1" style="font-size: 0.62rem;" title="مدرسة ذكية بنظام SchoPlus">
                                                                <i class="fas fa-award"></i> SchoPlus
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($sch['is_historic'])): ?>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0 px-1" style="font-size: 0.62rem;" title="صرح تعليمي عريق">
                                                                <i class="fas fa-landmark"></i> عريقة
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap text-muted" style="font-size: 0.68rem;">
                                                        <span class="badge <?= $typeMeta['badge'] ?> px-1.5 py-0.5" style="font-size: 0.64rem;">
                                                            <i class="fas <?= $typeMeta['icon'] ?> me-1"></i><?= $typeMeta['label'] ?>
                                                        </span>
                                                        <?php if (!empty($sch['stages'])): ?>
                                                            <span><i class="fas fa-layer-group me-1"></i><?= e($sch['stages']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($sch['established_year'])): ?>
                                                            <span><i class="fas fa-calendar-alt me-1"></i>تأسست <?= e($sch['established_year']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 2. LOCATION & COUNTRY -->
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 0.7rem;">
                                                        <?= getCountryBadge($sch['country'] ?? 'السودان') ?>
                                                    </span>
                                                    <span class="fw-bold text-dark" style="font-size: 0.76rem;"><?= e($sch['state']) ?></span>
                                                </div>
                                                <div class="text-muted small" style="font-size: 0.68rem;">
                                                    <?= e($sch['city']) ?>
                                                    <?php if (!empty($sch['district'])): ?> — حي <?= e($sch['district']) ?><?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 3. PHONE & DIRECT CONTACT -->
                                        <td>
                                            <?php if ($phoneVal !== ''): ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <div>
                                                        <a href="tel:<?= e($phoneVal) ?>" class="fw-bold text-dark text-decoration-none dir-ltr hover-primary" style="font-size: 0.76rem; font-family: monospace;">
                                                            <i class="fas fa-phone-alt text-muted me-1" style="font-size: 0.65rem;"></i><?= e($phoneVal) ?>
                                                        </a>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 mt-0.5">
                                                        <?php if ($waUrl): ?>
                                                            <a href="<?= e($waUrl) ?>" target="_blank" class="btn btn-sm btn-outline-success d-inline-flex align-items-center justify-content-center p-0" style="width: 22px; height: 22px; border-radius: 6px;" title="محادثة واتساب">
                                                                <i class="fab fa-whatsapp" style="font-size: 0.68rem;"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="tel:<?= e($phoneVal) ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center p-0" style="width: 22px; height: 22px; border-radius: 6px;" title="اتصال هاتفي">
                                                            <i class="fas fa-phone-alt" style="font-size: 0.62rem;"></i>
                                                        </a>
                                                        <?php if ($isLocalStation): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center p-0" style="width: 22px; height: 22px; border-radius: 6px;" onclick="openCustomSchoolMsg(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>', '<?= e(addslashes($phoneVal)) ?>')" title="مراسلة عبر خادم الواتساب">
                                                                <i class="fas fa-comment-dots" style="font-size: 0.6rem;"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size: 0.66rem;">
                                                    <i class="fas fa-phone-slash me-1"></i>غير متوفر
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 4. RANK & STATUS BADGE -->
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <span class="badge" style="background: linear-gradient(135deg, #0B2A52, #1E5FA8); color: #FFF; font-size: 0.72rem; padding: 4px 8px;" title="الترتيب في الدليل">
                                                    #<?= $actualDirRank ?> في الدليل
                                                </span>
                                                <?php if (($sch['status'] ?? 'approved') === 'approved'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                        نشطة بالدليل
                                                    </span>
                                                <?php elseif (($sch['status'] ?? '') === 'pending'): ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                        قيد المراجعة
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                        معلقة
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- 5. MANAGE SCHOOL (PROMINENT BUTTON) -->
                                        <td class="text-center pe-3">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                                <a href="admin.php?tab=manage_school&id=<?= $sch['id'] ?>" class="btn btn-sm btn-primary fw-bold d-inline-flex align-items-center justify-content-center gap-1.5 px-3 py-1.5 w-100 shadow-sm" style="font-size: 0.76rem; border-radius: 8px; background: #0B2A52; border-color: #0B2A52;" title="فتح صفحة إدارة المدرسة والتحكم الكامل">
                                                    <i class="fas fa-sliders-h"></i> <span>إدارة المدرسة</span>
                                                </a>
                                                <div class="d-flex align-items-center justify-content-center gap-1 mt-1 w-100">
                                                    <a href="https://daleel.schoplus.sd/school/<?= urlencode($sch['slug']) ?>" target="_blank" class="act-btn flex-grow-1 text-center" title="معاينة الصفحة العامة بالدليل السحابي الحي">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <button type="button" class="act-btn danger flex-grow-1 text-center" onclick="deleteSchool(<?= $sch['id'] ?>, '<?= e(addslashes($sch['name'])) ?>')" title="حذف المدرسة">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
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
                    <?php 
                    $pageParams = http_build_query(array_filter([
                        'tab' => 'schools',
                        'search' => $filterSearch,
                        'type' => $filterType,
                        'wa_status' => $filterWaStatus,
                        'country' => $filterCountry,
                        'state' => $filterState,
                        'status' => $filterStatus,
                        'sort' => $filterSort
                    ]));
                    ?>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="small text-muted" style="font-size: 0.7rem;">
                            عرض الصفحة <strong><?= $page ?></strong> من أصل <strong><?= $totalPages ?></strong> (إجمالي <?= number_format($totalFiltered) ?> مدرسة)
                        </div>
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="admin.php?page=<?= $page - 1 ?>&<?= $pageParams ?>">السابقة</a></li>
                                <?php endif; ?>
                                
                                <li class="page-item active"><span class="page-link" style="background: var(--navy); border-color: var(--navy);"><?= $page ?></span></li>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item"><a class="page-link" href="admin.php?page=<?= $page + 1 ?>&<?= $pageParams ?>">التالية</a></li>
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
                        <i class="fas fa-file-signature text-primary"></i> طلبات التوثيق والملكية
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
                                            <a href="https://daleel.schoplus.sd/school/<?= urlencode($clm['school_slug'] ?? '') ?>" target="_blank" class="fw-bold text-dark text-decoration-none">
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

        <!-- TAB CONTENT: CLOUD CRM - INQUIRIES & REVIEWS -->
        <?php elseif ($activeTab === 'inquiries'): ?>
            <!-- 1. PARENT ADMISSION & SCHOOL INQUIRIES -->
            <div class="admin-card mb-4">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-comments text-success"></i> طلبات واستفسارات أولياء الأمور
                        <span class="badge bg-success-subtle text-success border border-success ms-2" style="font-size: 0.68rem;"><?= count($inquiriesList) ?> استفسار</span>
                    </h5>
                    <div class="small text-muted">
                        الاستفسارات الواردة عبر صفحات المدارس في الدليل العام
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المدرسة المستهدفة</th>
                                <th>بيانات ولي الأمر</th>
                                <th>بيانات الطالب والمرحلة</th>
                                <th>نص الاستفسار والترحيل</th>
                                <th>الحالة</th>
                                <th>تاريخ الطلب</th>
                                <th class="text-center pe-3">إجراءات المراسلة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiriesList)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-2 text-muted opacity-50"></i>
                                        <div>لا توجد استفسارات واردة من أولياء الأمور حتى الآن.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inquiriesList as $inq): ?>
                                    <tr id="inquiry-row-<?= $inq['id'] ?>">
                                        <td class="fw-bold ps-3">#<?= $inq['id'] ?></td>
                                        <td>
                                            <a href="https://daleel.schoplus.sd/school/<?= urlencode($inq['school_slug'] ?? '') ?>" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary">
                                                <?= e($inq['school_name'] ?? 'مدرسة #' . $inq['school_id']) ?>
                                            </a>
                                            <div class="small text-muted"><?= e($inq['state'] ?? '') ?> — <?= e($inq['city'] ?? '') ?></div>
                                            <?php if (!empty($inq['branch_name'])): ?>
                                                <span class="badge bg-light text-secondary border" style="font-size: 0.62rem;">فرع: <?= e($inq['branch_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($inq['parent_name'] ?? 'ولي أمر') ?></div>
                                            <?php if (!empty($inq['parent_phone'])): ?>
                                                <a href="tel:<?= e($inq['parent_phone']) ?>" class="text-primary text-decoration-none fw-bold small dir-ltr d-inline-block">
                                                    <i class="fas fa-phone-alt me-1"></i> <?= e($inq['parent_phone']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($inq['parent_email'])): ?>
                                                <div class="text-muted" style="font-size: 0.65rem;"><?= e($inq['parent_email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($inq['student_name'])): ?>
                                                <div class="fw-bold text-dark"><?= e($inq['student_name']) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <?= e($inq['student_stage'] ?? '') ?> <?= !empty($inq['student_grade']) ? '— ' . e($inq['student_grade']) : '' ?>
                                            </div>
                                            <?php if (!empty($inq['previous_school'])): ?>
                                                <div class="text-muted" style="font-size: 0.62rem;">السابقة: <?= e($inq['previous_school']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <div class="text-dark small" style="line-height: 1.4;"><?= nl2br(e($inq['message'] ?? '')) ?></div>
                                            <?php if (!empty($inq['needs_transportation'])): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mt-1" style="font-size: 0.62rem;">
                                                    <i class="fas fa-bus me-1"></i> يطلب ترحيل
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm py-0 px-2" style="font-size: 0.68rem; height: 26px; border-radius: 6px; width: 105px;" onchange="updateInquiryStatus(<?= $inq['id'] ?>, this.value)">
                                                <option value="new" <?= ($inq['status'] ?? 'new') === 'new' ? 'selected' : '' ?>>جديد ⚪</option>
                                                <option value="contacted" <?= ($inq['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>تم التواصل 🟢</option>
                                                <option value="closed" <?= ($inq['status'] ?? '') === 'closed' ? 'selected' : '' ?>>مكتمل / مغلق 🔒</option>
                                            </select>
                                        </td>
                                        <td class="text-muted small text-nowrap" style="font-size: 0.68rem;">
                                            <?= !empty($inq['created_at']) ? date('Y-m-d H:i', strtotime($inq['created_at'])) : '—' ?>
                                        </td>
                                        <td class="text-center pe-3 text-nowrap">
                                            <?php if (!empty($inq['parent_phone'])): ?>
                                                <a href="tel:<?= e($inq['parent_phone']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.68rem; border-radius: 6px;" title="الاتصال الهاتفي المباشر بولي الأمر">
                                                    <i class="fas fa-phone-alt me-1"></i> اتصال
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger p-0 ms-1" style="width: 24px; height: 24px; border-radius: 6px;" onclick="deleteInquiry(<?= $inq['id'] ?>)" title="حذف الاستفسار">
                                                <i class="fas fa-trash-alt" style="font-size: 0.62rem;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. REVIEWS & RATINGS SECTION -->
            <div class="admin-card" id="reviews-section">
                <div class="admin-card-head">
                    <h5 class="admin-card-title">
                        <i class="fas fa-star text-warning"></i> تقييمات وتجارب الزوار وأولياء الأمور
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning ms-2" style="font-size: 0.68rem;"><?= count($reviewsList) ?> تقييم</span>
                    </h5>
                    <div class="small text-muted">
                        إدارة الآراء المجتمعية المنشورة على الدليل
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المدرسة</th>
                                <th>صاحب التقييم</th>
                                <th>التقييم بالنجوم</th>
                                <th>التعليق والتجربة</th>
                                <th>حالة الاعتماد</th>
                                <th>التاريخ</th>
                                <th class="text-center pe-3">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reviewsList)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="far fa-star fa-3x mb-2 text-muted opacity-50"></i>
                                        <div>لا توجد تقييمات مسجلة حتى الآن.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reviewsList as $rev): ?>
                                    <tr id="review-row-<?= $rev['id'] ?>">
                                        <td class="fw-bold ps-3">#<?= $rev['id'] ?></td>
                                        <td>
                                            <a href="https://daleel.schoplus.sd/school/<?= urlencode($rev['school_slug'] ?? '') ?>" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary">
                                                <?= e($rev['school_name'] ?? 'مدرسة #' . $rev['school_id']) ?>
                                            </a>
                                            <div class="small text-muted"><?= e($rev['state'] ?? '') ?> — <?= e($rev['city'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($rev['reviewer_name'] ?? 'زائر') ?></div>
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.62rem;">
                                                <?= e($rev['reviewer_role'] === 'parent' ? 'ولي أمر' : ($rev['reviewer_role'] === 'student' ? 'طالب' : ($rev['reviewer_role'] === 'teacher' ? 'معلم' : 'زائر'))) ?>
                                            </span>
                                            <?php if (!empty($rev['reviewer_phone'])): ?>
                                                <div class="text-muted small dir-ltr"><?= e($rev['reviewer_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-warning text-nowrap" style="font-size: 0.8rem;">
                                                <?php 
                                                $stars = min(5, max(1, (int)($rev['rating'] ?? 5)));
                                                for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $stars ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="fw-bold text-dark ms-1 small">(<?= $stars ?>/5)</span>
                                            </div>
                                        </td>
                                        <td style="max-width: 280px;">
                                            <div class="small text-dark" style="line-height: 1.4;"><?= nl2br(e($rev['comment'] ?? '')) ?></div>
                                            <?php if (!empty($rev['school_reply'])): ?>
                                                <div class="mt-1 p-2 rounded bg-light border-start border-3 border-primary small" style="font-size: 0.68rem;">
                                                    <strong class="text-primary">رد المدرسة:</strong> <?= e($rev['school_reply']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="sp-toggle-pill <?= !empty($rev['is_approved']) ? 'toggle-on-green' : 'toggle-off-gray' ?>" 
                                                  onclick="toggleReviewApproval(<?= $rev['id'] ?>, <?= !empty($rev['is_approved']) ? 0 : 1 ?>, this)"
                                                  title="انقر لتغيير حالة نشر التقييم">
                                                <i class="fas <?= !empty($rev['is_approved']) ? 'fa-check-circle' : 'fa-eye-slash' ?>"></i>
                                                <span><?= !empty($rev['is_approved']) ? 'منشور ومعتمد' : 'قيد المراجعة / مخفي' ?></span>
                                            </span>
                                        </td>
                                        <td class="text-muted small text-nowrap" style="font-size: 0.68rem;">
                                            <?= !empty($rev['created_at']) ? date('Y-m-d', strtotime($rev['created_at'])) : '—' ?>
                                        </td>
                                        <td class="text-center pe-3">
                                            <button type="button" class="btn btn-sm btn-outline-danger p-0" style="width: 24px; height: 24px; border-radius: 6px;" onclick="deleteReview(<?= $rev['id'] ?>)" title="حذف التقييم">
                                                <i class="fas fa-trash-alt" style="font-size: 0.62rem;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB CONTENT: 3. AUTONOMOUS WORKER (DEVELOPED DISCOVERY & RESEARCH ENGINE) -->
        <?php elseif ($activeTab === 'researcher'): ?>
            <?php if (!$isLocalStation): ?>
                <div class="row">
                    <div class="col-12 col-md-8 mx-auto py-5">
                        <div class="admin-card text-center p-5 shadow-sm">
                            <div class="mb-3 text-warning">
                                <i class="fas fa-desktop fa-4x"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-2">محرك الاستكشاف والمسح الآلي متاح بالمحطة المحلية المستقلة</h4>
                            <p class="text-muted" style="line-height: 1.7; font-size: 0.85rem;">
                                نظراً لأن استكشاف خرائط جوجل ومطابقة المواقع الجغرافية بالذكاء الاصطناعي يتطلب بيئة روبوتية مستقلة، يتم تشغيل هذا المحرك من خلال <strong>محطة العمليات المركزية (Local Master HQ)</strong> لضمان أعلى دقة وسرعة دون استهلاك موارد الاستضافة السحابية المشتركة.
                            </p>
                            <div class="d-flex justify-content-center gap-2 mt-4">
                                <a href="admin.php?view=local&tab=researcher" class="btn btn-primary fw-bold px-4 py-2" style="border-radius: 10px;">
                                    <i class="fas fa-desktop me-1.5"></i> التبديل إلى المحطة المحلية
                                </a>
                                <a href="admin.php?tab=schools" class="btn btn-outline-secondary px-3 py-2" style="border-radius: 10px;">
                                    العودة لإدارة المدارس
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <div class="row">
                <div class="col-12 col-xl-11 mx-auto">
                    <div class="admin-card mb-4 shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px; overflow: hidden;">
                        <!-- Main Card Header -->
                        <div class="admin-card-head p-3 p-md-4" style="background: linear-gradient(135deg, #0B2A52, #163870) !important; color: #FFFFFF;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
                                <div>
                                    <h5 class="admin-card-title text-white mb-1" style="font-size: 1.05rem;">
                                        <i class="fas fa-bolt text-warning me-1"></i> محرك الاستكشاف والمسح الآلي المباشر (Autonomous Maps Worker)
                                    </h5>
                                    <div class="small text-white-50" style="font-size: 0.72rem;">
                                        مسح واستكشاف المدارس ورياض الأطفال ميدانياً عبر خرائط جوجل والمنصات الرقمية واعتمادها بالدليل.
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-light py-1.5 px-3" onclick="recalculateRanks()" style="font-size: 0.72rem; border-radius: 8px;" title="إعادة حساب الترتيب الفعلي">
                                        <i class="fas fa-sort-numeric-down me-1"></i> مواءمة الرتب
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-light py-1.5 px-3" onclick="exportMasterSeed()" style="font-size: 0.72rem; border-radius: 8px;" title="تصدير قاعدة البيانات">
                                        <i class="fas fa-file-export me-1"></i> تصدير Seed
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5 p-md-4 bg-white">
                            <!-- HERO ENGINE CONTROL: AUTONOMOUS MAPS DISCOVERY -->
                            <div class="p-3.5 rounded-3 border mb-4" style="background: #F8FAFC; border-color: #E2E8F0 !important;">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <label class="form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size: 0.88rem;">
                                        <i class="fas fa-satellite-dish text-success fs-5"></i>
                                        <span>إعدادات المسح والاستكشاف الآلي لخرائط جوجل والمنصات الرقمية:</span>
                                    </label>
                                    <span class="badge bg-success text-white py-1.5 px-2.5" style="font-size: 0.68rem;">
                                        <i class="fas fa-map-marked-alt me-1"></i> Google Maps Grounding المعتمد
                                    </span>
                                </div>

                                <div class="row g-2.5 align-items-end">
                                    <!-- 1. State Selector -->
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i> الولاية المستهدفة:
                                        </label>
                                        <select id="workerState" onchange="onWorkerStateChange()" class="form-select form-select-sm" style="font-size: 0.8rem; border-color: #CBD5E1; padding: 8px 12px;">
                                            <option value="">🌐 كافة ولايات السودان (مسح قومي شامل)</option>
                                            <optgroup label="الولايات (18 ولاية)">
                                                <option value="الخرطوم">ولاية الخرطوم (7 محليات رسمية)</option>
                                                <option value="الجزيرة">ولاية الجزيرة (7 محليات)</option>
                                                <option value="البحر الأحمر">ولاية البحر الأحمر (5 محليات)</option>
                                                <option value="نهر النيل">ولاية نهر النيل (6 محليات)</option>
                                                <option value="الشمالية">الولاية الشمالية (5 محليات)</option>
                                                <option value="النيل الأبيض">ولاية النيل الأبيض (6 محليات)</option>
                                                <option value="سنار">ولاية سنار (6 محليات)</option>
                                                <option value="القضارف">ولاية القضارف (6 محليات)</option>
                                                <option value="كسلا">ولاية كسلا (5 محليات)</option>
                                                <option value="شمال كردفان">ولاية شمال كردفان (5 محليات)</option>
                                                <option value="جنوب كردفان">ولاية جنوب كردفان (5 محليات)</option>
                                                <option value="غرب كردفان">ولاية غرب كردفان (4 محليات)</option>
                                                <option value="شمال دارفور">ولاية شمال دارفور (4 محليات)</option>
                                                <option value="جنوب دارفور">ولاية جنوب دارفور (4 محليات)</option>
                                                <option value="غرب دارفور">ولاية غرب دارفور (4 محليات)</option>
                                                <option value="وسط دارفور">ولاية وسط دارفور (4 محليات)</option>
                                                <option value="شرق دارفور">ولاية شرق دارفور (4 محليات)</option>
                                                <option value="النيل الأزرق">ولاية النيل الأزرق (5 محليات)</option>
                                            </optgroup>
                                            <optgroup label="خارج السودان">
                                                <option value="دول المهجر">دول المهجر والشتات (المدارس بالخارج)</option>
                                            </optgroup>
                                        </select>
                                    </div>

                                    <!-- 1.b Locality / City Selector -->
                                    <div class="col-lg-3 col-sm-6 col-12">
                                        <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between">
                                            <span><i class="fas fa-city text-info me-1"></i> المحلية / المدينة:</span>
                                            <span class="badge bg-light text-muted border py-0 px-1" style="font-size: 0.64rem;">تدقيق محلي</span>
                                        </label>
                                        <select id="workerLocality" class="form-select form-select-sm" style="font-size: 0.8rem; border-color: #CBD5E1; padding: 8px 12px;">
                                            <option value="">🌐 كافة المحليات بالولاية (مسح شامل)</option>
                                        </select>
                                    </div>

                                    <!-- 2. Institution Type -->
                                    <div class="col-lg-2 col-sm-6 col-12">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="fas fa-shapes text-primary me-1"></i> تصنيف المؤسسات:
                                        </label>
                                        <select id="workerSchoolType" class="form-select form-select-sm" style="font-size: 0.8rem; border-color: #CBD5E1; padding: 8px 12px;">
                                            <option value="all">كافة المؤسسات</option>
                                            <option value="kindergarten">رياض الأطفال والحضانات</option>
                                            <option value="private">المدارس الخاصة والدولية</option>
                                            <option value="government">المدارس الحكومية</option>
                                        </select>
                                    </div>

                                    <!-- 3. Target Count -->
                                    <div class="col-lg-2 col-sm-6 col-12">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="fas fa-bullseye text-warning me-1"></i> العدد المستهدف:
                                        </label>
                                        <select id="workerTargetCount" class="form-select form-select-sm" style="font-size: 0.8rem; border-color: #CBD5E1; padding: 8px 12px;">
                                            <option value="10">10 مؤسسات</option>
                                            <option value="20" selected>20 مؤسسة (موصى به)</option>
                                            <option value="50">50 مؤسسة</option>
                                            <option value="100">100 مؤسسة</option>
                                        </select>
                                    </div>

                                    <!-- 4. Optional School Name Query -->
                                    <div class="col-lg-2 col-sm-6 col-12">
                                        <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between">
                                            <span><i class="fas fa-search text-muted me-1"></i> اسم مدرسة:</span>
                                            <span class="text-muted fw-normal" style="font-size: 0.65rem;">(اختياري)</span>
                                        </label>
                                        <input type="text" id="workerSchoolName" class="form-control form-control-sm" placeholder="فارغ للكل..." style="font-size: 0.8rem; border-color: #CBD5E1; padding: 8px 12px;" onkeydown="if(event.key==='Enter') runAutonomousWorker();">
                                    </div>

                                    <!-- 5. Options and Action Button Row -->
                                    <div class="col-12 mt-2 d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                                        <div class="d-flex align-items-center gap-2 text-muted small" style="font-size: 0.76rem;">
                                            <i class="fas fa-info-circle text-primary fs-6"></i>
                                            <span>تُجلب المدارس أولاً إلى <strong>قائمة المعاينة المؤقتة</strong> لمراجعتها واستبعاد ما لا تريده، ثم حفظ المدارس المتبقية يدوياً.</span>
                                        </div>

                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <button type="button" id="btnRunAutonomousWorker" class="btn fw-bold px-4 py-2 text-white shadow-sm" onclick="runAutonomousWorker()" style="background: linear-gradient(135deg, #1E5FA8, #0B2A52); border: none; font-size: 0.85rem; border-radius: 8px;">
                                                <i class="fas fa-search-location me-1.5 text-warning"></i> جلب واستكشاف المدارس للمعاينة
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border rounded-3 p-2.5 mb-0 mt-3 d-flex align-items-center gap-2" style="background: #F0FDF4; border-color: #BBF7D0 !important; font-size: 0.73rem; color: #166534;">
                                    <i class="fas fa-bolt text-warning fs-6 flex-shrink-0"></i>
                                    <div>
                                        <strong>محرك استكشاف فوري فائق الدقة:</strong> يفحص قاعدة بيانات جغرافية معتمدة ومحققة تضم <strong>1,293 مؤسسة تعليمية حقيقية</strong> في ولايات السودان الـ 18، ويطابق فورياً مع خرائط Google وسجلات امتحانات الشهادة بدون أي توليد عشوائي، وتتاح مراجعة كل مدرسة فورياً عبر رابط الخريطة الرسمي.
                                    </div>
                                </div>
                                <div class="form-text small text-muted mt-2" style="font-size: 0.72rem;">
                                    <i class="fas fa-info-circle text-info me-1"></i> <strong>قاعدة الاعتماد الجغرافي:</strong> وجود المدرسة في خرائط جوجل مع عنوان حقيقي يؤهلها للاعتماد بالدليل حتى في حال عدم توفر هاتف مسجل في الخريطة، مع ربط مباشر بفيسبوك ويوتيوب وتيك توك والمراجع الحكومية.
                                </div>
                            </div>

                            <!-- VISUAL INTERACTIVE RESULTS DASHBOARD -->
                            <div id="workerResultsContainer" style="display: none;" class="mb-4"></div>

                            <!-- ADDITIONAL OPTIONS (Single School Dossier Studio & Batch Ingest Collapsible) -->
                            <div class="border-top pt-3 mt-4">
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-secondary py-1.5 px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSingleStudio" aria-expanded="false" style="font-size: 0.76rem; border-radius: 8px;">
                                        <i class="fas fa-search-plus me-1"></i> استوديو الاستقصاء المفصل لمدرسة واحدة (Single Studio)
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-1.5 px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBatchIngest" aria-expanded="false" style="font-size: 0.76rem; border-radius: 8px;">
                                        <i class="fas fa-layer-group me-1"></i> استكشاف وإدراج قائمة مدارس بالدفعات (Batch)
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-1.5 px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseManualDirect" aria-expanded="false" style="font-size: 0.76rem; border-radius: 8px;">
                                        <i class="fas fa-plus-circle me-1"></i> إدخال بيانات مدرسة يدوياً
                                    </button>
                                </div>

                                <!-- Collapsible 0: Single School Studio -->
                                <div class="collapse mt-3" id="collapseSingleStudio">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.82rem;"><i class="fas fa-search text-primary me-1"></i> فحص واستقصاء مدرسة واحدة بمفردها:</h6>
                                        <div class="row g-2 align-items-center mb-2">
                                            <div class="col-lg-7 col-12">
                                                <input type="text" id="studioQuery" class="form-control form-control-sm" placeholder="اسم المدرسة (مثال: مدارس القبس، روضة الإيمان...)" style="font-size: 0.78rem;" onkeydown="if(event.key==='Enter') runStudioResearch();">
                                            </div>
                                            <div class="col-lg-3 col-sm-6 col-12">
                                                <input type="text" id="studioCity" class="form-control form-control-sm" placeholder="المدينة (اختياري: الخرطوم، بورتسودان...)" style="font-size: 0.78rem;" onkeydown="if(event.key==='Enter') runStudioResearch();">
                                            </div>
                                            <div class="col-lg-2 col-sm-6 col-12">
                                                <button type="button" id="btnRunStudio" class="btn btn-sm btn-success w-100 fw-bold text-white shadow-sm" onclick="runStudioResearch()" style="font-size: 0.78rem;">
                                                    <i class="fas fa-search me-1"></i> فحص المنصات
                                                </button>
                                            </div>
                                        </div>
                                        <div id="studioDossierBox" style="display: none;" class="mt-3"></div>
                                    </div>
                                </div>

                                <!-- Collapsible 1: Batch List -->
                                <div class="collapse mt-3" id="collapseBatchIngest">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.82rem;"><i class="fas fa-layer-group text-primary me-1"></i> استكشاف قائمة مدارس دفعة واحدة:</h6>
                                        <div class="alert alert-light border py-1.5 px-2.5 mb-2 small text-muted" style="font-size: 0.72rem;">
                                            الصيغة: <code>اسم المدرسة | الولاية | المدينة | الحي | هاتف التوثيق | رابط الخريطة | فيسبوك | نبذة</code> (الهاتف اختياري)
                                        </div>
                                        <textarea id="batchInputText" class="form-control font-monospace mb-2" rows="4" placeholder="مدارس المنار الخاصة | البحر الأحمر | بورتسودان | حي المطار | 0123456789 | https://maps.app.goo.gl/...&#10;روضة الإيمان الحديثة | نهر النيل | عطبرة | حي الداخلة | | https://maps.app.goo.gl/..." style="border-color: #DDE4ED; font-size: 0.76rem;"></textarea>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span id="batchParsedSummary" class="small text-muted" style="font-size: 0.74rem;"></span>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1" onclick="previewBatchList()" style="font-size: 0.76rem;">
                                                    <i class="fas fa-eye me-1"></i> فحص القائمة
                                                </button>
                                                <button type="button" id="btnExecuteBatch" class="btn btn-sm btn-success px-3 py-1 fw-bold text-white shadow-sm" onclick="executeBatchImport()" disabled style="font-size: 0.76rem;">
                                                    <i class="fas fa-check-double me-1"></i> اعتماد وإدراج الدفعة
                                                </button>
                                            </div>
                                        </div>
                                        <div id="batchPreviewBox" class="mt-3" style="display: none;"></div>
                                    </div>
                                </div>

                                <!-- Collapsible 2: Manual Direct Form -->
                                <div class="collapse mt-3" id="collapseManualDirect">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h6 class="fw-bold text-dark mb-3" style="font-size: 0.82rem;"><i class="fas fa-edit text-primary me-1"></i> إدخال مباشر لبيانات مدرسة:</h6>
                                        <form id="autoDiscoverForm" onsubmit="event.preventDefault(); runAutoDiscover();">
                                            <div class="row g-2.5">
                                                <div class="col-lg-5 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">اسم المدرسة <span class="text-danger">*</span></label>
                                                    <input type="text" id="discQuery" class="form-control form-control-sm" placeholder="اسم المدرسة" required style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-lg-4 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">رابط خرائط جوجل</label>
                                                    <input type="url" id="discGoogleMapUrl" class="form-control form-control-sm" placeholder="https://maps.app.goo.gl/..." style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">الهاتف الموثق (إن وجد)</label>
                                                    <input type="text" id="discPhone" class="form-control form-control-sm" placeholder="فارغ إن لم يتوفر" style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">الولاية</label>
                                                    <select id="discState" class="form-select form-select-sm" style="font-size: 0.78rem;">
                                                        <option value="">استنتاج تلقائي</option>
                                                        <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                                            <option value="<?= e($st) ?>"><?= e($st) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">المدينة</label>
                                                    <input type="text" id="discCity" class="form-control form-control-sm" placeholder="المدينة" style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">الحي</label>
                                                    <input type="text" id="discDistrict" class="form-control form-control-sm" placeholder="الحي" style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-lg-3 col-sm-6 col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">صفحة فيسبوك</label>
                                                    <input type="url" id="discFacebook" class="form-control form-control-sm" placeholder="https://facebook.com/..." style="font-size: 0.78rem;">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small fw-bold text-dark mb-1">نبذة تعريفية</label>
                                                    <textarea id="discDesc" class="form-control form-control-sm" rows="2" placeholder="نبذة تعريفية عن المدرسة..." style="font-size: 0.78rem;"></textarea>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-end">
                                                <button type="submit" id="btnRunDiscovery" class="btn btn-sm btn-primary px-3 py-1.5 fw-bold text-white shadow-sm" style="font-size: 0.78rem;">
                                                    <i class="fas fa-check-circle me-1"></i> اعتماد وحفظ المدرسة بالدليل
                                                </button>
                                            </div>
                                        </form>
                                        <div id="discoveryResultBox" class="mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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

        <!-- TAB CONTENT: 6. WHATSAPP MISSION CONTROL & BOT AGENT -->
        <?php elseif ($activeTab === 'whatsapp'): ?>
            <?php if (!$isLocalStation): ?>
                <div class="row">
                    <div class="col-12 col-md-8 mx-auto py-5">
                        <div class="admin-card text-center p-5 shadow-sm">
                            <div class="mb-3 text-success">
                                <i class="fab fa-whatsapp fa-4x"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-2">مركز عمليات الواتساب والبوت متاح بالمحطة المحلية المستقلة</h4>
                            <p class="text-muted" style="line-height: 1.7; font-size: 0.85rem;">
                                يعمل خادم الواتساب المستقل، فاحص الأرقام الجماعي، ومحاكي البوت التفاعلي عبر <strong>محطة العمليات المركزية (Local Master HQ على المنفذ 8085)</strong> لتأمين جلسات الأرقام وتنفيذ الحملات الآلية.
                                <br>في السحابة الحالية، يمكنك مراسلة أي مدرسة مباشرة بنقرة واحدة عبر زر <strong>"مراسلة واتساب"</strong> المتوفر في جدول المدارس.
                            </p>
                            <div class="d-flex justify-content-center gap-2 mt-4">
                                <a href="admin.php?view=local&tab=whatsapp" class="btn btn-success fw-bold px-4 py-2" style="border-radius: 10px;">
                                    <i class="fas fa-desktop me-1.5"></i> فتح مركز الواتساب بالمحطة المحلية
                                </a>
                                <a href="admin.php?tab=schools" class="btn btn-outline-secondary px-3 py-2" style="border-radius: 10px;">
                                    العودة لإدارة المدارس
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <?php
            $waSentCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE whatsapp_sent = 1")->fetchColumn();
            $waSchoolsWithPhone = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE phone IS NOT NULL AND phone != ''")->fetchColumn();
            $waValidCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE whatsapp_status = 'valid'")->fetchColumn();
            $waInvalidCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE whatsapp_status = 'invalid'")->fetchColumn();
            $waPendingCheckCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE (phone IS NOT NULL AND phone != '' OR whatsapp IS NOT NULL AND whatsapp != '') AND (whatsapp_status IS NULL OR whatsapp_status = 'unverified')")->fetchColumn();
            $waMessagesCount = 0;
            $recentMessages = [];
            try {
                $waMessagesCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_whatsapp_messages")->fetchColumn();
                $recentMessages = $pdo->query("SELECT * FROM daleel_whatsapp_messages ORDER BY id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
            ?>
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.1rem;">
                        <i class="fab fa-whatsapp text-success me-1.5"></i> مركز عمليات الواتساب المستقل وبوت الرد الذكي
                    </h5>
                    <div class="text-muted small" style="font-size: 0.74rem;">
                        المحطة المركزية للتحكم بخادم الواتساب (Port 8085)، فحص الأرقام الميداني، حملات المراسلة الآلية، ومحاكي البوت التفاعلي
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="admin.php?tab=sync" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow-sm" style="font-size: 0.78rem; border-radius: 8px;">
                        <i class="fas fa-cloud-upload-alt me-1 text-warning"></i> ☁️ مركز المزامنة السحابية
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 py-1.5" onclick="updateWaWidgetStatus()" style="font-size: 0.78rem; border-radius: 8px;">
                        <i class="fas fa-sync-alt me-1"></i> فحص فوري
                    </button>
                </div>
            </div>

            <!-- METRIC CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="metric-card m-green">
                        <div class="metric-lbl">حالة جلسة الواتساب</div>
                        <div class="metric-val" id="waTabStatusVal" style="font-size: 1.15rem;">جاري الفحص...</div>
                        <div class="small text-muted" id="waTabPhoneVal">منفذ 8085 مستقل</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="metric-card m-blue">
                        <div class="metric-lbl">أرقام واتساب مؤكدة 🟢</div>
                        <div class="metric-val text-success"><?= number_format($waValidCount) ?> <span style="font-size: 0.8rem; font-weight: 500; color: #64748B;">/ <?= number_format($waSchoolsWithPhone) ?></span></div>
                        <div class="small text-muted"><?= round(($waSchoolsWithPhone > 0 ? ($waValidCount / $waSchoolsWithPhone * 100) : 0), 1) ?>% نسبة الأرقام الصالحة</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="metric-card m-orange">
                        <div class="metric-lbl">أرقام أرضية أو غير مفعلة 🔴</div>
                        <div class="metric-val text-danger"><?= number_format($waInvalidCount) ?></div>
                        <div class="small text-muted">مستبعدة من الإرسال لتجنب الهدر</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="metric-card m-purple">
                        <div class="metric-lbl">هواتف بانتظار الفحص ⚪</div>
                        <div class="metric-val" style="color: #7C3AED; font-size: 1.15rem;"><?= number_format($waPendingCheckCount) ?></div>
                        <div class="small text-muted">المدارس التي تم مراسلتها: <b><?= number_format($waSentCount) ?></b></div>
                    </div>
                </div>
            </div>

            <!-- GRID ROW 1: SERVER CONTROL & BULK VALIDATOR -->
            <div class="row g-4 mb-4">
                <!-- 1. SERVER & SESSION CONTROL -->
                <div class="col-lg-5 col-12">
                    <div class="admin-card h-100 shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #0F5C3E, #0A3E2A); border-radius: 14px 14px 0 0;">
                            <h5 class="admin-card-title text-white mb-0" style="font-size: 0.95rem;">
                                <i class="fab fa-whatsapp text-warning me-1.5"></i> خادم وجلسة واتساب الدليل
                            </h5>
                            <span class="badge bg-white-subtle text-white border" style="font-size: 0.68rem;">Port 8085</span>
                        </div>
                        <div class="p-4 text-center">
                            <div id="waTabStatusBadge" class="p-3 rounded-3 mb-3 fw-bold" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D;">
                                ⏳ جاري فحص حالة الاتصال...
                            </div>
                            <div id="waTabDevicePhone" class="fw-bold text-dark mb-3" style="font-size: 0.95rem;"></div>

                            <div id="waTabQrWrap" class="p-3 bg-white rounded-3 border mb-3 mx-auto" style="min-height: 220px; max-width: 250px; display: flex; align-items: center; justify-content: center;">
                                <span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i> جاري استعلام الخادم...</span>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                                <button type="button" class="btn btn-sm btn-success fw-bold px-3 py-1.5" onclick="startWaServerFromPanel(this)" style="font-size: 0.76rem; border-radius: 8px;">
                                    <i class="fas fa-play me-1"></i> تشغيل الخادم
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 py-1.5" onclick="stopWaServerFromPanel(this)" style="font-size: 0.76rem; border-radius: 8px;">
                                    <i class="fas fa-stop me-1"></i> إيقاف الخادم
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-warning fw-bold px-3 py-1.5" onclick="restartWaGatewaySession()" style="font-size: 0.76rem; border-radius: 8px;">
                                    <i class="fas fa-redo me-1"></i> إعادة تشغيل
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3 py-1.5" onclick="logoutWaSession()" style="font-size: 0.76rem; border-radius: 8px;">
                                    <i class="fas fa-sign-out-alt me-1"></i> فصل الرقم
                                </button>
                            </div>
                            <div class="alert alert-light border rounded-3 p-2 mt-3 text-muted mb-0" style="font-size: 0.68rem; text-align: right; line-height: 1.5; background: #F8FAFC;">
                                <i class="fas fa-info-circle text-primary me-1"></i>
                                الجلسة محفوظة باسم <code>daleel_session</code> داخل <code>whatsapp-server/tokens/</code> وتعمل بصفة دائمة ومعزولة عن سيرفر SchoPlus الأساسي.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. BULK NUMBER VALIDATOR & SINGLE CHECKER -->
                <div class="col-lg-7 col-12">
                    <div class="admin-card mb-4 shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head" style="background: linear-gradient(135deg, #0B2A52, #163870); color: #FFFFFF; border-radius: 14px 14px 0 0;">
                            <h5 class="admin-card-title text-white mb-0" style="font-size: 0.95rem;">
                                <i class="fas fa-phone-volume text-warning me-1.5"></i> أداة الفحص الجماعي الذكي لأرقام المدارس
                            </h5>
                            <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">فحص فوري</span>
                        </div>
                        <div class="p-4">
                            <p class="text-muted small mb-3" style="font-size: 0.76rem; line-height: 1.5;">
                                فحص أرقام هواتف المدارس دفعة واحدة للتحقق من امتلاكها حسابات واتساب نشطة (🟢) وتمييزها عن الهواتف الأرضية والأرقام غير المفعلة (🔴)، مع حفظ وتثبيت النتيجة في قاعدة البيانات لحظياً:
                            </p>
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-5 col-12">
                                    <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">الولاية المستهدفة:</label>
                                    <select id="bulkCheckState" class="form-select form-select-sm" style="font-size: 0.78rem;">
                                        <option value="">كافة الولايات (السودان والمهجر)</option>
                                        <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                            <option value="<?= e($st) ?>"><?= e($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">حجم الدفعة:</label>
                                    <select id="bulkCheckLimit" class="form-select form-select-sm" style="font-size: 0.78rem;">
                                        <option value="10">10 مدارس</option>
                                        <option value="20" selected>20 مدرسة</option>
                                        <option value="35">35 مدرسة</option>
                                        <option value="50">50 مدرسة</option>
                                    </select>
                                </div>
                                <div class="col-md-4 col-6 d-flex align-items-end">
                                    <div class="form-check small mb-1">
                                        <input class="form-check-input" type="checkbox" id="bulkCheckOnlyUnverified" checked>
                                        <label class="form-check-label" for="bulkCheckOnlyUnverified" style="font-size: 0.72rem;">
                                            غير المفحوصة فقط (⚪)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" id="btnRunBulkCheck" class="btn btn-primary fw-bold px-4 py-2" onclick="runBulkNumberValidation()" style="font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-bolt text-warning me-1"></i> بدء الفحص الجماعي التلقائي
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="document.getElementById('bulkCheckProgressWrap').style.display='none'; document.getElementById('bulkCheckResultsList').innerHTML='';" style="font-size: 0.75rem; border-radius: 8px;">
                                    مسح النتائج
                                </button>
                            </div>

                            <!-- Live Progress Box -->
                            <div id="bulkCheckProgressWrap" class="mt-3 p-3 rounded-3 border" style="display: none; background: #F8FAFC;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small text-dark" id="bulkCheckProgressText">جاري فحص الأرقام عبر بوابة الواتساب...</span>
                                    <span class="badge bg-primary" id="bulkCheckCounterBadge">0 / 0</span>
                                </div>
                                <div class="progress mb-2" style="height: 7px;">
                                    <div id="bulkCheckProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%;"></div>
                                </div>
                                <div id="bulkCheckResultsList" style="max-height: 200px; overflow-y: auto; font-size: 0.75rem;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- SINGLE NUMBER QUICK TEST -->
                    <div class="admin-card shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title" style="font-size: 0.9rem;">
                                <i class="fas fa-search text-primary me-1.5"></i> فحص فوري لرقم فردي محدد
                            </h5>
                        </div>
                        <div class="p-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-sm-8 col-12">
                                    <input type="text" id="checkSinglePhoneInput" class="form-control form-control-sm" placeholder="أدخل الرقم (مثال: 0911197022 أو 249111970220)" style="font-size: 0.78rem;">
                                </div>
                                <div class="col-sm-4 col-12">
                                    <button type="button" class="btn btn-sm btn-primary w-100 fw-bold" onclick="runSinglePhoneCheck()" style="font-size: 0.78rem; border-radius: 6px;">
                                        <i class="fas fa-check-circle me-1"></i> فحص الرقم
                                    </button>
                                </div>
                            </div>
                            <div id="checkSingleResultBox" class="mt-2 p-2.5 rounded-3" style="display: none; font-size: 0.78rem;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRID ROW 2: AUTOMATED CAMPAIGN RUNNER & INTERACTIVE BOT SIMULATOR -->
            <div class="row g-4 mb-4">
                <!-- 3. AUTOMATED BATCH OUTREACH CAMPAIGNS -->
                <div class="col-lg-6 col-12">
                    <div class="admin-card h-100 shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E3A8A, #0F2D6B); border-radius: 14px 14px 0 0;">
                            <h5 class="admin-card-title text-white mb-0" style="font-size: 0.95rem;">
                                <i class="fas fa-paper-plane text-warning me-1.5"></i> حملات المراسلة الآلية المجدولة للمدارس
                            </h5>
                            <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">أتمتة ذكية</span>
                        </div>
                        <div class="p-4">
                            <p class="text-muted small mb-3" style="font-size: 0.76rem; line-height: 1.5;">
                                إرسال رسائل التعريف والدعوة الرسمية المخصصة للمدارس آلياً على دفعات متتالية، مع فواصل زمنية ذكية لحماية الرقم وتجنب الحظر:
                            </p>

                            <div class="row g-2 mb-3">
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">استهداف ولاية معينة:</label>
                                    <select id="campaignState" class="form-select form-select-sm" style="font-size: 0.78rem;">
                                        <option value="">كافة الولايات (المدارس غير المراسلة)</option>
                                        <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                            <option value="<?= e($st) ?>"><?= e($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">الفاصل الزمني الآمن بين كل رسالة:</label>
                                    <select id="campaignDelaySec" class="form-select form-select-sm" style="font-size: 0.78rem;">
                                        <option value="6">6 ثوانٍ (سريع)</option>
                                        <option value="8" selected>8 ثوانٍ (موصى به لحماية الحساب)</option>
                                        <option value="12">12 ثانية (فائق الأمان)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Template Preview Collapsible -->
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-primary fw-bold" onclick="toggleCampaignTemplate()" style="font-size: 0.74rem;">
                                    <i class="fas fa-eye me-1"></i> معاينة قالب الرسالة الرسمية المخصصة
                                </button>
                                <div id="campaignTemplateWrap" class="p-2.5 bg-light rounded-3 border mt-2 small text-muted dir-rtl" style="display: none; font-size: 0.72rem; line-height: 1.5; max-height: 140px; overflow-y: auto;">
                                    السلام عليكم ورحمة الله وبركاته،<br>
                                    إدارة مدرسة <b>{اسم المدرسة}</b> الموقرة،<br>
                                    تحية طيبة لكم من منصة <b>دليل المدارس السودانية</b>.<br>
                                    يسرنا إعلامكم بإدراج مدرستكم ضمن الدليل الوطني الشامل، وندعوكم لتوثيق صفحة المدرسة وإدارتها مجاناً عبر الرابط:<br>
                                    <code>https://daleel.schoplus.sd/school/{slug}</code>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" id="btnStartCampaign" class="btn btn-success fw-bold px-4 py-2" onclick="startBatchCampaign()" style="font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-play me-1"></i> بدء حملة الإرسال الآن 🚀
                                </button>
                                <button type="button" id="btnPauseCampaign" class="btn btn-outline-warning fw-bold px-3 py-2" onclick="pauseBatchCampaign()" style="display: none; font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-pause me-1"></i> إيقاف مؤقت
                                </button>
                                <button type="button" id="btnStopCampaign" class="btn btn-outline-danger fw-bold px-3 py-2" onclick="stopBatchCampaign()" style="display: none; font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-stop me-1"></i> إنهاء الحملة
                                </button>
                            </div>

                            <!-- Campaign Live Progress -->
                            <div id="campaignProgressWrap" class="p-3 rounded-3 border" style="display: none; background: #F8FAFC;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small text-dark" id="campaignStatusText">جاري تشغيل الحملة...</span>
                                    <span class="badge bg-success" id="campaignCountBadge">0 مرسلة</span>
                                </div>
                                <div class="progress mb-2" style="height: 7px;">
                                    <div id="campaignProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%;"></div>
                                </div>
                                <div id="campaignStreamLogs" style="max-height: 160px; overflow-y: auto; font-size: 0.72rem; line-height: 1.5;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. INTERACTIVE BOT SIMULATOR & PLAYGROUND -->
                <div class="col-lg-6 col-12">
                    <div class="admin-card h-100 shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #0F5C3E, #144933); border-radius: 14px 14px 0 0;">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-robot text-warning fs-6"></i>
                                </div>
                                <div>
                                    <h5 class="admin-card-title text-white mb-0" style="font-size: 0.95rem;">محاكي البوت والرد الذكي (Live Bot Simulator)</h5>
                                    <div style="font-size: 0.65rem; color: #BBF7D0;">اختبر سيناريوهات أولياء الأمور والمدارس محلياً بدون إرسال للهاتف</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" onclick="resetBotSimulationChat()" style="font-size: 0.68rem;" title="إعادة تعيين المحادثة">
                                <i class="fas fa-redo me-1"></i> إعادة تعيين
                            </button>
                        </div>
                        <div class="p-3 d-flex flex-column" style="height: 430px;">
                            <!-- Test Prompt Pills -->
                            <div class="d-flex flex-wrap gap-1 mb-2 pb-2 border-bottom">
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('مرحبا')">مرحبا 👋</span>
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('1')">1. بحث عن مدرسة 🔍</span>
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('2')">2. توثيق مدرسة ⭐</span>
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('3')">3. تعديل بيانات ✏️</span>
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('4')">4. تسجيل مدرسة 🏫</span>
                                <span class="badge bg-light text-dark border hover-primary" style="cursor: pointer; font-size: 0.65rem;" onclick="sendSimPrompt('كم رسوم المدارس في بحري؟')">رسوم بحري؟ 💰</span>
                            </div>

                            <!-- Chat Messages Screen -->
                            <div id="botSimChatBox" class="flex-grow-1 p-3 rounded-3 border mb-2" style="background: #EFEAE2; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                                <div class="text-center text-muted small my-1" style="font-size: 0.68rem;">
                                    <span class="bg-white px-2 py-1 rounded-pill shadow-2xs">محادثة تجريبية مشفرة محلياً مع وكيل الدليل الذكي</span>
                                </div>
                                <div class="bot-msg-bubble align-self-start p-2.5 rounded-3 shadow-2xs" style="background: #FFFFFF; max-width: 85%; font-size: 0.76rem; line-height: 1.5; border-radius: 10px 10px 10px 0;">
                                    مرحباً بك! أنا <b>المساعد الآلي لدليل المدارس السودانية 🤖</b><br>
                                    اكتب أي سؤال أو اختر من الأوامر أعلاه لتجربة تدفق المحادثة والذكاء الاصطناعي مباشرة.
                                    <div class="text-end text-muted mt-1" style="font-size: 0.62rem;"><?= date('H:i') ?> ✓✓</div>
                                </div>
                            </div>

                            <!-- Input Form -->
                            <form id="botSimForm" onsubmit="event.preventDefault(); sendSimMsg();" class="d-flex gap-2">
                                <input type="text" id="botSimInput" class="form-control form-control-sm" placeholder="اكتب رسالة إلى البوت (مثال: بحث عن مدرسة في الخرطوم)..." style="font-size: 0.78rem;" autocomplete="off">
                                <button type="submit" id="btnBotSimSend" class="btn btn-sm btn-success fw-bold px-3" style="font-size: 0.78rem;">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRID ROW 3: LIVE MESSAGE LOGS & DIRECT MANUAL SENDER -->
            <div class="row g-4">
                <!-- 5. DIRECT MANUAL SENDER -->
                <div class="col-lg-4 col-12">
                    <div class="admin-card shadow-sm h-100" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head">
                            <h5 class="admin-card-title" style="font-size: 0.92rem;">
                                <i class="fas fa-paper-plane text-success me-1.5"></i> إرسال رسالة مباشرة مخصصة
                            </h5>
                        </div>
                        <div class="p-3">
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">رقم هاتف المستلم:</label>
                                <input type="text" id="tabSendPhone" class="form-control form-control-sm" placeholder="مثال: 0911197022 أو 249111970220" style="font-size: 0.78rem;">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">نص الرسالة:</label>
                                <textarea id="tabSendMessage" class="form-control form-control-sm" rows="4" placeholder="اكتب نص الرسالة هنا..." style="font-size: 0.78rem;"></textarea>
                            </div>
                            <button type="button" class="btn btn-sm btn-success fw-bold w-100 py-1.5" onclick="runTabSendMessage()" style="font-size: 0.78rem; border-radius: 8px;">
                                <i class="fab fa-whatsapp me-1"></i> إرسال الرسالة الآن 📲
                            </button>
                            <div id="tabSendResultBox" class="mt-2 p-2.5 rounded-3" style="display: none; font-size: 0.78rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- 6. LIVE MESSAGE LOGS TABLE -->
                <div class="col-lg-8 col-12">
                    <div class="admin-card shadow-sm h-100" style="border: 1px solid #DDE4ED; border-radius: 14px;">
                        <div class="admin-card-head d-flex justify-content-between align-items-center">
                            <h5 class="admin-card-title" style="font-size: 0.92rem;">
                                <i class="fas fa-comments text-primary me-1.5"></i> سجل المحادثات والرسائل التفاعلية الحية
                            </h5>
                            <span class="badge bg-light text-dark border" style="font-size: 0.68rem;">آخر <?= count($recentMessages) ?> رسالة</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الاتجاه</th>
                                        <th>رقم الهاتف</th>
                                        <th>نص الرسالة</th>
                                        <th>نوع الإجراء</th>
                                        <th>الوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentMessages)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 text-muted opacity-50"></i>
                                                <div>لا توجد رسائل مسجلة حتى الآن. ستظهر هنا الرسائل الواردة والصادرة تلقائياً.</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentMessages as $m): ?>
                                            <tr>
                                                <td class="text-muted fw-bold">#<?= $m['id'] ?></td>
                                                <td>
                                                    <?php if ($m['direction'] === 'in'): ?>
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle py-0 px-1" style="font-size: 0.65rem;"><i class="fas fa-arrow-down me-1"></i>وارد</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1" style="font-size: 0.65rem;"><i class="fas fa-arrow-up me-1"></i>صادر</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold dir-ltr text-end" style="font-family: monospace; font-size: 0.72rem;"><?= e($m['phone']) ?></td>
                                                <td style="max-width: 320px; white-space: normal; line-height: 1.4; font-size: 0.72rem;">
                                                    <?= nl2br(e(mb_substr($m['message'], 0, 120))) ?><?= mb_strlen($m['message']) > 120 ? '...' : '' ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border" style="font-size: 0.65rem;"><?= e($m['intent'] ?: 'عام') ?></span>
                                                </td>
                                                <td class="text-muted small" style="font-size: 0.68rem;"><?= e(date('H:i | m/d', strtotime($m['created_at']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <!-- TAB CONTENT: 7. TWO-WAY CLOUD SYNC COMMAND HUB -->
        <?php elseif ($activeTab === 'sync'): ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="admin-card shadow-sm" style="border: 1px solid #DDE4ED; border-radius: 14px; overflow: hidden;">
                        <?php if (!$isLocalStation): ?>
                            <!-- CLOUD PRODUCTION VIEW: API ENDPOINT & SYNC STATUS -->
                            <div class="admin-card-head p-4 text-white" style="background: linear-gradient(135deg, #0B2A52, #1E5FA8);">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <h4 class="fw-bold text-white mb-1" style="font-size: 1.15rem;">
                                            <i class="fas fa-cloud text-warning me-2"></i> بوابة المزامنة السحابية (Cloud Production Sync Gateway)
                                        </h4>
                                        <div style="font-size: 0.76rem; color: #CBD5E1;">
                                            نقطة النهاية الآمنة لاستقبال التحديثات، المدارس المستكشفة، وحالات الواتساب من محطة العمليات المحلية
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge bg-success text-white px-3 py-1.5" style="font-size: 0.76rem;">
                                            <i class="fas fa-check-circle me-1"></i> نقطة النهاية جاهزة ونشطة 🟢
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6 col-12">
                                        <div class="p-3.5 rounded-3 border h-100" style="background: #F8FAFC;">
                                            <h6 class="fw-bold text-dark mb-2" style="font-size: 0.88rem;">
                                                <i class="fas fa-link text-primary me-1"></i> رابط نقطة نهاية المزامنة (API Endpoint)
                                            </h6>
                                            <div class="input-group input-group-sm mb-2">
                                                <input type="text" class="form-control font-monospace text-muted bg-white" readonly value="https://daleel.schoplus.sd/api_sync.php" id="cloudEndpointUrl">
                                                <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cloudEndpointUrl').value); showAdminToast('تم نسخ رابط الـ API ✅');">نسخ</button>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.72rem; line-height: 1.5;">
                                                تستخدم محطة العمليات المركزية هذا الرابط لرفع المدارس الجديدة وسجلات المراسلات واستيراد التوثيقات.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="p-3.5 rounded-3 border h-100" style="background: #F0FDF4; border-color: #BBF7D0 !important;">
                                            <h6 class="fw-bold text-success mb-2" style="font-size: 0.88rem;">
                                                <i class="fas fa-shield-alt me-1"></i> مفتاح الأمان المشفر (Sync Secret Token)
                                            </h6>
                                            <div class="input-group input-group-sm mb-2">
                                                <input type="password" class="form-control font-monospace bg-white" readonly value="SchoPlus_Daleel_Sync_Secret_Key_2026_Live" id="cloudSyncToken">
                                                <button class="btn btn-outline-secondary" type="button" onclick="const f=document.getElementById('cloudSyncToken'); f.type = f.type==='password'?'text':'password';">إظهار</button>
                                                <button class="btn btn-outline-success" type="button" onclick="navigator.clipboard.writeText('SchoPlus_Daleel_Sync_Secret_Key_2026_Live'); showAdminToast('تم نسخ مفتاح الأمان المشفر ✅');">نسخ</button>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.72rem;">
                                                يتم التحقق من هذا الرمز في كل طلب مزامنة لمنع الوصول غير المصرح به.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-3 border mb-3" style="background: #EFF6FF; border-color: #BFDBFE !important;">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <h6 class="fw-bold text-primary mb-1" style="font-size: 0.88rem;">
                                                <i class="fas fa-database me-1"></i> النسخة الاحتياطية السحابية وقاعدة البيانات الحية
                                            </h6>
                                            <div class="text-muted small" style="font-size: 0.74rem;">
                                                يحتوي الدليل السحابي على <?= number_format($totalCount) ?> مدرسة معتمدة و <?= number_format($verifiedCount) ?> مدرسة موثقة.
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5" onclick="exportMasterSeed()" style="border-radius: 8px; font-size: 0.78rem;">
                                                <i class="fas fa-file-export me-1"></i> تصدير Master Seed SQL
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1.5" onclick="recalculateRanks()" style="border-radius: 8px; font-size: 0.78rem;">
                                                <i class="fas fa-sort-numeric-down me-1"></i> تدقيق الترقيم المتسلسل
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                        <!-- LOCAL MASTER HQ VIEW: FULL 2-WAY PUSH/PULL HUB -->
                        <div class="admin-card-head p-4 text-white" style="background: linear-gradient(135deg, #0B2A52, #1E5FA8);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h4 class="fw-bold text-white mb-1" style="font-size: 1.15rem;">
                                        <i class="fas fa-cloud-upload-alt text-warning me-2"></i> مركز المزامنة السحابية المتقدم (Cloud Sync Command Hub)
                                    </h4>
                                    <div style="font-size: 0.76rem; color: #CBD5E1;">
                                        ربط محطة العمل المحلية المستقلة بالسحابة العامة (https://daleel.schoplus.sd/api_sync.php) لمزامنة المدارس، التوثيقات، وحالات الواتساب
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-white-subtle text-white border px-3 py-1.5" style="font-size: 0.76rem;">
                                        <i class="fas fa-shield-alt text-warning me-1"></i> الربط بمفتاح المزامنة المشفر
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <!-- Latency / Status Badge -->
                            <div id="syncHubStatusBadge" class="p-3 rounded-3 text-center mb-4 fw-bold" style="background: #F1F5F9; color: #334155; font-size: 0.85rem;">
                                <i class="fas fa-spinner fa-spin me-1.5 text-primary"></i> جاري فحص حالة نقطة نهاية السحابة والمطابقة...
                            </div>

                            <!-- Side-by-Side Statistics Grid -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 col-12">
                                    <div class="p-3 rounded-3 border" style="background: #F8FAFC;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="fas fa-desktop text-success fs-5"></i>
                                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">محطة العمل المحلية (Localhost XAMPP)</h6>
                                        </div>
                                        <div class="d-flex justify-content-between border-bottom py-1.5 small">
                                            <span class="text-muted">إجمالي المدارس المحلية:</span>
                                            <span class="fw-bold text-dark" id="syncHubLocalCount"><?= number_format($totalCount) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between border-bottom py-1.5 small">
                                            <span class="text-muted">المدارس التي تم مراسلتها محلياً:</span>
                                            <span class="fw-bold text-success" id="syncHubLocalWaSent"><?= number_format($waSentCount) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1.5 small">
                                            <span class="text-muted">أرقام واتساب مؤكدة 🟢:</span>
                                            <span class="fw-bold text-primary"><?= number_format($waValidCount) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="p-3 rounded-3 border" style="background: #EFF6FF;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="fas fa-cloud text-primary fs-5"></i>
                                            <h6 class="fw-bold mb-0 text-primary" style="font-size: 0.9rem;">السحابة العامة (daleel.schoplus.sd)</h6>
                                        </div>
                                        <div class="d-flex justify-content-between border-bottom py-1.5 small">
                                            <span class="text-muted">إجمالي المدارس بالسحابة:</span>
                                            <span class="fw-bold text-dark" id="syncHubCloudCount">جاري الجلب...</span>
                                        </div>
                                        <div class="d-flex justify-content-between border-bottom py-1.5 small">
                                            <span class="text-muted">المدارس المراسلة بالسحابة:</span>
                                            <span class="fw-bold text-success" id="syncHubCloudWaSent">جاري الجلب...</span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1.5 small">
                                            <span class="text-muted">الإصدار السحابي المنشور:</span>
                                            <span class="fw-bold text-primary" id="syncHubCloudVer">v3.8.32</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 col-12">
                                    <div class="card p-3 border shadow-none h-100" style="border-radius: 12px; background: #FAF5FF; border-color: #E9D5FF !important;">
                                        <h6 class="fw-bold mb-1" style="font-size: 0.88rem; color: #6B21A8;">
                                            <i class="fas fa-cloud-download-alt me-1 text-purple"></i> سحب البيانات من السحابة (Pull from Cloud)
                                        </h6>
                                        <p class="text-muted small mb-3" style="font-size: 0.74rem; line-height: 1.5;">
                                            جلب أي مدارس جديدة أو طلبات توثيق مسجلة عبر الإنترنت على السحابة، وتحديث قاعدة البيانات المحلية دون المساس بالفحوصات المحلية.
                                        </p>
                                        <button type="button" class="btn btn-sm text-white fw-bold py-2 mt-auto" onclick="doCloudPull(this)" style="background: #7C3AED; border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fas fa-download me-1.5"></i> بدء السحب والاستيراد من السحابة 📥
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="card p-3 border shadow-none h-100" style="border-radius: 12px; background: #F0FDF4; border-color: #BBF7D0 !important;">
                                        <h6 class="fw-bold mb-1" style="font-size: 0.88rem; color: #166534;">
                                            <i class="fas fa-cloud-upload-alt me-1 text-success"></i> رفع ومزامنة التحديثات إلى السحابة (Push to Cloud)
                                        </h6>
                                        <p class="text-muted small mb-3" style="font-size: 0.74rem; line-height: 1.5;">
                                            رفع كافة المدارس المضافة محلياً، وأرقام الهواتف المكتشفة، وحالات إرسال الواتساب، وسجلات الرسائل الصادرة إلى السحابة بنقرة واحدة.
                                        </p>
                                        <button type="button" class="btn btn-sm btn-success fw-bold py-2 mt-auto" onclick="doCloudPush(this)" style="border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fas fa-upload me-1.5"></i> بدء رفع التحديثات إلى السحابة 📤
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Sync Log Box -->
                            <div id="cloudSyncLogBox" class="p-3 rounded-3 border" style="display: none; background: #F8FAFC; font-size: 0.8rem;"></div>
                        </div>
                        <?php endif; ?>
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
                        showAdminToast(data.message || 'تم اعتماد وتوثيق المدرسة وترقية ترتيبها في الدليل بنجاح ✅', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-circle-xmark"></i> <span>عامة</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_verified', 1, this)`);
                        showAdminToast(data.message || 'تم تحويل المدرسة إلى عامة (غير موثقة) ℹ️', 'warning');
                    }
                    if (data.new_rank) {
                        const rankInput = document.getElementById('dir-rank-' + schoolId);
                        if (rankInput) rankInput.value = data.new_rank;
                        const rankBadge = el.closest('tr')?.querySelector('.actual-rank-badge');
                        if (rankBadge) rankBadge.textContent = `#${data.new_rank} في الدليل`;
                    }
                } else if (action === 'toggle_schoplus') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-orange is-success-pulse';
                        el.innerHTML = '<i class="fas fa-award"></i> <span>ذكي</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_schoplus', 0, this)`);
                        showAdminToast('تم تفعيل شارة عميل SchoPlus وترقية ترتيب المدرسة 🌟', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-award"></i> <span>عادي</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_schoplus', 1, this)`);
                        showAdminToast('تم إلغاء شارة SchoPlus الذكي ℹ️', 'warning');
                    }
                    if (data.new_rank) {
                        const rankInput = document.getElementById('dir-rank-' + schoolId);
                        if (rankInput) rankInput.value = data.new_rank;
                        const rankBadge = el.closest('tr')?.querySelector('.actual-rank-badge');
                        if (rankBadge) rankBadge.textContent = `#${data.new_rank} في الدليل`;
                    }
                } else if (action === 'toggle_historic') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-blue is-success-pulse';
                        el.innerHTML = '<i class="fas fa-landmark"></i> <span>عريق</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_historic', 0, this)`);
                        showAdminToast('تم اعتماد المدرسة كصرح وطني عريق وترقيتها لصدارة الدليل 🏛️', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fas fa-landmark"></i> <span>حديث</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_historic', 1, this)`);
                        showAdminToast('تم إلغاء شارة الصرح العريق ℹ️', 'warning');
                    }
                    if (data.new_rank) {
                        const rankInput = document.getElementById('dir-rank-' + schoolId);
                        if (rankInput) rankInput.value = data.new_rank;
                        const rankBadge = el.closest('tr')?.querySelector('.actual-rank-badge');
                        if (rankBadge) rankBadge.textContent = `#${data.new_rank} في الدليل`;
                    }
                } else if (action === 'toggle_whatsapp_sent') {
                    if (newVal === 1) {
                        el.className = 'sp-toggle-pill toggle-on-green is-success-pulse';
                        el.innerHTML = '<i class="fab fa-whatsapp"></i> <span>تم الإرسال</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_whatsapp_sent', 0, this)`);
                        el.setAttribute('title', 'تم إرسال الرسالة - انقر لإلغاء العلامة');
                        showAdminToast('تم تحديد المدرسة: تم إرسال رسالة الواتساب بنجاح 📲', 'success');
                    } else {
                        el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                        el.innerHTML = '<i class="fab fa-whatsapp"></i> <span>لم يُرسل</span>';
                        el.setAttribute('onclick', `toggleSchoolMeta(${schoolId}, 'toggle_whatsapp_sent', 1, this)`);
                        el.setAttribute('title', 'لم يتم إرسال الرسالة بعد - انقر لتحديد أنه تم الإرسال');
                        showAdminToast('تم إلغاء علامة إرسال الواتساب للمدرسة ℹ️', 'warning');
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

    function copySchoolMessage(schoolId) {
        const el = document.getElementById('msg-text-' + schoolId);
        if (!el) return;
        const text = el.innerText || el.textContent;
        navigator.clipboard.writeText(text).then(() => {
            showAdminToast('تم نسخ نص الرسالة الترحيبية المنسق للحافظة بنجاح ✅ يمكنك لصقها الآن في محادثة المدرسة مباشرة', 'success');
        }).catch(err => {
            showAdminToast('تعذر النسخ التلقائي، يمكنك استخدام زر واتساب المباشر', 'warning');
        });
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

    let parsedBatchItems = [];

    function switchResearcherTab(tab) {
        const sStudio = document.getElementById('sectionStudioDiscovery');
        const sSingle = document.getElementById('sectionSingleDiscovery');
        const sBatch = document.getElementById('sectionBatchDiscovery');

        const bStudio = document.getElementById('tabBtnStudio');
        const bSingle = document.getElementById('tabBtnSingle');
        const bBatch = document.getElementById('tabBtnBatch');

        if (!sStudio || !sSingle || !sBatch) return;

        sStudio.style.display = (tab === 'studio') ? 'block' : 'none';
        sSingle.style.display = (tab === 'single') ? 'block' : 'none';
        sBatch.style.display = (tab === 'batch') ? 'block' : 'none';

        bStudio.className = (tab === 'studio') ? 'btn btn-sm px-3 py-1.5 fw-bold btn-primary text-white' : 'btn btn-sm px-3 py-1.5 fw-bold btn-outline-secondary';
        bSingle.className = (tab === 'single') ? 'btn btn-sm px-3 py-1.5 fw-bold btn-primary text-white' : 'btn btn-sm px-3 py-1.5 fw-bold btn-outline-secondary';
        bBatch.className = (tab === 'batch') ? 'btn btn-sm px-3 py-1.5 fw-bold btn-primary text-white' : 'btn btn-sm px-3 py-1.5 fw-bold btn-outline-secondary';
    }

    let currentWorkerDiscoveredSchools = [];
    let currentWorkerActiveFilter = 'all';
    let currentWorkerLastResultData = {};

    const sudanLocalitiesMap = <?= json_encode(SudanGeography::statesWithCities('السودان'), JSON_UNESCAPED_UNICODE) ?>;

    function onWorkerStateChange() {
        const st = document.getElementById('workerState').value.trim();
        const locSelect = document.getElementById('workerLocality');
        if (!locSelect) return;

        locSelect.innerHTML = '';
        if (!st || st === 'دول المهجر') {
            locSelect.innerHTML = '<option value="">🌐 كافة المحليات والمدن (مسح شامل)</option>';
            return;
        }

        const cities = sudanLocalitiesMap[st] || [];
        let opts = `<option value="">🌐 كافة محليات ولاية ${escapeHtml(st)} (${cities.length} محليات)</option>`;

        cities.forEach(c => {
            let details = '';
            if (st === 'الخرطوم') {
                if (c === 'الخرطوم') details = ' (وسط الخرطوم، العمارات، الرياض، المعمورة)';
                else if (c === 'أم درمان') details = ' (الملازمين، العباسية، ود نوباوي، المهندسين)';
                else if (c === 'بحري') details = ' (بحري وسط، الصافية، الشعبية، كافوري)';
                else if (c === 'شرق النيل') details = ' (حلة كوكو، الفيحاء، الردمية، الوادي الأخضر)';
                else if (c === 'كرري') details = ' (الثورات 1-100، الواحة، الفتح)';
                else if (c === 'أم بدة') details = ' (الحارات، دار السلام، البقعة)';
                else if (c === 'جبل أولياء') details = ' (الكلاكلات، الأزهري، السلمة)';
            }
            opts += `<option value="${escapeHtml(c)}">📍 محلية ${escapeHtml(c)}${details}</option>`;
        });

        locSelect.innerHTML = opts;
    }

    function toggleCardMapPin(idx) {
        const box = document.getElementById('card-map-embed-' + idx);
        if (!box) return;
        box.style.display = (box.style.display === 'none') ? 'block' : 'none';
    }

    function runAutonomousWorker() {
        const st = document.getElementById('workerState').value.trim();
        const loc = document.getElementById('workerLocality') ? document.getElementById('workerLocality').value.trim() : '';
        const typ = document.getElementById('workerSchoolType').value.trim();
        const cnt = document.getElementById('workerTargetCount').value.trim();
        const nameQ = document.getElementById('workerSchoolName').value.trim();

        const btn = document.getElementById('btnRunAutonomousWorker');
        const container = document.getElementById('workerResultsContainer');

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5 text-warning"></i> جاري استكشاف وجلب المدارس للمعاينة...';

        container.style.display = 'block';
        const locLabel = loc ? ` — محلية (${escapeHtml(loc)})` : '';
        container.innerHTML = `
            <div class="card border-0 shadow-sm rounded-3 p-4 text-center bg-white" style="border: 1px solid #DDE4ED !important;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.4rem; height: 2.4rem;"></div>
                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.96rem;">
                    جاري جلب واستكشاف المدارس في خرائط جوجل ${st ? 'لولاية (' + escapeHtml(st) + ')' + locLabel : 'لكافة الولايات'}...
                </h6>
                <div class="text-muted small" style="font-size: 0.74rem;">
                    يتم استخراج البيانات الميدانية والتحقق من الـ PIN والخرائط وإعدادها في قائمة المعاينة لمراجعتها يدوياً قبل الحفظ...
                </div>
            </div>
        `;

        const fd = new FormData();
        fd.append('ajax_action', 'run_autonomous_maps_worker');
        fd.append('state', st);
        fd.append('locality', loc);
        fd.append('school_type', typ);
        fd.append('target_count', cnt);
        fd.append('school_name', nameQ);
        fd.append('auto_register', 0); // دائماً صفر لجلبها للمعاينة فقط بدون حفظ مسبق

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (!data || !data.success) {
                container.innerHTML = `
                    <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                        <i class="fas fa-exclamation-triangle text-danger me-1"></i> ${escapeHtml(data.error || 'تعذر استكمال جلب المدارس.')}
                    </div>
                `;
                return;
            }

            currentWorkerLastResultData = data;
            currentWorkerDiscoveredSchools = data.schools || [];
            currentWorkerActiveFilter = 'all';
            renderWorkerResults(data);
            showAdminToast(`تم جلب (${currentWorkerDiscoveredSchools.length}) مدرسة إلى قائمة المعاينة للمراجعة والاستبعاد 📋`, 'info');
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            container.innerHTML = `
                <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
                </div>
            `;
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function removeCandidateSchool(idx) {
        const s = currentWorkerDiscoveredSchools[idx];
        const name = s ? s.name : 'المدرسة';
        currentWorkerDiscoveredSchools.splice(idx, 1);
        renderWorkerResults(currentWorkerLastResultData || {});
        showAdminToast(`تم استبعاد "${name}" من القائمة 🗑️`, 'warning');
    }

    function clearAllCandidates() {
        if (!confirm('هل تريد إلغاء وتفريغ قائمة المعاينة بالكامل؟')) return;
        currentWorkerDiscoveredSchools = [];
        const container = document.getElementById('workerResultsContainer');
        if (container) container.style.display = 'none';
        showAdminToast('تم تفريغ قائمة المعاينة ℹ️', 'info');
    }

    function saveCandidateSchool(idx, btn) {
        const s = currentWorkerDiscoveredSchools[idx];
        if (!s) return;

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

        fetch('admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ajax_action: 'quick_approve_discovered_school',
                name: s.name,
                state: s.state,
                city: s.city,
                district: s.district,
                school_type: s.school_type,
                phone: s.phone,
                google_map_url: s.google_map_url,
                facebook: s.facebook_url,
                description: s.description
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                s.registered = true;
                s.existing_id = data.school?.id || s.existing_id;
                s.slug = data.school?.slug || s.slug;
                if (data.total_schools_now) {
                    updateLiveSchoolCounters(data.total_schools_now);
                }
                showAdminToast(`تم حفظ وإدراج "${s.name}" في قاعدة البيانات وتزامنت مع السحابة بنجاح 🟢`, 'success');
                renderWorkerResults(currentWorkerLastResultData || {});
            } else {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                showAdminToast('فشل الحفظ: ' + (data.error || 'خطأ غير معروف'), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function updateLiveSchoolCounters(count) {
        if (!count) return;
        const formatted = Number(count).toLocaleString('en-US');
        document.querySelectorAll('.metric-val-total-schools, #tabSchoolsCountBadge, #syncHubLocalCount, #headerTotalSchoolsCount').forEach(el => {
            el.innerText = formatted;
        });
    }

    function saveAllRemainingCandidates() {
        const pending = currentWorkerDiscoveredSchools.filter(s => !s.registered);
        if (pending.length === 0) {
            alert('لا توجد مدارس جديدة بانتظار الحفظ.');
            return;
        }

        if (!confirm(`هل أنت متأكد من حفظ كافة المدارس المتبقية في القائمة (${pending.length} مدرسة) في قاعدة البيانات ورفعها للسحابة الآن؟`)) {
            return;
        }

        const btn = document.getElementById('btnSaveAllCandidates');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5"></i> جاري الحفظ والرفع للسحابة...';
        }

        fetch('admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ajax_action: 'batch_save_discovered_schools',
                schools: pending.map(s => ({
                    name: s.name,
                    state: s.state,
                    city: s.city,
                    district: s.district,
                    school_type: s.school_type,
                    phone: s.phone || '',
                    google_map_url: s.google_map_url || '',
                    facebook: s.facebook_url || '',
                    description: s.description || ''
                }))
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                pending.forEach(s => {
                    s.registered = true;
                });
                if (data.total_schools_now) {
                    updateLiveSchoolCounters(data.total_schools_now);
                }
                const savedNum = data.created || pending.length;
                const totalMsg = data.total_schools_now ? ` | إجمالي المدارس الآن: ${data.total_schools_now}` : '';
                showAdminToast(`تم حفظ وإدراج ${savedNum} مدرسة بنجاح في قاعدة البيانات وتزامنت مع السحابة ✅${totalMsg}`, 'success');
                renderWorkerResults(currentWorkerLastResultData || {});
            } else {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-1.5"></i> إعادة المحاولة';
                }
                showAdminToast('فشل الحفظ الجماعي: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1.5"></i> إعادة المحاولة';
            }
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function renderWorkerResults(data) {
        const container = document.getElementById('workerResultsContainer');
        const schools = currentWorkerDiscoveredSchools;
        const total = schools.length;
        const pendingCount = schools.filter(s => !s.registered).length;
        const savedCount = schools.filter(s => s.registered).length;

        if (total === 0) {
            container.innerHTML = `
                <div class="card border-0 shadow-sm rounded-3 p-4 text-center bg-white" style="border: 1px solid #DDE4ED !important;">
                    <i class="fas fa-inbox fa-3x text-muted mb-2 opacity-50"></i>
                    <h6 class="fw-bold text-dark mb-1">قائمة المعاينة فارغة</h6>
                    <div class="text-muted small mb-3">تم استبعاد كافة المدارس من القائمة أو لم يتم استكشاف نتائج جديدة.</div>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary px-3 py-1.5" onclick="runAutonomousWorker()">
                            <i class="fas fa-redo me-1"></i> إعادة البحث والاستكشاف
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        let bannerHtml = '';
        if (pendingCount > 0) {
            bannerHtml = `
                <div class="alert alert-warning border-0 shadow-sm p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3" style="background: #FEF3C7; color: #92400E; border-radius: 12px; border-right: 5px solid #F59E0B !important;">
                    <div>
                        <h6 class="fw-bold mb-1 d-flex align-items-center gap-2" style="font-size: 0.92rem;">
                            <i class="fas fa-list-check text-warning fs-5"></i>
                            <span>قائمة المعاينة جاهزة: لديك (${pendingCount}) مدرسة بانتظار المراجعة والحفظ</span>
                        </h6>
                        <div class="small opacity-90" style="font-size: 0.74rem;">
                            • يمكنك <strong>استبعاد أي مدرسة غير مناسبة</strong> بالضغط على زر "🗑️ استبعاد" باللون الأحمر لحذفها من القائمة.<br>
                            • عند الانتهاء من المراجعة، اضغط زر <strong>"💾 حفظ كافة المدارس المتبقية"</strong> لحفظها في القاعدة والسحابة دفعة واحدة.
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" id="btnSaveAllCandidates" class="btn btn-success fw-bold py-2.5 px-4 shadow-sm text-white" onclick="saveAllRemainingCandidates()" style="font-size: 0.85rem; border-radius: 8px; background: linear-gradient(135deg, #059669, #047857);">
                            <i class="fas fa-save me-1.5 text-warning"></i> 💾 حفظ كافة المدارس المتبقية في القاعدة والسحابة (${pendingCount})
                        </button>
                        <button type="button" class="btn btn-outline-secondary py-2 px-3 fw-bold" onclick="clearAllCandidates()" style="font-size: 0.78rem; border-radius: 8px;" title="إلغاء قائمة المعاينة">
                            <i class="fas fa-times me-1"></i> إلغاء القائمة
                        </button>
                    </div>
                </div>
            `;
        } else {
            bannerHtml = `
                <div class="alert alert-success border-0 shadow-sm p-3 mb-3 d-flex align-items-center gap-2.5" style="background: #DCFCE7; color: #166534; border-radius: 12px; border-right: 5px solid #10B981 !important;">
                    <i class="fas fa-check-circle fs-3 flex-shrink-0 text-success"></i>
                    <div>
                        <h6 class="fw-bold mb-0.5" style="font-size: 0.92rem;">✅ تم حفظ وإدراج كافة المدارس المعروضة (${savedCount} مدرسة) بنجاح في قاعدة البيانات وتزامنت مع السحابة!</h6>
                        <div class="small opacity-90" style="font-size: 0.74rem;">أصبحت المدارس الآن مسجلة ولها روابط وترتيب مباشر. يمكنك النقر على "إدارة المدرسة" في أي بطاقة أدناه لتعديل أي تفاصيل أو إضافة معلومات.</div>
                    </div>
                </div>
            `;
        }

        let html = `
            ${bannerHtml}
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden" style="border: 1px solid #DDE4ED !important;">
                <div class="card-header bg-white p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="badge bg-primary text-white py-1 px-2.5" style="font-size: 0.74rem;">
                            <i class="fas fa-clipboard-list me-1"></i> المدارس في قائمة المعاينة (${total} مؤسسة)
                        </span>
                        <span class="text-muted small ms-2" style="font-size: 0.72rem;">
                            (متبقي للحفظ: <strong>${pendingCount}</strong> | تم حفظها بالفعل: <strong>${savedCount}</strong>)
                        </span>
                    </div>

                    ${pendingCount > 0 ? `
                        <button type="button" class="btn btn-sm btn-success fw-bold py-1.5 px-3 text-white shadow-2xs" onclick="saveAllRemainingCandidates()" style="font-size: 0.78rem; border-radius: 6px;">
                            <i class="fas fa-save me-1"></i> حفظ المتبقي (${pendingCount})
                        </button>
                    ` : ''}
                </div>

                <div class="card-body p-3 p-md-3.5 bg-light">
                    <div class="row g-3" id="workerCardsGrid">
        `;

        schools.forEach((s, idx) => {
            const sTypeBadge = (s.school_type === 'kindergarten') ? 'روضة / حضانة' : ((s.school_type === 'government') ? 'حكومية' : 'خاصة');
            const sTypeColor = (s.school_type === 'kindergarten') ? '#8B5CF6' : ((s.school_type === 'government') ? '#0B2A52' : '#1E5FA8');
            const schId = s.existing_id || s.id;

            let statusBadge = '';
            let actionBtns = '';

            if (s.registered) {
                statusBadge = `<span class="badge bg-success text-white py-1 px-2" style="font-size: 0.68rem;"><i class="fas fa-check-circle me-1"></i> محفوظة ومسجلة في القاعدة ✅</span>`;
                actionBtns = `
                    <div class="d-flex align-items-center gap-1.5">
                        ${schId ? `<a href="admin.php?tab=manage_school&id=${schId}" class="btn btn-sm btn-primary py-1 px-2.5 fw-bold text-white shadow-2xs" style="font-size: 0.72rem; border-radius: 6px;"><i class="fas fa-edit me-1"></i> إدارة المدرسة</a>` : ''}
                        <a href="https://daleel.schoplus.sd/school/${encodeURIComponent(s.slug)}" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.72rem; border-radius: 6px;" title="عرض المدرسة في الدليل السحابي الحي"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                `;
            } else {
                statusBadge = `<span class="badge bg-warning text-dark py-1 px-2" style="font-size: 0.68rem;"><i class="fas fa-clock me-1"></i> بانتظار المراجعة والحفظ ⏳</span>`;
                actionBtns = `
                    <div class="d-flex align-items-center gap-1.5">
                        <button type="button" class="btn btn-sm btn-success text-white fw-bold py-1 px-3 shadow-2xs d-inline-flex align-items-center gap-1" onclick="saveCandidateSchool(${idx}, this)" style="font-size: 0.74rem; border-radius: 6px;">
                            <i class="fas fa-save"></i> حفظ في القاعدة
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2.5 fw-bold d-inline-flex align-items-center gap-1" onclick="removeCandidateSchool(${idx})" style="font-size: 0.74rem; border-radius: 6px;" title="استبعاد وحذف هذه المدرسة من قائمة المعاينة">
                            <i class="fas fa-trash-alt"></i> استبعاد
                        </button>
                    </div>
                `;
            }

            html += `
                <div class="col-lg-6 col-12 worker-card-item" id="worker-card-${idx}">
                    <div class="card h-100 border-0 shadow-sm rounded-3 bg-white p-3" style="border: 1px solid #E2E8F0 !important;">
                        <!-- Top Info -->
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="d-flex align-items-center gap-1.5 mb-1 flex-wrap">
                                    <span class="badge text-white" style="background: ${sTypeColor}; font-size: 0.64rem;">${sTypeBadge}</span>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.64rem;">${s.curriculum === 'british' ? 'بريطاني' : 'سوداني'}</span>
                                    ${statusBadge}
                                </div>
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 0.92rem;">${escapeHtml(s.name)}</h6>
                                <div class="text-muted small" style="font-size: 0.7rem;">${escapeHtml(s.name_en || '')}</div>
                            </div>
                            <div class="text-end">
                                ${actionBtns}
                            </div>
                        </div>

                        <!-- Geography & Contact -->
                        <div class="p-2 rounded-2 mb-2" style="background: #F8FAFC; border: 1px solid #F1F5F9; font-size: 0.72rem;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-1">
                                <span class="text-dark fw-bold"><i class="fas fa-map-marker-alt text-danger me-1"></i> ${escapeHtml(s.state)} — ${escapeHtml(s.city)} (${escapeHtml(s.district || s.city)})</span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="تم التحقق من وجود دبوس جغرافي في خرائط جوجل"><i class="fas fa-map-pin me-1"></i> PIN خرائط جوجل 📍</span>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1">
                                <div class="text-muted small text-truncate" title="${escapeHtml(s.address || '')}">
                                    <i class="fas fa-location-arrow text-primary me-1"></i> ${escapeHtml(s.address || s.city)}
                                </div>
                                ${s.phone ? `
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-phone-alt me-1"></i> ${escapeHtml(s.phone)}</span>
                                ` : `
                                    <span class="badge bg-light text-muted border"><i class="fas fa-phone-slash me-1"></i> بدون هاتف</span>
                                `}
                            </div>
                        </div>

                        <!-- Platform Links & PIN Verification Toolbar -->
                        <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                            ${s.google_map_url ? `
                                <a href="${s.google_map_url}" target="_blank" class="btn btn-sm btn-light border py-1 px-2.5 text-danger fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;" title="فتح الدبوس في تطبيق خرائط جوجل">
                                    <i class="fas fa-map-pin text-danger"></i> فتح الـ PIN بالخرائط ↗
                                </a>
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 text-primary fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;" onclick="toggleCardMapPin(${idx})" title="معاينة موقع الدبوس على الخريطة التفاعلية مباشرة">
                                    <i class="fas fa-eye text-primary"></i> 🗺️ معاينة الخريطة
                                </button>
                            ` : ''}
                            ${s.facebook_url ? `
                                <a href="${s.facebook_url}" target="_blank" class="btn btn-sm btn-light border py-1 px-2 text-primary fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;" title="بحث فيسبوك">
                                    <i class="fab fa-facebook"></i> فيسبوك ↗
                                </a>
                            ` : ''}
                        </div>

                        <!-- Embedded Interactive Map PIN Preview (Collapsible) -->
                        <div id="card-map-embed-${idx}" class="mb-2 rounded-2 overflow-hidden border shadow-2xs" style="display: none; height: 170px; background: #E2E8F0;">
                            <iframe src="${s.embed_map_url || ('https://maps.google.com/maps?q=' + encodeURIComponent(s.name + ' ' + (s.district || s.city) + ' ' + s.state + ' السودان') + '&t=&z=15&ie=UTF8&iwloc=&output=embed')}" width="100%" height="170" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>

                        <!-- Description Synopsis -->
                        <div class="text-muted small mt-auto" style="font-size: 0.7rem; line-height: 1.45; max-height: 38px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            ${escapeHtml(s.description || '')}
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    function runStudioResearch() {
        const q = document.getElementById('studioQuery').value.trim();
        const ct = document.getElementById('studioCity').value.trim();
        const btn = document.getElementById('btnRunStudio');
        const box = document.getElementById('studioDossierBox');

        if (!q) {
            alert('يرجى إدخال اسم المدرسة للبدء في الاستقصاء الشامل.');
            return;
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الاستقصاء عبر المنصات...';

        box.style.display = 'block';
        box.innerHTML = `
            <div class="p-4 bg-white rounded-3 border text-center shadow-sm">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.2rem; height: 2.2rem;"></div>
                <h6 class="fw-bold text-dark" style="font-size: 0.95rem;">جاري الاستقصاء الرقمي الشامل لـ "${escapeHtml(q)}"...</h6>
                <div class="text-muted small" style="font-size: 0.74rem;">
                    يتم الآن مسح إحداثيات خرائط جوجل، والبحث في صفحات فيسبوك، يوتيوب، تيك توك ومراجع الشهادة السودانية...
                </div>
            </div>
        `;

        fetch('api_deep_research.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: q, city: ct })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (!data || !data.success) {
                box.innerHTML = `
                    <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                        <i class="fas fa-exclamation-triangle text-danger me-1"></i> ${escapeHtml(data.error || 'تعذر إتمام الاستقصاء.')}
                    </div>
                `;
                return;
            }

            renderStudioDossier(data);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            box.innerHTML = `
                <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
                </div>
            `;
        });
    }

    function renderStudioDossier(d) {
        const box = document.getElementById('studioDossierBox');
        const p = d.platforms;

        let html = `
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden" style="border: 1px solid #DDE4ED !important;">
                <!-- Dossier Header -->
                <div class="card-header bg-white p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="badge bg-primary text-white mb-1" style="font-size: 0.68rem;"><i class="fas fa-folder-open me-1"></i> ملف الاستقصاء الرقمي المكتشف</span>
                        <h5 class="fw-bold text-dark mb-0" style="font-size: 1.05rem;">${escapeHtml(d.formatted_name)}</h5>
                        <div class="text-muted small" style="font-size: 0.72rem;">${escapeHtml(d.formatted_name_en)}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-1.5 align-items-center">
                        <span class="badge bg-light text-dark border"><i class="fas fa-map-marker-alt text-danger me-1"></i> ${escapeHtml(d.state)} — ${escapeHtml(d.city)} (${escapeHtml(d.district)})</span>
                        <span class="badge" style="background: #EFF6FF; color: #1E5FA8; border: 1px solid #BFDBFE;">${d.curriculum === 'british' ? 'منهج بريطاني' : 'منهج سوداني'}</span>
                        ${d.exists_in_db ? `<span class="badge bg-warning text-dark"><i class="fas fa-database me-1"></i> مسجلة بالدليل (#${d.existing_rank || d.existing_id})</span> <a href="/school.php?slug=${encodeURIComponent(d.slug || d.existing_id)}" target="_blank" class="btn btn-sm btn-outline-dark py-0 px-2 shadow-none" style="font-size: 0.68rem;"><i class="fas fa-external-link-alt me-1"></i> فتح الصفحة بالدليل</a>` : `<span class="badge bg-success text-white"><i class="fas fa-sparkles me-1"></i> مدرسة جديدة</span>`}
                    </div>
                </div>

                <div class="card-body p-3 p-md-4">
                    <!-- 1. DEDICATED GOOGLE MAPS GROUNDING SECTION -->
                    <div class="p-3 rounded-3 border mb-3" style="background: #F0FDF4; border-color: #BBF7D0 !important;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <span class="fw-bold text-success" style="font-size: 0.84rem;">
                                <i class="fas fa-map-marked-alt me-1"></i> التحقق الجغرافي الميداني من خرائط جوجل (Google Maps Grounding):
                            </span>
                            <a href="${p.google_maps.search_url}" target="_blank" class="btn btn-sm btn-success fw-bold py-1 px-3 shadow-sm text-white" style="font-size: 0.74rem; border-radius: 6px;">
                                <i class="fas fa-external-link-alt me-1"></i> فتح الخريطة بكامل الشاشة في Google Maps ↗
                            </a>
                        </div>
                        
                        <!-- Interactive Embedded Map Preview -->
                        <div class="rounded-3 overflow-hidden border shadow-2xs mb-2" style="height: 200px; background: #E2E8F0;">
                            <iframe src="https://maps.google.com/maps?q=${encodeURIComponent(d.formatted_name + ' ' + d.city + ' ' + d.state)}&output=embed" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>

                        <div>
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fas fa-map-pin text-danger me-1"></i> رابط موقع المدرسة على خرائط جوجل (Google Maps Link):
                            </label>
                            <input type="url" id="dosGmap" class="form-control form-control-sm" placeholder="الصق الرابط الفعلي من خرائط جوجل إن توفر (مثال: https://maps.app.goo.gl/...)" value="${escapeHtml(p.google_maps.existing_url || '')}" style="font-size: 0.78rem;">
                        </div>
                    </div>

                    <!-- 2. OTHER PLATFORMS FOR DEEP ENRICHMENT -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-2"><i class="fas fa-share-nodes text-primary me-1"></i> استقصاء المنصات الرقمية ومراجع الشهادة (للهواتف والنبذة):</label>
                        <div class="row g-2">
                            <div class="col-md-3 col-sm-6 col-12">
                                <a href="${p.facebook.search_url}" target="_blank" class="btn btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-1.5 text-white fw-bold shadow-sm" style="background: #1877F2; font-size: 0.74rem; border-radius: 6px;">
                                    <i class="${p.facebook.icon}"></i> صفحة فيسبوك
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <a href="${p.youtube.search_url}" target="_blank" class="btn btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-1.5 text-white fw-bold shadow-sm" style="background: #DC2626; font-size: 0.74rem; border-radius: 6px;">
                                    <i class="${p.youtube.icon}"></i> فيديوهات يوتيوب
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <a href="${p.tiktok.search_url}" target="_blank" class="btn btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-1.5 text-white fw-bold shadow-sm" style="background: #111827; font-size: 0.74rem; border-radius: 6px;">
                                    <i class="${p.tiktok.icon}"></i> جولات تيك توك
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <a href="${p.web_references.search_url}" target="_blank" class="btn btn-sm w-100 py-2 d-flex align-items-center justify-content-center gap-1.5 text-white fw-bold shadow-sm" style="background: #0284C7; font-size: 0.74rem; border-radius: 6px;">
                                    <i class="${p.web_references.icon}"></i> مراجع غوغل والشهادة
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 3. EDITABLE DOSSIER FORM -->
                    <div class="p-3 bg-light rounded-3 border">
                        <h6 class="fw-bold text-dark mb-3" style="font-size: 0.84rem;"><i class="fas fa-check-circle text-success me-1"></i> مراجعة واعتماد بيانات المدرسة قبل الحفظ بالدليل:</h6>
                        
                        <div class="row g-2.5">
                            <div class="col-lg-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1">الاسم المعتمد في الدليل</label>
                                <input type="text" id="dosName" class="form-control form-control-sm" value="${escapeHtml(d.formatted_name)}">
                            </div>

                            <div class="col-lg-3 col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1">الهاتف الموثق (إن وجد بالخريطة أو فيسبوك)</label>
                                <input type="text" id="dosPhone" class="form-control form-control-sm" placeholder="فارغ إن لم يتوفر بالخريطة" value="${escapeHtml(d.phone || '')}">
                            </div>

                            <div class="col-lg-3 col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1">الواتساب</label>
                                <input type="text" id="dosWhatsapp" class="form-control form-control-sm" placeholder="فارغ إن لم يتوفر" value="${escapeHtml(d.whatsapp || '')}">
                            </div>

                            <div class="col-lg-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1"><i class="fab fa-facebook text-primary me-1"></i> رابط صفحة الفيسبوك الرسمية</label>
                                <input type="url" id="dosFacebook" class="form-control form-control-sm" placeholder="https://facebook.com/..." value="${escapeHtml(p.facebook.existing_url || '')}">
                            </div>

                            <div class="col-lg-3 col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1"><i class="fab fa-youtube text-danger me-1"></i> قناة / فيديو يوتيوب</label>
                                <input type="url" id="dosYoutube" class="form-control form-control-sm" placeholder="https://youtube.com/..." value="${escapeHtml(p.youtube.existing_url || '')}">
                            </div>

                            <div class="col-lg-3 col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1"><i class="fab fa-tiktok text-dark me-1"></i> حساب تيك توك</label>
                                <input type="url" id="dosTiktok" class="form-control form-control-sm" placeholder="https://tiktok.com/@..." value="${escapeHtml(p.tiktok.existing_url || '')}">
                            </div>

                            <div class="col-lg-4 col-sm-6 col-12">
                                <label class="form-label small fw-bold text-dark mb-1">سنة التأسيس (اختياري)</label>
                                <input type="text" id="dosYear" class="form-control form-control-sm" placeholder="مثال: 2012" value="${escapeHtml(d.established_year || '')}">
                            </div>

                            <div class="col-lg-8 col-12">
                                <label class="form-label small fw-bold text-dark mb-1"><i class="fas fa-align-left text-muted me-1"></i> النبذة التعريفية الشاملة عن المدرسة</label>
                                <textarea id="dosDesc" class="form-control form-control-sm" rows="2">${escapeHtml(d.description || '')}</textarea>
                            </div>
                        </div>

                        <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="text-muted small" style="font-size: 0.72rem;">
                                <i class="fas fa-shield-alt text-success me-1"></i> خالية من أي أرقام هواتف وهمية، موثقة جغرافياً ورقمياً.
                            </span>
                            <button type="button" id="btnCommitDossier" class="btn btn-success fw-bold px-4 py-2 text-white shadow-sm" onclick="commitStudioDossier('${escapeHtml(d.state)}', '${escapeHtml(d.city)}', '${escapeHtml(d.district)}', '${escapeHtml(d.school_type)}')" style="font-size: 0.84rem;">
                                <i class="fas fa-check-circle me-1"></i> اعتماد وحفظ بيانات المدرسة في الدليل
                            </button>
                        </div>
                    </div>

                    <div id="dossierSaveStatus" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        `;

        box.innerHTML = html;
    }

    function commitStudioDossier(state, city, district, schoolType) {
        const name = document.getElementById('dosName').value.trim();
        const phone = document.getElementById('dosPhone').value.trim();
        const whatsapp = document.getElementById('dosWhatsapp').value.trim();
        const gmap = document.getElementById('dosGmap').value.trim();
        const fb = document.getElementById('dosFacebook').value.trim();
        const yt = document.getElementById('dosYoutube').value.trim();
        const tt = document.getElementById('dosTiktok').value.trim();
        const yr = document.getElementById('dosYear').value.trim();
        const desc = document.getElementById('dosDesc').value.trim();

        const btn = document.getElementById('btnCommitDossier');
        const stBox = document.getElementById('dossierSaveStatus');

        if (!name) {
            alert('يرجى تحديد اسم المدرسة.');
            return;
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ والاعتماد بالدليل...';

        const fd = new FormData();
        fd.append('query', name);
        fd.append('state', state);
        fd.append('city', city);
        fd.append('district', district);
        fd.append('school_type', schoolType);
        fd.append('phone', phone);
        fd.append('whatsapp', whatsapp || phone);
        fd.append('google_map_url', gmap);
        fd.append('facebook', fb);
        fd.append('youtube', yt);
        fd.append('tiktok', tt);
        fd.append('established_year', yr);
        fd.append('description', desc);

        fetch('api_auto_discover.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            stBox.style.display = 'block';

            if (data && data.success) {
                const sch = data.school;
                showAdminToast(data.message || 'تم اعتماد وحفظ المدرسة بنجاح ✅', 'success');
                stBox.innerHTML = `
                    <div class="alert alert-success p-3 rounded-3 shadow-sm border mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-bold text-dark mb-0.5"><i class="fas fa-check-circle text-success me-1"></i> ${escapeHtml(data.message || 'تم اعتماد وحفظ المدرسة بنجاح!')}</div>
                            <div class="text-muted small">أصبحت المدرسة مفهرسة في دليل المدارس السودانية برتبة رسمية.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="${sch.url}" target="_blank" class="btn btn-sm btn-primary fw-bold px-3 py-1.5" style="font-size: 0.76rem;">
                                <i class="fas fa-external-link-alt me-1"></i> عرض صفحة المدرسة بالدليل
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('studioQuery').value=''; document.getElementById('studioDossierBox').style.display='none';" style="font-size: 0.76rem;">
                                استقصاء مدرسة أخرى
                            </button>
                        </div>
                    </div>
                `;
            } else {
                stBox.innerHTML = `
                    <div class="alert alert-danger p-3 rounded-3 shadow-sm border mb-0">
                        <i class="fas fa-exclamation-triangle text-danger me-1"></i> ${escapeHtml(data.error || 'حدث خطأ أثناء الحفظ.')}
                    </div>
                `;
                showAdminToast('فشل الحفظ: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            stBox.style.display = 'block';
            stBox.innerHTML = `
                <div class="alert alert-danger p-3 rounded-3 shadow-sm border mb-0">
                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
                </div>
            `;
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function runAutoDiscover() {
        const q = document.getElementById('discQuery').value.trim();
        const gmap = document.getElementById('discGoogleMapUrl').value.trim();
        const phone = document.getElementById('discPhone').value.trim();
        const st = document.getElementById('discState').value.trim();
        const ct = document.getElementById('discCity').value.trim();
        const dist = document.getElementById('discDistrict').value.trim();
        const fb = document.getElementById('discFacebook').value.trim();
        const desc = document.getElementById('discDesc').value.trim();

        const btn = document.getElementById('btnRunDiscovery');
        const resBox = document.getElementById('discoveryResultBox');

        if (!q) {
            alert('يرجى كتابة اسم المدرسة للبدء.');
            return;
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري التدقيق والاعتماد...';

        resBox.style.display = 'block';
        resBox.innerHTML = `
            <div class="p-3 bg-white rounded-3 border text-center shadow-sm">
                <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
                <div class="fw-bold text-dark" style="font-size: 0.82rem;">جاري التحقق الجغرافي وفهرسة بيانات "${escapeHtml(q)}"...</div>
                <div class="text-muted small" style="font-size: 0.72rem;">يتم الآن فحص عدم التكرار، ربط خرائط جوجل، وتحديث الرتبة...</div>
            </div>
        `;

        const fd = new FormData();
        fd.append('query', q);
        fd.append('google_map_url', gmap);
        fd.append('phone', phone);
        fd.append('state', st);
        fd.append('city', ct);
        fd.append('district', dist);
        fd.append('facebook', fb);
        fd.append('description', desc);

        fetch('api_auto_discover.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (data && data.success) {
                const sch = data.school;
                const isExisting = data.already_exists;
                const alertClass = isExisting ? 'alert-warning' : 'alert-success';
                const icon = isExisting ? 'fa-info-circle text-warning' : 'fa-check-circle text-success';
                const badgeText = isExisting ? 'مدرسة مسجلة سلفاً (تم تحديث البيانات)' : 'مدرسة معتمدة ومضافة حديثاً';

                showAdminToast(data.message || 'تم الاعتماد بنجاح ✅', 'success');

                resBox.innerHTML = `
                    <div class="alert ${alertClass} p-3 rounded-3 shadow-sm border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas ${icon} fs-5"></i>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">${escapeHtml(data.message || 'اكتملت العملية بنجاح!')}</h6>
                            </div>
                            <span class="badge ${isExisting ? 'bg-warning text-dark' : 'bg-success text-white'}">${badgeText}</span>
                        </div>

                        <div class="p-3 bg-white rounded-3 border mt-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-7 col-12">
                                    <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">${escapeHtml(sch.name)}</h5>
                                    <div class="text-muted small mb-2" style="font-size: 0.74rem;">${escapeHtml(sch.name_en || '')}</div>
                                    <div class="d-flex flex-wrap gap-2 small">
                                        <span class="badge bg-light text-dark border"><i class="fas fa-map-marker-alt text-danger me-1"></i> ${escapeHtml(sch.state)} — ${escapeHtml(sch.city)}</span>
                                        <span class="badge bg-light text-dark border"><i class="fas fa-building text-primary me-1"></i> حي ${escapeHtml(sch.district || sch.city)}</span>
                                        ${sch.phone ? `<span class="badge bg-success text-white"><i class="fas fa-phone me-1"></i> ${escapeHtml(sch.phone)}</span>` : `<span class="badge bg-secondary text-white"><i class="fas fa-phone-slash me-1"></i> بدون هاتف مسجل</span>`}
                                    </div>
                                </div>
                                <div class="col-md-5 col-12 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                                    <a href="${sch.url}" target="_blank" class="btn btn-sm btn-primary py-2 px-3 fw-bold" style="background: #1E5FA8; font-size: 0.78rem;">
                                        <i class="fas fa-external-link-alt me-1"></i> فتح صفحة المدرسة
                                    </a>
                                    ${sch.google_map_url ? `<a href="${sch.google_map_url}" target="_blank" class="btn btn-sm btn-light border py-2 px-3" style="font-size: 0.78rem;"><i class="fas fa-map-marked-alt text-success me-1"></i> خرائط جوجل</a>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                resBox.innerHTML = `
                    <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-triangle text-danger fs-5"></i>
                            <span class="fw-bold" style="font-size: 0.85rem;">${escapeHtml(data.error || 'تعذر استكشاف المدرسة.')}</span>
                        </div>
                    </div>
                `;
                showAdminToast('فشل: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            resBox.innerHTML = `
                <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                    <i class="fas fa-exclamation-triangle text-danger me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
                </div>
            `;
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function previewBatchList() {
        const raw = document.getElementById('batchInputText').value.trim();
        const prevBox = document.getElementById('batchPreviewBox');
        const summarySpan = document.getElementById('batchParsedSummary');
        const btnExecute = document.getElementById('btnExecuteBatch');

        if (!raw) {
            alert('يرجى لصق قائمة المدارس أولاً للمعاينة.');
            return;
        }

        const lines = raw.split('\n');
        parsedBatchItems = [];

        lines.forEach((line) => {
            const cleanLine = line.trim();
            if (!cleanLine || cleanLine.startsWith('#')) return;

            const parts = cleanLine.split('|').map(p => p.trim());
            if (parts.length >= 1 && parts[0].length >= 2) {
                parsedBatchItems.push({
                    name: parts[0],
                    state: parts[1] || '',
                    city: parts[2] || '',
                    district: parts[3] || '',
                    phone: parts[4] || '',
                    google_map_url: parts[5] || '',
                    facebook: parts[6] || '',
                    description: parts[7] || ''
                });
            }
        });

        if (parsedBatchItems.length === 0) {
            prevBox.style.display = 'none';
            btnExecute.disabled = true;
            summarySpan.innerHTML = '<span class="text-danger">لم يتم العثور على أسطر صالحة للتحليل.</span>';
            return;
        }

        btnExecute.disabled = false;
        summarySpan.innerHTML = `<span class="text-success fw-bold">تم تحليل ${parsedBatchItems.length} مدرسة بنجاح</span>`;

        let html = `
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mt-3" style="border: 1px solid #DDE4ED !important;">
                <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.84rem;"><i class="fas fa-list-check text-primary me-1"></i> جدول معاينة الدفعة (${parsedBatchItems.length} مؤسسة)</h6>
                    <span class="badge bg-light text-dark border">جاهزة للاعتماد</span>
                </div>
                <div class="table-responsive" style="max-height: 280px;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.72rem;">
                        <thead style="background: #F8FAFC; color: #64748B;">
                            <tr>
                                <th>#</th>
                                <th>اسم المدرسة</th>
                                <th>الموقع</th>
                                <th>الحي</th>
                                <th>الهاتف الموثق</th>
                                <th>خرائط جوجل</th>
                                <th>فيسبوك</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        parsedBatchItems.forEach((item, i) => {
            html += `
                <tr>
                    <td class="fw-bold text-muted">${i + 1}</td>
                    <td class="fw-bold text-dark">${escapeHtml(item.name)}</td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(item.state || 'تلقائي')}</span> ${item.city ? `— ${escapeHtml(item.city)}` : ''}</td>
                    <td>${escapeHtml(item.district || '-')}</td>
                    <td>${item.phone ? `<span class="badge bg-success text-white">${escapeHtml(item.phone)}</span>` : `<span class="text-muted">بدون هاتف</span>`}</td>
                    <td>${item.google_map_url ? `<a href="${item.google_map_url}" target="_blank" class="text-success"><i class="fas fa-map-pin"></i> نعم</a>` : `<span class="text-muted">تلقائي</span>`}</td>
                    <td>${item.facebook ? `<a href="${item.facebook}" target="_blank" class="text-primary"><i class="fab fa-facebook"></i> نعم</a>` : `<span class="text-muted">-</span>`}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        prevBox.style.display = 'block';
        prevBox.innerHTML = html;
    }

    function executeBatchImport() {
        if (!parsedBatchItems || parsedBatchItems.length === 0) {
            alert('لا توجد عناصر مفحوصة للإدراج.');
            return;
        }

        const btn = document.getElementById('btnExecuteBatch');
        const prevBox = document.getElementById('batchPreviewBox');

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري إدراج الدفعة بالدليل...';

        fetch('api_auto_discover.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'batch',
                schools: parsedBatchItems
            })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (data && data.success) {
                showAdminToast(`تم إدراج الدفعة بنجاح: ${data.created} مدرسة جديدة، و${data.existing} مدرسة سابقة ✅`, 'success');
                prevBox.innerHTML = `
                    <div class="alert alert-success p-3 rounded-3 shadow-sm border mt-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fas fa-check-circle text-success fs-4"></i>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">تم إدراج الدفعة بنجاح!</h6>
                        </div>
                        <div class="small text-muted mb-2">
                            تمت إضافة <strong>${data.created}</strong> مدرسة جديدة، وتحديث <strong>${data.existing}</strong> مدرسة سابقة.
                        </div>
                        <div class="mt-2">
                            <a href="admin.php?tab=schools" class="btn btn-sm btn-primary py-1 px-3" style="font-size: 0.76rem;">
                                <i class="fas fa-school me-1"></i> فتح قائمة المدارس بالإدارة
                            </a>
                        </div>
                    </div>
                `;
                document.getElementById('batchInputText').value = '';
                parsedBatchItems = [];
            } else {
                alert(data.error || 'حدث خطأ أثناء استيراد الدفعة.');
                showAdminToast('فشل استيراد الدفعة: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            alert('حدث خطأ أثناء الاتصال بالخادم.');
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ─── WHATSAPP GATEWAY JS INTEGRATION ────────────────────────────
    function updateWaWidgetStatus() {
        const fd = new FormData();
        fd.append('ajax_action', 'get_whatsapp_server_status');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const btn = document.getElementById('btnWaGatewayWidget');
            const txt = document.getElementById('waWidgetStatusText');
            if (btn && txt) {
                if (data.status === 'cloud_hub') {
                    btn.style.background = '#1E3A8A';
                    btn.style.borderColor = '#2563EB';
                    txt.innerHTML = '☁️ مركز الواتساب (المكتب المحلي)';
                } else if (data.status === 'connected') {
                    btn.style.background = '#0F5C3E';
                    btn.style.borderColor = '#166534';
                    txt.innerHTML = `🟢 واتساب متصل ${data.phone ? '(+' + data.phone + ')' : ''}`;
                } else if (data.status === 'qr_waiting') {
                    btn.style.background = '#D97706';
                    btn.style.borderColor = '#B45309';
                    txt.innerHTML = '⏳ امسح رمز QR للربط';
                } else {
                    btn.style.background = '#991B1B';
                    btn.style.borderColor = '#7F1D1D';
                    txt.innerHTML = '🔴 سيرفر الواتساب غير متصل';
                }
            }

            // تحديث عناصر تبويب الواتساب المدمج داخل لوحة التحكم إن كان مفتوحاً
            renderTabWaStatus(data);
        })
        .catch(err => {
            const txt = document.getElementById('waWidgetStatusText');
            if (txt) txt.innerHTML = '🔴 تعذر الاتصال بالسيرفر';
        });
    }

    function renderTabWaStatus(data) {
        const val = document.getElementById('waTabStatusVal');
        const phoneVal = document.getElementById('waTabPhoneVal');
        const badge = document.getElementById('waTabStatusBadge');
        const devPhone = document.getElementById('waTabDevicePhone');
        const qrWrap = document.getElementById('waTabQrWrap');

        if (!val || !qrWrap) return;

        if (data.status === 'cloud_hub') {
            val.style.color = '#2563EB';
            val.innerHTML = '☁️ مُدار محلياً';
            if (phoneVal) phoneVal.innerHTML = 'المكتب المحلي (Local Station)';
            if (badge) {
                badge.style.background = '#EFF6FF';
                badge.style.color = '#1E40AF';
                badge.innerHTML = '☁️ المراسلة الآلية المركزية مُدارة عبر المحطة المحلية مع إمكانية المزامنة بنقرة واحدة';
            }
            if (devPhone) devPhone.innerHTML = 'السيرفر الميداني: <b>localhost:8085 (مزامنة السحابة مفعلة)</b>';
            qrWrap.innerHTML = `
                <div class="py-3 px-2 text-center">
                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-2"></i>
                    <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">المراسلة الآلية عبر محطة العمل المحلية</div>
                    <div class="text-muted small mb-3" style="font-size: 0.73rem;">لضمان أعلى استقرار ومنع حظر الجلسات، يتم الإرسال الجماعي الآلي من المحطة المحلية وتتزامن حالات الرسائل وسجلات التواصل فورياً مع السحابة.</div>
                    <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5" onclick="openCloudSyncModal()" style="font-size: 0.76rem; border-radius: 6px;">
                        <i class="fas fa-sync-alt me-1"></i> فتح مركز المزامنة السحابية
                    </button>
                </div>
            `;
        } else if (data.status === 'connected') {
            val.style.color = '#059669';
            val.innerHTML = '🟢 متصل ونشط';
            if (phoneVal) phoneVal.innerHTML = data.phone ? ('+' + data.phone) : 'حساب نشط';
            if (badge) {
                badge.style.background = '#DCFCE7';
                badge.style.color = '#166534';
                badge.innerHTML = '✅ متصل وجاهز للإرسال والرد التفاعلي 24/7';
            }
            if (devPhone) devPhone.innerHTML = 'رقم الحساب المتصل: <b>+' + (data.phone || 'نشط') + '</b>';
            qrWrap.innerHTML = '<div class="py-4 text-success fw-bold"><i class="fas fa-check-circle fa-4x mb-2 text-success"></i><br>حساب الواتساب مربوط بنجاح</div>';
        } else if (data.status === 'qr_waiting' && data.qrcode) {
            val.style.color = '#D97706';
            val.innerHTML = '⏳ بانتظار مسح QR';
            if (phoneVal) phoneVal.innerHTML = 'افتح واتساب لمسح الكود';
            if (badge) {
                badge.style.background = '#FEF3C7';
                badge.style.color = '#92400E';
                badge.innerHTML = '⏳ امسح رمز الاستجابة السريعة (QR Code) أدناه بهاتفك:';
            }
            if (devPhone) devPhone.innerHTML = 'واتساب > الأجهزة المرتبطة > ربط جهاز';
            qrWrap.innerHTML = '<img src="' + data.qrcode + '" style="max-width: 220px; border-radius: 10px; border: 2px dashed #CBD5E1; padding: 6px;">';
        } else if (['PAIRING', 'SYNCING', 'init', 'loading'].includes(data.status)) {
            val.style.color = '#D97706';
            val.innerHTML = '⏳ جاري الاتصال (' + data.status + ')...';
            if (phoneVal) phoneVal.innerHTML = 'ثوانٍ قليلة ويكتمل الربط...';
            if (badge) {
                badge.style.background = '#FEF3C7';
                badge.style.color = '#92400E';
                badge.innerHTML = '⏳ جاري مزامنة بيانات الحساب والاتصال...';
            }
            if (devPhone) devPhone.innerHTML = 'جاري المزامنة مع الهاتف';
            qrWrap.innerHTML = '<div class="py-4 text-center text-warning fw-bold"><i class="fas fa-spinner fa-spin fa-3x mb-2"></i><br>جاري مزامنة جلسة الواتساب...</div>';
        } else {
            val.style.color = '#DC2626';
            val.innerHTML = '🔴 غير متصل';
            if (phoneVal) phoneVal.innerHTML = 'تحقق من تشغيل الخادم';
            if (badge) {
                badge.style.background = '#FEE2E2';
                badge.style.color = '#991B1B';
                badge.innerHTML = '❌ خادم الواتساب غير متصل (' + (data.status || 'غير متاح') + ')';
            }
            if (devPhone) devPhone.innerHTML = '';
            qrWrap.innerHTML = `
                <div class="text-center py-3">
                    <div class="text-danger fw-bold mb-2"><i class="fas fa-power-off fa-2x mb-1 text-danger"></i><br>خادم الواتساب متوقف حالياً</div>
                    <button type="button" class="btn btn-sm btn-success fw-bold px-3 py-1.5 shadow-sm" onclick="startWaServerFromPanel(this)" style="font-size: 0.78rem; border-radius: 8px;">
                        <i class="fas fa-play me-1"></i> تشغيل خادم الواتساب الآن 🚀
                    </button>
                    <div class="text-muted small mt-2" style="font-size: 0.68rem;">أو عبر تشغيل ملف <code>start_whatsapp.bat</code></div>
                </div>
            `;
        }
    }

    function startWaServerFromPanel(btn) {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري تشغيل الخادم...';
        }
        const fd = new FormData();
        fd.append('ajax_action', 'start_whatsapp_server');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAdminToast(data.message || 'تم بدء تشغيل الخادم 🚀', 'success');
            setTimeout(updateWaWidgetStatus, 3000);
            setTimeout(refreshWaGatewayStatus, 3500);
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play me-1"></i> تشغيل خادم الواتساب الآن 🚀';
            }
            showAdminToast('جاري تشغيل الخادم في الخلفية...', 'info');
            setTimeout(updateWaWidgetStatus, 3000);
        });
    }

    function stopWaServerFromPanel(btn) {
        if (!confirm('هل تريد إيقاف خادم الواتساب (Port 8085) الآن؟')) return;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الإيقاف...';
        }
        const fd = new FormData();
        fd.append('ajax_action', 'stop_whatsapp_server');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAdminToast(data.message || 'تم إيقاف الخادم ⏹️', 'info');
            setTimeout(updateWaWidgetStatus, 1500);
            setTimeout(refreshWaGatewayStatus, 2000);
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            showAdminToast('حدث خطأ أثناء إيقاف الخادم', 'danger');
        });
    }

    // ─── BULK NUMBER VALIDATION JS ─────────────────────────────────────
    function runBulkNumberValidation() {
        const state = document.getElementById('bulkCheckState')?.value || '';
        const limit = document.getElementById('bulkCheckLimit')?.value || '20';
        const onlyUnverified = document.getElementById('bulkCheckOnlyUnverified')?.checked ? 1 : 0;
        const btn = document.getElementById('btnRunBulkCheck');
        const wrap = document.getElementById('bulkCheckProgressWrap');
        const txt = document.getElementById('bulkCheckProgressText');
        const badge = document.getElementById('bulkCheckCounterBadge');
        const bar = document.getElementById('bulkCheckProgressBar');
        const list = document.getElementById('bulkCheckResultsList');

        if (!btn || !wrap) return;

        btn.disabled = true;
        const oldBtn = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الفحص التلقائي...';
        wrap.style.display = 'block';
        txt.innerHTML = 'جاري استعلام سيرفر الواتساب وتدقيق الأرقام في قاعدة البيانات...';
        bar.style.width = '30%';

        const fd = new FormData();
        fd.append('ajax_action', 'bulk_check_whatsapp_numbers');
        fd.append('state', state);
        fd.append('limit', limit);
        fd.append('only_unverified', onlyUnverified);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldBtn;
            bar.style.width = '100%';

            if (data && data.success) {
                const total = data.checked_count || 0;
                const valids = data.valid_count || 0;
                const invalids = data.invalid_count || 0;

                txt.innerHTML = `اكتمل الفحص! تم فحص <b>${total}</b> مدرسة (🟢 <b>${valids}</b> نشطة — 🔴 <b>${invalids}</b> أرضية/غير مسجلة)`;
                badge.className = 'badge bg-success';
                badge.innerText = `${valids} / ${total} نشط`;

                let html = '<div class="table-responsive mt-2"><table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.72rem;">';
                html += '<thead class="table-light"><tr><th>المدرسة</th><th>الهاتف</th><th>النتيجة</th></tr></thead><tbody>';
                (data.schools || []).forEach(s => {
                    const isV = s.status === 'valid';
                    html += `<tr>
                        <td class="fw-bold">${escapeHtml(s.name)}</td>
                        <td class="dir-ltr text-end" style="font-family: monospace;">${escapeHtml(s.phone)}</td>
                        <td>${isV ? '<span class="badge bg-success text-white">واتساب نشط 🟢</span>' : '<span class="badge bg-danger text-white">أرضي / غير مفعل 🔴</span>'}</td>
                    </tr>`;

                    // Update school table badge if present
                    const bWrap = document.getElementById('badge-wa-status-wrap-' + s.id);
                    if (bWrap) {
                        bWrap.innerHTML = isV 
                            ? '<span class="badge bg-success text-white py-0 px-1.5" style="font-size: 0.58rem;"><i class="fab fa-whatsapp me-1"></i>واتساب نشط ومؤكد 🟢</span>'
                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-1.5" style="font-size: 0.58rem;"><i class="fas fa-phone-slash me-1"></i>أرضي / غير مفعل 🔴</span>';
                    }
                });
                html += '</tbody></table></div>';
                list.innerHTML = html;

                showAdminToast(`تم فحص ${total} مدرسة: ${valids} رقم واتساب نشط 🟢`, 'success');
            } else {
                txt.innerHTML = 'حدث خطأ: ' + (data.error || 'تعذر استكمال الفحص');
                bar.className = 'progress-bar bg-danger';
                showAdminToast('فشل الفحص الجماعي: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldBtn;
            txt.innerHTML = 'فشل الاتصال بالخادم المحلي.';
            bar.className = 'progress-bar bg-danger';
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    // ─── AUTOMATED BATCH CAMPAIGNS JS ──────────────────────────────────
    let isCampaignRunning = false;
    let isCampaignPaused = false;
    let campaignTotalSent = 0;

    function toggleCampaignTemplate() {
        const wrap = document.getElementById('campaignTemplateWrap');
        if (wrap) wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
    }

    function startBatchCampaign() {
        if (!confirm('تنبيه: ستبدأ الآن حملة إرسال رسائل الواتساب الآلية للمدارس غير المراسلة والتي تملك أرقام واتساب مؤكدة.\n\nهل ترغب بالبدء؟')) return;

        isCampaignRunning = true;
        isCampaignPaused = false;
        campaignTotalSent = 0;

        document.getElementById('btnStartCampaign').style.display = 'none';
        document.getElementById('btnPauseCampaign').style.display = 'inline-block';
        document.getElementById('btnStopCampaign').style.display = 'inline-block';

        const wrap = document.getElementById('campaignProgressWrap');
        const txt = document.getElementById('campaignStatusText');
        const badge = document.getElementById('campaignCountBadge');
        const bar = document.getElementById('campaignProgressBar');
        const logs = document.getElementById('campaignStreamLogs');

        if (wrap) wrap.style.display = 'block';
        if (txt) txt.innerHTML = '<i class="fas fa-spinner fa-spin me-1 text-primary"></i> الحملة قيد التنفيذ الآن...';
        if (bar) bar.style.width = '10%';
        if (logs) logs.innerHTML = '<div class="text-muted"><i class="fas fa-clock me-1"></i> تم بدء حملة الإرسال المجدول...</div>';

        runCampaignNextBatch();
    }

    function pauseBatchCampaign() {
        isCampaignPaused = !isCampaignPaused;
        const btn = document.getElementById('btnPauseCampaign');
        const txt = document.getElementById('campaignStatusText');
        if (isCampaignPaused) {
            btn.innerHTML = '<i class="fas fa-play me-1"></i> استئناف';
            btn.className = 'btn btn-outline-success fw-bold px-3 py-2';
            if (txt) txt.innerHTML = '⏸️ تم إيقاف الحملة مؤقتاً';
        } else {
            btn.innerHTML = '<i class="fas fa-pause me-1"></i> إيقاف مؤقت';
            btn.className = 'btn btn-outline-warning fw-bold px-3 py-2';
            if (txt) txt.innerHTML = '<i class="fas fa-spinner fa-spin me-1 text-primary"></i> تم استئناف الحملة...';
            runCampaignNextBatch();
        }
    }

    function stopBatchCampaign() {
        isCampaignRunning = false;
        isCampaignPaused = false;
        document.getElementById('btnStartCampaign').style.display = 'inline-block';
        document.getElementById('btnPauseCampaign').style.display = 'none';
        document.getElementById('btnStopCampaign').style.display = 'none';

        const txt = document.getElementById('campaignStatusText');
        if (txt) txt.innerHTML = `⏹️ تم إيقاف الحملة. إجمالي ما تم إرساله: <b>${campaignTotalSent}</b> مدرسة.`;
        showAdminToast(`تم إيقاف الحملة بعد إرسال ${campaignTotalSent} رسالة.`, 'info');
    }

    function runCampaignNextBatch() {
        if (!isCampaignRunning || isCampaignPaused) return;

        const state = document.getElementById('campaignState')?.value || '';
        const delaySec = parseInt(document.getElementById('campaignDelaySec')?.value || '8', 10);
        const txt = document.getElementById('campaignStatusText');
        const badge = document.getElementById('campaignCountBadge');
        const bar = document.getElementById('campaignProgressBar');
        const logs = document.getElementById('campaignStreamLogs');

        const fd = new FormData();
        fd.append('ajax_action', 'run_batch_whatsapp_campaign');
        fd.append('state', state);
        fd.append('batch_size', '3'); // دفعات صغيرة ثلاثية مع تأخير

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!isCampaignRunning) return;

            if (data && data.success) {
                const sentList = data.sent || [];
                const failedList = data.failed || [];
                const remaining = data.remaining || 0;

                campaignTotalSent += sentList.length;
                if (badge) badge.innerText = `${campaignTotalSent} مرسلة (متبقي ${remaining})`;

                sentList.forEach(s => {
                    if (logs) {
                        const entry = document.createElement('div');
                        entry.className = 'text-success py-0.5';
                        entry.innerHTML = `<i class="fas fa-check-circle me-1"></i> تم الإرسال إلى: <b>${escapeHtml(s.name)}</b> (${s.phone})`;
                        logs.insertBefore(entry, logs.firstChild);
                    }
                    // Update school row if on screen
                    const row = document.getElementById('row-school-' + s.id);
                    if (row) {
                        const togglePill = row.querySelector('.sp-toggle-pill[onclick*="toggle_whatsapp_sent"]');
                        if (togglePill) {
                            togglePill.className = 'sp-toggle-pill toggle-on-green is-success-pulse';
                            togglePill.innerHTML = '<i class="fab fa-whatsapp"></i> <span>تم الإرسال</span>';
                        }
                    }
                });

                failedList.forEach(f => {
                    if (logs) {
                        const entry = document.createElement('div');
                        entry.className = 'text-danger py-0.5';
                        entry.innerHTML = `<i class="fas fa-times-circle me-1"></i> فشل الإرسال إلى: <b>${escapeHtml(f.name)}</b> (${f.error})`;
                        logs.insertBefore(entry, logs.firstChild);
                    }
                });

                if (sentList.length === 0 && remaining === 0) {
                    if (txt) txt.innerHTML = '🎉 تم إرسال الرسائل لكافة المدارس المستهدفة بنجاح!';
                    if (bar) bar.style.width = '100%';
                    stopBatchCampaign();
                    return;
                }

                if (bar) {
                    const pct = Math.min(95, Math.max(15, Math.round((campaignTotalSent / (campaignTotalSent + remaining)) * 100)));
                    bar.style.width = pct + '%';
                }

                if (txt) txt.innerHTML = `جاري انتظار الفاصل الآمن (${delaySec} ثوانٍ) قبل الدفعة القادمة...`;

                setTimeout(() => {
                    if (isCampaignRunning && !isCampaignPaused) {
                        runCampaignNextBatch();
                    }
                }, delaySec * 1000);

            } else {
                if (txt) txt.innerHTML = 'توقفت الحملة بسبب خطأ: ' + (data.error || 'غير معروف');
                stopBatchCampaign();
            }
        })
        .catch(err => {
            if (txt) txt.innerHTML = 'خطأ اتصال بالخادم أثناء تنفيذ الحملة.';
            stopBatchCampaign();
        });
    }

    // ─── INTERACTIVE BOT SIMULATOR JS ──────────────────────────────────
    function sendSimPrompt(text) {
        const inp = document.getElementById('botSimInput');
        if (inp) {
            inp.value = text;
            sendSimMsg();
        }
    }

    function sendSimMsg() {
        const inp = document.getElementById('botSimInput');
        const box = document.getElementById('botSimChatBox');
        const btn = document.getElementById('btnBotSimSend');
        if (!inp || !inp.value.trim() || !box) return;

        const userMsg = inp.value.trim();
        inp.value = '';
        if (btn) btn.disabled = true;

        // Render user message bubble (Green WhatsApp style)
        const userBubble = document.createElement('div');
        userBubble.className = 'bot-msg-bubble align-self-end p-2.5 rounded-3 shadow-2xs';
        userBubble.style.background = '#DCF8C6';
        userBubble.style.maxWidth = '85%';
        userBubble.style.fontSize = '0.76rem';
        userBubble.style.lineHeight = '1.5';
        userBubble.style.borderRadius = '10px 10px 0 10px';
        userBubble.innerHTML = `${escapeHtml(userMsg)}<div class="text-end text-muted mt-1" style="font-size: 0.62rem;">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} ✓</div>`;
        box.appendChild(userBubble);
        box.scrollTop = box.scrollHeight;

        // Typing indicator bubble
        const typingBubble = document.createElement('div');
        typingBubble.id = 'botSimTyping';
        typingBubble.className = 'bot-msg-bubble align-self-start p-2 rounded-3 shadow-2xs small text-muted';
        typingBubble.style.background = '#FFFFFF';
        typingBubble.innerHTML = '<i class="fas fa-ellipsis-h fa-beat text-success me-1"></i> جاري إعداد الرد...';
        box.appendChild(typingBubble);
        box.scrollTop = box.scrollHeight;

        const fd = new FormData();
        fd.append('ajax_action', 'simulate_bot_message');
        fd.append('message', userMsg);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn) btn.disabled = false;
            const t = document.getElementById('botSimTyping');
            if (t) t.remove();

            if (data && data.success) {
                const replies = data.replies || [];
                if (replies.length === 0) {
                    const fallbackBubble = document.createElement('div');
                    fallbackBubble.className = 'bot-msg-bubble align-self-start p-2.5 rounded-3 shadow-2xs';
                    fallbackBubble.style.background = '#FFFFFF';
                    fallbackBubble.style.maxWidth = '85%';
                    fallbackBubble.style.fontSize = '0.76rem';
                    fallbackBubble.style.borderRadius = '10px 10px 10px 0';
                    fallbackBubble.innerHTML = 'تم استقبال رسالتك وحفظها. <div class="text-muted small mt-1">الخطوة الحالية: ' + (data.step || 'idle') + '</div>';
                    box.appendChild(fallbackBubble);
                } else {
                    replies.forEach(rep => {
                        const botBubble = document.createElement('div');
                        botBubble.className = 'bot-msg-bubble align-self-start p-2.5 rounded-3 shadow-2xs';
                        botBubble.style.background = '#FFFFFF';
                        botBubble.style.maxWidth = '85%';
                        botBubble.style.fontSize = '0.76rem';
                        botBubble.style.lineHeight = '1.5';
                        botBubble.style.borderRadius = '10px 10px 10px 0';
                        botBubble.innerHTML = `${escapeHtml(rep.text).replace(/\n/g, '<br>')}<div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 0.62rem;"><span>${rep.intent ? '#' + rep.intent : ''}</span><span>${rep.time || ''} ✓✓</span></div>`;
                        box.appendChild(botBubble);
                    });
                }
                box.scrollTop = box.scrollHeight;
            } else {
                const errBubble = document.createElement('div');
                errBubble.className = 'bot-msg-bubble align-self-start p-2.5 rounded-3 text-danger small shadow-2xs';
                errBubble.style.background = '#FEE2E2';
                errBubble.innerText = 'خطأ في معالجة البوت: ' + (data.error || 'تعذر المعالجة');
                box.appendChild(errBubble);
                box.scrollTop = box.scrollHeight;
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            const t = document.getElementById('botSimTyping');
            if (t) t.remove();
            showAdminToast('حدث خطأ في محاكاة البوت', 'danger');
        });
    }

    function resetBotSimulationChat() {
        if (!confirm('هل تريد إعادة تعيين محادثة البوت التجريبية وبدء جلسة جديدة؟')) return;
        const fd = new FormData();
        fd.append('ajax_action', 'reset_bot_simulation');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAdminToast(data.message || 'تمت إعادة تعيين محادثة البوت', 'success');
            const box = document.getElementById('botSimChatBox');
            if (box) {
                box.innerHTML = `
                    <div class="text-center text-muted small my-1" style="font-size: 0.68rem;">
                        <span class="bg-white px-2 py-1 rounded-pill shadow-2xs">تمت إعادة تعيين الجلسة والبدء من جديد</span>
                    </div>
                    <div class="bot-msg-bubble align-self-start p-2.5 rounded-3 shadow-2xs" style="background: #FFFFFF; max-width: 85%; font-size: 0.76rem; line-height: 1.5; border-radius: 10px 10px 10px 0;">
                        مرحباً بك مجدداً! أنا <b>المساعد الآلي لدليل المدارس السودانية 🤖</b><br>
                        اكتب أي استفسار أو اختر رقماً من القائمة لتجربة الردود المباشرة.
                        <div class="text-end text-muted mt-1" style="font-size: 0.62rem;">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} ✓✓</div>
                    </div>
                `;
            }
        });
    }

    // ─── CUSTOM SCHOOL WHATSAPP MESSAGE MODAL ─────────────────────────
    let currentCustomSchoolId = 0;
    function openCustomSchoolMsg(id, name, phone) {
        currentCustomSchoolId = id;
        const modal = document.getElementById('modalCustomSchoolMsg');
        const title = document.getElementById('customMsgSchoolName');
        const pInp = document.getElementById('customMsgPhone');
        const tArea = document.getElementById('customMsgBody');
        const resBox = document.getElementById('customMsgResultBox');

        if (title) title.innerText = name;
        if (pInp) pInp.value = phone;
        if (tArea) tArea.value = `السلام عليكم ورحمة الله وبركاته،\nإدارة مدرسة ${name} الموقرة،\nنود إفادتكم بخصوص دليل المدارس السودانية...`;
        if (resBox) resBox.style.display = 'none';

        if (modal) modal.style.display = 'block';
    }

    function closeCustomSchoolMsg() {
        const modal = document.getElementById('modalCustomSchoolMsg');
        if (modal) modal.style.display = 'none';
    }

    function sendCustomSchoolMsg() {
        const pInp = document.getElementById('customMsgPhone');
        const tArea = document.getElementById('customMsgBody');
        const btn = document.getElementById('btnSendCustomMsg');
        const resBox = document.getElementById('customMsgResultBox');

        if (!pInp || !pInp.value.trim() || !tArea || !tArea.value.trim()) {
            alert('يرجى التأكد من رقم الهاتف ونص الرسالة');
            return;
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الإرسال...';

        const fd = new FormData();
        fd.append('ajax_action', 'send_direct_whatsapp');
        fd.append('phone', pInp.value.trim());
        fd.append('message', tArea.value.trim());

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (resBox) {
                resBox.style.display = 'block';
                if (data && data.success) {
                    resBox.className = 'mt-3 p-3 rounded-3 alert alert-success small mb-0';
                    resBox.innerHTML = '✅ ' + (data.message || 'تم إرسال الرسالة بنجاح عبر خادم الواتساب');
                    showAdminToast('تم إرسال الرسالة المخصصة بنجاح 📲', 'success');
                    setTimeout(closeCustomSchoolMsg, 1800);
                } else {
                    resBox.className = 'mt-3 p-3 rounded-3 alert alert-danger small mb-0';
                    resBox.innerHTML = '❌ ' + (data.error || 'فشل إرسال الرسالة');
                }
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (resBox) {
                resBox.style.display = 'block';
                resBox.className = 'mt-3 p-3 rounded-3 alert alert-danger small mb-0';
                resBox.innerHTML = 'خطأ في الاتصال بالخادم.';
            }
        });
    }

    function refreshTabWaStatus() {
        updateWaWidgetStatus();
    }

    function runSinglePhoneCheck() {
        const inp = document.getElementById('checkSinglePhoneInput');
        const box = document.getElementById('checkSingleResultBox');
        if (!inp || !inp.value.trim()) {
            alert('يرجى إدخال رقم الهاتف');
            return;
        }
        const phone = inp.value.trim();
        box.style.display = 'block';
        box.style.background = '#F1F5F9';
        box.style.color = '#334155';
        box.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري فحص الرقم عبر سيرفرات واتساب...';

        const fd = new FormData();
        fd.append('ajax_action', 'check_school_whatsapp');
        fd.append('id', 0);
        // Direct post
        fetch('DaleelWhatsAppService.php') // verify
        const fd2 = new FormData();
        fd2.append('ajax_action', 'check_school_whatsapp');
        fetch('http://localhost:8085/api/daleel_session/check-number', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({phone})
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.exists) {
                box.style.background = '#DCFCE7';
                box.style.color = '#166534';
                box.innerHTML = '🟢 <b>الرقم مسجل في الواتساب ونشط!</b><br>المعرف: ' + data.jid;
            } else {
                box.style.background = '#FEE2E2';
                box.style.color = '#991B1B';
                box.innerHTML = '🔴 <b>الرقم ليس لديه حساب واتساب</b> (هاتف أرضي أو غير مفعل).';
            }
        })
        .catch(err => {
            box.style.background = '#FEE2E2';
            box.style.color = '#991B1B';
            box.innerHTML = 'خطأ في الاتصال بالخادم: ' + err.message;
        });
    }

    function runTabSendMessage() {
        const pInp = document.getElementById('tabSendPhone');
        const mInp = document.getElementById('tabSendMessage');
        const box = document.getElementById('tabSendResultBox');
        if (!pInp || !pInp.value.trim() || !mInp || !mInp.value.trim()) {
            alert('يرجى كتابة رقم الهاتف ونص الرسالة');
            return;
        }

        box.style.display = 'block';
        box.style.background = '#F1F5F9';
        box.style.color = '#334155';
        box.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري إرسال الرسالة عبر الخادم...';

        const fd = new FormData();
        fd.append('ajax_action', 'send_direct_whatsapp');
        fd.append('phone', pInp.value.trim());
        fd.append('message', mInp.value.trim());

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                box.style.background = '#DCFCE7';
                box.style.color = '#166534';
                box.innerHTML = '✅ ' + data.message;
                mInp.value = '';
            } else {
                box.style.background = '#FEE2E2';
                box.style.color = '#991B1B';
                box.innerHTML = '❌ ' + (data.error || 'فشل الإرسال');
            }
        })
        .catch(err => {
            box.style.background = '#FEE2E2';
            box.style.color = '#991B1B';
            box.innerHTML = 'خطأ في الاتصال بالخادم.';
        });
    }

    function logoutWaSession() {
        if (!confirm('هل أنت متأكد من فصل رقم الهاتف وتسجيل الخروج من الجلسة؟')) return;
        const fd = new FormData();
        fd.append('ajax_action', 'logout_whatsapp_server');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            alert('تم تسجيل الخروج وفصل الرقم.');
            updateWaWidgetStatus();
        });
    }

    function openWaGatewayModal() {
        const modal = document.getElementById('modalWaGateway');
        if (modal) modal.style.display = 'block';
        refreshWaGatewayStatus();
    }

    function closeWaGatewayModal() {
        const modal = document.getElementById('modalWaGateway');
        if (modal) modal.style.display = 'none';
    }

    function refreshWaGatewayStatus() {
        const b = document.getElementById('waModalStatusBadge');
        const p = document.getElementById('waModalPhoneInfo');
        const q = document.getElementById('waModalQrWrap');
        if (!b || !q) return;

        b.className = 'p-3 rounded-3 text-center mb-3 fw-bold';
        b.style.background = '#F1F5F9';
        b.style.color = '#334155';
        b.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري فحص حالة الخادم...';

        const fd = new FormData();
        fd.append('ajax_action', 'get_whatsapp_server_status');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'cloud_hub') {
                b.style.background = '#EFF6FF';
                b.style.color = '#1E40AF';
                b.innerHTML = '☁️ المراسلة الآلية المركزية مُدارة عبر المحطة المحلية';
                p.innerHTML = 'البيئة السحابية: <b>daleel.schoplus.sd</b>';
                q.innerHTML = `
                    <div class="py-3 px-2 text-center">
                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-2"></i>
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.88rem;">إدارة واتساب المركزية عبر المكتب المحلي</div>
                        <div class="text-muted small mb-3" style="font-size: 0.74rem; line-height: 1.5;">
                            لضمان استقرار جلسة الواتساب وتجنب حظر الخوادم المشتركة، يتم تشغيل الإرسال الآلي من محطة العمل المحلية (localhost) وتتزامن حالات الرسائل تلقائياً مع السحابة بنقرة واحدة. كما يتوفر خيار Click-to-Chat للإرسال الفوري المباشر من السحابة.
                        </div>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5" onclick="closeWaGatewayModal(); openCloudSyncModal();" style="font-size: 0.78rem; border-radius: 6px;">
                                <i class="fas fa-sync-alt me-1"></i> فتح مركز المزامنة السحابية
                            </button>
                        </div>
                    </div>
                `;
            } else if (data.status === 'connected') {
                b.style.background = '#DCFCE7';
                b.style.color = '#166534';
                b.innerHTML = '✅ متصل وجاهز للإرسال والرد الآلي على الاستفسارات';
                p.innerHTML = 'رقم الحساب المتصل: <b>+' + (data.phone || 'نشط') + '</b>';
                q.innerHTML = '<div class="py-4 text-success fw-bold"><i class="fas fa-check-circle fa-3x mb-2 text-success"></i><br>جلسة الواتساب نشطة ومربوطة بنجاح</div>';
            } else if (data.status === 'qr_waiting' && data.qrcode) {
                b.style.background = '#FEF3C7';
                b.style.color = '#92400E';
                b.innerHTML = '⏳ امسح رمز الاستجابة السريعة (QR Code) من تطبيق الواتساب بهاتفك';
                p.innerHTML = 'واتساب > الأجهزة المرتبطة > ربط جهاز';
                q.innerHTML = '<img src="' + data.qrcode + '" style="max-width: 210px; border-radius: 8px; border: 2px dashed #CBD5E1; padding: 6px;">';
            } else if (['PAIRING', 'SYNCING', 'init', 'loading'].includes(data.status)) {
                b.style.background = '#FEF3C7';
                b.style.color = '#92400E';
                b.innerHTML = '⏳ جاري مزامنة جلسة الواتساب مع الهاتف... (' + data.status + ')';
                p.innerHTML = 'الحالة الحالية: جاري الربط';
                q.innerHTML = '<div class="py-4 text-center text-warning fw-bold"><i class="fas fa-spinner fa-spin fa-3x mb-2"></i><br>جاري مزامنة الحساب...</div>';
            } else {
                b.style.background = '#FEE2E2';
                b.style.color = '#991B1B';
                b.innerHTML = '❌ الخادم غير متصل (' + (data.status || 'غير متاح') + ')';
                p.innerHTML = '';
                q.innerHTML = '<div class="text-muted small py-3">يرجى التأكد من تشغيل start_whatsapp.bat أو فحص المنفذ 8085</div>';
            }
        })
        .catch(err => {
            b.style.background = '#FEE2E2';
            b.style.color = '#991B1B';
            b.innerHTML = '❌ فشل الاتصال بخادم الواتساب';
        });
    }

    function restartWaGatewaySession() {
        if (!confirm('هل تريد إعادة تشغيل جلسة واتساب الدليل؟')) return;
        const fd = new FormData();
        fd.append('ajax_action', 'restart_whatsapp_server');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            alert(data.message || 'تم طلب إعادة التشغيل');
            setTimeout(refreshWaGatewayStatus, 1500);
            setTimeout(updateWaWidgetStatus, 2000);
        });
    }

    // إرسال آلي مباشر بنقرة واحدة
    function sendAutoWhatsApp(schoolId, btn) {
        if (!confirm('هل تريد إرسال رسالة التعريف والدعوة الرسمية إلى هذه المدرسة آلياً الآن؟')) return;

        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>جاري الإرسال...</span>';

        const fd = new FormData();
        fd.append('ajax_action', 'send_school_whatsapp');
        fd.append('id', schoolId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data && data.success) {
                btn.innerHTML = '<i class="fas fa-check"></i> <span>تم الإرسال</span>';
                btn.style.backgroundColor = '#166534';
                showAdminToast(data.message, 'success');

                const row = document.getElementById('row-school-' + schoolId);
                if (row) {
                    const togglePill = row.querySelector('.sp-toggle-pill[onclick*="toggle_whatsapp_sent"]');
                    if (togglePill) {
                        togglePill.className = 'sp-toggle-pill toggle-on-green is-success-pulse';
                        togglePill.innerHTML = '<i class="fab fa-whatsapp"></i> <span>تم الإرسال</span>';
                    }
                }
            } else {
                btn.innerHTML = oldHtml;
                alert('فشل الإرسال: ' + (data.error || 'حدث خطأ'));
                showAdminToast('فشل الإرسال: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            alert('حدث خطأ في الاتصال بالخادم.');
        });
    }

    // فحص هل الرقم يملك حساب واتساب نشط
    function checkSchoolWhatsApp(schoolId, btn) {
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 0.6rem;"></i>';

        const fd = new FormData();
        fd.append('ajax_action', 'check_school_whatsapp');
        fd.append('id', schoolId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            const bWrap = document.getElementById('badge-wa-status-wrap-' + schoolId);

            if (data && data.success) {
                if (data.exists) {
                    btn.className = 'btn btn-sm btn-success d-inline-flex align-items-center justify-content-center p-0';
                    btn.innerHTML = '<i class="fas fa-check" style="font-size: 0.6rem;"></i>';
                    if (bWrap) {
                        bWrap.innerHTML = '<span class="badge bg-success text-white py-0 px-1.5" style="font-size: 0.58rem;"><i class="fab fa-whatsapp me-1"></i>واتساب نشط ومؤكد 🟢</span>';
                    }
                    showAdminToast('🟢 ' + data.phone + ' مسجل في الواتساب ونشط!', 'success');
                } else {
                    btn.className = 'btn btn-sm btn-danger d-inline-flex align-items-center justify-content-center p-0';
                    btn.innerHTML = '<i class="fas fa-times" style="font-size: 0.6rem;"></i>';
                    if (bWrap) {
                        bWrap.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-1.5" style="font-size: 0.58rem;"><i class="fas fa-phone-slash me-1"></i>أرضي / غير مفعل 🔴</span>';
                    }
                    showAdminToast('🔴 ' + data.phone + ' ليس لديه حساب واتساب (هاتف أرضي أو غير مفعل)', 'danger');
                }
            } else {
                alert(data.error || 'فشل الفحص');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            alert('خطأ في الاتصال بخادم الواتساب');
        });
    }

    // ─── CLOUD SYNC JS INTEGRATION (OPTION 1) ──────────────────────
    let syncStatsData = null;

    function openCloudSyncModal() {
        const modal = document.getElementById('modalCloudSync');
        if (modal) modal.style.display = 'block';
        loadCloudSyncStats();
    }

    function closeCloudSyncModal() {
        const modal = document.getElementById('modalCloudSync');
        if (modal) modal.style.display = 'none';
    }

    function loadCloudSyncStats() {
        const b = document.getElementById('cloudSyncStatusBadge');
        const hubBadge = document.getElementById('syncHubStatusBadge');
        if (b) {
            b.className = 'p-3 rounded-3 text-center mb-3 fw-bold';
            b.style.background = '#F1F5F9';
            b.style.color = '#334155';
            b.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5 text-primary"></i> جاري فحص حالة المزامنة ومطابقة السجلات مع السحابة...';
        }
        if (hubBadge) {
            hubBadge.className = 'p-3 rounded-3 text-center mb-4 fw-bold';
            hubBadge.style.background = '#F1F5F9';
            hubBadge.style.color = '#334155';
            hubBadge.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5 text-primary"></i> جاري استعلام السحابة ومطابقة السجلات...';
        }

        const fd = new FormData();
        fd.append('ajax_action', 'get_cloud_sync_stats');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            syncStatsData = data;
            if (data && data.success) {
                const loc = data.local || {};
                const cld = data.cloud || {};

                const localCountEl = document.getElementById('syncLocalCount');
                if (localCountEl) localCountEl.innerText = Number(loc.total_schools || 0).toLocaleString();
                const localWaSentEl = document.getElementById('syncLocalWaSent');
                if (localWaSentEl) localWaSentEl.innerText = Number(loc.whatsapp_sent_count || 0).toLocaleString();

                const hubLocalCountEl = document.getElementById('syncHubLocalCount');
                if (hubLocalCountEl) hubLocalCountEl.innerText = Number(loc.total_schools || 0).toLocaleString();
                const hubLocalWaSentEl = document.getElementById('syncHubLocalWaSent');
                if (hubLocalWaSentEl) hubLocalWaSentEl.innerText = Number(loc.whatsapp_sent_count || 0).toLocaleString();

                const cloudTotal = (cld.total_schools !== undefined) ? Number(cld.total_schools).toLocaleString() : 'غير متاح';
                const cloudWa = (cld.whatsapp_sent_count !== undefined) ? Number(cld.whatsapp_sent_count).toLocaleString() : '0';

                const cloudCountEl = document.getElementById('syncCloudCount');
                if (cloudCountEl) cloudCountEl.innerText = cloudTotal;
                const cloudWaSentEl = document.getElementById('syncCloudWaSent');
                if (cloudWaSentEl) cloudWaSentEl.innerText = cloudWa;

                const hubCloudCountEl = document.getElementById('syncHubCloudCount');
                if (hubCloudCountEl) hubCloudCountEl.innerText = cloudTotal;
                const hubCloudWaSentEl = document.getElementById('syncHubCloudWaSent');
                if (hubCloudWaSentEl) hubCloudWaSentEl.innerText = cloudWa;
                const hubCloudVerEl = document.getElementById('syncHubCloudVer');
                if (hubCloudVerEl) hubCloudVerEl.innerText = 'v' + (cld.version || '3.8.32');

                const statusHtml = (cld.success !== false)
                    ? `✅ متصل بنقطة نهاية السحابة (daleel.schoplus.sd) — الإصدار: v${cld.version || '3.8.32'}`
                    : `⚠️ تعذر الاتصال بنقطة نهاية السحابة: ${escapeHtml(cld.error || '')}`;
                const bg = (cld.success !== false) ? '#DCFCE7' : '#FEF3C7';
                const color = (cld.success !== false) ? '#166534' : '#92400E';

                if (b) {
                    b.style.background = bg;
                    b.style.color = color;
                    b.innerHTML = statusHtml;
                }
                if (hubBadge) {
                    hubBadge.style.background = bg;
                    hubBadge.style.color = color;
                    hubBadge.innerHTML = statusHtml;
                }
            } else {
                if (b) {
                    b.style.background = '#FEE2E2';
                    b.style.color = '#991B1B';
                    b.innerHTML = '❌ فشل جلب إحصائيات المزامنة.';
                }
                if (hubBadge) {
                    hubBadge.style.background = '#FEE2E2';
                    hubBadge.style.color = '#991B1B';
                    hubBadge.innerHTML = '❌ فشل جلب إحصائيات المزامنة.';
                }
            }
        })
        .catch(err => {
            if (b) {
                b.style.background = '#FEE2E2';
                b.style.color = '#991B1B';
                b.innerHTML = '❌ خطأ أثناء الاتصال بالخادم المحلي.';
            }
            if (hubBadge) {
                hubBadge.style.background = '#FEE2E2';
                hubBadge.style.color = '#991B1B';
                hubBadge.innerHTML = '❌ خطأ أثناء الاتصال بالخادم المحلي.';
            }
        });
    }

    function doCloudPush(btn) {
        if (!confirm('هل تريد بدء مزامنة ورفع المدارس الجديدة وحالات إرسال الواتساب إلى السحابة الآن؟')) return;

        const logBox = document.getElementById('cloudSyncLogBox');
        if (logBox) {
            logBox.style.display = 'block';
            logBox.innerHTML = '<div class="text-primary"><i class="fas fa-spinner fa-spin me-1.5"></i> جاري استخراج السجلات المحلية وإرسال الحزمة للسحابة (https://daleel.schoplus.sd/api_sync.php)...</div>';
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري المزامنة...';

        const fd = new FormData();
        fd.append('ajax_action', 'trigger_cloud_push');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (data && data.success) {
                if (logBox) {
                    logBox.innerHTML = `
                        <div class="text-success fw-bold mb-1"><i class="fas fa-check-circle me-1"></i> ${escapeHtml(data.message || 'اكتملت المزامنة السحابية بنجاح!')}</div>
                        <div class="small text-muted" style="line-height: 1.6;">
                            • تم إدراج مدارس جديدة بالسحابة: <strong>${data.created_schools || 0}</strong><br>
                            • تم تحديث مدارس قائمة: <strong>${data.updated_schools || 0}</strong><br>
                            • تم مزامنة حالات واتساب: <strong>${data.synced_whatsapp || 0}</strong><br>
                            • إجمالي المدارس بالسحابة الآن: <strong>${Number(data.total_cloud_schools || 0).toLocaleString()}</strong>
                        </div>
                    `;
                }
                showAdminToast(data.message || 'اكتملت المزامنة مع السحابة بنجاح ✅', 'success');
                loadCloudSyncStats();
            } else {
                if (logBox) {
                    logBox.innerHTML = `<div class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> خطأ أثناء المزامنة: ${escapeHtml(data.error || 'استجابة غير صالحة')}</div>`;
                }
                showAdminToast('فشلت المزامنة: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (logBox) {
                logBox.innerHTML = '<div class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> فشل الاتصال بالخادم.</div>';
            }
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    function doCloudPull(btn) {
        if (!confirm('تحذير: سيتم جلب كافة المدارس المعتمدة في السحابة وحفظها في قاعدة بيانات جهازك المحلية. هل ترغب بالاستمرار؟')) return;

        const logBox = document.getElementById('cloudSyncLogBox');
        if (logBox) {
            logBox.style.display = 'block';
            logBox.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin me-1.5"></i> جاري تنزيل سجلات المدارس من السحابة وتحديث قاعدة البيانات المحلية...</div>';
        }

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الجلب...';

        const fd = new FormData();
        fd.append('ajax_action', 'trigger_cloud_pull');
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;

            if (data && data.success) {
                if (logBox) {
                    logBox.innerHTML = `
                        <div class="text-success fw-bold mb-1"><i class="fas fa-check-circle me-1"></i> ${escapeHtml(data.message || 'اكتمل الاستيراد والتحديث المحلي بنجاح!')}</div>
                        <div class="small text-muted" style="line-height: 1.6;">
                            • تم استيراد مدارس جديدة بالقاعدة المحلية: <strong>${data.imported_count || 0}</strong><br>
                            • تم تحديث مدارس: <strong>${data.updated_count || 0}</strong><br>
                            • إجمالي المدارس بالقاعدة المحلية الآن: <strong>${Number(data.total_local_now || 0).toLocaleString()}</strong>
                        </div>
                    `;
                }
                showAdminToast(data.message || 'اكتمل جلب المدارس من السحابة بنجاح ✅', 'success');
                loadCloudSyncStats();
            } else {
                if (logBox) {
                    logBox.innerHTML = `<div class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> خطأ: ${escapeHtml(data.error || 'تعذر استكمال العملية')}</div>`;
                }
                showAdminToast('فشل الجلب: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (logBox) {
                logBox.innerHTML = '<div class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> فشل الاتصال بالخادم.</div>';
            }
            showAdminToast('حدث خطأ في الاتصال بالخادم.', 'danger');
        });
    }

    // ─── INQUIRIES & REVIEWS MANAGEMENT (CLOUD CRM) ───────────────
    function updateInquiryStatus(inqId, newStatus) {
        const fd = new FormData();
        fd.append('ajax_action', 'update_inquiry_status');
        fd.append('id', inqId);
        fd.append('status', newStatus);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                showAdminToast('تم تحديث حالة الاستفسار بنجاح ✅', 'success');
            } else {
                showAdminToast('فشل التحديث: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            showAdminToast('حدث خطأ أثناء التحديث.', 'danger');
        });
    }

    function deleteInquiry(inqId) {
        if (!confirm('هل أنت متأكد من حذف هذا الاستفسار نهائياً؟')) return;
        const fd = new FormData();
        fd.append('ajax_action', 'delete_inquiry');
        fd.append('id', inqId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const row = document.getElementById('inquiry-row-' + inqId);
                if (row) row.remove();
                showAdminToast('تم حذف الاستفسار بنجاح 🗑️', 'warning');
            } else {
                showAdminToast('فشل الحذف: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            showAdminToast('حدث خطأ في الاتصال.', 'danger');
        });
    }

    function toggleReviewApproval(revId, newVal, el) {
        if (el.classList.contains('is-loading')) return;
        const prevHtml = el.innerHTML;
        const prevClass = el.className;
        el.classList.add('is-loading');
        el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>جاري...</span>';

        const fd = new FormData();
        fd.append('ajax_action', 'toggle_review_approval');
        fd.append('id', revId);
        fd.append('is_approved', newVal);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            el.classList.remove('is-loading');
            if (data && data.success) {
                if (newVal === 1) {
                    el.className = 'sp-toggle-pill toggle-on-green is-success-pulse';
                    el.innerHTML = '<i class="fas fa-check-circle"></i> <span>منشور ومعتمد</span>';
                    el.setAttribute('onclick', `toggleReviewApproval(${revId}, 0, this)`);
                    showAdminToast('تم اعتماد ونشر التقييم بنجاح ⭐', 'success');
                } else {
                    el.className = 'sp-toggle-pill toggle-off-gray is-success-pulse';
                    el.innerHTML = '<i class="fas fa-eye-slash"></i> <span>قيد المراجعة / مخفي</span>';
                    el.setAttribute('onclick', `toggleReviewApproval(${revId}, 1, this)`);
                    showAdminToast('تم إخفاء التقييم ℹ️', 'warning');
                }
                setTimeout(() => el.classList.remove('is-success-pulse'), 1200);
            } else {
                el.className = prevClass;
                el.innerHTML = prevHtml;
                showAdminToast('فشل التعديل: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            el.classList.remove('is-loading');
            el.className = prevClass;
            el.innerHTML = prevHtml;
            showAdminToast('حدث خطأ في الاتصال.', 'danger');
        });
    }

    function deleteReview(revId) {
        if (!confirm('هل أنت متأكد من حذف هذا التقييم نهائياً؟')) return;
        const fd = new FormData();
        fd.append('ajax_action', 'delete_review');
        fd.append('id', revId);

        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const row = document.getElementById('review-row-' + revId);
                if (row) row.remove();
                showAdminToast('تم حذف التقييم بنجاح 🗑️', 'warning');
            } else {
                showAdminToast('فشل الحذف: ' + (data.error || ''), 'danger');
            }
        })
        .catch(err => {
            showAdminToast('حدث خطأ في الاتصال.', 'danger');
        });
    }

    <?php if ($isLocalStation): ?>
    // بدء فحص دوري لحالة سيرفر الواتساب كل 8 ثوان في المحطة المحلية فقط
    setInterval(updateWaWidgetStatus, 8000);
    setTimeout(updateWaWidgetStatus, 1000);

    // ─── ZERO-BUTTON CONTINUOUS BACKGROUND SYNCHRONIZATION ───
    (function() {
        let isSyncing = false;
        function runSilentAutoSync() {
            if (isSyncing) return;
            isSyncing = true;
            const fd = new FormData();
            fd.append('ajax_action', 'auto_sync_delta');
            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    isSyncing = false;
                    if (data && data.success && data.synced_count > 0) {
                        const txt = document.getElementById('liveSyncStatusText');
                        if (txt) {
                            txt.innerText = `تمت مزامنة (${data.synced_count}) سجل تلقائياً ✅`;
                            setTimeout(() => { txt.innerText = 'مزامنة تلقائية متزامنة 🟢'; }, 6000);
                        }
                    }
                })
                .catch(() => { isSyncing = false; });
        }
        setTimeout(runSilentAutoSync, 2500);
        setInterval(runSilentAutoSync, 35000);
    })();
    <?php endif; ?>
    </script>

    <?php if ($isLocalStation): ?>
    <!-- MODAL: WHATSAPP SERVER & QR DASHBOARD -->
    <div class="modal fade show" id="modalWaGateway" tabindex="-1" aria-hidden="true" style="display: none; background: rgba(0,0,0,0.65); z-index: 99999;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #0F5C3E, #0A3E2A); padding: 18px 24px;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fab fa-whatsapp fs-4 text-warning"></i>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="font-size: 1.05rem;">خادم واتساب دليل المدارس السودانية</h5>
                            <div class="small opacity-75" style="font-size: 0.72rem;">Port 8085 — جلسة daleel_session المستقلة</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeWaGatewayModal()"></button>
                </div>
                <div class="modal-body p-4" style="background: #F8FAFC;">
                    <div id="waModalStatusBadge" class="p-3 rounded-3 text-center mb-3 fw-bold" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D;">
                        ⏳ جاري فحص حالة الاتصال...
                    </div>
                    <div id="waModalPhoneInfo" class="text-center fw-bold mb-3 text-secondary" style="font-size: 0.85rem;"></div>

                    <div id="waModalQrWrap" class="text-center p-3 bg-white rounded-3 border mb-3" style="min-height: 200px; display: flex; align-items: center; justify-content: center;">
                        <span class="text-muted small">جاري جلب رمز الاستجابة السريعة...</span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3" onclick="refreshWaGatewayStatus()">
                            <i class="fas fa-sync-alt me-1"></i> تحديث الحالة
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning fw-bold px-3" onclick="restartWaGatewaySession()">
                            <i class="fas fa-redo me-1"></i> إعادة تشغيل الجلسة
                        </button>
                        <a href="http://localhost:8085/" target="_blank" class="btn btn-sm btn-success fw-bold px-3">
                            <i class="fas fa-external-link-alt me-1"></i> فتح لوحة السيرفر المباشرة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- MODAL: DIRECT CLOUD SYNC HUB -->
    <div class="modal fade show" id="modalCloudSync" tabindex="-1" aria-hidden="true" style="display: none; background: rgba(0,0,0,0.65); z-index: 99999;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 680px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #0B2A52, #163870); border-bottom: 2px solid #F5A020; padding: 18px 24px;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-cloud-upload-alt fs-4 text-warning"></i>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="font-size: 1.05rem;">مركز المزامنة السحابية المباشرة (Cloud Sync Hub)</h5>
                            <div class="small opacity-75" style="font-size: 0.72rem;">مزامنة قاعدة بيانات الدليل ورسائل الواتساب بين المحلي و https://daleel.schoplus.sd</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCloudSyncModal()"></button>
                </div>
                <div class="modal-body p-4" style="background: #F8FAFC;">
                    <!-- Status Banner -->
                    <div id="cloudSyncStatusBadge" class="p-3 rounded-3 text-center mb-3 fw-bold" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D;">
                        ⏳ جاري فحص حالة المزامنة...
                    </div>

                    <!-- Comparison Metrics Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-sm-3">
                            <div class="bg-white p-2.5 rounded-3 border text-center shadow-2xs">
                                <div class="small text-muted" style="font-size: 0.68rem;">مدارس المحلي</div>
                                <div class="fw-bold text-dark fs-6" id="syncLocalCount">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="bg-white p-2.5 rounded-3 border text-center shadow-2xs">
                                <div class="small text-muted" style="font-size: 0.68rem;">مدارس السحابة</div>
                                <div class="fw-bold text-primary fs-6" id="syncCloudCount">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="bg-white p-2.5 rounded-3 border text-center shadow-2xs">
                                <div class="small text-muted" style="font-size: 0.68rem;">رسائل مرسلة محلياً</div>
                                <div class="fw-bold text-success fs-6" id="syncLocalWaSent">--</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="bg-white p-2.5 rounded-3 border text-center shadow-2xs">
                                <div class="small text-muted" style="font-size: 0.68rem;">رسائل بالسحابة</div>
                                <div class="fw-bold text-info fs-6" id="syncCloudWaSent">--</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sync Actions Cards -->
                    <div class="row g-3 mb-3">
                        <!-- PUSH -->
                        <div class="col-md-6 col-12">
                            <div class="card h-100 border-0 shadow-2xs rounded-3 p-3 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="p-2 rounded-2 bg-primary-subtle text-primary"><i class="fas fa-cloud-upload-alt fs-5"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">1. رفع وتحديث السحابة</div>
                                        <div class="text-muted small" style="font-size: 0.68rem;">Local ➔ Cloud Sync (Push)</div>
                                    </div>
                                </div>
                                <p class="text-muted small mb-3" style="font-size: 0.72rem; line-height: 1.45;">
                                    يرفع كافة المدارس الجديدة والمكتشفة محلياً، ويحدّث حالات إرسال رسائل الواتساب وسجلات التواصل إلى الموقع الحي مع إعادة حساب الرتب التسلسلية.
                                </p>
                                <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm text-white mt-auto" onclick="doCloudPush(this)" style="font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-rocket me-1"></i> بدء الرفع والمزامنة للسحابة
                                </button>
                            </div>
                        </div>

                        <!-- PULL -->
                        <div class="col-md-6 col-12">
                            <div class="card h-100 border-0 shadow-2xs rounded-3 p-3 bg-white" style="border: 1px solid #E2E8F0 !important;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="p-2 rounded-2 bg-success-subtle text-success"><i class="fas fa-cloud-download-alt fs-5"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.88rem;">2. مطابقة القاعدة المحلية</div>
                                        <div class="text-muted small" style="font-size: 0.68rem;">Cloud ➔ Local Sync (Pull)</div>
                                    </div>
                                </div>
                                <p class="text-muted small mb-3" style="font-size: 0.72rem; line-height: 1.45;">
                                    يجلب كافة المدارس المعتمدة في السحابة (1,200+ مدرسة) إلى قاعدة بيانات جهازك المحلية لتصبح مطابقة تماماً للموقع الحي.
                                </p>
                                <button type="button" class="btn btn-outline-success w-100 fw-bold py-2 shadow-2xs mt-auto" onclick="doCloudPull(this)" style="font-size: 0.8rem; border-radius: 8px;">
                                    <i class="fas fa-download me-1"></i> جلب وتحديث المدارس محلياً
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Log / Feedback Box -->
                    <div id="cloudSyncLogBox" class="p-3 rounded-3 bg-white border" style="display: none; font-size: 0.76rem; border-color: #CBD5E1 !important;"></div>

                    <!-- Footer Controls -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-3" onclick="loadCloudSyncStats()" style="font-size: 0.74rem;">
                            <i class="fas fa-sync-alt me-1"></i> إعادة فحص الحالة
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary py-1 px-3" onclick="closeCloudSyncModal()" style="font-size: 0.74rem;">
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: CUSTOM SCHOOL WHATSAPP MESSAGE -->
    <div class="modal fade show" id="modalCustomSchoolMsg" tabindex="-1" aria-hidden="true" style="display: none; background: rgba(0,0,0,0.65); z-index: 99999;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #0F5C3E, #166534); padding: 16px 22px;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fab fa-whatsapp fs-5 text-warning"></i>
                        <div>
                            <h6 class="modal-title fw-bold mb-0" id="customMsgSchoolName" style="font-size: 0.95rem;">مراسلة المدرسة</h6>
                            <div class="small opacity-75" style="font-size: 0.68rem;">إرسال رسالة مخصصة مباشرة عبر خادم الواتساب المستقل</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCustomSchoolMsg()"></button>
                </div>
                <div class="modal-body p-4" style="background: #F8FAFC;">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.74rem;">رقم هاتف المستلم (واتساب):</label>
                        <input type="text" id="customMsgPhone" class="form-control form-control-sm dir-ltr fw-bold" style="font-size: 0.8rem; font-family: monospace;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.74rem;">نص الرسالة المخصصة:</label>
                        <textarea id="customMsgBody" class="form-control form-control-sm" rows="5" style="font-size: 0.78rem; line-height: 1.5;"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="btnSendCustomMsg" class="btn btn-success fw-bold px-4 py-2 w-100" onclick="sendCustomSchoolMsg()" style="font-size: 0.8rem; border-radius: 8px;">
                            <i class="fab fa-whatsapp me-1"></i> إرسال الرسالة الآن 📲
                        </button>
                    </div>
                    <div id="customMsgResultBox" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
