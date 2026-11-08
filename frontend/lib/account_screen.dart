// Account tab: who is signed in, when the session ends, and the Sign out button.

import 'package:flutter/material.dart';

import 'auth.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key, required this.user, required this.onSignOut});

  final AuthUser user;
  final Future<void> Function() onSignOut;

  String _time(DateTime value) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(value.hour)}:${two(value.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    final expiresAt = user.expiresAt;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: ListView(
        children: [
          Text('Account', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text(user.name),
              subtitle: Text('Role: ${roleLabel(user.role)}'),
            ),
          ),
          if (expiresAt != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text('Your session ends at ${_time(expiresAt)}. After that you sign in again.'),
            ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: OutlinedButton.icon(
              onPressed: onSignOut,
              icon: const Icon(Icons.logout),
              label: const Text('Sign out'),
            ),
          ),
        ],
      ),
    );
  }
}
