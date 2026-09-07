import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

const String appLocale = 'tr_TR';

String formatDate(DateTime? date) {
  if (date == null) return '-';
  return DateFormat('d MMMM yyyy, EEEE', appLocale).format(date);
}

String formatShortDate(DateTime? date) {
  if (date == null) return '-';
  return DateFormat('d MMM yyyy', appLocale).format(date);
}

String formatDateTime(DateTime? date) {
  if (date == null) return '-';
  return DateFormat('d MMM yyyy HH:mm', appLocale).format(date);
}

/// Sunucudan gelen saat değerini `HH:mm` olarak gösterir.
/// Tam tarih-saat, `08:30:00` ya da `08:30` gibi değerleri kabul eder.
String formatTime(String? raw) {
  if (raw == null || raw.trim().isEmpty) return '-';
  final parsed = DateTime.tryParse(raw.trim());
  if (parsed != null) return DateFormat('HH:mm').format(parsed.toLocal());
  final match = RegExp(r'(\d{1,2}):(\d{2})').firstMatch(raw);
  if (match != null) return '${match[1]!.padLeft(2, '0')}:${match[2]}';
  return raw;
}

String formatMoney(double amount) =>
    NumberFormat.currency(locale: appLocale, symbol: '₺', decimalDigits: 2).format(amount);

String relativeTime(DateTime? date) {
  if (date == null) return '';
  final diff = DateTime.now().difference(date);
  if (diff.inSeconds < 60) return 'az önce';
  if (diff.inMinutes < 60) return '${diff.inMinutes} dk önce';
  if (diff.inHours < 24) return '${diff.inHours} sa önce';
  if (diff.inDays == 1) return 'dün';
  if (diff.inDays < 7) return '${diff.inDays} gün önce';
  return formatShortDate(date);
}

/// Proje günü durumu → Türkçe etiket.
String dayStatusLabel(String status) {
  switch (status) {
    case 'pending':
      return 'Bekliyor';
    case 'active':
      return 'Devam ediyor';
    case 'completed':
      return 'Tamamlandı';
    case 'cancelled':
      return 'İptal';
    case '':
      return 'Bilinmiyor';
    default:
      return status;
  }
}

Color dayStatusColor(String status) {
  switch (status) {
    case 'active':
      return const Color(0xFF2E7D32);
    case 'completed':
      return const Color(0xFF546E7A);
    case 'cancelled':
      return const Color(0xFFB71C1C);
    case 'pending':
    default:
      return const Color(0xFFF9A825);
  }
}

/// Envanter durumu → Türkçe etiket.
String inventoryStatusLabel(String status) {
  switch (status) {
    case 'pending':
    case 'assigned':
    case 'planned':
      return 'Teslim bekliyor';
    case 'delivered':
    case 'in_use':
      return 'Teslim edildi';
    case 'returned':
      return 'İade alındı';
    case 'damaged':
      return 'Hasarlı';
    case 'lost':
      return 'Kayıp';
    case '':
      return 'Bilinmiyor';
    default:
      return status;
  }
}

/// Gün akışı aşaması (Bugün kartı / başlık): Başlamadı · Devam ediyor · Tamamlandı.
String dayPhaseLabel(String status) {
  switch (status) {
    case 'pending':
    case '':
      return 'Başlamadı';
    case 'active':
      return 'Devam ediyor';
    case 'completed':
      return 'Tamamlandı';
    case 'cancelled':
      return 'İptal';
    default:
      return status;
  }
}

String paymentMethodLabel(String? method) {
  switch (method) {
    case 'cash':
      return 'Nakit';
    case 'bank':
      return 'Banka';
    case 'mixed':
      return 'Karışık';
    case null:
    case '':
      return '-';
    default:
      return method;
  }
}

String expenseStatusLabel(String status) {
  switch (status) {
    case 'pending':
      return 'Onay bekliyor';
    case 'approved':
      return 'Onaylandı';
    case 'rejected':
      return 'Reddedildi';
    case '':
      return '-';
    default:
      return status;
  }
}

/// `1.5` → `1,5 sa`.
String formatHours(double hours) {
  final text = hours == hours.roundToDouble()
      ? hours.toInt().toString()
      : hours.toStringAsFixed(1).replaceAll('.', ',');
  return '$text sa';
}

String paymentStatusLabel(String status) {
  switch (status) {
    case 'paid':
      return 'Ödendi';
    case 'partial':
      return 'Kısmi ödendi';
    case 'unpaid':
    case 'pending':
      return 'Ödenmedi';
    case '':
      return '-';
    default:
      return status;
  }
}

/// Kullanıcının yazdığı tutarı (`1.250,50` / `1250.5`) sayıya çevirir.
double? parseDecimal(String raw) {
  var s = raw.trim().replaceAll('₺', '').replaceAll(' ', '');
  if (s.isEmpty) return null;
  if (s.contains(',') && s.contains('.')) {
    // 1.250,50 → 1250.50
    s = s.replaceAll('.', '').replaceAll(',', '.');
  } else {
    s = s.replaceAll(',', '.');
  }
  return double.tryParse(s);
}

/// Sayıyı giriş alanında göstermek için (`3600.0` → `3600`, `450.5` → `450,5`).
String formatInputNumber(double value) {
  if (value == value.roundToDouble()) return value.toInt().toString();
  return value.toStringAsFixed(2).replaceAll(RegExp(r'0+$'), '').replaceAll('.', ',');
}

/// Personel durumu (`presence`) → Türkçe etiket.
/// assigned · checked_in · on_break · checked_out · absent
String presenceLabel(String presence) {
  switch (presence) {
    case 'assigned':
    case '':
      return 'Bekleniyor';
    case 'checked_in':
      return 'Sahada';
    case 'on_break':
      return 'Molada';
    case 'checked_out':
      return 'Çıkış yaptı';
    case 'absent':
      return 'Gelmedi';
    default:
      return presence;
  }
}

Color presenceColor(String presence) {
  switch (presence) {
    case 'checked_in':
      return const Color(0xFF2E7D32);
    case 'on_break':
      return const Color(0xFFF9A825);
    case 'checked_out':
      return const Color(0xFF546E7A);
    case 'absent':
      return const Color(0xFFB71C1C);
    case 'assigned':
    default:
      return const Color(0xFF757575);
  }
}

/// Moladan bu yana geçen süre: `12 dk`, `1 sa 05 dk`.
String formatElapsed(DateTime? since, {DateTime? now}) {
  if (since == null) return '';
  final diff = (now ?? DateTime.now()).difference(since);
  if (diff.isNegative) return '0 dk';
  if (diff.inHours > 0) {
    final m = diff.inMinutes % 60;
    return '${diff.inHours} sa ${m.toString().padLeft(2, '0')} dk';
  }
  return '${diff.inMinutes} dk';
}

/// Telefon numarasını `tel:` URI'si için sadeleştirir (`0532 818 41 21` → `05328184121`).
String? telUriFor(String? phone) {
  if (phone == null) return null;
  final digits = phone.replaceAll(RegExp(r'[^0-9+]'), '');
  return digits.isEmpty ? null : 'tel:$digits';
}
