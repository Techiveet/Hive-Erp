import 'package:flutter/material.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class ModulesBentoGridSection extends StatelessWidget {
  const ModulesBentoGridSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 48.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: HiveTheme.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              'ALL-IN-ONE SOLUTION',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: HiveTheme.primary,
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
              style: Theme.of(context).textTheme.displayMedium,
              children: const [
                TextSpan(text: 'Unified '),
                TextSpan(text: 'Ecosystem', style: TextStyle(color: HiveTheme.primary)),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Stop switching between spreadsheets. Hive centralizes every aspect of your Ethiopian business operations.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: HiveTheme.mutedForeground,
            ),
          ),
          const SizedBox(height: 48),

          // Bento Cards as vertical stack for mobile
          _buildPrimaryCard(context),
          const SizedBox(height: 16),
          _buildSecondaryCard(context, 'Inventory Management', 'Multi-branch stock syncing, automated reorder triggers.', LucideIcons.boxes),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(child: _buildSmallCard(context, 'Compliance', 'INSA & NBE aligned.', LucideIcons.shieldCheck)),
              const SizedBox(width: 16),
              Expanded(child: _buildAccentCard(context, 'Real-Time BI', 'Predictive analytics.', LucideIcons.pieChart)),
            ],
          )
        ],
      ),
    );
  }

  Widget _buildPrimaryCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.5),
        border: Border.all(color: HiveTheme.border),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: HiveTheme.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(LucideIcons.wallet, color: HiveTheme.primary, size: 32),
          ),
          const SizedBox(height: 24),
          Text('Intelligent Finance', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(
            'Automated ERCA tax compliance, local bank API integrations for immediate reconciliation.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: HiveTheme.background,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: HiveTheme.border),
            ),
            child: Column(
              children: [
                _buildSyncRow(context, 'Telebirr Sync', 'SUCCESS'),
                const Divider(color: HiveTheme.border, height: 24),
                _buildSyncRow(context, 'VAT Calculation', 'AUTOMATED'),
              ],
            ),
          )
        ],
      ),
    );
  }

  Widget _buildSyncRow(BuildContext context, String title, String status) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.mutedForeground)),
        Text(status, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.green500, fontWeight: FontWeight.bold)),
      ],
    );
  }

  Widget _buildSecondaryCard(BuildContext context, String title, String subtitle, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.5),
        border: Border.all(color: HiveTheme.border),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: HiveTheme.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: HiveTheme.primary, size: 24),
          ),
          const SizedBox(height: 16),
          Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }

  Widget _buildSmallCard(BuildContext context, String title, String subtitle, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.5),
        border: Border.all(color: HiveTheme.border),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: HiveTheme.primary, size: 24),
          const SizedBox(height: 16),
          Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 12)),
        ],
      ),
    );
  }

  Widget _buildAccentCard(BuildContext context, String title, String subtitle, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: HiveTheme.primary,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: HiveTheme.primary.withOpacity(0.3),
            blurRadius: 15,
            offset: const Offset(0, 5),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: HiveTheme.primaryForeground.withOpacity(0.8), size: 24),
          const SizedBox(height: 16),
          Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold, color: HiveTheme.primaryForeground)),
          const SizedBox(height: 4),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 12, color: HiveTheme.primaryForeground.withOpacity(0.8))),
        ],
      ),
    );
  }
}
