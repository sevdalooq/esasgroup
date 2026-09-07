import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/app_notification.dart';
import '../notifications_provider.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(notificationsProvider);
    final unread = async.valueOrNull?.unreadCount ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bildirimler'),
        actions: [
          if (unread > 0)
            TextButton(
              onPressed: () =>
                  ref.read(notificationsProvider.notifier).markAllRead(),
              style: TextButton.styleFrom(foregroundColor: Colors.white),
              child: const Text('Tümünü okundu yap'),
            ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorView(
          message: errorMessage(e),
          onRetry: () => ref.read(notificationsProvider.notifier).refresh(),
        ),
        data: (page) => RefreshIndicator(
          onRefresh: () => ref.read(notificationsProvider.notifier).refresh(),
          child: page.items.isEmpty
              ? ListView(
                  children: const [
                    SizedBox(height: 120),
                    EmptyView(
                      icon: Icons.notifications_none,
                      title: 'Bildirim yok',
                      subtitle: 'Yeni görev ve değişiklikler burada görünecek.',
                    ),
                  ],
                )
              : ListView.separated(
                  itemCount: page.items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) =>
                      _NotificationTile(item: page.items[index]),
                ),
        ),
      ),
    );
  }
}

class _NotificationTile extends ConsumerWidget {
  const _NotificationTile({required this.item});

  final AppNotification item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final weight = item.isRead ? FontWeight.normal : FontWeight.bold;
    return ListTile(
      tileColor: item.isRead
          ? null
          : theme.colorScheme.primary.withValues(alpha: 0.05),
      leading: CircleAvatar(
        backgroundColor: item.isRead
            ? theme.colorScheme.surfaceContainerHighest
            : theme.colorScheme.primary,
        child: Icon(
          Icons.notifications,
          color: item.isRead ? theme.colorScheme.outline : Colors.white,
        ),
      ),
      title: Text(item.title, style: TextStyle(fontWeight: weight)),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (item.body.isNotEmpty)
            Text(item.body, maxLines: 3, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
          Text(
            relativeTime(item.createdAt),
            style: theme.textTheme.bodySmall
                ?.copyWith(color: theme.colorScheme.outline),
          ),
        ],
      ),
      isThreeLine: item.body.isNotEmpty,
      onTap: () {
        ref.read(notificationsProvider.notifier).markRead(item.id);
        final dayId = item.projectDayId;
        if (dayId != null) context.push('/days/$dayId');
      },
    );
  }
}
