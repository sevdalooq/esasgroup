import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/json.dart';
import 'models/app_user.dart';

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  /// POST /login {email, password} → {user, token, permissions, is_admin}
  Future<AuthPayload> login(String email, String password) async {
    final res = await _api.post(
      '/login',
      data: {
        'email': email,
        'password': password,
        'device_name': 'esas_saha_mobile',
      },
    );
    return AuthPayload.fromJson(asMap(res.data));
  }

  /// GET /user → {user, permissions, is_admin}
  Future<AuthPayload> me() async {
    final res = await _api.get('/user');
    return AuthPayload.fromJson(asMap(res.data));
  }

  /// POST /logout
  Future<void> logout() async {
    await _api.post('/logout');
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(apiClientProvider)),
);
