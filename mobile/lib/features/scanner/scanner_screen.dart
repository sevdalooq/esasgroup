import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/config/app_config.dart';
import '../../core/widgets/ui_helpers.dart';
import 'nfc_reader.dart';

/// Tam ekran QR okuyucu. Okunan payload `Navigator.pop(payload)` ile döner.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key, this.title = 'QR Okut', this.hint});

  final String title;
  final String? hint;

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen>
    with WidgetsBindingObserver {
  late final MobileScannerController _controller;
  final _manualCtrl = TextEditingController();
  bool _handled = false;
  bool _nfcAvailable = false;
  bool _nfcReading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _controller = MobileScannerController(
      detectionSpeed: DetectionSpeed.noDuplicates,
      formats: const [BarcodeFormat.qrCode],
      autoStart: true,
    );
    _checkNfc();
  }

  Future<void> _checkNfc() async {
    final available = await NfcReader.isAvailable();
    if (mounted) setState(() => _nfcAvailable = available);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Uygulama arka plana gidince kamerayı durdur, dönünce başlat.
    switch (state) {
      case AppLifecycleState.resumed:
        _controller.start();
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
      case AppLifecycleState.detached:
        _controller.stop();
        break;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _manualCtrl.dispose();
    _controller.dispose();
    if (_nfcReading) NfcReader.cancel();
    super.dispose();
  }

  void _finish(String payload) {
    if (_handled) return;
    final value = payload.trim();
    if (value.isEmpty) return;
    _handled = true;
    Navigator.of(context).pop(value);
  }

  void _onDetect(BarcodeCapture capture) {
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue;
      if (raw != null && raw.trim().isNotEmpty) {
        _finish(raw);
        return;
      }
    }
  }

  Future<void> _readNfc() async {
    if (_nfcReading) return;
    setState(() => _nfcReading = true);
    await _controller.stop();
    if (!mounted) return;
    final result = await showDialog<String?>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _NfcDialog(),
    );
    if (!mounted) return;
    setState(() => _nfcReading = false);
    if (result != null && result.isNotEmpty) {
      _finish(result);
      return;
    }
    await _controller.start();
    if (!mounted) return;
    showSnack(context, 'NFC etiketi okunamadı.', error: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(widget.title),
        actions: [
          ValueListenableBuilder<MobileScannerState>(
            valueListenable: _controller,
            builder: (context, state, _) {
              final torch = state.torchState;
              final unavailable = torch == TorchState.unavailable;
              return IconButton(
                tooltip: 'Fener',
                onPressed: unavailable ? null : () => _controller.toggleTorch(),
                icon: Icon(
                  torch == TorchState.on ? Icons.flash_on : Icons.flash_off,
                  color: unavailable
                      ? Colors.white38
                      : (torch == TorchState.on ? Colors.amber : Colors.white),
                ),
              );
            },
          ),
          IconButton(
            tooltip: 'Kamerayı değiştir',
            onPressed: () => _controller.switchCamera(),
            icon: const Icon(Icons.cameraswitch_outlined),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              fit: StackFit.expand,
              children: [
                MobileScanner(
                  controller: _controller,
                  onDetect: _onDetect,
                  errorBuilder: (context, error, _) => _CameraError(error: error),
                ),
                const _ScanOverlay(),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 16,
                  child: Text(
                    widget.hint ?? 'QR kodu çerçevenin içine hizalayın',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      shadows: [Shadow(blurRadius: 6, color: Colors.black)],
                    ),
                  ),
                ),
              ],
            ),
          ),
          Container(
            color: theme.colorScheme.surface,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: SafeArea(
              top: false,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _manualCtrl,
                          textInputAction: TextInputAction.done,
                          autocorrect: false,
                          onSubmitted: _finish,
                          decoration: const InputDecoration(
                            labelText: 'Kodu elle gir',
                            hintText: 'ESAS:PER:…',
                            isDense: true,
                            prefixIcon: Icon(Icons.keyboard_outlined),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      FilledButton(
                        onPressed: () => _finish(_manualCtrl.text),
                        child: const Text('Kullan'),
                      ),
                    ],
                  ),
                  if (AppConfig.nfcEnabled && _nfcAvailable) ...[
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: _nfcReading ? null : _readNfc,
                        icon: const Icon(Icons.nfc),
                        label: const Text('NFC ile oku'),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NfcDialog extends StatefulWidget {
  @override
  State<_NfcDialog> createState() => _NfcDialogState();
}

class _NfcDialogState extends State<_NfcDialog> {
  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    final text = await NfcReader.readText();
    if (mounted) Navigator.of(context).pop(text);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('NFC okunuyor'),
      content: const Row(
        children: [
          Icon(Icons.nfc, size: 36),
          SizedBox(width: 16),
          Expanded(child: Text('Telefonu NFC etiketine yaklaştırın…')),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () async {
            await NfcReader.cancel();
            if (context.mounted) Navigator.of(context).pop(null);
          },
          child: const Text('Vazgeç'),
        ),
      ],
    );
  }
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    final message = switch (error.errorCode) {
      MobileScannerErrorCode.permissionDenied =>
        'Kamera izni verilmedi. Ayarlardan kamera iznini açın ya da kodu elle girin.',
      MobileScannerErrorCode.unsupported =>
        'Bu cihazda kamera desteklenmiyor. Kodu elle girebilirsiniz.',
      _ => 'Kamera başlatılamadı. Kodu elle girebilirsiniz.',
    };
    return Container(
      color: Colors.black,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.no_photography_outlined, color: Colors.white70, size: 56),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white),
          ),
        ],
      ),
    );
  }
}

/// Karartılmış alan + ortada kırmızı köşeli okuma çerçevesi.
class _ScanOverlay extends StatelessWidget {
  const _ScanOverlay();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: CustomPaint(
        painter: _OverlayPainter(Theme.of(context).colorScheme.primary),
      ),
    );
  }
}

class _OverlayPainter extends CustomPainter {
  _OverlayPainter(this.accent);

  final Color accent;

  @override
  void paint(Canvas canvas, Size size) {
    final side = size.shortestSide * 0.68;
    final rect = Rect.fromCenter(
      center: Offset(size.width / 2, size.height / 2 - 20),
      width: side,
      height: side,
    );
    final rrect = RRect.fromRectAndRadius(rect, const Radius.circular(16));

    final overlay = Path()
      ..addRect(Offset.zero & size)
      ..addRRect(rrect)
      ..fillType = PathFillType.evenOdd;
    canvas.drawPath(overlay, Paint()..color = Colors.black.withValues(alpha: 0.55));

    final corner = Paint()
      ..color = accent
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round;
    const len = 28.0;
    final l = rect.left, r = rect.right, t = rect.top, b = rect.bottom;
    canvas
      ..drawLine(Offset(l, t + len), Offset(l, t), corner)
      ..drawLine(Offset(l, t), Offset(l + len, t), corner)
      ..drawLine(Offset(r - len, t), Offset(r, t), corner)
      ..drawLine(Offset(r, t), Offset(r, t + len), corner)
      ..drawLine(Offset(l, b - len), Offset(l, b), corner)
      ..drawLine(Offset(l, b), Offset(l + len, b), corner)
      ..drawLine(Offset(r - len, b), Offset(r, b), corner)
      ..drawLine(Offset(r, b), Offset(r, b - len), corner);
  }

  @override
  bool shouldRepaint(covariant _OverlayPainter oldDelegate) =>
      oldDelegate.accent != accent;
}
