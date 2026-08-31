#!/bin/bash
# Script de test 2FA avec curl
# Utilisation: bash test_2fa_curl.sh

set -e

BASE_URL="https://localhost:8000/api"
# Désactiver vérification SSL pour dev local
CURL_OPTS="-s -k"

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}Test Suite: 2FA Implementation${NC}"
echo -e "${BLUE}Base URL: $BASE_URL${NC}"
echo -e "${BLUE}================================================${NC}\n"

# Test 1: Créer utilisateur avec 2FA
echo -e "${YELLOW}TEST 1: Create user WITH 2FA enabled${NC}"
USER_WITH_2FA=$(curl $CURL_OPTS -X POST "$BASE_URL/users" \
  -H "Content-Type: application/json" \
  -d '{
    "firstName": "Test",
    "lastName": "TwoFA",
    "email": "test2fa@example.com",
    "password": "TestPassword123!",
    "twoFactorEnabled": true,
    "is_active": true
  }')

echo "$USER_WITH_2FA" | jq '.'
USER_ID_2FA=$(echo "$USER_WITH_2FA" | jq -r '.data.id // empty')
TWO_FACTOR_ENABLED=$(echo "$USER_WITH_2FA" | jq -r '.data.twoFactorEnabled // false')

if [ "$TWO_FACTOR_ENABLED" = "true" ]; then
  echo -e "${GREEN}✓ User created with twoFactorEnabled = true${NC}\n"
else
  echo -e "${RED}✗ User twoFactorEnabled not set to true${NC}\n"
fi

# Test 2: Créer utilisateur sans 2FA (défaut)
echo -e "${YELLOW}TEST 2: Create user WITHOUT 2FA (default)${NC}"
USER_NO_2FA=$(curl $CURL_OPTS -X POST "$BASE_URL/users" \
  -H "Content-Type: application/json" \
  -d '{
    "firstName": "Test",
    "lastName": "NoTwoFA",
    "email": "testno2fa@example.com",
    "password": "TestPassword123!",
    "is_active": true
  }')

echo "$USER_NO_2FA" | jq '.'
USER_ID_NO_2FA=$(echo "$USER_NO_2FA" | jq -r '.data.id // empty')
TWO_FACTOR_ENABLED=$(echo "$USER_NO_2FA" | jq -r '.data.twoFactorEnabled // false')

if [ "$TWO_FACTOR_ENABLED" = "false" ]; then
  echo -e "${GREEN}✓ User created with twoFactorEnabled = false (default)${NC}\n"
else
  echo -e "${RED}✗ User twoFactorEnabled should be false by default${NC}\n"
fi

# Test 3: Login avec 2FA user (doit retourner requires_otp)
echo -e "${YELLOW}TEST 3: Login with 2FA user (should require OTP)${NC}"
LOGIN_2FA=$(curl $CURL_OPTS -X POST "$BASE_URL/login_check" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test2fa@example.com",
    "password": "TestPassword123!"
  }')

echo "$LOGIN_2FA" | jq '.'
REQUIRES_OTP=$(echo "$LOGIN_2FA" | jq -r '.data.requires_otp // false')

if [ "$REQUIRES_OTP" = "true" ]; then
  echo -e "${GREEN}✓ Login returned requires_otp = true${NC}"
  echo -e "${YELLOW}Note: Check email for OTP code (5 minute expiry)${NC}\n"
else
  echo -e "${RED}✗ Login should return requires_otp = true${NC}\n"
fi

# Test 4: Login sans 2FA user (doit retourner JWT direct)
echo -e "${YELLOW}TEST 4: Login without 2FA user (should get JWT)${NC}"
LOGIN_NO_2FA=$(curl $CURL_OPTS -X POST "$BASE_URL/login_check" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testno2fa@example.com",
    "password": "TestPassword123!"
  }')

echo "$LOGIN_NO_2FA" | jq '.'
JWT_TOKEN=$(echo "$LOGIN_NO_2FA" | jq -r '.data.token // empty')

if [ -n "$JWT_TOKEN" ]; then
  echo -e "${GREEN}✓ Login returned JWT token (no OTP required)${NC}\n"
else
  echo -e "${RED}✗ Login should return JWT token${NC}\n"
fi

# Test 5: Verify OTP (exemple avec OTP factice - en production, utiliser code reçu par email)
echo -e "${YELLOW}TEST 5: Verify OTP (avec code factice)${NC}"
echo -e "${BLUE}Note: En production, utiliser le code reçu par email${NC}"
VERIFY_OTP=$(curl $CURL_OPTS -X POST "$BASE_URL/auth/verify-otp" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test2fa@example.com",
    "otp": "123456"
  }')

echo "$VERIFY_OTP" | jq '.'
OTP_ERROR=$(echo "$VERIFY_OTP" | jq -r '.message // empty')

if [[ "$OTP_ERROR" == *"invalide"* ]] || [[ "$OTP_ERROR" == *"expiré"* ]]; then
  echo -e "${YELLOW}✓ OTP validation working (code invalid/expired as expected)${NC}\n"
else
  echo -e "${YELLOW}⚠ OTP response received${NC}\n"
fi

# Test 6: Update 2FA status via dedicated endpoint
if [ -n "$JWT_TOKEN" ]; then
  echo -e "${YELLOW}TEST 6: Update 2FA status via PATCH /users/{id}/two-factor${NC}"
  UPDATE_2FA=$(curl $CURL_OPTS -X PATCH "$BASE_URL/users/1/two-factor" \
    -H "Authorization: Bearer $JWT_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"twoFactorEnabled": true}')

  echo "$UPDATE_2FA" | jq '.'
  TWO_FACTOR_RESULT=$(echo "$UPDATE_2FA" | jq -r '.data.twoFactorEnabled // false')

  if [ "$TWO_FACTOR_RESULT" = "true" ]; then
    echo -e "${GREEN}✓ 2FA status updated successfully${NC}\n"
  else
    echo -e "${RED}✗ 2FA status update failed${NC}\n"
  fi

  # Test 7: Get profile (doit inclure twoFactorEnabled)
  echo -e "${YELLOW}TEST 7: Get /profile (should include twoFactorEnabled)${NC}"
  PROFILE=$(curl $CURL_OPTS -X GET "$BASE_URL/profile" \
    -H "Authorization: Bearer $JWT_TOKEN" \
    -H "Content-Type: application/json")

  echo "$PROFILE" | jq '.'
  HAS_2FA_FIELD=$(echo "$PROFILE" | jq '.data | has("twoFactorEnabled")')

  if [ "$HAS_2FA_FIELD" = "true" ]; then
    echo -e "${GREEN}✓ Profile includes twoFactorEnabled field${NC}\n"
  else
    echo -e "${RED}✗ Profile should include twoFactorEnabled field${NC}\n"
  fi

  # Test 8: Get /users (doit inclure twoFactorEnabled dans liste)
  echo -e "${YELLOW}TEST 8: Get /users (list should include twoFactorEnabled)${NC}"
  USERS_LIST=$(curl $CURL_OPTS -X GET "$BASE_URL/users?page=1&limit=10" \
    -H "Authorization: Bearer $JWT_TOKEN" \
    -H "Content-Type: application/json")

  echo "$USERS_LIST" | jq '.data.data[0]' 2>/dev/null || echo "$USERS_LIST" | jq '.'
  FIRST_USER=$(echo "$USERS_LIST" | jq '.data.data[0] // empty')
  HAS_2FA=$(echo "$FIRST_USER" | jq 'has("twoFactorEnabled")')

  if [ "$HAS_2FA" = "true" ]; then
    echo -e "${GREEN}✓ Users list includes twoFactorEnabled field${NC}\n"
  else
    echo -e "${RED}✗ Users list should include twoFactorEnabled field${NC}\n"
  fi

  # Test 9: Get /users/{id} detail (doit inclure twoFactorEnabled)
  if [ -n "$USER_ID_NO_2FA" ]; then
    echo -e "${YELLOW}TEST 9: Get /users/{id} detail (should include twoFactorEnabled)${NC}"
    USER_DETAIL=$(curl $CURL_OPTS -X GET "$BASE_URL/users/$USER_ID_NO_2FA" \
      -H "Authorization: Bearer $JWT_TOKEN" \
      -H "Content-Type: application/json")

    echo "$USER_DETAIL" | jq '.'
    HAS_2FA=$(echo "$USER_DETAIL" | jq '.data | has("twoFactorEnabled")')

    if [ "$HAS_2FA" = "true" ]; then
      echo -e "${GREEN}✓ User detail includes twoFactorEnabled field${NC}\n"
    else
      echo -e "${RED}✗ User detail should include twoFactorEnabled field${NC}\n"
    fi
  fi

  # Test 10: Update user with twoFactorEnabled via PUT/PATCH
  echo -e "${YELLOW}TEST 10: Update user with twoFactorEnabled via PATCH /users/{id}${NC}"
  if [ -n "$USER_ID_NO_2FA" ]; then
    UPDATE_USER=$(curl $CURL_OPTS -X PATCH "$BASE_URL/users/$USER_ID_NO_2FA" \
      -H "Authorization: Bearer $JWT_TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "firstName": "TestUpdated",
        "twoFactorEnabled": true
      }')

    echo "$UPDATE_USER" | jq '.'
    TWO_FACTOR_RESULT=$(echo "$UPDATE_USER" | jq -r '.data.twoFactorEnabled // false')

    if [ "$TWO_FACTOR_RESULT" = "true" ]; then
      echo -e "${GREEN}✓ User updated with twoFactorEnabled = true${NC}\n"
    else
      echo -e "${RED}✗ User twoFactorEnabled should be true after update${NC}\n"
    fi
  fi
else
  echo -e "${RED}✗ Cannot run remaining tests without JWT token${NC}\n"
fi

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}Test Suite Complete${NC}"
echo -e "${BLUE}================================================${NC}\n"

echo -e "${YELLOW}Manual Testing:${NC}"
echo "1. Check inbox/spam for OTP email at: test2fa@example.com"
echo "2. Copy the 6-digit code from email"
echo "3. Test OTP verification with actual code:"
echo ""
echo "   curl -X POST $BASE_URL/auth/verify-otp \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"email\": \"test2fa@example.com\", \"otp\": \"XXXXXX\"}'"
echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "Update .env with your email provider:"
echo "  MAILER_DSN=smtp://your-email@gmail.com:app-password@smtp.gmail.com:587?encryption=tls&auth_mode=login"
echo "  MAIL_FROM_ADDRESS=your-email@gmail.com"
echo "  MAIL_FROM_NAME=Library patnuc"
