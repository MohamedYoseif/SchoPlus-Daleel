<?php
/**
 * Clean Test School and Claims Script
 */
require_once __DIR__ . '/config.php';

$secKey = $_GET['key'] ?? '';
if (php_sapi_name() !== 'cli' && $secKey !== 'schoplus_daleel_import_secret_8899') {
    die('Unauthorized');
}

$pdo = db();

// 1. Delete test claim
$delClaims = $pdo->prepare("DELETE FROM daleel_school_claims WHERE claimant_phone = '0242124212' OR claimant_name = 'بلللالا' OR school_id = 137");
$delClaims->execute();
$claimsCount = $delClaims->rowCount();

// 2. Delete test school
$delSchool = $pdo->prepare("DELETE FROM daleel_schools WHERE id = 137 AND name LIKE '%مدارس المعالي الأهلية الحديثة%'");
$delSchool->execute();
$schoolCount = $delSchool->rowCount();

// If not deleted by id 137, search by exact name and pending status
if ($schoolCount === 0) {
    $delSchool2 = $pdo->prepare("DELETE FROM daleel_schools WHERE name = 'مدارس المعالي الأهلية الحديثة' AND status = 'pending'");
    $delSchool2->execute();
    $schoolCount = $delSchool2->rowCount();
}

echo "Clean Results:\n";
echo "- Deleted Claims: {$claimsCount}\n";
echo "- Deleted Schools: {$schoolCount}\n";

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM daleel_schools WHERE status = 'pending'")->fetchColumn();
$pendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM daleel_school_claims WHERE status = 'pending'")->fetchColumn();
echo "- Remaining Pending Schools: {$pendingCount}\n";
echo "- Remaining Pending Claims: {$pendingClaims}\n";
