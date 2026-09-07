import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../models/models.dart';
import '../widgets/bottom_action_bar.dart';
import '../widgets/inventory_row.dart';
import '../widgets/personnel_row.dart';
import '../widgets/photo_widgets.dart';
import '../widgets/step_indicator.dart';
import 'day_flow_actions.dart';

/// AŞAMA A – Gün Başlangıcı (status: pending)
/// 1 Personel Girişi → 2 Özet → 3 Başlangıç Fotoğrafı → "Günü Başlat"
class StartPhaseView extends ConsumerStatefulWidget {
  const StartPhaseView({super.key, required this.detail});

  final ProjectDayDetail detail;

  @override
  ConsumerState<StartPhaseView> createState() => _StartPhaseViewState();
}

class _StartPhaseViewState extends ConsumerState<StartPhaseView> {
  static const _steps = ['Personel Girişi', 'Özet', 'Fotoğraf'];

  XFile? _startPhoto;
  String _query = '';

  ProjectDayDetail get detail => widget.detail;
  DayFlowActions get _actions => DayFlowActions(context: context, ref: ref, detail: detail);
  DayFlowNotifier get _flow => ref.read(dayFlowProvider(detail.id).notifier);

  Future<void> _continueFromCheckIn() async {
    final missing = detail.notCheckedIn;
    if (missing.isNotEmpty) {
      final names = missing.take(8).map((p) => '• ${p.displayName}').join('\n');
      final more = missing.length > 8 ? '\n… ve ${missing.length - 8} kişi daha' : '';
      final ok = await confirmDialog(
        context,
        title: '${missing.length} personel giriş yapmadı',
        message: '$names$more\n\nGiriş yapmayanlar daha sonra "Geç Gelen Personel Girişi" ile eklenebilir. Devam edilsin mi?',
        confirmText: 'Yine de devam',
        destructive: true,
      );
      if (!ok || !mounted) return;
    }
    _flow.setStartStep(1);
  }

  Future<void> _startDay() async {
    final ok = await confirmDialog(
      context,
      title: 'Günü başlat',
      message: _startPhoto == null
          ? 'Başlangıç fotoğrafı eklenmedi. Gün fotoğrafsız başlatılsın mı?'
          : 'Gün başlatılacak ve etkinlik aşamasına geçilecek. Devam edilsin mi?',
      confirmText: 'Günü Başlat',
    );
    if (!ok || !mounted) return;
    await _actions.startDay(_startPhoto);
  }

  @override
  Widget build(BuildContext context) {
    final step = ref.watch(dayFlowProvider(detail.id).select((s) => s.startStep)).clamp(0, 2);

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: step == 0
          ? FloatingActionButton.extended(
              heroTag: 'fab_checkin',
              onPressed: _actions.checkInByScan,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('QR Okut'),
            )
          : null,
      bottomNavigationBar: _bottomBar(step),
      body: Column(
        children: [
          StepIndicator(
            steps: _steps,
            current: step,
            phaseLabel: 'Gün Başlangıcı',
            onStepTap: _flow.setStartStep,
          ),
          const Divider(height: 1),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
              child: switch (step) {
                0 => _checkInStep(),
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
    final total = detail.personnel.length;
    switch (step) {
      case 0:
        final missing = detail.notCheckedIn.length;
        return BottomActionBar(
          primaryLabel: 'Devam',
          onPrimary: _continueFromCheckIn,
          hint: missing > 0
              ? '$missing personel henüz giriş yapmadı${detail.absent.isNotEmpty ? ' · ${detail.absent.length} gelmedi' : ''}'
              : total == 0
                  ? 'Bu güne atanmış personel yok; QR ile ekleyebilirsiniz'
                  : 'Tüm personel giriş yaptı',
          hintIsWarning: missing > 0,
        );
      case 1:
        return BottomActionBar(
          primaryLabel: 'Devam',
          onPrimary: () => _flow.setStartStep(2),
          secondaryLabel: 'Geri',
          onSecondary: () => _flow.setStartStep(0),
        );
      default:
        return BottomActionBar(
          primaryLabel: 'Günü Başlat',
          primaryIcon: Icons.play_arrow,
          onPrimary: _startDay,
          secondaryLabel: 'Geri',
          onSecondary: () => _flow.setStartStep(1),
          hint: _startPhoto == null ? 'Fotoğraf isteğe bağlıdır' : null,
        );
    }
  }

  // ---------------- 1 · Personel girişi ----------------

  Widget _checkInStep() {
    final theme = Theme.of(context);
    final q = _query.toLowerCase();
    final list = detail.personnel
        .where((p) => q.isEmpty || p.displayName.toLowerCase().contains(q))
        .toList()
      ..sort((a, b) {
        // Giriş yapmamışlar üstte, gelmeyenler en altta.
        if (a.isAbsent != b.isAbsent) return a.isAbsent ? 1 : -1;
        if (a.isCheckedIn != b.isCheckedIn) return a.isCheckedIn ? 1 : -1;
        return 0;
      });
    final checkedIn = detail.checkedInCount;
    final total = detail.personnel.length;

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
                  'Giriş yapan $checkedIn / $total',
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
              ),
              Text(
                'Satırdaki "Giriş" ile ya da QR ile',
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
              value: total == 0 ? 0 : checkedIn / total,
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
        if (list.isEmpty)
          EmptyView(
            icon: Icons.people_outline,
            title: detail.personnel.isEmpty
                ? 'Bu güne atanmış personel yok'
                : 'Personel bulunamadı',
            subtitle: 'QR okutarak son dakika personel ekleyebilirsiniz.',
          )
        else
          for (final p in list) ...[
            PersonnelRow(
              assignment: p,
              onTap: p.isCheckedIn || p.isAbsent
                  ? () => _actions.showPersonnelMenu(p)
                  : () => _actions.checkInManual(p),
              onLongPress: () => _actions.showPersonnelMenu(p),
              trailing: p.isAbsent
                  ? IconButton(
                      tooltip: 'Durum',
                      icon: const Icon(Icons.more_vert),
                      onPressed: () => _actions.showPersonnelMenu(p),
                    )
                  : p.isCheckedIn
                      ? const Icon(Icons.check_circle, color: kSuccessGreen)
                      : FilledButton.tonal(
                          onPressed: () => _actions.checkInManual(p),
                          style: FilledButton.styleFrom(visualDensity: VisualDensity.compact),
                          child: const Text('Giriş'),
                        ),
            ),
            const Divider(height: 1),
          ],
      ],
    );
  }

  // ---------------- 2 · Özet ----------------

  Widget _summaryStep() {
    final theme = Theme.of(context);
    final checkedIn = detail.personnel.where((p) => p.isCheckedIn).toList();
    final undelivered = detail.undeliveredInventory;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Row(
          children: [
            Expanded(
              child: _KpiTile(
                label: 'Giriş yapan',
                value: '${checkedIn.length}/${detail.personnel.length}',
                icon: Icons.how_to_reg_outlined,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _KpiTile(
                label: 'Teslim edilen',
                value: '${detail.deliveredCount}/${detail.inventory.length}',
                icon: Icons.inventory_2_outlined,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Text('Giriş yapan personel', style: theme.textTheme.titleMedium),
        const SizedBox(height: 6),
        if (checkedIn.isEmpty)
          Card(
            child: ListTile(
              leading: Icon(Icons.info_outline, color: theme.colorScheme.outline),
              title: const Text('Henüz giriş yapan personel yok'),
              subtitle: const Text('Geri dönüp giriş yapabilir ya da gün başladıktan sonra ekleyebilirsiniz.'),
            ),
          )
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < checkedIn.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  Builder(builder: (context) {
                    final p = checkedIn[i];
                    final items = detail.inventoryDeliveredTo(p);
                    return PersonnelRow(
                      assignment: p,
                      subtitle: [
                        p.zone.isEmpty ? 'Alan: -' : p.zone,
                        items.isEmpty
                            ? 'Zimmet yok'
                            : 'Zimmet: ${items.map((e) => e.displayName).join(', ')}',
                      ].join(' · '),
                    );
                  }),
                ],
              ],
            ),
          ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: Text('Teslim edilmemiş envanter (${undelivered.length})',
                  style: theme.textTheme.titleMedium),
            ),
            TextButton.icon(
              onPressed: _actions.deliverByScan,
              icon: const Icon(Icons.qr_code_scanner, size: 18),
              label: const Text('QR'),
            ),
          ],
        ),
        const SizedBox(height: 6),
        if (undelivered.isEmpty)
          Card(
            child: ListTile(
              leading: const Icon(Icons.check_circle, color: kSuccessGreen),
              title: Text(detail.inventory.isEmpty
                  ? 'Bu güne atanmış envanter yok'
                  : 'Tüm envanter teslim edildi'),
            ),
          )
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < undelivered.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  InventoryRow(
                    item: undelivered[i],
                    onDeliver: () => _actions.deliverItem(undelivered[i]),
                  ),
                ],
              ],
            ),
          ),
      ],
    );
  }

  // ---------------- 3 · Başlangıç fotoğrafı ----------------

  Widget _photoStep() {
    final theme = Theme.of(context);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        Text('Başlangıç fotoğrafı', style: theme.textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(
          'Ekibin toplanma / alan fotoğrafını çekin. Fotoğraf isteğe bağlıdır.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
        const SizedBox(height: 12),
        PhotoCapture(
          value: _startPhoto,
          onChanged: (f) => setState(() => _startPhoto = f),
          placeholder: 'Gün başlangıç fotoğrafı',
        ),
        const SizedBox(height: 20),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Başlatınca', style: theme.textTheme.titleSmall),
                const SizedBox(height: 6),
                Text(
                  '• Gün "Devam ediyor" durumuna geçer.\n'
                  '• Geç gelen personel, son dakika ekleme, envanter teslimi ve masraf girişi etkinlik ekranından yapılır.\n'
                  '• Gün sonunda çıkış, iade ve ödeme adımları sırayla açılır.',
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

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Row(
          children: [
            Icon(icon, color: theme.colorScheme.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(value,
                      style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                  Text(label,
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
