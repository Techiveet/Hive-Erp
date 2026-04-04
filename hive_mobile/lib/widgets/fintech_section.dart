import 'package:flutter/material.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class FintechSection extends StatelessWidget {
  const FintechSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 48.0),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.3),
        border: const Border(
          top: BorderSide(color: HiveTheme.border),
          bottom: BorderSide(color: HiveTheme.border),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: HiveTheme.green500.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              'FINANCIAL ECOSYSTEM',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: HiveTheme.green500,
                fontWeight: FontWeight.bold,
                letterSpacing: 1.2,
                fontSize: 10,
              ),
            ),
          ),
          const SizedBox(height: 16),
          RichText(
            textAlign: TextAlign.center,
            text: TextSpan(
              style: Theme.of(context).textTheme.displayMedium?.copyWith(fontSize: 32),
              children: const [
                TextSpan(text: 'Native '),
                TextSpan(text: 'Payment Gateway', style: TextStyle(color: HiveTheme.green500)),
                TextSpan(text: '\nSync'),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'We understand the Ethiopian financial landscape. Hive bridges the gap between your operational ERP and localized payment processors.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: HiveTheme.mutedForeground,
            ),
          ),
          const SizedBox(height: 48),

          // Cards
          _buildFeatureCard(
            context,
            'Chapa & ArifPay Ready',
            'Connect directly to Ethiopia\'s leading modern payment gateways.',
            LucideIcons.zap,
            HiveTheme.green500,
          ),
          const SizedBox(height: 16),
          _buildFeatureCard(
            context,
            'NBE Criteria Compliant',
            'Our financial modules strictly adhere to the regulatory criteria set by the National Bank of Ethiopia.',
            LucideIcons.building2,
            HiveTheme.blue500,
          ),
          const SizedBox(height: 16),
          _buildFeatureCard(
            context,
            'Multi-Channel Routing',
            'Process payroll directly to CBE, distribute funds via Telebirr, or handle card payments seamlessly.',
            LucideIcons.network,
            HiveTheme.purple500,
          ),
        ],
      ),
    );
  }

  Widget _buildFeatureCard(BuildContext context, String title, String subtitle, IconData icon, Color paramColor) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: HiveTheme.background,
        border: Border.all(color: HiveTheme.border),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.2),
            blurRadius: 10,
            offset: const Offset(0, 5),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: paramColor.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: paramColor, size: 28),
          ),
          const SizedBox(height: 20),
          Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 20)),
          const SizedBox(height: 12),
          Text(subtitle, style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.5)),
        ],
      ),
    );
  }
}
