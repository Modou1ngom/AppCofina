import 'package:flutter/material.dart';

import '../config.dart';
import '../services/pointage_api.dart';
import '../services/token_store.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.onLogout});

  final VoidCallback onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final TokenStore _tokens = TokenStore();
  final TextEditingController _codeController = TextEditingController();

  late final PointageApi _api = PointageApi(
    getToken: _tokens.read,
    onUnauthorized: () {
      _tokens.clear();
      widget.onLogout();
    },
  );

  List<PointageSite> _sites = [];
  List<PointageJour> _today = [];
  bool _loading = true;
  String? _error;
  String _sens = 'entree';
  String? _selectedSiteCode;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final sites = await _api.fetchSites();
      final today = await _api.fetchToday();
      if (!mounted) return;
      setState(() {
        _sites = sites;
        _today = today;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = 'Erreur : $e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onSitePicked(String? code) {
    setState(() => _selectedSiteCode = code);
    if (code == null || code.isEmpty) return;
    _codeController.text = code;
    _codeController.selection = TextSelection.collapsed(offset: code.length);
  }

  Future<void> _pointer() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indiquez le code du site.')),
      );
      return;
    }
    try {
      await _api.enregistrerPointage(codePublic: code, sens: _sens);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pointage enregistré.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
  }

  Future<void> _deconnexion() async {
    try {
      await _api.logout();
    } catch (_) {}
    await _tokens.clear();
    if (mounted) widget.onLogout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pointage'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loading ? null : _load,
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _deconnexion,
          ),
        ],
      ),
      body: _loading && _sites.isEmpty && _today.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    'API : ${AppConfig.mobilePrefix}',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: Theme.of(context).colorScheme.outline,
                        ),
                  ),
                  const SizedBox(height: 16),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Text(
                        _error!,
                        style: TextStyle(color: Theme.of(context).colorScheme.error),
                      ),
                    ),
                  if (_sites.isNotEmpty) ...[
                    DropdownButtonFormField<String?>(
                      decoration: const InputDecoration(
                        labelText: 'Site (raccourci)',
                        border: OutlineInputBorder(),
                      ),
                      value: _selectedSiteCode,
                      items: [
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('— Choisir —'),
                        ),
                        ..._sites.map(
                          (s) => DropdownMenuItem<String?>(
                            value: s.codePublic,
                            child: Text(s.nom),
                          ),
                        ),
                      ],
                      onChanged: _onSitePicked,
                    ),
                    const SizedBox(height: 16),
                  ],
                  TextField(
                    controller: _codeController,
                    decoration: const InputDecoration(
                      labelText: 'Code du site',
                      border: OutlineInputBorder(),
                    ),
                    autocorrect: false,
                  ),
                  const SizedBox(height: 16),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'entree', label: Text('Entrée'), icon: Icon(Icons.login)),
                      ButtonSegment(value: 'sortie', label: Text('Sortie'), icon: Icon(Icons.logout)),
                    ],
                    selected: {_sens},
                    onSelectionChanged: (s) => setState(() => _sens = s.first),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _loading ? null : _pointer,
                    style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                    child: const Text('Enregistrer le pointage'),
                  ),
                  const SizedBox(height: 32),
                  Text('Aujourd’hui', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (_today.isEmpty)
                    const Text('Aucun pointage.')
                  else
                    ..._today.map(
                      (p) => Card(
                        child: ListTile(
                          title: Text(p.siteNom ?? '—'),
                          subtitle: Text('${p.sens} · ${p.source}'),
                          trailing: Text(
                            p.enregistreAt.length >= 16 ? p.enregistreAt.substring(11, 16) : p.enregistreAt,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
