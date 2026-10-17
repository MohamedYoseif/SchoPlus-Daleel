<?php
require_once __DIR__ . '/config.php';

$secKey = $_GET['key'] ?? '';
if (php_sapi_name() !== 'cli' && $secKey !== 'schoplus_daleel_import_secret_8899') {
    die('Unauthorized');
}

$pdo = db();
$pending = $pdo->query("SELECT id, name, school_type, country, state, city, district, status, is_verified, created_at FROM daleel_schools WHERE status = 'pending' OR (is_verified = 0 AND status != 'approved')")->fetchAll(PDO::FETCH_ASSOC);

echo "Pending Schools on Cloud:\n";
print_r($pending);

$claims = $pdo->query("SELECT * FROM daleel_school_claims WHERE status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
echo "\nPending Claims on Cloud:\n";
print_r($claims);

