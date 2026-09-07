import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';

const kSuccessGreen = Color(0xFF2E7D32);
const kWarnAmber = Color(0xFFF9A825);

/// Personel satırı: avatar + giriş/çıkış rozeti, ad, alan/saat özeti,
/// sağda çağıranın verdiği işlem bileşeni.
class PersonnelRow extends ConsumerWidget {
  const PersonnelRow({
    super.key,
    required this.assignment,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.dense = false,
  });

  final PersonnelAssignment assignment;

  /// Verilmezse alan · giriş · çıkış bilgisi gösterilir.
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool dense;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = assignment;
    final theme = Theme.of(context);
    final photoUrl = ref.watch(apiClientProvider).resolveUrl(p.photo);

    final parts = <String>[
      if (p.zone.isNotEmpty) p.zone,
      if (p.isCheckedIn) 'Giriş ${formatTime(p.checkInTime)}',
      if (p.isCheckedOut) 'Çıkış ${formatTime(p.checkOutTime)}',
      if (!p.isCheckedIn) 'Henüz giriş yapmadı',
    ];

    return ListTile(
      dense: dense,
      onTap: onTap,
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
                  color: p.isCheckedOut ? theme.colorScheme.outline : kSuccessGreen,
                  shape: BoxShape.circle,
                  border: Border.all(color: theme.colorScheme.surface, width: 2),
                ),
                child: Icon(
                  p.isCheckedOut ? Icons.logout : Icons.check,
                  size: 12,
                  color: Colors.white,
                ),
              ),
            ),
        ],
      ),
      title: Text(
        p.displayName,
        style: TextStyle(fontWeight: p.isCheckedIn ? FontWeight.w600 : FontWeight.normal),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        subtitle ?? parts.join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: trailing,
    );
  }
}
