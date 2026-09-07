import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Token, sunucu adresi ve önbelleklenmiş kullanıcı bilgisi için güvenli depo.
/// Tüm okuma/yazma işlemleri hata durumunda sessizce null/no-op döner.
class AppStorage {
  AppStorage([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  final FlutterSecureStorage _storage;

  static const _kToken = 'auth_token';
  static const _kBaseUrl = 'base_url';
  static const _kWsUrl = 'ws_url';
  static const _kWsKey = 'ws_key';
  static const _kUser = 'auth_user';
  static const _kLastEmail = 'last_email';
  static const _kLocationSharing = 'location_sharing';

  Future<String?> readToken() => _read(_kToken);
  Future<void> writeToken(String? token) => _write(_kToken, token);

  Future<String?> readBaseUrl() => _read(_kBaseUrl);
  Future<void> writeBaseUrl(String? url) => _write(_kBaseUrl, url);

  /// Websocket (Reverb) adresi `ws://host:port` ve uygulama anahtarı.
  Future<String?> readWsUrl() => _read(_kWsUrl);
  Future<void> writeWsUrl(String? url) => _write(_kWsUrl, url);
  Future<String?> readWsKey() => _read(_kWsKey);
  Future<void> writeWsKey(String? key) => _write(_kWsKey, key);

  /// `{user, permissions, is_admin}` JSON'u (eski sürümlerde düz kullanıcı).
  Future<String?> readUserJson() => _read(_kUser);
  Future<void> writeUserJson(String? json) => _write(_kUser, json);

  Future<String?> readLastEmail() => _read(_kLastEmail);
  Future<void> writeLastEmail(String? email) => _write(_kLastEmail, email);

  /// Personel modunda "Konum paylaşımı" anahtarının son durumu.
  Future<bool> readLocationSharing() async => (await _read(_kLocationSharing)) == '1';
  Future<void> writeLocationSharing(bool enabled) =>
      _write(_kLocationSharing, enabled ? '1' : '0');

  Future<void> clearSession() async {
    await writeToken(null);
    await writeUserJson(null);
  }

  Future<String?> _read(String key) async {
    try {
      final value = await _storage.read(key: key);
      return (value == null || value.isEmpty) ? null : value;
    } catch (_) {
      return null;
    }
  }

  Future<void> _write(String key, String? value) async {
    try {
      if (value == null || value.isEmpty) {
        await _storage.delete(key: key);
      } else {
        await _storage.write(key: key, value: value);
      }
    } catch (_) {
      // Depolama hatası uygulamayı durdurmasın.
    }
  }
}

final appStorageProvider = Provider<AppStorage>((ref) => AppStorage());
