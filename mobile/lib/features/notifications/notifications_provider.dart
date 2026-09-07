import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_provider.dart';
import 'models/app_notification.dart';
import 'notifications_repository.dart';

class NotificationsNotifier extends AsyncNotifier<NotificationsPage> {
  NotificationsRepository get _repo => ref.read(notificationsRepositoryProvider);

  @override
  Future<NotificationsPage> build() async {
    // Kullanıcı değişince (giriş/çıkış) liste yeniden yüklenir.
    final userId = ref.watch(authProvider.select((s) => s.user?.id));
    if (userId == null) return const NotificationsPage();
    return _repo.list();
  }

  Future<void> refresh() async {
    state = const AsyncValue<NotificationsPage>.loading().copyWithPrevious(state);
    state = await AsyncValue.guard(_repo.list);
  }

  Future<void> markRead(String id) async {
    final current = state.valueOrNull;
    if (current == null) return;
    final target = current.items.where((n) => n.id == id).firstOrNull;
    if (target == null || target.isRead) return;

    final now = DateTime.now();
    state = AsyncValue.data(
      current.copyWith(
        items: current.items
            .map((n) => n.id == id ? n.copyWith(readAt: now) : n)
            .toList(),
        unreadCount: (current.unreadCount - 1).clamp(0, 1 << 30),
      ),
    );
    try {
      await _repo.markRead(id);
    } catch (_) {
      // Sunucu hatasında bir sonraki yenilemede düzelecek.
    }
  }

  Future<void> markAllRead() async {
    final current = state.valueOrNull;
    if (current == null) return;
    final now = DateTime.now();
    state = AsyncValue.data(
      current.copyWith(
        items: current.items
            .map((n) => n.isRead ? n : n.copyWith(readAt: now))
            .toList(),
        unreadCount: 0,
      ),
    );
    try {
      await _repo.markAllRead();
    } catch (_) {}
  }
}

final notificationsProvider =
    AsyncNotifierProvider<NotificationsNotifier, NotificationsPage>(
  NotificationsNotifier.new,
);

/// Sadece okunmamış sayısı (app bar rozeti için).
final unreadCountProvider = Provider<int>((ref) {
  return ref.watch(notificationsProvider).valueOrNull?.unreadCount ?? 0;
});
