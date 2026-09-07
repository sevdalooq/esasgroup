import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../../scanner/qr_payload.dart';
import '../../scanner/scan_helpers.dart';
import '../field_providers.dart';
import '../field_repository.dart';
import '../models/models.dart';
import '../widgets/personnel_picker_sheet.dart';
import '../widgets/return_inventory_sheet.dart';
import 'day_actions.dart';

enum _InventoryMode { deliver, returnItem, auto }

class InventoryTab extends ConsumerStatefulWidget {
  const InventoryTab({super.key, required this.detail});

  final ProjectDayDetail detail;

  @override
  ConsumerState<InventoryTab> createState() => _InventoryTabState();
}

class _InventoryTabState extends ConsumerState<InventoryTab>
    with AutomaticKeepAliveClientMixin {
  ProjectDayDetail get detail => widget.detail;
  FieldRepository get _repo => ref.read(fieldRepositoryProvider);
  DayActions get _actions =>
      DayActions(context: context, ref: ref, dayId: detail.id);

  @override
  bool get wantKeepAlive => true;

  Future<void> _scan(_InventoryMode mode) async {
    final payload = await openScanner(
      context,
      title: mode == _InventoryMode.returnItem ? 'Teslim Al – Envanter QR' : 'Teslim Et – Envanter QR',
      hint: 'Envanter etiketindeki QR kodu okutun',
    );
    if (payload == null || !mounted) return;

    final type = QrPayload.typeOf(payload);
    if (type == QrType.personnel || type == QrType.zone) {
      showSnack(
        context,
        'Bu kod bir ${QrPayload.typeLabel(type)} kodu; envanter QR kodu okutun.',
        error: true,
      );
      return;
    }

    final local = detail.inventory.where((i) => i.qrPayload == payload).firstOrNull;
    var name = local?.displayName ?? '';

    if (local == null && type == QrType.unknown) {
      // Bilinmeyen format: sunucuya sor.
      try {
        final result = await withProgress(
          context,
          () => _repo.scan(payload, detail.id),
          message: 'Kod sorgulanıyor…',
        );
        if (!mounted) return;
        if (!result.isInventory) {
          showSnack(context, 'Bu kod envanter kodu değil.', error: true);
          return;
        }
        name = result.entityName;
      } catch (e) {
        if (mounted) showSnack(context, errorMessage(e), error: true);
        return;
      }
    }

    var effective = mode;
    if (effective == _InventoryMode.auto) {
      if (local == null) {
        effective = _InventoryMode.deliver;
      } else if (local.isReturned) {
        showSnack(context, '${local.displayName} zaten iade alınmış.');
        return;
      } else {
        effective = local.isDelivered ? _InventoryMode.returnItem : _InventoryMode.deliver;
      }
    }

    if (effective == _InventoryMode.deliver) {
      await _deliver(payload: payload, name: name);
    } else {
      await _returnItem(payload: payload, name: name);
    }
  }

  Future<void> _deliver({
    String? payload,
    int? inventoryId,
    required String name,
    int? quantity,
  }) async {
    final itemName = name.isEmpty ? 'Envanter' : name;
    final pick = await showPersonnelPicker(
      context,
      personnel: detail.personnel,
      itemName: itemName,
    );
    if (pick == null || !mounted) return;
    final person = pick.assignment;
    await _actions.run(
      () => _repo.deliverInventory(
        detail.id,
        inventoryPayload: payload,
        inventoryId: inventoryId,
        personnelId: person?.personnelId,
        quantity: quantity,
      ),
      success: '$itemName teslim edildi${person != null ? ' · ${person.displayName}' : ''}',
      progress: 'Teslim kaydediliyor…',
    );
  }

  Future<void> _returnItem({String? payload, int? inventoryId, required String name}) async {
    final itemName = name.isEmpty ? 'Envanter' : name;
    final info = await showReturnInventorySheet(context, itemName: itemName);
    if (info == null || !mounted) return;
    await _actions.run(
      () => _repo.returnInventory(
        detail.id,
        inventoryPayload: payload,
        inventoryId: inventoryId,
        damaged: info.damaged,
        damageDescription: info.description,
      ),
      success: info.damaged
          ? '$itemName hasarlı olarak teslim alındı'
          : '$itemName teslim alındı',
      progress: 'İade kaydediliyor…',
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final theme = Theme.of(context);
    final list = detail.inventory;

    return Scaffold(
      backgroundColor: Colors.transparent,
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () => _scan(_InventoryMode.deliver),
                  icon: const Icon(Icons.qr_code_scanner),
                  label: const Text('Teslim Et'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.tonalIcon(
                  onPressed: () => _scan(_InventoryMode.returnItem),
                  icon: const Icon(Icons.qr_code_scanner),
                  label: const Text('Teslim Al'),
                ),
              ),
            ],
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(dayDetailProvider(detail.id).future),
        child: list.isEmpty
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  EmptyView(
                    icon: Icons.inventory_2_outlined,
                    title: 'Bu güne atanmış envanter yok',
                    subtitle: 'QR okutarak teslim kaydı oluşturabilirsiniz.',
                  ),
                ],
              )
            : ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: 16),
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
                          const Icon(Icons.inventory_outlined, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            'Teslim ${detail.deliveredCount} · İade ${detail.returnedCount} · Toplam ${list.length}',
                            style: theme.textTheme.titleSmall,
                          ),
                        ],
                      ),
                    );
                  }
                  final item = list[index - 1];
                  return _InventoryRow(
                    item: item,
                    personnelName: _holderName(item),
                    onDeliver: () => _deliver(
                      inventoryId: item.inventoryId,
                      name: item.displayName,
                      quantity: item.quantity,
                    ),
                    onReturn: () => _returnItem(inventoryId: item.inventoryId, name: item.displayName),
                  );
                },
              ),
      ),
    );
  }

  /// Zimmetli personel adı: iç içe nesne → görevlendirme id → personel id.
  String? _holderName(InventoryAssignment item) {
    if (item.assignedToName != null) return item.assignedToName;
    final byAssignment = detail.assignmentById(item.assignedToAssignmentId);
    if (byAssignment != null) return byAssignment.displayName;
    if (item.assignedToPersonnelId == null) return null;
    return detail.personnel
        .where((p) => p.personnelId == item.assignedToPersonnelId)
        .firstOrNull
        ?.displayName;
  }
}

class _InventoryRow extends StatelessWidget {
  const _InventoryRow({
    required this.item,
    required this.onDeliver,
    required this.onReturn,
    this.personnelName,
  });

  final InventoryAssignment item;
  final VoidCallback onDeliver;
  final VoidCallback onReturn;
  final String? personnelName;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    const green = Color(0xFF2E7D32);
    const amber = Color(0xFFF9A825);

    final Color statusColor;
    final String statusText;
    if (item.isDamaged) {
      statusColor = theme.colorScheme.error;
      statusText = 'Hasarlı iade';
    } else if (item.isReturned) {
      statusColor = theme.colorScheme.outline;
      statusText = 'İade alındı';
    } else if (item.isDelivered) {
      statusColor = green;
      statusText = 'Teslim edildi';
    } else {
      statusColor = amber;
      statusText = inventoryStatusLabel(item.status);
    }

    final subtitle = <String>[
      if (item.serialNumber.isNotEmpty) 'SN: ${item.serialNumber}',
      'Adet: ${item.quantity}',
      if (personnelName != null) personnelName!,
      if (item.damageDescription != null) item.damageDescription!,
    ];

    Widget trailing;
    if (item.isReturned) {
      trailing = Icon(
        item.isDamaged ? Icons.report_problem_outlined : Icons.check_circle,
        color: statusColor,
      );
    } else if (item.isDelivered) {
      trailing = OutlinedButton(
        onPressed: onReturn,
        style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
        child: const Text('Teslim Al'),
      );
    } else {
      trailing = FilledButton.tonal(
        onPressed: onDeliver,
        style: FilledButton.styleFrom(visualDensity: VisualDensity.compact),
        child: const Text('Teslim Et'),
      );
    }

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: statusColor.withValues(alpha: 0.15),
        child: Icon(Icons.inventory_2_outlined, color: statusColor),
      ),
      title: Text(item.displayName),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(subtitle.join(' · '), maxLines: 2, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
          StatusChip(label: statusText, color: statusColor),
        ],
      ),
      isThreeLine: true,
      trailing: trailing,
      onLongPress: item.isReturned ? null : (item.isDelivered ? onReturn : onDeliver),
    );
  }
}
