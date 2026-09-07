import 'package:flutter/material.dart';

/// Ekranın altına yapışık işlem çubuğu: isteğe bağlı ikincil buton + büyük
/// birincil buton; üstte kısa bir ipucu satırı gösterilebilir.
class BottomActionBar extends StatelessWidget {
  const BottomActionBar({
    super.key,
    required this.primaryLabel,
    required this.onPrimary,
    this.primaryIcon,
    this.primaryColor,
    this.secondaryLabel,
    this.onSecondary,
    this.secondaryIcon,
    this.hint,
    this.hintIsWarning = false,
  });

  final String primaryLabel;

  /// null → buton pasif.
  final VoidCallback? onPrimary;
  final IconData? primaryIcon;
  final Color? primaryColor;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;
  final IconData? secondaryIcon;
  final String? hint;
  final bool hintIsWarning;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Material(
      color: scheme.surface,
      elevation: 8,
      shadowColor: Colors.black26,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (hint != null && hint!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Icon(
                        hintIsWarning ? Icons.warning_amber_rounded : Icons.info_outline,
                        size: 16,
                        color: hintIsWarning ? const Color(0xFFF9A825) : scheme.outline,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          hint!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: hintIsWarning ? scheme.onSurface : scheme.outline,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              Row(
                children: [
                  if (secondaryLabel != null) ...[
                    Expanded(
                      flex: 2,
                      child: OutlinedButton.icon(
                        onPressed: onSecondary,
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                        ),
                        icon: Icon(secondaryIcon ?? Icons.arrow_back),
                        label: Text(secondaryLabel!, overflow: TextOverflow.ellipsis),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    flex: 3,
                    child: FilledButton.icon(
                      onPressed: onPrimary,
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: primaryColor,
                        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                      ),
                      icon: Icon(primaryIcon ?? Icons.arrow_forward),
                      label: Text(primaryLabel, overflow: TextOverflow.ellipsis),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
