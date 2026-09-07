// Canlı Reverb doğrulaması (saf Dart, Flutter gerekmez):
//
//   cd mobile && dart run tool/reverb_probe.dart [--api http://localhost:8000/api] [--ws ws://localhost:8081] [--day 5] [--no-trigger]
//
// 1) Saha sorumlusu hesabıyla /login → token
// 2) ws://host:8081/app/<key>?protocol=7 bağlantısı (uygulamanın PusherClient'ı)
// 3) POST /broadcasting/auth ile `private-live` aboneliği
// 4) Abonelik başarılı olunca gün {day} içinde check_in_time == null olan bir
//    görevlendirme için POST /field/days/{day}/absent {absent:true} → {absent:false}
//    tetikler (durum geri alınır) ve ilk olayı 20 sn içinde yazdırır.
import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:esas_saha/core/realtime/pusher_client.dart';
import 'package:esas_saha/core/realtime/realtime_events.dart';

const _email = 'saha@esasgroup.com.tr';
const _password = 'EsasSaha2026!';

Future<void> main(List<String> args) async {
  var api = 'http://localhost:8000/api';
  var ws = 'ws://localhost:8081';
  var day = 5;
  var trigger = true;
  var key = _readEnvKey() ?? 'bfci1swbu8eicvlkrsbc';
  for (var i = 0; i < args.length; i++) {
    switch (args[i]) {
      case '--api':
        api = args[++i];
      case '--ws':
        ws = args[++i];
      case '--day':
        day = int.parse(args[++i]);
      case '--key':
        key = args[++i];
      case '--no-trigger':
        trigger = false;
    }
  }

  final http = HttpClient();
  Future<Map<String, dynamic>> call(String method, String path, {Object? body, String? token}) async {
    final req = await http.openUrl(method, Uri.parse('$api$path'));
    req.headers.set('Accept', 'application/json');
    req.headers.set('Content-Type', 'application/json');
    if (token != null) req.headers.set('Authorization', 'Bearer $token');
    if (body != null) req.write(jsonEncode(body));
    final res = await req.close();
    final text = await utf8.decodeStream(res);
    if (res.statusCode >= 400) {
      throw HttpException('$method $path → ${res.statusCode}: ${text.length > 300 ? text.substring(0, 300) : text}');
    }
    final decoded = jsonDecode(text);
    return decoded is Map ? decoded.cast<String, dynamic>() : {'data': decoded};
  }

  stdout.writeln('1) login $_email @ $api');
  final login = await call('POST', '/login', body: {'email': _email, 'password': _password, 'device_name': 'reverb_probe'});
  final token = login['token'] as String;
  stdout.writeln('   token alındı (${token.length} kr), izinler: ${login['permissions']}');

  final endpoint = Uri.parse('$ws/app/$key').replace(queryParameters: const {'protocol': '7', 'client': 'flutter', 'version': '1'});
  stdout.writeln('2) websocket $endpoint');

  final firstEvent = Completer<PusherEvent>();
  final subscribed = Completer<void>();
  final client = PusherClient(
    endpoint: () => endpoint,
    authorizer: (socketId, channel) async {
      stdout.writeln('3) POST /broadcasting/auth {socket_id: $socketId, channel_name: $channel}');
      final res = await call('POST', '/broadcasting/auth', body: {'socket_id': socketId, 'channel_name': channel}, token: token);
      stdout.writeln('   auth: ${res['auth']}');
      return res['auth'] as String;
    },
    log: (m) => stdout.writeln('   $m'),
  );
  client.events.listen((e) {
    if (e.event == 'pusher_internal:subscription_succeeded' && !subscribed.isCompleted) {
      subscribed.complete();
    }
    if (!e.isInternal && !firstEvent.isCompleted) firstEvent.complete(e);
  });
  client.subscribe('private-live');
  client.connect();

  try {
    await subscribed.future.timeout(const Duration(seconds: 15));
    stdout.writeln('   private-live aboneliği başarılı (socket_id=${client.socketId})');

    if (trigger) {
      final detail = await call('GET', '/field/days/$day', token: token);
      final assignments = ((detail['day'] as Map)['personnel_assignments'] as List).cast<Map>();
      final target = assignments.firstWhere(
        (a) => a['check_in_time'] == null && a['presence'] != 'absent',
        orElse: () => throw StateError('Gün $day içinde check_in_time == null görevlendirme yok'),
      );
      final id = target['id'];
      final name = (target['personnel'] as Map?)?['full_name'];
      stdout.writeln('4) tetik: POST /field/days/$day/absent {assignment_id: $id ($name), absent: true}');
      final r1 = await call('POST', '/field/days/$day/absent', body: {'assignment_id': id, 'absent': true}, token: token);
      stdout.writeln('   → ${r1['message']}');
      stdout.writeln('   geri al: {absent: false}');
      final r2 = await call('POST', '/field/days/$day/absent', body: {'assignment_id': id, 'absent': false}, token: token);
      stdout.writeln('   → ${r2['message']} (presence: ${(r2['assignment'] as Map?)?['presence']})');
    } else {
      stdout.writeln('4) tetik atlandı (--no-trigger); 20 sn olay bekleniyor…');
    }

    final e = await firstEvent.future.timeout(const Duration(seconds: 20));
    stdout.writeln('5) İLK OLAY: channel=${e.channel} event=${e.event}');
    stdout.writeln('   data=${jsonEncode(e.data)}');
    final update = DayUpdatedEvent.fromPusher(e);
    if (update != null) {
      stdout.writeln('   → DayUpdatedEvent type=${update.type} day=${update.projectDayId} actor=${update.actorId} toast="${update.toastMessage}"');
    }
    stdout.writeln('OK');
  } on TimeoutException {
    stderr.writeln('ZAMAN AŞIMI: olay gelmedi (Reverb çalışıyor mu? BROADCAST_CONNECTION=reverb mi?)');
    exitCode = 1;
  } catch (e) {
    stderr.writeln('HATA: $e');
    exitCode = 1;
  } finally {
    await client.dispose();
    http.close(force: true);
  }
}

/// `../.env` içindeki REVERB_APP_KEY (varsa).
String? _readEnvKey() {
  for (final path in ['../.env', '.env']) {
    final f = File(path);
    if (!f.existsSync()) continue;
    for (final line in f.readAsLinesSync()) {
      if (line.startsWith('REVERB_APP_KEY=')) {
        return line.substring('REVERB_APP_KEY='.length).trim().replaceAll('"', '');
      }
    }
  }
  return null;
}
