import '../utils/json.dart';
import 'pusher_client.dart';

/// `day.updated` olayı (private-live ve private-day.{id}).
class DayUpdatedEvent {
  const DayUpdatedEvent({
    required this.projectDayId,
    this.projectId,
    this.type = '',
    this.payload = const {},
    this.actorId,
    this.at,
  });

  static const eventName = 'day.updated';

  final int projectDayId;
  final int? projectId;

  /// check_in | check_out | break_start | break_end | absent | absent_cleared |
  /// assignment | assignment_removed | inventory | expense | day_status | change
  final String type;
  final Map<String, dynamic> payload;
  final int? actorId;
  final DateTime? at;

  int? get assignmentId => asIntOrNull(payload['assignment_id']);
  int? get personnelId => asIntOrNull(payload['personnel_id']);
  String get name => asString(payload['name']);
  String get presence => asString(payload['presence']);
  String get status => asString(payload['status']);

  bool get isPersonnelEvent => const {
        'check_in',
        'check_out',
        'break_start',
        'break_end',
        'absent',
        'absent_cleared',
        'assignment',
        'assignment_removed',
      }.contains(type);

  /// Bildirim metni ("Ahmet Yılmaz giriş yaptı"); gösterilmeyecekse null.
  String? get toastMessage {
    final who = name.isEmpty ? 'Personel' : name;
    switch (type) {
      case 'check_in':
        return '$who giriş yaptı';
      case 'check_out':
        return '$who çıkış yaptı';
      case 'break_start':
        return '$who molaya çıktı';
      case 'break_end':
        return '$who moladan döndü';
      case 'absent':
        return '$who gelmedi olarak işaretlendi';
      case 'absent_cleared':
        return '$who için gelmedi işareti kaldırıldı';
      case 'assignment':
        return asString(payload['action']) == 'created' ? '$who güne eklendi' : null;
      case 'assignment_removed':
        return '$who günden çıkarıldı';
      case 'day_status':
        switch (status) {
          case 'active':
            return 'Gün başlatıldı';
          case 'completed':
            return 'Gün tamamlandı';
          case 'cancelled':
            return 'Gün iptal edildi';
        }
        return null;
      default:
        return null;
    }
  }

  factory DayUpdatedEvent.fromJson(Map<String, dynamic> json) => DayUpdatedEvent(
        projectDayId: asInt(json['project_day_id']),
        projectId: asIntOrNull(json['project_id']),
        type: asString(json['type']),
        payload: asMap(json['payload']),
        actorId: asIntOrNull(json['actor_id']),
        at: asDateTime(json['at']),
      );

  static DayUpdatedEvent? fromPusher(PusherEvent e) =>
      e.event == eventName ? DayUpdatedEvent.fromJson(e.dataMap) : null;
}

/// `personnel.location` olayı.
class PersonnelLocationEvent {
  const PersonnelLocationEvent({
    required this.personnelId,
    this.projectDayId,
    this.name = '',
    this.photo,
    this.lat = 0,
    this.lng = 0,
    this.accuracy,
    this.recordedAt,
  });

  static const eventName = 'personnel.location';

  final int personnelId;
  final int? projectDayId;
  final String name;
  final String? photo;
  final double lat;
  final double lng;
  final int? accuracy;
  final DateTime? recordedAt;

  factory PersonnelLocationEvent.fromJson(Map<String, dynamic> json) => PersonnelLocationEvent(
        personnelId: asInt(json['personnel_id']),
        projectDayId: asIntOrNull(json['project_day_id']),
        name: asString(json['name']),
        photo: asStringOrNull(json['photo']),
        lat: asDouble(json['lat']),
        lng: asDouble(json['lng']),
        accuracy: asIntOrNull(json['accuracy']),
        recordedAt: asDateTime(json['recorded_at']),
      );

  static PersonnelLocationEvent? fromPusher(PusherEvent e) =>
      e.event == eventName ? PersonnelLocationEvent.fromJson(e.dataMap) : null;
}
