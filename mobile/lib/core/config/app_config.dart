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

  /// Platforma göre varsayılan API adresi.
  /// Android emülatörü ana makineye 10.0.2.2 üzerinden erişir.
  static String get defaultBaseUrl {
    if (!kIsWeb && Platform.isAndroid) return 'http://10.0.2.2:8000/api';
    return 'http://localhost:8000/api';
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
