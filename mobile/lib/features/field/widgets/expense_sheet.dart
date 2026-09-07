import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/utils/formatters.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';
import 'photo_widgets.dart';

/// Masraf formu sonucu.
class ExpenseDraft {
  const ExpenseDraft({
    required this.description,
    required this.amount,
    required this.category,
    this.receiptPhoto,
  });

  final String description;
  final double amount;

  /// Kategori slug'ı (food | transport | material | accommodation | other)
  final String category;
  final XFile? receiptPhoto;
}

/// Kategori slug'ı → Material ikon.
IconData expenseCategoryIcon(String slug) {
  switch (slug) {
    case 'food':
      return Icons.restaurant_outlined;
    case 'transport':
      return Icons.directions_car_outlined;
    case 'material':
      return Icons.inventory_2_outlined;
    case 'accommodation':
      return Icons.hotel_outlined;
    default:
      return Icons.more_horiz;
  }
}

/// Masraf ekleme alt sayfası: kategori çipleri, açıklama, tutar, fiş fotoğrafı.
Future<ExpenseDraft?> showExpenseSheet(
  BuildContext context, {
  required List<ExpenseCategory> categories,
}) {
  return showModalBottomSheet<ExpenseDraft>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    useSafeArea: true,
    builder: (ctx) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
      child: _ExpenseSheet(categories: categories.isEmpty ? ExpenseCategory.defaults : categories),
    ),
  );
}

class _ExpenseSheet extends StatefulWidget {
  const _ExpenseSheet({required this.categories});

  final List<ExpenseCategory> categories;

  @override
  State<_ExpenseSheet> createState() => _ExpenseSheetState();
}

class _ExpenseSheetState extends State<_ExpenseSheet> {
  late String _category;
  final _descCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  XFile? _photo;

  @override
  void initState() {
    super.initState();
    _category = widget.categories.first.slug;
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    _amountCtrl.dispose();
    super.dispose();
  }

  void _submit() {
    final desc = _descCtrl.text.trim();
    final amount = parseDecimal(_amountCtrl.text);
    if (desc.isEmpty) {
      showSnack(context, 'Açıklama girin.', error: true);
      return;
    }
    if (amount == null || amount <= 0) {
      showSnack(context, 'Geçerli bir tutar girin.', error: true);
      return;
    }
    Navigator.of(context).pop(
      ExpenseDraft(
        description: desc,
        amount: amount,
        category: _category,
        receiptPhoto: _photo,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Masraf Ekle', style: theme.textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(
            'Kaydedilen masraf muhasebe onayına düşer.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
          ),
          const SizedBox(height: 14),
          Text('Kategori', style: theme.textTheme.labelLarge),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              for (final c in widget.categories)
                ChoiceChip(
                  avatar: Icon(expenseCategoryIcon(c.slug), size: 18),
                  label: Text(c.name),
                  selected: _category == c.slug,
                  onSelected: (_) => setState(() => _category = c.slug),
                ),
            ],
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _descCtrl,
            textCapitalization: TextCapitalization.sentences,
            decoration: const InputDecoration(
              labelText: 'Açıklama',
              hintText: 'Örn. Ekip öğle yemeği',
              prefixIcon: Icon(Icons.notes),
              isDense: true,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _amountCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              labelText: 'Tutar',
              suffixText: '₺',
              prefixIcon: Icon(Icons.payments_outlined),
              isDense: true,
            ),
          ),
          const SizedBox(height: 14),
          Text('Fiş / fatura fotoğrafı (isteğe bağlı)', style: theme.textTheme.labelLarge),
          const SizedBox(height: 6),
          PhotoCapture(
            value: _photo,
            onChanged: (f) => setState(() => _photo = f),
            compact: true,
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: _submit,
            style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
            icon: const Icon(Icons.save_outlined),
            label: const Text('Masrafı Kaydet'),
          ),
        ],
      ),
    );
  }
}
