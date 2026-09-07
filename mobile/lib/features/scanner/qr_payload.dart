import '../../core/config/app_config.dart';

enum QrType { personnel, inventory, zone, unknown }

/// `ESAS:PER:<uuid>`, `ESAS:INV:<uuid>`, `ESAS:ZONE:<uuid>` payload yardımcıları.
class QrPayload {
  QrPayload._();

  static String normalize(String raw) => raw.trim();

  static bool isEsas(String payload) =>
      normalize(payload).toUpperCase().startsWith(AppConfig.qrPrefix);

  static QrType typeOf(String payload) {
    final upper = normalize(payload).toUpperCase();
    if (upper.startsWith('ESAS:PER:')) return QrType.personnel;
    if (upper.startsWith('ESAS:INV:')) return QrType.inventory;
    if (upper.startsWith('ESAS:ZONE:')) return QrType.zone;
    return QrType.unknown;
  }

  static String typeLabel(QrType type) {
    switch (type) {
      case QrType.personnel:
        return 'personel';
      case QrType.inventory:
        return 'envanter';
      case QrType.zone:
        return 'alan';
      case QrType.unknown:
        return 'bilinmeyen';
    }
  }
}
