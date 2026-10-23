<?php
/**
 * SchoPlus Schools Directory (Node 6)
 * Scalable Batch Importer for Schools (خوارزمية الإدخال والاستيراد الشامل لأي عدد من المدارس)
 * 
 * Features:
 * 1. Safe Idempotency (يتحقق من عدم تكرار الاسم في نفس الولاية أو المدينة أو تطابق الـ slug).
 * 2. Automated Arabic Slug Generation with unique numeric suffixing.
 * 3. Safe Defaults: status = 'approved', is_verified = 0 (بيانات عامة جاهزة للمطالبة والتوثيق).
 * 4. Automatic Sequential Ranks Recalculation (directory_rank, state_rank).
 * 5. CLI & Web Execution ready with comprehensive execution metrics.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';

class SchoolBatchImporter
{
    private PDO $pdo;
    private array $reservedSlugs = [
        'index', 'home', 'schools', 'school', 'daleel', 'admin', 'api',
        'login', 'register', 'dashboard', 'logout', 'search'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Validate if a phone number is genuine and not a placeholder/dummy pattern
     */
    public static function isValidRealPhone(?string $phone): bool
    {
        if (!$phone) return false;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 9 || strlen($digits) > 15) return false;

        $norm = $digits;
        if (str_starts_with($norm, '00249')) $norm = substr($norm, 5);
        elseif (str_starts_with($norm, '249')) $norm = substr($norm, 3);
        if (str_starts_with($norm, '0')) $norm = substr($norm, 1);

        // Dummy/Placeholder checks (matching normalized local digits or raw digits)
        $dummyNumbers = [
            '912389100', '912376820', '912389010', '912378940',
            '123456012', '912778899', '912445566', '912667788',
            '912556677', '912334455', '912448833', '912448822',
            '912445588', '912771144', '912551188', '912338877',
            '912441122', '912447788', '912440011', '912558822',
            '912558833', '100000000', '900000000', '123456789'
        ];

        foreach ($dummyNumbers as $d) {
            if (str_contains($norm, $d) || str_contains($digits, $d)) return false;
        }

        // Repeating sequences (e.g. 5 identical consecutive digits, or sequential patterns)
        if (preg_match('/(.)\1{4,}/', $norm) || preg_match('/(.)\1{4,}/', $digits)) return false;
        if (preg_match('/123456|654321|445566|778899|112233|223344|334455|556677/', $norm)) return false;
        if (preg_match('/123456|654321|445566|778899|112233|223344|334455|556677/', $digits)) return false;

        return true;
    }

    /**
     * Generate unique URL slug
     */
    public function generateSlug(string $name, ?string $state = null): string
    {
        $clean = mb_strtolower(trim($name), 'UTF-8');
        // Replace common Arabic suffixes and punctuation
        $clean = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]+/u', '', $clean);
        $clean = preg_replace('/\s+/u', '-', $clean);
        $clean = trim($clean, '-');

        if (empty($clean) || in_array($clean, $this->reservedSlugs, true)) {
            $clean = 'school-' . bin2hex(random_bytes(3));
        }

        $baseSlug = $clean;
        $slug = $baseSlug;
        $counter = 1;

        $checkStmt = $this->pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? LIMIT 1");
        while (true) {
            $checkStmt->execute([$slug]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Import a single school safely
     */
    public function importSchool(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['status' => 'error', 'message' => 'اسم المدرسة مطلوب'];
        }

        // Guard against accidental report/markdown headings
        if (preg_match('/(قائمة\s+المدارس|فحصها\s+واستبعادها|تقرير|منهجية\s+البحث|ملخص\s+تنفيذي)/u', $name)) {
            return ['status' => 'error', 'message' => 'اسم غير صالح للمؤسسة التعليمية (عنوان تقرير)'];
        }

        $country = SudanGeography::normalizeCountry($data['country'] ?? 'السودان');
        $state = trim($data['state'] ?? 'الخرطوم') ?: 'الخرطوم';
        $city = trim($data['city'] ?? 'الخرطوم') ?: 'الخرطوم';
        $district = trim($data['district'] ?? '');

        // Check if school already exists by exact name in the same state/city
        $dupStmt = $this->pdo->prepare("SELECT id, name, slug FROM daleel_schools WHERE name = ? AND country = ? AND state = ? LIMIT 1");
        $dupStmt->execute([$name, $country, $state]);
        $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing school with newly verified fields
            $upPhone = !empty($data['phone']) ? trim($data['phone']) : null;
            $upWhatsapp = !empty($data['whatsapp']) ? trim($data['whatsapp']) : $upPhone;
            $upDesc = !empty($data['description']) ? trim($data['description']) : null;
            $upStages = !empty($data['stages']) ? json_encode(SudanGeography::deduceStages($data['school_type'] ?? 'private', $name, $data['stages'], $upDesc), JSON_UNESCAPED_UNICODE) : null;
            
            $upStmt = $this->pdo->prepare("UPDATE daleel_schools SET 
                phone = COALESCE(phone, :phone),
                whatsapp = COALESCE(whatsapp, :whatsapp),
                stages = COALESCE(:stages, stages),
                description = CASE WHEN description IS NULL OR description = '' THEN :desc ELSE description END
                WHERE id = :id");
            $upStmt->execute([
                ':phone' => $upPhone,
                ':whatsapp' => $upWhatsapp,
                ':stages' => $upStages,
                ':desc' => $upDesc,
                ':id' => $existing['id']
            ]);

            return [
                'status' => 'skipped',
                'message' => 'المدرسة موجودة مسبقاً وتم تحديث التوثيق',
                'id' => $existing['id'],
                'name' => $existing['name'],
                'slug' => $existing['slug']
            ];
        }

        $slug = $this->generateSlug($name, $state);
        $schoolType = SudanGeography::deduceSchoolType($name, $data['school_type'] ?? null);
        $genderType = $data['gender_type'] ?? 'mixed';
        $curriculum = $data['curriculum'] ?? 'sudanese';
        
        // Accurate stage deduction: Government schools NEVER have kindergarten in Sudan, and no school gets kindergarten unless explicitly stated
        $rawStages = $data['stages'] ?? null;
        $description = trim($data['description'] ?? '');
        $deducedStages = SudanGeography::deduceStages($schoolType, $name, $rawStages, $description);
        $stages = json_encode($deducedStages, JSON_UNESCAPED_UNICODE);
        
        $phone = trim($data['phone'] ?? '');
        if (!self::isValidRealPhone($phone)) {
            $phone = null;
        }

        $whatsapp = trim($data['whatsapp'] ?? '');
        if (!self::isValidRealPhone($whatsapp)) {
            $whatsapp = $phone;
        }

        // STRICT ZERO-PHONE PROHIBITION FOR PRIVATE INSTITUTIONS:
        // "سنجعل البحث اكثر صرامة في المدارس الخاصة مالم تجد رقم هاتف حقيقي لا تقم باضافة اي مدرسة"
        if ($schoolType !== 'government') {
            $hasRealPhone = ($phone !== null || $whatsapp !== null);
            if (!$hasRealPhone) {
                return [
                    'status' => 'skipped',
                    'message' => 'مرفوضة: مدرسة خاصة تفتقر لرقم هاتف حقيقي موثق (قاعدة الصرامة الصفرية للمدارس الخاصة)',
                    'name' => $name
                ];
            }
        }

        $email = trim($data['email'] ?? '');
        $website = trim($data['website'] ?? '');
        $establishedYear = trim((string)($data['established_year'] ?? ''));
        // Guard against invalid or placeholder years
        if ($establishedYear === '' || (int)$establishedYear < 1850 || (int)$establishedYear > (int)date('Y')) {
            $establishedYear = null;
        }

        $description = trim($data['description'] ?? "مؤسسة تعليمية وتربوية تقدم خدماتها في {$city} — ولاية {$state}.");

        $status = $data['status'] ?? 'approved';
        $isVerified = !empty($data['force_verified']) ? 1 : 0;
        $isSchoplus = !empty($data['force_schoplus']) ? 1 : 0;
        $ratingAvg = isset($data['rating_avg']) ? (float)$data['rating_avg'] : 5.00;

        $ownerName = trim($data['owner_name'] ?? ('إدارة ' . $name));
        $ownerTitle = trim($data['owner_title'] ?? 'مدير المدرسة / المفوض');
        $ownerPhone = $phone ?: null;
        $password = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);

        $insertSql = "INSERT INTO daleel_schools (
            name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
            stages, state, city, district, address, phone, whatsapp, email, website,
            description, status, is_verified, is_schoplus_client, rating_avg, reviews_count, views_count,
            owner_name, owner_title, owner_phone, password, created_at, updated_at
        ) VALUES (
            :name, :name_en, :slug, :school_type, :gender_type, :curriculum, :country, :established_year,
            :stages, :state, :city, :district, :address, :phone, :whatsapp, :email, :website,
            :description, :status, :is_verified, :is_schoplus_client, :rating_avg, 0, 1,
            :owner_name, :owner_title, :owner_phone, :password, NOW(), NOW()
        )";

        $stmt = $this->pdo->prepare($insertSql);
        $stmt->execute([
            ':name' => $name,
            ':name_en' => trim($data['name_en'] ?? ''),
            ':slug' => $slug,
            ':school_type' => $schoolType,
            ':gender_type' => $genderType,
            ':curriculum' => $curriculum,
            ':country' => $country,
            ':established_year' => $establishedYear ?: null,
            ':stages' => $stages,
            ':state' => $state,
            ':city' => $city,
            ':district' => $district ?: null,
            ':address' => trim($data['address'] ?? ($state . ' — ' . $city . ($district ? ' — ' . $district : ''))),
            ':phone' => $phone ?: null,
            ':whatsapp' => $whatsapp ?: null,
            ':email' => $email ?: null,
            ':website' => $website ?: null,
            ':description' => $description,
            ':status' => $status,
            ':is_verified' => $isVerified,
            ':is_schoplus_client' => $isSchoplus,
            ':rating_avg' => $ratingAvg,
            ':owner_name' => $ownerName,
            ':owner_title' => $ownerTitle,
            ':owner_phone' => $ownerPhone,
            ':password' => $password
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        return [
            'status' => 'inserted',
            'id' => $newId,
            'name' => $name,
            'slug' => $slug,
            'state' => $state
        ];
    }

    /**
     * Batch import multiple schools with auto rank recalculation
     */
    public function importBatch(array $schoolsList): array
    {
        $results = [
            'total' => count($schoolsList),
            'inserted' => 0,
            'skipped' => 0,
            'errors' => 0,
            'items' => []
        ];

        foreach ($schoolsList as $sch) {
            try {
                $res = $this->importSchool($sch);
                if ($res['status'] === 'inserted') {
                    $results['inserted']++;
                } elseif ($res['status'] === 'skipped') {
                    $results['skipped']++;
                } else {
                    $results['errors']++;
                }
                $results['items'][] = $res;
            } catch (Throwable $e) {
                $results['errors']++;
                $results['items'][] = [
                    'status' => 'error',
                    'name' => $sch['name'] ?? 'مجهول',
                    'message' => $e->getMessage()
                ];
            }
        }

        // Recalculate directory_rank and state_rank
        $this->recalculateRanks();

        return $results;
    }

    /**
     * Recalculate directory_rank and state_rank sequentially
     */
    public function recalculateRanks(): void
    {
        recalculateDaleelRanks($this->pdo);
    }
}
