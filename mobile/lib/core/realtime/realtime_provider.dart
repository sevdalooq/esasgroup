import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/auth_provider.dart';
import '../api/api_client.dart';
import '../config/app_config.dart';
import '../utils/json.dart';
import 'pusher_client.dart';

export 'pusher_client.dart' show PusherConnectionState, PusherEvent;

/// Kanal aboneliği; [dispose] ile bırakılır (sayaç sıfırlanınca kanal kapanır).
class RealtimeSubscription {
  RealtimeSubscription._(this._release);

  final void Function() _release;
  bool _released = false;

  void dispose() {
    if (_released) return;
    _released = true;
    _release();
  }
}

/// [PusherClient] sarmalayıcısı: `/broadcasting/auth` yetkilendirmesi,
/// sayaçlı kanal abonelikleri ve oturuma bağlı bağlan/kopar.
class RealtimeService {
  RealtimeService({
    required ApiClient api,
    required Uri Function() endpoint,
    PusherSocketConnector? connector,
  }) : _api = api {
    client = PusherClient(
      endpoint: endpoint,
      authorizer: _authorize,
      connector: connector,
      log: kDebugMode ? debugPrint : null,
    );
  }

  final ApiClient _api;
  late final PusherClient client;
  final Map<String, int> _refs = {};

  /// POST /broadcasting/auth {socket_id, channel_name} → {auth}
  Future<String> _authorize(String socketId, String channelName) async {
    final res = await _api.post(
      '/broadcasting/auth',
      data: {'socket_id': socketId, 'channel_name': channelName},
    );
    final auth = asString(asMap(res.data)['auth']);
    if (auth.isEmpty) throw StateError('Yetkilendirme yanıtı boş.');
    return auth;
  }

  void connect() => client.connect();
  Future<void> disconnect() => client.disconnect();

  Stream<PusherEvent> channelEvents(String channel) => client.channel(channel);

  RealtimeSubscription subscribe(String channel) {
    final count = (_refs[channel] ?? 0) + 1;
    _refs[channel] = count;
    if (count == 1) client.subscribe(channel);
    return RealtimeSubscription._(() {
      final left = (_refs[channel] ?? 1) - 1;
      if (left <= 0) {
        _refs.remove(channel);
        client.unsubscribe(channel);
      } else {
        _refs[channel] = left;
      }
    });
  }

  Future<void> dispose() => client.dispose();
}

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  final api = ref.watch(apiClientProvider);
  final service = RealtimeService(
    api: api,
    endpoint: () {
      final auth = ref.read(authProvider);
      return AppConfig.reverbUri(auth.effectiveWsUrl, auth.wsKey);
    },
  );
  ref.onDispose(service.dispose);
  ref.listen<AuthStatus>(
    authProvider.select((s) => s.status),
    (_, status) {
      if (status == AuthStatus.authenticated) {
        service.connect();
      } else {
        service.disconnect();
      }
    },
    fireImmediately: true,
  );
  return service;
});

class _ConnectionNotifier extends Notifier<PusherConnectionState> {
  @override
  PusherConnectionState build() {
    final service = ref.watch(realtimeServiceProvider);
    final sub = service.client.stateStream.listen((s) => state = s);
    ref.onDispose(sub.cancel);
    return service.client.state;
  }
}

/// Canlı bağlantı durumu (app bar'daki nokta için).
final realtimeStateProvider =
    NotifierProvider<_ConnectionNotifier, PusherConnectionState>(_ConnectionNotifier.new);

/// `private-live` kanalı olayları (dinlendiği sürece abone kalır).
final liveEventsProvider = StreamProvider.autoDispose<PusherEvent>((ref) {
  final service = ref.watch(realtimeServiceProvider);
  final sub = service.subscribe('private-live');
  ref.onDispose(sub.dispose);
  return service.channelEvents('private-live');
});

/// `private-day.{id}` kanalı olayları.
final dayEventsProvider = StreamProvider.autoDispose.family<PusherEvent, int>((ref, dayId) {
  final service = ref.watch(realtimeServiceProvider);
  final channel = 'private-day.$dayId';
  final sub = service.subscribe(channel);
  ref.onDispose(sub.dispose);
  return service.channelEvents(channel);
});
