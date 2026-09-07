// Personel modu modelleri – gerçek `GET /me/assignments` yanıtından (07.09.2026).
import 'package:esas_saha/core/utils/formatters.dart';
import 'package:esas_saha/features/self/models/self_models.dart';
import 'package:flutter_test/flutter_test.dart';

final Map<String, dynamic> kMeAssignments = {
  'personnel': {
    'id': 3,
    'first_name': 'Ahmet',
    'last_name': 'Yılmaz',
    'full_name': 'Ahmet Yılmaz',
    'photo': null,
    'qr_payload': 'ESAS:PER:efba4be2-0361-4d63-8ca1-0f4d509369cd',
  },
  'assignments': [
    {
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
      'presence': 'assigned',
      'break_started_at': null,
      'break_minutes': 0,
      'created_at': '2026-09-07T13:49:27.000000Z',
      'updated_at': '2026-09-07T18:37:05.000000Z',
      'project_day': {
        'id': 5,
        'project_id': 3,
        'date': '2026-09-07T00:00:00.000000Z',
        'status': 'pending',
        'supervisor_id': 2,
        'notes': 'Ekip toplanma: 17:30 stüdyo önü.',
        'venue_lat': '41.0868000',
        'venue_lng': '28.9737000',
        'project': {
          'id': 3,
          'name': 'TV100 Yılbaşı Özel Yayını',
          'customer_id': 6,
          'venue_address': 'TV100 Stüdyoları, Kağıthane / İstanbul',
          'venue_lat': '41.0868000',
          'venue_lng': '28.9737000',
          'customer': {'id': 6, 'name': 'TV100'},
        },
        'supervisor': {'id': 2, 'name': 'Ahmet Saha', 'phone': '0532 411 20 30'},
      },
    },
  ],
  'today': '2026-09-07',
};

void main() {
  group('SelfAssignments', () {
    late SelfAssignments data;
    setUp(() => data = SelfAssignments.fromJson(kMeAssignments));

    test('personel ve bugünkü görev ayrıştırılır', () {
      expect(data.personnel.id, 3);
      expect(data.personnel.fullName, 'Ahmet Yılmaz');
      expect(data.personnel.qrPayload, 'ESAS:PER:efba4be2-0361-4d63-8ca1-0f4d509369cd');
      expect(data.today, DateTime(2026, 9, 7));

      final a = data.todays!;
      expect(a.id, 61);
      expect(a.projectDayId, 5);
      expect(a.zone, 'Stüdyo Girişi');
      expect(a.presence, 'assigned');
      expect(a.effectivePresence, 'assigned');
      expect(a.isCheckedIn, isFalse);
      expect(a.canAct, isTrue);
      expect(a.dailyWage, 3600);
      expect(a.breakMinutes, 0);

      final day = a.day!;
      expect(day.date, DateTime(2026, 9, 7));
      expect(day.status, 'pending');
      expect(day.projectName, 'TV100 Yılbaşı Özel Yayını');
      expect(day.customerName, 'TV100');
      expect(day.venueAddress, 'TV100 Stüdyoları, Kağıthane / İstanbul');
      expect(day.venueLat, 41.0868);
      expect(day.supervisorName, 'Ahmet Saha');
      expect(day.supervisorPhone, '0532 411 20 30');
      expect(day.notes, 'Ekip toplanma: 17:30 stüdyo önü.');
      expect(data.upcoming, isEmpty);
    });

    test('yaklaşan / geçmiş görevler tarihe göre ayrılır', () {
      final base = (kMeAssignments['assignments'] as List).first as Map<String, dynamic>;
      Map<String, dynamic> withDate(int id, String date, [String status = 'pending']) => {
            ...base,
            'id': id,
            'project_day_id': id,
            'project_day': {...(base['project_day'] as Map), 'id': id, 'date': date, 'status': status},
          };
      final d = SelfAssignments.fromJson({
        ...kMeAssignments,
        'assignments': [
          withDate(70, '2026-09-10T00:00:00.000000Z'),
          withDate(60, '2026-09-06T00:00:00.000000Z', 'completed'),
          base,
          withDate(65, '2026-09-08T00:00:00.000000Z'),
        ],
      });
      expect(d.todays!.id, 61);
      expect(d.upcoming.map((a) => a.id), [65, 70]);
      expect(d.past.map((a) => a.id), [60]);
    });

    test('durum türetme: mola, çıkış, gelmedi, doğrulanmamış giriş', () {
      final base = (kMeAssignments['assignments'] as List).first as Map<String, dynamic>;
      final onBreak = SelfAssignment.fromJson({
        ...base,
        'check_in_time': '2026-09-07T15:00:00.000000Z',
        'presence': 'on_break',
        'break_started_at': '2026-09-07T16:00:00.000000Z',
      });
      expect(onBreak.isOnBreak, isTrue);
      expect(onBreak.isOnSite, isTrue);
      expect(onBreak.needsVerification, isTrue);
      expect(onBreak.breakStartedAt, isNotNull);

      final out = SelfAssignment.fromJson({
        ...base,
        'check_in_time': '2026-09-07T15:00:00.000000Z',
        'check_out_time': '2026-09-07T20:00:00.000000Z',
        'presence': 'checked_out',
        'is_checked': true,
      });
      expect(out.isCheckedOut, isTrue);
      expect(out.canAct, isFalse);
      expect(out.needsVerification, isFalse);

      expect(SelfAssignment.fromJson({...base, 'presence': 'absent'}).isAbsent, isTrue);
      expect(
        SelfAssignment.fromJson({...base, 'presence': '', 'check_in_time': '2026-09-07 15:00:00'})
            .effectivePresence,
        'checked_in',
      );
      final closed = SelfAssignment.fromJson({
        ...base,
        'project_day': {...(base['project_day'] as Map), 'status': 'completed'},
      });
      expect(closed.canAct, isFalse);
    });
  });

  group('İstek gövdeleri', () {
    test('SelfCheckInRequest → POST /me/check-in', () {
      const req = SelfCheckInRequest(
        zonePayload: 'ESAS:ZONE:z1',
        projectDayId: 5,
        lat: 41.0868,
        lng: 28.9737,
      );
      expect(req.toJson(), {
        'zone_payload': 'ESAS:ZONE:z1',
        'project_day_id': 5,
        'lat': 41.0868,
        'lng': 28.9737,
      });
      expect(const SelfCheckInRequest(zone: 'Kulis').toJson(), {'zone': 'Kulis'});
    });

    test('LocationReport → POST /field/location (accuracy tam sayı)', () {
      final report = LocationReport(
        lat: 41.0868,
        lng: 28.9737,
        accuracy: 12.6,
        projectDayId: 5,
        recordedAt: DateTime.utc(2026, 9, 7, 19, 10),
      );
      expect(report.toJson(), {
        'lat': 41.0868,
        'lng': 28.9737,
        'accuracy': 13,
        'project_day_id': 5,
        'recorded_at': '2026-09-07T19:10:00.000Z',
      });
      expect(const LocationReport(lat: 1, lng: 2).toJson(), {'lat': 1, 'lng': 2});
    });

    test('SelfActionResult {message, assignment}', () {
      final r = SelfActionResult.fromJson({
        'message': 'Girişiniz alındı – Stüdyo Girişi. Saha sorumlusu doğrulayacak.',
        'assignment': {
          ...(kMeAssignments['assignments'] as List).first as Map,
          'check_in_time': '2026-09-07T19:05:00.000000Z',
          'presence': 'checked_in',
        },
      });
      expect(r.message, startsWith('Girişiniz alındı'));
      expect(r.assignment!.isCheckedIn, isTrue);
      expect(r.assignment!.needsVerification, isTrue);
    });
  });

  group('Biçimleyiciler (personel / durum)', () {
    test('presence etiket ve renk', () {
      expect(presenceLabel('assigned'), 'Bekleniyor');
      expect(presenceLabel('checked_in'), 'Sahada');
      expect(presenceLabel('on_break'), 'Molada');
      expect(presenceLabel('checked_out'), 'Çıkış yaptı');
      expect(presenceLabel('absent'), 'Gelmedi');
      expect(presenceColor('absent'), isNot(presenceColor('checked_in')));
    });

    test('formatElapsed / telUriFor', () {
      final now = DateTime(2026, 9, 7, 12, 0);
      expect(formatElapsed(DateTime(2026, 9, 7, 11, 48), now: now), '12 dk');
      expect(formatElapsed(DateTime(2026, 9, 7, 10, 55), now: now), '1 sa 05 dk');
      expect(formatElapsed(null), '');
      expect(telUriFor('0532 411 20 30'), 'tel:05324112030');
      expect(telUriFor('+90 (532) 411-20-30'), 'tel:+905324112030');
      expect(telUriFor(null), isNull);
      expect(telUriFor('  '), isNull);
    });
  });
}
