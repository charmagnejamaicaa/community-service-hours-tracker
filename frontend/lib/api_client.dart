// The ONLY place that talks to the backend. If an endpoint changes, only this file changes.

import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';

enum ApiErrorKind { network, validation, notFound, conflict, server }

/// Every failure from the backend is turned into one of these, so screens can show
/// the right message (network error, server error with a reference ID, and so on).
class ApiException implements Exception {
  const ApiException(this.kind, this.message, {this.referenceId});

  final ApiErrorKind kind;
  final String message;
  final String? referenceId; // matches the referenceId in the backend logs

  @override
  String toString() => 'ApiException($kind, $message, $referenceId)';
}

class ApiClient {
  ApiClient({http.Client? client, this.baseUrl = 'http://localhost:3000'})
      : _client = client ?? http.Client();

  final String baseUrl;
  final http.Client _client;

  Future<StudentProgress> getProgress(int studentId) async {
    final body = await _request(
      () => _client.get(Uri.parse('$baseUrl/api/students/$studentId/progress')),
    );
    return StudentProgress.fromJson(body);
  }

  Future<List<FailedNotification>> getFailedNotifications() async {
    final body = await _request(
      () => _client.get(Uri.parse('$baseUrl/api/notifications/failed')),
    );
    final items = (body['items'] as List<dynamic>? ?? <dynamic>[]);
    return items
        .map((item) => FailedNotification.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> markHandled(int notificationId) async {
    await _request(
      () => _client.patch(Uri.parse('$baseUrl/api/notifications/$notificationId/handled')),
    );
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

    switch (response.statusCode) {
      case 400:
        throw ApiException(ApiErrorKind.validation, message, referenceId: referenceId);
      case 404:
        throw ApiException(ApiErrorKind.notFound, message, referenceId: referenceId);
      case 409:
        throw ApiException(ApiErrorKind.conflict, message, referenceId: referenceId);
      default:
        throw ApiException(ApiErrorKind.server, message, referenceId: referenceId);
    }
  }
}
