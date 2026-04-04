import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class HiveTheme {
  static const Color background = Color(0xFF09090b); // Zinc 950
  static const Color card = Color(0xFF18181b); // Zinc 900
  static const Color border = Color(0xFF27272a); // Zinc 800
  static const Color primary = Color(0xFFf97316); // Orange 500
  static const Color primaryForeground = Color(0xFFfafafa); // Zinc 50
  static const Color muted = Color(0xFF27272a);
  static const Color mutedForeground = Color(0xFFa1a1aa); // Zinc 400
  
  static const Color green500 = Color(0xFF22c55e);
  static const Color blue500 = Color(0xFF3b82f6);
  static const Color purple500 = Color(0xFFa855f7);
  static const Color red500 = Color(0xFFef4444);
  static const Color yellow500 = Color(0xFFeab308);

  static ThemeData get darkTheme {
    return ThemeData.dark().copyWith(
      scaffoldBackgroundColor: background,
      primaryColor: primary,
      colorScheme: const ColorScheme.dark(
        primary: primary,
        secondary: Color(0xFF3b82f6),
        surface: card,
        onSurface: primaryForeground,
      ),
      textTheme: GoogleFonts.interTextTheme(ThemeData.dark().textTheme).copyWith(
        displayLarge: GoogleFonts.spaceGrotesk(
          fontSize: 48,
          fontWeight: FontWeight.w900,
          color: primaryForeground,
        ),
        displayMedium: GoogleFonts.spaceGrotesk(
          fontSize: 36,
          fontWeight: FontWeight.w700,
          color: primaryForeground,
        ),
        titleLarge: GoogleFonts.spaceGrotesk(
          fontSize: 24,
          fontWeight: FontWeight.w700,
          color: primaryForeground,
        ),
        bodyLarge: GoogleFonts.inter(
          fontSize: 16,
          color: primaryForeground,
        ),
        bodyMedium: GoogleFonts.inter(
          fontSize: 14,
          color: mutedForeground,
        ),
      ),
    );
  }
}
