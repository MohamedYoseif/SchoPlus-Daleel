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
        student_grade VARCHAR(100) NULL,
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
        ];
        foreach ($alterStatements as $colName => $sql) {
            if (!isset($existingCols[$colName])) {
                $pdo->exec($sql);
            }
        }
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
