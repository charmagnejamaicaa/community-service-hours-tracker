// Screen 3 in docs/screen-flow.md: Register Activity (student).

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'models.dart';
import 'widgets.dart';

class RegisterActivityScreen extends StatefulWidget {
  const RegisterActivityScreen({super.key, required this.api, required this.studentId});

  final ApiClient api;

  /// The signed-in student's own ID (from the login answer). The server checks it again.
  final int? studentId;

  @override
  State<RegisterActivityScreen> createState() => _RegisterActivityScreenState();
}

class _RegisterActivityScreenState extends State<RegisterActivityScreen> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _organization = TextEditingController();
  final TextEditingController _date = TextEditingController();

  bool _busy = false;
  List<String> _problems = <String>[];
  ApiException? _error;
  Activity? _created;

  @override
  void dispose() {
    _name.dispose();
    _organization.dispose();
    _date.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(2020),
      lastDate: DateTime(now.year + 1, now.month, now.day),
    );
    if (picked != null && mounted) {
      setState(() => _date.text = formatDate(picked));
    }
  }

  Future<void> _submit() async {
    final studentId = widget.studentId;
    final problems = <String>[];
    if (studentId == null) {
      problems.add('Your account is not linked to a student record. Ask your coordinator.');
    }
    if (_name.text.trim().isEmpty) problems.add('Activity name is required.');
    if (_organization.text.trim().isEmpty) problems.add('Organization is required.');
    if (!isDateText(_date.text.trim())) problems.add('Enter the date as YYYY-MM-DD.');

    if (problems.isNotEmpty) {
      setState(() {
        _problems = problems;
        _error = null;
        _created = null;
      });
      return;
    }

    setState(() {
      _busy = true;
      _problems = <String>[];
      _error = null;
      _created = null;
    });
    try {
      final activity = await widget.api.registerActivity(
        studentId: studentId!,
        name: _name.text.trim(),
        organization: _organization.text.trim(),
        activityDate: _date.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _created = activity;
        _busy = false;
        _name.clear();
        _organization.clear();
        _date.clear();
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _busy = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: ListView(
        children: [
          Text('Register Activity', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 4),
          const Text('Register a community service activity, then submit your hours for it.'),
          const SizedBox(height: 16),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: _name,
                  maxLength: 150,
                  decoration: const InputDecoration(labelText: 'Activity name', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 4),
                TextField(
                  controller: _organization,
                  maxLength: 150,
                  decoration: const InputDecoration(labelText: 'Partner organization', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 4),
                TextField(
                  controller: _date,
                  decoration: InputDecoration(
                    labelText: 'Activity date (YYYY-MM-DD)',
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      tooltip: 'Pick a date',
                      icon: const Icon(Icons.calendar_today),
                      onPressed: _pickDate,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Register activity'),
                ),
                const SizedBox(height: 16),
                if (_problems.isNotEmpty) FormErrorBox(lines: _problems),
                if (_error != null) FormErrorBox.fromError(_error!),
                if (_created != null)
                  SuccessBox(
                    message: 'Activity registered. Your activity ID is ${_created!.id}. '
                        'Use it on the Submit hours screen.',
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
