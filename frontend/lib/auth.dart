// Who is signed in. The role comes from the server (login response), never from a dropdown.

enum UserRole { student, supervisor, coordinator, adviser }

String roleLabel(UserRole role) {
  switch (role) {
    case UserRole.student:
      return 'Student';
    case UserRole.supervisor:
      return 'Supervisor';
    case UserRole.coordinator:
      return 'Coordinator';
    case UserRole.adviser:
      return 'Adviser';
  }
}

UserRole? parseRole(Object? value) {
  for (final role in UserRole.values) {
    if (role.name == value) return role;
  }
  return null;
}

class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.role,
    this.studentId,
    this.expiresAt,
  });

  final int id;
  final String name;
  final UserRole role;
  final int? studentId; // only students have one
  final DateTime? expiresAt; // when the sign-in token stops working
}
