import '../../../core/utils/json.dart';

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    this.readAt,
    this.createdAt,
    this.data = const {},
  });

  final String id;
  final String title;
  final String body;
  final DateTime? readAt;
  final DateTime? createdAt;
  final Map<String, dynamic> data;

  bool get isRead => readAt != null;

  /// Bildirim bir proje gününe bağlıysa (`project_day_id`) kimliği.
  int? get projectDayId =>
      asIntOrNull(data['project_day_id'] ?? data['day_id']);

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = asMap(json['data']);
    return AppNotification(
      id: asString(json['id']),
      title: asString(
        data['title'],
        asString(data['subject'], asString(json['title'], 'Bildirim')),
      ),
      body: asString(
        data['body'],
        asString(data['message'], asString(json['body'])),
      ),
      readAt: asDateTime(json['read_at']),
      createdAt: asDateTime(json['created_at']),
      data: data,
    );
  }

  AppNotification copyWith({DateTime? readAt}) => AppNotification(
        id: id,
        title: title,
        body: body,
        readAt: readAt ?? this.readAt,
        createdAt: createdAt,
        data: data,
      );
}

class NotificationsPage {
  const NotificationsPage({this.items = const [], this.unreadCount = 0});

  final List<AppNotification> items;
  final int unreadCount;

  NotificationsPage copyWith({List<AppNotification>? items, int? unreadCount}) =>
      NotificationsPage(
        items: items ?? this.items,
        unreadCount: unreadCount ?? this.unreadCount,
      );
}
