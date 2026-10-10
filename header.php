<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SudanGeography.php';
$currSchool = currentSchool();

$isDaleelMobile = (
    (!empty($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'SchoPlusDaleelMobile')) ||
    (!empty($_COOKIE['is_daleel_app']) && $_COOKIE['is_daleel_app'] === '1') ||
    (isset($_GET['app']) && $_GET['app'] === 'daleel')
);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="_N537rCkVvCqHnhP1qUaVRtqiNzAB-tcGFNeAm9z5gU">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="<?= isset($canonicalUrl) ? e($canonicalUrl) : (isset($ogUrl) ? e($ogUrl) : 'https://daleel.schoplus.sd' . e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) ?>">

    <!-- Google tag (gtag.js) - Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-SMPS2NBVZ3"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-SMPS2NBVZ3');
    </script>

    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/logo-icon.png">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | دليل المدارس السودانية — SchoPlus' : 'دليل المدارس السودانية — دليلك الشامل لاختيار أفضل المدارس في السودان' ?></title>
    
    <!-- Open Graph / WhatsApp / Facebook Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= isset($ogTitle) ? e($ogTitle) : (isset($pageTitle) ? e($pageTitle) . ' | دليل المدارس الذكي' : 'دليل المدارس الذكي — SchoPlus') ?>">
    <meta property="og:description" content="<?= isset($ogDesc) ? e($ogDesc) : 'دليلك الشامل لاختيار أفضل المدارس في السودان — تصفح الرسوم والمرافق وتواصل مباشرة.' ?>">
    <meta property="og:image" content="<?= isset($ogImage) ? e($ogImage) : 'https://daleel.schoplus.sd/logo.png' ?>">
    <meta property="og:url" content="<?= isset($ogUrl) ? e($ogUrl) : 'https://daleel.schoplus.sd' ?>">
    <meta property="og:site_name" content="دليل المدارس الذكي — SchoPlus">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($ogTitle) ? e($ogTitle) : (isset($pageTitle) ? e($pageTitle) : 'دليل المدارس الذكي — SchoPlus') ?>">
    <meta name="twitter:description" content="<?= isset($ogDesc) ? e($ogDesc) : 'دليلك الشامل لاختيار أفضل المدارس في السودان' ?>">
    <meta name="twitter:image" content="<?= isset($ogImage) ? e($ogImage) : 'https://daleel.schoplus.sd/logo.png' ?>">

    <!-- Schema.org Structured Data (JSON-LD) for Rich Google Snippets -->
    <?php
    $schemaTargetSchool = $school ?? ($currSchool ?? null);
    if (!empty($schemaTargetSchool) && is_array($schemaTargetSchool)) {
        $daleelSchema = [
            "@context" => "https://schema.org",
            "@type" => "School",
            "name" => $schemaTargetSchool['name'] ?? '',
            "alternateName" => $schemaTargetSchool['name_en'] ?? '',
            "url" => "https://daleel.schoplus.sd/school/" . ($schemaTargetSchool['slug'] ?? $schemaTargetSchool['id']),
            "description" => $schemaTargetSchool['description'] ?? 'الملف التعريفي للمدرسة في دليل المدارس السودانية',
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => $schemaTargetSchool['city'] ?? '',
                "addressRegion" => $schemaTargetSchool['state'] ?? '',
                "addressCountry" => "SD"
            ]
        ];
        if (!empty($schemaTargetSchool['logo'])) {
            $daleelSchema["image"] = 'https://daleel.schoplus.sd' . assetUrl($schemaTargetSchool['logo']);
        }
        if (!empty($schemaTargetSchool['phone'])) {
            $daleelSchema["telephone"] = $schemaTargetSchool['phone'];
        }
    } else {
        $daleelSchema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "WebSite",
                    "@id" => "https://daleel.schoplus.sd/#website",
                    "url" => "https://daleel.schoplus.sd",
                    "name" => "دليل المدارس السودانية — SchoPlus",
                    "description" => "دليلك الشامل لاختيار أفضل المدارس الخاصة والنموذجية في السودان",
                    "inLanguage" => "ar",
                    "potentialAction" => [
                        "@type" => "SearchAction",
                        "target" => "https://daleel.schoplus.sd/?q={search_term_string}",
                        "query-input" => "required name=search_term_string"
                    ]
                ],
                [
                    "@type" => "EducationalOrganization",
                    "@id" => "https://daleel.schoplus.sd/#organization",
                    "name" => "دليل المدارس السودانية الذكي",
                    "url" => "https://daleel.schoplus.sd",
                    "logo" => "https://daleel.schoplus.sd/logo.png"
                ]
            ]
        ];
    }
    ?>
    <script type="application/ld+json">
    <?= json_encode($daleelSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>

    <!-- Cairo Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">

    <!-- PWA & Mobile Web App Meta -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0B2A52">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="دليل المدارس">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <!-- Quill Rich Text Editor (Word-like WYSIWYG) -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <style>
        :root {
            /* Luxurious Warm Cream & Crisp White Identity */
            --bg-body: #FAF8F5;
            --bg-surface: #FFFFFF;
            --bg-surface-soft: #F5EFE6;
            --bg-surface-tint: #FDFBF7;
            
            --border-subtle: #EAE3D8;
            --border-focus: #D4AF37;
            
            --text-main: #1A202C;
            --text-body: #4A5568;
            --text-muted: #7A8699;
            --text-gold: #92400E;
            
            --brand-navy: #0B2A52;
            --brand-blue: #1E5FA8;
            --brand-blue-hover: #164985;
            --brand-gold: #D97706;
            --brand-gold-light: #FEF3C7;
            --brand-gold-border: #FCD34D;
            --brand-emerald: #059669;
            
            --shadow-card: 0 4px 20px -2px rgba(45, 35, 20, 0.05), 0 2px 6px -1px rgba(45, 35, 20, 0.03);
            --shadow-hover: 0 12px 30px -4px rgba(45, 35, 20, 0.09), 0 4px 10px -2px rgba(45, 35, 20, 0.04);
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 8px;
        }

        html {
            font-size: 13.5px !important;
            -webkit-text-size-adjust: 100%;
            overflow-x: clip !important;
            max-width: 100%;
        }

        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-body);
            font-size: 0.75rem;
            line-height: 1.6;
            overflow-x: clip !important;
            max-width: 100%;
            width: 100%;
            position: relative;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        main, header, footer, section {
            max-width: 100%;
        }

        p {
            font-size: 0.75rem;
            line-height: 1.6;
            color: var(--text-body);
            margin-bottom: 0.65rem;
        }

        .lead {
            font-size: 0.8rem !important;
            line-height: 1.6 !important;
        }

        .small, small {
            font-size: 0.68rem !important;
        }

        .form-control, .form-select {
            font-size: 0.75rem !important;
            padding: 6px 10px !important;
        }

        .form-label {
            font-size: 0.72rem !important;
            font-weight: 600;
            margin-bottom: 4px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            color: var(--brand-navy);
            letter-spacing: -0.2px;
        }

        .ambient-cream-glow {
            display: none !important;
        }
        @media (max-width: 767px) {
            .ambient-cream-glow {
                display: none !important;
            }
        }

        /* Pure Mobile App Shell Isolation: Hide Web Nav and Footers */
        body.is-daleel-app .daleel-topbar,
        body.is-daleel-app .daleel-topbar-mobile,
        body.is-daleel-app .daleel-navbar,
        body.is-daleel-app .daleel-footer,
        body.is-daleel-app footer {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Top Bar & Navbar */
        .daleel-topbar {
            background-color: #F4ECE1;
            font-size: 0.8rem;
            color: #6B7280;
            border-bottom: 1px solid #EBE3D7;
            padding: 5px 0;
        }

        .daleel-navbar {
            background-color: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(226, 215, 200, 0.85);
            padding: 10px 0;
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1040 !important;
            box-shadow: 0 4px 18px rgba(11, 42, 82, 0.05);
            transition: all 0.25s ease;
        }

        .daleel-navbar.scrolled {
            background-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 8px 24px rgba(11, 42, 82, 0.1);
            padding: 7px 0;
            border-bottom-color: #DCCFBF;
        }

        .daleel-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-main);
        }

        .daleel-logo-img {
            height: 44px;
            width: auto;
            object-fit: contain;
            display: block;
            transition: transform 0.2s ease;
        }

        .daleel-brand:hover .daleel-logo-img {
            transform: scale(1.02);
        }

        @media (max-width: 767px) {
            .daleel-logo-img {
                height: 36px;
            }
        }

        .daleel-nav-island {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #F5ECE0;
            border: 1px solid #E4D8C7;
            padding: 4px 6px;
            border-radius: 999px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .nav-link-custom {
            color: #4A5568;
            font-weight: 700;
            font-size: 0.77rem;
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid transparent;
        }

        .nav-link-custom:hover {
            color: var(--brand-navy);
            background-color: #FFFFFF;
            border-color: #E2D7C8;
            box-shadow: 0 2px 8px rgba(11, 42, 82, 0.06);
            transform: translateY(-1px);
        }

        .nav-link-custom.active {
            color: var(--brand-navy);
            background-color: #FFFFFF;
            border-color: #D9CBB7;
            box-shadow: 0 2px 8px rgba(11, 42, 82, 0.08);
        }

        /* Rank Badges */
        .badge-directory-rank {
            background: #0B2A52;
            color: #F5A020 !important;
            border: 1px solid #1E5FA8;
            padding: 3px 9px;
            border-radius: 8px;
            font-size: 0.68rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 3px 8px rgba(11, 42, 82, 0.25);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .badge-rank-tag {
            background: #F4ECE1;
            color: var(--brand-navy);
            border: 1px solid #E2D7C8;
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .badge-rank-tag::before {
            content: "ترتيب ";
            font-size: 0.62rem;
            font-weight: 600;
            color: #6B7280;
        }

        .badge-established {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        /* Buttons & CTAs */
        .btn-luxury-primary {
            background: #1E5FA8;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 6px 14px;
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(30, 95, 168, 0.2);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }

        .btn-luxury-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(30, 95, 168, 0.28);
            background: #0B2A52;
        }

        .btn-luxury-outline {
            background: #FFFFFF;
            color: var(--brand-navy) !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 6px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border-subtle);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }

        .btn-luxury-outline:hover {
            border-color: var(--brand-blue);
            color: var(--brand-blue) !important;
            background: var(--bg-surface-tint);
            transform: translateY(-1px);
        }

        .btn-luxury-gold {
            background: #F5A020;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 6px 14px;
            border-radius: 10px;
            border: none;
            box-shadow: 0 3px 10px rgba(245, 160, 32, 0.22);
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-luxury-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(245, 160, 32, 0.35);
            background: #D97706;
        }

        /* Cards & Styling */
        .luxury-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            position: relative;
        }

        .luxury-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
            border-color: #DFD5C7;
        }

        /* Badges */
        .badge-schoplus-smart {
            background: #FFFDF5;
            color: #0B2A52;
            border: 1px solid #F5A020;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-verified {
            background: #F0F4F8;
            color: #1E5FA8;
            border: 1px solid #DDE4ED;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-stage-pill {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .star-rating {
            color: #F5A020;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        /* ======= DESIGN FIXES & IMPROVEMENTS v3.5.36 ======= */

        /* Fix 1: badge-schoplus was undefined — now defined */
        .badge-schoplus {
            background: #0B2A52;
            color: #F5A020;
            border: 1px solid #1E5FA8;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Fix 2: Hero title — compact and crisp */
        .daleel-hero h1 {
            font-size: clamp(1.15rem, 2.5vw, 1.55rem) !important;
            font-weight: 800 !important;
            line-height: 1.35 !important;
            letter-spacing: -0.3px !important;
        }

        /* Fix 3: School card description — clean 2-line clamp */
        .school-card-desc {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.6em;
            line-height: 1.55;
            font-size: 0.74rem;
        }

        /* Fix 4: Cover placeholder for schools without cover photo */
        .cover-placeholder {
            background: #0B2A52 !important;
        }
        .cover-placeholder-icon {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: rgba(255,255,255,0.12);
            pointer-events: none;
        }

        /* Fix 5: Gallery image hover zoom */
        .gallery-thumb img {
            transition: transform 0.35s ease;
        }
        .gallery-thumb:hover img {
            transform: scale(1.06);
        }

        /* Fix 6: Skeleton loading */
        .skeleton {
            background: #F0F4F8;
            border-radius: 8px;
        }

        /* Fix 7: Nav pills — brand-consistent active state */
        .nav-pills .nav-link.active {
            background-color: var(--brand-navy) !important;
            color: #ffffff !important;
        }
        .nav-pills .nav-link {
            color: var(--text-body);
            font-weight: 700;
            font-size: 0.88rem;
            transition: all 0.2s ease;
        }
        .nav-pills .nav-link:not(.active):hover {
            background-color: var(--bg-surface-soft);
            color: var(--brand-navy);
        }

        /* Fix 8: Fade-up scroll reveal */
        .fade-up {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity 0.55s ease, transform 0.55s ease;
        }
        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Fix 9: Mobile hamburger button */
        .mobile-nav-toggle {
            display: none;
            background: #ffffff;
            border: 1.5px solid var(--border-subtle);
            border-radius: 10px;
            padding: 7px 13px;
            color: var(--text-main);
            cursor: pointer;
            font-weight: 700;
            font-size: 0.82rem;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .mobile-nav-toggle:hover {
            border-color: var(--brand-blue);
            color: var(--brand-blue);
        }
        @media (max-width: 991px) {
            .mobile-nav-toggle { display: flex; }
        }

        /* Fix 10: Mobile topbar (shown only on small screens) */
        .daleel-topbar-mobile {
            display: none;
            background-color: #F4ECE1;
            font-size: 0.78rem;
            color: #6B7280;
            border-bottom: 1px solid #EBE3D7;
            padding: 6px 16px;
            gap: 14px;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 767px) {
            .daleel-topbar-mobile { display: flex !important; }
            .daleel-topbar { display: none !important; }
        }

        /* Fix 11: Offcanvas nav link style */
        .offcanvas-nav-link {
            color: var(--text-body);
            font-weight: 600;
            font-size: 0.93rem;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .offcanvas-nav-link:hover {
            background-color: var(--bg-surface-soft);
            color: var(--brand-blue);
        }
    </style>

</head>
<body class="<?= $isDaleelMobile ? 'is-daleel-app' : '' ?>">
    <?php if (!$isDaleelMobile): ?>
    <!-- Mobile Topbar (visible only on small screens) -->
    <div class="daleel-topbar-mobile">
        <span class="text-muted"><i class="fas fa-book-reader text-warning me-1"></i> خدمة رقمية من منظومة ايسكو بلص</span>
        <span>•</span>
        <a href="/register.php" class="text-decoration-none fw-bold d-flex align-items-center gap-1" style="color: var(--brand-gold);">
            <i class="fas fa-plus-circle"></i> سجّل مدرستك
        </a>
    </div>

    <!-- Mobile Offcanvas Navigation -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNavOffcanvas" aria-labelledby="mobileNavLabel">
        <div class="offcanvas-header border-bottom" style="background: var(--bg-surface-tint);">
            <div class="d-flex align-items-center gap-2" id="mobileNavLabel">
                <img src="/assets/images/logo.png" alt="دليل المدارس السودانية" style="height: 36px; width: auto; object-fit: contain;">
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-3">
            <nav class="d-flex flex-column gap-1 mb-4">
                <a href="/" class="offcanvas-nav-link">
                    <i class="fas fa-home text-muted" style="width:18px"></i> الرئيسية
                </a>
                <a href="/#schoplus-schools" class="offcanvas-nav-link">
                    <i class="fas fa-star text-warning" style="width:18px"></i> المدارس الذكية
                </a>
                <a href="/#top-rated" class="offcanvas-nav-link">
                    <i class="fas fa-award text-primary" style="width:18px"></i> الأعلى تقييماً
                </a>
                <a href="/#search-section" class="offcanvas-nav-link">
                    <i class="fas fa-search text-muted" style="width:18px"></i> استكشف المدارس
                </a>
                <a href="/app.php" class="offcanvas-nav-link">
                    <i class="fab fa-android text-success" style="width:18px"></i> تطبيق أندرويد (APK)
                </a>
                <a href="https://schoplus.sd" target="_blank" class="offcanvas-nav-link">
                    <i class="fas fa-info-circle text-muted" style="width:18px"></i> عن النظام
                </a>
            </nav>
            <div class="border-top pt-3 d-flex flex-column gap-2">
                <?php if ($currSchool): ?>
                    <a href="/dashboard.php" class="btn-luxury-primary justify-content-center">
                        <i class="fas fa-columns"></i> لوحة إدارة المدرسة
                    </a>
                    <a href="/logout.php" class="btn btn-sm btn-light border text-danger rounded-3 py-2">
                        <i class="fas fa-sign-out-alt me-1"></i> تسجيل الخروج
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn-luxury-outline justify-content-center">
                        <i class="fas fa-user-tie text-secondary"></i> دخول أصحاب المدارس
                    </a>
                    <a href="/register.php" class="btn-luxury-primary justify-content-center mt-1">
                        <i class="fas fa-plus-circle"></i> سجّل مدرستك الآن
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ambient-cream-glow" style="top: -200px; right: -200px;"></div>
    <div class="ambient-cream-glow" style="top: 800px; left: -250px;"></div>


    <!-- Top Announcement Bar -->
    <div class="daleel-topbar d-none d-md-block">
        <div class="container d-flex justify-content-center align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span><i class="fas fa-book-reader text-warning me-1"></i> دليل المدارس السودانية — خدمة رقمية من منظومة ايسكو بلص (SchoPlus)</span>
                <span>•</span>
                <span class="text-success"><i class="fas fa-check-circle me-1"></i> تصفح المدارس وتواصل مع إداراتها مباشرة</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="daleel-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            
            <a href="/" class="daleel-brand" title="دليل المدارس السودانية — SchoPlus">
                <img src="/assets/images/logo.png" alt="دليل المدارس السودانية" class="daleel-logo-img">
            </a>

            <nav class="d-none d-lg-flex align-items-center">
                <div class="daleel-nav-island">
                    <a href="/" class="nav-link-custom"><i class="fas fa-home text-muted"></i> الرئيسية</a>
                    <a href="/#schoplus-schools" class="nav-link-custom"><i class="fas fa-bolt text-warning"></i> المدارس الذكية</a>
                    <a href="/#top-rated" class="nav-link-custom"><i class="fas fa-award text-primary"></i> الأعلى تقييماً</a>
                    <a href="/#search-section" class="nav-link-custom"><i class="fas fa-search text-muted"></i> استكشف المدارس</a>
                    <a href="https://schoplus.sd" target="_blank" class="nav-link-custom"><i class="fas fa-globe text-info"></i> عن المنظومة</a>
                </div>
            </nav>

            <div class="d-flex align-items-center gap-2">
                <!-- Hamburger (mobile only) -->
                <button class="mobile-nav-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNavOffcanvas" aria-controls="mobileNavOffcanvas">
                    <i class="fas fa-bars"></i>
                    <span>القائمة</span>
                </button>
                <!-- Desktop CTA buttons -->
                <?php if ($currSchool): ?>
                    <a href="/dashboard.php" class="btn-luxury-outline d-none d-lg-inline-flex" style="border-radius: 999px; padding: 7px 16px;">
                        <i class="fas fa-columns text-primary"></i>
                        <span>لوحة إدارة المدرسة</span>
                    </a>
                    <a href="/logout.php" class="btn btn-sm btn-light border text-danger d-none d-lg-inline-flex rounded-circle" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn-luxury-outline d-none d-lg-inline-flex" style="border-radius: 999px; padding: 7px 16px;">
                        <i class="fas fa-user-tie text-secondary"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                    <a href="/register.php" class="btn-luxury-primary d-none d-lg-inline-flex" style="border-radius: 999px; padding: 7px 18px;">
                        <i class="fas fa-plus-circle"></i>
                        <span>سجّل مدرستك الآن</span>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </header>
    <?php endif; ?>

    <main>
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="container mt-3">
                <div class="alert alert-info border-0 shadow-sm alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-1"></i> <?= e($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>
