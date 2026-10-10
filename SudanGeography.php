<?php
/**
 * Sudan States, Cities, Facilities, and Standard Labels
 */

class SudanGeography
{
    public static function statesWithCities(): array
    {
        return [
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
        ];
    }

    public static function states(): array
    {
        return array_keys(self::statesWithCities());
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
        return $types[$key ?? ''] ?? 'مدرسة خاصة';
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
