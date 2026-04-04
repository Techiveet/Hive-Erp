import 'package:flutter/material.dart';
import 'package:hive_mobile/theme.dart';
import 'package:hive_mobile/screens/landing_page.dart';

void main() {
  runApp(const HiveApp());
}

class HiveApp extends StatelessWidget {
  const HiveApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Hive - Enterprise Operations',
      debugShowCheckedModeBanner: false,
      theme: HiveTheme.darkTheme,
      home: const LandingPage(),
    );
  }
}
