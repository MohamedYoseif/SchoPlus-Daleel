<?php
/**
 * Sudan States, Cities, World Countries, Facilities, and Standard Labels
 */

class SudanGeography
{
    /**
     * Map of ISO 3166-1 alpha-2 codes to Arabic country names
     */
    public static function isoToCountryMap(): array
    {
        return [
            'SD' => 'السودان',
            'EG' => 'مصر',
            'SA' => 'المملكة العربية السعودية',
            'AE' => 'الإمارات العربية المتحدة',
            'QA' => 'قطر',
            'OM' => 'سلطنة عمان',
            'KW' => 'الكويت',
            'BH' => 'البحرين',
            'JO' => 'الأردن',
            'IQ' => 'العراق',
            'SY' => 'سوريا',
            'LB' => 'لبنان',
            'PS' => 'فلسطين',
            'YE' => 'اليمن',
            'LY' => 'ليبيا',
            'TN' => 'تونس',
            'DZ' => 'الجزائر',
            'MA' => 'المغرب',
            'MR' => 'موريتانيا',
            'SO' => 'الصومال',
            'DJ' => 'جيبوتي',
            'KM' => 'جزر القمر',
            'TR' => 'تركيا',
            'MY' => 'ماليزيا',
            'ID' => 'إندونيسيا',
            'GB' => 'المملكة المتحدة',
            'US' => 'الولايات المتحدة الأمريكية',
            'CA' => 'كندا',
            'DE' => 'ألمانيا',
            'FR' => 'فرنسا',
            'IT' => 'إيطاليا',
            'ES' => 'إسبانيا',
            'NL' => 'هولندا',
            'SE' => 'السويد',
            'CH' => 'سويسرا',
            'RU' => 'روسيا',
            'CN' => 'الصين',
            'IN' => 'الهند',
            'PK' => 'باكستان',
            'TD' => 'تشاد',
            'SS' => 'جنوب السودان',
            'ET' => 'إثيوبيا',
            'ER' => 'إريتريا',
            'UG' => 'أوغندا',
            'KE' => 'كينيا',
            'NG' => 'نيجيريا',
            'GH' => 'غانا',
            'ZA' => 'جنوب أفريقيا',
            'AU' => 'أستراليا',
            'NZ' => 'نيوزيلندا',
            'JP' => 'اليابان',
            'KR' => 'كوريا الجنوبية',
            'KP' => 'كوريا الشمالية',
            'IR' => 'إيران',
            'AF' => 'أفغانستان',
            'BD' => 'بنغلاديش',
            'LK' => 'سريلانكا',
            'TH' => 'تايلاند',
            'VN' => 'فيتنام',
            'PH' => 'الفلبين',
            'SG' => 'سنغافورة',
            'NO' => 'النرويج',
            'DK' => 'الدنمارك',
            'FI' => 'فنلندا',
            'BE' => 'بلجيكا',
            'AT' => 'النمسا',
            'PL' => 'بولندا',
            'CZ' => 'جمهورية التشيك',
            'GR' => 'اليونان',
            'PT' => 'البرتغال',
            'IE' => 'أيرلندا',
            'BR' => 'البرازيل',
            'AR' => 'الأرجنتين',
            'CL' => 'تشيلي',
            'CO' => 'كولومبيا',
            'MX' => 'المكسيك',
        ];
    }

    /**
     * Normalize formal country names to standard Arabic names matching the directory
     */
    public static function normalizeCountry(?string $country): string
    {
        $c = trim((string)$country);
        if ($c === '' || $c === 'SD' || $c === 'السودان') {
            return 'السودان';
        }

        static $map = [
            'جمهورية مصر العربية' => 'مصر',
            'دولة الإمارات العربية المتحدة' => 'الإمارات العربية المتحدة',
            'دولة قطر' => 'قطر',
            'دولة الكويت' => 'الكويت',
            'مملكة البحرين' => 'البحرين',
            'دولة ليبيا' => 'ليبيا',
            'جمهورية تشاد' => 'تشاد',
            'جمهورية أوغندا' => 'أوغندا',
            'جمهورية جنوب السودان' => 'جنوب السودان',
            'جمهورية إثيوبيا الفيدرالية' => 'إثيوبيا',
            'جمهورية كينيا' => 'كينيا',
            'جمهورية الصومال الفيدرالية' => 'الصومال',
            'جمهورية إندونيسيا' => 'إندونيسيا',
            'الجمهورية التركية' => 'تركيا',
        ];

        return $map[$c] ?? $c;
    }

    /**
     * All recognized sovereign world countries in Arabic (195 countries)
     */
    public static function allWorldCountries(): array
    {
        return [
            'السودان',
            'مصر',
            'المملكة العربية السعودية',
            'الإمارات العربية المتحدة',
            'قطر',
            'سلطنة عمان',
            'الكويت',
            'البحرين',
            'الأردن',
            'العراق',
            'سوريا',
            'لبنان',
            'فلسطين',
            'اليمن',
            'ليبيا',
            'تونس',
            'الجزائر',
            'المغرب',
            'موريتانيا',
            'الصومال',
            'جيبوتي',
            'جزر القمر',
            'تشاد',
            'جنوب السودان',
            'إثيوبيا',
            'إريتريا',
            'أوغندا',
            'كينيا',
            'تركيا',
            'ماليزيا',
            'إندونيسيا',
            'باكستان',
            'الهند',
            'بنغلاديش',
            'المملكة المتحدة',
            'الولايات المتحدة الأمريكية',
            'كندا',
            'ألمانيا',
            'فرنسا',
            'إيطاليا',
            'إسبانيا',
            'هولندا',
            'السويد',
            'سويسرا',
            'النرويج',
            'الدنمارك',
            'فنلندا',
            'بلجيكا',
            'النمسا',
            'أستراليا',
            'نيوزيلندا',
            'روسيا',
            'الصين',
            'اليابان',
            'كوريا الجنوبية',
            'أفغانستان',
            'ألبانيا',
            'أندورا',
            'أنغولا',
            'أنتيغوا وباربودا',
            'الأرجنتين',
            'أرمينيا',
            'أذربيجان',
            'جزر البهاما',
            'باربادوس',
            'بيلاروسيا',
            'بليز',
            'بنين',
            'بوتان',
            'بوليفيا',
            'البوسنة والهرسك',
            'بوتسوانا',
            'البرازيل',
            'بروناي',
            'بلغاريا',
            'بوركينا فاسو',
            'بوروندي',
            'كابو فيردي (الرأس الأخضر)',
            'كمبوديا',
            'الكاميرون',
            'جمهورية أفريقيا الوسطى',
            'تشيلي',
            'كولومبيا',
            'الكونغو',
            'جمهورية الكونغو الديمقراطية',
            'كوستاريكا',
            'كرواتيا',
            'كوبا',
            'قبرص',
            'جمهورية التشيك',
            'دومينيكا',
            'جمهورية الدومينيكان',
            'الإكوادور',
            'السلفادور',
            'غينيا الاستوائية',
            'إستونيا',
            'إسواتيني',
            'فيجي',
            'الغابون',
            'غامبيا',
            'جورجيا',
            'غانا',
            'اليونان',
            'غرينادا',
            'غواتيمالا',
            'غينيا',
            'غينيا بيساو',
            'غيانا',
            'هايتي',
            'هندوراس',
            'المجر',
            'آيسلندا',
            'إيران',
            'أيرلندا',
            'ساحل العاج',
            'جامايكا',
            'كازاخستان',
            'كيريباتي',
            'قيرغيزستان',
            'لاوس',
            'لاتفيا',
            'ليسوتو',
            'ليبيريا',
            'ليختنشتاين',
            'ليتوانيا',
            'لوكسمبورغ',
            'مدغشقر',
            'مالاوي',
            'المالديف',
            'مالي',
            'مالطا',
            'جزر مارشال',
            'موريشيوس',
            'المكسيك',
            'ميكرونيزيا',
            'مولدوفا',
            'موناكو',
            'منغوليا',
            'الجبل الأسود',
            'موزمبيق',
            'ميانمار',
            'ناميبيا',
            'ناورو',
            'نيبال',
            'نيكاراغوا',
            'النيجر',
            'نيجيريا',
            'كوريا الشمالية',
            'مقدونيا الشمالية',
            'بالاو',
            'بنما',
            'بابوا غينيا الجديدة',
            'باراغواي',
            'بيرو',
            'الفلبين',
            'بولندا',
            'البرتغال',
            'رومانيا',
            'رواندا',
            'سانت كيتس ونيفيس',
            'سانت لوسيا',
            'سانت فنسنت والغرينادين',
            'ساموا',
            'سان مارينو',
            'ساو تومي وبرينسيب',
            'السنغال',
            'صربيا',
            'سيشل',
            'سيراليون',
            'سنغافورة',
            'سلوفاكيا',
            'سلوفينيا',
            'جزر سليمان',
            'جنوب أفريقيا',
            'سورينام',
            'طاجيكستان',
            'تنزانيا',
            'تايلاند',
            'تيمور الشرقية',
            'توغو',
            'تونغا',
            'ترينيداد وتوباغو',
            'تركمانستان',
            'توفالو',
            'أوكرانيا',
            'أوروغواي',
            'أوزبكستان',
            'فانواتو',
            'الفاتيكان',
            'فنزويلا',
            'فيتنام',
            'زامبيا',
            'زيمبابوي',
        ];
    }

    /**
     * Detect visitor country based on IP (Cloudflare header, session, or cookie)
     */
    public static function detectVisitorCountry(): ?string
    {
        if (!empty($_COOKIE['detected_country'])) {
            return trim($_COOKIE['detected_country']);
        }
        if (!empty($_SESSION['detected_country'])) {
            return trim($_SESSION['detected_country']);
        }
        if (!empty($_COOKIE['detected_country_iso'])) {
            $iso = strtoupper(trim($_COOKIE['detected_country_iso']));
            $map = self::isoToCountryMap();
            if (isset($map[$iso])) {
                return $map[$iso];
            }
        }

        // 1. Edge & Proxy headers (Cloudflare, LiteSpeed, Nginx GeoIP)
        $headers = ['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE', 'HTTP_X_FORWARDED_COUNTRY'];
        $map = self::isoToCountryMap();
        foreach ($headers as $hdr) {
            if (!empty($_SERVER[$hdr])) {
                $iso = strtoupper(trim($_SERVER[$hdr]));
                if (isset($map[$iso])) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION['detected_country'] = $map[$iso];
                    }
                    return $map[$iso];
                }
            }
        }

        return null;
    }

    /**
     * Sorted countries list:
     * Rank #1: السودان (Always first)
     * Rank #2: الدولة المحددة من IP الزائر (إذا كانت مختلفة عن السودان)
     * Rest: جميع دول العالم مرتبة أبجدياً باللغة العربية
     */
    public static function sortedCountries(?string $detectedCountry = null): array
    {
        $detected = $detectedCountry ?: self::detectVisitorCountry();
        $all = self::allWorldCountries();

        // 1. First position is always Sudan
        $pinned = ['السودان'];

        // 2. Second position is the detected country (if valid and not Sudan)
        if (!empty($detected) && $detected !== 'السودان' && in_array($detected, $all, true)) {
            $pinned[] = $detected;
        }

        // 3. Remaining countries
        $remaining = array_values(array_filter($all, function($c) use ($pinned) {
            return !in_array($c, $pinned, true);
        }));

        // 4. Sort remaining alphabetically in Arabic
        if (class_exists('Collator')) {
            $collator = new Collator('ar_SA');
            $collator->sort($remaining);
        } else {
            usort($remaining, function($a, $b) {
                return strcmp($a, $b);
            });
        }

        return array_merge($pinned, $remaining);
    }

    /**
     * Public list of countries
     */
    public static function countries(): array
    {
        return self::sortedCountries();
    }

    /**
     * Countries with structured states and cities mapping
     */
    public static function countriesWithStatesAndCities(): array
    {
        return [
            'السودان' => [
                'الخرطوم' => ['الخرطوم', 'بحري', 'أم درمان', 'شرق النيل', 'جبل أولياء', 'كرري', 'أم بدة'],
                'البحر الأحمر' => ['بورتسودان', 'سواكن', 'سنكات', 'طوكر', 'جبيت'],
                'الجزيرة' => ['ود مدني', 'المناقل', 'الحصاحيصا', 'الكاملين', 'رفاعة', 'شرق الجزيرة', 'أم القرى'],
                'نهر النيل' => ['الدامر', 'عطبرة', 'شندي', 'بربر', 'المتمة', 'أبو حمد'],
                'الشمالية' => ['دنقلا', 'مروي', 'الدبة', 'كرمة', 'حلفا'],
                'كسلا' => ['كسلا', 'حلفا الجديدة', 'خشم القربة', 'أروما', 'همشكوريب'],
                'القضارف' => ['القضارف', 'الفاو', 'القلابات', 'الرهد', 'دوكة', 'البطانة'],
                'سنار' => ['سنار', 'سنجة', 'الدندر', 'السوكي', 'مايرنو', 'أبو حجار'],
                'النيل الأبيض' => ['ربك', 'كوستي', 'الدويم', 'القطينة', 'تندلتي', 'أم رمتة'],
                'النيل الأزرق' => ['الدمازين', 'الروصيرص', 'باو', 'الكرمك', 'ود الماحي'],
                'شمال كردفان' => ['الأبيض', 'بارا', 'أم روابة', 'الرهد', 'سودري'],
                'جنوب كردفان' => ['كادوقلي', 'الدلنج', 'رشاد', 'تلودي', 'أبو جبيهة'],
                'غرب كردفان' => ['الفولة', 'النهود', 'بابنوسة', 'غبيش'],
                'شمال دارفور' => ['الفاشر', 'كتم', 'مليط', 'كبكابية'],
                'جنوب دارفور' => ['نيالا', 'كاس', 'رهيد البردي', 'عد الفرسان'],
                'غرب دارفور' => ['الجنينة', 'سربا', 'كرينك', 'فوربرنقا'],
                'وسط دارفور' => ['زالنجي', 'نيرتتي', 'وادي صالح', 'بندسي'],
                'شرق دارفور' => ['الضعين', 'عديلة', 'أبو كارنكا', 'عسلاية'],
            ],
            'مصر' => [
                'القاهرة' => ['مدينة نصر', 'المعادي', 'التجمع الخامس', 'مصر الجديدة', 'فيصل', 'العباسية', 'حلوان'],
                'الجيزة' => ['الدقي', 'المهندسين', 'الهرم', 'فيصل', 'أكتوبر', 'الشيخ زايد', 'العمرانية'],
                'الإسكندرية' => ['سموحة', 'سيدي جابر', 'الرمل', 'المنتزه', 'العجمي', 'وسط الإسكندرية'],
                'أسوان' => ['أسوان', 'كوم أمبو', 'إدفو', 'دراو', 'نصر النوبة'],
                'البحر الأحمر' => ['الغردقة', 'سفاجا', 'القصير', 'رأس غارب'],
                'القليوبية' => ['بنها', 'شبرا الخيمة', 'قليوب', 'طوخ', 'العبور'],
                'الشرقية' => ['الزقازيق', 'العاشر من رمضان', 'بلبيس', 'فاقوس'],
                'الدقهلية' => ['المنصورة', 'ميت غمر', 'طلخا', 'دكرنس'],
            ],
            'المملكة العربية السعودية' => [
                'منطقة الرياض' => ['الرياض', 'الخرج', 'الدرعية', 'المجمعة', 'الدوادمي'],
                'منطقة مكة المكرمة' => ['جدة', 'مكة المكرمة', 'الطائف', 'القنفذة', 'رابغ'],
                'المنطقة الشرقية' => ['الدمام', 'الخبر', 'الجبيل', 'الأحساء', 'القطيف', 'حفر الباطن'],
                'منطقة المدينة المنورة' => ['المدينة المنورة', 'ينبع', 'العلا', 'بدر'],
                'منطقة عسير' => ['أبها', 'خميس مشيط', 'بيشة', 'محايل عسير'],
                'منطقة القصيم' => ['بريدة', 'عنيزة', 'الرس', 'البكيرية'],
                'منطقة تبوك' => ['تبوك', 'الوجه', 'ضباء', 'تيماء'],
            ],
            'الإمارات العربية المتحدة' => [
                'دبي' => ['دبي', 'ديرة', 'بر دبي', 'البرشاء', 'الجميرا', 'القصيص'],
                'أبوظبي' => ['أبوظبي', 'العين', 'الظفرة', 'مصفح'],
                'الشارقة' => ['الشارقة', 'خورفكان', 'كلباء', 'الذيد'],
                'عجمان' => ['عجمان', 'مصفوت', 'المنامة'],
                'رأس الخيمة' => ['رأس الخيمة', 'الرمس', 'الدقداقة'],
                'الفجيرة' => ['الفجيرة', 'دبا الفجيرة'],
                'أم القيوين' => ['أم القيوين', 'فلج المعلا'],
            ],
            'قطر' => [
                'الدوحة' => ['الدوحة', 'السد', 'الهلال', 'الدفنة', 'المطار القديم'],
                'الريان' => ['الريان', 'معيذر', 'الغرافة', 'عين خالد'],
                'الوكرة' => ['الوكرة', 'المشاف', 'مسيعيد'],
                'الخور' => ['الخور', 'الذخيرة'],
            ],
            'سلطنة عمان' => [
                'محافظة مسقط' => ['مسقط', 'السيب', 'بوشر', 'مطرح', 'العامرات'],
                'محافظة ظفار' => ['صلالة', 'طاقة', 'مرباط'],
                'محافظة شمال الباطنة' => ['صحار', 'السويق', 'صحم', 'شناص'],
            ],
            'الكويت' => [
                'محافظة العاصمة' => ['مدينة الكويت', 'الروضة', 'كيفان', 'الدسمة'],
                'محافظة حولي' => ['حولي', 'السالمية', 'الجابرية', 'الرميثية'],
                'محافظة الفروانية' => ['الفروانية', 'خيطان', 'الأندلس', 'العارضية'],
                'محافظة الأحمدي' => ['الأحمدي', 'الفحيحيل', 'المنقف', 'الصباحية'],
            ],
            'البحرين' => [
                'محافظة العاصمة' => ['المنامة', 'الجفير', 'السيف', 'الماحوز'],
                'محافظة المحرق' => ['المحرق', 'البسيتين', 'الحد', 'عراد'],
                'المحافظة الشمالية' => ['سار', 'البديع', 'مدينة حمد'],
            ],
            'تركيا' => [
                'إسطنبول' => ['الفاتح', 'باشاك شهير', 'بيليك دوزو', 'أسنيورت', 'كاديكوي'],
                'أنقرة' => ['تشانكايا', 'كجيعوران', 'يني محلة'],
                'بورصة' => ['عثمان غازي', 'نيلوفر', 'يلدريم'],
            ],
            'ماليزيا' => [
                'كوالالمبور' => ['كوالالمبور', 'شيركاس', 'بوكيت بينتانغ'],
                'سيلانغور' => ['شاه علم', 'بيتالينغ جايا', 'سوبانغ جايا', 'سايبرجايا'],
            ],
            'المملكة المتحدة' => [
                'لندن الكبرى' => ['لندن', 'وستمنستر', 'كامدن', 'غرينتش'],
                'مانشستر' => ['مانشستر', 'سالفورد', 'ترافورد'],
                'برمنغهام' => ['برمنغهام', 'إدجباستون', 'سوليهل'],
            ],
            'الولايات المتحدة الأمريكية' => [
                'فرجينيا' => ['فولز تشيرش', 'ألكسندريا', 'ريتشموند', 'أرلينغتون'],
                'تكساس' => ['هيوستن', 'دالاس', 'أوستن', 'سان أنطونيو', 'فورت وورث'],
                'آيوا' => ['دي موين', 'سيدار رابيدز', 'دابوق'],
                'كاليفورنيا' => ['لوس أنجلوس', 'سان فرانسيسكو', 'سان دييغو', 'سان خوسيه'],
                'نيويورك' => ['نيويورك', 'بروكلين', 'مانهاتن', 'كوينز'],
                'فلوريدا' => ['ميامي', 'أورلاندو', 'تامبا', 'جاكسونفيل'],
            ],
            'الأردن' => [
                'محافظة العاصمة (عمان)' => ['عمان', 'الجبيهة', 'صويلح', 'تلاع العلي', 'ماركا'],
                'محافظة إربد' => ['إربد', 'الرمثا', 'بني كنانة', 'الأغوار الشمالية'],
                'محافظة الزرقاء' => ['الزرقاء', 'الرصيفة', 'الهاشمية'],
                'محافظة البلقاء' => ['السلط', 'عين الباشا', 'دير علا', 'الشونة الجنوبية'],
                'محافظة العقبة' => ['العقبة', 'القويرة', 'وادي رم'],
            ],
            'العراق' => [
                'محافظة بغداد' => ['الكرخ', 'الرصافة', 'المنصور', 'الأعظمية', 'الكرادة'],
                'محافظة أربيل' => ['أربيل', 'عينكاوة', 'راوندوز', 'شقلاوة'],
                'محافظة البصرة' => ['البصرة', 'الزبير', 'شط العرب', 'أبو الخصيب'],
                'محافظة النجف' => ['النجف', 'الكوفة', 'المناذرة'],
                'محافظة كربلاء' => ['كربلاء', 'عين التمر', 'الهندية'],
                'محافظة نينوى' => ['الموصل', 'تلعفر', 'الحمدانية', 'سنجار'],
            ],
            'اليمن' => [
                'أمانة العاصمة (صنعاء)' => ['صنعاء', 'السبعين', 'التحرير', 'معين', 'الوحدة'],
                'محافظة عدن' => ['كريتر', 'المعلا', 'التواهي', 'الشيخ عثمان', 'المنصورة'],
                'محافظة تعز' => ['تعز', 'المظفر', 'القاهرة', 'صالة'],
                'محافظة حضرموت' => ['المكلا', 'سيئون', 'تريم', 'الشحر'],
            ],
            'ليبيا' => [
                'بلدية طرابلس' => ['طرابلس', 'سوق الجمعة', 'حي الأندلس', 'تاجوراء', 'أبو سليم'],
                'بلدية بنغازي' => ['بنغازي', 'البركة', 'السلاوي', 'بوعطني'],
                'بلدية مصراتة' => ['مصراتة', 'زريق', 'طريق الشاطئ'],
                'بلدية الزاوية' => ['الزاوية', 'أبي عيسى', 'المطرد'],
            ],
            'تونس' => [
                'ولاية تونس' => ['تونس العاصمة', 'المرسى', 'حلق الوادي', 'سيدي بوسعيد', 'باردو'],
                'ولاية صفاقس' => ['صفاقس', 'ساقية الزيت', 'طينة', 'قرقنة'],
                'ولاية سوسة' => ['سوسة', 'حمام سوسة', 'القلعة الكبرى', 'أكودة'],
            ],
            'الجزائر' => [
                'ولاية الجزائر' => ['الجزائر الوسطى', 'سيدي امحمد', 'باب الوادي', 'حيدرة', 'بئر مراد رايس'],
                'ولاية وهران' => ['وهران', 'السانية', 'أرزيو', 'عين الترك'],
                'ولاية قسنطينة' => ['قسنطينة', 'الخروب', 'عين سمارة'],
            ],
            'المغرب' => [
                'جهة الدار البيضاء - سطات' => ['الدار البيضاء', 'المحمدية', 'سطات', 'بوسكورة'],
                'جهة الرباط - سلا - القنيطرة' => ['الرباط', 'سلا', 'القنيطرة', 'تمارة'],
                'جهة مراكش - آسفي' => ['مراكش', 'آسفي', 'الصويرة', 'قلعة السراغنة'],
                'جهة طنجة - تطوان - الحسيمة' => ['طنجة', 'تطوان', 'الحسيمة', 'العرائش'],
            ],
            'فلسطين' => [
                'محافظة القدس' => ['القدس الشريف', 'بيت حنينا', 'شعفاط', 'العيزرية'],
                'محافظة رام الله والبيرة' => ['رام الله', 'البيرة', 'بيتونيا'],
                'محافظة غزة' => ['مدينة غزة', 'الرمال', 'الشجاعية', 'الزيتون'],
            ],
            'لبنان' => [
                'محافظة بيروت' => ['بيروت', 'الحمرا', 'الأشرفية', 'راس بيروت'],
                'محافظة جبل لبنان' => ['بعبدا', 'المتن', 'كسروان', 'عاليه', 'الشوف'],
                'محافظة لبنان الشمالي' => ['طرابلس', 'الميناء', 'زغرتا', 'الكورة'],
            ],
            'سوريا' => [
                'محافظة دمشق' => ['دمشق', 'المزة', 'الميدان', 'الصالحية', 'كفرسوسة'],
                'محافظة ريف دمشق' => ['دوما', 'داريا', 'قدسيا', 'جرمانا', 'التل'],
                'محافظة حلب' => ['حلب', 'الشهباء', 'سيف الدولة', 'السريان'],
            ],
            'تشاد' => [
                'منطقة نجامينا' => ['نجامينا', 'فارش', 'موروبول', 'واليا'],
                'منطقة وداي' => ['أبشي', 'أدري', 'أبوقدام'],
            ],
            'جنوب السودان' => [
                'ولاية وسط الاستوائية' => ['جوبا', 'كاجو كيجي', 'يي'],
                'ولاية غرب بحر الغزال' => ['واو', 'راجا'],
                'ولاية أعالي النيل' => ['ملكال', 'الرنك', 'فشودة'],
            ],
            'إثيوبيا' => [
                'إدارة أديس أبابا' => ['أديس أبابا', 'بولي', 'أرادا', 'كيركوس'],
                'إدارة دري داوا' => ['دري داوا', 'سابيان'],
            ],
            'أوغندا' => [
                'المنطقة الوسطى (كمبالا)' => ['كمبالا', 'عنتيبي', 'مكينديه', 'كاويمبي'],
                'المنطقة الشرقية' => ['جنجا', 'مبيلي', 'تورو'],
            ],
            'كينيا' => [
                'مقاطعة نيروبي' => ['نيروبي', 'كيليماني', 'ويستلاندز', 'إيستلي'],
                'مقاطعة مومباسا' => ['مومباسا', 'نيالي'],
            ],
            'الصومال' => [
                'إقليم وقويي جالبيد (هرجيسا)' => ['هرجيسا'],
                'إقليم بنادر (مقديشو)' => ['مقديشو'],
            ],
            'إندونيسيا' => [
                'منطقة جاكرتا الخاصة' => ['جاكرتا', 'سيلانداك', 'جنوب جاكرتا', 'وسط جاكرتا'],
            ],
            'كندا' => [
                'مقاطعة أونتاريو' => ['تورونتو', 'أوتاوا', 'ميسيساغا', 'برامبتون'],
                'مقاطعة كيبك' => ['مونتريال', 'مدينة كيبك', 'لافال'],
                'مقاطعة كولومبيا البريطانية' => ['فانكوفر', 'سري', 'فيكتوريا', 'برنابي'],
                'مقاطعة ألبرتا' => ['كالغاري', 'إدمونتون', 'ريد دير'],
            ],
            'ألمانيا' => [
                'ولاية برلين' => ['برلين', 'ميتيه', 'شارلوتنبورغ', 'بانكو'],
                'ولاية بافاريا' => ['ميونخ', 'نورمبرغ', 'أوغسبورغ'],
                'ولاية شمال الراين - وستفاليا' => ['كولونيا', 'دوسلدورف', 'دورتموند', 'إيسن'],
            ],
            'فرنسا' => [
                'منطقة إيل دو فرانس' => ['باريس', 'بولوني بيانكور', 'سان دوني', 'فرساي'],
                'منطقة أوفيرن - رون ألب' => ['ليون', 'غرونوبل', 'سانت إتيان'],
                'منطقة بروفنس - ألب - كوت دازور' => ['مرسيليا', 'نيس', 'تولون', 'إيكس أون بروفانس'],
            ],
            'الصين' => [
                'بلدية بكين' => ['بكين', 'تشاويانغ', 'هايديان'],
                'بلدية شنغهاي' => ['شنغهاي', 'بودونغ', 'هوانغبو'],
                'مقاطعة غوانغدونغ' => ['غوانغتشو', 'شنجن', 'فوشان'],
            ],
            'الهند' => [
                'إقليم العاصمة الوطنية دلهي' => ['نيودلهي', 'دلهي القديمة'],
                'ولاية ماهاراشترا' => ['مومباي', 'بونا', 'ناغبور'],
                'ولاية كارناتاكا' => ['بنغالور', 'ميسور'],
            ],
        ];
    }

    public static function statesWithCities(?string $country = 'السودان'): array
    {
        $all = self::countriesWithStatesAndCities();
        $c = ($country && isset($all[$country])) ? $country : null;
        if ($c) {
            return $all[$c];
        }

        // Generic fallback for any other world country
        return [
            'المركز الرئيسي / العاصمة' => ['المركز الرئيسي / العاصمة'],
        ];
    }

    public static function states(?string $country = 'السودان'): array
    {
        return array_keys(self::statesWithCities($country));
    }

    public static function availableFacilities(): array
    {
        return [
            'computer_lab' => ['name' => 'معامل حاسوب متطورة', 'label' => 'معامل حاسوب متطورة', 'icon' => 'fas fa-laptop-code'],
            'science_lab' => ['name' => 'مختبرات علمية مجهزة', 'label' => 'مختبرات علمية مجهزة', 'icon' => 'fas fa-flask'],
            'sports_court' => ['name' => 'ملاعب رياضية', 'label' => 'ملاعب رياضية', 'icon' => 'fas fa-futbol'],
            'transportation' => ['name' => 'ترحيل وباصات مدرسية', 'label' => 'ترحيل وباصات مدرسية', 'icon' => 'fas fa-bus'],
            'cafeteria' => ['name' => 'كافتيريا وتغذية صحية', 'label' => 'كافتيريا وتغذية صحية', 'icon' => 'fas fa-utensils'],
            'library' => ['name' => 'مكتبة شاملة وقاعة قراءة', 'label' => 'مكتبة شاملة وقاعة قراءة', 'icon' => 'fas fa-book-reader'],
            'mosque' => ['name' => 'مسجد ومصلى', 'label' => 'مسجد ومصلى', 'icon' => 'fas fa-mosque'],
            'smart_classes' => ['name' => 'شاشات وفصول ذكية', 'label' => 'شاشات وفصول ذكية', 'icon' => 'fas fa-tv'],
            'ac' => ['name' => 'فصول مكيفة بالكامل', 'label' => 'فصول مكيفة بالكامل', 'icon' => 'fas fa-snowflake'],
            'activities' => ['name' => 'أنشطة لا صفية وفنون', 'label' => 'أنشطة لا صفية وفنون', 'icon' => 'fas fa-palette'],
            'security' => ['name' => 'حراسة وكاميرات مراقبة', 'label' => 'حراسة وكاميرات مراقبة', 'icon' => 'fas fa-shield-alt'],
            'pool' => ['name' => 'مسبح تدريبي', 'label' => 'مسبح تدريبي', 'icon' => 'fas fa-swimming-pool'],
        ];
    }

    public static function facilitiesList(): array
    {
        return self::availableFacilities();
    }

    public static function facilityLabel(string $key): string
    {
        $all = self::availableFacilities();
        return $all[$key]['name'] ?? $all[$key]['label'] ?? $key;
    }

    public static function facilityIcon(string $key): string
    {
        $all = self::availableFacilities();
        return $all[$key]['icon'] ?? 'fas fa-check-circle';
    }

    public static function schoolTypes(): array
    {
        return [
            'private' => 'مدرسة خاصة',
            'government' => 'مدرسة حكومية',
            'international' => 'مدرسة عالمية / دولية',
            'language' => 'مدرسة لغات',
            'community' => 'مدرسة مجتمعية',
        ];
    }

    public static function schoolTypeLabel(?string $key): string
    {
        $types = self::schoolTypes();
        if ($key && isset($types[$key])) {
            return $types[$key];
        }
        return !empty($key) ? $key : 'مدرسة حكومية / عامة';
    }

    public static function schoolTypeBadge(?string $key): array
    {
        $type = $key ?? '';
        switch ($type) {
            case 'government':
                return [
                    'label' => 'مدرسة حكومية',
                    'icon' => 'fas fa-university',
                    'style' => 'background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0;',
                ];
            case 'international':
                return [
                    'label' => 'مدرسة دولية / عالمية',
                    'icon' => 'fas fa-globe',
                    'style' => 'background: #F5F3FF; color: #5B21B6; border: 1px solid #DDD6FE;',
                ];
            case 'community':
                return [
                    'label' => 'مدرسة مجتمعية',
                    'icon' => 'fas fa-users',
                    'style' => 'background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A;',
                ];
            case 'language':
                return [
                    'label' => 'مدرسة لغات',
                    'icon' => 'fas fa-language',
                    'style' => 'background: #F0FDFA; color: #0F766E; border: 1px solid #99F6E4;',
                ];
            case 'private':
            default:
                return [
                    'label' => 'مدرسة خاصة',
                    'icon' => 'fas fa-graduation-cap',
                    'style' => 'background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE;',
                ];
        }
    }

    public static function deduceSchoolType(string $name, ?string $currentType = null): string
    {
        if (preg_match('/(روضة|حضانة|براعم|kindergarten|nursery|preschool)/ui', $name)) {
            return 'kindergarten';
        }
        if (preg_match('/(خاصة|الخاصة|خاصه|الخاصه|private)/ui', $name)) {
            return 'private';
        }
        if (!empty($currentType) && in_array($currentType, ['private', 'government', 'kindergarten', 'international', 'community', 'language'])) {
            return $currentType;
        }
        // قاعدة الاعتماد الصارمة: إذا لم توجد كلمة "خاصة" في الاسم فالمؤسسة حكومية
        return 'government';
    }

    public static function schoolStages(): array
    {
        return [
            'kindergarten' => 'رياض أطفال',
            'primary' => 'ابتدائي / أساس',
            'intermediate' => 'متوسطة',
            'secondary' => 'ثانوية',
        ];
    }

    public static function stages(): array
    {
        return self::schoolStages();
    }

    public static function stageLabel(?string $key): string
    {
        $stages = self::schoolStages();
        return $stages[$key ?? ''] ?? ($key ?? '');
    }

    /**
     * Deduces accurate educational stages based on school type and name/description
     * Strict User Rule: مالم تذكر المدرسة صراحة أنها تحتوي رياض أطفال لا يتم إضافتها نهائياً حتى لو كانت مدرسة خاصة.
     * Government schools NEVER have kindergarten in Sudan under any circumstances.
     *
     * @param string|null $schoolType 'government', 'private', 'international', etc.
     * @param string|null $schoolName Name of the school
     * @param array|string|null $explicitStages Explicitly provided stages if any
     * @param string|null $description Optional school description
     * @return array Array of stage keys: e.g. ['secondary'] or ['primary', 'intermediate']
     */
    public static function deduceStages(?string $schoolType, ?string $schoolName = '', $explicitStages = null, ?string $description = ''): array
    {
        $schoolType = strtolower(trim((string)$schoolType));
        $schoolName = trim((string)$schoolName);
        $description = trim((string)$description);

        $textSearch = mb_strtolower($schoolName . ' ' . $description, 'UTF-8');

        // Check if Kindergarten is EXPLICITLY mentioned in name or description
        $hasExplicitKg = (
            mb_stripos($textSearch, 'روضة') !== false ||
            mb_stripos($textSearch, 'روضه') !== false ||
            mb_stripos($textSearch, 'رياض أطفال') !== false ||
            mb_stripos($textSearch, 'رياض اطفال') !== false ||
            mb_stripos($textSearch, 'تمهيدي') !== false ||
            mb_stripos($textSearch, 'حضانة') !== false ||
            mb_stripos($textSearch, 'حضانه') !== false ||
            mb_stripos($textSearch, 'kindergarten') !== false ||
            mb_stripos($textSearch, 'nursery') !== false ||
            mb_stripos($textSearch, 'preschool') !== false
        );

        // Parse explicit stages if passed
        $stages = [];
        if (is_array($explicitStages)) {
            $stages = $explicitStages;
        } elseif (is_string($explicitStages) && !empty($explicitStages)) {
            $decoded = json_decode($explicitStages, true);
            if (is_array($decoded)) {
                $stages = $decoded;
            }
        }

        $fullSet = ['kindergarten', 'primary', 'intermediate', 'secondary'];
        if (empty($stages) || $stages === $fullSet) {
            $nameLower = mb_strtolower($schoolName, 'UTF-8');

            $isSec = (mb_stripos($nameLower, 'ثانوي') !== false || mb_stripos($nameLower, 'ثانويه') !== false || mb_stripos($nameLower, 'secondary') !== false || mb_stripos($nameLower, 'عليا') !== false);
            $isInter = (mb_stripos($nameLower, 'متوسط') !== false || mb_stripos($nameLower, 'متوسطه') !== false || mb_stripos($nameLower, 'intermediate') !== false || mb_stripos($nameLower, 'إعدادي') !== false);
            $isPrim = (mb_stripos($nameLower, 'ابتدائي') !== false || mb_stripos($nameLower, 'أساس') !== false || mb_stripos($nameLower, 'اساس') !== false || mb_stripos($nameLower, 'primary') !== false);
            $isOnlyKg = $hasExplicitKg && (mb_stripos($nameLower, 'روضة') !== false || mb_stripos($nameLower, 'روضه') !== false || mb_stripos($nameLower, 'تمهيدي') !== false) && !$isPrim && !$isInter && !$isSec;

            if ($isOnlyKg) {
                $stages = ['kindergarten'];
            } elseif ($isSec && !$isPrim && !$isInter) {
                $stages = ['secondary'];
            } elseif ($isInter && !$isPrim && !$isSec) {
                $stages = ['intermediate'];
            } elseif ($isPrim && !$isInter && !$isSec) {
                $stages = ['primary'];
            } elseif ($isPrim && $isInter && !$isSec) {
                $stages = ['primary', 'intermediate'];
            } elseif ($isInter && $isSec && !$isPrim) {
                $stages = ['intermediate', 'secondary'];
            } else {
                // Default stages for schools (primary, intermediate, secondary) WITHOUT kindergarten by default
                if ($isSec) {
                    $stages = ['secondary'];
                } else {
                    $stages = ['primary', 'intermediate', 'secondary'];
                }
            }

            // ONLY include kindergarten if EXPLICITLY mentioned in school name or description AND NOT a government school
            if ($hasExplicitKg && $schoolType !== 'government' && !in_array('kindergarten', $stages, true)) {
                array_unshift($stages, 'kindergarten');
            }
        }

        // Strict Enforcement:
        // 1. Government schools NEVER have kindergarten in Sudan
        if ($schoolType === 'government') {
            $stages = array_values(array_filter($stages, function($stg) {
                return $stg !== 'kindergarten';
            }));
        } else {
            // 2. For private/international/other schools: DO NOT include kindergarten UNLESS explicitly mentioned in name or description
            if (!$hasExplicitKg) {
                $stages = array_values(array_filter($stages, function($stg) {
                    return $stg !== 'kindergarten';
                }));
            }
        }

        // Fallback if empty
        if (empty($stages)) {
            $stages = ($schoolType === 'government') ? ['primary', 'intermediate'] : ['primary', 'intermediate', 'secondary'];
        }

        return array_values(array_unique($stages));
    }

    public static function curriculums(): array
    {
        return [
            'sudanese' => 'المنهج السوداني القومي',
            'british' => 'المنهج البريطاني (IGCSE)',
            'american' => 'المنهج الأمريكي',
            'mixed' => 'منهج مزدوج',
        ];
    }

    public static function curricula(): array
    {
        return self::curriculums();
    }

    public static function curriculumLabel(?string $key): string
    {
        $list = self::curriculums();
        return $list[$key ?? ''] ?? 'المنهج السوداني القومي';
    }

    public static function genderTypes(): array
    {
        return [
            'mixed' => 'مختلط (بنين وبنات)',
            'boys' => 'بنين فقط',
            'girls' => 'بنات فقط',
        ];
    }

    public static function genderTypeLabel(?string $key): string
    {
        $types = self::genderTypes();
        return $types[$key ?? ''] ?? 'مختلط';
    }
}
