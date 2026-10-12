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

// Dynamic region labels based on selected country
$regionMiniLabel = 'الولاية';
$regionAllLabel = 'جميع الولايات';
$regionSearchPlaceholder = 'ابحث عن ولاية...';

if ($country === 'مصر' || $country === 'العراق' || $country === 'الأردن' || $country === 'اليمن' || $country === 'لبنان' || $country === 'سوريا' || $country === 'فلسطين' || $country === 'البحرين' || $country === 'الكويت' || $country === 'سلطنة عمان') {
    $regionMiniLabel = 'المحافظة';
    $regionAllLabel = 'جميع المحافظات';
    $regionSearchPlaceholder = 'ابحث عن محافظة...';
} elseif ($country === 'المملكة العربية السعودية') {
    $regionMiniLabel = 'المنطقة';
    $regionAllLabel = 'جميع المناطق';
    $regionSearchPlaceholder = 'ابحث عن منطقة...';
} elseif ($country === 'الإمارات العربية المتحدة') {
    $regionMiniLabel = 'الإمارة';
    $regionAllLabel = 'جميع الإمارات';
    $regionSearchPlaceholder = 'ابحث عن إمارة...';
} elseif ($country === 'المغرب') {
    $regionMiniLabel = 'الجهة';
    $regionAllLabel = 'جميع الجهات';
    $regionSearchPlaceholder = 'ابحث عن جهة...';
} elseif ($country === 'ليبيا') {
    $regionMiniLabel = 'البلدية';
    $regionAllLabel = 'جميع البلديات';
    $regionSearchPlaceholder = 'ابحث عن بلدية...';
} elseif ($country === 'كندا') {
    $regionMiniLabel = 'المقاطعة';
    $regionAllLabel = 'جميع المقاطعات';
    $regionSearchPlaceholder = 'ابحث عن مقاطعة...';
} elseif (!empty($country) && $country !== 'السودان') {
    $regionMiniLabel = 'المنطقة / الولاية';
    $regionAllLabel = 'جميع المناطق';
    $regionSearchPlaceholder = 'ابحث عن منطقة...';
}

// Statistics
$statTotal = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$statSchoplus = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$statStates = (int) $pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: count(SudanGeography::states());
$statReviews = (int) $pdo->query("SELECT COUNT(*) FROM daleel_school_reviews WHERE is_approved = 1")->fetchColumn();
$totalTopRatedCount = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND rating_avg >= 4.0")->fetchColumn();

// State school counters for the state cards
$stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Distinct states list & regional mapping
$allStatesList = SudanGeography::states();
$sudanStatesWithCities = $countriesWithStates['السودان'] ?? SudanGeography::statesWithCities('السودان');
$sudanRegionMapping = [
    'الخرطوم' => 'central',
    'الجزيرة' => 'central',
    'سنار' => 'central',
    'النيل الأبيض' => 'central',
    'النيل الأزرق' => 'central',
    'البحر الأحمر' => 'east',
    'كسلا' => 'east',
    'القضارف' => 'east',
    'نهر النيل' => 'north',
    'الشمالية' => 'north',
    'شمال كردفان' => 'kordofan',
    'جنوب كردفان' => 'kordofan',
    'غرب كردفان' => 'kordofan',
    'شمال دارفور' => 'darfur',
    'جنوب دارفور' => 'darfur',
    'غرب دارفور' => 'darfur',
    'وسط دارفور' => 'darfur',
    'شرق دارفور' => 'darfur',
];

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

/* ===== LUXURY SEARCHABLE DROPDOWN COMPONENT (HERO CONSOLE) ===== */
.sp-hero-dropdown {
    position: relative;
    width: 100%;
    display: block !important;
}
.sp-hero-dropdown-trigger {
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
.sp-hero-dropdown-trigger:hover {
    background: #FFFFFF;
    border-color: #1E5FA8;
}
.sp-hero-dropdown.is-open .sp-hero-dropdown-trigger {
    background: #FFFFFF;
    border-color: #1E5FA8;
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
}
.sp-hero-dropdown-trigger-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    overflow: hidden;
    line-height: 1.25;
}
.sp-hero-dropdown-mini-lbl {
    font-size: 0.63rem;
    font-weight: 700;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}
.sp-hero-dropdown-selected-val {
    font-size: 0.76rem;
    font-weight: 700;
    color: #0B2A52;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.sp-hero-dropdown-chevron {
    font-size: 0.68rem;
    color: #94A3B8;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.sp-hero-dropdown.is-open .sp-hero-dropdown-chevron {
    transform: rotate(180deg);
    color: #1E5FA8;
}

.sp-hero-dropdown.is-open {
    z-index: 1060;
}

/* Floating Dropdown Panel */
.sp-hero-dropdown-panel {
    position: absolute;
    top: calc(100% + 5px);
    right: 0;
    min-width: 100%;
    width: 230px;
    max-width: min(280px, calc(100vw - 32px));
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    box-shadow: 0 14px 34px rgba(11, 42, 82, 0.14);
    z-index: 1070;
    display: none;
    overflow: hidden;
    animation: spHeroDropdownFadeIn 0.15s ease-out;
}
@media (min-width: 768px) {
    .col-md-3:nth-child(3) .sp-hero-dropdown-panel,
    .col-md-3:nth-child(4) .sp-hero-dropdown-panel {
        right: auto;
        left: 0;
    }
}
@media (max-width: 767px) {
    .col-6:nth-child(even) .sp-hero-dropdown-panel {
        right: auto;
        left: 0;
    }
}
@keyframes spHeroDropdownFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.sp-hero-dropdown.is-open .sp-hero-dropdown-panel {
    display: block !important;
}

/* Dropdown Search Box */
.sp-hero-dropdown-search-box {
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
.sp-hero-dropdown-search-input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.74rem;
    font-weight: 600;
    color: #0D1B2E;
}
.sp-hero-dropdown-search-input::placeholder {
    color: #94A3B8;
}

/* Options List */
.sp-hero-dropdown-options-list {
    max-height: 220px;
    overflow-y: auto;
    padding: 4px;
    scrollbar-width: thin;
    scrollbar-color: #CBD5E1 #F1F5F9;
}
.sp-hero-dropdown-option {
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
.sp-hero-dropdown-option:hover {
    background: #F0F4F8;
    color: #1E5FA8;
}
.sp-hero-dropdown-option.is-selected {
    background: #EFF6FF;
    color: #1E5FA8;
    font-weight: 700;
}
.sp-hero-dropdown-option.is-selected i {
    font-size: 0.7rem;
    color: #1E5FA8;
}
.sp-hero-dropdown-empty {
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

/* Luxury Hero Trust & Value Strip */
.sp-hero-trust-bar {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 14px;
    padding: 10px 16px;
    margin-top: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 16px rgba(11, 42, 82, 0.04);
}
.sp-trust-item {
    display: flex;
    align-items: center;
    gap: 9px;
    flex: 1;
    min-width: 0;
    transition: transform 0.2s ease;
}
.sp-trust-item:hover {
    transform: translateY(-1px);
}
.sp-trust-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.sp-trust-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.25;
}
.sp-trust-title {
    font-size: 0.76rem;
    font-weight: 800;
    color: #0B2A52;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sp-trust-sub {
    font-size: 0.62rem;
    font-weight: 600;
    color: #64748B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.sp-trust-sep {
    width: 1px;
    height: 24px;
    background: #E2E8F0;
    margin: 0 8px;
    flex-shrink: 0;
}
@media (max-width: 991px) {
    .sp-hero-trust-bar {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 12px;
    }
    .sp-trust-sep {
        display: none;
    }
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
    .sp-hero-trust-bar {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 10px 12px;
    }
    .sp-search-card {
        padding: 14px;
        border-radius: 14px;
    }
/* ===== LUXURY JOIN DALEEL CTA BANNER (STYLE F v2) ===== */
.sp-join-section {
    padding: 16px 0 8px;
    margin-bottom: 4px;
}
.sp-join-section.sp-join-top {
    padding-top: 18px;
    padding-bottom: 8px;
    margin-bottom: 0;
}
.sp-join-card {
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 8px 24px rgba(11, 42, 82, 0.14);
    border: 1px solid #1E5FA8;
    border-right: 5px solid #F5A020;
    position: relative;
    overflow: hidden;
}
.sp-join-card::before {
    content: '';
    position: absolute;
    top: -60px;
    left: -60px;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245, 160, 32, 0.12) 0%, rgba(245, 160, 32, 0) 70%);
    pointer-events: none;
}
.sp-join-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(245, 160, 32, 0.2);
    border: 1px solid #F5A020;
    color: #F5A020 !important;
    font-weight: 800;
    font-size: 0.74rem;
    padding: 4px 12px;
    border-radius: 999px;
    margin-bottom: 10px;
}
.sp-join-eyebrow i {
    color: #F5A020 !important;
}
.sp-join-title {
    font-size: 1.32rem;
    font-weight: 800;
    color: #FFFFFF !important;
    margin-bottom: 8px;
    line-height: 1.35;
}
.sp-join-desc {
    font-size: 0.78rem;
    color: #F1F5F9 !important;
    line-height: 1.65;
    margin-bottom: 18px;
    max-width: 650px;
}
.sp-join-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.sp-join-btn-primary {
    background: #F5A020 !important;
    color: #FFFFFF !important;
    font-weight: 800;
    font-size: 0.8rem;
    padding: 9px 20px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(245, 160, 32, 0.35);
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.sp-join-btn-primary:hover {
    background: #E08E15 !important;
    color: #FFFFFF !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(245, 160, 32, 0.45);
}
.sp-join-btn-secondary {
    background: rgba(255, 255, 255, 0.14) !important;
    color: #FFFFFF !important;
    border: 1.5px solid rgba(255, 255, 255, 0.45) !important;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 9px 18px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}
.sp-join-btn-secondary:hover, .sp-join-btn-secondary:active, .sp-join-btn-secondary:focus {
    background: #FFFFFF !important;
    color: #0B2A52 !important;
    border-color: #FFFFFF !important;
}

/* Side Info Box (Visible on Desktop & Mobile with Full Details) */
.sp-join-side-box {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    padding: 18px 20px;
    text-align: center;
}
.sp-join-side-badge {
    color: #F5A020 !important;
    font-size: 1.8rem;
    margin-bottom: 8px;
}
.sp-join-side-title {
    color: #FFFFFF !important;
    font-weight: 800;
    font-size: 0.95rem;
    margin-bottom: 6px;
}
.sp-join-side-desc {
    color: #E2E8F0 !important;
    font-size: 0.72rem;
    line-height: 1.6;
    margin-bottom: 14px;
}
.sp-join-side-btn {
    background: #FFFFFF !important;
    color: #0B2A52 !important;
    font-weight: 800;
    font-size: 0.76rem;
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    border: none;
}
.sp-join-side-btn:hover, .sp-join-side-btn:active {
    background: #F0F4F8 !important;
    color: #1E5FA8 !important;
}

/* Mobile Responsive Optimization (<= 767px) */
@media (max-width: 767px) {
    .sp-join-section.sp-join-top {
        padding-top: 12px;
        padding-bottom: 6px;
    }
    .sp-join-card {
        padding: 16px 14px;
        border-radius: 14px;
        border-right-width: 4px;
    }
    .sp-join-eyebrow {
        font-size: 0.7rem;
        padding: 4px 12px;
        margin-bottom: 8px;
    }
    .sp-join-title {
        font-size: 1.15rem;
        margin-bottom: 8px;
    }
    .sp-join-desc {
        font-size: 0.75rem;
        line-height: 1.65;
        margin-bottom: 14px;
    }
    .sp-join-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }
    .sp-join-btn-primary, .sp-join-btn-secondary {
        width: 100%;
        justify-content: center;
        padding: 10px 14px;
        font-size: 0.82rem;
    }
    .sp-join-side-box {
        padding: 14px 14px;
        margin-top: 4px;
    }
    .sp-join-side-badge {
        font-size: 1.5rem;
        margin-bottom: 6px;
    }
    .sp-join-side-title {
        font-size: 0.9rem;
    }
    .sp-join-side-desc {
        font-size: 0.72rem;
        margin-bottom: 10px;
    }
}

/* ===== LUXURY GEOGRAPHICAL DIRECTORY GRID (STYLE F v2) ===== */
.sp-geo-section {
    background: #FFFFFF;
    border-top: 1px solid #DDE4ED;
    border-bottom: 1px solid #DDE4ED;
    padding: 38px 0 44px;
    margin-top: 36px;
}
.sp-geo-header {
    margin-bottom: 20px;
}
.sp-geo-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #1E5FA8;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.sp-geo-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0B2A52;
    margin-bottom: 4px;
}
.sp-geo-sub {
    font-size: 0.74rem;
    color: #64748B;
    margin-bottom: 0;
}

/* Region Segmented Filter Pills */
.sp-geo-region-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.sp-geo-pill {
    padding: 5px 14px;
    border-radius: 999px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sp-geo-pill:hover {
    background: #E2E8F0;
    color: #0B2A52;
}
.sp-geo-pill.is-active {
    background: #0B2A52;
    color: #FFFFFF;
    border-color: #0B2A52;
}

/* State Cards Grid */
.sp-geo-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
}
.sp-state-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    padding: 13px 12px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 104px;
    position: relative;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(11, 42, 82, 0.02);
}
.sp-state-card:hover {
    border-color: #1E5FA8;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(30, 95, 168, 0.08);
    background: #FAFCFE;
}
.sp-state-card.is-active {
    border-color: #1E5FA8;
    background: #EFF6FF;
    box-shadow: 0 0 0 2px rgba(30, 95, 168, 0.15);
}
.sp-state-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.sp-state-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #F1F5F9;
    color: #1E5FA8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    transition: all 0.2s ease;
}
.sp-state-card:hover .sp-state-icon {
    background: #1E5FA8;
    color: #FFFFFF;
}
.sp-state-card.is-active .sp-state-icon {
    background: #0B2A52;
    color: #FFFFFF;
}
.sp-state-badge {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    background: #F8FAFC;
    color: #64748B;
    border: 1px solid #E2E8F0;
}
.sp-state-badge.has-schools {
    background: #EFF6FF;
    color: #1E5FA8;
    border-color: #BFDBFE;
}
.sp-state-name {
    font-size: 0.82rem;
    font-weight: 800;
    color: #0B2A52;
    line-height: 1.3;
    margin-bottom: 3px;
}
.sp-state-cities {
    font-size: 0.64rem;
    color: #64748B;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sp-state-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px dashed #EDF2F7;
    font-size: 0.65rem;
    color: #94A3B8;
}
.sp-state-card:hover .sp-state-foot {
    color: #1E5FA8;
}

@media (max-width: 1199px) {
    .sp-geo-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 767px) {
    .sp-geo-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .sp-state-card {
        padding: 10px 10px;
        min-height: 90px;
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

<!-- SCHOOL OWNERS CTA BANNER (PLACED AT THE VERY TOP OF THE PAGE WITH ALL DETAILS) -->
<section class="sp-join-section sp-join-top" id="join-daleel-section">
    <div class="container">
        <div class="sp-join-card">
            <div class="row align-items-center g-4">
                <div class="col-lg-8 col-12">
                    <div class="sp-join-eyebrow">
                        <i class="fas fa-school"></i>
                        <span>انضم إلى دليل المدارس</span>
                    </div>
                    <h2 class="sp-join-title">هل تدير مدرسة أو مجمعاً تعليمياً؟</h2>
                    <p class="sp-join-desc">
                        خدمة رقمية مقدمة من منظومة ايسكو بلص (SchoPlus). أضف بيانات مدرستك، أنشئ ملفاً تعريفياً متكاملاً، وانشر أخبار ومرافق مدرستك واستقبل استفسارات أولياء الأمور مباشرة.
                    </p>
                    <div class="sp-join-actions">
                        <a href="/register.php" class="sp-join-btn-primary">
                            <i class="fas fa-school"></i>
                            <span>سجّل مدرستك الآن</span>
                        </a>
                        <a href="/login.php" class="sp-join-btn-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>دخول أصحاب المدارس</span>
                        </a>
                    </div>
                </div>

                <!-- Side Box: SchoPlus Smart Systems (Detailed & Fully Visible on Mobile & Desktop) -->
                <div class="col-lg-4 col-12">
                    <div class="sp-join-side-box">
                        <div class="sp-join-side-badge">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h5 class="sp-join-side-title">ارتقِ بمدرستك مع SchoPlus</h5>
                        <p class="sp-join-side-desc">
                            حوّل مدرستك إلى مدرسة ذكية مع أنظمة الحسابات، شؤون الطلاب، الجداول الآلية، وبوابة أولياء الأمور عبر الواتساب.
                        </p>
                        <a href="https://schoplus.sd" target="_blank" class="sp-join-side-btn">
                            <i class="fas fa-external-link-alt"></i>
                            <span>تعرف على نظام SchoPlus</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
                            <div class="sp-hero-dropdown" data-target="filterCountrySelect">
                                <select name="country" class="d-none" id="filterCountrySelect">
                                    <option value="">جميع الدول</option>
                                    <?php foreach($countriesList as $cntry): ?>
                                        <option value="<?= e($cntry) ?>" <?= $country === $cntry ? 'selected' : '' ?>><?= e($cntry) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-hero-dropdown-trigger" id="trigger_filterCountrySelect">
                                    <div class="sp-hero-dropdown-trigger-content">
                                        <span class="sp-hero-dropdown-mini-lbl"><i class="fas fa-globe text-primary"></i> الدولة</span>
                                        <span class="sp-hero-dropdown-selected-val"><?= e(!empty($country) ? $country : 'جميع الدول') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-hero-dropdown-chevron"></i>
                                </button>
                                <div class="sp-hero-dropdown-panel" id="panel_filterCountrySelect">
                                    <div class="sp-hero-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-hero-dropdown-search-input" placeholder="ابحث عن دولة..." autocomplete="off">
                                    </div>
                                    <div class="sp-hero-dropdown-options-list">
                                        <div class="sp-hero-dropdown-option <?= empty($country) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع الدول</span>
                                            <?php if (empty($country)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($countriesList as $cntry): ?>
                                            <div class="sp-hero-dropdown-option <?= $country === $cntry ? 'is-selected' : '' ?>" data-value="<?= e($cntry) ?>">
                                                <span><?= e($cntry) ?></span>
                                                <?php if ($country === $cntry): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-hero-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- State -->
                        <div class="col-md-3 col-6">
                            <div class="sp-hero-dropdown" data-target="filterStateSelect">
                                <select name="state" class="d-none" id="filterStateSelect">
                                    <option value=""><?= $regionAllLabel ?></option>
                                    <?php foreach($statesWithCities as $stName => $cities): ?>
                                        <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-hero-dropdown-trigger" id="trigger_filterStateSelect">
                                    <div class="sp-hero-dropdown-trigger-content">
                                        <span class="sp-hero-dropdown-mini-lbl"><i class="fas fa-map-marker-alt text-danger"></i> <?= $regionMiniLabel ?></span>
                                        <span class="sp-hero-dropdown-selected-val"><?= e(!empty($state) ? $state : $regionAllLabel) ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-hero-dropdown-chevron"></i>
                                </button>
                                <div class="sp-hero-dropdown-panel" id="panel_filterStateSelect">
                                    <div class="sp-hero-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-hero-dropdown-search-input" placeholder="<?= $regionSearchPlaceholder ?>" autocomplete="off">
                                    </div>
                                    <div class="sp-hero-dropdown-options-list">
                                        <div class="sp-hero-dropdown-option <?= empty($state) ? 'is-selected' : '' ?>" data-value="">
                                            <span><?= $regionAllLabel ?></span>
                                            <?php if (empty($state)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($statesWithCities as $stName => $cities): ?>
                                            <div class="sp-hero-dropdown-option <?= $state === $stName ? 'is-selected' : '' ?>" data-value="<?= e($stName) ?>">
                                                <span><?= e($stName) ?></span>
                                                <?php if ($state === $stName): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-hero-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- City -->
                        <div class="col-md-3 col-6">
                            <div class="sp-hero-dropdown" data-target="filterCitySelect">
                                <select name="city" class="d-none" id="filterCitySelect">
                                    <option value="">جميع المدن</option>
                                    <?php if ($state && isset($statesWithCities[$state])): ?>
                                        <?php foreach($statesWithCities[$state] as $c): ?>
                                            <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="sp-hero-dropdown-trigger" id="trigger_filterCitySelect">
                                    <div class="sp-hero-dropdown-trigger-content">
                                        <span class="sp-hero-dropdown-mini-lbl"><i class="fas fa-city text-info"></i> المدينة / المحلية</span>
                                        <span class="sp-hero-dropdown-selected-val"><?= e(!empty($city) ? $city : 'جميع المدن') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-hero-dropdown-chevron"></i>
                                </button>
                                <div class="sp-hero-dropdown-panel" id="panel_filterCitySelect">
                                    <div class="sp-hero-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-hero-dropdown-search-input" placeholder="ابحث عن مدينة..." autocomplete="off">
                                    </div>
                                    <div class="sp-hero-dropdown-options-list">
                                        <div class="sp-hero-dropdown-option <?= empty($city) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع المدن</span>
                                            <?php if (empty($city)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php if ($state && isset($statesWithCities[$state])): ?>
                                            <?php foreach($statesWithCities[$state] as $c): ?>
                                                <div class="sp-hero-dropdown-option <?= $city === $c ? 'is-selected' : '' ?>" data-value="<?= e($c) ?>">
                                                    <span><?= e($c) ?></span>
                                                    <?php if ($city === $c): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sp-hero-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- Stage -->
                        <div class="col-md-3 col-6">
                            <div class="sp-hero-dropdown" data-target="filterStageSelect">
                                <select name="stage" class="d-none" id="filterStageSelect">
                                    <option value="">جميع المراحل</option>
                                    <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                        <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="sp-hero-dropdown-trigger" id="trigger_filterStageSelect">
                                    <div class="sp-hero-dropdown-trigger-content">
                                        <span class="sp-hero-dropdown-mini-lbl"><i class="fas fa-graduation-cap text-success"></i> المرحلة</span>
                                        <span class="sp-hero-dropdown-selected-val"><?= e(!empty($stage) && isset($schoolStages[$stage]) ? $schoolStages[$stage] : 'جميع المراحل') ?></span>
                                    </div>
                                    <i class="fas fa-chevron-down sp-hero-dropdown-chevron"></i>
                                </button>
                                <div class="sp-hero-dropdown-panel" id="panel_filterStageSelect">
                                    <div class="sp-hero-dropdown-search-box">
                                        <i class="fas fa-search text-muted"></i>
                                        <input type="text" class="sp-hero-dropdown-search-input" placeholder="ابحث عن مرحلة..." autocomplete="off">
                                    </div>
                                    <div class="sp-hero-dropdown-options-list">
                                        <div class="sp-hero-dropdown-option <?= empty($stage) ? 'is-selected' : '' ?>" data-value="">
                                            <span>جميع المراحل</span>
                                            <?php if (empty($stage)): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                        </div>
                                        <?php foreach($schoolStages as $sKey => $sLbl): ?>
                                            <div class="sp-hero-dropdown-option <?= $stage === $sKey ? 'is-selected' : '' ?>" data-value="<?= e($sKey) ?>">
                                                <span><?= e($sLbl) ?></span>
                                                <?php if ($stage === $sKey): ?><i class="fas fa-check text-primary"></i><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="sp-hero-dropdown-empty d-none">لا توجد نتائج مطابقة</div>
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

                <!-- Luxury Trust & Value Strip -->
                <div class="sp-hero-trust-bar">
                    <div class="sp-trust-item">
                        <div class="sp-trust-icon" style="color: #1E5FA8; background: #EFF6FF; border-color: #DBEAFE;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="sp-trust-info">
                            <span class="sp-trust-title"><?= $statTotal > 5 ? number_format($statTotal) . '+ مدرسة معتمدة' : 'دليل معتمد وموثق' ?></span>
                            <span class="sp-trust-sub">بيانات وتراخيص رسمية</span>
                        </div>
                    </div>
                    <div class="sp-trust-sep"></div>
                    <div class="sp-trust-item">
                        <div class="sp-trust-icon" style="color: #0B2A52; background: #F1F5F9; border-color: #E2E8F0;">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="sp-trust-info">
                            <span class="sp-trust-title">18 ولاية سودانية</span>
                            <span class="sp-trust-sub">تغطية جغرافية شاملة</span>
                        </div>
                    </div>
                    <div class="sp-trust-sep"></div>
                    <div class="sp-trust-item">
                        <div class="sp-trust-icon" style="color: #F5A020; background: #FFFBEB; border-color: #FEF3C7;">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="sp-trust-info">
                            <span class="sp-trust-title">مقارنة ذكية للمدارس</span>
                            <span class="sp-trust-sub">المناهج والرسوم والخدمات</span>
                        </div>
                    </div>
                    <div class="sp-trust-sep"></div>
                    <div class="sp-trust-item">
                        <div class="sp-trust-icon" style="color: #16A34A; background: #F0FDF4; border-color: #DCFCE7;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div class="sp-trust-info">
                            <span class="sp-trust-title">تواصل مباشر وسريع</span>
                            <span class="sp-trust-sub">واتساب وقبول فوري</span>
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

<!-- LUXURY GEOGRAPHICAL DIRECTORY SECTION (BOTTOM) -->
<section class="sp-geo-section" id="geo-directory-section">
    <div class="container">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end sp-geo-header">
            <div>
                <span class="sp-geo-eyebrow">
                    <i class="fas fa-map-marked-alt"></i> التوزيع الجغرافي الشامل
                </span>
                <h3 class="sp-geo-title">دليل المدارس حسب الولايات والمناطق</h3>
                <p class="sp-geo-sub">
                    استكشف المدارس المعتمدة والمجمعات التعليمية موزعة جغرافياً عبر كافة ولايات السودان الـ 18
                </p>
            </div>
            <div class="mt-3 mt-md-0 text-muted" style="font-size: 0.72rem;">
                <i class="fas fa-check-circle text-primary me-1"></i> تغطية لجميع المحليات والمراكز الأكاديمية
            </div>
        </div>

        <!-- Regional Filter Pills -->
        <div class="sp-geo-region-pills">
            <button type="button" class="sp-geo-pill is-active" onclick="filterGeoRegion('all', this)">
                <i class="fas fa-layer-group"></i> كافة الولايات (18)
            </button>
            <button type="button" class="sp-geo-pill" onclick="filterGeoRegion('central', this)">
                <i class="fas fa-city"></i> الوسط والعاصمة
            </button>
            <button type="button" class="sp-geo-pill" onclick="filterGeoRegion('east', this)">
                <i class="fas fa-water"></i> القطاع الشرقي
            </button>
            <button type="button" class="sp-geo-pill" onclick="filterGeoRegion('north', this)">
                <i class="fas fa-compass"></i> القطاع الشمالي
            </button>
            <button type="button" class="sp-geo-pill" onclick="filterGeoRegion('kordofan', this)">
                <i class="fas fa-tree"></i> كردفان الكبرى
            </button>
            <button type="button" class="sp-geo-pill" onclick="filterGeoRegion('darfur', this)">
                <i class="fas fa-mountain"></i> دارفور الكبرى
            </button>
        </div>

        <!-- State Cards Grid (3x6) -->
        <div class="sp-geo-grid" id="spGeoGrid">
            <?php foreach($allStatesList as $stName): ?>
                <?php 
                    $countInState = $stateCounts[$stName] ?? 0;
                    $isSelectedState = ($state === $stName);
                    $stateUrl = $isSelectedState ? '/' : '/?state=' . urlencode($stName);
                    $citiesList = $sudanStatesWithCities[$stName] ?? [];
                    $regKey = $sudanRegionMapping[$stName] ?? 'all';
                ?>
                <a href="<?= $stateUrl ?>" class="sp-state-card <?= $isSelectedState ? 'is-active' : '' ?>" data-region="<?= $regKey ?>" title="مدارس ولاية <?= e($stName) ?>">
                    <div>
                        <div class="sp-state-top">
                            <div class="sp-state-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <span class="sp-state-badge <?= $countInState > 0 ? 'has-schools' : '' ?>">
                                <?= $countInState > 0 ? $countInState . ($countInState == 1 ? ' مدرسة' : ' مدارس') : 'استكشف' ?>
                            </span>
                        </div>
                        <div class="sp-state-name"><?= e($stName) ?></div>
                        <div class="sp-state-cities"><?= e(!empty($citiesList) ? implode(' • ', array_slice($citiesList, 0, 2)) : 'المراكز التعليمية') ?></div>
                    </div>
                    <div class="sp-state-foot">
                        <span>تصفح المدارس</span>
                        <i class="fas fa-arrow-left"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
</section>


<?php
$jsonAllGeo = json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE);
$extraScripts = <<<JS
<script>
    const allGeoMap = {$jsonAllGeo};

    // Geographic Region Filter (for bottom directory)
    window.filterGeoRegion = function(regionKey, btn) {
        document.querySelectorAll('.sp-geo-pill').forEach(p => p.classList.remove('is-active'));
        if (btn) btn.classList.add('is-active');
        document.querySelectorAll('.sp-state-card').forEach(card => {
            if (regionKey === 'all' || card.dataset.region === regionKey) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    };

    // Region labels resolver per country
    function getRegionLabels(countryVal) {
        if (!countryVal || countryVal === 'السودان') {
            return {
                mini: 'الولاية',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع الولايات',
                search: 'ابحث عن ولاية...'
            };
        }
        if (countryVal === 'مصر' || countryVal === 'العراق' || countryVal === 'الأردن' || countryVal === 'اليمن' || countryVal === 'لبنان' || countryVal === 'سوريا' || countryVal === 'فلسطين' || countryVal === 'البحرين' || countryVal === 'الكويت' || countryVal === 'سلطنة عمان') {
            return {
                mini: 'المحافظة',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع المحافظات',
                search: 'ابحث عن محافظة...'
            };
        }
        if (countryVal === 'المملكة العربية السعودية') {
            return {
                mini: 'المنطقة',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع المناطق',
                search: 'ابحث عن منطقة...'
            };
        }
        if (countryVal === 'الإمارات العربية المتحدة') {
            return {
                mini: 'الإمارة',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع الإمارات',
                search: 'ابحث عن إمارة...'
            };
        }
        if (countryVal === 'المغرب') {
            return {
                mini: 'الجهة',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع الجهات',
                search: 'ابحث عن جهة...'
            };
        }
        if (countryVal === 'ليبيا') {
            return {
                mini: 'البلدية',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع البلديات',
                search: 'ابحث عن بلدية...'
            };
        }
        if (countryVal === 'كندا') {
            return {
                mini: 'المقاطعة',
                icon: 'fas fa-map-marker-alt text-danger',
                all: 'جميع المقاطعات',
                search: 'ابحث عن مقاطعة...'
            };
        }
        return {
            mini: 'المنطقة / الولاية',
            icon: 'fas fa-map-marker-alt text-danger',
            all: 'جميع المناطق',
            search: 'ابحث عن منطقة...'
        };
    }

    // Rebuild options in custom searchable dropdown panel from native select
    function rebuildCustomDropdown(targetSelectId, defaultLabel) {
        const wrapper = document.querySelector('.sp-hero-dropdown[data-target="' + targetSelectId + '"]');
        if (!wrapper) return;
        const nativeSelect = document.getElementById(targetSelectId);
        if (!nativeSelect) return;
        const optionsList = wrapper.querySelector('.sp-hero-dropdown-options-list');
        const displayVal = wrapper.querySelector('.sp-hero-dropdown-selected-val');
        const emptyState = wrapper.querySelector('.sp-hero-dropdown-empty');
        const searchInput = wrapper.querySelector('.sp-hero-dropdown-search-input');
        if (!optionsList) return;

        optionsList.innerHTML = '';
        const opts = nativeSelect.querySelectorAll('option');
        let selectedFound = false;

        opts.forEach(o => {
            const item = document.createElement('div');
            const isSelected = (o.value === nativeSelect.value) && (!selectedFound || o.value !== '');
            if (isSelected) {
                selectedFound = true;
            }
            item.className = 'sp-hero-dropdown-option' + (isSelected ? ' is-selected' : '');
            item.dataset.value = o.value;

            const span = document.createElement('span');
            span.textContent = o.textContent;
            item.appendChild(span);

            if (isSelected) {
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
        if (searchInput) {
            searchInput.value = '';
        }
        if (emptyState) {
            emptyState.classList.add('d-none');
        }
    }

    function updateStates(countryVal) {
        const stateSelect = document.getElementById('filterStateSelect');
        const citySelect = document.getElementById('filterCitySelect');
        if (!stateSelect) return;

        const rLabels = getRegionLabels(countryVal);

        // Update trigger mini-label and search placeholder
        const stateWrapper = document.querySelector('.sp-hero-dropdown[data-target="filterStateSelect"]');
        if (stateWrapper) {
            const miniLbl = stateWrapper.querySelector('.sp-hero-dropdown-mini-lbl');
            if (miniLbl) {
                miniLbl.innerHTML = '<i class="' + rLabels.icon + '"></i> ' + rLabels.mini;
            }
            const searchInput = stateWrapper.querySelector('.sp-hero-dropdown-search-input');
            if (searchInput) {
                searchInput.placeholder = rLabels.search;
                searchInput.value = '';
            }
        }

        // Repopulate native state select
        stateSelect.innerHTML = '<option value="">' + rLabels.all + '</option>';
        let statesObj = {};
        if (countryVal && allGeoMap[countryVal]) {
            statesObj = allGeoMap[countryVal];
        } else if (countryVal) {
            statesObj = { 'المركز الرئيسي / العاصمة': ['المركز الرئيسي / العاصمة'] };
        } else {
            statesObj = allGeoMap['السودان'] || {};
        }

        Object.keys(statesObj).forEach(st => {
            const opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            stateSelect.appendChild(opt);
        });
        stateSelect.value = '';

        // Reset native city select
        if (citySelect) {
            citySelect.innerHTML = '<option value="">جميع المدن</option>';
            citySelect.value = '';
        }
        const cityWrapper = document.querySelector('.sp-hero-dropdown[data-target="filterCitySelect"]');
        if (cityWrapper) {
            const searchInput = cityWrapper.querySelector('.sp-hero-dropdown-search-input');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        rebuildCustomDropdown('filterStateSelect', rLabels.all);
        rebuildCustomDropdown('filterCitySelect', 'جميع المدن');
    }

    function updateCities(stateVal) {
        const countrySelect = document.getElementById('filterCountrySelect');
        const citySelect = document.getElementById('filterCitySelect');
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
        citySelect.value = '';

        const cityWrapper = document.querySelector('.sp-hero-dropdown[data-target="filterCitySelect"]');
        if (cityWrapper) {
            const searchInput = cityWrapper.querySelector('.sp-hero-dropdown-search-input');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        rebuildCustomDropdown('filterCitySelect', 'جميع المدن');
    }

    // Initialize all Searchable Dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        const countrySelect = document.getElementById('filterCountrySelect');
        const stateSelect = document.getElementById('filterStateSelect');
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

        const dropdownWrappers = document.querySelectorAll('.sp-hero-dropdown');

        dropdownWrappers.forEach(wrapper => {
            const trigger = wrapper.querySelector('.sp-hero-dropdown-trigger');
            const panel = wrapper.querySelector('.sp-hero-dropdown-panel');
            const searchInput = wrapper.querySelector('.sp-hero-dropdown-search-input');
            const targetSelectId = wrapper.dataset.target;
            const nativeSelect = document.getElementById(targetSelectId);
            const optionsList = wrapper.querySelector('.sp-hero-dropdown-options-list');
            const emptyState = wrapper.querySelector('.sp-hero-dropdown-empty');
            const displayVal = wrapper.querySelector('.sp-hero-dropdown-selected-val');

            if (!trigger || !panel) return;

            // Toggle Open/Close
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = wrapper.classList.contains('is-open');
                
                // Close all other open dropdowns
                document.querySelectorAll('.sp-hero-dropdown.is-open').forEach(w => {
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
                const items = optionsList.querySelectorAll('.sp-hero-dropdown-option');
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
                const opt = e.target.closest('.sp-hero-dropdown-option');
                if (!opt) return;

                const val = opt.dataset.value;
                const text = opt.querySelector('span') ? opt.querySelector('span').textContent.trim() : opt.textContent.trim();

                // 1. Update native select & trigger change
                if (nativeSelect) {
                    nativeSelect.value = val;
                    nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 2. Direct cascading updates for instant response
                if (targetSelectId === 'filterCountrySelect') {
                    updateStates(val);
                } else if (targetSelectId === 'filterStateSelect') {
                    updateCities(val);
                }

                // 3. Update display text on trigger
                if (displayVal) {
                    displayVal.textContent = text;
                }

                // 4. Mark active state
                optionsList.querySelectorAll('.sp-hero-dropdown-option').forEach(o => {
                    o.classList.remove('is-selected');
                    const check = o.querySelector('i.fa-check');
                    if (check) check.remove();
                });
                opt.classList.add('is-selected');
                const check = document.createElement('i');
                check.className = 'fas fa-check text-primary';
                check.style.fontSize = '0.7rem';
                opt.appendChild(check);

                // 5. Close panel
                wrapper.classList.remove('is-open');
            });
        });

        // Close on Outside Click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sp-hero-dropdown')) {
                document.querySelectorAll('.sp-hero-dropdown.is-open').forEach(w => w.classList.remove('is-open'));
            }
        });

        // Close on Escape Key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.sp-hero-dropdown.is-open').forEach(w => w.classList.remove('is-open'));
            }
        });

        // Smart Visitor Country Auto-Ordering (Position #2 right after Sudan)
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            const tzMap = {
                'Africa/Cairo': 'مصر',
                'Asia/Riyadh': 'المملكة العربية السعودية',
                'Asia/Dubai': 'الإمارات العربية المتحدة',
                'Asia/Qatar': 'قطر',
                'Asia/Kuwait': 'الكويت',
                'Asia/Muscat': 'سلطنة عمان',
                'Asia/Bahrain': 'البحرين',
                'Asia/Amman': 'الأردن',
                'Asia/Baghdad': 'العراق',
                'Africa/Tripoli': 'ليبيا',
                'Europe/London': 'المملكة المتحدة',
                'America/New_York': 'الولايات المتحدة الأمريكية',
                'America/Chicago': 'الولايات المتحدة الأمريكية',
                'America/Los_Angeles': 'الولايات المتحدة الأمريكية',
                'America/Toronto': 'كندا',
                'Europe/Berlin': 'ألمانيا',
                'Europe/Paris': 'فرنسا',
                'Europe/Istanbul': 'تركيا',
                'Asia/Istanbul': 'تركيا',
                'Asia/Kuala_Lumpur': 'ماليزيا'
            };
            const detectedFromTz = tzMap[tz];
            if (detectedFromTz && detectedFromTz !== 'السودان') {
                if (document.cookie.indexOf('detected_country') === -1) {
                    document.cookie = 'detected_country=' + encodeURIComponent(detectedFromTz) + '; path=/; max-age=604800';
                }
                const cntryList = document.querySelector('.sp-hero-dropdown[data-target="filterCountrySelect"] .sp-hero-dropdown-options-list');
                if (cntryList) {
                    const opt = cntryList.querySelector('.sp-hero-dropdown-option[data-value="' + detectedFromTz + '"]');
                    const sudanOpt = cntryList.querySelector('.sp-hero-dropdown-option[data-value="السودان"]');
                    if (opt && sudanOpt && sudanOpt.nextSibling !== opt) {
                        sudanOpt.after(opt);
                    }
                }
            }
        } catch(err) {}
    });
</script>
JS;
require_once __DIR__ . '/footer.php';
