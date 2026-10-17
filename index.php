<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

$isAjax = (!empty($_GET['ajax']) && $_GET['ajax'] == '1') || 
          (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$pdo = db();

// Filter Inputs
$q = trim($_GET['q'] ?? '');
$country = trim($_GET['country'] ?? '');
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$stage = trim($_GET['stage'] ?? '');
$schoolType = trim($_GET['type'] ?? $_GET['school_type'] ?? '');
$schoplusOnly = !empty($_GET['schoplus_only']);
$sort = trim($_GET['sort'] ?? '');
$view = trim($_GET['view'] ?? ''); // 'all', 'schoplus', 'top_rated', 'historic', 'latest', or ''
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// Determine if user is performing a specific search or filter
$isSearching = ($q !== '' || $country !== '' || $state !== '' || $city !== '' || $stage !== '' || $schoolType !== '' || $schoplusOnly || in_array($view, ['all', 'schoplus', 'top_rated', 'historic', 'latest']) || $sort !== '');
$isDefaultCatalog = !$isSearching;

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

// School types global counters
$statGovernment = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND school_type = 'government'")->fetchColumn();
$statPrivate = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND school_type IN ('private', 'international', 'community', 'language')")->fetchColumn();

// Historic schools global counter (is_historic = 1 OR established_year <= 1950 OR historic_votes >= 500 OR iconic names)
$historicWhereSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%')";
$statHistoric = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND {$historicWhereSql}")->fetchColumn();

// State school counters for the state cards
$stateCounts = $pdo->query("SELECT state, COUNT(*) as cnt FROM daleel_schools WHERE status = 'approved' GROUP BY state")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Dynamic Tab Counters (taking selected state into account if state is filtered)
if ($state !== '') {
    $stCountsStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN school_type = 'government' THEN 1 ELSE 0 END) as gov,
        SUM(CASE WHEN school_type IN ('private', 'international', 'community', 'language') THEN 1 ELSE 0 END) as priv,
        SUM(CASE WHEN is_schoplus_client = 1 THEN 1 ELSE 0 END) as schoplus,
        SUM(CASE WHEN {$historicWhereSql} THEN 1 ELSE 0 END) as historic
        FROM daleel_schools WHERE status = 'approved' AND state = ?");
    $stCountsStmt->execute([$state]);
    $stRow = $stCountsStmt->fetch(PDO::FETCH_ASSOC);
    $tabCountTotal = (int) ($stRow['total'] ?? 0);
    $tabCountGov = (int) ($stRow['gov'] ?? 0);
    $tabCountPriv = (int) ($stRow['priv'] ?? 0);
    $tabCountSchoplus = (int) ($stRow['schoplus'] ?? 0);
    $tabCountHistoric = (int) ($stRow['historic'] ?? 0);
} else {
    $tabCountTotal = $statTotal;
    $tabCountGov = $statGovernment;
    $tabCountPriv = $statPrivate;
    $tabCountSchoplus = $statSchoplus;
    $tabCountHistoric = $statHistoric;
}

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

/**
 * Safe filter URL builder preserving existing query parameters
 */
if (!function_exists('buildFilterUrl')) {
    function buildFilterUrl(array $overrides = []): string {
        $params = $_GET;
        unset($params['ajax']);
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        $clean = [];
        foreach ($params as $k => $v) {
            if ($v !== null && $v !== '') {
                $clean[$k] = $v;
            }
        }
        if (empty($clean)) return '/';
        return '/?' . http_build_query($clean);
    }
}

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

    if ($schoolType === 'government') {
        $where[] = "school_type = 'government'";
    } elseif ($schoolType === 'private') {
        $where[] = "school_type IN ('private', 'international', 'community', 'language')";
    } elseif ($schoolType !== '') {
        $where[] = "school_type = ?";
        $params[] = $schoolType;
    }

    if ($schoplusOnly || $view === 'schoplus') {
        $where[] = "is_schoplus_client = 1";
    }

    if ($view === 'historic') {
        $where[] = $historicWhereSql;
    }

    $whereClause = implode(' AND ', $where);

    $whereClause = implode(' AND ', $where);

    // Default sorting order: Historic schools FIRST, then newest additions (id DESC)
    $orderBy = "{$historicWhereSql} DESC, id DESC";
    if ($view === 'historic') $orderBy = "is_historic DESC, historic_votes DESC, (CAST(established_year AS UNSIGNED) > 0) DESC, CAST(established_year AS UNSIGNED) ASC, rating_avg DESC, id DESC";
    if ($sort === 'rating' || $view === 'top_rated') $orderBy = "rating_avg DESC, reviews_count DESC, id DESC";
    if ($sort === 'reviews') $orderBy = "reviews_count DESC, id DESC";
    if ($sort === 'views') $orderBy = "views_count DESC, id DESC";
    if ($sort === 'latest' || $view === 'latest') $orderBy = "created_at DESC, id DESC";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE {$whereClause}");
    $countStmt->execute($params);
    $totalMatching = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalMatching / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    $searchStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
    $searchStmt->execute($params);
    $searchResults = $searchStmt->fetchAll();
} else {
    // Default home view (تبويب كافة المدارس):
    // Orders historic schools FIRST, then by newest addition (id DESC), paginated 12 schools per page
    $totalMatching = $statTotal;
    $totalPages = max(1, (int) ceil($totalMatching / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // Featured / SchoPlus Showcase only on page 1 of default view
    $schoplusShowcase = [];
    if ($page === 1) {
        $schoplusShowcase = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1 ORDER BY is_featured DESC, rating_avg DESC, id DESC LIMIT 6")->fetchAll();
    }

    $defaultOrder = "{$historicWhereSql} DESC, id DESC";
    $catalogSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY {$defaultOrder} LIMIT {$perPage} OFFSET {$offset}")->fetchAll();
    $searchResults = $catalogSchools;
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
<div class="col-xl-4 col-md-6 col-12">
    <div class="luxury-card clickable-school-card h-100 d-flex flex-column" onclick="window.location.href='<?= $schoolUrl ?>'">
        
        <div class="card-cover-wrap" style="<?= $cover ? "background-image: url('{$cover}');" : '' ?>">
            <div class="card-cover-overlay"></div>
            <?php if (!$cover): ?>
                <div class="cover-placeholder-icon"><i class="fas fa-school"></i></div>
            <?php endif; ?>

            <div style="position: absolute; top: 10px; left: 10px; z-index: 2; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                <span class="badge-directory-rank"><i class="fas fa-award"></i> الترتيب #<?= $sRank ?></span>
                <?php if (isSchoolHistoric($s)): ?>
                    <?php $hB = getSchoolHistoricBadge($s); ?>
                    <span class="badge" style="background: linear-gradient(135deg, #78350F 0%, #B45309 100%); color: #FEF3C7; border: 1px solid #FCD34D; font-size: 0.65rem; padding: 2.5px 8px; border-radius: 999px; font-weight: 800; box-shadow: 0 2px 8px rgba(180, 83, 9, 0.35);">
                        <i class="fas fa-landmark text-warning"></i> <?= e($hB['label']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($s['is_schoplus_client'])): ?>
                    <span class="badge-schoplus-smart"><i class="fas fa-bolt"></i> SchoPlus الذكي</span>
                <?php endif; ?>
                <?php if (!empty($s['is_verified'])): ?>
                    <span class="badge bg-success text-white border border-light" style="font-size: 0.65rem; padding: 2px 8px; border-radius: 999px;"><i class="fas fa-check-circle me-1"></i> موثقة</span>
                <?php else: ?>
                    <span class="badge bg-secondary text-white border border-light" style="font-size: 0.65rem; padding: 2px 8px; border-radius: 999px; opacity: 0.9;"><i class="fas fa-info-circle me-1"></i> عامة</span>
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
                <?php if (isSchoolHistoric($s)): ?>
                    <?php $hInfo = getSchoolHistoricBadge($s); ?>
                    <span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.68rem; font-weight: 800;">
                        <i class="fas fa-landmark text-warning me-1"></i> صرح عريق (<?= e($hInfo['sub']) ?>)
                    </span>
                <?php elseif (!empty($s['established_year'])): ?>
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

// Dynamic Title Resolution
$dynamicTitle = 'دليل المدارس — دليلك الشامل لاختيار أفضل المدارس';
if ($schoolType === 'government') {
    $dynamicTitle = !empty($state) ? "المدارس الحكومية في ولاية {$state} — دليل المدارس" : "المدارس الحكومية في السودان — دليل المدارس";
} elseif ($schoolType === 'private') {
    $dynamicTitle = !empty($state) ? "المدارس الخاصة والدولية في ولاية {$state} — دليل المدارس" : "المدارس الخاصة والدولية في السودان — دليل المدارس";
} elseif (!empty($state)) {
    $dynamicTitle = "مدارس ولاية {$state} — دليل المدارس الذكي";
} elseif ($view === 'schoplus' || $schoplusOnly) {
    $dynamicTitle = 'مدارس SchoPlus الذكية — دليل المدارس';
} elseif ($view === 'top_rated' || $sort === 'rating') {
    $dynamicTitle = 'المدارس الأعلى تقييماً — دليل المدارس';
} elseif ($view === 'latest' || $sort === 'latest') {
    $dynamicTitle = 'أحدث المدارس المنضمة — دليل المدارس';
} elseif (!empty($q)) {
    $dynamicTitle = "نتائج البحث عن '{$q}' — دليل المدارس";
}

// Handle AJAX Partial Response
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    ob_start();
    require __DIR__ . '/_catalog_content.php';
    $catalogHtml = ob_get_clean();

    echo json_encode([
        'success' => true,
        'title' => $dynamicTitle,
        'html' => $catalogHtml,
        'total' => $isSearching ? $totalMatching : $statTotal,
        'state' => $state,
        'city' => $city,
        'stage' => $stage,
        'school_type' => $schoolType,
        'type' => $schoolType,
        'q' => $q,
        'view' => $view,
        'sort' => $sort,
        'schoplus_only' => $schoplusOnly ? 1 : 0,
        'page' => $page,
        'total_pages' => $totalPages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normal Full Page Request: Render Header
$pageTitle = $dynamicTitle;
require_once __DIR__ . '/header.php';
?>

<style>
/* Center Main Catalog Tabs */
.sp-main-tabs-wrap {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    text-align: center !important;
    width: 100% !important;
    margin-bottom: 24px !important;
}
.sp-main-tabs {
    margin-left: auto !important;
    margin-right: auto !important;
    justify-content: center !important;
}

/* AJAX Dynamic Loader Bar & Transitions */
#catalog-dynamic-wrap {
    transition: opacity 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== SchoPlus Style F v2 Luxury Pagination Bar ===== */
.sp-catalog-pagination-wrap {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    padding: 8px 16px;
    box-shadow: 0 2px 8px rgba(11, 42, 82, 0.04);
}
.sp-pagination-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.sp-pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #0B2A52;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.16s ease;
    user-select: none;
}
.sp-pg-btn:hover {
    background: #F4F8FC;
    border-color: #1E5FA8;
    color: #1E5FA8;
}
.sp-pg-btn.active {
    background: #F5A020 !important;
    border-color: #F5A020 !important;
    color: #0D1B2E !important;
    font-weight: 900;
    box-shadow: 0 2px 6px rgba(245, 160, 32, 0.35);
}
.sp-pg-btn.disabled {
    opacity: 0.45;
    pointer-events: none;
    background: #F8FAFC;
    border-color: #E2E8F0;
    color: #94A3B8;
}
.sp-pg-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 32px;
    color: #94A3B8;
    font-size: 0.75rem;
    font-weight: 700;
}
.sp-pagination-info {
    font-size: 0.73rem;
    color: #64748B;
    font-weight: 600;
}
.sp-ajax-progress {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #F5A020 0%, #1E5FA8 50%, #F5A020 100%);
    background-size: 200% 100%;
    animation: spAjaxLoaderBar 1s linear infinite;
    z-index: 50;
    display: none;
    border-radius: 3px;
}
@keyframes spAjaxLoaderBar {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ===== SCHOPLUS LUXURY CATALOG PRELOADER OVERLAY ===== */
.sp-catalog-preloader {
    position: absolute;
    inset: 0;
    z-index: 60;
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding-top: 130px;
    background: rgba(248, 250, 252, 0.82);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    border-radius: 16px;
    transition: opacity 0.25s ease;
}
.sp-preloader-card {
    background: #FFFFFF;
    border: 1.5px solid #DDE4ED;
    border-radius: 20px;
    padding: 24px 36px;
    text-align: center;
    box-shadow: 0 16px 40px rgba(11, 42, 82, 0.12);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 240px;
    animation: spPreloaderPop 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes spPreloaderPop {
    0% { transform: scale(0.92) translateY(-10px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
.sp-preloader-spinner {
    position: relative;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}
.sp-spinner-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 3.5px solid #E2E8F0;
    border-top-color: #F5A020;
    border-right-color: #1E5FA8;
    animation: spSpinRing 0.85s linear infinite;
}
@keyframes spSpinRing {
    to { transform: rotate(360deg); }
}
.sp-spinner-icon {
    font-size: 1.25rem;
    color: #0B2A52;
    animation: spIconPulse 1.4s ease-in-out infinite;
}
@keyframes spIconPulse {
    0%, 100% { transform: scale(1); opacity: 0.9; }
    50% { transform: scale(1.15); opacity: 1; color: #F5A020; }
}
.sp-preloader-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0B2A52;
}
.sp-preloader-sub {
    font-size: 0.72rem;
    color: #64748B;
    font-weight: 600;
}
.sp-preloader-bar {
    width: 140px;
    height: 3px;
    background: #E2E8F0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
}
.sp-preloader-bar-fill {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #F5A020, #1E5FA8);
    background-size: 200% 100%;
    animation: spAjaxLoaderBar 1.2s linear infinite;
}

/* ===== SCHOPLUS STYLE F v2 HERO & SEARCH BAR ===== */
.daleel-hero-v2 {
    background: #F0F4F8;
    padding: 22px 0 14px;
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
    margin-bottom: 8px;
}
.hero-v2-eyebrow-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #F5A020;
}
.hero-v2-title {
    font-weight: 800;
    font-size: clamp(1.3rem, 2.6vw, 1.8rem);
    color: #0B2A52;
    line-height: 1.35;
    margin-bottom: 6px;
    letter-spacing: -0.3px;
}
.hero-v2-title-sub {
    display: block;
    font-size: clamp(0.85rem, 1.7vw, 1.05rem);
    color: #1E5FA8;
    font-weight: 700;
    margin-top: 3px;
}
.hero-v2-desc {
    font-size: 0.76rem;
    color: #64748B;
    line-height: 1.6;
    margin-bottom: 12px;
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
    max-height: 350px;
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

.sp-btn-audit-hero {
    flex: 1;
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    color: #FFFFFF !important;
    border: 1.5px solid #1E5FA8;
    border-radius: 10px;
    padding: 11px 18px;
    font-size: 0.85rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(11, 42, 82, 0.2);
    transition: all 0.2s ease;
    text-decoration: none;
    white-space: nowrap;
}
.sp-btn-audit-hero:hover {
    background: linear-gradient(135deg, #163870 0%, #1E5FA8 100%);
    color: #FFFFFF !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(11, 42, 82, 0.32);
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
    .sp-search-card {
        padding: 14px;
        border-radius: 14px;
    }
}
@media (max-width: 575px) {
    .sp-search-action-wrap {
        flex-direction: column;
    }
    .sp-btn-search-hero,
    .sp-btn-audit-hero {
        width: 100%;
    }
}

/* ===== LUXURY JOIN DALEEL CTA BANNER (STYLE F v2) ===== */
.sp-join-section {
    padding: 6px 0 6px;
    margin-bottom: 2px;
}
.sp-join-section.sp-join-top {
    padding-top: 6px;
    padding-bottom: 6px;
    margin-bottom: 0;
}
.sp-join-card {
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    border-radius: 16px;
    padding: 18px 24px;
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
    padding: 3px 10px;
    border-radius: 999px;
    margin-bottom: 8px;
}
.sp-join-eyebrow i {
    color: #F5A020 !important;
}
.sp-join-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #FFFFFF !important;
    margin-bottom: 6px;
    line-height: 1.35;
}
.sp-join-desc {
    font-size: 0.76rem;
    color: #F1F5F9 !important;
    line-height: 1.6;
    margin-bottom: 14px;
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

/* ===== MODERN STATES SIDEBAR FILTER (STYLE F v2) ===== */
.sp-state-sidebar-wrap {
    position: sticky;
    top: 85px;
    z-index: 10;
}
.sp-state-sidebar-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(11, 42, 82, 0.04);
    overflow: hidden;
}
.sp-state-sidebar-header {
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    border-bottom: 2px solid #F5A020;
    padding: 12px 14px;
    color: #FFFFFF;
}
.sp-state-sidebar-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(245, 160, 32, 0.2);
    border: 1px solid rgba(245, 160, 32, 0.4);
    color: #F5A020;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}
.sp-state-sidebar-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: #FFFFFF;
    margin: 0;
    line-height: 1.2;
}
.sp-state-sidebar-subtitle {
    font-size: 0.66rem;
    color: rgba(255, 255, 255, 0.75);
}
.sp-state-reset-link {
    color: #F5A020;
    font-size: 0.68rem;
    font-weight: 700;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.12);
    padding: 3px 8px;
    border-radius: 6px;
    transition: all 0.15s ease;
}
.sp-state-reset-link:hover {
    background: #F5A020;
    color: #0B2A52;
}
.sp-state-sidebar-search {
    padding: 10px 12px 8px;
    background: #F8FAFC;
    border-bottom: 1px solid #EDF2F7;
}
.sp-state-sidebar-search input {
    border-radius: 6px;
    font-size: 0.74rem;
}
.sp-state-sidebar-regions {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    background: #F8FAFC;
    border-bottom: 1px solid #EDF2F7;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
}
.sp-state-sidebar-regions::-webkit-scrollbar {
    display: none;
}
.sp-sidebar-region-btn {
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #64748B;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sp-sidebar-region-btn:hover {
    border-color: #1E5FA8;
    color: #1E5FA8;
}
.sp-sidebar-region-btn.is-active {
    background: #0B2A52;
    border-color: #0B2A52;
    color: #FFFFFF;
}
.sp-state-sidebar-list {
    max-height: 440px;
    overflow-y: auto;
    padding: 4px 0;
}
.sp-state-sidebar-list::-webkit-scrollbar {
    width: 4px;
}
.sp-state-sidebar-list::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}
.sp-state-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    text-decoration: none;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 600;
    transition: all 0.15s ease;
    border-right: 3px solid transparent;
}
.sp-state-item:hover {
    background: #F0F6FF;
    color: #1E5FA8;
    border-right-color: #1E5FA8;
}
.sp-state-item.is-active {
    background: #EFF6FF;
    color: #0B2A52;
    font-weight: 800;
    border-right-color: #F5A020;
}
.sp-state-item-name {
    line-height: 1.3;
}
.sp-state-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #CBD5E1;
    display: inline-block;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.sp-state-item:hover .sp-state-dot {
    background: #1E5FA8;
    transform: scale(1.3);
}
.sp-state-item.is-active .sp-state-dot {
    background: #F5A020;
    transform: scale(1.4);
}
.sp-state-item-count {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    background: #F1F5F9;
    color: #64748B;
    border: 1px solid #E2E8F0;
    min-width: 22px;
    text-align: center;
}
.sp-state-item-count.has-count {
    background: #EFF6FF;
    color: #1E5FA8;
    border-color: #BFDBFE;
}
.sp-state-item.is-active .sp-state-item-count {
    background: #0B2A52;
    color: #FFFFFF;
    border-color: #0B2A52;
}
.sp-state-sidebar-footer {
    padding: 9px 12px;
    background: #F8FAFC;
    border-top: 1px solid #EDF2F7;
    font-size: 0.68rem;
    color: #64748B;
    display: flex;
    align-items: center;
}
.sp-mobile-filter-btn {
    border-color: #DDE4ED;
}
.sp-mobile-filter-btn:hover {
    background: #F8FAFC;
    border-color: #1E5FA8;
}

/* Active Filter Chips */
.sp-active-filter-chips {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 10px;
    padding: 8px 14px;
}
.sp-chip-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    color: #1E5FA8;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sp-chip-tag:hover {
    background: #DBEAFE;
    color: #0B2A52;
    border-color: #93C5FD;
}
.sp-chip-tag i {
    font-size: 0.65rem;
    opacity: 0.75;
}
.sp-chip-tag:hover i {
    opacity: 1;
    color: #DC2626;
}
.sp-chips-label {
    font-size: 0.72rem;
    color: #64748B;
    font-weight: 700;
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
<section class="daleel-hero-v2" id="search-section">
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
                        تصفح بيانات المدارس، الرسوم الدراسية، المناهج التعليمية، وتقييمات أولياء الأمور الحقيقية في منصة رقمية موحدة وشاملة.
                    </p>
                </div>

                <!-- LUXURY UNIFIED SEARCH CONSOLE CARD -->
                <form action="/" method="GET" class="sp-search-card" id="smartFilterForm">
                    <input type="hidden" name="type" id="filterTypeHidden" value="<?= e($schoolType) ?>">
                    
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
                                        <span class="sp-hero-dropdown-mini-lbl">الدولة</span>
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
                                        <span class="sp-hero-dropdown-mini-lbl"><?= $regionMiniLabel ?></span>
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
                                        <span class="sp-hero-dropdown-mini-lbl">المدينة / المحلية</span>
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
                                        <span class="sp-hero-dropdown-mini-lbl">المرحلة</span>
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
                        <a href="/presence-audit.php" class="sp-btn-audit-hero" id="heroAuditBtn" title="فحص الأثر والوجود الرقمي الشامل لهذه المدرسة">
                            <i class="fas fa-satellite-dish" style="color: #F5A020;"></i>
                            <span>فحص الوجود الرقمي</span>
                        </a>
                        <?php if ($isSearching): ?>
                            <a href="/" class="sp-btn-reset-hero" title="إلغاء الفلاتر">
                                <i class="fas fa-undo"></i>
                                <span>إعادة ضبط</span>
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>

            <!-- Left Column: Pure Family Photo Showcase (No Cards Around It) -->
            <div class="col-lg-5">
                <div class="hero-family-frame">
                    <picture>
                        <source srcset="/assets/images/family-hero.webp" type="image/webp">
                        <img src="/assets/images/family-hero.jpg" alt="جلسة أسرية لاختيار المدرسة في دليل المدارس الذكي" class="hero-family-img" loading="eager" width="900" height="672" fetchpriority="high">
                    </picture>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- SCHOOL OWNERS CTA BANNER (MOVED TO TOP WITH FULL DETAILS & MOBILE COLOR FIXES) -->
<section class="sp-join-section sp-join-top" id="join-daleel-section">
    <div class="container">
        <div class="sp-join-card" style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); border-radius: 16px; border: 1px solid #1E5FA8; border-right: 5px solid #F5A020; color: #FFFFFF; padding: 18px 24px;">
            <div class="row align-items-center g-3">
                <div class="col-lg-8 col-12">
                    <div class="sp-join-eyebrow" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 160, 32, 0.2); border: 1px solid #F5A020; color: #F5A020 !important; font-weight: 800; font-size: 0.72rem; padding: 3px 10px; border-radius: 20px; margin-bottom: 8px;">
                        <i class="fas fa-school" style="color: #F5A020 !important;"></i>
                        <span style="color: #FFFFFF !important;">انضم إلى دليل المدارس</span>
                    </div>
                    <h2 class="sp-join-title fw-bold mb-2" style="font-size: 1.25rem; color: #FFFFFF !important;">هل تدير مدرسة أو مجمعاً تعليمياً؟</h2>
                    <p class="sp-join-desc mb-3" style="max-width: 650px; font-size: 0.76rem; color: #F1F5F9 !important; line-height: 1.6; margin-bottom: 12px !important;">
                        خدمة رقمية مقدمة من منظومة SchoPlus الذكية. أضف بيانات مدرستك، أنشئ ملفاً تعريفياً متكاملاً، وانشر أخبار ومرافق مدرستك واستقبل استفسارات أولياء الأمور مباشرة.
                    </p>
                    <div class="sp-join-actions d-flex flex-wrap gap-2">
                        <a href="/register.php" class="sp-join-btn-primary" style="background: #F5A020 !important; color: #FFFFFF !important; font-weight: 800; font-size: 0.78rem; padding: 8px 18px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;">
                            <i class="fas fa-school" style="color: #FFFFFF !important;"></i>
                            <span style="color: #FFFFFF !important;">سجّل مدرستك الآن</span>
                        </a>
                        <a href="/login.php" class="sp-join-btn-secondary" style="background: rgba(255, 255, 255, 0.14) !important; color: #FFFFFF !important; border: 1.5px solid rgba(255, 255, 255, 0.45) !important; font-weight: 700; font-size: 0.78rem; padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;">
                            <i class="fas fa-sign-in-alt" style="color: #FFFFFF !important;"></i>
                            <span style="color: #FFFFFF !important;">دخول أصحاب المدارس</span>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 col-12 text-center">
                    <div class="sp-join-side-box" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 12px; padding: 14px 18px;">
                        <i class="fas fa-certificate fa-2x mb-2" style="color: #F5A020 !important;"></i>
                        <h5 class="sp-join-side-title fw-bold mb-1" style="font-size: 0.9rem; color: #FFFFFF !important;">ارتقِ بمدرستك مع SchoPlus</h5>
                        <p class="sp-join-side-desc mb-2" style="font-size: 0.71rem !important; line-height: 1.55; color: #E2E8F0 !important;">حوّل مدرستك إلى مدرسة ذكية مع أنظمة الحسابات، شؤون الطلاب، الجداول الآلية، وبوابة أولياء الأمور عبر الواتساب.</p>
                        <a href="https://schoplus.sd" target="_blank" class="sp-join-side-btn" style="background: #FFFFFF !important; color: #0B2A52 !important; font-weight: 800; font-size: 0.74rem; padding: 7px 14px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; text-decoration: none;">
                            <i class="fas fa-external-link-alt" style="color: #1E5FA8 !important;"></i>
                            <span style="color: #0B2A52 !important;">تعرف على نظام SchoPlus</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MAIN DIRECTORY CATALOG WITH MODERN STATES SIDEBAR FILTER -->
<section class="py-3" id="directory-catalog-section" style="position: relative; min-height: 420px;">
    <div class="sp-ajax-progress" id="spAjaxProgress"></div>

    <!-- PRELOADER OVERLAY FOR SCHOOLS & SEARCH RESULTS -->
    <div id="spCatalogPreloader" class="sp-catalog-preloader" style="display: none;">
        <div class="sp-preloader-card">
            <div class="sp-preloader-spinner">
                <div class="sp-spinner-ring"></div>
                <div class="sp-spinner-icon"><i class="fas fa-graduation-cap"></i></div>
            </div>
            <div class="sp-preloader-title">جاري تحميل وتحديث المدارس...</div>
            <div class="sp-preloader-sub">يرجى الانتظار، جاري جلب أحدث النتائج</div>
            <div class="sp-preloader-bar">
                <div class="sp-preloader-bar-fill"></div>
            </div>
        </div>
    </div>

    <div class="container" id="catalog-dynamic-wrap">
        <?php require __DIR__ . '/_catalog_content.php'; ?>
    </div>
</section>

<?php
$jsonAllGeo = json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE);
$extraScripts = <<<JS
<script>
    const allGeoMap = {$jsonAllGeo};

    // Sidebar State instant search filter
    window.filterSidebarStates = function(query) {
        query = (query || '').trim().toLowerCase();
        const items = document.querySelectorAll('#sidebarStatesList .sp-state-item[data-name]');
        let matches = 0;
        items.forEach(el => {
            const name = (el.dataset.name || '').toLowerCase();
            const match = !query || name.includes(query);
            el.style.display = match ? 'flex' : 'none';
            if (match) matches++;
        });
        const noMsg = document.getElementById('noMatchingStatesMsg');
        if (noMsg) {
            noMsg.classList.toggle('d-none', matches > 0 || !query);
        }
    };

    // Sidebar Regional Quick Filter
    window.filterSidebarRegion = function(regionKey, btn) {
        document.querySelectorAll('.sp-sidebar-region-btn').forEach(b => b.classList.remove('is-active'));
        if (btn) btn.classList.add('is-active');
        
        // Reset search box
        const sInput = document.getElementById('sidebarStateSearch');
        if (sInput) sInput.value = '';
        const noMsg = document.getElementById('noMatchingStatesMsg');
        if (noMsg) noMsg.classList.add('d-none');

        document.querySelectorAll('#sidebarStatesList .sp-state-item').forEach(el => {
            if (regionKey === 'all') {
                el.style.display = 'flex';
            } else {
                const r = el.dataset.region;
                el.style.display = (r === regionKey || el.getAttribute('href') === '/' || !r) ? 'flex' : 'none';
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
                miniLbl.textContent = rLabels.mini;
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

        // ========================================================
        // SCHOPLUS DALEEL — INSTANT AJAX SEARCH & FILTER CONTROLLER
        // ========================================================
        const catalogWrap = document.getElementById('catalog-dynamic-wrap');
        const progressBar = document.getElementById('spAjaxProgress');
        const catalogPreloader = document.getElementById('spCatalogPreloader');
        const smartFilterForm = document.getElementById('smartFilterForm');
        let isFetchingCatalog = false;
        let activeAbortCtrl = null;

        function setCatalogLoading(loading) {
            if (catalogWrap) {
                catalogWrap.style.transition = 'opacity 0.22s ease';
                catalogWrap.style.opacity = loading ? '0.3' : '1';
                catalogWrap.style.pointerEvents = loading ? 'none' : 'auto';
            }
            if (progressBar) {
                progressBar.style.display = loading ? 'block' : 'none';
            }
            if (catalogPreloader) {
                catalogPreloader.style.display = loading ? 'flex' : 'none';
            }
        }

        async function loadCatalogAjax(url, pushToHistory = true, shouldScroll = false) {
            if (isFetchingCatalog && activeAbortCtrl) {
                activeAbortCtrl.abort();
            }
            activeAbortCtrl = new AbortController();
            isFetchingCatalog = true;
            setCatalogLoading(true);

            try {
                const targetObj = new URL(url, window.location.origin);
                targetObj.searchParams.set('ajax', '1');

                const response = await fetch(targetObj.toString(), {
                    signal: activeAbortCtrl.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();

                if (data && typeof data.html === 'string' && catalogWrap) {
                    // Update catalog markup
                    catalogWrap.innerHTML = data.html;

                    // Update document title
                    if (data.title) {
                        document.title = data.title;
                    }

                    // Update browser address bar without reload
                    if (pushToHistory) {
                        const cleanUrl = new URL(url, window.location.origin);
                        cleanUrl.searchParams.delete('ajax');
                        window.history.pushState({ path: cleanUrl.toString() }, '', cleanUrl.toString());
                    }

                    // Smooth scroll to catalog section if user submitted hero form or requested
                    if (shouldScroll) {
                        const sec = document.getElementById('directory-catalog-section');
                        if (sec) {
                            const top = sec.getBoundingClientRect().top + window.pageYOffset - 80;
                            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                        }
                    }

                    // Close mobile filter collapse if open on small screens
                    const mobCollapse = document.getElementById('statesSidebarCollapse');
                    if (mobCollapse && window.innerWidth < 992) {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                            try {
                                const bsCol = bootstrap.Collapse.getOrCreateInstance(mobCollapse, { toggle: false });
                                if (bsCol) bsCol.hide();
                            } catch(e) {}
                        }
                        mobCollapse.classList.remove('show');
                    }

                    // Synchronize Hero form inputs/dropdowns
                    syncHeroForm(data);
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.warn('AJAX load failed, falling back to full navigation:', err);
                    window.location.href = url;
                }
            } finally {
                isFetchingCatalog = false;
                setCatalogLoading(false);
            }
        }

        // Synchronize Hero Form Dropdowns and Search Input
        function syncHeroForm(data) {
            if (!smartFilterForm) return;

            // 1. Search text input (only sync when input is not active/focused by user)
            const qInput = smartFilterForm.querySelector('input[name="q"]');
            if (qInput && typeof data.q !== 'undefined' && document.activeElement !== qInput) {
                qInput.value = data.q;
            }

            // 2. School type hidden input
            const typeHidden = smartFilterForm.querySelector('#filterTypeHidden');
            if (typeHidden && typeof data.school_type !== 'undefined') {
                typeHidden.value = data.school_type;
            }

            // 3. State select & trigger
            const stateSelect = document.getElementById('filterStateSelect');
            const stateTrigger = document.querySelector('#trigger_filterStateSelect .sp-hero-dropdown-selected-val');
            if (stateSelect && stateTrigger && typeof data.state !== 'undefined') {
                stateSelect.value = data.state;
                stateTrigger.textContent = data.state || 'جميع الولايات';
                const panel = document.getElementById('panel_filterStateSelect');
                if (panel) {
                    panel.querySelectorAll('.sp-hero-dropdown-option').forEach(opt => {
                        const isMatch = opt.dataset.value === (data.state || '');
                        opt.classList.toggle('is-selected', isMatch);
                        const chk = opt.querySelector('i.fa-check');
                        if (isMatch && !chk) {
                            const i = document.createElement('i');
                            i.className = 'fas fa-check text-primary';
                            i.style.fontSize = '0.7rem';
                            opt.appendChild(i);
                        } else if (!isMatch && chk) {
                            chk.remove();
                        }
                    });
                }
            }

            // 4. Stage select & trigger
            const stageSelect = document.getElementById('filterStageSelect');
            const stageTrigger = document.querySelector('#trigger_filterStageSelect .sp-hero-dropdown-selected-val');
            if (stageSelect && stageTrigger && typeof data.stage !== 'undefined') {
                stageSelect.value = data.stage;
                const activeOpt = stageSelect.options[stageSelect.selectedIndex];
                stageTrigger.textContent = activeOpt ? activeOpt.text : 'جميع المراحل';
                const panel = document.getElementById('panel_filterStageSelect');
                if (panel) {
                    panel.querySelectorAll('.sp-hero-dropdown-option').forEach(opt => {
                        const isMatch = opt.dataset.value === (data.stage || '');
                        opt.classList.toggle('is-selected', isMatch);
                        const chk = opt.querySelector('i.fa-check');
                        if (isMatch && !chk) {
                            const i = document.createElement('i');
                            i.className = 'fas fa-check text-primary';
                            i.style.fontSize = '0.7rem';
                            opt.appendChild(i);
                        } else if (!isMatch && chk) {
                            chk.remove();
                        }
                    });
                }
            }
        }

        // 1. Intercept Hero Form Submit
        if (smartFilterForm) {
            smartFilterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(smartFilterForm);
                const params = new URLSearchParams();
                for (const [k, v] of formData.entries()) {
                    const val = (v || '').trim();
                    if (val !== '') {
                        params.set(k, val);
                    }
                }
                const qs = params.toString();
                const targetUrl = qs ? '/?' + qs : '/';
                loadCatalogAjax(targetUrl, true, true);
            });

            // Instant live debounced search on typing
            const searchInput = smartFilterForm.querySelector('input[name="q"]');
            if (searchInput) {
                let debounceTimer = null;
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        const formData = new FormData(smartFilterForm);
                        const params = new URLSearchParams();
                        for (const [k, v] of formData.entries()) {
                            const val = (v || '').trim();
                            if (val !== '') params.set(k, val);
                        }
                        const qs = params.toString();
                        const targetUrl = qs ? '/?' + qs : '/';
                        loadCatalogAjax(targetUrl, true, false);
                    }, 380);
                });
            }

            // Auto-trigger filter on native select changes (with smooth auto-scroll to results)
            smartFilterForm.addEventListener('change', function(e) {
                if (e.target && e.target.tagName === 'SELECT') {
                    const formData = new FormData(smartFilterForm);
                    const params = new URLSearchParams();
                    for (const [k, v] of formData.entries()) {
                        const val = (v || '').trim();
                        if (val !== '') params.set(k, val);
                    }
                    const qs = params.toString();
                    const targetUrl = qs ? '/?' + qs : '/';
                    loadCatalogAjax(targetUrl, true, true);
                }
            });
        }

        // 2. Intercept Global Filter Links (Hero Reset, Search Clear Icon, Digital Presence Audit)
        document.addEventListener('click', function(e) {
            // Hero Presence Audit Button: Forward current search query, country, state & city directly
            const auditBtn = e.target.closest('.sp-btn-audit-hero');
            if (auditBtn) {
                e.preventDefault();
                let school = '';
                let country = '';
                let state = '';
                let city = '';
                if (smartFilterForm) {
                    const qInput = smartFilterForm.querySelector('input[name="q"]');
                    const countrySel = smartFilterForm.querySelector('#filterCountrySelect') || smartFilterForm.querySelector('select[name="country"]');
                    const stateSel = smartFilterForm.querySelector('#filterStateSelect') || smartFilterForm.querySelector('select[name="state"]');
                    const citySel = smartFilterForm.querySelector('#filterCitySelect') || smartFilterForm.querySelector('select[name="city"]');
                    if (qInput) school = qInput.value.trim();
                    if (countrySel) country = countrySel.value.trim();
                    if (stateSel) state = stateSel.value.trim();
                    if (citySel) city = citySel.value.trim();
                }
                const params = new URLSearchParams();
                if (school) {
                    params.set('school', school);
                    params.set('auto', '1');
                }
                if (country) params.set('country', country);
                if (state) params.set('state', state);
                if (city) params.set('city', city);
                const qs = params.toString();
                window.location.href = '/presence-audit.php' + (qs ? '?' + qs : '');
                return;
            }

            // Hero Form Reset button
            const resetBtn = e.target.closest('.sp-btn-reset-hero');
            if (resetBtn) {
                e.preventDefault();
                if (smartFilterForm) {
                    smartFilterForm.reset();
                    document.querySelectorAll('.sp-hero-dropdown').forEach(dropdown => {
                        const triggerVal = dropdown.querySelector('.sp-hero-dropdown-selected-val');
                        const targetId = dropdown.dataset.target;
                        if (triggerVal) {
                            if (targetId === 'filterCountrySelect') triggerVal.textContent = 'جميع الدول';
                            else if (targetId === 'filterStateSelect') triggerVal.textContent = 'جميع الولايات';
                            else if (targetId === 'filterCitySelect') triggerVal.textContent = 'جميع المدن';
                            else if (targetId === 'filterStageSelect') triggerVal.textContent = 'جميع المراحل';
                        }
                        const sel = document.getElementById(targetId);
                        if (sel) sel.value = '';
                    });
                }
                loadCatalogAjax('/', true, true);
                return;
            }

            // Hero Search Input Clear button (the x icon)
            const inputClearLink = e.target.closest('.sp-search-input-box a');
            if (inputClearLink) {
                e.preventDefault();
                const qInput = smartFilterForm ? smartFilterForm.querySelector('input[name="q"]') : null;
                if (qInput) qInput.value = '';
                const formData = new FormData(smartFilterForm);
                const params = new URLSearchParams();
                for (const [k, v] of formData.entries()) {
                    const val = (v || '').trim();
                    if (val !== '') params.set(k, val);
                }
                const qs = params.toString();
                const targetUrl = qs ? '/?' + qs : '/';
                loadCatalogAjax(targetUrl, true, false);
                return;
            }

            // 3. Intercept Links inside the Catalog (Main Tabs, Sidebar States, Chips, Reset, Results header)
            if (catalogWrap && catalogWrap.contains(e.target)) {
                const link = e.target.closest('a');
                if (!link) return;

                // Respect modified clicks (Ctrl+Click, Cmd+Click, middle click)
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;

                const href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:')) return;

                // Don't intercept school page links, telephone, email, whatsapp
                if (href.startsWith('/school/') || href.startsWith('tel:') || href.startsWith('mailto:') || href.includes('wa.me')) {
                    return;
                }

                // Intercept directory catalog navigation
                if (href === '/' || href.startsWith('/?') || href.startsWith('index.php') || href.startsWith('./')) {
                    e.preventDefault();
                    const isPgClick = link.classList.contains('sp-pg-btn');
                    const sec = document.getElementById('directory-catalog-section');
                    const shouldScrollCatalog = isPgClick || (sec && (window.pageYOffset < (sec.offsetTop - 120)));
                    loadCatalogAjax(href, true, shouldScrollCatalog);
                }
            }
        });

        // 4. Handle Browser Back & Forward Buttons (Popstate)
        window.addEventListener('popstate', function() {
            loadCatalogAjax(window.location.href, false, false);
        });
    });
</script>
JS;
require_once __DIR__ . '/footer.php';
