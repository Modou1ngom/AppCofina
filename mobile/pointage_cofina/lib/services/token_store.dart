import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const _kTokenKey = 'sanctum_token';

class TokenStore {
  TokenStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<void> save(String token) => _storage.write(key: _kTokenKey, value: token);

  Future<String?> read() => _storage.read(key: _kTokenKey);

  Future<void> clear() => _storage.delete(key: _kTokenKey);
}
