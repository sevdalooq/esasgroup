/// Laravel Reverb için küçük, elle yazılmış Pusher protokolü (v7) istemcisi.
///
/// Flutter'a bağımlı değildir (`dart run tool/reverb_probe.dart` ile de
/// çalışır). Bağlantı, `pusher:ping`/`pong`, özel kanal yetkilendirmesi
/// (`/broadcasting/auth`), üstel geri çekilmeli yeniden bağlanma ve
/// `(channel, event, data)` akışı sağlar.
library;

import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:web_socket_channel/web_socket_channel.dart';

enum PusherConnectionState { disconnected, connecting, connected }

/// Sunucudan gelen bir olay. [data] JSON çözülmüş haldedir (Map/List/String).
class PusherEvent {
  const PusherEvent({required this.event, this.channel, this.data});

  final String event;
  final String? channel;
  final Object? data;

  Map<String, dynamic> get dataMap {
    final d = data;
    if (d is Map<String, dynamic>) return d;
    if (d is Map) return d.map((k, v) => MapEntry(k.toString(), v));
    return const <String, dynamic>{};
  }

  bool get isInternal => event.startsWith('pusher:') || event.startsWith('pusher_internal:');

  @override
  String toString() => 'PusherEvent($channel, $event, $data)';
}

/// Ham soket soyutlaması (testlerde sahte soket kullanılabilir).
abstract class PusherSocket {
  Stream<dynamic> get stream;
  void send(String text);
  Future<void> close();
}

class WebSocketChannelSocket implements PusherSocket {
  WebSocketChannelSocket(this._channel);

  final WebSocketChannel _channel;

  static Future<PusherSocket> connect(Uri uri) async {
    final channel = WebSocketChannel.connect(uri);
    await channel.ready;
    return WebSocketChannelSocket(channel);
  }

  @override
  Stream<dynamic> get stream => _channel.stream;

  @override
  void send(String text) => _channel.sink.add(text);

  @override
  Future<void> close() => _channel.sink.close();
}

typedef PusherSocketConnector = Future<PusherSocket> Function(Uri uri);

/// Özel kanal için `auth` imzasını döndürür (`POST /broadcasting/auth`).
typedef PusherAuthorizer = Future<String> Function(String socketId, String channelName);

class PusherClient {
  PusherClient({
    required Uri Function() endpoint,
    required PusherAuthorizer authorizer,
    PusherSocketConnector? connector,
    Duration Function(int attempt)? backoff,
    this.pongTimeout = const Duration(seconds: 30),
    this.log,
  })  : _endpoint = endpoint,
        _authorizer = authorizer,
        _connector = connector ?? WebSocketChannelSocket.connect,
        _backoff = backoff ?? defaultBackoff;

  final Uri Function() _endpoint;
  final PusherAuthorizer _authorizer;
  final PusherSocketConnector _connector;
  final Duration Function(int attempt) _backoff;
  final Duration pongTimeout;
  final void Function(String message)? log;

  final _events = StreamController<PusherEvent>.broadcast();
  final _stateCtrl = StreamController<PusherConnectionState>.broadcast();
  final Set<String> _channels = {};
  final Set<String> _subscribed = {};

  PusherSocket? _socket;
  StreamSubscription<dynamic>? _socketSub;
  Timer? _reconnectTimer;
  Timer? _activityTimer;
  Timer? _pongTimer;
  Duration _activityTimeout = const Duration(seconds: 120);
  int _attempt = 0;
  int _generation = 0;
  bool _enabled = false;
  bool _disposed = false;
  String? _socketId;
  PusherConnectionState _state = PusherConnectionState.disconnected;

  /// `1s, 2s, 4s … 30s` + küçük rastgele gecikme.
  static Duration defaultBackoff(int attempt) {
    final base = min(30000, 1000 * (1 << min(attempt, 5)));
    return Duration(milliseconds: base + Random().nextInt(500));
  }

  Stream<PusherEvent> get events => _events.stream;
  Stream<PusherConnectionState> get stateStream => _stateCtrl.stream;
  PusherConnectionState get state => _state;
  bool get isConnected => _state == PusherConnectionState.connected;
  String? get socketId => _socketId;
  Set<String> get subscribedChannels => Set.unmodifiable(_subscribed);

  /// Belirli bir kanalın olayları (dahili `pusher*` olayları hariç).
  Stream<PusherEvent> channel(String name) =>
      _events.stream.where((e) => e.channel == name && !e.isInternal);

  /// Bağlantıyı açar; kopunca geri çekilmeyle yeniden dener.
  void connect() {
    if (_disposed) return;
    _enabled = true;
    if (_socket != null || _reconnectTimer != null) return;
    _open();
  }

  /// Bağlantıyı kapatır; yeniden denemez. Kanal listesi korunur.
  Future<void> disconnect() async {
    _enabled = false;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    await _teardown();
    _setState(PusherConnectionState.disconnected);
  }

  void subscribe(String channelName) {
    _channels.add(channelName);
    if (isConnected) _sendSubscribe(channelName);
  }

  void unsubscribe(String channelName) {
    _channels.remove(channelName);
    _subscribed.remove(channelName);
    if (isConnected) {
      _send({'event': 'pusher:unsubscribe', 'data': {'channel': channelName}});
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    await disconnect();
    await _events.close();
    await _stateCtrl.close();
  }

  // ---------------- iç işleyiş ----------------

  Future<void> _open() async {
    if (!_enabled || _disposed) return;
    final generation = ++_generation;
    _setState(PusherConnectionState.connecting);
    final uri = _endpoint();
    _log('bağlanıyor: $uri');
    try {
      final socket = await _connector(uri);
      if (generation != _generation || !_enabled) {
        await socket.close();
        return;
      }
      _socket = socket;
      _socketSub = socket.stream.listen(
        _onMessage,
        onError: (Object e) {
          _log('soket hatası: $e');
          _onClosed();
        },
        onDone: _onClosed,
        cancelOnError: true,
      );
    } catch (e) {
      _log('bağlantı kurulamadı: $e');
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic raw) {
    _touchActivity();
    Map<String, dynamic> msg;
    try {
      final decoded = jsonDecode(raw is String ? raw : utf8.decode(raw as List<int>));
      if (decoded is! Map) return;
      msg = decoded.map((k, v) => MapEntry(k.toString(), v));
    } catch (_) {
      _log('çözülemeyen mesaj: $raw');
      return;
    }
    final event = msg['event']?.toString() ?? '';
    final channelName = msg['channel']?.toString();
    var data = msg['data'];
    if (data is String) {
      try {
        data = jsonDecode(data);
      } catch (_) {
        // düz metin veri
      }
    }

    switch (event) {
      case 'pusher:connection_established':
        final d = data is Map ? data : const {};
        _socketId = d['socket_id']?.toString();
        final timeout = d['activity_timeout'];
        if (timeout is num && timeout > 0) {
          _activityTimeout = Duration(seconds: timeout.toInt());
        }
        _attempt = 0;
        _subscribed.clear();
        _setState(PusherConnectionState.connected);
        _log('bağlandı, socket_id=$_socketId');
        for (final c in _channels) {
          _sendSubscribe(c);
        }
        _touchActivity();
        return;
      case 'pusher:ping':
        _send({'event': 'pusher:pong', 'data': {}});
        return;
      case 'pusher:pong':
        _pongTimer?.cancel();
        _pongTimer = null;
        return;
      case 'pusher:error':
        final d = data is Map ? data : const {};
        final code = d['code'];
        _log('pusher:error ${d['message']} (code $code)');
        if (code is num && code >= 4000 && code < 4100) {
          // Kalıcı hata (ör. yanlış uygulama anahtarı) → yeniden deneme.
          _enabled = false;
        }
        break;
      case 'pusher_internal:subscription_succeeded':
        if (channelName != null) _subscribed.add(channelName);
        _log('abone olundu: $channelName');
        break;
      case 'pusher_internal:subscription_error':
        _log('abonelik hatası: $channelName $data');
        break;
    }
    if (!_events.isClosed) {
      _events.add(PusherEvent(event: event, channel: channelName, data: data));
    }
  }

  Future<void> _sendSubscribe(String channelName) async {
    final socketId = _socketId;
    if (socketId == null) return;
    final needsAuth = channelName.startsWith('private-') || channelName.startsWith('presence-');
    String? auth;
    if (needsAuth) {
      try {
        auth = await _authorizer(socketId, channelName);
      } catch (e) {
        _log('yetkilendirme başarısız ($channelName): $e');
        return;
      }
      // Bekleme sırasında bağlantı değiştiyse yeni bağlantı zaten yeniden abone olur.
      if (_socketId != socketId || !isConnected || !_channels.contains(channelName)) return;
    }
    _send({
      'event': 'pusher:subscribe',
      'data': {
        'channel': channelName,
        if (auth != null) 'auth': auth,
      },
    });
  }

  void _send(Map<String, dynamic> message) {
    final socket = _socket;
    if (socket == null) return;
    try {
      socket.send(jsonEncode(message));
    } catch (e) {
      _log('gönderilemedi: $e');
    }
  }

  void _touchActivity() {
    _activityTimer?.cancel();
    _activityTimer = Timer(_activityTimeout, () {
      if (!isConnected) return;
      _send({'event': 'pusher:ping', 'data': {}});
      _pongTimer?.cancel();
      _pongTimer = Timer(pongTimeout, () {
        _log('pong gelmedi, yeniden bağlanılıyor');
        _onClosed();
      });
    });
  }

  void _onClosed() {
    if (_socket == null && _state == PusherConnectionState.disconnected) return;
    _teardown();
    _setState(PusherConnectionState.disconnected);
    _scheduleReconnect();
  }

  Future<void> _teardown() async {
    _activityTimer?.cancel();
    _activityTimer = null;
    _pongTimer?.cancel();
    _pongTimer = null;
    _generation++;
    final sub = _socketSub;
    final socket = _socket;
    _socketSub = null;
    _socket = null;
    _socketId = null;
    _subscribed.clear();
    await sub?.cancel();
    try {
      await socket?.close();
    } catch (_) {
      // zaten kapalı
    }
  }

  void _scheduleReconnect() {
    if (!_enabled || _disposed || _reconnectTimer != null) {
      if (!_enabled) _setState(PusherConnectionState.disconnected);
      return;
    }
    final delay = _backoff(_attempt++);
    _log('yeniden bağlanma ${delay.inMilliseconds} ms sonra (deneme $_attempt)');
    _reconnectTimer = Timer(delay, () {
      _reconnectTimer = null;
      _open();
    });
  }

  void _setState(PusherConnectionState s) {
    if (_state == s) return;
    _state = s;
    if (!_stateCtrl.isClosed) _stateCtrl.add(s);
  }

  void _log(String message) => log?.call('[Pusher] $message');
}
