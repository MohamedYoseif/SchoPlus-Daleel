<?php
/**
 * School Side-by-Side Comparison (SchoPlus Style F v2)
 */

$pageTitle = 'مقارنة المدارس';
require_once __DIR__ . '/header.php';

$pdo = db();

// Parse requested IDs from URL
$rawIds = trim($_GET['ids'] ?? '');
$requestedIds = [];
if ($rawIds !== '') {
    $requestedIds = array_filter(array_map('intval', explode(',', $rawIds)), fn($id) => $id > 0);
    $requestedIds = array_slice(array_unique($requestedIds), 0, 4);
}

// Fetch schools
$schools = [];
if (!empty($requestedIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($requestedIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id IN ({$inPlaceholders}) AND status = 'approved'");
    $stmt->execute($requestedIds);
    $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Keep the exact order of requested IDs
    $indexed = [];
    foreach ($fetched as $sch) {
        $indexed[(int)$sch['id']] = $sch;
    }
    foreach ($requestedIds as $rid) {
        if (isset($indexed[$rid])) {
            $schools[] = $indexed[$rid];
        }
    }
}

// Facilities dictionary
$allFacilities = SudanGeography::availableFacilities();
$schoolStages = SudanGeography::schoolStages();
$schoolTypes = SudanGeography::schoolTypes();
$curriculums = SudanGeography::curriculums();
$genderTypes = SudanGeography::genderTypes();
?>

<style>
/* ===== COMPARE PAGE STYLES (SchoPlus Style F v2) ===== */
.compare-page-header {
    background: #F0F4F8;
    border-bottom: 1px solid #DDE4ED;
    padding: 32px 0 24px;
    margin-bottom: 28px;
}
.compare-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--brand-navy, #0B2A52);
    margin-bottom: 4px;
}
.compare-subtitle {
    font-size: 0.78rem;
    color: #475569;
    margin: 0;
}
.compare-table-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(11, 42, 82, 0.05);
    overflow: hidden;
    margin-bottom: 40px;
}
.compare-table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.compare-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
}
.compare-table th,
.compare-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #EDF2F7;
    border-left: 1px solid #EDF2F7;
    font-size: 0.78rem;
    vertical-align: middle;
}
.compare-table tr:last-child th,
.compare-table tr:last-child td {
    border-bottom: none;
}
.compare-table th:last-child,
.compare-table td:last-child {
    border-left: none;
}
.compare-criterion-col {
    width: 220px;
    min-width: 200px;
    background: #F8FAFC;
    font-weight: 700;
    color: var(--brand-navy, #0B2A52);
    position: sticky;
    right: 0;
    z-index: 2;
    border-left: 2px solid #DDE4ED !important;
}
.compare-section-header {
    background: #0B2A52 !important;
    color: #FFFFFF !important;
    font-weight: 800;
    font-size: 0.8rem;
    padding: 10px 16px !important;
    border: none !important;
}
.compare-school-col {
    text-align: center;
    min-width: 200px;
    background: #FFFFFF;
}
.compare-school-header-box {
    padding: 16px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}
.compare-school-logo {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    border: 1.5px solid #DDE4ED;
    background: #FFFFFF;
    padding: 3px;
    object-fit: cover;
    margin-bottom: 10px;
}
.compare-school-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--brand-navy, #0B2A52);
    text-decoration: none;
    margin-bottom: 4px;
    line-height: 1.35;
}
.compare-school-name:hover {
    color: var(--brand-blue, #1E5FA8);
}
.compare-school-loc {
    font-size: 0.7rem;
    color: #64748B;
    margin-bottom: 8px;
}
.compare-remove-btn {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    color: #64748B;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.compare-remove-btn:hover {
    background: #FFE4E6;
    border-color: #FDA4AF;
    color: #E11D48;
}
.compare-badge-pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    margin: 2px;
    background: #EFF6FF;
    color: var(--brand-blue, #1E5FA8);
    border: 1px solid #BFDBFE;
}
.compare-facility-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.74rem;
}
.compare-facility-yes {
    color: #059669;
    font-weight: 700;
}
.compare-facility-no {
    color: #94A3B8;
}
.compare-empty-state {
    padding: 60px 20px;
    text-align: center;
    max-width: 520px;
    margin: 0 auto;
}

/* Print Friendly Styles */
@media print {
    .daleel-topbar,
    .daleel-navbar,
    .daleel-footer,
    .sp-floating-compare-bar,
    .compare-actions-bar,
    .compare-remove-btn,
    .compare-action-btns {
        display: none !important;
    }
    .compare-page-header {
        padding: 10px 0;
        background: none;
        border: none;
    }
    .compare-table-card {
        border: 1px solid #000;
        box-shadow: none;
    }
    .compare-criterion-col {
        position: static !important;
        background: #F8FAFC !important;
    }
}
</style>

<div class="compare-page">

    <!-- Header Section -->
    <div class="compare-page-header">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h1 class="compare-title">
                        <i class="fas fa-balance-scale text-warning me-2"></i>
                        مقارنة المدارس جنباً إلى جنب
                    </h1>
                    <p class="compare-subtitle">
                        مقارنة دقيقة وشاملة للمناهج، الرسوم، التقييم، والمرافق بين المدارس المحددة لمساعدتك في اتخاذ القرار الأمثل.
                    </p>
                </div>

                <?php if (count($schools) >= 2): ?>
                <div class="d-flex align-items-center gap-2 compare-actions-bar">
                    <button type="button" class="btn-luxury-outline btn-sm" onclick="window.print()" title="طباعة تقرير المقارنة">
                        <i class="fas fa-print me-1"></i> طباعة التقرير
                    </button>
                    <a href="/" class="btn-luxury-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> إضافة مدرسة أخرى
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container mb-5">

        <?php if (count($schools) < 2): ?>
            <!-- EMPTY / INSUFFICIENT STATE -->
            <div class="card border-0 rounded-4 shadow-sm bg-white compare-empty-state">
                <div class="mb-3" style="font-size: 3rem; color: #F5A020;">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <h4 class="fw-bold mb-2" style="font-size: 1.15rem; color: #0B2A52;">
                    <?= count($schools) === 1 ? 'تم اختيار مدرسة واحدة فقط' : 'لم تقم باختيار أي مدارس للمقارنة بعد' ?>
                </h4>
                <p class="text-muted small mb-4" style="line-height: 1.7; font-size: 0.78rem;">
                    <?= count($schools) === 1
                        ? 'يرجى اختيار مدرسة ثانية على الأقل لبدء المقارنة جنباً إلى جنب. يمكنك تصفح دليل المدارس والنقر على زر "مقارنة" على أي بطاقة.'
                        : 'تصفح قائمة المدارس في الدليل واضغط على زر "مقارنة" على بطاقات المدارس التي ترغب في المقارنة بينها (حتى 4 مدارس).' ?>
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="/" class="btn-luxury-primary px-4 py-2">
                        <i class="fas fa-search me-1"></i> استكشف المدارس بالدليل
                    </a>
                    <?php if (count($schools) > 0): ?>
                        <button type="button" class="btn btn-outline-secondary px-3 py-2 rounded-3" onclick="clearCompare(); window.location.href='/';">
                            مسح القائمة
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Auto-redirect if localStorage has schools that aren't in URL yet -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var stored = window.getCompareSchools ? window.getCompareSchools() : [];
                if (stored.length >= 2 && (!window.location.search || window.location.search.indexOf('ids=') === -1)) {
                    var ids = stored.map(function(s) { return s.id; }).join(',');
                    window.location.href = '/compare.php?ids=' + encodeURIComponent(ids);
                }
            });
            </script>

        <?php else: ?>

            <!-- COMPARISON TABLE -->
            <div class="compare-table-card">
                <div class="compare-table-responsive">
                    <table class="compare-table">
                        <thead>
                            <tr>
                                <th class="compare-criterion-col" style="vertical-align: top; padding-top: 24px;">
                                    <div class="small text-muted mb-1">عدد المدارس المقارنة:</div>
                                    <div class="fs-6 fw-bold text-dark"><?= count($schools) ?> مدارس من 4</div>
                                </th>
                                <?php foreach ($schools as $s): ?>
                                    <?php
                                        $logo = defaultSchoolLogo($s['logo_path'] ?? null);
                                        $schoolUrl = '/school/' . urlencode($s['slug']);
                                    ?>
                                    <th class="compare-school-col">
                                        <div class="compare-school-header-box">
                                            <button type="button" class="compare-remove-btn" onclick="removeCompareSchool('<?= $s['id'] ?>'); removeSchoolFromUrl('<?= $s['id'] ?>');" title="إزالة هذه المدرسة من المقارنة">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <img src="<?= e($logo) ?>" alt="<?= e($s['name']) ?>" class="compare-school-logo">
                                            <a href="<?= $schoolUrl ?>" target="_blank" class="compare-school-name">
                                                <?= e($s['name']) ?>
                                            </a>
                                            <div class="compare-school-loc">
                                                <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                <?= e($s['country'] ?? 'السودان') ?> — <?= e($s['state']) ?>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center gap-1 mb-2">
                                                <i class="fas fa-star text-warning small"></i>
                                                <span class="fw-bold small"><?= number_format($s['rating_avg'], 1) ?></span>
                                                <span class="text-muted small">(<?= $s['reviews_count'] ?>)</span>
                                            </div>
                                            <a href="<?= $schoolUrl ?>" target="_blank" class="btn-luxury-outline btn-sm py-1 px-3 w-100" style="font-size: 0.72rem;">
                                                عرض الملف التعريفي
                                            </a>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>

                            <!-- SECTION 1: IDENTITY & RANKS -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-id-card me-1 text-warning"></i> 1. الهوية والترتيب التنافسي
                                </th>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">الترتيب العام في الدليل</th>
                                <?php foreach ($schools as $s): ?>
                                    <?php $sRank = getSchoolDirectoryRank($pdo, (int)$s['id'], !empty($s['directory_rank']) ? (int)$s['directory_rank'] : null); ?>
                                    <td class="compare-school-col">
                                        <span class="badge-directory-rank">#<?= $sRank ?> بالدليل</span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">الترتيب على مستوى الولاية</th>
                                <?php foreach ($schools as $s): ?>
                                    <?php $sStateRank = getSchoolStateRank($pdo, (int)$s['id'], (string)($s['state'] ?? ''), !empty($s['state_rank']) ? (int)$s['state_rank'] : null, (string)($s['country'] ?? 'السودان')); ?>
                                    <td class="compare-school-col">
                                        <span class="badge-state-rank-tag">#<?= $sStateRank ?> في ولاية <?= e($s['state']) ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">سنة التأسيس</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col fw-bold">
                                        <?= !empty($s['established_year']) ? e($s['established_year']) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- SECTION 2: ACADEMICS -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-graduation-cap me-1 text-warning"></i> 2. المناهج والمراحل التعليمية
                                </th>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">المنهج الدراسي</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col fw-bold text-dark">
                                        <?= SudanGeography::curriculumLabel($s['curriculum'] ?? '') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">المراحل الدراسية المتوفرة</th>
                                <?php foreach ($schools as $s): ?>
                                    <?php $stArr = json_decode($s['stages'] ?? '[]', true) ?: []; ?>
                                    <td class="compare-school-col">
                                        <?php if (!empty($stArr)): ?>
                                            <?php foreach ($stArr as $stKey): ?>
                                                <span class="compare-badge-pill"><?= SudanGeography::stageLabel($stKey) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">نوع المدرسة</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?= SudanGeography::schoolTypeLabel($s['school_type'] ?? '') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">نوع الطلاب</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?= SudanGeography::genderTypeLabel($s['gender_type'] ?? '') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- SECTION 3: FINANCES & FEES -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-coins me-1 text-warning"></i> 3. المصروفات والرسوم الدراسية
                                </th>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">متوسط الرسوم</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?php if (!empty($s['approx_fees'])): ?>
                                            <span class="fw-bold" style="color: var(--brand-navy); font-size: 0.84rem;"><?= e($s['approx_fees']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">تحدد مع الإدارة</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">نظام الرسوم</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?= ($s['fees_type'] ?? '') === 'detailed' ? 'مفصلة حسب المراحل' : 'رسوم عامة تقريبية' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">عروض وخصومات المصروفات</th>
                                <?php foreach ($schools as $s): ?>
                                    <?php $dsc = json_decode($s['fee_discounts'] ?? '[]', true) ?: []; ?>
                                    <td class="compare-school-col">
                                        <?php if (!empty($dsc)): ?>
                                            <span class="badge" style="background: #FAF5FF; color: #7E22CE; border: 1px solid #E9D5FF; font-size: 0.7rem; font-weight: 700;">
                                                <i class="fas fa-tag me-1"></i> تتوفر خصومات (<?= count($dsc) ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- SECTION 4: DIGITAL SYSTEM -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-microchip me-1 text-warning"></i> 4. التحول الرقمي ومنظومة SchoPlus
                                </th>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">مدرسة ذكية بنظام SchoPlus</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?php if (!empty($s['is_schoplus_client'])): ?>
                                            <span class="badge-schoplus-smart py-1 px-2 d-inline-flex align-items-center gap-1">
                                                <i class="fas fa-bolt text-warning"></i> نعم، شريك معتمد
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">غير مفعل</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- SECTION 5: FACILITIES -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-cubes me-1 text-warning"></i> 5. المرافق والخدمات المتاحة
                                </th>
                            </tr>
                            <?php foreach ($allFacilities as $fKey => $fMeta): ?>
                                <tr>
                                    <th class="compare-criterion-col">
                                        <i class="<?= e($fMeta['icon']) ?> me-1 text-secondary" style="width: 18px;"></i>
                                        <?= e($fMeta['label']) ?>
                                    </th>
                                    <?php foreach ($schools as $s): ?>
                                        <?php
                                            $sFacs = json_decode($s['facilities'] ?? '[]', true) ?: [];
                                            $hasFac = in_array($fKey, $sFacs, true);
                                        ?>
                                        <td class="compare-school-col">
                                            <?php if ($hasFac): ?>
                                                <span class="compare-facility-item compare-facility-yes">
                                                    <i class="fas fa-check-circle"></i> متوفر
                                                </span>
                                            <?php else: ?>
                                                <span class="compare-facility-item compare-facility-no">
                                                    <i class="fas fa-minus"></i> -
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                            <!-- SECTION 6: LOCATION & CONTACT -->
                            <tr>
                                <th colspan="<?= count($schools) + 1 ?>" class="compare-section-header">
                                    <i class="fas fa-map-marked-alt me-1 text-warning"></i> 6. الموقع والتواصل المباشر
                                </th>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">الدولة والولاية والمدينة</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <div class="fw-bold"><?= e($s['country'] ?? 'السودان') ?> — <?= e($s['state']) ?></div>
                                        <small class="text-muted"><?= e($s['city']) ?><?= !empty($s['district']) ? ' (' . e($s['district']) . ')' : '' ?></small>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">العنوان الفعلي والخريطة</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <?php if (!empty($s['address'])): ?>
                                            <?php if (!empty($s['google_map_url'])): ?>
                                                <div class="small mb-1">
                                                    <a href="<?= e($s['google_map_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none text-dark fw-bold hover-primary" title="انقر لفتح العنوان في خرائط Google">
                                                        <i class="fas fa-map-pin text-danger me-1"></i><?= e($s['address']) ?>
                                                        <i class="fas fa-external-link-alt text-muted ms-1" style="font-size: 0.65rem;"></i>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="small mb-1"><?= e($s['address']) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($s['google_map_url'])): ?>
                                            <a href="<?= e($s['google_map_url']) ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-2" style="font-size: 0.68rem;">
                                                <i class="fas fa-map-marker-alt text-danger"></i> فتح الخريطة
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th class="compare-criterion-col">التواصل الفوري</th>
                                <?php foreach ($schools as $s): ?>
                                    <td class="compare-school-col">
                                        <div class="d-flex justify-content-center align-items-center gap-1 flex-wrap compare-action-btns">
                                            <?php if (!empty($s['whatsapp'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>?text=<?= urlencode('مرحباً بكم، أود الاستفسار عن التسجيل في مدرستكم بعد الاطلاع عليها في دليل SchoPlus.') ?>" target="_blank" class="btn btn-sm btn-outline-success py-1 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                                    <i class="fab fa-whatsapp"></i> واتساب
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($s['phone'])): ?>
                                                <a href="tel:<?= e($s['phone']) ?>" class="btn btn-sm btn-light border py-1 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                                    <i class="fas fa-phone-alt text-primary"></i> اتصال
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Page Script to sync URL when removed -->
            <script>
            function removeSchoolFromUrl(removeId) {
                var urlParams = new URLSearchParams(window.location.search);
                var ids = (urlParams.get('ids') || '').split(',').map(function(s) { return s.trim(); });
                var filtered = ids.filter(function(id) { return id && id !== String(removeId); });
                if (filtered.length >= 2) {
                    window.location.href = '/compare.php?ids=' + encodeURIComponent(filtered.join(','));
                } else if (filtered.length === 1) {
                    window.location.href = '/compare.php?ids=' + encodeURIComponent(filtered[0]);
                } else {
                    window.location.href = '/compare.php';
                }
            }
            </script>

        <?php endif; ?>

    </div>

</div>

<?php
require_once __DIR__ . '/footer.php';
