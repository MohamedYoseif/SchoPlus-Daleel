import 'package:flutter/material.dart';
import 'app_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/favorites_service.dart';
import '../screens/school_detail_screen.dart';

class SchoolCard extends StatelessWidget {
  final School school;
  final VoidCallback? onFavoriteToggled;

  const SchoolCard({
    super.key,
    required this.school,
    this.onFavoriteToggled,
  });

  void _launchWhatsApp(BuildContext context) async {
    final cleanPhone = school.whatsapp.replaceAll(RegExp(r'[^0-9]'), '');
    if (cleanPhone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('رقم واتساب غير متوفر لهذه المدرسة')),
      );
      return;
    }
    final text = Uri.encodeComponent(
      'مرحباً بكم إدارة مدرسة ${school.name}، أود الاستفسار عن التسجيل والقبول عبر تطبيق دليل المدارس السودانية.',
    );
    final url = Uri.parse('https://wa.me/$cleanPhone?text=$text');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }

  void _launchCall(BuildContext context) async {
    final cleanPhone = school.phone.replaceAll(RegExp(r'[^0-9+]'), '');
    if (cleanPhone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('رقم الهاتف غير متوفر لهذه المدرسة')),
      );
      return;
    }
    final url = Uri.parse('tel:$cleanPhone');
    if (await canLaunchUrl(url)) {
      await launchUrl(url);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: school.isSchoplusClient
              ? AppTheme.gold.withValues(alpha: 0.5)
              : AppTheme.border,
          width: school.isSchoplusClient ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => SchoolDetailScreen(schoolId: school.id),
              ),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header Row: Logo, Badges, Favorite Button
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // School Logo
                    Container(
                      width: 58,
                      height: 58,
                      decoration: BoxDecoration(
                        color: AppTheme.bgSlate,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.border),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: school.logo.isNotEmpty && school.logo.startsWith('http')
                          ? AppNetworkImage(
                              imageUrl: school.logo,
                              fit: BoxFit.cover,
                              errorWidget: const Icon(
                                Icons.school,
                                color: AppTheme.blue,
                                size: 30,
                              ),
                            )
                          : const Icon(
                              Icons.school,
                              color: AppTheme.blue,
                              size: 30,
                            ),
                    ),
                    const SizedBox(width: 12),

                    // Title & Location
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            school.name,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: AppTheme.textHeading,
                              height: 1.25,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              const Icon(
                                Icons.location_on_outlined,
                                size: 14,
                                color: AppTheme.textMuted,
                              ),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  '${school.state} • ${school.city}${school.district.isNotEmpty ? ' • ${school.district}' : ''}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppTheme.textMuted,
                                    fontWeight: FontWeight.w500,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    // Favorite Toggle
                    ValueListenableBuilder<List<School>>(
                      valueListenable: FavoritesService.favoritesNotifier,
                      builder: (context, favs, _) {
                        final isFav = favs.any((s) => s.id == school.id);
                        return IconButton(
                          icon: Icon(
                            isFav ? Icons.favorite : Icons.favorite_border,
                            color: isFav ? Colors.red : AppTheme.textMuted,
                            size: 22,
                          ),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                          onPressed: () async {
                            await FavoritesService.toggleFavorite(school);
                            onFavoriteToggled?.call();
                          },
                        );
                      },
                    ),
                  ],
                ),

                const SizedBox(height: 10),

                // Tags: Stages, Type, Curriculum & SchoPlus Badge
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  children: [
                    if (school.isSchoplusClient)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [AppTheme.blue, AppTheme.navy],
                          ),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.bolt, size: 12, color: AppTheme.gold),
                            SizedBox(width: 3),
                            Text(
                              'مدرسة ذكية',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    _tagChip(school.schoolType, AppTheme.blue),
                    _tagChip(school.curriculum, AppTheme.textMuted),
                    if (school.stages.isNotEmpty)
                      _tagChip(school.stages.first, AppTheme.emerald),
                  ],
                ),

                if (school.description.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    school.description,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppTheme.textBody,
                      height: 1.4,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],

                const SizedBox(height: 12),
                const Divider(height: 1, color: AppTheme.border),
                const SizedBox(height: 10),

                // Footer Row: Rating / Rank & Action Buttons
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Rating & Rank
                    Row(
                      children: [
                        const Icon(Icons.star_rounded, color: AppTheme.gold, size: 18),
                        const SizedBox(width: 3),
                        Text(
                          school.ratingAvg > 0 ? school.ratingAvg.toStringAsFixed(1) : 'جديد',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.textHeading,
                          ),
                        ),
                        if (school.reviewsCount > 0)
                          Text(
                            ' (${school.reviewsCount})',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppTheme.textMuted,
                            ),
                          ),
                        if (school.directoryRank != null) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                            decoration: BoxDecoration(
                              color: AppTheme.navy.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              '#${school.directoryRank}',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.navy,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),

                    // Action Buttons (WhatsApp & Call)
                    Row(
                      children: [
                        if (school.whatsapp.isNotEmpty)
                          _actionIconButton(
                            icon: Icons.chat_bubble_outline_rounded,
                            color: const Color(0xFF25D366),
                            bgColor: const Color(0xFFE8F5E9),
                            onTap: () => _launchWhatsApp(context),
                            tooltip: 'واتساب',
                          ),
                        const SizedBox(width: 8),
                        if (school.phone.isNotEmpty)
                          _actionIconButton(
                            icon: Icons.phone_outlined,
                            color: AppTheme.blue,
                            bgColor: const Color(0xFFEFF6FF),
                            onTap: () => _launchCall(context),
                            tooltip: 'اتصال',
                          ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.gold,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'التفاصيل',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.navy,
                                ),
                              ),
                              SizedBox(width: 2),
                              Icon(Icons.arrow_forward_ios_rounded, size: 10, color: AppTheme.navy),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _tagChip(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 10.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _actionIconButton({
    required IconData icon,
    required Color color,
    required Color bgColor,
    required VoidCallback onTap,
    required String tooltip,
  }) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: color, size: 16),
        ),
      ),
    );
  }
}
