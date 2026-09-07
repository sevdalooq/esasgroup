import 'package:dio/dio.dart';

/// API hatalarını kullanıcıya gösterilebilir Türkçe mesajlara çevirir.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;

  /// Laravel 422 doğrulama hataları: alan adı → ilk hata mesajı.
  final Map<String, String> fieldErrors;

  bool get isUnauthorized => statusCode == 401;

  factory ApiException.fromDio(DioException e) {
    final status = e.response?.statusCode;
    final data = e.response?.data;
    String? serverMessage;
    final fieldErrors = <String, String>{};

    if (data is Map) {
      final m = data['message'];
      if (m is String && m.isNotEmpty) serverMessage = m;
      final errors = data['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          if (value is List && value.isNotEmpty) {
            fieldErrors['$key'] = '${value.first}';
          } else if (value is String) {
            fieldErrors['$key'] = value;
          }
        });
      }
    } else if (data is String && data.isNotEmpty && data.length < 200) {
      serverMessage = data;
    }

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException(
          'Bağlantı zaman aşımına uğradı. Lütfen tekrar deneyin.',
        );
      case DioExceptionType.connectionError:
        return ApiException(
          'Sunucuya bağlanılamadı. İnternet bağlantınızı ve sunucu adresini kontrol edin.',
        );
      case DioExceptionType.cancel:
        return ApiException('İstek iptal edildi.');
      case DioExceptionType.badCertificate:
        return ApiException('Sunucu sertifikası doğrulanamadı.');
      case DioExceptionType.badResponse:
      case DioExceptionType.unknown:
        break;
      // ignore: unreachable_switch_default
      default:
        break;
    }

    if (status == null) {
      return ApiException(
        serverMessage ?? 'Sunucuya ulaşılamadı. Sunucu adresini kontrol edin.',
      );
    }

    if (status == 422) {
      final first = fieldErrors.isNotEmpty ? fieldErrors.values.first : null;
      return ApiException(
        first ?? serverMessage ?? 'Girilen bilgiler geçersiz.',
        statusCode: status,
        fieldErrors: fieldErrors,
      );
    }
    if (status == 401) {
      return ApiException(
        serverMessage ?? 'Oturum süreniz doldu. Lütfen tekrar giriş yapın.',
        statusCode: status,
      );
    }
    if (status == 403) {
      return ApiException(
        serverMessage ?? 'Bu işlem için yetkiniz yok.',
        statusCode: status,
      );
    }
    if (status == 404) {
      return ApiException(
        serverMessage ?? 'Kayıt bulunamadı.',
        statusCode: status,
      );
    }
    if (status >= 500) {
      return ApiException(
        'Sunucu hatası ($status). Lütfen daha sonra tekrar deneyin.',
        statusCode: status,
      );
    }
    return ApiException(
      serverMessage ?? 'Beklenmeyen bir hata oluştu ($status).',
      statusCode: status,
    );
  }

  @override
  String toString() => message;
}

/// Herhangi bir hatayı kullanıcı mesajına çevirir.
String errorMessage(Object error) {
  if (error is ApiException) return error.message;
  if (error is DioException) return ApiException.fromDio(error).message;
  if (error is FormatException) return 'Sunucudan beklenmeyen bir yanıt geldi.';
  return 'Beklenmeyen bir hata oluştu.';
}
