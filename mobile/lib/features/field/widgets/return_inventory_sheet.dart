import 'package:flutter/material.dart';

class ReturnInfo {
  const ReturnInfo({required this.damaged, this.description});

  final bool damaged;
  final String? description;
}

/// İade alt sayfası: hasar anahtarı + açıklama.
Future<ReturnInfo?> showReturnInventorySheet(
  BuildContext context, {
  required String itemName,
}) {
  return showModalBottomSheet<ReturnInfo>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
      child: _ReturnSheet(itemName: itemName),
    ),
  );
}

class _ReturnSheet extends StatefulWidget {
  const _ReturnSheet({required this.itemName});

  final String itemName;

  @override
  State<_ReturnSheet> createState() => _ReturnSheetState();
}

class _ReturnSheetState extends State<_ReturnSheet> {
  bool _damaged = false;
  final _descCtrl = TextEditingController();

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

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
            Text('Teslim Al', style: theme.textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(
              widget.itemName,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: theme.colorScheme.primary),
            ),
            const SizedBox(height: 12),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Hasarlı'),
              subtitle: const Text('Ürün hasarlı ya da eksik döndüyse işaretleyin'),
              value: _damaged,
              onChanged: (v) => setState(() => _damaged = v),
            ),
            AnimatedSize(
              duration: const Duration(milliseconds: 200),
              child: _damaged
                  ? Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: TextField(
                        controller: _descCtrl,
                        maxLines: 3,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          labelText: 'Hasar açıklaması',
                          hintText: 'Hasarın türünü ve yerini yazın',
                          alignLabelWithHint: true,
                        ),
                      ),
                    )
                  : const SizedBox.shrink(),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(
                ReturnInfo(
                  damaged: _damaged,
                  description: _damaged ? _descCtrl.text.trim() : null,
                ),
              ),
              icon: const Icon(Icons.assignment_return_outlined),
              label: Text(_damaged ? 'Hasarlı olarak teslim al' : 'Teslim al'),
            ),
          ],
        ),
      ),
    );
  }
}
