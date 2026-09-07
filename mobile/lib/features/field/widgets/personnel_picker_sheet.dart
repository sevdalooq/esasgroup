import 'package:flutter/material.dart';

import '../../../core/widgets/ui_helpers.dart';
import '../models/models.dart';

/// Envanter teslimi için personel seçimi. `PersonnelPick.none` = personelsiz.
class PersonnelPick {
  const PersonnelPick(this.assignment);

  final PersonnelAssignment? assignment;

  static const none = PersonnelPick(null);
}

Future<PersonnelPick?> showPersonnelPicker(
  BuildContext context, {
  required List<PersonnelAssignment> personnel,
  required String itemName,
}) {
  return showModalBottomSheet<PersonnelPick>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) => _PersonnelPickerSheet(personnel: personnel, itemName: itemName),
  );
}

class _PersonnelPickerSheet extends StatefulWidget {
  const _PersonnelPickerSheet({required this.personnel, required this.itemName});

  final List<PersonnelAssignment> personnel;
  final String itemName;

  @override
  State<_PersonnelPickerSheet> createState() => _PersonnelPickerSheetState();
}

class _PersonnelPickerSheetState extends State<_PersonnelPickerSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final q = _query.toLowerCase();
    final filtered = widget.personnel
        .where((p) => q.isEmpty || p.displayName.toLowerCase().contains(q))
        .toList();

    return SafeArea(
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.7,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Kime teslim ediliyor?', style: theme.textTheme.titleLarge),
                  const SizedBox(height: 2),
                  Text(
                    widget.itemName,
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(color: theme.colorScheme.primary),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    onChanged: (v) => setState(() => _query = v),
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.search),
                      hintText: 'Personel ara',
                      isDense: true,
                    ),
                  ),
                ],
              ),
            ),
            ListTile(
              leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
              title: const Text('Personel seçmeden teslim et'),
              subtitle: const Text('Sadece gün stoğuna teslim'),
              onTap: () => Navigator.of(context).pop(PersonnelPick.none),
            ),
            const Divider(height: 1),
            Expanded(
              child: filtered.isEmpty
                  ? const EmptyView(
                      icon: Icons.person_search_outlined,
                      title: 'Personel bulunamadı',
                    )
                  : ListView.builder(
                      itemCount: filtered.length,
                      itemBuilder: (context, index) {
                        final p = filtered[index];
                        return ListTile(
                          leading: InitialsAvatar(name: p.displayName, radius: 18),
                          title: Text(p.displayName),
                          subtitle: Text(
                            p.isCheckedIn
                                ? 'Giriş yaptı${p.zone.isNotEmpty ? ' · ${p.zone}' : ''}'
                                : 'Henüz giriş yapmadı',
                          ),
                          trailing: p.isCheckedIn
                              ? const Icon(Icons.check_circle, color: Color(0xFF2E7D32))
                              : null,
                          onTap: () => Navigator.of(context).pop(PersonnelPick(p)),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
