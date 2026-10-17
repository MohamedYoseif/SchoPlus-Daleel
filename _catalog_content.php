<?php
/**
 * SchoPlus Daleel — Dynamic Catalog Partial
 * Used for both initial server-side render and instant AJAX updates
 */
?>
<!-- Mandatory Specification for Main Tabs (.sp-main-tabs) -->
<div class="sp-main-tabs-wrap d-flex justify-content-center text-center">
    <div class="sp-main-tabs mx-auto">
        <a href="<?= buildFilterUrl(['type' => null, 'view' => null, 'sort' => null, 'schoplus_only' => null]) ?>" class="sp-main-tab <?= empty($schoolType) && (empty($view) || $view === 'all') && !$schoplusOnly && $sort !== 'rating' && $sort !== 'latest' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> كافة المدارس
            <span class="tab-badge"><?= number_format($tabCountTotal) ?></span>
        </a>
        <a href="<?= buildFilterUrl(['type' => 'government', 'view' => null]) ?>" class="sp-main-tab <?= $schoolType === 'government' ? 'active' : '' ?>">
            <i class="fas fa-landmark"></i> مدارس حكومية
            <span class="tab-badge"><?= number_format($tabCountGov) ?></span>
        </a>
        <a href="<?= buildFilterUrl(['type' => 'private', 'view' => null]) ?>" class="sp-main-tab <?= $schoolType === 'private' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i> مدارس خاصة ودولية
            <span class="tab-badge"><?= number_format($tabCountPriv) ?></span>
        </a>
        <a href="<?= buildFilterUrl(['view' => 'historic', 'type' => null, 'sort' => null]) ?>" class="sp-main-tab <?= $view === 'historic' ? 'active' : '' ?>">
            <i class="fas fa-landmark text-warning"></i> مدارس وصروح عريقة
            <span class="tab-badge" style="<?= ($tabCountHistoric ?? 0) > 0 ? 'background:#FEF3C7;color:#92400E;font-weight:900;' : '' ?>"><?= number_format($tabCountHistoric ?? 0) ?></span>
        </a>
        <a href="<?= buildFilterUrl(['view' => 'schoplus', 'type' => null, 'sort' => null]) ?>" class="sp-main-tab <?= $view === 'schoplus' || $schoplusOnly ? 'active' : '' ?>">
            <i class="fas fa-bolt text-warning"></i> مدارس SchoPlus الذكية
            <span class="tab-badge"><?= number_format($tabCountSchoplus) ?></span>
        </a>
        <a href="<?= buildFilterUrl(['view' => 'top_rated', 'sort' => 'rating', 'type' => null]) ?>" class="sp-main-tab <?= $view === 'top_rated' || $sort === 'rating' ? 'active' : '' ?>">
            <i class="fas fa-star text-warning"></i> الأعلى تقييماً
            <span class="tab-badge"><?= number_format($totalTopRatedCount) ?></span>
        </a>
    </div>
</div>

<!-- Top Quick Filter Toolbar (Instant State & Type Navigation) -->
<div class="sp-top-catalog-toolbar mb-4 p-2.5 bg-white rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-xs" style="border-color: #DDE4ED !important;">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Quick State Filter Dropdown -->
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-bold" style="font-size: 0.72rem;"><i class="fas fa-map-marker-alt text-danger me-1"></i> الولاية:</span>
            <select class="form-select form-select-sm sp-top-filter-select" onchange="loadCatalogAjax(this.value, true, true)" style="font-size: 0.76rem; font-weight: 700; color: #0B2A52; border-color: #DDE4ED; border-radius: 8px; width: auto; min-width: 175px; cursor: pointer;">
                <option value="<?= buildFilterUrl(['state' => null, 'city' => null]) ?>" <?= empty($state) ? 'selected' : '' ?>>كافة الولايات (18 ولاية)</option>
                <?php foreach($allStatesList as $st): ?>
                    <option value="<?= buildFilterUrl(['state' => $st, 'city' => null]) ?>" <?= $state === $st ? 'selected' : '' ?>>
                        ولاية <?= e($st) ?> (<?= $stateCounts[$st] ?? 0 ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="vr mx-1 text-muted opacity-25 d-none d-sm-block" style="height: 24px;"></div>

        <!-- Quick Type Selector Pills -->
        <div class="d-flex align-items-center gap-1">
            <a href="<?= buildFilterUrl(['type' => null, 'view' => ($view === 'historic' ? 'historic' : null)]) ?>" class="btn btn-sm <?= empty($schoolType) ? 'btn-dark' : 'btn-light border' ?>" style="font-size: 0.72rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; <?= empty($schoolType) ? 'background: #0B2A52; border-color: #0B2A52; color: #fff;' : 'color: #475569;' ?>">
                الكل
            </a>
            <a href="<?= buildFilterUrl(['type' => 'government']) ?>" class="btn btn-sm <?= $schoolType === 'government' ? 'btn-primary' : 'btn-light border' ?>" style="font-size: 0.72rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; <?= $schoolType === 'government' ? 'background: #1E5FA8; border-color: #1E5FA8; color: #fff;' : 'color: #475569;' ?>">
                <i class="fas fa-landmark me-1"></i> حكومي
            </a>
            <a href="<?= buildFilterUrl(['type' => 'private']) ?>" class="btn btn-sm <?= $schoolType === 'private' ? 'btn-warning' : 'btn-light border' ?>" style="font-size: 0.72rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; <?= $schoolType === 'private' ? 'background: #F5A020; border-color: #F5A020; color: #0D1B2E;' : 'color: #475569;' ?>">
                <i class="fas fa-graduation-cap me-1"></i> خاص
            </a>
            <a href="<?= buildFilterUrl(['view' => 'historic', 'type' => null]) ?>" class="btn btn-sm <?= $view === 'historic' ? 'btn-warning text-dark' : 'btn-light border' ?>" style="font-size: 0.72rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; <?= $view === 'historic' ? 'background: linear-gradient(135deg, #FDE68A 0%, #F59E0B 100%); border-color: #FCD34D; color: #78350F !important;' : 'color: #78350F;' ?>">
                <i class="fas fa-landmark me-1"></i> صروح عريقة
            </a>
        </div>
    </div>

    <!-- Active State & Total Indicator -->
    <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.73rem;">
        <?php if (!empty($state)): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.7rem; font-weight: 700;">
                <i class="fas fa-map-marker-alt me-1"></i> ولاية <?= e($state) ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($schoolType)): ?>
            <span class="badge <?= $schoolType === 'government' ? 'bg-info-subtle text-info-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?> border px-2 py-1" style="font-size: 0.7rem; font-weight: 700;">
                <?= $schoolType === 'government' ? 'مدارس حكومية' : 'مدارس خاصة ودولية' ?>
            </span>
        <?php endif; ?>
        <span>إجمالي المدارس: <strong class="text-dark"><?= number_format($totalMatching ?? $statTotal) ?></strong></span>
    </div>
</div>

<!-- Mobile Filter Toggle Button -->
<div class="d-lg-none mb-3">
    <button class="btn btn-white border w-100 py-2.5 px-3 d-flex justify-content-between align-items-center rounded-3 shadow-sm sp-mobile-filter-btn" type="button" data-bs-toggle="collapse" data-bs-target="#statesSidebarCollapse" aria-expanded="false" aria-controls="statesSidebarCollapse">
        <span class="d-flex align-items-center gap-2">
            <i class="fas fa-filter text-primary"></i>
            <span>تصفية حسب الولاية:</span>
            <strong style="color: #0B2A52;"><?= !empty($state) ? 'ولاية ' . e($state) : 'كافة الولايات (18 ولاية)' ?></strong>
        </span>
        <span class="badge bg-light text-dark border"><i class="fas fa-sliders-h"></i></span>
    </button>
</div>

<!-- 2-COLUMN CATALOG LAYOUT: SIDEBAR FILTER (RIGHT) + CATALOG GRID (LEFT) -->
<div class="row g-4 align-items-start">
    
    <!-- 1. RIGHT SIDEBAR: MODERN STATES FILTER (col-lg-3 col-md-4) -->
    <div class="col-lg-3 col-md-4">
        <div class="collapse d-lg-block" id="statesSidebarCollapse">
            <aside class="sp-state-sidebar-wrap">
                <div class="sp-state-sidebar-card">
                    
                    <!-- Header -->
                    <div class="sp-state-sidebar-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sp-state-sidebar-icon">
                                    <i class="fas fa-map-marked-alt"></i>
                                </div>
                                <div>
                                    <h5 class="sp-state-sidebar-title"><?= !empty($country) && $country !== 'السودان' ? 'تصفية حسب ' . e($regionMiniLabel) : 'تصفية حسب الولاية' ?></h5>
                                    <span class="sp-state-sidebar-subtitle"><?= !empty($country) && $country !== 'السودان' ? (count($statesWithCities) . ' ' . e($regionAllLabel)) : '18 ولاية سودانية' ?></span>
                                </div>
                            </div>
                            <?php if (!empty($state)): ?>
                                <a href="<?= buildFilterUrl(['state' => null, 'city' => null]) ?>" class="sp-state-reset-link" title="إلغاء تصفية الولاية">
                                    <i class="fas fa-times-circle"></i> مسح
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Search in States -->
                    <div class="sp-state-sidebar-search">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #DDE4ED;">
                                <i class="fas fa-search" style="font-size: 0.7rem;"></i>
                            </span>
                            <input type="text" id="sidebarStateSearch" class="form-control border-start-0 ps-1" placeholder="<?= e($regionSearchPlaceholder) ?>" style="border-color: #DDE4ED; font-size: 0.74rem;" oninput="filterSidebarStates(this.value)">
                        </div>
                    </div>

                    <?php if (empty($country) || $country === 'السودان'): ?>
                    <!-- Regional Jump Pills -->
                    <div class="sp-state-sidebar-regions">
                        <button type="button" class="sp-sidebar-region-btn is-active" data-region="all" onclick="filterSidebarRegion('all', this)">الكل</button>
                        <button type="button" class="sp-sidebar-region-btn" data-region="central" onclick="filterSidebarRegion('central', this)">الوسط</button>
                        <button type="button" class="sp-sidebar-region-btn" data-region="east" onclick="filterSidebarRegion('east', this)">الشرق</button>
                        <button type="button" class="sp-sidebar-region-btn" data-region="north" onclick="filterSidebarRegion('north', this)">الشمال</button>
                        <button type="button" class="sp-sidebar-region-btn" data-region="kordofan" onclick="filterSidebarRegion('kordofan', this)">كردفان</button>
                        <button type="button" class="sp-sidebar-region-btn" data-region="darfur" onclick="filterSidebarRegion('darfur', this)">دارفور</button>
                    </div>
                    <?php endif; ?>

                    <!-- States List -->
                    <div class="sp-state-sidebar-list" id="sidebarStatesList">
                        
                        <!-- All States / Country Item -->
                        <a href="<?= buildFilterUrl(['state' => null, 'city' => null]) ?>" class="sp-state-item <?= empty($state) ? 'is-active' : '' ?>" data-region="all">
                            <div class="d-flex align-items-center gap-2">
                                <span class="sp-state-dot"></span>
                                <span class="sp-state-item-name"><?= !empty($country) && $country !== 'السودان' ? 'كافة مناطق ' . e($country) : 'كافة ولايات السودان' ?></span>
                            </div>
                            <span class="sp-state-item-count"><?= number_format($statTotal) ?></span>
                        </a>

                        <?php 
                            $statesLoop = (!empty($country) && $country !== 'السودان') ? array_keys($statesWithCities) : $allStatesList;
                            foreach($statesLoop as $stName): 
                                $countInState = $stateCounts[$stName] ?? 0;
                                $isSelected = ($state === $stName);
                                $stUrl = $isSelected ? buildFilterUrl(['state' => null, 'city' => null]) : buildFilterUrl(['state' => $stName, 'city' => null]);
                                $regKey = $sudanRegionMapping[$stName] ?? 'all';
                        ?>
                        <a href="<?= $stUrl ?>" class="sp-state-item <?= $isSelected ? 'is-active' : '' ?>" data-region="<?= $regKey ?>" data-name="<?= e($stName) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <span class="sp-state-dot"></span>
                                <span class="sp-state-item-name">ولاية <?= e($stName) ?></span>
                            </div>
                            <span class="sp-state-item-count <?= $countInState > 0 ? 'has-count' : '' ?>"><?= number_format($countInState) ?></span>
                        </a>
                        <?php endforeach; ?>
                        
                        <div id="noMatchingStatesMsg" class="p-3 text-center text-muted d-none" style="font-size: 0.72rem;">
                            لا توجد ولاية مطابقة للبحث
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sp-state-sidebar-footer">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        <span>تحديث لحظي لعدد المدارس</span>
                    </div>

                </div>
            </aside>
        </div>
    </div>

    <!-- 2. LEFT COLUMN: ACTIVE CHIPS + SCHOOLS GRID (col-lg-9 col-md-8) -->
    <div class="col-lg-9 col-md-8">
        
        <!-- Active Filters Chips -->
        <?php if ($state !== '' || $city !== '' || $stage !== '' || $schoolType !== '' || $q !== '' || $schoplusOnly || ($view !== '' && $view !== 'all')): ?>
        <div class="sp-active-filter-chips mb-3 d-flex flex-wrap align-items-center gap-2">
            <span class="sp-chips-label"><i class="fas fa-filter text-muted me-1"></i> التصفية الحالية:</span>
            <?php if ($state !== ''): ?>
                <a href="<?= buildFilterUrl(['state' => null, 'city' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية الولاية">
                    <span>ولاية: <strong><?= e($state) ?></strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($schoolType !== ''): ?>
                <a href="<?= buildFilterUrl(['type' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية نوع المدرسة">
                    <span>النوع: <strong><?= $schoolType === 'government' ? 'مدارس حكومية' : 'مدارس خاصة ودولية' ?></strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($city !== ''): ?>
                <a href="<?= buildFilterUrl(['city' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية المدينة">
                    <span>مدينة: <strong><?= e($city) ?></strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($stage !== ''): ?>
                <a href="<?= buildFilterUrl(['stage' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية المرحلة">
                    <span>مرحلة: <strong><?= e($schoolStages[$stage] ?? $stage) ?></strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($q !== ''): ?>
                <a href="<?= buildFilterUrl(['q' => null]) ?>" class="sp-chip-tag" title="إزالة البحث النصي">
                    <span>بحث: <strong><?= e($q) ?></strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($view === 'schoplus' || $schoplusOnly): ?>
                <a href="<?= buildFilterUrl(['view' => null, 'schoplus_only' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية المدارس الذكية">
                    <span><strong>مدارس SchoPlus الذكية</strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($view === 'historic'): ?>
                <a href="<?= buildFilterUrl(['view' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية الصروح العريقة" style="background:#FEF3C7;color:#92400E;border-color:#FDE68A;">
                    <i class="fas fa-landmark text-warning me-1"></i>
                    <span><strong>صروح ومدارس عريقة</strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($view === 'top_rated' || $sort === 'rating'): ?>
                <a href="<?= buildFilterUrl(['view' => null, 'sort' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية التقييم">
                    <span><strong>المدارس الأعلى تقييماً</strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <?php if ($view === 'latest' || $sort === 'latest'): ?>
                <a href="<?= buildFilterUrl(['view' => null, 'sort' => null]) ?>" class="sp-chip-tag" title="إزالة تصفية الأحدث">
                    <span><strong>المضافة حديثاً</strong></span>
                    <i class="fas fa-times ms-1"></i>
                </a>
            <?php endif; ?>
            <a href="/" class="sp-chips-reset text-danger text-decoration-none ms-auto" style="font-size: 0.72rem; font-weight: 700;">
                <i class="fas fa-undo me-1"></i> مسح الكل
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isSearching): ?>
            <!-- SEARCH RESULTS HEADER -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">
                        <?php if ($view === 'historic'): ?>
                            المدارس والصروح العريقة والتاريخية <?= !empty($state) ? '— ولاية ' . e($state) : 'في السودان' ?>
                        <?php elseif ($schoolType === 'government'): ?>
                            المدارس الحكومية <?= !empty($state) ? '— ولاية ' . e($state) : 'في السودان' ?>
                        <?php elseif ($schoolType === 'private'): ?>
                            المدارس الخاصة والدولية <?= !empty($state) ? '— ولاية ' . e($state) : 'في السودان' ?>
                        <?php elseif ($view === 'schoplus' || $schoplusOnly): ?>
                            مدارس تعمل بنظام SchoPlus الذكي <?= !empty($state) ? '— ولاية ' . e($state) : '' ?>
                        <?php elseif ($view === 'top_rated' || $sort === 'rating'): ?>
                            المدارس الأعلى تقييماً في الدليل <?= !empty($state) ? '— ولاية ' . e($state) : '' ?>
                        <?php elseif ($view === 'latest' || $sort === 'latest'): ?>
                            أحدث المدارس المنضمة إلى الدليل <?= !empty($state) ? '— ولاية ' . e($state) : '' ?>
                        <?php elseif ($state !== '' && empty($q) && empty($stage) && empty($city)): ?>
                            مدارس ولاية <?= e($state) ?>
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
            <div class="row g-3">
                <?php if (empty($searchResults)): ?>
                    <div class="col-12 py-5 text-center">
                        <div class="p-4 p-md-5 bg-white rounded-4 border text-center shadow-sm max-w-md mx-auto" style="max-width: 520px; margin: 0 auto; border-color: #DDE4ED !important;">
                            <div style="width: 56px; height: 56px; border-radius: 50%; background: #EFF6FF; color: #1E5FA8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 14px;">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4 class="fw-bold text-dark" style="font-size: 1.05rem;">لم نعثر على مدرسة مطابقة للبحث</h4>
                            <?php if (!empty($q)): ?>
                                <p class="text-muted small" style="font-size: 0.76rem; line-height: 1.6; margin-bottom: 18px;">
                                    هل تبحث عن مدرسة <strong class="text-dark">"<?= e($q) ?>"</strong>؟ قد لا تكون مسجلة بعد في الدليل، ولكن يمكنك فحص حضورها الرقمي الشامل على جوجل وخرائط جوجل وشبكات التواصل بنقرة واحدة!
                                </p>
                                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mb-2">
                                    <a href="/presence-audit.php?school=<?= urlencode($q) ?>&country=<?= urlencode($country) ?>&state=<?= urlencode($state) ?>&auto=1" class="btn-luxury-primary btn-sm py-2 px-3" style="font-size: 0.78rem;">
                                        <i class="fas fa-satellite-dish me-1"></i> فحص الوجود الرقمي لـ "<?= e($q) ?>"
                                    </a>
                                    <a href="/" class="btn-luxury-outline btn-sm py-2 px-3" style="font-size: 0.78rem;">
                                        <i class="fas fa-undo me-1"></i> عرض كافة المدارس
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small" style="font-size: 0.74rem;">جرب تغيير شروط الفلترة أو تصفح ولاية أخرى لاكتشاف خيارات أوسع.</p>
                                <a href="/" class="btn-luxury-primary btn-sm mt-2">
                                    <i class="fas fa-undo me-1"></i> إعادة ضبط وعرض كل المدارس
                                </a>
                            <?php endif; ?>
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
            <div class="mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div>
                        <span style="color: #1E5FA8; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                            <i class="fas fa-award me-1"></i> التميز والرقمنة
                        </span>
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">مدارس ذكية رائدة تعمل بنظام SchoPlus</h2>
                    </div>
                    <?php if ($statSchoplus > 6): ?>
                        <div class="mt-2 mt-md-0">
                            <a href="<?= buildFilterUrl(['view' => 'schoplus']) ?>" class="btn-luxury-outline btn-sm">
                                <span>عرض جميع المدارس الذكية (<?= $statSchoplus ?>)</span>
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
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
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #0B2A52; margin: 0;">قائمة المدارس السودانية في الدليل</h2>
                    </div>
                    <div class="mt-2 mt-md-0 text-muted" style="font-size: 0.72rem;">
                        عرض <strong><?= count($catalogSchools) ?></strong> من أصل <strong><?= $statTotal ?></strong> مدرسة مسجلة
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach($catalogSchools as $s): ?>
                        <?php $renderSchoolCard($s); ?>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>

</div>
