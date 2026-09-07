import '../../../core/utils/json.dart';

class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.raw = const {},
  });

  final int id;
  final String name;
  final String email;

  /// Sunucudan gelen tüm alanlar (rol, telefon vb.) – ileride kullanılmak üzere.
  final Map<String, dynamic> raw;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: asInt(json['id']),
      name: asString(json['name'], asString(json['full_name'], 'Kullanıcı')),
      email: asString(json['email']),
      raw: json,
    );
  }

  Map<String, dynamic> toJson() => {
        ...raw,
        'id': id,
        'name': name,
        'email': email,
      };
}

/// `/login` ve `/user` yanıtlarının ortak şekli.
class AuthPayload {
  const AuthPayload({
    required this.user,
    this.token,
    this.permissions = const [],
    this.isAdmin = false,
  });

  final AppUser user;
  final String? token;
  final List<String> permissions;
  final bool isAdmin;

  factory AuthPayload.fromJson(Map<String, dynamic> json) {
    // `/user` bazı kurulumlarda kullanıcıyı doğrudan kökte döndürebilir.
    final userMap = json['user'] is Map ? asMap(json['user']) : json;
    return AuthPayload(
      user: AppUser.fromJson(userMap),
      token: asStringOrNull(json['token'] ?? json['access_token']),
      permissions: asStringList(json['permissions']),
      isAdmin: asBool(json['is_admin']),
    );
  }
}
