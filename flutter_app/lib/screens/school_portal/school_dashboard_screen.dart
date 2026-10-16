import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/api_service.dart';
import 'school_login_screen.dart';

class SchoolDashboardScreen extends StatefulWidget {
  const SchoolDashboardScreen({super.key});

  @override
  State<SchoolDashboardScreen> createState() => _SchoolDashboardScreenState();
}

class _SchoolDashboardScreenState extends State<SchoolDashboardScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  String? _error;
  
  Map<String, dynamic>? _school;
  List<dynamic> _inquiries = [];
  List<dynamic> _news = [];
  List<dynamic> _reviews = [];

  // Profile Edit Controllers
  final _profileFormKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _nameEnController = TextEditingController();
  final _aboutController = TextEditingController();
  final _phoneController = TextEditingController();
  final _whatsappController = TextEditingController();
  final _emailController = TextEditingController();
  final _websiteController = TextEditingController();
  final _addressController = TextEditingController();
  final _feesMinController = TextEditingController();
  final _feesMaxController = TextEditingController();
  
  String _selectedState = 'الخرطوم';
  String _selectedCity = '';
  String _selectedGender = 'mixed';
  List<String> _selectedStages = [];
  List<String> _selectedFacilities = [];
  bool _isSavingProfile = false;

  // Inquiries filter
  String _inquiryFilter = 'all';

  final List<String> _allFacilities = [
    'مختبر حاسوب متطور',
    'مختبر علوم وفيزياء',
    'ملاعب رياضية',
    'ترحيل وباصات مدرسية',
    'مكتبة مدرسية وقاعة قراءة',
    'عيادة طبية وإسعافات',
    'كافتيريا ومطعم مدرسي',
    'مسرح وقاعة احتفالات',
    'كاميرات مراقبة وأمن 24 ساعة',
    'فصول مكيفة وشاشات تفاعلية',
    'أنشطة كشفية وموسيقية',
    'تعليم إلكتروني ومنصة متابعة',
  ];

  final List<String> _allStages = [
    'رياض أطفال',
    'ابتدائي',
    'متوسط',
    'ثانوي',
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _loadDashboardData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _nameController.dispose();
    _nameEnController.dispose();
    _aboutController.dispose();
    _phoneController.dispose();
    _whatsappController.dispose();
    _emailController.dispose();
    _websiteController.dispose();
    _addressController.dispose();
    _feesMinController.dispose();
    _feesMaxController.dispose();
    super.dispose();
  }

  Future<void> _loadDashboardData() async {
    final token = await AuthService.getToken();
    if (token == null) {
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const SchoolLoginScreen()),
        );
      }
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await ApiService.getSchoolPortalData(token);
      final schoolData = res['school'] as Map<String, dynamic>;
      
      _school = schoolData;
      _inquiries = res['inquiries'] ?? [];
      _news = res['news'] ?? [];
      _reviews = res['reviews'] ?? [];

      // Populate edit fields
      _nameController.text = schoolData['name'] ?? '';
      _nameEnController.text = schoolData['name_en'] ?? '';
      _aboutController.text = schoolData['about'] ?? schoolData['description'] ?? '';
      _phoneController.text = schoolData['phone'] ?? '';
      _whatsappController.text = schoolData['whatsapp'] ?? '';
      _emailController.text = schoolData['email'] ?? '';
      _websiteController.text = schoolData['website'] ?? '';
      _addressController.text = schoolData['address'] ?? '';
      _feesMinController.text = (schoolData['fees_min'] ?? '').toString();
      _feesMaxController.text = (schoolData['fees_max'] ?? '').toString();

      _selectedState = schoolData['state'] ?? 'الخرطوم';
      _selectedCity = schoolData['city'] ?? '';
      _selectedGender = schoolData['gender_type'] ?? 'mixed';

      if (schoolData['stages'] is List) {
        _selectedStages = List<String>.from(schoolData['stages']);
      } else {
        _selectedStages = [];
      }

      if (schoolData['facilities'] is List) {
        _selectedFacilities = List<String>.from(schoolData['facilities']);
      } else {
        _selectedFacilities = [];
      }

    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _saveProfile() async {
    final token = await AuthService.getToken();
    if (token == null) return;

    if (!_profileFormKey.currentState!.validate()) return;

    setState(() => _isSavingProfile = true);

    try {
      final data = {
        'name': _nameController.text.trim(),
        'name_en': _nameEnController.text.trim(),
        'about': _aboutController.text.trim(),
        'phone': _phoneController.text.trim(),
        'whatsapp': _whatsappController.text.trim(),
        'email': _emailController.text.trim(),
        'website': _websiteController.text.trim(),
        'state': _selectedState,
        'city': _selectedCity,
        'address': _addressController.text.trim(),
        'fees_min': double.tryParse(_feesMinController.text.trim()) ?? 0,
        'fees_max': double.tryParse(_feesMaxController.text.trim()) ?? 0,
        'gender_type': _selectedGender,
        'stages': _selectedStages,
        'facilities': _selectedFacilities,
      };

      await ApiService.updateSchoolProfile(token, data);

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم حفظ وتحديث بيانات المدرسة بنجاح!'),
          backgroundColor: AppTheme.emerald,
        ),
      );
      _loadDashboardData();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceAll('Exception: ', '')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isSavingProfile = false);
      }
    }
  }

  Future<void> _updateInquiry(int inqId, String newStatus) async {
    final token = await AuthService.getToken();
    if (token == null) return;

    try {
      await ApiService.updateInquiryStatus(token, inqId, newStatus);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تحديث حالة الطلب'),
          backgroundColor: AppTheme.navy,
          duration: Duration(seconds: 1),
        ),
      );
      _loadDashboardData();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('فشل التحديث: $e'), backgroundColor: Colors.red),
      );
    }
  }

  void _showAddNewsDialog() {
    final titleCtrl = TextEditingController();
    final contentCtrl = TextEditingController();
    String category = 'general';
    bool isPinned = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'نشر خبر أو إعلان جديد',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.pop(ctx),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: titleCtrl,
                decoration: const InputDecoration(
                  labelText: 'عنوان الخبر',
                  prefixIcon: Icon(Icons.title),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: contentCtrl,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'تفاصيل الخبر أو الإعلان',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: category,
                      decoration: const InputDecoration(labelText: 'التصنيف'),
                      items: const [
                        DropdownMenuItem(value: 'general', child: Text('عام')),
                        DropdownMenuItem(value: 'academic', child: Text('أكاديمي')),
                        DropdownMenuItem(value: 'activity', child: Text('أنشطة')),
                        DropdownMenuItem(value: 'admission', child: Text('قبول وتسجيل')),
                      ],
                      onChanged: (v) => setModalState(() => category = v ?? 'general'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Row(
                    children: [
                      Checkbox(
                        value: isPinned,
                        activeColor: AppTheme.gold,
                        onChanged: (v) => setModalState(() => isPinned = v ?? false),
                      ),
                      const Text('تثبيت الخبر'),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                icon: const Icon(Icons.send_rounded),
                label: const Text('نشر الخبر الآن'),
                onPressed: () async {
                  if (titleCtrl.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  final token = await AuthService.getToken();
                  if (token == null) return;
                  try {
                    await ApiService.addSchoolNews(token, titleCtrl.text.trim(), contentCtrl.text.trim(), category, isPinned);
                    _loadDashboardData();
                  } catch (e) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('فشل النشر: $e')));
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تسجيل الخروج'),
        content: const Text('هل أنت متأكد من تسجيل الخروج من لوحة إدارة المدرسة؟'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('إلغاء')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('تسجيل خروج'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await AuthService.logout();
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const SchoolLoginScreen()),
      );
    }
  }

  void _callPhone(String phone) async {
    final clean = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  void _openWhatsApp(String phone, String parentName) async {
    String clean = phone.replaceAll(RegExp(r'[^0-9]'), '');
    if (clean.startsWith('0')) {
      clean = '249${clean.substring(1)}';
    } else if (!clean.startsWith('249') && clean.length == 9) {
      clean = '249$clean';
    }
    final text = Uri.encodeComponent('مرحباً أستاذ/ة $parentName، نتواصل معكم من إدارة مدرسة ${_school?['name'] ?? ''} بخصوص طلب القبول عبر دليل المدارس الذكي.');
    final uri = Uri.parse('https://wa.me/$clean?text=$text');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(color: AppTheme.navy),
              SizedBox(height: 16),
              Text('جاري تحميل بيانات لوحة المدرسة...'),
            ],
          ),
        ),
      );
    }

    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('لوحة إدارة المدرسة')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                const SizedBox(height: 16),
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 20),
                ElevatedButton(onPressed: _loadDashboardData, child: const Text('إعادة المحاولة')),
              ],
            ),
          ),
        ),
      );
    }

    final newInquiries = _inquiries.where((i) => (i['status'] ?? 'new') == 'new').length;

    return Scaffold(
      backgroundColor: AppTheme.bgSlate,
      appBar: AppBar(
        title: Column(
          children: [
            Text(
              _school?['name'] ?? 'لوحة إدارة المدرسة',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.verified, color: AppTheme.emerald, size: 14),
                const SizedBox(width: 4),
                Text(
                  _school?['state'] ?? 'السودان',
                  style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                ),
              ],
            ),
          ],
        ),
        leading: IconButton(
          icon: const Icon(Icons.refresh_rounded),
          onPressed: _loadDashboardData,
          tooltip: 'تحديث البيانات',
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: Colors.redAccent),
            onPressed: _logout,
            tooltip: 'تسجيل الخروج',
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppTheme.navy,
          unselectedLabelColor: AppTheme.textMuted,
          indicatorColor: AppTheme.gold,
          indicatorWeight: 3,
          isScrollable: true,
          tabAlignment: TabAlignment.center,
          tabs: [
            Tab(
              child: Row(
                children: [
                  const Text('الطلبات'),
                  if (newInquiries > 0) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.redAccent,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '$newInquiries',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const Tab(text: 'الملف والبيانات'),
            const Tab(text: 'الأخبار والأنشطة'),
            const Tab(text: 'التقييمات'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildInquiriesTab(),
          _buildProfileEditTab(),
          _buildNewsTab(),
          _buildReviewsTab(),
        ],
      ),
    );
  }

  // ==========================================
  // TAB 1: INQUIRIES & ADMISSIONS
  // ==========================================
  Widget _buildInquiriesTab() {
    final filtered = _inquiries.where((iq) {
      if (_inquiryFilter == 'all') return true;
      return (iq['status'] ?? 'new') == _inquiryFilter;
    }).toList();

    return Column(
      children: [
        // Filter Chips
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              _buildFilterChip('all', 'جميع الطلبات (${_inquiries.length})'),
              const SizedBox(width: 8),
              _buildFilterChip('new', 'طلبات جديدة'),
              const SizedBox(width: 8),
              _buildFilterChip('contacted', 'تم التواصل'),
              const SizedBox(width: 8),
              _buildFilterChip('enrolled', 'تم القبول'),
              const SizedBox(width: 8),
              _buildFilterChip('cancelled', 'ملغية'),
            ],
          ),
        ),
        // Inquiries List
        Expanded(
          child: filtered.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.inbox_outlined, size: 54, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      Text(
                        _inquiryFilter == 'all'
                            ? 'لا توجد طلبات قبول أو استفسارات حتى الآن'
                            : 'لا توجد طلبات في هذا التصنيف',
                        style: const TextStyle(color: AppTheme.textMuted),
                      ),
                    ],
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: filtered.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (ctx, idx) {
                    final iq = filtered[idx];
                    return _buildInquiryCard(iq);
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildFilterChip(String key, String label) {
    final isSelected = _inquiryFilter == key;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) => setState(() => _inquiryFilter = key),
      selectedColor: AppTheme.navy,
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : AppTheme.textBody,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 13,
      ),
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: isSelected ? AppTheme.navy : AppTheme.border),
      ),
    );
  }

  Widget _buildInquiryCard(Map<String, dynamic> iq) {
    final status = iq['status'] ?? 'new';
    Color statusColor;
    String statusText;
    switch (status) {
      case 'contacted':
        statusColor = Colors.blue;
        statusText = 'تم التواصل';
        break;
      case 'enrolled':
        statusColor = AppTheme.emerald;
        statusText = 'تم القبول بنجاح';
        break;
      case 'cancelled':
        statusColor = Colors.grey;
        statusText = 'ملغي';
        break;
      case 'new':
      default:
        statusColor = Colors.orange;
        statusText = 'طلب جديد';
        break;
    }

    final parentName = iq['parent_name'] ?? '';
    final parentPhone = iq['parent_phone'] ?? '';
    final studentName = iq['student_name'] ?? '';
    final studentStage = iq['student_stage'] ?? '';
    final studentGrade = iq['student_grade'] ?? '';
    final needsTrans = iq['needs_transportation'] == true;
    final message = iq['message'] ?? '';
    final createdAt = iq['created_at'] ?? '';

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Row: Parent Name & Status Badge
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Row(
                    children: [
                      const CircleAvatar(
                        radius: 18,
                        backgroundColor: AppTheme.surfaceSoft,
                        child: Icon(Icons.person, color: AppTheme.navy, size: 20),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              parentName,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              createdAt,
                              style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    statusText,
                    style: TextStyle(color: statusColor, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const Divider(height: 20),

            // Student Info
            if (studentName.isNotEmpty || studentStage.isNotEmpty) ...[
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: [
                  if (studentName.isNotEmpty)
                    Chip(
                      avatar: const Icon(Icons.school, size: 16, color: AppTheme.navy),
                      label: Text('الطالب: $studentName', style: const TextStyle(fontSize: 12)),
                      backgroundColor: AppTheme.surfaceSoft,
                      padding: EdgeInsets.zero,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  if (studentStage.isNotEmpty)
                    Chip(
                      label: Text('المرحلة: $studentStage', style: const TextStyle(fontSize: 12)),
                      backgroundColor: AppTheme.surfaceSoft,
                      padding: EdgeInsets.zero,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  if (studentGrade.isNotEmpty)
                    Chip(
                      label: Text('الصف: $studentGrade', style: const TextStyle(fontSize: 12)),
                      backgroundColor: AppTheme.surfaceSoft,
                      padding: EdgeInsets.zero,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  if (needsTrans)
                    const Chip(
                      avatar: Icon(Icons.directions_bus, size: 16, color: AppTheme.gold),
                      label: Text('يحتاج ترحيل', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                      backgroundColor: Color(0xFFFEF3C7),
                      padding: EdgeInsets.zero,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                ],
              ),
              const SizedBox(height: 8),
            ],

            // Message / Notes
            if (message.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.surfaceSoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  message,
                  style: const TextStyle(fontSize: 13, color: AppTheme.textBody),
                ),
              ),
              const SizedBox(height: 12),
            ],

            // Action Buttons: WhatsApp, Call, Status
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    icon: const Icon(Icons.chat_bubble_outline, size: 16),
                    label: const Text('واتساب', style: TextStyle(fontSize: 13)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF25D366),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                    onPressed: () => _openWhatsApp(parentPhone, parentName),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.phone, size: 16),
                    label: const Text('اتصال', style: TextStyle(fontSize: 13)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                    onPressed: () => _callPhone(parentPhone),
                  ),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<String>(
                  icon: const Icon(Icons.more_vert),
                  tooltip: 'تغيير الحالة',
                  onSelected: (val) => _updateInquiry(iq['id'], val),
                  itemBuilder: (ctx) => [
                    const PopupMenuItem(value: 'new', child: Text('جديد')),
                    const PopupMenuItem(value: 'contacted', child: Text('تم التواصل')),
                    const PopupMenuItem(value: 'enrolled', child: Text('تم القبول')),
                    const PopupMenuItem(value: 'cancelled', child: Text('إلغاء الطلب')),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ==========================================
  // TAB 2: EDIT PROFILE
  // ==========================================
  Widget _buildProfileEditTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _profileFormKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Note
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFEFF6FF),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFBFDBFE)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline, color: AppTheme.blue, size: 20),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'تحديث بيانات مدرستك ورسومها ينعكس فوراً للجمهور على دليل المدارس وموقع وتطبيق SchoPlus.',
                      style: TextStyle(fontSize: 12, color: AppTheme.navy),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // 1. Basic Info
            const Text('البيانات الأساسية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 12),
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'اسم المدرسة باللغة العربية *'),
              validator: (v) => v == null || v.trim().isEmpty ? 'يرجى إدخال اسم المدرسة' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _nameEnController,
              decoration: const InputDecoration(labelText: 'اسم المدرسة بالإنجليزية (اختياري)'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _aboutController,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'نبذة تعريفية عن المدرسة ورؤيتها *',
                alignLabelWithHint: true,
              ),
              validator: (v) => v == null || v.trim().isEmpty ? 'يرجى كتابة نبذة تعريفية' : null,
            ),
            const SizedBox(height: 24),

            // 2. Contacts
            const Text('بيانات التواصل الرسمية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'هاتف المدرسة *', prefixIcon: Icon(Icons.phone)),
                    validator: (v) => v == null || v.trim().isEmpty ? 'يرجى إدخال الهاتف' : null,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _whatsappController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'رقم الواتساب *', prefixIcon: Icon(Icons.chat)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(labelText: 'البريد الإلكتروني', prefixIcon: Icon(Icons.email)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _websiteController,
                    decoration: const InputDecoration(labelText: 'الموقع الإلكتروني', prefixIcon: Icon(Icons.language)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // 3. Location
            const Text('الموقع الجغرافي والعنوان', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 12),
            TextFormField(
              controller: _addressController,
              decoration: const InputDecoration(labelText: 'العنوان بالتفصيل *', prefixIcon: Icon(Icons.location_on)),
              validator: (v) => v == null || v.trim().isEmpty ? 'يرجى تحديد العنوان' : null,
            ),
            const SizedBox(height: 24),

            // 4. Fees
            const Text('الرسوم الدراسية التقريبية (جنيه سوداني)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _feesMinController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'الحد الأدنى للرسوم', suffixText: 'ج.س'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _feesMaxController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'الحد الأعلى للرسوم', suffixText: 'ج.س'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // 5. Stages & Type
            const Text('المراحل التعليمية المتاحة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _allStages.map((stg) {
                final isSelected = _selectedStages.contains(stg);
                return FilterChip(
                  label: Text(stg),
                  selected: isSelected,
                  selectedColor: AppTheme.gold,
                  checkmarkColor: AppTheme.navy,
                  onSelected: (val) {
                    setState(() {
                      if (val) {
                        _selectedStages.add(stg);
                      } else {
                        _selectedStages.remove(stg);
                      }
                    });
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 24),

            // 6. Facilities
            const Text('المرافق والخدمات المتاحة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _allFacilities.map((fac) {
                final isSelected = _selectedFacilities.contains(fac);
                return FilterChip(
                  label: Text(fac),
                  selected: isSelected,
                  selectedColor: const Color(0xFFDBEAFE),
                  checkmarkColor: AppTheme.blue,
                  onSelected: (val) {
                    setState(() {
                      if (val) {
                        _selectedFacilities.add(fac);
                      } else {
                        _selectedFacilities.remove(fac);
                      }
                    });
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 32),

            // Save Button
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton.icon(
                icon: _isSavingProfile
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: AppTheme.navy, strokeWidth: 2))
                    : const Icon(Icons.check_circle_rounded),
                label: Text(
                  _isSavingProfile ? 'جاري الحفظ...' : 'حفظ ونشر التعديلات فوراً',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                onPressed: _isSavingProfile ? null : _saveProfile,
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  // ==========================================
  // TAB 3: NEWS & POSTS
  // ==========================================
  Widget _buildNewsTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'أخبار المدرسة (${_news.length})',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              ElevatedButton.icon(
                icon: const Icon(Icons.add, size: 18),
                label: const Text('إضافة خبر'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                ),
                onPressed: _showAddNewsDialog,
              ),
            ],
          ),
        ),
        Expanded(
          child: _news.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.newspaper_outlined, size: 54, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text('لم تقم بنشر أي أخبار بعد', style: TextStyle(color: AppTheme.textMuted)),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: _showAddNewsDialog,
                        child: const Text('انشر أول خبر لمدرستك الآن'),
                      ),
                    ],
                  ),
                )
              : ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: _news.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (ctx, idx) {
                    final item = _news[idx];
                    return Card(
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                        side: const BorderSide(color: AppTheme.border),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    item['title'] ?? '',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                                  onPressed: () async {
                                    final confirm = await showDialog<bool>(
                                      context: context,
                                      builder: (c) => AlertDialog(
                                        title: const Text('حذف الخبر'),
                                        content: const Text('هل أنت متأكد من حذف هذا الخبر؟'),
                                        actions: [
                                          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('إلغاء')),
                                          ElevatedButton(
                                            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                                            onPressed: () => Navigator.pop(c, true),
                                            child: const Text('حذف'),
                                          ),
                                        ],
                                      ),
                                    );
                                    if (confirm == true) {
                                      final token = await AuthService.getToken();
                                      if (token != null) {
                                        await ApiService.deleteSchoolNews(token, item['id']);
                                        _loadDashboardData();
                                      }
                                    }
                                  },
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              item['content'] ?? '',
                              style: const TextStyle(fontSize: 13, color: AppTheme.textBody),
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 10),
                            Text(
                              item['published_at'] ?? '',
                              style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  // ==========================================
  // TAB 4: REVIEWS
  // ==========================================
  Widget _buildReviewsTab() {
    return _reviews.isEmpty
        ? Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.star_outline_rounded, size: 54, color: Colors.grey.shade400),
                const SizedBox(height: 12),
                const Text('لا توجد تقييمات مسجلة لمدرستك حتى الآن', style: TextStyle(color: AppTheme.textMuted)),
              ],
            ),
          )
        : ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: _reviews.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (ctx, idx) {
              final rev = _reviews[idx];
              final rating = rev['rating'] ?? 5;
              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: const BorderSide(color: AppTheme.border),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            rev['author'] ?? 'ولي أمر',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                          ),
                          Row(
                            children: List.generate(
                              5,
                              (i) => Icon(
                                i < rating ? Icons.star_rounded : Icons.star_outline_rounded,
                                color: AppTheme.gold,
                                size: 18,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if ((rev['comment'] ?? '').isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          rev['comment'],
                          style: const TextStyle(fontSize: 13, color: AppTheme.textBody),
                        ),
                      ],
                      const SizedBox(height: 8),
                      Text(
                        rev['created_at'] ?? '',
                        style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
  }
}
