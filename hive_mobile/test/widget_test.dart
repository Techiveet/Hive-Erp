import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive_mobile/main.dart';

void main() {
  group('HiveApp Smoke Tests', () {
    testWidgets('LandingPage renders all key sections', (WidgetTester tester) async {
      // Set a large surface size so all sections are built and "visible" in the tree
      await tester.binding.setSurfaceSize(const Size(1000, 5000));

      // Build our app and trigger a frame.
      await tester.pumpWidget(const HiveApp());
      await tester.pump(const Duration(milliseconds: 500));

      // 1. Verify NavBar + Hero (Initial View)
      expect(find.text('HIVE'), findsOneWidget);
      expect(find.text('Unify Your'), findsOneWidget);

      // 2. Verify subsequent sections
      expect(find.text('FINANCIAL ECOSYSTEM'), findsOneWidget);
      expect(find.text('INFRASTRUCTURE MODULES'), findsOneWidget);
      expect(find.text('HUMAN RESOURCES'), findsOneWidget);
      
      // Reset surface size
      await tester.binding.setSurfaceSize(null);
    });

    testWidgets('Scroll to top button functional test', (WidgetTester tester) async {
      await tester.pumpWidget(const HiveApp());
      await tester.pump(const Duration(milliseconds: 500));

      final scrollable = find.byType(CustomScrollView);
      
      // Initially, the scroll-to-top button should not be visible
      expect(find.byIcon(Icons.arrow_upward), findsNothing);

      // Scroll down enough to show the button (> 400px)
      await tester.drag(scrollable, const Offset(0, -1000));
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(); // Frame for state update

      // Now the button should be visible
      expect(find.byIcon(Icons.arrow_upward), findsOneWidget);

      // Tap the button
      await tester.tap(find.byIcon(Icons.arrow_upward));
      
      // The animation takes 500ms. Pump several times to process the animation.
      for (int i = 0; i < 10; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      await tester.pump(); // Final frame

      // Button should be hidden again after scrolling to top
      expect(find.byIcon(Icons.arrow_upward), findsNothing);
    });
  });
}
