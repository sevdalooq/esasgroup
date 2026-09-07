import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';

const kSuccessGreen = Color(0xFF2E7D32);
const kWarnAmber = Color(0xFFF9A825);

/// Personel satırı: avatar + durum rozeti, ad + `presence` çipi
/// (Bekleniyor / Sahada / Molada · süre / Çıkış yaptı / Gelmedi) ve
/// doğrulanmamış girişte "Doğrulanmadı" çipi; alan/saat özeti; sağda
/// çağıranın verdiği işlem bileşeni. Uzun basınca [onLongPress] (durum menüsü).
class PersonnelRow extends ConsumerWidget {
  const PersonnelRow({
    super.key,
    required this.assignment,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.onLongPress,
    this.dense = false,
    this.showPresence = true,
  });

  final PersonnelAssignment assignment;

  /// Verilmezse alan · giriş · çıkış bilgisi gösterilir.
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  final bool dense;

  /// Ad yanında durum çipi gösterilsin mi.
  final bool showPresence;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = assignment;
    final theme = Theme.of(context);
    final photoUrl = ref.watch(apiClientProvider).resolveUrl(p.photo);
    final presence = p.effectivePresence;

    final parts = <String>[
      if (p.zone.isNotEmpty) p.zone,
      if (p.isCheckedIn) 'Giriş ${formatTime(p.checkInTime)}',
      if (p.isCheckedOut) 'Çıkış ${formatTime(p.checkOutTime)}',
      if (p.breakMinutes > 0 && !p.isOnBreak) 'Mola ${p.breakMinutes} dk',
      if (!p.isCheckedIn && !p.isAbsent) 'Henüz giriş yapmadı',
      if (p.isAbsent) 'Gelmedi olarak işaretlendi',
    ];

    final (badgeColor, badgeIcon) = p.isAbsent
        ? (theme.colorScheme.error, Icons.close)
        : p.isCheckedOut
            ? (theme.colorScheme.outline, Icons.logout)
            : p.isOnBreak
                ? (kWarnAmber, Icons.coffee)
                : p.isCheckedIn
                    ? (kSuccessGreen, Icons.check)
                    : (null, null);

    return ListTile(
      dense: dense,
      onTap: onTap,
      onLongPress: onLongPress,
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
          if (badgeColor != null)
            Positioned(
              right: -2,
              bottom: -2,
              child: Container(
                decoration: BoxDecoration(
                  color: badgeColor,
                  shape: BoxShape.circle,
                  border: Border.all(color: theme.colorScheme.surface, width: 2),
                ),
                child: Icon(badgeIcon, size: 12, color: Colors.white),
              ),
            ),
        ],
      ),
      title: Row(
        children: [
          Flexible(
            child: Text(
              p.displayName,
              style: TextStyle(
                fontWeight: p.isCheckedIn ? FontWeight.w600 : FontWeight.normal,
                color: p.isAbsent ? theme.colorScheme.outline : null,
                decoration: p.isAbsent ? TextDecoration.lineThrough : null,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          if (showPresence) ...[
            const SizedBox(width: 6),
            PresenceChip(assignment: p),
          ],
          if (showPresence && p.needsVerification && !p.isCheckedOut) ...[
            const SizedBox(width: 4),
            const StatusChip(label: 'Doğrulanmadı', color: kWarnAmber),
          ],
        ],
      ),
      subtitle: Text(
        subtitle ?? (parts.isEmpty ? presenceLabel(presence) : parts.join(' · ')),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: trailing,
    );
  }
}

/// `presence` çipi; molada geçen süreyi de gösterir ("Molada · 12 dk").
class PresenceChip extends StatelessWidget {
  const PresenceChip({super.key, required this.assignment});

  final PersonnelAssignment assignment;

  @override
  Widget build(BuildContext context) {
    final p = assignment;
    final presence = p.effectivePresence;
    var label = presenceLabel(presence);
    if (p.isOnBreak && p.breakStartedAt != null) {
      label = '$label · ${formatElapsed(p.breakStartedAt)}';
    }
    return StatusChip(label: label, color: presenceColor(presence));
  }
}
