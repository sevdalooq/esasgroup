import 'package:flutter/material.dart';

import '../../scanner/scan_helpers.dart';
import '../models/models.dart';

/// Seçilen alan: QR payload'ı ve/veya serbest metin adı.
class ZoneSelection {
  const ZoneSelection({this.payload, this.name});

  final String? payload;
  final String? name;

  bool get isEmpty => (payload == null || payload!.isEmpty) && (name == null || name!.isEmpty);

  String get label => name ?? payload ?? '';

  static const ZoneSelection none = ZoneSelection();
}

/// Alan seçimi alt sayfası: tanımlı alan çipleri, QR ile alan okutma, serbest metin.
Future<ZoneSelection?> showZonePicker(
  BuildContext context, {
  required List<Zone> zones,
  String? personnelName,
  String? initialZone,
}) {
  return showModalBottomSheet<ZoneSelection>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
      child: _ZonePickerSheet(
        zones: zones,
        personnelName: personnelName,
        initialZone: initialZone,
      ),
    ),
  );
}

class _ZonePickerSheet extends StatefulWidget {
  const _ZonePickerSheet({
    required this.zones,
    this.personnelName,
    this.initialZone,
  });

  final List<Zone> zones;
  final String? personnelName;
  final String? initialZone;

  @override
  State<_ZonePickerSheet> createState() => _ZonePickerSheetState();
}

class _ZonePickerSheetState extends State<_ZonePickerSheet> {
  ZoneSelection _selection = ZoneSelection.none;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Görev alanı seçin', style: theme.textTheme.titleLarge),
            if (widget.personnelName != null && widget.personnelName!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  widget.personnelName!,
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(color: theme.colorScheme.primary),
                ),
              ),
            const SizedBox(height: 16),
            ZoneSelector(
              zones: widget.zones,
              initialZone: widget.initialZone,
              onChanged: (s) => setState(() => _selection = s),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(_selection),
              icon: const Icon(Icons.check),
              label: Text(_selection.isEmpty ? 'Alan seçmeden devam' : 'Devam'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Alan seçim bileşeni: alan çipleri + QR ile alan okut + serbest metin.
/// Hem alan alt sayfasında hem giriş akışı içinde kullanılır.
class ZoneSelector extends StatefulWidget {
  const ZoneSelector({
    super.key,
    required this.zones,
    required this.onChanged,
    this.initialZone,
  });

  final List<Zone> zones;
  final ValueChanged<ZoneSelection> onChanged;
  final String? initialZone;

  @override
  State<ZoneSelector> createState() => _ZoneSelectorState();
}

class _ZoneSelectorState extends State<ZoneSelector> {
  Zone? _selected;
  String? _scannedPayload;
  late final TextEditingController _freeTextCtrl;

  @override
  void initState() {
    super.initState();
    _freeTextCtrl = TextEditingController();
    final initial = widget.initialZone;
    if (initial != null && initial.isNotEmpty) {
      final match = widget.zones.where((z) => z.name == initial).firstOrNull;
      if (match != null) {
        _selected = match;
      } else {
        _freeTextCtrl.text = initial;
      }
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) widget.onChanged(_current);
    });
  }

  @override
  void dispose() {
    _freeTextCtrl.dispose();
    super.dispose();
  }

  ZoneSelection get _current {
    final free = _freeTextCtrl.text.trim();
    return ZoneSelection(
      payload: _selected?.qrPayload ?? _scannedPayload,
      name: _selected?.name ?? (free.isEmpty ? null : free),
    );
  }

  void _emit() => widget.onChanged(_current);

  Future<void> _scanZone() async {
    final payload = await openScanner(
      context,
      title: 'Alan QR',
      hint: 'Alan / nokta QR kodunu okutun',
    );
    if (payload == null || !mounted) return;
    final match = widget.zones.where((z) => z.qrPayload == payload).firstOrNull;
    setState(() {
      _scannedPayload = payload;
      _selected = match;
      if (match != null) _freeTextCtrl.clear();
    });
    _emit();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (widget.zones.isNotEmpty) ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: widget.zones
                .map(
                  (z) => ChoiceChip(
                    label: Text(z.name),
                    selected: _selected?.id == z.id,
                    onSelected: (_) {
                      setState(() {
                        _selected = _selected?.id == z.id ? null : z;
                        _scannedPayload = null;
                        if (_selected != null) _freeTextCtrl.clear();
                      });
                      _emit();
                    },
                  ),
                )
                .toList(),
          ),
          const SizedBox(height: 16),
        ] else
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              'Bu gün için tanımlı alan yok. QR okutabilir ya da alan adını yazabilirsiniz.',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
            ),
          ),
        OutlinedButton.icon(
          onPressed: _scanZone,
          icon: const Icon(Icons.qr_code_scanner),
          label: Text(
            _scannedPayload != null && _selected == null
                ? 'Alan QR okundu: $_scannedPayload'
                : 'QR ile alan okut',
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _freeTextCtrl,
          textCapitalization: TextCapitalization.sentences,
          onChanged: (_) {
            setState(() {
              if (_freeTextCtrl.text.isNotEmpty) _selected = null;
            });
            _emit();
          },
          decoration: const InputDecoration(
            labelText: 'Alan adı (serbest metin)',
            hintText: 'Örn. Ana giriş, Sahne arkası',
            prefixIcon: Icon(Icons.edit_location_alt_outlined),
            isDense: true,
          ),
        ),
      ],
    );
  }
}
