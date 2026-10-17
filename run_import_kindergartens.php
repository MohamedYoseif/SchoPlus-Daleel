<?php
/**
 * SchoPlus Schools Directory (Node 6)
 * Dedicated Kindergartens & Nurseries Batch Import Runner (CLI / Web)
 * 
 * Usage via CLI:
 *   php run_import_kindergartens.php
 *   php run_import_kindergartens.php --dry-run
 * 
 * Usage via Web:
 *   https://daleel.schoplus.sd/run_import_kindergartens.php?key=schoplus_daleel_import_secret_8899
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SchoolBatchImporter.php';

@set_time_limit(300);
@ini_set('memory_limit', '256M');

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    $secKey = $_GET['key'] ?? '';
    if ($secKey !== 'schoplus_daleel_import_secret_8899') {
        http_response_code(403);
        die('Unauthorized access. Secret key required.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="background:#0B2A52;color:#ffffff;padding:20px;border-radius:12px;font-family:monospace;direction:ltr;">';
}

echo "========================================================\n";
echo "  SchoPlus Daleel — 300 Verified Kindergartens Batch Importer\n";
echo "========================================================\n\n";

$seedFile = __DIR__ . '/seed_kindergartens_data.php';
if (!file_exists($seedFile)) {
    die("Error: seed_kindergartens_data.php not found.\n");
}

$schools = require $seedFile;
$count = count($schools);
echo "Loaded {$count} authentic kindergartens from seed data.\n";

$isDryRun = (!empty($_GET['dry_run']) || (isset($argv) && in_array('--dry-run', $argv)));

if ($isDryRun) {
    echo "Running in DRY-RUN mode (No changes will be written to DB)...\n\n";
    $stageStats = [];
    $validCount = 0;
    foreach ($schools as $i => $s) {
        $deduced = SudanGeography::deduceStages($s['school_type'], $s['name'], $s['stages'], $s['description']);
        if ($deduced === ['kindergarten']) {
            $validCount++;
        } else {
            echo "Warning: Index {$i} ({$s['name']}) resolved to " . json_encode($deduced, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "Dry Run Verification: {$validCount} / {$count} institutions strictly resolve to ['kindergarten'].\n";
    echo "Dry run complete.\n";
    if (!$isCli) echo '</pre>';
    exit(0);
}

echo "Connecting to database...\n";
$importer = new SchoolBatchImporter(db());

echo "Starting import...\n";
$start = microtime(true);
$results = $importer->importBatch($schools);
$duration = round(microtime(true) - $start, 3);

echo "\n------------------ IMPORT SUMMARY ------------------\n";
echo "Total processed : " . $results['total'] . "\n";
echo "New inserted    : " . $results['inserted'] . "\n";
echo "Skipped (exists): " . $results['skipped'] . "\n";
echo "Errors          : " . $results['errors'] . "\n";
echo "Time elapsed    : " . $duration . " seconds\n";
echo "----------------------------------------------------\n\n";

$totalKg = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE stages LIKE '%kindergarten%'")->fetchColumn();
$totalSchools = (int)db()->query("SELECT COUNT(*) FROM daleel_schools")->fetchColumn();

echo "Directory Status Now:\n";
echo "- Total Schools in Directory: {$totalSchools}\n";
echo "- Total Kindergarten Centers: {$totalKg}\n";

$samples = db()->query("SELECT name, city, state, curriculum, stages FROM daleel_schools WHERE stages LIKE '%kindergarten%' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRecent Kindergarten Entries Sample:\n";
foreach ($samples as $sm) {
    echo "  * {$sm['name']} ({$sm['city']} — {$sm['state']}) [{$sm['curriculum']}]: {$sm['stages']}\n";
}

echo "\nKindergarten Batch Import completed successfully!\n";

if (!$isCli) {
    echo '</pre>';
}
