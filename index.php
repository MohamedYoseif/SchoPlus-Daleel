<?php
$pageTitle = 'الصفحة الرئيسية للدليل';
require_once __DIR__ . '/header.php';

$pdo = db();

// Filters Input
$q = trim($_GET['q'] ?? '');
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$schoolType = trim($_GET['school_type'] ?? '');
$stage = trim($_GET['stage'] ?? '');
$genderType = trim($_GET['gender_type'] ?? '');
$curriculum = trim($_GET['curriculum'] ?? '');
$schoplusOnly = !empty($_GET['schoplus_only']);
$sort = trim($_GET['sort'] ?? 'featured');

// Base query for approved schools
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

if ($schoolType !== '') {
    $where[] = "school_type = ?";
    $params[] = $schoolType;
}

if ($genderType !== '') {
    $where[] = "gender_type = ?";
    $params[] = $genderType;
}

if ($curriculum !== '') {
    $where[] = "curriculum = ?";
    $params[] = $curriculum;
}

if ($stage !== '') {
    $where[] = "stages LIKE ?";
    $params[] = "%\"{$stage}\"%";
}

if ($schoplusOnly) {
    $where[] = "is_schoplus_client = 1";
}

$whereClause = implode(' AND ', $where);

// Order by
$orderBy = "is_featured DESC, is_schoplus_client DESC, rating_avg DESC";
if ($sort === 'rating') $orderBy = "rating_avg DESC, reviews_count DESC";
if ($sort === 'reviews') $orderBy = "reviews_count DESC";
if ($sort === 'views') $orderBy = "views_count DESC";
if ($sort === 'latest') $orderBy = "created_at DESC";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM daleel_schools WHERE {$whereClause}");
$countStmt->execute($params);
$totalSchools = (int) $countStmt->fetchColumn();

// Fetch schools
$stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE {$whereClause} ORDER BY {$orderBy} LIMIT 30");
$stmt->execute($params);
$schools = $stmt->fetchAll();

// Top Rated Showcase
$topRated = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' ORDER BY rating_avg DESC, reviews_count DESC LIMIT 4")->fetchAll();

// SchoPlus Smart Schools Showcase
$schoplusSchools = $pdo->query("SELECT * FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1 ORDER BY is_featured DESC, rating_avg DESC LIMIT 6")->fetchAll();

// Statistics
$statTotal = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$statSchoplus = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND is_schoplus_client = 1")->fetchColumn();
$statStates = (int) $pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved'")->fetchColumn() ?: count(SudanGeography::states());
$statReviews = (int) $pdo->query("SELECT COUNT(*) FROM daleel_school_reviews WHERE is_approved = 1")->fetchColumn();

$statesWithCities = SudanGeography::statesWithCities();
$schoolTypes = SudanGeography::schoolTypes();
$schoolStages = SudanGeography::schoolStages();
$curriculums = SudanGeography::curriculums();
$genderTypes = SudanGeography::genderTypes();
?>

<!-- HERO SECTION -->
<section class="daleel-hero" style="background: linear-gradient(135deg, #FDFBF7 0%, #F5ECE0 100%); padding: 70px 0 60px; position: relative; overflow: hidden; border-bottom: 1px solid #EAE2D5;">
    <div class="container text-center">
        
        <div style="display: inline-flex; align-items: center; gap: 7px; background: #FFFFFF; border: 1px solid #E2D7C7; padding: 5px 14px; border-radius: 24px; font-size: 0.76rem; font-weight: 700; color: var(--brand-gold); box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 16px;">
            <i class="fas fa-sparkles text-warning"></i>
            <span>المنصة الأولى المعتمدة لدليل المدارس في السودان</span>
        </div>

        <h1 style="font-weight: 800; font-size: clamp(1.15rem, 2.5vw, 1.55rem); color: var(--brand-navy); line-height: 1.35; margin-bottom: 12px; letter-spacing: -0.3px;">
            اكتشف المدرسة الأنسب لمستقبل أبنائك<br>
            <span style="color: var(--brand-blue);">بكل ثقة وشفافية وسهولة</span>
        </h1>

        <p style="font-size: 0.88rem; color: #4A5568; line-height: 1.6; max-width: 620px; margin: 0 auto 26px;">
            الدليل التفاعلي الأكبر لاستكشاف ومقارنة المدارس بالولاية والمدينة والحي، مع إبراز المدارس الذكية المعتمدة وتجارب وتقييمات أولياء الأمور الحقيقية.
        </p>

        <!-- Fast Search Box inside Hero -->
        <form action="/" method="GET" style="background: #FFFFFF; border: 1px solid #E2D8C9; border-radius: 16px; padding: 10px; box-shadow: 0 8px 24px -5px rgba(50, 40, 20, 0.06); max-width: 780px; margin: 0 auto;">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-4">
                    <div class="d-flex align-items-center px-2">
                        <i class="fas fa-search text-muted me-2"></i>
                        <input type="text" name="q" value="<?= e($q) ?>" style="border: none; outline: none; padding: 9px 12px; font-size: 0.86rem; width: 100%; color: var(--text-main); font-weight: 600;" placeholder="ابحث باسم المدرسة، الحي، أو المنطقة...">
                    </div>
                </div>
                <div class="col-lg-3 col-md-4">
                    <select name="state" class="form-select border-0 bg-light" style="font-size: 0.84rem; padding: 8px 12px;" onchange="this.form.submit()">
                        <option value="">كل الولايات السودانية</option>
                        <?php foreach($statesWithCities as $stName => $cities): ?>
                            <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>>ولاية <?= e($stName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select name="stage" class="form-select border-0 bg-light" style="font-size: 0.84rem; padding: 8px 12px;" onchange="this.form.submit()">
                        <option value="">جميع المراحل</option>
                        <?php foreach($schoolStages as $key => $lbl): ?>
                            <option value="<?= e($key) ?>" <?= $stage === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="btn-luxury-primary w-100 justify-content-center py-2" style="font-size: 0.84rem;">
                        <i class="fas fa-search"></i>
                        <span>بحث سريع</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Hero Stats Counter -->
        <div style="margin-top: 35px; padding-top: 22px; border-top: 1px dashed #DFCFC0;">
            <div class="row justify-content-center g-3 text-center">
                <div class="col-lg-3 col-6">
                    <div data-countup="<?= $statTotal ?>" data-suffix="+" style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy);"><?= number_format($statTotal) ?>+</div>
                    <div style="font-size: 0.74rem; color: #6B7280; font-weight: 600;">مدرسة مسجلة بالدليل</div>
                </div>
                <div class="col-lg-3 col-6">
                    <div data-countup="<?= $statSchoplus ?>" style="font-size: 1.25rem; font-weight: 700; color: var(--brand-blue);"><?= number_format($statSchoplus) ?></div>
                    <div style="font-size: 0.74rem; color: #6B7280; font-weight: 600;">مدرسة ذكية بنظام SchoPlus</div>
                </div>
                <div class="col-lg-3 col-6">
                    <div data-countup="<?= $statStates ?>" style="font-size: 1.25rem; font-weight: 700; color: var(--brand-gold);"><?= $statStates ?></div>
                    <div style="font-size: 0.74rem; color: #6B7280; font-weight: 600;">ولاية مغطاة بالكامل</div>
                </div>
                <div class="col-lg-3 col-6">
                    <div data-countup="<?= $statReviews ?>" data-suffix="+" style="font-size: 1.25rem; font-weight: 700; color: var(--brand-emerald);"><?= number_format($statReviews) ?>+</div>
                    <div style="font-size: 0.74rem; color: #6B7280; font-weight: 600;">تقييم موثق من أولياء الأمور</div>
                </div>
            </div>
        </div>


    </div>
</section>

<!-- SECTION 1: SCHOPLUS SMART SCHOOLS -->
<?php if (!empty($schoplusSchools)): ?>
<section id="schoplus-schools" class="py-5" style="background-color: #F8F3EA;">
    <div class="container">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span style="color: var(--brand-gold); font-size: 0.74rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                    <i class="fas fa-award"></i> التميز والرقمنة
                </span>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">مدارس ذكية تعمل بنظام SchoPlus</h2>
                <p style="color: #6B7280; font-size: 0.82rem; margin-top: 4px;">
                    مدارس رائدة تتيح متابعة إلكترونية فورية لدرجات وحضور ابنك، إشعارات واتساب لحظية، وبوابة مخصصة لأولياء الأمور.
                </p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="/?schoplus_only=1#search-section" class="btn-luxury-outline btn-sm">
                    <span>عرض كافة مدارس SchoPlus</span>
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach($schoplusSchools as $s): ?>
                <?php
                    $logo = !empty($s['logo_path']) ? e($s['logo_path']) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                    $cover = !empty($s['cover_path']) ? e($s['cover_path']) : '';
                    $stagesArr = json_decode($s['stages'] ?? '[]', true) ?: [];
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="luxury-card" style="border: 1.5px solid #FCD34D; background: linear-gradient(135deg, #FFFDF9 0%, #FEF9EC 100%);">
                        
                        <div style="position: absolute; top: 0; right: 25px; background: linear-gradient(135deg, #1E5FA8 0%, #0B2A52 100%); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 0 0 8px 8px; z-index: 3;">
                            <i class="fas fa-bolt text-warning me-1"></i> مدرسة ذكية معتمدة
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
                            <a href="/school/<?= urlencode($s['slug']) ?>" style="font-weight: 700; font-size: 0.96rem; color: var(--brand-navy); margin-bottom: 5px; text-decoration: none;">
                                <?= e($s['name']) ?>
                            </a>

                            <div style="color: #64748B; font-size: 0.78rem; margin-bottom: 8px;">
                                <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= !empty($s['district']) ? '(' . e($s['district']) . ')' : '' ?>
                            </div>

                            <p class="school-card-desc" style="font-size: 0.8rem; color: #4B5563; margin-bottom: 10px;">
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
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="واتساب">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="/school/<?= urlencode($s['slug']) ?>" class="btn-luxury-primary btn-sm py-1 px-3">
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

<!-- SECTION 2: TOP RATED SCHOOLS -->
<?php if (!empty($topRated)): ?>
<section id="top-rated" class="py-5">
    <div class="container">
        
        <div class="text-center mb-4">
            <span style="color: var(--brand-gold); font-size: 0.74rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                <i class="fas fa-star"></i> ثقة أولياء الأمور
            </span>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">المدارس الأعلى تقييماً</h2>
            <p style="color: #6B7280; font-size: 0.82rem; margin-top: 4px;">
                مدارس حازت على أعلى التقييمات ورضا أولياء الأمور والطلاب لجودة التعليم والرعاية التربوية.
            </p>
        </div>

        <div class="row g-4">
            <?php foreach($topRated as $s): ?>
                <?php
                    $logo = !empty($s['logo_path']) ? e($s['logo_path']) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="luxury-card p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?= $logo ?>" alt="" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover;">
                            <div>
                                <a href="/school/<?= urlencode($s['slug']) ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 170px; font-size: 0.9rem;">
                                    <?= e($s['name']) ?>
                                </a>
                                <div class="small text-muted"><?= e($s['city']) ?></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center small mb-3">
                            <span class="badge bg-light text-secondary border"><?= e($schoolTypes[$s['school_type']] ?? $s['school_type']) ?></span>
                            <div class="star-rating">
                                <i class="fas fa-star text-warning"></i>
                                <span class="fw-bold"><?= number_format($s['rating_avg'], 1) ?></span>
                                <span class="text-muted">(<?= $s['reviews_count'] ?>)</span>
                            </div>
                        </div>

                        <a href="/school/<?= urlencode($s['slug']) ?>" class="btn-luxury-outline btn-sm w-100 justify-content-center">
                            عرض التفاصيل
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<!-- SECTION 3: ADVANCED FILTERS & ALL SCHOOLS DIRECTORY -->
<section id="search-section" class="py-5" style="background-color: var(--bg-surface-tint); border-top: 1px solid #EAE2D5;">
    <div class="container">
        
        <div class="mb-4">
            <span style="color: var(--brand-gold); font-size: 0.74rem; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;">
                <i class="fas fa-sliders-h"></i> التصفية الذكية
            </span>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin: 0;">استكشف جميع المدارس</h2>
            <p style="color: #6B7280; font-size: 0.82rem; margin-top: 4px;">
                استخدم الفلاتر الجغرافية الدقيقة لاختيار المدرسة الملائمة حسب مدينتك، نوع التعليم، والمراحل الدراسية.
            </p>
        </div>

        <!-- FILTER FORM -->
        <form action="/#search-section" method="GET" class="luxury-card p-4 mb-4">
            <div class="row g-3">
                
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small fw-bold text-dark">اسم المدرسة أو الحي</label>
                    <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="ابحث بالاسم، الحي...">
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">الولاية</label>
                    <select name="state" class="form-select" id="filterStateSelect">
                        <option value="">جميع الولايات</option>
                        <?php foreach($statesWithCities as $stName => $cities): ?>
                            <option value="<?= e($stName) ?>" <?= $state === $stName ? 'selected' : '' ?>>ولاية <?= e($stName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">المدينة / المحلية</label>
                    <select name="city" class="form-select" id="filterCitySelect">
                        <option value="">جميع المدن</option>
                        <?php if ($state && isset($statesWithCities[$state])): ?>
                            <?php foreach($statesWithCities[$state] as $c): ?>
                                <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">نوع المدرسة</label>
                    <select name="school_type" class="form-select">
                        <option value="">كافة الأنواع</option>
                        <?php foreach($schoolTypes as $tKey => $tLbl): ?>
                            <option value="<?= e($tKey) ?>" <?= $schoolType === $tKey ? 'selected' : '' ?>><?= e($tLbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">المرحلة الدراسية</label>
                    <select name="stage" class="form-select">
                        <option value="">كافة المراحل</option>
                        <?php foreach($schoolStages as $sKey => $sLbl): ?>
                            <option value="<?= e($sKey) ?>" <?= $stage === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">جنس الطلاب</label>
                    <select name="gender_type" class="form-select">
                        <option value="">الكل (بنين / بنات / مختلط)</option>
                        <?php foreach($genderTypes as $gKey => $gLbl): ?>
                            <option value="<?= e($gKey) ?>" <?= $genderType === $gKey ? 'selected' : '' ?>><?= e($gLbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">المنهج المتبع</label>
                    <select name="curriculum" class="form-select">
                        <option value="">كافة المناهج</option>
                        <?php foreach($curriculums as $cKey => $cLbl): ?>
                            <option value="<?= e($cKey) ?>" <?= $curriculum === $cKey ? 'selected' : '' ?>><?= e($cLbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">ترتيب النتائج</label>
                    <select name="sort" class="form-select">
                        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>المميزة ومدارس SchoPlus أولاً</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>الأعلى تقييماً</option>
                        <option value="reviews" <?= $sort === 'reviews' ? 'selected' : '' ?>>الأكثر مراجعات</option>
                        <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>الأكثر مشاهدة</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>أحدث المدارس</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6 d-flex align-items-center">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="schoplus_only" value="1" id="schoplusOnlyCheck" <?= $schoplusOnly ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark small" for="schoplusOnlyCheck">
                            <i class="fas fa-bolt text-warning me-1"></i> مدارس SchoPlus الذكية فقط
                        </label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center pt-3 border-top">
                    <span class="small text-muted">تم العثور على <strong><?= $totalSchools ?></strong> مدرسة مطابقة</span>
                    <div class="d-flex gap-2">
                        <a href="/#search-section" class="btn btn-sm btn-light border text-muted">إعادة ضبط</a>
                        <button type="submit" class="btn-luxury-primary btn-sm"><i class="fas fa-filter me-1"></i> تطبيق الفلاتر</button>
                    </div>
                </div>

            </div>
        </form>

        <!-- SCHOOLS GRID -->
        <div class="row g-4">
            <?php if (empty($schools)): ?>
                <div class="col-12 py-5 text-center">
                    <div class="p-5 bg-white rounded-4 border text-center shadow-sm max-w-md mx-auto">
                        <i class="fas fa-school fa-3x text-muted mb-3 opacity-50"></i>
                        <h4 class="fw-bold text-dark">لا توجد مدارس مطابقة للبحث حالياً</h4>
                        <p class="text-muted small">جرب تغيير شروط الفلترة أو اختيار ولاية ومدينة أخرى.</p>
                        <a href="/#search-section" class="btn-luxury-outline btn-sm mt-2">إعادة ضبط الفلاتر</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($schools as $s): ?>
                    <?php
                        $logo = !empty($s['logo_path']) ? e($s['logo_path']) : "https://ui-avatars.com/api/?name=" . urlencode(mb_substr($s['name'], 0, 1, 'UTF-8')) . "&background=1E5FA8&color=ffffff&size=160&bold=true";
                        $cover = !empty($s['cover_path']) ? e($s['cover_path']) : '';
                        $stagesArr = json_decode($s['stages'] ?? '[]', true) ?: [];
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="luxury-card h-100 d-flex flex-column">
                            
                            <div style="height: 140px; background-size: cover; background-position: center; position: relative; <?= $cover ? "background-image: url('{$cover}');" : '' ?>" class="<?= !$cover ? 'cover-placeholder' : '' ?>">
                                <div style="position: absolute; inset: 0; background: <?= $cover ? 'linear-gradient(180deg, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0.52) 100%)' : 'linear-gradient(180deg, rgba(11,42,82,0.0) 0%, rgba(11,42,82,0.35) 100%)' ?>;"></div>
                                <?php if (!$cover): ?><div class="cover-placeholder-icon"><i class="fas fa-school"></i></div><?php endif; ?>

                                <div style="position: absolute; top: 12px; left: 12px; z-index: 2; display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                                    <?php if ($s['is_schoplus_client']): ?>
                                        <span class="badge-schoplus-smart"><i class="fas fa-bolt"></i> SchoPlus الذكي</span>
                                    <?php endif; ?>
                                    <span class="badge-verified"><i class="fas fa-check-circle"></i> موثقة</span>
                                </div>

                                <div style="width: 68px; height: 68px; border-radius: 16px; background: #fff; padding: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); position: absolute; bottom: -24px; right: 20px; z-index: 2;">
                                    <img src="<?= $logo ?>" alt="" loading="lazy" style="width: 100%; height: 100%; border-radius: 12px; object-fit: cover;">
                                </div>
                            </div>

                            <div style="padding: 32px 18px 18px; display: flex; flex-direction: column; flex-grow: 1;">
                                <a href="/school/<?= urlencode($s['slug']) ?>" style="font-weight: 700; font-size: 0.96rem; color: var(--brand-navy); margin-bottom: 5px; text-decoration: none;">
                                    <?= e($s['name']) ?>
                                </a>

                                <div style="color: #64748B; font-size: 0.78rem; margin-bottom: 8px;">
                                    <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                    ولاية <?= e($s['state']) ?> — <?= e($s['city']) ?> <?= !empty($s['district']) ? '(' . e($s['district']) . ')' : '' ?>
                                </div>

                                <p class="school-card-desc" style="font-size: 0.8rem; color: #4B5563; margin-bottom: 10px;">
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
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="واتساب">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="/school/<?= urlencode($s['slug']) ?>" class="btn-luxury-outline btn-sm py-1 px-3">
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

<!-- SECTION 4: SCHOOL OWNERS CTA -->
<section class="py-5" style="background: linear-gradient(135deg, #0B2A52 0%, #16467D 100%); color: #FFFFFF;">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill mb-3" style="font-size: 0.76rem;">
                    <i class="fas fa-gift me-1"></i> تسجيل مجاني بالكامل
                </span>
                <h2 class="fw-bold mb-2" style="font-size: 1.35rem;">هل تدير مدرسة أو مجمعاً تعليمياً؟</h2>
                <p class="mb-4" style="max-width: 650px; font-size: 0.88rem; opacity: 0.9; line-height: 1.65;">
                    انضم إلى مئات المدارس في الدليل الأكبر في السودان. احصل على بروفايل إلكتروني موثق، انشر أخبار مدرستك، واستقبل طلبات التسجيل واستفسارات أولياء الأمور بسهولة تامة.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/register.php" class="btn-luxury-gold px-3 py-2" style="font-size: 0.86rem;">
                        <i class="fas fa-school"></i>
                        <span>سجّل مدرستك الآن مجاناً</span>
                    </a>
                    <a href="/login.php" class="btn btn-outline-light px-3 py-2 rounded-3 fw-bold" style="font-size: 0.86rem;">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <div class="p-4 rounded-4" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-certificate fa-4x text-warning mb-3"></i>
                    <h5 class="fw-bold">ارتقِ بمدرستك مع SchoPlus</h5>
                    <p class="small opacity-80 mb-3">حوّل مدرستك إلى مدرسة ذكية مع أنظمة الحسابات، شؤون الطلاب، الجداول الآلية، وبوابة أولياء الأمور عبر الواتساب.</p>
                    <a href="https://schoplus.sd" target="_blank" class="btn btn-sm btn-light text-primary fw-bold w-100">
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
            citySelect.innerHTML = '<option value="">جميع المدن</option>';
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

