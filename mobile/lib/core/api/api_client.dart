import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/app_storage.dart';
import 'api_exception.dart';

/// Dio tabanlı HTTP istemcisi.
/// - Her isteğe `Authorization: Bearer <token>` ekler.
/// - 401 yanıtında (login hariç) [onUnauthorized] geri çağrısını tetikler.
class ApiClient {
  ApiClient({required AppStorage storage}) : _storage = storage {
    dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.defaultBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 30),
        sendTimeout: const Duration(seconds: 60),
        headers: const {'Accept': 'application/json'},
        responseType: ResponseType.json,
      ),
    );
    dio.interceptors.add(
      InterceptorsWrapper(onRequest: _onRequest, onError: _onError),
    );
    if (kDebugMode) {
      dio.interceptors.add(
        LogInterceptor(
          requestBody: false,
          responseBody: false,
          logPrint: (o) => debugPrint('[API] $o'),
        ),
      );
    }
  }

  final AppStorage _storage;
  late final Dio dio;
  String? _token;

  /// 401 alındığında çağrılır (oturumu kapatmak için).
  VoidCallback? onUnauthorized;

  String get baseUrl => dio.options.baseUrl;
  set baseUrl(String url) => dio.options.baseUrl = url;

  set token(String? value) => _token = value;

  Future<void> _onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = _token ?? await _storage.readToken();
    if (token != null && token.isNotEmpty) {
      _token = token;
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  void _onError(DioException e, ErrorInterceptorHandler handler) {
    final status = e.response?.statusCode;
    final isLogin = e.requestOptions.path.endsWith('/login');
    if (status == 401 && !isLogin) {
      _token = null;
      onUnauthorized?.call();
    }
    handler.next(e);
  }

  /// Sunucudan gelen göreli dosya yolunu (`/storage/...`) tam URL'ye çevirir.
  String? resolveUrl(String? path) {
    if (path == null || path.isEmpty) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    final origin = AppConfig.originOf(baseUrl);
    return path.startsWith('/') ? '$origin$path' : '$origin/$path';
  }

  // ---- Yardımcı sarmalayıcılar: DioException → ApiException ----

  Future<Response<dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) =>
      _guard(() => dio.get<dynamic>(path, queryParameters: query));

  Future<Response<dynamic>> post(String path, {Object? data}) =>
      _guard(() => dio.post<dynamic>(path, data: data));

  Future<Response<dynamic>> _guard(
    Future<Response<dynamic>> Function() call,
  ) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient(storage: ref.watch(appStorageProvider)),
);
