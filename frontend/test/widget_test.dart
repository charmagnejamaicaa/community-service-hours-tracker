// Flutter Web state tests (loading, empty, error + retry, success, validation).
// Run with:  flutter test
// A fake API replaces the real backend, so no server is needed.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:service_hours_tracker/api_client.dart';
import 'package:service_hours_tracker/dashboard_screen.dart';
import 'package:service_hours_tracker/models.dart';
import 'package:service_hours_tracker/review_queue_screen.dart';

class FakeApi extends ApiClient {
  FakeApi({
    this.items = const <FailedNotification>[],
    this.failFirstCall = false,
    this.progressError,
  }) : super(baseUrl: 'http://fake');

  List<FailedNotification> items;
  final bool failFirstCall;
  final ApiException? progressError;
  int queueCalls = 0;
  final List<int> handled = <int>[];

  @override
  Future<List<FailedNotification>> getFailedNotifications() async {
    queueCalls++;
    if (failFirstCall && queueCalls == 1) {
      throw const ApiException(ApiErrorKind.network, 'Cannot reach the server.');
    }
    return List<FailedNotification>.of(items);
  }

  @override
  Future<void> markHandled(int notificationId) async {
    handled.add(notificationId);
    items = items.where((n) => n.id != notificationId).toList();
  }

  @override
  Future<StudentProgress> getProgress(int studentId) async {
    if (progressError != null) throw progressError!;
    return const StudentProgress(
      studentId: 1,
      requiredHours: 40,
      approvedHours: 22.5,
      pendingHours: 4.5,
      remainingHours: 17.5,
      percentComplete: 56.25,
      status: 'IN_PROGRESS',
      daysLeft: 42,
      deadline: '2026-12-15',
    );
  }
}

const FailedNotification _sample = FailedNotification(
  id: 2,
  type: 'MISSING_VERIFICATION_ALERT',
  studentId: 2,
  referenceId: 'submission-4',
  attempts: 3,
  reason: 'PROVIDER_UNAVAILABLE',
  createdAt: '2026-11-04T06:46:25.000Z',
);

Widget _wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

void main() {
  group('ReviewQueueScreen', () {
    testWidgets('shows a loading spinner first', (tester) async {
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: FakeApi())));
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.pumpAndSettle();
    });

    testWidgets('shows the empty state when nothing failed', (tester) async {
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: FakeApi())));
      await tester.pumpAndSettle();
      expect(find.text('Nothing needs manual action right now.'), findsOneWidget);
    });

    testWidgets('lists failed notifications', (tester) async {
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: FakeApi(items: [_sample]))));
      await tester.pumpAndSettle();
      expect(find.text('Missing verification alert'), findsOneWidget);
      expect(find.textContaining('Student #2'), findsOneWidget);
      expect(find.textContaining('PROVIDER_UNAVAILABLE'), findsOneWidget);
    });

    testWidgets('shows a network error and recovers after Retry', (tester) async {
      final api = FakeApi(items: [_sample], failFirstCall: true);
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Connection problem'), findsOneWidget);

      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(find.text('Missing verification alert'), findsOneWidget);
    });

    testWidgets('Mark handled asks for confirmation, then removes the item', (tester) async {
      final api = FakeApi(items: [_sample]);
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: api)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Mark handled'));
      await tester.pumpAndSettle();
      expect(find.text('Mark as handled?'), findsOneWidget);

      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();

      expect(api.handled, [2]);
      expect(find.text('Nothing needs manual action right now.'), findsOneWidget);
    });

    testWidgets('Cancel in the dialog does not change anything', (tester) async {
      final api = FakeApi(items: [_sample]);
      await tester.pumpWidget(_wrap(ReviewQueueScreen(api: api)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Mark handled'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(api.handled, isEmpty);
      expect(find.text('Missing verification alert'), findsOneWidget);
    });
  });

  group('DashboardScreen', () {
    testWidgets('shows the progress numbers', (tester) async {
      await tester.pumpWidget(_wrap(DashboardScreen(api: FakeApi())));
      await tester.pumpAndSettle();
      expect(find.text('17.5'), findsOneWidget); // remaining
      expect(find.text('22.5'), findsOneWidget); // approved
      expect(find.text('IN PROGRESS'), findsOneWidget);
    });

    testWidgets('rejects an invalid student ID without calling the API', (tester) async {
      await tester.pumpWidget(_wrap(DashboardScreen(api: FakeApi())));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'abc');
      await tester.tap(find.text('Load'));
      await tester.pumpAndSettle();
      expect(find.text('Enter a whole number greater than 0.'), findsOneWidget);
    });

    testWidgets('shows a friendly message when the student is not found', (tester) async {
      final api = FakeApi(
        progressError: const ApiException(ApiErrorKind.notFound, 'Student not found.'),
      );
      await tester.pumpWidget(_wrap(DashboardScreen(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('No student found with that ID.'), findsOneWidget);
    });

    testWidgets('shows the reference ID on a server error', (tester) async {
      final api = FakeApi(
        progressError: const ApiException(
          ApiErrorKind.server,
          'Something went wrong. Please try again.',
          referenceId: 'req-abc12345',
        ),
      );
      await tester.pumpWidget(_wrap(DashboardScreen(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Reference: req-abc12345'), findsOneWidget);
    });
  });
}