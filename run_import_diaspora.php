<?php
/**
 * SchoPlus Schools Directory (Node 6)
 * Dedicated Diaspora & Overseas Sudanese Schools Batch Import Runner (CLI / Web)
 * 
 * Usage via CLI:
 *   php run_import_diaspora.php
 *   php run_import_diaspora.php --dry-run
 * 
 * Usage via Web:
 *   https://daleel.schoplus.sd/run_import_diaspora.php?key=schoplus_daleel_import_secret_8899
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
echo "  SchoPlus Daleel — Sudanese Diaspora Schools Batch Importer\n";
echo "========================================================\n\n";

$seedFile = __DIR__ . '/seed_diaspora_schools_data.php';
if (!file_exists($seedFile)) {
    die("Error: seed_diaspora_schools_data.php not found.\n");
}

$schools = require $seedFile;
$count = count($schools);
echo "Loaded {$count} authentic diaspora schools from seed data.\n";

$isDryRun = (!empty($_GET['dry_run']) || (isset($argv) && in_array('--dry-run', $argv)));

if ($isDryRun) {
    echo "Running in DRY-RUN mode (No changes will be written to DB)...\n\n";
    $countries = [];
    foreach ($schools as $s) {
        $c = SudanGeography::normalizeCountry($s['country'] ?? 'غير محدد');
        $countries[$c] = ($countries[$c] ?? 0) + 1;
    }
    echo "Country Distribution (" . count($countries) . " countries):\n";
    foreach ($countries as $cName => $cCount) {
        echo "  - {$cName}: {$cCount} institutions\n";
    }
    echo "\nDry run complete.\n";
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

$totalDiaspora = (int)db()->query("SELECT COUNT(*) FROM daleel_schools WHERE country != 'السودان'")->fetchColumn();
$totalSchools = (int)db()->query("SELECT COUNT(*) FROM daleel_schools")->fetchColumn();

echo "Directory Status Now:\n";
echo "- Total Institutions in Directory : {$totalSchools}\n";
echo "- Total Diaspora Schools Worldwide: {$totalDiaspora}\n";

$samples = db()->query("SELECT name, country, state, city, curriculum, phone FROM daleel_schools WHERE country != 'السودان' ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRecent Diaspora Entries Sample:\n";
foreach ($samples as $sm) {
    echo "  * {$sm['name']} ({$sm['country']} — {$sm['city']}) [{$sm['curriculum']}]: {$sm['phone']}\n";
}

echo "\nDiaspora Batch Import completed successfully!\n";

if (!$isCli) {
    echo '</pre>';
}
