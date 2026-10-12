<?php
$pageTitle = 'دليل المدارس — دليلك الشامل لاختيار أفضل المدارس';
require_once __DIR__ . '/header.php';

$pdo = db();

// Filter Inputs
$q = trim($_GET['q'] ?? '');
$country = trim($_GET['country'] ?? '');
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$stage = trim($_GET['stage'] ?? '');
$schoplusOnly = !empty($_GET['schoplus_only']);
$sort = trim($_GET['sort'] ?? 'featured');
$view = trim($_GET['view'] ?? ''); // 'all', 'schoplus', 'top_rated', 'latest', or ''

// Determine if user is performing a specific search or filter
$isSearching = ($q !== '' || $country !== '' || $state !== '' || $city !== '' || $stage !== '' || $schoplusOnly || in_array($view, ['all', 'schoplus', 'top_rated', 'latest']));

// Geography & standard mappings
$countriesList = SudanGeography::countries();
$countriesWithStates = SudanGeography::countriesWithStatesAndCities();
$statesWithCities = !empty($country) ? SudanGeography::statesWithCities($country) : SudanGeography::statesWithCities();
$schoolStages = SudanGeography::schoolStages();
$schoolTypes = SudanGeography::schoolTypes();

// Statistics
$statTotal = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$statSchoplus = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$statStates = (int) $pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: count(SudanGeography::states());
$statReviews = (int) $pdo->query("SELECT COUNT(*) FROM daleel_school_reviews WHERE is_approved = 1")->fetchColumn();
$totalTopRatedCount = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND rating_avg >= 4.0")->fetchColumn();

// State school counters for the state cards
$stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Distinct states list
$allStatesList = SudanGeography::states();

// Query for search or view
if ($isSearching) {
    $where = ["status = 'approved'"];
    $params = [];

    if ($q !== '') {
        $where[] = "(name LIKE ? OR name_en LIKE ? OR district LIKE ? OR address LIKE ? OR description LIKE ?)";
        $term = "%{$q}%";
        $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
    }

    if ($country !== '') {
        $where[] = "country = ?";
        $params[] = $country;
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

    if ($schoplusOnly || $view === 'schoplus') {
        $where[] = "is_schoplus_client = 1";
    }

    $whereClause = implode(' AND ', $where);

    $orderBy = "is_featured DESC, is_schoplus_client DESC, rating_avg DESC";
    if ($sort === 'rating' || $view === 'top_rated') $orderBy = "rating_avg DESC, reviews_count DESC";
    if ($sort === 'reviews') $orderBy = "reviews_count DESC";
    if ($sort === 'views') $orderBy = "views_count DESC";
    if ($sort === 'latest' || $view === 'latest') $orderBy = "created_at DESC";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalMatching = (int) $countStmt->fetchColumn();

    $searchStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY {$orderBy}");
    $searchStmt->execute($params);
    $searchResults = $searchStmt->fetchAll();
} else {
    // Default home view:
    // 1. Featured / SchoPlus Showcase: max 6 schools
    $schoplusShowcase = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1 ORDER BY is_featured DESC, rating_avg DESC LIMIT 6")->fetchAll();
    // 2. Default catalog:
    $catalogSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY is_featured DESC, is_schoplus_client DESC, rating_avg DESC LIMIT 15")->fetchAll();
}

/**
 * Reusable School Card Renderer (Single Source of Truth for Style F v2)
 */
$renderSchoolCard = function($s) use ($pdo, $schoolStages, $schoolTypes) {
    $logo = e(defaultSchoolLogo($s['logo_path'] ?? null));
    $cover = !empty($s['cover_path']) ? e(assetUrl($s['cover_path'])) : '';
    $stagesArr = json_decode($s['stages'] ?? '[]', true) ?: [];
    $schoolUrl = '/school/' . urlencode($s['slug']);
    $sRank = getSchoolDirectoryRank($pdo, (int)$s['id'], !empty($s['directory_rank']) ? (int)$s['directory_rank'] : null);
    $sStateRank = getSchoolStateRank($pdo, (int)$s['id'], (string)($s['state'] ?? ''), !empty($s['state_rank']) ? (int)$s['state_rank'] : null);
?>
<div class="col-lg-4 col-md-6">
    <div class="luxury-card clickable-school-card h-100 d-flex flex-column" onclick="window.location.href='<?= $schoolUrl ?>'">
        
        <div class="card-cover-wrap" style="<?= $cover ? "background-image: url('{$cover}');" : '' ?>">
            <div class="card-cover-overlay"></div>
            <?php if (!$cover): ?>
                <div class="cover-placeholder-icon"><i class="fas fa-school"></i></div>
            <?php endif; ?>

            <div style="position: absolute; top: 10px; left: 10px; z-index: 2; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                <span class="badge-directory-rank"><i class="fas fa-award"></i> الترتيب #<?= $sRank ?></span>
                <?php if (!empty($s['is_schoplus_client'])): ?>
                    <span class="badge-schoplus-smart"><i class="fas fa-bolt"></i> SchoPlus الذكي</span>
                <?php endif; ?>
            </div>

            <div class="card-logo-badge">
                <img src="<?= $logo ?>" alt="" loading="lazy">
            </div>
        </div>

        <div style="padding: 26px 16px 14px; display: flex; flex-direction: column; flex-grow: 1;">
            
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <a href="<?= $schoolUrl ?>" style="font-weight: 700; font-size: 0.9rem; color: #0B2A52; text-decoration: none;" onclick="event.stopPropagation();">
                    <?= e($s['name']) ?>
                </a>
                <span class="badge-state-rank-tag" title="الترتيب في ولاية <?= e($s['state']) ?>">#<?= $sStateRank ?> <?= e($s['state']) ?></span>
            </div>

            <div class="d-flex align-items-center gap-1 mb-2 text-muted" style="font-size: 0.72rem;">
                <i class="fas fa-map-marker-alt text-danger me-1"></i>
                <span><?= e($s['country'] ?? 'السودان') ?> — ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= !empty($s['district']) ? '('.e($s['district']).')' : '' ?></span>
            </div>

            <p class="school-card-desc mb-2" style="font-size: 0.73rem; color: #475569;">
                <?= e($s['description'] ?: 'مؤسسة تعليمية وتربوية تقدم برامج أكاديمية متميزة وبيئة متطورة للطلاب.') ?>
            </p>

            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px;">
                <?php foreach(array_slice($stagesArr, 0, 3) as $st): ?>
                    <span class="badge-stage-pill"><?= e($schoolStages[$st] ?? $st) ?></span>
                <?php endforeach; ?>
                <span class="badge" style="background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; font-size: 0.68rem; font-weight: 700;"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                <?php if (!empty($s['established_year'])): ?>
                    <span class="badge-established"><i class="fas fa-calendar-alt me-1"></i> تأسست <?= e($s['established_year']) ?></span>
                <?php endif; ?>
            </div>

            <div style="padding-top: 12px; border-top: 1px solid #EDF2F7; display: flex; align-items: center; justify-content: space-between; margin-top: auto;">
                <div class="star-rating">
                    <i class="fas fa-star"></i>
                    <span class="fw-bold text-dark" style="font-size: 0.78rem;"><?= number_format($s['rating_avg'], 1) ?></span>
                    <span class="text-muted" style="font-size: 0.68rem;">(<?= $s['reviews_count'] ?>)</span>
                </div>

                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn-compare-card" data-compare-id="<?= $s['id'] ?>" onclick="event.stopPropagation(); toggleSchoolCompare({id: '<?= $s['id'] ?>', name: '<?= addslashes(e($s['name'])) ?>', logo: '<?= addslashes($logo) ?>', slug: '<?= addslashes($s['slug']) ?>'});" title="إضافة إلى المقارنة">
                        <i class="fas fa-balance-scale"></i>
                        <span class="compare-lbl">مقارنة</span>
                    </button>
                    <?php if (!empty($s['whatsapp'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="واتساب المدرسة" onclick="event.stopPropagation();">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    <?php endif; ?>
                    <a href="<?= $schoolUrl ?>" class="btn-luxury-outline btn-sm py-1 px-2" onclick="event.stopPropagation();" style="font-size: 0.72rem;">
                        <span>عرض التفاصيل</span>
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
<?php
};
?>

<style>
/* ===== SCHOPLUS STYLE F v2 HERO & SEARCH BAR ===== */
.daleel-hero-v2 {
    background: #F0F4F8;
    padding: 34px 0 30px;
    border-bottom: 1px solid #DDE4ED;
    position: relative;
}
.hero-v2-container {
    max-width: 1240px;
    margin: 0 auto;
}
.hero-v2-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #0B2A52;
    margin-bottom: 10px;
}
.hero-v2-eyebrow-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #F5A020;
}
.hero-v2-title {
    font-weight: 800;
    font-size: clamp(1.35rem, 2.8vw, 1.9rem);
    color: #0B2A52;
    line-height: 1.35;
    margin-bottom: 8px;
    letter-spacing: -0.3px;
}
.hero-v2-title-sub {
    display: block;
    font-size: clamp(0.88rem, 1.8vw, 1.1rem);
    color: #1E5FA8;
    font-weight: 700;
    margin-top: 4px;
}
.hero-v2-desc {
    font-size: 0.76rem;
    color: #64748B;
    line-height: 1.65;
    margin-bottom: 16px;
}

/* Family Hero Photo Card (Left Column) */
.hero-family-wrapper {
    position: relative;
    padding: 10px;
}
.hero-family-card {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    box-shadow: 0 16px 36px rgba(11, 42, 82, 0.08);
}
.hero-family-img {
    width: 100%;
    height: 100%;
    max-height: 390px;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.hero-family-card:hover .hero-family-img {
    transform: scale(1.02);
}
.hero-family-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 70px;
    background: linear-gradient(180deg, transparent 0%, rgba(11, 42, 82, 0.85) 100%);
    pointer-events: none;
}
.hero-family-caption {
    position: absolute;
    bottom: 12px;
    right: 16px;
    left: 16px;
    color: #FFFFFF;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 8px;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
}

/* Floating Trust Chips */
.floating-family-chip {
    position: absolute;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 8px 24px rgba(11, 42, 82, 0.12);
    z-index: 3;
    transition: transform 0.25s ease;
}
.floating-family-chip:hover {
    transform: translateY(-2px);
}
.chip-top-right {
    top: -6px;
    right: -10px;
}
.chip-bottom-left {
    bottom: 24px;
    left: -10px;
}
.chip-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.chip-icon.bg-blue {
    background: #EFF6FF;
    color: #1E5FA8;
}
.chip-icon.bg-orange {
    background: #FFFBEB;
    color: #F5A020;
}
.chip-text {
    display: flex;
    flex-direction: column;
    text-align: right;
    line-height: 1.25;
}
.chip-title {
    font-size: 0.72rem;
    font-weight: 800;
    color: #0B2A52;
}
.chip-sub {
    font-size: 0.62rem;
    color: #64748B;
    font-weight: 600;
}

/* Smart Unified Search Bar Card */
.filter-bar-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 10px 30px rgba(11, 42, 82, 0.08);
    position: relative;
    z-index: 20;
    text-align: right;
}
.filter-bar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 1px solid #EDF2F7;
}
.filter-bar-title {
    font-size: 0.74rem;
    font-weight: 700;
    color: #0B2A52;
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-quick-links {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.filter-quick-pill {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    text-decoration: none;
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
    transition: all 0.2s ease;
}
.filter-quick-pill:hover, .filter-quick-pill.is-active {
    background: #0B2A52;
    color: #FFFFFF;
    border-color: #0B2A52;
}

.filter-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 8px;
    padding: 2px 10px;
    transition: all 0.2s ease;
}
.filter-input-wrap:focus-within {
    border-color: #1E5FA8;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}
.filter-input-wrap input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.76rem;
    font-weight: 600;
    color: #0D1B2E;
    width: 100%;
    padding: 7px 6px;
}
.filter-select-wrap select {
    background-color: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #0D1B2E;
    padding: 8px 12px;
    transition: all 0.2s ease;
}
.filter-select-wrap select:focus {
    border-color: #1E5FA8;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}

/* Hero Highlights Strip */
.hero-highlights-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 18px;
}
.hero-highlight-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-right: 3px solid #1E5FA8;
    border-radius: 12px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: right;
    box-shadow: 0 2px 8px rgba(11, 42, 82, 0.03);
}
.hero-highlight-card.m-orange {
    border-right-color: #F5A020;
}
.hero-highlight-card.m-navy {
    border-right-color: #0B2A52;
}
.hero-highlight-card.m-emerald {
    border-right-color: #059669;
}
.hero-highlight-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
}
.hero-highlight-val {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0B2A52;
    line-height: 1.2;
}
.hero-highlight-lbl {
    font-size: 0.65rem;
    color: #64748B;
    font-weight: 600;
}
@media (max-width: 991px) {
    .floating-family-chip {
        display: none !important;
    }
    .hero-family-wrapper {
        margin-top: 14px;
        padding: 0;
    }
    .hero-family-img {
        max-height: 290px;
    }
}
@media (max-width: 767px) {
    .daleel-hero-v2 {
        padding: 24px 0 20px;
    }
    .hero-highlights-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .filter-bar-card {
        padding: 12px;
        border-radius: 14px;
    }
}

/* States Carousel */
.states-slider-container {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    padding: 6px 2px 14px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #CBD5E1 #F1F5F9;
}
.state-slide-card {
    flex: 0 0 auto;
    scroll-snap-align: start;
    width: 130px;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    padding: 10px 8px;
    text-align: center;
    text-decoration: none;
    color: #334155;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.state-slide-card:hover, .state-slide-card.active {
    border-color: #1E5FA8;
    background: #F0F4F8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 95, 168, 0.1);
}
.state-slide-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #F1F5F9;
    color: #0B2A52;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    margin-bottom: 4px;
}
.state-slide-card.active .state-slide-icon {
    background: #0B2A52;
    color: #FFFFFF;
}
.state-slide-title {
    font-size: 0.74rem;
    font-weight: 700;
    color: #0B2A52;
    margin-bottom: 2px;
}
.state-slide-count {
    font-size: 0.64rem;
    color: #64748B;
}

@media (max-width: 576px) {
    .states-slider-container {
        gap: 6px;
    }
    .state-slide-card {
        width: calc((100% - 12px) / 3) !important;
        min-width: calc((100% - 12px) / 3) !important;
        padding: 8px 4px;
    }
}

/* School Cards (SchoPlus Style F v2) */
.card-cover-wrap {
    height: 150px;
    background-size: cover;
    background-position: center;
    position: relative;
    background-color: #0B2A52;
    overflow: hidden;
}
.card-cover-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(11,42,82,0.15) 0%, rgba(11,42,82,0.65) 100%);
}
.card-logo-badge {
    width: 58px;
    height: 58px;
    border-radius: 12px;
    background: #FFFFFF;
    padding: 3px;
    box-shadow: 0 4px 12px rgba(11,42,82,0.12);
    border: 2px solid #FFFFFF;
    position: absolute;
    bottom: -20px;
    right: 16px;
    z-index: 2;
}
.card-logo-badge img {
    width: 100%;
    height: 100%;
    border-radius: 9px;
    object-fit: cover;
}
.clickable-school-card {
    cursor: pointer;
    transition: all 0.25s ease;
}
.clickable-school-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(11, 42, 82, 0.12);
    border-color: #CBD5E1;
}
</style>

<!-- HERO SECTION WITH INTEGRATED SEARCH -->
<section class="daleel-hero-v2 mb-4" id="search-section">
    <div class="container hero-v2-container">
        <div class="row align-items-center g-4">

            <!-- Right Column (In RTL): Headline, Unified Filter Console, and Metrics -->
            <div class="col-lg-7">
                <div class="text-start">
                    <div class="hero-v2-eyebrow">
                        <span class="hero-v2-eyebrow-dot"></span>
                        <span>المنصة المعتمدة لدليل المدارس السودانية والعربية</span>
                    </div>

                    <h1 class="hero-v2-title">
                        دليل المدارس الشامل
                        <span class="hero-v2-title-sub">ابحث، قارن، واختر المدرسة الأنسب لأبنائك بكل ثقة واطمئنان</span>
                    </h1>

                    <p class="hero-v2-desc">
                        تصفح بيانات المدارس المعتمدة، الرسوم الدراسية، المناهج التعليمية، وتقييمات أولياء الأمور الحقيقية في منصة رقمية موحدة وشاملة.
                    </p>

                    <!-- Android App Quick Announcement Banner (Mobile only) -->
                    <div class="mb-3 d-inline-flex d-md-none align-items-center gap-2 px-3 py-1 bg-white border rounded-pill shadow-sm" style="border-color: #E2E8F0 !important;">
                        <span class="badge bg-success text-white rounded-pill px-2 py-1" style="font-size: 0.65rem;"><i class="fab fa-android me-1"></i> جديد</span>
                        <span class="text-dark fw-bold" style="font-size: 0.73rem;">تطبيق دليل المدارس متاح الآن للأندرويد (APK)</span>
                        <a href="/app.php" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 fw-bold" style="font-size: 0.68rem; line-height: 1.6;">
                            تحميل <i class="fas fa-arrow-left ms-1"></i>
                        </a>
                    </div>
                </div>

                <!-- SMART UNIFIED FILTER BAR (Organized in 2 clean rows) -->
                <form action="/" method="GET" class="filter-bar-card" id="smartFilterForm">
                    <div class="filter-bar-header">
                        <div class="filter-bar-title">
                            <i class="fas fa-filter text-primary"></i>
                            <span>تصفية سريعة ومباشرة</span>
                        </div>
                        <div class="filter-quick-links">
                            <a href="/?view=schoplus" class="filter-quick-pill <?= $view === 'schoplus' || $schoplusOnly ? 'is-active' : '' ?>"><i class="fas fa-bolt text-primary me-1"></i> مدارس SchoPlus</a>
                            <a href="/?view=top_rated" class="filter-quick-pill <?= $view === 'top_rated' ? 'is-active' : '' ?>"><i class="fas fa-star text-warning me-1"></i> الأعلى تقييماً</a>
                            <a href="/?stage=primary" class="filter-quick-pill <?= $stage === 'primary' ? 'is-active' : '' ?>">الابتدائي</a>
                            <a href="/?stage=intermediate" class="filter-quick-pill <?= $stage === 'intermediate' ? 'is-active' : '' ?>">المتوسط</a>
                            <a href="/?stage=secondary" class="filter-quick-pill <?= $stage === 'secondary' ? 'is-active' : '' ?>">الثانوي</a>
                        </div>
                    </div>

                    <!-- Row 1: Geographic Cascading Dropdowns (Country, State, City) -->
                    <div class="row g-2 mb-2">
                        <!-- 1. Country Filter -->
                        <div class="col-md-4 col-12 filter-select-wrap">
                            <select name="country" class="form-select form-select-sm" id="filterCountrySelect">
                                <option value="">جميع الدول</option>
                                <?php foreach($countriesList as $cntry): ?>
                                    <option value="<?= e($cntry) ?>" <?= $country === $cntry ? 'selected' : '' ?>><?= e($cntry) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 2. State Filter -->
                        <div class="col-md-4 col-12 filter-select-wrap">
                            <select name="state" class="form-select form-select-sm" id="filterStateSelect">
                                <option value="">جميع الولايات</option>
                                <?php foreach($statesWithCities as $stName => $cities): ?>
                                    <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 3. City Filter -->
                        <div class="col-md-4 col-12 filter-select-wrap">
                            <select name="city" class="form-select form-select-sm" id="filterCitySelect">
                                <option value="">جميع المدن / المحليات</option>
                                <?php if ($state && isset($statesWithCities[$state])): ?>
                                    <?php foreach($statesWithCities[$state] as $c): ?>
                                        <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Stage Filter, School Name Search, Submit -->
                    <div class="row g-2 align-items-center">
                        <!-- 4. Stage Filter -->
                        <div class="col-md-4 col-12 filter-select-wrap">
                            <select name="stage" class="form-select form-select-sm" id="filterStageSelect">
                                <option value="">جميع المراحل الدراسية</option>
                                <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                    <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 5. School Name / Keyword Input -->
                        <div class="col-md-6 col-9">
                            <div class="filter-input-wrap">
                                <i class="fas fa-search text-muted me-1"></i>
                                <input type="text" name="q" value="<?= e($q) ?>" placeholder="ابحث باسم المدرسة، الحي..." autocomplete="off">
                                <?php if ($q !== ''): ?>
                                    <a href="/" class="text-muted small text-decoration-none px-1" title="مسح"><i class="fas fa-times-circle"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-md-2 col-3 d-flex gap-1">
                            <button type="submit" class="btn-luxury-primary w-100 justify-content-center py-2" title="بحث فوري">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if ($isSearching): ?>
                                <a href="/" class="btn btn-light border py-2 px-2 rounded-3 text-secondary" title="إعادة ضبط">
                                    <i class="fas fa-undo"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Hero Highlights Strip -->
                <div class="hero-highlights-grid">
                    <div class="hero-highlight-card">
                        <div class="hero-highlight-icon" style="background: #EFF6FF; color: var(--brand-blue);">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <div class="hero-highlight-val" data-countup="<?= $statTotal ?>" data-suffix="+"><?= number_format($statTotal) ?>+</div>
                            <div class="hero-highlight-lbl">مدرسة مسجلة بالدليل</div>
                        </div>
                    </div>
                    <div class="hero-highlight-card m-navy">
                        <div class="hero-highlight-icon" style="background: #F8FAFC; color: #0B2A52;">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div>
                            <div class="hero-highlight-val" data-countup="<?= $statStates ?>"><?= $statStates ?></div>
                            <div class="hero-highlight-lbl">ولاية سودانية مغطاة</div>
                        </div>
                    </div>
                    <div class="hero-highlight-card m-orange">
                        <div class="hero-highlight-icon" style="background: #FFFBEB; color: #F5A020;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <div class="hero-highlight-val" data-countup="<?= $statSchoplus ?>"><?= number_format($statSchoplus) ?></div>
                            <div class="hero-highlight-lbl">مدرسة بنظام SchoPlus الذكي</div>
                        </div>
                    </div>
                    <div class="hero-highlight-card m-emerald">
                        <div class="hero-highlight-icon" style="background: #F0FDF4; color: #059669;">
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <div>
                            <div class="hero-highlight-val" data-countup="<?= $statReviews ?>" data-suffix="+"><?= number_format($statReviews) ?>+</div>
                            <div class="hero-highlight-lbl">تقييم ومشاركة أولياء الأمور</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Left Column (In RTL): Front-Facing Family Photo Card & Floating Trust Chips -->
            <div class="col-lg-5">
                <div class="hero-family-wrapper">
                    <div class="hero-family-card">
                        <img src="/assets/images/family-hero.jpg" alt="أسرة تناقش وتختار المدرسة الأنسب لطفلتهم في دليل المدارس الذكي" class="hero-family-img" loading="eager">
                        <div class="hero-family-overlay"></div>
                        <div class="hero-family-caption">
                            <i class="fas fa-heart text-warning"></i>
                            <span>اختيار مدرسة طفلتك... قرار أسري يبدأ من هنا</span>
                        </div>
                    </div>

                    <!-- Floating Trust Badges -->
                    <div class="floating-family-chip chip-top-right">
                        <div class="chip-icon bg-blue"><i class="fas fa-shield-alt"></i></div>
                        <div class="chip-text">
                            <span class="chip-title">بيانات ومناهج معتمدة</span>
                            <span class="chip-sub">تحديث دوري وموثق</span>
                        </div>
                    </div>

                    <div class="floating-family-chip chip-bottom-left">
                        <div class="chip-icon bg-orange"><i class="fas fa-star"></i></div>
                        <div class="chip-text">
                            <span class="chip-title">تقييمات أولياء الأمور</span>
                            <span class="chip-sub">تجارب حقيقية وموثوقة</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- STATES CAROUSEL SECTION -->
<section class="mb-4" id="states-slider-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <span style="color: #1E5FA8; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                    <i class="fas fa-map-marked-alt me-1"></i> التوزيع الجغرافي
                </span>
                <h3 style="font-size: 0.98rem; font-weight: 700; color: #0B2A52; margin: 0;">تصفح المدارس حسب الولايات</h3>
            </div>
            <div class="d-none d-md-block text-muted small" style="font-size: 0.7rem;">
                اسحب لتصفح كافة ولايات السودان الـ 18 <i class="fas fa-arrows-left-right text-primary ms-1"></i>
            </div>
        </div>

        <div class="states-slider-container">
            <?php foreach($allStatesList as $stName): ?>
                <?php 
                    $countInState = $stateCounts[$stName] ?? 0;
                    $isSelectedState = ($state === $stName);
                    $stateUrl = $isSelectedState ? '/' : '/?state=' . urlencode($stName);
                ?>
                <a href="<?= $stateUrl ?>" class="state-slide-card <?= $isSelectedState ? 'active' : '' ?>" title="مدارس ولاية <?= e($stName) ?>">
                    <div class="state-slide-icon">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div class="state-slide-title"><?= e($stName) ?></div>
                    <div class="state-slide-count"><?= $countInState ?> <?= $countInState == 1 ? 'مدرسة' : 'مدارس' ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- MAIN DIRECTORY CATALOG WITH STANDARD MAIN TABS -->
<section class="py-3" id="directory-catalog-section">
    <div class="container">
        
        <!-- Mandatory Specification for Main Tabs (.sp-main-tabs) -->
        <div class="sp-main-tabs-wrap">
            <div class="sp-main-tabs">
                <a href="/" class="sp-main-tab <?= (empty($view) || $view === 'all') && empty($stage) && !$schoplusOnly && empty($state) && empty($q) ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i> كافة المدارس
                    <span class="tab-badge"><?= number_format($statTotal) ?></span>
                </a>
                <a href="/?view=schoplus" class="sp-main-tab <?= $view === 'schoplus' || $schoplusOnly ? 'active' : '' ?>">
                    <i class="fas fa-bolt"></i> مدارس SchoPlus الذكية
                    <span class="tab-badge"><?= number_format($statSchoplus) ?></span>
                </a>
                <a href="/?view=top_rated" class="sp-main-tab <?= $view === 'top_rated' || $sort === 'rating' ? 'active' : '' ?>">
                    <i class="fas fa-star"></i> الأعلى تقييماً
                    <span class="tab-badge"><?= number_format($totalTopRatedCount) ?></span>
                </a>
                <a href="/?view=latest" class="sp-main-tab <?= $view === 'latest' || $sort === 'latest' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> المضافة حديثاً
                    <span class="tab-badge"><?= number_format($statTotal) ?></span>
                </a>
            </div>
        </div>

        <?php if ($isSearching): ?>
            <!-- SEARCH RESULTS HEADER -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">
                        <?php if ($view === 'schoplus' || $schoplusOnly): ?>
                            مدارس معتمدة بنظام SchoPlus الذكي
                        <?php elseif ($view === 'top_rated' || $sort === 'rating'): ?>
                            المدارس الأعلى تقييماً في الدليل
                        <?php elseif ($view === 'latest' || $sort === 'latest'): ?>
                            أحدث المدارس المنضمة إلى الدليل
                        <?php elseif ($country !== '' || $state !== '' || $city !== '' || $stage !== '' || $q !== ''): ?>
                            نتائج البحث المخصص
                        <?php else: ?>
                            جميع المدارس المسجلة
                        <?php endif; ?>
                    </h2>
                    <p style="color: #64748B; font-size: 0.74rem; margin-top: 4px; margin-bottom: 0;">
                        تم العثور على <strong><?= number_format($totalMatching) ?></strong> مدرسة مطابقة لمعايير البحث.
                    </p>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2 align-items-center">
                    <a href="/" class="btn-luxury-outline btn-sm">
                        <i class="fas fa-arrow-right"></i>
                        <span>عرض كافة المدارس</span>
                    </a>
                </div>
            </div>

            <!-- SEARCH RESULTS GRID -->
            <div class="row g-4">
                <?php if (empty($searchResults)): ?>
                    <div class="col-12 py-5 text-center">
                        <div class="p-5 bg-white rounded-3 border text-center shadow-sm max-w-md mx-auto" style="max-width: 480px; margin: 0 auto;">
                            <i class="fas fa-school fa-3x text-muted mb-3 opacity-50"></i>
                            <h4 class="fw-bold text-dark" style="font-size: 1.05rem;">لا توجد مدارس مطابقة للبحث حالياً</h4>
                            <p class="text-muted small" style="font-size: 0.74rem;">جرب تغيير شروط الفلترة أو مسح اسم المدرسة لاكتشاف خيارات أوسع.</p>
                            <a href="/" class="btn-luxury-primary btn-sm mt-2">
                                <i class="fas fa-undo me-1"></i> إعادة ضبط وعرض كل المدارس
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($searchResults as $s): ?>
                        <?php $renderSchoolCard($s); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- DEFAULT VIEW: FEATURED SCHOPLUS SHOWCASE -->
            <?php if (!empty($schoplusShowcase)): ?>
            <div class="mb-5">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div>
                        <span style="color: #1E5FA8; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                            <i class="fas fa-award me-1"></i> التميز والرقمنة
                        </span>
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">مدارس ذكية رائدة تعمل بنظام SchoPlus</h2>
                    </div>
                    <?php if ($statSchoplus > 6): ?>
                        <div class="mt-2 mt-md-0">
                            <a href="/?view=schoplus" class="btn-luxury-outline btn-sm">
                                <span>عرض جميع المدارس الذكية (<?= $statSchoplus ?>)</span>
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-4">
                    <?php foreach($schoplusShowcase as $s): ?>
                        <?php $renderSchoolCard($s); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- DEFAULT VIEW: MAIN CATALOG GRID -->
            <div>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 pb-2 border-bottom">
                    <div>
                        <span style="color: #64748B; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                            <i class="fas fa-list me-1"></i> استكشف المدارس
                        </span>
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">دليل المدارس المعتمدة في السودان</h2>
                    </div>
                    <div class="mt-2 mt-md-0 text-muted" style="font-size: 0.72rem;">
                        عرض <strong><?= count($catalogSchools) ?></strong> من أصل <strong><?= $statTotal ?></strong> مدرسة مسجلة
                    </div>
                </div>

                <div class="row g-4">
                    <?php foreach($catalogSchools as $s): ?>
                        <?php $renderSchoolCard($s); ?>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>
</section>

<!-- SCHOOL OWNERS CTA BANNER -->
<section class="py-5 mt-4" style="background: #0B2A52; color: #FFFFFF;">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 160, 32, 0.2); border: 1px solid rgba(245, 160, 32, 0.4); color: #FFFFFF; font-weight: 700; font-size: 0.72rem; padding: 4px 12px; border-radius: 20px; margin-bottom: 12px;">
                    <i class="fas fa-school" style="color: #F5A020;"></i>
                    <span>انضم إلى دليل المدارس</span>
                </div>
                <h2 class="fw-bold mb-2" style="font-size: 1.35rem; color: #FFFFFF;">هل تدير مدرسة أو مجمعاً تعليمياً؟</h2>
                <p class="mb-4" style="max-width: 650px; font-size: 0.76rem; color: rgba(255, 255, 255, 0.85); line-height: 1.65;">
                    خدمة رقمية مقدمة من منظومة ايسكو بلص (SchoPlus). أضف بيانات مدرستك، أنشئ ملفاً تعريفياً متكاملاً، وانشر أخبار ومرافق مدرستك واستقبل استفسارات أولياء الأمور مباشرة.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/register.php" class="btn-luxury-primary px-3 py-2" style="font-size: 0.78rem;">
                        <i class="fas fa-school"></i>
                        <span>سجّل مدرستك الآن</span>
                    </a>
                    <a href="/login.php" class="btn btn-outline-light px-3 py-2 rounded-2 fw-bold" style="font-size: 0.78rem;">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <div class="p-4 rounded-3" style="background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255,255,255,0.12);">
                    <i class="fas fa-certificate fa-3x text-warning mb-3"></i>
                    <h5 class="fw-bold text-white" style="font-size: 0.95rem;">ارتقِ بمدرستك مع SchoPlus</h5>
                    <p class="small mb-3" style="font-size: 0.72rem !important; line-height: 1.6; color: rgba(255,255,255,0.8);">حوّل مدرستك إلى مدرسة ذكية مع أنظمة الحسابات، شؤون الطلاب، الجداول الآلية، وبوابة أولياء الأمور عبر الواتساب.</p>
                    <a href="https://schoplus.sd" target="_blank" class="btn btn-sm btn-light text-primary fw-bold w-100" style="font-size: 0.76rem;">
                        تعرف على نظام SchoPlus
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$jsonAllGeo = json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE);
$extraScripts = <<<JS
<script>
    const allGeoMap = {$jsonAllGeo};
    const countrySelect = document.getElementById('filterCountrySelect');
    const stateSelect = document.getElementById('filterStateSelect');
    const citySelect = document.getElementById('filterCitySelect');

    function updateStates(countryVal) {
        if (!stateSelect) return;
        stateSelect.innerHTML = '<option value="">جميع الولايات</option>';
        if (citySelect) {
            citySelect.innerHTML = '<option value="">جميع المدن / المحليات</option>';
        }
        let statesObj = {};
        if (countryVal && allGeoMap[countryVal]) {
            statesObj = allGeoMap[countryVal];
        } else {
            statesObj = allGeoMap['السودان'] || {};
        }
        Object.keys(statesObj).forEach(st => {
            const opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            stateSelect.appendChild(opt);
        });
    }

    function updateCities(stateVal) {
        if (!citySelect) return;
        citySelect.innerHTML = '<option value="">جميع المدن / المحليات</option>';
        const countryVal = (countrySelect && countrySelect.value) ? countrySelect.value : 'السودان';
        const statesObj = allGeoMap[countryVal] || allGeoMap['السودان'] || {};
        if (stateVal && statesObj[stateVal]) {
            statesObj[stateVal].forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                citySelect.appendChild(opt);
            });
        }
    }

    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            updateStates(this.value);
        });
    }
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            updateCities(this.value);
        });
    }
</script>
JS;
require_once __DIR__ . '/footer.php';
