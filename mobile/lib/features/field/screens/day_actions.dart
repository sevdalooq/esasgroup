import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/widgets/ui_helpers.dart';
import '../field_providers.dart';

/// Sekmelerin ortak "işlemi çalıştır → bildir → yenile" yardımcısı.
class DayActions {
  DayActions({required this.context, required this.ref, required this.dayId});

  final BuildContext context;
  final WidgetRef ref;
  final int dayId;

  /// [task] başarılıysa true döner; hata mesajını snackbar ile gösterir.
  Future<bool> run(
    Future<void> Function() task, {
    required String success,
    String progress = 'İşleniyor…',
  }) async {
    try {
      await withProgress(context, task, message: progress);
      if (context.mounted) showSnack(context, success);
      refresh();
      return true;
    } catch (e) {
      if (context.mounted) showSnack(context, errorMessage(e), error: true);
      return false;
    }
  }

  void refresh() {
    ref.invalidate(dayDetailProvider(dayId));
    ref.invalidate(todayProvider);
  }
}
