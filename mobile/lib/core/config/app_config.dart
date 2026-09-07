import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;

/// Uygulama geneli sabitler ve ortam ayarları.
class AppConfig {
  AppConfig._();

  static const String appName = 'Esas Saha';
  static const String brandTitle = 'ESAS GRUP';

  /// NFC okuma özelliği (feature flag). `--dart-define=ESAS_NFC=false` ile kapatılabilir.
  static const bool nfcEnabled =
      bool.fromEnvironment('ESAS_NFC', defaultValue: true);

  /// QR / NFC payload ön eki: `ESAS:PER:<uuid>`, `ESAS:INV:<uuid>`, `ESAS:ZONE:<uuid>`.
  static const String qrPrefix = 'ESAS:';

  // ---------------- Canlı bağlantı (Laravel Reverb, Pusher protokolü v7) ----------------

  /// Reverb uygulama anahtarı (backend `.env` → `REVERB_APP_KEY`).
  /// `--dart-define=REVERB_APP_KEY=...` ile değiştirilebilir; giriş ekranındaki
  /// sunucu ayarları bunun da üzerine yazar.
  static const String reverbAppKey = String.fromEnvironment(
    'REVERB_APP_KEY',
    defaultValue: 'bfci1swbu8eicvlkrsbc',
  );

  /// Reverb ana makinesi. Boşsa API sunucusunun ana makinesi kullanılır
  /// (`--dart-define=REVERB_HOST=192.168.1.10`).
  static const String reverbHost = String.fromEnvironment('REVERB_HOST', defaultValue: '');

  /// Reverb portu (`--dart-define=REVERB_PORT=8081`).
  static const int reverbPort = int.fromEnvironment('REVERB_PORT', defaultValue: 8081);

  /// Reverb şeması: `ws` ya da `wss` (`--dart-define=REVERB_SCHEME=wss`).
  static const String reverbScheme = String.fromEnvironment('REVERB_SCHEME', defaultValue: 'ws');

  /// Konum paylaşımı periyodu (saniye) ve önemli değişim mesafesi (metre).
  static const Duration locationInterval = Duration(seconds: 60);
  static const int locationDistanceFilterMeters = 25;

  /// Platforma göre varsayılan API adresi.
  /// Android emülatörü ana makineye 10.0.2.2 üzerinden erişir.
  static String get defaultBaseUrl {
    if (!kIsWeb && Platform.isAndroid) return 'http://10.0.2.2:8000/api';
    return 'http://localhost:8000/api';
  }

  /// Verilen API adresine göre varsayılan websocket adresi
  /// (`ws://<api-host>:8081`; `REVERB_HOST` tanımlıysa o kullanılır).
  static String defaultWsUrlFor(String baseUrl) {
    final uri = Uri.tryParse(baseUrl);
    final host = reverbHost.isNotEmpty
        ? reverbHost
        : (uri != null && uri.hasAuthority && uri.host.isNotEmpty ? uri.host : 'localhost');
    return '$reverbScheme://$host:$reverbPort';
  }

  /// Varsayılan websocket adresi (varsayılan API adresine göre).
  static String get defaultWsUrl => defaultWsUrlFor(defaultBaseUrl);

  /// Kullanıcının girdiği websocket adresini normalize eder:
  /// `http(s)` → `ws(s)`, şema yoksa `ws://`, port yoksa [reverbPort],
  /// yol/sorgu temizlenir. Boşsa [fallback] (yoksa [defaultWsUrl]) döner.
  static String normalizeWsUrl(String raw, {String? fallback}) {
    var url = raw.trim();
    if (url.isEmpty) return fallback ?? defaultWsUrl;
    if (url.startsWith('https://')) {
      url = 'wss://${url.substring(8)}';
    } else if (url.startsWith('http://')) {
      url = 'ws://${url.substring(7)}';
    } else if (!url.startsWith('ws://') && !url.startsWith('wss://')) {
      url = 'ws://$url';
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !uri.hasAuthority || uri.host.isEmpty) {
      return fallback ?? defaultWsUrl;
    }
    final port = uri.hasPort ? uri.port : reverbPort;
    return '${uri.scheme}://${uri.host}:$port';
  }

  /// Pusher protokolü bağlantı adresi:
  /// `ws://host:port/app/<key>?protocol=7&client=flutter&version=1`.
  static Uri reverbUri(String wsUrl, String appKey) {
    final base = normalizeWsUrl(wsUrl);
    return Uri.parse('$base/app/$appKey').replace(
      queryParameters: const {
        'protocol': '7',
        'client': 'flutter',
        'version': '1',
        'flash': 'false',
      },
    );
  }

  /// Kullanıcının girdiği sunucu adresini normalize eder:
  /// şema ekler, sondaki `/` işaretlerini temizler, `/api` son ekini garanti eder.
  static String normalizeBaseUrl(String raw) {
    var url = raw.trim();
    if (url.isEmpty) return defaultBaseUrl;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'http://$url';
    }
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    if (!url.endsWith('/api')) url = '$url/api';
    return url;
  }

  /// `http://host:port` kısmını döndürür (göreli dosya URL'lerini çözmek için).
  static String originOf(String baseUrl) {
    final uri = Uri.tryParse(baseUrl);
    if (uri == null || !uri.hasAuthority) return baseUrl;
    return '${uri.scheme}://${uri.authority}';
  }
}
