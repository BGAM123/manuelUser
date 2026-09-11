# API de Gestion de la Langue Utilisateur

Cette documentation décrit les nouveaux endpoints pour gérer la préférence de langue des utilisateurs.

## Endpoints

### 1. Récupérer la langue de l'utilisateur connecté

**GET** `/core/user/langue`

**Headers requis:**
```
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json
```

**Réponse (200):**
```json
{
  "user": {
    "id": 1,
    "username": "jdupont",
    "langue": true
  }
}
```

### 2. Modifier la langue de l'utilisateur connecté

**PATCH** `/core/user/langue`

**Headers requis:**
```
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json
```

**Corps de la requête:**
```json
{
  "langue": true
}
```

**Réponse (200):**
```json
{
  "message": "Langue mise à jour avec succès",
  "user": {
    "id": 1,
    "username": "jdupont",
    "langue": true,
    "updatedAt": "2025-10-29 12:30:45"
  }
}
```

**Erreurs possibles:**
- **400** - Le champ langue est requis ou doit être un booléen
- **401** - Utilisateur non authentifié

## Utilisation avec l'API de connexion

Lors de la connexion via `POST /login`, la réponse inclut maintenant le champ `langue`:

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "id": 1,
  "username": "test1",
  "email": "test@example.com",
  "lastName": "Doe",
  "langue": false
}
```

## Exemple d'utilisation côté frontend

```javascript
// 1. Récupérer la langue actuelle
const getLangue = async () => {
  const response = await fetch('/core/user/langue', {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  const data = await response.json();
  console.log('Langue actuelle:', data.user.langue);
};

// 2. Modifier la langue
const updateLangue = async (newLangue) => {
  const response = await fetch('/core/user/langue', {
    method: 'PATCH',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      langue: newLangue
    })
  });
  const data = await response.json();
  console.log('Langue mise à jour:', data.message);
};

// Exemples d'utilisation
getLangue();
updateLangue(true);  // Activer la langue
updateLangue(false); // Désactiver la langue
```

## Notes

- Le champ `langue` est un booléen (`true` ou `false`)
- La valeur par défaut est `false`
- Seul l'utilisateur connecté peut modifier sa propre langue
- La langue est automatiquement incluse dans la réponse de connexion JWT
