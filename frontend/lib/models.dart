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

/// A registered service activity.
class Activity {
  const Activity({
    required this.id,
    required this.studentId,
    required this.name,
    required this.organization,
    required this.activityDate,
  });

  final int id;
  final int studentId;
  final String name;
  final String organization;
  final String activityDate; // YYYY-MM-DD

  factory Activity.fromJson(Map<String, dynamic> json) {
    return Activity(
      id: (json['id'] as num).toInt(),
      studentId: (json['studentId'] as num).toInt(),
      name: json['name'] as String,
      organization: json['organization'] as String,
      activityDate: json['activityDate'] as String,
    );
  }
}

/// One hours submission moving through the workflow.
class Submission {
  const Submission({
    required this.id,
    required this.activityId,
    required this.studentId,
    required this.hours,
    required this.serviceDate,
    required this.evidenceNote,
    required this.status,
    this.rejectReason,
  });

  final int id;
  final int activityId;
  final int studentId;
  final double hours;
  final String serviceDate; // YYYY-MM-DD
  final String evidenceNote;
  final String status; // SUBMITTED | VERIFIED | APPROVED | REJECTED
  final String? rejectReason;

  factory Submission.fromJson(Map<String, dynamic> json) {
    return Submission(
      id: (json['id'] as num).toInt(),
      activityId: (json['activityId'] as num).toInt(),
      studentId: (json['studentId'] as num).toInt(),
      hours: (json['hours'] as num).toDouble(),
      serviceDate: json['serviceDate'] as String,
      evidenceNote: json['evidenceNote'] as String,
      status: json['status'] as String,
      rejectReason: json['rejectReason'] as String?,
    );
  }
}

/// What the backend returns after a coordinator approves hours.
class ApprovalResult {
  const ApprovalResult({
    required this.id,
    required this.studentId,
    required this.remainingHours,
    required this.progressStatus,
  });

  final int id;
  final int studentId;
  final double remainingHours;
  final String progressStatus; // IN_PROGRESS | COMPLETED | OVERDUE

  factory ApprovalResult.fromJson(Map<String, dynamic> json) {
    return ApprovalResult(
      id: (json['id'] as num).toInt(),
      studentId: (json['studentId'] as num).toInt(),
      remainingHours: (json['remainingHours'] as num).toDouble(),
      progressStatus: json['progressStatus'] as String,
    );
  }
}

class SummaryTotals {
  const SummaryTotals({
    required this.students,
    required this.completed,
    required this.inProgress,
    required this.overdue,
    required this.approvedHours,
    required this.remainingHours,
  });

  final int students;
  final int completed;
  final int inProgress;
  final int overdue;
  final double approvedHours;
  final double remainingHours;

  factory SummaryTotals.fromJson(Map<String, dynamic> json) {
    return SummaryTotals(
      students: (json['students'] as num).toInt(),
      completed: (json['completed'] as num).toInt(),
      inProgress: (json['inProgress'] as num).toInt(),
      overdue: (json['overdue'] as num).toInt(),
      approvedHours: (json['approvedHours'] as num).toDouble(),
      remainingHours: (json['remainingHours'] as num).toDouble(),
    );
  }
}

/// Progress of all students (coordinator / adviser view).
class ProgressSummary {
  const ProgressSummary({required this.totals, required this.students});

  final SummaryTotals totals;
  final List<StudentProgress> students;

  factory ProgressSummary.fromJson(Map<String, dynamic> json) {
    final rows = (json['students'] as List<dynamic>? ?? <dynamic>[]);
    return ProgressSummary(
      totals: SummaryTotals.fromJson(json['totals'] as Map<String, dynamic>),
      students: rows.map((row) => StudentProgress.fromJson(row as Map<String, dynamic>)).toList(),
    );
  }
}