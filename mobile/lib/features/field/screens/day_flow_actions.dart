import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../../scanner/qr_payload.dart';
import '../../scanner/scan_helpers.dart';
import '../field_providers.dart';
import '../field_repository.dart';
import '../models/models.dart';
import '../widgets/check_in_sheet.dart';
import '../widgets/check_out_sheet.dart';
import '../widgets/expense_sheet.dart';
import '../widgets/personnel_picker_sheet.dart';
import '../widgets/return_inventory_sheet.dart';
import 'day_actions.dart';

/// Gün akışındaki tüm sunucu işlemleri (giriş/çıkış, teslim/iade, masraf,
/// gün başlat/bitir). Aşama görünümleri yalnızca bunları çağırır.
class DayFlowActions {
  DayFlowActions({required this.context, required this.ref, required this.detail});

  final BuildContext context;
  final WidgetRef ref;
  final ProjectDayDetail detail;

  FieldRepository get _repo => ref.read(fieldRepositoryProvider);
  DayActions get _actions => DayActions(context: context, ref: ref, dayId: detail.id);

  // ======================= Personel girişi =======================

  /// QR okut → personeli çöz (listede / sunucuda) → giriş akışı.
  /// Güne atanmamış personel "son dakika" olarak eklenir.
  Future<void> checkInByScan() async {
    final payload = await openScanner(
      context,
      title: 'Personel QR',
      hint: 'Personel kartındaki QR kodu okutun',
    );
    if (payload == null || !context.mounted) return;

    final type = QrPayload.typeOf(payload);
    final local = detail.personnel.where((p) => p.qrPayload == payload).firstOrNull;

    if (local != null) {
      if (local.isCheckedIn) {
        showSnack(context, '${local.displayName} zaten ${formatTime(local.checkInTime)} saatinde giriş yapmış.');
        return;
      }
      await _runCheckIn(
        displayName: local.displayName,
        personnelPayload: payload,
        initialZone: local.zone,
      );
      return;
    }

    if (type == QrType.inventory) {
      showSnack(context, 'Bu kod bir envanter kodu. Envanter teslimi için "Envanter Teslim" kullanın.', error: true);
      return;
    }
    if (type == QrType.zone) {
      showSnack(context, 'Bu kod bir alan kodu. Önce personel QR kodunu okutun.', error: true);
      return;
    }

    // Listede yok: sunucuya sorup kim olduğunu öğren (son dakika ekleme).
    ScanResult result;
    try {
      result = await withProgress(
        context,
        () => _repo.scan(payload, detail.id),
        message: 'Kod sorgulanıyor…',
      );
    } catch (e) {
      if (context.mounted) showSnack(context, errorMessage(e), error: true);
      return;
    }
    if (!context.mounted) return;
    if (!result.isPersonnel) {
      showSnack(
        context,
        'Bu kod personel kodu değil (${result.type.isEmpty ? 'bilinmeyen' : result.type}).',
        error: true,
      );
      return;
    }

    final name = result.entityName.isEmpty ? 'Personel' : result.entityName;
    final ok = await confirmDialog(
      context,
      title: 'Son dakika personel',
      message: '$name bu güne atanmamış. Güne eklenip giriş yapılsın mı?',
      confirmText: 'Ekle ve giriş yap',
    );
    if (!ok || !context.mounted) return;
    await _runCheckIn(displayName: name, personnelPayload: payload, isNewPersonnel: true);
  }

  /// Listeden "Giriş" → giriş akışı.
  Future<void> checkInManual(PersonnelAssignment p) => _runCheckIn(
        displayName: p.displayName,
        personnelId: p.personnelId,
        initialZone: p.zone,
      );

  Future<void> _runCheckIn({
    required String displayName,
    String? personnelPayload,
    int? personnelId,
    String? initialZone,
    bool isNewPersonnel = false,
  }) async {
    final plan = await showCheckInSheet(
      context,
      personnelName: displayName,
      zones: detail.zones,
      undelivered: detail.undeliveredInventory,
      initialZone: initialZone,
      isNewPersonnel: isNewPersonnel,
    );
    if (plan == null || !context.mounted) return;

    final failures = <String>[];
    await _actions.run(
      () async {
        final message = await _repo.checkIn(
          detail.id,
          personnelPayload: personnelPayload,
          personnelId: personnelId,
          zonePayload: plan.zone.payload,
          zone: plan.zone.name,
          photo: plan.photo,
        );
        // Zimmet teslimleri: giriş başarılıysa sırayla gönder; tekil hatalar
        // girişi geri almaz, sonda listelenir.
        for (final item in plan.inventory) {
          try {
            await _repo.deliverInventory(
              detail.id,
              inventoryId: item.inventoryId,
              personnelPayload: personnelPayload,
              personnelId: personnelId,
              quantity: item.quantity,
            );
          } catch (e) {
            failures.add('${item.displayName}: ${errorMessage(e)}');
          }
        }
        for (final payload in plan.extraPayloads) {
          try {
            await _repo.deliverInventory(
              detail.id,
              inventoryPayload: payload,
              personnelPayload: personnelPayload,
              personnelId: personnelId,
            );
          } catch (e) {
            failures.add('$payload: ${errorMessage(e)}');
          }
        }
        if (failures.isNotEmpty) {
          return 'Giriş kaydedildi; ${failures.length} zimmet teslim edilemedi.';
        }
        final base = (message == null || message.isEmpty) ? '$displayName giriş yaptı' : message;
        return plan.deliveryCount > 0 ? '$base · ${plan.deliveryCount} zimmet teslim edildi' : base;
      },
      success: '$displayName giriş yaptı',
      progress: plan.deliveryCount > 0 ? 'Giriş ve zimmet kaydediliyor…' : 'Giriş kaydediliyor…',
    );
    if (failures.isNotEmpty && context.mounted) {
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Teslim edilemeyen zimmet'),
          content: Text(failures.join('\n')),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Tamam')),
          ],
        ),
      );
    }
  }

  // ======================= Envanter =======================

  /// Satırdan "Teslim Et": personel seç → teslim.
  Future<void> deliverItem(InventoryAssignment item) => _deliver(
        inventoryId: item.inventoryId,
        name: item.displayName,
        quantity: item.quantity,
      );

  /// QR ile teslim.
  Future<void> deliverByScan() async {
    final resolved = await _scanInventory(title: 'Teslim Et – Envanter QR');
    if (resolved == null || !context.mounted) return;
    if (resolved.local != null && !resolved.local!.isPending) {
      showSnack(
        context,
        resolved.local!.isReturned
            ? '${resolved.local!.displayName} iade alınmış.'
            : '${resolved.local!.displayName} zaten teslim edilmiş.',
      );
      return;
    }
    await _deliver(payload: resolved.payload, name: resolved.name);
  }

  Future<void> _deliver({
    String? payload,
    int? inventoryId,
    required String name,
    int? quantity,
    PersonnelAssignment? to,
  }) async {
    final itemName = name.isEmpty ? 'Envanter' : name;
    PersonnelAssignment? person = to;
    if (person == null) {
      final pick = await showPersonnelPicker(
        context,
        personnel: detail.personnel,
        itemName: itemName,
      );
      if (pick == null || !context.mounted) return;
      person = pick.assignment;
    }
    final target = person;
    await _actions.run(
      () => _repo.deliverInventory(
        detail.id,
        inventoryPayload: payload,
        inventoryId: inventoryId,
        personnelId: target?.personnelId,
        quantity: quantity,
      ),
      success: '$itemName teslim edildi${target != null ? ' · ${target.displayName}' : ''}',
      progress: 'Teslim kaydediliyor…',
    );
  }

  /// Satırdan "Teslim Al".
  Future<void> returnItem(InventoryAssignment item) =>
      _returnItem(inventoryId: item.inventoryId, name: item.displayName);

  /// QR ile iade.
  Future<void> returnByScan() async {
    final resolved = await _scanInventory(title: 'Teslim Al – Envanter QR');
    if (resolved == null || !context.mounted) return;
    if (resolved.local != null && resolved.local!.isReturned) {
      showSnack(context, '${resolved.local!.displayName} zaten iade alınmış.');
      return;
    }
    await _returnItem(payload: resolved.payload, name: resolved.name);
  }

  Future<void> _returnItem({String? payload, int? inventoryId, required String name}) async {
    final itemName = name.isEmpty ? 'Envanter' : name;
    final info = await showReturnInventorySheet(context, itemName: itemName);
    if (info == null || !context.mounted) return;
    await _actions.run(
      () => _repo.returnInventory(
        detail.id,
        inventoryPayload: payload,
        inventoryId: inventoryId,
        damaged: info.damaged,
        damageDescription: info.description,
      ),
      success: info.damaged ? '$itemName hasarlı olarak teslim alındı' : '$itemName teslim alındı',
      progress: 'İade kaydediliyor…',
    );
  }

  Future<_ResolvedInventory?> _scanInventory({required String title}) async {
    final payload = await openScanner(
      context,
      title: title,
      hint: 'Envanter etiketindeki QR kodu okutun',
    );
    if (payload == null || !context.mounted) return null;

    final type = QrPayload.typeOf(payload);
    if (type == QrType.personnel || type == QrType.zone) {
      showSnack(context, 'Bu kod bir ${QrPayload.typeLabel(type)} kodu; envanter QR kodu okutun.', error: true);
      return null;
    }
    final local = detail.inventory.where((i) => i.qrPayload == payload).firstOrNull;
    var name = local?.displayName ?? '';
    if (local == null && type == QrType.unknown) {
      try {
        final result = await withProgress(
          context,
          () => _repo.scan(payload, detail.id),
          message: 'Kod sorgulanıyor…',
        );
        if (!context.mounted) return null;
        if (!result.isInventory) {
          showSnack(context, 'Bu kod envanter kodu değil.', error: true);
          return null;
        }
        name = result.entityName;
      } catch (e) {
        if (context.mounted) showSnack(context, errorMessage(e), error: true);
        return null;
      }
    }
    return _ResolvedInventory(payload: payload, local: local, name: name);
  }

  // ======================= Personel çıkışı =======================

  /// Çıkış alt sayfası (saat/mesai → iade → ödeme) → POST check-out.
  Future<void> checkOut(PersonnelAssignment p) async {
    if (!p.isCheckedIn) {
      showSnack(context, '${p.displayName} henüz giriş yapmamış.', error: true);
      return;
    }
    if (p.isCheckedOut) {
      showSnack(context, '${p.displayName} zaten ${formatTime(p.checkOutTime)} saatinde çıkış yapmış.');
      return;
    }
    final request = await showCheckOutSheet(
      context,
      assignment: p,
      heldInventory: detail.inventoryHeldBy(p),
    );
    if (request == null || !context.mounted) return;
    await _actions.run(
      () async {
        final result = await _repo.checkOut(detail.id, request);
        final a = result.assignment;
        final extra = a == null
            ? ''
            : ' · Hakediş ${formatMoney(a.totalEarnings)}'
                '${a.paymentAmount > 0 ? ', ödenen ${formatMoney(a.paymentAmount)}' : ''}';
        return '${result.message ?? '${p.displayName} çıkış yaptı'}$extra';
      },
      success: '${p.displayName} çıkış yaptı',
      progress: 'Çıkış kaydediliyor…',
    );
  }

  /// QR okut → listedeki personeli bul → çıkış akışı.
  Future<void> checkOutByScan() async {
    final payload = await openScanner(
      context,
      title: 'Çıkış – Personel QR',
      hint: 'Personel kartındaki QR kodu okutun',
    );
    if (payload == null || !context.mounted) return;
    final local = detail.personnel.where((p) => p.qrPayload == payload).firstOrNull;
    if (local == null) {
      final type = QrPayload.typeOf(payload);
      showSnack(
        context,
        type == QrType.personnel
            ? 'Bu personel bugünkü listede yok.'
            : 'Bu kod bir ${QrPayload.typeLabel(type)} kodu; personel QR okutun.',
        error: true,
      );
      return;
    }
    await checkOut(local);
  }

  // ======================= Personel durumu (gelmedi / mola) =======================

  /// Satır menüsü (uzun basma / ⋮): duruma göre Giriş, Çıkış, Gelmedi ↔ geri al,
  /// Mola başlat / Moladan döndü, Ara.
  Future<void> showPersonnelMenu(PersonnelAssignment p) async {
    final tel = telUriFor(p.phone);
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(p.displayName, style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text(
                [
                  presenceLabel(p.effectivePresence),
                  if (p.zone.isNotEmpty) p.zone,
                  if (p.needsVerification && !p.isCheckedOut) 'Doğrulanmadı (kendi girişi)',
                ].join(' · '),
              ),
            ),
            const Divider(height: 1),
            if (!p.isCheckedIn && !p.isAbsent)
              ListTile(
                leading: const Icon(Icons.login),
                title: const Text('Giriş yap'),
                onTap: () => Navigator.of(ctx).pop('check_in'),
              ),
            if (p.needsVerification && !p.isCheckedOut)
              ListTile(
                leading: const Icon(Icons.verified_outlined),
                title: const Text('Girişi doğrula (QR ile giriş)'),
                subtitle: const Text('Personelin kendi girişini saha sorumlusu girişiyle onaylar'),
                onTap: () => Navigator.of(ctx).pop('check_in'),
              ),
            if (p.isOnSite && !p.isOnBreak)
              ListTile(
                leading: const Icon(Icons.coffee_outlined),
                title: const Text('Mola başlat'),
                onTap: () => Navigator.of(ctx).pop('break_start'),
              ),
            if (p.isOnBreak)
              ListTile(
                leading: const Icon(Icons.play_arrow_outlined),
                title: Text('Moladan döndü (${formatElapsed(p.breakStartedAt)})'),
                onTap: () => Navigator.of(ctx).pop('break_end'),
              ),
            if (p.isOnSite && detail.isActive)
              ListTile(
                leading: const Icon(Icons.logout),
                title: const Text('Çıkış yap'),
                onTap: () => Navigator.of(ctx).pop('check_out'),
              ),
            if (!p.isCheckedIn && !p.isAbsent)
              ListTile(
                leading: Icon(Icons.person_off_outlined, color: Theme.of(ctx).colorScheme.error),
                title: const Text('Gelmedi'),
                onTap: () => Navigator.of(ctx).pop('absent'),
              ),
            if (p.isAbsent)
              ListTile(
                leading: const Icon(Icons.undo),
                title: const Text('Gelmedi işaretini geri al'),
                onTap: () => Navigator.of(ctx).pop('present'),
              ),
            if (tel != null)
              ListTile(
                leading: const Icon(Icons.call_outlined),
                title: Text('Ara · ${p.phone}'),
                onTap: () => Navigator.of(ctx).pop('call'),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (choice == null || !context.mounted) return;
    switch (choice) {
      case 'check_in':
        await checkInManual(p);
      case 'check_out':
        await checkOut(p);
      case 'break_start':
        await breakStart(p);
      case 'break_end':
        await breakEnd(p);
      case 'absent':
        await markAbsent(p, absent: true);
      case 'present':
        await markAbsent(p, absent: false);
      case 'call':
        await callPhone(p.phone);
    }
  }

  /// POST /field/days/{id}/absent {assignment_id, absent}
  Future<bool> markAbsent(PersonnelAssignment p, {required bool absent}) async {
    if (absent && p.isCheckedIn) {
      showSnack(context, '${p.displayName} giriş yapmış; gelmedi işaretlenemez.', error: true);
      return false;
    }
    if (absent) {
      final ok = await confirmDialog(
        context,
        title: 'Gelmedi',
        message: '${p.displayName} bugün gelmedi olarak işaretlensin mi?',
        confirmText: 'Gelmedi',
        destructive: true,
      );
      if (!ok || !context.mounted) return false;
    }
    return _actions.run(
      () async => (await _repo.markAbsent(detail.id, p.id, absent: absent)).message,
      success: absent ? '${p.displayName} gelmedi olarak işaretlendi' : 'İşaret kaldırıldı',
      progress: 'Kaydediliyor…',
    );
  }

  /// POST /field/days/{id}/break/start {assignment_id}
  Future<bool> breakStart(PersonnelAssignment p) {
    if (!p.isOnSite) {
      showSnack(context, '${p.displayName} sahada değil.', error: true);
      return Future.value(false);
    }
    return _actions.run(
      () async => (await _repo.breakStart(detail.id, p.id)).message,
      success: '${p.displayName} molaya çıktı',
      progress: 'Mola başlatılıyor…',
    );
  }

  /// POST /field/days/{id}/break/end {assignment_id}
  Future<bool> breakEnd(PersonnelAssignment p) => _actions.run(
        () async => (await _repo.breakEnd(detail.id, p.id)).message,
        success: '${p.displayName} moladan döndü',
        progress: 'Kaydediliyor…',
      );

  /// `tel:` bağlantısını açar.
  Future<void> callPhone(String? phone) async {
    final tel = telUriFor(phone);
    if (tel == null) return;
    try {
      final ok = await launchUrl(Uri.parse(tel));
      if (!ok && context.mounted) showSnack(context, 'Arama uygulaması açılamadı.', error: true);
    } catch (_) {
      if (context.mounted) showSnack(context, 'Arama uygulaması açılamadı.', error: true);
    }
  }

  // ======================= Masraflar =======================

  Future<void> addExpense() async {
    List<ExpenseCategory> categories;
    try {
      categories = await ref.read(expenseCategoriesProvider.future);
    } catch (_) {
      categories = ExpenseCategory.defaults;
    }
    if (!context.mounted) return;
    final draft = await showExpenseSheet(context, categories: categories);
    if (draft == null || !context.mounted) return;
    await _actions.run(
      () async {
        final result = await _repo.addExpense(
          detail.id,
          description: draft.description,
          amount: draft.amount,
          category: draft.category,
          receiptPhoto: draft.receiptPhoto,
        );
        return result.message;
      },
      success: 'Masraf kaydedildi',
      progress: 'Masraf kaydediliyor…',
    );
  }

  Future<void> deleteExpense(DayExpense expense) async {
    if (!expense.isPending) {
      showSnack(context, 'Onaylanmış/reddedilmiş masraf silinemez.', error: true);
      return;
    }
    final ok = await confirmDialog(
      context,
      title: 'Masrafı sil',
      message: '${expense.description} (${formatMoney(expense.amount)}) silinsin mi?',
      confirmText: 'Sil',
      destructive: true,
    );
    if (!ok || !context.mounted) return;
    await _actions.run(
      () => _repo.deleteExpense(detail.id, expense.id),
      success: 'Masraf silindi',
      progress: 'Siliniyor…',
    );
  }

  // ======================= Gün başlat / bitir =======================

  Future<bool> startDay(XFile? photo) => _actions.run(
        () => _repo.startDay(detail.id, photo: photo),
        success: 'Gün başlatıldı',
        progress: 'Gün başlatılıyor…',
      );

  Future<bool> endDay(XFile? photo) => _actions.run(
        () => _repo.endDay(detail.id, photo: photo),
        success: 'Gün tamamlandı',
        progress: 'Gün bitiriliyor…',
      );

  void refresh() => _actions.refresh();

  /// Zimmetli personel adı: iç içe nesne → görevlendirme id → personel id.
  String? holderName(InventoryAssignment item) {
    if (item.assignedToName != null) return item.assignedToName;
    final byAssignment = detail.assignmentById(item.assignedToAssignmentId);
    if (byAssignment != null) return byAssignment.displayName;
    if (item.assignedToPersonnelId == null) return null;
    return detail.personnel
        .where((p) => p.personnelId == item.assignedToPersonnelId)
        .firstOrNull
        ?.displayName;
  }
}

class _ResolvedInventory {
  const _ResolvedInventory({required this.payload, this.local, required this.name});

  final String payload;
  final InventoryAssignment? local;
  final String name;
}
