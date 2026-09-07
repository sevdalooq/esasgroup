import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'field_repository.dart';
import 'models/models.dart';

/// Bugünkü proje günleri.
final todayProvider = FutureProvider.autoDispose<List<ProjectDaySummary>>(
  (ref) => ref.watch(fieldRepositoryProvider).today(),
);

/// Bir proje gününün detayı.
final dayDetailProvider =
    FutureProvider.autoDispose.family<ProjectDayDetail, int>(
  (ref, id) => ref.watch(fieldRepositoryProvider).day(id),
);

/// Masraf kategorileri (oturum boyunca önbellekte; hata olursa sabit liste).
final expenseCategoriesProvider = FutureProvider<List<ExpenseCategory>>(
  (ref) async {
    try {
      return await ref.watch(fieldRepositoryProvider).expenseCategories();
    } catch (_) {
      return ExpenseCategory.defaults;
    }
  },
);

/// Gün akışının UI aşaması. `end` sunucu durumu değil; "Gün Sonu Akışını
/// Başlat" ile yerel olarak girilir ve gün `completed` olana kadar sürer.
enum DayPhase { start, active, end, completed, readOnly }

/// Bir gün için yerel akış durumu: aşama başına adım ve gün sonu akışı bayrağı.
class DayFlowState {
  const DayFlowState({
    this.startStep = 0,
    this.endStep = 0,
    this.closingStarted = false,
  });

  /// A aşaması (Gün Başlangıcı) adımı: 0 giriş · 1 özet · 2 fotoğraf
  final int startStep;

  /// C aşaması (Gün Sonu) adımı: 0 çıkış · 1 özet · 2 fotoğraf
  final int endStep;

  /// Etkinlik sürerken "Gün Sonu Akışını Başlat" seçildi mi.
  final bool closingStarted;

  DayFlowState copyWith({int? startStep, int? endStep, bool? closingStarted}) =>
      DayFlowState(
        startStep: startStep ?? this.startStep,
        endStep: endStep ?? this.endStep,
        closingStarted: closingStarted ?? this.closingStarted,
      );
}

class DayFlowNotifier extends FamilyNotifier<DayFlowState, int> {
  @override
  DayFlowState build(int arg) => const DayFlowState();

  void setStartStep(int step) => state = state.copyWith(startStep: step);
  void setEndStep(int step) => state = state.copyWith(endStep: step);

  void startClosing() => state = state.copyWith(closingStarted: true, endStep: 0);
  void cancelClosing() => state = state.copyWith(closingStarted: false, endStep: 0);
}

/// Gün başına yerel akış durumu (ekran kapanıp açılsa da oturum boyunca kalır).
final dayFlowProvider =
    NotifierProvider.family<DayFlowNotifier, DayFlowState, int>(DayFlowNotifier.new);

/// Sunucu durumu + yerel bayraktan UI aşaması.
DayPhase phaseOf(ProjectDayDetail detail, DayFlowState flow) {
  if (detail.isCompleted) return DayPhase.completed;
  if (detail.isPending) return DayPhase.start;
  if (detail.isActive) return flow.closingStarted ? DayPhase.end : DayPhase.active;
  return DayPhase.readOnly;
}
