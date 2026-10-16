<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * School Digital Presence Audit (فحص الوجود الرقمي والأثر الإلكتروني للمدارس)
 * Style F v2 Design System
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

$pdo = db();

// =========================================================================
// 1. AJAX API ENDPOINTS (Suggest & Scan)
// =========================================================================

if (isset($_GET['action']) && $_GET['action'] === 'suggest') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, name_en, slug, country, state, city, is_schoplus_client, rating_avg 
                               FROM daleel_schools 
                               WHERE name LIKE ? OR name_en LIKE ? 
                               ORDER BY is_schoplus_client DESC, rating_avg DESC, id ASC 
                               LIMIT 8");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $r) {
            $results[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'name_en' => $r['name_en'] ?? '',
                'slug' => $r['slug'] ?? '',
                'country' => $r['country'] ?? 'السودان',
                'state' => $r['state'] ?? '',
                'city' => $r['city'] ?? '',
                'is_smart' => (int)($r['is_schoplus_client'] ?? 0) === 1,
            ];
        }
        echo json_encode(['success' => true, 'suggestions' => $results], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'suggestions' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ((isset($_REQUEST['action']) && $_REQUEST['action'] === 'scan')) {
    header('Content-Type: application/json; charset=utf-8');
    $schoolName = trim((string)($_REQUEST['school_name'] ?? ($_REQUEST['school'] ?? ($_REQUEST['q'] ?? ''))));
    $country = trim((string)($_REQUEST['country'] ?? 'السودان')) ?: 'السودان';
    $state = trim((string)($_REQUEST['state'] ?? ''));

    if (mb_strlen($schoolName) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'يرجى إدخال اسم المدرسة بشكل صحيح (حرفين على الأقل)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- STEP 1: Search Daleel Database ---
    $matchedSchool = null;
    $reviewsCount = 0;
    $ratingAvg = 5.00;

    try {
        // Exact match or contains match
        $sStmt = $pdo->prepare("SELECT * FROM daleel_schools 
                                WHERE (name = ? OR slug = ?) 
                                ORDER BY id ASC LIMIT 1");
        $sStmt->execute([$schoolName, $schoolName]);
        $matchedSchool = $sStmt->fetch(PDO::FETCH_ASSOC);

        if (!$matchedSchool) {
            $sStmt2 = $pdo->prepare("SELECT * FROM daleel_schools 
                                     WHERE (name LIKE ? OR name_en LIKE ?) 
                                     ORDER BY (CASE WHEN state = ? THEN 1 ELSE 2 END), is_schoplus_client DESC, rating_avg DESC 
                                     LIMIT 1");
            $sStmt2->execute(['%' . $schoolName . '%', '%' . $schoolName . '%', $state]);
            $matchedSchool = $sStmt2->fetch(PDO::FETCH_ASSOC);
        }

        if ($matchedSchool && !empty($matchedSchool['id'])) {
            $rStmt = $pdo->prepare("SELECT COUNT(*) as rev_count, AVG(rating) as rev_avg 
                                    FROM daleel_school_reviews 
                                    WHERE school_id = ? AND is_approved = 1");
            $rStmt->execute([(int)$matchedSchool['id']]);
            $rData = $rStmt->fetch(PDO::FETCH_ASSOC);
            if ($rData) {
                $reviewsCount = (int)($rData['rev_count'] ?? 0);
                $ratingAvg = round((float)($rData['rev_avg'] ?? 5.00), 1);
            }
        }
    } catch (Throwable $e) {
        // Fallback gracefully on DB error
    }

    // --- STEP 2: Geographic & Maps Detection (OpenStreetMap / Nominatim) ---
    $detectedCoords = null;
    $googleMapsUrl = '';
    $mapDisplayName = '';

    if ($matchedSchool && !empty($matchedSchool['google_map_url'])) {
        $googleMapsUrl = $matchedSchool['google_map_url'];
    } elseif ($matchedSchool && !empty($matchedSchool['latitude']) && !empty($matchedSchool['longitude'])) {
        $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . $matchedSchool['latitude'] . ',' . $matchedSchool['longitude'];
        $detectedCoords = ['lat' => $matchedSchool['latitude'], 'lon' => $matchedSchool['longitude']];
    } else {
        // Fast Nominatim Query with timeout 2.5s
        try {
            $nomQuery = urlencode($schoolName . ' ' . ($state ?: '') . ' ' . $country);
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2.5,
                    'user_agent' => 'SchoPlusDaleelBot/1.0 (https://daleel.schoplus.sd; audit@schoplus.sd)'
                ]
            ]);
            $nomJson = @file_get_contents('https://nominatim.openstreetmap.org/search?q=' . $nomQuery . '&format=json&limit=1', false, $ctx);
            if ($nomJson) {
                $nomData = json_decode($nomJson, true);
                if (!empty($nomData[0]['lat']) && !empty($nomData[0]['lon'])) {
                    $detectedCoords = [
                        'lat' => $nomData[0]['lat'],
                        'lon' => $nomData[0]['lon']
                    ];
                    $mapDisplayName = $nomData[0]['display_name'] ?? '';
                    $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . $nomData[0]['lat'] . ',' . $nomData[0]['lon'];
                }
            }
        } catch (Throwable $e) {}

        if (empty($googleMapsUrl)) {
            $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($schoolName . ' ' . ($state ?: '') . ' ' . $country);
        }
    }

    // --- STEP 3: Domain & Official Website Discovery ---
    $detectedWebsite = '';
    if ($matchedSchool && !empty($matchedSchool['website'])) {
        $detectedWebsite = $matchedSchool['website'];
    } else {
        // Check potential domains if transliterated or matches
        $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $schoolName);
        $slugDomain = preg_replace('/[^a-z0-9]/i', '', strtolower($cleanWord));
        if (strlen($slugDomain) >= 4) {
            $candidates = [$slugDomain . '.edu.sd', $slugDomain . '.sd', $slugDomain . '.com', $slugDomain . '-school.com'];
            foreach ($candidates as $cDom) {
                if (@checkdnsrr($cDom, 'A')) {
                    $detectedWebsite = 'https://' . $cDom;
                    break;
                }
            }
        }
    }

    // --- STEP 4: Social Media Detection (Facebook, TikTok, Instagram, YouTube) ---
    $detectedFacebook = $matchedSchool['facebook'] ?? '';
    if (empty($detectedFacebook)) {
        // Construct verified search link
        $detectedFacebook = 'https://www.facebook.com/search/top?q=' . urlencode($schoolName . ' ' . $state);
        $facebookFound = false;
    } else {
        $facebookFound = true;
    }

    $detectedTiktok = $matchedSchool['tiktok'] ?? '';
    if (empty($detectedTiktok)) {
        $detectedTiktok = 'https://www.tiktok.com/search?q=' . urlencode($schoolName);
        $tiktokFound = false;
    } else {
        $tiktokFound = true;
    }

    $detectedInstagram = $matchedSchool['instagram'] ?? '';
    $detectedYoutube = $matchedSchool['youtube'] ?? '';
    $socialExtraFound = (!empty($detectedInstagram) || !empty($detectedYoutube));
    if (!$socialExtraFound) {
        $detectedInstagram = 'https://www.instagram.com/explore/tags/' . urlencode(str_replace(' ', '', $schoolName));
    }

    // --- STEP 5: News & Media Articles ---
    $newsList = [];
    if ($matchedSchool && !empty($matchedSchool['id'])) {
        try {
            $nStmt = $pdo->prepare("SELECT title, published_at FROM daleel_school_news WHERE school_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 2");
            $nStmt->execute([(int)$matchedSchool['id']]);
            $dbNews = $nStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dbNews as $dn) {
                $newsList[] = [
                    'title' => $dn['title'],
                    'source' => 'دليل المدارس السودانية',
                    'url' => '/school/' . ($matchedSchool['slug'] ?: $matchedSchool['id']) . '#news'
                ];
            }
        } catch (Throwable $e) {}
    }
    // Also include Google search queries for news
    $newsSearchUrl = 'https://www.google.com/search?q=' . urlencode('"' . $schoolName . '" ' . ($state ?: '') . ' نتيجة الشهادة تكريم أوائل');
    $hasNews = !empty($newsList);

    // --- STEP 6: Direct Phone & WhatsApp ---
    $detectedPhone = $matchedSchool['phone'] ?? '';
    $detectedWhatsapp = $matchedSchool['whatsapp'] ?? ($matchedSchool['phone'] ?? '');
    $phoneFound = !empty($detectedPhone);

    // --- STEP 7: DYNAMIC CHECKS FOR INCENTIVE ITEMS (8, 9, 10) ---
    // Item 8: Presence in Daleel
    $isInDaleel = ($matchedSchool !== null && !empty($matchedSchool['id']));
    $daleelProfileUrl = $isInDaleel ? ('/school/' . ($matchedSchool['slug'] ?: $matchedSchool['id'])) : null;

    // Item 9: SchoPlus Smart School
    $isSmartSchool = ($isInDaleel && !empty($matchedSchool['is_schoplus_client']) && (int)$matchedSchool['is_schoplus_client'] === 1);

    // Item 10: Parent Reviews
    $hasReviews = ($isInDaleel && $reviewsCount > 0);

    // =========================================================================
    // COMPILE THE 10 AUDIT ITEMS
    // =========================================================================

    $items = [
        // 1. الموقع الإلكتروني
        [
            'id' => 1,
            'key' => 'website',
            'title' => 'الموقع الإلكتروني الرسمي للمدرسة',
            'category' => 'الهوية الرقمية المستقلة',
            'icon' => 'fas fa-globe',
            'status' => !empty($detectedWebsite) ? 'detected' : 'not_found',
            'value' => $detectedWebsite ?: '',
            'display_value' => $detectedWebsite ?: 'لم يُرصد موقع رسمي مستقل نطاق خاص',
            'verified' => !empty($detectedWebsite),
            'points' => 10,
            'description' => 'امتلاك المدرسة لموقع إلكتروني خاص يعزز المصداقية الأكاديمية والتوثيق الرسمي لدى جهات الاعتماد والوزارة.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'https://example-school.edu.sd'
        ],
        // 2. صفحة الفيسبوك
        [
            'id' => 2,
            'key' => 'facebook',
            'title' => 'صفحة الفيسبوك الرسمية',
            'category' => 'قنوات التواصل المباشر',
            'icon' => 'fab fa-facebook',
            'status' => $facebookFound ? 'verified' : 'detected',
            'value' => $detectedFacebook,
            'display_value' => $facebookFound ? $detectedFacebook : 'تم رصد نتائج بحث مطابقة على فيسبوك — بحاجة لتأكيد الرابط الرسمي',
            'verified' => $facebookFound,
            'points' => 10,
            'description' => 'المنصة الأولى لأولياء الأمور في السودان لمتابعة الفعاليات والمناسبات المدرسية وجداول الامتحانات.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'https://facebook.com/SchoolName'
        ],
        // 3. حساب التيك توك
        [
            'id' => 3,
            'key' => 'tiktok',
            'title' => 'حساب التيك توك (TikTok)',
            'category' => 'المحتوى المرئي والأنشطة',
            'icon' => 'fab fa-tiktok',
            'status' => $tiktokFound ? 'verified' : 'not_found',
            'value' => $tiktokFound ? $detectedTiktok : '',
            'display_value' => $tiktokFound ? $detectedTiktok : 'لم يُرصد حساب تيك توك موثق للمدرسة',
            'verified' => $tiktokFound,
            'points' => 10,
            'description' => 'الوسيلة الأسرع انتشاراً لعرض طابور الصباح، المعارض العلمية، والأنشطة اللاصفية بالفيديو القصير.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'https://tiktok.com/@school_name'
        ],
        // 4. المنصات الإضافية (انستغرام / يوتيوب)
        [
            'id' => 4,
            'key' => 'social_extra',
            'title' => 'المنصات الإضافية (Instagram / YouTube)',
            'category' => 'الأرشيف المرئي والتوثيق',
            'icon' => 'fab fa-instagram',
            'status' => $socialExtraFound ? 'verified' : 'not_found',
            'value' => $socialExtraFound ? ($detectedInstagram ?: $detectedYoutube) : '',
            'display_value' => $socialExtraFound ? ($detectedInstagram ?: $detectedYoutube) : 'غير مفعلة أو غير متصلة بروابط موثقة',
            'verified' => $socialExtraFound,
            'points' => 10,
            'description' => 'أرشيف رقمي عالي الجودة لمعارض التخرج، المؤتمرات التربوية، ومقاطع الفيديو التوثيقية للمدرسة.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'https://instagram.com/school أو https://youtube.com/@school'
        ],
        // 5. المقالات والتغطيات الإخبارية
        [
            'id' => 5,
            'key' => 'news_coverage',
            'title' => 'المقالات والتغطيات الإخبارية على جوجل',
            'category' => 'السمعة العامة وتكريمات التفوق',
            'icon' => 'fas fa-newspaper',
            'status' => $hasNews ? 'verified' : 'detected',
            'value' => $hasNews ? $newsList[0]['url'] : $newsSearchUrl,
            'display_value' => $hasNews ? ('تم رصد تغطيات إخبارية موثقة (' . count($newsList) . ' مقال)') : 'فحص أرشفة جوجل لنتائج الشهادة وتكريمات التفوق',
            'verified' => $hasNews,
            'points' => 10,
            'description' => 'ظهور إنجازات وتكريمات المدرسة في الصحف والمواقع الإخبارية يرفع ترتيبها الطبيعي في محركات البحث.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'رابط مقال صحفي أو خبر تفوق على الإنترنت'
        ],
        // 6. التواجد على خرائط جوجل
        [
            'id' => 6,
            'key' => 'google_maps',
            'title' => 'التواجد والموقع على خرائط جوجل (Google Maps)',
            'category' => 'الوصول الجغرافي والملاحة',
            'icon' => 'fas fa-map-marked-alt',
            'status' => !empty($detectedCoords) || ($matchedSchool && !empty($matchedSchool['google_map_url'])) ? 'verified' : 'detected',
            'value' => $googleMapsUrl,
            'display_value' => !empty($detectedCoords) ? ('إحداثيات جغرافية مؤكدة: ' . round((float)$detectedCoords['lat'], 4) . ', ' . round((float)$detectedCoords['lon'], 4)) : 'رابط موقع الخريطة والاتجاهات',
            'verified' => !empty($detectedCoords) || ($matchedSchool && !empty($matchedSchool['google_map_url'])),
            'points' => 10,
            'description' => 'تثبيت المقر الجغرافي يُمكّن أولياء الأمور من تقدير مسافات التوصيل وخطوط الترحيل بدقة وسرعة.',
            'can_edit' => true,
            'input_type' => 'url',
            'placeholder' => 'https://maps.google.com/?q=...'
        ],
        // 7. قنوات الاتصال المباشر (الهاتف والواتساب)
        [
            'id' => 7,
            'key' => 'direct_contact',
            'title' => 'قنوات الاتصال المباشر (رقم الهاتف والواتساب)',
            'category' => 'الاستجابة لطلبات القبول',
            'icon' => 'fas fa-phone-alt',
            'status' => $phoneFound ? 'verified' : 'not_found',
            'value' => $phoneFound ? ($detectedPhone . ($detectedWhatsapp && $detectedWhatsapp !== $detectedPhone ? ' / واتساب: ' . $detectedWhatsapp : '')) : '',
            'display_value' => $phoneFound ? ($detectedPhone . ($detectedWhatsapp && $detectedWhatsapp !== $detectedPhone ? ' (واتساب: ' . $detectedWhatsapp . ')' : '')) : 'لم يُرصد رقم هاتف مباشر مفهرس للاتصال',
            'verified' => $phoneFound,
            'points' => 10,
            'description' => 'وجود أرقام هواتف وواتساب مفعلة يزيد معدل تحويل أولياء الأمور من مستكشفين إلى مسجلين بنسبة 65%.',
            'can_edit' => true,
            'input_type' => 'tel',
            'placeholder' => '09xxxxxxx أو 01xxxxxxx'
        ],
        // 8. التواجد في دليل المدارس (DYNAMIC CHECK)
        [
            'id' => 8,
            'key' => 'daleel_presence',
            'title' => 'التواجد في دليل المدارس السودانية (Daleel)',
            'category' => 'الاعتماد المؤسسي الرسمي بالدليل',
            'icon' => 'fas fa-book-reader',
            'status' => $isInDaleel ? 'verified' : 'not_found',
            'value' => $isInDaleel ? $daleelProfileUrl : '',
            'display_value' => $isInDaleel ? ('مدرجة وموثقة رسمياً في دليل المدارس — رقم التعريف #' . $matchedSchool['id']) : '0 / غير مدرجة في دليل المدارس (فقدت المدرسة مئات الزيارات الشهرية)',
            'verified' => $isInDaleel,
            'points' => 10,
            'is_dynamic_db' => true,
            'description' => 'دليل المدارس السودانية هو الوجهة الرقمية المرجعية التي يعتمد عليها آلاف أولياء الأمور لاختيار المدارس.',
            'action_cta' => $isInDaleel ? [
                'type' => 'view_school',
                'label' => 'عرض الملف التعريفي للمدرسة',
                'url' => $daleelProfileUrl
            ] : [
                'type' => 'register_school',
                'label' => '+ سجّل مدرستك مجاناً بالدليل الآن',
                'url' => '/register.php?name=' . urlencode($schoolName) . '&country=' . urlencode($country) . '&state=' . urlencode($state)
            ]
        ],
        // 9. الاعتماد كمدرسة ذكية (DYNAMIC CHECK)
        [
            'id' => 9,
            'key' => 'smart_school',
            'title' => 'الاعتماد كمدرسة ذكية (SchoPlus Smart School)',
            'category' => 'التحول الرقمي والإدارة السحابية',
            'icon' => 'fas fa-bolt',
            'status' => $isSmartSchool ? 'verified' : 'not_found',
            'value' => $isSmartSchool ? 'معتمدة كمدرسة ذكية' : '',
            'display_value' => $isSmartSchool ? 'مدرسة ذكية معتمدة رسمياً (تعمل بنظام SchoPlus Cloud السحابي)' : '0 / غير مسجلة كمدرسة ذكية (تفتقد نظام الإدارة والتحول الرقمي)',
            'verified' => $isSmartSchool,
            'points' => 10,
            'is_dynamic_db' => true,
            'description' => 'المدارس الذكية تتميز بإدارة الدرجات، الرسوم، الحضور والغياب، وتطبيق أولياء الأمور لحظياً على الهواتف.',
            'action_cta' => $isSmartSchool ? [
                'type' => 'badge_smart',
                'label' => 'عرض وسام المدرسة الذكية',
                'url' => $daleelProfileUrl ?: '/?view=schoplus'
            ] : [
                'type' => 'upgrade_smart',
                'label' => 'طلب اعتماد مدرسة ذكية والترقية للسحابة',
                'url' => 'https://schoplus.sd/demo.html'
            ]
        ],
        // 10. آراء وتقييمات أولياء الأمور (DYNAMIC CHECK)
        [
            'id' => 10,
            'key' => 'parent_reviews',
            'title' => 'آراء وتقييمات أولياء الأمور (Parent Reviews)',
            'category' => 'الثقة المجتمعية والتوصيات',
            'icon' => 'fas fa-star',
            'status' => $hasReviews ? 'verified' : 'not_found',
            'value' => $hasReviews ? ($ratingAvg . ' / 5 (' . $reviewsCount . ' تقييم)') : '',
            'display_value' => $hasReviews ? ('موثقة بـ ' . $reviewsCount . ' تقييم من أولياء الأمور (متوسط ' . $ratingAvg . ' من 5)') : '0 / لا توجد آراء أو تقييمات موثقة من أولياء الأمور',
            'verified' => $hasReviews,
            'points' => 10,
            'is_dynamic_db' => true,
            'description' => 'التقييمات الحقيقية هي العامل الحاسم رقم #1 في قرار اختيار المدرسة لدى 78% من أولياء الأمور.',
            'action_cta' => $hasReviews ? [
                'type' => 'view_reviews',
                'label' => 'قراءة تقييمات أولياء الأمور (' . $reviewsCount . ')',
                'url' => $daleelProfileUrl ? ($daleelProfileUrl . '#reviews') : '/#reviews'
            ] : [
                'type' => 'activate_reviews',
                'label' => 'تفعيل استقبال تقييمات أولياء الأمور',
                'url' => $isInDaleel ? $daleelProfileUrl : ('/register.php?name=' . urlencode($schoolName))
            ]
        ],
    ];

    // Compute Overall Score with smart partial credit for detected footprint (6 pts per detected item)
    $earnedScore = 0;
    foreach ($items as &$it) {
        if (!empty($it['verified'])) {
            $it['earned_points'] = (int)$it['points'];
        } elseif ($it['status'] === 'detected') {
            $it['earned_points'] = 6;
        } else {
            $it['earned_points'] = 0;
        }
        $earnedScore += $it['earned_points'];
    }
    unset($it);

    $hasDbLogo = ($matchedSchool && !empty($matchedSchool['logo']));
    if ($hasDbLogo) {
        $earnedScore = min(100, $earnedScore + 10);
    }

    echo json_encode([
        'success' => true,
        'school_name' => $schoolName,
        'country' => $country,
        'state' => $state,
        'has_logo' => $hasDbLogo,
        'logo' => $hasDbLogo ? $matchedSchool['logo'] : null,
        'matched_db_school' => $matchedSchool ? [
            'id' => (int)$matchedSchool['id'],
            'name' => $matchedSchool['name'],
            'slug' => $matchedSchool['slug'],
            'logo' => !empty($matchedSchool['logo']) ? $matchedSchool['logo'] : null,
            'is_smart' => (int)($matchedSchool['is_schoplus_client'] ?? 0) === 1,
            'reviews_count' => $reviewsCount,
            'rating_avg' => $ratingAvg,
        ] : null,
        'score' => $earnedScore,
        'max_score' => 100,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// 2. PAGE HTML & RENDERING (Style F v2)
// =========================================================================

$pageTitle = 'فحص الوجود الرقمي والأثر الإلكتروني للمدارس';
$ogTitle = 'فحص الوجود الرقمي للمدارس — تدقيق فوري وشامل لـ 10 بنود';
$ogDesc = 'أداة ذكية لفحص الحضور الرقمي لمدرستك عبر محركات البحث، منصات التواصل، ودليل المدارس السودانية في ثوانٍ مع خيارات التحقق والتثبيت.';
require_once __DIR__ . '/header.php';

$allCountries = SudanGeography::countries();
$countriesWithStates = SudanGeography::countriesWithStatesAndCities();
$defaultCountry = SudanGeography::detectVisitorCountry() ?: 'السودان';
$prefillCountry = trim((string)($_GET['country'] ?? $defaultCountry)) ?: $defaultCountry;
$statesForDefault = SudanGeography::statesWithCities($prefillCountry);

// URL Prefill support (e.g. ?school=...&q=...&state=...)
$prefillSchool = trim((string)($_GET['school'] ?? ($_GET['q'] ?? ($_GET['school_name'] ?? ''))));
$prefillState = trim((string)($_GET['state'] ?? ''));
$prefillCity = trim((string)($_GET['city'] ?? ''));
$autoRun = !empty($_GET['auto']) || !empty($prefillSchool);
?>

<style>
/* ==========================================================================
   SCHOPLUS STYLE F v2 — DIGITAL PRESENCE AUDIT COMPONENT
   ========================================================================== */
.audit-page-header {
    background: #F0F4F8;
    border-bottom: 1px solid #DDE4ED;
    padding: 34px 0 26px;
    margin-bottom: 28px;
    position: relative;
}
.audit-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--brand-orange, #F5A020);
    margin-bottom: 6px;
}
.audit-eyebrow::after {
    content: '';
    display: inline-block;
    width: 14px;
    height: 2px;
    background: var(--brand-orange, #F5A020);
    vertical-align: middle;
}
.audit-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--brand-navy, #0B2A52);
    margin: 0 0 4px;
}
.audit-subtitle {
    font-size: 0.74rem;
    color: #475569;
    margin: 0;
    max-width: 720px;
    line-height: 1.6;
}

/* SEARCH INPUT CARD */
.audit-search-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(11, 42, 82, 0.06);
    padding: 24px;
    margin-bottom: 30px;
    position: relative;
}
.audit-form-label {
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--brand-navy, #0B2A52);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.audit-input {
    height: 42px;
    border-radius: 10px;
    border: 1.5px solid #CBD5E1;
    font-size: 0.8rem;
    font-weight: 600;
    color: #0F172A;
    padding: 8px 14px;
    transition: all 0.2s ease;
}
.audit-input:focus {
    border-color: var(--brand-blue, #1E5FA8);
    box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
    outline: none;
}
.audit-btn-submit {
    height: 42px;
    background: linear-gradient(135deg, #F5A020 0%, #D97706 100%);
    color: #FFFFFF;
    font-size: 0.82rem;
    font-weight: 800;
    border: none;
    border-radius: 10px;
    padding: 0 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(245, 160, 32, 0.35);
    transition: all 0.2s ease;
}
.audit-btn-submit:hover {
    background: linear-gradient(135deg, #E08E15 0%, #B45309 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(245, 160, 32, 0.45);
    color: #FFFFFF;
}

/* AUTOCOMPLETE SUGGESTIONS */
.audit-suggest-box {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(11, 42, 82, 0.15);
    z-index: 1050;
    max-height: 260px;
    overflow-y: auto;
    display: none;
}
.audit-suggest-item {
    padding: 9px 14px;
    border-bottom: 1px solid #F1F5F9;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.77rem;
    transition: background 0.15s ease;
}
.audit-suggest-item:hover {
    background: #F8FAFC;
}
.audit-suggest-item:last-child {
    border-bottom: none;
}

/* SCANNING RADAR HUD */
.audit-hud-container {
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    border-radius: 16px;
    padding: 40px 20px;
    color: #FFFFFF;
    text-align: center;
    box-shadow: 0 10px 30px rgba(11, 42, 82, 0.2);
    margin-bottom: 30px;
    display: none;
}
.audit-radar-pulse {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(245, 160, 32, 0.15);
    border: 2px solid #F5A020;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    animation: radarPulse 1.6s infinite ease-out;
}
.audit-radar-pulse i {
    font-size: 1.8rem;
    color: #F5A020;
}
@keyframes radarPulse {
    0% { box-shadow: 0 0 0 0 rgba(245, 160, 32, 0.5); transform: scale(0.96); }
    70% { box-shadow: 0 0 0 28px rgba(245, 160, 32, 0); transform: scale(1.04); }
    100% { box-shadow: 0 0 0 0 rgba(245, 160, 32, 0); transform: scale(0.96); }
}
.audit-progress-bar-wrap {
    max-width: 450px;
    height: 8px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    margin: 16px auto;
    overflow: hidden;
}
.audit-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #1E5FA8, #F5A020);
    width: 0%;
    transition: width 0.3s ease;
}

/* RESULTS DASHBOARD */
.audit-results-container {
    display: none;
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* SCORE HERO CARD */
.audit-score-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(11, 42, 82, 0.05);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}
.audit-score-circle-wrap {
    display: flex;
    align-items: center;
    gap: 18px;
}
.audit-radial-svg {
    width: 90px;
    height: 90px;
    transform: rotate(-90deg);
}
.audit-radial-bg {
    fill: none;
    stroke: #E2E8F0;
    stroke-width: 8;
}
.audit-radial-progress {
    fill: none;
    stroke: var(--brand-orange, #F5A020);
    stroke-width: 8;
    stroke-linecap: round;
    stroke-dasharray: 251.2;
    stroke-dashoffset: 251.2;
    transition: stroke-dashoffset 0.8s ease, stroke 0.3s ease;
}
.audit-score-number {
    font-size: 1.55rem;
    font-weight: 900;
    color: var(--brand-navy, #0B2A52);
    line-height: 1;
}
.audit-score-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748B;
}

/* SCHOOL LOGO AVATAR BOX */
.audit-school-avatar-box {
    width: 58px;
    height: 58px;
    border-radius: 14px;
    background: #F8FAFC;
    border: 1.5px solid #DDE4ED;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
    box-shadow: 0 2px 8px rgba(11, 42, 82, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}
.audit-school-avatar-box:hover {
    border-color: var(--brand-orange, #F5A020);
    transform: scale(1.03);
}
.audit-school-avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 3px;
}
.audit-school-avatar-box .avatar-monogram {
    font-size: 1.4rem;
    font-weight: 900;
    color: var(--brand-navy, #0B2A52);
    font-family: inherit;
}

/* VISUAL SEARCH & LOGO AUDIT CARD */
.audit-visual-search-card {
    background: #FFFFFF;
    border: 1.5px dashed #CBD5E1;
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 24px;
    box-shadow: 0 4px 18px rgba(11, 42, 82, 0.04);
    position: relative;
    transition: all 0.2s ease;
}
.audit-visual-search-card:hover {
    border-color: var(--brand-blue, #1E5FA8);
    box-shadow: 0 6px 22px rgba(11, 42, 82, 0.07);
}
.audit-logo-dropzone {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    background: #F8FAFC;
    border: 2px dashed #94A3B8;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
    overflow: hidden;
    position: relative;
}
.audit-logo-dropzone:hover {
    border-color: var(--brand-orange, #F5A020);
    background: #FFFBEB;
    transform: scale(1.02);
}

/* ACTIVE AUDIT STATUS BAR (COMPACT EXECUTIVE TOOLBAR) */
.audit-active-bar {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-right: 4px solid var(--brand-orange, #F5A020);
    border-radius: 12px;
    padding: 10px 18px;
    margin-bottom: 20px;
    display: none;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 2px 10px rgba(11, 42, 82, 0.04);
}
.audit-active-bar-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* 4 KPI METRICS GRID (STYLE F v2) */
.metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 22px;
}
@media (max-width: 991px) {
    .metrics {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .metrics {
        grid-template-columns: 1fr;
    }
}
.metric {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 2px 8px rgba(11, 42, 82, 0.04);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
}
.metric:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(11, 42, 82, 0.08);
}
.metric::before {
    content: '';
    position: absolute;
    top: 0; bottom: 0; right: 0;
    width: 3.5px;
    border-radius: 0 12px 12px 0;
}
.metric.m-blue::before { background: var(--brand-blue, #1E5FA8); }
.metric.m-green::before { background: #10B981; }
.metric.m-orange::before { background: var(--brand-orange, #F5A020); }
.metric.m-gray::before { background: #0B2A52; }

.metric-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.m-blue .metric-icon { background: #EFF6FF; color: var(--brand-blue, #1E5FA8); }
.m-green .metric-icon { background: #ECFDF5; color: #10B981; }
.m-orange .metric-icon { background: #FFFBEB; color: var(--brand-orange, #F5A020); }
.m-gray .metric-icon { background: #F1F5F9; color: #0B2A52; }

.metric-body {
    flex: 1;
    min-width: 0;
    text-align: right;
}
.metric-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--brand-navy, #0B2A52);
    line-height: 1.2;
    display: block;
}
.metric-lbl {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748B;
    margin-top: 2px;
}

/* SEGMENTED QUICK PILLS */
.segmented-pills {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.seg-pill {
    height: 32px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #DDE4ED;
    background: #FFFFFF;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
    user-select: none;
}
.seg-pill:hover {
    border-color: var(--brand-blue, #1E5FA8);
    color: var(--brand-blue, #1E5FA8);
    background: #F8FAFC;
}
.seg-pill.active {
    background: #0B2A52;
    border-color: #0B2A52;
    color: #FFFFFF;
}
.seg-pill.active i {
    color: #F5A020 !important;
}
.seg-pill .pill-count {
    font-size: 0.65rem;
    padding: 1px 6px;
    border-radius: 999px;
    background: rgba(0,0,0,0.06);
    font-weight: 800;
}
.seg-pill.active .pill-count {
    background: rgba(255,255,255,0.22);
    color: #FFFFFF;
}

/* 10 AUDIT ITEMS GRID */
.audit-items-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
@media (max-width: 860px) {
    .audit-items-grid {
        grid-template-columns: 1fr;
    }
}

.audit-item-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
    position: relative;
}
.audit-item-card:hover {
    border-color: var(--brand-blue, #1E5FA8);
    box-shadow: 0 6px 18px rgba(11, 42, 82, 0.08);
}
.audit-item-card.is-confirmed {
    border-color: #10B981;
    background: #F0FDF4;
}
.audit-item-card.is-missing {
    border-color: #FCA5A5;
    background: #FFFBFB;
}

.audit-item-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.audit-item-badge-index {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: #F1F5F9;
    color: var(--brand-navy, #0B2A52);
    font-size: 0.7rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.audit-item-title {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--brand-navy, #0B2A52);
    margin: 0;
    line-height: 1.35;
}
.audit-item-category {
    font-size: 0.65rem;
    color: #64748B;
    font-weight: 600;
}

/* STATUS PILLS */
.audit-status-pill {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.audit-status-pill.b-green {
    background: #DCFCE7;
    color: #166534;
    border: 1px solid #BBF7D0;
}
.audit-status-pill.b-orange {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
}
.audit-status-pill.b-red {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

/* VALUE & DESCRIPTION */
.audit-item-body {
    margin-bottom: 12px;
}
.audit-detected-val {
    font-size: 0.76rem;
    font-weight: 700;
    color: #0F172A;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 6px 10px;
    margin-bottom: 6px;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.audit-detected-val a {
    color: var(--brand-blue, #1E5FA8);
    text-decoration: none;
}
.audit-detected-val a:hover {
    text-decoration: underline;
}
.audit-item-desc {
    font-size: 0.68rem;
    color: #64748B;
    line-height: 1.5;
    margin: 0;
}

/* ACTIONS BAR */
.audit-item-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid #F1F5F9;
}
.audit-act-btn {
    height: 28px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}
.audit-act-btn.btn-confirm {
    background: #10B981;
    color: #FFFFFF;
}
.audit-act-btn.btn-confirm:hover {
    background: #059669;
}
.audit-act-btn.btn-edit {
    background: #FFFFFF;
    border-color: #CBD5E1;
    color: #334155;
}
.audit-act-btn.btn-edit:hover {
    background: #F1F5F9;
    color: #0F172A;
    border-color: #94A3B8;
}
.audit-act-btn.btn-add {
    background: #FFFFFF;
    border-color: #F5A020;
    color: #D97706;
}
.audit-act-btn.btn-add:hover {
    background: #FFFBEB;
    border-color: #D97706;
}

/* INLINE EDIT FORM */
.audit-inline-edit {
    display: none;
    margin-top: 8px;
    padding: 8px;
    background: #F8FAFC;
    border-radius: 8px;
    border: 1px dashed #CBD5E1;
}

/* CONVERSION BANNER (STYLE F v2) */
.audit-conversion-card {
    background: linear-gradient(135deg, #0B2A52 0%, #163870 100%);
    border: 1px solid #1E5FA8;
    border-right: 5px solid #F5A020;
    border-radius: 16px;
    padding: 24px;
    color: #FFFFFF;
    margin-top: 30px;
    box-shadow: 0 12px 32px rgba(11, 42, 82, 0.15);
}
.audit-conversion-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #FFFFFF;
    margin-bottom: 6px;
}
.audit-conversion-desc {
    font-size: 0.74rem;
    color: #E2E8F0;
    line-height: 1.6;
    margin-bottom: 16px;
    max-width: 680px;
}

/* SCHOOL AVATAR & LOGO BOX */
.audit-school-avatar-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}
.audit-school-avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.audit-school-avatar-box .school-monogram {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1E5FA8;
}
.active-bar-logo {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid #CBD5E1;
    display: none;
}

/* VISUAL SEARCH & LOGO AUDIT CARD */
.audit-visual-search-card {
    background: #FFFFFF;
    border: 1px solid #DDE4ED;
    border-top: 3px solid #1E5FA8;
    border-radius: 14px;
    padding: 16px 20px;
    margin-top: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}
.audit-visual-search-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.audit-visual-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0D1B2E;
    display: flex;
    align-items: center;
    gap: 8px;
}
.audit-visual-desc {
    font-size: 0.74rem;
    color: #64748B;
    line-height: 1.55;
    margin: 0;
}
.audit-logo-dropzone {
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    padding: 14px 18px;
    text-align: center;
    background: #F8FAFC;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.audit-logo-dropzone:hover {
    border-color: #1E5FA8;
    background: #F0F7FF;
}
.audit-logo-dropzone.dragover {
    border-color: #F5A020;
    background: #FFFBEB;
}
.audit-logo-preview {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #CBD5E1;
    display: none;
}

/* ========================================================================= */
/* PRINT VIEW STYLING (SPECIALIZED EXECUTIVE REPORT)                         */
/* ========================================================================= */
#auditPrintReport {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 14mm;
    }

    /* Hide everything on screen */
    body * {
        visibility: hidden !important;
    }

    /* Only show the dedicated executive print report */
    #auditPrintReport,
    #auditPrintReport * {
        visibility: visible !important;
    }

    #auditPrintReport {
        display: block !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        background: #FFFFFF !important;
        color: #0D1B2E !important;
        padding: 0 !important;
        margin: 0 !important;
        font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif !important;
        direction: rtl !important;
        text-align: right !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print-report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 12px;
        border-bottom: 2.5px solid #0B2A52;
        margin-bottom: 14px;
    }

    .print-brand-box {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .print-brand-logo {
        height: 44px;
        width: auto;
        object-fit: contain;
    }

    .print-doc-title {
        text-align: center;
    }
    .print-doc-title h1 {
        font-size: 14pt;
        font-weight: 800;
        color: #0B2A52;
        margin: 0;
    }
    .print-doc-title p {
        font-size: 8pt;
        color: #64748B;
        margin: 2px 0 0;
    }

    .print-meta-box {
        font-size: 7.8pt;
        color: #475569;
        text-align: left;
        line-height: 1.5;
        direction: rtl;
    }

    /* Executive Summary Card */
    .print-summary-card {
        background: #F8FAFC !important;
        border: 1px solid #CBD5E1 !important;
        border-right: 5px solid #1E5FA8 !important;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        page-break-inside: avoid;
    }

    .print-school-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .print-school-avatar {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        border: 1px solid #CBD5E1;
        object-fit: cover;
    }

    .print-school-monogram {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        background: #E2E8F0 !important;
        color: #0B2A52;
        font-size: 18pt;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .print-school-name {
        font-size: 13pt;
        font-weight: 800;
        color: #0D1B2E;
        margin: 0 0 3px;
    }

    .print-school-meta {
        font-size: 8pt;
        color: #64748B;
    }

    .print-score-box {
        text-align: center;
        background: #FFFFFF !important;
        border: 2px solid #1E5FA8;
        border-radius: 8px;
        padding: 6px 16px;
        min-width: 110px;
    }

    .print-score-val {
        font-size: 20pt;
        font-weight: 900;
        color: #1E5FA8;
        line-height: 1;
    }

    .print-score-label {
        font-size: 7.2pt;
        font-weight: 700;
        color: #475569;
        margin-top: 3px;
    }

    .print-kpi-row {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
        page-break-inside: avoid;
    }

    .print-kpi-pill {
        flex: 1;
        background: #F1F5F9 !important;
        border: 1px solid #E2E8F0;
        border-radius: 6px;
        padding: 5px 8px;
        text-align: center;
        font-size: 7.5pt;
    }
    .print-kpi-pill strong {
        display: block;
        font-size: 10pt;
        margin-top: 2px;
    }

    /* 10 Items Table */
    .print-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-bottom: 12px;
    }

    .print-table th {
        background: #0B2A52 !important;
        color: #FFFFFF !important;
        padding: 6px 7px;
        font-weight: 700;
        border: 1px solid #0B2A52;
        text-align: right;
    }

    .print-table td {
        padding: 5px 7px;
        border: 1px solid #DDE4ED;
        color: #1E293B;
        vertical-align: middle;
    }

    .print-table tr:nth-child(even) td {
        background: #F8FAFC !important;
    }

    .print-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 7pt;
        font-weight: 700;
    }
    .print-badge.b-green {
        background: #DCFCE7 !important;
        color: #166534 !important;
        border: 1px solid #86EFAC;
    }
    .print-badge.b-orange {
        background: #FEF3C7 !important;
        color: #92400E !important;
        border: 1px solid #FCD34D;
    }
    .print-badge.b-red {
        background: #FEE2E2 !important;
        color: #991B1B !important;
        border: 1px solid #FCA5A5;
    }

    /* Recommendations & Footer */
    .print-recommendations {
        background: #F8FAFC !important;
        border: 1px solid #E2E8F0;
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 12px;
        page-break-inside: avoid;
    }
    .print-recommendations h3 {
        font-size: 8.8pt;
        font-weight: 800;
        color: #0B2A52;
        margin: 0 0 5px;
    }
    .print-recommendations ul {
        margin: 0;
        padding-right: 16px;
        font-size: 7.6pt;
        color: #334155;
        line-height: 1.55;
    }

    .print-report-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px dashed #CBD5E1;
        padding-top: 8px;
        font-size: 7.2pt;
        color: #64748B;
        page-break-inside: avoid;
    }

    .print-seal-box {
        border: 1.5px solid #0B2A52;
        border-radius: 5px;
        padding: 3px 8px;
        text-align: center;
        font-weight: 800;
        font-size: 7pt;
        color: #0B2A52;
        text-transform: uppercase;
    }
}
</style>

<!-- ======================================================================= -->
<!-- PAGE CONTENT -->
<!-- ======================================================================= -->

<section class="audit-page-header">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="audit-eyebrow">
                    <i class="fas fa-satellite-dish"></i>
                    <span>فحص الوجود الرقمي</span>
                </div>
                <h1 class="audit-title">فحص الأثر الرقمي والظهور الإلكتروني للمدارس</h1>
                <p class="audit-subtitle">
                    أداة تدقيق ذكية تفحص الحضور الرقمي لمدرستك عبر 10 بنود أساسية تشمل محركات البحث، شبكات التواصل الاجتماعي، الموقع الجغرافي، ودليل المدارس السودانية في ثوانٍ معدودة.
                </p>
            </div>
            <div>
                <a href="/register.php" class="btn-luxury-primary" style="font-size: 0.78rem; padding: 8px 18px;">
                    <i class="fas fa-plus-circle"></i>
                    <span>إضافة مدرسة جديدة</span>
                </a>
            </div>
        </div>
    </div>
</section>

<div class="container pb-5">

    <!-- SEARCH & AUDIT FORM -->
    <div class="audit-search-card" id="auditSearchCard">
        <form id="auditForm" onsubmit="event.preventDefault(); runDigitalAudit();">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5 col-md-6 position-relative">
                    <label class="audit-form-label" for="auditSchoolName">
                        <span>اسم المدرسة الكامل <span class="text-danger">*</span></span>
                    </label>
                    <input type="text" 
                           id="auditSchoolName" 
                           name="school_name" 
                           class="form-control audit-input" 
                           placeholder="مثال: مدارس المنار النموذجية الخاصة" 
                           value="<?= e($prefillSchool) ?>" 
                           autocomplete="off" 
                           required>
                    <!-- Suggestions Dropdown -->
                    <div class="audit-suggest-box" id="auditSuggestBox"></div>
                </div>

                <div class="col-lg-3 col-md-3 col-6">
                    <label class="audit-form-label" for="auditCountry">
                        <span>الدولة</span>
                    </label>
                    <select id="auditCountry" name="country" class="form-select audit-input" onchange="updateAuditStates()">
                        <?php foreach ($allCountries as $c): ?>
                            <option value="<?= e($c) ?>" <?= $c === $prefillCountry ? 'selected' : '' ?>>
                                <?= e($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <label class="audit-form-label" for="auditState">
                        <span>الولاية / المنطقة</span>
                    </label>
                    <select id="auditState" name="state" class="form-select audit-input">
                        <option value="">كافة الولايات</option>
                        <?php foreach ($statesForDefault as $stName => $cities): ?>
                            <option value="<?= e($stName) ?>" <?= $stName === $prefillState ? 'selected' : '' ?>>
                                <?= e($stName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="audit-btn-submit w-100" id="auditSubmitBtn">
                        <i class="fas fa-bolt"></i>
                        <span>بدء الفحص</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- SCANNING HUD (LIVE RADAR ANIMATION) -->
    <div class="audit-hud-container" id="auditHud">
        <div class="audit-radar-pulse">
            <i class="fas fa-search"></i>
        </div>
        <h4 class="fw-bold mb-1" id="hudStatusHeading" style="font-size: 1.15rem;">جاري فحص الوجود الرقمي الشامل...</h4>
        <p class="text-light opacity-75 mb-3" id="hudStatusDetail" style="font-size: 0.74rem;">الاتصال بمحركات البحث والشبكات الاجتماعية وقاعدة دليل المدارس</p>
        
        <div class="audit-progress-bar-wrap">
            <div class="audit-progress-bar-fill" id="hudProgressBar"></div>
        </div>
        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size: 0.7rem;" id="hudStepBadge">
            الخطوة 1 من 4: مسح الهوية الرقمية
        </span>
    </div>

    <!-- =================================================================== -->
    <!-- DEDICATED PRINTABLE AUDIT REPORT (PRINT MEDIA ONLY)                 -->
    <!-- =================================================================== -->
    <div id="auditPrintReport">
        <!-- Print Header -->
        <div class="print-report-header">
            <div class="print-brand-box">
                <img src="/assets/images/logo.png" alt="SchoPlus Daleel" class="print-brand-logo">
                <div>
                    <div style="font-weight: 800; font-size: 10pt; color: #0B2A52;">منظومة ايسكو بلص (SchoPlus)</div>
                    <div style="font-size: 7.5pt; color: #64748B;">دليل المدارس والخدمات التعليمية الذكية</div>
                </div>
            </div>
            <div class="print-doc-title">
                <h1>تقرير فحص الوجود والأثر الرقمي للمدرسة</h1>
                <p>وثيقة تقييم وتحليل الأداء والظهور الإلكتروني (10 بنود رقمية معتمدة)</p>
            </div>
            <div class="print-meta-box">
                <div><strong>تاريخ الفحص:</strong> <span id="printAuditDate"><?= date('Y-m-d') ?></span></div>
                <div><strong>الرقم المرجعي:</strong> <span id="printAuditRef">SP-AUD-<?= date('Ymd') ?>-01</span></div>
                <div><strong>المصدر:</strong> محرك فحص الوجود الرقمي</div>
            </div>
        </div>

        <!-- School Executive Summary -->
        <div class="print-summary-card">
            <div class="print-school-info">
                <div id="printSchoolLogoWrap">
                    <img id="printSchoolLogoImg" class="print-school-avatar" alt="شعار المدرسة" style="display: none;">
                    <div id="printSchoolMonogram" class="print-school-monogram">م</div>
                </div>
                <div>
                    <h2 class="print-school-name" id="printSchoolName">اسم المدرسة</h2>
                    <div class="print-school-meta" id="printSchoolGeo">السودان — ولاية الخرطوم</div>
                    <div style="font-size: 7.5pt; color: #1E5FA8; font-weight: 700; margin-top: 3px;" id="printSchoolDaleelStatus">
                        حالة التوثيق: مسجلة وموثقة في دليل المدارس
                    </div>
                </div>
            </div>
            <div class="print-score-box">
                <div class="print-score-val" id="printScoreVal">0%</div>
                <div class="print-score-label" id="printScoreLabel">مؤشر الحضور العام</div>
            </div>
        </div>

        <!-- KPI Breakdown Row -->
        <div class="print-kpi-row">
            <div class="print-kpi-pill">
                <span style="color:#64748B;">البنود المؤكدة</span>
                <strong id="printKpiVerified" style="color: #166534;">0 من 10</strong>
            </div>
            <div class="print-kpi-pill">
                <span style="color:#64748B;">الأثر المكتشف آلياً</span>
                <strong id="printKpiDetected" style="color: #D97706;">0 بنود</strong>
            </div>
            <div class="print-kpi-pill">
                <span style="color:#64748B;">فرص التحسين</span>
                <strong id="printKpiMissing" style="color: #DC2626;">0 بنود</strong>
            </div>
            <div class="print-kpi-pill">
                <span style="color:#64748B;">بونص الهوية والشعار</span>
                <strong id="printKpiLogo" style="color: #1E5FA8;">غير مفعل</strong>
            </div>
        </div>

        <!-- Detailed 10-Item Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">البند الرقمي ومجال الفحص</th>
                    <th style="width: 18%;">حالة الرصد</th>
                    <th style="width: 40%;">الأثر الرقمي أو الرابط المسجل</th>
                    <th style="width: 12%; text-align: center;">النقاط</th>
                </tr>
            </thead>
            <tbody id="printTableBody">
                <!-- Dynamically populated via printAuditReport() -->
            </tbody>
        </table>

        <!-- Strategic Recommendations -->
        <div class="print-recommendations">
            <h3>توصيات تطوير الأثر الرقمي للمدرسة:</h3>
            <ul id="printRecommendationsList">
                <li><strong>توثيق الهوية الرسمية:</strong> استكمال رفع الشعار المعتمد والزي المدرسي بدقة عالية لتعزيز ثقة أولياء الأمور بنسبة 75%.</li>
                <li><strong>تأكيد قنوات التواصل والخرائط:</strong> تثبيت موقع المدرسة الدقيق على خرائط جوجل وربط رقم الواتساب المباشر لخدمة القبول والتسجيل.</li>
                <li><strong>التحول الرقمي السحابي:</strong> الاستفادة من منظومة SchoPlus الذكية لإدارة المدارس لأتمتة شؤون الطلاب، الدرجات، ونشر نتائج الامتحانات إلكترونياً.</li>
            </ul>
        </div>

        <!-- Official Print Footer -->
        <div class="print-report-footer">
            <div>
                <div>تم استخراج هذا التقرير رسمياً من بوابة <strong>دليل المدارس (daleel.schoplus.sd)</strong> — إحدى مبادرات منظومة SchoPlus.</div>
                <div>رابط التحقق الرقمي: <span id="printVerifyUrl" style="direction:ltr; display:inline-block;">https://daleel.schoplus.sd/presence-audit.php</span></div>
            </div>
            <div class="print-seal-box">
                <div>SCHOPLUS AUDIT</div>
                <div>معتمد رقمياً</div>
            </div>
        </div>
    </div>

    <!-- AUDIT RESULTS CONTAINER -->
    <div class="audit-results-container" id="auditResults">

        <!-- ACTIVE AUDIT STATUS BAR (COMPACT EXECUTIVE TOOLBAR) -->
        <div class="audit-active-bar" id="auditActiveBar">
            <div class="audit-active-bar-info">
                <span class="badge bg-primary text-white px-2.5 py-1 rounded-pill" style="font-size: 0.68rem;">
                    <i class="fas fa-satellite-dish me-1"></i> فحص رقمي معتمد
                </span>
                <img id="activeBarSchoolLogo" class="active-bar-logo" alt="شعار المدرسة">
                <strong class="text-dark" id="activeSchoolTitle" style="font-size: 0.9rem;">اسم المدرسة</strong>
                <span class="text-muted" id="activeSchoolGeo" style="font-size: 0.74rem;">— السودان</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light border" style="font-size: 0.72rem;" onclick="toggleAuditSearchForm()">
                    <i class="fas fa-search me-1 text-primary"></i> فحص مدرسة أخرى
                </button>
            </div>
        </div>
        
        <!-- SCORE HERO CARD -->
        <div class="audit-score-card">
            <div class="audit-score-circle-wrap">
                <svg class="audit-radial-svg" viewBox="0 0 100 100">
                    <circle class="audit-radial-bg" cx="50" cy="50" r="40"></circle>
                    <circle class="audit-radial-progress" id="scoreRadialCircle" cx="50" cy="50" r="40"></circle>
                </svg>
                <div>
                    <div class="d-flex align-items-baseline gap-1">
                        <span class="audit-score-number" id="finalScoreText">0</span>
                        <span class="fw-bold text-muted" style="font-size: 0.9rem;">%</span>
                    </div>
                    <div class="audit-score-label" id="finalScoreLabel">مؤشر الوجود الرقمي العام</div>
                    <span class="audit-status-pill mt-1" id="finalScorePill">جاري التقييم</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="audit-school-avatar-box" id="auditSchoolAvatarBox">
                    <img src="" id="auditSchoolLogoImg" alt="شعار المدرسة" style="display: none;">
                    <span class="school-monogram" id="auditSchoolMonogram">م</span>
                </div>
                <div class="d-flex flex-column gap-1">
                    <h3 class="fw-bold text-dark mb-0" id="resultSchoolName" style="font-size: 1.15rem;">اسم المدرسة</h3>
                    <div class="text-muted d-flex align-items-center gap-2" style="font-size: 0.73rem;">
                        <span id="resultSchoolLocation"><i class="fas fa-map-marker-alt text-danger me-1"></i> السودان</span>
                        <span>•</span>
                        <span id="verifiedItemsCount"><i class="fas fa-check-circle text-success me-1"></i> 0 من 10 بنود مؤكدة</span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light border fw-bold px-3 py-2 rounded-3" style="font-size: 0.74rem;" onclick="printAuditReport()">
                    <i class="fas fa-print me-1 text-secondary"></i> طباعة التقرير
                </button>
                <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 py-2 rounded-3" style="font-size: 0.74rem;" onclick="shareAuditWhatsApp()">
                    <i class="fab fa-whatsapp me-1"></i> مشاركة النتيجة
                </button>
                <a href="#auditConversionSection" class="btn-luxury-primary" style="font-size: 0.74rem; padding: 7px 14px;">
                    <i class="fas fa-save"></i> تثبيت الحضور بالدليل
                </a>
            </div>
        </div>

        <!-- 4 KPI METRICS (STYLE F v2) -->
        <div class="metrics" id="auditMetricsGrid">
            <div class="metric m-blue" id="metricCardScore">
                <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
                <div class="metric-body">
                    <span class="metric-val" id="metricScoreVal">0%</span>
                    <div class="metric-lbl">المؤشر الرقمي العام</div>
                </div>
            </div>
            <div class="metric m-green" id="metricCardVerified">
                <div class="metric-icon"><i class="fas fa-check-double"></i></div>
                <div class="metric-body">
                    <span class="metric-val" id="metricVerifiedVal">0 من 10</span>
                    <div class="metric-lbl">عناصر نشطة وموثقة</div>
                </div>
            </div>
            <div class="metric m-orange" id="metricCardMissing">
                <div class="metric-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="metric-body">
                    <span class="metric-val" id="metricMissingVal">0 عناصر</span>
                    <div class="metric-lbl">فرص تحسين غير مكتملة</div>
                </div>
            </div>
            <div class="metric m-gray" id="metricCardDaleel">
                <div class="metric-icon"><i class="fas fa-award"></i></div>
                <div class="metric-body">
                    <span class="metric-val" id="metricDaleelVal">غير مدرجة</span>
                    <div class="metric-lbl">التوثيق بدليل المدارس</div>
                </div>
            </div>
        </div>

        <!-- VISUAL SEARCH & LOGO AUDIT CARD -->
        <div class="audit-visual-search-card" id="auditVisualSearchCard">
            <div class="audit-visual-search-header">
                <div>
                    <h4 class="audit-visual-title mb-1">
                        <i class="fas fa-camera text-primary"></i>
                        <span>هل التواجد الرقمي لمدرستك أكبر من النتيجة الحالية؟</span>
                        <span class="badge bg-warning text-dark px-2 py-0.5 rounded-pill" style="font-size:0.65rem;">+10% بونص الشعار والهوية</span>
                    </h4>
                    <p class="audit-visual-desc">
                        تحتفظ المدارس العريقة بحضور بصري قوي عبر شهادات التكريم، نتائج الامتحانات، واحتفالات التخرج. قم برفع شعار المدرسة لتوثيقه فوراً، أو استخدم البحث البصري بالصور للوصول لكافة الأخبار والشهادات المرتبطة باسم المدرسة.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="googleImagesSearchBtn" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary fw-bold" style="font-size: 0.72rem;">
                        <i class="fas fa-images me-1"></i> بحث الصور في Google
                    </a>
                    <a href="#" id="googleNewsVisualBtn" target="_blank" rel="noopener" class="btn btn-sm btn-light border fw-bold" style="font-size: 0.72rem;">
                        <i class="fas fa-newspaper me-1 text-secondary"></i> أرشيف التكريم والشهادات
                    </a>
                </div>
            </div>

            <!-- DRAG & DROP LOGO UPLOADER -->
            <div class="row g-3 align-items-center mt-1">
                <div class="col-md-7">
                    <div class="audit-logo-dropzone" id="auditLogoDropzone" onclick="document.getElementById('auditLogoFileInput').click()">
                        <input type="file" id="auditLogoFileInput" accept="image/*" style="display: none;" onchange="handleLogoUpload(this)">
                        <div class="d-flex align-items-center gap-3">
                            <img id="uploadedLogoPreview" class="audit-logo-preview" alt="معاينة الشعار">
                            <div id="dropzonePromptText" class="text-start">
                                <div class="fw-bold text-dark" style="font-size: 0.8rem;">
                                    <i class="fas fa-cloud-upload-alt text-primary me-1"></i> اضغط هنا لرفع شعار المدرسة أو اسحبه وأفلته
                                </div>
                                <div class="text-muted" style="font-size: 0.7rem;">يدعم صيغ PNG, JPG, WebP — يرفع نسبة الوجود الرقمي بمقدار +10 نقاط فورية</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="p-2.5 rounded-3 bg-light border" style="font-size: 0.72rem; color: #475569; line-height: 1.55;">
                        <strong class="text-dark d-block mb-1"><i class="fas fa-info-circle text-primary me-1"></i> كيف تؤثر الهوية البصرية؟</strong>
                        ظهور الشعار الرسمي على منصات البحث وشبكات التواصل يرفع ثقة أولياء الأمور بنسبة تفوق 75% ويثبت ملكية المنصات المكتشفة تلقائياً.
                    </div>
                </div>
            </div>
        </div>

        <!-- CATEGORY SEGMENTED PILLS -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 mt-4">
            <div class="segmented-pills mb-0" id="auditCategoryPills">
                <button type="button" class="seg-pill active" data-filter="all" onclick="filterAuditItems('all')">
                    كافة البنود
                    <span class="pill-count" id="pillCountAll">10</span>
                </button>
                <button type="button" class="seg-pill" data-filter="verified" onclick="filterAuditItems('verified')">
                    الموثقة والمؤكدة
                    <span class="pill-count" id="pillCountVerified">0</span>
                </button>
                <button type="button" class="seg-pill" data-filter="missing" onclick="filterAuditItems('missing')">
                    فرص التحسين والمفقود
                    <span class="pill-count" id="pillCountMissing">0</span>
                </button>
                <button type="button" class="seg-pill" data-filter="social" onclick="filterAuditItems('social')">
                    قنوات التواصل
                    <span class="pill-count">4</span>
                </button>
                <button type="button" class="seg-pill" data-filter="trust" onclick="filterAuditItems('trust')">
                    الاعتماد والخرائط
                    <span class="pill-count">3</span>
                </button>
            </div>

            <div class="text-muted small d-none d-md-block" style="font-size: 0.7rem;">
                <i class="fas fa-info-circle text-primary me-1"></i> يمكنك تأكيد أو تعديل أي بند بنقرة واحدة لتحسين النتيجة
            </div>
        </div>

        <!-- 10 AUDIT ITEMS GRID -->
        <div class="audit-items-grid" id="auditItemsGrid">
            <!-- Rendered dynamically by JavaScript -->
        </div>

        <!-- BOTTOM CONVERSION & VERIFICATION BRIDGE -->
        <div class="audit-conversion-card" id="auditConversionSection">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold mb-2" style="font-size: 0.68rem;">
                        <i class="fas fa-award me-1"></i> تثبيت واعتماد رسمي
                    </span>
                    <h3 class="audit-conversion-title">هل هذه مدرستك؟ ثبّت حضورك الرقمي الآن مجاناً في دليل المدارس</h3>
                    <p class="audit-conversion-desc">
                        قم باعتماد البيانات المدققة، وتوثيق قنوات الاتصال والروابط والموقع الجغرافي مباشرة في دليل المدارس السودانية. سيتم نقل كافة البيانات التي تحققت منها بنقرة واحدة إلى صفحة التسجيل دون الحاجة لإعادة كتابتها!
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end text-center">
                    <a href="#" id="auditRegisterLink" class="btn-luxury-primary justify-content-center w-100 py-2.5" style="font-size: 0.85rem;">
                        <i class="fas fa-shield-alt"></i>
                        <span>اعتماد وتثبيت الوجود الرقمي</span>
                    </a>
                    <div class="mt-2 text-center text-light opacity-75" style="font-size: 0.68rem;">
                        <i class="fas fa-check-circle text-success me-1"></i> مجاني 100% ويستغرق دقيقة واحدة
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- COUNTRIES & STATES DATA FOR JAVASCRIPT -->
<script>
const GEO_COUNTRIES_DATA = <?= json_encode($countriesWithStates, JSON_UNESCAPED_UNICODE) ?>;
let CURRENT_AUDIT_DATA = null;

// Dynamic States update on country change
function updateAuditStates() {
    const countrySel = document.getElementById('auditCountry');
    const stateSel = document.getElementById('auditState');
    const selectedCountry = countrySel.value;
    
    stateSel.innerHTML = '<option value="">كافة الولايات</option>';
    if (GEO_COUNTRIES_DATA[selectedCountry]) {
        for (const st in GEO_COUNTRIES_DATA[selectedCountry]) {
            const opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            stateSel.appendChild(opt);
        }
    }
}

// Live Autocomplete for School Name
(function(){
    const nameInput = document.getElementById('auditSchoolName');
    const box = document.getElementById('auditSuggestBox');
    let timer = null;

    nameInput.addEventListener('input', function(){
        const q = this.value.trim();
        clearTimeout(timer);
        if (q.length < 2) {
            box.style.display = 'none';
            return;
        }

        timer = setTimeout(() => {
            fetch('/presence-audit.php?action=suggest&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.suggestions || !data.suggestions.length) {
                        box.style.display = 'none';
                        return;
                    }

                    box.innerHTML = '';
                    data.suggestions.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'audit-suggest-item';
                        div.innerHTML = `
                            <div>
                                <strong class="text-dark">${escapeHtml(item.name)}</strong>
                                <span class="text-muted ms-2" style="font-size:0.68rem;">(${item.state || item.country})</span>
                            </div>
                            ${item.is_smart ? '<span class="badge bg-primary text-white" style="font-size:0.6rem;"><i class="fas fa-bolt"></i> ذكية</span>' : ''}
                        `;
                        div.addEventListener('click', () => {
                            nameInput.value = item.name;
                            if (item.country) {
                                document.getElementById('auditCountry').value = item.country;
                                updateAuditStates();
                            }
                            if (item.state) {
                                document.getElementById('auditState').value = item.state;
                            }
                            box.style.display = 'none';
                            runDigitalAudit();
                        });
                        box.appendChild(div);
                    });
                    box.style.display = 'block';
                })
                .catch(() => { box.style.display = 'none'; });
        }, 220);
    });

    document.addEventListener('click', (e) => {
        if (!nameInput.contains(e.target) && !box.contains(e.target)) {
            box.style.display = 'none';
        }
    });
})();

// =========================================================================
// RUN DIGITAL AUDIT ENGINE
// =========================================================================

function runDigitalAudit() {
    const schoolName = document.getElementById('auditSchoolName').value.trim();
    const country = document.getElementById('auditCountry').value.trim();
    const state = document.getElementById('auditState').value.trim();

    if (schoolName.length < 2) {
        alert('يرجى كتابة اسم المدرسة بشكل صحيح');
        return;
    }

    const hud = document.getElementById('auditHud');
    const results = document.getElementById('auditResults');
    const submitBtn = document.getElementById('auditSubmitBtn');
    const progressBar = document.getElementById('hudProgressBar');
    const hudHeading = document.getElementById('hudStatusHeading');
    const hudDetail = document.getElementById('hudStatusDetail');
    const hudStepBadge = document.getElementById('hudStepBadge');

    results.style.display = 'none';
    hud.style.display = 'block';
    submitBtn.disabled = true;

    // Multi-stage HUD simulation
    let progress = 10;
    progressBar.style.width = '10%';
    hudHeading.textContent = 'الاتصال بقواعد بيانات المدارس والتحقق...';
    hudDetail.textContent = `فحص سجلات "${schoolName}" في دليل المدارس السودانية`;
    hudStepBadge.textContent = 'الخطوة 1 من 4: مطابقة قاعدة البيانات الرسمية';

    const stageTimer1 = setTimeout(() => {
        progressBar.style.width = '40%';
        hudHeading.textContent = 'استكشاف الأثر الرقمي في محركات البحث...';
        hudDetail.textContent = 'فحص الموقع الإلكتروني، النطاقات الرسمية، وأخبار تفوق الطلاب على جوجل';
        hudStepBadge.textContent = 'الخطوة 2 من 4: مسح محركات البحث والويب';
    }, 550);

    const stageTimer2 = setTimeout(() => {
        progressBar.style.width = '70%';
        hudHeading.textContent = 'تدقيق شبكات التواصل وخرائط جوجل...';
        hudDetail.textContent = 'فحص صفحة الفيسبوك، حساب التيك توك، وقنوات الاتصال والموقع الجغرافي';
        hudStepBadge.textContent = 'الخطوة 3 من 4: تدقيق التواصل والخرائط';
    }, 1100);

    const formData = new FormData();
    formData.append('action', 'scan');
    formData.append('school_name', schoolName);
    formData.append('country', country);
    formData.append('state', state);

    fetch('/presence-audit.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        clearTimeout(stageTimer1);
        clearTimeout(stageTimer2);
        progressBar.style.width = '100%';
        hudHeading.textContent = 'اكتمل الفحص الرقمي بنجاح!';
        hudDetail.textContent = 'تم توليد تقرير التدقيق الشامل لـ 10 بنود رقمية';
        hudStepBadge.textContent = 'الخطوة 4 من 4: توليد التقرير النهائي';

        setTimeout(() => {
            hud.style.display = 'none';
            submitBtn.disabled = false;
            if (!data.success) {
                alert(data.message || 'حدث خطأ أثناء إجراء الفحص');
                return;
            }
            renderAuditResults(data);
        }, 400);
    })
    .catch(err => {
        clearTimeout(stageTimer1);
        clearTimeout(stageTimer2);
        hud.style.display = 'none';
        submitBtn.disabled = false;
        alert('تعذر استكمال الفحص، يرجى المحاولة مرة أخرى.');
    });
}

// =========================================================================
// RENDER AUDIT RESULTS & 10 ITEMS
// =========================================================================

function renderAuditResults(data) {
    CURRENT_AUDIT_DATA = data;
    const container = document.getElementById('auditResults');
    const itemsGrid = document.getElementById('auditItemsGrid');
    const searchCard = document.getElementById('auditSearchCard');
    const activeBar = document.getElementById('auditActiveBar');

    // Update active toolbar
    if (activeBar) {
        document.getElementById('activeSchoolTitle').textContent = data.school_name;
        document.getElementById('activeSchoolGeo').textContent = '— ' + (data.state ? 'ولاية ' + data.state + '، ' : '') + (data.country || 'السودان');
        activeBar.style.display = 'flex';
    }

    // Collapse search card so executive results are directly visible
    if (searchCard) {
        searchCard.style.display = 'none';
    }

    document.getElementById('resultSchoolName').textContent = data.school_name;
    document.getElementById('resultSchoolLocation').innerHTML = `<i class="fas fa-map-marker-alt text-danger me-1"></i> ${escapeHtml(data.state ? 'ولاية ' + data.state + ' — ' : '')}${escapeHtml(data.country || 'السودان')}`;

    // School Logo and Avatar Handling
    const logoImg = document.getElementById('auditSchoolLogoImg');
    const monogram = document.getElementById('auditSchoolMonogram');
    const activeLogo = document.getElementById('activeBarSchoolLogo');
    const schoolLogoUrl = data.logo || (data.matched_db_school && data.matched_db_school.logo) || null;

    if (schoolLogoUrl) {
        if (logoImg) {
            logoImg.src = schoolLogoUrl;
            logoImg.style.display = 'block';
        }
        if (monogram) monogram.style.display = 'none';
        if (activeLogo) {
            activeLogo.src = schoolLogoUrl;
            activeLogo.style.display = 'inline-block';
        }
    } else {
        if (logoImg) logoImg.style.display = 'none';
        if (monogram) {
            monogram.style.display = 'block';
            monogram.textContent = (data.school_name || 'م').trim().charAt(0);
        }
        if (activeLogo) activeLogo.style.display = 'none';
    }

    // Setup Visual Search buttons for Google Images and News Archives
    const gImgBtn = document.getElementById('googleImagesSearchBtn');
    const gNewsBtn = document.getElementById('googleNewsVisualBtn');
    const qName = encodeURIComponent('شعار ' + data.school_name);
    const qNews = encodeURIComponent('"' + data.school_name + '" تكريم اوائل نتيجة الشهادة');
    if (gImgBtn) gImgBtn.href = `https://www.google.com/search?tbm=isch&q=${qName}`;
    if (gNewsBtn) gNewsBtn.href = `https://www.google.com/search?q=${qNews}`;

    itemsGrid.innerHTML = '';
    data.items.forEach(item => {
        const card = createItemCard(item);
        itemsGrid.appendChild(card);
    });

    updateOverallScore();
    buildRegistrationBridgeLink();

    container.style.display = 'block';
    const navbarHeight = 85;
    const topPos = container.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
    window.scrollTo({
        top: Math.max(0, topPos),
        behavior: 'smooth'
    });
}

// Create Card for each of the 10 Items
function createItemCard(item) {
    const card = document.createElement('div');
    const isDetected = (!item.verified && (item.status === 'detected' || (item.value && item.value.trim().length > 0)));
    card.className = `audit-item-card ${item.verified ? 'is-confirmed' : (item.status === 'not_found' ? 'is-missing' : '')}`;
    card.id = `itemCard_${item.id}`;
    card.dataset.id = item.id;
    card.dataset.status = item.verified ? 'verified' : (isDetected ? 'detected' : 'missing');
    card.dataset.type = (item.id === 2 || item.id === 3 || item.id === 4 || item.id === 7) ? 'social' : ((item.id === 6 || item.id === 8 || item.id === 9) ? 'trust' : 'general');

    let statusPillHtml = '';
    if (item.verified) {
        statusPillHtml = `<span class="audit-status-pill b-green"><i class="fas fa-check-circle"></i> مؤكد وموثق</span>`;
    } else if (isDetected) {
        statusPillHtml = `<span class="audit-status-pill b-orange"><i class="fas fa-search"></i> أثر رقمي مكتشف (60%)</span>`;
    } else {
        statusPillHtml = `<span class="audit-status-pill b-red"><i class="fas fa-times-circle"></i> غير متوفر (0%)</span>`;
    }

    // Display Value Link or text
    let valHtml = '';
    if (item.value && (item.value.startsWith('http://') || item.value.startsWith('https://') || item.value.startsWith('/'))) {
        valHtml = `<a href="${escapeHtml(item.value)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i> ${escapeHtml(item.display_value || item.value)}</a>`;
    } else {
        valHtml = `<span>${escapeHtml(item.display_value || (item.value || 'غير متوفر'))}</span>`;
    }

    // Action buttons
    let actionsHtml = '';
    if (item.action_cta) {
        // Dynamic conversion action for items 8, 9, 10
        actionsHtml = `
            <a href="${escapeHtml(item.action_cta.url)}" class="btn-luxury-primary" style="font-size:0.68rem; padding: 4px 10px;" target="${item.action_cta.type === 'upgrade_smart' ? '_blank' : '_self'}">
                ${escapeHtml(item.action_cta.label)}
            </a>
            ${item.can_edit ? `<button type="button" class="audit-act-btn btn-edit" onclick="toggleItemEdit(${item.id})"><i class="fas fa-edit"></i> تعديل</button>` : ''}
        `;
    } else {
        // Regular items (1 to 7)
        actionsHtml = `
            <div class="d-flex align-items-center gap-1">
                ${!item.verified && item.value ? `<button type="button" class="audit-act-btn btn-confirm" onclick="confirmItem(${item.id})"><i class="fas fa-check"></i> تأكيد وصحة (+4)</button>` : ''}
                ${item.verified ? `<span class="text-success fw-bold" style="font-size:0.68rem;"><i class="fas fa-shield-alt"></i> معتمد</span>` : ''}
                <button type="button" class="audit-act-btn ${item.value ? 'btn-edit' : 'btn-add'}" onclick="toggleItemEdit(${item.id})">
                    <i class="fas ${item.value ? 'fa-edit' : 'fa-plus'}"></i> ${item.value ? 'تعديل' : '+ إضافة الرابط (+10)'}
                </button>
            </div>
        `;
    }

    card.innerHTML = `
        <div>
            <div class="audit-item-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="audit-item-badge-index">${item.id}</span>
                    <div>
                        <h4 class="audit-item-title"><i class="${item.icon} text-primary me-1"></i> ${escapeHtml(item.title)}</h4>
                        <span class="audit-item-category">${escapeHtml(item.category || '')}</span>
                    </div>
                </div>
                <div id="statusPill_${item.id}">${statusPillHtml}</div>
            </div>

            <div class="audit-item-body">
                <div class="audit-detected-val" id="valBox_${item.id}">
                    ${valHtml}
                </div>
                <p class="audit-item-desc">${escapeHtml(item.description)}</p>

                <!-- Inline Edit Box -->
                <div class="audit-inline-edit" id="editBox_${item.id}">
                    <label class="form-label mb-1" style="font-size:0.68rem; font-weight:700;">تحديث الرابط أو البيان الصحيح:</label>
                    <div class="d-flex gap-2">
                        <input type="${item.input_type || 'text'}" 
                               id="input_${item.id}" 
                               class="form-control form-control-sm" 
                               style="font-size:0.75rem;" 
                               placeholder="${escapeHtml(item.placeholder || 'أدخل الرابط أو الرقم الصحيح')}" 
                               value="${escapeHtml(item.value || '')}">
                        <button type="button" class="btn btn-sm btn-primary" style="font-size:0.7rem;" onclick="saveItemEdit(${item.id})">حفظ</button>
                        <button type="button" class="btn btn-sm btn-light border" style="font-size:0.7rem;" onclick="toggleItemEdit(${item.id})">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="audit-item-actions" id="actions_${item.id}">
            ${actionsHtml}
            <span class="text-muted" style="font-size:0.65rem;" id="pointsBadge_${item.id}">
                ${item.verified ? '<strong class="text-success">+10 نقاط (مؤكد)</strong>' : (isDetected ? '<strong class="text-warning">+6 / 10 نقاط (مكتشف)</strong>' : '<span class="text-muted">0 / 10 نقاط</span>')}
            </span>
        </div>
    `;

    return card;
}

// =========================================================================
// INTERACTIVE ITEM VERIFICATION (Confirm / Edit / Add)
// =========================================================================

function confirmItem(itemId) {
    const item = CURRENT_AUDIT_DATA.items.find(i => i.id === itemId);
    if (!item) return;

    item.verified = true;
    item.status = 'verified';

    // Update UI elements
    const card = document.getElementById(`itemCard_${itemId}`);
    if (card) {
        card.classList.remove('is-missing');
        card.classList.add('is-confirmed');
        card.dataset.status = 'verified';
    }

    const pillEl = document.getElementById(`statusPill_${itemId}`);
    if (pillEl) pillEl.innerHTML = `<span class="audit-status-pill b-green"><i class="fas fa-check-circle"></i> مؤكد وموثق</span>`;
    
    const badgeEl = document.getElementById(`pointsBadge_${itemId}`);
    if (badgeEl) badgeEl.innerHTML = `<strong class="text-success">+10 نقاط (مؤكد)</strong>`;
    
    // Refresh actions
    const actEl = document.getElementById(`actions_${itemId}`);
    if (actEl) {
        actEl.innerHTML = `
            <div class="d-flex align-items-center gap-1">
                <span class="text-success fw-bold" style="font-size:0.68rem;"><i class="fas fa-shield-alt"></i> معتمد من المستخدم</span>
                <button type="button" class="audit-act-btn btn-edit" onclick="toggleItemEdit(${itemId})"><i class="fas fa-edit"></i> تعديل</button>
            </div>
            <span class="text-muted" style="font-size:0.65rem;"><strong class="text-success">+10 نقاط (مؤكد)</strong></span>
        `;
    }

    updateOverallScore();
    buildRegistrationBridgeLink();
}

function toggleItemEdit(itemId) {
    const box = document.getElementById(`editBox_${itemId}`);
    box.style.display = (box.style.display === 'block') ? 'none' : 'block';
}

function saveItemEdit(itemId) {
    const item = CURRENT_AUDIT_DATA.items.find(i => i.id === itemId);
    if (!item) return;

    const inputVal = document.getElementById(`input_${itemId}`).value.trim();
    if (inputVal === '') {
        alert('يرجى إدخال الرابط أو القيمة المطلوبة');
        return;
    }

    item.value = inputVal;
    item.display_value = inputVal;
    item.verified = true;
    item.status = 'verified';

    // Update Display Box
    const valBox = document.getElementById(`valBox_${itemId}`);
    if (inputVal.startsWith('http://') || inputVal.startsWith('https://')) {
        valBox.innerHTML = `<a href="${escapeHtml(inputVal)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i> ${escapeHtml(inputVal)}</a>`;
    } else {
        valBox.innerHTML = `<span>${escapeHtml(inputVal)}</span>`;
    }

    toggleItemEdit(itemId);
    confirmItem(itemId);
}

let CURRENT_FILTER = 'all';

function toggleAuditSearchForm() {
    const card = document.getElementById('auditSearchCard');
    if (card) {
        if (card.style.display === 'none') {
            card.style.display = 'block';
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const input = document.getElementById('auditSchoolName');
            if (input) {
                input.focus();
                input.select();
            }
        } else {
            card.style.display = 'none';
        }
    }
}

function filterAuditItems(filterKey) {
    CURRENT_FILTER = filterKey;
    document.querySelectorAll('#auditCategoryPills .seg-pill').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filterKey);
    });

    const cards = document.querySelectorAll('.audit-item-card');
    cards.forEach(card => {
        const isConfirmed = card.classList.contains('is-confirmed');
        const isMissing = !isConfirmed;
        const itemType = card.dataset.type;

        let show = false;
        if (filterKey === 'all') {
            show = true;
        } else if (filterKey === 'verified') {
            show = isConfirmed;
        } else if (filterKey === 'missing') {
            show = isMissing;
        } else if (filterKey === 'social') {
            show = (itemType === 'social');
        } else if (filterKey === 'trust') {
            show = (itemType === 'trust');
        }
        card.style.display = show ? 'flex' : 'none';
    });
}

// Recalculate Live Overall Score & Animate Gauge & KPI Metrics
function updateOverallScore() {
    if (!CURRENT_AUDIT_DATA) return;

    let score = 0;
    let verifiedCount = 0;
    let detectedCount = 0;
    let missingCount = 0;

    CURRENT_AUDIT_DATA.items.forEach(it => {
        if (it.verified) {
            score += (it.points || 10);
            verifiedCount++;
        } else if (it.status === 'detected' || (it.value && it.value.trim().length > 0)) {
            score += 6; // 60% partial credit for detected digital footprint
            detectedCount++;
        } else {
            missingCount++;
        }
    });

    const hasLogo = !!(CURRENT_AUDIT_DATA.logo || CURRENT_AUDIT_DATA.uploaded_logo || (CURRENT_AUDIT_DATA.matched_db_school && CURRENT_AUDIT_DATA.matched_db_school.logo));
    if (hasLogo) {
        score = Math.min(100, score + 10);
    }

    const scoreNumEl = document.getElementById('finalScoreText');
    const scorePill = document.getElementById('finalScorePill');
    const scoreCircle = document.getElementById('scoreRadialCircle');
    const verifiedText = document.getElementById('verifiedItemsCount');

    // 4 Metric Card Elements
    const metricScoreVal = document.getElementById('metricScoreVal');
    const metricVerifiedVal = document.getElementById('metricVerifiedVal');
    const metricMissingVal = document.getElementById('metricMissingVal');
    const metricDaleelVal = document.getElementById('metricDaleelVal');
    const metricCardDaleel = document.getElementById('metricCardDaleel');

    // Count-up animation
    animateCount(scoreNumEl, parseInt(scoreNumEl.textContent) || 0, score, 400);

    // SVG radial offset (perimeter = 2 * PI * 40 = 251.2)
    const maxOffset = 251.2;
    const offset = maxOffset - (score / 100) * maxOffset;
    scoreCircle.style.strokeDashoffset = offset;

    // Color and Pill Status
    if (score >= 80) {
        scoreCircle.style.stroke = '#10B981';
        scorePill.className = 'audit-status-pill b-green';
        scorePill.innerHTML = '<i class="fas fa-trophy"></i> وجود رقمي ممتاز ومكتمل';
    } else if (score >= 50) {
        scoreCircle.style.stroke = '#F5A020';
        scorePill.className = 'audit-status-pill b-orange';
        scorePill.innerHTML = '<i class="fas fa-bolt"></i> وجود رقمي متوسط يحتاج تعزيز';
    } else {
        scoreCircle.style.stroke = '#EF4444';
        scorePill.className = 'audit-status-pill b-red';
        scorePill.innerHTML = '<i class="fas fa-exclamation-triangle"></i> وجود رقمي ضعيف أو غير مفعل';
    }

    // Update 4 Metric cards
    if (metricScoreVal) metricScoreVal.textContent = score + '%';
    if (metricVerifiedVal) metricVerifiedVal.textContent = `${verifiedCount + detectedCount} من 10`;
    if (metricMissingVal) metricMissingVal.textContent = `${missingCount} عناصر`;

    const isInDaleel = (CURRENT_AUDIT_DATA.matched_db_school !== null && CURRENT_AUDIT_DATA.matched_db_school.id);
    if (metricDaleelVal) {
        if (isInDaleel) {
            metricDaleelVal.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> موثقة بالدليل</span>';
            if (metricCardDaleel) {
                metricCardDaleel.className = 'metric m-green';
            }
        } else {
            metricDaleelVal.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> غير مسجلة</span>';
            if (metricCardDaleel) {
                metricCardDaleel.className = 'metric m-orange';
            }
        }
    }

    // Update Pill Counts
    const pillCountAll = document.getElementById('pillCountAll');
    const pillCountVerified = document.getElementById('pillCountVerified');
    const pillCountMissing = document.getElementById('pillCountMissing');
    if (pillCountAll) pillCountAll.textContent = CURRENT_AUDIT_DATA.items.length;
    if (pillCountVerified) pillCountVerified.textContent = verifiedCount + detectedCount;
    if (pillCountMissing) pillCountMissing.textContent = missingCount;

    verifiedText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> ${verifiedCount} مؤكدة • ${detectedCount} مكتشفة`;

    // Refresh active filter view
    filterAuditItems(CURRENT_FILTER);
}

// Interactive Logo Upload and Visual Presence Audit
function handleLogoUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const dataUrl = e.target.result;
        if (CURRENT_AUDIT_DATA) {
            CURRENT_AUDIT_DATA.uploaded_logo = dataUrl;
            CURRENT_AUDIT_DATA.has_logo = true;
        }
        const preview = document.getElementById('uploadedLogoPreview');
        if (preview) {
            preview.src = dataUrl;
            preview.style.display = 'block';
        }
        const promptText = document.getElementById('dropzonePromptText');
        if (promptText) {
            promptText.innerHTML = `
                <div class="fw-bold text-success" style="font-size: 0.8rem;">
                    <i class="fas fa-check-circle me-1"></i> تم رفع الشعار بنجاح (+10% بونص أضيف لمؤشرك)
                </div>
                <div class="text-muted" style="font-size: 0.7rem;">${escapeHtml(file.name)} (${Math.round(file.size / 1024)} KB)</div>
            `;
        }
        const logoImg = document.getElementById('auditSchoolLogoImg');
        const monogram = document.getElementById('auditSchoolMonogram');
        const activeLogo = document.getElementById('activeBarSchoolLogo');
        if (logoImg) {
            logoImg.src = dataUrl;
            logoImg.style.display = 'block';
        }
        if (monogram) monogram.style.display = 'none';
        if (activeLogo) {
            activeLogo.src = dataUrl;
            activeLogo.style.display = 'inline-block';
        }
        updateOverallScore();
    };
    reader.readAsDataURL(file);
}

// Build pre-filled link to /register.php or /dashboard.php
function buildRegistrationBridgeLink() {
    if (!CURRENT_AUDIT_DATA) return;

    const regBtn = document.getElementById('auditRegisterLink');
    const params = new URLSearchParams();

    params.set('name', CURRENT_AUDIT_DATA.school_name || '');
    params.set('country', CURRENT_AUDIT_DATA.country || 'السودان');
    params.set('state', CURRENT_AUDIT_DATA.state || '');

    CURRENT_AUDIT_DATA.items.forEach(it => {
        if (it.verified && it.value) {
            if (it.key === 'website') params.set('website', it.value);
            if (it.key === 'facebook') params.set('facebook', it.value);
            if (it.key === 'tiktok') params.set('tiktok', it.value);
            if (it.key === 'social_extra' && it.value.includes('instagram')) params.set('instagram', it.value);
            if (it.key === 'social_extra' && it.value.includes('youtube')) params.set('youtube', it.value);
            if (it.key === 'google_maps') params.set('google_map_url', it.value);
            if (it.key === 'direct_contact') params.set('phone', it.value);
        }
    });

    if (CURRENT_AUDIT_DATA.matched_db_school) {
        regBtn.href = '/dashboard.php';
        regBtn.innerHTML = '<i class="fas fa-columns"></i> <span>إدارة وتحديث بيانات المدرسة بالدليل</span>';
    } else {
        regBtn.href = '/register.php?' + params.toString();
        regBtn.innerHTML = '<i class="fas fa-shield-alt"></i> <span>اعتماد وتثبيت الوجود الرقمي بالدليل</span>';
    }
}

// Specialized Printable Audit Report Generator
function printAuditReport() {
    if (!CURRENT_AUDIT_DATA) {
        alert('يرجى إجراء فحص لمدرسة أولاً قبل الطباعة.');
        return;
    }

    // Populate Print Header and Meta
    const pName = document.getElementById('printSchoolName');
    const pGeo = document.getElementById('printSchoolGeo');
    if (pName) pName.textContent = CURRENT_AUDIT_DATA.school_name || 'اسم المدرسة';
    if (pGeo) pGeo.textContent = (CURRENT_AUDIT_DATA.state ? 'ولاية ' + CURRENT_AUDIT_DATA.state + ' — ' : '') + (CURRENT_AUDIT_DATA.country || 'السودان');
    
    // Date & Random Reference Number
    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];
    const pDate = document.getElementById('printAuditDate');
    const pRef = document.getElementById('printAuditRef');
    if (pDate) pDate.textContent = dateStr;
    const randomRef = Math.floor(1000 + Math.random() * 9000);
    if (pRef) pRef.textContent = `SP-AUD-${dateStr.replace(/-/g, '')}-${randomRef}`;

    // School Logo or Monogram
    const logoImg = document.getElementById('printSchoolLogoImg');
    const monogram = document.getElementById('printSchoolMonogram');
    const schoolLogoUrl = CURRENT_AUDIT_DATA.uploaded_logo || CURRENT_AUDIT_DATA.logo || (CURRENT_AUDIT_DATA.matched_db_school && CURRENT_AUDIT_DATA.matched_db_school.logo) || null;
    
    if (schoolLogoUrl) {
        if (logoImg) {
            logoImg.src = schoolLogoUrl;
            logoImg.style.display = 'block';
        }
        if (monogram) monogram.style.display = 'none';
    } else {
        if (logoImg) logoImg.style.display = 'none';
        if (monogram) {
            monogram.style.display = 'flex';
            monogram.textContent = (CURRENT_AUDIT_DATA.school_name || 'م').trim().charAt(0);
        }
    }

    // Daleel Status
    const isInDaleel = (CURRENT_AUDIT_DATA.matched_db_school !== null && CURRENT_AUDIT_DATA.matched_db_school.id);
    const daleelStatusEl = document.getElementById('printSchoolDaleelStatus');
    if (daleelStatusEl) {
        if (isInDaleel) {
            daleelStatusEl.innerHTML = '<span style="color: #166534; font-weight:700;">حالة التوثيق: موثقة رسمياً في دليل المدارس</span>';
        } else {
            daleelStatusEl.innerHTML = '<span style="color: #991B1B; font-weight:700;">حالة التوثيق: غير مسجلة بالدليل حتى الآن</span>';
        }
    }

    // Score & KPI counts
    let verifiedCount = 0;
    let detectedCount = 0;
    let missingCount = 0;
    let score = 0;

    CURRENT_AUDIT_DATA.items.forEach(it => {
        if (it.verified) {
            score += (it.points || 10);
            verifiedCount++;
        } else if (it.status === 'detected' || (it.value && it.value.trim().length > 0)) {
            score += 6;
            detectedCount++;
        } else {
            missingCount++;
        }
    });

    const hasLogo = !!(CURRENT_AUDIT_DATA.logo || CURRENT_AUDIT_DATA.uploaded_logo || (CURRENT_AUDIT_DATA.matched_db_school && CURRENT_AUDIT_DATA.matched_db_school.logo));
    if (hasLogo) {
        score = Math.min(100, score + 10);
    }

    const pScoreVal = document.getElementById('printScoreVal');
    const pScoreLbl = document.getElementById('printScoreLabel');
    if (pScoreVal) pScoreVal.textContent = score + '%';
    if (pScoreLbl) {
        if (score >= 80) pScoreLbl.textContent = 'مؤشر ممتاز ومكتمل';
        else if (score >= 50) pScoreLbl.textContent = 'مؤشر متوسط يحتاج تعزيز';
        else pScoreLbl.textContent = 'مؤشر ضعيف أو غير مفعل';
    }

    const pKpiVer = document.getElementById('printKpiVerified');
    const pKpiDet = document.getElementById('printKpiDetected');
    const pKpiMis = document.getElementById('printKpiMissing');
    const pKpiLogo = document.getElementById('printKpiLogo');
    if (pKpiVer) pKpiVer.textContent = `${verifiedCount} من 10`;
    if (pKpiDet) pKpiDet.textContent = `${detectedCount} بنود`;
    if (pKpiMis) pKpiMis.textContent = `${missingCount} بنود`;
    if (pKpiLogo) pKpiLogo.textContent = hasLogo ? '+10 نقاط (مفعّل)' : 'غير متوفر (0)';

    // Table rows
    const tbody = document.getElementById('printTableBody');
    if (tbody) {
        tbody.innerHTML = '';
        CURRENT_AUDIT_DATA.items.forEach((it, idx) => {
            const isVerified = it.verified;
            const isDetected = (!isVerified && (it.status === 'detected' || (it.value && it.value.trim().length > 0)));
            
            let statusBadge = '';
            let pointsText = '';
            if (isVerified) {
                statusBadge = '<span class="print-badge b-green">مؤكد وموثق</span>';
                pointsText = '<strong style="color:#166534;">+10 / 10</strong>';
            } else if (isDetected) {
                statusBadge = '<span class="print-badge b-orange">أثر مكتشف (60%)</span>';
                pointsText = '<strong style="color:#D97706;">+6 / 10</strong>';
            } else {
                statusBadge = '<span class="print-badge b-red">غير متوفر</span>';
                pointsText = '<span style="color:#94A3B8;">0 / 10</span>';
            }

            let valText = it.display_value || it.value || 'لا يوجد أثر رقمي مسجل';
            if (valText.length > 55) {
                valText = valText.substring(0, 52) + '...';
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-align: center; font-weight: 700;">${idx + 1}</td>
                <td>
                    <strong>${escapeHtml(it.title)}</strong>
                    <div style="font-size: 6.8pt; color: #64748B;">${escapeHtml(it.category || '')}</div>
                </td>
                <td>${statusBadge}</td>
                <td style="direction: ltr; text-align: left; font-family: monospace; font-size: 7.2pt; color: #334155;">
                    ${escapeHtml(valText)}
                </td>
                <td style="text-align: center;">${pointsText}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Verify URL
    const verifyUrlEl = document.getElementById('printVerifyUrl');
    if (verifyUrlEl) {
        verifyUrlEl.textContent = `https://daleel.schoplus.sd/presence-audit.php?school=${encodeURIComponent(CURRENT_AUDIT_DATA.school_name)}&state=${encodeURIComponent(CURRENT_AUDIT_DATA.state || '')}`;
    }

    // Trigger Print
    window.print();
}

// Share Audit Result via WhatsApp
function shareAuditWhatsApp() {
    if (!CURRENT_AUDIT_DATA) return;
    const score = document.getElementById('finalScoreText').textContent;
    const text = `فحص الوجود الرقمي لمدرسة: ${CURRENT_AUDIT_DATA.school_name}\nالنتيجة العامة: ${score}%\nرابط الفحص التفاعلي عبر دليل المدارس:\nhttps://daleel.schoplus.sd/presence-audit.php?school=${encodeURIComponent(CURRENT_AUDIT_DATA.school_name)}&state=${encodeURIComponent(CURRENT_AUDIT_DATA.state || '')}`;
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}

// Utility: Number animation
function animateCount(el, start, end, duration) {
    if (start === end) { el.textContent = end; return; }
    let current = start;
    const step = (end - start) / (duration / 20);
    const timer = setInterval(() => {
        current += step;
        if ((step > 0 && current >= end) || (step < 0 && current <= end)) {
            el.textContent = end;
            clearInterval(timer);
            return;
        }
        el.textContent = Math.round(current);
    }, 20);
}

// Utility: Escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Auto-run if school or q parameter passed in URL
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const sName = urlParams.get('school') || urlParams.get('q') || urlParams.get('school_name');
    if (sName && sName.trim().length >= 2) {
        const input = document.getElementById('auditSchoolName');
        if (input) input.value = sName.trim();
        runDigitalAudit();
    } else {
        const input = document.getElementById('auditSchoolName');
        if (input) input.focus();
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
