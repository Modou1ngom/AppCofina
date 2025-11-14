# Documentation API - Applications

## Endpoints disponibles

Toutes les routes API sont préfixées par `/api`.

### 1. Lister toutes les applications

```bash
GET /api/applications
```

**Paramètres optionnels :**
- `actif` (boolean) : Filtrer par statut actif/inactif

**Exemple avec curl :**
```bash
# Toutes les applications
curl http://127.0.0.1:8000/api/applications

# Seulement les applications actives
curl http://127.0.0.1:8000/api/applications?actif=true

# Seulement les applications inactives
curl http://127.0.0.1:8000/api/applications?actif=false
```

**Réponse :**
```json
[
  {
    "id": 1,
    "nom": "Compte Windows",
    "description": null,
    "actif": true,
    "ordre": 1,
    "created_at": "2025-11-14T09:00:00.000000Z",
    "updated_at": "2025-11-14T09:00:00.000000Z"
  },
  {
    "id": 2,
    "nom": "Outlook",
    "description": null,
    "actif": true,
    "ordre": 2,
    "created_at": "2025-11-14T09:00:00.000000Z",
    "updated_at": "2025-11-14T09:00:00.000000Z"
  }
]
```

### 2. Créer une nouvelle application

```bash
POST /api/applications
```

**Exemple avec curl :**
```bash
curl -X POST http://127.0.0.1:8000/api/applications \
  -H "Content-Type: application/json" \
  -d '{
    "nom": "Nouvelle Application",
    "description": "Description de la nouvelle application",
    "actif": true,
    "ordre": 15
  }'
```

**Champs obligatoires :**
- `nom` (string, max: 255, unique)

**Champs optionnels :**
- `description` (string)
- `actif` (boolean, défaut: true)
- `ordre` (integer, min: 0, défaut: 0)

**Réponse (201 Created) :**
```json
{
  "id": 16,
  "nom": "Nouvelle Application",
  "description": "Description de la nouvelle application",
  "actif": true,
  "ordre": 15,
  "created_at": "2025-11-14T09:00:00.000000Z",
  "updated_at": "2025-11-14T09:00:00.000000Z"
}
```

### 3. Récupérer une application spécifique

```bash
GET /api/applications/{id}
```

**Exemple avec curl :**
```bash
curl http://127.0.0.1:8000/api/applications/1
```

**Réponse :**
```json
{
  "id": 1,
  "nom": "Compte Windows",
  "description": null,
  "actif": true,
  "ordre": 1,
  "created_at": "2025-11-14T09:00:00.000000Z",
  "updated_at": "2025-11-14T09:00:00.000000Z"
}
```

### 4. Mettre à jour une application

```bash
PUT /api/applications/{id}
PATCH /api/applications/{id}
```

**Exemple avec curl :**
```bash
curl -X PUT http://127.0.0.1:8000/api/applications/1 \
  -H "Content-Type: application/json" \
  -d '{
    "nom": "Compte Windows (Mis à jour)",
    "description": "Nouvelle description",
    "actif": true,
    "ordre": 1
  }'
```

**Champs optionnels (tous peuvent être mis à jour) :**
- `nom` (string, max: 255, unique)
- `description` (string)
- `actif` (boolean)
- `ordre` (integer, min: 0)

**Réponse :**
```json
{
  "id": 1,
  "nom": "Compte Windows (Mis à jour)",
  "description": "Nouvelle description",
  "actif": true,
  "ordre": 1,
  "created_at": "2025-11-14T09:00:00.000000Z",
  "updated_at": "2025-11-14T09:30:00.000000Z"
}
```

### 5. Supprimer une application

```bash
DELETE /api/applications/{id}
```

**Exemple avec curl :**
```bash
curl -X DELETE http://127.0.0.1:8000/api/applications/16
```

**Réponse :**
```json
{
  "message": "Application supprimée avec succès"
}
```

## Installation et configuration

### 1. Exécuter la migration

```bash
php artisan migrate
```

### 2. Exécuter le seeder pour créer les applications par défaut

```bash
php artisan db:seed --class=ApplicationSeeder
```

Ou si vous voulez réinitialiser complètement :

```bash
php artisan migrate:fresh --seed
```

## Utilisation dans le code

Le contrôleur `HabilitationController` utilise maintenant automatiquement les applications depuis la base de données :

```php
// Dans HabilitationController
private function getApplicationsList(): array
{
    return Application::actives()
        ->ordered()
        ->pluck('nom')
        ->toArray();
}
```

Les applications sont automatiquement récupérées et triées par ordre, puis par nom.

## Codes de réponse

- `200` : Succès (GET, PUT, PATCH)
- `201` : Créé avec succès (POST)
- `404` : Ressource non trouvée
- `422` : Erreur de validation
- `500` : Erreur serveur

## Notes importantes

- Les applications sont triées par le champ `ordre` puis par `nom`
- Seules les applications actives (`actif = true`) sont utilisées dans les formulaires d'habilitation
- Le champ `nom` doit être unique
- Le champ `ordre` permet de contrôler l'ordre d'affichage des applications

