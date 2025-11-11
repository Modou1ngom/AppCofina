#!/bin/bash

# Script de test pour l'API Profiles
# Assurez-vous que le serveur Laravel est en cours d'exécution sur http://127.0.0.1:8000

BASE_URL="http://127.0.0.1:8000/api"

echo "=== Test de l'API Profiles ==="
echo ""

# Couleurs pour la sortie
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Lister tous les profils (GET /api/profiles)
echo -e "${YELLOW}1. Test GET /api/profiles (Liste des profils)${NC}"
response=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/profiles")
http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
body=$(echo "$response" | sed '/HTTP_CODE/d')
echo "Code HTTP: $http_code"
echo "Réponse: $body"
echo ""

# Test 2: Créer un nouveau profil (POST /api/profiles)
echo -e "${YELLOW}2. Test POST /api/profiles (Création d'un profil)${NC}"
response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
  -X POST \
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
  }' \
  "$BASE_URL/profiles")
http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
body=$(echo "$response" | sed '/HTTP_CODE/d')
echo "Code HTTP: $http_code"
echo "Réponse: $body"
echo ""

# Extraire l'ID du profil créé si succès
if [ "$http_code" = "201" ]; then
  profile_id=$(echo "$body" | grep -o '"id":[0-9]*' | cut -d: -f2 | head -1)
  echo -e "${GREEN}✓ Profil créé avec l'ID: $profile_id${NC}"
  echo ""

  # Test 3: Récupérer un profil spécifique (GET /api/profiles/{id})
  echo -e "${YELLOW}3. Test GET /api/profiles/$profile_id (Détails d'un profil)${NC}"
  response=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/profiles/$profile_id")
  http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
  body=$(echo "$response" | sed '/HTTP_CODE/d')
  echo "Code HTTP: $http_code"
  echo "Réponse: $body"
  echo ""

  # Test 4: Mettre à jour un profil (PUT /api/profiles/{id})
  echo -e "${YELLOW}4. Test PUT /api/profiles/$profile_id (Mise à jour d'un profil)${NC}"
  response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X PUT \
    -H "Content-Type: application/json" \
    -d '{
      "prenom": "Jean-Pierre",
      "fonction": "Développeur Senior"
    }' \
    "$BASE_URL/profiles/$profile_id")
  http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
  body=$(echo "$response" | sed '/HTTP_CODE/d')
  echo "Code HTTP: $http_code"
  echo "Réponse: $body"
  echo ""

  # Test 5: Supprimer un profil (DELETE /api/profiles/{id})
  echo -e "${YELLOW}5. Test DELETE /api/profiles/$profile_id (Suppression d'un profil)${NC}"
  response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X DELETE \
    "$BASE_URL/profiles/$profile_id")
  http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
  body=$(echo "$response" | sed '/HTTP_CODE/d')
  echo "Code HTTP: $http_code"
  echo "Réponse: $body"
  echo ""
else
  echo -e "${RED}✗ Échec de la création du profil. Impossible de continuer les tests.${NC}"
fi

# Test 6: Test de validation (créer un profil avec des données invalides)
echo -e "${YELLOW}6. Test POST /api/profiles (Validation - données invalides)${NC}"
response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "matricule": "",
    "prenom": ""
  }' \
  "$BASE_URL/profiles")
http_code=$(echo "$response" | grep "HTTP_CODE" | cut -d: -f2)
body=$(echo "$response" | sed '/HTTP_CODE/d')
echo "Code HTTP: $http_code (attendu: 422 pour erreur de validation)"
echo "Réponse: $body"
echo ""

echo -e "${GREEN}=== Tests terminés ===${NC}"


