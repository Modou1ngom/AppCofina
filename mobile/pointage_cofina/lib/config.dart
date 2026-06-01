/// URL de base de l’API Laravel (sans slash final).
///
/// Build : `flutter run --dart-define=API_BASE_URL=https://votre-serveur.com`
///
/// Développement :
/// - Émulateur Android : `http://10.0.2.2:8000` (accède au localhost de la machine hôte)
/// - Simulateur iOS : `http://127.0.0.1:8000`
/// - Téléphone physique : IP locale du PC, ex. `http://192.168.1.10:8000`
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  static String get mobilePrefix => '$apiBaseUrl/api/mobile';
}
