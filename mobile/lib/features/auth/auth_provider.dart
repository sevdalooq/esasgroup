import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/storage/app_storage.dart';
import '../notifications/notifications_repository.dart';
import 'auth_repository.dart';
import 'models/app_user.dart';

enum AuthStatus { loading, authenticated, unauthenticated }

/// Uygulama modu: saha sorumlusu (`field.access`) ya da personel (`self.access`).
enum AppMode { supervisor, personnel }

class AuthState {
  const AuthState({
    required this.status,
    this.user,
    this.permissions = const [],
    this.isAdmin = false,
    this.baseUrl = '',
    this.wsUrl = '',
    this.wsKey = AppConfig.reverbAppKey,
    this.lastEmail,
  });

  final AuthStatus status;
  final AppUser? user;
  final List<String> permissions;
  final bool isAdmin;
  final String baseUrl;

  /// Reverb adresi `ws://host:port` (boşsa API adresinden türetilir).
  final String wsUrl;

  /// Reverb uygulama anahtarı.
  final String wsKey;
  final String? lastEmail;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  bool hasPermission(String permission) =>
      isAdmin || permissions.contains(permission);

  /// `field.access` yoksa ama `self.access` varsa personel modu.
  AppMode get mode => !hasPermission('field.access') && hasPermission('self.access')
      ? AppMode.personnel
      : AppMode.supervisor;

  bool get isPersonnelMode => mode == AppMode.personnel;

  /// Etkin websocket adresi.
  String get effectiveWsUrl =>
      wsUrl.isEmpty ? AppConfig.defaultWsUrlFor(baseUrl) : wsUrl;

  AuthState copyWith({
    AuthStatus? status,
    AppUser? user,
    bool clearUser = false,
    List<String>? permissions,
    bool? isAdmin,
    String? baseUrl,
    String? wsUrl,
    String? wsKey,
    String? lastEmail,
  }) {
    return AuthState(
      status: status ?? this.status,
      user: clearUser ? null : (user ?? this.user),
      permissions: permissions ?? this.permissions,
      isAdmin: isAdmin ?? this.isAdmin,
      baseUrl: baseUrl ?? this.baseUrl,
      wsUrl: wsUrl ?? this.wsUrl,
      wsKey: wsKey ?? this.wsKey,
      lastEmail: lastEmail ?? this.lastEmail,
    );
  }
}

class AuthNotifier extends Notifier<AuthState> {
  late final AppStorage _storage;
  late final ApiClient _api;
  late final AuthRepository _repo;

  @override
  AuthState build() {
    _storage = ref.read(appStorageProvider);
    _api = ref.read(apiClientProvider);
    _repo = ref.read(authRepositoryProvider);
    _api.onUnauthorized = _handleUnauthorized;
    Future.microtask(_restore);
    return AuthState(
      status: AuthStatus.loading,
      baseUrl: AppConfig.defaultBaseUrl,
    );
  }

  /// Uygulama açılışında kayıtlı oturumu geri yükler.
  Future<void> _restore() async {
    final baseUrl = await _storage.readBaseUrl() ?? AppConfig.defaultBaseUrl;
    _api.baseUrl = baseUrl;
    final wsUrl = await _storage.readWsUrl() ?? '';
    final wsKey = await _storage.readWsKey() ?? AppConfig.reverbAppKey;
    final lastEmail = await _storage.readLastEmail();
    final token = await _storage.readToken();

    if (token == null) {
      state = AuthState(
        status: AuthStatus.unauthenticated,
        baseUrl: baseUrl,
        wsUrl: wsUrl,
        wsKey: wsKey,
        lastEmail: lastEmail,
      );
      return;
    }
    _api.token = token;

    // Önbellek: `{user, permissions, is_admin}` (eski sürümde düz kullanıcı nesnesi).
    AuthPayload? cached;
    final cachedJson = await _storage.readUserJson();
    if (cachedJson != null) {
      try {
        cached = AuthPayload.fromJson(
          (jsonDecode(cachedJson) as Map).cast<String, dynamic>(),
        );
      } catch (_) {
        cached = null;
      }
    }

    try {
      final me = await _repo.me();
      await _storage.writeUserJson(jsonEncode(_cacheJson(me)));
      state = AuthState(
        status: AuthStatus.authenticated,
        user: me.user,
        permissions: me.permissions,
        isAdmin: me.isAdmin,
        baseUrl: baseUrl,
        wsUrl: wsUrl,
        wsKey: wsKey,
        lastEmail: lastEmail,
      );
    } on ApiException catch (e) {
      if (e.isUnauthorized || cached == null) {
        await _storage.clearSession();
        _api.token = null;
        state = AuthState(
          status: AuthStatus.unauthenticated,
          baseUrl: baseUrl,
          wsUrl: wsUrl,
          wsKey: wsKey,
          lastEmail: lastEmail,
        );
      } else {
        // Ağ hatası: önbellekteki kullanıcıyla devam et (çevrimdışı tolerans).
        state = AuthState(
          status: AuthStatus.authenticated,
          user: cached.user,
          permissions: cached.permissions,
          isAdmin: cached.isAdmin,
          baseUrl: baseUrl,
          wsUrl: wsUrl,
          wsKey: wsKey,
          lastEmail: lastEmail,
        );
      }
    } catch (_) {
      state = AuthState(
        status: cached == null
            ? AuthStatus.unauthenticated
            : AuthStatus.authenticated,
        user: cached?.user,
        permissions: cached?.permissions ?? const [],
        isAdmin: cached?.isAdmin ?? false,
        baseUrl: baseUrl,
        wsUrl: wsUrl,
        wsKey: wsKey,
        lastEmail: lastEmail,
      );
    }
  }

  Map<String, dynamic> _cacheJson(AuthPayload p) => {
        'user': p.user.toJson(),
        'permissions': p.permissions,
        'is_admin': p.isAdmin,
      };

  /// Giriş yapar; hata durumunda [ApiException] fırlatır.
  /// [wsUrl] boş bırakılırsa API adresinden türetilir; [wsKey] boşsa
  /// derleme zamanı anahtarı ([AppConfig.reverbAppKey]) kullanılır.
  Future<void> login({
    required String email,
    required String password,
    required String serverUrl,
    String wsUrl = '',
    String wsKey = '',
  }) async {
    final baseUrl = AppConfig.normalizeBaseUrl(serverUrl);
    final normalizedWs = wsUrl.trim().isEmpty ? '' : AppConfig.normalizeWsUrl(wsUrl);
    final key = wsKey.trim().isEmpty ? AppConfig.reverbAppKey : wsKey.trim();
    _api.baseUrl = baseUrl;
    _api.token = null;
    await _storage.writeBaseUrl(baseUrl);
    await _storage.writeWsUrl(normalizedWs);
    await _storage.writeWsKey(key == AppConfig.reverbAppKey ? null : key);
    state = state.copyWith(baseUrl: baseUrl, wsUrl: normalizedWs, wsKey: key);

    final payload = await _repo.login(email.trim(), password);
    final token = payload.token;
    if (token == null || token.isEmpty) {
      throw ApiException('Sunucu geçerli bir oturum anahtarı döndürmedi.');
    }

    _api.token = token;
    await _storage.writeToken(token);
    await _storage.writeUserJson(jsonEncode(_cacheJson(payload)));
    await _storage.writeLastEmail(email.trim());

    state = AuthState(
      status: AuthStatus.authenticated,
      user: payload.user,
      permissions: payload.permissions,
      isAdmin: payload.isAdmin,
      baseUrl: baseUrl,
      wsUrl: normalizedWs,
      wsKey: key,
      lastEmail: email.trim(),
    );

    // TODO(firebase): FCM token alınınca gerçek kayıt yapılacak (şimdilik no-op).
    await ref.read(notificationsRepositoryProvider).registerDevice(null);
  }

  Future<void> logout() async {
    try {
      await _repo.logout();
    } catch (_) {
      // Sunucuya ulaşılamasa bile yerel oturumu kapat.
    }
    await _clearLocalSession();
  }

  void _handleUnauthorized() {
    if (state.status == AuthStatus.unauthenticated) return;
    _clearLocalSession();
  }

  Future<void> _clearLocalSession() async {
    _api.token = null;
    await _storage.clearSession();
    state = AuthState(
      status: AuthStatus.unauthenticated,
      baseUrl: state.baseUrl,
      wsUrl: state.wsUrl,
      wsKey: state.wsKey,
      lastEmail: state.lastEmail,
    );
  }
}

final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);
