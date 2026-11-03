// Screen 2 in docs/screen-flow.md: Student Dashboard.
// Week 13 has no sign-in yet, so the student ID is typed in. Real sign-in comes in Week 14.

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'models.dart';
import 'widgets.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final TextEditingController _idController = TextEditingController(text: '1');
  bool _loading = false;
  bool _loadedOnce = false;
  String? _inputError;
  ApiException? _error;
  StudentProgress? _progress;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _idController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final id = int.tryParse(_idController.text.trim());
    if (id == null || id <= 0) {
      setState(() => _inputError = 'Enter a whole number greater than 0.');
      return;
    }
    setState(() {
      _inputError = null;
      _loading = true;
      _error = null;
    });
    try {
      final progress = await widget.api.getProgress(id);
      if (!mounted) return;
      setState(() {
        _progress = progress;
        _loading = false;
        _loadedOnce = true;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _progress = null;
        _loading = false;
        _loadedOnce = true;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: ListView(
        children: [
          Text('Student Dashboard', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 160,
                child: TextField(
                  controller: _idController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Student ID',
                    border: const OutlineInputBorder(),
                    errorText: _inputError,
                  ),
                  onSubmitted: (_) => _load(),
                ),
              ),
              const SizedBox(width: 12),
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: FilledButton(
                  onPressed: _loading ? null : _load,
                  child: const Text('Load'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _buildBody(context),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.all(32),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null) {
      // A student that does not exist is an "empty" result, not a crash.
      if (_error!.kind == ApiErrorKind.notFound) {
        return const Padding(
          padding: EdgeInsets.all(24),
          child: Center(child: Text('No student found with that ID.')),
        );
      }
      return ErrorView(error: _error!, onRetry: _load);
    }
    final progress = _progress;
    if (progress == null) {
      return _loadedOnce
          ? const SizedBox.shrink()
          : const Center(child: Text('Enter a student ID and press Load.'));
    }
    return _ProgressView(progress: progress);
  }
}

class _ProgressView extends StatelessWidget {
  const _ProgressView({required this.progress});

  final StudentProgress progress;

  Color _statusColor() {
    switch (progress.status) {
      case 'COMPLETED':
        return Colors.green;
      case 'OVERDUE':
        return Colors.red;
      default:
        return Colors.blue;
    }
  }

  @override
  Widget build(BuildContext context) {
    final percent = progress.percentComplete.clamp(0, 100).toDouble();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Chip(
              label: Text(progress.status.replaceAll('_', ' ')),
              backgroundColor: _statusColor().withAlpha(40),
            ),
            const SizedBox(width: 12),
            if (progress.daysLeft != null)
              Text(
                progress.daysLeft! >= 0
                    ? '${progress.daysLeft} days left (deadline ${progress.deadline})'
                    : '${-progress.daysLeft!} days past the deadline (${progress.deadline})',
              ),
          ],
        ),
        const SizedBox(height: 16),
        LinearProgressIndicator(value: percent / 100, minHeight: 10),
        const SizedBox(height: 4),
        Text('${percent.toStringAsFixed(percent == percent.roundToDouble() ? 0 : 1)}% of required hours approved'),
        const SizedBox(height: 16),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            _StatCard(label: 'Required', value: formatHours(progress.requiredHours)),
            _StatCard(label: 'Approved', value: formatHours(progress.approvedHours)),
            _StatCard(label: 'Pending', value: formatHours(progress.pendingHours)),
            _StatCard(label: 'Remaining', value: formatHours(progress.remainingHours)),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 150,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelLarge),
              const SizedBox(height: 4),
              Text(value, style: Theme.of(context).textTheme.headlineMedium),
              const Text('hours'),
            ],
          ),
        ),
      ),
    );
  }
}
