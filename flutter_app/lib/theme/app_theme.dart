import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // Brand Color Palette (SchoPlus Ocean & Daleel Identity)
  static const Color navy = Color(0xFF0B2A52);
  static const Color blue = Color(0xFF1E5FA8);
  static const Color gold = Color(0xFFF5A020);
  static const Color goldDark = Color(0xFFD97706);
  static const Color emerald = Color(0xFF10B981);
  static const Color bgSlate = Color(0xFFF8FAFC);
  static const Color cardBg = Color(0xFFFFFFFF);
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderStrong = Color(0xFFCBD5E1);
  static const Color borderSubtle = Color(0xFFE2E8F0);
  static const Color surfaceSoft = Color(0xFFF8FAFC);
  
  static const Color textHeading = Color(0xFF0D1B2E);
  static const Color textBody = Color(0xFF334155);
  static const Color textMuted = Color(0xFF64748B);
  static const Color textLight = Color(0xFF94A3B8);

  static ThemeData get lightTheme {
    final baseText = GoogleFonts.cairoTextTheme();

    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: bgSlate,
      primaryColor: navy,
      colorScheme: const ColorScheme.light(
        primary: navy,
        secondary: gold,
        surface: cardBg,
        error: Color(0xFFEF4444),
        onPrimary: Colors.white,
        onSecondary: navy,
        onSurface: textHeading,
      ),
      textTheme: baseText.copyWith(
        headlineLarge: baseText.headlineLarge?.copyWith(
          color: textHeading,
          fontWeight: FontWeight.w900,
        ),
        headlineMedium: baseText.headlineMedium?.copyWith(
          color: textHeading,
          fontWeight: FontWeight.w800,
        ),
        titleLarge: baseText.titleLarge?.copyWith(
          color: textHeading,
          fontWeight: FontWeight.w800,
        ),
        titleMedium: baseText.titleMedium?.copyWith(
          color: textHeading,
          fontWeight: FontWeight.w700,
        ),
        bodyLarge: baseText.bodyLarge?.copyWith(
          color: textBody,
          fontWeight: FontWeight.w500,
        ),
        bodyMedium: baseText.bodyMedium?.copyWith(
          color: textBody,
          fontWeight: FontWeight.w400,
        ),
        bodySmall: baseText.bodySmall?.copyWith(
          color: textMuted,
          fontWeight: FontWeight.w500,
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: navy,
        elevation: 0,
        centerTitle: true,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: cardBg,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: border, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: gold,
          foregroundColor: navy,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: GoogleFonts.cairo(
            fontWeight: FontWeight.w800,
            fontSize: 15,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: blue, width: 1.5),
        ),
        hintStyle: GoogleFonts.cairo(
          color: textLight,
          fontSize: 14,
        ),
      ),
    );
  }
}
