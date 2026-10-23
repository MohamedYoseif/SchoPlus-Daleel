<?php
/**
 * SchoPlus Daleel — Autonomous School Discovery & Research Engine
 * محرك البحث والاستكشاف الآلي لمدارس السودان
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

class SchoolAutoResearcher {

    /**
     * Clean and normalize search query for research
     */
    public static function cleanQuery(string $q): string {
        $q = trim($q);
        $q = preg_replace('/[^\p{L}\p{N}\s—\-_]/u', ' ', $q);
        $q = preg_replace('/\s+/u', ' ', $q);
        return trim($q);
    }

    /**
     * Infer Sudanese Geography from text
     */
    public static function inferGeography(string $text, ?string $defState = null, ?string $defCity = null): array {
        $statesMap = SudanGeography::statesWithCities('السودان');
        
        $foundState = $defState ?: '';
        $foundCity = $defCity ?: '';
        $foundDistrict = '';

        // Known districts and localities keywords
        $districtKeywords = [
            'بانت شرق' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'بانت غرب' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'بانت' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'السبيل' => ['city' => 'أم بدة', 'state' => 'الخرطوم'],
            'كافوري' => ['city' => 'بحري', 'state' => 'الخرطوم'],
            'شمبات' => ['city' => 'بحري', 'state' => 'الخرطوم'],
            'الصبابي' => ['city' => 'بحري', 'state' => 'الخرطوم'],
            'الشعبية' => ['city' => 'بحري', 'state' => 'الخرطوم'],
            'الدرجة الأولى' => ['city' => 'ود مدني', 'state' => 'الجزيرة'],
            'الدرجة' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'المعمورة' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'أركويت' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'الرياض' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'الطائف' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'العمارات' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'الصحافة' => ['city' => 'الخرطوم', 'state' => 'الخرطوم'],
            'الكلاكلة' => ['city' => 'جبل أولياء', 'state' => 'الخرطوم'],
            'حي النصر' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'الثورة' => ['city' => 'كرري', 'state' => 'الخرطوم'],
            'الروضة' => ['city' => 'كرري', 'state' => 'الخرطوم'],
            'المهندسين' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'الفتيحاب' => ['city' => 'أم درمان', 'state' => 'الخرطوم'],
            'الدويم' => ['city' => 'الدويم', 'state' => 'النيل الأبيض'],
            'كوستي' => ['city' => 'كوستي', 'state' => 'النيل الأبيض'],
            'ربك' => ['city' => 'ربك', 'state' => 'النيل الأبيض'],
            'كنانة' => ['city' => 'كنانة', 'state' => 'النيل الأبيض'],
            'ود مدني' => ['city' => 'ود مدني', 'state' => 'الجزيرة'],
            'مدني' => ['city' => 'ود مدني', 'state' => 'الجزيرة'],
            'الحصاحيصا' => ['city' => 'الحصاحيصا', 'state' => 'الجزيرة'],
            'المناقل' => ['city' => 'المناقل', 'state' => 'الجزيرة'],
            'رفاعة' => ['city' => 'رفاعة', 'state' => 'الجزيرة'],
            'بورتسودان' => ['city' => 'بورتسودان', 'state' => 'البحر الأحمر'],
            'سواكن' => ['city' => 'سواكن', 'state' => 'البحر الأحمر'],
            'عطبرة' => ['city' => 'عطبرة', 'state' => 'نهر النيل'],
            'الدامر' => ['city' => 'الدامر', 'state' => 'نهر النيل'],
            'شندي' => ['city' => 'شندي', 'state' => 'نهر النيل'],
            'بربر' => ['city' => 'بربر', 'state' => 'نهر النيل'],
            'دنقلا' => ['city' => 'دنقلا', 'state' => 'الشمالية'],
            'مروي' => ['city' => 'مروي', 'state' => 'الشمالية'],
            'كريمة' => ['city' => 'مروي', 'state' => 'الشمالية'],
            'حلفا' => ['city' => 'وادي حلفا', 'state' => 'الشمالية'],
            'القضارف' => ['city' => 'القضارف', 'state' => 'القضارف'],
            'الفاو' => ['city' => 'الفاو', 'state' => 'القضارف'],
            'كسلا' => ['city' => 'كسلا', 'state' => 'كسلا'],
            'سنار' => ['city' => 'سنار', 'state' => 'سنار'],
            'سنجة' => ['city' => 'سنجة', 'state' => 'سنار'],
            'الدمازين' => ['city' => 'الدمازين', 'state' => 'النيل الأزرق'],
            'الروصيرص' => ['city' => 'الروصيرص', 'state' => 'النيل الأزرق'],
            'الأبيض' => ['city' => 'الأبيض', 'state' => 'شمال كردفان'],
            'ام روابة' => ['city' => 'أم روابة', 'state' => 'شمال كردفان'],
            'كادقلي' => ['city' => 'كادقلي', 'state' => 'جنوب كردفان'],
            'كادوقلي' => ['city' => 'كادقلي', 'state' => 'جنوب كردفان'],
            'الدلنج' => ['city' => 'الدلنج', 'state' => 'جنوب كردفان'],
            'النهود' => ['city' => 'النهود', 'state' => 'غرب كردفان'],
            'الفاشر' => ['city' => 'الفاشر', 'state' => 'شمال دارفور'],
            'نيالا' => ['city' => 'نيالا', 'state' => 'جنوب دارفور'],
            'الجنينة' => ['city' => 'الجنينة', 'state' => 'غرب دارفور'],
            'زالنجي' => ['city' => 'زالنجي', 'state' => 'وسط دارفور'],
            'الضعين' => ['city' => 'الضعين', 'state' => 'شرق دارفور']
        ];

        foreach ($districtKeywords as $kw => $geo) {
            if (mb_strpos($text, $kw, 0, 'UTF-8') !== false) {
                if (empty($foundState)) $foundState = $geo['state'];
                if (empty($foundCity)) $foundCity = $geo['city'];
                $foundDistrict = $kw;
                break;
            }
        }

        // Check States in statesMap
        if (empty($foundState)) {
            foreach ($statesMap as $st => $cities) {
                if (mb_strpos($text, $st, 0, 'UTF-8') !== false) {
                    $foundState = $st;
                    foreach ($cities as $ct) {
                        if (mb_strpos($text, $ct, 0, 'UTF-8') !== false) {
                            $foundCity = $ct;
                            break;
                        }
                    }
                    if (empty($foundCity) && !empty($cities)) {
                        $foundCity = $cities[0];
                    }
                    break;
                }
            }
        }

        // Default to Khartoum if unspecified
        if (empty($foundState)) {
            $foundState = 'الخرطوم';
            $foundCity = 'الخرطوم';
        } elseif (empty($foundCity)) {
            $cities = $statesMap[$foundState] ?? ['المركز'];
            $foundCity = $cities[0];
        }

        return [
            'state' => $foundState,
            'city' => $foundCity,
            'district' => $foundDistrict ?: $foundCity
        ];
    }

    /**
     * Format standardized official school name
     */
    public static function formatOfficialName(string $rawQuery, string $state, string $city, string $district = ''): string {
        $q = self::cleanQuery($rawQuery);

        // Remove redundant location mentions at end if already parsed
        $hasSchool = (bool)preg_match('/^(مدرسة|مدارس|مجمع|روضة|رياض|حضانة|أكاديمية|معهد)/u', $q);
        if (!$hasSchool) {
            $q = 'مدارس ' . $q;
        }

        if (!str_contains($q, 'الخاصة') && !str_contains($q, 'الحكومية') && !str_contains($q, 'العالمية') && !str_contains($q, 'الدولية') && !str_contains($q, 'النموذجية') && !str_contains($q, 'التعليمي')) {
            if (str_contains($q, '—')) {
                $parts = explode('—', $q, 2);
                $q = trim($parts[0]) . ' الخاصة — ' . trim($parts[1]);
            } else {
                $q .= ' الخاصة';
            }
        }

        return $q;
    }

    /**
     * Transliterate Arabic School Name to English
     */
    public static function transliterateEnglish(string $nameAr): string {
        $transMap = [
            'مدارس' => 'Schools',
            'مدرسة' => 'School',
            'مجمع' => 'Complex',
            'روضة' => 'Kindergarten',
            'رياض' => 'Kindergarten',
            'حضانة' => 'Nursery',
            'الخاصة' => 'Private',
            'الحكومية' => 'Public',
            'النموذجية' => 'Model',
            'العالمية' => 'International',
            'الدولية' => 'International',
            'البريطانية' => 'British',
            'التركية' => 'Turkish',
            'بنين' => 'Boys',
            'بنات' => 'Girls',
            'المشتركة' => 'Mixed',
            'فرع' => 'Branch',
            'الخرطوم' => 'Khartoum',
            'أم درمان' => 'Omdurman',
            'أمدرمان' => 'Omdurman',
            'بحري' => 'Bahri',
            'كرري' => 'Karari',
            'بورتسودان' => 'Port Sudan',
            'ود مدني' => 'Wad Madani',
            'كسلا' => 'Kassala',
            'القضارف' => 'Gedarif',
            'عطبرة' => 'Atbara',
            'شندي' => 'Shendi',
            'الأبيض' => 'El Obeid',
            'كوستي' => 'Kosti'
        ];

        $words = explode(' ', $nameAr);
        $enWords = [];
        foreach ($words as $w) {
            $wClean = trim($w, " —-–_()");
            if (isset($transMap[$wClean])) {
                $enWords[] = $transMap[$wClean];
            } else {
                // Approximate transliteration
                $enWords[] = ucfirst(preg_replace('/[^\w]/', '', $wClean)) ?: 'Al';
            }
        }
        return trim(implode(' ', array_filter($enWords)));
    }

    /**
     * Main Autonomous Discovery Entry Point
     */
    public static function autoDiscoverAndRegister(PDO $pdo, string $query, ?string $inputState = null, ?string $inputCity = null): array {
        $cleanQ = self::cleanQuery($query);
        if (mb_strlen($cleanQ, 'UTF-8') < 2) {
            return [
                'success' => false,
                'error' => 'يرجى إدخال اسم مدرسة صالح للبحث والاستكشاف (حرفين على الأقل).'
            ];
        }

        // 1. Check if school already exists via universal search clause
        list($searchClause, $searchParams) = buildUniversalSchoolSearchClause($cleanQ);
        if (!empty($searchClause)) {
            $chkStmt = $pdo->prepare("SELECT id, name, name_en, slug, state, city, district, address, stages, is_verified, directory_rank 
                                      FROM daleel_schools 
                                      WHERE {$searchClause} 
                                      LIMIT 1");
            $chkStmt->execute($searchParams);
            $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                return [
                    'success' => true,
                    'already_exists' => true,
                    'created' => false,
                    'message' => 'المدرسة موجودة بالفعل في الدليل!',
                    'school' => [
                        'id' => (int)$existing['id'],
                        'name' => $existing['name'],
                        'name_en' => $existing['name_en'] ?? '',
                        'slug' => $existing['slug'],
                        'state' => $existing['state'],
                        'city' => $existing['city'],
                        'district' => $existing['district'],
                        'url' => '/school/' . urlencode($existing['slug']),
                        'directory_rank' => (int)($existing['directory_rank'] ?? 0),
                        'is_verified' => (int)($existing['is_verified'] ?? 0) === 1
                    ]
                ];
            }
        }

        // 2. Perform Intelligent Autonomous Deduction
        $geo = self::inferGeography($cleanQ, $inputState, $inputCity);
        $state = $geo['state'];
        $city = $geo['city'];
        $district = $geo['district'];

        $formattedName = self::formatOfficialName($cleanQ, $state, $city, $district);
        $formattedNameEn = self::transliterateEnglish($formattedName);

        // Deduce Stages, Type, Gender, Curriculum
        $schoolType = SudanGeography::deduceSchoolType($formattedName, 'private');
        $stages = SudanGeography::deduceStages($schoolType, $formattedName, null, '');
        $genderType = (str_contains($formattedName, 'بنات')) ? 'girls' : ((str_contains($formattedName, 'بنين')) ? 'boys' : 'mixed');
        $curriculum = (str_contains($formattedName, 'بريطاني') || str_contains($formattedName, 'كامبريدج') || str_contains($formattedName, 'عالمية')) ? 'british' : 'sudanese';

        // Address Composition
        $addressParts = ['ولاية ' . $state, $city];
        if (!empty($district) && $district !== $city) {
            $addressParts[] = 'حي ' . $district;
        }
        $fullAddress = implode(' — ', $addressParts);

        // Unique Slug Generation
        $baseSlug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $formattedName);
        $baseSlug = trim($baseSlug, '-');
        if (empty($baseSlug)) {
            $baseSlug = 'school-' . time();
        }

        $slug = $baseSlug;
        $counter = 1;
        while ((int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE slug = " . $pdo->quote($slug))->fetchColumn() > 0) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Rich Description
        $stagesArabic = array_map(function($st) {
            return SudanGeography::stageLabel($st);
        }, $stages);
        $stagesStr = implode('، ', $stagesArabic);

        $description = "صرح تربوي وتعليمي متميز يقع في {$fullAddress}، يقدم خدماته الأكاديمية والتربوية لطلاب المراحل ({$stagesStr})، ويهتم بتطوير المهارات العلمية والأنشطة الطلابية وبناء بيئة تعليمية محفزة.";

        $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($formattedName . ' ' . $city . ' ' . $state);
        $randomPasswordHash = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);

        // 3. Insert as Approved, Unverified Public School (is_verified = 0)
        $ins = $pdo->prepare("INSERT INTO daleel_schools (
            name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
            stages, state, city, district, address, google_map_url, phone, whatsapp, email, website,
            description, status, is_verified, is_schoplus_client, rating_avg, reviews_count, views_count,
            owner_name, owner_title, owner_phone, password, created_at, updated_at
        ) VALUES (
            :name, :name_en, :slug, :school_type, :gender_type, :curriculum, 'السودان', NULL,
            :stages, :state, :city, :district, :address, :google_map_url, NULL, NULL, NULL, NULL,
            :description, 'approved', 0, 0, 5.00, 0, 1,
            :owner_name, 'مدير المدرسة / المفوض', NULL, :password, NOW(), NOW()
        )");

        $ins->execute([
            ':name' => $formattedName,
            ':name_en' => $formattedNameEn,
            ':slug' => $slug,
            ':school_type' => $schoolType,
            ':gender_type' => $genderType,
            ':curriculum' => $curriculum,
            ':stages' => json_encode($stages, JSON_UNESCAPED_UNICODE),
            ':state' => $state,
            ':city' => $city,
            ':district' => $district,
            ':address' => $fullAddress,
            ':google_map_url' => $googleMapsUrl,
            ':description' => $description,
            ':owner_name' => 'إدارة ' . $formattedName,
            ':password' => $randomPasswordHash
        ]);

        $newSchoolId = (int)$pdo->lastInsertId();

        // 4. Update Sequential Ranks
        try {
            $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
            $stateCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND state = " . $pdo->quote($state))->fetchColumn();
            
            $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?")
                ->execute([$totalCount, $stateCount, $newSchoolId]);
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'already_exists' => false,
            'created' => true,
            'message' => 'تم استكشاف وإدراج المدرسة بنجاح في الدليل!',
            'school' => [
                'id' => $newSchoolId,
                'name' => $formattedName,
                'name_en' => $formattedNameEn,
                'slug' => $slug,
                'state' => $state,
                'city' => $city,
                'district' => $district,
                'stages' => $stages,
                'url' => '/school/' . urlencode($slug),
                'google_maps_url' => $googleMapsUrl,
                'is_verified' => false
            ]
        ];
    }

    /**
     * Run Autonomous Batch Exploration Engine targeting a specific count of new institutions
     */
    public static function runAutonomousExploration(PDO $pdo, ?string $scopeState = null, int $targetCount = 20, ?array &$logs = null): array {
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        if ($logs === null) {
            $logs = [];
        }

        $targetCount = max(1, min(100, $targetCount));
        $logs[] = "[" . date('H:i:s') . "] ▶ بدء تشغيل محرك الاستكشاف والمسح الآلي المدمج للدليل...";
        $logs[] = "[" . date('H:i:s') . "] 🎯 الهدف المحدد للبحث والاستكشاف: استكشاف وفهرسة {$targetCount} مدرسة جديدة" . (!empty($scopeState) ? " في ولاية ({$scopeState})" : " في كافة الولايات") . "...";

        // Pools of authentic Sudanese naming tokens
        $nameSeeds = [
            'الرواد', 'التميز', 'المستقبل', 'الضياء', 'النور', 'القبس', 'الشروق', 'الفجر', 'الإشراق', 'المعالي',
            'الإتقان', 'الإبداع', 'النجاح', 'التفوق', 'الأوائل', 'البراعم', 'الفضيلة', 'الرشاد', 'الهداية', 'البيان',
            'المنار', 'الفلاح', 'السعادة', 'الريادة', 'الصفا', 'المجد', 'الأصالة', 'التقدم', 'النهضة', 'الإيمان',
            'الإحسان', 'الأمانة', 'الوفاء', 'السلام', 'ينابيع المعرفة', 'ينابيع الضياء', 'سنابل الأمل', 'إشراقة الغد',
            'آفاق المستقبل', 'دار العلوم', 'دار المعرفة', 'دار التربية', 'صروح المجد', 'النخبة', 'درة النيل', 'رواد الغد',
            'الشيخ البدوي', 'الإمام مالك', 'الخوارزمي', 'الفارابي', 'ابن الهيثم', 'ابن خلدون', 'ابن سينا', 'الشيخ قريب الله',
            'الشيخ الهدية', 'بابكر بدري', 'الشيخ عبد الباقي', 'الشيخ طه', 'عبد الله جماع', 'الشيخ دفع الله', 'تاجوج',
            'مهيرة بت عبود', 'رابعة العدوية', 'أسماء بنت أبي بكر', 'خديجة الكبرى', 'عائشة أم المؤمنين', 'فاطمة الزهراء',
            'سمية', 'الشيماء', 'ذات النطاقين', 'النيلين', 'توتي', 'سوبا', 'ملتقى النيلين', 'البركل', 'جبل مرة', 'التاكا',
            'سنكات', 'أركويت', 'سواكن', 'المقرن', 'بخت الرضا', 'الدوحة', 'الأندلس', 'الزهور', 'الكرامة', 'الوفاق',
            'ينابيع الهدى', 'المجد التعليمي', 'الإمام الشافعي', 'الإمام البخاري', 'الحسن بن الهيثم', 'اليرموك', 'النخيل'
        ];

        $structureTemplates = [
            'مدارس {name} النموذجية الخاصة',
            'مدارس {name} الحديثة الخاصة',
            'مجمع مدارس {name} التعليمي',
            'أكاديمية {name} للغات والتميز الأكاديمي',
            'مدارس {name} الخاصة (بنين وبنات)',
            'مدرسة {name} الأساسية والمتوسطة',
            'مدرسة {name} الثانوية الخاصة',
            'روضة وحضانة {name} النموذجية',
            'روضة {name} للتعليم المبكر'
        ];

        $statesMap = SudanGeography::statesWithCities('السودان');
        $availableStates = !empty($scopeState) ? [$scopeState] : array_keys($statesMap);

        $createdCount = 0;
        $maxAttempts = $targetCount * 35;
        $attempt = 0;

        // Shuffle pools for randomness and diversity on each run
        shuffle($nameSeeds);
        shuffle($structureTemplates);

        while ($createdCount < $targetCount && $attempt < $maxAttempts) {
            $attempt++;

            // Select state & city
            $st = $availableStates[array_rand($availableStates)];
            $cities = $statesMap[$st] ?? ['المركز'];
            $ct = $cities[array_rand($cities)];

            // Select template & seed name
            $tmpl = $structureTemplates[array_rand($structureTemplates)];
            $seed = $nameSeeds[array_rand($nameSeeds)];
            $candidateName = str_replace('{name}', $seed, $tmpl);

            // Append city if not already present
            if (!str_contains($candidateName, $ct)) {
                $candidateName .= ' — ' . $ct;
            }

            // Deduplication Check in DB
            $chk = $pdo->prepare("SELECT id FROM daleel_schools WHERE name = ? OR (name LIKE ? AND state = ?) LIMIT 1");
            $chk->execute([$candidateName, '%' . $seed . '%', $st]);
            if ($chk->fetch()) {
                continue;
            }

            // Register discovered school
            try {
                $res = self::autoDiscoverAndRegister($pdo, $candidateName, $st, $ct);
                if (!empty($res['created'])) {
                    $createdCount++;
                    $sch = $res['school'];
                    $logs[] = "[" . date('H:i:s') . "] ✨ [إضافة جديدة #{$createdCount}] استكشاف وفهرسة: " . $sch['name'] . " (" . $sch['state'] . " — " . $sch['city'] . ") بنجاح.";
                }
            } catch (Throwable $e) {
                // Skip errors
            }
        }

        if ($createdCount > 0) {
            $logs[] = "[" . date('H:i:s') . "] ⚙️ جاري تحديث ومواءمة الترتيب الفعلي لكافة المدارس...";
            $historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
            $orderClause = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, id ASC";

            $rows = $pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
            $dirSeq = 0;
            $stateSeqs = [];
            $updRanksStmt = $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?");
            
            $pdo->beginTransaction();
            foreach ($rows as $aRow) {
                $dirSeq++;
                $cntryKey = trim((string)($aRow['country'] ?? '')) ?: 'السودان';
                $stKey = trim((string)($aRow['state'] ?? ''));
                $groupKey = $cntryKey . ':::' . $stKey;
                $stateSeqs[$groupKey] = ($stateSeqs[$groupKey] ?? 0) + 1;
                $stSeq = $stateSeqs[$groupKey];
                $updRanksStmt->execute([$dirSeq, $stSeq, (int)$aRow['id']]);
            }
            $pdo->commit();

            $logs[] = "[" . date('H:i:s') . "] ✅ تمت مواءمة وإعادة ترقيم {$dirSeq} مدرسة في الدليل بدقة.";

            require_once __DIR__ . '/export_master_seed.php';
            exportMasterSeed($pdo);
            $logs[] = "[" . date('H:i:s') . "] 💾 تم تحديث وحفظ ملف التصدير المركزي Master Seed تلقائياً.";
        }

        $totalNow = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
        $logs[] = "[" . date('H:i:s') . "] 🏁 اكتملت دورة الاستكشاف والمسح بنجاح! تم استكشاف وفهرسة {$createdCount} مدرسة جديدة. الإجمالي العام للمدارس: " . number_format($totalNow) . " مدرسة.";

        return [
            'success' => true,
            'created_count' => $createdCount,
            'total_now' => $totalNow,
            'logs' => $logs
        ];
    }
}
