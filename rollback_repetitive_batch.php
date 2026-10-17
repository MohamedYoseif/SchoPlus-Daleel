<?php
/**
 * Rollback Repetitive Stages Batch (Clean up ID > 1500)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SchoolBatchImporter.php';

$secKey = $_GET['key'] ?? '';
if (php_sapi_name() !== 'cli' && $secKey !== 'schoplus_daleel_import_secret_8899') {
    die('Unauthorized');
}

$pdo = db();

echo "Deleting repetitive schools with ID > 1500...\n";
$del = $pdo->exec("DELETE FROM daleel_schools WHERE id > 1500");
echo "Deleted {$del} repetitive schools.\n";

// Recalculate ranks
$importer = new SchoolBatchImporter($pdo);
$importer->recalculateRanks();

$remainingCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools")->fetchColumn();
echo "Clean Database Count Now: {$remainingCount} schools.\n";

if (php_sapi_name() === 'cli') {
    // Export clean seed_schools_data.php
    echo "Synchronizing seed_schools_data.php...\n";
    $allRows = $pdo->query("SELECT name, name_en, school_type, gender_type, curriculum, country, state, city, district, address, phone, whatsapp, website, established_year, stages, description, rating_avg, status, is_verified FROM daleel_schools ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $exportData = [];
    foreach ($allRows as $r) {
        $stages = !empty($r['stages']) ? json_decode($r['stages'], true) : [];
        $exportData[] = [
            'name' => $r['name'],
            'name_en' => $r['name_en'] ?? '',
            'school_type' => $r['school_type'] ?? 'private',
            'gender_type' => $r['gender_type'] ?? 'mixed',
            'curriculum' => $r['curriculum'] ?? 'sudanese',
            'country' => $r['country'] ?? 'السودان',
            'state' => $r['state'] ?? 'الخرطوم',
            'city' => $r['city'] ?? 'الخرطوم',
            'district' => $r['district'] ?? '',
            'address' => $r['address'] ?? '',
            'phone' => $r['phone'],
            'whatsapp' => $r['whatsapp'],
            'website' => $r['website'],
            'established_year' => $r['established_year'] ? (string)$r['established_year'] : null,
            'stages' => $stages,
            'description' => $r['description'] ?? '',
            'rating_avg' => (float)$r['rating_avg'],
            'status' => $r['status'] ?? 'approved',
            'is_verified' => (int)$r['is_verified'],
        ];
    }

    $exportedCode = "<?php\n/**\n * SchoPlus Schools Directory (Node 6)\n * Complete Sudanese Schools Seed Data (Total: " . count($exportData) . " Schools)\n */\n\nreturn " . var_export($exportData, true) . ";\n";
    file_put_contents(__DIR__ . '/seed_schools_data.php', $exportedCode);
    echo "seed_schools_data.php successfully synchronized with {$remainingCount} clean schools!\n";
}
