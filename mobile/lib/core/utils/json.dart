/// Sunucu yanıtlarını toleranslı şekilde ayrıştırmak için yardımcılar.
/// Eksik/yanlış tipli alanlar varsayılan değerlere düşer.
library;

int asInt(Object? value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    return int.tryParse(value) ?? double.tryParse(value)?.toInt() ?? fallback;
  }
  return fallback;
}

int? asIntOrNull(Object? value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    return int.tryParse(value) ?? double.tryParse(value)?.toInt();
  }
  return null;
}

/// "3600.00" gibi string ondalıkları da kabul eder.
double asDouble(Object? value, [double fallback = 0]) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value.replaceAll(',', '.')) ?? fallback;
  return fallback;
}

String asString(Object? value, [String fallback = '']) {
  if (value == null) return fallback;
  if (value is String) return value;
  return value.toString();
}

String? asStringOrNull(Object? value) {
  if (value == null) return null;
  final s = value.toString();
  return s.isEmpty ? null : s;
}

bool asBool(Object? value, [bool fallback = false]) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final s = value.toLowerCase().trim();
    if (s == 'true' || s == '1' || s == 'yes' || s == 'evet') return true;
    if (s == 'false' || s == '0' || s == 'no' || s == '' || s == 'hayır') {
      return false;
    }
  }
  return fallback;
}

Map<String, dynamic> asMap(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) {
    return value.map((key, val) => MapEntry(key.toString(), val));
  }
  return const <String, dynamic>{};
}

List<Map<String, dynamic>> asMapList(Object? value) {
  if (value is List) {
    return value.whereType<Map>().map(asMap).toList();
  }
  if (value is Map) {
    // Laravel bazen keyed collection döndürebilir; `data` sarmalını da açar.
    if (value['data'] is List) return asMapList(value['data']);
    return value.values.whereType<Map>().map(asMap).toList();
  }
  return const <Map<String, dynamic>>[];
}

List<String> asStringList(Object? value) {
  if (value is List) return value.map((e) => e.toString()).toList();
  if (value is Map) return value.values.map((e) => e.toString()).toList();
  return const <String>[];
}

DateTime? asDateTime(Object? value) {
  if (value is DateTime) return value;
  if (value is String && value.isNotEmpty) {
    return DateTime.tryParse(value)?.toLocal();
  }
  if (value is int) {
    // Unix zaman damgası (saniye).
    return DateTime.fromMillisecondsSinceEpoch(value * 1000);
  }
  return null;
}

/// `2026-09-07` gibi tarih-only değerler için yerel gün (saat kaydırmadan).
DateTime? asDateOnly(Object? value) {
  if (value is DateTime) return DateTime(value.year, value.month, value.day);
  if (value is String && value.isNotEmpty) {
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return null;
    if (value.length <= 10) return DateTime(parsed.year, parsed.month, parsed.day);
    // `2026-09-07T00:00:00.000000Z` → takvim günü UTC bileşenleriyle alınır,
    // yerel saate çevrilince güne kaymasın.
    if (parsed.isUtc && parsed.hour == 0 && parsed.minute == 0) {
      return DateTime(parsed.year, parsed.month, parsed.day);
    }
    final local = parsed.toLocal();
    return DateTime(local.year, local.month, local.day);
  }
  return null;
}
