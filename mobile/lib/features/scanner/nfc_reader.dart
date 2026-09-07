import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:nfc_manager/nfc_manager.dart';

import '../../core/config/app_config.dart';

/// NFC etiketinden NDEF metin kaydı okur (feature flag: [AppConfig.nfcEnabled]).
/// Tüm çağrılar try/catch ile sarılıdır; cihaz desteklemiyorsa null döner.
class NfcReader {
  NfcReader._();

  static Future<bool> isAvailable() async {
    if (!AppConfig.nfcEnabled) return false;
    try {
      return await NfcManager.instance.isAvailable();
    } catch (e) {
      debugPrint('[nfc] isAvailable hatası: $e');
      return false;
    }
  }

  /// Etiket okunana ya da [timeout] dolana kadar bekler.
  /// Metin kaydı yoksa URI/ham payload'ı UTF-8 olarak döndürmeyi dener.
  static Future<String?> readText({
    Duration timeout = const Duration(seconds: 30),
  }) async {
    if (!AppConfig.nfcEnabled) return null;
    final completer = Completer<String?>();
    Timer? timer;

    Future<void> finish(String? value) async {
      timer?.cancel();
      try {
        await NfcManager.instance.stopSession();
      } catch (_) {}
      if (!completer.isCompleted) completer.complete(value);
    }

    try {
      await NfcManager.instance.startSession(
        alertMessage: 'Telefonu NFC etiketine yaklaştırın',
        onDiscovered: (NfcTag tag) async {
          try {
            final text = _extractText(tag);
            await finish(text);
          } catch (e) {
            debugPrint('[nfc] okuma hatası: $e');
            await finish(null);
          }
        },
      );
      timer = Timer(timeout, () => finish(null));
    } catch (e) {
      debugPrint('[nfc] startSession hatası: $e');
      await finish(null);
    }
    return completer.future;
  }

  static Future<void> cancel() async {
    try {
      await NfcManager.instance.stopSession();
    } catch (_) {}
  }

  static String? _extractText(NfcTag tag) {
    final ndef = Ndef.from(tag);
    final message = ndef?.cachedMessage;
    if (message == null) return null;
    for (final record in message.records) {
      final decoded = _decodeRecord(record);
      if (decoded != null && decoded.isNotEmpty) return decoded;
    }
    return null;
  }

  static String? _decodeRecord(NdefRecord record) {
    final payload = record.payload;
    if (payload.isEmpty) return null;
    if (record.typeNameFormat == NdefTypeNameFormat.nfcWellknown) {
      final type = String.fromCharCodes(record.type);
      if (type == 'T') {
        // Text record: [status][lang code][text]; status & 0x3F = dil kodu uzunluğu.
        final langLength = payload[0] & 0x3F;
        final isUtf16 = (payload[0] & 0x80) != 0;
        final bytes = payload.sublist(1 + langLength);
        if (isUtf16) {
          return String.fromCharCodes(bytes).trim();
        }
        return utf8.decode(bytes, allowMalformed: true).trim();
      }
      if (type == 'U') {
        // URI record: ilk bayt prefix kodu; payload'ın geri kalanı URI.
        return utf8.decode(payload.sublist(1), allowMalformed: true).trim();
      }
    }
    // Diğer kayıtlar: ham veriyi metin olarak dene.
    final raw = utf8.decode(payload, allowMalformed: true).trim();
    return raw.contains('ESAS:') ? raw.substring(raw.indexOf('ESAS:')) : raw;
  }
}
