import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';
import '../models/models.dart';
import 'day_tab.dart';
import 'inventory_tab.dart';
import 'personnel_tab.dart';

class DayDetailScreen extends ConsumerWidget {
  const DayDetailScreen({super.key, required this.dayId});

  final int dayId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(dayDetailProvider(dayId));
    final detail = async.valueOrNull;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                detail == null || detail.projectName.isEmpty
                    ? 'Görev Detayı'
                    : detail.projectName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              if (detail != null)
                Text(
                  [
                    if (detail.customerName.isNotEmpty) detail.customerName,
                    formatShortDate(detail.date),
                  ].join(' · '),
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.normal,
                  ),
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
                  child: StatusChip(
                    label: dayStatusLabel(detail.status),
                    color: Colors.white,
                  ),
                ),
              ),
          ],
          bottom: TabBar(
            tabs: [
              Tab(
                text: detail == null
                    ? 'Personel'
                    : 'Personel ${detail.checkedInCount}/${detail.personnel.length}',
              ),
              Tab(
                text: detail == null
                    ? 'Envanter'
                    : 'Envanter ${detail.deliveredCount}/${detail.inventory.length}',
              ),
              const Tab(text: 'Gün'),
            ],
          ),
        ),
        body: async.when(
          skipLoadingOnRefresh: true,
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorView(
            message: errorMessage(e),
            onRetry: () => ref.invalidate(dayDetailProvider(dayId)),
          ),
          data: (ProjectDayDetail d) => TabBarView(
            children: [
              PersonnelTab(detail: d),
              InventoryTab(detail: d),
              DayTab(detail: d),
            ],
          ),
        ),
      ),
    );
  }
}
