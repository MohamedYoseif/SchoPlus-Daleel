<?php
/**
 * SchoPlus Daleel - Master Database Deduplication & Branch Disambiguation Script
 */

require_once __DIR__ . '/config.php';

function runDaleelDeduplication(PDO $pdo): array {
    $cleanPhone = function($p) {
        if (!$p) return '';
        $p = preg_replace('/[^0-9]/', '', $p);
        if (strpos($p, '249') === 0) $p = substr($p, 3);
        if (strpos($p, '00249') === 0) $p = substr($p, 5);
        if (strpos($p, '0') === 0) $p = substr($p, 1);
        return (strlen($p) >= 7) ? $p : '';
    };

    $normAr = function($str) {
        $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
        $str = str_replace('ة', 'ه', $str);
        $str = str_replace(['ى', 'ي'], 'ي', $str);
        $str = preg_replace('/[—\-\–_]/u', ' ', $str);
        $str = preg_replace('/[^\p{Arabic}\p{N}\s]/u', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    };

    $coreName = function($name, $city = '', $state = '') use ($normAr) {
        $n = $normAr($name);
        $orig = $n;
        if ($city) {
            $c = $normAr($city);
            $n = preg_replace('/\b(بـ|ب|في|فرع)\s*' . preg_quote($c, '/') . '\b/u', '', $n);
            $n = preg_replace('/\b' . preg_quote($c, '/') . '\b/u', '', $n);
        }
        if ($state) {
            $s = $normAr($state);
            $n = preg_replace('/\b(بـ|ب|في|فرع)\s*' . preg_quote($s, '/') . '\b/u', '', $n);
            $n = preg_replace('/\b' . preg_quote($s, '/') . '\b/u', '', $n);
        }
        $n = preg_replace('/^(روضه ورياض|روضه وحضانه|حضانه وروضه|روضه|رياض|حضانه|مدارس|مدرسه|مؤسسه|مجمع|سلسله مدارس)\s+/u', '', $n);
        $n = preg_replace('/\b(الخاصه|النموذجيه|الحديثه|الاهليه|القرانيه|المشتركه|التربويه|التعليميه|العالميه|الانجليزيه|الدوليه)\b/u', '', $n);
        $clean = trim(preg_replace('/\s+/', ' ', $n));

        $cleanWithoutAl = preg_replace('/^ال/', '', $clean);
        $generics = ['ثانويه', 'اساسيه', 'ابتدائيه', 'متوسطه', 'نموذجيه', 'بنات', 'بنين', ''];
        if (in_array($clean, $generics) || in_array($cleanWithoutAl, $generics) || mb_strlen($clean, 'UTF-8') <= 3) {
            $cleanOrig = preg_replace('/^(روضه ورياض|روضه وحضانه|حضانه وروضه|روضه|رياض|حضانه|مدارس|مدرسه|مؤسسه|مجمع|سلسله مدارس)\s+/u', '', $orig);
            $cleanOrig = preg_replace('/\b(الخاصه|النموذجيه|الحديثه|الاهليه|القرانيه|المشتركه|التربويه|التعليميه|العالميه|الانجليزيه|الدوليه)\b/u', '', $cleanOrig);
            return trim(preg_replace('/\s+/', ' ', $cleanOrig));
        }
        return $clean;
    };

    // 1. Identify and Process Exact Safe Duplicates FIRST
    $all = $pdo->query("SELECT * FROM daleel_schools ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $confirmedDuplicates = [];
    $count = count($all);

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $a = $all[$i];
            $b = $all[$j];

            if ($a['id'] <= 120 && $b['id'] <= 120) continue;

            $countryA = trim($a['country'] ?? '') ?: 'السودان';
            $countryB = trim($b['country'] ?? '') ?: 'السودان';
            if ($countryA !== $countryB) continue;

            $stateA = trim($a['state'] ?? '');
            $stateB = trim($b['state'] ?? '');
            if ($stateA !== '' && $stateB !== '' && $stateA !== $stateB) continue;

            $cityA = trim($a['city'] ?? '');
            $cityB = trim($b['city'] ?? '');
            if ($cityA === '' || $cityB === '' || $cityA !== $cityB) continue;

            $phoneA = $cleanPhone($a['phone']);
            $phoneB = $cleanPhone($b['phone']);
            $samePhone = ($phoneA !== '' && $phoneB !== '' && $phoneA === $phoneB);

            $coreA = $coreName($a['name'], $cityA, $stateA);
            $coreB = $coreName($b['name'], $cityB, $stateB);

            $distA = $normAr($a['district'] ?? '');
            $distB = $normAr($b['district'] ?? '');

            // Distinct gender check
            $isABoys = (strpos($a['name'], 'بنين') !== false);
            $isAGirls = (strpos($a['name'], 'بنات') !== false);
            $isBBoys = (strpos($b['name'], 'بنين') !== false);
            $isBGirls = (strpos($b['name'], 'بنات') !== false);
            if (($isABoys && $isBGirls) || ($isAGirls && $isBBoys)) continue;

            $isDuplicate = false;

            if ($samePhone && ($coreA === $coreB || str_starts_with($coreA, $coreB) || str_starts_with($coreB, $coreA))) {
                $isDuplicate = true;
            } elseif ($coreA !== '' && $coreB !== '' && $coreA === $coreB) {
                $distOverlap = false;
                if ($distA !== '' && $distB !== '') {
                    if ($distA === $distB || strpos($distA, $distB) !== false || strpos($distB, $distA) !== false) {
                        $distOverlap = true;
                    }
                } elseif ($distA === '' || $distB === '' || strpos($a['district'] ?? '', '?') !== false || strpos($b['district'] ?? '', '?') !== false) {
                    $distOverlap = true;
                }

                if ($distOverlap) {
                    $isAKg = (strpos($a['name'], 'روضة') !== false || strpos($a['name'], 'رياض') !== false || strpos($a['name'], 'حضانة') !== false);
                    $isBKg = (strpos($b['name'], 'روضة') !== false || strpos($b['name'], 'رياض') !== false || strpos($b['name'], 'حضانة') !== false);
                    if ($isAKg === $isBKg) {
                        $isDuplicate = true;
                    }
                }
            }

            if ($isDuplicate) {
                if ($a['id'] <= 120) {
                    $keeper = $a;
                    $redundant = $b;
                } elseif ($b['id'] <= 120) {
                    $keeper = $b;
                    $redundant = $a;
                } elseif ($a['is_verified'] && !$b['is_verified']) {
                    $keeper = $a;
                    $redundant = $b;
                } elseif ($b['is_verified'] && !$a['is_verified']) {
                    $keeper = $b;
                    $redundant = $a;
                } else {
                    $scoreA = 0;
                    $scoreB = 0;
                    if (!empty($a['phone'])) $scoreA += 2;
                    if (strpos($a['phone'] ?? '', '+249') !== false || strpos($a['phone'] ?? '', '+20') !== false) $scoreA += 2;
                    if (!empty($a['district']) && strpos($a['district'], '?') === false && strpos($a['district'], 'http') === false) $scoreA += 3;
                    if (!empty($a['logo']) && strpos($a['logo'], 'default') === false) $scoreA += 2;
                    if ((int)$a['views_count'] > 0 || (int)$a['reviews_count'] > 0) $scoreA += 1;

                    if (!empty($b['phone'])) $scoreB += 2;
                    if (strpos($b['phone'] ?? '', '+249') !== false || strpos($b['phone'] ?? '', '+20') !== false) $scoreB += 2;
                    if (!empty($b['district']) && strpos($b['district'], '?') === false && strpos($b['district'], 'http') === false) $scoreB += 3;
                    if (!empty($b['logo']) && strpos($b['logo'], 'default') === false) $scoreB += 2;
                    if ((int)$b['views_count'] > 0 || (int)$b['reviews_count'] > 0) $scoreB += 1;

                    if ($scoreA >= $scoreB) {
                        $keeper = $a;
                        $redundant = $b;
                    } else {
                        $keeper = $b;
                        $redundant = $a;
                    }
                }

                $delId = (int)$redundant['id'];
                $keepId = (int)$keeper['id'];

                if (!isset($confirmedDuplicates[$delId])) {
                    $confirmedDuplicates[$delId] = [
                        'keeper' => $keeper,
                        'redundant' => $redundant
                    ];
                }
            }
        }
    }

    // Specific duplicates cleanup if present
    $specificMerges = [
        3981 => 4824,
        4294 => 4944,
        4039 => 4472,
        4094 => 4989,
        4766 => 4043,
        4383 => 5119,
    ];
    foreach ($specificMerges as $sDel => $sKeep) {
        if (!isset($confirmedDuplicates[$sDel])) {
            $delRow = $pdo->query("SELECT * FROM daleel_schools WHERE id = $sDel")->fetch(PDO::FETCH_ASSOC);
            $keepRow = $pdo->query("SELECT * FROM daleel_schools WHERE id = $sKeep")->fetch(PDO::FETCH_ASSOC);
            if ($delRow && $keepRow) {
                $confirmedDuplicates[$sDel] = [
                    'keeper' => $keepRow,
                    'redundant' => $delRow
                ];
            }
        }
    }

    // 2. Merge & Delete Duplicates
    $deletedCount = 0;
    foreach ($confirmedDuplicates as $delId => $info) {
        $k = $info['keeper'];
        $r = $info['redundant'];
        $keepId = (int)$k['id'];

        try {
            $pdo->prepare("UPDATE daleel_school_reviews SET school_id = ? WHERE school_id = ?")->execute([$keepId, $delId]);
            $pdo->prepare("UPDATE daleel_school_news SET school_id = ? WHERE school_id = ?")->execute([$keepId, $delId]);
            $pdo->prepare("UPDATE daleel_school_inquiries SET school_id = ? WHERE school_id = ?")->execute([$keepId, $delId]);
        } catch (Throwable $e) {}

        $upData = [];
        $params = [];
        if (empty($k['phone']) && !empty($r['phone'])) {
            $upData[] = "phone = ?";
            $params[] = $r['phone'];
        }
        if (empty($k['whatsapp']) && !empty($r['whatsapp'])) {
            $upData[] = "whatsapp = ?";
            $params[] = $r['whatsapp'];
        }
        if (empty($k['district']) && !empty($r['district']) && strpos($r['district'], '?') === false) {
            $upData[] = "district = ?";
            $params[] = $r['district'];
        }
        if (empty($k['address']) && !empty($r['address'])) {
            $upData[] = "address = ?";
            $params[] = $r['address'];
        }
        if (empty($k['description']) && !empty($r['description'])) {
            $upData[] = "description = ?";
            $params[] = $r['description'];
        }
        if (!empty($r['views_count'])) {
            $upData[] = "views_count = views_count + ?";
            $params[] = (int)$r['views_count'];
        }

        if (!empty($upData)) {
            $params[] = $keepId;
            $pdo->prepare("UPDATE daleel_schools SET " . implode(', ', $upData) . " WHERE id = ?")->execute($params);
        }

        $pdo->prepare("DELETE FROM daleel_schools WHERE id = ?")->execute([$delId]);
        $deletedCount++;
    }

    // 3. Rename Distinct City Branches that shared identical names to avoid slug collisions
    $branchRenames = [
        ['old_name' => 'مدارس القبس الخاصة', 'city' => 'عطبرة', 'name' => 'مدارس القبس الخاصة — عطبرة', 'slug' => 'مدارس-القبس-الخاصة-عطبرة', 'district' => 'حي المطار مربع 11'],
        ['old_name' => 'مؤسسة د. أبوذر الكودة التعليمية', 'city' => 'عطبرة', 'name' => 'مؤسسة د. أبوذر الكودة التعليمية — عطبرة', 'slug' => 'مؤسسة-د-أبوذر-الكودة-التعليمية-عطبرة', 'district' => 'مربع 4'],
        ['old_name' => 'مدارس المعالي الخاصة', 'city' => 'أبو جبيهة', 'name' => 'مدارس المعالي الخاصة — أبو جبيهة', 'slug' => 'مدارس-المعالي-الخاصة-أبو-جبيهة', 'district' => 'حي السلام'],
        ['old_name' => 'مدارس الأوائل الخاصة', 'city' => 'خشم القربة', 'name' => 'مدارس الأوائل الخاصة — خشم القربة', 'slug' => 'مدارس-الأوائل-الخاصة-خشم-القربة', 'district' => 'وسط المدينة'],
        ['old_name' => 'مدرسة الأستاذ الخاصة', 'city' => 'جبل أولياء', 'name' => 'مدرسة الأستاذ الخاصة — جبل أولياء', 'slug' => 'مدرسة-الأستاذ-الخاصة-جبل-أولياء', 'district' => 'جبل أولياء'],
        ['old_name' => 'مدرسة الأستاذ الخاصة', 'city' => 'الدمازين', 'name' => 'مدرسة الأستاذ الخاصة — الدمازين', 'slug' => 'مدرسة-الأستاذ-الخاصة-الدمازين', 'district' => 'حي النهضة'],
        ['old_name' => 'مدرسة النهضة الثانوية الخاصة', 'city' => 'عطبرة', 'name' => 'مدرسة النهضة الثانوية الخاصة — عطبرة', 'slug' => 'مدرسة-النهضة-الثانوية-الخاصة-عطبرة', 'district' => 'جنوب غرب السويطي'],
        ['old_name' => 'مدرسة النهضة الثانوية الخاصة', 'city' => 'نيالا', 'name' => 'مدرسة النهضة الثانوية الخاصة — نيالا', 'slug' => 'مدرسة-النهضة-الثانوية-الخاصة-نيالا', 'district' => 'وسط نيالا'],
        ['old_name' => 'مدارس التميز الخاصة', 'city' => 'القضارف', 'name' => 'مدارس التميز الخاصة — القضارف', 'slug' => 'مدارس-التميز-الخاصة-القضارف', 'district' => 'وسط القضارف'],
        ['old_name' => 'مدرسة أجيال المستقبل الخاصة', 'city' => 'زالنجي', 'name' => 'مدرسة أجيال المستقبل الخاصة — زالنجي', 'slug' => 'مدرسة-أجيال-المستقبل-الخاصة-زالنجي', 'district' => 'وسط زالنجي'],
        ['old_name' => 'مدرسة الآفاق الثانوية الخاصة', 'city' => 'قارسيلا', 'name' => 'مدرسة الآفاق الثانوية الخاصة — قارسيلا', 'slug' => 'مدرسة-الآفاق-الثانوية-الخاصة-قارسيلا', 'district' => 'وادي صالح'],
        ['old_name' => 'مدارس العلا الثانوية الخاصة', 'city' => 'حلفا الجديدة', 'name' => 'مدارس العلا الثانوية الخاصة — حلفا الجديدة', 'slug' => 'مدارس-العلا-الثانوية-الخاصة-حلفا-الجديدة', 'district' => 'وسط المدينة'],
        ['old_name' => 'مدارس الأوائل الثانوية الخاصة', 'city' => 'كسلا', 'name' => 'مدارس الأوائل الثانوية الخاصة — كسلا', 'slug' => 'مدارس-الأوائل-الثانوية-الخاصة-كسلا', 'district' => 'وسط كسلا'],
        ['old_name' => 'روضة براعم النصر الخاصة', 'city' => 'جبل أولياء', 'name' => 'روضة براعم النصر الخاصة — جبل أولياء', 'slug' => 'روضة-براعم-النصر-الخاصة-جبل-أولياء', 'district' => 'طيبة الحسناب'],
        ['old_name' => 'روضة براعم النصر الخاصة', 'city' => 'ود مدني', 'name' => 'روضة براعم النصر الخاصة — ود مدني', 'slug' => 'روضة-براعم-النصر-الخاصة-ود-مدني', 'district' => 'حي أركويت']
    ];

    $upBranch = $pdo->prepare("UPDATE daleel_schools SET name = :name, slug = :slug, district = COALESCE(NULLIF(:district, ''), district) WHERE name = :old_name AND city = :city");
    foreach ($branchRenames as $bData) {
        try {
            $upBranch->execute([
                ':name' => $bData['name'],
                ':slug' => $bData['slug'],
                ':district' => $bData['district'],
                ':old_name' => $bData['old_name'],
                ':city' => $bData['city']
            ]);
        } catch (Throwable $e) {}
    }

    // Fix Abu Kershola & other specific anomalies
    try {
        $pdo->exec("UPDATE daleel_schools SET 
            city = 'أبو كرشولا', 
            district = 'حي السوق الكبير', 
            address = 'ولاية جنوب كردفان — محلية أبو كرشولا — حي السوق الكبير',
            name = 'مدرسة الأندلس الخاصة بنات — أبو كرشولا', 
            slug = 'مدرسة-الأندلس-الخاصة-بنات-أبو-كرشولا' 
            WHERE id = 4385");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE daleel_schools SET slug = 'مدرسة-العوالي-الخاصة-كادوقلي' WHERE id = 5119");
    } catch (Throwable $e) {}

    // 4. Recalculate directory_rank and state_rank for all approved schools
    $approvedRows = $pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $dirSeq = 0;
    $stateSeqs = [];
    $upRanksStmt = $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?");
    foreach ($approvedRows as $row) {
        $dirSeq++;
        $cntryKey = trim((string)($row['country'] ?? '')) ?: 'السودان';
        $stKey = trim((string)($row['state'] ?? ''));
        $groupKey = $cntryKey . ':::' . $stKey;
        $stateSeqs[$groupKey] = ($stateSeqs[$groupKey] ?? 0) + 1;
        $stSeq = $stateSeqs[$groupKey];
        $upRanksStmt->execute([$dirSeq, $stSeq, (int)$row['id']]);
    }

    return [
        'renamed' => count($branchRenames),
        'deleted' => $deletedCount,
        'remaining' => count($approvedRows)
    ];
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $res = runDaleelDeduplication(db());
    print_r($res);
}
