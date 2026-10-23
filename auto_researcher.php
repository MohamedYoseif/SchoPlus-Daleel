<?php
/**
 * SchoPlus Daleel — Autonomous School Discovery & Research Engine
 * محرك الباحث الآلي للمدارس — يعمل في الخلفية (Background Engine / Admin Automation)
 * استوديو الاستقصاء متاح حصرياً من داخل لوحة التحكم: admin.php?tab=researcher
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
require_once __DIR__ . '/SchoolAutoResearcher.php';

$pdo = db();

// =========================================================================
// 1. CLI EXECUTION SUPPORT (FOR CRON JOBS & BACKGROUND WORKERS)
// =========================================================================
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "=== SchoPlus Daleel Autonomous Background Researcher ===\n";
    $options = getopt('', ['query:', 'state:', 'city:', 'phone:', 'scan']);
    $query = $options['query'] ?? null;
    $state = $options['state'] ?? null;
    $city = $options['city'] ?? null;
    $phone = $options['phone'] ?? null;

    if (!empty($query)) {
        echo "Discovering single school: {$query}...\n";
        $res = SchoolAutoResearcher::autoDiscoverAndRegister($pdo, $query, $state, $city, null, $phone, $phone);
        print_r($res);
        exit(0);
    }

    echo "Running background locality check...\n";
    $total = (int)$pdo->query("SELECT count(*) FROM daleel_schools WHERE status = 'approved'")->fetchColumn();
    echo "Current approved institutions: {$total}\n";
    echo "=== Background Run Completed ===\n";
    exit(0);
}

// =========================================================================
// 2. CONTROL PANEL ENFORCEMENT (STUDIO LIVES INSIDE ADMIN CP ONLY)
// =========================================================================
$adminSecretKey = "schoplus_daleel_deploy_sec_4412f";
$providedKey = $_GET['key'] ?? ($_POST['key'] ?? ($_SESSION['admin_key'] ?? ''));

if (!empty($_SESSION['daleel_master_admin']) || $providedKey === $adminSecretKey) {
    $_SESSION['daleel_master_admin'] = true;
    header('Location: admin.php?tab=researcher');
    exit;
}

// Redirect unauthorized visitors to Admin Login
header('Location: admin.php');
exit;
