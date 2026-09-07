// Pusher protokolü istemcisi – sahte soketle: bağlantı, ping/pong, özel kanal
// yetkilendirmesi, olay dağıtımı, yeniden bağlanma; olay modelleri.
import 'dart:async';
import 'dart:convert';

import 'package:esas_saha/core/config/app_config.dart';
import 'package:esas_saha/core/realtime/pusher_client.dart';
import 'package:esas_saha/core/realtime/realtime_events.dart';
import 'package:flutter_test/flutter_test.dart';

class FakeSocket implements PusherSocket {
  final _incoming = StreamController<dynamic>();
  final sent = <Map<String, dynamic>>[];
  bool closed = false;

  @override
  Stream<dynamic> get stream => _incoming.stream;

  @override
  void send(String text) => sent.add(jsonDecode(text) as Map<String, dynamic>);

  @override
  Future<void> close() async {
    closed = true;
    if (!_incoming.isClosed) await _incoming.close();
  }

  void push(Map<String, dynamic> message) => _incoming.add(jsonEncode(message));

  /// Sunucu tarafı bağlantıyı düşürdü.
  Future<void> drop() => _incoming.close();

  void establish([String socketId = '1.1']) => push({
        'event': 'pusher:connection_established',
        'data': jsonEncode({'socket_id': socketId, 'activity_timeout': 30}),
      });
}

Future<void> pump() => Future<void>.delayed(Duration.zero);

void main() {
  group('PusherClient', () {
    late List<FakeSocket> sockets;
    late List<(String, String)> authCalls;
    late PusherClient client;

    setUp(() {
      sockets = [];
      authCalls = [];
      client = PusherClient(
        endpoint: () => Uri.parse('ws://localhost:8081/app/key?protocol=7'),
        authorizer: (socketId, channel) async {
          authCalls.add((socketId, channel));
          return 'key:signature-for-$channel';
        },
        connector: (_) async {
          final s = FakeSocket();
          sockets.add(s);
          return s;
        },
        backoff: (_) => Duration.zero,
      );
    });

    tearDown(() => client.dispose());

    test('connection_established → socket_id ve connected durumu', () async {
      final states = <PusherConnectionState>[];
      client.stateStream.listen(states.add);
      client.connect();
      await pump();
      expect(client.state, PusherConnectionState.connecting);
      sockets.single.establish('123.456');
      await pump();
      expect(client.isConnected, isTrue);
      expect(client.socketId, '123.456');
      expect(states, [PusherConnectionState.connecting, PusherConnectionState.connected]);
    });

    test('pusher:ping → pusher:pong', () async {
      client.connect();
      await pump();
      sockets.single.establish();
      await pump();
      sockets.single.push({'event': 'pusher:ping', 'data': {}});
      await pump();
      expect(sockets.single.sent.last['event'], 'pusher:pong');
    });

    test('özel kanal: authorizer çağrılır, pusher:subscribe auth ile gönderilir', () async {
      client.subscribe('private-live'); // bağlanmadan önce de kuyruklanır
      client.connect();
      await pump();
      sockets.single.establish('9.9');
      await pump();
      await pump();
      expect(authCalls, [('9.9', 'private-live')]);
      final sub = sockets.single.sent.singleWhere((m) => m['event'] == 'pusher:subscribe');
      expect(sub['data'], {'channel': 'private-live', 'auth': 'key:signature-for-private-live'});

      sockets.single.push({
        'event': 'pusher_internal:subscription_succeeded',
        'channel': 'private-live',
        'data': '{}',
      });
      await pump();
      expect(client.subscribedChannels, {'private-live'});
    });

    test('olaylar kanal/olay/veri ile dağıtılır; data JSON string çözülür', () async {
      final received = <PusherEvent>[];
      client.channel('private-day.5').listen(received.add);
      client.subscribe('private-day.5');
      client.connect();
      await pump();
      sockets.single.establish();
      await pump();
      sockets.single.push({
        'event': 'day.updated',
        'channel': 'private-day.5',
        'data': jsonEncode({
          'project_day_id': 5,
          'project_id': 3,
          'type': 'absent',
          'payload': {'assignment_id': 62, 'personnel_id': 4, 'name': 'Mustafa Demir', 'presence': 'absent'},
          'actor_id': 2,
          'at': '2026-09-07T19:00:00+00:00',
        }),
      });
      await pump();
      expect(received.single.event, 'day.updated');
      expect(received.single.dataMap['type'], 'absent');
      final update = DayUpdatedEvent.fromPusher(received.single)!;
      expect(update.projectDayId, 5);
      expect(update.assignmentId, 62);
      expect(update.actorId, 2);
      expect(update.toastMessage, 'Mustafa Demir gelmedi olarak işaretlendi');
    });

    test('bağlantı kopunca geri çekilmeyle yeniden bağlanır ve kanallara yeniden abone olur',
        () async {
      final states = <PusherConnectionState>[];
      client.stateStream.listen(states.add);
      client.subscribe('private-live');
      client.connect();
      await pump();
      sockets.first.establish('1.1');
      await pump();
      await pump();
      expect(client.isConnected, isTrue);

      await sockets.first.drop();
      await pump();
      await pump();
      await pump();
      // Kopunca disconnected, ardından (sıfır geri çekilmeyle) yeniden connecting.
      expect(states, [
        PusherConnectionState.connecting,
        PusherConnectionState.connected,
        PusherConnectionState.disconnected,
        PusherConnectionState.connecting,
      ]);
      expect(sockets.length, 2);
      sockets.last.establish('2.2');
      await pump();
      await pump();
      expect(client.isConnected, isTrue);
      expect(authCalls.map((c) => c.$1), ['1.1', '2.2']);
      expect(sockets.last.sent.where((m) => m['event'] == 'pusher:subscribe').length, 1);
    });

    test('disconnect → yeniden denemez, unsubscribe gönderilir', () async {
      client.subscribe('private-live');
      client.connect();
      await pump();
      sockets.single.establish();
      await pump();
      await pump();
      client.unsubscribe('private-live');
      expect(sockets.single.sent.last['event'], 'pusher:unsubscribe');
      await client.disconnect();
      await pump();
      await pump();
      expect(sockets.single.closed, isTrue);
      expect(sockets.length, 1);
      expect(client.state, PusherConnectionState.disconnected);
    });

    test('yetkilendirme hatasında subscribe gönderilmez, bağlantı sürer', () async {
      final failing = PusherClient(
        endpoint: () => Uri.parse('ws://x/app/k'),
        authorizer: (_, __) async => throw StateError('403'),
        connector: (_) async {
          final s = FakeSocket();
          sockets.add(s);
          return s;
        },
        backoff: (_) => Duration.zero,
      );
      failing.subscribe('private-live');
      failing.connect();
      await pump();
      sockets.single.establish();
      await pump();
      await pump();
      expect(failing.isConnected, isTrue);
      expect(sockets.single.sent.where((m) => m['event'] == 'pusher:subscribe'), isEmpty);
      await failing.dispose();
    });
  });

  group('Olay modelleri', () {
    test('DayUpdatedEvent day_status / personnel', () {
      final e = DayUpdatedEvent.fromJson({
        'project_day_id': '5',
        'project_id': 3,
        'type': 'day_status',
        'payload': {'status': 'active', 'date': '2026-09-07'},
        'actor_id': null,
        'at': '2026-09-07T19:00:00+00:00',
      });
      expect(e.projectDayId, 5);
      expect(e.isPersonnelEvent, isFalse);
      expect(e.toastMessage, 'Gün başlatıldı');
      expect(e.at, isNotNull);

      final c = DayUpdatedEvent.fromJson({
        'project_day_id': 5,
        'type': 'check_in',
        'payload': {'assignment_id': 61, 'personnel_id': 3, 'name': 'Ahmet Yılmaz', 'presence': 'checked_in', 'check_in_time': '2026-09-07T19:05:00+00:00'},
        'actor_id': 6,
      });
      expect(c.isPersonnelEvent, isTrue);
      expect(c.personnelId, 3);
      expect(c.toastMessage, 'Ahmet Yılmaz giriş yaptı');
      expect(DayUpdatedEvent.fromJson({'type': 'inventory', 'payload': {}}).toastMessage, isNull);
      expect(DayUpdatedEvent.fromJson({'type': 'absent_cleared', 'payload': {'name': 'X'}}).toastMessage,
          'X için gelmedi işareti kaldırıldı');
    });

    test('PersonnelLocationEvent', () {
      final e = PersonnelLocationEvent.fromPusher(const PusherEvent(
        event: 'personnel.location',
        channel: 'private-live',
        data: {
          'personnel_id': 3,
          'project_day_id': 5,
          'name': 'Ahmet Yılmaz',
          'photo': null,
          'lat': 41.0868,
          'lng': 28.9737,
          'accuracy': 12,
          'recorded_at': '2026-09-07T19:10:00+00:00',
        },
      ))!;
      expect(e.personnelId, 3);
      expect(e.lat, 41.0868);
      expect(e.accuracy, 12);
      expect(e.recordedAt, isNotNull);
      expect(PersonnelLocationEvent.fromPusher(const PusherEvent(event: 'day.updated')), isNull);
    });
  });

  group('AppConfig websocket adresi', () {
    test('reverbUri Pusher v7 sorgusu', () {
      final uri = AppConfig.reverbUri('ws://localhost:8081', 'abc');
      expect(uri.scheme, 'ws');
      expect(uri.host, 'localhost');
      expect(uri.port, 8081);
      expect(uri.path, '/app/abc');
      expect(uri.queryParameters['protocol'], '7');
      expect(uri.queryParameters['client'], 'flutter');
      expect(uri.queryParameters['version'], '1');
    });

    test('normalizeWsUrl şema/port tamamlar', () {
      expect(AppConfig.normalizeWsUrl('192.168.1.10'), 'ws://192.168.1.10:8081');
      expect(AppConfig.normalizeWsUrl('http://192.168.1.10:9000/'), 'ws://192.168.1.10:9000');
      expect(AppConfig.normalizeWsUrl('https://saha.esasgroup.com.tr'), 'wss://saha.esasgroup.com.tr:8081');
      expect(AppConfig.normalizeWsUrl('', fallback: 'ws://x:1'), 'ws://x:1');
    });

    test('defaultWsUrlFor API adresinin ana makinesini kullanır', () {
      expect(AppConfig.defaultWsUrlFor('http://192.168.1.10:8000/api'), 'ws://192.168.1.10:8081');
      expect(AppConfig.reverbAppKey, isNotEmpty);
    });
  });
}
