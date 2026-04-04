import 'package:flutter/material.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class MobilitySection extends StatelessWidget {
  const MobilitySection({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 48.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: HiveTheme.blue500.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              'INFRASTRUCTURE MODULES',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: HiveTheme.blue500,
                fontWeight: FontWeight.bold,
                letterSpacing: 1.2,
                fontSize: 10,
              ),
            ),
          ),
          const SizedBox(height: 16),
          RichText(
            text: TextSpan(
              style: Theme.of(context).textTheme.displayMedium?.copyWith(fontSize: 32),
              children: const [
                TextSpan(text: 'Smart Mobility & \n'),
                TextSpan(text: 'Fleet Operations', style: TextStyle(color: HiveTheme.blue500)),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Expand beyond basic tracking. Hive features advanced integration capabilities for municipalities, transit authorities, and logistics giants.',
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: HiveTheme.mutedForeground,
            ),
          ),
          const SizedBox(height: 32),
          
          _buildListItem(context, 'Smart Traffic & Toll Management', 'Automate toll collection and traffic violation processing.', LucideIcons.car, HiveTheme.blue500),
          const SizedBox(height: 24),
          _buildListItem(context, 'EV Dashboard Integration', 'Manage an Electric Vehicle fleet with specialized dashboard modules.', LucideIcons.batteryCharging, HiveTheme.blue500),
          
          const SizedBox(height: 48),
          
          // Mobility UI Preview Placeholder for mobile
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: HiveTheme.card,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: HiveTheme.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Active Tolls', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18)),
                    const Text('842', style: TextStyle(color: HiveTheme.blue500, fontSize: 24, fontWeight: FontWeight.bold)),
                  ],
                ),
                const SizedBox(height: 16),
                _buildTollRow(context, 'A 42315 AA', 'CLEARED', '45.00 ETB', HiveTheme.green500),
                _buildTollRow(context, 'B 19482 OR', 'PENDING', '120.00 ETB', HiveTheme.yellow500),
                _buildTollRow(context, 'EV 00412 AA', 'EXEMPT', '0.00 ETB', HiveTheme.blue500),
              ],
            ),
          )
        ],
      ),
    );
  }

  Widget _buildListItem(BuildContext context, String title, String subtitle, IconData icon, Color color) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: color, size: 20),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.mutedForeground)),
            ],
          ),
        )
      ],
    );
  }

  Widget _buildTollRow(BuildContext context, String plate, String status, String amount, Color statusColor) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: HiveTheme.background,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: HiveTheme.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: HiveTheme.muted,
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(plate, style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.bold, fontSize: 12)),
            ),
            Text(status, style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 10)),
            Text(amount, style: const TextStyle(fontFamily: 'monospace', fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
