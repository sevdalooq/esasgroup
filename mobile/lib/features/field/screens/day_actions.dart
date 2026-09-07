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

  /// [task] başarılıysa true döner; sunucunun `message` alanı varsa onu,
  /// yoksa [success] metnini snackbar ile gösterir. Hata mesajı da snackbar'da.
  Future<bool> run(
    Future<String?> Function() task, {
    required String success,
    String progress = 'İşleniyor…',
  }) async {
    try {
      final message = await withProgress(context, task, message: progress);
      if (context.mounted) {
        showSnack(context, (message == null || message.isEmpty) ? success : message);
      }
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
