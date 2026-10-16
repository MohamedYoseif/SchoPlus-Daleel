import 'package:flutter/material.dart';
import '../models/home_data.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../widgets/school_card.dart';
import '../widgets/news_card.dart';
import '../widgets/state_chip.dart';
import 'search_screen.dart';

class HomeScreen extends StatefulWidget {
  final Function(String stateName)? onStateSelected;
  final VoidCallback? onSearchTap;

  const HomeScreen({
    super.key,
    this.onStateSelected,
    this.onSearchTap,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<HomeData> _homeFuture;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  void _loadData() {
    _homeFuture = ApiService.getHome();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(
          children: [
            Image.asset(
              'assets/images/logo-icon.png',
              height: 34,
              width: 34,
              errorBuilder: (_, __, ___) => const Icon(Icons.school, color: AppTheme.navy),
            ),
            const SizedBox(width: 10),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'دليل المدارس السودانية',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                    color: AppTheme.navy,
                  ),
                ),
                Text(
                  'ملتقى المدارس وأولياء الأمور في السودان',
                  style: TextStyle(
                    fontSize: 10,
                    color: AppTheme.goldDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.search, color: AppTheme.navy),
            onPressed: widget.onSearchTap ?? () {
              Navigator.push(context, MaterialPageRoute(builder: (_) => const SearchScreen()));
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        color: AppTheme.navy,
        onRefresh: () async {
          setState(() {
            _loadData();
          });
          await _homeFuture;
        },
        child: FutureBuilder<HomeData>(
          future: _homeFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator(color: AppTheme.navy));
            }

            if (snapshot.hasError || !snapshot.hasData) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.wifi_off, size: 48, color: AppTheme.textMuted),
                      const SizedBox(height: 12),
                      Text(
                        snapshot.error.toString().replaceAll('Exception: ', ''),
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () => setState(() => _loadData()),
                        child: const Text('إعادة المحاولة'),
                      ),
                    ],
                  ),
                ),
              );
            }

            final data = snapshot.data!;

            return ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: [
                // Quick Search Bar Card
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                  child: InkWell(
                    onTap: widget.onSearchTap ?? () {
                      Navigator.push(context, MaterialPageRoute(builder: (_) => const SearchScreen()));
                    },
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppTheme.border),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.03),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.search, color: AppTheme.blue, size: 22),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'ابحث باسم المدرسة، المدينة أو الحي...',
                              style: TextStyle(
                                color: AppTheme.textMuted,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          Icon(Icons.tune, color: AppTheme.navy, size: 18),
                        ],
                      ),
                    ),
                  ),
                ),

                // Stats Banner Row
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppTheme.navy, Color(0xFF163870)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _bannerStat(
                          '${data.stats.totalSchools}+',
                          'مدرسة مسجلة',
                          Icons.account_balance_outlined,
                          AppTheme.gold,
                        ),
                        Container(width: 1, height: 32, color: Colors.white24),
                        _bannerStat(
                          '${data.stats.schoplusSchools}',
                          'مدرسة ذكية',
                          Icons.bolt,
                          AppTheme.gold,
                        ),
                        Container(width: 1, height: 32, color: Colors.white24),
                        _bannerStat(
                          '${data.stats.statesCount}',
                          'ولاية مشمولة',
                          Icons.map_outlined,
                          Colors.white,
                        ),
                      ],
                    ),
                  ),
                ),

                // States Horizontal Chips
                if (data.states.isNotEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'استكشف حسب الولاية',
                          style: TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w800,
                            color: AppTheme.navy,
                          ),
                        ),
                        TextButton(
                          onPressed: widget.onSearchTap,
                          child: const Text('عرض الكل', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                  ),
                  SizedBox(
                    height: 38,
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      scrollDirection: Axis.horizontal,
                      itemCount: data.states.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 8),
                      itemBuilder: (context, i) {
                        final st = data.states[i];
                        return StateChip(
                          stateName: st.name,
                          count: st.count,
                          onTap: () {
                            if (widget.onStateSelected != null) {
                              widget.onStateSelected!(st.name);
                            } else {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => SearchScreen(initialState: st.name),
                                ),
                              );
                            }
                          },
                        );
                      },
                    ),
                  ),
                ],

                // Featured / Smart Schools Section
                if (data.schoplusSchools.isNotEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: AppTheme.gold.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Icon(Icons.bolt, color: AppTheme.goldDark, size: 18),
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'المدارس الذكية المتميزة',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: AppTheme.navy,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ...data.schoplusSchools.take(3).map((s) => SchoolCard(school: s)),
                ],

                // Top Rated Schools Section
                if (data.topRatedSchools.isNotEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: AppTheme.blue.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Icon(Icons.star, color: AppTheme.gold, size: 18),
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'المدارس الأعلى تقييماً',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: AppTheme.navy,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ...data.topRatedSchools.take(4).map((s) => SchoolCard(school: s)),
                ],

                // Latest News Section
                if (data.latestNews.isNotEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: AppTheme.emerald.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Icon(Icons.newspaper, color: AppTheme.emerald, size: 18),
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'أحدث أخبار وفعاليات المدارس',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: AppTheme.navy,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ...data.latestNews.take(3).map((nw) => NewsCard(item: nw)),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _bannerStat(String value, String label, IconData icon, Color color) {
    return Column(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 4),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}
