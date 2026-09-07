import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

/// Fotoğraf seçim sonucu: [file] null ise fotoğrafsız devam edilmiştir,
/// [cancelled] true ise kullanıcı işlemi iptal etmiştir.
class PhotoResult {
  const PhotoResult({this.file, this.cancelled = false});

  final XFile? file;
  final bool cancelled;

  static const PhotoResult skipped = PhotoResult();
  static const PhotoResult canceled = PhotoResult(cancelled: true);
}

final ImagePicker _picker = ImagePicker();

Future<XFile?> _pick(ImageSource source) async {
  try {
    return await _picker.pickImage(
      source: source,
      imageQuality: 70,
      maxWidth: 1600,
      maxHeight: 1600,
    );
  } catch (_) {
    return null;
  }
}

/// Kullanıcıya kamera / galeri / fotoğrafsız devam seçenekleri sunar.
Future<PhotoResult> askForPhoto(
  BuildContext context, {
  String title = 'Fotoğraf ekle',
  bool allowSkip = true,
}) async {
  final source = await showModalBottomSheet<_PhotoChoice>(
    context: context,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Text(title, style: Theme.of(ctx).textTheme.titleMedium),
          ),
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Kamera ile çek'),
            onTap: () => Navigator.of(ctx).pop(_PhotoChoice.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Galeriden seç'),
            onTap: () => Navigator.of(ctx).pop(_PhotoChoice.gallery),
          ),
          if (allowSkip)
            ListTile(
              leading: const Icon(Icons.skip_next_outlined),
              title: const Text('Fotoğrafsız devam et'),
              onTap: () => Navigator.of(ctx).pop(_PhotoChoice.skip),
            ),
          const SizedBox(height: 8),
        ],
      ),
    ),
  );

  switch (source) {
    case null:
      return PhotoResult.canceled;
    case _PhotoChoice.skip:
      return PhotoResult.skipped;
    case _PhotoChoice.camera:
      final file = await _pick(ImageSource.camera);
      if (file == null && context.mounted) {
        _notify(context, 'Kamera kullanılamadı, fotoğrafsız devam ediliyor.');
      }
      return PhotoResult(file: file);
    case _PhotoChoice.gallery:
      final file = await _pick(ImageSource.gallery);
      return PhotoResult(file: file);
  }
}

void _notify(BuildContext context, String message) {
  ScaffoldMessenger.maybeOf(context)
      ?.showSnackBar(SnackBar(content: Text(message)));
}

enum _PhotoChoice { camera, gallery, skip }
