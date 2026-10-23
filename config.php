<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Standalone Node Configuration & Database Bootstrap
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/SudanHistoricSchools.php';

// Security & Helper Functions
if (!function_exists('e')) {
    function e($val): string {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('assetUrl')) {
    function assetUrl(?string $path): string {
        $path = trim((string)($path ?? ''));
        if ($path === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('getSystemVersion')) {
    function getSystemVersion(): string {
        static $ver = null;
        if ($ver === null) {
            $ver = '3.8.11';
            $vFile = __DIR__ . '/version.json';
            if (file_exists($vFile)) {
                $vData = json_decode(@file_get_contents($vFile), true);
                if (!empty($vData['version'])) $ver = $vData['version'];
            }
        }
        return $ver;
    }
}
$systemVersion = getSystemVersion();

if (!function_exists('defaultSchoolLogo')) {
    function defaultSchoolLogo(?string $path): string {
        $path = trim((string)($path ?? ''));
        if ($path !== '') {
            return assetUrl($path);
        }
        return '/assets/images/default-school-logo.svg';
    }
}

if (!function_exists('defaultNewsImage')) {
    function defaultNewsImage(?string $path, string $title = '', string $schoolName = '', string $branchName = ''): string {
        $path = trim((string)($path ?? ''));
        if ($path !== '') {
            return assetUrl($path);
        }

        // Generate dynamic branded SVG data URI with Title, School Name, and Branch
        $cleanTitle = htmlspecialchars(mb_substr($title ?: 'خبر أو إعلان', 0, 75), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cleanSchool = htmlspecialchars(mb_substr($schoolName ?: 'مؤسسة تعليمية', 0, 60), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cleanBranch = $branchName ? ' — ' . htmlspecialchars(mb_substr($branchName, 0, 40), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';

        // Multi-line wrap for long headlines to ensure perfect visibility without clipping
        $words = preg_split('/\s+/u', $cleanTitle, -1, PREG_SPLIT_NO_EMPTY);
        $line1 = '';
        $line2 = '';
        if (is_array($words) && count($words) > 1 && mb_strlen($cleanTitle) > 30) {
            foreach ($words as $w) {
                if (mb_strlen($line1 . ' ' . $w) <= 32) {
                    $line1 = trim($line1 . ' ' . $w);
                } else {
                    $line2 = trim($line2 . ' ' . $w);
                }
            }
        } else {
            $line1 = $cleanTitle;
        }

        $titleSvg = '';
        if ($line2 !== '') {
            $titleSvg = '<text x="300" y="125" fill="#FFFFFF" font-family="Cairo, Segoe UI, Tahoma, sans-serif" font-size="19" font-weight="800" text-anchor="middle">' . $line1 . '</text>'
                      . '<text x="300" y="156" fill="#FFFFFF" font-family="Cairo, Segoe UI, Tahoma, sans-serif" font-size="18" font-weight="800" text-anchor="middle">' . $line2 . '</text>';
        } else {
            $titleSvg = '<text x="300" y="140" fill="#FFFFFF" font-family="Cairo, Segoe UI, Tahoma, sans-serif" font-size="22" font-weight="800" text-anchor="middle">' . $line1 . '</text>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 320" width="600" height="320">'
            . '<rect width="600" height="320" rx="16" fill="#0B2A52"/>'
            . '<circle cx="530" cy="50" r="140" fill="rgba(255,255,255,0.03)"/>'
            . '<circle cx="70" cy="270" r="120" fill="rgba(255,255,255,0.02)"/>'
            . '<rect x="20" y="20" width="560" height="280" rx="14" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>'
            . '<rect x="235" y="34" width="130" height="28" rx="14" fill="rgba(255,255,255,0.12)" stroke="rgba(245,160,32,0.3)" stroke-width="1"/>'
            . '<text x="300" y="53" fill="#F5A020" font-family="Cairo, Segoe UI, Tahoma, sans-serif" font-size="12" font-weight="700" text-anchor="middle">إعلان مدرسي</text>'
            . $titleSvg
            . '<line x1="200" y1="188" x2="400" y2="188" stroke="#F5A020" stroke-width="3" stroke-linecap="round"/>'
            . '<text x="300" y="228" fill="#E2E8F0" font-family="Cairo, Segoe UI, Tahoma, sans-serif" font-size="16" font-weight="700" text-anchor="middle">' . $cleanSchool . $cleanBranch . '</text>'
            . '<rect x="0" y="312" width="600" height="8" fill="#F5A020"/>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('normalizeArabicForSearch')) {
    function normalizeArabicForSearch(?string $str): string {
        if ($str === null || $str === '') return '';
        $s = mb_strtolower(trim($str), 'UTF-8');
        // Remove diacritics / tashkeel
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s);
        // Normalize Alefs (أ, إ, آ, ٱ -> ا)
        $s = preg_replace('/[أإآٱ]/u', 'ا', $s);
        // Normalize Taa Marbuta (ة -> ه)
        $s = preg_replace('/ة/u', 'ه', $s);
        // Normalize Yaa / Alef Maksura (ي, ى -> ي)
        $s = preg_replace('/[يى]/u', 'ي', $s);
        // Normalize Hamzas (ؤ -> و, ئ -> ي)
        $s = preg_replace('/ؤ/u', 'و', $s);
        $s = preg_replace('/ئ/u', 'ي', $s);
        // Remove excessive punctuation and multiple spaces
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }
}

if (!function_exists('buildUniversalSchoolSearchClause')) {
    function buildUniversalSchoolSearchClause(string $query): array {
        $rawNorm = normalizeArabicForSearch($query);
        if ($rawNorm === '') {
            return ['', []];
        }

        // MySQL expression that normalizes Arabic characters on the fly across all search fields
        $normSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ئ', 'ي')";
        
        $schoolNouns = ['مدرسه', 'مدارس', 'مدار', 'مجمع', 'مؤسسه', 'سلسله'];
        $typeWords = ['خاصه', 'حكوميه', 'دوليه', 'عالميه', 'اهليه', 'نموذجيه', 'بنين', 'بنات', 'مشترك', 'مختلط'];
        $stageWords = ['روضه', 'رياض', 'حضانه', 'اطفال', 'ابتدائي', 'اساس', 'متوسط', 'ثانوي'];
        $stopWords = ['في', 'من', 'الي', 'علي', 'عن', 'مع', 'ب'];

        // Tokenize search query
        $tokens = array_values(array_filter(explode(' ', $rawNorm), function($t) {
            return mb_strlen(trim($t), 'UTF-8') >= 2;
        }));

        if (empty($tokens)) {
            $tokens = [$rawNorm];
        }

        $hasSchoolNoun = false;
        $distinctiveTokens = [];
        $typeTokens = [];
        $stageTokens = [];

        foreach ($tokens as $t) {
            if (in_array($t, $schoolNouns)) {
                $hasSchoolNoun = true;
                continue;
            }
            if (in_array($t, $typeWords)) {
                $typeTokens[] = $t;
                continue;
            }
            if (in_array($t, $stageWords)) {
                $stageTokens[] = $t;
                continue;
            }
            if (in_array($t, $stopWords)) {
                continue;
            }
            $distinctiveTokens[] = $t;
        }

        $clauses = [];
        $params = [];

        // CASE 1: The user explicitly searched for a school by name using prefixes like "مدرسة / مدارس / مدار / مجمع كذا"
        // E.g. "مدار الكفاح الخاصة" -> Distinctive name is "الكفاح".
        // The distinctive name MUST match the school's actual name (name or name_en) or official city!
        // It CANNOT be satisfied by a random neighborhood or district while the school has an entirely different name!
        if ($hasSchoolNoun && !empty($distinctiveTokens)) {
            foreach ($distinctiveTokens as $dt) {
                $term = "%{$dt}%";
                $clauses[] = sprintf("(COALESCE({$normSql}, '') LIKE ? OR COALESCE({$normSql}, '') LIKE ? OR COALESCE({$normSql}, '') LIKE ? OR COALESCE({$normSql}, '') LIKE ?)",
                    'name', 'name_en', 'city', 'state');
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            if (!empty($typeTokens)) {
                $typeSql = "(CASE WHEN school_type = 'government' THEN 'حكومية حكومي' WHEN school_type IN ('private', 'international', 'language') THEN 'خاصة اهلية دولية' ELSE '' END)";
                foreach ($typeTokens as $tt) {
                    $term = "%{$tt}%";
                    $clauses[] = sprintf("(COALESCE({$normSql}, '') LIKE ? OR COALESCE({$normSql}, '') LIKE ?)", 'name', $typeSql);
                    $params[] = $term;
                    $params[] = $term;
                }
            }

            if (!empty($stageTokens)) {
                $stageSql = "(CASE 
                    WHEN stages LIKE '%kindergarten%' THEN 'روضة رياض اطفال حضانة' 
                    WHEN stages LIKE '%primary%' THEN 'اساس ابتدائي' 
                    WHEN stages LIKE '%intermediate%' THEN 'متوسط' 
                    WHEN stages LIKE '%secondary%' THEN 'ثانوي ثانوية' 
                    ELSE '' END)";
                foreach ($stageTokens as $st) {
                    $term = "%{$st}%";
                    $clauses[] = sprintf("(COALESCE({$normSql}, '') LIKE ? OR COALESCE({$normSql}, '') LIKE ?)", 'name', $stageSql);
                    $params[] = $term;
                    $params[] = $term;
                }
            }
        } else {
            // CASE 2: Freeform keyword or location search
            $fields = [
                'name',
                'name_en',
                'city',
                'state',
                'district',
                'address',
                'description',
                'curriculum',
                'owner_name',
                'owner_title',
                'phone',
                'whatsapp',
                'owner_phone',
                "(CASE WHEN school_type = 'government' THEN 'حكومية حكومي' WHEN school_type IN ('private', 'international', 'language') THEN 'خاصة اهلية دولية' ELSE '' END)",
                "(CASE WHEN stages LIKE '%kindergarten%' THEN 'روضة رياض اطفال حضانة حضانه' ELSE '' END)",
                "(CASE WHEN stages LIKE '%primary%' THEN 'اساس اساسي ابتدائي ابتدائية' ELSE '' END)",
                "(CASE WHEN stages LIKE '%intermediate%' THEN 'متوسط متوسطة' ELSE '' END)",
                "(CASE WHEN stages LIKE '%secondary%' THEN 'ثانوي ثانوية' ELSE '' END)"
            ];

            foreach ($tokens as $token) {
                $fieldClauses = [];
                $term = "%{$token}%";
                foreach ($fields as $f) {
                    $fieldClauses[] = sprintf("COALESCE({$normSql}, '') LIKE ?", $f);
                    $params[] = $term;
                }
                $clauses[] = '(' . implode(' OR ', $fieldClauses) . ')';
            }
        }

        $sql = '(' . implode(' AND ', $clauses) . ')';
        return [$sql, $params];
    }
}

if (!function_exists('getSchoolDirectoryRank')) {
    function getSchoolDirectoryRank(PDO $pdo, int $schoolId, ?int $customRank = null, bool $refresh = false): int {
        static $cachedRanks = null;
        if ($refresh || $cachedRanks === null) {
            try {
                // ترتيب الدليل: الصروح والمدارس العريقة أولاً (حسب سنة التأسيس الأقدم)، ثم المدارس الموثقة والذكية، ثم بقية المدارس المعتمدة
                $historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
                $orderClause = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, (is_verified = 1 OR is_schoplus_client = 1) DESC, is_featured DESC, rating_avg DESC, id ASC";

                $stmt = $pdo->query("SELECT id FROM daleel_schools WHERE status = 'approved' ORDER BY {$orderClause}");
                $cachedRanks = [];
                $seq = 1;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cachedRanks[(int)$row['id']] = $seq;
                    $seq++;
                }
            } catch (Throwable $e) {
                $cachedRanks = [];
            }
        }

        if (isset($cachedRanks[$schoolId])) {
            return $cachedRanks[$schoolId];
        }

        if ($customRank !== null && $customRank > 0) {
            return $customRank;
        }

        // إذا كانت المدرسة قيد المراجعة أو جديدة، ترتيبها المتوقع عند الاعتماد هو (حصر المدارس المعتمدة + 1)
        $approvedCount = is_array($cachedRanks) ? count($cachedRanks) : 0;
        return max(1, $approvedCount + 1);
    }
}

if (!function_exists('getSchoolStateRank')) {
    function getSchoolStateRank(PDO $pdo, int $schoolId, ?string $state = null, ?int $customStateRank = null, ?string $country = null, bool $refresh = false): int {
        static $cachedStateRanks = null;
        static $cachedStateCounts = null;
        if ($refresh || $cachedStateRanks === null) {
            try {
                // الترتيب داخل الولاية: الصروح العريقة أولاً، ثم المدارس الموثقة والذكية، ثم بقية المدارس
                $historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
                $orderClause = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, (is_verified = 1 OR is_schoplus_client = 1) DESC, is_featured DESC, rating_avg DESC, id ASC";

                $stmt = $pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY {$orderClause}");
                $cachedStateRanks = [];
                $cachedStateCounts = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cntry = trim((string)($row['country'] ?? '')) ?: 'السودان';
                    $st = trim((string)($row['state'] ?? ''));
                    $groupKey = $cntry . ':::' . $st;
                    $cachedStateCounts[$groupKey] = ($cachedStateCounts[$groupKey] ?? 0) + 1;
                    $cachedStateRanks[(int)$row['id']] = $cachedStateCounts[$groupKey];
                }
            } catch (Throwable $e) {
                $cachedStateRanks = [];
                $cachedStateCounts = [];
            }
        }

        if (isset($cachedStateRanks[$schoolId])) {
            return $cachedStateRanks[$schoolId];
        }

        $stKey = trim((string)($state ?? ''));
        $cntryKey = trim((string)($country ?? '')) ?: 'السودان';
        if (($stKey === '' || $cntryKey === '') && $schoolId > 0) {
            try {
                $stStmt = $pdo->prepare("SELECT country, state FROM daleel_schools WHERE id = ?");
                $stStmt->execute([$schoolId]);
                $sRow = $stStmt->fetch(PDO::FETCH_ASSOC);
                if ($sRow) {
                    $stKey = trim((string)($sRow['state'] ?? ''));
                    $cntryKey = trim((string)($sRow['country'] ?? '')) ?: 'السودان';
                }
            } catch (Throwable $e) {}
        }

        $fullKey = $cntryKey . ':::' . $stKey;
        // إذا كانت المدرسة قيد المراجعة أو جديدة، ترتيبها المتوقع في الولاية عند الاعتماد هو (حصر المدارس المعتمدة بالولاية + 1)
        $approvedInState = ($stKey !== '' && isset($cachedStateCounts[$fullKey])) ? (int)$cachedStateCounts[$fullKey] : 0;
        return max(1, $approvedInState + 1);
    }
}

if (!function_exists('recalculateDaleelRanks')) {
    /**
     * Recalculates and re-sequences directory_rank and state_rank for all approved schools
     * Canonical order:
     * 1. Historic schools (oldest established year first)
     * 2. Verified & Smart SchoPlus institutions (is_verified = 1 OR is_schoplus_client = 1)
     * 3. General unverified schools (id ASC)
     */
    function recalculateDaleelRanks(PDO $pdo): int {
        $historicSql = "(is_historic = 1 OR (established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950) OR historic_votes >= 500 OR name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%')";
        $orderClause = "({$historicSql}) DESC, (CASE WHEN CAST(established_year AS UNSIGNED) > 1800 AND CAST(established_year AS UNSIGNED) <= 1950 THEN CAST(established_year AS UNSIGNED) ELSE 9999 END) ASC, (is_verified = 1 OR is_schoplus_client = 1) DESC, is_featured DESC, rating_avg DESC, id ASC";

        $approvedRows = $pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY {$orderClause}")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($approvedRows)) return 0;

        $dirSeq = 0;
        $stateSeqs = [];
        $updRanksStmt = $pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?");

        $alreadyInTx = false;
        try {
            $alreadyInTx = $pdo->inTransaction();
        } catch (Throwable $e) {}

        if (!$alreadyInTx) {
            $pdo->beginTransaction();
        }

        foreach ($approvedRows as $aRow) {
            $dirSeq++;
            $cntryKey = trim((string)($aRow['country'] ?? '')) ?: 'السودان';
            $stKey = trim((string)($aRow['state'] ?? ''));
            $groupKey = $cntryKey . ':::' . $stKey;
            $stateSeqs[$groupKey] = ($stateSeqs[$groupKey] ?? 0) + 1;
            $stSeq = $stateSeqs[$groupKey];
            $updRanksStmt->execute([$dirSeq, $stSeq, (int)$aRow['id']]);
        }

        if (!$alreadyInTx) {
            $pdo->commit();
        }

        // Invalidate in-memory cached ranks
        getSchoolDirectoryRank($pdo, 0, null, true);
        getSchoolStateRank($pdo, 0, null, null, null, true);

        return $dirSeq;
    }
}

if (!function_exists('getSchoolVerificationSourceUrl')) {
    function getSchoolVerificationSourceUrl(array $school): array {
        $rawName = trim((string)($school['name'] ?? ''));
        $country = trim((string)($school['country'] ?? '')) ?: 'السودان';
        $city = trim((string)($school['city'] ?? ''));
        $district = trim((string)($school['district'] ?? ''));
        $state = trim((string)($school['state'] ?? ''));

        // 1. Official Stored Website
        if (!empty($school['website'])) {
            return [
                'url' => $school['website'],
                'label' => 'الموقع الرسمي',
                'icon' => 'fas fa-globe text-primary',
                'type' => 'website'
            ];
        }

        // 2. Official Stored Facebook
        if (!empty($school['facebook'])) {
            return [
                'url' => $school['facebook'],
                'label' => 'صفحة فيسبوك',
                'icon' => 'fab fa-facebook text-primary',
                'type' => 'facebook'
            ];
        }

        // 3. Custom direct source_url (only if not an unanchored google search)
        if (!empty($school['source_url'])) {
            $srcUrl = $school['source_url'];
            $type = $school['source_type'] ?? 'custom';

            $isGoogleSearch = str_contains($srcUrl, 'google.com/search');
            $hasCountry = str_contains($srcUrl, urlencode($country)) || str_contains($srcUrl, 'السودان');

            if (!$isGoogleSearch || $hasCountry) {
                $icon = 'fas fa-external-link-alt';
                $lbl = 'المصدر المعتمد';
                if ($type === 'facebook' || str_contains($srcUrl, 'facebook.com')) {
                    $icon = 'fab fa-facebook text-primary';
                    $lbl = 'صفحة فيسبوك';
                } elseif ($isGoogleSearch) {
                    $icon = 'fab fa-google text-danger';
                    $lbl = 'توثيق Google';
                }
                return [
                    'url' => $srcUrl,
                    'label' => $lbl,
                    'icon' => $icon,
                    'type' => $type
                ];
            }
        }

        // 4. Fallback Google Search Knowledge query with STRICT GEOGRAPHIC ANCHORING
        // Avoid repeating prefixes like "مدرسة مدارس" or "مدرسة روضة"
        $namePrefix = '';
        if (!preg_match('/^(مدرسة|مدارس|روضة|حضانة|كلية|مجمع|أكاديمية|معهد)/iu', $rawName)) {
            $namePrefix = 'مدرسة ';
        }

        // Clean quotes and excessive punctuation
        $cleanName = trim(str_replace(['"', '“', '”', '«', '»'], '', $rawName));

        // If the school name has a branch suffix (e.g. "مدارس كامبريدج — الخرطوم"), extract the core school name
        $parts = preg_split('/[\—\–\-]\s*/u', $cleanName, 2);
        $coreName = trim($parts[0] ?? $cleanName);
        $branchName = trim($parts[1] ?? '');

        // Build search query tokens
        $tokens = [];
        $tokens[] = '"' . $namePrefix . $coreName . '"';

        if (!empty($branchName)) {
            $tokens[] = $branchName;
        }

        if (!empty($district) && !str_contains($cleanName, $district)) {
            $tokens[] = $district;
        }

        if (!empty($city) && !str_contains($cleanName, $city)) {
            $tokens[] = $city;
        }

        if (!empty($state) && $state !== $city && !str_contains($state, $city) && !str_contains($cleanName, $state)) {
            $tokens[] = $state;
        }

        // MANDATORY: Country anchor (السودان for Sudan, or host country for Diaspora)
        $tokens[] = $country;

        // If diaspora institution, ensure Sudanese context
        if ($country !== 'السودان' && !str_contains($cleanName, 'السودانية') && !str_contains($cleanName, 'السودان')) {
            $tokens[] = 'المنهج السوداني';
        }

        $searchQuery = implode(' ', array_filter($tokens));
        $searchUrl = 'https://www.google.com/search?q=' . urlencode($searchQuery);

        return [
            'url' => $searchUrl,
            'label' => 'توثيق Google',
            'icon' => 'fab fa-google text-danger',
            'type' => 'google_search'
        ];
    }
}

if (!function_exists('arabicDate')) {
    function arabicDate(?string $datetime): string {
        if (!$datetime) return '';
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $ts = strtotime($datetime);
        $d = date('j', $ts);
        $m = (int) date('n', $ts);
        $y = date('Y', $ts);
        return $d . ' ' . ($months[$m] ?? '') . ' ' . $y;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $tok = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . $tok . '"><input type="hidden" name="csrf_token" value="' . $tok . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool {
        $token = trim((string)($_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? $_POST['_token'] ?? ''));
        $stored = trim((string)($_SESSION['_csrf_token'] ?? $_SESSION['csrf_token'] ?? ''));
        return $token !== '' && $stored !== '' && hash_equals($stored, $token);
    }
}

if (!function_exists('sanitizeHtml')) {
    function sanitizeHtml(?string $html): string {
        $html = trim((string)($html ?? ''));
        if ($html === '') {
            return '';
        }
        // Allowed tags for rich text formatted news / announcements
        $allowedTags = '<p><br><b><strong><i><em><u><s><strike><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><font><span><div><hr><table><thead><tbody><tr><th><td><img>';
        $cleaned = strip_tags($html, $allowedTags);
        // Strip out dangerous attributes like onload, onclick, javascript:
        $cleaned = preg_replace('/(\s+on[a-z]+=["\'][^"\']*["\'])/i', '', $cleaned);
        $cleaned = preg_replace('/href=["\']javascript:[^"\']*["\']/i', 'href="#"', $cleaned);
        $cleaned = preg_replace('/src=["\']javascript:[^"\']*["\']/i', '', $cleaned);
        return $cleaned;
    }
}

if (!function_exists('isSchoolHistoric')) {
    function isSchoolHistoric(array $school): bool {
        if (class_exists('SudanHistoricSchools')) {
            return SudanHistoricSchools::isHistoric($school);
        }
        // 1. Explicitly flagged
        if (!empty($school['is_historic'])) {
            return true;
        }
        // 2. Founded in or before 1960 (the golden era of Sudanese historic education)
        $year = (int)($school['established_year'] ?? 0);
        if ($year > 1800 && $year <= 1960) {
            if (($school['school_type'] ?? '') === 'private' && mb_stripos($school['name'] ?? '', 'كمبوني') === false && mb_stripos($school['name'] ?? '', 'راهبات') === false) {
                return false;
            }
            return true;
        }
        // 3. More than 500 community votes
        $votes = (int)($school['historic_votes'] ?? 0);
        if ($votes >= 500) {
            return true;
        }
        // 4. Iconic national historic schools in Sudan
        $name = $school['name'] ?? '';
        $nameEn = $school['name_en'] ?? '';
        $iconicNames = [
            'وادي سيدنا', 'خور طقت', 'حنتوب الثانوية', 'حنتوب العريقة', 'رفاعة', 'بخت الرضا',
            'الأهلية أم درمان', 'أم درمان الأهلية', 'المؤتمر الثانوية', 'المؤتمر',
            'الشيخ مصطفى الأمين', 'بربر الثانوية', 'الخرطوم الإنجيلية',
            'كلية كمبوني', 'كمبوني', 'السنية', 'مدرسة الراهبات', 'الراهبات',
            'بورتسودان الثانوية بنين القديمة', 'الفاشر الثانوية القديمة',
            'الدلنج الثانوية', 'عطبرة الثانوية القديمة', 'الدويم الثانوية'
        ];
        foreach ($iconicNames as $ic) {
            if (mb_stripos($name, $ic) !== false) {
                return true;
            }
        }
        if (mb_stripos($nameEn, 'Sister') !== false || mb_stripos($name, 'Sister') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('getSchoolHistoricBadge')) {
    function getSchoolHistoricBadge(array $school): ?array {
        if (!isSchoolHistoric($school)) {
            return null;
        }
        $year = (int)($school['established_year'] ?? 0);
        $votes = (int)($school['historic_votes'] ?? 0);

        $label = 'صرح وطني عريق';
        $sub = '';
        if ($year > 1800) {
            $sub = "تأسست {$year}م";
        } elseif ($votes >= 500) {
            $sub = "تصويت مجتمعي (+{$votes})";
        } elseif (!empty($school['historic_badge_reason'])) {
            $sub = $school['historic_badge_reason'];
        } else {
            $sub = "إرث تاريخي وطني";
        }

        $legacyYears = ($year > 1800) ? ((int)date('Y') - $year) : null;

        return [
            'label'        => $label,
            'sub'          => $sub,
            'year'         => $year ?: null,
            'legacy_years' => $legacyYears,
            'votes'        => $votes,
        ];
    }
}

if (!function_exists('getDaleelSetting')) {
    function getDaleelSetting(PDO $pdo, string $key, $default = null) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM daleel_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('setDaleelSetting')) {
    function setDaleelSetting(PDO $pdo, string $key, $value): bool {
        try {
            $stmt = $pdo->prepare("INSERT INTO daleel_settings (setting_key, setting_value, created_at, updated_at) 
                                   VALUES (?, ?, NOW(), NOW()) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
            return $stmt->execute([$key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Singleton Database Connection
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isServerEnv = is_dir('/home/wdenfygx') ||
        (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'schoplus.sd') !== false) ||
        (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'schoplus.sd') !== false);

    if ($isServerEnv) {
        // Production cPanel Credentials
        $host = 'localhost';
        $dbname = 'wdenfygx_schoplus_schools';
        $user = 'wdenfygx_schools_user';
        $pass = '-tUrA9@%=yXYQS3i';
        $charset = 'utf8mb4';

        // Secondary fallback to activation DB if needed
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $dbname = 'wdenfygx_schoplus_activation';
            $user = 'wdenfygx_schoplus_user';
            $pass = ')1#m%w$@^JZx6meX';
            $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
    } else {
        // Localhost XAMPP
        $host = '127.0.0.1';
        $dbname = 'schoplus';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset={$charset}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    ensureTablesExist($pdo);
    return $pdo;
}

/**
 * Ensure Directory Tables Exist in the Connected DB
 */
function ensureTablesExist(PDO $pdo): void {
    // 1. daleel_schools
    $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_schools (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        name_en VARCHAR(255) NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        school_type VARCHAR(50) NOT NULL DEFAULT 'private',
        gender_type VARCHAR(50) NOT NULL DEFAULT 'mixed',
        curriculum VARCHAR(100) NOT NULL DEFAULT 'sudanese',
        established_year VARCHAR(50) NULL,
        stages TEXT NULL,
        state VARCHAR(100) NOT NULL,
        city VARCHAR(100) NOT NULL,
        district VARCHAR(100) NULL,
        address TEXT NULL,
        google_map_url TEXT NULL,
        latitude DECIMAL(10, 7) NULL,
        longitude DECIMAL(10, 7) NULL,
        phone VARCHAR(50) NOT NULL,
        whatsapp VARCHAR(50) NULL,
        email VARCHAR(150) NULL,
        website VARCHAR(255) NULL,
        facebook VARCHAR(255) NULL,
        twitter VARCHAR(255) NULL,
        youtube VARCHAR(255) NULL,
        telegram VARCHAR(255) NULL,
        instagram VARCHAR(255) NULL,
        tiktok VARCHAR(255) NULL,
        logo_path VARCHAR(255) NULL,
        cover_path VARCHAR(255) NULL,
        gallery TEXT NULL,
        description TEXT NULL,
        vision TEXT NULL,
        mission TEXT NULL,
        approx_fees VARCHAR(100) NULL,
        fees_type VARCHAR(50) NOT NULL DEFAULT 'average',
        stage_fees LONGTEXT NULL,
        fee_discounts LONGTEXT NULL,
        working_hours VARCHAR(100) NULL,
        facilities TEXT NULL,
        branches_data LONGTEXT NULL,
        activities_data LONGTEXT NULL,
        teachers_data LONGTEXT NULL,
        optional_tabs TEXT NULL,
        is_schoplus_client TINYINT(1) NOT NULL DEFAULT 0,
        schoplus_school_id BIGINT UNSIGNED NULL,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        is_verified TINYINT(1) NOT NULL DEFAULT 0,
        rating_avg DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
        reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
        views_count INT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        rejection_reason TEXT NULL,
        owner_name VARCHAR(150) NOT NULL,
        owner_title VARCHAR(100) NULL,
        owner_phone VARCHAR(50) NOT NULL,
        owner_email VARCHAR(150) NULL,
        password VARCHAR(255) NOT NULL,
        remember_token VARCHAR(100) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        INDEX idx_state_city (state, city),
        INDEX idx_status (status),
        INDEX idx_schoplus (is_schoplus_client)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. daleel_school_news
    $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_school_news (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        school_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        published_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        views_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. daleel_school_reviews
    $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_school_reviews (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        school_id BIGINT UNSIGNED NOT NULL,
        reviewer_name VARCHAR(150) NOT NULL,
        reviewer_phone VARCHAR(50) NULL,
        reviewer_role VARCHAR(50) NOT NULL DEFAULT 'parent',
        rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
        comment TEXT NOT NULL,
        school_reply TEXT NULL,
        school_replied_at DATETIME NULL,
        is_approved TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. daleel_school_inquiries
    $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_school_inquiries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        school_id BIGINT UNSIGNED NOT NULL,
        parent_name VARCHAR(150) NOT NULL,
        parent_phone VARCHAR(50) NOT NULL,
        parent_email VARCHAR(150) NULL,
        student_name VARCHAR(150) NULL,
        student_gender VARCHAR(50) NULL,
        student_stage VARCHAR(100) NULL,
        student_grade VARCHAR(100) NULL,
        previous_school VARCHAR(200) NULL,
        branch_name VARCHAR(150) NULL,
        needs_transportation TINYINT(1) NOT NULL DEFAULT 0,
        message TEXT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'new',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. daleel_school_claims (طلبات توثيق ملكية المدارس المضافة تلقائياً)
    $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_school_claims (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        school_id BIGINT UNSIGNED NOT NULL,
        claimant_name VARCHAR(150) NOT NULL,
        claimant_title VARCHAR(100) NOT NULL,
        claimant_phone VARCHAR(50) NOT NULL,
        claimant_email VARCHAR(150) NULL,
        verification_doc_path VARCHAR(255) NULL,
        notes TEXT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        reviewed_by VARCHAR(100) NULL,
        reviewed_at DATETIME NULL,
        rejection_reason TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_claim (school_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Zero-Skip Schema Migration: Ensure all new columns exist in daleel_schools
    try {
        $existingCols = [];
        $colsQuery = $pdo->query("SHOW COLUMNS FROM daleel_schools");
        if ($colsQuery) {
            foreach ($colsQuery->fetchAll() as $colRow) {
                $existingCols[$colRow['Field']] = true;
            }
        }
        $alterStatements = [
            'gallery'         => "ALTER TABLE daleel_schools ADD COLUMN gallery TEXT NULL AFTER cover_path",
            'twitter'         => "ALTER TABLE daleel_schools ADD COLUMN twitter VARCHAR(255) NULL AFTER facebook",
            'youtube'         => "ALTER TABLE daleel_schools ADD COLUMN youtube VARCHAR(255) NULL AFTER twitter",
            'telegram'        => "ALTER TABLE daleel_schools ADD COLUMN telegram VARCHAR(255) NULL AFTER youtube",
            'instagram'       => "ALTER TABLE daleel_schools ADD COLUMN instagram VARCHAR(255) NULL AFTER telegram",
            'tiktok'          => "ALTER TABLE daleel_schools ADD COLUMN tiktok VARCHAR(255) NULL AFTER instagram",
            'branches_data'   => "ALTER TABLE daleel_schools ADD COLUMN branches_data LONGTEXT NULL AFTER facilities",
            'activities_data' => "ALTER TABLE daleel_schools ADD COLUMN activities_data LONGTEXT NULL AFTER branches_data",
            'teachers_data'   => "ALTER TABLE daleel_schools ADD COLUMN teachers_data LONGTEXT NULL AFTER activities_data",
            'country'         => "ALTER TABLE daleel_schools ADD COLUMN country VARCHAR(100) NOT NULL DEFAULT 'السودان' AFTER curriculum",
            'directory_rank'  => "ALTER TABLE daleel_schools ADD COLUMN directory_rank INT UNSIGNED NULL DEFAULT NULL AFTER is_featured",
            'state_rank'      => "ALTER TABLE daleel_schools ADD COLUMN state_rank INT UNSIGNED NULL DEFAULT NULL AFTER directory_rank",
            'established_year'=> "ALTER TABLE daleel_schools ADD COLUMN established_year VARCHAR(50) NULL AFTER curriculum",
            'fees_type'       => "ALTER TABLE daleel_schools ADD COLUMN fees_type VARCHAR(50) NOT NULL DEFAULT 'average' AFTER approx_fees",
            'stage_fees'      => "ALTER TABLE daleel_schools ADD COLUMN stage_fees LONGTEXT NULL AFTER fees_type",
            'fee_discounts'   => "ALTER TABLE daleel_schools ADD COLUMN fee_discounts LONGTEXT NULL AFTER stage_fees",
            'admission_status'       => "ALTER TABLE daleel_schools ADD COLUMN admission_status VARCHAR(50) NOT NULL DEFAULT 'open' AFTER fee_discounts",
            'admission_requirements' => "ALTER TABLE daleel_schools ADD COLUMN admission_requirements LONGTEXT NULL AFTER admission_status",
            'admission_steps'        => "ALTER TABLE daleel_schools ADD COLUMN admission_steps LONGTEXT NULL AFTER admission_requirements",
            'admission_notes'        => "ALTER TABLE daleel_schools ADD COLUMN admission_notes TEXT NULL AFTER admission_steps",
            'is_historic'            => "ALTER TABLE daleel_schools ADD COLUMN is_historic TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified",
            'historic_votes'         => "ALTER TABLE daleel_schools ADD COLUMN historic_votes INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_historic",
            'historic_badge_reason'  => "ALTER TABLE daleel_schools ADD COLUMN historic_badge_reason VARCHAR(255) NULL AFTER historic_votes",
            'whatsapp_sent'          => "ALTER TABLE daleel_schools ADD COLUMN whatsapp_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp",
            'whatsapp_sent_at'       => "ALTER TABLE daleel_schools ADD COLUMN whatsapp_sent_at TIMESTAMP NULL DEFAULT NULL AFTER whatsapp_sent",
            'source_url'             => "ALTER TABLE daleel_schools ADD COLUMN source_url VARCHAR(500) NULL AFTER website",
            'source_type'            => "ALTER TABLE daleel_schools ADD COLUMN source_type VARCHAR(50) NULL DEFAULT 'web' AFTER source_url",
        ];
        foreach ($alterStatements as $colName => $sql) {
            if (!isset($existingCols[$colName])) {
                $pdo->exec($sql);
            }
        }

        // Create historic votes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_school_historic_votes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT UNSIGNED NOT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            session_id VARCHAR(100) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_school_vote (school_id),
            INDEX idx_school_ip (school_id, ip_address),
            INDEX idx_school_session (school_id, session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Create daleel_settings table for dynamic system & ads management
        $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value LONGTEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Pre-flag heritage schools founded <= 1960 or iconic national schools
        try {
            $pdo->exec("UPDATE daleel_schools SET is_historic = 1, historic_badge_reason = 'صرح وطني عريق' WHERE established_year IS NOT NULL AND CAST(established_year AS UNSIGNED) <= 1960 AND CAST(established_year AS UNSIGNED) >= 1800");
            $pdo->exec("UPDATE daleel_schools SET is_historic = 1, historic_badge_reason = 'صرح وطني تاريخي' WHERE name LIKE '%خور طقت%' OR name LIKE '%وادي سيدنا%' OR name LIKE '%حنتوب الثانوية%' OR name LIKE '%حنتوب العريقة%' OR name LIKE '%بخت الرضا%' OR name LIKE '%رفاعة%بابكر بدري%' OR name LIKE '%بربر الثانوية%' OR name LIKE '%الأهلية%أم درمان%' OR name LIKE '%المؤتمر%' OR name LIKE '%الشيخ مصطفى الأمين%' OR name LIKE '%كمبوني%' OR name LIKE '%الراهبات%' OR name_en LIKE '%Sister%'");
        } catch (Throwable $e) {}

        // Strict Directory Deduplication & National Heritage Refinements (v3.7.64)
        try {
            // 1. Delete confirmed duplicates safely by pattern and state
            $pdo->exec("DELETE FROM daleel_schools WHERE (name LIKE '%التعليم البريطانية%' OR name_en LIKE '%British Educational Schools%') AND state IN ('نهر النيل', 'سنار', 'النيل الأبيض', 'كسلا')");
            try {
                $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE '%المجلس الأفريقي%' AND state = 'الجزيرة' AND id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM daleel_schools WHERE name LIKE '%المجلس الأفريقي%' AND state = 'الجزيرة') t)");
                $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE '%الرميساء%' AND state = 'النيل الأبيض' AND id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM daleel_schools WHERE name LIKE '%الرميساء%' AND state = 'النيل الأبيض') t)");
                $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE '%كوستي الأهلية%' AND id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM daleel_schools WHERE name LIKE '%كوستي الأهلية%') t)");
                $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE '%كمبوني%' AND state = 'الخرطوم' AND id NOT IN (SELECT min_id FROM (SELECT MIN(id) as min_id FROM daleel_schools WHERE name LIKE '%كمبوني%' AND state = 'الخرطوم') t)");
                $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE '%كمبوني%' AND state = 'البحر الأحمر' AND id NOT IN (SELECT max_id FROM (SELECT MAX(id) as max_id FROM daleel_schools WHERE name LIKE '%كمبوني%' AND state = 'البحر الأحمر') t)");
            } catch (Throwable $e) {}

            // 2. Restore Mawaheb English Schools if mistakenly modified by ID collision
            $pdo->exec("UPDATE daleel_schools 
                SET name = 'مدارس المواهب الإنجليزية',
                    name_en = 'Al-Mawaheb English Schools',
                    school_type = 'international',
                    established_year = NULL,
                    is_historic = 0,
                    historic_badge_reason = NULL,
                    description = 'من المدارس الإنجليزية الرائدة في بحري لاكتشاف ورعاية الموهوبين دراسياً وعلمياً.'
                WHERE slug = 'مدارس-المواهب-الإنجليزية'");

            // 3. Iconify & brand Sisters' School (مدرسة الراهبات) exclusively
            $pdo->exec("UPDATE daleel_schools 
                SET name = 'مدرسة الراهبات (Sisters\' School) — الخرطوم',
                    name_en = 'Sisters\' School Khartoum',
                    school_type = 'community',
                    established_year = '1902',
                    is_historic = 1,
                    historic_badge_reason = 'صرح تاريخي عريق',
                    description = 'مدرسة الراهبات (Sisters\' School / سيستر سكول) — الخرطوم، تأسست عام 1902م وتُعد من أقدم وأعرق الصروح التعليمية للبنات في السودان. تميزت بالانضباط الأكاديمي واللغات وتخريج أجيال متميزة في تاريخ السودان الحديث.'
                WHERE slug = 'مدرسة-الراهبات-الخرطوم' OR (slug != 'مدارس-المواهب-الإنجليزية' AND (name LIKE '%راهبات%' OR name_en LIKE '%Sister%'))");

            // 4. Restore / Confirm Port Sudan Old Secondary School
            $pdo->exec("UPDATE daleel_schools 
                SET name = 'مدرسة بورتسودان الثانوية بنين القديمة',
                    name_en = 'Port Sudan Old Secondary School for Boys',
                    school_type = 'government',
                    established_year = '1952',
                    is_historic = 1,
                    historic_badge_reason = 'صرح تعليمي عريق'
                WHERE slug = 'مدرسة-بورتسودان-الثانوية-بنين-القديمة'");

            // 5. Comboni Port Sudan
            $pdo->exec("UPDATE daleel_schools 
                SET name = 'كلية ومدرسة كمبوني (St. Joseph Comboni) — بورتسودان',
                    name_en = 'Comboni College & School Port Sudan',
                    phone = '+249311822456',
                    address = 'شارع الميناء أمام الكنيسة الكاثوليكية، حي الشاطئ، بورتسودان',
                    established_year = '1935',
                    is_historic = 1,
                    historic_badge_reason = 'صرح تعليمي تاريخي'
                WHERE slug = 'كلية-ومدرسة-كمبوني-بورتسودان' OR (name LIKE '%كمبوني%' AND state = 'البحر الأحمر')");

            // 6. Comboni Atbara & Kadugli founding years
            $pdo->exec("UPDATE daleel_schools SET established_year = '1944', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدارس-كمبوني-عطبرة' OR name LIKE '%كمبوني عطبرة%'");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1954', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-كمبوني-كادقلي-الابتدائية-المختلطة' OR name LIKE '%كمبوني كادقلي%'");

            // 7. Fix misclassified school types
            $pdo->exec("UPDATE daleel_schools SET school_type = 'private', is_historic = 0, historic_badge_reason = NULL WHERE slug = 'مدارس-ورياض-حنتوب-الخاصة' OR name LIKE '%مدارس ورياض حنتوب الخاصة%'");
            $pdo->exec("UPDATE daleel_schools SET school_type = 'private' WHERE slug = 'مؤسسة-الأميرية-التعليمية-القرآنية-الخاصة' OR name LIKE '%مؤسسة الأميرية التعليمية القرآنية الخاصة%'");

            // 8. Enrich iconic government schools with accurate founding years & historic badges
            $pdo->exec("UPDATE daleel_schools SET established_year = '1952', is_historic = 1, historic_badge_reason = 'صرح أهلي عريق' WHERE slug = 'مدرسة-كوستي-الأهلية-الثانوية-العريقة' OR name LIKE '%كوستي الأهلية%'");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1953', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-كسلا-الثانوية-بنين-القديمة' OR (name LIKE '%كسلا الثانوية%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1957', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-دنقلا-الثانوية-بنين-العريقة' OR (name LIKE '%دنقلا الثانوية%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1954', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-شندي-الثانوية-بنين' OR (name LIKE '%شندي الثانوية%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1950', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-الخرطوم-الثانوية-القديمة-بنين' OR (name LIKE '%الخرطوم الثانوية القديمة%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1956', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-بحري-الثانوية-القديمة-بنين' OR (name LIKE '%بحري الثانوية القديمة%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1947', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-الأميرية-بورتسودان' OR (name LIKE '%الأميرية بورتسودان%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1951', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-ود-مدني-الثانوية-بنات-أم-المدائن' OR (name LIKE '%ود مدني الثانوية بنات%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1952', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-أم-درمان-النموذجية-بنات' OR (name LIKE '%أم درمان النموذجية بنات%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1958', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-الدلنج-الثانوية-العريقة' OR (name LIKE '%الدلنج الثانوية%' AND school_type = 'government')");
            $pdo->exec("UPDATE daleel_schools SET established_year = '1955', is_historic = 1, historic_badge_reason = 'صرح تعليمي عريق' WHERE slug = 'مدرسة-الفاشر-الثانوية-القديمة-بنين' OR (name LIKE '%الفاشر الثانوية القديمة%' AND school_type = 'government')");

            // 9. Update Al-Rumaisa School (مجمع مدارس الرميساء الخاصة — كوستي)
            // Correction: It is NOT for girls only — it is mixed (بنين وبنات) across preschool, primary, intermediate, and secondary.
            $pdo->exec("UPDATE daleel_schools 
                SET name = 'مجمع مدارس الرميساء الخاصة — كوستي',
                    name_en = 'Al-Rumaisa Private Schools - Kosti',
                    gender_type = 'mixed',
                    stages = '[\"kindergarten\",\"primary\",\"intermediate\",\"secondary\"]',
                    phone = NULL,
                    whatsapp = NULL,
                    owner_phone = NULL,
                    description = 'مجمع تعليمي وتربوي رائد في مدينة كوستي بولاية النيل الأبيض، يضم فروعاً متكاملة للبنين والبنات لمراحل رياض الأطفال، الابتدائي، المتوسط، والثانوي.'
                WHERE name LIKE '%الرميساء%' OR slug LIKE '%الرميساء%' OR slug LIKE '%rumaisa%'");

            // 10. Scrub all legacy sequential dummy phone placeholders (e.g. 012345678X, 0100000000)
            $pdo->exec("UPDATE daleel_schools 
                SET phone = NULL, whatsapp = NULL, owner_phone = NULL 
                WHERE phone LIKE '0123456%' 
                   OR phone LIKE '+249912345678%' 
                   OR phone LIKE '010000%' 
                   OR phone LIKE '%00000%' 
                   OR whatsapp LIKE '0123456%' 
                   OR whatsapp LIKE '+249912345678%'
                   OR whatsapp LIKE '010000%'
                   OR whatsapp LIKE '%00000%'");

            // 11. Repair Al-Ola Modern Private Schools (مدارس العلا الحديثة الخاصة) — It is a modern private school (NOT founded in 1956)
            $pdo->exec("UPDATE daleel_schools 
                SET established_year = NULL, 
                    is_historic = 0, 
                    historic_badge_reason = NULL 
                WHERE slug = 'مدارس-العلا-الحديثة-الخاصة' 
                   OR name LIKE '%العلا الحديثة%'");

            // 12. Purge rogue historic flags on private schools caused by legacy ID collisions
            $pdo->exec("UPDATE daleel_schools 
                SET is_historic = 0, established_year = NULL, historic_badge_reason = NULL 
                WHERE school_type = 'private'
                  AND established_year IS NOT NULL 
                  AND CAST(established_year AS UNSIGNED) <= 1970 
                  AND name NOT LIKE '%كمبوني%'
                  AND name NOT LIKE '%راهبات%'
                  AND name_en NOT LIKE '%Sister%'
                  AND name NOT LIKE '%إنجيلية%'
                  AND name NOT LIKE '%أهلية%'
                  AND name NOT LIKE '%بخت الرضا%'
                  AND name NOT LIKE '%خور طقت%'
                  AND name NOT LIKE '%وادي سيدنا%'
                  AND name NOT LIKE '%حنتوب%'
                  AND name NOT LIKE '%الشيخ مصطفى الأمين%'");

            // 13. Enrich iconic national schools with comprehensive detailed descriptions
            $historicUpdates = [
                'وادي سيدنا' => 'صرح وطني تعليمي قومي رائد تأسس عام 1937م شمال أم درمان على ضفاف النيل. إحدى قلاع المثلث القومي للتعليم الثانوي بالسودان (مع حنتوب وخور طقت). تميزت بطراز معماري مهيب وداخليات بأسماء قادة التاريخ، وخرّجت رؤساء وزارات وعلماء ذرة واستشاريي طب وقادة استقلال وبناء السودان.',
                'حنتوب' => 'درة المدارس القومية الكبرى في السودان تأسست عام 1946م على الضفة الشرقية للنيل الأزرق بود مدني. موئل الفكر والأدب والسياسة، وضمت حرماً جامعياً مصغراً وملاعب أولمبية، وخرّجت الروائي العالمي الطيب صالح والبروفيسور عبد الله الطيب ونخبة صناع النهضة الوطنية.',
                'خور طقت' => 'درة التعليم القومي في الغرب السوداني وشعلة كردفان التاريخية تأسست عام 1950م قرب الأبيض. صُممت وسط غابات الهشاب لجمع نوابغ الطلاب من كافة أقاليم السودان، وعُرفت بالانضباط التربوي الصارم وصهر النسيج الوطني وتخريج كبار قادة الدولة والطب والهندسة.',
                'بخت الرضا' => 'كعبة التربية والتعليم وقبلة المناهج السودانية تأسست عام 1934م بمدينة الدويم على النيل الأبيض بقيادة عبد الرحمن علي طه ومستر غريفيثس. الصرح الذي وُلدت فيه كتب المناهج السودانية وتدرب فيه آلاف المعلمين وصيغت فيه الهوية التربوية للسودان الحديث.',
                'رفاعة' => 'أول مدرسة لتعليم البنات في تاريخ السودان الحديث، أسسها رائد تعليم المرأة الشيخ بابكر بدري عام 1903م في رفاعة بشرق الجزيرة. مثلت الشرارة الكبرى لتنوير وتحرير المرأة السودانية وتخريج أولى المعلمات والطبيبات وتأسيس كلية الأحفاد.',
                'الراهبات' => 'مدرسة الراهبات (Sisters\' School / سيستر سكول) — الخرطوم، تأسست عام 1902م وتُعد من أقدم وأعرق الصروح التعليمية للبنات في السودان. تميزت بالانضباط الأكاديمي الصارم واللغات الحية وتخريج رائدات المجتمع في الطب والعلوم والدبلوماسية.',
                'الأهلية أم درمان' => 'أول مدرسة ثانوية أهلية وطنية أسسها الشعب السوداني بجهده الذاتي وتبرعاته الحرة عام 1927م في حي ود أرو بأم درمان لمناهضة الاستعمار ونشر التعليم الحر، وتخرجت منها قيادات مؤتمر الخريجين والحركة الوطنية والقضاء والطب.',
                'المؤتمر' => 'مدرسة المؤتمر الثانوية بنين بأم درمان تأسست عام 1951م تخليداً لمؤتمر الخريجين العام رمز حركة الاستقلال. صرح أكاديمي رصين عُرف باحتلال المراكز الأولى في الشهادة الثانوية وتخريج نوابغ الطب والعلوم والهندسة.',
                'بربر' => 'إحدى أقدم المدارس النظامية في تاريخ السودان تأسست عام 1906م بمدينة بربر العريقة بنهر النيل، وتخرجت منها أجيال القضاة والعلماء والإداريين والأدباء الذين شيدوا أجهزة الدولة السودانية بعد الاستقلال.',
                'بحري الثانوية القديمة' => 'قلعة التعليم الثانوي الكبرى في مدينة بحري تأسست في عام الاستقلال 1956م بحي المزاد العريق. تخرج منها نخبة من كبار العلماء والسياسيين والأطباء والرياضيين الذين مثلوا السودان محلياً ودولياً.',
                'الخرطوم الثانوية القديمة' => 'قلعة التعليم الأكاديمي النموذجي في قلب العاصمة الخرطوم تأسست عام 1950م، واحتضنت أوائل الطلاب ومثلت مصنعاً حقيقياً للأطباء والمهندسين وأساتذة الجامعات وقادة الخدمة المدنية.',
                'ود مدني السنية' => 'مدرسة ود مدني الثانوية بنين الشهيرة بـ (السنية) تأسست عام 1947م في قلب عاصمة الجزيرة. عُرفت بأناقتها وتفوقها الأكاديمي الصارم وتخريج قادة الزراعة والاقتصاد والطب والخدمة المدنية.',
                'كسلا الثانوية' => 'الصرح الثانوي التاريخي الأول بشرق السودان تأسس عام 1953م تحت سفوح جبال التاكا وتوتيل بحاضرة الشرق كسلا، وتخرجت منه أجيال قادت التنمية والإدارة والقضاء والطب في شرق السودان.',
                'بورتسودان القديمة' => 'أعرق المدارس الثانوية الحكومية في ثغر السودان الباسم تأسست عام 1952م، وصاحبة الفضل في تعليم وإعداد قيادات الموانئ البحرية والتجارة والطب والهندسة بالبحر الأحمر.',
                'دنقلا الثانوية' => 'منارة الولاية الشمالية وقلعة النوابغ على ضفاف النيل تأسست عام 1957م بدنقلا، وعُرفت بنسب تفوقها الكاسحة في الشهادة الثانوية وتخريج كبار العلماء والأساتذة والأطباء.',
                'الفاشر القديمة' => 'قلعة التعليم الكبرى في دارفور وحاضرة السلطان علي دينار تأسست عام 1955م، ومثلت المركز الثانوي الأول الذي احتضن نوابغ الغرب الحبيب وخرّج كبار وزراء وقضاة وأطباء السودان.',
                'عطبرة الثانوية القديمة' => 'قلعة التنوير والفكر في عاصمة الحديد والنار تأسست عام 1950م بمدينة عطبرة، وخرّجت كبار قادة الهندسة الميكانيكية والسكك الحديدية والطب وأساتذة الجامعات ورواد الحركة الوطنية.',
                'شندي الثانوية' => 'صرح تربوي وأكاديمي عريق بمدينة شندي التاريخية تأسس عام 1954م، وتخرجت منه أجيال قادت مرافق الدولة والجامعات والقطاع الطبي والهندسي والقضائي.',
                'كوستي الأهلية' => 'أيقونة الجهد الشعبي والتجاري بالنيل الأبيض تأسست عام 1952م بالجهد الذاتي لأهالي وتجار كوستي، وخرّجت أطباء ومهندسين ورجال أعمال وقادة في الخدمة المدنية.',
                'الدلنج الثانوية' => 'قلعة العلم والمعرفة في قلب جبال النوبة ومدينة التعليم الأولى تأسست عام 1958م بالدلنج، ومثلت صرحاً للتآخي والتنوير وتخريج قيادات التعليم والصحة والإدارة بجنوب كردفان.',
                'الشيخ مصطفى الأمين' => 'درة الوقف التعليمي الخيري وأسطورة التفوق بالشهادة السودانية تأسست عام 1970م بمبادرة المحسن الكبير الشيخ مصطفى الأمين، وحصدت المركز الأول بالشهادة السودانية عشرات المرات وخرّجت نوابغ جراحة القلب والذكاء الاصطناعي.',
                'أم درمان النموذجية بنات' => 'قلعة تفوق الفتيات بالعاصمة الوطنية وصانعة أوائل الشهادة الثانوية تأسست عام 1952م بأم درمان، وتربعت لعقود على قمة كليات الطب والصيدلة والعلوم بجامعة الخرطوم.'
            ];

            foreach ($historicUpdates as $keyName => $descText) {
                try {
                    $pdo->prepare("UPDATE daleel_schools SET description = ? WHERE name LIKE ?")->execute([$descText, "%{$keyName}%"]);
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {}

        // Strict Verification Rule: Verification & accreditation is exclusively managed through Control Panel claims/approvals
        try {
            $pdo->exec("ALTER TABLE daleel_schools MODIFY COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("UPDATE daleel_schools SET is_verified = 0 WHERE is_schoplus_client = 0 AND id NOT IN (SELECT school_id FROM daleel_school_claims WHERE status = 'approved')");
        } catch (Throwable $e) {}

        // Zero-Skip Schema Migration: Make phone fields nullable and clean dummy placeholders
        try {
            $pdo->exec("ALTER TABLE daleel_schools MODIFY COLUMN phone VARCHAR(50) NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE daleel_schools MODIFY COLUMN owner_phone VARCHAR(50) NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE daleel_schools MODIFY COLUMN whatsapp VARCHAR(50) NULL DEFAULT NULL");
            $pdo->exec("UPDATE daleel_schools SET phone = NULL WHERE phone = '0100000000' OR phone LIKE '%00000%' OR phone LIKE '%00001' OR phone LIKE '%00002' OR phone LIKE '%00003'");
            $pdo->exec("UPDATE daleel_schools SET owner_phone = NULL WHERE owner_phone = '0100000000' OR owner_phone LIKE '%00000%' OR owner_phone LIKE '%00001' OR owner_phone LIKE '%00002' OR owner_phone LIKE '%00003'");
            $pdo->exec("UPDATE daleel_schools SET whatsapp = NULL WHERE whatsapp = '0100000000' OR whatsapp LIKE '%00000%' OR whatsapp LIKE '%00001' OR whatsapp LIKE '%00002' OR whatsapp LIKE '%00003'");
        } catch (Throwable $e) {}

        // Strict Stages Audit: Government schools NEVER have kindergarten in Sudan + Deduce stages by name
        try {
            require_once __DIR__ . '/SudanGeography.php';
            $allSchoolsToAudit = $pdo->query("SELECT id, name, school_type, description, stages FROM daleel_schools")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($allSchoolsToAudit)) {
                $updStageStmt = $pdo->prepare("UPDATE daleel_schools SET stages = ? WHERE id = ?");
                foreach ($allSchoolsToAudit as $schItem) {
                    $sId = (int)$schItem['id'];
                    $sName = $schItem['name'] ?? '';
                    $sType = $schItem['school_type'] ?? 'private';
                    $sDesc = $schItem['description'] ?? '';
                    $curStages = json_decode($schItem['stages'] ?? '[]', true) ?: [];

                    $newStages = SudanGeography::deduceStages($sType, $sName, $curStages, $sDesc);
                    if ($newStages !== $curStages) {
                        $updStageStmt->execute([json_encode($newStages, JSON_UNESCAPED_UNICODE), $sId]);
                    }
                }
            }
        } catch (Throwable $e) {}

        // News table schema update: branch_name
        $newsCols = [];
        $nColsQuery = $pdo->query("SHOW COLUMNS FROM daleel_school_news");
        if ($nColsQuery) {
            foreach ($nColsQuery->fetchAll() as $ncRow) {
                $newsCols[$ncRow['Field']] = true;
            }
        }
        if (!isset($newsCols['branch_name'])) {
            $pdo->exec("ALTER TABLE daleel_school_news ADD COLUMN branch_name VARCHAR(150) NULL AFTER title");
        }

        // Inquiries table schema update: extra fields
        $inqCols = [];
        $iColsQuery = $pdo->query("SHOW COLUMNS FROM daleel_school_inquiries");
        if ($iColsQuery) {
            foreach ($iColsQuery->fetchAll() as $icRow) {
                $inqCols[$icRow['Field']] = true;
            }
        }
        $inqAlters = [
            'parent_email'         => "ALTER TABLE daleel_school_inquiries ADD COLUMN parent_email VARCHAR(150) NULL AFTER parent_phone",
            'student_name'         => "ALTER TABLE daleel_school_inquiries ADD COLUMN student_name VARCHAR(150) NULL AFTER parent_email",
            'student_gender'       => "ALTER TABLE daleel_school_inquiries ADD COLUMN student_gender VARCHAR(50) NULL AFTER student_name",
            'student_stage'        => "ALTER TABLE daleel_school_inquiries ADD COLUMN student_stage VARCHAR(100) NULL AFTER student_gender",
            'previous_school'      => "ALTER TABLE daleel_school_inquiries ADD COLUMN previous_school VARCHAR(200) NULL AFTER student_grade",
            'branch_name'          => "ALTER TABLE daleel_school_inquiries ADD COLUMN branch_name VARCHAR(150) NULL AFTER previous_school",
            'needs_transportation' => "ALTER TABLE daleel_school_inquiries ADD COLUMN needs_transportation TINYINT(1) NOT NULL DEFAULT 0 AFTER branch_name",
        ];
        foreach ($inqAlters as $col => $sql) {
            if (!isset($inqCols[$col])) {
                $pdo->exec($sql);
            }
        }

        // Purge accidental markdown report header artifacts if present
        try {
            $pdo->exec("DELETE FROM daleel_schools WHERE slug = 'قائمة-المدارس-التي-تم-فحصها-واستبعادها' OR name LIKE '%فحصها واستبعادها%' OR name LIKE 'قائمة المدارس%'");
        } catch (Throwable $e) {}

        // Ensure Al-Akhillaa exists as TWO distinct schools:
        // 1. مدارس الأخلاء الخاصة — فرع بانت شرق (ID 25)
        // 2. مدارس الأخلاء الخاصة — فرع أمبدة السبيل (ID 26)
        try {
            // Delete old combined single entry if still present
            $pdo->exec("DELETE FROM daleel_schools WHERE name = 'مدارس الأخلاء الخاصة' OR slug = 'مدارس-الأخلاء-الخاصة'");

            // Check Branch 1 (Bant East)
            $b1 = $pdo->query("SELECT id, name FROM daleel_schools WHERE slug = 'مدارس-الأخلاء-الخاصة-فرع-بانت-شرق' OR name LIKE '%الأخلاء%بانت%'")->fetch(PDO::FETCH_ASSOC);
            // Check Branch 2 (Ombada)
            $b2 = $pdo->query("SELECT id, name FROM daleel_schools WHERE slug = 'مدارس-الأخلاء-الخاصة-فرع-أمبدة-السبيل' OR name LIKE '%الأخلاء%أمبدة%' OR name LIKE '%الاخلاء%امبدة%'")->fetch(PDO::FETCH_ASSOC);

            if (!$b1 || (int)$b1['id'] !== 25 || !$b2 || (int)$b2['id'] !== 26) {
                $pdo->exec("DELETE FROM daleel_schools WHERE slug IN ('مدارس-الأخلاء-الخاصة-فرع-بانت-شرق', 'مدارس-الأخلاء-الخاصة-فرع-أمبدة-السبيل')");

                $has25 = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE id = 25")->fetchColumn();
                if ($has25 > 0) {
                    $pdo->exec("UPDATE daleel_schools SET id = id + 1 WHERE id >= 25 ORDER BY id DESC");
                }
                $has26 = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE id = 26")->fetchColumn();
                if ($has26 > 0) {
                    $pdo->exec("UPDATE daleel_schools SET id = id + 1 WHERE id >= 26 ORDER BY id DESC");
                }

                $stagesJson = json_encode(['primary', 'intermediate', 'secondary'], JSON_UNESCAPED_UNICODE);
                $pwdHash = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);

                $insStmt = $pdo->prepare("INSERT INTO daleel_schools (
                    id, name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
                    stages, state, city, district, address, phone, whatsapp, email, website,
                    description, status, is_verified, is_schoplus_client, rating_avg, reviews_count, views_count,
                    owner_name, owner_title, owner_phone, password, created_at, updated_at
                ) VALUES (
                    :id, :name, :name_en, :slug, 'private', 'mixed', 'sudanese', 'السودان', NULL,
                    :stages, 'الخرطوم', :city, :district, :address, NULL, NULL, NULL, NULL,
                    :description, 'approved', 0, 0, 5.00, 0, 1,
                    'إدارة مدارس الأخلاء الخاصة', 'مدير المدرسة / المفوض', NULL, :password, NOW(), NOW()
                )");

                // Branch 1: Bant East (ID 25)
                $insStmt->execute([
                    ':id' => 25,
                    ':name' => 'مدارس الأخلاء الخاصة — فرع بانت شرق',
                    ':name_en' => 'Al-Ikhlaa Private Schools — Bant East Branch',
                    ':slug' => 'مدارس-الأخلاء-الخاصة-فرع-بانت-شرق',
                    ':stages' => $stagesJson,
                    ':city' => 'أم درمان',
                    ':district' => 'بانت شرق',
                    ':address' => 'ولاية الخرطوم — أم درمان — حي بانت شرق (جوار مدرسة أم درمان الأهلية)',
                    ':description' => 'صرح تربوي وتعليمي متميز في أم درمان بحي بانت شرق (جوار مدرسة أم درمان الأهلية)، يضم المراحل الابتدائية والمتوسطة والثانوية (بنين وبنات)، ويهتم ببناء القيم الأكاديمية والتربوية والأنشطة الإبداعية وتنمية مهارات الطلاب.',
                    ':password' => $pwdHash
                ]);

                // Branch 2: Ombada Al-Sabeel (ID 26)
                $insStmt->execute([
                    ':id' => 26,
                    ':name' => 'مدارس الأخلاء الخاصة — فرع أمبدة السبيل',
                    ':name_en' => 'Al-Ikhlaa Private Schools — Ombada Al-Sabeel Branch',
                    ':slug' => 'مدارس-الأخلاء-الخاصة-فرع-أمبدة-السبيل',
                    ':stages' => $stagesJson,
                    ':city' => 'أم بدة',
                    ':district' => 'السبيل الحارة 8',
                    ':address' => 'ولاية الخرطوم — أم بدة — السبيل الحارة 8 (جوار مسجد كرار)',
                    ':description' => 'مؤسسة تعليمية وتربوية رائدة في أم بدة السبيل الحارة 8 (جوار مسجد كرار)، توفر بيئة مدرسية نموذجية لطلاب المراحل الابتدائية والمتوسطة والثانوية (بنين وبنات)، وتضم نخبة من الكفاءات التعليمية مع برامج تربوية وأنشطة صفية ولاصفية متكاملة.',
                    ':password' => $pwdHash
                ]);
            } else {
                $pdo->exec("UPDATE daleel_schools SET is_verified = 0, is_schoplus_client = 0 WHERE id IN (25, 26)");
            }
        } catch (Throwable $e) {}

        // Zero-Skip Production Auto-Seed: Ensure 100% data parity across cloud nodes
        // Initial Master Seed Check: only on clean empty database
        try {
            $currCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
            $seedFile = __DIR__ . '/seed_schools_data.php';
            if ($currCount === 0 && file_exists($seedFile)) {
                require_once __DIR__ . '/SchoolBatchImporter.php';
                $seedImporter = new SchoolBatchImporter($pdo);
                $seedSchools = require $seedFile;
                if (is_array($seedSchools) && count($seedSchools) > 0) {
                    $seedImporter->importBatch($seedSchools);
                }
            }
        } catch (Throwable $e) {}

        // Dedicated Diaspora Seed Check: Ensure international diaspora schools parity
        try {
            $diasporaCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE country != 'السودان'")->fetchColumn();
            $diasporaSeedFile = __DIR__ . '/seed_diaspora_schools_data.php';
            if ($diasporaCount === 0 && file_exists($diasporaSeedFile)) {
                require_once __DIR__ . '/SchoolBatchImporter.php';
                $diasporaImporter = new SchoolBatchImporter($pdo);
                $diasporaSchools = require $diasporaSeedFile;
                if (is_array($diasporaSchools) && count($diasporaSchools) > 0) {
                    $diasporaImporter->importBatch($diasporaSchools);
                }
            }
        } catch (Throwable $e) {}

        // Self-healing Daleel Deduplication & Branch Normalization Routine
        try {
            $apprCount = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
            $dupCheck = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE name = 'مدارس القبس الخاصة' AND city = 'عطبرة'")->fetchColumn();
            if ($apprCount > 1560 || $dupCheck > 0) {
                require_once __DIR__ . '/run_deduplication.php';
                runDaleelDeduplication($pdo);
            }
        } catch (Throwable $e) {}

        // Sync default country and sequential directory_rank and state_rank for approved schools
        try {
            $pdo->exec("UPDATE daleel_schools SET country = 'السودان' WHERE country IS NULL OR country = ''");
            recalculateDaleelRanks($pdo);
        } catch (Throwable $e) {}
    } catch (Exception $e) {}

    // Clean up any accidental rogue schools created from system filenames or reserved slugs
    try {
        $dummySlugs = "'index.php', 'index', 'home', 'schools', 'school', 'admin', 'api', 'dashboard', 'login', 'register', 'logout'";
        $pdo->exec("DELETE FROM daleel_school_reviews WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ({$dummySlugs}) OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_school_news WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ({$dummySlugs}) OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_school_inquiries WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ({$dummySlugs}) OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_schools WHERE slug IN ({$dummySlugs}) OR name LIKE '%Index.php%'");
    } catch (Throwable $e) {}
}

/**
 * Get currently logged-in school owner
 */
function currentSchool(): ?array {
    $id = $_SESSION['daleel_school_id'] ?? null;
    if (!$id) return null;
    $stmt = db()->prepare("SELECT * FROM daleel_schools WHERE id = ?");
    $stmt->execute([(int)$id]);
    $res = $stmt->fetch();
    return $res ?: null;
}
