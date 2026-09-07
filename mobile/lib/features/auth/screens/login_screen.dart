import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _emailCtrl;
  final _passwordCtrl = TextEditingController();
  late final TextEditingController _serverCtrl;
  late final TextEditingController _wsCtrl;
  late final TextEditingController _wsKeyCtrl;

  bool _showServer = false;
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final auth = ref.read(authProvider);
    _emailCtrl = TextEditingController(text: auth.lastEmail ?? '');
    _serverCtrl = TextEditingController(
      text: auth.baseUrl.isEmpty ? AppConfig.defaultBaseUrl : auth.baseUrl,
    );
    _wsCtrl = TextEditingController(text: auth.wsUrl);
    _wsKeyCtrl = TextEditingController(
      text: auth.wsKey == AppConfig.reverbAppKey ? '' : auth.wsKey,
    );
    _showServer = (auth.baseUrl.isNotEmpty &&
            auth.baseUrl != AppConfig.defaultBaseUrl) ||
        auth.wsUrl.isNotEmpty ||
        _wsKeyCtrl.text.isNotEmpty;
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _serverCtrl.dispose();
    _wsCtrl.dispose();
    _wsKeyCtrl.dispose();
    super.dispose();
  }

  /// Sunucu alanına göre canlı bağlantı için önerilen adres (ipucu).
  String get _wsHint => AppConfig.defaultWsUrlFor(
        AppConfig.normalizeBaseUrl(_serverCtrl.text),
      );

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).login(
            email: _emailCtrl.text,
            password: _passwordCtrl.text,
            serverUrl: _serverCtrl.text,
            wsUrl: _wsCtrl.text,
            wsKey: _wsKeyCtrl.text,
          );
      // Yönlendirme router redirect'i tarafından yapılır.
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const _BrandHeader(),
                      const SizedBox(height: 32),
                      TextFormField(
                        controller: _emailCtrl,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        autocorrect: false,
                        autofillHints: const [AutofillHints.username],
                        decoration: const InputDecoration(
                          labelText: 'E-posta',
                          prefixIcon: Icon(Icons.mail_outline),
                        ),
                        validator: (v) {
                          final value = v?.trim() ?? '';
                          if (value.isEmpty) return 'E-posta adresi gerekli';
                          if (!value.contains('@')) {
                            return 'Geçerli bir e-posta girin';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _passwordCtrl,
                        obscureText: _obscure,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => _loading ? null : _submit(),
                        decoration: InputDecoration(
                          labelText: 'Şifre',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: IconButton(
                            tooltip: _obscure ? 'Şifreyi göster' : 'Şifreyi gizle',
                            icon: Icon(
                              _obscure
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                            onPressed: () =>
                                setState(() => _obscure = !_obscure),
                          ),
                        ),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'Şifre gerekli' : null,
                      ),
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: () =>
                              setState(() => _showServer = !_showServer),
                          icon: Icon(
                            _showServer
                                ? Icons.expand_less
                                : Icons.settings_ethernet,
                            size: 18,
                          ),
                          label: Text(
                            _showServer ? 'Sunucu ayarını gizle' : 'Sunucu',
                          ),
                        ),
                      ),
                      AnimatedCrossFade(
                        duration: const Duration(milliseconds: 200),
                        crossFadeState: _showServer
                            ? CrossFadeState.showSecond
                            : CrossFadeState.showFirst,
                        firstChild: const SizedBox(width: double.infinity),
                        secondChild: Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              TextFormField(
                                controller: _serverCtrl,
                                keyboardType: TextInputType.url,
                                autocorrect: false,
                                onChanged: (_) => setState(() {}),
                                decoration: InputDecoration(
                                  labelText: 'Sunucu adresi',
                                  hintText: AppConfig.defaultBaseUrl,
                                  helperText:
                                      'Örn. http://192.168.1.10:8000/api – boş bırakılırsa varsayılan kullanılır.',
                                  helperMaxLines: 2,
                                  prefixIcon: const Icon(Icons.dns_outlined),
                                  suffixIcon: IconButton(
                                    tooltip: 'Varsayılana dön',
                                    icon: const Icon(Icons.restart_alt),
                                    onPressed: () => setState(
                                      () => _serverCtrl.text = AppConfig.defaultBaseUrl,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _wsCtrl,
                                keyboardType: TextInputType.url,
                                autocorrect: false,
                                decoration: InputDecoration(
                                  labelText: 'Canlı bağlantı (Reverb) adresi',
                                  hintText: _wsHint,
                                  helperText:
                                      'ws://host:port – boş bırakılırsa sunucu adresinin ana makinesi ve ${AppConfig.reverbPort} portu kullanılır.',
                                  helperMaxLines: 2,
                                  prefixIcon: const Icon(Icons.sensors),
                                  suffixIcon: IconButton(
                                    tooltip: 'Temizle',
                                    icon: const Icon(Icons.clear),
                                    onPressed: () => _wsCtrl.clear(),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _wsKeyCtrl,
                                autocorrect: false,
                                decoration: InputDecoration(
                                  labelText: 'Reverb uygulama anahtarı',
                                  hintText: AppConfig.reverbAppKey,
                                  helperText:
                                      'Backend .env → REVERB_APP_KEY. Boş bırakılırsa derleme anahtarı kullanılır.',
                                  helperMaxLines: 2,
                                  prefixIcon: const Icon(Icons.key_outlined),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 8),
                        _ErrorBanner(message: _error!),
                      ],
                      const SizedBox(height: 20),
                      FilledButton(
                        onPressed: _loading ? null : _submit,
                        style: FilledButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                        ),
                        child: _loading
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                'Giriş Yap',
                                style: TextStyle(fontSize: 16),
                              ),
                      ),
                      const SizedBox(height: 24),
                      Text(
                        'Saha Sorumlusu Uygulaması',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall
                            ?.copyWith(color: theme.colorScheme.outline),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Container(
          width: 84,
          height: 84,
          decoration: BoxDecoration(
            color: theme.colorScheme.primary,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: theme.colorScheme.primary.withValues(alpha: 0.35),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: const Icon(Icons.shield_outlined, size: 48, color: Colors.white),
        ),
        const SizedBox(height: 16),
        Text(
          AppConfig.brandTitle,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            letterSpacing: 4,
            color: const Color(0xFF2B2A29),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Esas Saha',
          style: theme.textTheme.titleMedium?.copyWith(
            color: theme.colorScheme.primary,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline, color: scheme.onErrorContainer),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: scheme.onErrorContainer),
            ),
          ),
        ],
      ),
    );
  }
}
