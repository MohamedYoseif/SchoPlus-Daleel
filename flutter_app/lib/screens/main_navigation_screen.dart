import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import 'home_screen.dart';
import 'smart_schools_screen.dart';
import 'search_screen.dart';
import 'news_screen.dart';
import 'favorites_screen.dart';
import '../services/update_service.dart';

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;
  String? _preselectedState;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      UpdateService.checkForUpdates(context, silent: true);
    });
  }

  void _onTabSelected(int index) {
    setState(() {
      _currentIndex = index;
    });
  }

  void _onStateSelectedFromHome(String stateName) {
    setState(() {
      _preselectedState = stateName;
      _currentIndex = 2; // Jump to search tab
    });
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      HomeScreen(
        onStateSelected: _onStateSelectedFromHome,
        onSearchTap: () => _onTabSelected(2),
      ),
      const SmartSchoolsScreen(),
      SearchScreen(
        key: ValueKey(_preselectedState ?? 'search_key'),
        initialState: _preselectedState,
      ),
      const NewsScreen(),
      const FavoritesScreen(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 16,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _navItem(0, Icons.home_rounded, Icons.home_outlined, 'الرئيسية'),
                _navItem(1, Icons.bolt_rounded, Icons.bolt_outlined, 'مدارس ذكية'),
                _centerSearchBtn(),
                _navItem(3, Icons.newspaper_rounded, Icons.newspaper_outlined, 'الأخبار'),
                _navItem(4, Icons.favorite_rounded, Icons.favorite_outline_rounded, 'المفضلة'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _navItem(int index, IconData activeIcon, IconData icon, String label) {
    final isSelected = _currentIndex == index;
    return InkWell(
      onTap: () => _onTabSelected(index),
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isSelected ? activeIcon : icon,
              color: isSelected ? AppTheme.blue : AppTheme.textMuted,
              size: 22,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                color: isSelected ? AppTheme.blue : AppTheme.textMuted,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _centerSearchBtn() {
    final isSelected = _currentIndex == 2;
    return GestureDetector(
      onTap: () {
        setState(() {
          _preselectedState = null;
          _currentIndex = 2;
        });
      },
      child: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [AppTheme.navy, AppTheme.blue],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: AppTheme.blue.withValues(alpha: 0.35),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
          border: isSelected ? Border.all(color: AppTheme.gold, width: 2) : null,
        ),
        child: const Icon(
          Icons.search,
          color: Colors.white,
          size: 24,
        ),
      ),
    );
  }
}
