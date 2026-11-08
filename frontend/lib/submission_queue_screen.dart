// Screens 5 and 6 in docs/screen-flow.md:
//   Verification Queue (supervisor): verify or reject submissions that are waiting.
//   Approval and Correction (coordinator): approve verified hours, or return them for correction.

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'models.dart';
import 'widgets.dart';

enum QueueMode { verification, approval }

class SubmissionQueueScreen extends StatefulWidget {
  const SubmissionQueueScreen({super.key, required this.api, required this.mode});

  final ApiClient api;
  final QueueMode mode;

  @override
  State<SubmissionQueueScreen> createState() => _SubmissionQueueScreenState();
}

class _SubmissionQueueScreenState extends State<SubmissionQueueScreen> {
  bool _loading = true;
  ApiException? _error;
  List<Submission> _items = <Submission>[];
  int? _busyId;

  bool get _isVerification => widget.mode == QueueMode.verification;
  String get _status => _isVerification ? 'SUBMITTED' : 'VERIFIED';

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
      final items = await widget.api.listSubmissions(_status);
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

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  /// Runs one action on one submission. On success the item leaves the list.
  Future<void> _run(Submission item, Future<String> Function() action) async {
    setState(() => _busyId = item.id);
    try {
      final message = await action();
      if (!mounted) return;
      setState(() {
        _items = _items.where((s) => s.id != item.id).toList();
        _busyId = null;
      });
      _toast(message);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busyId = null);
      final ref = e.referenceId == null ? '' : ' (ref ${e.referenceId})';
      _toast('${e.message}$ref');
      // Someone else changed it first: show the current list.
      if (e.kind == ApiErrorKind.conflict) _load();
    }
  }

  Future<void> _verify(Submission item) {
    return _run(item, () async {
      await widget.api.verifySubmission(item.id);
      return 'Submission #${item.id} verified.';
    });
  }

  Future<void> _reject(Submission item) async {
    final reason = await showReasonDialog(context, title: 'Reject submission #${item.id}');
    if (reason == null || !mounted) return;
    await _run(item, () async {
      await widget.api.rejectSubmission(item.id, reason);
      return 'Submission #${item.id} rejected.';
    });
  }

  Future<void> _approve(Submission item) {
    return _run(item, () async {
      final result = await widget.api.approve(item.id);
      final state = result.progressStatus.replaceAll('_', ' ').toLowerCase();
      return 'Approved. Student #${result.studentId} has ${formatHours(result.remainingHours)} hours remaining ($state).';
    });
  }

  Future<void> _returnForCorrection(Submission item) async {
    final reason = await showReasonDialog(context, title: 'Return submission #${item.id} for correction');
    if (reason == null || !mounted) return;
    await _run(item, () async {
      await widget.api.returnForCorrection(item.id, reason);
      return 'Submission #${item.id} returned for correction.';
    });
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
                  _isVerification ? 'Verification Queue' : 'Approval and Correction',
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
          Text(
            _isVerification
                ? 'Submissions waiting for a supervisor to verify the hours.'
                : 'Verified submissions waiting for the coordinator to approve the hours.',
          ),
          const SizedBox(height: 12),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ErrorView(error: _error!, onRetry: _load);
    }
    if (_items.isEmpty) {
      return Center(
        child: Text(
          _isVerification
              ? 'No submissions are waiting for verification.'
              : 'No verified submissions are waiting for approval.',
        ),
      );
    }
    return ListView.separated(
      itemCount: _items.length,
      separatorBuilder: (context, index) => const SizedBox(height: 8),
      itemBuilder: (context, index) => _buildCard(context, _items[index]),
    );
  }

  Widget _buildCard(BuildContext context, Submission item) {
    final busy = _busyId == item.id;
    final disabled = _busyId != null;

    final VoidCallback? primary = disabled ? null : () => _isVerification ? _verify(item) : _approve(item);
    final VoidCallback? secondary =
        disabled ? null : () => _isVerification ? _reject(item) : _returnForCorrection(item);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Submission #${item.id}', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text('Student #${item.studentId}  |  Activity #${item.activityId}'),
            Text('${formatHours(item.hours)} hours on ${item.serviceDate}'),
            Text('Evidence: ${item.evidenceNote}'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton(
                  onPressed: primary,
                  child: busy
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(_isVerification ? 'Verify' : 'Approve'),
                ),
                OutlinedButton(
                  onPressed: secondary,
                  child: Text(_isVerification ? 'Reject' : 'Return for correction'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
