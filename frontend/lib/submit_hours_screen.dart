// Screen 4 in docs/screen-flow.md: Submit Hours / Evidence (student).
// Also used to resubmit a submission that was rejected or returned for correction.

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'widgets.dart';

class SubmitHoursScreen extends StatefulWidget {
  const SubmitHoursScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<SubmitHoursScreen> createState() => _SubmitHoursScreenState();
}

class _SubmitHoursScreenState extends State<SubmitHoursScreen> {
  final TextEditingController _targetId = TextEditingController();
  final TextEditingController _hours = TextEditingController();
  final TextEditingController _date = TextEditingController();
  final TextEditingController _evidence = TextEditingController();

  int _mode = 0; // 0 = new submission, 1 = resubmit a rejected one
  bool _busy = false;
  List<String> _problems = <String>[];
  ApiException? _error;
  String? _success;

  bool get _isResubmit => _mode == 1;

  @override
  void dispose() {
    _targetId.dispose();
    _hours.dispose();
    _date.dispose();
    _evidence.dispose();
    super.dispose();
  }

  void _clearMessages() {
    _problems = <String>[];
    _error = null;
    _success = null;
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(2020),
      lastDate: now,
    );
    if (picked != null && mounted) {
      setState(() => _date.text = formatDate(picked));
    }
  }

  Future<void> _submit() async {
    final targetId = int.tryParse(_targetId.text.trim());
    final hours = double.tryParse(_hours.text.trim());
    final evidence = _evidence.text.trim();

    final problems = <String>[];
    if (targetId == null || targetId <= 0) {
      problems.add(_isResubmit
          ? 'Enter the submission ID (a whole number greater than 0).'
          : 'Enter the activity ID (a whole number greater than 0).');
    }
    if (hours == null || hours <= 0 || hours > 12) {
      problems.add('Hours must be greater than 0 and at most 12.');
    }
    if (!isDateText(_date.text.trim())) problems.add('Enter the service date as YYYY-MM-DD.');
    if (evidence.isEmpty) problems.add('Evidence note is required.');
    if (evidence.length > 500) problems.add('Evidence note must be at most 500 characters.');

    if (problems.isNotEmpty) {
      setState(() {
        _clearMessages();
        _problems = problems;
      });
      return;
    }

    setState(() {
      _clearMessages();
      _busy = true;
    });
    try {
      if (_isResubmit) {
        await widget.api.resubmit(
          submissionId: targetId!,
          hours: hours!,
          serviceDate: _date.text.trim(),
          evidenceNote: evidence,
        );
        if (!mounted) return;
        setState(() {
          _busy = false;
          _success = 'Submission #$targetId was sent again and is waiting for verification.';
        });
      } else {
        final newId = await widget.api.submitHours(
          activityId: targetId!,
          hours: hours!,
          serviceDate: _date.text.trim(),
          evidenceNote: evidence,
        );
        if (!mounted) return;
        setState(() {
          _busy = false;
          _success = 'Hours submitted. Your submission ID is $newId. It is waiting for verification.';
          _hours.clear();
          _evidence.clear();
        });
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: ListView(
        children: [
          Text('Submit Hours', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 12),
          SegmentedButton<int>(
            segments: const [
              ButtonSegment<int>(value: 0, label: Text('New submission')),
              ButtonSegment<int>(value: 1, label: Text('Resubmit')),
            ],
            selected: <int>{_mode},
            onSelectionChanged: (selection) {
              setState(() {
                _mode = selection.first;
                _clearMessages();
              });
            },
          ),
          const SizedBox(height: 16),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: _targetId,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: _isResubmit ? 'Submission ID (the rejected one)' : 'Activity ID',
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _hours,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Hours (up to 12)', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _date,
                  decoration: InputDecoration(
                    labelText: 'Service date (YYYY-MM-DD)',
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      tooltip: 'Pick a date',
                      icon: const Icon(Icons.calendar_today),
                      onPressed: _pickDate,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _evidence,
                  maxLines: 3,
                  maxLength: 500,
                  decoration: const InputDecoration(
                    labelText: 'Evidence / attendance note',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(_isResubmit ? 'Resubmit' : 'Submit hours'),
                ),
                const SizedBox(height: 16),
                if (_problems.isNotEmpty) FormErrorBox(lines: _problems),
                if (_error != null) FormErrorBox.fromError(_error!),
                if (_success != null) SuccessBox(message: _success!),
              ],
            ),
          ),
        ],
      ),
    );
  }
}