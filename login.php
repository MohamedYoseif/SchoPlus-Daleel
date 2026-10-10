<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Owner Login Page (تسجيل دخول أصحاب المدارس)
 */

require_once __DIR__ . '/config.php';

// If already logged in, redirect to dashboard
if (currentSchool()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();
$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$identifier || !$password) {
            $error = 'يرجى إدخال رقم الهاتف أو البريد الإلكتروني وكلمة المرور.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE (owner_phone = ? OR owner_email = ?) LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $school = $stmt->fetch();

            if ($school && password_verify($password, $school['password'])) {
                $_SESSION['daleel_school_id'] = (int)$school['id'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'بيانات الدخول غير صحيحة. يرجى التحقق من رقم الهاتف وكلمة المرور.';
            }
        }
    }
}

$pageTitle = 'تسجيل دخول أصحاب المدارس';
require_once __DIR__ . '/header.php';
?>

<div class="login-page py-5">
    <div class="container" style="max-width: 520px;">
        
        <div class="card border-0 rounded-4 shadow-sm bg-white p-4 p-md-5 my-3">
            <div class="text-center mb-4">
                <img src="/assets/images/logo-icon.png" alt="دليل المدارس السودانية" class="mx-auto mb-3 d-block" style="width: 68px; height: 68px; object-fit: contain;">
                <h3 class="fw-bold text-dark mb-1" style="font-size: 1.15rem;">دخول أصحاب المدارس</h3>
                <p class="text-muted small">لوحة إدارة الملف التعريفي، الأخبار، والتقييمات</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 small mb-4">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">رقم هاتف المسؤول أو البريد الإلكتروني</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-mobile-alt"></i></span>
                        <input type="text" name="identifier" required class="form-control border-start-0" value="<?= e($identifier) ?>" placeholder="09xxxxxxx">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small fw-bold text-secondary mb-0">كلمة المرور</label>
                        <a href="https://wa.me/249912345678?text=<?= urlencode('مرحباً، نسيت كلمة المرور الخاصة بمدرستي في دليل المدارس') ?>" target="_blank" class="small text-muted text-decoration-none" style="font-size: 0.76rem;">
                            نسيت كلمة المرور؟
                        </a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" required class="form-control border-start-0" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn-luxury-primary w-100 py-3 fs-6 justify-content-center mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول
                </button>

                <div class="text-center pt-3 border-top">
                    <span class="text-muted small">لم تقم بتسجيل مدرستك بعد؟</span>
                    <a href="register.php" class="text-primary fw-bold small text-decoration-none ms-1">سجّل مدرستك مجاناً الآن</a>
                </div>
            </form>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
