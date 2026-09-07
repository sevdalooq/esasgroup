import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/photo_picker.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../field_repository.dart';
import '../models/models.dart';
import 'day_actions.dart';

class DayTab extends ConsumerWidget {
  const DayTab({super.key, required this.detail});

  final ProjectDayDetail detail;

  Future<void> _startDay(BuildContext context, WidgetRef ref) async {
    final photo = await askForPhoto(context, title: 'Gün başlangıç fotoğrafı (isteğe bağlı)');
    if (photo.cancelled || !context.mounted) return;
    await DayActions(context: context, ref: ref, dayId: detail.id).run(
      () => ref.read(fieldRepositoryProvider).startDay(detail.id, photo: photo.file),
      success: 'Gün başlatıldı',
      progress: 'Gün başlatılıyor…',
    );
  }

  Future<void> _endDay(BuildContext context, WidgetRef ref) async {
    final missingReturns = detail.inventory.where((i) => i.isDelivered).length;
    final stillIn = detail.personnel.where((p) => p.isCheckedIn && !p.isCheckedOut).length;
    final warnings = <String>[
      if (missingReturns > 0) '$missingReturns envanter henüz iade alınmadı.',
      if (stillIn > 0) '$stillIn personelin çıkışı yapılmadı.',
    ];
    final ok = await confirmDialog(
      context,
      title: 'Günü bitir',
      message: [
        ...warnings,
        'Gün tamamlandı olarak işaretlenecek. Devam edilsin mi?',
      ].join('\n'),
      confirmText: 'Günü Bitir',
      destructive: warnings.isNotEmpty,
    );
    if (!ok || !context.mounted) return;
    final photo = await askForPhoto(context, title: 'Gün bitiş fotoğrafı (isteğe bağlı)');
    if (photo.cancelled || !context.mounted) return;
    await DayActions(context: context, ref: ref, dayId: detail.id).run(
      () => ref.read(fieldRepositoryProvider).endDay(detail.id, photo: photo.file),
      success: 'Gün tamamlandı',
      progress: 'Gün bitiriliyor…',
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final api = ref.watch(apiClientProvider);
    final color = dayStatusColor(detail.status);
    final summary = detail.summary;

    return RefreshIndicator(
      onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          detail.projectName.isEmpty ? 'Proje' : detail.projectName,
                          style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                        ),
                      ),
                      StatusChip(label: dayStatusLabel(detail.status), color: color),
                    ],
                  ),
                  if (detail.customerName.isNotEmpty || detail.supervisorName.isNotEmpty)
                    Text(
                      [
                        if (detail.customerName.isNotEmpty) detail.customerName,
                        if (detail.supervisorName.isNotEmpty) 'Sorumlu: ${detail.supervisorName}',
                      ].join(' · '),
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline),
                    ),
                  const SizedBox(height: 8),
                  Text(formatDate(detail.date), style: theme.textTheme.bodyMedium),
                  if (detail.startedAt != null)
                    Text('Başlangıç: ${formatDateTime(detail.startedAt)}', style: theme.textTheme.bodySmall),
                  if (detail.endedAt != null)
                    Text('Bitiş: ${formatDateTime(detail.endedAt)}', style: theme.textTheme.bodySmall),
                  const Divider(height: 24),
                  Row(
                    children: [
                      _Stat(label: 'Giriş', value: '${summary.checkedInCount}/${summary.personnelCount}'),
                      _Stat(label: 'Çıkış', value: '${summary.checkedOutCount}'),
                      _Stat(label: 'Teslim', value: '${summary.inventoryDelivered}/${summary.inventoryCount}'),
                      _Stat(label: 'İade', value: '${summary.inventoryReturned}'),
                    ],
                  ),
                  if (summary.inventoryDamaged > 0 || summary.inventoryPendingReturn > 0) ...[
                    const SizedBox(height: 8),
                    Text(
                      [
                        if (summary.inventoryPendingReturn > 0)
                          'İade bekleyen: ${summary.inventoryPendingReturn}',
                        if (summary.inventoryDamaged > 0) 'Hasarlı: ${summary.inventoryDamaged}',
                      ].join(' · '),
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.error),
                    ),
                  ],
                  if (summary.totalEarnings > 0) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Hakediş: ${formatMoney(summary.totalEarnings)} · Ödenen: ${formatMoney(summary.totalPaid)} · Bekleyen: ${formatMoney(summary.totalPending)}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                  if (detail.notes != null) ...[
                    const Divider(height: 24),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.sticky_note_2_outlined, size: 18, color: theme.colorScheme.outline),
                        const SizedBox(width: 8),
                        Expanded(child: Text(detail.notes!, style: theme.textTheme.bodyMedium)),
                      ],
                    ),
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
                child: _PhotoTile(
                  label: 'Gün başlangıcı',
                  url: api.resolveUrl(detail.startPhoto),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _PhotoTile(
                  label: 'Gün bitişi',
                  url: api.resolveUrl(detail.endPhoto),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          if (detail.isPending)
            FilledButton.icon(
              onPressed: () => _startDay(context, ref),
              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
              icon: const Icon(Icons.play_arrow),
              label: const Text('Günü Başlat', style: TextStyle(fontSize: 16)),
            )
          else if (detail.isActive)
            FilledButton.icon(
              onPressed: () => _endDay(context, ref),
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: const Color(0xFF2B2A29),
              ),
              icon: const Icon(Icons.stop_circle_outlined),
              label: const Text('Günü Bitir', style: TextStyle(fontSize: 16)),
            )
          else
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: theme.colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Icon(Icons.info_outline, color: theme.colorScheme.outline),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      detail.isCompleted
                          ? 'Bu gün tamamlandı. Kayıtlar salt okunur.'
                          : 'Bu gün için işlem yapılamaz (durum: ${dayStatusLabel(detail.status)}).',
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

class _PhotoTile extends StatelessWidget {
  const _PhotoTile({required this.label, required this.url});

  final String label;
  final String? url;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AspectRatio(
          aspectRatio: 4 / 3,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: url == null
                ? Container(
                    color: theme.colorScheme.surfaceContainerHighest,
                    child: Center(
                      child: Icon(Icons.image_not_supported_outlined,
                          color: theme.colorScheme.outline, size: 32),
                    ),
                  )
                : GestureDetector(
                    onTap: () => showDialog<void>(
                      context: context,
                      builder: (_) => Dialog(
                        insetPadding: const EdgeInsets.all(12),
                        child: InteractiveViewer(
                          child: CachedNetworkImage(imageUrl: url!, fit: BoxFit.contain),
                        ),
                      ),
                    ),
                    child: CachedNetworkImage(
                      imageUrl: url!,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(
                        color: theme.colorScheme.surfaceContainerHighest,
                        child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                      ),
                      errorWidget: (_, __, ___) => Container(
                        color: theme.colorScheme.surfaceContainerHighest,
                        child: const Center(child: Icon(Icons.broken_image_outlined)),
                      ),
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          url == null ? '$label · fotoğraf yok' : label,
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
      ],
    );
  }
}
