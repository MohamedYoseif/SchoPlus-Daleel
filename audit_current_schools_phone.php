<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SchoolBatchImporter.php';

$pdo = db();

echo "=== Comprehensive Audit of Current Database against Strict Phone Rule ===\n\n";

$allSchools = $pdo->query("SELECT id, name, city, state, district, school_type, phone, whatsapp, is_historic, is_verified FROM daleel_schools WHERE status = 'approved' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$govCount = 0;
$privPassingCount = 0;
$privFailing = [];

foreach ($allSchools as $s) {
    if ($s['school_type'] === 'government' || $s['is_historic'] == 1) {
        $govCount++;
        continue;
    }

    $hasRealPhone = SchoolBatchImporter::isValidRealPhone($s['phone']) || SchoolBatchImporter::isValidRealPhone($s['whatsapp']);

    if ($hasRealPhone) {
        $privPassingCount++;
    } else {
        $privFailing[] = $s;
    }
}

echo "Total Approved Schools in Database: " . count($allSchools) . "\n";
echo "1. Government / Historic Schools (Exempt): {$govCount}\n";
echo "2. Private Schools PASSING (Valid Real Phone): {$privPassingCount}\n";
echo "3. Private Schools FAILING (No valid phone): " . count($privFailing) . "\n\n";

echo "=== Breakdown of Failing Private Schools ===\n";

// Let's categorize the failing private schools:
// Category A: Elite / Well-known schools that just need their real official phone numbers fetched/updated
$wellKnown = [];
$otherFailing = [];

$famousTokens = ['كامبريدج', 'كبييدة', 'الأخلاء', 'كنانة', 'المجلس الأفريقي', 'كمبوني', 'الراهبات', 'الفرنسية', 'الأمريكية', 'البريطانية', 'التركية', 'الجالية', 'ميمونة', 'أبو عبيدة', 'الإمام مالك', 'الريادة', 'المصباح', 'الرميساء', 'المنهل'];

foreach ($privFailing as $f) {
    $matched = false;
    foreach ($famousTokens as $tok) {
        if (str_contains($f['name'], $tok)) {
            $matched = true;
            break;
        }
    }
    if ($matched) {
        $wellKnown[] = $f;
    } else {
        $otherFailing[] = $f;
    }
}

echo "Category A (Well-Known / Famous institutions needing official phone retrieval): " . count($wellKnown) . "\n";
echo "Category B (Other private schools without phone): " . count($otherFailing) . "\n\n";

echo "--- Sample of Category A (Famous institutions) ---\n";
foreach (array_slice($wellKnown, 0, 15) as $w) {
    echo "- [{$w['id']}] {$w['name']} ({$w['city']} - {$w['state']})\n";
}

echo "\n--- Sample of Category B (Other failing schools) ---\n";
foreach (array_slice($otherFailing, 0, 15) as $o) {
    echo "- [{$o['id']}] {$o['name']} ({$o['city']} - {$o['state']})\n";
}
