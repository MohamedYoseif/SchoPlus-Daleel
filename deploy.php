<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd) - GitHub Auto Deployment Webhook
 * Node 6 of SchoPlus Cloud Ecosystem
 */

$secretKey = "schoplus_daleel_deploy_sec_4412f";

// Security Check (Token in query parameter OR GitHub HMAC signature)
$providedKey = $_GET["key"] ?? "";
$signature = $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? "";

$authorized = false;

if ($providedKey === $secretKey) {
    $authorized = true;
} elseif ($signature) {
    $payload = file_get_contents("php://input");
    $computed = "sha256=" . hash_hmac("sha256", $payload, $secretKey);
    if (hash_equals($computed, $signature)) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    die(json_encode(["error" => "Unauthorized access"]));
}

chdir(__DIR__);

$output = [];
$output[] = "=== SchoPlus Daleel Auto-Deploy Started: " . date("Y-m-d H:i:s") . " ===";

// 1. Check if git repository exists, if not clone it
$repoDir = '/home/wdenfygx/repositories/SchoPlus-Daleel';
if (!is_dir(__DIR__ . '/.git')) {
    if (!is_dir($repoDir . '/.git')) {
        @mkdir(dirname($repoDir), 0755, true);
        exec("git clone https://github.com/MohamedYoseif/SchoPlus-Daleel.git " . escapeshellarg($repoDir) . " 2>&1", $output);
    }
    if (is_dir($repoDir . '/.git')) {
        chdir($repoDir);
        exec("git fetch origin master 2>&1", $output);
        exec("git reset --hard origin/master 2>&1", $output);
        exec("git pull origin master 2>&1", $output);
        chdir(__DIR__);
        exec("cp -rf " . escapeshellarg($repoDir) . "/. " . escapeshellarg(__DIR__) . "/ 2>&1", $output);
        $output[] = "Synced repository files into daleel.schoplus.sd successfully.";
    }
} else {
    // Standard git deployment
    exec("git fetch origin master 2>&1", $output);
    exec("git stash -u 2>&1", $output);
    exec("git reset --hard origin/master 2>&1", $output);
    exec("git pull origin master 2>&1", $output);
}


exec("git log -n 1 --oneline 2>&1", $output);
$output[] = "=== Daleel Deployment Finished Successfully ===";

header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    "success" => true,
    "timestamp" => date("c"),
    "logs" => $output
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
