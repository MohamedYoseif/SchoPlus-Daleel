<?php
$pageTitle = 'الصفحة الرئيسية للدليل';
require_once __DIR__ . '/header.php';

$pdo = db();

// Filter Inputs (The 3 dropdowns + Name/keyword)
$q = trim($_GET['q'] ?? '');
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$stage = trim($_GET['stage'] ?? '');
$schoplusOnly = !empty($_GET['schoplus_only']);
$sort = trim($_GET['sort'] ?? 'featured');
$view = trim($_GET['view'] ?? ''); // 'all', 'schoplus', 'top_rated', 'latest', or ''

// Determine if user is performing a search or requesting a full standalone view
$isSearching = ($q !== '' || $state !== '' || $city !== '' || $stage !== '' || $schoplusOnly || in_array($view, ['all', 'schoplus', 'top_rated', 'latest']));

// Geography & standard mappings
$statesWithCities = SudanGeography::statesWithCities();
$schoolStages = SudanGeography::schoolStages();
$schoolTypes = SudanGeography::schoolTypes();

// Statistics
$statTotal = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$statSchoplus = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$statStates = (int) $pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: count(SudanGeography::states());
$statReviews = (int) $pdo->query("SELECT COUNT(*) FROM daleel_school_reviews WHERE is_approved = 1")->fetchColumn();

// State school counters for the state cards
$stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Distinct states list
$allStatesList = SudanGeography::states();

if ($isSearching) {
    // Build query for search results
    $where = ["status = 'approved'"];
    $params = [];

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

    if ($schoplusOnly || $view === 'schoplus') {
        $where[] = "is_schoplus_client = 1";
    }

    $whereClause = implode(' AND ', $where);

    // Order by logic
    $orderBy = "is_featured DESC, is_schoplus_client DESC, rating_avg DESC";
    if ($sort === 'rating' || $view === 'top_rated') $orderBy = "rating_avg DESC, reviews_count DESC";
    if ($sort === 'reviews') $orderBy = "reviews_count DESC";
    if ($sort === 'views') $orderBy = "views_count DESC";
    if ($sort === 'latest' || $view === 'latest') $orderBy = "created_at DESC";

    // Count matching
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalMatching = (int) $countStmt->fetchColumn();

    // Fetch schools
    $searchStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY {$orderBy}");
    $searchStmt->execute($params);
    $searchResults = $searchStmt->fetchAll();
} else {
    // Default home view: 3 sections with up to 5 schools each
    // 1. SchoPlus Smart Schools: max 5, random on each load
    $schoplusSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1 ORDER BY RAND() LIMIT 5")->fetchAll();
    $totalSchoplusCount = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();

    // 2. Top-Rated Schools: max 5, random on each load
    $topRatedSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND rating_avg >= 4.0 ORDER BY RAND() LIMIT 5")->fetchAll();
    if (empty($topRatedSchools)) {
        // Fallback if no school has >= 4.0 yet
        $topRatedSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY RAND() LIMIT 5")->fetchAll();
    }
    $totalTopRatedCount = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND rating_avg >= 4.0")->fetchColumn();

    // 3. Recently Added Schools: max 5, newest first
    $latestSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $totalSchoolsCount = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
}
?>

<style>
/* ===== REDESIGNED MAIN HERO & INTEGRATED FILTER BAR ===== */
.daleel-hero-v2 {
    position: relative;
    background: linear-gradient(165deg, #FFFDF9 0%, #F7EFE4 52%, #EFE4D4 100%);
    padding: 36px 0 32px;
    border-bottom: 1px solid #E5D9C8;
    overflow: hidden;
}
.daleel-hero-v2::before {
    content: '';
    position: absolute;
    top: -120px;
    right: -120px;
    width: 380px;
    height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(30, 95, 168, 0.07) 0%, rgba(30, 95, 168, 0) 70%);
    pointer-events: none;
}
.daleel-hero-v2::after {
    content: '';
    position: absolute;
    bottom: -140px;
    left: -100px;
    width: 360px;
    height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(217, 119, 6, 0.09) 0%, rgba(217, 119, 6, 0) 70%);
    pointer-events: none;
}
.hero-v2-inner {
    position: relative;
    z-index: 2;
    max-width: 920px;
    margin: 0 auto;
}
.hero-v2-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid #E2D5C3;
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--brand-navy);
    box-shadow: 0 2px 10px rgba(11, 42, 82, 0.04);
    margin-bottom: 14px;
}
.hero-v2-badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #F59E0B;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}
.hero-v2-title {
    font-weight: 800;
    font-size: clamp(1.22rem, 2.8vw, 1.72rem);
    color: var(--brand-navy);
    line-height: 1.38;
    margin-bottom: 10px;
    letter-spacing: -0.3px;
}
.hero-v2-title-sub {
    display: block;
    font-size: clamp(0.95rem, 2vw, 1.22rem);
    color: var(--brand-blue);
    font-weight: 700;
    margin-top: 4px;
}
.hero-v2-desc {
    font-size: 0.76rem;
    color: #4B5563;
    line-height: 1.75;
    max-width: 670px;
    margin: 0 auto 22px;
}

/* Smart Interactive Filter Bar inside Hero */
.filter-bar-card {
    background: #FFFFFF;
    border: 1.5px solid #E2D7C7;
    border-radius: 20px;
    padding: 16px 18px;
    box-shadow: 0 14px 36px -8px rgba(11, 42, 82, 0.1), 0 4px 12px -2px rgba(45, 35, 20, 0.05);
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
    border-bottom: 1px solid #F1EAE0;
}
.filter-bar-title {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--brand-navy);
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
    background: #FAF6F0;
    color: var(--text-body);
    border: 1px solid #E6DCCD;
    transition: all 0.2s ease;
}
.filter-quick-pill:hover {
    background: var(--brand-navy);
    color: #FFFFFF;
    border-color: var(--brand-navy);
}
.filter-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    background: #F8FAFC;
    border: 1px solid #DCE4EE;
    border-radius: 12px;
    padding: 2px 10px;
    transition: all 0.2s ease;
}
.filter-input-wrap:focus-within {
    border-color: var(--brand-blue);
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}
.filter-input-wrap input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--text-main);
    width: 100%;
    padding: 8px 6px;
}
.filter-select-wrap select {
    background-color: #F8FAFC;
    border: 1px solid #DCE4EE;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-main);
    padding: 9px 12px;
    transition: all 0.2s ease;
}
.filter-select-wrap select:focus {
    border-color: var(--brand-blue);
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}

/* Hero Highlights Strip below Filter Card */
.hero-highlights-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 16px;
}
.hero-highlight-card {
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(6px);
    border: 1px solid #E5D9C8;
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: right;
}
.hero-highlight-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
}
.hero-highlight-val {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--brand-navy);
    line-height: 1.2;
}
.hero-highlight-lbl {
    font-size: 0.65rem;
    color: #64748B;
    font-weight: 600;
}
@media (max-width: 767px) {
    .daleel-hero-v2 {
        padding: 22px 0 20px;
    }
    .hero-highlights-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .filter-bar-card {
        padding: 12px;
        border-radius: 16px;
    }
}

/* States Carousel / Slider (3 cards on mobile) */
.states-slider-container {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    padding: 10px 4px 16px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #D4AF37 #F5ECE0;
}
.states-slider-container::-webkit-scrollbar {
    height: 6px;
}
.states-slider-container::-webkit-scrollbar-thumb {
    background: #D4AF37;
    border-radius: 4px;
}
.states-slider-container::-webkit-scrollbar-track {
    background: #F5ECE0;
    border-radius: 4px;
}
.state-slide-card {
    flex: 0 0 auto;
    scroll-snap-align: start;
    width: 135px;
    background: #FFFFFF;
    border: 1px solid #E6DCCD;
    border-radius: 14px;
    padding: 12px 10px;
    text-align: center;
    text-decoration: none;
    color: var(--text-main);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 3px 10px rgba(0,0,0,0.03);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.state-slide-card:hover, .state-slide-card.active {
    border-color: var(--brand-gold);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(217, 119, 6, 0.12);
    background: linear-gradient(180deg, #FFFFFF 0%, #FFFDF9 100%);
}
.state-slide-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #F5EFE6;
    color: var(--brand-navy);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    margin-bottom: 6px;
    transition: all 0.2s ease;
}
.state-slide-card:hover .state-slide-icon, .state-slide-card.active .state-slide-icon {
    background: linear-gradient(135deg, var(--brand-navy) 0%, var(--brand-blue) 100%);
    color: #FFFFFF;
}
.state-slide-title {
    font-size: 0.74rem;
    font-weight: 700;
    margin-bottom: 2px;
    color: var(--brand-navy);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}
.state-slide-count {
    font-size: 0.65rem;
    color: #718096;
    font-weight: 600;
}

/* Mobile Screen Layout: Exactly 3 State Cards Visible At Once */
@media (max-width: 576px) {
    .states-slider-container {
        gap: 8px;
        padding: 6px 2px 12px;
    }
    .state-slide-card {
        /* Screen width minus container padding and 2 gaps divided by 3 cards */
        width: calc((100% - 16px) / 3) !important;
        min-width: calc((100% - 16px) / 3) !important;
        padding: 8px 4px;
        border-radius: 11px;
    }
    .state-slide-icon {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
        margin-bottom: 4px;
    }
    .state-slide-title {
        font-size: 0.68rem;
    }
    .state-slide-count {
        font-size: 0.58rem;
    }
}

/* Clickable Luxury Card Styles */
.clickable-school-card {
    cursor: pointer;
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.clickable-school-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px -4px rgba(45, 35, 20, 0.12), 0 4px 10px -2px rgba(45, 35, 20, 0.05);
}
</style>

<!-- REDESIGNED HERO SECTION WITH INTEGRATED FILTERS -->
<section class="daleel-hero-v2 mb-4" id="search-section">
    <div class="container">
        <div class="hero-v2-inner text-center">

            <div class="hero-v2-badge">
                <span class="hero-v2-badge-dot"></span>
                <span>مبادرة رقمية مجانية من منظومة ايسكو بلص (SchoPlus)</span>
            </div>

            <h1 class="hero-v2-title">
                دليلك الرقمي للتعرف على المدارس في السودان
                <span class="hero-v2-title-sub">ابحث بالولاية والمدينة والمرحلة الدراسية وتواصل مع إدارات المدارس مباشرة</span>
            </h1>

            <p class="hero-v2-desc">
                خدمة رقمية مفتوحة ومجانية تجمع بيانات المدارس السودانية، مراحلها التعليمية، مرافقها، وفروعها في مكان واحد؛ لتسهيل وصول أولياء الأمور والطلاب للمعلومات، وإتاحة الفرصة لأصحاب المدارس للتعريف بمدارسهم دون أي مقابل مادي.
            </p>

            <!-- SMART UNIFIED FILTER BAR (Preserved & Integrated inside Hero) -->
            <form action="/" method="GET" class="filter-bar-card" id="smartFilterForm">
                <div class="row g-2 align-items-center">
                    
                    <!-- 1. State Filter (الولاية) -->
                    <div class="col-lg-3 col-md-6 filter-select-wrap">
                        <select name="state" class="form-select" id="filterStateSelect">
                            <option value="">جميع الولايات (الكل)</option>
                            <?php foreach($statesWithCities as $stName => $cities): ?>
                                <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>>ولاية <?= e($stName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. City Filter (المدينة) -->
                    <div class="col-lg-3 col-md-6 filter-select-wrap">
                        <select name="city" class="form-select" id="filterCitySelect">
                            <option value="">جميع المدن / المحليات</option>
                            <?php if ($state && isset($statesWithCities[$state])): ?>
                                <?php foreach($statesWithCities[$state] as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 3. Stage Filter (المراحل) -->
                    <div class="col-lg-2 col-md-6 filter-select-wrap">
                        <select name="stage" class="form-select" id="filterStageSelect">
                            <option value="">جميع المراحل الدراسية</option>
                            <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. School Name / Keyword Input (كتابة الاسم) -->
                    <div class="col-lg-3 col-md-6">
                        <div class="filter-input-wrap">
                            <i class="fas fa-search text-muted me-1"></i>
                            <input type="text" name="q" value="<?= e($q) ?>" placeholder="ابحث باسم المدرسة، الحي..." autocomplete="off">
                            <?php if ($q !== ''): ?>
                                <a href="/" class="text-muted small text-decoration-none px-1" title="مسح"><i class="fas fa-times-circle"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-lg-1 col-md-12 d-flex gap-1">
                        <button type="submit" class="btn-luxury-primary w-100 justify-content-center py-2" title="بحث فوري">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($isSearching): ?>
                            <a href="/" class="btn btn-light border py-2 px-2 rounded-3 text-secondary" title="إلغاء البحث والعودة للرئيسية">
                                <i class="fas fa-undo"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>

            <!-- Hero Quick Highlights Strip -->
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
                <div class="hero-highlight-card">
                    <div class="hero-highlight-icon" style="background: #FFFBEB; color: #D97706;">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div>
                        <div class="hero-highlight-val" data-countup="<?= $statStates ?>"><?= $statStates ?></div>
                        <div class="hero-highlight-lbl">ولاية سودانية متاحة</div>
                    </div>
                </div>
                <div class="hero-highlight-card">
                    <div class="hero-highlight-icon" style="background: #F0FDF4; color: #059669;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="hero-highlight-val" data-countup="<?= $statSchoplus ?>"><?= number_format($statSchoplus) ?></div>
                        <div class="hero-highlight-lbl">مدرسة بنظام SchoPlus</div>
                    </div>
                </div>
                <div class="hero-highlight-card">
                    <div class="hero-highlight-icon" style="background: #F8FAFC; color: var(--brand-navy);">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div>
                        <div class="hero-highlight-val" style="font-size: 0.82rem;">مجاني 100%</div>
                        <div class="hero-highlight-lbl">للأهالي وأصحاب المدارس</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- STATES SLIDER (CAROUSEL) - Always visible whether searching or on default view -->
<section class="mb-4" id="states-slider-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <span style="color: var(--brand-gold); font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                    <i class="fas fa-map-marked-alt"></i> التوزيع الجغرافي
                </span>
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--brand-navy); margin: 0;">تصفح المدارس حسب الولايات</h3>
            </div>
            <div class="d-none d-md-block text-muted small" style="font-size: 0.7rem;">
                اسحب لتصفح كافة ولايات السودان الـ 18 <i class="fas fa-arrows-left-right text-warning ms-1"></i>
            </div>
        </div>

        <!-- States Slider: Exactly 3 cards visible simultaneously on mobile -->
        <div class="states-slider-container">
            <?php foreach($allStatesList as $stName): ?>
                <?php 
                    $countInState = $stateCounts[$stName] ?? 0;
                    $isSelectedState = ($state === $stName);
                    // If already clicked, clicking again resets state to show all; otherwise filters by state
                    $stateUrl = $isSelectedState ? '/' : '/?state=' . urlencode($stName);
                ?>
                <a href="<?= $stateUrl ?>" class="state-slide-card <?= $isSelectedState ? 'active' : '' ?>" title="مدارس ولاية <?= e($stName) ?>">
                    <div class="state-slide-icon">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <div class="state-slide-title"><?= e($stName) ?></div>
                    <div class="state-slide-count"><?= $countInState ?> <?= $countInState == 1 ? 'مدرسة' : ($countInState > 2 && $countInState < 11 ? 'مدارس' : 'مدرسة') ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($isSearching): ?>
    <!-- ==========================================
         CASE A: SEARCH / DEDICATED VIEW ACTIVE
         Results appear immediately below the states slider
         ========================================== -->
    <section class="py-4" id="search-results-section">
        <div class="container">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
                <div>
                    <span style="color: var(--brand-gold); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                        <i class="fas fa-poll"></i> نتائج البحث والاستكشاف
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">
                        <?php if ($view === 'schoplus' || $schoplusOnly): ?>
                            جميع المدارس الذكية المعتمدة بنظام SchoPlus
                        <?php elseif ($view === 'top_rated' || $sort === 'rating'): ?>
                            جميع المدارس الأعلى تقييماً في السودان
                        <?php elseif ($view === 'latest' || $sort === 'latest'): ?>
                            أحدث المدارس المنضمة إلى الدليل
                        <?php elseif ($state !== '' || $city !== '' || $stage !== '' || $q !== ''): ?>
                            نتائج البحث المخصص
                        <?php else: ?>
                            جميع مدارس دليل السودان
                        <?php endif; ?>
                    </h2>
                    <p style="color: #6B7280; font-size: 0.74rem; margin-top: 4px;">
                        تم العثور على <strong><?= number_format($totalMatching) ?></strong> مدرسة مطابقة لمعايير البحث المحددة.
                    </p>
                </div>

                <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center">
                    <a href="/" class="btn-luxury-outline btn-sm">
                        <i class="fas fa-arrow-right"></i>
                        <span>العودة للرئيسية</span>
                    </a>
                </div>
            </div>

            <!-- Schools Grid for Search Results -->
            <div class="row g-4">
                <?php if (empty($searchResults)): ?>
                    <div class="col-12 py-5 text-center">
                        <div class="p-5 bg-white rounded-4 border text-center shadow-sm max-w-md mx-auto" style="max-width: 480px; margin: 0 auto;">
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
                        <?php
                            $logo = !empty($s['logo_path']) ? e(assetUrl($s['logo_path'])) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                            $cover = !empty($s['cover_path']) ? e(assetUrl($s['cover_path'])) : '';
                            $stagesArr = json_decode($s['stages'] ?? '[]', true) ?: [];
                            $schoolUrl = '/school/' . urlencode($s['slug']);
                        ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="luxury-card h-100 d-flex flex-column clickable-school-card" onclick="window.location.href='<?= $schoolUrl ?>'">
                                
                                <div style="height: 140px; background-size: cover; background-position: center; position: relative; <?= $cover ? "background-image: url('{$cover}');" : '' ?>" class="<?= !$cover ? 'cover-placeholder' : '' ?>">
                                    <div style="position: absolute; inset: 0; background: <?= $cover ? 'linear-gradient(180deg, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0.52) 100%)' : 'linear-gradient(180deg, rgba(11,42,82,0.0) 0%, rgba(11,42,82,0.35) 100%)' ?>;"></div>
                                    <?php if (!$cover): ?><div class="cover-placeholder-icon"><i class="fas fa-school"></i></div><?php endif; ?>

                                    <div style="position: absolute; top: 12px; left: 12px; z-index: 2; display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                                        <?php if ($s['is_schoplus_client']): ?>
                                            <span class="badge-schoplus-smart"><i class="fas fa-bolt"></i> SchoPlus الذكي</span>
                                        <?php endif; ?>
                                        <span class="badge-verified"><i class="fas fa-school"></i> مسجلة بالدليل</span>
                                    </div>

                                    <div style="width: 68px; height: 68px; border-radius: 16px; background: #fff; padding: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); position: absolute; bottom: -24px; right: 20px; z-index: 2;">
                                        <img src="<?= $logo ?>" alt="" loading="lazy" style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover;">
                                    </div>
                                </div>

                                <div style="padding: 32px 18px 18px; display: flex; flex-direction: column; flex-grow: 1;">
                                    <a href="<?= $schoolUrl ?>" style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy); margin-bottom: 4px; text-decoration: none;" onclick="event.stopPropagation();">
                                        <?= e($s['name']) ?>
                                    </a>

                                    <div style="color: #64748B; font-size: 0.72rem; margin-bottom: 6px;">
                                        <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                        ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= !empty($s['district']) ? '(' . e($s['district']) . ')' : '' ?>
                                    </div>

                                    <p class="school-card-desc" style="font-size: 0.74rem; color: #4B5563; margin-bottom: 10px;">
                                        <?= e($s['description'] ?: 'مؤسسة تعليمية وتربوية تقدم برامج أكاديمية متميزة وبيئة متطورة للطلاب.') ?>
                                    </p>

                                    <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 16px;">
                                        <?php foreach($stagesArr as $st): ?>
                                            <span class="badge-stage-pill"><?= e($schoolStages[$st] ?? $st) ?></span>
                                        <?php endforeach; ?>
                                        <span class="badge bg-light text-dark border"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                                    </div>

                                    <div style="padding-top: 14px; border-top: 1px solid #F1EAE0; display: flex; align-items: center; justify-content: space-between; margin-top: auto;">
                                        <div class="star-rating">
                                            <i class="fas fa-star text-warning"></i>
                                            <span class="fw-bold text-dark"><?= number_format($s['rating_avg'], 1) ?></span>
                                            <span class="text-muted small">(<?= $s['reviews_count'] ?>)</span>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($s['whatsapp'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="واتساب" onclick="event.stopPropagation();">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= $schoolUrl ?>" class="btn-luxury-outline btn-sm py-1 px-3" onclick="event.stopPropagation();">
                                                عرض البروفايل
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </section>

<?php else: ?>
    <!-- ==========================================
         CASE B: DEFAULT HOME VIEW (NOT SEARCHING)
         1. SchoPlus Smart Schools (max 5 random)
         2. Top-Rated Schools (max 5 random)
         3. Recently Added Schools (max 5 latest)
         ========================================== -->

    <!-- SECTION 1: SCHOPLUS SMART SCHOOLS (MAX 5 RANDOM) -->
    <?php if (!empty($schoplusSchools)): ?>
    <section id="schoplus-schools" class="py-4" style="background-color: #F8F3EA; border-top: 1px solid #EAE2D5; border-bottom: 1px solid #EAE2D5;">
        <div class="container">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div>
                    <span style="color: var(--brand-gold); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                        <i class="fas fa-award"></i> التميز والرقمنة
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">مدارس ذكية تعمل بنظام SchoPlus</h2>
                    <p style="color: #6B7280; font-size: 0.74rem; margin-top: 4px;">
                        مدارس رائدة تتيح متابعة إلكترونية فورية لدرجات وحضور ابنك، إشعارات واتساب لحظية، وبوابة مخصصة لأولياء الأمور.
                    </p>
                </div>
                <?php if ($totalSchoplusCount > 5): ?>
                    <div class="mt-3 mt-md-0">
                        <a href="/?view=schoplus" class="btn-luxury-outline btn-sm">
                            <span>عرض جميع المدارس</span>
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <?php foreach($schoplusSchools as $s): ?>
                    <?php
                        $logo = !empty($s['logo_path']) ? e(assetUrl($s['logo_path'])) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                        $cover = !empty($s['cover_path']) ? e(assetUrl($s['cover_path'])) : '';
                        $stagesArr = json_decode($s['stages'] ?? '[]', true) ?: [];
                        $schoolUrl = '/school/' . urlencode($s['slug']);
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="luxury-card clickable-school-card" style="border: 1.5px solid #FCD34D; background: linear-gradient(135deg, #FFFDF9 0%, #FEF9EC 100%);" onclick="window.location.href='<?= $schoolUrl ?>'">
                            
                            <div style="position: absolute; top: 0; right: 25px; background: linear-gradient(135deg, #1E5FA8 0%, #0B2A52 100%); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 0 0 8px 8px; z-index: 3;">
                                <i class="fas fa-bolt text-warning me-1"></i> تستخدم نظام SchoPlus
                            </div>

                            <div style="height: 140px; background-size: cover; background-position: center; position: relative; <?= $cover ? "background-image: url('{$cover}');" : '' ?>" class="<?= !$cover ? 'cover-placeholder' : '' ?>">
                                <div style="position: absolute; inset: 0; background: <?= $cover ? 'linear-gradient(180deg, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0.52) 100%)' : 'linear-gradient(180deg, rgba(11,42,82,0.0) 0%, rgba(11,42,82,0.35) 100%)' ?>;"></div>
                                <?php if (!$cover): ?><div class="cover-placeholder-icon"><i class="fas fa-school"></i></div><?php endif; ?>
                                
                                <div style="position: absolute; top: 12px; left: 12px; z-index: 2;">
                                    <span class="badge-schoplus-smart"><i class="fas fa-microchip"></i> SchoPlus</span>
                                </div>

                                <div style="width: 68px; height: 68px; border-radius: 16px; background: #fff; padding: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); position: absolute; bottom: -24px; right: 20px; z-index: 2;">
                                    <img src="<?= $logo ?>" alt="" loading="lazy" style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover;">
                                </div>
                            </div>

                            <div style="padding: 32px 18px 18px; display: flex; flex-direction: column;">
                                <a href="<?= $schoolUrl ?>" style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy); margin-bottom: 4px; text-decoration: none;" onclick="event.stopPropagation();">
                                    <?= e($s['name']) ?>
                                </a>

                                <div style="color: #64748B; font-size: 0.72rem; margin-bottom: 6px;">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                    <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= !empty($s['district']) ? '(' . e($s['district']) . ')' : '' ?>
                                </div>

                                <p class="school-card-desc" style="font-size: 0.74rem; color: #4B5563; margin-bottom: 10px;">
                                    <?= e($s['description'] ?: 'مؤسسة تعليمية رائدة تقدم تعليماً حديثاً وبيئة محفزة للطلاب.') ?>
                                </p>

                                <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 14px;">
                                    <?php foreach($stagesArr as $st): ?>
                                        <span class="badge-stage-pill"><?= e($schoolStages[$st] ?? $st) ?></span>
                                    <?php endforeach; ?>
                                    <span class="badge bg-light text-dark border"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                                </div>

                                <div style="padding-top: 14px; border-top: 1px solid #F1EAE0; display: flex; align-items: center; justify-content: space-between;">
                                    <div class="star-rating">
                                        <i class="fas fa-star text-warning"></i>
                                        <span class="fw-bold text-dark"><?= number_format($s['rating_avg'], 1) ?></span>
                                        <span class="text-muted small">(<?= $s['reviews_count'] ?>)</span>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        <?php if (!empty($s['whatsapp'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="واتساب" onclick="event.stopPropagation();">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= $schoolUrl ?>" class="btn-luxury-primary btn-sm py-1 px-3" onclick="event.stopPropagation();">
                                            <span>البروفايل</span>
                                            <i class="fas fa-chevron-left small"></i>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <!-- SECTION 3: TOP RATED SCHOOLS (MAX 5 RANDOM) -->
    <?php if (!empty($topRatedSchools)): ?>
    <section id="top-rated" class="py-5">
        <div class="container">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div>
                    <span style="color: var(--brand-gold); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                        <i class="fas fa-star"></i> ثقة أولياء الأمور
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">المدارس الأعلى تقييماً</h2>
                    <p style="color: #6B7280; font-size: 0.74rem; margin-top: 4px;">
                        مدارس حازت على أعلى التقييمات ورضا أولياء الأمور والطلاب لجودة التعليم والرعاية التربوية.
                    </p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="/?view=top_rated" class="btn-luxury-outline btn-sm">
                        <span>عرض الكل</span>
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach($topRatedSchools as $s): ?>
                    <?php
                        $logo = !empty($s['logo_path']) ? e(assetUrl($s['logo_path'])) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                        $schoolUrl = '/school/' . urlencode($s['slug']);
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="luxury-card p-3 h-100 d-flex flex-column clickable-school-card" onclick="window.location.href='<?= $schoolUrl ?>'">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="<?= $logo ?>" alt="" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover;">
                                <div>
                                    <a href="<?= $schoolUrl ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 220px; font-size: 0.84rem;" onclick="event.stopPropagation();">
                                        <?= e($s['name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <i class="fas fa-map-marker-alt text-muted me-1"></i> <?= e($s['state']) ?> — <?= e($s['city']) ?>
                                    </div>
                                </div>
                            </div>

                            <p class="school-card-desc mb-2" style="font-size: 0.72rem; color: #64748B;">
                                <?= e($s['description'] ?: 'مؤسسة تعليمية رائدة متميزة بالرعاية والتحصيل العلمي.') ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center small mb-3 mt-auto pt-2 border-top">
                                <span class="badge bg-light text-secondary border"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                                <div class="star-rating">
                                    <i class="fas fa-star text-warning"></i>
                                    <span class="fw-bold"><?= number_format($s['rating_avg'], 1) ?></span>
                                    <span class="text-muted">(<?= $s['reviews_count'] ?>)</span>
                                </div>
                            </div>

                            <a href="<?= $schoolUrl ?>" class="btn-luxury-outline btn-sm w-100 justify-content-center" onclick="event.stopPropagation();">
                                عرض التفاصيل
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <!-- SECTION 4: RECENTLY ADDED SCHOOLS (MAX 5 LATEST) -->
    <?php if (!empty($latestSchools)): ?>
    <section id="latest-schools" class="py-5" style="background-color: #FAF8F5; border-top: 1px solid #EAE2D5;">
        <div class="container">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div>
                    <span style="color: var(--brand-gold); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                        <i class="fas fa-sparkles"></i> انضمت حديثاً
                    </span>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">أحدث المدارس المضافة</h2>
                    <p style="color: #6B7280; font-size: 0.74rem; margin-top: 4px;">
                        مدارس ومجمعات تعليمية أضافت بياناتها مؤخراً في الدليل لتسهيل التواصل والتعريف ببرامجها.
                    </p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="/?view=all" class="btn-luxury-outline btn-sm">
                        <span>عرض الكل</span>
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach($latestSchools as $s): ?>
                    <?php
                        $logo = !empty($s['logo_path']) ? e(assetUrl($s['logo_path'])) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                        $schoolUrl = '/school/' . urlencode($s['slug']);
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="luxury-card p-3 h-100 d-flex flex-column clickable-school-card" onclick="window.location.href='<?= $schoolUrl ?>'">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="<?= $logo ?>" alt="" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover;">
                                <div>
                                    <a href="<?= $schoolUrl ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 220px; font-size: 0.84rem;" onclick="event.stopPropagation();">
                                        <?= e($s['name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <i class="fas fa-map-marker-alt text-muted me-1"></i> ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?>
                                    </div>
                                </div>
                            </div>

                            <p class="school-card-desc mb-2" style="font-size: 0.72rem; color: #64748B;">
                                <?= e($s['description'] ?: 'مؤسسة تعليمية حديثة تقدم بيئة تربوية ملهمة للطلاب.') ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center small mb-3 mt-auto pt-2 border-top">
                                <span class="badge bg-light text-secondary border"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                                <div class="star-rating">
                                    <i class="fas fa-star text-warning"></i>
                                    <span class="fw-bold"><?= number_format($s['rating_avg'], 1) ?></span>
                                    <span class="text-muted">(<?= $s['reviews_count'] ?>)</span>
                                </div>
                            </div>

                            <a href="<?= $schoolUrl ?>" class="btn-luxury-outline btn-sm w-100 justify-content-center" onclick="event.stopPropagation();">
                                عرض التفاصيل
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

<!-- SECTION 5: SCHOOL OWNERS CTA (Refined white text for free registration card) -->
<section class="py-5" style="background: linear-gradient(135deg, #0B2A52 0%, #16467D 100%); color: #FFFFFF;">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <!-- Free Registration Badge: Pure white text with elegant golden accent -->
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 158, 11, 0.22); border: 1px solid rgba(252, 211, 77, 0.45); color: #FFFFFF; font-weight: 700; font-size: 0.74rem; padding: 5px 14px; border-radius: 20px; margin-bottom: 14px;">
                    <i class="fas fa-gift text-warning"></i>
                    <span style="color: #FFFFFF !important;">تسجيل مجاني بالكامل</span>
                </div>
                <h2 class="fw-bold mb-2" style="font-size: 1.35rem; color: #FFFFFF;">هل تدير مدرسة أو مجمعاً تعليمياً؟</h2>
                <p class="mb-4" style="max-width: 650px; font-size: 0.76rem; color: rgba(255, 255, 255, 0.9); line-height: 1.65;">
                    خدمة مجانية بالكامل من منظومة ايسكو بلص (SchoPlus) دون أي رسوم أو مقابل مادي. أضف بيانات مدرستك بنفسك، أنشئ صفحة تعريفية متكاملة، انشر أخبار ومناشط مدرستك، واستقبل استفسارات أولياء الأمور مباشرة.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/register.php" class="btn-luxury-gold px-3 py-2" style="font-size: 0.78rem;">
                        <i class="fas fa-school"></i>
                        <span>سجّل مدرستك الآن مجاناً</span>
                    </a>
                    <a href="/login.php" class="btn btn-outline-light px-3 py-2 rounded-3 fw-bold" style="font-size: 0.78rem;">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <div class="p-4 rounded-4" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-certificate fa-4x text-warning mb-3"></i>
                    <h5 class="fw-bold text-white" style="font-size: 0.95rem;">ارتقِ بمدرستك مع SchoPlus</h5>
                    <p class="small mb-3" style="font-size: 0.72rem !important; line-height: 1.6; color: rgba(255,255,255,0.85);">حوّل مدرستك إلى مدرسة ذكية مع أنظمة الحسابات، شؤون الطلاب، الجداول الآلية، وبوابة أولياء الأمور عبر الواتساب.</p>
                    <a href="https://schoplus.sd" target="_blank" class="btn btn-sm btn-light text-primary fw-bold w-100" style="font-size: 0.78rem;">
                        تعرف على نظام SchoPlus
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$jsonStatesMap = json_encode($statesWithCities, JSON_UNESCAPED_UNICODE);
$extraScripts = <<<JS
<script>
    const statesMap = {$jsonStatesMap};
    const stateSelect = document.getElementById('filterStateSelect');
    const citySelect = document.getElementById('filterCitySelect');

    if (stateSelect && citySelect) {
        stateSelect.addEventListener('change', function() {
            const st = this.value;
            citySelect.innerHTML = '<option value="">جميع المدن / المحليات</option>';
            if (st && statesMap[st]) {
                statesMap[st].forEach(city => {
                    const opt = document.createElement('option');
                    opt.value = city;
                    opt.textContent = city;
                    citySelect.appendChild(opt);
                });
            }
        });
    }
</script>
JS;
require_once __DIR__ . '/footer.php';
