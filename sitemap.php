<?php
/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Dynamic XML Sitemap Generator
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">

    <!-- Homepage -->
    <url>
        <loc>https://daleel.schoplus.sd/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Register School -->
    <url>
        <loc>https://daleel.schoplus.sd/register</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Login -->
    <url>
        <loc>https://daleel.schoplus.sd/login</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

<?php
try {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, slug, updated_at, created_at FROM daleel_schools WHERE status = 'approved' ORDER BY id DESC");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schools as $s) {
        $slug = !empty($s['slug']) ? $s['slug'] : (string)$s['id'];
        $loc = 'https://daleel.schoplus.sd/school/' . $slug;
        $date = !empty($s['updated_at']) ? substr($s['updated_at'], 0, 10) : (!empty($s['created_at']) ? substr($s['created_at'], 0, 10) : date('Y-m-d'));
        echo "    <url>\n";
        echo "        <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        echo "        <lastmod>" . htmlspecialchars($date, ENT_XML1, 'UTF-8') . "</lastmod>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.9</priority>\n";
        echo "    </url>\n";
    }
} catch (\Throwable $e) {
    // Fail-safe empty catch
}
?>
</urlset>
