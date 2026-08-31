# 🔍 Audit des Endpoints API - MINEPIA Guardian Gem

**Date**: 2026-08-20  
**Backend**: http://185.98.136.192:8075  
**Source**: Swagger NelmioApiDoc (analysé en direct)

## 🔑 Légende
- ✅ **Implémenté côté front ET visible dans l'UI**
- 🟡 **Fonction API existe côté front mais PAS visible dans l'UI**
- ❌ **Endpoint backend existant, non implémenté côté front**
- ⚠️ **Problème connu / Bug / Incohérence**
- 💡 **Recommandation**

---

## 🔐 Authentication

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /login_check | POST | ✅ Page login | ✅ | Retourne token + refresh_token + langue |
| /refresh_token | POST | 🟡 Auto (axios interceptor) | ✅ | Refresh préventif + retry sur 401. Retourne aussi `langue` — **non utilisé côté front** |
| /logout | - | - | ❌ **N'EXISTE PAS dans Swagger** | `logoutApi()` dans le front appelle un endpoint inexistant. Déconnexion = suppression du localStorage uniquement |

💡 **Action requise** : `logoutApi()` devrait juste vider le localStorage, pas faire un appel API qui va échouer silencieusement.

---

## 👤 Profile

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /profile | GET | ✅ Page Profile | ✅ | Retourne aussi `assets[]` et `permissions{}` — **non exploités** |
| /profile/password | PATCH | ✅ Page Profile | ✅ | |
| /profile/language | PATCH | ❌ **Non implémenté** | ✅ | Backend supporte FR/EN/ES/DE/IT. Front a un sélecteur FR/EN mais **n'appelle pas cet endpoint** |

💡 **Action requise** : Connecter le sélecteur de langue dans `i18n.tsx` à `PATCH /profile/language` pour persister la préférence côté serveur.

---

## 👥 Users (Utilisateurs)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /users | GET | ✅ Config > Utilisateurs | ✅ | Retourne aussi `matricule` et `cni` — **non affichés** dans le tableau |
| /users | POST | ✅ Formulaire création | ✅ | Backend accepte aussi `matricule`, `cni`, `group_ids`, `granted_permission_ids`, `revoked_permission_ids` — **non dans le formulaire front** |
| /users/{id} | GET | 🟡 Non utilisé directement | ✅ | Retourne `assets[]` de l'utilisateur |
| /users/{id} | PUT/PATCH | ✅ Formulaire édition | ✅ | Idem POST — `matricule`, `cni`, `granted/revoked_permission_ids` manquants dans le form |
| /users/{id} | DELETE | ✅ Bouton supprimer | ✅ | Suppression physique (pas soft-delete) |
| /users/{id}/password | PATCH | ✅ Reset password | ✅ | |
| /users/{id}/two-factor | PATCH | ✅ Toggle 2FA | ✅ | |
| /users/{id}/permissions | GET | ❌ **Non implémenté** | ✅ | Retourne `inheritedFromRoles`, `inheritedFromGroupes`, `granted`, `revoked`, `effective` — **Utile pour afficher les permissions effectives** |
| /users/{id}/groupes | PATCH | ❌ **Non implémenté** | ✅ | Remplace toute la liste des groupes d'un utilisateur |
| /users/{userId}/groupes/{groupeId} | POST | ❌ **Non implémenté** | ✅ | Ajoute un groupe à un utilisateur |
| /users/{userId}/groupes/{groupeId} | DELETE | ❌ **Non implémenté** | ✅ | Retire un groupe d'un utilisateur |

⚠️ **Manques importants dans le formulaire utilisateur** :
- `matricule` : champ obligatoire pour l'identification officielle — absent du formulaire
- `cni` : numéro CNI — absent du formulaire
- `group_ids` : affecter des groupes à la création — absent du formulaire
- `granted/revoked_permission_ids` : permissions individuelles — absent du formulaire

💡 **Pour implémenter "Groupes d'utilisateurs"** : Il faut utiliser les endpoints `POST/DELETE /users/{id}/groupes/{groupeId}` ET `PATCH /users/{id}/groupes` (remplacement total). L'API est prête.

---

## 🏢 Services (Organigramme)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /services | GET | 🟡 Formulaire utilisateur uniquement | ✅ | Liste plate (sans hiérarchie) |
| /services | POST | ✅ Config > Organigramme | ✅ | Accepte `region_id`, `departement_id`, `arrondissement_id`, `typeOrganigrammes[]` |
| /services/{id} | GET | 🟡 Non utilisé directement | ✅ | |
| /services/{id} | PUT/PATCH | ✅ Config > Organigramme | ✅ | |
| /services/{id} | DELETE | ✅ Config > Organigramme | ✅ | Suppression physique |
| /organigramme | GET | ✅ Config > Organigramme | ✅ | Retourne hiérarchie avec `children[]` |
| /services/{id}/children | GET | ❌ **Non implémenté** | ✅ | Retourne un service + ses enfants directs |
| /type-organigrammes | GET | ✅ Config > Organigramme | ✅ | Types d'organigramme |
| /type-organigrammes | POST | ✅ | ✅ | |
| /type-organigrammes/{id} | GET/PATCH/DELETE | ✅ | ✅ | |

---

## 🔐 Rôles & Permissions

### Rôles

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /roles | GET | ✅ Config > Rôles | ✅ | Retourne permissions embarquées |
| /roles | POST | ✅ | ✅ | Accepte `permissions[]` à la création |
| /roles/{id} | GET | 🟡 | ✅ | |
| /roles/{id} | PUT/PATCH | ✅ | ✅ | Accepte `permissions[]` pour synchronisation |
| /roles/{id} | DELETE | ✅ | ✅ | **Suppression physique irréversible** + cascade sur associations |
| /roles/{id}/soft-delete | DELETE | 🟡 | ✅ | Soft-delete disponible mais le front utilise peut-être le DELETE direct |
| /roles/{roleId}/permissions | GET | ❌ **Non implémenté** | ✅ | Liste les permissions d'un rôle |
| /roles/{roleId}/permissions | POST | ✅ Via RolesSection | ✅ | Affecter plusieurs permissions |
| /roles/{roleId}/permissions/{permId} | POST | ✅ | ✅ | Affecter une permission |
| /roles/{roleId}/permissions/{permId} | DELETE | ✅ | ✅ | Retirer une permission |

### Permissions

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /permissions | GET | ✅ Config > Permissions | ✅ | |
| /permissions | POST | ✅ | ✅ | |
| /permissions/{id} | GET/PUT/PATCH/DELETE | ✅ | ✅ | |

---

## 👥 Groupes d'utilisateurs

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /groupes | GET | ✅ Config > Groupes | ✅ | ⚠️ Permissions non garanties dans la liste |
| /groupes/{id} | GET | 🟡 Chargé en édition | ✅ | **Seul endpoint** avec permissions complètes |
| /groupes | POST | ✅ | ✅ | Crée le groupe (sans permissions initiales) |
| /groupes/{id} | PATCH | ✅ | ✅ | Met à jour nom/description/is_active uniquement |
| /groupes/{id}/soft-delete | DELETE | ✅ | ✅ | Suppression logique |
| /groupes/{id}/permissions | POST | ✅ | ✅ | Affecter plusieurs permissions d'un coup |
| /groupes/{id}/permissions/{permId} | DELETE | ✅ | ✅ | Retirer une permission |

💡 **Manque côté UI** : La section Groupes existe dans la nav mais l'interface de gestion des membres (affecter/retirer des utilisateurs d'un groupe) n'est probablement pas implémentée — à vérifier. Les endpoints backend sont prêts (`POST/DELETE /users/{id}/groupes/{groupeId}`).

---

## 📦 Assets (Biens patrimoniaux)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /assets | GET | ✅ Page Biens | ✅ | |
| /assets/{id} | GET | ✅ Fiche détail | ✅ | Retourne `utilisateur{}` avec `matricule` |
| /assets | POST | ✅ Formulaire création | ✅ | multipart/form-data |
| /assets/{id} | POST | ✅ Formulaire édition | ✅ | multipart/form-data (pas PUT/PATCH) |
| /assets/{id}/soft-delete | DELETE | ✅ | ✅ | |
| /assets/{id}/restore | POST | 🟡 Non visible dans l'UI | ✅ | Existe dans le code mais pas de bouton "Restaurer" dans l'UI |
| /assets/{id}/mercurriale | GET | ✅ Bouton Mercuriale | ✅ | Typo dans l'URL ("mercurriale" avec 2 r) — à ne pas corriger, c'est côté backend |
| /asset-documents/{id} | DELETE | ✅ Suppression pièce jointe | ✅ | |
| /asset-documents/{id} | PUT | ✅ Renommage pièce jointe | ✅ | |
| /assets/{id}/exit | GET | 🟡 Dans asset-exits.api.ts | ✅ | Vérifie si un bien a une sortie |

---

## 🔄 Asset Events (Affectations, Maintenances, Réévaluations, Dépréciations)

### Affectations

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /assets/{id}/affectations | GET | ✅ Section Affectation fiche bien | ✅ | |
| /assets/{id}/affectations | POST | ✅ Formulaire affectation | ✅ | |
| /affectations/{id} | PUT | ✅ | ✅ | |
| /affectations/{id} | DELETE | ✅ | ✅ | |

### Maintenances

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /assets/{id}/maintenances | POST | ✅ Section Maintenance fiche bien | ✅ | |
| /maintenances/{id} | GET | 🟡 Via localStorage IDs | ✅ | ⚠️ Contournement : les IDs sont stockés en localStorage, pas récupérés via liste |
| /maintenances/{id} | PUT | ✅ | ✅ | |
| /maintenances/{id} | DELETE | ✅ | ✅ | |

⚠️ **Architecture fragile** : Les maintenances, réévaluations et dépréciations utilisent localStorage pour stocker les IDs. Si localStorage est vidé, les données disparaissent de l'UI (même si elles sont en base). Un `GET /assets/{id}/maintenances` serait plus fiable.

### Réévaluations

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /assets/{id}/reevaluations | POST | ✅ | ✅ | |
| /reevaluations/{id} | GET/PUT/DELETE | 🟡/✅ | ✅ | Même problème localStorage que maintenances |

### Dépréciations

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /assets/{id}/depreciations | POST | ✅ | ✅ | |
| /depreciations/{id} | GET/PUT/DELETE | 🟡/✅ | ✅ | Même problème localStorage |

---

## 🚪 Asset Exits (Sorties de biens)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /asset-exits | GET | 🟡 Utilisé pour panel réforme | ✅ | ⚠️ Format réponse instable — code a 4 fallbacks différents pour parser la réponse |
| /asset-exits | POST | ✅ Via SortieFormInline | ✅ | multipart/form-data |
| /asset-exits/{id} | GET | 🟡 | ✅ | |
| /asset-exits/{id} | PUT | 🟡 | ✅ | |
| /asset-exits/{id} | DELETE | 🟡 | ✅ | |
| /assets/{id}/exit | GET | 🟡 | ✅ | Sortie d'un bien donné |

⚠️ **Un bien ne peut avoir qu'UNE sortie** — le backend retourne 400 si on essaie d'en créer une seconde.

---

## 📋 BSP (Bons de Sortie Provisoire)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /asset-exits/{id}/bsps | GET | 🟡 Dans bsps.api.ts | ✅ | |
| /asset-exits/{id}/bsps | POST | 🟡 Dans bsps.api.ts | ✅ | |
| /bsps/{id} | GET | 🟡 | ✅ | |
| /bsps/{id} | PUT | 🟡 | ✅ | |
| /bsps/{id} | DELETE | 🟡 | ✅ | |
| /bsps/{id}/attachments/{attachId} | DELETE | 🟡 | ✅ | |

💡 **Module BSP non visible dans l'UI principale**. Les fonctions existent dans `bsps.api.ts` mais aucune page dédiée.

---

## 🔒 Securities (Sécurisations de biens)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /securities | GET | ❌ **Non visible** | ✅ | Liste avec filtre `asset_id`, `security_mode` |
| /securities | POST | ❌ **Non visible** | ✅ | multipart/form-data, plusieurs biens d'un coup |
| /securities/{id} | GET | ❌ **Non visible** | ✅ | ⚠️ Détail retourne `securityMode` comme objet `{id, nom}` vs liste qui retourne string — incohérence backend |
| /securities/{id} | POST | ❌ **Non visible** | ✅ | Modification |
| /securities/{id} | DELETE | ❌ **Non visible** | ✅ | Soft-delete ou hard-delete avec `force=true` |

⚠️ **Module Securities complet dans le code** (`securities.api.ts`) mais **aucune UI** pour le gérer. À implémenter dans la fiche détail d'un bien ou dans un module dédié.

---

## 📦 Consomptibles

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /consumables | GET | ✅ Page Consomptibles | ✅ | |
| /consumables/{id} | GET | 🟡 Chargé avant édition | ✅ | |
| /consumables | POST | ✅ Formulaire création | ✅ | multipart/form-data |
| /consumables/{id} | PUT | ✅ Formulaire édition | ✅ | ⚠️ `quantite` non modifiable après création |
| /consumables/{id} | DELETE | ✅ Bouton supprimer | ✅ | Soft-delete |
| /consumables/{id}/restore | POST | ✅ Bouton restaurer | ✅ | |
| /consumables/{id}/documents/{docId} | DELETE | ✅ Supprimer PJ | ✅ | |

⚠️ **Bug backend connu** : `asset_type_id` et `asset_sub_type_id` → 500 "Undefined method findActiveById". **Non utilisés** dans le formulaire par sécurité.

### Transferts de consomptibles

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /consumable-transfers | GET | 🟡 Dans le dialog de détail | ✅ | |
| /consumable-transfers/by-consumable/{id} | GET | ✅ Dialog ConsumableDetailDialog | ✅ | Tableau brut (pas paginé) |
| /consumable-transfers/by-service/{id} | GET | ❌ **Non utilisé dans l'UI** | ✅ | Utile pour une vue "Transferts par service" |
| /consumable-transfers/{id} | GET | 🟡 | ✅ | |
| /consumable-transfers | POST | ✅ Formulaire transfert | ✅ | multipart/form-data, types TRANSFERT_DIRECT ou BSP |
| /consumable-transfers/{id} | PATCH | 🟡 | ✅ | |
| /consumable-transfers/{id} | DELETE | 🟡 | ✅ | Soft-delete |
| /consumable-transfers/{id}/force | DELETE | 🟡 | ✅ | Hard-delete irréversible |
| /consumable-transfers/{id}/restore | PATCH | 🟡 | ✅ | |
| /consumable-transfers/{id}/bsp/retour | PATCH | ❌ **Non visible** | ✅ | Confirmer le retour d'un BSP consomptible |
| /consumable-transfers/{id}/piece-jointe/{pid} | DELETE | 🟡 | ✅ | |

⚠️ **Incohérence backend** : `pieceJointes` (sans "s") en lecture vs `piecesJointes[]` (avec "s") en écriture — déjà géré dans `normalizeTransfer()`.

---

## 📁 Catégories

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /categories | GET | ✅ Config > Catégories + selects formulaires | ✅ | |
| /categories/{id} | GET | 🟡 | ✅ | |
| /categories | POST | ✅ Config > Catégories | ✅ | |
| /categories/{id} | PUT | ✅ | ✅ | |
| /categories/{id}/soft-delete | DELETE | ✅ | ✅ | |
| /categories/{id}/restore | POST | ✅ | ✅ | |

---

## 🏷️ Types de biens

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /asset-types | GET | ✅ Config > Types + select formulaire biens | ✅ | |
| /asset-types/{id} | GET | 🟡 | ✅ | |
| /asset-types | POST | ✅ Config > Types | ✅ | |
| /asset-types/{id} | PUT | ✅ | ✅ | |
| /asset-types/{id}/soft-delete | DELETE | ✅ | ✅ | |
| /asset-types/{id}/restore | POST | ✅ | ✅ | |

---

## 🏷️ Sous-types de biens

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /asset-sub-types | GET | ❌ **Non visible dans l'UI** | ✅ | |
| /asset-sub-types/{id} | GET | ❌ | ✅ | |
| /asset-sub-types | POST | ❌ | ✅ | |
| /asset-sub-types/{id} | PATCH | ❌ | ✅ | |
| /asset-sub-types/{id}/soft-delete | DELETE | ❌ | ✅ | |
| /asset-sub-types/{id}/restore | POST | ❌ | ✅ | |

🔴 **DUPLICATION de code** : 2 fichiers font la même chose (`asset-sub-types.api.ts` et `asset-subtypes.api.ts`). Un utilise PATCH, l'autre PUT. Garder `asset-sub-types.api.ts` (PATCH = plus RESTful).

💡 **À implémenter** : Section "Sous-types de biens" dans Config + select en cascade dans le formulaire de bien (Catégorie → Type → Sous-type).

---

## 🎨 États des biens

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /etat-biens | GET | ✅ Config > États + select formulaire | ✅ | |
| /etat-biens | POST | ✅ Config > États | ✅ | |
| /etat-biens/{id} | PUT | ✅ | ✅ | |
| /etat-biens/{id} | DELETE | ✅ | ✅ | |

---

## 🚪 Types de sortie

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /exit-types | GET | ✅ Config > Types de sortie | ✅ | ⚠️ Format réponse différent : `{ items[], pagination{} }` au lieu de `{ meta, data }` |
| /exit-types/{id} | GET | 🟡 | ✅ | |
| /exit-types | POST | ✅ | ✅ | |
| /exit-types/{id} | PUT | ✅ | ✅ | |
| /exit-types/{id} | DELETE | ✅ | ✅ | **Suppression physique** (pas soft-delete) |

⚠️ **Cohérence** : Tous les autres modules ont soft-delete + restore, mais exit-types utilise DELETE direct.

---

## 💼 Projets (Sources de financement)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /projects | GET | ✅ Config > Sources financement | ✅ | |
| /projects | POST | ✅ | ✅ | |
| /projects/{id} | PUT | ✅ | ✅ | |
| /projects/{id}/soft-delete | DELETE | ✅ | ✅ | |
| /projects/{id}/restore | PUT | ✅ | ✅ | ⚠️ Utilise PUT (pas POST comme les autres modules) |
| /projects/{id}/status | PATCH | ❌ **Non implémenté** | ✅ | Statuts : PLANIFIE, EN_COURS, TERMINE — **non géré côté front** |

💡 **Le statut des projets** (`PLANIFIE`, `EN_COURS`, `TERMINE`) n'est pas géré côté front. Utile pour filtrer les sources de financement actives.

---

## 🔧 Champs personnalisés

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /champs | GET | ✅ Config > Champs | ✅ | |
| /champs/{id} | GET | 🟡 Chargé avant édition | ✅ | Inclut `categories[]` embarquées |
| /champs | POST | ✅ | ✅ | |
| /champs/{id} | PATCH | ✅ | ✅ | `category_ids[]` remplace toute l'association |
| /champs/{id}/soft-delete | DELETE | ✅ | ✅ | |
| /champs/{id}/restore | POST | ✅ | ✅ | |

---

## 📊 Inventaire

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /inventaire | GET | ❌ **Page Inventaires.tsx non connectée à l'API** | ✅ | L'API `getInventaire()` existe mais la page Inventaires utilise probablement encore des mocks |

💡 **À connecter** : La page `Inventaires.tsx` devrait utiliser `GET /inventaire?categories=all` pour afficher l'inventaire réel.

---

## 🗺️ Géographie

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /regions | GET/POST/PUT | ✅ Config > Cartographie | ✅ | POST accepte un seul objet OU un tableau (1-10 régions) |
| /regions/{id}/soft-delete | DELETE | ✅ | ✅ | Accepte `?force=true` pour suppression définitive |
| /departements | GET/POST/PUT | ✅ Config > Cartographie | ✅ | |
| /arrondissements | GET/POST/PUT | ✅ Config > Cartographie | ✅ | |
| /cartographie | GET | ✅ Config > Cartographie | ✅ | Hiérarchie complète Région→Département→Arrondissement |

---

## 📊 Statistiques

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /api/stats/vue-globale | GET | ❌ **Non connecté** | ✅ | |
| /api/stats/repartition-par-structure | GET | ❌ | ✅ | |
| /api/stats/repartition-par-projet | GET | ❌ | ✅ | |
| /api/stats/repartition-par-region | GET | ❌ | ✅ | Pour carte géographique |
| /api/stats/evolution-mensuelle | GET | ❌ | ✅ | Séries temporelles |
| /api/stats/repartition-par-categorie | GET | ❌ | ✅ | |
| /api/stats/evolution-gap | GET | ❌ | ✅ | Comparaison entre 2 années |
| /api/stats/classement-regions | GET | ❌ | ✅ | |
| /api/stats/vehicules/* | GET | ❌ | ✅ | 8 endpoints véhicules |
| /api/stats/terrains/* | GET | ❌ | ✅ | 7 endpoints terrains |
| /api/stats/batiments/* | GET | ❌ | ✅ | 8 endpoints bâtiments |
| /api/stats/structures/* | GET | ❌ | ✅ | 4 endpoints structures |
| /api/stats/suivi/* | GET | ❌ | ✅ | 5 endpoints suivi |

🔴 **Module Statistiques non connecté au backend** : La page `Statistiques.tsx` utilise probablement des données mock. Le backend offre **35+ endpoints de statistiques** très riches (véhicules, terrains, bâtiments, structures, suivi temporel...).

---

## 📤 Upload

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /upload | POST | ❌ **Non utilisé directement** | ✅ | Upload générique — les uploads se font via les endpoints métier (assets, consumables, etc.) |

---

## 🔢 Inputs (Champs personnalisés - valeurs)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /inputs/* | - | ❌ **Non implémenté** | 🔍 À vérifier | Tag "Inputs" visible dans Swagger mais endpoints non détaillés dans la réponse |

---

## 📍 Locations (Localisations de biens)

| Endpoint | Méthode | Front visible | Backend OK | Commentaires |
|----------|---------|--------------|------------|--------------|
| /locations/* | - | ❌ **Non implémenté** | 🔍 À vérifier | `asset-locations.api.ts` existe mais les endpoints détaillés ne sont pas dans la réponse Swagger |

---

## 🧾 Récapitulatif des problèmes prioritaires

### 🔴 Bugs / Problèmes bloquants
1. **`logoutApi()`** appelle un endpoint qui n'existe pas — à corriger
2. **`asset_type_id` dans consumables** → 500 backend — à corriger côté backend
3. **Maintenances/Réévaluations/Dépréciations via localStorage** — architecture fragile
4. **Format réponse `/asset-exits`** instable — 4 fallbacks côté front
5. **Duplication** `asset-sub-types/` vs `asset-subtypes/` — à nettoyer

### 🟡 Fonctionnalités backend disponibles mais non connectées côté UI
1. **Statistiques réelles** : 35+ endpoints disponibles, page front utilise probablement des mocks
2. **Sous-types de biens** : Endpoints prêts, aucune UI
3. **Sécurisations de biens** : Module complet dans le code, aucune UI
4. **`matricule` et `cni` utilisateur** : Backend les gère, formulaire front les ignore
5. **Permissions effectives utilisateur** : `GET /users/{id}/permissions` disponible, non affiché
6. **Langue via API** : `PATCH /profile/language` disponible, non appelé
7. **Statut projet** : `PATCH /projects/{id}/status` disponible, non géré
8. **Inventaire réel** : `GET /inventaire` disponible, page probablement encore sur mocks
9. **BSP** : Module complet, aucune page dédiée
10. **`/consumable-transfers/bsp/retour`** : Confirmation retour BSP non implémentée

### 💡 Recommandations pour les prochaines implémentations
1. Avant d'implémenter les **sous-types de biens** : vérifier que le bug `findActiveById` est corrigé
2. Avant d'implémenter **groupes dans le formulaire utilisateur** : les 3 endpoints sont prêts (`POST/DELETE /users/{id}/groupes/{groupeId}` + `PATCH /users/{id}/groupes`)
3. Pour les **statistiques** : créer un fichier `src/api/stats/stats.api.ts` avec les 35 endpoints
4. Pour les **BSP** : décider si c'est un sous-module de "Sorties" ou une page dédiée

---

**Généré le** : 2026-08-20 — Basé sur l'analyse du Swagger http://185.98.136.192:8075
