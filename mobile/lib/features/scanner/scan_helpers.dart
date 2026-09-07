import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

/// Tarayıcı ekranını açar; okunan payload'ı ya da iptalde null döndürür.
Future<String?> openScanner(
  BuildContext context, {
  String title = 'QR Okut',
  String? hint,
}) {
  final query = <String, String>{
    'title': title,
    if (hint != null) 'hint': hint,
  };
  return context.push<String>(Uri(path: '/scan', queryParameters: query).toString());
}
