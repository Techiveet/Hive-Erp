import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:hive_mobile/theme.dart';
import 'package:hive_mobile/widgets/navbar.dart';
import 'package:hive_mobile/widgets/hero_section.dart';
import 'package:hive_mobile/widgets/modules_bento_grid.dart';
import 'package:hive_mobile/widgets/fintech_section.dart';
import 'package:hive_mobile/widgets/mobility_section.dart';
import 'package:hive_mobile/widgets/hr_section.dart';

class LandingPage extends StatefulWidget {
  const LandingPage({super.key});

  @override
  State<LandingPage> createState() => _LandingPageState();
}

class _LandingPageState extends State<LandingPage> {
  final ScrollController _scrollController = ScrollController();
  bool _showScrollTop = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.offset > 400 && !_showScrollTop) {
        setState(() => _showScrollTop = true);
      } else if (_scrollController.offset <= 400 && _showScrollTop) {
        setState(() => _showScrollTop = false);
      }
    });
  }

  void _scrollToTop() {
    _scrollController.animateTo(
      0,
      duration: const Duration(milliseconds: 500),
      curve: Curves.easeInOut,
    );
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: HiveTheme.background,
      body: Stack(
        children: [
          Positioned.fill(
              child: Opacity(
                  opacity: 0.5,
                  child: CustomPaint(
                    painter: HexagonBackgroundPainter(),
                  ))),

          CustomScrollView(
            controller: _scrollController,
            slivers: [
              const NavBarSliver(),
              SliverList(
                delegate: SliverChildListDelegate([
                  const HeroSection()
                      .animate()
                      .fadeIn(duration: 800.ms)
                      .slideY(begin: 0.2, end: 0),
                  const ModulesBentoGridSection(),
                  const FintechSection(),
                  const MobilitySection(),
                  const HRSection(),
                  const SizedBox(height: 100), // footer padding
                ]),
              ),
            ],
          ),

          if (_showScrollTop)
            Positioned(
              bottom: 24,
              right: 24,
              child: FloatingActionButton(
                backgroundColor: HiveTheme.primary,
                foregroundColor: HiveTheme.primaryForeground,
                onPressed: _scrollToTop,
                child: const Icon(Icons.arrow_upward),
              ).animate().scale(duration: 300.ms),
            ),
        ],
      ),
    );
  }
}

class HexagonBackgroundPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = HiveTheme.primary.withOpacity(0.05)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.0;

    const double radius = 40.0;
    final double hexWidth = math.sqrt(3) * radius;
    final double hexHeight = 2 * radius;

    for (double y = 0; y < size.height + hexHeight; y += hexHeight * 0.75) {
      bool isEven = (y / (hexHeight * 0.75)).round() % 2 == 0;
      for (double x = 0; x < size.width + hexWidth; x += hexWidth) {
        double xOffset = isEven ? 0 : hexWidth / 2;
        _drawHexagon(canvas, paint, Offset(x + xOffset, y), radius);
      }
    }
  }

  void _drawHexagon(Canvas canvas, Paint paint, Offset center, double radius) {
    var path = Path();
    for (int i = 0; i < 6; i++) {
      double angle = (math.pi / 3) * i + (math.pi / 6);
      double x = center.dx + radius * math.cos(angle);
      double y = center.dy + radius * math.sin(angle);
      if (i == 0) {
        path.moveTo(x, y);
      } else {
        path.lineTo(x, y);
      }
    }
    path.close();
    canvas.drawPath(path, paint);
    
    // Technical node point
    final pointPaint = Paint()
      ..color = HiveTheme.primary.withOpacity(0.1)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(center, 1.5, pointPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
