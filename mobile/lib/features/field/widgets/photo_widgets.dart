import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/utils/photo_picker.dart';

/// Sunucudaki fotoğrafın küçük resmi; dokununca büyütür.
class NetworkPhotoTile extends StatelessWidget {
  const NetworkPhotoTile({super.key, required this.label, required this.url});

  final String label;
  final String? url;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AspectRatio(
          aspectRatio: 4 / 3,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: url == null
                ? Container(
                    color: theme.colorScheme.surfaceContainerHighest,
                    child: Center(
                      child: Icon(Icons.image_not_supported_outlined,
                          color: theme.colorScheme.outline, size: 32),
                    ),
                  )
                : GestureDetector(
                    onTap: () => showDialog<void>(
                      context: context,
                      builder: (_) => Dialog(
                        insetPadding: const EdgeInsets.all(12),
                        child: InteractiveViewer(
                          child: CachedNetworkImage(imageUrl: url!, fit: BoxFit.contain),
                        ),
                      ),
                    ),
                    child: CachedNetworkImage(
                      imageUrl: url!,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(
                        color: theme.colorScheme.surfaceContainerHighest,
                        child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                      ),
                      errorWidget: (_, __, ___) => Container(
                        color: theme.colorScheme.surfaceContainerHighest,
                        child: const Center(child: Icon(Icons.broken_image_outlined)),
                      ),
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 6),
        Text(
          url == null ? '$label · fotoğraf yok' : label,
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline),
        ),
      ],
    );
  }
}

/// Kamera / galeri ile fotoğraf seçimi ve önizleme (denetimli bileşen).
class PhotoCapture extends StatelessWidget {
  const PhotoCapture({
    super.key,
    required this.value,
    required this.onChanged,
    this.compact = false,
    this.placeholder = 'Fotoğraf ekleyin',
  });

  final XFile? value;
  final ValueChanged<XFile?> onChanged;

  /// Küçük (alt sayfa içi) görünüm.
  final bool compact;
  final String placeholder;

  Future<void> _pick(BuildContext context, ImageSource source) async {
    final file = await pickPhoto(source);
    if (file != null) {
      onChanged(file);
    } else if (source == ImageSource.camera && context.mounted) {
      ScaffoldMessenger.maybeOf(context)?.showSnackBar(
        const SnackBar(content: Text('Kamera kullanılamadı.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final file = value;

    if (file != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AspectRatio(
            aspectRatio: compact ? 16 / 9 : 4 / 3,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.file(File(file.path), fit: BoxFit.cover),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pick(context, ImageSource.camera),
                  icon: const Icon(Icons.photo_camera_outlined),
                  label: const Text('Yeniden çek'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => onChanged(null),
                  style: OutlinedButton.styleFrom(foregroundColor: scheme.error),
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Kaldır'),
                ),
              ),
            ],
          ),
        ],
      );
    }

    final buttons = Row(
      children: [
        Expanded(
          child: FilledButton.tonalIcon(
            onPressed: () => _pick(context, ImageSource.camera),
            style: FilledButton.styleFrom(
              padding: EdgeInsets.symmetric(vertical: compact ? 10 : 16),
            ),
            icon: const Icon(Icons.photo_camera_outlined),
            label: const Text('Kamera'),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: () => _pick(context, ImageSource.gallery),
            style: OutlinedButton.styleFrom(
              padding: EdgeInsets.symmetric(vertical: compact ? 10 : 16),
            ),
            icon: const Icon(Icons.photo_library_outlined),
            label: const Text('Galeri'),
          ),
        ),
      ],
    );

    if (compact) return buttons;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AspectRatio(
          aspectRatio: 4 / 3,
          child: Container(
            decoration: BoxDecoration(
              color: scheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: scheme.outlineVariant),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.add_a_photo_outlined, size: 48, color: scheme.outline),
                const SizedBox(height: 8),
                Text(placeholder, style: TextStyle(color: scheme.outline)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        buttons,
      ],
    );
  }
}
