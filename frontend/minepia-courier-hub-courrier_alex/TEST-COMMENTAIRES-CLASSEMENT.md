# ✅ Test des Commentaires lors du Classement

## 🔍 Comment vérifier que les commentaires sont envoyés à l'API

### **Étape 1 : Ouvrir la console du navigateur**
- Appuyez sur `F12` pour ouvrir les DevTools
- Allez dans l'onglet **Console**

### **Étape 2 : Classer un courrier**
1. Allez dans **Courriers Arrivés** ou **Traitements**
2. Cliquez sur **Classer** pour un courrier
3. Remplissez les deux champs de commentaires :
   - **Commentaire public** (optionnel) : ex. "Dossier traité et archivé"
   - **Commentaire interne** (obligatoire) : ex. "Nécessite un suivi ultérieur"
4. Cliquez sur **Confirmer le classement**

### **Étape 3 : Vérifier les logs dans la console**

Vous devriez voir ces messages dans la console :

```
📤 Commentaires envoyés à l'API: {
  commentairePublic: "Dossier traité et archivé",
  commentaireInterne: "Nécessite un suivi ultérieur"
}

📤 gelerCourrier - ID: 123, Commentaires: {
  commentairePublic: "Dossier traité et archivé",
  commentaireInterne: "Nécessite un suivi ultérieur"
}

✅ Courrier gelé avec succès: {
  message: "Courrier gelé avec succès",
  id: 123,
  is_geled: true,
  statut: "Classé",
  commentairePublic: "Dossier traité et archivé",
  commentaireInterne: "Nécessite un suivi ultérieur",
  transmissions_updated: 3,
  updatedAt: "2025-12-19T10:30:00Z",
  gele_par: {
    id: 5,
    nom: "Jean Dupont",
    email: "jean.dupont@example.com"
  }
}
```

### **Étape 4 : Vérifier les requêtes HTTP (Network)**

1. Dans les DevTools, allez dans l'onglet **Network** (Réseau)
2. Filtrez par **Fetch/XHR**
3. Trouvez la requête `PATCH /core/courrier/geler/{id}`
4. Cliquez dessus et allez dans l'onglet **Payload** ou **Request**
5. Vous devriez voir :

```json
{
  "commentairePublic": "Dossier traité et archivé",
  "commentaireInterne": "Nécessite un suivi ultérieur"
}
```

## ✅ Résultat attendu

Si tout fonctionne correctement :
- ✅ Les commentaires sont affichés dans la console avant l'envoi
- ✅ Les commentaires sont envoyés dans la requête HTTP
- ✅ L'API renvoie les commentaires dans la réponse
- ✅ Le toast de succès s'affiche
- ✅ Le courrier est marqué comme "Classé" dans la liste

## 🐛 En cas de problème

### Si les commentaires ne sont pas envoyés :
1. Vérifiez que vous avez bien rempli le **commentaire interne** (obligatoire)
2. Vérifiez dans la console s'il y a des erreurs
3. Vérifiez dans l'onglet Network si la requête contient bien le payload

### Si l'API ne retourne pas les commentaires :
- C'est normal si votre backend ne renvoie pas encore ces champs
- Les commentaires sont quand même enregistrés en base de données
- Contactez le développeur backend pour vérifier que l'API `/core/courrier/geler/{id}` accepte et retourne les commentaires

## 📝 Structure de l'API

### Requête PATCH `/core/courrier/geler/{id}`
```json
{
  "commentairePublic": "string (optionnel)",
  "commentaireInterne": "string (obligatoire)"
}
```

### Réponse 200 OK
```json
{
  "message": "Courrier gelé avec succès",
  "id": 123,
  "is_geled": true,
  "statut": "Classé",
  "commentairePublic": "Dossier traité et archivé",
  "commentaireInterne": "Nécessite un suivi ultérieur",
  "transmissions_updated": 3,
  "updatedAt": "2025-12-19T10:30:00Z",
  "gele_par": {
    "id": 5,
    "nom": "Jean Dupont",
    "email": "jean.dupont@example.com"
  }
}
```
