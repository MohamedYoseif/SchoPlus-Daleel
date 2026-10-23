<?php
/**
 * SchoPlus Schools Directory (Node 6)
 * Master Seed Exporter
 */

require_once __DIR__ . '/config.php';

if (!function_exists('exportMasterSeed')) {
    function exportMasterSeed(?PDO $pdo = null): int {
        if ($pdo === null) {
            $pdo = db();
        }

        $stmt = $pdo->query("SELECT 
            name, name_en, slug, school_type, gender_type, curriculum, country, established_year,
            stages, state, city, district, address, phone, whatsapp, email, website, source_url, source_type, description,
            status, is_verified, is_schoplus_client, rating_avg, is_historic, historic_badge_reason, historic_votes
            FROM daleel_schools 
            WHERE status = 'approved' 
            ORDER BY id ASC");

        $schools = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Decode JSON stages
            if (!empty($row['stages'])) {
                $decoded = json_decode($row['stages'], true);
                $row['stages'] = is_array($decoded) ? $decoded : [$row['stages']];
            } else {
                $row['stages'] = [];
            }

            $row['is_verified'] = (int)($row['is_verified'] ?? 0);
            $row['is_schoplus_client'] = (int)($row['is_schoplus_client'] ?? 0);
            $row['is_historic'] = (int)($row['is_historic'] ?? 0);
            $row['historic_votes'] = (int)($row['historic_votes'] ?? 0);

            $schools[] = $row;
        }

        $count = count($schools);
        $targetFile = __DIR__ . '/seed_schools_data.php';

        // Guard against accidentally overwriting production master seed with small local database
        if ($count < 500 && file_exists($targetFile)) {
            $existingData = @include $targetFile;
            if (is_array($existingData) && count($existingData) > $count) {
                return count($existingData);
            }
        }

        $dateStr = date('Y-m-d H:i:s');

        $header = "<?php\n\n/**\n * SchoPlus Daleel - Comprehensive Global Sudanese Schools Directory Dataset\n * Total Institutions: {$count}\n * Coverage: All 18 States of Sudan + 19 Diaspora Countries Worldwide\n * Updated: {$dateStr}\n */\n\nreturn ";

        $content = $header . var_export($schools, true) . ";\n";

        $targetFile = __DIR__ . '/seed_schools_data.php';
        file_put_contents($targetFile, $content);

        return $count;
    }
}

// Only echo output when invoked directly via CLI
if (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $count = exportMasterSeed();
    echo "Successfully exported {$count} approved schools to seed_schools_data.php\n";
}
