import '../../../core/utils/json.dart';

/// Proje günü sayaçları.
class DayCounts {
  const DayCounts({
    this.personnelTotal = 0,
    this.personnelCheckedIn = 0,
    this.inventoryDelivered = 0,
    this.inventoryReturned = 0,
    this.inventoryTotal,
  });

  final int personnelTotal;
  final int personnelCheckedIn;
  final int inventoryDelivered;
  final int inventoryReturned;
  final int? inventoryTotal;

  factory DayCounts.fromJson(Map<String, dynamic> json) => DayCounts(
        personnelTotal: asInt(json['personnel_total']),
        personnelCheckedIn: asInt(json['personnel_checked_in']),
        inventoryDelivered: asInt(json['inventory_delivered']),
        inventoryReturned: asInt(json['inventory_returned']),
        inventoryTotal: asIntOrNull(json['inventory_total']),
      );

  double get personnelRatio =>
      personnelTotal == 0 ? 0 : (personnelCheckedIn / personnelTotal).clamp(0, 1);
}

/// `GET /field/today` satırı.
class ProjectDaySummary {
  const ProjectDaySummary({
    required this.id,
    this.date,
    this.status = '',
    this.projectId,
    this.projectName = '',
    this.customerName = '',
    this.counts = const DayCounts(),
  });

  final int id;
  final DateTime? date;
  final String status;
  final int? projectId;
  final String projectName;
  final String customerName;
  final DayCounts counts;

  factory ProjectDaySummary.fromJson(Map<String, dynamic> json) {
    final project = asMap(json['project']);
    final customer = asMap(project['customer']);
    return ProjectDaySummary(
      id: asInt(json['id']),
      date: asDateOnly(json['date']),
      status: asString(json['status']),
      projectId: asIntOrNull(project['id']),
      projectName: asString(project['name'], asString(json['project_name'])),
      customerName: asString(
        customer['name'],
        asString(project['customer_name'], asString(json['customer_name'])),
      ),
      counts: DayCounts.fromJson(asMap(json['counts'])),
    );
  }
}

class Zone {
  const Zone({required this.id, required this.name, this.qrPayload});

  final int id;
  final String name;
  final String? qrPayload;

  factory Zone.fromJson(Map<String, dynamic> json) => Zone(
        id: asInt(json['id']),
        name: asString(json['name'], 'Alan'),
        qrPayload: asStringOrNull(json['qr_payload']),
      );
}

/// Personel görevlendirme satırı (id = assignment id).
class PersonnelAssignment {
  const PersonnelAssignment({
    required this.id,
    required this.personnelId,
    this.firstName = '',
    this.lastName = '',
    this.fullName = '',
    this.photo,
    this.qrPayload,
    this.zone = '',
    this.checkInTime,
    this.checkOutTime,
    this.isChecked = false,
    this.paymentStatus = '',
  });

  final int id;
  final int personnelId;
  final String firstName;
  final String lastName;
  final String fullName;
  final String? photo;
  final String? qrPayload;
  final String zone;
  final String? checkInTime;
  final String? checkOutTime;
  final bool isChecked;
  final String paymentStatus;

  bool get isCheckedIn => isChecked || (checkInTime?.isNotEmpty ?? false);
  bool get isCheckedOut => checkOutTime?.isNotEmpty ?? false;

  String get displayName {
    if (fullName.isNotEmpty) return fullName;
    final joined = '$firstName $lastName'.trim();
    return joined.isEmpty ? 'Personel #$personnelId' : joined;
  }

  factory PersonnelAssignment.fromJson(Map<String, dynamic> json) {
    final p = asMap(json['personnel']);
    final zoneRaw = json['zone'];
    final zone = zoneRaw is Map
        ? asString(zoneRaw['name'])
        : asString(zoneRaw, asString(json['zone_name']));
    return PersonnelAssignment(
      id: asInt(json['id']),
      personnelId: asInt(p['id'], asInt(json['personnel_id'])),
      firstName: asString(p['first_name']),
      lastName: asString(p['last_name']),
      fullName: asString(p['full_name'], asString(p['name'])),
      photo: asStringOrNull(p['photo'] ?? p['photo_url']),
      qrPayload: asStringOrNull(p['qr_payload']),
      zone: zone,
      checkInTime: asStringOrNull(json['check_in_time'] ?? json['check_in']),
      checkOutTime: asStringOrNull(json['check_out_time'] ?? json['check_out']),
      isChecked: asBool(json['is_checked']),
      paymentStatus: asString(json['payment_status']),
    );
  }
}

/// Envanter görevlendirme satırı.
class InventoryAssignment {
  const InventoryAssignment({
    required this.id,
    required this.inventoryId,
    this.name = '',
    this.serialNumber = '',
    this.qrPayload,
    this.quantity = 1,
    this.status = '',
    this.returnStatus = '',
    this.assignedToPersonnelId,
    this.damageDescription,
  });

  final int id;
  final int inventoryId;
  final String name;
  final String serialNumber;
  final String? qrPayload;
  final int quantity;
  final String status;
  final String returnStatus;
  final int? assignedToPersonnelId;
  final String? damageDescription;

  bool get isReturned =>
      status == 'returned' ||
      (returnStatus.isNotEmpty && returnStatus != 'pending' && returnStatus != 'none');

  bool get isDelivered =>
      !isReturned &&
      (status == 'delivered' || status == 'in_use' || status == 'active');

  bool get isDamaged =>
      returnStatus == 'damaged' || status == 'damaged';

  String get displayName => name.isEmpty ? 'Envanter #$inventoryId' : name;

  factory InventoryAssignment.fromJson(Map<String, dynamic> json) {
    final inv = asMap(json['inventory']);
    return InventoryAssignment(
      id: asInt(json['id']),
      inventoryId: asInt(inv['id'], asInt(json['inventory_id'])),
      name: asString(inv['name'], asString(json['name'])),
      serialNumber: asString(inv['serial_number'], asString(json['serial_number'])),
      qrPayload: asStringOrNull(inv['qr_payload']),
      quantity: asInt(json['quantity'], 1),
      status: asString(json['status']),
      returnStatus: asString(json['return_status']),
      assignedToPersonnelId: asIntOrNull(json['assigned_to_personnel_id']),
      damageDescription: asStringOrNull(json['damage_description']),
    );
  }
}

/// `GET /field/days/{id}` yanıtı.
class ProjectDayDetail {
  const ProjectDayDetail({
    required this.id,
    this.date,
    this.status = '',
    this.startPhoto,
    this.endPhoto,
    this.startedAt,
    this.endedAt,
    this.projectId,
    this.projectName = '',
    this.customerName = '',
    this.personnel = const [],
    this.inventory = const [],
    this.zones = const [],
  });

  final int id;
  final DateTime? date;
  final String status;
  final String? startPhoto;
  final String? endPhoto;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final int? projectId;
  final String projectName;
  final String customerName;
  final List<PersonnelAssignment> personnel;
  final List<InventoryAssignment> inventory;
  final List<Zone> zones;

  bool get isPending => status == 'pending' || status.isEmpty;
  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';

  int get checkedInCount => personnel.where((p) => p.isCheckedIn).length;
  int get deliveredCount => inventory.where((i) => i.isDelivered).length;
  int get returnedCount => inventory.where((i) => i.isReturned).length;

  factory ProjectDayDetail.fromJson(Map<String, dynamic> raw) {
    // Bazı API'ler `{data: {...}}` sarmalı döndürür.
    final json = raw['data'] is Map && raw['id'] == null ? asMap(raw['data']) : raw;
    final project = asMap(json['project']);
    final customerRaw = project['customer'];
    final customerName = customerRaw is Map
        ? asString(customerRaw['name'])
        : asString(customerRaw, asString(project['customer_name']));
    return ProjectDayDetail(
      id: asInt(json['id']),
      date: asDateOnly(json['date']),
      status: asString(json['status']),
      startPhoto: asStringOrNull(json['start_photo'] ?? json['start_photo_url']),
      endPhoto: asStringOrNull(json['end_photo'] ?? json['end_photo_url']),
      startedAt: asDateTime(json['started_at'] ?? json['start_time']),
      endedAt: asDateTime(json['ended_at'] ?? json['end_time']),
      projectId: asIntOrNull(project['id']),
      projectName: asString(project['name'], asString(json['project_name'])),
      customerName: customerName,
      personnel: asMapList(json['personnel']).map(PersonnelAssignment.fromJson).toList(),
      inventory: asMapList(json['inventory']).map(InventoryAssignment.fromJson).toList(),
      zones: asMapList(json['zones']).map(Zone.fromJson).toList(),
    );
  }
}

/// `POST /field/scan` yanıtı.
class ScanResult {
  const ScanResult({
    required this.type,
    this.entity = const {},
    this.context = const {},
  });

  /// `inventory` | `personnel` | `zone` | (bilinmeyen)
  final String type;
  final Map<String, dynamic> entity;
  final Map<String, dynamic> context;

  bool get isPersonnel => type == 'personnel';
  bool get isInventory => type == 'inventory';
  bool get isZone => type == 'zone';

  String get entityName {
    final full = asString(entity['full_name']);
    if (full.isNotEmpty) return full;
    final name = asString(entity['name']);
    if (name.isNotEmpty) return name;
    final joined = '${asString(entity['first_name'])} ${asString(entity['last_name'])}'.trim();
    return joined;
  }

  int? get entityId => asIntOrNull(entity['id']);

  factory ScanResult.fromJson(Map<String, dynamic> json) => ScanResult(
        type: asString(json['type']),
        entity: asMap(json['entity']),
        context: asMap(json['context']),
      );
}
