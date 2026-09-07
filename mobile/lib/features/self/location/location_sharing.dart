import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../../../core/storage/app_storage.dart';
import '../models/self_models.dart';
import '../self_repository.dart';

/// Konum paylaşımı durumu (personel modu ekranındaki anahtar ve bilgi satırı).
class LocationSharingState {
  const LocationSharingState({
    this.enabled = false,
    this.running = false,
    this.lastSentAt,
    this.lastError,
    this.permissionDeniedForever = false,
    this.serviceDisabled = false,
    this.projectDayId,
  });

  /// Kullanıcı anahtarı açtı mı (kalıcı).
  final bool enabled;

  /// Şu an fiilen gönderim yapılıyor mu (ön planda + bugün görev var + izin var).
  final bool running;
  final DateTime? lastSentAt;
  final String? lastError;
  final bool permissionDeniedForever;
  final bool serviceDisabled;
  final int? projectDayId;

  LocationSharingState copyWith({
    bool? enabled,
    bool? running,
    DateTime? lastSentAt,
    String? lastError,
    bool clearError = false,
    bool? permissionDeniedForever,
    bool? serviceDisabled,
    int? projectDayId,
    bool clearDay = false,
  }) =>
      LocationSharingState(
        enabled: enabled ?? this.enabled,
        running: running ?? this.running,
        lastSentAt: lastSentAt ?? this.lastSentAt,
        lastError: clearError ? null : (lastError ?? this.lastError),
        permissionDeniedForever: permissionDeniedForever ?? this.permissionDeniedForever,
        serviceDisabled: serviceDisabled ?? this.serviceDisabled,
        projectDayId: clearDay ? null : (projectDayId ?? this.projectDayId),
      );
}

/// Ön planda konum paylaşımı: izin (whileInUse) → her 60 sn `POST /field/location`
/// + 25 m'lik önemli değişimde anında gönderim. Uygulama arka plana gidince
/// durur, dönünce sürer. Arka plan konumu YOK (README → Yapılacaklar).
class LocationSharingNotifier extends Notifier<LocationSharingState>
    with WidgetsBindingObserver {
  Timer? _timer;
  StreamSubscription<Position>? _positions;
  bool _foreground = true;
  bool _hasTask = false;
  bool _sending = false;

  @override
  LocationSharingState build() {
    WidgetsBinding.instance.addObserver(this);
    ref.onDispose(() {
      WidgetsBinding.instance.removeObserver(this);
      _stop();
    });
    // Kayıtlı tercihi geri yükle.
    ref.read(appStorageProvider).readLocationSharing().then((enabled) {
      if (enabled && !state.enabled) {
        state = state.copyWith(enabled: true);
        _reconcile();
      }
    });
    return const LocationSharingState();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    _reconcile();
  }

  /// Ekran bugünkü görevi yükleyince çağrılır: paylaşım hangi güne bağlanacak.
  void updateContext(SelfAssignment? todays) {
    final hasTask = todays != null && todays.canAct && !todays.isAbsent;
    final dayId = todays?.projectDayId;
    _hasTask = hasTask;
    if (state.projectDayId != dayId) {
      state = dayId == null ? state.copyWith(clearDay: true) : state.copyWith(projectDayId: dayId);
    }
    _reconcile();
  }

  Future<void> setEnabled(bool enabled) async {
    state = state.copyWith(enabled: enabled, clearError: true);
    await ref.read(appStorageProvider).writeLocationSharing(enabled);
    await _reconcile();
  }

  /// Şimdi bir konum gönder (anahtar açıkken elle tetiklenebilir).
  Future<void> sendNow() async {
    if (!state.enabled) return;
    if (!await _ensurePermission()) return;
    await _sendCurrent();
  }

  Future<void> _reconcile() async {
    final shouldRun = state.enabled && _foreground && _hasTask;
    if (shouldRun && !state.running) {
      if (!await _ensurePermission()) return;
      _start();
    } else if (!shouldRun && state.running) {
      _stop();
    }
  }

  Future<bool> _ensurePermission() async {
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        state = state.copyWith(
          serviceDisabled: true,
          lastError: 'Cihazın konum servisi kapalı.',
          running: false,
        );
        return false;
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.deniedForever) {
        state = state.copyWith(
          permissionDeniedForever: true,
          lastError: 'Konum izni kalıcı olarak reddedildi. Ayarlardan izin verin.',
          running: false,
        );
        return false;
      }
      if (permission == LocationPermission.denied) {
        state = state.copyWith(lastError: 'Konum izni verilmedi.', running: false);
        return false;
      }
      state = state.copyWith(
        serviceDisabled: false,
        permissionDeniedForever: false,
        clearError: true,
      );
      return true;
    } catch (e) {
      state = state.copyWith(lastError: 'Konum izni kontrol edilemedi.', running: false);
      debugPrint('[Location] izin hatası: $e');
      return false;
    }
  }

  void _start() {
    _stop();
    state = state.copyWith(running: true, clearError: true);
    _timer = Timer.periodic(AppConfig.locationInterval, (_) => _sendCurrent());
    _positions = Geolocator.getPositionStream(
      locationSettings: LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: AppConfig.locationDistanceFilterMeters,
      ),
    ).listen(
      _send,
      onError: (Object e) {
        debugPrint('[Location] akış hatası: $e');
        state = state.copyWith(lastError: 'Konum alınamadı.');
      },
    );
    _sendCurrent();
  }

  void _stop() {
    _timer?.cancel();
    _timer = null;
    _positions?.cancel();
    _positions = null;
    if (state.running) state = state.copyWith(running: false);
  }

  Future<void> _sendCurrent() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
      await _send(position);
    } catch (e) {
      debugPrint('[Location] konum alınamadı: $e');
      state = state.copyWith(lastError: 'Konum alınamadı.');
    }
  }

  Future<void> _send(Position position) async {
    if (_sending) return;
    _sending = true;
    try {
      await ref.read(selfRepositoryProvider).reportLocation(
            LocationReport(
              lat: position.latitude,
              lng: position.longitude,
              accuracy: position.accuracy,
              projectDayId: state.projectDayId,
              recordedAt: position.timestamp,
            ),
          );
      state = state.copyWith(lastSentAt: DateTime.now(), clearError: true);
    } catch (e) {
      state = state.copyWith(lastError: errorMessage(e));
    } finally {
      _sending = false;
    }
  }

  Future<void> openSettings() => Geolocator.openAppSettings();
}

final locationSharingProvider =
    NotifierProvider<LocationSharingNotifier, LocationSharingState>(LocationSharingNotifier.new);

/// Tek seferlik konum (giriş sırasında `lat/lng` için). Hata/izin yoksa null.
Future<Position?> currentPositionOrNull({Duration timeout = const Duration(seconds: 8)}) async {
  try {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      return null;
    }
    return await Geolocator.getCurrentPosition(
      locationSettings: LocationSettings(accuracy: LocationAccuracy.high, timeLimit: timeout),
    );
  } catch (_) {
    return null;
  }
}
