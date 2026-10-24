<?php
/**
 * SchoPlus Daleel — Seamless Real-Time Autonomous Synchronization Service
 * خدمة المزامنة المتزامنة اللحظية بين القاعدة المحلية والسحابية بدون أزرار
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DaleelWhatsAppService.php';

class DaleelSyncService {

    private static string $syncUrl = 'https://daleel.schoplus.sd/api_sync.php';
    private static string $syncSecret = 'schoplus_daleel_deploy_sec_4412f';

    /**
     * فحص هل المحطة الحالية هي المحطة المحلية
     */
    public static function isLocal(): bool {
        return DaleelWhatsAppService::isLocalStation();
    }

    /**
     * إرسال طلب آمن للسحابة مع معالجة سريعة (cURL)
     */
    public static function callCloud(string $action, array $data = [], int $timeout = 6): ?array {
        $payload = array_merge([
            'action' => $action,
            'key' => self::$syncSecret
        ], $data);

        $url = self::$syncUrl . '?action=' . urlencode($action) . '&key=' . self::$syncSecret;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Sync-Key: ' . self::$syncSecret
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SchoPlus-Daleel-AutoSync/3.8');

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$res) {
            return null;
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * مزامنة فورية لمدرسة تم حفظها أو تعديلها محلياً
     */
    public static function pushSchoolById(int $schoolId): bool {
        if (!self::isLocal()) return false;
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$school) return false;

            $res = self::callCloud('push_single_school', ['school' => $school], 5);
            return !empty($res['success']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * مزامنة فورية لحالة فحص الواتساب
     */
    public static function pushWaStatus(int $schoolId, string $status, ?string $jid = null): bool {
        if (!self::isLocal()) return false;
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id, name, slug, phone, whatsapp FROM daleel_schools WHERE id = ? LIMIT 1");
            $stmt->execute([$schoolId]);
            $sch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sch) return false;

            $res = self::callCloud('push_wa_status', [
                'school_id' => $schoolId,
                'slug' => $sch['slug'],
                'phone' => $sch['whatsapp'] ?: $sch['phone'],
                'whatsapp_status' => $status,
                'jid' => $jid
            ], 4);
            return !empty($res['success']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * المزامنة المتزامنة الصامتة بين السحابي والمحلي في الخلفية (تبادل التغييرات)
     */
    public static function autoSyncDelta(): array {
        if (!self::isLocal()) {
            return ['success' => true, 'is_cloud' => true];
        }

        $pdo = db();
        $lastSyncTime = null;
        try {
            $st = $pdo->prepare("SELECT setting_value FROM daleel_settings WHERE setting_key = 'last_cloud_auto_sync' LIMIT 1");
            $st->execute();
            $lastSyncTime = $st->fetchColumn();
        } catch (Throwable $e) {}

        if (!$lastSyncTime) {
            $lastSyncTime = date('Y-m-d H:i:s', strtotime('-1 day'));
        }

        // جلب المدارس المحدثة في السحابة
        $cloudData = self::callCloud('pull', [
            'since' => $lastSyncTime,
            'limit' => 500
        ], 10);

        $syncedCount = 0;
        if (!empty($cloudData['success']) && !empty($cloudData['schools']) && is_array($cloudData['schools'])) {
            $chk = $pdo->prepare("SELECT id, updated_at FROM daleel_schools WHERE name = ? OR slug = ? LIMIT 1");

            foreach ($cloudData['schools'] as $s) {
                $name = trim($s['name'] ?? '');
                $slug = trim($s['slug'] ?? '');
                if ($name === '') continue;

                $chk->execute([$name, $slug]);
                $loc = $chk->fetch(PDO::FETCH_ASSOC);

                if ($loc) {
                    // تحديث المحلي إن كان تاريخ السحابة أحدث
                    if (empty($loc['updated_at']) || strtotime($s['updated_at']) > strtotime($loc['updated_at'])) {
                        self::upsertLocalSchool($pdo, $s, (int)$loc['id']);
                        $syncedCount++;
                    }
                } else {
                    // إضافة المدرسة الجديدة محلياً
                    self::upsertLocalSchool($pdo, $s, null);
                    $syncedCount++;
                }
            }

            if ($syncedCount > 0) {
                try { recalculateDaleelRanks($pdo); } catch (Throwable $e) {}
            }
        }

        // دفع المدارس المحدثة أو المضافة محلياً منذ آخر مزامنة إلى السحابة تلقائياً
        try {
            $locStmt = $pdo->prepare("SELECT * FROM daleel_schools WHERE status = 'approved' AND (updated_at >= ? OR created_at >= ?) AND deleted_at IS NULL LIMIT 50");
            $locStmt->execute([$lastSyncTime, $lastSyncTime]);
            $localChanges = $locStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($localChanges)) {
                foreach ($localChanges as $ls) {
                    self::callCloud('push_single_school', ['school' => $ls], 4);
                }
            }
        } catch (Throwable $e) {}

        // تحديث طابع آخر مزامنة تلقائية
        try {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO daleel_settings (setting_key, setting_value, created_at) 
                           VALUES ('last_cloud_auto_sync', ?, NOW()) 
                           ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()")->execute([$now, $now]);
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'synced_count' => $syncedCount,
            'timestamp' => date('H:i:s')
        ];
    }

    /**
     * إدراج أو تحديث سجل مدرسة في القاعدة المحلية
     */
    private static function upsertLocalSchool(PDO $pdo, array $s, ?int $existingId): void {
        $fields = [
            'name' => trim($s['name'] ?? ''),
            'name_en' => $s['name_en'] ?? null,
            'slug' => trim($s['slug'] ?? ''),
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
            'owner_name' => $s['owner_name'] ?? ('إدارة ' . ($s['name'] ?? '')),
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
            'updated_at' => $s['updated_at'] ?? date('Y-m-d H:i:s')
        ];

        try {
            if ($existingId) {
                $sets = [];
                $vals = [];
                foreach ($fields as $col => $val) {
                    $sets[] = "`{$col}` = ?";
                    $vals[] = $val;
                }
                $vals[] = $existingId;
                $sql = "UPDATE daleel_schools SET " . implode(', ', $sets) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($vals);
            } else {
                $rndPass = password_hash(bin2hex(random_bytes(6)), PASSWORD_BCRYPT);
                $fields['password'] = $rndPass;
                $cols = array_keys($fields);
                $placeholders = array_fill(0, count($cols), '?');
                $sql = "INSERT INTO daleel_schools (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $pdo->prepare($sql)->execute(array_values($fields));
            }
        } catch (Throwable $e) {}
    }
}
