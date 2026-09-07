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

    test('ProjectDayDetail tam yanıt', () {
      final detail = ProjectDayDetail.fromJson({
        'id': 1,
        'date': '2026-09-07',
        'status': 'active',
        'project': {
          'id': 3,
          'name': 'Konser',
          'customer': {'name': 'Müşteri A.Ş.'},
        },
        'personnel': [
          {
            'id': 10,
            'personnel': {'id': 7, 'full_name': 'Ayşe Yılmaz', 'qr_payload': 'ESAS:PER:x'},
            'zone': 'Ana giriş',
            'check_in_time': '2026-09-07 08:30:00',
            'is_checked': 1,
          }
        ],
        'inventory': [
          {
            'id': 20,
            'inventory': {'id': 4, 'name': 'Telsiz', 'serial_number': 'SN1'},
            'quantity': 2,
            'status': 'delivered',
            'return_status': null,
          }
        ],
        'zones': [
          {'id': 1, 'name': 'Ana giriş', 'qr_payload': 'ESAS:ZONE:z'}
        ],
      });
      expect(detail.projectName, 'Konser');
      expect(detail.customerName, 'Müşteri A.Ş.');
      expect(detail.personnel.single.isCheckedIn, isTrue);
      expect(detail.inventory.single.isDelivered, isTrue);
      expect(detail.inventory.single.isReturned, isFalse);
      expect(detail.zones.single.qrPayload, 'ESAS:ZONE:z');
      expect(detail.checkedInCount, 1);
    });

    test('json yardımcıları', () {
      expect(asInt('12'), 12);
      expect(asInt(null, 3), 3);
      expect(asBool('1'), isTrue);
      expect(asBool('false'), isFalse);
      expect(asMapList({'data': [{'a': 1}]}).length, 1);
      expect(asDateOnly('2026-09-07')?.day, 7);
    });
  });
}
