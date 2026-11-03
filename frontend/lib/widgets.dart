// Small shared widgets used by more than one screen.

import 'package:flutter/material.dart';

import 'api_client.dart';

/// Shown when a request fails. Network problems and server problems get different wording;
/// server problems show the reference ID so it can be matched to the backend logs.
class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, required this.onRetry});

  final ApiException error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final isNetwork = error.kind == ApiErrorKind.network;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isNetwork ? Icons.cloud_off : Icons.error_outline,
              size: 48,
              color: Theme.of(context).colorScheme.error,
            ),
            const SizedBox(height: 12),
            Text(
              isNetwork ? 'Connection problem' : 'Something went wrong',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(error.message, textAlign: TextAlign.center),
            if (error.referenceId != null) ...[
              const SizedBox(height: 4),
              Text(
                'Reference: ${error.referenceId}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

/// 17.5 -> "17.5", 40.0 -> "40"
String formatHours(double value) {
  return value == value.roundToDouble() ? value.toInt().toString() : value.toString();
}
