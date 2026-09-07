import 'package:esas_saha/core/config/app_config.dart';
import 'package:esas_saha/core/utils/json.dart';
import 'package:esas_saha/features/field/models/models.dart';
import 'package:esas_saha/features/scanner/qr_payload.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppConfig.normalizeBaseUrl', () {
    test('şema ve /api ekler, sondaki / temizler', () {
      expect(
        AppConfig.normalizeBaseUrl('192.168.1.10:8000/'),
        'http://192.168.1.10:8000/api',
      );
      expect(
        AppConfig.normalizeBaseUrl('https://saha.esasgroup.com.tr/api/'),
        'https://saha.esasgroup.com.tr/api',
      );
      expect(AppConfig.normalizeBaseUrl('   '), AppConfig.defaultBaseUrl);
    });
  });

  group('QrPayload', () {
    test('tipleri ayırt eder', () {
      expect(QrPayload.typeOf('ESAS:PER:abc'), QrType.personnel);
      expect(QrPayload.typeOf('esas:inv:abc'), QrType.inventory);
      expect(QrPayload.typeOf('ESAS:ZONE:abc'), QrType.zone);
      expect(QrPayload.typeOf('hello'), QrType.unknown);
    });
  });

  group('Modeller toleranslı ayrıştırır', () {
    test('ProjectDaySummary eksik alanlarla', () {
      final day = ProjectDaySummary.fromJson({'id': '5', 'project': null});
      expect(day.id, 5);
      expect(day.projectName, '');
      expect(day.counts.personnelTotal, 0);
      expect(day.status, '');
    });

    test('ProjectDaySummary gerçek /field/today şekli (düz sayaçlar)', () {
      final day = ProjectDaySummary.fromJson({
        'id': 5,
        'project_id': 3,
        'date': '2026-09-07T00:00:00.000000Z',
        'status': 'pending',
        'personnel_total': 12,
        'personnel_checked_in': 3,
        'inventory_total': 14,
        'inventory_delivered': 2,
        'inventory_returned': 1,
        'project': {'id': 3, 'name': 'TV100 Yayını', 'customer': {'id': 6, 'name': 'TV100'}},
      });
      expect(day.date, DateTime(2026, 9, 7));
      expect(day.counts.personnelTotal, 12);
      expect(day.counts.personnelCheckedIn, 3);
      expect(day.counts.inventoryTotal, 14);
      expect(day.customerName, 'TV100');
    });

    test('ProjectDayDetail gerçek {day, zones, summary} şekli', () {
      final detail = ProjectDayDetail.fromJson({
        'day': {
          'id': 5,
          'date': '2026-09-07T00:00:00.000000Z',
          'status': 'active',
          'notes': 'Toplanma 17:30',
          'project': {
            'id': 3,
            'name': 'Konser',
            'customer': {'id': 6, 'name': 'Müşteri A.Ş.'},
          },
          'supervisor': {'id': 2, 'name': 'Ahmet Saha'},
          'personnel_assignments': [
            {
              'id': 61,
              'personnel_id': 3,
              'daily_wage': '3600.00',
              'total_earnings': '3600.00',
              'zone': 'Ana giriş',
              'check_in_time': '2026-09-07 08:30:00',
              'is_checked': true,
              'payment_status': 'pending',
              'personnel': {
                'id': 3,
                'first_name': 'Ayşe',
                'last_name': 'Yılmaz',
                'full_name': 'Ayşe Yılmaz',
                'qr_code': 'abc',
                'qr_payload': 'ESAS:PER:abc',
              },
            }
          ],
          'inventory_assignments': [
            {
              'id': 102,
              'inventory_id': 5,
              'quantity': 2,
              'assigned_to_personnel_id': 61,
              'status': 'delivered',
              'return_status': 'pending',
              'inventory': {'id': 5, 'name': 'Telsiz', 'serial_number': 'SN1', 'qr_code': 'inv1', 'qr_payload': 'ESAS:INV:'},
              'assigned_to_personnel': {
                'id': 61,
                'personnel_id': 3,
                'personnel': {'id': 3, 'full_name': 'Ayşe Yılmaz'},
              },
            },
            {
              'id': 103,
              'inventory_id': 6,
              'status': 'damaged',
              'return_status': 'damaged',
              'inventory': {'id': 6, 'name': 'Yelek'},
            }
          ],
        },
        'zones': [
          {'id': 1, 'name': 'Ana giriş', 'qr_code': 'z1', 'qr_payload': 'ESAS:ZONE:z1'}
        ],
        'summary': {'personnel_count': 1, 'checked_in_count': 1, 'inventory_count': 2, 'inventory_delivered': 1, 'total_earnings': '3600.00'},
      });
      expect(detail.projectName, 'Konser');
      expect(detail.customerName, 'Müşteri A.Ş.');
      expect(detail.supervisorName, 'Ahmet Saha');
      expect(detail.notes, 'Toplanma 17:30');
      expect(detail.personnel.single.isCheckedIn, isTrue);
      expect(detail.personnel.single.dailyWage, 3600);
      final telsiz = detail.inventory.first;
      expect(telsiz.isDelivered, isTrue);
      expect(telsiz.isReturned, isFalse);
      expect(telsiz.qrPayload, 'ESAS:INV:inv1');
      expect(telsiz.assignedToAssignmentId, 61);
      expect(telsiz.assignedToPersonnelId, 3);
      expect(telsiz.assignedToName, 'Ayşe Yılmaz');
      final yelek = detail.inventory.last;
      expect(yelek.isReturned, isTrue);
      expect(yelek.isDamaged, isTrue);
      expect(detail.zones.single.qrPayload, 'ESAS:ZONE:z1');
      expect(detail.summary.totalEarnings, 3600);
      expect(detail.summary.inventoryCount, 2);
      expect(detail.assignmentById(61)?.displayName, 'Ayşe Yılmaz');
    });

    test('json yardımcıları', () {
      expect(asInt('12'), 12);
      expect(asInt(null, 3), 3);
      expect(asBool('1'), isTrue);
      expect(asBool('false'), isFalse);
      expect(asMapList({'data': [{'a': 1}]}).length, 1);
      expect(asDateOnly('2026-09-07')?.day, 7);
      expect(asDateOnly('2026-09-07T00:00:00.000000Z'), DateTime(2026, 9, 7));
      expect(asDouble('3600.00'), 3600.0);
      expect(asDouble(null, 1.5), 1.5);
    });
  });
}
