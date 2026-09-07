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
