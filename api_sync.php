<?php
/**
 * SchoPlus Daleel — Direct Cloud Sync Endpoint (Node 6)
 * نقطة نهاية المزامنة المباشرة لقاعدة بيانات دليل المدارس ورسائل الواتساب
 *
 * Security: Shared Secret Token + Admin Session Check
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$pdo = db();
$syncSecretKey = 'schoplus_daleel_deploy_sec_4412f';

$rawInput = file_get_contents('php://input');
$jsonPayload = json_decode($rawInput, true) ?: [];

// ─── 1. AUTHENTICATION CHECK ────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$syncKeyHeader = $_SERVER['HTTP_X_SYNC_KEY'] ?? '';
$providedKey = $_POST['key'] ?? ($_GET['key'] ?? ($jsonPayload['key'] ?? ''));

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) {
    $providedKey = trim($m[1]);
} elseif (!empty($syncKeyHeader)) {
    $providedKey = trim($syncKeyHeader);
}

$isAdminSession = false;
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!empty($_SESSION['daleel_master_admin']) || !empty($_SESSION['daleel_admin_id'])) {
    $isAdminSession = true;
}

if (!$isAdminSession && $providedKey !== $syncSecretKey) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'غير مصرح لك بالوصول (مفتاح المزامنة السري غير صالح أو مفقود).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_POST['action'] ?? ($_GET['action'] ?? ($jsonPayload['action'] ?? 'stats')));

// ─── 2. ACTION: STATS (فحص إحصائيات السحابة والمزامنة) ───────────────────────
if ($action === 'stats') {
    try {
        $totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE deleted_at IS NULL")->fetchColumn();
        $approvedSchools = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND deleted_at IS NULL")->fetchColumn();
        $verifiedSchools = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE is_verified = 1 AND deleted_at IS NULL")->fetchColumn();
        $withPhone = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE phone IS NOT NULL AND phone != '' AND deleted_at IS NULL")->fetchColumn();
        $waSentCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE whatsapp_sent = 1 AND deleted_at IS NULL")->fetchColumn();
        
        $waMessagesCount = 0;
        try {
            $waMessagesCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_whatsapp_messages")->fetchColumn();
        } catch (Throwable $e) {}

        $lastUpdated = $pdo->query("SELECT MAX(updated_at) FROM daleel_schools")->fetchColumn();

        $versionData = [];
        $versionFile = __DIR__ . '/version.json';
        if (file_exists($versionFile)) {
            $versionData = json_decode(file_get_contents($versionFile), true) ?: [];
        }

        echo json_encode([
            'success' => true,
            'node' => 'daleel.schoplus.sd',
            'version' => $versionData['version'] ?? '3.8.32',
            'total_schools' => $totalSchools,
            'approved_schools' => $approvedSchools,
            'verified_schools' => $verifiedSchools,
            'with_phone' => $withPhone,
            'whatsapp_sent_count' => $waSentCount,
            'whatsapp_messages_count' => $waMessagesCount,
            'last_updated' => $lastUpdated,
            'server_time' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()]);
    }
    exit;
}

// ─── ACTION: SAVE_SETTING (مزامنة الإعدادات كالمفاتيح بين السحابة والمحلي) ────────
if ($action === 'save_setting') {
    $key = trim($_POST['key'] ?? ($jsonPayload['key'] ?? ''));
    $val = trim($_POST['value'] ?? ($jsonPayload['value'] ?? ''));
    if (!empty($key)) {
        try {
            $ok = setDaleelSetting($pdo, $key, $val);
            echo json_encode(['success' => $ok, 'message' => "Setting {$key} synced successfully"]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// ─── 3. ACTION: PUSH_SINGLE_SCHOOL (مزامنة فورية لمدرسة تم حفظها أو تعديلها) ──────
if ($action === 'push_single_school') {
    $s = $jsonPayload['school'] ?? ($_POST['school'] ?? []);
    if (empty($s) || empty($s['name'])) {
        echo json_encode(['success' => false, 'error' => 'بيانات المدرسة غير مكتملة']);
        exit;
    }

    $name = trim($s['name']);
    $slug = trim($s['slug'] ?? '') ?: createSlug($name);

    $chk = $pdo->prepare("SELECT id FROM daleel_schools WHERE slug = ? OR name = ? LIMIT 1");
    $chk->execute([$slug, $name]);
    $existId = $chk->fetchColumn();

    $fields = [
        'name' => $name,
        'name_en' => $s['name_en'] ?? null,
        'slug' => $slug,
        'school_type' => $s['school_type'] ?? 'private',
        'gender_type' => $s['gender_type'] ?? 'mixed',
        'curriculum' => $s['curriculum'] ?? 'sudanese',
        'country' => $s['country'] ?? 'السودان',
        'state' => $s['state'] ?? 'الخرطوم',
        'city' => $s['city'] ?? 'المركز',
        'district' => $s['district'] ?? null,
        'address' => $s['address'] ?? null,
        'google_map_url' => $s['google_map_url'] ?? null,
        'phone' => $s['phone'] ?? '',
        'whatsapp' => $s['whatsapp'] ?? null,
        'email' => $s['email'] ?? null,
        'website' => $s['website'] ?? null,
        'established_year' => $s['established_year'] ?? null,
        'stages' => is_array($s['stages'] ?? null) ? json_encode($s['stages'], JSON_UNESCAPED_UNICODE) : ($s['stages'] ?? null),
        'facilities' => is_array($s['facilities'] ?? null) ? implode(',', $s['facilities']) : ($s['facilities'] ?? null),
        'approx_fees' => $s['approx_fees'] ?? null,
        'working_hours' => $s['working_hours'] ?? null,
        'description' => $s['description'] ?? null,
        'vision' => $s['vision'] ?? null,
        'mission' => $s['mission'] ?? null,
        'owner_name' => $s['owner_name'] ?? ('إدارة ' . $name),
        'owner_title' => $s['owner_title'] ?? 'مدير المدرسة / المفوض',
        'owner_phone' => $s['owner_phone'] ?? ($s['phone'] ?? ''),
        'owner_email' => $s['owner_email'] ?? null,
        'status' => $s['status'] ?? 'approved',
        'is_verified' => !empty($s['is_verified']) ? 1 : 0,
        'is_schoplus_client' => !empty($s['is_schoplus_client']) ? 1 : 0,
        'is_historic' => !empty($s['is_historic']) ? 1 : 0,
        'directory_rank' => !empty($s['directory_rank']) ? (int)$s['directory_rank'] : null,
        'state_rank' => !empty($s['state_rank']) ? (int)$s['state_rank'] : null,
        'whatsapp_status' => $s['whatsapp_status'] ?? 'unverified',
        'whatsapp_checked_at' => $s['whatsapp_checked_at'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($existId) {
        $setClauses = [];
        $setValues = [];
        foreach ($fields as $col => $val) {
            $setClauses[] = "`{$col}` = ?";
            $setValues[] = $val;
        }
        $setValues[] = (int)$existId;
        $sql = "UPDATE daleel_schools SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($setValues);
        $finalId = $existId;
        $op = 'updated';
    } else {
        $cols = array_keys($fields);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO daleel_schools (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $pdo->prepare($sql)->execute(array_values($fields));
        $finalId = $pdo->lastInsertId();
        $op = 'created';
    }

    try { recalculateDaleelRanks($pdo); } catch (Throwable $e) {}

    echo json_encode([
        'success' => true,
        'op' => $op,
        'id' => $finalId,
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── ACTION: PUSH_WA_STATUS (تحديث فوري لحالة واتساب) ────────────────────────
if ($action === 'push_wa_status') {
    $slug = trim($jsonPayload['slug'] ?? ($_POST['slug'] ?? ''));
    $phone = trim($jsonPayload['phone'] ?? ($_POST['phone'] ?? ''));
    $status = trim($jsonPayload['whatsapp_status'] ?? ($_POST['whatsapp_status'] ?? 'valid'));
    $schoolId = (int)($jsonPayload['school_id'] ?? ($_POST['school_id'] ?? 0));

    $sql = "UPDATE daleel_schools SET whatsapp_status = ?, whatsapp_checked_at = NOW() WHERE ";
    $params = [$status];
    if ($schoolId > 0) {
        $sql .= "id = ?";
        $params[] = $schoolId;
    } elseif ($slug !== '') {
        $sql .= "slug = ?";
        $params[] = $slug;
    } elseif ($phone !== '') {
        $sql .= "(phone = ? OR whatsapp = ?)";
        $params[] = $phone;
        $params[] = $phone;
    } else {
        echo json_encode(['success' => false, 'error' => 'معرف المدرسة مفقود']);
        exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── ACTION: DELETE DISTORTED DUPLICATES ────────────────────────────────────
if ($action === 'delete_distorted_duplicates') {
    $cnt = $pdo->exec("DELETE FROM daleel_schools WHERE name LIKE 'مدارس ثانوية%' OR name LIKE '%الخاصه الخاصة%' OR name LIKE '%درة الإيمان%' OR name LIKE 'مدارس روضه%' OR name LIKE 'مدارس مدرسه%'");
    echo json_encode(['success' => true, 'deleted_count' => (int)$cnt], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 4. ACTION: PUSH (استقبال وتحديث المدارس وحالات الواتساب من المحلي) ────────
if ($action === 'push') {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    $payload = !empty($jsonPayload) ? $jsonPayload : (json_decode(file_get_contents('php://input'), true) ?: []);

    if (empty($payload) && !empty($_POST['data'])) {
        $payload = json_decode($_POST['data'], true);
    }

    if (empty($payload) || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'حزمة البيانات فارغة أو بتنسيق غير صالح.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $schools = $payload['schools'] ?? [];
    $whatsappUpdates = $payload['whatsapp_updates'] ?? [];
    $messages = $payload['messages'] ?? [];

    $createdSchools = 0;
    $updatedSchools = 0;
    $syncedWhatsApp = 0;
    $syncedMessages = 0;
    $errors = [];

    // أ) مزامنة المدارس (Upsert)
    if (!empty($schools) && is_array($schools)) {
        foreach ($schools as $s) {
            $name = trim($s['name'] ?? '');
            if (mb_strlen($name, 'UTF-8') < 2) continue;

            $slug = trim($s['slug'] ?? '');
            if (empty($slug)) {
                $slug = createSlug($name);
            }

            $state = trim($s['state'] ?? 'الخرطوم');
            $city = trim($s['city'] ?? 'المركز');
            $district = trim($s['district'] ?? $city);
            $schoolType = trim($s['school_type'] ?? 'private');
            $genderType = trim($s['gender_type'] ?? 'mixed');
            $curriculum = trim($s['curriculum'] ?? 'sudanese');
            $estYear = !empty($s['established_year']) ? (int)$s['established_year'] : null;

            $stages = $s['stages'] ?? ['primary'];
            if (is_string($stages)) {
                $stagesDecoded = json_decode($stages, true);
                if (is_array($stagesDecoded)) $stages = $stagesDecoded;
                else $stages = array_filter(array_map('trim', explode(',', $stages)));
            }
            $stagesJson = json_encode(array_values($stages), JSON_UNESCAPED_UNICODE);

            $phone = !empty($s['phone']) ? trim($s['phone']) : null;
            $whatsapp = !empty($s['whatsapp']) ? trim($s['whatsapp']) : $phone;
            $waSent = !empty($s['whatsapp_sent']) ? 1 : 0;
            $waSentAt = !empty($s['whatsapp_sent_at']) ? $s['whatsapp_sent_at'] : ($waSent ? date('Y-m-d H:i:s') : null);

            $gmapUrl = !empty($s['google_map_url']) ? trim($s['google_map_url']) : null;
            $facebook = !empty($s['facebook']) ? trim($s['facebook']) : null;
            $youtube = !empty($s['youtube']) ? trim($s['youtube']) : null;
            $tiktok = !empty($s['tiktok']) ? trim($s['tiktok']) : null;
            $website = !empty($s['website']) ? trim($s['website']) : null;
            $description = !empty($s['description']) ? trim($s['description']) : null;
            $address = !empty($s['address']) ? trim($s['address']) : "ولاية {$state} — {$city}" . ($district !== $city ? " — حي {$district}" : "");

            try {
                // فحص هل المدرسة موجودة مسبقاً بالسحابة بالاسم أو الرابط الدائم
                $chk = $pdo->prepare("SELECT id, phone, whatsapp, google_map_url, facebook, description, whatsapp_sent, whatsapp_sent_at FROM daleel_schools WHERE name = ? OR slug = ? LIMIT 1");
                $chk->execute([$name, $slug]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // تحديث الحقول الفارغة فقط أو تحديث حالة الواتساب
                    $updParts = [];
                    $updParams = [];

                    if (empty($existing['phone']) && !empty($phone)) {
                        $updParts[] = "phone = ?";
                        $updParams[] = $phone;
                    }
                    if (empty($existing['whatsapp']) && !empty($whatsapp)) {
                        $updParts[] = "whatsapp = ?";
                        $updParams[] = $whatsapp;
                    }
                    if (empty($existing['google_map_url']) && !empty($gmapUrl)) {
                        $updParts[] = "google_map_url = ?";
                        $updParams[] = $gmapUrl;
                    }
                    if (empty($existing['facebook']) && !empty($facebook)) {
                        $updParts[] = "facebook = ?";
                        $updParams[] = $facebook;
                    }
                    if (empty($existing['description']) && !empty($description)) {
                        $updParts[] = "description = ?";
                        $updParams[] = $description;
                    }

                    // مزامنة حالة الواتساب إن كانت مرسلة محلياً
                    if ($waSent && empty($existing['whatsapp_sent'])) {
                        $updParts[] = "whatsapp_sent = 1";
                        $updParts[] = "whatsapp_sent_at = ?";
                        $updParams[] = $waSentAt;
                        $syncedWhatsApp++;
                    }

                    if (!empty($updParts)) {
                        $updParts[] = "updated_at = NOW()";
                        $updParams[] = (int)$existing['id'];
                        $sql = "UPDATE daleel_schools SET " . implode(', ', $updParts) . " WHERE id = ?";
                        $pdo->prepare($sql)->execute($updParams);
                        $updatedSchools++;
                    }
                } else {
                    // إدراج المدرسة كمؤسسة جديدة معتمدة
                    $rndPass = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO daleel_schools (
                        name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
                        stages, state, city, district, address, google_map_url, phone, whatsapp, whatsapp_sent, whatsapp_sent_at,
                        website, facebook, youtube, tiktok, description, status, is_verified, is_schoplus_client,
                        rating_avg, reviews_count, views_count, owner_name, owner_title, owner_phone, password, created_at, updated_at
                    ) VALUES (
                        :name, :name_en, :slug, :school_type, :gender_type, :curriculum, 'السودان', :established_year,
                        :stages, :state, :city, :district, :address, :google_map_url, :phone, :whatsapp, :whatsapp_sent, :whatsapp_sent_at,
                        :website, :facebook, :youtube, :tiktok, :description, 'approved', 0, 0,
                        5.00, 0, 1, :owner_name, 'مدير المدرسة / المفوض', :owner_phone, :password, NOW(), NOW()
                    )");

                    $ins->execute([
                        ':name' => $name,
                        ':name_en' => $s['name_en'] ?? null,
                        ':slug' => $slug,
                        ':school_type' => $schoolType,
                        ':gender_type' => $genderType,
                        ':curriculum' => $curriculum,
                        ':established_year' => $estYear,
                        ':stages' => $stagesJson,
                        ':state' => $state,
                        ':city' => $city,
                        ':district' => $district,
                        ':address' => $address,
                        ':google_map_url' => $gmapUrl,
                        ':phone' => $phone,
                        ':whatsapp' => $whatsapp,
                        ':whatsapp_sent' => $waSent,
                        ':whatsapp_sent_at' => $waSentAt,
                        ':website' => $website,
                        ':facebook' => $facebook,
                        ':youtube' => $youtube,
                        ':tiktok' => $tiktok,
                        ':description' => $description,
                        ':owner_name' => 'إدارة ' . $name,
                        ':owner_phone' => $phone,
                        ':password' => $rndPass
                    ]);

                    $createdSchools++;
                    if ($waSent) $syncedWhatsApp++;
                }
            } catch (Throwable $e) {
                $errors[] = "خطأ في معالجة مدرسة {$name}: " . $e->getMessage();
            }
        }
    }

    // ب) مزامنة تحديثات الواتساب المستقلة
    if (!empty($whatsappUpdates) && is_array($whatsappUpdates)) {
        $updWaStmt = $pdo->prepare("UPDATE daleel_schools SET whatsapp_sent = 1, whatsapp_sent_at = ? WHERE (name = ? OR slug = ? OR (phone = ? AND phone IS NOT NULL AND phone != ''))");
        foreach ($whatsappUpdates as $wu) {
            $wName = trim($wu['name'] ?? '');
            $wSlug = trim($wu['slug'] ?? '');
            $wPhone = trim($wu['phone'] ?? '');
            $wSentAt = !empty($wu['whatsapp_sent_at']) ? $wu['whatsapp_sent_at'] : date('Y-m-d H:i:s');

            if ($wName !== '' || $wSlug !== '' || $wPhone !== '') {
                $updWaStmt->execute([$wSentAt, $wName, $wSlug, $wPhone]);
                if ($updWaStmt->rowCount() > 0) {
                    $syncedWhatsApp++;
                }
            }
        }
    }

    // ج) مزامنة سجلات الرسائل (Message Logs)
    if (!empty($messages) && is_array($messages)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_whatsapp_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                school_id BIGINT UNSIGNED NULL,
                direction ENUM('in', 'out') NOT NULL DEFAULT 'in',
                phone VARCHAR(50) NOT NULL,
                chat_id VARCHAR(100) NULL,
                message TEXT NOT NULL,
                media_url VARCHAR(255) NULL,
                status VARCHAR(50) DEFAULT 'delivered',
                intent VARCHAR(50) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_phone (phone),
                INDEX idx_school (school_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $insMsg = $pdo->prepare("INSERT INTO daleel_whatsapp_messages (school_id, direction, phone, chat_id, message, intent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $chkMsg = $pdo->prepare("SELECT id FROM daleel_whatsapp_messages WHERE phone = ? AND message = ? AND created_at = ? LIMIT 1");

            foreach ($messages as $msg) {
                $mPhone = trim($msg['phone'] ?? '');
                $mBody = trim($msg['message'] ?? '');
                $mTime = !empty($msg['created_at']) ? $msg['created_at'] : date('Y-m-d H:i:s');
                if (empty($mPhone) || empty($mBody)) continue;

                $chkMsg->execute([$mPhone, $mBody, $mTime]);
                if (!$chkMsg->fetchColumn()) {
                    // العثور على معرف المدرسة بالسحابة إن توفر
                    $schId = null;
                    if (!empty($msg['school_name'])) {
                        $schId = $pdo->prepare("SELECT id FROM daleel_schools WHERE name = ? LIMIT 1");
                        $schId->execute([$msg['school_name']]);
                        $schId = $schId->fetchColumn() ?: null;
                    }
                    $insMsg->execute([
                        $schId,
                        $msg['direction'] ?? 'out',
                        $mPhone,
                        $msg['chat_id'] ?? null,
                        $mBody,
                        $msg['intent'] ?? 'school_outreach',
                        $mTime
                    ]);
                    $syncedMessages++;
                }
            }
        } catch (Throwable $e) {}
    }

    // د) إعادة حساب وترتيب الرتب التسلسلية في السحابة
    $totalCloudRanks = 0;
    try {
        $totalCloudRanks = recalculateDaleelRanks($pdo);
    } catch (Throwable $e) {}

    // هـ) إعادة تصدير ملف البذرة الرئيسي على السحابة لضمان الأرشفة
    try {
        if (file_exists(__DIR__ . '/export_master_seed.php')) {
            require_once __DIR__ . '/export_master_seed.php';
            exportMasterSeed($pdo);
        }
    } catch (Throwable $e) {}

    $totalSchoolsNow = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved' AND deleted_at IS NULL")->fetchColumn();

    echo json_encode([
        'success' => true,
        'created_schools' => $createdSchools,
        'updated_schools' => $updatedSchools,
        'synced_whatsapp' => $syncedWhatsApp,
        'synced_messages' => $syncedMessages,
        'total_cloud_schools' => $totalSchoolsNow,
        'ranks_recalculated' => $totalCloudRanks,
        'errors' => $errors,
        'message' => "تمت المزامنة السحابية بنجاح! تم إدراج ({$createdSchools}) مدرسة جديدة، وتحديث ({$updatedSchools}) مؤسسة، ومزامنة ({$syncedWhatsApp}) حالة إرسال واتساب."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 4. ACTION: PULL (جلب المدارس من السحابة لتحديث القاعدة المحلية) ──────────
if ($action === 'pull') {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    $since = trim($_GET['since'] ?? ($_POST['since'] ?? ''));
    $limit = (int)($_GET['limit'] ?? ($_POST['limit'] ?? 2000));
    $limit = max(1, min(5000, $limit));

    try {
        $sql = "SELECT id, name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
                       stages, state, city, district, address, google_map_url, phone, whatsapp, whatsapp_sent, whatsapp_sent_at,
                       whatsapp_status, whatsapp_checked_at,
                       email, website, source_url, source_type, facebook, twitter, youtube, telegram, instagram, tiktok,
                       facilities, approx_fees, working_hours, description, vision, mission,
                       owner_name, owner_title, owner_phone, owner_email,
                       status, is_verified, is_schoplus_client, is_historic, directory_rank, state_rank,
                       rating_avg, reviews_count, views_count, created_at, updated_at
                FROM daleel_schools 
                WHERE status = 'approved' AND deleted_at IS NULL";
        
        $params = [];
        if (!empty($since) && strtotime($since) !== false) {
            $sql .= " AND updated_at >= ?";
            $params[] = $since;
        }

        $sql .= " ORDER BY id ASC LIMIT " . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => count($rows),
            'schools' => $rows,
            'server_time' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ أثناء جلب السجلات من السحابة: ' . $e->getMessage()]);
    }
    exit;
}

// Default fallback
echo json_encode([
    'success' => false,
    'error' => 'إجراء غير معروف. الإجراءات المتاحة: stats, push, pull.'
], JSON_UNESCAPED_UNICODE);
