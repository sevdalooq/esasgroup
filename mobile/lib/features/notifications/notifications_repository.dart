import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/json.dart';
import 'models/app_notification.dart';

class NotificationsRepository {
  NotificationsRepository(this._api);

  final ApiClient _api;

  /// GET /notifications → {data:[...], unread_count}
  Future<NotificationsPage> list() async {
    final res = await _api.get('/notifications');
    final body = res.data;
    List<Map<String, dynamic>> rows;
    int unread;
    if (body is List) {
      rows = asMapList(body);
      unread = rows.where((r) => r['read_at'] == null).length;
    } else {
      final map = asMap(body);
      rows = asMapList(map['data']);
      unread = asIntOrNull(map['unread_count']) ??
          rows.where((r) => r['read_at'] == null).length;
    }
    return NotificationsPage(
      items: rows.map(AppNotification.fromJson).toList(),
      unreadCount: unread,
    );
  }

  /// POST /notifications/{id}/read
  Future<void> markRead(String id) async {
    await _api.post('/notifications/$id/read');
  }

  /// POST /notifications/read-all
  Future<void> markAllRead() async {
    await _api.post('/notifications/read-all');
  }

  /// POST /devices {fcm_token}
  ///
  /// TODO(firebase): Firebase Messaging eklendiğinde gerçek FCM token'ı ile
  /// çağrılacak. Şimdilik token yoksa hiçbir şey yapmaz (no-op).
  Future<void> registerDevice(String? fcmToken) async {
    if (fcmToken == null || fcmToken.isEmpty) {
      debugPrint('[devices] FCM token yok, cihaz kaydı atlandı (TODO firebase).');
      return;
    }
    try {
      await _api.post('/devices', data: {'fcm_token': fcmToken});
    } catch (e) {
      debugPrint('[devices] Cihaz kaydı başarısız: $e');
    }
  }
}

final notificationsRepositoryProvider = Provider<NotificationsRepository>(
  (ref) => NotificationsRepository(ref.watch(apiClientProvider)),
);
