// The ONLY place that talks to the backend. If an endpoint changes, only this file changes.
//
// Week 13: the role is sent in the demo header X-Demo-Role. Real sign-in replaces it in Week 14.

import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';

enum ApiErrorKind { network, validation, forbidden, notFound, conflict, server }

/// Every failure from the backend is turned into one of these, so screens can show
/// the right message (network error, server error with a reference ID, and so on).
class ApiException implements Exception {
  const ApiException(
    this.kind,
    this.message, {
    this.referenceId,
    this.details = const <String>[],
  });

  final ApiErrorKind kind;
  final String message;
  final String? referenceId; // matches the referenceId in the backend logs
  final List<String> details; // every validation problem the server found

  @override
  String toString() => 'ApiException($kind, $message, $referenceId)';
}

class ApiClient {
  ApiClient({http.Client? client, this.baseUrl = 'http://localhost:3000'})
      : _client = client ?? http.Client();

  final String baseUrl;
  final http.Client _client;

  // ---------- Student ----------

  Future<Activity> registerActivity({
    required int studentId,
    required String name,
    required String organization,
    required String activityDate,
  }) async {
    final body = await _call(
      'POST',
      '/api/activities',
      role: 'student',
      body: <String, dynamic>{
        'studentId': studentId,
        'name': name,
        'organization': organization,
        'activityDate': activityDate,
      },
    );
    return Activity.fromJson(body);
  }

  /// Returns the id of the new submission.
  Future<int> submitHours({
    required int activityId,
    required double hours,
    required String serviceDate,
    required String evidenceNote,
  }) async {
    final body = await _call(
      'POST',
      '/api/activities/$activityId/submissions',
      role: 'student',
      body: <String, dynamic>{
        'hours': hours,
        'serviceDate': serviceDate,
        'evidenceNote': evidenceNote,
      },
    );
    return (body['id'] as num).toInt();
  }

  Future<void> resubmit({
    required int submissionId,
    required double hours,
    required String serviceDate,
    required String evidenceNote,
  }) async {
    await _call(
      'PATCH',
      '/api/submissions/$submissionId/resubmit',
      role: 'student',
      body: <String, dynamic>{
        'hours': hours,
        'serviceDate': serviceDate,
        'evidenceNote': evidenceNote,
      },
    );
  }

  Future<StudentProgress> getProgress(int studentId) async {
    final body = await _call('GET', '/api/students/$studentId/progress');
    return StudentProgress.fromJson(body);
  }

  // ---------- Supervisor ----------

  /// [role] is `supervisor` or `coordinator`.
  Future<List<Submission>> listSubmissions(String status, {required String role}) async {
    final body = await _call('GET', '/api/submissions?status=$status', role: role);
    final items = (body['items'] as List<dynamic>? ?? <dynamic>[]);
    return items.map((item) => Submission.fromJson(item as Map<String, dynamic>)).toList();
  }

  Future<void> verifySubmission(int submissionId) async {
    await _call(
      'PATCH',
      '/api/submissions/$submissionId/verify',
      role: 'supervisor',
      body: <String, dynamic>{'decision': 'VERIFY'},
    );
  }

  Future<void> rejectSubmission(int submissionId, String reason) async {
    await _call(
      'PATCH',
      '/api/submissions/$submissionId/verify',
      role: 'supervisor',
      body: <String, dynamic>{'decision': 'REJECT', 'reason': reason},
    );
  }

  // ---------- Coordinator ----------

  Future<ApprovalResult> approve(int submissionId) async {
    final body = await _call('PATCH', '/api/submissions/$submissionId/approve', role: 'coordinator');
    return ApprovalResult.fromJson(body);
  }

  Future<void> returnForCorrection(int submissionId, String reason) async {
    await _call(
      'PATCH',
      '/api/submissions/$submissionId/correct',
      role: 'coordinator',
      body: <String, dynamic>{'reason': reason},
    );
  }

  Future<ProgressSummary> getProgressSummary() async {
    final body = await _call('GET', '/api/summaries/progress');
    return ProgressSummary.fromJson(body);
  }

  Future<List<FailedNotification>> getFailedNotifications() async {
    final body = await _call('GET', '/api/notifications/failed');
    final items = (body['items'] as List<dynamic>? ?? <dynamic>[]);
    return items
        .map((item) => FailedNotification.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> markHandled(int notificationId) async {
    await _call('PATCH', '/api/notifications/$notificationId/handled');
  }

  // ---------- Shared request code ----------

  Future<Map<String, dynamic>> _call(
    String method,
    String path, {
    String? role,
    Map<String, dynamic>? body,
  }) {
    final uri = Uri.parse('$baseUrl$path');
    final headers = <String, String>{
      if (role != null) 'X-Demo-Role': role,
      if (body != null) 'Content-Type': 'application/json',
    };
    final encoded = body == null ? null : jsonEncode(body);

    return _request(() {
      switch (method) {
        case 'GET':
          return _client.get(uri, headers: headers);
        case 'POST':
          return _client.post(uri, headers: headers, body: encoded);
        case 'PATCH':
          return _client.patch(uri, headers: headers, body: encoded);
        default:
          throw ArgumentError('Unsupported method: $method');
      }
    });
  }

  Future<Map<String, dynamic>> _request(Future<http.Response> Function() send) async {
    http.Response response;
    try {
      response = await send().timeout(const Duration(seconds: 10));
    } on TimeoutException {
      throw const ApiException(ApiErrorKind.network, 'The server took too long to respond.');
    } on http.ClientException {
      throw const ApiException(
        ApiErrorKind.network,
        'Cannot reach the server. Check that the backend is running.',
      );
    }

    Map<String, dynamic> body = <String, dynamic>{};
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) body = decoded;
    } catch (_) {
      // Not JSON: fall through to the generic messages below.
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }

    final error = body['error'];
    final errorMap = error is Map<String, dynamic> ? error : <String, dynamic>{};
    final message = errorMap['message'] as String? ?? 'Unexpected error (${response.statusCode}).';
    final referenceId = errorMap['referenceId'] as String?;
    final rawDetails = errorMap['details'];
    final details = rawDetails is List ? rawDetails.map((d) => d.toString()).toList() : <String>[];

    switch (response.statusCode) {
      case 400:
        throw ApiException(ApiErrorKind.validation, message, referenceId: referenceId, details: details);
      case 403:
        throw ApiException(ApiErrorKind.forbidden, message, referenceId: referenceId);
      case 404:
        throw ApiException(ApiErrorKind.notFound, message, referenceId: referenceId);
      case 409:
        throw ApiException(ApiErrorKind.conflict, message, referenceId: referenceId);
      default:
        throw ApiException(ApiErrorKind.server, message, referenceId: referenceId);
    }
  }
}