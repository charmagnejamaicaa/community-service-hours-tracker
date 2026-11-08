// Flutter Web tests (loading, empty, error + retry, success, validation, workflow, sign-in, roles, token handling).
// Run with:  flutter test
// A fake API replaces the real backend, so no server is needed.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'package:service_hours_tracker/api_client.dart';
import 'package:service_hours_tracker/auth.dart';
import 'package:service_hours_tracker/dashboard_screen.dart';
import 'package:service_hours_tracker/main.dart';
import 'package:service_hours_tracker/models.dart';
import 'package:service_hours_tracker/progress_summary_screen.dart';
import 'package:service_hours_tracker/register_activity_screen.dart';
import 'package:service_hours_tracker/review_queue_screen.dart';
import 'package:service_hours_tracker/submission_queue_screen.dart';
import 'package:service_hours_tracker/submit_hours_screen.dart';

class FakeApi extends ApiClient {
  FakeApi({
    this.items = const <FailedNotification>[],
    this.failFirstCall = false,
    this.progressError,
    this.submissions = const <Submission>[],
    this.actionError,
    this.loginRole = UserRole.student,
    this.loginError,
  }) : super(baseUrl: 'http://fake');

  final UserRole loginRole;
  final ApiException? loginError;

  @override
  Future<AuthUser> login(String email, String password) async {
    calls.add('login:$email');
    if (loginError != null) throw loginError!;
    return AuthUser(
      id: 1,
      name: 'Sample ${roleLabel(loginRole)}',
      role: loginRole,
      studentId: loginRole == UserRole.student ? 1 : null,
    );
  }

  @override
  Future<void> logout() async {
    calls.add('logout');
  }

  /// Pretends the server answered 401 to a normal request.
  void expireSession() => onSessionExpired?.call();

  List<Submission> submissions;
  final ApiException? actionError;
  final List<String> calls = <String>[];

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
  Future<List<Submission>> listSubmissions(String status) async {
    calls.add('list:$status');
    return submissions.where((s) => s.status == status).toList();
  }

  void _removeSubmission(int id) {
    submissions = submissions.where((s) => s.id != id).toList();
  }

  @override
  Future<void> verifySubmission(int submissionId) async {
    if (actionError != null) throw actionError!;
    calls.add('verify:$submissionId');
    _removeSubmission(submissionId);
  }

  @override
  Future<void> rejectSubmission(int submissionId, String reason) async {
    if (actionError != null) throw actionError!;
    calls.add('reject:$submissionId:$reason');
    _removeSubmission(submissionId);
  }

  @override
  Future<ApprovalResult> approve(int submissionId) async {
    if (actionError != null) throw actionError!;
    calls.add('approve:$submissionId');
    _removeSubmission(submissionId);
    return ApprovalResult(id: submissionId, studentId: 1, remainingHours: 12.5, progressStatus: 'IN_PROGRESS');
  }

  @override
  Future<void> returnForCorrection(int submissionId, String reason) async {
    if (actionError != null) throw actionError!;
    calls.add('return:$submissionId:$reason');
    _removeSubmission(submissionId);
  }

  @override
  Future<Activity> registerActivity({
    required int studentId,
    required String name,
    required String organization,
    required String activityDate,
  }) async {
    if (actionError != null) throw actionError!;
    calls.add('register:$studentId');
    return Activity(
      id: 7,
      studentId: studentId,
      name: name,
      organization: organization,
      activityDate: activityDate,
    );
  }

  @override
  Future<int> submitHours({
    required int activityId,
    required double hours,
    required String serviceDate,
    required String evidenceNote,
  }) async {
    if (actionError != null) throw actionError!;
    calls.add('submit:$activityId:$hours');
    return 21;
  }

  @override
  Future<void> resubmit({
    required int submissionId,
    required double hours,
    required String serviceDate,
    required String evidenceNote,
  }) async {
    if (actionError != null) throw actionError!;
    calls.add('resubmit:$submissionId');
  }

  @override
  Future<ProgressSummary> getProgressSummary() async {
    return const ProgressSummary(
      totals: SummaryTotals(
        students: 2,
        completed: 0,
        inProgress: 1,
        overdue: 1,
        approvedHours: 30,
        remainingHours: 50,
      ),
      students: [
        StudentProgress(
          studentId: 1,
          requiredHours: 40,
          approvedHours: 22.5,
          pendingHours: 0,
          remainingHours: 17.5,
          percentComplete: 56.25,
          status: 'IN_PROGRESS',
          daysLeft: 42,
          deadline: '2026-12-15',
        ),
        StudentProgress(
          studentId: 2,
          requiredHours: 40,
          approvedHours: 7.5,
          pendingHours: 0,
          remainingHours: 32.5,
          percentComplete: 18.75,
          status: 'OVERDUE',
          daysLeft: -4,
          deadline: '2026-11-01',
        ),
      ],
    );
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

Submission _submission(int id, String status) => Submission(
      id: id,
      activityId: 3,
      studentId: 1,
      hours: 4.5,
      serviceDate: '2026-11-03',
      evidenceNote: 'Signed attendance sheet',
      status: status,
    );

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(800, 1600);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

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

    testWidgets('a signed-in student sees their own progress and no ID box', (tester) async {
      await tester.pumpWidget(_wrap(DashboardScreen(api: FakeApi(), studentId: 1)));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsNothing);
      expect(find.text('17.5'), findsOneWidget);
    });

    testWidgets('a forbidden answer is shown as "Not allowed"', (tester) async {
      final api = FakeApi(
        progressError: const ApiException(ApiErrorKind.forbidden, 'You are not allowed to do that.'),
      );
      await tester.pumpWidget(_wrap(DashboardScreen(api: api, studentId: 2)));
      await tester.pumpAndSettle();
      expect(find.text('Not allowed'), findsOneWidget);
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

  group('SubmissionQueueScreen (verification)', () {
    testWidgets('shows the empty state', (tester) async {
      await tester.pumpWidget(
          _wrap(SubmissionQueueScreen(api: FakeApi(), mode: QueueMode.verification)));
      await tester.pumpAndSettle();
      expect(find.text('No submissions are waiting for verification.'), findsOneWidget);
    });

    testWidgets('lists waiting submissions', (tester) async {
      final api = FakeApi(submissions: [_submission(5, 'SUBMITTED'), _submission(6, 'VERIFIED')]);
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.verification)));
      await tester.pumpAndSettle();
      expect(find.text('Submission #5'), findsOneWidget);
      expect(find.text('Submission #6'), findsNothing);
      expect(api.calls, contains('list:SUBMITTED'));
    });

    testWidgets('Verify removes the item', (tester) async {
      final api = FakeApi(submissions: [_submission(5, 'SUBMITTED')]);
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.verification)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Verify'));
      await tester.pumpAndSettle();

      expect(api.calls, contains('verify:5'));
      expect(find.text('Submission #5'), findsNothing);
      expect(find.text('Submission #5 verified.'), findsOneWidget);
    });

    testWidgets('Reject needs a reason', (tester) async {
      final api = FakeApi(submissions: [_submission(5, 'SUBMITTED')]);
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.verification)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Reject'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(find.text('A reason is required.'), findsOneWidget);
      expect(api.calls.where((c) => c.startsWith('reject')), isEmpty);

      await tester.enterText(find.byType(TextField), 'Evidence unclear');
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(api.calls, contains('reject:5:Evidence unclear'));
      expect(find.text('Submission #5'), findsNothing);
    });

    testWidgets('a conflict reloads the list and tells the user', (tester) async {
      final api = FakeApi(
        submissions: [_submission(5, 'SUBMITTED')],
        actionError: const ApiException(ApiErrorKind.conflict, 'Submission is no longer waiting.'),
      );
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.verification)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Verify'));
      await tester.pumpAndSettle();

      expect(find.textContaining('no longer waiting'), findsOneWidget);
      expect(api.calls.where((c) => c.startsWith('list')).length, 2);
    });
  });

  group('SubmissionQueueScreen (approval)', () {
    testWidgets('shows the empty state', (tester) async {
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: FakeApi(), mode: QueueMode.approval)));
      await tester.pumpAndSettle();
      expect(find.text('No verified submissions are waiting for approval.'), findsOneWidget);
    });

    testWidgets('Approve shows the remaining hours', (tester) async {
      final api = FakeApi(submissions: [_submission(8, 'VERIFIED')]);
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.approval)));
      await tester.pumpAndSettle();
      expect(api.calls, contains('list:VERIFIED'));

      await tester.tap(find.text('Approve'));
      await tester.pumpAndSettle();

      expect(api.calls, contains('approve:8'));
      expect(find.textContaining('12.5 hours remaining'), findsOneWidget);
      expect(find.text('Submission #8'), findsNothing);
    });

    testWidgets('Return for correction sends the reason', (tester) async {
      final api = FakeApi(submissions: [_submission(8, 'VERIFIED')]);
      await tester.pumpWidget(_wrap(SubmissionQueueScreen(api: api, mode: QueueMode.approval)));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Return for correction'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Wrong date');
      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();

      expect(api.calls, contains('return:8:Wrong date'));
    });
  });

  group('Register activity and submit hours', () {
    testWidgets('register: shows validation problems and does not call the API', (tester) async {
      _tall(tester);
      final api = FakeApi();
      await tester.pumpWidget(_wrap(RegisterActivityScreen(api: api, studentId: 1)));
      await tester.tap(find.text('Register activity').last);
      await tester.pumpAndSettle();
      expect(find.textContaining('Activity name is required.'), findsOneWidget);
      expect(find.textContaining('Organization is required.'), findsOneWidget);
      expect(api.calls, isEmpty);
    });

    testWidgets('register: success shows the new activity ID', (tester) async {
      _tall(tester);
      final api = FakeApi();
      await tester.pumpWidget(_wrap(RegisterActivityScreen(api: api, studentId: 1)));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'Tree planting');
      await tester.enterText(fields.at(1), 'Barangay Council');
      await tester.enterText(fields.at(2), '2026-11-03');
      await tester.tap(find.text('Register activity').last);
      await tester.pumpAndSettle();
      expect(api.calls, contains('register:1')); // the student ID comes from the sign-in, not from a text box
      expect(find.textContaining('Your activity ID is 7'), findsOneWidget);
    });

    testWidgets('submit: rejects hours above 12', (tester) async {
      _tall(tester);
      final api = FakeApi();
      await tester.pumpWidget(_wrap(SubmitHoursScreen(api: api)));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), '3');
      await tester.enterText(fields.at(1), '13');
      await tester.enterText(fields.at(2), '2026-11-03');
      await tester.enterText(fields.at(3), 'Attendance sheet');
      await tester.tap(find.text('Submit hours').last);
      await tester.pumpAndSettle();
      expect(find.textContaining('Hours must be greater than 0 and at most 12.'), findsOneWidget);
      expect(api.calls, isEmpty);
    });

    testWidgets('submit: success shows the submission ID', (tester) async {
      _tall(tester);
      final api = FakeApi();
      await tester.pumpWidget(_wrap(SubmitHoursScreen(api: api)));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), '3');
      await tester.enterText(fields.at(1), '4.5');
      await tester.enterText(fields.at(2), '2026-11-03');
      await tester.enterText(fields.at(3), 'Attendance sheet');
      await tester.tap(find.text('Submit hours').last);
      await tester.pumpAndSettle();
      expect(api.calls, contains('submit:3:4.5'));
      expect(find.textContaining('Your submission ID is 21'), findsOneWidget);
    });

    testWidgets('submit: a server validation error is shown in the form', (tester) async {
      _tall(tester);
      final api = FakeApi(
        actionError: const ApiException(
          ApiErrorKind.validation,
          'Please fix the problems below.',
          details: <String>['Total hours for that date would exceed 12.'],
        ),
      );
      await tester.pumpWidget(_wrap(SubmitHoursScreen(api: api)));
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), '3');
      await tester.enterText(fields.at(1), '4.5');
      await tester.enterText(fields.at(2), '2026-11-03');
      await tester.enterText(fields.at(3), 'Attendance sheet');
      await tester.tap(find.text('Submit hours').last);
      await tester.pumpAndSettle();
      expect(find.textContaining('would exceed 12'), findsOneWidget);
    });
  });

  group('ProgressSummaryScreen', () {
    testWidgets('shows totals and one row per student', (tester) async {
      await tester.pumpWidget(_wrap(ProgressSummaryScreen(api: FakeApi())));
      await tester.pumpAndSettle();
      expect(find.text('Approved hours'), findsOneWidget);
      expect(find.text('Student #1'), findsOneWidget);
      expect(find.text('Student #2'), findsOneWidget);
      expect(find.textContaining('4 days overdue'), findsOneWidget);
    });
  });

  group('Sign in and roles', () {
    Future<void> signIn(WidgetTester tester, FakeApi api) async {
      await tester.pumpWidget(ServiceHoursApp(api: api));
      await tester.pumpAndSettle();
      final fields = find.byType(TextField);
      await tester.enterText(fields.at(0), 'someone@example.test');
      await tester.enterText(fields.at(1), 'a-long-demo-password');
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pumpAndSettle();
    }

    testWidgets('the app starts on the login screen, not on a role picker', (tester) async {
      await tester.pumpWidget(ServiceHoursApp(api: FakeApi()));
      await tester.pumpAndSettle();
      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
      expect(find.byType(DropdownButton<UserRole>), findsNothing);
      expect(find.text('Register activity'), findsNothing);
    });

    testWidgets('empty fields show a message and do not call the API', (tester) async {
      final api = FakeApi();
      await tester.pumpWidget(ServiceHoursApp(api: api));
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Enter your email address.'), findsOneWidget);
      expect(find.textContaining('Enter your password.'), findsOneWidget);
      expect(api.calls, isEmpty);
    });

    testWidgets('the password field hides what is typed', (tester) async {
      await tester.pumpWidget(ServiceHoursApp(api: FakeApi()));
      final password = tester.widget<TextField>(find.byType(TextField).at(1));
      expect(password.obscureText, isTrue);
    });

    testWidgets('a wrong password shows the error and stays on the login screen', (tester) async {
      final api = FakeApi(
        loginError: const ApiException(
          ApiErrorKind.unauthorized,
          'Email or password is incorrect.',
          referenceId: 'req-login01',
        ),
      );
      await signIn(tester, api);
      expect(find.textContaining('Email or password is incorrect.'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
      expect(find.text('Account'), findsNothing);
    });

    testWidgets('too many attempts shows the rate limit message', (tester) async {
      final api = FakeApi(
        loginError: const ApiException(
          ApiErrorKind.rateLimited,
          'Too many attempts. Please wait and try again.',
        ),
      );
      await signIn(tester, api);
      expect(find.textContaining('Too many attempts'), findsOneWidget);
    });

    testWidgets('a student sees student screens and their own progress', (tester) async {
      await signIn(tester, FakeApi(loginRole: UserRole.student));
      expect(find.text('Register activity'), findsWidgets);
      expect(find.text('Submit hours'), findsWidgets);
      expect(find.text('Account'), findsWidgets);
      expect(find.text('Verification queue'), findsNothing);
      expect(find.text('Approval'), findsNothing);
      expect(find.text('Student Dashboard'), findsOneWidget);
      expect(find.text('17.5'), findsOneWidget); // loaded without typing a student ID
    });

    testWidgets('a supervisor sees only the verification queue and the account', (tester) async {
      final api = FakeApi(loginRole: UserRole.supervisor, submissions: [_submission(5, 'SUBMITTED')]);
      await signIn(tester, api);
      expect(find.text('Verification queue'), findsWidgets);
      expect(find.text('Submission #5'), findsOneWidget);
      expect(find.text('Dashboard'), findsNothing);
      expect(find.text('Summary'), findsNothing);
      expect(find.text('Approval'), findsNothing);
    });

    testWidgets('a coordinator sees approval, review queue and summary', (tester) async {
      await signIn(tester, FakeApi(loginRole: UserRole.coordinator));
      expect(find.text('Approval'), findsWidgets);
      expect(find.text('Review queue'), findsOneWidget);
      expect(find.text('Summary'), findsOneWidget);
      expect(find.text('Verification queue'), findsNothing);
    });

    testWidgets('an adviser sees read-only screens only', (tester) async {
      await signIn(tester, FakeApi(loginRole: UserRole.adviser));
      expect(find.text('Dashboard'), findsWidgets);
      expect(find.text('Summary'), findsOneWidget);
      expect(find.text('Approval'), findsNothing);
      expect(find.text('Review queue'), findsNothing);
      expect(find.text('Register activity'), findsNothing);
    });

    testWidgets('Sign out asks the server, then returns to the login screen', (tester) async {
      final api = FakeApi(loginRole: UserRole.supervisor);
      await signIn(tester, api);

      await tester.tap(find.text('Account').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();

      expect(api.calls, contains('logout'));
      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
      expect(find.text('You are signed out.'), findsOneWidget);
      expect(find.text('Verification queue'), findsNothing);
    });

    testWidgets('an expired session returns to the login screen with a message', (tester) async {
      final api = FakeApi(loginRole: UserRole.coordinator);
      await signIn(tester, api);
      expect(find.text('Approval'), findsWidgets);

      api.expireSession();
      await tester.pumpAndSettle();

      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
      expect(find.text('Your session ended. Please sign in again.'), findsOneWidget);
      expect(find.text('Approval'), findsNothing);
    });
  });

  group('ApiClient token handling', () {
    const loginAnswer =
        '{"token":"csht_abc","tokenType":"Bearer","expiresAt":"2030-01-01T00:00:00+00:00",'
        '"user":{"id":1,"name":"Sample Student One","role":"student","studentId":1}}';

    test('login sends no token, then later requests send it as a Bearer header', () async {
      final seen = <Map<String, String>>[];
      final client = MockClient((request) async {
        seen.add(Map<String, String>.of(request.headers));
        if (request.url.path == '/api/auth/login') return http.Response(loginAnswer, 200);
        return http.Response('{"items":[]}', 200);
      });
      final api = ApiClient(client: client, baseUrl: 'http://test');

      final user = await api.login('student1@example.test', 'pw');
      expect(user.role, UserRole.student);
      expect(user.studentId, 1);
      expect(api.isSignedIn, isTrue);
      expect(seen.first.containsKey('Authorization'), isFalse);

      await api.getFailedNotifications();
      expect(seen.last['Authorization'], 'Bearer csht_abc');
    });

    test('a 401 on a normal request forgets the token and tells the app', () async {
      final client = MockClient((request) async {
        if (request.url.path == '/api/auth/login') return http.Response(loginAnswer, 200);
        return http.Response(
          '{"error":{"code":"UNAUTHENTICATED","message":"Please sign in again.","referenceId":"req-1"}}',
          401,
        );
      });
      final api = ApiClient(client: client, baseUrl: 'http://test');
      var expired = 0;
      api.onSessionExpired = () => expired++;

      await api.login('student1@example.test', 'pw');
      await expectLater(
        api.getFailedNotifications(),
        throwsA(isA<ApiException>().having((e) => e.kind, 'kind', ApiErrorKind.unauthorized)),
      );
      expect(expired, 1);
      expect(api.isSignedIn, isFalse);
      expect(api.currentUser, isNull);
    });

    test('a 401 on login (wrong password) does not count as an expired session', () async {
      final client = MockClient((request) async => http.Response(
            '{"error":{"code":"INVALID_CREDENTIALS","message":"Email or password is incorrect.","referenceId":"req-2"}}',
            401,
          ));
      final api = ApiClient(client: client, baseUrl: 'http://test');
      var expired = 0;
      api.onSessionExpired = () => expired++;

      await expectLater(api.login('a@example.test', 'bad'), throwsA(isA<ApiException>()));
      expect(expired, 0);
      expect(api.isSignedIn, isFalse);
    });

    test('a 429 becomes a rateLimited error', () async {
      final client = MockClient((request) async => http.Response(
            '{"error":{"code":"RATE_LIMITED","message":"Too many attempts. Please wait and try again.","referenceId":"req-3"}}',
            429,
          ));
      final api = ApiClient(client: client, baseUrl: 'http://test');
      await expectLater(
        api.login('a@example.test', 'bad'),
        throwsA(isA<ApiException>().having((e) => e.kind, 'kind', ApiErrorKind.rateLimited)),
      );
    });

    test('logout forgets the token even when the server cannot be reached', () async {
      var down = false;
      final client = MockClient((request) async {
        if (down) throw http.ClientException('offline');
        return http.Response(loginAnswer, 200);
      });
      final api = ApiClient(client: client, baseUrl: 'http://test');
      await api.login('student1@example.test', 'pw');
      expect(api.isSignedIn, isTrue);

      down = true;
      await api.logout();
      expect(api.isSignedIn, isFalse);
    });
  });
}
