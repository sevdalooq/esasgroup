import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/json.dart';
import 'models/models.dart';

/// Saha uçları (`/field/...`) için veri katmanı.
class FieldRepository {
  FieldRepository(this._api);

  final ApiClient _api;

  /// GET /field/today
  Future<List<ProjectDaySummary>> today() async {
    final res = await _api.get('/field/today');
    return asMapList(res.data).map(ProjectDaySummary.fromJson).toList();
  }

  /// GET /field/days/{id}
  Future<ProjectDayDetail> day(int id) async {
    final res = await _api.get('/field/days/$id');
    return ProjectDayDetail.fromJson(asMap(res.data));
  }

  /// POST /field/scan {payload, project_day_id}
  Future<ScanResult> scan(String payload, int projectDayId) async {
    final res = await _api.post(
      '/field/scan',
      data: {'payload': payload, 'project_day_id': projectDayId},
    );
    return ScanResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/check-in
  Future<void> checkIn(
    int dayId, {
    String? personnelPayload,
    int? personnelId,
    String? zonePayload,
    String? zone,
    XFile? photo,
  }) async {
    final fields = <String, dynamic>{
      if (personnelPayload != null) 'personnel_payload': personnelPayload,
      if (personnelId != null) 'personnel_id': personnelId,
      if (zonePayload != null && zonePayload.isNotEmpty) 'zone_payload': zonePayload,
      if (zone != null && zone.isNotEmpty) 'zone': zone,
    };
    await _api.post('/field/days/$dayId/check-in', data: await _withPhoto(fields, photo));
  }

  /// POST /field/days/{id}/check-out
  Future<void> checkOut(
    int dayId, {
    String? personnelPayload,
    int? assignmentId,
  }) async {
    await _api.post(
      '/field/days/$dayId/check-out',
      data: {
        if (personnelPayload != null) 'personnel_payload': personnelPayload,
        if (assignmentId != null) 'assignment_id': assignmentId,
      },
    );
  }

  /// POST /field/days/{id}/inventory/deliver
  Future<void> deliverInventory(
    int dayId, {
    String? inventoryPayload,
    int? inventoryId,
    int? personnelId,
  }) async {
    await _api.post(
      '/field/days/$dayId/inventory/deliver',
      data: {
        if (inventoryPayload != null) 'inventory_payload': inventoryPayload,
        if (inventoryId != null) 'inventory_id': inventoryId,
        if (personnelId != null) 'personnel_id': personnelId,
      },
    );
  }

  /// POST /field/days/{id}/inventory/return
  Future<void> returnInventory(
    int dayId, {
    String? inventoryPayload,
    int? inventoryId,
    required bool damaged,
    String? damageDescription,
  }) async {
    await _api.post(
      '/field/days/$dayId/inventory/return',
      data: {
        if (inventoryPayload != null) 'inventory_payload': inventoryPayload,
        if (inventoryId != null) 'inventory_id': inventoryId,
        'damaged': damaged,
        if (damageDescription != null && damageDescription.isNotEmpty)
          'damage_description': damageDescription,
      },
    );
  }

  /// POST /field/days/{id}/start {photo?}
  Future<void> startDay(int dayId, {XFile? photo}) async {
    await _api.post('/field/days/$dayId/start', data: await _withPhoto({}, photo));
  }

  /// POST /field/days/{id}/end {photo?}
  Future<void> endDay(int dayId, {XFile? photo}) async {
    await _api.post('/field/days/$dayId/end', data: await _withPhoto({}, photo));
  }

  /// Fotoğraf varsa multipart, yoksa düz JSON gövde döndürür.
  Future<Object> _withPhoto(Map<String, dynamic> fields, XFile? photo) async {
    if (photo == null) return fields;
    final name = photo.name.isEmpty ? 'photo.jpg' : photo.name;
    return FormData.fromMap({
      ...fields,
      'photo': await MultipartFile.fromFile(photo.path, filename: name),
    });
  }
}

final fieldRepositoryProvider = Provider<FieldRepository>(
  (ref) => FieldRepository(ref.watch(apiClientProvider)),
);
