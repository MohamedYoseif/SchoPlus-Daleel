import 'package:flutter/material.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/favorites_service.dart';
import '../widgets/school_card.dart';

class FavoritesScreen extends StatelessWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'المدارس المفضلة',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
        ),
      ),
      body: ValueListenableBuilder<List<School>>(
        valueListenable: FavoritesService.favoritesNotifier,
        builder: (context, favs, _) {
          if (favs.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: Colors.red.withValues(alpha: 0.08),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.favorite_border, size: 36, color: Colors.redAccent),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'لم تقم بحفظ أي مدرسة بعد',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'اضغط على أيقونة القلب في بطاقة أي مدرسة لحفظها في هذه القائمة للرجوع إليها سريعاً بدون إنترنت.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppTheme.textMuted, fontSize: 12.5, height: 1.5),
                    ),
                  ],
                ),
              ),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: favs.length,
            itemBuilder: (context, i) {
              return SchoolCard(school: favs[i]);
            },
          );
        },
      ),
    );
  }
}
