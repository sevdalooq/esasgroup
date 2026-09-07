import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'models/self_models.dart';
import 'self_repository.dart';

/// Personelin görevleri (`GET /me/assignments`).
final myAssignmentsProvider = FutureProvider.autoDispose<SelfAssignments>(
  (ref) => ref.watch(selfRepositoryProvider).assignments(),
);
