import 'package:flutter/material.dart';
import 'app_network_image.dart';
import '../models/news_item.dart';
import '../theme/app_theme.dart';
import '../screens/school_detail_screen.dart';

class NewsCard extends StatelessWidget {
  final NewsItem item;

  const NewsCard({super.key, required this.item});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Optional News Banner Image
          if (item.image.isNotEmpty && item.image.startsWith('http'))
            SizedBox(
              height: 160,
              width: double.infinity,
              child: AppNetworkImage(
                imageUrl: item.image,
                fit: BoxFit.cover,
                placeholder: Container(
                  color: AppTheme.bgSlate,
                  child: const Center(
                    child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.blue),
                  ),
                ),
                errorWidget: const SizedBox(),
              ),
            ),

          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // School Header Row
                Row(
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: AppTheme.bgSlate,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: AppTheme.border),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: item.schoolLogo.isNotEmpty && item.schoolLogo.startsWith('http')
                          ? AppNetworkImage(
                              imageUrl: item.schoolLogo,
                              fit: BoxFit.cover,
                              errorWidget: const Icon(
                                Icons.school,
                                size: 16,
                                color: AppTheme.blue,
                              ),
                            )
                          : const Icon(Icons.school, size: 16, color: AppTheme.blue),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.schoolName,
                            style: const TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.navy,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (item.state.isNotEmpty || item.city.isNotEmpty)
                            Text(
                              '${item.state} • ${item.city}',
                              style: const TextStyle(
                                fontSize: 10.5,
                                color: AppTheme.textMuted,
                              ),
                            ),
                        ],
                      ),
                    ),
                    Text(
                      _formatDate(item.publishedAt),
                      style: const TextStyle(
                        fontSize: 10.5,
                        color: AppTheme.textMuted,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 10),

                // News Title
                Text(
                  item.title,
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.textHeading,
                    height: 1.3,
                  ),
                ),

                const SizedBox(height: 6),

                // Content Snippet
                Text(
                  item.content,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppTheme.textBody,
                    height: 1.5,
                  ),
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                ),

                const SizedBox(height: 12),

                // Link to School Profile
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => SchoolDetailScreen(schoolId: item.schoolId),
                        ),
                      );
                    },
                    icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 12, color: AppTheme.blue),
                    label: const Text(
                      'زيارة صفحة المدرسة',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.blue,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(String raw) {
    if (raw.isEmpty) return '';
    try {
      final date = DateTime.parse(raw);
      return '${date.year}/${date.month}/${date.day}';
    } catch (_) {
      return raw.split(' ').first;
    }
  }
}
