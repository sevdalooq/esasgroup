// Gün akışı modelleri – gerçek backend yanıtlarından (07.09.2026, gün #5)
// alınmış JSON şekilleriyle.
import 'package:esas_saha/core/utils/formatters.dart';
import 'package:esas_saha/features/field/field_providers.dart';
import 'package:esas_saha/features/field/models/models.dart';
import 'package:flutter_test/flutter_test.dart';

/// `GET /field/days/5` → `day.personnel_assignments[0]` (assigned_inventory ile)
final Map<String, dynamic> kAssignmentJson = {
  'id': 61,
  'project_day_id': 5,
  'personnel_id': 3,
  'daily_wage': '3600.00',
  'overtime_hours': '0.00',
  'overtime_rate': '0.00',
  'total_earnings': '0.00',
  'zone': 'Stüdyo Girişi',
  'check_in_time': null,
  'check_in_photo': null,
  'check_out_time': null,
  'check_out_photo': null,
  'payment_status': 'pending',
  'payment_method': null,
  'payment_amount': '0.00',
  'notes': null,
  'is_checked': false,
  'personnel': {
    'id': 3,
    'first_name': 'Ahmet',
    'last_name': 'Yılmaz',
    'phone': '0532 818 41 21',
    'photo': null,
    'photo_1': null,
    'qr_code': 'efba4be2-0361-4d63-8ca1-0f4d509369cd',
    'default_wage': '3600.00',
    'group_id': null,
    'full_name': 'Ahmet Yılmaz',
    'qr_payload': 'ESAS:PER:efba4be2-0361-4d63-8ca1-0f4d509369cd',
  },
  'assigned_inventory': [
    {
      'id': 102,
      'project_day_id': 5,
      'inventory_id': 5,
      'quantity': 1,
      'assigned_to_personnel_id': 61,
      'delivered_at': '2026-09-07T14:13:46.000000Z',
      'delivered_by': 1,
      'returned_at': null,
      'returned_by': null,
      'return_status': 'pending',
      'damage_photo': null,
      'damage_description': null,
      'status': 'delivered',
      'inventory': {
        'id': 5,
        'name': 'Telsiz Motorola DP1400',
        'serial_number': 'MOT-DP1400-005',
        'qr_payload': 'ESAS:INV:',
      },
    }
  ],
};

/// `GET /field/days/5` → `day.inventory_assignments[0]`
final Map<String, dynamic> kInventoryJson = {
  'id': 102,
  'project_day_id': 5,
  'inventory_id': 5,
  'quantity': 1,
  'assigned_to_personnel_id': 61,
  'delivered_at': '2026-09-07T14:13:46.000000Z',
  'returned_at': null,
  'return_status': 'pending',
  'damage_photo': null,
  'damage_description': null,
  'status': 'delivered',
  'inventory': {
    'id': 5,
    'name': 'Telsiz Motorola DP1400',
    'type': 'zimmet',
    'serial_number': 'MOT-DP1400-005',
    'qr_code': '42a947a8-982f-43b2-bfe9-0629f0ff5dd4',
    'nfc_uid': null,
    'current_status': 'in_use',
    'current_holder_id': 3,
    'qr_payload': 'ESAS:INV:42a947a8-982f-43b2-bfe9-0629f0ff5dd4',
  },
  'assigned_to_personnel': {
    'id': 61,
    'personnel_id': 3,
    'zone': 'Stüdyo Girişi',
    'personnel': {
      'id': 3,
      'first_name': 'Ahmet',
      'last_name': 'Yılmaz',
      'full_name': 'Ahmet Yılmaz',
      'qr_payload': 'ESAS:PER:',
    },
  },
};

/// `day.expenses[]` satırı (ProjectExpense modeli)
final Map<String, dynamic> kExpenseJson = {
  'id': 7,
  'project_day_id': 5,
  'description': 'Ekip öğle yemeği',
  'amount': '1250.50',
  'category': 'food',
  'receipt_photo': 'receipt-photos/abc.jpg',
  'status': 'pending',
  'approved_by': null,
  'approved_at': null,
  'rejection_reason': null,
  'created_by': 2,
  'created_at': '2026-09-07T12:10:00.000000Z',
};

void main() {
  group('ProjectDayDetail · expenses ve zimmet', () {
    late ProjectDayDetail detail;

    setUp(() {
      detail = ProjectDayDetail.fromJson({
        'day': {
          'id': 5,
          'project_id': 3,
          'date': '2026-09-07T00:00:00.000000Z',
          'status': 'active',
          'start_photo': 'day-photos/start/x.png',
          'end_photo': null,
          'notes': 'Ekip toplanma: 17:30 stüdyo önü.',
          'personnel_assignments': [
            kAssignmentJson,
            {
              ...kAssignmentJson,
              'id': 62,
              'personnel_id': 4,
              'check_in_time': '2026-09-07T15:00:00.000000Z',
              'is_checked': true,
              'assigned_inventory': [],
              'personnel': {
                'id': 4,
                'first_name': 'Mehmet',
                'last_name': 'Kaya',
                'full_name': 'Mehmet Kaya',
                'qr_code': 'p4',
                'qr_payload': 'ESAS:PER:p4',
              },
            },
          ],
          'inventory_assignments': [
            kInventoryJson,
            {
              'id': 103,
              'inventory_id': 6,
              'quantity': 1,
              'assigned_to_personnel_id': null,
              'delivered_at': null,
              'return_status': 'pending',
              'status': 'pending',
              'inventory': {'id': 6, 'name': 'Yelek', 'qr_code': 'inv6', 'qr_payload': 'ESAS:INV:inv6'},
              'assigned_to_personnel': null,
            },
          ],
          'project': {
            'id': 3,
            'name': 'TV100 Yılbaşı Özel Yayını',
            'customer': {'id': 6, 'name': 'TV100'},
          },
          'supervisor': {'id': 2, 'name': 'Ahmet Saha'},
          'expenses': [
            kExpenseJson,
            {...kExpenseJson, 'id': 8, 'amount': 300, 'category': 'transport', 'status': 'approved', 'receipt_photo': null},
          ],
        },
        'zones': [
          {'id': 1, 'qr_code': 'z1', 'name': 'Stüdyo Girişi', 'usage_count': 3, 'qr_payload': 'ESAS:ZONE:z1'},
        ],
        'summary': {
          'personnel_count': 2,
          'checked_in_count': 1,
          'checked_out_count': 0,
          'total_earnings': 0,
          'total_paid': 0,
          'total_pending': 0,
          'total_overtime': 0,
          'overtime_personnel_count': 0,
          'inventory_count': 2,
          'inventory_delivered': 1,
          'inventory_returned': 0,
          'inventory_damaged': 0,
          'inventory_pending_return': 1,
        },
      });
    });

    test('masraflar ayrıştırılır ve toplanır', () {
      expect(detail.expenses.length, 2);
      final e = detail.expenses.first;
      expect(e.id, 7);
      expect(e.description, 'Ekip öğle yemeği');
      expect(e.amount, 1250.5);
      expect(e.category, 'food');
      expect(e.status, 'pending');
      expect(e.isPending, isTrue);
      expect(e.receiptPhoto, 'receipt-photos/abc.jpg');
      expect(e.createdAt, isNotNull);
      expect(detail.expenses.last.isPending, isFalse);
      expect(detail.expensesTotal, 1550.5);
    });

    test('personel görevlendirmesi assigned_inventory ve ücret alanlarını okur', () {
      final p = detail.personnel.first;
      expect(p.id, 61);
      expect(p.displayName, 'Ahmet Yılmaz');
      expect(p.qrPayload, 'ESAS:PER:efba4be2-0361-4d63-8ca1-0f4d509369cd');
      expect(p.dailyWage, 3600);
      expect(p.overtimeRate, 0);
      expect(p.suggestedOvertimeRate, 450); // 3600 / 8
      expect(p.paymentMethod, isNull);
      expect(p.isCheckedIn, isFalse);
      expect(p.isOnSite, isFalse);
      expect(p.assignedInventory.single.displayName, 'Telsiz Motorola DP1400');
      expect(p.assignedInventory.single.isDelivered, isTrue);

      final q = detail.personnel.last;
      expect(q.isCheckedIn, isTrue);
      expect(q.isOnSite, isTrue);
    });

    test('listeler: giriş yapmayan, sahada, teslim edilmemiş, iade edilmemiş', () {
      expect(detail.notCheckedIn.map((p) => p.id), [61]);
      expect(detail.onSite.map((p) => p.id), [62]);
      expect(detail.undeliveredInventory.map((i) => i.id), [103]);
      expect(detail.unreturnedInventory.map((i) => i.id), [102]);
    });

    test('inventoryHeldBy görevlendirme id ile eşler; yoksa assigned_inventory', () {
      final ahmet = detail.personnel.first;
      final held = detail.inventoryHeldBy(ahmet);
      expect(held.single.id, 102);
      expect(held.single.qrPayload, 'ESAS:INV:42a947a8-982f-43b2-bfe9-0629f0ff5dd4');

      // Gün listesinde eşleşme yoksa görevlendirmenin kopyasına düşer.
      final orphan = PersonnelAssignment.fromJson({...kAssignmentJson, 'id': 99});
      final fallbackDetail = ProjectDayDetail(id: 5, personnel: [orphan]);
      expect(fallbackDetail.inventoryHeldBy(orphan).single.inventoryId, 5);
      expect(fallbackDetail.inventoryHeldBy(orphan).single.qrPayload, isNull);

      expect(detail.inventoryHeldBy(detail.personnel.last), isEmpty);
    });

    test('expenses yoksa boş liste', () {
      final d = ProjectDayDetail.fromJson({'day': {'id': 1, 'status': 'pending'}});
      expect(d.expenses, isEmpty);
      expect(d.expensesTotal, 0);
    });
  });

  group('Aşama belirleme', () {
    ProjectDayDetail withStatus(String s) => ProjectDayDetail(id: 1, status: s);

    test('status → aşama', () {
      expect(phaseOf(withStatus('pending'), const DayFlowState()), DayPhase.start);
      expect(phaseOf(withStatus(''), const DayFlowState()), DayPhase.start);
      expect(phaseOf(withStatus('active'), const DayFlowState()), DayPhase.active);
      expect(
        phaseOf(withStatus('active'), const DayFlowState(closingStarted: true)),
        DayPhase.end,
      );
      expect(
        phaseOf(withStatus('completed'), const DayFlowState(closingStarted: true)),
        DayPhase.completed,
      );
      expect(phaseOf(withStatus('cancelled'), const DayFlowState()), DayPhase.readOnly);
    });

    test('Bugün kartı etiketi', () {
      expect(dayPhaseLabel('pending'), 'Başlamadı');
      expect(dayPhaseLabel('active'), 'Devam ediyor');
      expect(dayPhaseLabel('completed'), 'Tamamlandı');
    });

    test('DayFlowState copyWith', () {
      const s = DayFlowState();
      expect(s.copyWith(startStep: 2).startStep, 2);
      expect(s.copyWith(closingStarted: true).closingStarted, isTrue);
      expect(s.copyWith(closingStarted: true).startStep, 0);
    });
  });

  group('CheckOutRequest', () {
    test('toJson sunucu sözleşmesine uyar (mesai + kısmi ödeme + iadeler)', () {
      final req = CheckOutRequest(
        assignmentId: 61,
        checkOutTime: DateTime.utc(2026, 9, 7, 18, 30),
        overtimeHours: 2,
        overtimeRate: 450,
        paymentStatus: 'partial',
        paymentMethod: 'cash',
        paymentAmount: 1000,
        inventoryReturns: const [
          InventoryReturnEntry(id: 102, returnStatus: 'returned'),
          InventoryReturnEntry(
            id: 104,
            returnStatus: 'damaged',
            damageDescription: 'Anten kırık',
            deductionAmount: 250,
          ),
        ],
      );
      final json = req.toJson();
      expect(json['assignment_id'], 61);
      expect(json['check_out_time'], '2026-09-07T18:30:00.000Z');
      expect(json['overtime_hours'], 2);
      expect(json['overtime_rate'], 450);
      expect(json['payment_status'], 'partial');
      expect(json['payment_method'], 'cash');
      expect(json['payment_amount'], 1000);
      final returns = json['inventory_returns'] as List;
      expect(returns.length, 2);
      expect(returns.first, {'id': 102, 'return_status': 'returned'});
      expect(returns.last, {
        'id': 104,
        'return_status': 'damaged',
        'damage_description': 'Anten kırık',
        'deduction_amount': 250,
      });

      expect(req.overtimeTotal, 900);
      expect(req.totalEarnings(3600), 4500);
      expect(req.deductionTotal, 250);
      expect(req.effectivePaymentAmount(3600), 1000);
      expect(req.remaining(3600), 3500);
    });

    test('pending ödemede yöntem/tutar gönderilmez; paid tutarsızsa hakediş', () {
      const pending = CheckOutRequest(assignmentId: 1);
      final json = pending.toJson();
      expect(json.containsKey('payment_method'), isFalse);
      expect(json.containsKey('payment_amount'), isFalse);
      expect(json.containsKey('inventory_returns'), isFalse);
      expect(json.containsKey('check_out_time'), isFalse);
      expect(pending.remaining(3600), 3600);

      const paid = CheckOutRequest(assignmentId: 1, paymentStatus: 'paid');
      expect(paid.effectivePaymentAmount(3600), 3600);
      expect(paid.remaining(3600), 0);
    });

    test('yerel saat UTC ISO olarak gönderilir', () {
      final local = DateTime(2026, 9, 7, 18, 30);
      final json = CheckOutRequest(assignmentId: 1, checkOutTime: local).toJson();
      expect(json['check_out_time'], local.toUtc().toIso8601String());
      expect((json['check_out_time'] as String).endsWith('Z'), isTrue);
    });
  });

  group('Yanıt modelleri', () {
    test('CheckOutResult {message, assignment, summary}', () {
      final result = CheckOutResult.fromJson({
        'message': 'Ahmet Yılmaz çıkış yaptı.',
        'assignment': {
          ...kAssignmentJson,
          'check_in_time': '2026-09-07T15:00:00.000000Z',
          'check_out_time': '2026-09-07T18:30:00.000000Z',
          'overtime_hours': '2.00',
          'overtime_rate': '450.00',
          'total_earnings': '4500.00',
          'payment_status': 'partial',
          'payment_method': 'cash',
          'payment_amount': '1000.00',
          'assigned_inventory': [
            {...(kAssignmentJson['assigned_inventory'] as List).first as Map, 'return_status': 'returned', 'status': 'returned', 'returned_at': '2026-09-07T18:30:00.000000Z'},
          ],
        },
        'summary': {
          'personnel_count': 12,
          'checked_in_count': 1,
          'checked_out_count': 1,
          'total_earnings': 4500,
          'total_paid': 1000,
          'total_pending': 3500,
          'total_overtime': 900,
          'overtime_personnel_count': 1,
          'inventory_count': 14,
          'inventory_delivered': 1,
          'inventory_returned': 1,
          'inventory_damaged': 0,
          'inventory_pending_return': 0,
        },
      });
      expect(result.message, 'Ahmet Yılmaz çıkış yaptı.');
      final a = result.assignment!;
      expect(a.isCheckedOut, isTrue);
      expect(a.overtimeHours, 2);
      expect(a.overtimeRate, 450);
      expect(a.overtimeTotal, 900);
      expect(a.totalEarnings, 4500);
      expect(a.paymentMethod, 'cash');
      expect(a.paymentAmount, 1000);
      expect(a.remainingPayment, 3500);
      expect(a.assignedInventory.single.isReturned, isTrue);
      expect(result.summary!.totalPending, 3500);
      expect(result.summary!.totalOvertime, 900);
    });

    test('ExpenseResult 201 {message, expense, expenses}', () {
      final result = ExpenseResult.fromJson({
        'message': 'Masraf kaydedildi, onay bekliyor.',
        'expense': kExpenseJson,
        'expenses': [kExpenseJson, {...kExpenseJson, 'id': 6, 'amount': '80.00', 'category': 'other'}],
      });
      expect(result.message, 'Masraf kaydedildi, onay bekliyor.');
      expect(result.expense!.id, 7);
      expect(result.expenses.length, 2);
      expect(result.expenses.last.amount, 80);
    });

    test('ExpenseCategory /expense-categories/all', () {
      final list = [
        {'id': 1, 'name': 'Yemek', 'slug': 'food', 'icon': 'tabler-tools-kitchen-2', 'color': 'warning', 'is_active': true, 'sort_order': 1},
        {'id': 5, 'name': 'Diğer', 'slug': 'other', 'icon': 'tabler-dots', 'color': 'default'},
      ].map(ExpenseCategory.fromJson).toList();
      expect(list.first.slug, 'food');
      expect(list.first.name, 'Yemek');
      expect(list.first.color, 'warning');
      expect(ExpenseCategory.nameOf(list, 'food'), 'Yemek');
      expect(ExpenseCategory.nameOf(list, 'transport'), 'Ulaşım'); // varsayılan listeden
      expect(ExpenseCategory.nameOf(list, 'unknown'), 'unknown');
      expect(ExpenseCategory.defaults.map((c) => c.slug), ['food', 'transport', 'material', 'accommodation', 'other']);
    });

    test('DayExpense kategori nesne olarak gelirse slug okunur', () {
      final e = DayExpense.fromJson({
        'id': 1,
        'description': 'x',
        'amount': 10,
        'category': {'id': 2, 'slug': 'transport', 'name': 'Ulaşım'},
      });
      expect(e.category, 'transport');
    });
  });

  group('Biçimleyiciler', () {
    test('parseDecimal / formatInputNumber / formatHours', () {
      expect(parseDecimal('1.250,50'), 1250.5);
      expect(parseDecimal('1250.5'), 1250.5);
      expect(parseDecimal('450,00'), 450);
      expect(parseDecimal('₺ 3 600'), 3600);
      expect(parseDecimal(''), isNull);
      expect(parseDecimal('abc'), isNull);
      expect(formatInputNumber(3600), '3600');
      expect(formatInputNumber(450.5), '450,5');
      expect(formatHours(2), '2 sa');
      expect(formatHours(1.5), '1,5 sa');
    });

    test('etiketler', () {
      expect(paymentMethodLabel('cash'), 'Nakit');
      expect(paymentMethodLabel('bank'), 'Banka');
      expect(paymentMethodLabel('mixed'), 'Karışık');
      expect(paymentMethodLabel(null), '-');
      expect(expenseStatusLabel('pending'), 'Onay bekliyor');
      expect(expenseStatusLabel('approved'), 'Onaylandı');
      expect(expenseStatusLabel('rejected'), 'Reddedildi');
    });
  });
}
