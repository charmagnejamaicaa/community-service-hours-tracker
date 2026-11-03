// Screen 8 in docs/screen-flow.md: Manual Review Queue (coordinator).
// Lists notifications that failed permanently so a person can follow up, then mark them handled.

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'models.dart';
import 'widgets.dart';

class ReviewQueueScreen extends StatefulWidget {
  const ReviewQueueScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ReviewQueueScreen> createState() => _ReviewQueueScreenState();
}

class _ReviewQueueScreenState extends State<ReviewQueueScreen> {
  bool _loading = true;
  ApiException? _error;
  List<FailedNotification> _items = <FailedNotification>[];
  int? _busyId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await widget.api.getFailedNotifications();
      if (!mounted) return;
      setState(() {
        _items = items;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  Future<void> _markHandled(FailedNotification item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Mark as handled?'),
        content: Text(
          'Confirm that you contacted student #${item.studentId} another way '
          '(${_prettyType(item.type)}).',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _busyId = item.id);
    try {
      await widget.api.markHandled(item.id);
      if (!mounted) return;
      setState(() {
        _items = _items.where((n) => n.id != item.id).toList();
        _busyId = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Marked as handled.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busyId = null);
      final ref = e.referenceId == null ? '' : ' (ref ${e.referenceId})';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${e.message}$ref')),
      );
    }
  }

  static String _prettyType(String type) {
    final words = type.toLowerCase().split('_');
    final text = words.join(' ');
    return text.isEmpty ? type : text[0].toUpperCase() + text.substring(1);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Manual Review Queue',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              IconButton(
                tooltip: 'Refresh',
                onPressed: _loading ? null : _load,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text('Notifications that could not be delivered after all retries.'),
          const SizedBox(height: 12),
          Expanded(child: _buildBody(context)),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ErrorView(error: _error!, onRetry: _load);
    }
    if (_items.isEmpty) {
      return const Center(
        child: Text('Nothing needs manual action right now.'),
      );
    }
    return ListView.separated(
      itemCount: _items.length,
      separatorBuilder: (context, index) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final item = _items[index];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_prettyType(item.type), style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text('Student #${item.studentId}  |  ${item.referenceId}'),
                Text('${item.attempts} attempts  |  Reason: ${item.reason ?? 'unknown'}'),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.tonal(
                    onPressed: _busyId == null ? () => _markHandled(item) : null,
                    child: _busyId == item.id
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Mark handled'),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
