// Community Service Hours Tracker - Flutter Web (Week 13)
//
// Screen 1 in docs/screen-flow.md: a demo role picker (real sign-in comes in Week 14).
// Each role sees the screens it needs.
// Wide screens: side navigation. Narrow screens: bottom navigation (responsive layout).

import 'package:flutter/material.dart';

import 'api_client.dart';
import 'dashboard_screen.dart';
import 'progress_summary_screen.dart';
import 'register_activity_screen.dart';
import 'review_queue_screen.dart';
import 'submission_queue_screen.dart';
import 'submit_hours_screen.dart';

void main() {
  runApp(ServiceHoursApp(api: ApiClient()));
}

enum DemoRole { student, supervisor, coordinator, adviser }

String roleLabel(DemoRole role) {
  switch (role) {
    case DemoRole.student:
      return 'Student';
    case DemoRole.supervisor:
      return 'Supervisor';
    case DemoRole.coordinator:
      return 'Coordinator';
    case DemoRole.adviser:
      return 'Adviser';
  }
}

class ServiceHoursApp extends StatelessWidget {
  const ServiceHoursApp({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Community Service Hours Tracker',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: Colors.teal, useMaterial3: true),
      home: HomeShell(api: api),
    );
  }
}

class _TabSpec {
  const _TabSpec(this.label, this.icon, this.selectedIcon, this.builder);

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget Function(ApiClient api) builder;
}

/// The screens each role can use.
List<_TabSpec> _tabsFor(DemoRole role) {
  final dashboard = _TabSpec(
    'Dashboard',
    Icons.dashboard_outlined,
    Icons.dashboard,
    (api) => DashboardScreen(api: api),
  );
  final summary = _TabSpec(
    'Summary',
    Icons.groups_outlined,
    Icons.groups,
    (api) => ProgressSummaryScreen(api: api),
  );

  switch (role) {
    case DemoRole.student:
      return [
        dashboard,
        _TabSpec('Register activity', Icons.add_circle_outline, Icons.add_circle,
            (api) => RegisterActivityScreen(api: api)),
        _TabSpec('Submit hours', Icons.upload_file_outlined, Icons.upload_file,
            (api) => SubmitHoursScreen(api: api)),
      ];
    case DemoRole.supervisor:
      return [
        _TabSpec('Verification queue', Icons.fact_check_outlined, Icons.fact_check,
            (api) => SubmissionQueueScreen(api: api, mode: QueueMode.verification)),
        dashboard,
      ];
    case DemoRole.coordinator:
      return [
        _TabSpec('Approval', Icons.approval_outlined, Icons.approval,
            (api) => SubmissionQueueScreen(api: api, mode: QueueMode.approval)),
        _TabSpec('Review queue', Icons.report_problem_outlined, Icons.report_problem,
            (api) => ReviewQueueScreen(api: api)),
        summary,
        dashboard,
      ];
    case DemoRole.adviser:
      return [dashboard, summary];
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.api});

  final ApiClient api;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  DemoRole _role = DemoRole.student;
  int _index = 0;

  Widget _roleSelector() {
    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<DemoRole>(
          value: _role,
          onChanged: (role) {
            if (role == null) return;
            setState(() {
              _role = role;
              _index = 0;
            });
          },
          items: [
            for (final role in DemoRole.values)
              DropdownMenuItem<DemoRole>(value: role, child: Text(roleLabel(role))),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _tabsFor(_role);
    // A new screen object per role and tab means each screen reloads its data when you switch to it.
    final Widget page = KeyedSubtree(
      key: ValueKey<String>('${_role.name}-$_index'),
      child: tabs[_index].builder(widget.api),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 700;

        if (isWide) {
          return Scaffold(
            appBar: AppBar(
              title: const Text('Community Service Hours Tracker'),
              actions: [_roleSelector()],
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
            actions: [_roleSelector()],
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