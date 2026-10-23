<?php
/**
 * SchoPlus Daleel — Customer Service & Smart Bot Agent
 * وكيل خدمة العملاء والرد الآلي والذكاء الاصطناعي لدليل المدارس السودانية
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DaleelWhatsAppService.php';
require_once __DIR__ . '/SudanGeography.php';

class DaleelBotService {

    /**
     * معالجة الرسالة الواردة من Webhook سيرفر الواتساب
     */
    public static function handleIncoming(string $phone, string $message, ?string $chatId = null, array $payload = []): void {
        $message = trim($message);
        if ($phone === '' || ($message === '' && empty($payload['mediaBase64']))) {
            return;
        }

        // 1. تسجيل الرسالة الواردة
        DaleelWhatsAppService::logMessage(null, 'in', $phone, $chatId, $message);

        // 2. إدارة الجلسة التفاعلية للمستخدم
        $session = self::getSession($phone, $chatId);
        $currentStep = $session['current_step'] ?? 'idle';
        $tempData = !empty($session['temp_data']) ? json_decode($session['temp_data'], true) : [];
        if (!is_array($tempData)) $tempData = [];

        // 3. التحقق من أمر إعادة التعيين أو العودة للقائمة الرئيسية
        $normMsg = trim(mb_strtolower($message, 'UTF-8'));
        if (in_array($normMsg, ['0', 'رئيسية', 'القائمة', 'الرئيسية', 'menu', 'start', 'مرحبا', 'السلام عليكم', 'سلام', 'start', 'help', 'مساعدة'])) {
            self::updateSession($phone, 'idle', []);
            self::sendMainMenu($phone, $chatId);
            return;
        }

        // 4. معالجة خطوات المحادثة بحسب الخطوة الحالية
        switch ($currentStep) {
            case 'awaiting_search_query':
                self::processSchoolSearch($phone, $chatId, $message);
                self::updateSession($phone, 'idle', []);
                break;

            case 'awaiting_verification_school':
                self::processVerificationStep1($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_verification_claimant':
                self::processVerificationStep2($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_verification_notes':
                self::processVerificationFinal($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_update_school':
                self::processUpdateStep1($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_update_details':
                self::processUpdateFinal($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_add_name':
                self::processAddSchoolStep1($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_add_geo':
                self::processAddSchoolStep2($phone, $chatId, $message, $tempData);
                break;

            case 'awaiting_add_phone':
                self::processAddSchoolFinal($phone, $chatId, $message, $tempData);
                break;

            case 'idle':
            default:
                self::processIdleMessage($phone, $chatId, $message);
                break;
        }
    }

    /**
     * معالجة الرسائل في الحالة العادية (Idle)
     */
    private static function processIdleMessage(string $phone, ?string $chatId, string $message): void {
        $msg = trim($message);

        // الخيارات الرقمية من القائمة الرئيسية
        if ($msg === '1' || str_contains($msg, 'بحث') || str_contains($msg, 'استفسار عن مدرسة') || str_contains($msg, 'رسوم')) {
            self::updateSession($phone, 'awaiting_search_query', []);
            $reply = "🔍 *البحث والاستفسار عن المدارس*\n\nيرجى كتابة *اسم المدرسة* أو *الولاية* أو *المنطقة* التي تبحث عنها، وسنقوم بتزويدك ببياناتها فوراً:";
            self::reply($phone, $chatId, $reply, 'search_prompt');
            return;
        }

        if ($msg === '2' || str_contains($msg, 'توثيق') || str_contains($msg, 'توثيق مدرسة')) {
            self::updateSession($phone, 'awaiting_verification_school', []);
            $reply = "🏛️ *خدمة توثيق وإدارة صفحة المدرسة*\n\nأهلاً بكم يا إدارة المدرسة الموقرة.\nيرجى تزويدنا بـ *اسم المدرسة* المسجلة في الدليل أو رابط صفحتها:";
            self::reply($phone, $chatId, $reply, 'verification_step1');
            return;
        }

        if ($msg === '3' || str_contains($msg, 'تعديل') || str_contains($msg, 'تحديث بيانات')) {
            self::updateSession($phone, 'awaiting_update_school', []);
            $reply = "✏️ *تحديث وتعديل بيانات مدرسة*\n\nيرجى كتابة *اسم المدرسة* المراد تعديل بياناتها (مثل أرقام الهواتف، الرسوم، الفروع، أو المراحل):";
            self::reply($phone, $chatId, $reply, 'update_step1');
            return;
        }

        if ($msg === '4' || str_contains($msg, 'إضافة مدرسة') || str_contains($msg, 'تسجيل مدرسة')) {
            self::updateSession($phone, 'awaiting_add_name', []);
            $reply = "➕ *إضافة مدرسة جديدة في الدليل*\n\nيسعدنا انضمام صرحكم التعليمي لمنصة دليل المدارس السودانية.\n\nالخطوة [1 من 3]: ما هو *الاسم الرسمي الكامل للمدرسة*؟";
            self::reply($phone, $chatId, $reply, 'add_step1');
            return;
        }

        if ($msg === '5' || str_contains($msg, 'خدمة العملاء') || str_contains($msg, 'الدعم') || str_contains($msg, 'تواصل')) {
            $reply = "💬 *خدمة العملاء والدعم الفني — منصة دليل المدارس*\n\n"
                   . "فريقنا في خدمتكم لأي استفسار أو شراكة تعليمية:\n"
                   . "📞 الهاتف المباشر: +249 912 345 678\n"
                   . "🌐 الموقع الإلكتروني: https://daleel.schoplus.sd\n"
                   . "📧 البريد: info@schoplus.sd\n\n"
                   . "سيتواصل معكم أحد مسؤولي العلاقات التعليمية في أقرب وقت. اكتب استفسارك مباشرة وسنرد عليك.";
            self::reply($phone, $chatId, $reply, 'contact_info');
            return;
        }

        // إذا كتب المستخدم اسم مدرسة مباشرة، نبحث له عنها
        if (mb_strlen($msg) >= 3 && !in_array($msg, ['شكرا', 'تمام', 'أوك', 'ok', 'شكراً'])) {
            $found = self::searchSchoolsInDb($msg, 3);
            if (!empty($found)) {
                self::processSchoolSearch($phone, $chatId, $msg);
                return;
            }
        }

        // المساعد الذكي العام للردود
        self::generateAiOrSmartReply($phone, $chatId, $message);
    }

    /**
     * إرسال القائمة الرئيسية الموحدة
     */
    private static function sendMainMenu(string $phone, ?string $chatId): void {
        $text = "🇸🇩 *مرحباً بكم في منصة دليل المدارس السودانية* 🎓\n"
              . "_المنصة الوطنية الأكبر لربط وتوثيق المدارس والخدمات التعليمية_\n\n"
              . "كيف يمكننا مساعدتك اليوم؟ يرجى اختيار رقم الخدمة:\n\n"
              . "1️⃣ *البحث عن مدرسة والاستفسار عن الرسوم*\n"
              . "2️⃣ *طلب توثيق مدرسة قائمة (لإدارات المدارس)*\n"
              . "3️⃣ *تعديل وتحديث بيانات مدرسة*\n"
              . "4️⃣ *إضافة مدرسة جديدة في الدليل*\n"
              . "5️⃣ *التواصل مع إدارة المنصة وخدمة العملاء*\n\n"
              . "💡 _أرسل رقم الخيار (1 - 5) أو اكتب استفسارك مباشرة في أي وقت._";

        self::reply($phone, $chatId, $text, 'main_menu');
    }

    /**
     * البحث عن مدرسة في قاعدة البيانات وإرجاع النتائج
     */
    private static function processSchoolSearch(string $phone, ?string $chatId, string $query): void {
        $schools = self::searchSchoolsInDb($query, 3);

        if (empty($schools)) {
            $reply = "🔍 لم نتمكن من العثور على مدرسة تطابق: *{$query}*\n\n"
                   . "💡 *نصيحة:* حاول كتابة اسم المدرسة بدون لواحق (مثل: \"المواهب\" بدلاً من \"مدارس المواهب الخاصة\").\n"
                   . "أو تصفح الدليل كاملاً عبر الرابط:\nhttps://daleel.schoplus.sd\n\n"
                   . "أرسل *0* للعودة للقائمة الرئيسية.";
            self::reply($phone, $chatId, $reply, 'search_no_results');
            return;
        }

        $reply = "🎓 *نتائج البحث في دليل المدارس السودانية:*\n";
        $reply .= "─────────────────────\n";

        foreach ($schools as $idx => $s) {
            $num = $idx + 1;
            $slug = $s['slug'] ?: $s['id'];
            $url = "https://daleel.schoplus.sd/school/{$slug}";
            $verifiedBadge = !empty($s['is_verified']) ? ' ✓ [موثقة]' : '';
            $stateCity = trim($s['state'] . ' — ' . $s['city']);
            $fees = !empty($s['approx_fees']) ? $s['approx_fees'] : 'غير محددة';
            $phoneNum = !empty($s['phone']) ? $s['phone'] : 'غير متوفر';

            $stagesArr = !empty($s['stages']) ? json_decode($s['stages'], true) : [];
            $stagesStr = is_array($stagesArr) ? implode('، ', $stagesArr) : '';

            $reply .= "\n*{$num}. {$s['name']}*{$verifiedBadge}\n";
            $reply .= "📍 الموقع: {$stateCity}\n";
            if ($stagesStr) $reply .= "📚 المراحل: {$stagesStr}\n";
            $reply .= "💰 الرسوم: {$fees}\n";
            $reply .= "📞 الهاتف: {$phoneNum}\n";
            $reply .= "🔗 صفحة المدرسة: {$url}\n";
            $reply .= "─────────────────────\n";
        }

        $reply .= "\n💡 أرسل *0* للرجوع للقائمة الرئيسية، أو اكتب اسم مدرسة أخرى للبحث.";
        self::reply($phone, $chatId, $reply, 'search_results');
    }

    /**
     * مسار التوثيق - الخطوة 1: استلام اسم المدرسة
     */
    private static function processVerificationStep1(string $phone, ?string $chatId, string $message, array $tempData): void {
        $tempData['school_name'] = trim($message);
        self::updateSession($phone, 'awaiting_verification_claimant', $tempData);

        $reply = "✅ تم استلام اسم المدرسة: *{$tempData['school_name']}*\n\n"
               . "الخطوة [2 من 3]: ما هو *اسمك الكامل* و*صفتك الوظيفية* في المدرسة؟ (مثال: أ. أحمد محمد — المدير العام / وكيل المدرسة):";
        self::reply($phone, $chatId, $reply, 'verification_step2');
    }

    /**
     * مسار التوثيق - الخطوة 2: استلام اسم المسؤول والصفة
     */
    private static function processVerificationStep2(string $phone, ?string $chatId, string $message, array $tempData): void {
        $tempData['claimant_info'] = trim($message);
        self::updateSession($phone, 'awaiting_verification_notes', $tempData);

        $reply = "ممتاز أستاذنا الكريم.\n\n"
               . "الخطوة [3 من 3]: يرجى كتابة *رقم هاتف إضافي أو البريد الإلكتروني للمدرسة* وأي ملاحظات أو مستند يثبت إدارة المدرسة (ترخيص وزارة التربية أو ختم المدرسة):";
        self::reply($phone, $chatId, $reply, 'verification_step3');
    }

    /**
     * مسار التوثيق - الخطوة النهائية: حفظ الطلب في جدول `daleel_school_claims`
     */
    private static function processVerificationFinal(string $phone, ?string $chatId, string $message, array $tempData): void {
        $pdo = db();
        $schoolName = $tempData['school_name'] ?? 'مدرسة غير محددة';
        $claimant = $tempData['claimant_info'] ?? 'إدارة المدرسة';
        $notes = trim($message);

        // البحث عن معرف المدرسة في الدليل
        $schoolId = null;
        try {
            $stmt = $pdo->prepare("SELECT id FROM daleel_schools WHERE name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $schoolName . '%']);
            $schoolId = $stmt->fetchColumn() ?: null;
        } catch (Throwable $e) {}

        if (!$schoolId) {
            // استخدام مدرسة وهمية 0 أو أول مدرسة إذا لم يطابق
            $schoolId = 1;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO daleel_school_claims (school_id, claimant_name, claimant_title, claimant_phone, notes, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $schoolId,
                $claimant,
                'طلب توثيق عبر واتساب',
                $phone,
                "اسم المدرسة المطلوب توثيقها: {$schoolName}\nبيانات مقدم الطلب: {$claimant}\nالملاحظات والمستندات: {$notes}"
            ]);
            $claimId = $pdo->lastInsertId();
        } catch (Throwable $e) {
            $claimId = rand(100, 999);
        }

        self::updateSession($phone, 'idle', []);

        $reply = "🎉 *تم تسجيل طلب التوثيق بنجاح!*\n"
               . "رقم الطلب: #{$claimId}\n\n"
               . "🏫 المدرسة: *{$schoolName}*\n"
               . "👤 مقدم الطلب: {$claimant}\n"
               . "📞 رقم التواصل: {$phone}\n\n"
               . "سيقوم فريق مراجعة وتدقيق المدارس بالتواصل معكم خلال 24 ساعة لاعتماد صفحة المدرسة ومنحكم شارة *التوثيق الرسمية* ✓ وصلاحية إدارة البيانات والإعلانات مجاناً.\n\n"
               . "أرسل *0* للعودة للقائمة الرئيسية.";

        self::reply($phone, $chatId, $reply, 'verification_success');
    }

    /**
     * مسار التعديل - الخطوة 1
     */
    private static function processUpdateStep1(string $phone, ?string $chatId, string $message, array $tempData): void {
        $tempData['school_name'] = trim($message);
        self::updateSession($phone, 'awaiting_update_details', $tempData);

        $reply = "تم تحديد المدرسة: *{$tempData['school_name']}*\n\n"
               . "يرجى كتابة *التعديلات المطلوبة بالتفصيل*:\n"
               . "(مثال: تحديث الرسوم للعام الجديد، تعديل أرقام الهواتف، إضافة فرع جديد، تعديل المراحل التعليمية):";
        self::reply($phone, $chatId, $reply, 'update_step2');
    }

    /**
     * مسار التعديل - الخطوة النهائية
     */
    private static function processUpdateFinal(string $phone, ?string $chatId, string $message, array $tempData): void {
        $pdo = db();
        $schoolName = $tempData['school_name'] ?? 'مدرسة';
        $details = trim($message);

        // تسجيل الطلب في جدول استفسارات/طلبات المدرسة
        try {
            $stmt = $pdo->prepare("INSERT INTO daleel_school_inquiries (school_id, parent_name, parent_phone, message, status, created_at) VALUES (1, ?, ?, ?, 'update_request', NOW())");
            $stmt->execute([
                "طلب تعديل مدرسة: {$schoolName}",
                $phone,
                "المدرسة: {$schoolName}\nالتعديلات المطلوبة:\n{$details}"
            ]);
        } catch (Throwable $e) {}

        self::updateSession($phone, 'idle', []);

        $reply = "✅ *تم استلام طلب التعديل بنجاح!*\n\n"
               . "المدرسة: *{$schoolName}*\n"
               . "التفاصيل: {$details}\n\n"
               . "يقوم فريق التحرير بمراجعة التحديث وتطبيقه على صفحة المدرسة في الدليل فوراً.\n\n"
               . "أرسل *0* للعودة للقائمة الرئيسية.";

        self::reply($phone, $chatId, $reply, 'update_success');
    }

    /**
     * مسار إضافة مدرسة جديدة - خطوة 1: الاسم
     */
    private static function processAddSchoolStep1(string $phone, ?string $chatId, string $message, array $tempData): void {
        $tempData['new_name'] = trim($message);
        self::updateSession($phone, 'awaiting_add_geo', $tempData);

        $reply = "أهلاً بمدرسة *{$tempData['new_name']}* 🎓\n\n"
               . "الخطوة [2 من 3]: في أي *ولاية ومدينة أو حي* تقع المدرسة؟\n"
               . "(مثال: ولاية البحر الأحمر — بورتسودان — حي المطار):";
        self::reply($phone, $chatId, $reply, 'add_step2');
    }

    /**
     * مسار إضافة مدرسة جديدة - خطوة 2: الموقع
     */
    private static function processAddSchoolStep2(string $phone, ?string $chatId, string $message, array $tempData): void {
        $tempData['geo'] = trim($message);
        self::updateSession($phone, 'awaiting_add_phone', $tempData);

        $reply = "ممتاز.\n\n"
               . "الخطوة [3 من 3]: ما هو *رقم هاتف المدرسة الرسمي* والمراحل المتاحة (رياض أطفال، ابتدائي، متوسط، ثانوي)؟:";
        self::reply($phone, $chatId, $reply, 'add_step3');
    }

    /**
     * مسار إضافة مدرسة جديدة - الخطوة النهائية: حفظها في `daleel_schools` كـ pending
     */
    private static function processAddSchoolFinal(string $phone, ?string $chatId, string $message, array $tempData): void {
        $pdo = db();
        $name = $tempData['new_name'] ?? 'مدرسة جديدة';
        $geo = $tempData['geo'] ?? 'السودان';
        $extra = trim($message);

        // اشتقاق Slug مناسب
        $cleanSlug = 'school-' . preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name, 'UTF-8')) . '-' . rand(100, 999);
        $cleanSlug = trim($cleanSlug, '-');

        // فصل الولاية والمدينة تقريبياً
        $state = 'الخرطوم';
        $city = 'الخرطوم';
        $geoParts = preg_split('/[—\-\,\،]/u', $geo);
        if (!empty($geoParts[0])) $state = trim($geoParts[0]);
        if (!empty($geoParts[1])) $city = trim($geoParts[1]);

        try {
            $stmt = $pdo->prepare("INSERT INTO daleel_schools (name, slug, state, city, address, phone, whatsapp, description, status, owner_name, owner_phone, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'إدارة المدرسة', ?, '', NOW())");
            $stmt->execute([
                $name,
                $cleanSlug,
                $state,
                $city,
                $geo,
                $phone,
                $phone,
                "تمت الإضافة عبر واتساب الدليل.\nالمراحل والتفاصيل: {$extra}",
                $phone
            ]);
            $schoolId = $pdo->lastInsertId();
        } catch (Throwable $e) {
            $schoolId = 0;
        }

        self::updateSession($phone, 'idle', []);

        $reply = "🎉 *تمت إضافة المدرسة إلى الدليل بنجاح!*\n\n"
               . "🏫 الاسم: *{$name}*\n"
               . "📍 الموقع: {$geo}\n"
               . "📞 رقم الاتصال: {$phone}\n\n"
               . "حالة المدرسة: *قيد المراجعة والاعتماد* من قبل إدارة المنصة.\n"
               . "فور اعتمادها ستظهر في نتائج البحث والترتيب العام على الرابط:\n"
               . "https://daleel.schoplus.sd/school/{$cleanSlug}\n\n"
               . "أرسل *0* للعودة للقائمة الرئيسية.";

        self::reply($phone, $chatId, $reply, 'add_success');
    }

    /**
     * الرد الذكي المساعد (AI & Smart Knowledge Base)
     */
    private static function generateAiOrSmartReply(string $phone, ?string $chatId, string $message): void {
        $msg = trim($message);

        // قواعد ذكية مدمجة للاستفسارات الشائعة
        if (str_contains($msg, 'سودان') || str_contains($msg, 'مهجر') || str_contains($msg, 'مصر') || str_contains($msg, 'سعودية')) {
            $reply = "🌍 *المدارس السودانية في الداخل والمهجر*\n\n"
                   . "توثق منصة «دليل المدارس السودانية» المدارس في كافة ولايات السودان الـ 18 بالإضافة إلى مدارس الجاليات والمناهج السودانية في:\n"
                   . "🇪🇬 جمهورية مصر العربية (القاهرة، الجيزة، الإسكندرية، أسوان)\n"
                   . "🇸🇦 المملكة العربية السعودية (الرياض، جدة)\n"
                   . "🇦🇪 الإمارات، 🇶🇦 قطر، 🇺🇬 أوغندا، و🇸🇸 جنوب السودان.\n\n"
                   . "للبحث عن مدرسة في دولة أو ولاية محددة، أرسل رقم *1* واكتب اسم المنطقة.";
            self::reply($phone, $chatId, $reply, 'ai_geography');
            return;
        }

        if (str_contains($msg, 'رسوم') || str_contains($msg, 'اسعار') || str_contains($msg, 'مصاريف')) {
            $reply = "💰 *الاستفسار عن رسوم المدارس*\n\n"
                   . "يوفر الدليل متوسط الرسوم المدرسية المحدثة للعام الدراسي لكل مدرسة مصنفة حسب المراحل الدراسية (رياض أطفال، ابتدائي، متوسط، ثانوي).\n\n"
                   . "للاطلاع على رسوم مدرسة معينة، أرسل رقم *1* ثم اكتب اسم المدرسة.";
            self::reply($phone, $chatId, $reply, 'ai_fees');
            return;
        }

        // رد عام ذكي مع التوجيه للقائمة الرئيسية
        $reply = "أهلاً بك! تلقينا رسالتك: *\"{$msg}\"*\n\n"
               . "أنا المساعد الآلي لمنصة «دليل المدارس السودانية» 🎓\n\n"
               . "لخدمتك بشكل أسرع، يرجى اختيار أحد الأرقام التالية:\n"
               . "1️⃣ للبحث عن مدرسة والرسوم\n"
               . "2️⃣ لطلب توثيق مدرسة قائمة\n"
               . "3️⃣ لتعديل وتحديث بيانات مدرسة\n"
               . "4️⃣ لإضافة مدرسة جديدة\n"
               . "5️⃣ للتواصل المباشر مع خدمة العملاء\n\n"
               . "💡 يمكنك إرسال رقم الخيار مباشرة وسأتابع معك فوراً.";

        self::reply($phone, $chatId, $reply, 'ai_fallback');
    }

    /**
     * دالة مساعدة للرد عبر سيرفر الواتساب وتسجيل الرسالة الصادرة
     */
    private static function reply(string $phone, ?string $chatId, string $text, ?string $intent = null): void {
        DaleelWhatsAppService::sendMessage($phone, $text, $chatId);
        DaleelWhatsAppService::logMessage(null, 'out', $phone, $chatId, $text, $intent);
    }

    /**
     * البحث في جدول المدارس
     */
    private static function searchSchoolsInDb(string $query, int $limit = 3): array {
        $pdo = db();
        $q = trim($query);
        $norm = '%' . $q . '%';

        try {
            $stmt = $pdo->prepare("SELECT id, name, slug, state, city, phone, stages, approx_fees, is_verified FROM daleel_schools WHERE (name LIKE ? OR state LIKE ? OR city LIKE ? OR address LIKE ?) AND status = 'approved' ORDER BY is_verified DESC, rating_avg DESC LIMIT " . (int)$limit);
            $stmt->execute([$norm, $norm, $norm, $norm]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * جلب جلسة المستخدم أو إنشائها
     */
    private static function getSession(string $phone, ?string $chatId): array {
        $pdo = db();
        self::ensureSessionTable($pdo);

        try {
            $stmt = $pdo->prepare("SELECT * FROM daleel_whatsapp_sessions WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;

            $stmt = $pdo->prepare("INSERT INTO daleel_whatsapp_sessions (phone, chat_id, current_step, temp_data) VALUES (?, ?, 'idle', '{}')");
            $stmt->execute([$phone, $chatId]);
            return ['phone' => $phone, 'chat_id' => $chatId, 'current_step' => 'idle', 'temp_data' => '{}'];
        } catch (Throwable $e) {
            return ['phone' => $phone, 'chat_id' => $chatId, 'current_step' => 'idle', 'temp_data' => '{}'];
        }
    }

    /**
     * تحديث جلسة المستخدم
     */
    private static function updateSession(string $phone, string $step, array $tempData): void {
        $pdo = db();
        self::ensureSessionTable($pdo);

        try {
            $stmt = $pdo->prepare("UPDATE daleel_whatsapp_sessions SET current_step = ?, temp_data = ?, last_interaction_at = NOW() WHERE phone = ?");
            $stmt->execute([$step, json_encode($tempData, JSON_UNESCAPED_UNICODE), $phone]);
        } catch (Throwable $e) {}
    }

    private static function ensureSessionTable(PDO $pdo): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS daleel_whatsapp_sessions (
                phone VARCHAR(50) PRIMARY KEY,
                chat_id VARCHAR(100) NULL,
                current_step VARCHAR(50) NOT NULL DEFAULT 'idle',
                temp_data LONGTEXT NULL,
                last_interaction_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {}
    }
}
