<?php
/**
 * SchoPlus Schools Directory (Node 6)
 * Import Runner Script (CLI / Web)
 * Usage via CLI: C:\xampp\php\php.exe run_import.php
 * Usage via Web: https://daleel.schoplus.sd/run_import.php?key=schoplus_daleel_import_secret_8899
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
echo "  SchoPlus Schools Directory — Automated Batch Importer \n";
echo "========================================================\n\n";

$seedFile = __DIR__ . '/seed_schools_data.php';
if (!file_exists($seedFile)) {
    die("Error: seed_schools_data.php not found.\n");
}

$schools = require $seedFile;
$count = count($schools);
echo "Loaded {$count} schools from seed data.\n";
echo "Connecting to database...\n";

$importer = new SchoolBatchImporter(db());

if (!empty($_GET['purge_synthetic']) || (isset($argv) && in_array('--purge-synthetic', $argv))) {
    echo "Purging synthetic records (id > 110)...\n";
    $deleted = db()->exec("DELETE FROM daleel_schools WHERE id > 110");
    echo "Deleted {$deleted} synthetic records from database.\n";
}

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

echo "Directory Status Now:\n";
$approved = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
$verified = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE is_verified = 1")->fetchColumn();
$unverified = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE is_verified = 0")->fetchColumn();

echo "- Total Approved Schools: {$approved}\n";
echo "- Verified Official     : {$verified}\n";
echo "- Public Claimable      : {$unverified}\n";

$govWithKg = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE school_type = 'government' AND stages LIKE '%kindergarten%'")->fetchColumn();
echo "- Gov schools with KG   : {$govWithKg} (MUST BE 0)\n";

$samples = db()->query("SELECT name, school_type, stages FROM daleel_schools WHERE school_type = 'government' LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
foreach ($samples as $sm) {
    echo "  * " . $sm['name'] . " => " . $sm['stages'] . "\n";
}

echo "\nImport completed successfully!\n";

if (!$isCli) {
    echo '</pre>';
}
