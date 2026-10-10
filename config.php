<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Standalone Node Configuration & Database Bootstrap
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

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

if (!function_exists('getSchoolDirectoryRank')) {
    function getSchoolDirectoryRank(PDO $pdo, int $schoolId, ?int $customRank = null): int {
        if ($customRank !== null && $customRank > 0) {
            return $customRank;
        }

        static $cachedRanks = null;
        if ($cachedRanks === null) {
            try {
                // الترتيب حصراً للمدارس المعتمدة فقط (status = 'approved') وفق أسبقية الاعتماد
                $stmt = $pdo->query("SELECT id, directory_rank FROM daleel_schools WHERE status = 'approved' ORDER BY id ASC");
                $cachedRanks = [];
                $seq = 1;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $savedRank = !empty($row['directory_rank']) ? (int)$row['directory_rank'] : null;
                    $rankToUse = ($savedRank !== null && $savedRank > 0) ? $savedRank : $seq;
                    $cachedRanks[(int)$row['id']] = $rankToUse;
                    $seq++;
                }
            } catch (Throwable $e) {
                $cachedRanks = [];
            }
        }

        if (isset($cachedRanks[$schoolId])) {
            return $cachedRanks[$schoolId];
        }

        // إذا كانت المدرسة قيد المراجعة أو جديدة، ترتيبها المتوقع عند الاعتماد هو (حصر المدارس المعتمدة + 1)
        $approvedCount = is_array($cachedRanks) ? count($cachedRanks) : 0;
        return max(1, $approvedCount + 1);
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
        return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool {
        $token = $_POST['_csrf_token'] ?? '';
        $stored = $_SESSION['_csrf_token'] ?? '';
        return !empty($token) && hash_equals($stored, $token);
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
        is_verified TINYINT(1) NOT NULL DEFAULT 1,
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
            'optional_tabs'   => "ALTER TABLE daleel_schools ADD COLUMN optional_tabs TEXT NULL AFTER teachers_data",
            'directory_rank'  => "ALTER TABLE daleel_schools ADD COLUMN directory_rank INT UNSIGNED NULL DEFAULT NULL AFTER is_featured",
            'established_year'=> "ALTER TABLE daleel_schools ADD COLUMN established_year VARCHAR(50) NULL AFTER curriculum",
            'fees_type'       => "ALTER TABLE daleel_schools ADD COLUMN fees_type VARCHAR(50) NOT NULL DEFAULT 'average' AFTER approx_fees",
            'stage_fees'      => "ALTER TABLE daleel_schools ADD COLUMN stage_fees LONGTEXT NULL AFTER fees_type",
            'fee_discounts'   => "ALTER TABLE daleel_schools ADD COLUMN fee_discounts LONGTEXT NULL AFTER stage_fees",
            'admission_status'       => "ALTER TABLE daleel_schools ADD COLUMN admission_status VARCHAR(50) NOT NULL DEFAULT 'open' AFTER fee_discounts",
            'admission_requirements' => "ALTER TABLE daleel_schools ADD COLUMN admission_requirements LONGTEXT NULL AFTER admission_status",
            'admission_steps'        => "ALTER TABLE daleel_schools ADD COLUMN admission_steps LONGTEXT NULL AFTER admission_requirements",
            'admission_notes'        => "ALTER TABLE daleel_schools ADD COLUMN admission_notes TEXT NULL AFTER admission_steps",
        ];
        foreach ($alterStatements as $colName => $sql) {
            if (!isset($existingCols[$colName])) {
                $pdo->exec($sql);
            }
        }

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
        // Auto-assign permanent sequential directory_rank for approved schools without rank (حصر المدارس المعتمدة + 1)
        try {
            $unrankedApproved = $pdo->query("SELECT id FROM daleel_schools WHERE status = 'approved' AND (directory_rank IS NULL OR directory_rank = 0) ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($unrankedApproved)) {
                $maxRank = (int) $pdo->query("SELECT MAX(directory_rank) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
                $updRankStmt = $pdo->prepare("UPDATE daleel_schools SET directory_rank = ? WHERE id = ?");
                foreach ($unrankedApproved as $unId) {
                    $maxRank++;
                    $updRankStmt->execute([$maxRank, (int)$unId]);
                }
            }
        } catch (Throwable $e) {}
    } catch (Exception $e) {}

    // Clean up any accidental rogue schools created from system filenames or reserved slugs
    try {
        $pdo->exec("DELETE FROM daleel_school_reviews WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ('index.php', 'index', 'home', 'schools', 'school', 'admin', 'api', 'dashboard', 'login', 'register', 'logout') OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_school_news WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ('index.php', 'index', 'home', 'schools', 'school', 'admin', 'api', 'dashboard', 'login', 'register', 'logout') OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_school_inquiries WHERE school_id IN (SELECT id FROM daleel_schools WHERE slug IN ('index.php', 'index', 'home', 'schools', 'school', 'admin', 'api', 'dashboard', 'login', 'register', 'logout') OR name LIKE '%Index.php%')");
        $pdo->exec("DELETE FROM daleel_schools WHERE slug IN ('index.php', 'index', 'home', 'schools', 'school', 'admin', 'api', 'dashboard', 'login', 'register', 'logout') OR name LIKE '%Index.php%'");
    } catch (Throwable $e) {}

    // Seed default flagship schools if empty
    $count = (int) $pdo->query("SELECT COUNT(*) FROM daleel_schools")->fetchColumn();
    if ($count === 0) {
        seedInitialSchools($pdo);
    }
}

/**
 * Seed Flagship Schools
 */
function seedInitialSchools(PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $pw = password_hash('password123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO daleel_schools (
        name, name_en, slug, school_type, gender_type, curriculum, stages, state, city, district,
        address, phone, whatsapp, email, website, description, approx_fees, working_hours,
        facilities, is_schoplus_client, is_featured, is_verified, rating_avg, reviews_count, views_count,
        status, owner_name, owner_title, owner_phone, password, created_at, updated_at
    ) VALUES (
        :name, :name_en, :slug, :school_type, :gender_type, :curriculum, :stages, :state, :city, :district,
        :address, :phone, :whatsapp, :email, :website, :description, :approx_fees, :working_hours,
        :facilities, :is_schoplus_client, :is_featured, :is_verified, :rating_avg, :reviews_count, :views_count,
        :status, :owner_name, :owner_title, :owner_phone, :password, :created_at, :updated_at
    )");

    $schools = [
        [
            'name' => 'مدارس آيسكو الذكية النموذجية',
            'name_en' => 'Isco Smart Model Schools',
            'slug' => 'isco-smart-model-schools',
            'school_type' => 'private',
            'gender_type' => 'mixed',
            'curriculum' => 'sudanese',
            'stages' => json_encode(['kindergarten', 'primary', 'intermediate', 'secondary'], JSON_UNESCAPED_UNICODE),
            'state' => 'الخرطوم',
            'city' => 'بحري',
            'district' => 'المزاد جنوب',
            'address' => 'شارع الإنقاذ، جوار مجمع النور الطبي، بحري',
            'phone' => '+249912345678',
            'whatsapp' => '+249912345678',
            'email' => 'info@isco-schools.sd',
            'website' => 'https://schoplus.sd',
            'description' => 'مؤسسة تعليمية رائدة تعتمد أحدث معايير التعليم الرقمي الذكي المتكامل المدعوم بنظام SchoPlus السحابي. نوفر بيئة تربوية ملهمة تركز على بناء الشخصية، التميز الأكاديمي، وتنمية المهارات الرقمية والابتكار.',
            'approx_fees' => 'رسوم مناسبة مع خطط تقسيط ميسرة',
            'working_hours' => 'الأحد إلى الخميس: 7:30 صباحاً - 3:00 مساءً',
            'facilities' => json_encode(['computer_lab', 'science_lab', 'smart_classes', 'transportation', 'library', 'sports_court', 'ac', 'activities', 'mosque'], JSON_UNESCAPED_UNICODE),
            'is_schoplus_client' => 1,
            'is_featured' => 1,
            'is_verified' => 1,
            'rating_avg' => 4.95,
            'reviews_count' => 38,
            'views_count' => 1420,
            'status' => 'approved',
            'owner_name' => 'د. محمد يوسف',
            'owner_title' => 'مدير عام المجمع',
            'owner_phone' => '0912345678',
            'password' => $pw,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'name' => 'مدارس المنار العالمية الحديثة',
            'name_en' => 'Al-Manar Modern International Schools',
            'slug' => 'al-manar-modern-international-schools',
            'school_type' => 'international',
            'gender_type' => 'mixed',
            'curriculum' => 'british',
            'stages' => json_encode(['kindergarten', 'primary', 'intermediate', 'secondary'], JSON_UNESCAPED_UNICODE),
            'state' => 'الخرطوم',
            'city' => 'الخرطوم',
            'district' => 'المعمورة',
            'address' => 'شارع الستين، بالقرب من مول عفراء، الخرطوم',
            'phone' => '+249911223344',
            'whatsapp' => '+249911223344',
            'email' => 'contact@almanar-school.sd',
            'website' => 'https://schoplus.sd',
            'description' => 'مدارس دولية متخصصة في تدريس المنهج البريطاني مع الحفاظ الكامل على الهوية واللغة العربية والتربية الإسلامية، بإشراف كفاءات تعليمية دولية ومحلية معتمدة.',
            'approx_fees' => 'رسوم متميزة بالعملة المحلية مع خدمات شاملة',
            'working_hours' => 'الأحد إلى الخميس: 7:00 صباحاً - 2:30 مساءً',
            'facilities' => json_encode(['computer_lab', 'science_lab', 'sports_court', 'pool', 'transportation', 'library', 'cafeteria', 'ac'], JSON_UNESCAPED_UNICODE),
            'is_schoplus_client' => 1,
            'is_featured' => 1,
            'is_verified' => 1,
            'rating_avg' => 4.88,
            'reviews_count' => 24,
            'views_count' => 980,
            'status' => 'approved',
            'owner_name' => 'أ. سارة مصطفى',
            'owner_title' => 'المشرف الأكاديمي',
            'owner_phone' => '0911223344',
            'password' => $pw,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'name' => 'مدارس الساحل الأهلية النموذجية',
            'name_en' => 'Al-Sahel Model Schools',
            'slug' => 'al-sahel-model-schools',
            'school_type' => 'private',
            'gender_type' => 'mixed',
            'curriculum' => 'sudanese',
            'stages' => json_encode(['primary', 'intermediate', 'secondary'], JSON_UNESCAPED_UNICODE),
            'state' => 'البحر الأحمر',
            'city' => 'بورتسودان',
            'district' => 'الثورة مربع 3',
            'address' => 'حي الثورة، غرب المستشفى العسكري، بورتسودان',
            'phone' => '+249918887766',
            'whatsapp' => '+249918887766',
            'email' => 'info@alsahel-sd.com',
            'website' => '',
            'description' => 'من أعرق المدارس الخاصة في ولاية البحر الأحمر، تخرج منها أوائل الشهادة السودانية، نوفر رعاية شاملة وترحيل لكافة أحياء بورتسودان.',
            'approx_fees' => 'مناسبة لكافة الأسر مع أقساط مرنة',
            'working_hours' => 'الأحد إلى الخميس: 7:30 ص - 2:30 م',
            'facilities' => json_encode(['science_lab', 'computer_lab', 'transportation', 'library', 'sports_court', 'mosque'], JSON_UNESCAPED_UNICODE),
            'is_schoplus_client' => 1,
            'is_featured' => 0,
            'is_verified' => 1,
            'rating_avg' => 4.90,
            'reviews_count' => 19,
            'views_count' => 730,
            'status' => 'approved',
            'owner_name' => 'أ. طارق إبراهيم',
            'owner_title' => 'المدير التنفيذي',
            'owner_phone' => '0918887766',
            'password' => $pw,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'name' => 'مجمع النخبة التعليمي الخاص',
            'name_en' => 'Al-Nukhba Educational Complex',
            'slug' => 'al-nukhba-educational-complex',
            'school_type' => 'private',
            'gender_type' => 'boys',
            'curriculum' => 'sudanese',
            'stages' => json_encode(['primary', 'intermediate', 'secondary'], JSON_UNESCAPED_UNICODE),
            'state' => 'الجزيرة',
            'city' => 'ود مدني',
            'district' => 'حي الدباغة',
            'address' => 'شارع النيل، جوار قصر الضيافة، ود مدني',
            'phone' => '+249915554433',
            'whatsapp' => '+249915554433',
            'email' => '',
            'website' => '',
            'description' => 'مجمع تعليمي للبنين يهدف إلى إعداد قيادات المستقبل مع التركيز على النشاط الرياضي وتنمية المهارات اللغوية والتقنية.',
            'approx_fees' => 'متوسطة',
            'working_hours' => 'الأحد إلى الخميس: 7:30 ص - 2:30 م',
            'facilities' => json_encode(['sports_court', 'library', 'computer_lab', 'mosque', 'activities'], JSON_UNESCAPED_UNICODE),
            'is_schoplus_client' => 0,
            'is_featured' => 0,
            'is_verified' => 1,
            'rating_avg' => 4.75,
            'reviews_count' => 15,
            'views_count' => 540,
            'status' => 'approved',
            'owner_name' => 'أ. عادل النور',
            'owner_title' => 'مدير المجمع',
            'owner_phone' => '0915554433',
            'password' => $pw,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    ];

    foreach ($schools as $s) {
        $stmt->execute($s);
        $schoolId = (int) $pdo->lastInsertId();

        // Seed sample news
        $newsStmt = $pdo->prepare("INSERT INTO daleel_school_news (school_id, title, content, published_at, views_count) VALUES (?, ?, ?, ?, ?)");
        $newsStmt->execute([
            $schoolId,
            'فتح باب القبول والتسجيل للعام الدراسي الجديد',
            'تعلن إدارة المدرسة عن بدء إجراءات التسجيل وحجز المقاعد للعام الدراسي الجديد لكافة المراحل التعليمية مع توفير خطط تقسيط ميسرة لأولياء الأمور.',
            $now,
            120
        ]);

        // Seed sample review
        $revStmt = $pdo->prepare("INSERT INTO daleel_school_reviews (school_id, reviewer_name, reviewer_role, rating, comment, school_reply, is_approved) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $revStmt->execute([
            $schoolId,
            'محمد عبد الرحيم',
            'parent',
            5,
            'مدرسة ممتازة وإدارة واعية وكادر تدريسي على أعلى مستوى من الكفاءة والأمانة.',
            'شكراً جزيلاً أستاذ محمد، نسعد دائماً بخدمة أبنائنا الطلاب.',
        ]);
    }
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
