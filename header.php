<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
$currSchool = currentSchool();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | دليل المدارس الذكي — SchoPlus' : 'دليل المدارس الذكي — دليلك الشامل لاختيار أفضل المدارس في السودان' ?></title>
    
    <!-- Cairo Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">

    <style>
        :root {
            /* Luxurious Warm Cream & Crisp White Identity */
            --bg-body: #FAF8F5;
            --bg-surface: #FFFFFF;
            --bg-surface-soft: #F5EFE6;
            --bg-surface-tint: #FDFBF7;
            
            --border-subtle: #EAE3D8;
            --border-focus: #D4AF37;
            
            --text-main: #1A202C;
            --text-body: #4A5568;
            --text-muted: #7A8699;
            --text-gold: #92400E;
            
            --brand-navy: #0B2A52;
            --brand-blue: #1E5FA8;
            --brand-blue-hover: #164985;
            --brand-gold: #D97706;
            --brand-gold-light: #FEF3C7;
            --brand-gold-border: #FCD34D;
            --brand-emerald: #059669;
            
            --shadow-card: 0 4px 20px -2px rgba(45, 35, 20, 0.05), 0 2px 6px -1px rgba(45, 35, 20, 0.03);
            --shadow-hover: 0 12px 30px -4px rgba(45, 35, 20, 0.09), 0 4px 10px -2px rgba(45, 35, 20, 0.04);
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-body);
            line-height: 1.65;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .ambient-cream-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 230, 211, 0.45) 0%, rgba(250, 248, 245, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Top Bar & Navbar */
        .daleel-topbar {
            background-color: #F4ECE1;
            font-size: 0.8rem;
            color: #6B7280;
            border-bottom: 1px solid #EBE3D7;
            padding: 5px 0;
        }

        .daleel-navbar {
            background-color: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1040;
            transition: all 0.25s ease;
        }

        .daleel-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-main);
        }

        .daleel-logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-navy) 0%, var(--brand-blue) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 1.25rem;
            box-shadow: 0 4px 14px rgba(30, 95, 168, 0.25);
        }

        .daleel-brand-title {
            font-weight: 800;
            font-size: 1.18rem;
            color: var(--brand-navy);
            line-height: 1.15;
            margin: 0;
        }

        .daleel-brand-tag {
            font-size: 0.72rem;
            color: var(--brand-gold);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .nav-link-custom {
            color: var(--text-body);
            font-weight: 600;
            font-size: 0.92rem;
            padding: 8px 14px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .nav-link-custom:hover {
            color: var(--brand-blue);
            background-color: var(--bg-surface-soft);
        }

        /* Buttons & CTAs */
        .btn-luxury-primary {
            background: linear-gradient(135deg, #1E5FA8 0%, #16467D 100%);
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 9px 20px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 14px rgba(30, 95, 168, 0.22);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-luxury-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 95, 168, 0.32);
            background: linear-gradient(135deg, #16467D 0%, #0F325C 100%);
        }

        .btn-luxury-outline {
            background: #FFFFFF;
            color: var(--brand-navy) !important;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 8px 18px;
            border-radius: 12px;
            border: 1.5px solid var(--border-subtle);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-luxury-outline:hover {
            border-color: var(--brand-blue);
            color: var(--brand-blue) !important;
            background: var(--bg-surface-tint);
            transform: translateY(-1px);
        }

        .btn-luxury-gold {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: #FFFFFF !important;
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-luxury-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(217, 119, 6, 0.35);
        }

        /* Cards & Styling */
        .luxury-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            position: relative;
        }

        .luxury-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
            border-color: #DFD5C7;
        }

        /* Badges */
        .badge-schoplus-smart {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            color: #92400E;
            border: 1px solid #FCD34D;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-verified {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-stage-pill {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .star-rating {
            color: #F59E0B;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
    </style>
</head>
<body>

    <div class="ambient-cream-glow" style="top: -200px; right: -200px;"></div>
    <div class="ambient-cream-glow" style="top: 800px; left: -250px;"></div>

    <!-- Top Announcement Bar -->
    <div class="daleel-topbar d-none d-md-block">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span><i class="fas fa-certificate text-warning me-1"></i> دليل المدارس الرسمي في السودان بدعم من منظومة SchoPlus</span>
                <span>•</span>
                <span class="text-success"><i class="fas fa-check-circle me-1"></i> خدمة مجانية للبحث ومقارنة المدارس</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="https://schoplus.sd" target="_blank" class="text-decoration-none text-muted"><i class="fas fa-globe me-1"></i> موقع SchoPlus الرسمي</a>
                <span>•</span>
                <a href="https://wa.me/249912345678" target="_blank" class="text-decoration-none text-muted"><i class="fab fa-whatsapp me-1 text-success"></i> دعم الدليل</a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="daleel-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            
            <a href="index.php" class="daleel-brand">
                <div class="daleel-logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h1 class="daleel-brand-title">دليل المدارس</h1>
                    <span class="daleel-brand-tag"><i class="fas fa-sparkles me-1"></i>SCHOPLUS DIRECTORY</span>
                </div>
            </a>

            <nav class="d-none d-lg-flex align-items-center gap-1">
                <a href="index.php" class="nav-link-custom"><i class="fas fa-home me-1 text-muted"></i> الرئيسية</a>
                <a href="index.php#schoplus-schools" class="nav-link-custom"><i class="fas fa-star text-warning me-1"></i> المدارس الذكية</a>
                <a href="index.php#top-rated" class="nav-link-custom"><i class="fas fa-award text-primary me-1"></i> الأعلى تقييماً</a>
                <a href="index.php#search-section" class="nav-link-custom"><i class="fas fa-search text-muted me-1"></i> استكشف المدارس</a>
                <a href="https://schoplus.sd" target="_blank" class="nav-link-custom"><i class="fas fa-info-circle text-muted me-1"></i> عن النظام</a>
            </nav>

            <div class="d-flex align-items-center gap-2">
                <?php if ($currSchool): ?>
                    <a href="dashboard.php" class="btn-luxury-outline">
                        <i class="fas fa-columns text-primary"></i>
                        <span>لوحة إدارة المدرسة</span>
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-light border text-danger" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-luxury-outline">
                        <i class="fas fa-user-tie text-secondary"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                    <a href="register.php" class="btn-luxury-primary">
                        <i class="fas fa-plus-circle"></i>
                        <span>سجّل مدرستك مجاناً</span>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <main>
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="container mt-3">
                <div class="alert alert-info border-0 shadow-sm alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-1"></i> <?= e($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>
