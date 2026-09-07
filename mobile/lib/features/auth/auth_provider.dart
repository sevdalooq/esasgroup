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

class AuthState {
  const AuthState({
    required this.status,
    this.user,
    this.permissions = const [],
    this.isAdmin = false,
    this.baseUrl = '',
    this.lastEmail,
  });

  final AuthStatus status;
  final AppUser? user;
  final List<String> permissions;
  final bool isAdmin;
  final String baseUrl;
  final String? lastEmail;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  bool hasPermission(String permission) =>
      isAdmin || permissions.contains(permission);

  AuthState copyWith({
    AuthStatus? status,
    AppUser? user,
    bool clearUser = false,
    List<String>? permissions,
    bool? isAdmin,
    String? baseUrl,
    String? lastEmail,
  }) {
    return AuthState(
      status: status ?? this.status,
      user: clearUser ? null : (user ?? this.user),
      permissions: permissions ?? this.permissions,
      isAdmin: isAdmin ?? this.isAdmin,
      baseUrl: baseUrl ?? this.baseUrl,
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
    final lastEmail = await _storage.readLastEmail();
    final token = await _storage.readToken();

    if (token == null) {
      state = AuthState(
        status: AuthStatus.unauthenticated,
        baseUrl: baseUrl,
        lastEmail: lastEmail,
      );
      return;
    }
    _api.token = token;

    AppUser? cachedUser;
    final cachedJson = await _storage.readUserJson();
    if (cachedJson != null) {
      try {
        cachedUser = AppUser.fromJson(
          (jsonDecode(cachedJson) as Map).cast<String, dynamic>(),
        );
      } catch (_) {
        cachedUser = null;
      }
    }

    try {
      final me = await _repo.me();
      await _storage.writeUserJson(jsonEncode(me.user.toJson()));
      state = AuthState(
        status: AuthStatus.authenticated,
        user: me.user,
        permissions: me.permissions,
        isAdmin: me.isAdmin,
        baseUrl: baseUrl,
        lastEmail: lastEmail,
      );
    } on ApiException catch (e) {
      if (e.isUnauthorized || cachedUser == null) {
        await _storage.clearSession();
        _api.token = null;
        state = AuthState(
          status: AuthStatus.unauthenticated,
          baseUrl: baseUrl,
          lastEmail: lastEmail,
        );
      } else {
        // Ağ hatası: önbellekteki kullanıcıyla devam et (çevrimdışı tolerans).
        state = AuthState(
          status: AuthStatus.authenticated,
          user: cachedUser,
          baseUrl: baseUrl,
          lastEmail: lastEmail,
        );
      }
    } catch (_) {
      state = AuthState(
        status: cachedUser == null
            ? AuthStatus.unauthenticated
            : AuthStatus.authenticated,
        user: cachedUser,
        baseUrl: baseUrl,
        lastEmail: lastEmail,
      );
    }
  }

  /// Giriş yapar; hata durumunda [ApiException] fırlatır.
  Future<void> login({
    required String email,
    required String password,
    required String serverUrl,
  }) async {
    final baseUrl = AppConfig.normalizeBaseUrl(serverUrl);
    _api.baseUrl = baseUrl;
    _api.token = null;
    await _storage.writeBaseUrl(baseUrl);
    state = state.copyWith(baseUrl: baseUrl);

    final payload = await _repo.login(email.trim(), password);
    final token = payload.token;
    if (token == null || token.isEmpty) {
      throw ApiException('Sunucu geçerli bir oturum anahtarı döndürmedi.');
    }

    _api.token = token;
    await _storage.writeToken(token);
    await _storage.writeUserJson(jsonEncode(payload.user.toJson()));
    await _storage.writeLastEmail(email.trim());

    state = AuthState(
      status: AuthStatus.authenticated,
      user: payload.user,
      permissions: payload.permissions,
      isAdmin: payload.isAdmin,
      baseUrl: baseUrl,
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
      lastEmail: state.lastEmail,
    );
  }
}

final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);
