import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class HeroSection extends StatelessWidget {
  const HeroSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 48.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Developer Badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: HiveTheme.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: HiveTheme.primary.withOpacity(0.3)),
              boxShadow: [
                BoxShadow(
                  color: HiveTheme.primary.withOpacity(0.2),
                  blurRadius: 15,
                )
              ],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: HiveTheme.primary,
                    shape: BoxShape.circle,
                  ),
                ).animate(onPlay: (controller) => controller.repeat(reverse: true)).fadeOut(duration: 1.seconds),
                const SizedBox(width: 8),
                Text(
                  'DEVELOPED BY TECHIVE TECHNOLOGY SOLUTIONS',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: HiveTheme.primary,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 1.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),
          
          // Headline
          Text(
            'Unify Your',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.displayLarge?.copyWith(
              height: 1.1,
            ),
          ),
          ShaderMask(
            shaderCallback: (bounds) => const LinearGradient(
              colors: [HiveTheme.primary, Colors.orangeAccent, HiveTheme.primary],
            ).createShader(bounds),
            child: Text(
              'Enterprise Operations',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.displayLarge?.copyWith(
                height: 1.1,
                color: Colors.white, // Needs to be white for ShaderMask
              ),
            ),
          ),
          const SizedBox(height: 24),
          
          // Subtitle
          Text(
            'Hive is the comprehensive ERP solution built for scalable businesses in Ethiopia. Connect your Finance, HR, and Supply Chain with local tax and banking integrations.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: HiveTheme.mutedForeground,
              height: 1.6,
            ),
          ),
          const SizedBox(height: 64),
          
          // Dashboard Preview adapted for mobile
          _buildDashboardPreview(context),
        ],
      ),
    );
  }

  Widget _buildDashboardPreview(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.6),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: HiveTheme.primary.withOpacity(0.3)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.5),
            blurRadius: 50,
            offset: const Offset(0, 20),
          ),
        ],
      ),
      child: Column(
        children: [
          // Browser-like top bar
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: HiveTheme.muted.withOpacity(0.2),
              border: const Border(bottom: BorderSide(color: HiveTheme.border)),
            ),
            child: Row(
              children: [
                const Icon(LucideIcons.activity, color: HiveTheme.primary, size: 16),
                const SizedBox(width: 8),
                Text(
                  'DASHBOARD :: LIVE DATA',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: HiveTheme.primary,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 1.5,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
          
          // Content
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Executive Summary', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 20)),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text('24.5M ETB', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: Colors.white)),
                        Text('GROSS REVENUE (YTD)', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.mutedForeground, fontSize: 10)),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                
                // Stat cards
                _buildStatCard(context, 'ACTIVE LOADS', '142', '12 In Transit', Icons.local_shipping, HiveTheme.green500),
                const SizedBox(height: 12),
                _buildStatCard(context, 'EMPLOYEE HEADCOUNT', '420', 'Across 4 Branches', Icons.people, HiveTheme.mutedForeground),
                const SizedBox(height: 12),
                _buildStatCard(context, 'SYSTEM LATENCY', '12ms', 'Optimal Performance', Icons.speed, HiveTheme.primary),
              ],
            ),
          )
        ],
      ),
    );
  }

  Widget _buildStatCard(BuildContext context, String title, String value, String subtitle, IconData icon, Color accentColor) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.5),
        border: Border.all(color: HiveTheme.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.mutedForeground, fontSize: 10, letterSpacing: 1)),
          const SizedBox(height: 8),
          Text(value, style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold, color: Colors.white)),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(icon, size: 14, color: accentColor),
              const SizedBox(width: 4),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: accentColor, fontSize: 12)),
            ],
          )
        ],
      ),
    );
  }
}
