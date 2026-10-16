import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../theme/app_theme.dart';
import 'api_service.dart';

class UpdateService {
  static const String currentAppVersion = '1.1.1';
  static const int currentAppVersionCode = 3;

  // Check for updates
  static Future<void> checkForUpdates(BuildContext context, {bool silent = false}) async {
    try {
      final res = await ApiService.checkUpdate(
        currentVersion: currentAppVersion,
        versionCode: currentAppVersionCode,
      );

      final hasUpdate = res['has_update'] == true;
      if (!context.mounted) return;

      if (hasUpdate) {
        final latestVersion = res['latest_version'] ?? '1.1.0';
        final releaseNotes = res['release_notes'] ?? 'تحديثات وتحسينات جديدة';
        final downloadUrl = res['download_url'] ?? 'https://daleel.schoplus.sd/downloads/daleel-schoplus.apk';
        final fileSize = res['file_size'] ?? '55.9 MB';
        final isCritical = res['is_critical'] == true;

        showDialog(
          context: context,
          barrierDismissible: !isCritical,
          builder: (ctx) => _UpdateDialog(
            latestVersion: latestVersion,
            releaseNotes: releaseNotes,
            downloadUrl: downloadUrl,
            fileSize: fileSize,
            isCritical: isCritical,
          ),
        );
      } else {
        if (!silent) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Row(
                children: [
                  Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
                  SizedBox(width: 8),
                  Text(
                    'أنت تستخدم أحدث إصدار من التطبيق',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),
              backgroundColor: AppTheme.emerald,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          );
        }
      }
    } catch (e) {
      if (!silent && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('تعذر التحقق من التحديثات: $e'),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    }
  }
}

class _UpdateDialog extends StatelessWidget {
  final String latestVersion;
  final String releaseNotes;
  final String downloadUrl;
  final String fileSize;
  final bool isCritical;

  const _UpdateDialog({
    required this.latestVersion,
    required this.releaseNotes,
    required this.downloadUrl,
    required this.fileSize,
    required this.isCritical,
  });

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppTheme.gold.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.system_update_rounded, color: AppTheme.gold, size: 26),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'تحديث جديد متوفر!',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.navy),
                      ),
                      Text(
                        'الإصدار $latestVersion ($fileSize)',
                        style: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.borderSubtle),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'ما الجديد في هذا التحديث:',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.navy),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    releaseNotes,
                    style: const TextStyle(color: AppTheme.textBody, fontSize: 12, height: 1.5),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: () async {
                final uri = Uri.parse(downloadUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
                if (!isCritical && context.mounted) {
                  Navigator.of(context).pop();
                }
              },
              icon: const Icon(Icons.download_rounded, color: Colors.white, size: 20),
              label: const Text('تحديث وتنزيل الآن', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.gold,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
            ),
            if (!isCritical) ...[
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('تذكيري لاحقاً', style: TextStyle(color: AppTheme.textMuted, fontSize: 13)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
