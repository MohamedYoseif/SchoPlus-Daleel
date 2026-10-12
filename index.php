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

/* Pure Family Photo Showcase (Left Column) — No Clutter, No Floating Cards */
.hero-family-frame {
    border-radius: 20px;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    box-shadow: 0 10px 30px rgba(11, 42, 82, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hero-family-frame:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 36px rgba(11, 42, 82, 0.12);
}
.hero-family-img {
    width: 100%;
    height: 100%;
    max-height: 420px;
    object-fit: cover;
    display: block;
}

/* Modern Luxury Search Console Card (Right Column) */
.sp-search-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 18px;
    padding: 18px 20px;
    box-shadow: 0 10px 30px rgba(11, 42, 82, 0.07);
    position: relative;
    text-align: right;
}

/* Quick Search View Tabs */
.sp-search-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    margin-bottom: 14px;
    border-bottom: 1px solid #F1F5F9;
    flex-wrap: wrap;
}
.sp-search-tab {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 999px;
    text-decoration: none;
    background: #F8FAFC;
    color: #475569;
    border: 1px solid #E2E8F0;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sp-search-tab:hover {
    background: #E2E8F0;
    color: #0B2A52;
}
.sp-search-tab.active {
    background: #0B2A52;
    color: #FFFFFF;
    border-color: #0B2A52;
}

/* Main Keyword Input Bar */
.sp-search-input-box {
    position: relative;
    display: flex;
    align-items: center;
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 10px;
    padding: 0 14px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
}
.sp-search-input-box:focus-within {
    background: #FFFFFF;
    border-color: #1E5FA8;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.1);
}
.sp-search-input-box i {
    font-size: 0.92rem;
    color: #64748B;
    margin-left: 10px;
}
.sp-search-input-box input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    padding: 11px 4px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #0D1B2E;
}
.sp-search-input-box input::placeholder {
    color: #94A3B8;
    font-weight: 500;
}

/* ===== LUXURY SEARCHABLE DROPDOWN COMPONENT ===== */
.sp-searchable-dropdown {
    position: relative;
    width: 100%;
}
.sp-dropdown-trigger {
    width: 100%;
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    border-radius: 10px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    text-align: right;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
    min-height: 48px;
    outline: none;
}
.sp-dropdown-trigger:hover {
    background: #FFFFFF;
    border-color: #1E5FA8;
}
.sp-searchable-dropdown.is-open .sp-dropdown-trigger {
    background: #FFFFFF;
    border-color: #1E5FA8;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}
.sp-dropdown-trigger-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    overflow: hidden;
    line-height: 1.25;
}
.sp-dropdown-mini-lbl {
    font-size: 0.63rem;
    font-weight: 700;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}
.sp-dropdown-selected-val {
    font-size: 0.76rem;
    font-weight: 700;
    color: #0B2A52;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.sp-dropdown-chevron {
    font-size: 0.68rem;
    color: #94A3B8;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.sp-searchable-dropdown.is-open .sp-dropdown-chevron {
    transform: rotate(180deg);
    color: #1E5FA8;
}

/* Floating Dropdown Panel */
.sp-dropdown-panel {
    position: absolute;
    top: calc(100% + 5px);
    right: 0;
    left: 0;
    min-width: 220px;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(11, 42, 82, 0.14);
    z-index: 1050;
    display: none;
    overflow: hidden;
    animation: spDropdownFadeIn 0.15s ease-out;
}
@keyframes spDropdownFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.sp-searchable-dropdown.is-open .sp-dropdown-panel {
    display: block;
}

/* Dropdown Search Box */
.sp-dropdown-search-box {
    padding: 8px 10px;
    background: #F8FAFC;
    border-bottom: 1px solid #EDF2F7;
    display: flex;
    align-items: center;
    gap: 8px;
    position: sticky;
    top: 0;
    z-index: 2;
}
.sp-dropdown-search-input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.74rem;
    font-weight: 600;
    color: #0D1B2E;
}
.sp-dropdown-search-input::placeholder {
    color: #94A3B8;
}

/* Options List */
.sp-dropdown-options-list {
    max-height: 220px;
    overflow-y: auto;
    padding: 4px;
    scrollbar-width: thin;
    scrollbar-color: #CBD5E1 #F1F5F9;
}
.sp-dropdown-option {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.15s ease;
}
.sp-dropdown-option:hover {
    background: #F0F4F8;
    color: #1E5FA8;
}
.sp-dropdown-option.is-selected {
    background: #EFF6FF;
    color: #1E5FA8;
    font-weight: 700;
}
.sp-dropdown-option.is-selected i {
    font-size: 0.7rem;
    color: #1E5FA8;
}
.sp-dropdown-empty {
    padding: 14px 10px;
    text-align: center;
    color: #94A3B8;
    font-size: 0.72rem;
    font-weight: 600;
}

/* Action Search Button */
.sp-search-action-wrap {
    display: flex;
    gap: 8px;
    margin-top: 14px;
}
.sp-btn-search-hero {
    flex: 1;
    background: #F5A020;
    color: #FFFFFF;
    border: none;
    border-radius: 10px;
    padding: 11px 20px;
    font-size: 0.85rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(245, 160, 32, 0.25);
    transition: all 0.2s ease;
    text-decoration: none;
}
.sp-btn-search-hero:hover {
    background: #E08E15;
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(245, 160, 32, 0.32);
}
.sp-btn-reset-hero {
    background: #F8FAFC;
    border: 1px solid #DDE4ED;
    color: #64748B;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.sp-btn-reset-hero:hover {
    background: #F1F5F9;
    color: #0B2A52;
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
    .hero-family-frame {
        margin-top: 16px;
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
    .sp-search-card {
        padding: 14px;
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
                    <h1 class="hero-v2-title">
                        دليل المدارس السودانية
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

                <!-- LUXURY UNIFIED SEARCH CONSOLE CARD -->
                <form action="/" method="GET" class="sp-search-card" id="smartFilterForm">
                    
                    <!-- Quick View Tabs -->
                    <div class="sp-search-tabs">
                        <a href="/" class="sp-search-tab <?= (empty($view) || $view === 'all') && empty($stage) && !$schoplusOnly ? 'active' : '' ?>">
                            <i class="fas fa-layer-group"></i> كافة المدارس
                        </a>
                        <a href="/?view=schoplus" class="sp-search-tab <?= $view === 'schoplus' || $schoplusOnly ? 'active' : '' ?>">
                            <i class="fas fa-bolt text-warning"></i> مدارس SchoPlus الذكية
                        </a>
                        <a href="/?view=top_rated" class="sp-search-tab <?= $view === 'top_rated' ? 'active' : '' ?>">
                            <i class="fas fa-star text-warning"></i> الأعلى تقييماً
                        </a>
                    </div>

                    <!-- 1. Main Search Bar Input -->
                    <div class="sp-search-input-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="ابحث باسم المدرسة، الحي، أو المنطقة..." autocomplete="off">
                        <?php if ($q !== ''): ?>
                            <a href="/" class="text-muted small text-decoration-none px-1" title="مسح"><i class="fas fa-times-circle"></i></a>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Structured Filter Grid (Country, State, City, Stage with Searchable Dropdowns) -->
                    <div class="row g-2">
                        <!-- Country -->
                        <div class="col-md-3 col-6">
                            <div class="sp-searchable-dropdown" data-target="filterCountrySelect">
                                <select name="country" class="d-none" id="filterCountrySelect">
                                    <option value="">جميع الدول</option>
                                    <?php foreach($countriesList as $cntry): ?>
                                        <option value="<?= e($cntry) ?>" <?= $country === $cntry ? 'selected' : '' ?>><?= e($cntry) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-dropdown-trigger" id="trigger_filterCountrySelect">
                                    <div class="sp-dropdown-trigger-content">
                                        <span class="sp-dropdown-mini-lbl"><i class="fas fa-globe text-primary"></i> الدولة</span>
                                        <span class="sp-dropdown-selected-val"><?= e(!empty($country) ? $country : 'جميع الدول') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-dropdown-chevron"></i>
                                </button>
                                <div class="sp-dropdown-panel" id="panel_filterCountrySelect">
                                    <div class="sp-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-dropdown-search-input" placeholder="ابحث عن دولة..." autocomplete="off">
                                    </div>
                                    <div class="sp-dropdown-options-list">
                                        <div class="sp-dropdown-option <?= empty($country) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع الدول</span>
                                            <?php if (empty($country)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($countriesList as $cntry): ?>
                                            <div class="sp-dropdown-option <?= $country === $cntry ? 'is-selected' : '' ?>" data-value="<?= e($cntry) ?>">
                                                <span><?= e($cntry) ?></span>
                                                <?php if ($country === $cntry): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- State -->
                        <div class="col-md-3 col-6">
                            <div class="sp-searchable-dropdown" data-target="filterStateSelect">
                                <select name="state" class="d-none" id="filterStateSelect">
                                    <option value="">جميع الولايات</option>
                                    <?php foreach($statesWithCities as $stName => $cities): ?>
                                        <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-dropdown-trigger" id="trigger_filterStateSelect">
                                    <div class="sp-dropdown-trigger-content">
                                        <span class="sp-dropdown-mini-lbl"><i class="fas fa-map-marker-alt text-danger"></i> الولاية</span>
                                        <span class="sp-dropdown-selected-val"><?= e(!empty($state) ? $state : 'جميع الولايات') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-dropdown-chevron"></i>
                                </button>
                                <div class="sp-dropdown-panel" id="panel_filterStateSelect">
                                    <div class="sp-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-dropdown-search-input" placeholder="ابحث عن ولاية..." autocomplete="off">
                                    </div>
                                    <div class="sp-dropdown-options-list">
                                        <div class="sp-dropdown-option <?= empty($state) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع الولايات</span>
                                            <?php if (empty($state)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($statesWithCities as $stName => $cities): ?>
                                            <div class="sp-dropdown-option <?= $state === $stName ? 'is-selected' : '' ?>" data-value="<?= e($stName) ?>">
                                                <span><?= e($stName) ?></span>
                                                <?php if ($state === $stName): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- City -->
                        <div class="col-md-3 col-6">
                            <div class="sp-searchable-dropdown" data-target="filterCitySelect">
                                <select name="city" class="d-none" id="filterCitySelect">
                                    <option value="">جميع المدن</option>
                                    <?php if ($state && isset($statesWithCities[$state])): ?>
                                        <?php foreach($statesWithCities[$state] as $c): ?>
                                            <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="sp-dropdown-trigger" id="trigger_filterCitySelect">
                                    <div class="sp-dropdown-trigger-content">
                                        <span class="sp-dropdown-mini-lbl"><i class="fas fa-city text-info"></i> المدينة / المحلية</span>
                                        <span class="sp-dropdown-selected-val"><?= e(!empty($city) ? $city : 'جميع المدن') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-dropdown-chevron"></i>
                                </button>
                                <div class="sp-dropdown-panel" id="panel_filterCitySelect">
                                    <div class="sp-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-dropdown-search-input" placeholder="ابحث عن مدينة..." autocomplete="off">
                                    </div>
                                    <div class="sp-dropdown-options-list">
                                        <div class="sp-dropdown-option <?= empty($city) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع المدن</span>
                                            <?php if (empty($city)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php if ($state && isset($statesWithCities[$state])): ?>
                                            <?php foreach($statesWithCities[$state] as $c): ?>
                                                <div class="sp-dropdown-option <?= $city === $c ? 'is-selected' : '' ?>" data-value="<?= e($c) ?>">
                                                    <span><?= e($c) ?></span>
                                                    <?php if ($city === $c): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sp-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- Stage -->
                        <div class="col-md-3 col-6">
                            <div class="sp-searchable-dropdown" data-target="filterStageSelect">
                                <select name="stage" class="d-none" id="filterStageSelect">
                                    <option value="">جميع المراحل</option>
                                    <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                        <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-dropdown-trigger" id="trigger_filterStageSelect">
                                    <div class="sp-dropdown-trigger-content">
                                        <span class="sp-dropdown-mini-lbl"><i class="fas fa-graduation-cap text-success"></i> المرحلة</span>
                                        <span class="sp-dropdown-selected-val"><?= e(!empty($stage) && isset($schoolStages[$stage]) ? $schoolStages[$stage] : 'جميع المراحل') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-dropdown-chevron"></i>
                                </button>
                                <div class="sp-dropdown-panel" id="panel_filterStageSelect">
                                    <div class="sp-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-dropdown-search-input" placeholder="ابحث عن مرحلة..." autocomplete="off">
                                    </div>
                                    <div class="sp-dropdown-options-list">
                                        <div class="sp-dropdown-option <?= empty($stage) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع المراحل</span>
                                            <?php if (empty($stage)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                            <div class="sp-dropdown-option <?= $stage === $sKey ? 'is-selected' : '' ?>" data-value="<?= e($sKey) ?>">
                                                <span><?= e($sLbl) ?></span>
                                                <?php if ($stage === $sKey): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Primary Action Row -->
                    <div class="sp-search-action-wrap">
                        <button type="submit" class="sp-btn-search-hero">
                            <i class="fas fa-search"></i>
                            <span>استكشاف المدارس</span>
                        </button>
                        <?php if ($isSearching): ?>
                            <a href="/" class="sp-btn-reset-hero" title="إلغاء الفلاتر">
                                <i class="fas fa-undo"></i>
                                <span>إعادة ضبط</span>
                            </a>
                        <?php endif; ?>
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

            <!-- Left Column: Pure Family Photo Showcase (No Cards Around It) -->
            <div class="col-lg-5">
                <div class="hero-family-frame">
                    <img src="/assets/images/family-hero.jpg" alt="جلسة أسرية لاختيار المدرسة في دليل المدارس الذكي" class="hero-family-img" loading="eager">
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

    // Rebuild options in custom searchable dropdown panel from native select
    function rebuildCustomDropdown(targetSelectId, defaultLabel) {
        const wrapper = document.querySelector(`.sp-searchable-dropdown[data-target="${targetSelectId}"]`);
        if (!wrapper) return;
        const nativeSelect = document.getElementById(targetSelectId);
        if (!nativeSelect) return;
        const optionsList = wrapper.querySelector('.sp-dropdown-options-list');
        const displayVal = wrapper.querySelector('.sp-dropdown-selected-val');
        const emptyState = wrapper.querySelector('.sp-dropdown-empty');
        if (!optionsList) return;

        optionsList.innerHTML = '';
        const opts = nativeSelect.querySelectorAll('option');
        let selectedFound = false;

        opts.forEach(o => {
            const item = document.createElement('div');
            const isSelected = o.selected;
            item.className = 'sp-dropdown-option' + (isSelected ? ' is-selected' : '');
            item.dataset.value = o.value;

            const span = document.createElement('span');
            span.textContent = o.textContent;
            item.appendChild(span);

            if (isSelected) {
                selectedFound = true;
                if (displayVal) displayVal.textContent = o.textContent;
                const check = document.createElement('i');
                check.className = 'fas fa-check text-primary';
                check.style.fontSize = '0.7rem';
                item.appendChild(check);
            }
            optionsList.appendChild(item);
        });

        if (!selectedFound && displayVal) {
            displayVal.textContent = defaultLabel || (opts.length > 0 ? opts[0].textContent : '');
        }
        if (emptyState) emptyState.classList.add('d-none');
    }

    function updateStates(countryVal) {
        if (!stateSelect) return;
        stateSelect.innerHTML = '<option value="">جميع الولايات</option>';
        if (citySelect) {
            citySelect.innerHTML = '<option value="">جميع المدن</option>';
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
        rebuildCustomDropdown('filterStateSelect', 'جميع الولايات');
        rebuildCustomDropdown('filterCitySelect', 'جميع المدن');
    }

    function updateCities(stateVal) {
        if (!citySelect) return;
        citySelect.innerHTML = '<option value="">جميع المدن</option>';
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
        rebuildCustomDropdown('filterCitySelect', 'جميع المدن');
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

    // Initialize all Searchable Dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownWrappers = document.querySelectorAll('.sp-searchable-dropdown');

        dropdownWrappers.forEach(wrapper => {
            const trigger = wrapper.querySelector('.sp-dropdown-trigger');
            const panel = wrapper.querySelector('.sp-dropdown-panel');
            const searchInput = wrapper.querySelector('.sp-dropdown-search-input');
            const targetSelectId = wrapper.dataset.target;
            const nativeSelect = document.getElementById(targetSelectId);
            const optionsList = wrapper.querySelector('.sp-dropdown-options-list');
            const emptyState = wrapper.querySelector('.sp-dropdown-empty');
            const displayVal = wrapper.querySelector('.sp-dropdown-selected-val');

            if (!trigger || !panel) return;

            // Toggle Open/Close
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = wrapper.classList.contains('is-open');
                
                // Close all other open dropdowns
                document.querySelectorAll('.sp-searchable-dropdown.is-open').forEach(w => {
                    if (w !== wrapper) w.classList.remove('is-open');
                });

                wrapper.classList.toggle('is-open', !isOpen);

                if (!isOpen && searchInput) {
                    searchInput.value = '';
                    filterItems('');
                    setTimeout(() => searchInput.focus(), 60);
                }
            });

            // Search Filter Logic
            function filterItems(query) {
                const items = optionsList.querySelectorAll('.sp-dropdown-option');
                let matchesCount = 0;
                items.forEach(item => {
                    const text = item.textContent.trim().toLowerCase();
                    const isMatch = query === '' || text.includes(query);
                    item.style.display = isMatch ? 'flex' : 'none';
                    if (isMatch) matchesCount++;
                });
                if (emptyState) {
                    emptyState.classList.toggle('d-none', matchesCount > 0);
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    filterItems(e.target.value.trim().toLowerCase());
                });
                searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Option Selection via Event Delegation
            optionsList.addEventListener('click', function(e) {
                const opt = e.target.closest('.sp-dropdown-option');
                if (!opt) return;

                const val = opt.dataset.value;
                const text = opt.querySelector('span') ? opt.querySelector('span').textContent.trim() : opt.textContent.trim();

                // 1. Update native select & trigger change
                if (nativeSelect) {
                    nativeSelect.value = val;
                    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 2. Update display text on trigger
                if (displayVal) {
                    displayVal.textContent = text;
                }

                // 3. Mark active state
                optionsList.querySelectorAll('.sp-dropdown-option').forEach(o => {
                    o.classList.remove('is-selected');
                    const check = o.querySelector('i.fa-check');
                    if (check) check.remove();
                });
                opt.classList.add('is-selected');
                const check = document.createElement('i');
                check.className = 'fas fa-check text-primary';
                check.style.fontSize = '0.7rem';
                opt.appendChild(check);

                // 4. Close panel
                wrapper.classList.remove('is-open');
            });
        });

        // Close on Outside Click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sp-searchable-dropdown')) {
                document.querySelectorAll('.sp-searchable-dropdown.is-open').forEach(w => w.classList.remove('is-open'));
            }
        });

        // Close on Escape Key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.sp-searchable-dropdown.is-open').forEach(w => w.classList.remove('is-open'));
            }
        });
    });
</script>
JS;
require_once __DIR__ . '/footer.php';
