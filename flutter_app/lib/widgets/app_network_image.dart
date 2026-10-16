import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

/// Lightweight, native network image with loading spinner and error fallback.
/// Uses Flutter's built-in Image.network and image cache without requiring NDK/C++ libraries.
class AppNetworkImage extends StatelessWidget {
  final String imageUrl;
  final BoxFit fit;
  final double? width;
  final double? height;
  final Widget? placeholder;
  final Widget? errorWidget;

  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.placeholder,
    this.errorWidget,
  });

  @override
  Widget build(BuildContext context) {
    if (imageUrl.isEmpty || !imageUrl.startsWith('http')) {
      return errorWidget ??
          Container(
            width: width,
            height: height,
            color: AppTheme.bgSlate,
            child: const Icon(Icons.school, color: AppTheme.blue, size: 28),
          );
    }

    return Image.network(
      imageUrl,
      width: width,
      height: height,
      fit: fit,
      loadingBuilder: (context, child, loadingProgress) {
        if (loadingProgress == null) return child;
        return placeholder ??
            Container(
              width: width,
              height: height,
              color: AppTheme.bgSlate,
              child: const Center(
                child: SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.blue),
                ),
              ),
            );
      },
      errorBuilder: (context, error, stackTrace) {
        return errorWidget ??
            Container(
              width: width,
              height: height,
              color: AppTheme.bgSlate,
              child: const Center(
                child: Icon(Icons.school, color: AppTheme.textMuted, size: 24),
              ),
            );
      },
    );
  }
}
