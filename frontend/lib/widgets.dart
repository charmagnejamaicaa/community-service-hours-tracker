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
    final isForbidden = error.kind == ApiErrorKind.forbidden;
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
              isNetwork ? 'Connection problem' : (isForbidden ? 'Not allowed' : 'Something went wrong'),
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

/// A box listing what is wrong with a form (checked on the device, or returned by the server).
class FormErrorBox extends StatelessWidget {
  const FormErrorBox({super.key, required this.lines, this.referenceId});

  /// Builds the box from a failed request: all validation details, or the main message.
  FormErrorBox.fromError(ApiException error, {Key? key})
      : this(
          key: key,
          lines: error.details.isNotEmpty ? error.details : <String>[error.message],
          referenceId: error.referenceId,
        );

  final List<String> lines;
  final String? referenceId;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      color: scheme.errorContainer,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final line in lines) Text('- $line', style: TextStyle(color: scheme.onErrorContainer)),
            if (referenceId != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  'Reference: $referenceId',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// A box confirming that something worked.
class SuccessBox extends StatelessWidget {
  const SuccessBox({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      color: scheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(Icons.check_circle, color: scheme.onPrimaryContainer),
            const SizedBox(width: 8),
            Expanded(child: Text(message, style: TextStyle(color: scheme.onPrimaryContainer))),
          ],
        ),
      ),
    );
  }
}

/// Asks for a reason (needed to reject or return a submission). Returns null if cancelled.
Future<String?> showReasonDialog(
  BuildContext context, {
  required String title,
  String label = 'Reason',
}) {
  return showDialog<String>(
    context: context,
    builder: (context) => _ReasonDialog(title: title, label: label),
  );
}

class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({required this.title, required this.label});

  final String title;
  final String label;

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final TextEditingController _controller = TextEditingController();
  String? _errorText;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _confirm() {
    final text = _controller.text.trim();
    if (text.isEmpty) {
      setState(() => _errorText = 'A reason is required.');
      return;
    }
    Navigator.of(context).pop(text);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: TextField(
        controller: _controller,
        autofocus: true,
        maxLength: 255,
        decoration: InputDecoration(labelText: widget.label, errorText: _errorText),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(onPressed: _confirm, child: const Text('Confirm')),
      ],
    );
  }
}

/// 17.5 -> "17.5", 40.0 -> "40"
String formatHours(double value) {
  return value == value.roundToDouble() ? value.toInt().toString() : value.toString();
}

/// DateTime -> "YYYY-MM-DD"
String formatDate(DateTime date) {
  String two(int n) => n.toString().padLeft(2, '0');
  return '${date.year}-${two(date.month)}-${two(date.day)}';
}

final RegExp _datePattern = RegExp(r'^\d{4}-\d{2}-\d{2}$');

/// True for text such as 2026-11-03.
bool isDateText(String text) => _datePattern.hasMatch(text);
