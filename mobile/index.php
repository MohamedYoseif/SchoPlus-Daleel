<?php
/**
 * SchoPlus Schools Directory — Mobile App Shell
 * Matches the prestigious UI/UX design of SchoPlus 5 Mobile Apps
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SudanGeography.php';

$pdo = db();

// Filter inputs
$q        = trim($_GET['q'] ?? '');
$state    = trim($_GET['state'] ?? '');
$city     = trim($_GET['city'] ?? '');
$stage    = trim($_GET['stage'] ?? '');
$tab      = trim($_GET['tab'] ?? 'home'); // home, search, news, schoplus
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

// Query schools based on tab & filters
$where = ["status = 'approved'"];
$params = [];

if ($tab === 'schoplus') {
    $where[] = "is_schoplus_client = 1";
}
if ($q !== '') {
    $where[] = "(name LIKE ? OR name_en LIKE ? OR district LIKE ? OR address LIKE ? OR description LIKE ?)";
    $term = "%{$q}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
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

$whereClause = implode(' AND ', $where);
$schoolsStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY is_featured DESC, is_schoplus_client DESC, rating_avg DESC, id ASC LIMIT 50");
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

        /* ── Hero Metrics Card: Financial & Stats Banner ── */
        .m-hero-card {
            background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
            border-radius: var(--m-radius);
            padding: 16px 18px;
            color: #ffffff;
            margin: 14px 14px 12px;
            box-shadow: 0 8px 24px -4px rgba(11, 42, 82, 0.25);
            position: relative;
            overflow: hidden;
        }
        .m-hero-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--m-brand-gold);
        }
        .m-hero-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .m-hero-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #E2E8F0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .m-hero-badge {
            font-size: 0.70rem;
            background: rgba(255, 255, 255, 0.16);
            color: #FFFFFF;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 700;
        }
        .m-hero-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            text-align: center;
        }
        .m-hero-val {
            font-size: 1.15rem;
            font-weight: 900;
            line-height: 1.2;
            color: #FFFFFF;
        }
        .m-hero-lbl {
            font-size: 0.65rem;
            opacity: 0.82;
            margin-top: 2px;
            font-weight: 600;
        }

        /* ── Search Bar: Sleek & Clean ── */
        .m-search-box {
            margin: 0 14px 12px;
            background: #ffffff;
            border: 1.5px solid var(--m-border);
            border-radius: 14px;
            display: flex;
            align-items: center;
            padding: 3px 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
            transition: all 0.2s ease;
        }
        .m-search-box:focus-within {
            border-color: var(--m-brand-blue);
            box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
        }
        .m-search-input {
            border: none;
            outline: none;
            width: 100%;
            padding: 9px 8px;
            font-size: 0.82rem;
            font-family: 'Cairo', sans-serif;
            font-weight: 600;
            color: var(--m-text);
            background: transparent;
        }
        .m-search-input::placeholder {
            color: var(--m-muted);
            font-weight: 500;
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
            padding: 4px 16px 8px;
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

        /* ── School Card: Matching SchoPlus Glass & Portal Cards ── */
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
            padding: 4px 12px;
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
            font-size: 0.82rem;
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
            display: inline-block;
            background: #F1F5F9;
            color: #334155;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            margin: 2px 4px 4px 0;
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
                <span class="m-brand-subtitle">المنظومة الوطنية للتعليم الذكي</span>
            </div>
        </a>
        <div class="m-header-actions">
            <?php if ($selectedSchool): ?>
                <a href="/mobile/?tab=<?= urlencode($tab) ?>" class="m-btn-icon" title="رجوع">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php else: ?>
                <a href="/mobile/" class="m-btn-icon" title="تحديث">
                    <i class="fas fa-redo-alt text-warning"></i>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($selectedSchool): ?>
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW: DETAILED SCHOOL PROFILE (NATIVE MOBILE VIEW)       -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <?php
            $s = $selectedSchool;
            $sLogo = !empty($s['logo_path']) ? '/' . ltrim($s['logo_path'], '/') : '/assets/images/default-school-logo.svg';
            $stages = !empty($s['stages']) ? json_decode($s['stages'], true) : [];
            $facilities = !empty($s['facilities']) ? json_decode($s['facilities'], true) : [];
            $branches = !empty($s['branches_data']) ? json_decode($s['branches_data'], true) : [];
        ?>
        <div class="m-detail-sheet">
            
            <div class="m-detail-header-card">
                <img src="<?= e($sLogo) ?>" alt="<?= e($s['name']) ?>" class="m-detail-logo">
                <h1 class="m-detail-name"><?= e($s['name']) ?></h1>
                <div class="m-detail-loc">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                    ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= $s['district'] ? '— ' . e($s['district']) : '' ?>
                </div>

                <div class="d-flex justify-content-center gap-2 mt-3">
                    <?php if ($s['is_schoplus_client']): ?>
                        <span class="m-card-schoplus-tag">⚡ مدرسة SchoPlus ذكية</span>
                    <?php endif; ?>
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

            <!-- Description -->
            <?php if (!empty($s['description'])): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-info-circle text-primary"></i> نبذة عن المدرسة</div>
                    <div class="m-info-content"><?= nl2br(e($s['description'])) ?></div>
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
                            <span class="m-tag-pill"><i class="fas fa-check-circle text-success me-1"></i><?= e($stgName) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Facilities -->
            <?php if (!empty($facilities)): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-cube text-info"></i> المرافق والخدمات</div>
                    <div>
                        <?php foreach ((array)$facilities as $fac): ?>
                            <span class="m-tag-pill"><?= e($fac) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Address & Location -->
            <?php if (!empty($s['address']) || !empty($s['google_map_url'])): ?>
                <div class="m-info-block">
                    <div class="m-info-title"><i class="fas fa-map-marked-alt text-danger"></i> العنوان والموقع</div>
                    <div class="m-info-content"><?= e($s['address'] ?: 'ولاية ' . $s['state'] . ' — ' . $s['city']) ?></div>
                    <?php if (!empty($s['google_map_url'])): ?>
                        <a href="<?= e($s['google_map_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill mt-2 w-100 fw-bold" style="font-size:0.74rem;">
                            <i class="fas fa-location-arrow me-1"></i> فتح الخريطة (Google Maps)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

    <?php elseif ($tab === 'news'): ?>
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW: NEWS & EVENTS TAB                                  -->
        <!-- ══════════════════════════════════════════════════════════ -->
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
                    <?php
                        $nLogo = !empty($n['school_logo']) ? '/' . ltrim($n['school_logo'], '/') : '/assets/images/default-school-logo.svg';
                    ?>
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

    <?php else: ?>
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- VIEW: MAIN HOME & SEARCH VIEW (DEFAULT)                  -->
        <!-- ══════════════════════════════════════════════════════════ -->

        <!-- Financial & Status Metrics Card (SchoPlus Style) -->
        <div class="m-hero-card">
            <div class="m-hero-head">
                <span class="m-hero-title"><i class="fas fa-chart-pie text-warning"></i> مؤشرات دليل المدارس</span>
                <span class="m-hero-badge">تحديث مباشر</span>
            </div>
            <div class="m-hero-grid">
                <div>
                    <div class="m-hero-val"><?= number_format($totalSchools) ?>+</div>
                    <div class="m-hero-lbl">مدرسة معتمدة</div>
                </div>
                <div>
                    <div class="m-hero-val"><?= number_format($schoplusCount) ?></div>
                    <div class="m-hero-lbl">مدارس SchoPlus</div>
                </div>
                <div>
                    <div class="m-hero-val"><?= $statesCount ?></div>
                    <div class="m-hero-lbl">ولاية سودانية</div>
                </div>
            </div>
        </div>

        <!-- Integrated Search Bar -->
        <form action="/mobile/" method="GET" class="m-search-box">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <?php if ($state): ?><input type="hidden" name="state" value="<?= e($state) ?>"><?php endif; ?>
            <i class="fas fa-search text-muted"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="ابحث باسم المدرسة، الولاية، أو الحي..." class="m-search-input" autocomplete="off">
            <?php if ($q !== ''): ?>
                <a href="/mobile/?tab=<?= e($tab) ?><?= $state ? '&state='.urlencode($state) : '' ?>" class="text-muted text-decoration-none px-2">
                    <i class="fas fa-times-circle"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Horizontal States Filter Bar -->
        <div class="m-states-bar">
            <a href="/mobile/?tab=<?= e($tab) ?><?= $q ? '&q='.urlencode($q) : '' ?>" class="m-state-pill <?= $state === '' ? 'active' : '' ?>">
                <span>الكل</span>
                <span class="m-state-pill-badge"><?= $totalSchools ?></span>
            </a>
            <?php foreach (SudanGeography::states() as $stName): ?>
                <?php $cnt = (int)($stateCounts[$stName] ?? 0); ?>
                <a href="/mobile/?tab=<?= e($tab) ?>&state=<?= urlencode($stName) ?><?= $q ? '&q='.urlencode($q) : '' ?>" class="m-state-pill <?= $state === $stName ? 'active' : '' ?>">
                    <span><?= e($stName) ?></span>
                    <?php if ($cnt > 0): ?>
                        <span class="m-state-pill-badge"><?= $cnt ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Schools Section Header -->
        <div class="m-sec-head">
            <span class="m-sec-title">
                <?php if ($tab === 'schoplus'): ?>
                    <i class="fas fa-bolt text-warning"></i> مدارس SchoPlus الذكية
                <?php elseif ($state !== ''): ?>
                    <i class="fas fa-map-marker-alt text-danger"></i> مدارس ولاية <?= e($state) ?>
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
                <?php
                    $scLogo = !empty($sc['logo_path']) ? '/' . ltrim($sc['logo_path'], '/') : '/assets/images/default-school-logo.svg';
                ?>
                <div class="m-school-card">
                    <a href="/mobile/?school_id=<?= (int)$sc['id'] ?>" class="text-decoration-none text-dark">
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

                        <?php if (!empty($sc['description'])): ?>
                            <div class="m-card-desc"><?= e($sc['description']) ?></div>
                        <?php endif; ?>
                    </a>

                    <div class="m-card-actions">
                        <span class="m-card-tag"><?= e($sc['school_type'] ?? 'مدرسة خاصة') ?></span>

                        <div class="m-card-btns">
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
        <a href="/mobile/?tab=search" class="m-nav-center-btn <?= $tab === 'search' ? 'active' : '' ?>" title="استكشف المدارس">
            <i class="fas fa-search"></i>
        </a>

        <a href="/mobile/?tab=news" class="m-nav-link <?= $tab === 'news' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span>الأخبار</span>
        </a>

        <a href="https://schoplus.sd" target="_blank" class="m-nav-link">
            <i class="fas fa-info-circle"></i>
            <span>عن النظام</span>
        </a>
    </nav>

</body>
</html>
