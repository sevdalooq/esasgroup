import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/utils/json.dart';
import 'models/models.dart';

/// Saha uçları (`/field/...`) için veri katmanı.
/// Yazma işlemleri sunucunun `message` alanını (varsa) döndürür.
class FieldRepository {
  FieldRepository(this._api);

  final ApiClient _api;

  /// GET /field/today → {today, days:[...]} (eski şekil: düz liste de kabul edilir)
  Future<List<ProjectDaySummary>> today() async {
    final res = await _api.get('/field/today');
    final body = res.data;
    final rows = body is Map
        ? asMapList(body['days'] ?? body['data'] ?? const [])
        : asMapList(body);
    return rows.map(ProjectDaySummary.fromJson).toList();
  }

  /// GET /field/days/{id} → {day:{..., expenses:[...]}, zones:[...], summary:{...}}
  Future<ProjectDayDetail> day(int id) async {
    final res = await _api.get('/field/days/$id');
    return ProjectDayDetail.fromJson(asMap(res.data));
  }

  /// POST /field/scan {payload | nfc_uid, project_day_id?}
  Future<ScanResult> scan(
    String payload,
    int? projectDayId, {
    bool isNfcUid = false,
  }) async {
    final res = await _api.post(
      '/field/scan',
      data: {
        if (isNfcUid) 'nfc_uid': payload else 'payload': payload,
        if (projectDayId != null) 'project_day_id': projectDayId,
      },
    );
    return ScanResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/check-in
  /// {personnel_payload | personnel_id, zone_payload | zone, photo?, lat?, lng?}
  /// Güne atanmamış personel payload ile gönderilirse sunucu görevlendirmeyi
  /// oluşturur ("son dakika ekle").
  Future<String?> checkIn(
    int dayId, {
    String? personnelPayload,
    int? personnelId,
    String? zonePayload,
    String? zone,
    XFile? photo,
    double? lat,
    double? lng,
  }) async {
    final fields = <String, dynamic>{
      if (personnelPayload != null) 'personnel_payload': personnelPayload,
      if (personnelId != null) 'personnel_id': personnelId,
      if (zonePayload != null && zonePayload.isNotEmpty) 'zone_payload': zonePayload,
      if (zone != null && zone.isNotEmpty) 'zone': zone,
      if (lat != null) 'lat': lat,
      if (lng != null) 'lng': lng,
    };
    final res = await _api.post(
      '/field/days/$dayId/check-in',
      data: await _withPhoto(fields, 'photo', photo),
    );
    return _message(res);
  }

  /// POST /field/days/{id}/check-out (JSON)
  /// {assignment_id, check_out_time, overtime_hours, overtime_rate,
  ///  payment_status, payment_method, payment_amount, inventory_returns:[...]}
  /// → {message, assignment, summary}
  Future<CheckOutResult> checkOut(int dayId, CheckOutRequest request) async {
    final res = await _api.post(
      '/field/days/$dayId/check-out',
      data: request.toJson(),
    );
    return CheckOutResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/check-out {personnel_payload} – QR ile hızlı çıkış.
  /// (Mesai/ödeme/iade seçenekleri olmadan; sunucu varsayılanları uygular.)
  Future<CheckOutResult> checkOutByPayload(int dayId, String personnelPayload) async {
    final res = await _api.post(
      '/field/days/$dayId/check-out',
      data: {'personnel_payload': personnelPayload},
    );
    return CheckOutResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/inventory/deliver
  /// {inventory_payload | nfc_uid | inventory_id, personnel_payload | personnel_id, quantity}
  Future<String?> deliverInventory(
    int dayId, {
    String? inventoryPayload,
    String? nfcUid,
    int? inventoryId,
    String? personnelPayload,
    int? personnelId,
    int? quantity,
  }) async {
    final res = await _api.post(
      '/field/days/$dayId/inventory/deliver',
      data: {
        if (inventoryPayload != null) 'inventory_payload': inventoryPayload,
        if (nfcUid != null) 'nfc_uid': nfcUid,
        if (inventoryId != null) 'inventory_id': inventoryId,
        if (personnelPayload != null) 'personnel_payload': personnelPayload,
        if (personnelId != null) 'personnel_id': personnelId,
        if (quantity != null) 'quantity': quantity,
      },
    );
    return _message(res);
  }

  /// POST /field/days/{id}/inventory/return
  /// {inventory_payload | nfc_uid | inventory_id, damaged, damage_description?, deduction_amount?, damage_photo?}
  Future<String?> returnInventory(
    int dayId, {
    String? inventoryPayload,
    String? nfcUid,
    int? inventoryId,
    required bool damaged,
    String? damageDescription,
    double? deductionAmount,
    XFile? damagePhoto,
  }) async {
    final fields = <String, dynamic>{
      if (inventoryPayload != null) 'inventory_payload': inventoryPayload,
      if (nfcUid != null) 'nfc_uid': nfcUid,
      if (inventoryId != null) 'inventory_id': inventoryId,
      // Multipart gövdede bool string'e dönüşür; Laravel `boolean` kuralı 1/0 kabul eder.
      'damaged': damagePhoto != null ? (damaged ? 1 : 0) : damaged,
      if (damageDescription != null && damageDescription.isNotEmpty)
        'damage_description': damageDescription,
      if (deductionAmount != null) 'deduction_amount': deductionAmount,
    };
    final res = await _api.post(
      '/field/days/$dayId/inventory/return',
      data: await _withPhoto(fields, 'damage_photo', damagePhoto),
    );
    return _message(res);
  }

  /// POST /field/days/{id}/start {start_photo?}
  Future<String?> startDay(int dayId, {XFile? photo}) async {
    final res = await _api.post(
      '/field/days/$dayId/start',
      data: await _withPhoto({}, 'start_photo', photo),
    );
    return _message(res);
  }

  /// POST /field/days/{id}/end {end_photo?}
  Future<String?> endDay(int dayId, {XFile? photo}) async {
    final res = await _api.post(
      '/field/days/$dayId/end',
      data: await _withPhoto({}, 'end_photo', photo),
    );
    return _message(res);
  }

  // ---------------- Personel durumu (gelmedi / mola) ----------------

  /// POST /field/days/{id}/absent {assignment_id, absent} → {message, assignment, summary}
  Future<AssignmentResult> markAbsent(int dayId, int assignmentId, {required bool absent}) async {
    final res = await _api.post(
      '/field/days/$dayId/absent',
      data: {'assignment_id': assignmentId, 'absent': absent},
    );
    return AssignmentResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/break/start {assignment_id} → {message, assignment, summary}
  Future<AssignmentResult> breakStart(int dayId, int assignmentId, {String? reason}) async {
    final res = await _api.post(
      '/field/days/$dayId/break/start',
      data: {
        'assignment_id': assignmentId,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      },
    );
    return AssignmentResult.fromJson(asMap(res.data));
  }

  /// POST /field/days/{id}/break/end {assignment_id} → {message, assignment, summary}
  Future<AssignmentResult> breakEnd(int dayId, int assignmentId) async {
    final res = await _api.post(
      '/field/days/$dayId/break/end',
      data: {'assignment_id': assignmentId},
    );
    return AssignmentResult.fromJson(asMap(res.data));
  }

  // ---------------- Masraflar ----------------

  /// GET /expense-categories/all → [{id, name, slug, icon, color}]
  Future<List<ExpenseCategory>> expenseCategories() async {
    final res = await _api.get('/expense-categories/all');
    final rows = asMapList(res.data);
    final list = rows.map(ExpenseCategory.fromJson).toList();
    return list.isEmpty ? ExpenseCategory.defaults : list;
  }

  /// POST /field/days/{id}/expenses (multipart)
  /// {description, amount, category, receipt_photo?} → 201 {message, expense, expenses}
  Future<ExpenseResult> addExpense(
    int dayId, {
    required String description,
    required double amount,
    required String category,
    XFile? receiptPhoto,
  }) async {
    final fields = <String, dynamic>{
      'description': description,
      'amount': amount,
      'category': category,
    };
    final res = await _api.post(
      '/field/days/$dayId/expenses',
      data: await _withPhoto(fields, 'receipt_photo', receiptPhoto, forceMultipart: true),
    );
    return ExpenseResult.fromJson(asMap(res.data));
  }

  /// DELETE /field/days/{id}/expenses/{expenseId} (yalnızca `pending`)
  Future<String?> deleteExpense(int dayId, int expenseId) async {
    final res = await _api.delete('/field/days/$dayId/expenses/$expenseId');
    return _message(res);
  }

  String? _message(Response<dynamic> res) {
    final data = res.data;
    if (data is Map) return asStringOrNull(data['message']);
    return null;
  }

  /// Fotoğraf varsa multipart, yoksa düz JSON gövde döndürür
  /// ([forceMultipart] ile fotoğrafsız da multipart gönderilir).
  Future<Object> _withPhoto(
    Map<String, dynamic> fields,
    String fieldName,
    XFile? photo, {
    bool forceMultipart = false,
  }) async {
    if (photo == null) return forceMultipart ? FormData.fromMap(fields) : fields;
    final name = photo.name.isEmpty ? 'photo.jpg' : photo.name;
    return FormData.fromMap({
      ...fields,
      fieldName: await MultipartFile.fromFile(photo.path, filename: name),
    });
  }
}

final fieldRepositoryProvider = Provider<FieldRepository>(
  (ref) => FieldRepository(ref.watch(apiClientProvider)),
);
