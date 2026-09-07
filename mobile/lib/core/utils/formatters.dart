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
