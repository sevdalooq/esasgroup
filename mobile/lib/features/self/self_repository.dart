import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/json.dart';
import 'models/self_models.dart';

/// Personel modu uçları (`/me/*`) ve konum bildirimi (`/field/location`).
class SelfRepository {
  SelfRepository(this._api);

  final ApiClient _api;

  /// GET /me/assignments → {personnel, assignments, today}
  Future<SelfAssignments> assignments() async {
    final res = await _api.get('/me/assignments');
    return SelfAssignments.fromJson(asMap(res.data));
  }

  /// POST /me/check-in {zone_payload | zone, project_day_id?, lat?, lng?} → {message, assignment}
  Future<SelfActionResult> checkIn(SelfCheckInRequest request) async {
    final res = await _api.post('/me/check-in', data: request.toJson());
    return SelfActionResult.fromJson(asMap(res.data));
  }

  /// POST /me/break/start {project_day_id?} → {message, assignment}
  Future<SelfActionResult> breakStart({int? projectDayId}) async {
    final res = await _api.post(
      '/me/break/start',
      data: {if (projectDayId != null) 'project_day_id': projectDayId},
    );
    return SelfActionResult.fromJson(asMap(res.data));
  }

  /// POST /me/break/end {project_day_id?} → {message, assignment}
  Future<SelfActionResult> breakEnd({int? projectDayId}) async {
    final res = await _api.post(
      '/me/break/end',
      data: {if (projectDayId != null) 'project_day_id': projectDayId},
    );
    return SelfActionResult.fromJson(asMap(res.data));
  }

  /// POST /field/location {lat, lng, accuracy?, project_day_id?} → {message, project_day_id}
  Future<String?> reportLocation(LocationReport report) async {
    final res = await _api.post('/field/location', data: report.toJson());
    final data = res.data;
    return data is Map ? asStringOrNull(data['message']) : null;
  }
}

final selfRepositoryProvider = Provider<SelfRepository>(
  (ref) => SelfRepository(ref.watch(apiClientProvider)),
);
