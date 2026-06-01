import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class PointageSite {
  const PointageSite({required this.id, required this.nom, required this.codePublic});

  final int id;
  final String nom;
  final String codePublic;

  factory PointageSite.fromJson(Map<String, dynamic> j) {
    return PointageSite(
      id: j['id'] as int,
      nom: j['nom'] as String,
      codePublic: j['code_public'] as String,
    );
  }
}

class PointageJour {
  const PointageJour({
    required this.id,
    required this.sens,
    required this.source,
    required this.enregistreAt,
    this.siteNom,
  });

  final int id;
  final String sens;
  final String source;
  final String enregistreAt;
  final String? siteNom;

  factory PointageJour.fromJson(Map<String, dynamic> j) {
    final site = j['site'] as Map<String, dynamic>?;
    return PointageJour(
      id: j['id'] as int,
      sens: j['sens'] as String,
      source: j['source'] as String,
      enregistreAt: j['enregistre_at'] as String,
      siteNom: site != null ? site['nom'] as String? : null,
    );
  }
}

class PointageApi {
  PointageApi({required this.getToken, this.onUnauthorized});

  final Future<String?> Function() getToken;
  final void Function()? onUnauthorized;

  Map<String, String> _jsonHeaders(String? token) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Future<void> _checkResponse(http.Response r) async {
    if (r.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException('Session expirée. Reconnectez-vous.', statusCode: 401);
    }
    if (r.statusCode >= 400) {
      String msg = 'Erreur ${r.statusCode}';
      try {
        final body = jsonDecode(r.body);
        if (body is Map && body['message'] != null) {
          msg = body['message'].toString();
        }
      } catch (_) {
        if (r.body.isNotEmpty) msg = r.body;
      }
      throw ApiException(msg, statusCode: r.statusCode);
    }
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    String? deviceName,
  }) async {
    final uri = Uri.parse('${AppConfig.mobilePrefix}/login');
    final res = await http.post(
      uri,
      headers: _jsonHeaders(null),
      body: jsonEncode({
        'email': email,
        'password': password,
        if (deviceName != null) 'device_name': deviceName,
      }),
    );
    await _checkResponse(res);
    return jsonDecode(res.body) as Map<String, dynamic>;
  }

  Future<void> logout() async {
    final token = await getToken();
    if (token == null || token.isEmpty) return;
    final uri = Uri.parse('${AppConfig.mobilePrefix}/logout');
    final res = await http.post(uri, headers: _jsonHeaders(token));
    await _checkResponse(res);
  }

  Future<List<PointageSite>> fetchSites() async {
    final token = await getToken();
    final uri = Uri.parse('${AppConfig.mobilePrefix}/pointage/sites');
    final res = await http.get(uri, headers: _jsonHeaders(token));
    await _checkResponse(res);
    final map = jsonDecode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>;
    return list.map((e) => PointageSite.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> enregistrerPointage({
    required String codePublic,
    required String sens,
    String? deviceId,
  }) async {
    final token = await getToken();
    final uri = Uri.parse('${AppConfig.mobilePrefix}/pointage');
    final res = await http.post(
      uri,
      headers: _jsonHeaders(token),
      body: jsonEncode({
        'code_public': codePublic,
        'sens': sens,
        if (deviceId != null) 'device_id': deviceId,
      }),
    );
    await _checkResponse(res);
  }

  Future<List<PointageJour>> fetchToday() async {
    final token = await getToken();
    final uri = Uri.parse('${AppConfig.mobilePrefix}/pointage/today');
    final res = await http.get(uri, headers: _jsonHeaders(token));
    await _checkResponse(res);
    final map = jsonDecode(res.body) as Map<String, dynamic>;
    final list = map['data'] as List<dynamic>;
    return list.map((e) => PointageJour.fromJson(e as Map<String, dynamic>)).toList();
  }
}
