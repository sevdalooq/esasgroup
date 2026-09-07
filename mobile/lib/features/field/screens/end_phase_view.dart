import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../models/models.dart';
import '../widgets/bottom_action_bar.dart';
import '../widgets/inventory_row.dart';
import '../widgets/personnel_row.dart';
import '../widgets/photo_widgets.dart';
import '../widgets/step_indicator.dart';
import 'day_flow_actions.dart';

/// AŞAMA C – Gün Sonu (status: active + yerel "gün sonu akışı" bayrağı)
/// 1 Personel Çıkışı → 2 Gün Sonu Özeti → 3 Kapanış Fotoğrafı → "Günü Bitir"
class EndPhaseView extends ConsumerStatefulWidget {
  const EndPhaseView({super.key, required this.detail});

  final ProjectDayDetail detail;

  @override
  ConsumerState<EndPhaseView> createState() => _EndPhaseViewState();
}

class _EndPhaseViewState extends ConsumerState<EndPhaseView> {
  static const _steps = ['Personel Çıkışı', 'Özet', 'Fotoğraf'];

  XFile? _endPhoto;
  String _query = '';

  ProjectDayDetail get detail => widget.detail;
  DayFlowActions get _actions => DayFlowActions(context: context, ref: ref, detail: detail);
  DayFlowNotifier get _flow => ref.read(dayFlowProvider(detail.id).notifier);

  Future<void> _endDay() async {
    final unreturned = detail.unreturnedInventory.length;
    final ok = await confirmDialog(
      context,
      title: 'Günü bitir',
      message: [
        if (unreturned > 0) '$unreturned envanter henüz iade alınmadı.',
        if (_endPhoto == null) 'Kapanış fotoğrafı eklenmedi.',
        'Gün tamamlandı olarak işaretlenecek ve kayıtlar salt okunur olacak. Devam edilsin mi?',
      ].join('\n'),
      confirmText: 'Günü Bitir',
      destructive: true,
    );
    if (!ok || !mounted) return;
    await _actions.endDay(_endPhoto);
  }

  @override
  Widget build(BuildContext context) {
    final step = ref.watch(dayFlowProvider(detail.id).select((s) => s.endStep)).clamp(0, 2);

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: step == 0 && detail.onSite.isNotEmpty
          ? FloatingActionButton.extended(
              heroTag: 'fab_checkout',
              onPressed: _actions.checkOutByScan,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('QR ile Çıkış'),
            )
          : null,
      bottomNavigationBar: _bottomBar(step),
      body: Column(
        children: [
          StepIndicator(
            steps: _steps,
            current: step,
            phaseLabel: 'Gün Sonu',
            onStepTap: _flow.setEndStep,
          ),
          const Divider(height: 1),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
              child: switch (step) {
                0 => _checkOutStep(),
                1 => _summaryStep(),
                _ => _photoStep(),
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _bottomBar(int step) {
    switch (step) {
      case 0:
        final onSite = detail.onSite.length;
        return BottomActionBar(
          primaryLabel: 'Devam',
          onPrimary: onSite == 0 ? () => _flow.setEndStep(1) : null,
          secondaryLabel: 'Etkinliğe Dön',
          secondaryIcon: Icons.undo,
          onSecondary: _flow.cancelClosing,
          hint: onSite > 0
              ? '$onSite personel çıkış bekliyor; devam için tümünün çıkışı gerekli'
              : 'Sahadaki tüm personelin çıkışı yapıldı',
          hintIsWarning: onSite > 0,
        );
      case 1:
        return BottomActionBar(
          primaryLabel: 'Devam',
          onPrimary: () => _flow.setEndStep(2),
          secondaryLabel: 'Geri',
          onSecondary: () => _flow.setEndStep(0),
          hint: detail.unreturnedInventory.isNotEmpty
              ? '${detail.unreturnedInventory.length} envanter iade bekliyor'
              : null,
          hintIsWarning: detail.unreturnedInventory.isNotEmpty,
        );
      default:
        return BottomActionBar(
          primaryLabel: 'Günü Bitir',
          primaryIcon: Icons.flag_outlined,
          primaryColor: const Color(0xFF2B2A29),
          onPrimary: _endDay,
          secondaryLabel: 'Geri',
          onSecondary: () => _flow.setEndStep(1),
          hint: _endPhoto == null ? 'Fotoğraf isteğe bağlıdır' : null,
        );
    }
  }

  // ---------------- 1 · Personel çıkışı ----------------

  Widget _checkOutStep() {
    final theme = Theme.of(context);
    final q = _query.toLowerCase();
    final checkedIn = detail.personnel
        .where((p) => p.isCheckedIn)
        .where((p) => q.isEmpty || p.displayName.toLowerCase().contains(q))
        .toList()
      ..sort((a, b) {
        if (a.isCheckedOut != b.isCheckedOut) return a.isCheckedOut ? 1 : -1;
        return 0;
      });
    final total = detail.checkedInCount;
    final out = detail.checkedOutCount;
    final never = detail.notCheckedIn.length;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 96),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'Çıkış yapan $out / $total',
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
              ),
              if (never > 0)
                Text(
                  '$never kişi hiç gelmedi',
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: total == 0 ? 0 : out / total,
              minHeight: 6,
              backgroundColor: theme.colorScheme.surfaceContainerHighest,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            onChanged: (v) => setState(() => _query = v),
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Personel ara',
              isDense: true,
            ),
          ),
        ),
        if (checkedIn.isEmpty)
          EmptyView(
            icon: Icons.people_outline,
            title: total == 0 ? 'Bugün giriş yapan personel yok' : 'Personel bulunamadı',
            subtitle: total == 0 ? 'Devam ederek günü kapatabilirsiniz.' : null,
          )
        else
          for (final p in checkedIn) ...[
            PersonnelRow(
              assignment: p,
              onTap: p.isOnSite ? () => _actions.checkOut(p) : null,
              subtitle: p.isCheckedOut
                  ? 'Çıkış ${formatTime(p.checkOutTime)} · Hakediş ${formatMoney(p.totalEarnings)} · ${paymentStatusLabel(p.paymentStatus)}'
                  : '${p.zone.isEmpty ? '' : '${p.zone} · '}Giriş ${formatTime(p.checkInTime)}'
                      '${detail.inventoryHeldBy(p).isNotEmpty ? ' · ${detail.inventoryHeldBy(p).length} zimmet' : ''}',
              trailing: p.isCheckedOut
                  ? Icon(Icons.logout, color: theme.colorScheme.outline)
                  : FilledButton.tonal(
                      onPressed: () => _actions.checkOut(p),
                      style: FilledButton.styleFrom(visualDensity: VisualDensity.compact),
                      child: const Text('Çıkış'),
                    ),
            ),
            const Divider(height: 1),
          ],
      ],
    );
  }

  // ---------------- 2 · Gün sonu özeti ----------------

  Widget _summaryStep() {
    final theme = Theme.of(context);
    final s = detail.summary;
    final worked = detail.personnel.where((p) => p.isCheckedIn).toList();
    final unreturned = detail.unreturnedInventory;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              children: [
                _TotalRow(label: 'Toplam hakediş', value: formatMoney(s.totalEarnings), bold: true),
                _TotalRow(
                  label: 'Mesai (${s.overtimePersonnelCount} kişi)',
                  value: formatMoney(s.totalOvertime),
                ),
                _TotalRow(label: 'Ödenen', value: formatMoney(s.totalPaid), color: kSuccessGreen),
                _TotalRow(
                  label: 'Kalan',
                  value: formatMoney(s.totalPending),
                  color: s.totalPending > 0 ? theme.colorScheme.error : null,
                ),
                const Divider(height: 16),
                _TotalRow(
                  label: 'Masraflar (${detail.expenses.length})',
                  value: formatMoney(detail.expensesTotal),
                ),
                _TotalRow(
                  label: 'Envanter',
                  value: 'İade ${s.inventoryReturned} · Hasarlı ${s.inventoryDamaged} · Bekleyen ${s.inventoryPendingReturn}',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Personel hakedişleri (${worked.length})', style: theme.textTheme.titleMedium),
        const SizedBox(height: 6),
        if (worked.isEmpty)
          const Card(child: ListTile(title: Text('Bugün giriş yapan personel yok')))
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < worked.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  _EarningsTile(assignment: worked[i]),
                ],
              ],
            ),
          ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: Text('İade edilmemiş envanter (${unreturned.length})',
                  style: theme.textTheme.titleMedium),
            ),
            if (unreturned.isNotEmpty)
              TextButton.icon(
                onPressed: _actions.returnByScan,
                icon: const Icon(Icons.qr_code_scanner, size: 18),
                label: const Text('QR'),
              ),
          ],
        ),
        const SizedBox(height: 6),
        if (unreturned.isEmpty)
          const Card(
            child: ListTile(
              leading: Icon(Icons.check_circle, color: kSuccessGreen),
              title: Text('Tüm envanter iade alındı'),
            ),
          )
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < unreturned.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  InventoryRow(
                    item: unreturned[i],
                    holderName: _actions.holderName(unreturned[i]),
                    onReturn: () => _actions.returnItem(unreturned[i]),
                  ),
                ],
              ],
            ),
          ),
      ],
    );
  }

  // ---------------- 3 · Kapanış fotoğrafı ----------------

  Widget _photoStep() {
    final theme = Theme.of(context);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        Text('Kapanış fotoğrafı', style: theme.textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(
          'Alanın teslim halinin fotoğrafını çekin. Fotoğraf isteğe bağlıdır.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
        const SizedBox(height: 12),
        PhotoCapture(
          value: _endPhoto,
          onChanged: (f) => setState(() => _endPhoto = f),
          placeholder: 'Gün kapanış fotoğrafı',
        ),
        const SizedBox(height: 20),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Bitirince', style: theme.textTheme.titleSmall),
                const SizedBox(height: 6),
                Text(
                  '• Gün "Tamamlandı" olur; giriş/çıkış ve envanter işlemleri kapanır.\n'
                  '• Masraflar muhasebe onayına düşer, hakedişler proje kapanışında hesaba işlenir.',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({required this.label, required this.value, this.bold = false, this.color});

  final String label;
  final String value;
  final bool bold;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = (bold ? theme.textTheme.titleMedium : theme.textTheme.bodyMedium)
        ?.copyWith(fontWeight: bold ? FontWeight.bold : null, color: color);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(label,
                style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline)),
          ),
          Flexible(child: Text(value, style: style, textAlign: TextAlign.end)),
        ],
      ),
    );
  }
}

/// Personel başına yevmiye / mesai / hakediş / ödenen / kalan.
class _EarningsTile extends StatelessWidget {
  const _EarningsTile({required this.assignment});

  final PersonnelAssignment assignment;

  @override
  Widget build(BuildContext context) {
    final p = assignment;
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(p.displayName,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
              ),
              StatusChip(
                label: p.isCheckedOut ? paymentStatusLabel(p.paymentStatus) : 'Çıkış bekliyor',
                color: !p.isCheckedOut
                    ? kWarnAmber
                    : p.paymentStatus == 'paid'
                        ? kSuccessGreen
                        : p.paymentStatus == 'partial'
                            ? kWarnAmber
                            : theme.colorScheme.outline,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 14,
            runSpacing: 4,
            children: [
              _Mini(label: 'Yevmiye', value: formatMoney(p.dailyWage), muted: muted),
              _Mini(
                label: 'Mesai',
                value: p.overtimeHours > 0
                    ? '${formatHours(p.overtimeHours)} · ${formatMoney(p.overtimeTotal)}'
                    : '-',
                muted: muted,
              ),
              _Mini(label: 'Hakediş', value: formatMoney(p.totalEarnings), muted: muted),
              _Mini(
                label: 'Ödenen',
                value: p.paymentAmount > 0
                    ? '${formatMoney(p.paymentAmount)}${p.paymentMethod != null ? ' (${paymentMethodLabel(p.paymentMethod)})' : ''}'
                    : '-',
                muted: muted,
              ),
              _Mini(label: 'Kalan', value: formatMoney(p.remainingPayment), muted: muted),
            ],
          ),
        ],
      ),
    );
  }
}

class _Mini extends StatelessWidget {
  const _Mini({required this.label, required this.value, this.muted});

  final String label;
  final String value;
  final TextStyle? muted;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: muted),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
      ],
    );
  }
}
