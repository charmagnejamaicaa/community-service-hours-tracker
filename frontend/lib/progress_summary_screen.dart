// Screen 7 in docs/screen-flow.md: Progress Summary (coordinator / adviser, read-only).

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'models.dart';
import 'widgets.dart';

class ProgressSummaryScreen extends StatefulWidget {
  const ProgressSummaryScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ProgressSummaryScreen> createState() => _ProgressSummaryScreenState();
}

class _ProgressSummaryScreenState extends State<ProgressSummaryScreen> {
  bool _loading = true;
  ApiException? _error;
  ProgressSummary? _summary;

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
      final summary = await widget.api.getProgressSummary();
      if (!mounted) return;
      setState(() {
        _summary = summary;
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
                child: Text('Progress Summary', style: Theme.of(context).textTheme.headlineSmall),
              ),
              IconButton(
                tooltip: 'Refresh',
                onPressed: _loading ? null : _load,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
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
    final summary = _summary;
    if (summary == null || summary.students.isEmpty) {
      return const Center(child: Text('No students to show yet.'));
    }

    final totals = summary.totals;
    return ListView(
      children: [
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            _TotalCard(label: 'Students', value: '${totals.students}'),
            _TotalCard(label: 'Completed', value: '${totals.completed}'),
            _TotalCard(label: 'In progress', value: '${totals.inProgress}'),
            _TotalCard(label: 'Overdue', value: '${totals.overdue}'),
            _TotalCard(label: 'Approved hours', value: formatHours(totals.approvedHours)),
            _TotalCard(label: 'Remaining hours', value: formatHours(totals.remainingHours)),
          ],
        ),
        const SizedBox(height: 16),
        Text('Per student', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        for (final student in summary.students) _StudentRow(student: student),
      ],
    );
  }
}

class _TotalCard extends StatelessWidget {
  const _TotalCard({required this.label, required this.value});

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
            ],
          ),
        ),
      ),
    );
  }
}

class _StudentRow extends StatelessWidget {
  const _StudentRow({required this.student});

  final StudentProgress student;

  @override
  Widget build(BuildContext context) {
    final percent = student.percentComplete.clamp(0, 100).toDouble();
    final deadline = student.daysLeft == null
        ? 'No deadline'
        : student.daysLeft! >= 0
            ? '${student.daysLeft} days left'
            : '${-student.daysLeft!} days overdue';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Student #${student.studentId}', style: Theme.of(context).textTheme.titleMedium),
                ),
                Chip(label: Text(student.status.replaceAll('_', ' '))),
              ],
            ),
            const SizedBox(height: 4),
            LinearProgressIndicator(value: percent / 100, minHeight: 8),
            const SizedBox(height: 4),
            Text(
              '${formatHours(student.approvedHours)} of ${formatHours(student.requiredHours)} hours approved '
              '(${formatHours(student.remainingHours)} remaining)  |  $deadline',
            ),
          ],
        ),
      ),
    );
  }
}