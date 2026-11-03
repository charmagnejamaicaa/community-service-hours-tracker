// Data classes that match the JSON returned by the backend (see docs/api-contract.md).

class StudentProgress {
  const StudentProgress({
    required this.studentId,
    required this.requiredHours,
    required this.approvedHours,
    required this.pendingHours,
    required this.remainingHours,
    required this.percentComplete,
    required this.status,
    this.daysLeft,
    this.deadline,
  });

  final int studentId;
  final double requiredHours;
  final double approvedHours;
  final double pendingHours;
  final double remainingHours;
  final double percentComplete;
  final String status; // IN_PROGRESS | COMPLETED | OVERDUE
  final int? daysLeft;
  final String? deadline; // YYYY-MM-DD

  factory StudentProgress.fromJson(Map<String, dynamic> json) {
    return StudentProgress(
      studentId: (json['studentId'] as num).toInt(),
      requiredHours: (json['requiredHours'] as num).toDouble(),
      approvedHours: (json['approvedHours'] as num).toDouble(),
      pendingHours: (json['pendingHours'] as num).toDouble(),
      remainingHours: (json['remainingHours'] as num).toDouble(),
      percentComplete: (json['percentComplete'] as num).toDouble(),
      status: json['status'] as String,
      daysLeft: (json['daysLeft'] as num?)?.toInt(),
      deadline: json['deadline'] as String?,
    );
  }
}

class FailedNotification {
  const FailedNotification({
    required this.id,
    required this.type,
    required this.studentId,
    required this.referenceId,
    required this.attempts,
    required this.createdAt,
    this.reason,
  });

  final int id;
  final String type;
  final int studentId;
  final String referenceId;
  final int attempts;
  final String? reason;
  final String createdAt;

  factory FailedNotification.fromJson(Map<String, dynamic> json) {
    return FailedNotification(
      id: (json['id'] as num).toInt(),
      type: json['type'] as String,
      studentId: (json['studentId'] as num).toInt(),
      referenceId: json['referenceId'] as String,
      attempts: (json['attempts'] as num).toInt(),
      reason: json['reason'] as String?,
      createdAt: json['createdAt'] as String,
    );
  }
}
