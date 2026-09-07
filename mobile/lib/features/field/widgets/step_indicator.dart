import 'package:flutter/material.dart';

/// Akış adımlarını gösteren üst çubuk: tamamlanan ✓, aktif dolu, sıradaki boş.
/// Tamamlanmış adımlara dokunarak geri dönülebilir ([onStepTap]).
class StepIndicator extends StatelessWidget {
  const StepIndicator({
    super.key,
    required this.steps,
    required this.current,
    this.onStepTap,
    this.phaseLabel,
  });

  final List<String> steps;
  final int current;
  final ValueChanged<int>? onStepTap;

  /// Üstte küçük aşama başlığı (örn. "Gün Başlangıcı").
  final String? phaseLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Material(
      color: theme.colorScheme.surface,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
        child: Column(
          children: [
            if (phaseLabel != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(
                  phaseLabel!.toUpperCase(),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.primary,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.2,
                  ),
                ),
              ),
            Row(
              children: [
                for (var i = 0; i < steps.length; i++) ...[
                  if (i > 0)
                    Expanded(
                      child: Container(
                        height: 2,
                        margin: const EdgeInsets.only(bottom: 18),
                        color: i <= current ? scheme.primary : scheme.outlineVariant,
                      ),
                    ),
                  _StepDot(
                    index: i,
                    label: steps[i],
                    state: i < current
                        ? _StepState.done
                        : i == current
                            ? _StepState.current
                            : _StepState.upcoming,
                    onTap: onStepTap != null && i < current ? () => onStepTap!(i) : null,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

enum _StepState { done, current, upcoming }

class _StepDot extends StatelessWidget {
  const _StepDot({
    required this.index,
    required this.label,
    required this.state,
    this.onTap,
  });

  final int index;
  final String label;
  final _StepState state;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final Color bg;
    final Color fg;
    final Color border;
    switch (state) {
      case _StepState.done:
        bg = scheme.primary;
        fg = scheme.onPrimary;
        border = scheme.primary;
      case _StepState.current:
        bg = scheme.primary;
        fg = scheme.onPrimary;
        border = scheme.primary;
      case _StepState.upcoming:
        bg = Colors.transparent;
        fg = scheme.outline;
        border = scheme.outlineVariant;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                color: bg,
                shape: BoxShape.circle,
                border: Border.all(color: border, width: 2),
              ),
              child: Center(
                child: state == _StepState.done
                    ? Icon(Icons.check, size: 16, color: fg)
                    : Text(
                        '${index + 1}',
                        style: TextStyle(
                          color: fg,
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: theme.textTheme.labelSmall?.copyWith(
                color: state == _StepState.upcoming ? scheme.outline : scheme.onSurface,
                fontWeight: state == _StepState.current ? FontWeight.w700 : FontWeight.w500,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
