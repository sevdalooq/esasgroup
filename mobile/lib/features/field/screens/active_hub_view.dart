import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../models/models.dart';
import '../widgets/bottom_action_bar.dart';
import '../widgets/expense_sheet.dart';
import '../widgets/inventory_row.dart';
import '../widgets/personnel_row.dart';
import 'day_flow_actions.dart';

/// AŞAMA B – Etkinlik Devam Ediyor (status: active)
/// KPI kutuları + işlemler (geç giriş, son dakika ekle, envanter teslim,
/// masraf) + büyük kırmızı "Gün Sonu Akışını Başlat".
class ActiveHubView extends ConsumerWidget {
  const ActiveHubView({super.key, required this.detail});

  final ProjectDayDetail detail;

  Future<void> _lateCheckIn(BuildContext context, DayFlowActions actions) async {
    final missing = detail.notCheckedIn;
    if (missing.isEmpty) {
      showSnack(context, 'Listedeki tüm personel giriş yaptı. Yeni kişi için QR okutun.');
      return;
    }
    final picked = await showModalBottomSheet<PersonnelAssignment>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      builder: (ctx) => _PickSheet(
        title: 'Geç gelen personel',
        subtitle: 'Giriş yapmamış ${missing.length} personel',
        children: [
          for (final p in missing)
            PersonnelRow(
              assignment: p,
              onTap: () => Navigator.of(ctx).pop(p),
              trailing: const Icon(Icons.login),
            ),
        ],
      ),
    );
    if (picked == null || !context.mounted) return;
    await actions.checkInManual(picked);
  }

  Future<void> _deliverInventory(BuildContext context, DayFlowActions actions) async {
    final pending = detail.undeliveredInventory;
    final choice = await showModalBottomSheet<Object>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      builder: (ctx) => _PickSheet(
        title: 'Envanter teslim',
        subtitle: pending.isEmpty
            ? 'Teslim bekleyen envanter yok'
            : 'Teslim bekleyen ${pending.length} envanter',
        action: FilledButton.tonalIcon(
          onPressed: () => Navigator.of(ctx).pop('scan'),
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('QR ile teslim et'),
        ),
        children: [
          for (final item in pending)
            InventoryRow(
              item: item,
              onDeliver: () => Navigator.of(ctx).pop(item),
            ),
        ],
      ),
    );
    if (choice == null || !context.mounted) return;
    if (choice == 'scan') {
      await actions.deliverByScan();
    } else if (choice is InventoryAssignment) {
      await actions.deliverItem(choice);
    }
  }

  Future<void> _startClosing(BuildContext context, WidgetRef ref) async {
    final onSite = detail.onSite.length;
    final ok = await confirmDialog(
      context,
      title: 'Gün sonu akışı',
      message: 'Sırayla personel çıkışı, gün sonu özeti ve kapanış fotoğrafı adımları açılacak.'
          '${onSite > 0 ? '\n\nSahada $onSite personel var; her biri için çıkış kaydı alınacak.' : ''}'
          '\n\nGün sonu akışından etkinlik ekranına geri dönebilirsiniz.',
      confirmText: 'Başlat',
    );
    if (!ok) return;
    ref.read(dayFlowProvider(detail.id).notifier).startClosing();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final actions = DayFlowActions(context: context, ref: ref, detail: detail);
    final summary = detail.summary;
    final categories = ref.watch(expenseCategoriesProvider).valueOrNull ?? ExpenseCategory.defaults;

    return Scaffold(
      backgroundColor: Colors.transparent,
      bottomNavigationBar: BottomActionBar(
        primaryLabel: 'Gün Sonu Akışını Başlat',
        primaryIcon: Icons.stop_circle_outlined,
        primaryColor: theme.colorScheme.error,
        onPrimary: () => _startClosing(context, ref),
        hint: 'Sahada ${detail.onSite.length} personel · ${detail.unreturnedInventory.length} envanter dışarıda',
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: kSuccessGreen.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.play_circle_fill, color: kSuccessGreen),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Etkinlik devam ediyor${detail.startedAt != null ? ' · ${formatTime(detail.startedAt!.toIso8601String())} başladı' : ''}',
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _KpiCard(
                    icon: Icons.how_to_reg_outlined,
                    value: '${summary.checkedInCount}/${summary.personnelCount}',
                    label: 'Giriş yapan',
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _KpiCard(
                    icon: Icons.inventory_2_outlined,
                    value: '${summary.inventoryDelivered}/${summary.inventoryCount}',
                    label: 'Teslim edilen',
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _KpiCard(
                    icon: Icons.receipt_long_outlined,
                    value: formatMoney(detail.expensesTotal),
                    label: 'Masraf',
                    small: true,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text('İşlemler', style: theme.textTheme.titleMedium),
            const SizedBox(height: 6),
            Card(
              clipBehavior: Clip.antiAlias,
              child: Column(
                children: [
                  _ActionTile(
                    icon: Icons.login,
                    title: 'Geç Gelen Personel Girişi',
                    subtitle: detail.notCheckedIn.isEmpty
                        ? 'Giriş yapmayan personel yok'
                        : '${detail.notCheckedIn.length} personel giriş yapmadı',
                    onTap: () => _lateCheckIn(context, actions),
                  ),
                  const Divider(height: 1),
                  _ActionTile(
                    icon: Icons.person_add_alt_1_outlined,
                    title: 'Son Dakika Personel Ekle',
                    subtitle: 'QR okutarak güne ekle ve giriş yap',
                    onTap: actions.checkInByScan,
                  ),
                  const Divider(height: 1),
                  _ActionTile(
                    icon: Icons.inventory_2_outlined,
                    title: 'Envanter Teslim',
                    subtitle: detail.undeliveredInventory.isEmpty
                        ? 'Teslim bekleyen envanter yok'
                        : '${detail.undeliveredInventory.length} envanter teslim bekliyor',
                    onTap: () => _deliverInventory(context, actions),
                  ),
                  const Divider(height: 1),
                  _ActionTile(
                    icon: Icons.receipt_long_outlined,
                    title: 'Masraf Ekle',
                    subtitle: 'Yemek, ulaşım, malzeme… fiş fotoğrafıyla',
                    onTap: actions.addExpense,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _PersonnelSection(detail: detail, actions: actions),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text('Masraflar (${detail.expenses.length})', style: theme.textTheme.titleMedium),
                ),
                Text(
                  formatMoney(detail.expensesTotal),
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (detail.expenses.isEmpty)
              Card(
                child: ListTile(
                  leading: Icon(Icons.receipt_long_outlined, color: theme.colorScheme.outline),
                  title: const Text('Henüz masraf girilmedi'),
                  trailing: TextButton(onPressed: actions.addExpense, child: const Text('Ekle')),
                ),
              )
            else
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    for (var i = 0; i < detail.expenses.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      ExpenseTile(
                        expense: detail.expenses[i],
                        categoryName: ExpenseCategory.nameOf(categories, detail.expenses[i].category),
                        onDelete: detail.expenses[i].isPending
                            ? () => actions.deleteExpense(detail.expenses[i])
                            : null,
                      ),
                    ],
                  ],
                ),
              ),
            if (detail.notes != null) ...[
              const SizedBox(height: 16),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.sticky_note_2_outlined),
                  title: const Text('Not'),
                  subtitle: Text(detail.notes!),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Personel durumu listesi: presence çipleri, uzun basma / ⋮ ile
/// Gelmedi ↔ geri al, Mola başlat / Moladan döndü, Çıkış.
class _PersonnelSection extends StatelessWidget {
  const _PersonnelSection({required this.detail, required this.actions});

  final ProjectDayDetail detail;
  final DayFlowActions actions;

  static int _rank(PersonnelAssignment p) {
    if (p.isOnBreak) return 0;
    if (p.needsVerification && !p.isCheckedOut) return 1;
    if (p.isOnSite) return 2;
    if (!p.isCheckedIn && !p.isAbsent) return 3;
    if (p.isAbsent) return 4;
    return 5;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final list = [...detail.personnel]..sort((a, b) => _rank(a).compareTo(_rank(b)));
    final onSite = detail.onSite.where((p) => !p.isOnBreak).length;
    final onBreak = detail.onBreak.length;
    final absent = detail.absent.length;
    final unverified = detail.awaitingVerification.length;
    final summary = [
      'Sahada $onSite',
      if (onBreak > 0) 'Molada $onBreak',
      if (absent > 0) 'Gelmedi $absent',
      if (unverified > 0) 'Doğrulanmadı $unverified',
    ].join(' · ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text('Personel (${detail.personnel.length})', style: theme.textTheme.titleMedium),
            ),
            Flexible(
              child: Text(
                summary,
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        if (list.isEmpty)
          const Card(child: ListTile(title: Text('Bu güne atanmış personel yok')))
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < list.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  PersonnelRow(
                    assignment: list[i],
                    onTap: () => actions.showPersonnelMenu(list[i]),
                    onLongPress: () => actions.showPersonnelMenu(list[i]),
                    trailing: IconButton(
                      tooltip: 'Durum',
                      icon: const Icon(Icons.more_vert),
                      onPressed: () => actions.showPersonnelMenu(list[i]),
                    ),
                  ),
                ],
              ],
            ),
          ),
      ],
    );
  }
}

/// Masraf satırı (hub ve özetlerde ortak).
class ExpenseTile extends StatelessWidget {
  const ExpenseTile({
    super.key,
    required this.expense,
    required this.categoryName,
    this.onDelete,
  });

  final DayExpense expense;
  final String categoryName;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final Color statusColor = switch (expense.status) {
      'approved' => kSuccessGreen,
      'rejected' => theme.colorScheme.error,
      _ => kWarnAmber,
    };
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: theme.colorScheme.surfaceContainerHighest,
        child: Icon(expenseCategoryIcon(expense.category), color: theme.colorScheme.onSurfaceVariant),
      ),
      title: Text(expense.description, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Row(
        children: [
          Flexible(child: Text(categoryName, overflow: TextOverflow.ellipsis)),
          const SizedBox(width: 8),
          StatusChip(label: expenseStatusLabel(expense.status), color: statusColor),
          if (expense.receiptPhoto != null) ...[
            const SizedBox(width: 6),
            Icon(Icons.receipt_outlined, size: 16, color: theme.colorScheme.outline),
          ],
        ],
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(formatMoney(expense.amount),
              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
          if (onDelete != null)
            IconButton(
              tooltip: 'Sil',
              icon: const Icon(Icons.delete_outline),
              onPressed: onDelete,
            ),
        ],
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({
    required this.icon,
    required this.value,
    required this.label,
    this.small = false,
  });

  final IconData icon;
  final String value;
  final String label;
  final bool small;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: theme.colorScheme.primary, size: 20),
            const SizedBox(height: 6),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                style: (small ? theme.textTheme.titleMedium : theme.textTheme.titleLarge)
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
            Text(label, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
          ],
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.12),
        child: Icon(icon, color: theme.colorScheme.primary),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: const Icon(Icons.chevron_right),
    );
  }
}

/// Basit seçim alt sayfası (başlık + isteğe bağlı buton + satırlar).
class _PickSheet extends StatelessWidget {
  const _PickSheet({
    required this.title,
    required this.subtitle,
    required this.children,
    this.action,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(title, style: theme.textTheme.titleLarge),
                Text(subtitle,
                    style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline)),
                if (action != null) ...[const SizedBox(height: 10), action!],
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: children.isEmpty
                ? const EmptyView(icon: Icons.inbox_outlined, title: 'Kayıt yok')
                : ListView.separated(
                    itemCount: children.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) => children[i],
                  ),
          ),
        ],
      ),
    );
  }
}
