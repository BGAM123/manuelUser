# API de Gestion de la Langue Utilisateur

Cette documentation dﾃｩcrit les nouveaux endpoints pour gﾃｩrer la prﾃｩfﾃｩrence de langue des utilisateurs.

## Endpoints

### 1. Rﾃｩcupﾃｩrer la langue de l'utilisateur connectﾃｩ

**GET** `/core/user/langue`

**Headers requis:**
```
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json
```

**Rﾃｩponse (200):**
```json
{
  "user": {
    "id": 1,
    "username": "jdupont",
    "langue": true
  }
}
```

### 2. Modifier la langue de l'utilisateur connectﾃｩ

**PATCH** `/core/user/langue`

**Headers requis:**
```
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json
```

**Corps de la requﾃｪte:**
```json
{
  "langue": true
}
```

**Rﾃｩponse (200):**
```json
{
  "message": "Langue mise ﾃ� jour avec succﾃｨs",
  "user": {
    "id": 1,
    "username": "jdupont",
    "langue": true,
    "updatedAt": "2025-10-29 12:30:45"
  }
}
```

**Erreurs possibles:**
- **400** - Le champ langue est requis ou doit ﾃｪtre un boolﾃｩen
- **401** - Utilisateur non authentifiﾃｩ

## Utilisation avec l'API de connexion

Lors de la connexion via `POST /login`, la rﾃｩponse inclut maintenant le champ `langue`:

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

## Exemple d'utilisation cﾃｴtﾃｩ frontend

```javascript
// 1. Rﾃｩcupﾃｩrer la langue actuelle
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
  console.log('Langue mise ﾃ� jour:', data.message);
};

// Exemples d'utilisation
getLangue();
updateLangue(true);  // Activer la langue
updateLangue(false); // Dﾃｩsactiver la langue
```

## Notes

- Le champ `langue` est un boolﾃｩen (`true` ou `false`)
- La valeur par dﾃｩfaut est `false`
- Seul l'utilisateur connectﾃｩ peut modifier sa propre langue
- La langue est automatiquement incluse dans la rﾃｩponse de connexion JWT
