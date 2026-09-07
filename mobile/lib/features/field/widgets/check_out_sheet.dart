import 'package:flutter/material.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';

/// Çıkış alt sayfası: (a) saat + mesai → (b) zimmet iadesi → (c) ödeme.
/// Sonuç: sunucuya gönderilecek [CheckOutRequest].
Future<CheckOutRequest?> showCheckOutSheet(
  BuildContext context, {
  required PersonnelAssignment assignment,
  required List<InventoryAssignment> heldInventory,
}) {
  return showModalBottomSheet<CheckOutRequest>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    useSafeArea: true,
    builder: (ctx) => _CheckOutSheet(assignment: assignment, heldInventory: heldInventory),
  );
}

enum _ReturnChoice { returned, damaged, keep }

class _ReturnDraft {
  _ReturnDraft();

  _ReturnChoice choice = _ReturnChoice.returned;
  final descCtrl = TextEditingController();
  final deductionCtrl = TextEditingController();

  void dispose() {
    descCtrl.dispose();
    deductionCtrl.dispose();
  }
}

class _CheckOutSheet extends StatefulWidget {
  const _CheckOutSheet({required this.assignment, required this.heldInventory});

  final PersonnelAssignment assignment;
  final List<InventoryAssignment> heldInventory;

  @override
  State<_CheckOutSheet> createState() => _CheckOutSheetState();
}

class _CheckOutSheetState extends State<_CheckOutSheet> {
  static const _titles = ['Çıkış Saati', 'Zimmet İadesi', 'Ödeme'];

  int _step = 0;

  // (a) saat + mesai
  late TimeOfDay _time;
  bool _overtime = false;
  final _hoursCtrl = TextEditingController(text: '1');
  late final TextEditingController _rateCtrl;

  // (b) iadeler
  final Map<int, _ReturnDraft> _returns = {};

  // (c) ödeme
  String _paymentStatus = 'paid';
  String _method = 'cash';
  final _amountCtrl = TextEditingController();
  bool _amountTouched = false;

  PersonnelAssignment get p => widget.assignment;

  @override
  void initState() {
    super.initState();
    _time = TimeOfDay.fromDateTime(DateTime.now());
    _rateCtrl = TextEditingController(text: formatInputNumber(p.suggestedOvertimeRate));
    for (final item in widget.heldInventory) {
      _returns[item.id] = _ReturnDraft();
    }
  }

  @override
  void dispose() {
    _hoursCtrl.dispose();
    _rateCtrl.dispose();
    _amountCtrl.dispose();
    for (final d in _returns.values) {
      d.dispose();
    }
    super.dispose();
  }

  double get _hours => _overtime ? (parseDecimal(_hoursCtrl.text) ?? 0) : 0;
  double get _rate => _overtime ? (parseDecimal(_rateCtrl.text) ?? 0) : 0;
  double get _overtimeTotal => _hours * _rate;
  double get _total => p.dailyWage + _overtimeTotal;

  double get _deductions => _returns.values.fold(
        0,
        (sum, d) => sum +
            (d.choice == _ReturnChoice.damaged ? (parseDecimal(d.deductionCtrl.text) ?? 0) : 0),
      );

  double get _amount {
    switch (_paymentStatus) {
      case 'paid':
        return _amountTouched ? (parseDecimal(_amountCtrl.text) ?? _total) : _total;
      case 'partial':
        return parseDecimal(_amountCtrl.text) ?? 0;
      default:
        return 0;
    }
  }

  double get _remaining => (_total - _amount).clamp(0, double.infinity);

  DateTime get _checkOutDateTime {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, _time.hour, _time.minute);
  }

  List<InventoryReturnEntry> get _returnEntries => [
        for (final entry in _returns.entries)
          if (entry.value.choice != _ReturnChoice.keep)
            InventoryReturnEntry(
              id: entry.key,
              returnStatus:
                  entry.value.choice == _ReturnChoice.damaged ? 'damaged' : 'returned',
              damageDescription: entry.value.descCtrl.text.trim(),
              deductionAmount: parseDecimal(entry.value.deductionCtrl.text),
            ),
      ];

  String? _validateStep() {
    switch (_step) {
      case 0:
        if (_overtime) {
          final h = parseDecimal(_hoursCtrl.text);
          if (h == null || h <= 0 || h > 16) return 'Mesai saati 0–16 arasında olmalı.';
          if ((parseDecimal(_rateCtrl.text) ?? -1) < 0) return 'Saat ücreti geçersiz.';
        }
        return null;
      case 1:
        for (final d in _returns.values) {
          if (d.choice == _ReturnChoice.damaged && d.descCtrl.text.trim().isEmpty) {
            return 'Hasarlı iade için açıklama yazın.';
          }
        }
        return null;
      default:
        if (_paymentStatus == 'partial') {
          final a = parseDecimal(_amountCtrl.text);
          if (a == null || a <= 0) return 'Kısmi ödeme tutarı girin.';
          if (a >= _total) return 'Kısmi tutar hakedişten küçük olmalı.';
        }
        if (_paymentStatus == 'paid' && _amountTouched) {
          final a = parseDecimal(_amountCtrl.text);
          if (a == null || a <= 0) return 'Ödeme tutarı geçersiz.';
        }
        return null;
    }
  }

  void _next() {
    final error = _validateStep();
    if (error != null) {
      showSnack(context, error, error: true);
      return;
    }
    if (_step < _titles.length - 1) {
      setState(() {
        _step++;
        // Ödeme adımına girerken "Şimdi Ödenecek" tutarını hakedişle doldur.
        if (_step == 2 && _paymentStatus == 'paid' && !_amountTouched) {
          _amountCtrl.text = formatInputNumber(_total);
        }
      });
      return;
    }
    Navigator.of(context).pop(
      CheckOutRequest(
        assignmentId: p.id,
        checkOutTime: _checkOutDateTime,
        overtimeHours: _hours,
        overtimeRate: _rate,
        paymentStatus: _paymentStatus,
        paymentMethod: _method,
        paymentAmount: _amount,
        inventoryReturns: _returnEntries,
      ),
    );
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: _time);
    if (picked != null) setState(() => _time = picked);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final height = MediaQuery.of(context).size.height * 0.88;

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
                        Text('Personel Çıkışı', style: theme.textTheme.titleLarge),
                        Text(
                          '${p.displayName} · Giriş ${formatTime(p.checkInTime)}',
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
                        onPressed: () => setState(() => _step--),
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
                      icon: Icon(_step == _titles.length - 1 ? Icons.logout : Icons.arrow_forward),
                      label: Text(_step == _titles.length - 1 ? 'Çıkışı Kaydet' : 'Devam'),
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

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _timeStep();
      case 1:
        return _returnStep();
      default:
        return _paymentStep();
    }
  }

  // ---------------- (a) Saat + mesai ----------------

  Widget _timeStep() {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      children: [
        Text('Çıkış saati', style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        Card(
          margin: EdgeInsets.zero,
          child: ListTile(
            leading: const Icon(Icons.schedule),
            title: Text(
              _time.format(context),
              style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            subtitle: const Text('Varsayılan: şimdi'),
            trailing: TextButton(onPressed: _pickTime, child: const Text('Değiştir')),
            onTap: _pickTime,
          ),
        ),
        const SizedBox(height: 16),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Mesaiye kaldı'),
          subtitle: const Text('Fazla çalışma saatini ve saat ücretini girin'),
          value: _overtime,
          onChanged: (v) => setState(() => _overtime = v),
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          child: !_overtime
              ? const SizedBox.shrink()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _hoursCtrl,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            onChanged: (_) => setState(() {}),
                            decoration: const InputDecoration(
                              labelText: 'Mesai saati',
                              suffixText: 'sa',
                              isDense: true,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextField(
                            controller: _rateCtrl,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            onChanged: (_) => setState(() {}),
                            decoration: const InputDecoration(
                              labelText: 'Saat ücreti',
                              suffixText: '₺',
                              isDense: true,
                              helperText: 'Varsayılan: yevmiye / 8',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
        ),
        const SizedBox(height: 16),
        _TotalsCard(
          rows: [
            ('Yevmiye', formatMoney(p.dailyWage)),
            if (_overtime) ('Mesai (${formatHours(_hours)} × ${formatMoney(_rate)})', formatMoney(_overtimeTotal)),
          ],
          total: ('Hakediş', formatMoney(_total)),
        ),
      ],
    );
  }

  // ---------------- (b) Zimmet iadesi ----------------

  Widget _returnStep() {
    final theme = Theme.of(context);
    final items = widget.heldInventory;
    if (items.isEmpty) {
      return const EmptyView(
        icon: Icons.inventory_2_outlined,
        title: 'Bu personele zimmetli envanter yok',
        subtitle: 'Devam ederek ödeme adımına geçebilirsiniz.',
      );
    }
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      children: [
        Text('Zimmet iadesi', style: theme.textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(
          'Her ürün için iade durumunu seçin. Hasarlı ürünlerde açıklama ve kesinti tutarı girin.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
        const SizedBox(height: 8),
        for (final item in items) _returnCard(item, _returns[item.id]!),
        if (_deductions > 0)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'Toplam kesinti: ${formatMoney(_deductions)}',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.error,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }

  Widget _returnCard(InventoryAssignment item, _ReturnDraft draft) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(item.displayName, style: theme.textTheme.titleSmall),
            if (item.serialNumber.isNotEmpty)
              Text('SN: ${item.serialNumber}',
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
            const SizedBox(height: 8),
            SegmentedButton<_ReturnChoice>(
              segments: const [
                ButtonSegment(
                  value: _ReturnChoice.returned,
                  label: Text('Sağlam'),
                  icon: Icon(Icons.check_circle_outline),
                ),
                ButtonSegment(
                  value: _ReturnChoice.damaged,
                  label: Text('Hasarlı'),
                  icon: Icon(Icons.report_problem_outlined),
                ),
                ButtonSegment(
                  value: _ReturnChoice.keep,
                  label: Text('Bekle'),
                  icon: Icon(Icons.schedule),
                ),
              ],
              selected: {draft.choice},
              showSelectedIcon: false,
              style: const ButtonStyle(visualDensity: VisualDensity.compact),
              onSelectionChanged: (s) => setState(() => draft.choice = s.first),
            ),
            AnimatedSize(
              duration: const Duration(milliseconds: 200),
              child: draft.choice != _ReturnChoice.damaged
                  ? const SizedBox.shrink()
                  : Column(
                      children: [
                        const SizedBox(height: 10),
                        TextField(
                          controller: draft.descCtrl,
                          maxLines: 2,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(
                            labelText: 'Hasar açıklaması',
                            isDense: true,
                          ),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: draft.deductionCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          onChanged: (_) => setState(() {}),
                          decoration: const InputDecoration(
                            labelText: 'Kesinti tutarı (isteğe bağlı)',
                            suffixText: '₺',
                            isDense: true,
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

  // ---------------- (c) Ödeme ----------------

  Widget _paymentStep() {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      children: [
        Text('Ödeme', style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'paid', label: Text('Şimdi Ödenecek'), icon: Icon(Icons.payments_outlined)),
            ButtonSegment(value: 'pending', label: Text('Sonradan'), icon: Icon(Icons.schedule)),
            ButtonSegment(value: 'partial', label: Text('Kısmi'), icon: Icon(Icons.call_split)),
          ],
          selected: {_paymentStatus},
          showSelectedIcon: false,
          onSelectionChanged: (s) => setState(() {
            _paymentStatus = s.first;
            _amountTouched = false;
            _amountCtrl.text = _paymentStatus == 'paid' ? formatInputNumber(_total) : '';
          }),
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          child: _paymentStatus == 'pending'
              ? Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(
                    'Ödeme daha sonra muhasebe tarafından yapılacak.',
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
                  ),
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 16),
                    Text('Ödeme yöntemi', style: theme.textTheme.labelLarge),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final m in const ['cash', 'bank', 'mixed'])
                          ChoiceChip(
                            label: Text(paymentMethodLabel(m)),
                            selected: _method == m,
                            onSelected: (_) => setState(() => _method = m),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _amountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      onChanged: (_) => setState(() => _amountTouched = true),
                      decoration: InputDecoration(
                        labelText: _paymentStatus == 'partial' ? 'Ödenen tutar' : 'Ödeme tutarı',
                        suffixText: '₺',
                        isDense: true,
                        helperText: _paymentStatus == 'paid'
                            ? 'Varsayılan: hakedişin tamamı'
                            : 'Kalan tutar sonradan ödenecek',
                      ),
                    ),
                  ],
                ),
        ),
        const SizedBox(height: 16),
        _TotalsCard(
          rows: [
            ('Yevmiye', formatMoney(p.dailyWage)),
            if (_overtime) ('Mesai', formatMoney(_overtimeTotal)),
            ('Hakediş', formatMoney(_total)),
            if (_deductions > 0) ('Kesinti (bilgi)', '- ${formatMoney(_deductions)}'),
            ('Ödenen', formatMoney(_amount)),
          ],
          total: ('Kalan', formatMoney(_remaining)),
        ),
      ],
    );
  }
}

class _TotalsCard extends StatelessWidget {
  const _TotalsCard({required this.rows, required this.total});

  final List<(String, String)> rows;
  final (String, String) total;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Column(
          children: [
            for (final row in rows)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(row.$1,
                          style: theme.textTheme.bodyMedium
                              ?.copyWith(color: theme.colorScheme.outline)),
                    ),
                    Text(row.$2, style: theme.textTheme.bodyMedium),
                  ],
                ),
              ),
            const Divider(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(total.$1,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                ),
                Text(total.$2,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: theme.colorScheme.primary,
                    )),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
