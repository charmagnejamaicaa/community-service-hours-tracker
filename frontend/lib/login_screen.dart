// Sign-in screen (Week 14). The role is decided by the server from the account, not chosen here.

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'auth.dart';
import 'widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.onSignedIn, this.notice});

  final ApiClient api;
  final void Function(AuthUser user) onSignedIn;

  /// A message shown above the form, for example "Your session expired."
  final String? notice;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();

  bool _busy = false;
  List<String> _problems = <String>[];
  ApiException? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    final problems = <String>[];
    if (!_email.text.contains('@')) problems.add('Enter your email address.');
    if (_password.text.isEmpty) problems.add('Enter your password.');
    if (problems.isNotEmpty) {
      setState(() {
        _problems = problems;
        _error = null;
      });
      return;
    }

    setState(() {
      _busy = true;
      _problems = <String>[];
      _error = null;
    });
    try {
      final user = await widget.api.login(_email.text.trim(), _password.text);
      if (!mounted) return;
      _password.clear(); // do not keep the password in the form after sign-in
      setState(() => _busy = false);
      widget.onSignedIn(user);
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
    return Scaffold(
      appBar: AppBar(title: const Text('Community Service Hours Tracker')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Sign in', style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 4),
                const Text('Use the account given to you by your coordinator.'),
                const SizedBox(height: 16),
                if (widget.notice != null) ...[
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text(widget.notice!),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.username],
                  decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  obscureText: true,
                  enableSuggestions: false,
                  autocorrect: false,
                  autofillHints: const [AutofillHints.password],
                  decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder()),
                  onSubmitted: (_) => _busy ? null : _signIn(),
                ),
                const SizedBox(height: 16),
                if (_problems.isNotEmpty) ...[
                  FormErrorBox(lines: _problems),
                  const SizedBox(height: 12),
                ],
                if (_error != null) ...[
                  FormErrorBox.fromError(_error!),
                  const SizedBox(height: 12),
                ],
                FilledButton(
                  onPressed: _busy ? null : _signIn,
                  child: _busy
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Sign in'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
