import '../../../core/utils/json.dart';

/// Proje günü sayaçları. Hem `counts:{...}` sarmalını hem de gün nesnesindeki
/// düz `personnel_total`… alanlarını okur.
class DayCounts {
  const DayCounts({
    this.personnelTotal = 0,
    this.personnelCheckedIn = 0,
    this.personnelCheckedOut = 0,
    this.inventoryTotal = 0,
    this.inventoryDelivered = 0,
    this.inventoryReturned = 0,
  });

  final int personnelTotal;
  final int personnelCheckedIn;
  final int personnelCheckedOut;
  final int inventoryTotal;
  final int inventoryDelivered;
  final int inventoryReturned;

  factory DayCounts.fromJson(Map<String, dynamic> json) => DayCounts(
        personnelTotal: asInt(json['personnel_total']),
        personnelCheckedIn: asInt(json['personnel_checked_in']),
        personnelCheckedOut: asInt(json['personnel_checked_out']),
        inventoryTotal: asInt(json['inventory_total']),
        inventoryDelivered: asInt(json['inventory_delivered']),
        inventoryReturned: asInt(json['inventory_returned']),
      );

  double get personnelRatio =>
      personnelTotal == 0 ? 0 : (personnelCheckedIn / personnelTotal).clamp(0, 1);
}

/// `GET /field/today` → `days[]` satırı.
class ProjectDaySummary {
  const ProjectDaySummary({
    required this.id,
    this.date,
    this.status = '',
    this.projectId,
    this.projectName = '',
    this.customerName = '',
    this.notes,
    this.counts = const DayCounts(),
  });

  final int id;
  final DateTime? date;
  final String status;
  final int? projectId;
  final String projectName;
  final String customerName;
  final String? notes;
  final DayCounts counts;

  factory ProjectDaySummary.fromJson(Map<String, dynamic> json) {
    final project = asMap(json['project']);
    final customer = asMap(project['customer']);
    final countsMap = json['counts'] is Map ? asMap(json['counts']) : json;
    return ProjectDaySummary(
      id: asInt(json['id']),
      date: asDateOnly(json['date']),
      status: asString(json['status']),
      projectId: asIntOrNull(project['id'] ?? json['project_id']),
      projectName: asString(project['name'], asString(json['project_name'])),
      customerName: asString(
        customer['name'],
        asString(project['customer_name'], asString(json['customer_name'])),
      ),
      notes: asStringOrNull(json['notes']),
      counts: DayCounts.fromJson(countsMap),
    );
  }
}

class Zone {
  const Zone({required this.id, required this.name, this.qrPayload, this.qrCode});

  final int id;
  final String name;
  final String? qrPayload;
  final String? qrCode;

  factory Zone.fromJson(Map<String, dynamic> json) {
    final code = asStringOrNull(json['qr_code']);
    return Zone(
      id: asInt(json['id']),
      name: asString(json['name'], 'Alan'),
      qrPayload: asStringOrNull(json['qr_payload']) ??
          (code != null ? 'ESAS:ZONE:$code' : null),
      qrCode: code,
    );
  }
}

/// Personel görevlendirme satırı (`id` = assignment id).
class PersonnelAssignment {
  const PersonnelAssignment({
    required this.id,
    required this.personnelId,
    this.firstName = '',
    this.lastName = '',
    this.fullName = '',
    this.phone,
    this.photo,
    this.qrPayload,
    this.zone = '',
    this.checkInTime,
    this.checkOutTime,
    this.checkInPhoto,
    this.checkOutPhoto,
    this.isChecked = false,
    this.paymentStatus = '',
    this.paymentAmount = 0,
    this.dailyWage = 0,
    this.overtimeHours = 0,
    this.totalEarnings = 0,
    this.notes,
  });

  final int id;
  final int personnelId;
  final String firstName;
  final String lastName;
  final String fullName;
  final String? phone;
  final String? photo;
  final String? qrPayload;
  final String zone;
  final String? checkInTime;
  final String? checkOutTime;
  final String? checkInPhoto;
  final String? checkOutPhoto;
  final bool isChecked;
  final String paymentStatus;
  final double paymentAmount;
  final double dailyWage;
  final double overtimeHours;
  final double totalEarnings;
  final String? notes;

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
    final qrCode = asStringOrNull(p['qr_code']);
    var qrPayload = asStringOrNull(p['qr_payload']);
    // Bazı iç içe kopyalarda payload uuid'siz (`ESAS:PER:`) gelebiliyor.
    if ((qrPayload == null || qrPayload.endsWith(':')) && qrCode != null) {
      qrPayload = 'ESAS:PER:$qrCode';
    }
    return PersonnelAssignment(
      id: asInt(json['id']),
      personnelId: asInt(p['id'], asInt(json['personnel_id'])),
      firstName: asString(p['first_name']),
      lastName: asString(p['last_name']),
      fullName: asString(p['full_name'], asString(p['name'])),
      phone: asStringOrNull(p['phone']),
      photo: asStringOrNull(p['photo'] ?? p['photo_1'] ?? p['photo_url']),
      qrPayload: qrPayload,
      zone: zone,
      checkInTime: asStringOrNull(json['check_in_time'] ?? json['check_in']),
      checkOutTime: asStringOrNull(json['check_out_time'] ?? json['check_out']),
      checkInPhoto: asStringOrNull(json['check_in_photo']),
      checkOutPhoto: asStringOrNull(json['check_out_photo']),
      isChecked: asBool(json['is_checked']),
      paymentStatus: asString(json['payment_status']),
      paymentAmount: asDouble(json['payment_amount']),
      dailyWage: asDouble(json['daily_wage']),
      overtimeHours: asDouble(json['overtime_hours']),
      totalEarnings: asDouble(json['total_earnings']),
      notes: asStringOrNull(json['notes']),
    );
  }
}

/// Envanter görevlendirme satırı.
/// `status`: pending|delivered|returned|damaged · `return_status`: pending|returned|damaged
class InventoryAssignment {
  const InventoryAssignment({
    required this.id,
    required this.inventoryId,
    this.name = '',
    this.type = '',
    this.serialNumber = '',
    this.qrPayload,
    this.nfcUid,
    this.quantity = 1,
    this.status = '',
    this.returnStatus = '',
    this.assignedToAssignmentId,
    this.assignedToPersonnelId,
    this.assignedToName,
    this.deliveredAt,
    this.returnedAt,
    this.damageDescription,
    this.damagePhoto,
  });

  final int id;
  final int inventoryId;
  final String name;
  final String type;
  final String serialNumber;
  final String? qrPayload;
  final String? nfcUid;
  final int quantity;
  final String status;
  final String returnStatus;

  /// Sunucudaki `assigned_to_personnel_id` aslında görevlendirme (assignment) id'sidir.
  final int? assignedToAssignmentId;

  /// `assigned_to_personnel.personnel_id` (gerçek personel id).
  final int? assignedToPersonnelId;
  final String? assignedToName;
  final DateTime? deliveredAt;
  final DateTime? returnedAt;
  final String? damageDescription;
  final String? damagePhoto;

  bool get isDamaged => status == 'damaged' || returnStatus == 'damaged';

  bool get isReturned =>
      status == 'returned' ||
      status == 'damaged' ||
      returnStatus == 'returned' ||
      returnStatus == 'damaged' ||
      returnedAt != null;

  bool get isDelivered =>
      !isReturned &&
      (status == 'delivered' || status == 'in_use' || deliveredAt != null);

  bool get isPending => !isDelivered && !isReturned;

  String get displayName => name.isEmpty ? 'Envanter #$inventoryId' : name;

  factory InventoryAssignment.fromJson(Map<String, dynamic> json) {
    final inv = asMap(json['inventory']);
    final holder = asMap(json['assigned_to_personnel']);
    final holderPerson = asMap(holder['personnel']);
    final qrCode = asStringOrNull(inv['qr_code']);
    var qrPayload = asStringOrNull(inv['qr_payload']);
    if ((qrPayload == null || qrPayload.endsWith(':')) && qrCode != null) {
      qrPayload = 'ESAS:INV:$qrCode';
    }
    final holderName = asString(
      holderPerson['full_name'],
      '${asString(holderPerson['first_name'])} ${asString(holderPerson['last_name'])}'.trim(),
    );
    return InventoryAssignment(
      id: asInt(json['id']),
      inventoryId: asInt(inv['id'], asInt(json['inventory_id'])),
      name: asString(inv['name'], asString(json['name'])),
      type: asString(inv['type']),
      serialNumber: asString(inv['serial_number'], asString(json['serial_number'])),
      qrPayload: qrPayload,
      nfcUid: asStringOrNull(inv['nfc_uid']),
      quantity: asInt(json['quantity'], 1),
      status: asString(json['status']),
      returnStatus: asString(json['return_status']),
      assignedToAssignmentId: asIntOrNull(json['assigned_to_personnel_id']),
      assignedToPersonnelId: asIntOrNull(holder['personnel_id'] ?? holderPerson['id']),
      assignedToName: holderName.isEmpty ? null : holderName,
      deliveredAt: asDateTime(json['delivered_at']),
      returnedAt: asDateTime(json['returned_at']),
      damageDescription: asStringOrNull(json['damage_description']),
      damagePhoto: asStringOrNull(json['damage_photo']),
    );
  }
}

/// `summary` bloğu; yoksa listelerden hesaplanır.
class DaySummary {
  const DaySummary({
    this.personnelCount = 0,
    this.checkedInCount = 0,
    this.checkedOutCount = 0,
    this.totalEarnings = 0,
    this.totalPaid = 0,
    this.totalPending = 0,
    this.totalOvertime = 0,
    this.overtimePersonnelCount = 0,
    this.inventoryCount = 0,
    this.inventoryDelivered = 0,
    this.inventoryReturned = 0,
    this.inventoryDamaged = 0,
    this.inventoryPendingReturn = 0,
  });

  final int personnelCount;
  final int checkedInCount;
  final int checkedOutCount;
  final double totalEarnings;
  final double totalPaid;
  final double totalPending;
  final double totalOvertime;
  final int overtimePersonnelCount;
  final int inventoryCount;
  final int inventoryDelivered;
  final int inventoryReturned;
  final int inventoryDamaged;
  final int inventoryPendingReturn;

  factory DaySummary.fromJson(Map<String, dynamic> json) => DaySummary(
        personnelCount: asInt(json['personnel_count']),
        checkedInCount: asInt(json['checked_in_count']),
        checkedOutCount: asInt(json['checked_out_count']),
        totalEarnings: asDouble(json['total_earnings']),
        totalPaid: asDouble(json['total_paid']),
        totalPending: asDouble(json['total_pending']),
        totalOvertime: asDouble(json['total_overtime']),
        overtimePersonnelCount: asInt(json['overtime_personnel_count']),
        inventoryCount: asInt(json['inventory_count']),
        inventoryDelivered: asInt(json['inventory_delivered']),
        inventoryReturned: asInt(json['inventory_returned']),
        inventoryDamaged: asInt(json['inventory_damaged']),
        inventoryPendingReturn: asInt(json['inventory_pending_return']),
      );

  factory DaySummary.compute(
    List<PersonnelAssignment> personnel,
    List<InventoryAssignment> inventory,
  ) =>
      DaySummary(
        personnelCount: personnel.length,
        checkedInCount: personnel.where((p) => p.isCheckedIn).length,
        checkedOutCount: personnel.where((p) => p.isCheckedOut).length,
        totalEarnings: personnel.fold(0, (sum, p) => sum + p.totalEarnings),
        inventoryCount: inventory.length,
        inventoryDelivered: inventory.where((i) => i.isDelivered).length,
        inventoryReturned: inventory.where((i) => i.isReturned).length,
        inventoryDamaged: inventory.where((i) => i.isDamaged).length,
        inventoryPendingReturn: inventory.where((i) => i.isDelivered).length,
      );
}

/// `GET /field/days/{id}` → `{day:{...}, zones:[...], summary:{...}}`.
class ProjectDayDetail {
  const ProjectDayDetail({
    required this.id,
    this.date,
    this.status = '',
    this.startPhoto,
    this.endPhoto,
    this.notes,
    this.startedAt,
    this.endedAt,
    this.projectId,
    this.projectName = '',
    this.customerName = '',
    this.supervisorName = '',
    this.personnel = const [],
    this.inventory = const [],
    this.zones = const [],
    this.summary = const DaySummary(),
  });

  final int id;
  final DateTime? date;
  final String status;
  final String? startPhoto;
  final String? endPhoto;
  final String? notes;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final int? projectId;
  final String projectName;
  final String customerName;
  final String supervisorName;
  final List<PersonnelAssignment> personnel;
  final List<InventoryAssignment> inventory;
  final List<Zone> zones;
  final DaySummary summary;

  bool get isPending => status == 'pending' || status.isEmpty;
  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';

  int get checkedInCount => personnel.where((p) => p.isCheckedIn).length;
  int get checkedOutCount => personnel.where((p) => p.isCheckedOut).length;
  int get deliveredCount => inventory.where((i) => i.isDelivered).length;
  int get returnedCount => inventory.where((i) => i.isReturned).length;

  /// Görevlendirme id'sinden personel satırı.
  PersonnelAssignment? assignmentById(int? assignmentId) => assignmentId == null
      ? null
      : personnel.where((p) => p.id == assignmentId).firstOrNull;

  factory ProjectDayDetail.fromJson(Map<String, dynamic> raw) {
    // Gerçek API: {day:{...}, zones, summary}. Sözleşme/eski şekil: düz nesne veya {data:{...}}.
    final Map<String, dynamic> json;
    if (raw['day'] is Map) {
      json = asMap(raw['day']);
    } else if (raw['data'] is Map && raw['id'] == null) {
      json = asMap(raw['data']);
    } else {
      json = raw;
    }
    final project = asMap(json['project']);
    final customerRaw = project['customer'];
    final customerName = customerRaw is Map
        ? asString(customerRaw['name'])
        : asString(customerRaw, asString(project['customer_name']));

    final personnel = asMapList(json['personnel_assignments'] ?? json['personnel'])
        .map(PersonnelAssignment.fromJson)
        .toList();
    final inventory = asMapList(json['inventory_assignments'] ?? json['inventory'])
        .map(InventoryAssignment.fromJson)
        .toList();
    final zones = asMapList(raw['zones'] ?? json['zones']).map(Zone.fromJson).toList();
    final summaryRaw = raw['summary'] ?? json['summary'];

    return ProjectDayDetail(
      id: asInt(json['id']),
      date: asDateOnly(json['date']),
      status: asString(json['status']),
      startPhoto: asStringOrNull(json['start_photo'] ?? json['start_photo_url']),
      endPhoto: asStringOrNull(json['end_photo'] ?? json['end_photo_url']),
      notes: asStringOrNull(json['notes']),
      startedAt: asDateTime(json['started_at'] ?? json['start_time']),
      endedAt: asDateTime(json['ended_at'] ?? json['end_time']),
      projectId: asIntOrNull(project['id'] ?? json['project_id']),
      projectName: asString(project['name'], asString(json['project_name'])),
      customerName: customerName,
      supervisorName: asString(asMap(json['supervisor'])['name']),
      personnel: personnel,
      inventory: inventory,
      zones: zones,
      summary: summaryRaw is Map
          ? DaySummary.fromJson(asMap(summaryRaw))
          : DaySummary.compute(personnel, inventory),
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

  /// `inventory` | `personnel` | `zone`
  final String type;
  final Map<String, dynamic> entity;

  /// inventory: {assigned, delivered, returned, assignment|null}
  /// personnel: bu günkü görevlendirme (veya null) · zone: alan
  final Map<String, dynamic> context;

  bool get isPersonnel => type == 'personnel';
  bool get isInventory => type == 'inventory';
  bool get isZone => type == 'zone';

  bool get contextAssigned => asBool(context['assigned']);
  bool get contextDelivered => asBool(context['delivered']);
  bool get contextReturned => asBool(context['returned']);

  String get entityName {
    final full = asString(entity['full_name']);
    if (full.isNotEmpty) return full;
    final name = asString(entity['name']);
    if (name.isNotEmpty) return name;
    return '${asString(entity['first_name'])} ${asString(entity['last_name'])}'.trim();
  }

  int? get entityId => asIntOrNull(entity['id']);

  factory ScanResult.fromJson(Map<String, dynamic> json) => ScanResult(
        type: asString(json['type']),
        entity: asMap(json['entity']),
        context: asMap(json['context']),
      );
}
