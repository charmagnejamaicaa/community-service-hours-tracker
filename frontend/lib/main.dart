// Community Service Hours Tracker - Flutter Web (Week 14)
//
// Sign in first. The server decides the role; each role sees the screens it needs.
// Wide screens: side navigation. Narrow screens: bottom navigation (responsive layout).

import 'package:flutter/material.dart';

import 'account_screen.dart';
import 'api_client.dart';
import 'auth.dart';
import 'dashboard_screen.dart';
import 'login_screen.dart';
import 'progress_summary_screen.dart';
import 'register_activity_screen.dart';
import 'review_queue_screen.dart';
import 'submission_queue_screen.dart';
import 'submit_hours_screen.dart';

void main() {
  runApp(ServiceHoursApp(api: ApiClient()));
}

class ServiceHoursApp extends StatefulWidget {
  const ServiceHoursApp({super.key, required this.api});

  final ApiClient api;

  @override
  State<ServiceHoursApp> createState() => _ServiceHoursAppState();
}

class _ServiceHoursAppState extends State<ServiceHoursApp> {
  AuthUser? _user;
  String? _notice;

  @override
  void initState() {
    super.initState();
    // The server answered 401 (token expired, revoked or invalid): back to the login screen.
    widget.api.onSessionExpired = () {
      if (!mounted) return;
      setState(() {
        _user = null;
        _notice = 'Your session ended. Please sign in again.';
      });
    };
  }

  Future<void> _signOut() async {
    await widget.api.logout();
    if (!mounted) return;
    setState(() {
      _user = null;
      _notice = 'You are signed out.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    return MaterialApp(
      title: 'Community Service Hours Tracker',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: Colors.teal, useMaterial3: true),
      home: user == null
          ? LoginScreen(
              api: widget.api,
              notice: _notice,
              onSignedIn: (signedIn) => setState(() {
                _user = signedIn;
                _notice = null;
              }),
            )
          : HomeShell(api: widget.api, user: user, onSignOut: _signOut),
    );
  }
}

class _TabSpec {
  const _TabSpec(this.label, this.icon, this.selectedIcon, this.builder);

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget Function() builder;
}

/// The screens each role can use (the server enforces the same rules; this only hides what would be refused).
List<_TabSpec> _tabsFor(ApiClient api, AuthUser user, Future<void> Function() onSignOut) {
  final account = _TabSpec(
    'Account',
    Icons.account_circle_outlined,
    Icons.account_circle,
    () => AccountScreen(user: user, onSignOut: onSignOut),
  );
  // Students see their own progress; coordinators and advisers can look up any student.
  final dashboard = _TabSpec(
    'Dashboard',
    Icons.dashboard_outlined,
    Icons.dashboard,
    () => DashboardScreen(api: api, studentId: user.role == UserRole.student ? user.studentId : null),
  );
  final summary = _TabSpec(
    'Summary',
    Icons.groups_outlined,
    Icons.groups,
    () => ProgressSummaryScreen(api: api),
  );

  switch (user.role) {
    case UserRole.student:
      return [
        dashboard,
        _TabSpec('Register activity', Icons.add_circle_outline, Icons.add_circle,
            () => RegisterActivityScreen(api: api, studentId: user.studentId)),
        _TabSpec('Submit hours', Icons.upload_file_outlined, Icons.upload_file,
            () => SubmitHoursScreen(api: api)),
        account,
      ];
    case UserRole.supervisor:
      return [
        _TabSpec('Verification queue', Icons.fact_check_outlined, Icons.fact_check,
            () => SubmissionQueueScreen(api: api, mode: QueueMode.verification)),
        account,
      ];
    case UserRole.coordinator:
      return [
        _TabSpec('Approval', Icons.approval_outlined, Icons.approval,
            () => SubmissionQueueScreen(api: api, mode: QueueMode.approval)),
        _TabSpec('Review queue', Icons.report_problem_outlined, Icons.report_problem,
            () => ReviewQueueScreen(api: api)),
        summary,
        dashboard,
        account,
      ];
    case UserRole.adviser:
      return [dashboard, summary, account];
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.api, required this.user, required this.onSignOut});

  final ApiClient api;
  final AuthUser user;
  final Future<void> Function() onSignOut;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  Widget _whoAmI() {
    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: Center(child: Text('${widget.user.name} (${roleLabel(widget.user.role)})')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _tabsFor(widget.api, widget.user, widget.onSignOut);
    // A new screen object per tab means each screen reloads its data when you switch to it.
    final Widget page = KeyedSubtree(
      key: ValueKey<String>('${widget.user.role.name}-$_index'),
      child: tabs[_index].builder(),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 700;

        if (isWide) {
          return Scaffold(
            appBar: AppBar(
              title: const Text('Community Service Hours Tracker'),
              actions: [_whoAmI()],
            ),
            body: Row(
              children: [
                NavigationRail(
                  selectedIndex: _index,
                  onDestinationSelected: (i) => setState(() => _index = i),
                  labelType: NavigationRailLabelType.all,
                  destinations: [
                    for (final tab in tabs)
                      NavigationRailDestination(
                        icon: Icon(tab.icon),
                        selectedIcon: Icon(tab.selectedIcon),
                        label: Text(tab.label),
                      ),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: page),
              ],
            ),
          );
        }

        return Scaffold(
          appBar: AppBar(
            title: const Text('Service Hours Tracker'),
          ),
          body: page,
          bottomNavigationBar: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: (i) => setState(() => _index = i),
            destinations: [
              for (final tab in tabs)
                NavigationDestination(
                  icon: Icon(tab.icon),
                  selectedIcon: Icon(tab.selectedIcon),
                  label: tab.label,
                ),
            ],
          ),
        );
      },
    );
  }
}
