import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/ui_helpers.dart';
import '../../scanner/qr_payload.dart';
import '../../scanner/scan_helpers.dart';
import '../models/models.dart';
import 'photo_widgets.dart';
import 'zone_picker_sheet.dart';

/// Giriş akışının sonucu: alan, isteğe bağlı fotoğraf ve teslim edilecek zimmet.
class CheckInPlan {
  const CheckInPlan({
    this.zone = ZoneSelection.none,
    this.photo,
    this.inventory = const [],
    this.extraPayloads = const [],
  });

  final ZoneSelection zone;
  final XFile? photo;

  /// Gün listesinden seçilen (henüz teslim edilmemiş) envanter.
  final List<InventoryAssignment> inventory;

  /// Listede olmayan, QR ile okutulan envanter payload'ları.
  final List<String> extraPayloads;

  int get deliveryCount => inventory.length + extraPayloads.length;
}

/// Giriş alt sayfası: Alan → Fotoğraf → Zimmet → Onay.
Future<CheckInPlan?> showCheckInSheet(
  BuildContext context, {
  required String personnelName,
  required List<Zone> zones,
  required List<InventoryAssignment> undelivered,
  String? initialZone,
  bool isNewPersonnel = false,
}) {
  return showModalBottomSheet<CheckInPlan>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    useSafeArea: true,
    builder: (ctx) => _CheckInSheet(
      personnelName: personnelName,
      zones: zones,
      undelivered: undelivered,
      initialZone: initialZone,
      isNewPersonnel: isNewPersonnel,
    ),
  );
}

class _CheckInSheet extends StatefulWidget {
  const _CheckInSheet({
    required this.personnelName,
    required this.zones,
    required this.undelivered,
    this.initialZone,
    this.isNewPersonnel = false,
  });

  final String personnelName;
  final List<Zone> zones;
  final List<InventoryAssignment> undelivered;
  final String? initialZone;
  final bool isNewPersonnel;

  @override
  State<_CheckInSheet> createState() => _CheckInSheetState();
}

class _CheckInSheetState extends State<_CheckInSheet> {
  static const _titles = ['Alan', 'Fotoğraf', 'Zimmet', 'Onay'];

  int _step = 0;
  ZoneSelection _zone = ZoneSelection.none;
  XFile? _photo;
  final Set<int> _selectedIds = {};
  final List<String> _extraPayloads = [];
  String _query = '';

  List<InventoryAssignment> get _selected =>
      widget.undelivered.where((i) => _selectedIds.contains(i.id)).toList();

  void _next() {
    if (_step < _titles.length - 1) {
      setState(() => _step++);
    } else {
      Navigator.of(context).pop(
        CheckInPlan(
          zone: _zone,
          photo: _photo,
          inventory: _selected,
          extraPayloads: List.unmodifiable(_extraPayloads),
        ),
      );
    }
  }

  void _back() {
    if (_step > 0) setState(() => _step--);
  }

  Future<void> _scanInventory() async {
    final payload = await openScanner(
      context,
      title: 'Zimmet – Envanter QR',
      hint: 'Teslim edilecek envanterin QR kodunu okutun',
    );
    if (payload == null || !mounted) return;
    final type = QrPayload.typeOf(payload);
    if (type == QrType.personnel || type == QrType.zone) {
      showSnack(context, 'Bu kod bir ${QrPayload.typeLabel(type)} kodu; envanter QR okutun.', error: true);
      return;
    }
    final match = widget.undelivered.where((i) => i.qrPayload == payload).firstOrNull;
    setState(() {
      if (match != null) {
        _selectedIds.add(match.id);
      } else if (!_extraPayloads.contains(payload)) {
        _extraPayloads.add(payload);
      }
    });
    showSnack(context, match != null ? '${match.displayName} seçildi' : 'Kod eklendi: $payload');
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final height = MediaQuery.of(context).size.height * 0.85;

    return AnimatedPadding(
      duration: const Duration(milliseconds: 150),
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Personel Girişi', style: theme.textTheme.titleLarge),
                        Text(
                          widget.personnelName +
                              (widget.isNewPersonnel ? ' · son dakika ekleniyor' : ''),
                          style: theme.textTheme.bodyMedium
                              ?.copyWith(color: theme.colorScheme.primary),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '${_step + 1}/${_titles.length} ${_titles[_step]}',
                    style: theme.textTheme.labelLarge?.copyWith(color: theme.colorScheme.outline),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 6),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: (_step + 1) / _titles.length,
                  minHeight: 4,
                  backgroundColor: theme.colorScheme.surfaceContainerHighest,
                ),
              ),
            ),
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 200),
                child: KeyedSubtree(key: ValueKey(_step), child: _buildStep()),
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
              child: Row(
                children: [
                  if (_step > 0) ...[
                    Expanded(
                      flex: 2,
                      child: OutlinedButton.icon(
                        onPressed: _back,
                        icon: const Icon(Icons.arrow_back),
                        label: const Text('Geri'),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    flex: 3,
                    child: FilledButton.icon(
                      onPressed: _next,
                      icon: Icon(_step == _titles.length - 1 ? Icons.login : Icons.arrow_forward),
                      label: Text(_primaryLabel),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String get _primaryLabel {
    switch (_step) {
      case 0:
        return _zone.isEmpty ? 'Alan seçmeden devam' : 'Devam';
      case 1:
        return _photo == null ? 'Fotoğrafsız devam' : 'Devam';
      case 2:
        final n = _selectedIds.length + _extraPayloads.length;
        return n == 0 ? 'Zimmetsiz devam' : 'Devam ($n zimmet)';
      default:
        return 'Girişi Kaydet';
    }
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _zoneStep();
      case 1:
        return _photoStep();
      case 2:
        return _inventoryStep();
      default:
        return _confirmStep();
    }
  }

  Widget _zoneStep() {
    final theme = Theme.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Görev alanı', style: theme.textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(
            'Personelin bugün görev yapacağı alanı seçin.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
          ),
          const SizedBox(height: 12),
          ZoneSelector(
            zones: widget.zones,
            initialZone: widget.initialZone,
            onChanged: (s) => setState(() => _zone = s),
          ),
        ],
      ),
    );
  }

  Widget _photoStep() {
    final theme = Theme.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Giriş fotoğrafı (isteğe bağlı)', style: theme.textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(
            'Personelin sahadaki fotoğrafını çekebilirsiniz.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
          ),
          const SizedBox(height: 12),
          PhotoCapture(
            value: _photo,
            onChanged: (f) => setState(() => _photo = f),
            placeholder: 'Giriş fotoğrafı',
          ),
        ],
      ),
    );
  }

  Widget _inventoryStep() {
    final theme = Theme.of(context);
    final q = _query.toLowerCase();
    final items = widget.undelivered
        .where((i) =>
            q.isEmpty ||
            i.displayName.toLowerCase().contains(q) ||
            i.serialNumber.toLowerCase().contains(q))
        .toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Zimmet teslim et', style: theme.textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(
                'Bu personele teslim edilecek envanteri işaretleyin ya da QR okutun.',
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      onChanged: (v) => setState(() => _query = v),
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.search),
                        hintText: 'Envanter ara',
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton.tonalIcon(
                    onPressed: _scanInventory,
                    icon: const Icon(Icons.qr_code_scanner),
                    label: const Text('QR'),
                  ),
                ],
              ),
            ],
          ),
        ),
        Expanded(
          child: items.isEmpty && _extraPayloads.isEmpty
              ? EmptyView(
                  icon: Icons.inventory_2_outlined,
                  title: widget.undelivered.isEmpty
                      ? 'Teslim bekleyen envanter yok'
                      : 'Envanter bulunamadı',
                  subtitle: 'QR okutarak listede olmayan envanteri de teslim edebilirsiniz.',
                )
              : ListView(
                  children: [
                    for (final payload in _extraPayloads)
                      ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.qr_code_2)),
                        title: Text(payload, maxLines: 1, overflow: TextOverflow.ellipsis),
                        subtitle: const Text('QR ile eklendi'),
                        trailing: IconButton(
                          icon: const Icon(Icons.close),
                          onPressed: () => setState(() => _extraPayloads.remove(payload)),
                        ),
                      ),
                    for (final item in items)
                      CheckboxListTile(
                        value: _selectedIds.contains(item.id),
                        onChanged: (v) => setState(() {
                          if (v == true) {
                            _selectedIds.add(item.id);
                          } else {
                            _selectedIds.remove(item.id);
                          }
                        }),
                        controlAffinity: ListTileControlAffinity.leading,
                        title: Text(item.displayName),
                        subtitle: Text(
                          [
                            if (item.serialNumber.isNotEmpty) 'SN: ${item.serialNumber}',
                            if (item.quantity > 1) 'Adet: ${item.quantity}',
                          ].join(' · '),
                        ),
                      ),
                  ],
                ),
        ),
      ],
    );
  }

  Widget _confirmStep() {
    final theme = Theme.of(context);
    final selected = _selected;
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      children: [
        Text('Özet', style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        _SummaryTile(
          icon: Icons.person_outline,
          label: 'Personel',
          value: widget.personnelName,
        ),
        _SummaryTile(
          icon: Icons.place_outlined,
          label: 'Alan',
          value: _zone.isEmpty ? 'Seçilmedi' : _zone.label,
        ),
        _SummaryTile(
          icon: Icons.photo_camera_outlined,
          label: 'Fotoğraf',
          value: _photo == null ? 'Yok' : 'Eklendi',
        ),
        _SummaryTile(
          icon: Icons.inventory_2_outlined,
          label: 'Zimmet',
          value: selected.isEmpty && _extraPayloads.isEmpty
              ? 'Yok'
              : [
                  ...selected.map((i) => i.displayName),
                  ..._extraPayloads,
                ].join(', '),
        ),
        const SizedBox(height: 12),
        Text(
          'Kaydet dediğinizde giriş ve seçilen zimmet teslimleri sırayla sunucuya gönderilir.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
      ],
    );
  }
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: theme.colorScheme.primary),
      title: Text(label, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
      subtitle: Text(value, style: theme.textTheme.bodyLarge),
    );
  }
}
