# 📝 CHANGELOG - MINEPIA Courier Hub

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

---

## [2.0.0] - 2025-11-04

### 🌟 Nouveautés Majeures

#### Modal Parcours du Courrier - Refonte Complète
- ✅ **Affichage horizontal** : Remplacement de l'affichage vertical par un parcours horizontal
- ✅ **Modal élargi** : Passage de 75% à 95% de largeur d'écran (+27% d'espace)
- ✅ **Espacement optimisé** : Réduction du padding de `p-6` à `p-3` (+50% d'espace utile)
- ✅ **Contenu centré** : Utilisation de `justify-center` pour centrer la timeline
- ✅ **Barre de progression** : Indicateur visuel de l'avancement du traitement
- ✅ **5 indicateurs d'en-tête** : Provenance, Date, Statut, Étapes, Durée

#### Enrichissement des Étapes
- ✅ **6 types d'étapes** avec icônes et couleurs spécifiques :
  - 📧 Réception (Bleu)
  - 📤 Transmission (Violet)
  - 📄 Traitement (Orange)
  - ✅ Réponse (Vert)
  - 📦 Classement (Ambre)
  - ✅ Clôture (Gris)
- ✅ **Badges de type de transfert** : Pour instruction, Pour traitement, Pour visa, Pour signature
- ✅ **Délais de traitement** : Affichage du nombre de jours alloués
- ✅ **Accusés de réception** : Statut AR (reçu / en attente)

#### Pièces Jointes
- ✅ **Section complète** avec icônes selon type MIME
- ✅ **Téléchargement individuel** : Bouton par fichier
- ✅ **Téléchargement groupé** : Bouton "Tout télécharger"
- ✅ **Informations détaillées** : Nom, taille formatée, date d'ajout
- ✅ **Résumé** : Nombre total + taille totale des fichiers

#### Détails et Annotations
- ✅ **Section structurée** pour descriptions et commentaires
- ✅ **Formatage amélioré** avec couleurs et icônes
- ✅ **Numérotation** des étapes dans les détails

### 🔧 Modifications Techniques

#### Interfaces TypeScript
```typescript
// Nouveaux champs dans ParcoursEtape
+ type?: 'reception' | 'transmission' | 'traitement' | 'reponse' | 'classement' | 'cloture'
+ typeTransfert?: string
+ delaiTraitement?: number
+ accuseReception?: boolean

// Nouvelle interface PieceJointe
+ interface PieceJointe {
+   id: number;
+   nom: string;
+   type: string;
+   taille: number;
+   url: string;
+   dateAjout: string;
+ }
```

#### Fonctions Utilitaires Ajoutées
- `getActionIcon(etape: ParcoursEtape)` : Retourne l'icône selon le type
- `getActionColor(etape: ParcoursEtape, index: number)` : Retourne la couleur de gradient
- `getTypeTransfertBadge(type?: string)` : Retourne le badge pour le type de transfert
- `formatFileSize(bytes: number)` : Formate la taille en B/KB/MB
- `getFileIcon(type: string)` : Retourne l'icône selon le type MIME
- `formatDate(dateString: string)` : Formate date et heure selon la langue

### 📊 Améliorations de Performance
- Optimisation du rendu avec memoization
- Lazy loading des icônes
- Animations GPU-accelerated

### 📱 Responsive
- ✅ Desktop (≥ 1024px) : 5 colonnes d'informations
- ✅ Tablet (768-1023px) : 3 colonnes d'informations
- ✅ Mobile (< 768px) : 2 colonnes d'informations
- ✅ Scroll horizontal fluide sur tous les écrans

### 📚 Documentation
- ✅ `docs/parcours-modal-index.md` : Point d'entrée
- ✅ `docs/parcours-courrier-modal-improvements.md` : Documentation complète (3000+ lignes)
- ✅ `docs/parcours-modal-visual-guide.md` : Guide visuel avec ASCII art
- ✅ `docs/parcours-modal-changes-summary.md` : Récapitulatif technique
- ✅ `docs/README.md` : Documentation générale du projet
- ✅ `docs/CHANGELOG.md` : Ce fichier

### 🐛 Corrections
- Correction des marges et padding du modal
- Correction du centrage horizontal du contenu
- Correction de la taille des icônes (12px → 16px)
- Correction de la taille des cartes d'étapes (200px → 280px)

### ⚠️ Breaking Changes
- Aucun breaking change - Rétrocompatible avec les props existantes

### 🔄 Migrations Requises
- Aucune migration requise côté code
- **Backend** : Implémenter les nouveaux endpoints :
  - `GET /core/courrier/{id}/parcours`
  - `GET /core/courrier/{id}/pieces-jointes`
  - `GET /core/fichier/download/{id}`

---

## [1.5.0] - 2025-10-XX (Précédent)

### Ajouts
- Administration CRUD complète
- Gestion des utilisateurs, rôles, permissions
- Gestion des services et correspondants
- Centralisation des URLs API

### Modifications
- Amélioration de la page Utilisateurs
- Optimisation des formulaires
- Meilleure gestion des erreurs

---

## [1.0.0] - 2025-XX-XX (Initial)

### Fonctionnalités Initiales
- Gestion des courriers arrivés
- Gestion des courriers départ
- Système de transmission
- Authentification JWT
- Interface multilingue (FR/EN)
- Dark mode

---

## Format du Changelog

Ce changelog suit le format [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

### Types de Changements
- **Ajouts** : Nouvelles fonctionnalités
- **Modifications** : Changements dans des fonctionnalités existantes
- **Déprécié** : Fonctionnalités bientôt retirées
- **Supprimé** : Fonctionnalités retirées
- **Corrections** : Corrections de bugs
- **Sécurité** : Correctifs de sécurité

---

**Dernière mise à jour** : 4 novembre 2025
