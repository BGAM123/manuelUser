# Guide de Test - Module Courrier Interne

## Prérequis
- Serveur de développement démarré (`npm run dev`)
- Utilisateur connecté avec permissions appropriées
- Backend API accessible (http://185.98.136.192:8081)

---

## Test 1: Navigation vers le module

### Étapes
1. Connectez-vous à l'application
2. Naviguez vers **Courriers Traitement** (`/courriers-traitement`)
3. Vérifiez la présence du bouton **"Courrier Interne"** en haut à droite
4. Cliquez sur le bouton

### Résultat attendu
✓ Redirection vers `/courrier-interne`  
✓ Page affichée avec titre "Gestion des Courriers Internes"  
✓ 2 onglets visibles: "Mes courriers internes" et "Courrier interne envoyés"

---

## Test 2: Affichage des onglets

### Test 2.1: Onglet "Mes courriers internes"
**Étapes:**
1. Assurez-vous que l'onglet est actif
2. Vérifiez la présence du bouton "Nouveau Courrier"
3. Vérifiez la présence des filtres (recherche, date, priorité)
4. Vérifiez le tableau des courriers

**Résultat attendu:**
✓ Filtres fonctionnels  
✓ Tableau avec colonnes: Référence, Émetteur, Objet, Type, Priorité, Date, Délai  
✓ Pagination si > 10 courriers

### Test 2.2: Onglet "Courrier interne envoyés"
**Étapes:**
1. Cliquez sur l'onglet "Courrier interne envoyés"
2. Vérifiez les mêmes éléments que 2.1

**Résultat attendu:**
✓ Colonne "Destinataire" au lieu de "Émetteur"  
✓ Filtres et pagination identiques

---

## Test 3: Création d'un courrier interne

### Étapes
1. Cliquez sur le bouton **"Nouveau Courrier"**
2. Vérifiez l'ouverture du modal
3. Remplissez le formulaire:
   - **Poste destinataire:** Sélectionnez un service dans l'arborescence
   - **Objet:** "Test courrier interne"
   - **Type de transfert:** Pour Instruction
   - **Délai de traitement:** 7
   - **Commentaire:** "Ceci est un test"
4. Ajoutez une pièce jointe (optionnel)
5. Cliquez sur **"Créer"**

### Résultat attendu
✓ Modal se ferme après validation  
✓ Toast de succès: "Le courrier interne a été créé avec succès"  
✓ Tableau rafraîchi automatiquement  
✓ Nouveau courrier visible dans l'onglet "Courrier interne envoyés"

---

## Test 4: Validation du formulaire

### Test 4.1: Champs requis
**Étapes:**
1. Ouvrez le modal
2. Laissez le formulaire vide
3. Cliquez sur "Créer"

**Résultat attendu:**
✓ Messages d'erreur affichés:
  - "Sélectionnez un poste destinataire"
  - "L'objet est requis"
  - "Sélectionnez le type de transfert"

### Test 4.2: Validation partielle
**Étapes:**
1. Remplissez uniquement l'objet
2. Cliquez sur "Créer"

**Résultat attendu:**
✓ Erreurs uniquement sur les champs vides

---

## Test 5: Filtres

### Test 5.1: Filtre de recherche
**Étapes:**
1. Dans l'onglet "Mes courriers internes"
2. Tapez dans la barre de recherche: "test"
3. Attendez 300ms (debounce)

**Résultat attendu:**
✓ Tableau filtré en temps réel  
✓ Affichage uniquement des courriers contenant "test" (référence ou objet)

### Test 5.2: Filtre par date
**Étapes:**
1. Cliquez sur le sélecteur de date
2. Sélectionnez une plage de dates
3. Vérifiez le filtrage

**Résultat attendu:**
✓ Courriers filtrés par date d'arrivée

### Test 5.3: Filtre par priorité
**Étapes:**
1. Sélectionnez "Haute" dans le filtre de priorité
2. Vérifiez le tableau

**Résultat attendu:**
✓ Affichage uniquement des courriers prioritaires

### Test 5.4: Réinitialisation des filtres
**Étapes:**
1. Appliquez plusieurs filtres
2. Cliquez sur "Réinitialiser les filtres"

**Résultat attendu:**
✓ Tous les filtres reviennent à leur état initial

---

## Test 6: Sélection de colonnes

### Étapes
1. Cliquez sur l'icône de colonnes (en haut à droite du tableau)
2. Décochez "Type"
3. Vérifiez le tableau
4. Rafraîchissez la page

**Résultat attendu:**
✓ Colonne "Type" masquée  
✓ Préférence sauvegardée (localStorage)  
✓ Après rafraîchissement, colonne toujours masquée

---

## Test 7: Détails d'un courrier

### Étapes
1. Cliquez sur un courrier dans le tableau
2. Vérifiez l'ouverture du panneau de détails (MailDetailsSheet)
3. Vérifiez les informations affichées

**Résultat attendu:**
✓ Panneau latéral ouvert  
✓ Toutes les informations du courrier affichées  
✓ Bouton de fermeture fonctionnel

---

## Test 8: Pagination

### Étapes (si > 10 courriers)
1. Vérifiez le nombre total de courriers
2. Cliquez sur "Page suivante"
3. Vérifiez le chargement de la page 2
4. Changez le nombre d'éléments par page (10 → 25)

**Résultat attendu:**
✓ Navigation entre pages fonctionnelle  
✓ Affichage du nombre total correct  
✓ Changement de taille de page fonctionnel

---

## Test 9: Responsive (Mobile)

### Étapes
1. Ouvrez les DevTools (F12)
2. Activez le mode responsive (Ctrl+Shift+M)
3. Testez en 375px (iPhone SE)
4. Testez en 768px (iPad)

**Résultat attendu:**
✓ Layout adapté aux petits écrans  
✓ Boutons accessibles  
✓ Tableau défilable horizontalement si nécessaire  
✓ Modal occupe toute la largeur sur mobile

---

## Test 10: Traductions

### Étapes
1. Changez la langue en anglais (menu utilisateur)
2. Naviguez vers Courrier Interne
3. Vérifiez toutes les traductions

**Résultat attendu:**
✓ Titre: "Internal Mail Management"  
✓ Onglets traduits  
✓ Formulaire traduit  
✓ Messages toast traduits

---

## Test 11: Gestion des erreurs

### Test 11.1: Erreur API
**Simulation:**
1. Coupez la connexion réseau (ou arrêtez le backend)
2. Essayez de créer un courrier

**Résultat attendu:**
✓ Message d'erreur affiché  
✓ Formulaire reste ouvert  
✓ Pas de perte de données saisies

### Test 11.2: Timeout
**Simulation:**
1. Simulez une requête lente (DevTools → Network → Slow 3G)
2. Créez un courrier

**Résultat attendu:**
✓ Bouton "Création..." désactivé pendant le chargement  
✓ Pas de double soumission  
✓ Message de succès après réponse du serveur

---

## Test 12: Performance

### Mesures
1. Ouvrez DevTools → Lighthouse
2. Lancez un audit de performance sur `/courrier-interne`
3. Vérifiez les métriques:
   - First Contentful Paint (FCP)
   - Largest Contentful Paint (LCP)
   - Time to Interactive (TTI)

**Résultat attendu:**
✓ Score > 90  
✓ Pas de warnings dans la console  
✓ Pas de fuites mémoire

---

## Checklist finale

- [ ] Navigation depuis CourriersTraitement fonctionne
- [ ] 2 onglets affichés correctement
- [ ] Modal de création s'ouvre/se ferme
- [ ] Formulaire validé avec Zod
- [ ] Création de courrier interne réussie
- [ ] Toast de succès affiché
- [ ] Tableau rafraîchi automatiquement
- [ ] Filtres fonctionnels (recherche, date, priorité)
- [ ] Sélection de colonnes sauvegardée
- [ ] Détails de courrier affichés
- [ ] Pagination fonctionnelle
- [ ] Responsive mobile OK
- [ ] Traductions FR/EN complètes
- [ ] Gestion d'erreurs robuste
- [ ] Performance acceptable
- [ ] Aucune erreur console

---

## Bugs connus / Limitations

### À implémenter (Backend)
- ⚠️ Filtre `type: 'interne'` non encore implémenté dans l'API
- ⚠️ Upload de fichiers nécessite configuration serveur
- ⚠️ Permissions utilisateur à vérifier

### Améliorations futures
- Actions groupées (transmission multiple)
- Statistiques courriers internes
- Notifications temps réel
- Export PDF/Excel
- Historique des modifications

---

## Contact & Support

En cas de problème:
1. Vérifier la console navigateur (F12)
2. Vérifier les logs serveur backend
3. Consulter la documentation: `COURRIER_INTERNE_IMPLEMENTATION.md`

---

**Version:** 1.0  
**Date:** Janvier 2025
