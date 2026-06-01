import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'services/token_store.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PointageCofinaApp());
}

class PointageCofinaApp extends StatefulWidget {
  const PointageCofinaApp({super.key});

  @override
  State<PointageCofinaApp> createState() => _PointageCofinaAppState();
}

class _PointageCofinaAppState extends State<PointageCofinaApp> {
  final TokenStore _tokens = TokenStore();
  bool _ready = false;
  bool _loggedIn = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final t = await _tokens.read();
    if (!mounted) return;
    setState(() {
      _loggedIn = t != null && t.isNotEmpty;
      _ready = true;
    });
  }

  void _setLoggedIn(bool v) {
    setState(() => _loggedIn = v);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Pointage Cofina',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1565C0)),
        useMaterial3: true,
      ),
      home: !_ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _loggedIn
              ? HomeScreen(onLogout: () => _setLoggedIn(false))
              : LoginScreen(onLoggedIn: () => _setLoggedIn(true)),
    );
  }
}
