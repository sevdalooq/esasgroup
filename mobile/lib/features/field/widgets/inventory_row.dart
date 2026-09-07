import 'package:flutter/material.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';
import 'personnel_row.dart';

/// Envanter satırı: durum rengi, SN/adet/zimmetli, sağda Teslim Et / Teslim Al.
class InventoryRow extends StatelessWidget {
  const InventoryRow({
    super.key,
    required this.item,
    this.holderName,
    this.onDeliver,
    this.onReturn,
    this.trailing,
    this.dense = false,
  });

  final InventoryAssignment item;
  final String? holderName;
  final VoidCallback? onDeliver;
  final VoidCallback? onReturn;

  /// Verilirse varsayılan butonların yerine kullanılır.
  final Widget? trailing;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final Color statusColor;
    final String statusText;
    if (item.isDamaged) {
      statusColor = theme.colorScheme.error;
      statusText = 'Hasarlı iade';
    } else if (item.isReturned) {
      statusColor = theme.colorScheme.outline;
      statusText = 'İade alındı';
    } else if (item.isDelivered) {
      statusColor = kSuccessGreen;
      statusText = 'Teslim edildi';
    } else {
      statusColor = kWarnAmber;
      statusText = inventoryStatusLabel(item.status);
    }

    final subtitle = <String>[
      if (item.serialNumber.isNotEmpty) 'SN: ${item.serialNumber}',
      if (item.quantity > 1) 'Adet: ${item.quantity}',
      if (holderName != null) holderName!,
      if (item.damageDescription != null) item.damageDescription!,
    ];

    Widget? action = trailing;
    if (action == null) {
      if (item.isReturned) {
        action = Icon(
          item.isDamaged ? Icons.report_problem_outlined : Icons.check_circle,
          color: statusColor,
        );
      } else if (item.isDelivered && onReturn != null) {
        action = OutlinedButton(
          onPressed: onReturn,
          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
          child: const Text('Teslim Al'),
        );
      } else if (item.isPending && onDeliver != null) {
        action = FilledButton.tonal(
          onPressed: onDeliver,
          style: FilledButton.styleFrom(visualDensity: VisualDensity.compact),
          child: const Text('Teslim Et'),
        );
      }
    }

    return ListTile(
      dense: dense,
      leading: CircleAvatar(
        backgroundColor: statusColor.withValues(alpha: 0.15),
        child: Icon(Icons.inventory_2_outlined, color: statusColor),
      ),
      title: Text(item.displayName, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (subtitle.isNotEmpty)
            Text(subtitle.join(' · '), maxLines: 2, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
          StatusChip(label: statusText, color: statusColor),
        ],
      ),
      isThreeLine: subtitle.isNotEmpty,
      trailing: action,
    );
  }
}
