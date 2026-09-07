import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/realtime/connection_dot.dart';
import '../../../core/realtime/realtime_events.dart';
import '../../../core/realtime/realtime_provider.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../../auth/auth_provider.dart';
import '../../scanner/qr_payload.dart';
import '../../scanner/scan_helpers.dart';
import '../location/location_sharing.dart';
import '../models/self_models.dart';
import '../self_providers.dart';
import '../self_repository.dart';

/// PERSONEL MODU – "Görevlerim": bugünkü görev kartı (proje, adres, sorumlu,
/// durum), "Geldim – Alan QR'ı okut", Mola / Moladan döndüm, QR kartım,
/// konum paylaşımı anahtarı ve yaklaşan görevler.
class SelfHomeScreen extends ConsumerStatefulWidget {
  const SelfHomeScreen({super.key});

  @override
  ConsumerState<SelfHomeScreen> createState() => _SelfHomeScreenState();
}

class _SelfHomeScreenState extends ConsumerState<SelfHomeScreen> {
  Timer? _debounce;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    // Mola süresi gibi zamana bağlı metinler için dakikada bir yenile.
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _ticker?.cancel();
    super.dispose();
  }

  void _scheduleRefresh() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (mounted) ref.invalidate(myAssignmentsProvider);
    });
  }

  void _onDayEvent(PusherEvent e, SelfAssignments data) {
    final update = DayUpdatedEvent.fromPusher(e);
    if (update == null) return;
    _scheduleRefresh();
    final me = ref.read(authProvider).user?.id;
    if (update.actorId == me) return;
    final mine = update.personnelId == data.personnel.id;
    String? message;
    if (mine) {
      message = switch (update.type) {
        'check_in' => 'Saha sorumlusu girişinizi kaydetti',
        'check_out' => 'Çıkışınız kaydedildi',
        'break_start' => 'Molanız başlatıldı',
        'break_end' => 'Moladan dönüşünüz kaydedildi',
        'absent' => 'Gelmedi olarak işaretlendiniz',
        'absent_cleared' => 'Gelmedi işaretiniz kaldırıldı',
        'assignment_removed' => 'Bugünkü görevden çıkarıldınız',
        _ => null,
      };
    } else if (update.type == 'day_status') {
      message = update.toastMessage;
    }
    if (message != null) showSnack(context, message);
  }

  // ---------------- işlemler ----------------

  Future<bool> _run(
    Future<SelfActionResult> Function() task, {
    required String success,
    String progress = 'Kaydediliyor…',
  }) async {
    try {
      final result = await withProgress(context, task, message: progress);
      if (mounted) showSnack(context, result.message ?? success);
      ref.invalidate(myAssignmentsProvider);
      return true;
    } catch (e) {
      if (mounted) showSnack(context, errorMessage(e), error: true);
      return false;
    }
  }

  Future<void> _checkIn(SelfAssignment a) async {
    final payload = await openScanner(
      context,
      title: 'Alan QR',
      hint: 'Görev alanındaki QR kodu okutun',
    );
    if (payload == null || !mounted) return;
    final type = QrPayload.typeOf(payload);
    if (type != QrType.zone) {
      showSnack(
        context,
        type == QrType.unknown
            ? 'Bu kod bir Esas alan kodu değil.'
            : 'Bu kod bir ${QrPayload.typeLabel(type)} kodu; alan QR kodunu okutun.',
        error: true,
      );
      return;
    }
    final repo = ref.read(selfRepositoryProvider);
    await _run(
      () async {
        final pos = await currentPositionOrNull();
        return repo.checkIn(
          SelfCheckInRequest(
            zonePayload: payload,
            projectDayId: a.projectDayId,
            lat: pos?.latitude,
            lng: pos?.longitude,
          ),
        );
      },
      success: 'Girişiniz alındı',
      progress: 'Giriş kaydediliyor…',
    );
  }

  Future<void> _breakStart(SelfAssignment a) => _run(
        () => ref.read(selfRepositoryProvider).breakStart(projectDayId: a.projectDayId),
        success: 'Mola başladı',
      );

  Future<void> _breakEnd(SelfAssignment a) => _run(
        () => ref.read(selfRepositoryProvider).breakEnd(projectDayId: a.projectDayId),
        success: 'Moladan dönüldü',
      );

  Future<void> _call(String? phone) async {
    final tel = telUriFor(phone);
    if (tel == null) return;
    try {
      final ok = await launchUrl(Uri.parse(tel));
      if (!ok && mounted) showSnack(context, 'Arama uygulaması açılamadı.', error: true);
    } catch (_) {
      if (mounted) showSnack(context, 'Arama uygulaması açılamadı.', error: true);
    }
  }

  void _showQr(SelfPersonnel p) {
    final payload = p.qrPayload;
    if (payload == null || payload.isEmpty) {
      showSnack(context, 'QR kodunuz henüz tanımlanmamış.', error: true);
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(p.fullName, style: Theme.of(ctx).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(
              'Saha sorumlusuna bu kodu okutun',
              style: Theme.of(ctx).textTheme.bodyMedium?.copyWith(color: Theme.of(ctx).colorScheme.outline),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Theme.of(ctx).dividerColor),
              ),
              child: QrImageView(
                data: payload,
                size: 240,
                backgroundColor: Colors.white,
                errorCorrectionLevel: QrErrorCorrectLevel.M,
              ),
            ),
            const SizedBox(height: 10),
            SelectableText(payload, style: const TextStyle(fontSize: 12, fontFamily: 'monospace')),
          ],
        ),
      ),
    );
  }

  // ---------------- görünüm ----------------

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(myAssignmentsProvider);
    final user = ref.watch(authProvider.select((s) => s.user));
    final data = async.valueOrNull;
    final todays = data?.todays;

    // Bugünkü günün kanalına abone ol; konum paylaşımına bağlamı ver.
    if (todays != null) {
      ref.listen(dayEventsProvider(todays.projectDayId), (_, next) {
        final e = next.valueOrNull;
        if (e != null && data != null) _onDayEvent(e, data);
      });
    }
    ref.listen(myAssignmentsProvider, (_, next) {
      if (next.hasValue) {
        ref.read(locationSharingProvider.notifier).updateContext(next.value!.todays);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Görevlerim'),
            Text(
              formatDate(DateTime.now()),
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.normal),
            ),
          ],
        ),
        actions: [
          const ConnectionDot(),
          if (data != null)
            IconButton(
              tooltip: 'QR kartım',
              icon: const Icon(Icons.qr_code_2),
              onPressed: () => _showQr(data.personnel),
            ),
          PopupMenuButton<String>(
            tooltip: 'Hesap',
            icon: const Icon(Icons.account_circle_outlined),
            onSelected: (value) async {
              if (value == 'logout') {
                final ok = await confirmDialog(
                  context,
                  title: 'Çıkış',
                  message: 'Oturumu kapatmak istiyor musunuz?',
                  confirmText: 'Çıkış Yap',
                );
                if (ok) await ref.read(authProvider.notifier).logout();
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem<String>(
                enabled: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(data?.personnel.fullName ?? user?.name ?? '',
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                    Text(user?.email ?? '', style: const TextStyle(fontSize: 12)),
                    const Text('Personel modu', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem<String>(
                value: 'logout',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.logout),
                  title: Text('Çıkış Yap'),
                ),
              ),
            ],
          ),
        ],
      ),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorView(
          message: errorMessage(e),
          onRetry: () => ref.invalidate(myAssignmentsProvider),
        ),
        data: (SelfAssignments d) => RefreshIndicator(
          onRefresh: () => ref.refresh(myAssignmentsProvider.future),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              _PersonnelHeader(personnel: d.personnel, onShowQr: () => _showQr(d.personnel)),
              const SizedBox(height: 12),
              if (d.todays == null)
                const Card(
                  child: ListTile(
                    leading: Icon(Icons.event_available_outlined),
                    title: Text('Bugün için görev yok'),
                    subtitle: Text('Size atanmış bir görev olduğunda burada görünecek.'),
                  ),
                )
              else
                _TodayCard(
                  assignment: d.todays!,
                  onCheckIn: () => _checkIn(d.todays!),
                  onBreakStart: () => _breakStart(d.todays!),
                  onBreakEnd: () => _breakEnd(d.todays!),
                  onCall: _call,
                ),
              const SizedBox(height: 12),
              const _LocationCard(),
              const SizedBox(height: 16),
              Text('Yaklaşan görevler (${d.upcoming.length})',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              if (d.upcoming.isEmpty)
                const Card(child: ListTile(title: Text('Yaklaşan görev yok')))
              else
                Card(
                  clipBehavior: Clip.antiAlias,
                  child: Column(
                    children: [
                      for (var i = 0; i < d.upcoming.length; i++) ...[
                        if (i > 0) const Divider(height: 1),
                        _UpcomingTile(assignment: d.upcoming[i]),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PersonnelHeader extends ConsumerWidget {
  const _PersonnelHeader({required this.personnel, required this.onShowQr});

  final SelfPersonnel personnel;
  final VoidCallback onShowQr;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final photoUrl = ref.watch(apiClientProvider).resolveUrl(personnel.photo);
    return Card(
      child: ListTile(
        leading: photoUrl == null
            ? InitialsAvatar(name: personnel.fullName, radius: 24)
            : CircleAvatar(radius: 24, backgroundImage: CachedNetworkImageProvider(photoUrl)),
        title: Text(personnel.fullName, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text('Personel', style: TextStyle(color: theme.colorScheme.outline)),
        trailing: FilledButton.tonalIcon(
          onPressed: onShowQr,
          icon: const Icon(Icons.qr_code_2),
          label: const Text('QR Kartım'),
        ),
      ),
    );
  }
}

/// Bugünkü görev: proje, müşteri, adres, sorumlu (ara), durum ve büyük butonlar.
class _TodayCard extends StatelessWidget {
  const _TodayCard({
    required this.assignment,
    required this.onCheckIn,
    required this.onBreakStart,
    required this.onBreakEnd,
    required this.onCall,
  });

  final SelfAssignment assignment;
  final VoidCallback onCheckIn;
  final VoidCallback onBreakStart;
  final VoidCallback onBreakEnd;
  final void Function(String? phone) onCall;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final a = assignment;
    final day = a.day;
    final presence = a.effectivePresence;
    final color = presenceColor(presence);
    var presenceText = presenceLabel(presence);
    if (a.isOnBreak && a.breakStartedAt != null) {
      presenceText = '$presenceText · ${formatElapsed(a.breakStartedAt)}';
    }

    final Widget action;
    if (day != null && (day.isCompleted || day.isCancelled)) {
      action = _InfoLine(
        icon: Icons.lock_outline,
        text: day.isCompleted ? 'Gün kapatıldı.' : 'Görev iptal edildi.',
      );
    } else if (a.isAbsent) {
      action = const _InfoLine(
        icon: Icons.person_off_outlined,
        text: 'Gelmedi olarak işaretlendiniz. Yanlışsa saha sorumlusuna bildirin.',
      );
    } else if (a.isCheckedOut) {
      action = _InfoLine(
        icon: Icons.logout,
        text: 'Çıkışınız ${formatTime(a.checkOutTime)} olarak kaydedildi. İyi dinlenmeler!',
      );
    } else if (!a.isCheckedIn) {
      action = FilledButton.icon(
        onPressed: onCheckIn,
        style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 18)),
        icon: const Icon(Icons.qr_code_scanner, size: 28),
        label: const Text("Geldim – Alan QR'ı okut", style: TextStyle(fontSize: 17)),
      );
    } else if (a.isOnBreak) {
      action = FilledButton.icon(
        onPressed: onBreakEnd,
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 18),
          backgroundColor: const Color(0xFF2E7D32),
        ),
        icon: const Icon(Icons.play_arrow, size: 28),
        label: const Text('Moladan döndüm', style: TextStyle(fontSize: 17)),
      );
    } else {
      action = OutlinedButton.icon(
        onPressed: onBreakStart,
        style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 18)),
        icon: const Icon(Icons.coffee_outlined, size: 26),
        label: const Text('Mola', style: TextStyle(fontSize: 17)),
      );
    }

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: color.withValues(alpha: 0.12),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(Icons.today_outlined, color: color),
                const SizedBox(width: 8),
                Expanded(
                  child: Text('Bugünkü görev',
                      style: theme.textTheme.titleSmall?.copyWith(color: color)),
                ),
                StatusChip(label: presenceText, color: color),
                if (a.needsVerification && !a.isCheckedOut) ...[
                  const SizedBox(width: 6),
                  const StatusChip(label: 'Doğrulanmadı', color: Color(0xFFF9A825)),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  day?.projectName.isNotEmpty == true ? day!.projectName : 'Proje',
                  style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                ),
                if (day != null && day.customerName.isNotEmpty)
                  Text(day.customerName,
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.outline)),
                const SizedBox(height: 10),
                if (day?.venueAddress != null)
                  _InfoLine(icon: Icons.place_outlined, text: day!.venueAddress!),
                if (a.zone.isNotEmpty)
                  _InfoLine(icon: Icons.grid_view_outlined, text: 'Alan: ${a.zone}'),
                if (a.isCheckedIn)
                  _InfoLine(
                    icon: Icons.login,
                    text: 'Giriş ${formatTime(a.checkInTime)}'
                        '${a.breakMinutes > 0 ? ' · Toplam mola ${a.breakMinutes} dk' : ''}',
                  ),
                if (day?.notes != null)
                  _InfoLine(icon: Icons.sticky_note_2_outlined, text: day!.notes!),
                if (day != null && day.supervisorName.isNotEmpty)
                  InkWell(
                    onTap: day.supervisorPhone == null ? null : () => onCall(day.supervisorPhone),
                    borderRadius: BorderRadius.circular(8),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(
                        children: [
                          Icon(Icons.support_agent_outlined, size: 20, color: theme.colorScheme.primary),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Saha sorumlusu: ${day.supervisorName}'
                              '${day.supervisorPhone != null ? ' · ${day.supervisorPhone}' : ''}',
                              style: TextStyle(
                                color: day.supervisorPhone != null ? theme.colorScheme.primary : null,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          if (day.supervisorPhone != null)
                            Icon(Icons.call, size: 20, color: theme.colorScheme.primary),
                        ],
                      ),
                    ),
                  ),
                const SizedBox(height: 14),
                action,
                if (!a.isCheckedIn && !a.isAbsent && a.canAct) ...[
                  const SizedBox(height: 6),
                  Text(
                    'Girişiniz saha sorumlusu tarafından doğrulanacaktır.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: theme.colorScheme.outline),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }
}

/// "Konum paylaşımı" anahtarı + son gönderim / hata bilgisi.
class _LocationCard extends ConsumerWidget {
  const _LocationCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final s = ref.watch(locationSharingProvider);
    final notifier = ref.read(locationSharingProvider.notifier);
    final String status;
    if (!s.enabled) {
      status = 'Kapalı. Açınca görev sırasında (uygulama açıkken) konumunuz 60 sn\'de bir saha sorumlusuyla paylaşılır.';
    } else if (s.lastError != null) {
      status = s.lastError!;
    } else if (!s.running) {
      status = s.projectDayId == null
          ? 'Bugün görev olmadığı için paylaşım beklemede.'
          : 'Beklemede (uygulama ön plana gelince sürer).';
    } else {
      status = s.lastSentAt == null
          ? 'Açık · ilk konum gönderiliyor…'
          : 'Açık · son gönderim ${formatTime(s.lastSentAt!.toIso8601String())} (${relativeTime(s.lastSentAt)})';
    }
    return Card(
      child: Column(
        children: [
          SwitchListTile(
            secondary: Icon(
              s.running ? Icons.my_location : Icons.location_off_outlined,
              color: s.running ? const Color(0xFF2E7D32) : theme.colorScheme.outline,
            ),
            title: const Text('Konum paylaşımı', style: TextStyle(fontWeight: FontWeight.w600)),
            subtitle: Text(status),
            value: s.enabled,
            onChanged: notifier.setEnabled,
          ),
          if (s.enabled && (s.permissionDeniedForever || s.serviceDisabled))
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: notifier.openSettings,
                  icon: const Icon(Icons.settings_outlined, size: 18),
                  label: const Text('Ayarları aç'),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _UpcomingTile extends StatelessWidget {
  const _UpcomingTile({required this.assignment});

  final SelfAssignment assignment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final day = assignment.day;
    final date = day?.date;
    return ListTile(
      leading: Container(
        width: 46,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: theme.colorScheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          children: [
            Text(date == null ? '-' : '${date.day}',
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            Text(date == null ? '' : formatShortDate(date).split(' ')[1],
                style: theme.textTheme.bodySmall),
          ],
        ),
      ),
      title: Text(day?.projectName.isNotEmpty == true ? day!.projectName : 'Proje',
          maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        [
          if (day != null && day.customerName.isNotEmpty) day.customerName,
          if (assignment.zone.isNotEmpty) assignment.zone,
          if (day?.venueAddress != null) day!.venueAddress!,
        ].join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: StatusChip(label: dayPhaseLabel(day?.status ?? ''), color: dayStatusColor(day?.status ?? '')),
    );
  }
}
