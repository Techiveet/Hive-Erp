import 'package:flutter/material.dart';
import 'package:hive_mobile/theme.dart';
import 'package:lucide_icons/lucide_icons.dart';

class NavBarSliver extends StatelessWidget {
  const NavBarSliver({super.key});

  @override
  Widget build(BuildContext context) {
    return SliverAppBar(
      pinned: true,
      floating: true,
      backgroundColor: HiveTheme.background.withOpacity(0.8),
      elevation: 0,
      centerTitle: false,
      title: Row(
        children: [
          const Icon(LucideIcons.globe, color: HiveTheme.primary),
          const SizedBox(width: 8),
          Text(
            'HIVE',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: 2,
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () {},
          child: Text(
            'SIGN IN',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: HiveTheme.primaryForeground,
              letterSpacing: 1,
            ),
          ),
        ),
        Container(
          margin: const EdgeInsets.only(right: 16, left: 8, top: 10, bottom: 10),
          decoration: BoxDecoration(
            color: HiveTheme.primary,
            borderRadius: BorderRadius.circular(4),
            boxShadow: [
              BoxShadow(
                color: HiveTheme.primary.withOpacity(0.3),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () {},
              borderRadius: BorderRadius.circular(4),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Text(
                  'DEPLOY NODE',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: HiveTheme.primaryForeground,
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
