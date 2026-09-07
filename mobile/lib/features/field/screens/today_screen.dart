import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../../auth/auth_provider.dart';
import '../../notifications/notifications_provider.dart';
import '../field_providers.dart';
import '../models/models.dart';

class TodayScreen extends ConsumerWidget {
  const TodayScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(todayProvider);
    final user = ref.watch(authProvider.select((s) => s.user));

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Bugün'),
            Text(
              formatDate(DateTime.now()),
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.normal),
            ),
          ],
        ),
        actions: [
          const _NotificationBell(),
          PopupMenuButton<String>(
            tooltip: 'Hesap',
            icon: const Icon(Icons.account_circle_outlined),
            onSelected: (value) async {
              if (value == 'logout') {
                final ok = await confirmDialog(
                  context,
                  title: 'Çıkış',
                  message: 'Oturumu kapatmak istiyor musunuz?',
                  confirmText: 'Çıkış Yap',
                );
                if (ok) await ref.read(authProvider.notifier).logout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem<String>(
                enabled: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user?.name ?? '',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Text(user?.email ?? '', style: const TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem<String>(
                value: 'logout',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.logout),
                  title: Text('Çıkış Yap'),
                ),
              ),
            ],
          ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorView(
          message: errorMessage(e),
          onRetry: () => ref.invalidate(todayProvider),
        ),
        data: (days) => RefreshIndicator(
          onRefresh: () => ref.refresh(todayProvider.future),
          child: days.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: const [
                    SizedBox(height: 120),
                    EmptyView(
                      icon: Icons.event_available_outlined,
                      title: 'Bugün için görev yok',
                      subtitle:
                          'Size atanmış bir proje günü olduğunda burada görünecek.\nYenilemek için aşağı çekin.',
                    ),
                  ],
                )
              : ListView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                  itemCount: days.length,
                  itemBuilder: (context, index) => _DayCard(day: days[index]),
                ),
        ),
      ),
    );
  }
}

class _NotificationBell extends ConsumerWidget {
  const _NotificationBell();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = ref.watch(unreadCountProvider);
    return IconButton(
      tooltip: 'Bildirimler',
      onPressed: () => context.push('/notifications'),
      icon: Badge(
        isLabelVisible: unread > 0,
        label: Text(unread > 99 ? '99+' : '$unread'),
        child: const Icon(Icons.notifications_outlined),
      ),
    );
  }
}

class _DayCard extends StatelessWidget {
  const _DayCard({required this.day});

  final ProjectDaySummary day;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = dayStatusColor(day.status);
    final c = day.counts;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/days/${day.id}'),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 6, color: color),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              day.projectName.isEmpty
                                  ? 'Proje #${day.projectId ?? day.id}'
                                  : day.projectName,
                              style: theme.textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 8),
                          StatusChip(
                            label: dayStatusLabel(day.status),
                            color: color,
                          ),
                        ],
                      ),
                      if (day.customerName.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          day.customerName,
                          style: theme.textTheme.bodyMedium
                              ?.copyWith(color: theme.colorScheme.outline),
                        ),
                      ],
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(Icons.calendar_today_outlined,
                              size: 14, color: theme.colorScheme.outline),
                          const SizedBox(width: 4),
                          Text(
                            formatShortDate(day.date),
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: c.personnelRatio,
                          minHeight: 6,
                          backgroundColor:
                              theme.colorScheme.surfaceContainerHighest,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          _ProgressChip(
                            icon: Icons.people_outline,
                            label:
                                'Personel ${c.personnelCheckedIn}/${c.personnelTotal}',
                            highlight: c.personnelTotal > 0 &&
                                c.personnelCheckedIn >= c.personnelTotal,
                          ),
                          _ProgressChip(
                            icon: Icons.inventory_2_outlined,
                            label: 'Teslim ${c.inventoryDelivered}',
                          ),
                          _ProgressChip(
                            icon: Icons.assignment_return_outlined,
                            label: 'İade ${c.inventoryReturned}',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.only(right: 8),
                child: Center(child: Icon(Icons.chevron_right)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProgressChip extends StatelessWidget {
  const _ProgressChip({
    required this.icon,
    required this.label,
    this.highlight = false,
  });

  final IconData icon;
  final String label;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final color = highlight ? const Color(0xFF2E7D32) : scheme.onSurfaceVariant;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: highlight
            ? const Color(0xFF2E7D32).withValues(alpha: 0.1)
            : scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
