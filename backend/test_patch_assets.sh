#!/bin/bash
# Tests PATCH /assets avec curl

# Configuration
API_URL="http://localhost:8000"
TOKEN="" # À remplir avec un token JWT valide
ASSET_ID=1 # À remplacer avec un asset existant

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== TESTS PATCH /assets ===${NC}\n"

# Test 1: Modification JSON sans fichiers
echo -e "${YELLOW}Test 1: Modification JSON sans fichiers${NC}"
curl -X PATCH "$API_URL/assets/$ASSET_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nom": "Test PATCH - Modification JSON",
    "valeur": 999999,
    "description": "Modification via JSON"
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 2: Modification avec project_ids CSV
echo -e "${YELLOW}Test 2: Modification JSON avec CSV pour project_ids${NC}"
curl -X PATCH "$API_URL/assets/$ASSET_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nom": "Test PATCH - CSV project_ids",
    "project_ids": "1,2,3"
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 3: Modification avec project_ids array
echo -e "${YELLOW}Test 3: Modification JSON avec Array pour project_ids${NC}"
curl -X PATCH "$API_URL/assets/$ASSET_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "nom": "Test PATCH - Array project_ids",
    "project_ids": [1, 2, 3, 4]
  }' \
  -w "\nHTTP Status: %{http_code}\n\n"

# Test 4: Modification multipart avec 1 photo
echo -e "${YELLOW}Test 4: Modification multipart avec 1 photo${NC}"
# Créer un fichier de test si n'existe pas
if [ ! -f "test_photo.jpg" ]; then
  echo "test" > test_photo.jpg
fi
curl -X PATCH "$API_URL/assets/$ASSET_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -F "nom=Test PATCH - Photo simple" \
  -F "photos[0]=@test_photo.jpg" \
  -w "\nHTTP Status: %{http_code}\n\n"

echo -e "${GREEN}=== Tests terminés ===${NC}"
echo "Note: Remplacer TOKEN et ASSET_ID avant exécution"
