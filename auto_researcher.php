<?php
/**
 * SchoPlus Daleel — Autonomous School Discovery & Research Engine
 * محرك الباحث الآلي للمدارس — يعمل في الخلفية (Background Engine / Admin Automation)
 * غير متاح للزوار العاديين
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';

$pdo = db();

// =========================================================================
// 1. CLI EXECUTION SUPPORT (FOR CRON JOBS & BACKGROUND WORKERS)
// =========================================================================
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "=== SchoPlus Daleel Autonomous Background Researcher ===\n";
    $options = getopt('', ['query:', 'state:', 'city:', 'phone:', 'scan']);
    $query = $options['query'] ?? null;
    $state = $options['state'] ?? null;
    $city = $options['city'] ?? null;
    $phone = $options['phone'] ?? null;

    if (!empty($query)) {
        echo "Discovering single school: {$query}...\n";
        $res = SchoolAutoResearcher::autoDiscoverAndRegister($pdo, $query, $state, $city, null, $phone, $phone);
        print_r($res);
        exit(0);
    }

    echo "Running background locality check...\n";
    $total = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
    echo "Current approved institutions: {$total}\n";
    echo "=== Background Run Completed ===\n";
    exit(0);
}

// =========================================================================
// 2. SECURITY GATE FOR WEB ACCESS (ADMIN / BACKGROUND SECRET ONLY)
// =========================================================================
$adminSecretKey = "schoplus_daleel_deploy_sec_4412f";
$providedKey = $_GET['key'] ?? ($_POST['key'] ?? '');
$isAuthAdmin = (!empty($_SESSION['school_id']));

if (!$isAuthAdmin && $providedKey !== $adminSecretKey) {
    http_response_code(403);
    header('Location: /');
    exit;
}

$pageTitle = 'الباحث الآلي المدمج للمدارس — لوحة التحكم بالخلفية';
$pageDesc = 'النظام الذاتي للبحث والاستكشاف الذكي لمدارس ورياض الأطفال بالسودان وفهرستها في الدليل آلياً.';

// Metrics
$totalApproved = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$totalStates = (int)$pdo->query("SELECT COUNT(DISTINCT state) FROM daleel_schools WHERE status = 'approved' AND state IS NOT NULL AND state != ''")->fetchColumn();
$recentDiscovered = $pdo->query("SELECT id, name, name_en, slug, state, city, district, created_at, directory_rank 
                                FROM daleel_schools 
                                WHERE status = 'approved' AND is_verified = 0 
                                ORDER BY id DESC 
                                LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size: 0.76rem;">
            <li class="breadcrumb-item"><a href="/" class="text-decoration-none" style="color: #1E5FA8;"><i class="fas fa-home me-1"></i> الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">الباحث الآلي المدمج للمدارس</li>
        </ol>
    </nav>

    <!-- Page Header (Style F v2) -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 pb-3 border-bottom">
        <div>
            <div style="font-size: 0.65rem; font-weight: 800; color: #F5A020; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fas fa-robot me-1"></i> SchoPlus Autonomous AI Discovery
            </div>
            <h1 style="font-size: 1.25rem; font-weight: 800; color: #0B2A52; margin: 4px 0 2px;">
                الباحث الآلي المدمج لاستكشاف المدارس
            </h1>
            <p style="font-size: 0.74rem; color: #64748B; margin: 0;">
                نظام استكشاف رقمي ذكي للبحث عن المدارس غير المسجلة، تحليل بياناتها الجغرافية والمرحلية، وفهرستها آلياً في الدليل.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="/" class="btn-luxury-outline btn-sm py-2 px-3" style="font-size: 0.76rem;">
                <i class="fas fa-search me-1"></i> تصفح الدليل
            </a>
            <a href="/presence-audit.php" class="btn-luxury-primary btn-sm py-2 px-3" style="font-size: 0.76rem;">
                <i class="fas fa-radar me-1"></i> فحص الأثر الرقمي
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
            <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="border-right: 4px solid #1E5FA8 !important; border-color: #DDE4ED;">
                <div class="text-muted small fw-bold" style="font-size: 0.7rem;">إجمالي المدارس المفهرسة</div>
                <div class="fs-4 fw-bold mt-1" style="color: #0B2A52;"><?= number_format($totalApproved) ?></div>
                <div class="small text-muted" style="font-size: 0.65rem;">مؤسسة تعليمية معتمدة</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="border-right: 4px solid #F5A020 !important; border-color: #DDE4ED;">
                <div class="text-muted small fw-bold" style="font-size: 0.7rem;">الولايات المشمولة</div>
                <div class="fs-4 fw-bold mt-1" style="color: #0B2A52;"><?= number_format($totalStates) ?></div>
                <div class="small text-muted" style="font-size: 0.65rem;">18 ولاية + المهجر</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="border-right: 4px solid #10B981 !important; border-color: #DDE4ED;">
                <div class="text-muted small fw-bold" style="font-size: 0.7rem;">حالة المحرك الآلي</div>
                <div class="fs-4 fw-bold mt-1 text-success">نشط 100%</div>
                <div class="small text-muted" style="font-size: 0.65rem;">فهرسة لحظية ذكية</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="border-right: 4px solid #6366F1 !important; border-color: #DDE4ED;">
                <div class="text-muted small fw-bold" style="font-size: 0.7rem;">حماية التكرار (De-dup)</div>
                <div class="fs-4 fw-bold mt-1" style="color: #4F46E5;">مفعلة تلقائياً</div>
                <div class="small text-muted" style="font-size: 0.65rem;">فحص وتطابق متعدد الطبقات</div>
            </div>
        </div>
    </div>

    <!-- Main Discovery Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="border: 1px solid #DDE4ED !important;">
        <div class="card-header bg-white p-3 p-md-4 border-bottom" style="background: linear-gradient(135deg, #0B2A52, #163870) !important; color: #FFFFFF;">
            <div class="d-flex align-items-center gap-2">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 160, 32, 0.2); color: #F5A020; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fas fa-search-location"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-white" style="font-size: 0.98rem;">استكشاف مدرسة جديدة وإدراجها فوراً</h5>
                    <span class="small" style="color: #CBD5E1; font-size: 0.72rem;">أدخل اسم أي مدرسة أو روضة أطفال للبحث عنها، تحليل بياناتها، وإدراج بطاقتها في الدليل تلقائياً.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-4 bg-light">
            <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size: 0.76rem; border-radius: 8px; border: 1px solid #FDE68A; background: #FEF3C7; color: #92400E;">
                <i class="fas fa-shield-alt fs-6"></i>
                <span><strong>قاعدة الصرامة الصفرية للهواتف:</strong> يُمنع منعاً باتاً إضافة، أو توليد، أو اعتماد أي مدرسة خاصة أو روضة أطفال في الدليل ما لم يتوفر لها رقم هاتف حقيقي موثق ومؤكد 100%. الصروح الحكومية التاريخية فقط هي المعفاة.</span>
            </div>
            <form id="autoDiscoverForm" onsubmit="event.preventDefault(); runAutoDiscover();">
                <div class="row g-3">
                    <div class="col-lg-5 col-12">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">اسم المدرسة أو المجمع أو الروضة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color: #DDE4ED;"><i class="fas fa-school text-muted"></i></span>
                            <input type="text" id="discQuery" class="form-control" placeholder="مثال: مدارس المنار الخاصة بورتسودان، مدارس الإشراق كوستي، روضة المستقبل..." required style="border-color: #DDE4ED; font-size: 0.8rem;">
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">رقم هاتف المدرسة <span class="text-danger">* (إلزامي للخاصة)</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white" style="border-color: #DDE4ED;"><i class="fas fa-phone text-muted"></i></span>
                            <input type="text" id="discPhone" class="form-control" placeholder="مثال: +249912345678 أو 0912345678" style="border-color: #DDE4ED; font-size: 0.8rem;">
                        </div>
                    </div>
                    <div class="col-lg-2 col-sm-6 col-12">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">الولاية (اختياري)</label>
                        <select id="discState" class="form-select" style="border-color: #DDE4ED; font-size: 0.8rem;">
                            <option value="">استنتاج تلقائي</option>
                            <?php foreach (SudanGeography::states('السودان') as $st): ?>
                                <option value="<?= e($st) ?>"><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-sm-6 col-12">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.75rem;">المدينة / المحلية</label>
                        <input type="text" id="discCity" class="form-control" placeholder="مثال: بورتسودان..." style="border-color: #DDE4ED; font-size: 0.8rem;">
                    </div>
                </div>

                <div class="mt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="text-muted small" style="font-size: 0.71rem;">
                        <i class="fas fa-shield-alt text-success me-1"></i> تخضع كافة المدخلات لفحص التحقق الصارم من الأرقام الحقيقية وتجنب التكرار.
                    </div>
                    <button type="submit" id="btnRunDiscovery" class="btn-luxury-primary px-4 py-2 text-white fw-bold" style="background: #F5A020; border-color: #F5A020; font-size: 0.8rem;">
                        <i class="fas fa-magic me-1"></i> بدء الاستكشاف والفهرسة
                    </button>
                </div>
            </form>

            <!-- Discovery Result Area -->
            <div id="discoveryResultBox" class="mt-4" style="display: none;"></div>
        </div>
    </div>

    <!-- Recently Discovered Schools Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="border: 1px solid #DDE4ED !important;">
        <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-history text-primary"></i>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.88rem;">أحدث المدارس المستكشفة والمضافة آلياً للدليل</h6>
            </div>
            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">آخر 10 مدارس</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.76rem;">
                <thead style="background: #F8FAFC; color: #64748B; font-weight: 700;">
                    <tr>
                        <th class="ps-3">المعرف</th>
                        <th>اسم المدرسة</th>
                        <th>الموقع</th>
                        <th>الحي / المنطقة</th>
                        <th>الترتيب العام</th>
                        <th>تاريخ الإضافة</th>
                        <th class="text-center pe-3">الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentDiscovered)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">لا توجد مدارس مستكشفة حديثاً.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentDiscovered as $rd): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-muted">#<?= $rd['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($rd['name']) ?></div>
                                    <?php if (!empty($rd['name_en'])): ?>
                                        <div class="text-muted small" style="font-size: 0.68rem;"><?= e($rd['name_en']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($rd['state']) ?></span>
                                    <span class="text-muted ms-1"><?= e($rd['city']) ?></span>
                                </td>
                                <td><?= e($rd['district'] ?: '-') ?></td>
                                <td><span class="badge" style="background: #EFF6FF; color: #1E5FA8; border: 1px solid #BFDBFE;">#<?= $rd['directory_rank'] ?: $rd['id'] ?></span></td>
                                <td class="text-muted"><?= date('Y-m-d H:i', strtotime($rd['created_at'])) ?></td>
                                <td class="text-center pe-3">
                                    <a href="/school/<?= urlencode($rd['slug']) ?>" class="btn btn-sm btn-light border py-1 px-2" style="font-size: 0.72rem; color: #1E5FA8;" target="_blank">
                                        <i class="fas fa-external-link-alt me-1"></i> عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function runAutoDiscover() {
    const q = document.getElementById('discQuery').value.trim();
    const phone = document.getElementById('discPhone') ? document.getElementById('discPhone').value.trim() : '';
    const st = document.getElementById('discState').value.trim();
    const ct = document.getElementById('discCity').value.trim();
    const btn = document.getElementById('btnRunDiscovery');
    const resBox = document.getElementById('discoveryResultBox');

    if (!q) {
        alert('يرجى كتابة اسم المدرسة للبدء.');
        return;
    }

    btn.disabled = true;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الاستكشاف والتحليل والفهرسة...';

    resBox.style.display = 'block';
    resBox.innerHTML = `
        <div class="p-3 bg-white rounded-3 border text-center shadow-sm">
            <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
            <div class="fw-bold text-dark" style="font-size: 0.82rem;">جاري مسح المصادر وفهرسة بيانات "${q}"...</div>
            <div class="text-muted small" style="font-size: 0.72rem;">يتم الآن استنتاج الموقع، التحقق من الهاتف الصارم، وفحص عدم التكرار...</div>
        </div>
    `;

    const fd = new FormData();
    fd.append('query', q);
    fd.append('phone', phone);
    fd.append('state', st);
    fd.append('city', ct);

    fetch('/api_auto_discover.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = oldHtml;

        if (data && data.success) {
            const sch = data.school;
            const isExisting = data.already_exists;
            const alertClass = isExisting ? 'alert-warning' : 'alert-success';
            const icon = isExisting ? 'fa-info-circle text-warning' : 'fa-check-circle text-success';
            const badgeText = isExisting ? 'مدرسة مسجلة سلفاً' : 'مدرسة مكتشفة ومضافة حديثاً';

            resBox.innerHTML = `
                <div class="alert ${alertClass} p-3 rounded-3 shadow-sm border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas ${icon} fs-5"></i>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">${data.message || 'اكتملت العملية بنجاح!'}</h6>
                        </div>
                        <span class="badge ${isExisting ? 'bg-warning text-dark' : 'bg-success text-white'}">${badgeText}</span>
                    </div>

                    <div class="p-3 bg-white rounded-3 border mt-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-7 col-12">
                                <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">${sch.name}</h5>
                                <div class="text-muted small mb-2" style="font-size: 0.74rem;">${sch.name_en || ''}</div>
                                <div class="d-flex flex-wrap gap-2 small">
                                    <span class="badge bg-light text-dark border"><i class="fas fa-map-marker-alt text-danger me-1"></i> ${sch.state} — ${sch.city}</span>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-building text-primary me-1"></i> حي ${sch.district || sch.city}</span>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-shield-alt text-muted me-1"></i> عامة (غير موثقة)</span>
                                </div>
                            </div>
                            <div class="col-md-5 col-12 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                                <a href="${sch.url}" target="_blank" class="btn btn-sm btn-primary py-2 px-3 fw-bold" style="background: #1E5FA8; font-size: 0.78rem;">
                                    <i class="fas fa-external-link-alt me-1"></i> فتح صفحة المدرسة
                                </a>
                                <a href="${sch.google_maps_url || '#'}" target="_blank" class="btn btn-sm btn-light border py-2 px-3" style="font-size: 0.78rem;">
                                    <i class="fas fa-map-marked-alt text-success me-1"></i> خرائط جوجل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            resBox.innerHTML = `
                <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-danger fs-5"></i>
                        <span class="fw-bold" style="font-size: 0.85rem;">${data.error || 'تعذر استكشاف المدرسة.'}</span>
                    </div>
                </div>
            `;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
        resBox.innerHTML = `
            <div class="alert alert-danger p-3 rounded-3 shadow-sm border">
                <i class="fas fa-exclamation-triangle text-danger me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
            </div>
        `;
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
