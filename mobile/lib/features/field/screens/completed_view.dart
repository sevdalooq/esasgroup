import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../field_providers.dart';
import '../models/models.dart';
import '../widgets/bottom_action_bar.dart';
import '../widgets/inventory_row.dart';
import '../widgets/personnel_row.dart';
import '../widgets/photo_widgets.dart';
import 'active_hub_view.dart';

/// AŞAMA D – Tamamlandı (status: completed) ve diğer salt okunur durumlar
/// (örn. iptal): özet + fotoğraflar + listeler; "Bugünkü Görevlere Dön".
class CompletedView extends ConsumerWidget {
  const CompletedView({super.key, required this.detail});

  final ProjectDayDetail detail;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final api = ref.watch(apiClientProvider);
    final s = detail.summary;
    final categories = ref.watch(expenseCategoriesProvider).valueOrNull ?? ExpenseCategory.defaults;
    final worked = detail.personnel.where((p) => p.isCheckedIn).toList();
    final color = dayStatusColor(detail.status);

    return Scaffold(
      backgroundColor: Colors.transparent,
      bottomNavigationBar: BottomActionBar(
        primaryLabel: 'Bugünkü Görevlere Dön',
        primaryIcon: Icons.home_outlined,
        onPrimary: () {
          if (context.canPop()) {
            context.pop();
          } else {
            context.go('/');
          }
        },
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  Icon(detail.isCompleted ? Icons.verified_outlined : Icons.info_outline, color: color),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      detail.isCompleted
                          ? 'Gün tamamlandı. Kayıtlar salt okunur.'
                          : 'Bu gün için işlem yapılamaz (durum: ${dayPhaseLabel(detail.status)}).',
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(detail.projectName.isEmpty ? 'Proje' : detail.projectName,
                        style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                    Text(
                      [
                        if (detail.customerName.isNotEmpty) detail.customerName,
                        formatDate(detail.date),
                        if (detail.supervisorName.isNotEmpty) 'Sorumlu: ${detail.supervisorName}',
                      ].join(' · '),
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline),
                    ),
                    const Divider(height: 24),
                    Row(
                      children: [
                        _Stat(label: 'Giriş', value: '${s.checkedInCount}/${s.personnelCount}'),
                        _Stat(label: 'Çıkış', value: '${s.checkedOutCount}'),
                        _Stat(label: 'Teslim', value: '${s.inventoryDelivered}/${s.inventoryCount}'),
                        _Stat(label: 'İade', value: '${s.inventoryReturned}'),
                      ],
                    ),
                    const Divider(height: 24),
                    _Line('Hakediş', formatMoney(s.totalEarnings)),
                    _Line('Mesai (${s.overtimePersonnelCount} kişi)', formatMoney(s.totalOvertime)),
                    _Line('Ödenen', formatMoney(s.totalPaid)),
                    _Line('Kalan', formatMoney(s.totalPending)),
                    _Line('Masraf (${detail.expenses.length})', formatMoney(detail.expensesTotal)),
                    if (s.inventoryDamaged > 0 || s.inventoryPendingReturn > 0)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          [
                            if (s.inventoryPendingReturn > 0) 'İade bekleyen: ${s.inventoryPendingReturn}',
                            if (s.inventoryDamaged > 0) 'Hasarlı: ${s.inventoryDamaged}',
                          ].join(' · '),
                          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.error),
                        ),
                      ),
                    if (detail.notes != null) ...[
                      const Divider(height: 24),
                      Text(detail.notes!, style: theme.textTheme.bodyMedium),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text('Fotoğraflar', style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: NetworkPhotoTile(label: 'Gün başlangıcı', url: api.resolveUrl(detail.startPhoto)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: NetworkPhotoTile(label: 'Gün bitişi', url: api.resolveUrl(detail.endPhoto)),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text('Personel (${worked.length}/${detail.personnel.length})',
                style: theme.textTheme.titleMedium),
            const SizedBox(height: 6),
            if (detail.personnel.isEmpty)
              const Card(child: ListTile(title: Text('Personel kaydı yok')))
            else
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    for (var i = 0; i < detail.personnel.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      Builder(builder: (context) {
                        final p = detail.personnel[i];
                        return PersonnelRow(
                          assignment: p,
                          dense: true,
                          subtitle: p.isCheckedIn
                              ? '${p.zone.isEmpty ? '' : '${p.zone} · '}${formatTime(p.checkInTime)} → ${formatTime(p.checkOutTime)} · '
                                  'Hakediş ${formatMoney(p.totalEarnings)} · ${paymentStatusLabel(p.paymentStatus)}'
                              : 'Gelmedi',
                          trailing: p.isCheckedIn
                              ? Text(formatMoney(p.totalEarnings),
                                  style: const TextStyle(fontWeight: FontWeight.w600))
                              : null,
                        );
                      }),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 16),
            Text('Envanter (${detail.inventory.length})', style: theme.textTheme.titleMedium),
            const SizedBox(height: 6),
            if (detail.inventory.isEmpty)
              const Card(child: ListTile(title: Text('Envanter kaydı yok')))
            else
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    for (var i = 0; i < detail.inventory.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      InventoryRow(
                        item: detail.inventory[i],
                        holderName: detail.inventory[i].assignedToName ??
                            detail.assignmentById(detail.inventory[i].assignedToAssignmentId)?.displayName,
                        dense: true,
                        trailing: const SizedBox.shrink(),
                      ),
                    ],
                  ],
                ),
              ),
            if (detail.expenses.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Masraflar (${detail.expenses.length})', style: theme.textTheme.titleMedium),
              const SizedBox(height: 6),
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    for (var i = 0; i < detail.expenses.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      ExpenseTile(
                        expense: detail.expenses[i],
                        categoryName: ExpenseCategory.nameOf(categories, detail.expenses[i].category),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        children: [
          Text(value, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
          Text(label, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline)),
          ),
          Text(value, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
