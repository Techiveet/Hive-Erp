import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class HRSection extends StatelessWidget {
  const HRSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 48.0),
      decoration: BoxDecoration(
        color: HiveTheme.card.withOpacity(0.2),
        border: const Border(
          top: BorderSide(color: HiveTheme.border),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: HiveTheme.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              'HUMAN RESOURCES',
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
            text: TextSpan(
              style: Theme.of(context).textTheme.displayMedium?.copyWith(fontSize: 32),
              children: const [
                TextSpan(text: 'Ethiopian \n'),
                TextSpan(text: 'Payroll & Pension', style: TextStyle(color: HiveTheme.primary)),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Managing payroll shouldn\'t require a master\'s degree in tax law. Hive automatically handles ERCA tax brackets and POESSA pension splits for your entire workforce.',
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: HiveTheme.mutedForeground,
            ),
          ),
          const SizedBox(height: 32),
          
          _buildListItem(context, 'Automated Deductions', 'System auto-calculates the progressive income tax tiers and exact pension splits instantly.', LucideIcons.calculator),
          const SizedBox(height: 24),
          _buildListItem(context, 'Compliance Reporting', 'Generate month-end Ministry of Revenue and Pension Agency declaration formats with one click.', LucideIcons.fileText),
          
          const SizedBox(height: 48),
          
          // Payslip Preview
          Center(
            child: Transform.rotate(
              angle: -0.05,
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: HiveTheme.background,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: HiveTheme.border),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.3),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Payslip Generation', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18)),
                        const Icon(LucideIcons.checkCircle2, color: HiveTheme.primary),
                      ],
                    ),
                    const Divider(color: HiveTheme.border, height: 32),
                    _buildPayslipRow(context, 'Gross Salary', '25,000.00 ETB', false),
                    const SizedBox(height: 12),
                    _buildPayslipRow(context, 'Income Tax (ERCA)', '-4,550.00 ETB', true),
                    const SizedBox(height: 12),
                    _buildPayslipRow(context, 'Pension (7% Emp)', '-1,750.00 ETB', true),
                    const Divider(color: HiveTheme.border, height: 32),
                    _buildPayslipRow(context, 'Employer Pension (11%)', '2,750.00 ETB', false, isMuted: true),
                    const Divider(color: HiveTheme.border, height: 32),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Net Pay', style: Theme.of(context).textTheme.titleMedium?.copyWith(color: HiveTheme.primary, fontWeight: FontWeight.bold)),
                        Text('18,700.00 ETB', style: Theme.of(context).textTheme.titleMedium?.copyWith(color: HiveTheme.primary, fontWeight: FontWeight.bold)),
                      ],
                    ),
                  ],
                ),
              ),
            ).animate(onPlay: (controller) => controller.repeat(reverse: true)).moveY(begin: -5, end: 5, duration: 2.seconds),
          )
        ],
      ),
    );
  }

  Widget _buildListItem(BuildContext context, String title, String subtitle, IconData icon) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: HiveTheme.primary, size: 24),
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

  Widget _buildPayslipRow(BuildContext context, String label, String value, bool isDeduction, {bool isMuted = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: HiveTheme.mutedForeground, fontSize: isMuted ? 10 : 12)),
        Text(
          value,
          style: TextStyle(
            fontFamily: 'monospace',
            fontSize: isMuted ? 10 : 12,
            color: isDeduction ? HiveTheme.red500 : (isMuted ? HiveTheme.mutedForeground : Colors.white),
          ),
        ),
      ],
    );
  }
}
