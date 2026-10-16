import 'package:flutter/material.dart';
import '../widgets/app_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:share_plus/share_plus.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../services/favorites_service.dart';
import 'admission_dialog.dart';

class SchoolDetailScreen extends StatefulWidget {
  final int schoolId;

  const SchoolDetailScreen({super.key, required this.schoolId});

  @override
  State<SchoolDetailScreen> createState() => _SchoolDetailScreenState();
}

class _SchoolDetailScreenState extends State<SchoolDetailScreen> with SingleTickerProviderStateMixin {
  late Future<School> _schoolFuture;
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _schoolFuture = ApiService.getSchoolDetails(widget.schoolId);
    _tabController = TabController(length: 5, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _launchWhatsApp(School s) async {
    final clean = s.whatsapp.replaceAll(RegExp(r'[^0-9]'), '');
    if (clean.isEmpty) return;
    final text = Uri.encodeComponent('مرحباً بكم إدارة مدرسة ${s.name}، أود الاستفسار عن التسجيل عبر تطبيق دليل المدارس.');
    final url = Uri.parse('https://wa.me/$clean?text=$text');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }

  void _launchPhone(School s) async {
    final clean = s.phone.replaceAll(RegExp(r'[^0-9+]'), '');
    if (clean.isEmpty) return;
    final url = Uri.parse('tel:$clean');
    if (await canLaunchUrl(url)) await launchUrl(url);
  }

  void _launchMap(School s) async {
    if (s.googleMapUrl.isEmpty) return;
    final url = Uri.parse(s.googleMapUrl);
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }

  void _shareSchool(School s) {
    Share.share(
      'تعرّف على مدرسة ${s.name} في ${s.state} - ${s.city} عبر تطبيق دليل المدارس السودانية:\n${s.shareUrl}',
      subject: s.name,
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<School>(
      future: _schoolFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Scaffold(
            body: Center(
              child: CircularProgressIndicator(color: AppTheme.navy),
            ),
          );
        }

        if (snapshot.hasError || !snapshot.hasData) {
          return Scaffold(
            appBar: AppBar(title: const Text('الملف التعريفي')),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline, size: 48, color: Colors.red),
                    const SizedBox(height: 12),
                    Text(
                      snapshot.error.toString().replaceAll('Exception: ', ''),
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => setState(() {
                        _schoolFuture = ApiService.getSchoolDetails(widget.schoolId);
                      }),
                      child: const Text('إعادة المحاولة'),
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        final school = snapshot.data!;

        return Scaffold(
          body: NestedScrollView(
            headerSliverBuilder: (context, innerBoxIsScrolled) {
              return [
                SliverAppBar(
                  expandedHeight: 220,
                  pinned: true,
                  backgroundColor: AppTheme.navy,
                  foregroundColor: Colors.white,
                  actions: [
                    ValueListenableBuilder<List<School>>(
                      valueListenable: FavoritesService.favoritesNotifier,
                      builder: (context, favs, _) {
                        final isFav = favs.any((s) => s.id == school.id);
                        return IconButton(
                          icon: Icon(
                            isFav ? Icons.favorite : Icons.favorite_border,
                            color: isFav ? Colors.red : Colors.white,
                          ),
                          onPressed: () => FavoritesService.toggleFavorite(school),
                        );
                      },
                    ),
                    IconButton(
                      icon: const Icon(Icons.share_outlined),
                      onPressed: () => _shareSchool(school),
                    ),
                  ],
                  flexibleSpace: FlexibleSpaceBar(
                    background: Stack(
                      fit: StackFit.expand,
                      children: [
                        // Cover image or fallback gradient
                        school.cover.isNotEmpty && school.cover.startsWith('http')
                            ? AppNetworkImage(
                                imageUrl: school.cover,
                                fit: BoxFit.cover,
                              )
                            : Container(
                                decoration: const BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.topRight,
                                    end: Alignment.bottomLeft,
                                    colors: [AppTheme.navy, AppTheme.blue],
                                  ),
                                ),
                              ),
                        // Dark overlay gradient
                        Container(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Colors.black.withValues(alpha: 0.3),
                                Colors.black.withValues(alpha: 0.75),
                              ],
                            ),
                          ),
                        ),
                        // Bottom Header Info inside AppBar
                        Positioned(
                          right: 16,
                          left: 16,
                          bottom: 16,
                          child: Row(
                            children: [
                              // School Logo
                              Container(
                                width: 68,
                                height: 68,
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(color: Colors.white, width: 2),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(alpha: 0.2),
                                      blurRadius: 8,
                                    ),
                                  ],
                                ),
                                clipBehavior: Clip.antiAlias,
                                child: school.logo.isNotEmpty && school.logo.startsWith('http')
                                    ? AppNetworkImage(
                                        imageUrl: school.logo,
                                        fit: BoxFit.cover,
                                      )
                                    : const Icon(Icons.school, size: 36, color: AppTheme.blue),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      school.name,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 16.5,
                                        fontWeight: FontWeight.w900,
                                        shadows: [
                                          Shadow(color: Colors.black45, blurRadius: 4),
                                        ],
                                      ),
                                      maxLines: 2,
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        const Icon(Icons.location_on, size: 14, color: AppTheme.gold),
                                        const SizedBox(width: 3),
                                        Expanded(
                                          child: Text(
                                            '${school.state} • ${school.city}',
                                            style: const TextStyle(
                                              color: Colors.white70,
                                              fontSize: 12,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // Contact Actions & Quick Stats Bar
                SliverToBoxAdapter(
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    child: Column(
                      children: [
                        // Quick Action Buttons Row
                        Row(
                          children: [
                            if (school.whatsapp.isNotEmpty)
                              Expanded(
                                child: ElevatedButton.icon(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF25D366),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                  ),
                                  onPressed: () => _launchWhatsApp(school),
                                  icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
                                  label: const Text('واتساب', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                                ),
                              ),
                            if (school.whatsapp.isNotEmpty) const SizedBox(width: 8),
                            if (school.phone.isNotEmpty)
                              Expanded(
                                child: OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AppTheme.navy,
                                    side: const BorderSide(color: AppTheme.navy),
                                    padding: const EdgeInsets.symmetric(vertical: 10),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                  ),
                                  onPressed: () => _launchPhone(school),
                                  icon: const Icon(Icons.phone_outlined, size: 18),
                                  label: const Text('اتصال', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                                ),
                              ),
                            if (school.phone.isNotEmpty) const SizedBox(width: 8),
                            Expanded(
                              flex: school.whatsapp.isNotEmpty && school.phone.isNotEmpty ? 1 : 2,
                              child: ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppTheme.gold,
                                  foregroundColor: AppTheme.navy,
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                ),
                                onPressed: () => AdmissionDialog.show(context, school),
                                icon: const Icon(Icons.assignment_turned_in_outlined, size: 18),
                                label: const Text('طلب قبول', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 12),

                        // Stats Summary Row
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _statBox('التقييم', school.ratingAvg > 0 ? '${school.ratingAvg} ★' : 'جديد', AppTheme.gold),
                            _statBox('الترتيب', school.directoryRank != null ? '#${school.directoryRank}' : '-', AppTheme.navy),
                            _statBox('المشاهدات', '${school.viewsCount}', AppTheme.blue),
                            _statBox('النوع', school.schoolType, AppTheme.emerald),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

                // Persistent Tab Bar
                SliverPersistentHeader(
                  pinned: true,
                  delegate: _SliverAppBarDelegate(
                    TabBar(
                      controller: _tabController,
                      isScrollable: true,
                      labelColor: AppTheme.navy,
                      unselectedLabelColor: AppTheme.textMuted,
                      indicatorColor: AppTheme.gold,
                      indicatorWeight: 3,
                      labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      tabs: const [
                        Tab(text: 'نظرة عامة'),
                        Tab(text: 'الرسوم'),
                        Tab(text: 'المرافق'),
                        Tab(text: 'معرض الصور'),
                        Tab(text: 'الفروع والموقع'),
                      ],
                    ),
                  ),
                ),
              ];
            },
            body: TabBarView(
              controller: _tabController,
              children: [
                _buildOverviewTab(school),
                _buildFeesTab(school),
                _buildFacilitiesTab(school),
                _buildGalleryTab(school),
                _buildBranchesTab(school),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _statBox(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: color)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
      ],
    );
  }

  // 1. Overview Tab
  Widget _buildOverviewTab(School s) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (s.isSchoplusClient)
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [AppTheme.blue, AppTheme.navy]),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Row(
              children: [
                Icon(Icons.verified, color: AppTheme.gold, size: 24),
                SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('شريك مدرسي ذكي معتمد', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                      Text('تستخدم هذه المدرسة منظومة الإدارة الذكية وتتيح متابعة مباشرة لأولياء الأمور.', style: TextStyle(color: Colors.white70, fontSize: 11)),
                    ],
                  ),
                ),
              ],
            ),
          ),

        _infoCard('نبذة عن المدرسة', s.description.isNotEmpty ? s.description : 'لم يتم إضافة وصف للمدرسة حتى الآن.'),
        if (s.vision.isNotEmpty) _infoCard('الرؤية', s.vision),
        if (s.mission.isNotEmpty) _infoCard('الرسالة', s.mission),

        _specCard([
          _specRow('المراحل الدراسية', s.stages.isNotEmpty ? s.stages.join('، ') : 'غير محدد'),
          _specRow('نوع المنهج', s.curriculum),
          _specRow('نوع الطلاب', s.genderType),
          if (s.establishedYear != null) _specRow('سنة التأسيس', s.establishedYear.toString()),
          _specRow('العنوان', s.address.isNotEmpty ? s.address : '${s.state} - ${s.city}'),
        ]),
      ],
    );
  }

  // 2. Fees Tab
  Widget _buildFeesTab(School s) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (s.approxFees.isNotEmpty)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.gold.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppTheme.gold.withValues(alpha: 0.3)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('متوسط الرسوم السنوية', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                const SizedBox(height: 4),
                Text(
                  s.approxFees,
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: AppTheme.navy),
                ),
              ],
            ),
          ),

        const SizedBox(height: 14),

        if (s.stageFees.isNotEmpty) ...[
          const Text('تفاصيل الرسوم حسب المرحلة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 8),
          ...s.stageFees.map((sf) {
            final stage = sf['stage'] ?? sf['name'] ?? '';
            final fee = sf['fee'] ?? sf['amount'] ?? '';
            return Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppTheme.border),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(stage.toString(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  Text(fee.toString(), style: const TextStyle(fontWeight: FontWeight.w800, color: AppTheme.blue, fontSize: 13)),
                ],
              ),
            );
          }),
        ],

        if (s.feeDiscounts.isNotEmpty) ...[
          const SizedBox(height: 14),
          const Text('الخصومات والمنح المتاحة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 8),
          ...s.feeDiscounts.map((d) {
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.local_offer_outlined, color: AppTheme.emerald),
              title: Text(d.toString(), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
            );
          }),
        ],

        if (s.approxFees.isEmpty && s.stageFees.isEmpty)
          const Center(
            child: Padding(
              padding: EdgeInsets.all(40),
              child: Text('يرجى التواصل مع إدارة المدرسة لمعرفة تفاصيل الرسوم والخصومات.', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.textMuted)),
            ),
          ),
      ],
    );
  }

  // 3. Facilities Tab
  Widget _buildFacilitiesTab(School s) {
    if (s.facilities.isEmpty) {
      return const Center(
        child: Text('لم يتم إدراج قائمة المرافق بعد.', style: TextStyle(color: AppTheme.textMuted)),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 2.8,
      ),
      itemCount: s.facilities.length,
      itemBuilder: (context, i) {
        final fac = s.facilities[i];
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppTheme.border),
          ),
          child: Row(
            children: [
              const Icon(Icons.check_circle_outline, color: AppTheme.emerald, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  fac,
                  style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  // 4. Gallery Tab
  Widget _buildGalleryTab(School s) {
    if (s.gallery.isEmpty) {
      return const Center(
        child: Text('لا توجد صور في المعرض حالياً.', style: TextStyle(color: AppTheme.textMuted)),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.2,
      ),
      itemCount: s.gallery.length,
      itemBuilder: (context, i) {
        final url = s.gallery[i];
        return Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppTheme.border),
          ),
          clipBehavior: Clip.antiAlias,
          child: AppNetworkImage(
            imageUrl: url,
            fit: BoxFit.cover,
            placeholder: Container(color: AppTheme.bgSlate),
            errorWidget: const Center(child: Icon(Icons.broken_image)),
          ),
        );
      },
    );
  }

  // 5. Branches Tab
  Widget _buildBranchesTab(School s) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (s.googleMapUrl.isNotEmpty) ...[
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.blue,
              foregroundColor: Colors.white,
            ),
            onPressed: () => _launchMap(s),
            icon: const Icon(Icons.map_outlined),
            label: const Text('فتح موقع المدرسة على خرائط Google'),
          ),
          const SizedBox(height: 16),
        ],

        if (s.branches.isNotEmpty) ...[
          const Text('الفروع الأخرى للمدرسة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 8),
          ...s.branches.map((b) {
            final name = b['name'] ?? b['branch_name'] ?? 'فرع';
            final addr = b['address'] ?? '';
            final phone = b['phone'] ?? '';
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const Icon(Icons.store_mall_directory_outlined, color: AppTheme.navy),
                title: Text(name.toString(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
                subtitle: Text('$addr ${phone.isNotEmpty ? "• $phone" : ""}', style: const TextStyle(fontSize: 12)),
              ),
            );
          }),
        ] else
          Card(
            child: ListTile(
              leading: const Icon(Icons.location_city, color: AppTheme.navy),
              title: const Text('المقر الرئيسي', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
              subtitle: Text(s.address.isNotEmpty ? s.address : '${s.state} - ${s.city}'),
            ),
          ),
      ],
    );
  }

  Widget _infoCard(String title, String content) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: AppTheme.navy)),
          const SizedBox(height: 6),
          Text(content, style: const TextStyle(fontSize: 12.5, color: AppTheme.textBody, height: 1.55)),
        ],
      ),
    );
  }

  Widget _specCard(List<Widget> rows) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(children: rows),
    );
  }

  Widget _specRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 12, color: AppTheme.textMuted)),
          Text(value, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold, color: AppTheme.textHeading)),
        ],
      ),
    );
  }
}

class _SliverAppBarDelegate extends SliverPersistentHeaderDelegate {
  final TabBar _tabBar;

  _SliverAppBarDelegate(this._tabBar);

  @override
  double get minExtent => _tabBar.preferredSize.height;
  @override
  double get maxExtent => _tabBar.preferredSize.height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: Colors.white,
      child: _tabBar,
    );
  }

  @override
  bool shouldRebuild(_SliverAppBarDelegate oldDelegate) {
    return false;
  }
}
