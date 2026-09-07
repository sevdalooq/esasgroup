import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/photo_picker.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../../scanner/qr_payload.dart';
import '../../scanner/scan_helpers.dart';
import '../field_providers.dart';
import '../field_repository.dart';
import '../models/models.dart';
import '../widgets/zone_picker_sheet.dart';
import 'day_actions.dart';

class PersonnelTab extends ConsumerStatefulWidget {
  const PersonnelTab({super.key, required this.detail});

  final ProjectDayDetail detail;

  @override
  ConsumerState<PersonnelTab> createState() => _PersonnelTabState();
}

class _PersonnelTabState extends ConsumerState<PersonnelTab>
    with AutomaticKeepAliveClientMixin {
  ProjectDayDetail get detail => widget.detail;
  FieldRepository get _repo => ref.read(fieldRepositoryProvider);
  DayActions get _actions =>
      DayActions(context: context, ref: ref, dayId: detail.id);

  @override
  bool get wantKeepAlive => true;

  // ---------------- QR ile giriş ----------------

  Future<void> _scanCheckIn() async {
    final payload = await openScanner(
      context,
      title: 'Personel QR',
      hint: 'Personel kartındaki QR kodu okutun',
    );
    if (payload == null || !mounted) return;
    await _checkInWithPayload(payload);
  }

  Future<void> _checkInWithPayload(String payload) async {
    final type = QrPayload.typeOf(payload);
    final local = detail.personnel.where((p) => p.qrPayload == payload).firstOrNull;
    var displayName = local?.displayName ?? '';

    if (local == null) {
      if (type == QrType.inventory) {
        showSnack(context, 'Bu kod bir envanter kodu. Envanter sekmesini kullanın.', error: true);
        return;
      }
      if (type == QrType.zone) {
        showSnack(context, 'Bu kod bir alan kodu. Önce personel QR kodunu okutun.', error: true);
        return;
      }
      // Listede yok: sunucuya sorup kim olduğunu öğren.
      ScanResult result;
      try {
        result = await withProgress(
          context,
          () => _repo.scan(payload, detail.id),
          message: 'Kod sorgulanıyor…',
        );
      } catch (e) {
        if (mounted) showSnack(context, errorMessage(e), error: true);
        return;
      }
      if (!mounted) return;
      if (!result.isPersonnel) {
        showSnack(
          context,
          'Bu kod personel kodu değil (${result.type.isEmpty ? 'bilinmeyen' : result.type}).',
          error: true,
        );
        return;
      }
      displayName = result.entityName;
    } else if (local.isCheckedIn) {
      final again = await confirmDialog(
        context,
        title: 'Zaten giriş yapmış',
        message: '${local.displayName} bugün ${formatTime(local.checkInTime)} saatinde giriş yapmış. Yeniden giriş kaydı oluşturulsun mu?',
        confirmText: 'Evet, yeniden',
      );
      if (!again || !mounted) return;
    }

    await _completeCheckIn(
      displayName: displayName,
      personnelPayload: payload,
      initialZone: local?.zone,
    );
  }

  /// Alan seçimi → fotoğraf → POST check-in.
  Future<void> _completeCheckIn({
    required String displayName,
    String? personnelPayload,
    int? personnelId,
    String? initialZone,
  }) async {
    final zone = await showZonePicker(
      context,
      zones: detail.zones,
      personnelName: displayName,
      initialZone: initialZone,
    );
    if (zone == null || !mounted) return;

    final photo = await askForPhoto(context, title: 'Giriş fotoğrafı (isteğe bağlı)');
    if (photo.cancelled || !mounted) return;

    final name = displayName.isEmpty ? 'Personel' : displayName;
    await _actions.run(
      () => _repo.checkIn(
        detail.id,
        personnelPayload: personnelPayload,
        personnelId: personnelId,
        zonePayload: zone.payload,
        zone: zone.name,
        photo: photo.file,
      ),
      success: '$name giriş yaptı${zone.label.isNotEmpty ? ' · ${zone.label}' : ''}',
      progress: 'Giriş kaydediliyor…',
    );
  }

  Future<void> _manualCheckIn(PersonnelAssignment p) => _completeCheckIn(
        displayName: p.displayName,
        personnelId: p.personnelId,
        initialZone: p.zone,
      );

  Future<void> _checkOut(PersonnelAssignment p) async {
    final ok = await confirmDialog(
      context,
      title: 'Çıkış',
      message: '${p.displayName} için çıkış kaydı oluşturulsun mu?',
      confirmText: 'Çıkış Yap',
    );
    if (!ok || !mounted) return;
    await _actions.run(
      () => _repo.checkOut(detail.id, assignmentId: p.id),
      success: '${p.displayName} çıkış yaptı',
      progress: 'Çıkış kaydediliyor…',
    );
  }

  Future<void> _showPersonnelSheet(PersonnelAssignment p) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => _PersonnelSheet(assignment: p),
    );
    if (!mounted) return;
    switch (action) {
      case 'check_in':
        await _manualCheckIn(p);
        break;
      case 'check_out':
        await _checkOut(p);
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final theme = Theme.of(context);
    final list = detail.personnel;

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'fab_personnel',
        onPressed: _scanCheckIn,
        icon: const Icon(Icons.qr_code_scanner),
        label: const Text('QR ile Giriş'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
        child: list.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  EmptyView(
                    icon: Icons.people_outline,
                    title: 'Bu güne atanmış personel yok',
                    subtitle: 'QR ile giriş yaparak listeye ekleyebilirsiniz.',
                  ),
                ],
              )
            : ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: 88),
                itemCount: list.length + 1,
                separatorBuilder: (_, i) =>
                    i == 0 ? const SizedBox.shrink() : const Divider(height: 1),
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return Container(
                      color: theme.colorScheme.surfaceContainerHighest
                          .withValues(alpha: 0.5),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      child: Row(
                        children: [
                          const Icon(Icons.how_to_reg_outlined, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            'Giriş yapan: ${detail.checkedInCount} / ${list.length}',
                            style: theme.textTheme.titleSmall,
                          ),
                          const Spacer(),
                          Text(
                            'Satıra dokunarak manuel işlem',
                            style: theme.textTheme.bodySmall
                                ?.copyWith(color: theme.colorScheme.outline),
                          ),
                        ],
                      ),
                    );
                  }
                  final p = list[index - 1];
                  return _PersonnelRow(
                    assignment: p,
                    onTap: () => _showPersonnelSheet(p),
                  );
                },
              ),
      ),
    );
  }
}

class _PersonnelRow extends ConsumerWidget {
  const _PersonnelRow({required this.assignment, required this.onTap});

  final PersonnelAssignment assignment;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = assignment;
    final theme = Theme.of(context);
    final photoUrl = ref.watch(apiClientProvider).resolveUrl(p.photo);
    const green = Color(0xFF2E7D32);

    final parts = <String>[
      if (p.zone.isNotEmpty) p.zone,
      if (p.isCheckedIn) 'Giriş ${formatTime(p.checkInTime)}',
      if (p.isCheckedOut) 'Çıkış ${formatTime(p.checkOutTime)}',
      if (!p.isCheckedIn) 'Henüz giriş yapmadı',
    ];

    return ListTile(
      onTap: onTap,
      onLongPress: onTap,
      leading: Stack(
        clipBehavior: Clip.none,
        children: [
          photoUrl == null
              ? InitialsAvatar(name: p.displayName)
              : CircleAvatar(
                  radius: 20,
                  backgroundImage: CachedNetworkImageProvider(photoUrl),
                  backgroundColor: theme.colorScheme.surfaceContainerHighest,
                ),
          if (p.isCheckedIn)
            Positioned(
              right: -2,
              bottom: -2,
              child: Container(
                decoration: BoxDecoration(
                  color: p.isCheckedOut ? theme.colorScheme.outline : green,
                  shape: BoxShape.circle,
                  border: Border.all(color: theme.colorScheme.surface, width: 2),
                ),
                child: const Icon(Icons.check, size: 12, color: Colors.white),
              ),
            ),
        ],
      ),
      title: Text(
        p.displayName,
        style: TextStyle(
          fontWeight: p.isCheckedIn ? FontWeight.w600 : FontWeight.normal,
        ),
      ),
      subtitle: Text(parts.join(' · '), maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: Icon(
        p.isCheckedOut
            ? Icons.logout
            : p.isCheckedIn
                ? Icons.check_circle
                : Icons.radio_button_unchecked,
        color: p.isCheckedOut
            ? theme.colorScheme.outline
            : p.isCheckedIn
                ? green
                : theme.colorScheme.outlineVariant,
      ),
    );
  }
}

class _PersonnelSheet extends StatelessWidget {
  const _PersonnelSheet({required this.assignment});

  final PersonnelAssignment assignment;

  @override
  Widget build(BuildContext context) {
    final p = assignment;
    final theme = Theme.of(context);
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: InitialsAvatar(name: p.displayName, radius: 24),
            title: Text(p.displayName, style: theme.textTheme.titleMedium),
            subtitle: Text('Görevlendirme #${p.id}'),
          ),
          const Divider(),
          _InfoRow(label: 'Alan', value: p.zone.isEmpty ? '-' : p.zone),
          _InfoRow(label: 'Giriş', value: formatTime(p.checkInTime)),
          _InfoRow(label: 'Çıkış', value: formatTime(p.checkOutTime)),
          _InfoRow(label: 'Ödeme', value: paymentStatusLabel(p.paymentStatus)),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: p.isCheckedIn && !p.isCheckedOut
                        ? () => Navigator.of(context).pop('check_out')
                        : null,
                    icon: const Icon(Icons.logout),
                    label: const Text('Çıkış Yap'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => Navigator.of(context).pop('check_in'),
                    icon: const Icon(Icons.login),
                    label: Text(p.isCheckedIn ? 'Yeniden Giriş' : 'Giriş Yap'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
      child: Row(
        children: [
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: theme.colorScheme.outline),
            ),
          ),
          Expanded(child: Text(value, style: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }
}
