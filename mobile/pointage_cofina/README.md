# Pointage Cofina (Flutter)

Application minimale qui consomme l’API Laravel ` /api/mobile ` (Sanctum).

## Prérequis

- [Flutter](https://docs.flutter.dev/get-started/install) (SDK stable)
- Backend Laravel démarré avec migrations Sanctum et routes `api/mobile`

## Première installation

```bash
cd mobile/pointage_cofina
flutter pub get
```

Générer les dossiers plateforme (`android`, `ios`, …) si besoin :

```bash
flutter create . --project-name pointage_cofina --org com.cofina.pointage
```

## Lancer l’app

**Émulateur Android** (Laravel sur la machine hôte, port 8000) :

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

**Simulateur iOS** :

```bash
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

**Téléphone physique** : remplacez par l’IP locale du PC (même Wi‑Fi), ex. :

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000
```

**Production** :

```bash
flutter run --release --dart-define=API_BASE_URL=https://votre-domaine.com
```

## Fichiers importants

| Fichier | Rôle |
|--------|------|
| `lib/config.dart` | `API_BASE_URL` (dart-define) |
| `lib/services/pointage_api.dart` | Appels HTTP |
| `lib/services/token_store.dart` | Jeton Sanctum (stockage sécurisé) |

## Limites actuelles (backend)

- Comptes avec **2FA Fortify** : l’API renvoie une erreur dédiée ; prévoir un flux TOTP dans l’app ou une politique métier.
- **Changement de mot de passe obligatoire** : traiter depuis le web avant la première connexion mobile.
