import '../../../core/utils/json.dart';

/// `GET /me/assignments` → `personnel`.
class SelfPersonnel {
  const SelfPersonnel({
    required this.id,
    this.fullName = '',
    this.photo,
    this.qrPayload,
  });

  final int id;
  final String fullName;
  final String? photo;

  /// `ESAS:PER:<uuid>` – saha sorumlusuna gösterilecek QR.
  final String? qrPayload;

  factory SelfPersonnel.fromJson(Map<String, dynamic> json) {
    final full = asString(json['full_name']);
    return SelfPersonnel(
      id: asInt(json['id']),
      fullName: full.isNotEmpty
          ? full
          : '${asString(json['first_name'])} ${asString(json['last_name'])}'.trim(),
      photo: asStringOrNull(json['photo']),
      qrPayload: asStringOrNull(json['qr_payload']),
    );
  }
}

/// `assignments[].project_day` (proje, müşteri, sorumlu ile).
class SelfDay {
  const SelfDay({
    required this.id,
    this.date,
    this.status = '',
    this.notes,
    this.venueLat,
    this.venueLng,
    this.projectId,
    this.projectName = '',
    this.venueAddress,
    this.customerName = '',
    this.supervisorName = '',
    this.supervisorPhone,
  });

  final int id;
  final DateTime? date;
  final String status;
  final String? notes;
  final double? venueLat;
  final double? venueLng;
  final int? projectId;
  final String projectName;
  final String? venueAddress;
  final String customerName;
  final String supervisorName;
  final String? supervisorPhone;

  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';

  factory SelfDay.fromJson(Map<String, dynamic> json) {
    final project = asMap(json['project']);
    final customer = asMap(project['customer']);
    final supervisor = asMap(json['supervisor']);
    final lat = json['venue_lat'] ?? project['venue_lat'];
    final lng = json['venue_lng'] ?? project['venue_lng'];
    return SelfDay(
      id: asInt(json['id']),
      date: asDateOnly(json['date']),
      status: asString(json['status']),
      notes: asStringOrNull(json['notes']),
      venueLat: lat == null ? null : asDouble(lat),
      venueLng: lng == null ? null : asDouble(lng),
      projectId: asIntOrNull(project['id'] ?? json['project_id']),
      projectName: asString(project['name']),
      venueAddress: asStringOrNull(project['venue_address']),
      customerName: asString(customer['name']),
      supervisorName: asString(supervisor['name']),
      supervisorPhone: asStringOrNull(supervisor['phone']),
    );
  }
}

/// `assignments[]` satırı – personelin kendi görevlendirmesi.
class SelfAssignment {
  const SelfAssignment({
    required this.id,
    required this.projectDayId,
    this.zone = '',
    this.presence = '',
    this.checkInTime,
    this.checkOutTime,
    this.breakStartedAt,
    this.breakMinutes = 0,
    this.dailyWage = 0,
    this.isChecked = false,
    this.day,
  });

  final int id;
  final int projectDayId;
  final String zone;

  /// assigned | checked_in | on_break | checked_out | absent
  final String presence;
  final String? checkInTime;
  final String? checkOutTime;
  final DateTime? breakStartedAt;
  final int breakMinutes;
  final double dailyWage;

  /// Saha sorumlusu girişi doğruladı mı.
  final bool isChecked;
  final SelfDay? day;

  bool get isCheckedIn => checkInTime?.isNotEmpty ?? false;
  bool get isCheckedOut => checkOutTime?.isNotEmpty ?? false;
  bool get isOnBreak => presence == 'on_break' && !isCheckedOut;
  bool get isAbsent => presence == 'absent';
  bool get isOnSite => isCheckedIn && !isCheckedOut;
  bool get needsVerification => isCheckedIn && !isChecked;

  String get effectivePresence {
    if (presence.isNotEmpty) return presence;
    if (isCheckedOut) return 'checked_out';
    if (isCheckedIn) return 'checked_in';
    return 'assigned';
  }

  /// Gün kapanmadıysa ve çıkış yapılmadıysa işlem (giriş/mola) yapılabilir.
  bool get canAct => !(day?.isCompleted ?? false) && !(day?.isCancelled ?? false) && !isCheckedOut;

  factory SelfAssignment.fromJson(Map<String, dynamic> json) {
    final dayRaw = json['project_day'];
    final zoneRaw = json['zone'];
    return SelfAssignment(
      id: asInt(json['id']),
      projectDayId: asInt(json['project_day_id'], asInt(asMap(dayRaw)['id'])),
      zone: zoneRaw is Map ? asString(zoneRaw['name']) : asString(zoneRaw),
      presence: asString(json['presence']),
      checkInTime: asStringOrNull(json['check_in_time']),
      checkOutTime: asStringOrNull(json['check_out_time']),
      breakStartedAt: asDateTime(json['break_started_at']),
      breakMinutes: asInt(json['break_minutes']),
      dailyWage: asDouble(json['daily_wage']),
      isChecked: asBool(json['is_checked']),
      day: dayRaw is Map ? SelfDay.fromJson(asMap(dayRaw)) : null,
    );
  }
}

/// `GET /me/assignments` → `{personnel, assignments, today}`.
class SelfAssignments {
  const SelfAssignments({
    required this.personnel,
    this.assignments = const [],
    this.today,
  });

  final SelfPersonnel personnel;
  final List<SelfAssignment> assignments;
  final DateTime? today;

  DateTime get _today {
    final t = today ?? DateTime.now();
    return DateTime(t.year, t.month, t.day);
  }

  bool _isToday(SelfAssignment a) {
    final d = a.day?.date;
    return d != null && d.year == _today.year && d.month == _today.month && d.day == _today.day;
  }

  /// Bugünkü görev (birden fazlaysa kapanmamış olan; yoksa ilki).
  SelfAssignment? get todays {
    final list = assignments.where(_isToday).toList();
    if (list.isEmpty) return null;
    return list.where((a) => !(a.day?.isCompleted ?? false)).firstOrNull ?? list.first;
  }

  /// Gelecek günlerdeki görevler (tarihe göre).
  List<SelfAssignment> get upcoming => assignments
      .where((a) => a.day?.date != null && a.day!.date!.isAfter(_today))
      .toList()
    ..sort((a, b) => a.day!.date!.compareTo(b.day!.date!));

  /// Geçmiş (dünkü) görevler.
  List<SelfAssignment> get past => assignments
      .where((a) => a.day?.date != null && a.day!.date!.isBefore(_today))
      .toList();

  factory SelfAssignments.fromJson(Map<String, dynamic> json) => SelfAssignments(
        personnel: SelfPersonnel.fromJson(asMap(json['personnel'])),
        assignments: asMapList(json['assignments']).map(SelfAssignment.fromJson).toList(),
        today: asDateOnly(json['today']),
      );
}

/// `POST /me/check-in` gövdesi.
class SelfCheckInRequest {
  const SelfCheckInRequest({
    this.zonePayload,
    this.zone,
    this.projectDayId,
    this.lat,
    this.lng,
  });

  final String? zonePayload;
  final String? zone;
  final int? projectDayId;
  final double? lat;
  final double? lng;

  Map<String, dynamic> toJson() => {
        if (zonePayload != null && zonePayload!.isNotEmpty) 'zone_payload': zonePayload,
        if (zone != null && zone!.isNotEmpty) 'zone': zone,
        if (projectDayId != null) 'project_day_id': projectDayId,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
      };
}

/// `POST /field/location` gövdesi.
class LocationReport {
  const LocationReport({
    required this.lat,
    required this.lng,
    this.accuracy,
    this.projectDayId,
    this.recordedAt,
  });

  final double lat;
  final double lng;

  /// Metre (sunucu tam sayı bekler).
  final double? accuracy;
  final int? projectDayId;
  final DateTime? recordedAt;

  Map<String, dynamic> toJson() => {
        'lat': lat,
        'lng': lng,
        if (accuracy != null && accuracy! >= 0) 'accuracy': accuracy!.round(),
        if (projectDayId != null) 'project_day_id': projectDayId,
        if (recordedAt != null) 'recorded_at': recordedAt!.toUtc().toIso8601String(),
      };
}

/// `/me/check-in`, `/me/break/*` yanıtı: `{message, assignment}`.
class SelfActionResult {
  const SelfActionResult({this.message, this.assignment});

  final String? message;
  final SelfAssignment? assignment;

  factory SelfActionResult.fromJson(Map<String, dynamic> json) => SelfActionResult(
        message: asStringOrNull(json['message']),
        assignment: json['assignment'] is Map
            ? SelfAssignment.fromJson(asMap(json['assignment']))
            : null,
      );
}
