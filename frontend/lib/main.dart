// Community Service Hours Tracker - Flutter Web (Week 13 starter)
//
// Wide screens: side navigation. Narrow screens: bottom navigation (responsive layout).
 
import 'package:flutter/material.dart';
 
import 'api_client.dart';
import 'dashboard_screen.dart';
import 'review_queue_screen.dart';
 
void main() {
  runApp(ServiceHoursApp(api: ApiClient()));
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
 
class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.api});
 
  final ApiClient api;
 
  @override
  State<HomeShell> createState() => _HomeShellState();
}
 
class _HomeShellState extends State<HomeShell> {
  int _index = 0;
 
  @override
  Widget build(BuildContext context) {
    // A new screen object per tab means each tab reloads its data when you switch to it.
    final Widget page = _index == 0
        ? DashboardScreen(key: const ValueKey('dashboard'), api: widget.api)
        : ReviewQueueScreen(key: const ValueKey('queue'), api: widget.api);
 
    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 700;
 
        if (isWide) {
          return Scaffold(
            appBar: AppBar(title: const Text('Community Service Hours Tracker')),
            body: Row(
              children: [
                NavigationRail(
                  selectedIndex: _index,
                  onDestinationSelected: (i) => setState(() => _index = i),
                  labelType: NavigationRailLabelType.all,
                  destinations: const [
                    NavigationRailDestination(
                      icon: Icon(Icons.dashboard_outlined),
                      selectedIcon: Icon(Icons.dashboard),
                      label: Text('Dashboard'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.report_problem_outlined),
                      selectedIcon: Icon(Icons.report_problem),
                      label: Text('Review queue'),
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
          appBar: AppBar(title: const Text('Service Hours Tracker')),
          body: page,
          bottomNavigationBar: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: (i) => setState(() => _index = i),
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.dashboard_outlined),
                selectedIcon: Icon(Icons.dashboard),
                label: 'Dashboard',
              ),
              NavigationDestination(
                icon: Icon(Icons.report_problem_outlined),
                selectedIcon: Icon(Icons.report_problem),
                label: 'Review queue',
              ),
            ],
          ),
        );
      },
    );
  }
}
 

