import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/auth_provider.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/field/screens/day_detail_screen.dart';
import '../../features/field/screens/today_screen.dart';
import '../../features/notifications/screens/notifications_screen.dart';
import '../../features/scanner/scanner_screen.dart';
import '../../features/self/screens/self_home_screen.dart';
import '../config/app_config.dart';

/// Auth durumu değişince router'ın redirect'i yeniden çalışsın diye
/// ChangeNotifier köprüsü.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen<AuthStatus>(
      authProvider.select((s) => s.status),
      (_, __) => notifyListeners(),
    );
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefresh(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    debugLogDiagnostics: false,
    redirect: (context, state) {
      final status = ref.read(authProvider).status;
      final location = state.matchedLocation;
      final atLogin = location == '/login';
      final atSplash = location == '/splash';

      switch (status) {
        case AuthStatus.loading:
          return atSplash ? null : '/splash';
        case AuthStatus.unauthenticated:
          return atLogin ? null : '/login';
        case AuthStatus.authenticated:
          return (atLogin || atSplash) ? '/' : null;
      }
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const _SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/',
        builder: (context, state) => const _HomeGate(),
        routes: [
          GoRoute(
            path: 'days/:id',
            builder: (context, state) {
              final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
              return DayDetailScreen(dayId: id);
            },
          ),
          GoRoute(
            path: 'notifications',
            builder: (context, state) => const NotificationsScreen(),
          ),
        ],
      ),
      GoRoute(
        path: '/scan',
        builder: (context, state) => ScannerScreen(
          title: state.uri.queryParameters['title'] ?? 'QR Okut',
          hint: state.uri.queryParameters['hint'],
        ),
      ),
    ],
    errorBuilder: (context, state) => Scaffold(
      appBar: AppBar(title: const Text('Sayfa bulunamadı')),
      body: Center(child: Text('Adres bulunamadı: ${state.uri}')),
    ),
  );
});

/// Ana ekran: `field.access` olan saha sorumlusu → Bugün;
/// yalnızca `self.access` olan personel → Görevlerim.
class _HomeGate extends ConsumerWidget {
  const _HomeGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final personnelMode = ref.watch(authProvider.select((s) => s.isPersonnelMode));
    return personnelMode ? const SelfHomeScreen() : const TodayScreen();
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.shield_outlined, size: 64, color: theme.colorScheme.primary),
            const SizedBox(height: 12),
            Text(
              AppConfig.brandTitle,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                letterSpacing: 4,
              ),
            ),
            const SizedBox(height: 24),
            const CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
