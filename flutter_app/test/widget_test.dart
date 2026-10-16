import 'package:flutter_test/flutter_test.dart';
import 'package:daleel_app/main.dart';

void main() {
  testWidgets('DaleelApp smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const DaleelApp());
    expect(find.byType(DaleelApp), findsOneWidget);
  });
}
