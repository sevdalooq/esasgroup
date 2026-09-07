import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'realtime_provider.dart';

/// App bar'da canlı bağlantı göstergesi: yeşil (bağlı) · sarı (bağlanıyor) · kırmızı (kopuk).
class ConnectionDot extends ConsumerWidget {
  const ConnectionDot({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(realtimeStateProvider);
    final (color, label) = switch (state) {
      PusherConnectionState.connected => (const Color(0xFF4CAF50), 'Canlı bağlantı açık'),
      PusherConnectionState.connecting => (const Color(0xFFFFC107), 'Canlı bağlantı kuruluyor…'),
      PusherConnectionState.disconnected => (const Color(0xFFE53935), 'Canlı bağlantı yok'),
    };
    return Tooltip(
      message: label,
      child: Semantics(
        label: label,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            width: 10,
            height: 10,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white70, width: 1.5),
            ),
          ),
        ),
      ),
    );
  }
}
