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

    <!-- Google tag (gtag.js) - Google Analytics 4 (Sudanese Schools Directory) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JX5XJEGH5H"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-JX5XJEGH5H');

      // Auto-track key conversion events in directory
      document.addEventListener('click', function(e) {
        var target = e.target.closest('a, button');
        if (!target) return;

        var href = target.getAttribute('href') || '';

        // 1. WhatsApp to Schools / Directory
        if (href.indexOf('wa.me') !== -1 || href.indexOf('whatsapp.com') !== -1) {
          gtag('event', 'contact', {
            'method': 'WhatsApp',
            'event_category': 'School Inquiry'
          });
        }

        // 2. Direct Phone Calls to Schools
        if (href.indexOf('tel:') !== -1) {
          gtag('event', 'contact', {
            'method': 'Phone Call',
            'event_category': 'School Inquiry'
          });
        }

        // 3. New School Registration Click
        if (href.indexOf('register') !== -1) {
          gtag('event', 'sign_up_start', {
            'event_category': 'School Onboarding'
          });
        }
      }, true);
    </script>

    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v=3.7.84">
    <link rel="apple-touch-icon" href="/assets/images/logo-icon.png?v=3.7.84">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | دليل المدارس السودانية' : 'دليل المدارس السودانية — دليلك الشامل لاختيار أفضل المدارس في السودان' ?></title>
    
    <!-- Meta Description (SEO) -->
    <meta name="description" content="<?= isset($pageDescription) ? e($pageDescription) : (isset($ogDesc) && !empty($ogDesc) && $ogDesc !== 'دليلك الشامل لاختيار أفضل المدارس في السودان — تصفح الرسوم والمرافق وتواصل مباشرة.' ? e($ogDesc) : 'دليل المدارس السودانية — المنصة الوطنية الأولى والذكية الشاملة لاستكشاف ومقارنة المدارس ورياض الأطفال والمراكز التعليمية في ولايات السودان ودول المهجر.') ?>">
    
    <!-- Preload Hero Image for faster LCP on Directory Home -->
    <?php if (empty($schemaTargetSchool) && (empty($_SERVER['REQUEST_URI']) || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/index.php')): ?>
    <link rel="preload" as="image" href="/assets/images/family-hero.webp" fetchpriority="high">
    <?php endif; ?>
    
    <!-- Open Graph / WhatsApp / Facebook Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= isset($ogTitle) ? e($ogTitle) : (isset($pageTitle) ? e($pageTitle) . ' | دليل المدارس السودانية' : 'دليل المدارس السودانية — منصة الاستكشاف والقبول الذكي') ?>">
    <meta property="og:description" content="<?= isset($ogDesc) ? e($ogDesc) : 'دليلك الشامل لاختيار أفضل المدارس في السودان — تصفح الرسوم والمرافق وتواصل مباشرة.' ?>">
    <meta property="og:image" content="<?= isset($ogImage) ? e($ogImage) : 'https://daleel.schoplus.sd/assets/images/logo.png?v=3.7.84' ?>">
    <meta property="og:url" content="<?= isset($ogUrl) ? e($ogUrl) : 'https://daleel.schoplus.sd' ?>">
    <meta property="og:site_name" content="دليل المدارس السودانية">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($ogTitle) ? e($ogTitle) : (isset($pageTitle) ? e($pageTitle) : 'دليل المدارس السودانية — منصة الاستكشاف والقبول الذكي') ?>">
    <meta name="twitter:description" content="<?= isset($ogDesc) ? e($ogDesc) : 'دليلك الشامل لاختيار أفضل المدارس في السودان' ?>">
    <meta name="twitter:image" content="<?= isset($ogImage) ? e($ogImage) : 'https://daleel.schoplus.sd/assets/images/logo.png?v=3.7.84' ?>">

    <!-- Geo Local SEO Targeting (Sudan) -->
    <meta name="geo.region" content="SD">
    <meta name="geo.placename" content="Khartoum">
    <meta name="geo.position" content="15.5007;32.5599">
    <meta name="ICBM" content="15.5007, 32.5599">

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
        if (!empty($schemaTargetSchool['phone']) && (!empty($schemaTargetSchool['is_verified']) || !empty($schemaTargetSchool['is_schoplus_client']))) {
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Tajawal:wght@700;800;900&display=swap" rel="stylesheet">

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
    <link rel="apple-touch-icon" href="/assets/images/logo-icon.png?v=3.7.84">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/logo-icon.png?v=3.7.84">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/images/logo-icon.png?v=3.7.84">
    <link rel="apple-touch-icon" sizes="167x167" href="/assets/images/logo-icon.png?v=3.7.84">

    <!-- Quill Rich Text Editor (Word-like WYSIWYG) -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <style>
        :root {
            /* SchoPlus Ocean Brand Design System (Style F v2) */
            --bg-body: #F0F4F8;
            --bg-surface: #FFFFFF;
            --bg-surface-soft: #F8FAFC;
            --bg-surface-tint: #FFFFFF;
            
            --border-subtle: #DDE4ED;
            --border-focus: #1E5FA8;
            
            --text-main: #0D1B2E;
            --text-body: #3D5068;
            --text-muted: #7A93AD;
            
            --brand-navy: #0B2A52;
            --brand-blue: #1E5FA8;
            --brand-blue-hover: #164985;
            --brand-orange: #F5A020;
            --brand-orange-hover: #D97706;
            --brand-gold: #F5A020;
            --brand-gold-light: #FEF3C7;
            --brand-gold-border: #FCD34D;
            --brand-emerald: #059669;
            
            --shadow-card: 0 4px 18px rgba(11, 42, 82, 0.05);
            --shadow-hover: 0 12px 30px rgba(11, 42, 82, 0.1);
            --radius-lg: 16px;
            --radius-md: 12px;
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
            background-color: #0B2A52;
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.82);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 0;
        }

        .daleel-navbar {
            background-color: #FFFFFF;
            border-bottom: 1px solid #DDE4ED;
            padding: 10px 0;
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1040 !important;
            box-shadow: 0 2px 10px rgba(11, 42, 82, 0.04);
            transition: all 0.25s ease;
        }

        .daleel-navbar.scrolled {
            box-shadow: 0 6px 20px rgba(11, 42, 82, 0.08);
            padding: 8px 0;
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
                height: 38px;
            }
        }

        .daleel-nav-island {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #F0F4F8;
            border: 1px solid #DDE4ED;
            padding: 3px 6px;
            border-radius: 999px;
        }

        .nav-link-custom {
            color: #475569;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .nav-link-custom:hover {
            color: var(--brand-blue);
            background-color: #FFFFFF;
            border-color: #DDE4ED;
        }

        .nav-link-custom.active {
            color: var(--brand-navy);
            background-color: #FFFFFF;
            border-color: #DDE4ED;
            box-shadow: 0 2px 6px rgba(11, 42, 82, 0.06);
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
            background: #0B2A52;
            color: #F5A020;
            border: 1px solid #1E5FA8;
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
            content: "الدليل ";
            font-size: 0.62rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
        }

        .badge-state-rank-tag {
            background: #EFF6FF;
            color: #1E5FA8;
            border: 1px solid #BFDBFE;
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
        .badge-state-rank-tag::before {
            content: "الولاية ";
            font-size: 0.62rem;
            font-weight: 600;
            color: #1E5FA8;
        }

        .badge-established {
            background: #EFF6FF;
            color: #1E5FA8;
            border: 1px solid #DBEAFE;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        /* Buttons & CTAs (SchoPlus Style F v2) */
        .btn-luxury-primary {
            background: #F5A020;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 7px 16px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(245, 160, 32, 0.25);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-luxury-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(245, 160, 32, 0.35);
            background: #D97706;
            color: #FFFFFF !important;
        }

        .btn-luxury-navy {
            background: #0B2A52;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 7px 16px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(11, 42, 82, 0.2);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-luxury-navy:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(11, 42, 82, 0.3);
            background: #163870;
            color: #FFFFFF !important;
        }

        .btn-luxury-outline {
            background: #FFFFFF;
            color: #0B2A52 !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #DDE4ED;
            box-shadow: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-luxury-outline:hover {
            border-color: #1E5FA8;
            color: #1E5FA8 !important;
            background: #F0F4F8;
            transform: translateY(-1px);
        }

        .btn-luxury-gold {
            background: #F5A020;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 7px 16px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(245, 160, 32, 0.25);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
        }

        .btn-luxury-gold:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(245, 160, 32, 0.35);
            background: #D97706;
            color: #FFFFFF !important;
        }

        /* Cards & Styling (SchoPlus Style F v2) */
        .luxury-card {
            background-color: #FFFFFF;
            border: 1px solid #DDE4ED;
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(11, 42, 82, 0.05);
            transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            position: relative;
        }

        .luxury-card:hover {
            box-shadow: 0 14px 34px rgba(11, 42, 82, 0.12);
            transform: translateY(-4px);
            border-color: #CBD5E1;
        }

        .card-cover-wrap {
            height: 138px;
            background-size: cover;
            background-position: center;
            position: relative;
            background-color: #0B2A52;
            overflow: hidden;
        }

        .card-cover-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(11,42,82,0.2) 0%, rgba(11,42,82,0.7) 100%);
            pointer-events: none;
        }

        .card-cover-topbar {
            position: absolute;
            top: 9px;
            left: 10px;
            right: 10px;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            pointer-events: none;
        }

        .card-cover-topbar-badges {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            pointer-events: auto;
        }

        .cover-placeholder-mesh {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0B2A52 0%, #163870 50%, #1E5FA8 100%);
            overflow: hidden;
        }

        .luxury-card-historic .cover-placeholder-mesh {
            background: linear-gradient(135deg, #451A03 0%, #78350F 50%, #B45309 100%);
        }

        .cover-placeholder-mesh i {
            font-size: 4.5rem;
            color: rgba(255, 255, 255, 0.08);
            transform: rotate(-10deg);
        }

        .card-logo-badge {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: #FFFFFF;
            padding: 3px;
            box-shadow: 0 6px 18px rgba(11,42,82,0.16);
            border: 2.5px solid #FFFFFF;
            position: absolute;
            top: 108px;
            right: 16px;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-logo-badge img {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            object-fit: contain;
        }

        .clickable-school-card {
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .clickable-school-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 34px rgba(11, 42, 82, 0.12);
            border-color: #CBD5E1;
        }

        .clickable-school-card:hover .luxury-card-title,
        .clickable-school-card:hover .luxury-row-title {
            color: #1E5FA8 !important;
        }

        .luxury-card-body {
            padding: 10px 16px 14px 16px;
        }

        .luxury-card-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0D1B2E;
            line-height: 1.35;
            transition: color 0.18s ease;
        }

        .luxury-card-subtitle {
            font-size: 0.7rem;
            color: #7A93AD;
            font-weight: 600;
            margin-top: 1px;
        }

        .luxury-card-location {
            font-size: 0.72rem;
        }

        .school-card-desc {
            font-size: 0.73rem;
            line-height: 1.5;
            color: #475569;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.2rem;
            max-height: 2.2rem;
        }

        .luxury-card-footer {
            padding-top: 10px;
            border-top: 1px solid #EDF2F7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
        }

        /* Unified Card Capsule Badges */
        .badge-card-pill {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            line-height: 1.25;
            white-space: nowrap;
        }

        .badge-pill-rank {
            background: rgba(11, 42, 82, 0.90);
            color: #F5A020 !important;
            border: 1px solid rgba(245, 160, 32, 0.45);
            box-shadow: 0 2px 6px rgba(11, 42, 82, 0.25);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            pointer-events: auto;
        }

        .badge-pill-historic {
            background: linear-gradient(135deg, #78350F 0%, #B45309 60%, #D97706 100%) !important;
            color: #FEF3C7 !important;
            border: 1px solid #FCD34D !important;
            box-shadow: 0 2px 6px rgba(120, 53, 15, 0.3) !important;
        }

        .badge-pill-smart {
            background: #FFFFFF;
            color: #0B2A52;
            border: 1.5px solid #1E5FA8;
            box-shadow: 0 2px 6px rgba(30, 95, 168, 0.15);
            font-weight: 800;
        }

        .badge-pill-verified {
            background: rgba(16, 185, 129, 0.92);
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .badge-pill-public {
            background: rgba(100, 116, 139, 0.85);
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        /* Quick Action Circle Icon Buttons */
        .btn-card-icon-wa {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ECFDF5;
            color: #059669 !important;
            border: 1px solid #A7F3D0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none !important;
            transition: all 0.2s ease;
        }
        .btn-card-icon-wa:hover {
            background: #059669;
            color: #FFFFFF !important;
            border-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.28);
        }

        .btn-card-icon-phone {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #EFF6FF;
            color: #1E5FA8 !important;
            border: 1px solid #BFDBFE;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            text-decoration: none !important;
            transition: all 0.2s ease;
        }
        .btn-card-icon-phone:hover {
            background: #1E5FA8;
            color: #FFFFFF !important;
            border-color: #1E5FA8;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(30, 95, 168, 0.28);
        }

        /* Primary Action Buttons */
        .btn-luxury-action {
            background: #FFFFFF;
            color: #0B2A52 !important;
            border: 1px solid #DDE4ED;
            font-weight: 700;
            font-size: 0.73rem;
            padding: 5px 12px;
            border-radius: 8px;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }
        .btn-luxury-action:hover {
            background: #0B2A52;
            color: #FFFFFF !important;
            border-color: #0B2A52;
            box-shadow: 0 4px 14px rgba(11, 42, 82, 0.2);
            transform: translateY(-1px);
        }
        .btn-luxury-action i {
            font-size: 0.68rem;
            transition: transform 0.2s ease;
        }
        .btn-luxury-action:hover i {
            transform: translateX(-3px);
        }

        /* ============================================================
           HISTORIC / HERITAGE SCHOOL CARDS (SchoPlus Style F v2 Luxury Heritage)
           ============================================================ */
        .luxury-card-historic {
            background: linear-gradient(180deg, #FFFDF8 0%, #FFFFFF 100%) !important;
            border: 1.5px solid #F5A020 !important;
            box-shadow: 0 4px 22px rgba(245, 160, 32, 0.12), 0 2px 8px rgba(11, 42, 82, 0.04) !important;
            position: relative;
            overflow: hidden;
        }

        .luxury-card-historic:hover {
            border-color: #D97706 !important;
            box-shadow: 0 16px 36px rgba(217, 119, 6, 0.22), 0 4px 12px rgba(11, 42, 82, 0.08) !important;
            transform: translateY(-5px) !important;
        }

        /* 1. Crown Bar pinned to top edge */
        .historic-card-crown-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4.5px;
            background: linear-gradient(90deg, #92400E 0%, #D97706 20%, #F5A020 50%, #FCD34D 80%, #92400E 100%);
            z-index: 10;
            box-shadow: 0 1px 4px rgba(180, 83, 9, 0.35);
        }

        /* 2. Watermark */
        .historic-card-watermark {
            position: absolute;
            bottom: -6px;
            left: -4px;
            font-size: 5.5rem;
            color: #B45309;
            opacity: 0.045;
            pointer-events: none;
            z-index: 0;
            transform: rotate(-10deg);
        }

        /* 3. Heritage Ribbon Badge on Cover */
        .historic-ribbon-badge {
            background: linear-gradient(135deg, #78350F 0%, #B45309 60%, #D97706 100%) !important;
            color: #FEF3C7 !important;
            border: 1px solid #FCD34D !important;
            font-size: 0.68rem !important;
            padding: 3px 10px !important;
            border-radius: 999px !important;
            font-weight: 800 !important;
            box-shadow: 0 3px 10px rgba(120, 53, 15, 0.4) !important;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .historic-ribbon-badge i {
            color: #FCD34D !important;
        }
        .historic-ribbon-year {
            font-size: 0.64rem;
            opacity: 0.95;
            color: #FFFBEB;
        }

        /* 4. Golden Logo Badge & Seal */
        .luxury-card-historic .card-logo-badge {
            border: 2px solid #F5A020 !important;
            box-shadow: 0 4px 14px rgba(245, 160, 32, 0.28) !important;
        }
        .historic-logo-seal {
            position: absolute;
            bottom: -4px;
            left: -4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #B45309 0%, #F5A020 100%);
            color: #FFFFFF;
            font-size: 0.58rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(120, 53, 15, 0.35);
            border: 1.5px solid #FFFFFF;
            z-index: 3;
        }

        /* 5. Historic Unique Description Styling */
        .historic-card-desc {
            color: #1E293B !important;
            font-size: 0.74rem !important;
            line-height: 1.55 !important;
            border-right: 3px solid #F5A020;
            padding-right: 8px;
            background: rgba(254, 243, 199, 0.4);
            border-radius: 4px;
            padding-top: 5px;
            padding-bottom: 5px;
            margin-bottom: 10px !important;
        }

        /* 6. Historic Action Button */
        .btn-historic-action {
            background: linear-gradient(135deg, #B45309 0%, #D97706 100%) !important;
            color: #FFFFFF !important;
            border: 1px solid #F5A020 !important;
            font-weight: 700 !important;
            font-size: 0.73rem !important;
            padding: 5px 12px;
            box-shadow: 0 2px 8px rgba(180, 83, 9, 0.22) !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            text-decoration: none !important;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-historic-action:hover {
            background: linear-gradient(135deg, #92400E 0%, #B45309 100%) !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 14px rgba(180, 83, 9, 0.35) !important;
            transform: translateY(-1px);
        }
        .btn-historic-action i {
            font-size: 0.68rem;
            transition: transform 0.2s ease;
        }
        .btn-historic-action:hover i {
            transform: translateX(-3px);
        }

        /* Badges */
        .badge-schoplus-smart {
            background: #FFFFFF;
            color: #0B2A52;
            border: 1.5px solid #1E5FA8;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-schoplus-smart i {
            color: #1E5FA8;
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
            color: #334155;
            border: 1px solid #E2E8F0;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.69rem;
            font-weight: 700;
        }

        .star-rating {
            color: #F5A020;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        /* Mandatory Specification for Main Tabs (.sp-main-tabs) - Centered in screen */
        .sp-main-tabs-wrap {
            width: 100%;
            margin-bottom: 24px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .sp-main-tabs {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: #E2E8F0;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            margin-left: auto;
            margin-right: auto;
            flex-shrink: 0;
        }
        .sp-main-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.76rem;
            font-weight: 700;
            color: #475569;
            background: transparent;
            border: none;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .sp-main-tab:hover {
            color: #0B2A52;
            background: rgba(255, 255, 255, 0.6);
        }
        .sp-main-tab.active {
            background: #FFFFFF !important;
            color: #0B2A52 !important;
            box-shadow: 0 2px 8px rgba(11, 42, 82, 0.08);
        }
        .sp-main-tab i {
            color: #64748B;
            font-size: 0.8rem;
        }
        .sp-main-tab.active i {
            color: #F5A020 !important;
        }
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            background: #EDF2F7;
            color: #475569;
        }
        .sp-main-tab.active .tab-badge {
            background: #0B2A52;
            color: #FFFFFF;
        }

        /* Fix 1: badge-schoplus */
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
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
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
            background-color: #F8FAFC;
            font-size: 0.74rem;
            color: #1E293B;
            border-bottom: 1px solid #DDE4ED;
            padding: 7px 16px;
            gap: 14px;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        @media (max-width: 767px) {
            .daleel-topbar-mobile { display: flex !important; }
            .daleel-topbar { display: none !important; }
        }

        /* Fix 11: Offcanvas nav link style */
        .offcanvas-nav-link {
            color: var(--text-body);
            font-weight: 600;
            font-size: 0.88rem;
            padding: 9px 14px;
            border-radius: 8px;
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

        /* ===== SEARCHABLE SELECT COMPONENT (SchoPlus Style F v2) ===== */
        .sp-searchable-wrapper {
            position: relative;
            width: 100%;
        }
        .sp-searchable-trigger {
            width: 100%;
            min-height: 38px;
            padding: 7px 12px;
            background: #FFFFFF;
            border: 1px solid #DDE4ED;
            border-radius: 10px;
            font-size: 0.8rem;
            color: var(--brand-navy, #0B2A52);
            font-weight: 600;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .sp-searchable-trigger:hover,
        .sp-searchable-trigger:focus,
        .sp-searchable-wrapper.is-open .sp-searchable-trigger {
            border-color: var(--brand-blue, #1E5FA8);
            box-shadow: 0 0 0 3px rgba(30, 95, 168, 0.12);
            outline: none;
        }
        .sp-searchable-trigger-arrow {
            font-size: 0.72rem;
            color: #64748B;
            transition: transform 0.2s ease;
        }
        .sp-searchable-wrapper.is-open .sp-searchable-trigger-arrow {
            transform: rotate(180deg);
        }
        .sp-searchable-wrapper .sp-searchable-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            left: 0;
            background: #FFFFFF;
            border: 1px solid #DDE4ED;
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(11, 42, 82, 0.12);
            z-index: 1060;
            display: none;
            overflow: hidden;
            animation: spDropdownIn 0.15s ease-out;
        }
        @keyframes spDropdownIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .sp-searchable-wrapper.is-open .sp-searchable-dropdown {
            display: block;
        }
        .sp-searchable-header {
            padding: 8px 10px;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .sp-searchable-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .sp-searchable-input-wrap i {
            position: absolute;
            right: 10px;
            font-size: 0.72rem;
            color: #94A3B8;
            pointer-events: none;
        }
        .sp-searchable-input {
            width: 100%;
            height: 32px;
            padding: 5px 30px 5px 10px;
            font-size: 0.78rem;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            color: #0F172A;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .sp-searchable-input:focus {
            border-color: var(--brand-blue, #1E5FA8);
            box-shadow: 0 0 0 2px rgba(30, 95, 168, 0.15);
        }
        .sp-searchable-options {
            max-height: 220px;
            overflow-y: auto;
            padding: 4px;
            list-style: none;
            margin: 0;
        }
        .sp-searchable-options::-webkit-scrollbar {
            width: 5px;
        }
        .sp-searchable-options::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }
        .sp-searchable-option {
            padding: 7px 12px;
            font-size: 0.78rem;
            color: #334155;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.12s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sp-searchable-option:hover {
            background: #F1F5F9;
            color: var(--brand-navy, #0B2A52);
        }
        .sp-searchable-option.is-selected {
            background: #EFF6FF;
            color: var(--brand-blue, #1E5FA8);
            font-weight: 700;
        }
        .sp-searchable-option.is-hidden {
            display: none !important;
        }
        .sp-searchable-empty {
            padding: 16px 12px;
            text-align: center;
            font-size: 0.75rem;
            color: #94A3B8;
        }

        /* ===== FLOATING COMPARE BAR (SchoPlus Style F v2) ===== */
        .sp-floating-compare-bar {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            z-index: 1050;
            pointer-events: none;
            animation: spCompareBarSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes spCompareBarSlideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .sp-compare-bar-content {
            pointer-events: auto;
            background: #FFFFFF;
            border: 1.5px solid #0B2A52;
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(11, 42, 82, 0.18);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            max-width: 900px;
            margin: 0 auto;
        }
        .sp-compare-bar-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .sp-compare-bar-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0B2A52;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sp-compare-bar-title i {
            color: #F5A020;
        }
        .sp-compare-clear-btn {
            background: none;
            border: none;
            color: #64748B;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            padding: 2px 6px;
            text-decoration: underline;
        }
        .sp-compare-clear-btn:hover {
            color: #e11d48;
        }
        .sp-compare-thumbnails {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-grow: 1;
            overflow-x: auto;
            padding: 2px 0;
        }
        .sp-compare-thumb-item {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1.5px solid #DDE4ED;
            background: #F8FAFC;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sp-compare-thumb-item img {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            object-fit: cover;
        }
        .sp-compare-thumb-remove {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #0B2A52;
            color: #FFFFFF;
            font-size: 10px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .sp-compare-thumb-remove:hover {
            background: #e11d48;
        }
        .sp-compare-slot-empty {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1.5px dashed #CBD5E1;
            background: #F8FAFC;
            color: #94A3B8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        .sp-compare-actions {
            flex-shrink: 0;
        }
        .sp-compare-go-btn {
            padding: 8px 18px !important;
            font-size: 0.8rem !important;
            border-radius: 10px !important;
        }
        .btn-compare-card {
            background: #FFFFFF;
            border: 1px solid #DDE4ED;
            color: #475569;
            border-radius: 8px;
            padding: 4px 9px;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }
        .btn-compare-card:hover {
            border-color: #1E5FA8;
            color: #1E5FA8;
            background: #F0F4F8;
        }
        .btn-compare-card.is-active {
            background: #0B2A52;
            border-color: #0B2A52;
            color: #FFFFFF;
        }
        .btn-compare-card.is-active i {
            color: #F5A020;
        }
        @media (max-width: 767px) {
            .sp-compare-bar-content {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 10px 12px;
            }
            .sp-compare-bar-info {
                justify-content: space-between;
            }
            .sp-compare-actions .sp-compare-go-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Live AI Discovery & Verification Activity Bar (Style F v2 - White Brand Canvas) */
        .daleel-live-bar {
            background: #FFFFFF;
            border-bottom: 2px solid #F5A020;
            border-top: 1px solid #DDE4ED;
            color: #3D5068;
            font-size: 0.76rem;
            z-index: 1030;
            position: relative;
            box-shadow: 0 4px 18px rgba(11, 42, 82, 0.05);
            padding: 4px 0;
        }
        .daleel-live-bar .container {
            min-height: 44px;
        }
        .live-pulse-badge {
            display: inline-flex;
            align-items: center;
            background: #FFFBEB;
            border: 1px solid #FCD34D;
            color: #B45309;
            font-size: 0.66rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
            gap: 6px;
            line-height: 1.2;
            letter-spacing: 0.2px;
            transition: all 0.3s ease;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(245, 160, 32, 0.10);
        }
        .live-pulse-badge.badge-verified {
            background: #ECFDF5;
            border-color: #A7F3D0;
            color: #047857;
            box-shadow: 0 1px 3px rgba(16, 185, 129, 0.10);
        }
        .pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            animation: livePulseDot 1.6s infinite;
        }
        .live-pulse-badge:not(.badge-verified) .pulse-dot {
            background: #F5A020;
            box-shadow: 0 0 6px #F5A020;
        }
        .live-pulse-badge.badge-verified .pulse-dot {
            background: #10B981;
            box-shadow: 0 0 6px #10B981;
        }
        @keyframes livePulseDot {
            0% { transform: scale(0.85); opacity: 0.75; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.85); opacity: 0.75; }
        }
        .live-ticker-text {
            font-size: 0.76rem;
            color: #3D5068;
            line-height: 1.45;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .live-ticker-text strong {
            color: #0B2A52;
            font-weight: 800;
            letter-spacing: -0.1px;
        }
        .live-ticker-loc {
            color: #1E5FA8;
            font-size: 0.74rem;
            font-weight: 700;
        }
        .live-btn-visit {
            background: linear-gradient(135deg, #F5A020 0%, #E08E15 100%);
            color: #FFFFFF !important;
            font-size: 0.70rem;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 999px;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 8px rgba(245, 160, 32, 0.30);
            flex-shrink: 0;
        }
        .live-btn-visit:hover {
            background: linear-gradient(135deg, #FFB03A 0%, #F5A020 100%);
            color: #FFFFFF !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(245, 160, 32, 0.45);
        }
        @media (max-width: 576px) {
            .daleel-live-bar {
                font-size: 0.72rem;
                padding: 3px 0;
            }
            .daleel-live-bar .container {
                min-height: 38px;
                padding-left: 8px !important;
                padding-right: 8px !important;
            }
            .live-pulse-badge {
                font-size: 0.62rem;
                padding: 3px 8px;
                gap: 4px;
            }
            .live-pulse-badge i {
                font-size: 0.65rem;
            }
            .pulse-dot {
                width: 6px;
                height: 6px;
            }
            .live-ticker-text {
                font-size: 0.73rem;
            }
            .live-ticker-loc {
                font-size: 0.70rem;
            }
            .live-btn-visit {
                font-size: 0.66rem;
                padding: 4px 10px;
                gap: 4px;
                border-radius: 8px;
            }
        }
    </style>

</head>
<body class="<?= $isDaleelMobile ? 'is-daleel-app' : '' ?>">
    <?php if (!$isDaleelMobile): ?>
    <!-- Mobile Offcanvas Navigation -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNavOffcanvas" aria-labelledby="mobileNavLabel">
        <div class="offcanvas-header border-bottom" style="background: var(--bg-surface-tint);">
            <a href="/" class="d-flex align-items-center text-decoration-none" id="mobileNavLabel" title="دليل المدارس السودانية">
                <img src="/assets/images/logo.png?v=3.7.84" alt="دليل المدارس السودانية" style="height: 38px; width: auto; max-width: 220px; object-fit: contain;">
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-3">
            <nav class="d-flex flex-column gap-1 mb-4">
                <a href="/" class="offcanvas-nav-link">
                    <i class="fas fa-home text-muted" style="width:18px"></i> الرئيسية
                </a>
                <?php /* رابط المدارس الذكية (مخفي حالياً)
                <a href="/#schoplus-schools" class="offcanvas-nav-link">
                    <i class="fas fa-star text-warning" style="width:18px"></i> المدارس الذكية
                </a>
                */ ?>
                <a href="/#top-rated" class="offcanvas-nav-link">
                    <i class="fas fa-award text-primary" style="width:18px"></i> الأعلى تقييماً
                </a>
                <a href="/#search-section" class="offcanvas-nav-link">
                    <i class="fas fa-search text-muted" style="width:18px"></i> استكشف المدارس
                </a>
                <a href="/compare.php" class="offcanvas-nav-link">
                    <i class="fas fa-balance-scale text-warning" style="width:18px"></i> مقارنة المدارس
                </a>
                <a href="/presence-audit.php" class="offcanvas-nav-link">
                    <i class="fas fa-satellite-dish text-primary" style="width:18px"></i> فحص الوجود الرقمي
                    <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.62rem; padding: 2px 6px;">جديد</span>
                </a>
                <a href="/#app-download" class="offcanvas-nav-link">
                    <i class="fas fa-mobile-alt text-success" style="width:18px"></i> تطبيق الهاتف (أندرويد وآيفون)
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

    <!-- Main Navigation Header (Clean Minimalist: Brand + Actions) -->
    <header class="daleel-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            
            <a href="/" class="daleel-brand" title="دليل المدارس السودانية">
                <img src="/assets/images/logo.png?v=3.7.84" alt="دليل المدارس السودانية" class="daleel-logo-img">
            </a>

            <div class="d-flex align-items-center gap-2">
                <!-- Hamburger (mobile only) -->
                <button class="mobile-nav-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNavOffcanvas" aria-controls="mobileNavOffcanvas">
                    <i class="fas fa-bars"></i>
                    <span>القائمة</span>
                </button>
                <!-- CTA buttons -->
                <?php if ($currSchool): ?>
                    <a href="/dashboard.php" class="btn-luxury-outline" style="border-radius: 8px; padding: 7px 16px;">
                        <i class="fas fa-columns text-primary"></i>
                        <span>لوحة إدارة المدرسة</span>
                    </a>
                    <a href="/logout.php" class="btn btn-sm btn-light border text-danger rounded-circle" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="تسجيل الخروج">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn-luxury-outline d-none d-sm-inline-flex" style="border-radius: 8px; padding: 7px 16px;">
                        <i class="fas fa-user-tie text-secondary"></i>
                        <span>دخول أصحاب المدارس</span>
                    </a>
                    <a href="/register.php" class="btn-luxury-primary d-none d-md-inline-flex" style="border-radius: 8px; padding: 7px 18px;">
                        <i class="fas fa-plus-circle"></i>
                        <span>سجّل مدرستك الآن</span>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </header>

    <!-- Live AI Discovery & Verification Ticker Bar (Placed BELOW Header) -->
    <?php
    $daleelLiveRecent = [];
    try {
        $livePdo = function_exists('db') ? db() : ($pdo ?? null);
        if ($livePdo) {
            $liveQ = $livePdo->query("SELECT id, name, slug, state, city, country, school_type, stages, is_verified, created_at, updated_at 
                                      FROM daleel_schools 
                                      WHERE status = 'approved' 
                                      ORDER BY id DESC 
                                      LIMIT 50");
            if ($liveQ) {
                $daleelLiveRecent = $liveQ->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {}
    ?>
    <?php if (!empty($daleelLiveRecent)): ?>
    <div id="daleelLiveActivityBar" class="daleel-live-bar" style="display: none;">
        <div class="container d-flex align-items-center justify-content-between py-1 px-2 px-md-3">
            <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1" style="min-width: 0;">
                <span id="daleelLiveBadge" class="live-pulse-badge">
                    <span class="pulse-dot"></span>
                    <i id="daleelLiveBadgeIcon" class="fas fa-robot text-warning me-1"></i>
                    <span id="daleelLiveBadgeText" class="d-none d-sm-inline">استكشاف ذكي</span>
                    <span id="daleelLiveBadgeTextMobile" class="d-inline d-sm-none">استكشاف</span>
                </span>
                <div id="daleelLiveTickerText" class="live-ticker-text text-truncate">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="d-flex align-items-center ms-2 flex-shrink-0">
                <a id="daleelLiveActionLink" href="#" class="live-btn-visit">
                    <span class="d-none d-sm-inline">زيارة صفحة المدرسة</span>
                    <span class="d-inline d-sm-none">عرض</span>
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var rawLive = <?= json_encode($daleelLiveRecent, JSON_UNESCAPED_UNICODE) ?>;
        if (!rawLive || rawLive.length === 0) return;

        var bar = document.getElementById('daleelLiveActivityBar');
        var ticker = document.getElementById('daleelLiveTickerText');
        var link = document.getElementById('daleelLiveActionLink');
        var badge = document.getElementById('daleelLiveBadge');
        var badgeIcon = document.getElementById('daleelLiveBadgeIcon');
        var badgeText = document.getElementById('daleelLiveBadgeText');
        var badgeTextMobile = document.getElementById('daleelLiveBadgeTextMobile');
        if (!bar || !ticker || !link) return;

        var cur = 0;
        var INTERVAL_MS = 10000;
        var tickerTimer = null;

        function renderLiveIdx(i) {
            var s = rawLive[i];
            if (!s) return;
            ticker.style.opacity = '0';
            ticker.style.transform = 'translateY(-3px)';
            setTimeout(function() {
                var isVerified = parseInt(s.is_verified, 10) === 1;
                var locStr = s.state ? (s.city ? s.state + ' — ' + s.city : s.state) : (s.country && s.country !== 'SD' ? s.country : '');
                var locHtml = locStr ? '<span class="live-ticker-loc ms-1">(' + locStr + ')</span>' : '';

                if (badge && badgeIcon && badgeText && badgeTextMobile) {
                    if (isVerified) {
                        badge.className = 'live-pulse-badge badge-verified';
                        badgeIcon.className = 'fas fa-shield-alt text-success me-1';
                        badgeText.textContent = 'توثيق رسمي';
                        badgeTextMobile.textContent = 'توثيق';
                    } else {
                        badge.className = 'live-pulse-badge';
                        badgeIcon.className = 'fas fa-robot text-warning me-1';
                        badgeText.textContent = 'استكشاف ذكي';
                        badgeTextMobile.textContent = 'استكشاف';
                    }
                }

                if (isVerified) {
                    ticker.innerHTML = '<span class="d-none d-md-inline text-muted">تم توثيق مدرسة </span>' +
                                       '<strong>' + (s.name || '') + '</strong>' +
                                       locHtml +
                                       '<span class="d-none d-lg-inline text-muted me-1"> رسمياً في الدليل</span>';
                    link.title = 'زيارة صفحة ' + (s.name || '') + ' الموثقة';
                } else {
                    ticker.innerHTML = '<span class="d-none d-md-inline text-muted">تمت إضافة مدرسة </span>' +
                                       '<strong>' + (s.name || '') + '</strong>' +
                                       locHtml +
                                       '<span class="d-none d-lg-inline text-muted me-1"> عبر محرك الاستكشاف</span>';
                    link.title = 'زيارة صفحة ' + (s.name || '');
                }

                link.href = '/school/' + encodeURIComponent(s.slug);
                ticker.style.opacity = '1';
                ticker.style.transform = 'translateY(0)';
            }, 200);
        }

        bar.style.display = 'block';
        renderLiveIdx(0);

        function startTicker() {
            if (tickerTimer) clearInterval(tickerTimer);
            tickerTimer = setInterval(function() {
                cur = (cur + 1) % rawLive.length;
                renderLiveIdx(cur);
            }, INTERVAL_MS);
        }

        startTicker();

        bar.addEventListener('mouseenter', function() {
            if (tickerTimer) clearInterval(tickerTimer);
        });
        bar.addEventListener('mouseleave', function() {
            startTicker();
        });
    })();
    </script>
    <?php endif; ?>
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
