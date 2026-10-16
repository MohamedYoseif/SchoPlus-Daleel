<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Official Android Mobile App Download & Showcase Landing Page
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'تحميل تطبيق دليل المدارس السودانية للأندرويد (APK)';
require_once __DIR__ . '/header.php';

$apkDownloadUrl = '/downloads/daleel-schoplus.apk';
$apkSize = '5.1 MB';
$apkVersion = '1.0.0';
?>

<div class="py-5" style="background: #F0F4F8;">
    <div class="container">

        <!-- HERO SECTION -->
        <div class="row align-items-center g-5 py-4">
            <div class="col-lg-7 text-center text-lg-start">
                
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; font-size: 0.8rem; font-weight: 700;">
                    <i class="fab fa-android text-success"></i>
                    <span>التطبيق الرسمي المعتمد لهواتف الأندرويد</span>
                </div>

                <h1 class="fw-bold mb-3" style="color: var(--brand-navy); font-size: 2.3rem; line-height: 1.3;">
                    تطبيق دليل المدارس السودانية
                </h1>

                <p class="text-secondary mb-4" style="font-size: 1.05rem; line-height: 1.8; max-width: 580px;">
                    اكتشف أفضل المدارس في كافة الولايات والمدن السودانية، استكشف الرسوم والمناهج، وتواصل مباشرة عبر واتساب ومكالمات الهاتف بإدارات المدارس بضغطة زر واحدة من هاتفك.
                </p>

                <!-- Download Actions -->
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-4">
                    <a href="<?= $apkDownloadUrl ?>" download class="btn px-4 py-3 fw-bold text-white shadow-sm d-inline-flex align-items-center gap-3 rounded-4" style="background: #0B2A52; font-size: 1.02rem; border: 2px solid #F5A020;">
                        <i class="fab fa-android fa-2x text-warning"></i>
                        <div class="text-start">
                            <span class="d-block small text-warning opacity-75" style="font-size: 0.72rem;">تنزيل مباشر وسريع</span>
                            <span class="d-block fw-bold" style="font-size: 1rem;">تحميل تطبيق أندرويد (APK)</span>
                        </div>
                    </a>

                    <a href="/#search-section" class="btn btn-outline-secondary px-4 py-3 fw-bold rounded-4 d-inline-flex align-items-center gap-2" style="font-size: 0.95rem;">
                        <i class="fas fa-search text-primary"></i>
                        <span>تصفح الدليل أونلاين</span>
                    </a>
                </div>

                <!-- Info Badges -->
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start">
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill small">
                        <i class="fas fa-file-archive text-warning me-1"></i> الحجم: <?= $apkSize ?>
                    </span>
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill small">
                        <i class="fas fa-code-branch text-primary me-1"></i> الإصدار: <?= $apkVersion ?>
                    </span>
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill small">
                        <i class="fas fa-mobile-alt text-success me-1"></i> أندرويد 7.0 فما فوق
                    </span>
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill small">
                        <i class="fas fa-sync-alt text-info me-1"></i> تحديث تلقائي فوري
                    </span>
                </div>

            </div>

            <!-- Phone Showcase Mockup -->
            <div class="col-lg-5 text-center">
                <div class="position-relative d-inline-block">

                    <!-- Phone Frame -->
                    <div class="position-relative bg-white rounded-5 p-3 shadow-lg border border-3" style="max-width: 320px; margin: 0 auto; z-index: 1; border-color: #0B2A52 !important;">
                        
                        <!-- Top Notch -->
                        <div class="d-flex justify-content-between align-items-center px-3 py-1 mb-2 bg-light rounded-pill">
                            <span class="small text-muted" style="font-size: 0.7rem; font-weight: 700;">SchoPlus Daleel</span>
                            <div class="d-flex align-items-center gap-1">
                                <i class="fas fa-wifi text-muted" style="font-size: 0.65rem;"></i>
                                <i class="fas fa-battery-full text-muted" style="font-size: 0.65rem;"></i>
                            </div>
                        </div>

                        <!-- Screen Content Card -->
                        <div class="rounded-4 overflow-hidden border p-3 text-center" style="background: #0B2A52; color: #FFFFFF;">
                            <img src="/assets/images/logo.png?v=3.6.97" alt="شعار الدليل" style="max-height: 52px; width: auto;" class="my-3">
                            <h5 class="fw-bold mb-1" style="font-size: 1.05rem;">دليل المدارس السودانية</h5>
                            <p class="text-white-50 small mb-3" style="font-size: 0.72rem;">منظومة البحث والتعليم الذكي في السودان</p>
                            
                            <div class="p-2 bg-white rounded-3 mb-2 text-start d-flex align-items-center gap-2">
                                <i class="fas fa-search text-muted small"></i>
                                <span class="text-muted small" style="font-size: 0.75rem;">ابحث بالاسم، الولاية، أو المرحلة...</span>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-white bg-opacity-10 text-center">
                                        <i class="fas fa-award text-warning d-block mb-1"></i>
                                        <span class="small fw-bold d-block" style="font-size: 0.68rem;">أعلى تقييماً</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-white bg-opacity-10 text-center">
                                        <i class="fas fa-bolt text-warning d-block mb-1"></i>
                                        <span class="small fw-bold d-block" style="font-size: 0.68rem;">مدارس SchoPlus</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Home Indicator -->
                        <div class="mt-3 text-center">
                            <div style="width: 100px; height: 4px; background: #CBD5E1; border-radius: 2px; margin: 0 auto;"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- APP FEATURES SECTION -->
        <div class="py-5">
            <div class="text-center mb-5">
                <span class="d-inline-block px-3 py-1 rounded-pill mb-2 fw-bold text-primary" style="background: #EFF6FF; font-size: 0.78rem;">
                    <i class="fas fa-star me-1 text-warning"></i> مميزات تطبيق الأندرويد
                </span>
                <h2 class="fw-bold text-dark fs-3">تجربة تعليمية متكاملة في جيبك</h2>
                <p class="text-muted small">صُمم التطبيق خصيصاً ليعمل بأعلى كفاءة وسرعة على كافة الهواتف والشبكات في السودان</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-primary" style="width: 48px; height: 48px; background: #EFF6FF; font-size: 1.3rem;">
                            <i class="fas fa-feather-alt text-primary"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">خفيف وسريع للغاية (5MB فقط)</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            حجم التطبيق صغير جداً، لا يستهلك ذاكرة الهاتف أو باقة الإنترنت، ويفتح في جزء من الثانية دون أي تأخير.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-warning" style="width: 48px; height: 48px; background: #FEF3C7; font-size: 1.3rem;">
                            <i class="fab fa-whatsapp text-success"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">تواصل مباشر مع إدارات المدارس</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            اتصال مباشر ومحادثات واتساب بنقرة واحدة للاستفسار عن الرسوم والتسجيل وحجز المقاعد مباشرة من التطبيق.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-success" style="width: 48px; height: 48px; background: #ECFDF5; font-size: 1.3rem;">
                            <i class="fas fa-sync-alt text-success"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">تزامن فوري لحظي</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            أي مدرسة جديدة أو إعلان أو خبر يُنشر يظهر فوراً داخل التطبيق دون الحاجة لتحديث التطبيق من المتجر.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-info" style="width: 48px; height: 48px; background: #F0F9FF; font-size: 1.3rem;">
                            <i class="fas fa-map-marked-alt text-info"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">تحديد الموقع والوصول للمدارس</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            ربط متكامل مع خرائط جوجل لمعرفة المدارس الأقرب إليك والحصول على اتجاهات القيادة والوصول الدقيقة.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-danger" style="width: 48px; height: 48px; background: #FEF2F2; font-size: 1.3rem;">
                            <i class="fas fa-wifi-slash text-danger"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">شاشة عدم الاتصال الذكية</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            تصميم خاص للتعامل مع انقطاع الشبكة، يتيح لك إعادة المحاولة فور عودة الاتصال بسلاسة تامة.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="p-4 rounded-4 bg-white border shadow-xs h-100">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 text-primary" style="width: 48px; height: 48px; background: #EEF2FF; font-size: 1.3rem;">
                            <i class="fas fa-user-shield text-primary"></i>
                        </div>
                        <h5 class="fw-bold text-dark fs-6 mb-2">لوحة متكاملة لأصحاب المدارس</h5>
                        <p class="text-secondary small mb-0 lh-base">
                            إدارة ملف المدرسة، التقاط صور الفعاليات بالكاميرا، ونشر الأخبار والتفاعل مع تقييمات أولياء الأمور مباشرة من الهاتف.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- INSTALLATION STEPS -->
        <div class="py-4 my-3">
            <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5" style="background: #FFFFFF;">
                <h4 class="fw-bold text-dark mb-4 text-center">خطوات تثبيت التطبيق على هاتفك (3 خطوات بسيطة)</h4>
                
                <div class="row g-4 text-center">
                    <div class="col-md-4">
                        <div class="p-3">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-bold mb-3" style="width: 44px; height: 44px; font-size: 1.2rem;">
                                1
                            </div>
                            <h6 class="fw-bold text-dark mb-2">تنزيل ملف التطبيق</h6>
                            <p class="text-muted small mb-0">اضغط على زر "تحميل ملف APK" وسيبدأ تحميل الملف الصغير (5MB) على هاتفك فوراً.</p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning text-dark fw-bold mb-3" style="width: 44px; height: 44px; font-size: 1.2rem;">
                                2
                            </div>
                            <h6 class="fw-bold text-dark mb-2">السماح بالتثبيت</h6>
                            <p class="text-muted small mb-0">افتح الملف المحمل واضغط "تثبيت" (Install). إذا طُلب منك السماح، اختر "السماح من هذا المصدر".</p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold mb-3" style="width: 44px; height: 44px; font-size: 1.2rem;">
                                3
                            </div>
                            <h6 class="fw-bold text-dark mb-2">فتح واستخدام التطبيق</h6>
                            <p class="text-muted small mb-0">ستظهر أيقونة التطبيق في شاشة هاتفك الرئيسية، افتحه واستمتع بتصفح دليل المدارس مباشرة!</p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="<?= $apkDownloadUrl ?>" download class="btn btn-luxury-primary px-5 py-3 fw-bold rounded-pill shadow-sm" style="font-size: 1rem;">
                        <i class="fab fa-android me-2"></i> تحميل ملف APK الآن (<?= $apkSize ?>)
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
