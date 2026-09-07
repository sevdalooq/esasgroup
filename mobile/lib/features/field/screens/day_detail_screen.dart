import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../models/models.dart';
import 'active_hub_view.dart';
import 'completed_view.dart';
import 'end_phase_view.dart';
import 'start_phase_view.dart';

/// Görev günü ekranı – yönlendirmeli akış (sihirbaz).
///
/// Aşama, sunucudaki `day.status` ve yerel [DayFlowState] ile belirlenir:
/// - **A · Gün Başlangıcı** (`pending`): Personel Girişi → Özet → Fotoğraf → Günü Başlat
/// - **B · Etkinlik Devam Ediyor** (`active`): KPI + işlemler + "Gün Sonu Akışını Başlat"
/// - **C · Gün Sonu** (`active` + yerel bayrak): Personel Çıkışı → Özet → Fotoğraf → Günü Bitir
/// - **D · Tamamlandı** (`completed`): salt okunur özet
class DayDetailScreen extends ConsumerWidget {
  const DayDetailScreen({super.key, required this.dayId});

  final int dayId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(dayDetailProvider(dayId));
    final flow = ref.watch(dayFlowProvider(dayId));
    final detail = async.valueOrNull;
    final phase = detail == null ? null : phaseOf(detail, flow);

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              detail == null || detail.projectName.isEmpty ? 'Görev Detayı' : detail.projectName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            if (detail != null)
              Text(
                [
                  if (detail.customerName.isNotEmpty) detail.customerName,
                  formatShortDate(detail.date),
                  if (phase != null) _phaseTitle(phase),
                ].join(' · '),
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.normal),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
          ],
        ),
        actions: [
          if (detail != null)
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Center(
                child: StatusChip(label: dayPhaseLabel(detail.status), color: Colors.white),
              ),
            ),
        ],
      ),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorView(
          message: errorMessage(e),
          onRetry: () => ref.invalidate(dayDetailProvider(dayId)),
        ),
        data: (ProjectDayDetail d) => switch (phaseOf(d, flow)) {
          DayPhase.start => StartPhaseView(detail: d),
          DayPhase.active => ActiveHubView(detail: d),
          DayPhase.end => EndPhaseView(detail: d),
          DayPhase.completed || DayPhase.readOnly => CompletedView(detail: d),
        },
      ),
    );
  }

  String _phaseTitle(DayPhase phase) {
    switch (phase) {
      case DayPhase.start:
        return 'Gün Başlangıcı';
      case DayPhase.active:
        return 'Etkinlik Devam Ediyor';
      case DayPhase.end:
        return 'Gün Sonu';
      case DayPhase.completed:
        return 'Tamamlandı';
      case DayPhase.readOnly:
        return 'Salt okunur';
    }
  }
}
