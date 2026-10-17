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

        $country = trim($data['country'] ?? 'السودان') ?: 'السودان';
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
        // Guard against fabricated or placeholder phone numbers
        if ($phone === '' || $phone === '0100000000' || preg_match('/0{4,}/', $phone)) {
            $phone = null;
        }

        $whatsapp = trim($data['whatsapp'] ?? '');
        if ($whatsapp === '' && $phone) {
            $whatsapp = $phone;
        }
        if ($whatsapp === '0100000000' || preg_match('/0{4,}/', (string)$whatsapp)) {
            $whatsapp = null;
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
        $approvedRows = $this->pdo->query("SELECT id, country, state FROM daleel_schools WHERE status = 'approved' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($approvedRows)) return;

        $dirSeq = 0;
        $stateSeqs = [];
        $upd = $this->pdo->prepare("UPDATE daleel_schools SET directory_rank = ?, state_rank = ? WHERE id = ?");

        foreach ($approvedRows as $row) {
            $dirSeq++;
            $cntry = trim((string)($row['country'] ?? '')) ?: 'السودان';
            $st = trim((string)($row['state'] ?? ''));
            $key = $cntry . ':::' . $st;
            $stateSeqs[$key] = ($stateSeqs[$key] ?? 0) + 1;
            $stSeq = $stateSeqs[$key];

            $upd->execute([$dirSeq, $stSeq, (int)$row['id']]);
        }
    }
}
