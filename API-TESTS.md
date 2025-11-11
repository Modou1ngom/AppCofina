# Documentation API - Profiles

## Endpoints disponibles

Toutes les routes API sont préfixées par `/api`.

### 1. Lister tous les profils
```bash
GET /api/profiles
```

**Exemple avec curl :**
```bash
curl http://127.0.0.1:8000/api/profiles
```

### 2. Créer un nouveau profil
```bash
POST /api/profiles
```

**Exemple avec curl :**
```bash
curl -X POST http://127.0.0.1:8000/api/profiles \
  -H "Content-Type: application/json" \
  -d '{
    "matricule": "TEST001",
    "prenom": "Jean",
    "nom": "Dupont",
    "fonction": "Développeur",
    "departement": "IT",
    "email": "jean.dupont@example.com",
    "telephone": "0612345678",
    "site": "Paris",
    "type_contrat": "CDI",
    "statut": "actif"
  }'
```

**Champs obligatoires :**
- `matricule` (string, unique)
- `prenom` (string, max: 100)
- `nom` (string, max: 100)

**Champs optionnels :**
- `fonction` (string, max: 150)
- `departement` (string, max: 150)
- `email` (email, unique)
- `telephone` (string, max: 20)
- `site` (string, max: 100)
- `type_contrat` (enum: CDI, CDD, Stagiaire, Autre)
- `statut` (enum: actif, inactif)

### 3. Récupérer un profil spécifique
```bash
GET /api/profiles/{id}
```

**Exemple avec curl :**
```bash
curl http://127.0.0.1:8000/api/profiles/1
```

### 4. Mettre à jour un profil
```bash
PUT /api/profiles/{id}
PATCH /api/profiles/{id}
```

**Exemple avec curl :**
```bash
curl -X PUT http://127.0.0.1:8000/api/profiles/1 \
  -H "Content-Type: application/json" \
  -d '{
    "prenom": "Jean-Pierre",
    "fonction": "Développeur Senior"
  }'
```

### 5. Supprimer un profil
```bash
DELETE /api/profiles/{id}
```

**Exemple avec curl :**
```bash
curl -X DELETE http://127.0.0.1:8000/api/profiles/1
```

## Test automatique

Un script de test est disponible pour tester tous les endpoints :

```bash
./test-api.sh
```

## Codes de réponse

- `200` : Succès (GET, PUT, PATCH, DELETE)
- `201` : Créé avec succès (POST)
- `404` : Ressource non trouvée
- `422` : Erreur de validation
- `500` : Erreur serveur

