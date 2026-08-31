## Objectif

Refonte structurelle de l'app MINEPIA autour d'une règle absolue : **zéro modale, zéro overlay, zéro popup** — toutes les actions (individuelles, sur bloc, ou groupées) se font par transformation **en place** de la zone concernée. Réorganisation en 3 onglets pilotés par un `activeTab` state (pas de routes).

## Contrainte non-négociable

Aucun `Dialog`, `Modal`, `Popover` centré, ni fond assombri pour les actions métier. Seuls tolérés : `Select` natifs shadcn, menus contextuels `⋮`, tooltips, toasts.

## Architecture cible

### Navigation
- Refonte `App` : mono-page avec `activeTab: "statistiques" | "biens" | "administration"` (state React).
- Header persistant : logo + titre + cloche notif + bloc utilisateur.
- Suppression des routes internes actuelles (Dashboard, Inventaires, Maintenance, Programmation, Projets deviennent soit des sous-onglets d'Administration, soit fusionnés dans Biens).

### Nouveaux composants partagés
| Composant | Rôle |
|---|---|
| `InlineFormBlock` | Bloc à 2 états `"read" \| "form"`, même carte/taille/position |
| `BulkActionBar` | Barre in-place au-dessus du tableau quand ≥1 ligne sélectionnée |
| `FilterPanel` | Panneau déployable sous la recherche, contexte-dépendant |
| `ExportButton`, `Pagination` | Déjà présents, réutilisés |

### Onglet Biens
- **Vue liste** : recherche + `FilterPanel` + `ExportButton` + cases à cocher par ligne + case "tout sélectionner" + `BulkActionBar` in-place (Affectation / Sortie / Réévaluation / Dépréciation / Maintenance / Exporter la sélection).
- Clic ligne → `activeView = "detail"` (même zone, pas de modal).
- Actions groupées → `activeView = "bulk-<action>"` : en-tête = liste des biens sélectionnés (retirables), puis le formulaire correspondant.
- **Vue détail** : grille de 7 blocs via `InlineFormBlock` :
  1. Informations générales (Modifier)
  2. Affectations / Propriétaires — propriétaire actuel (badge « Actuel ») + timeline historique + « + Nouvelle affectation » qui transforme **uniquement ce bloc**
  3. Localisation actuelle (Modifier)
  4. Informations financières (Modifier)
  5. Suivi du bien — historique complet (Date / Événement / Structure / Site / Utilisateur / Observation)
  6. Maintenance (Modifier + Ajouter maintenance in-place)
  7. Mouvements (Modifier)
- Aucun bloc « Documents », aucun upload.

### Onglet Statistiques
- Bouton Filtres déployable (5 colonnes : Organisation, Patrimoine, Statut, Source financière, Période).
- 6 KPI cards.
- Ligne 1 : Répartition par statut (**bar chart, pas donut**), Répartition par type (barres horizontales + tableau), Top 6 catégories (cartes colorées).
- Ligne 2 : Top 10 structures par nombre, Top 10 par valeur, bloc Maintenance (3 mini-KPI + line chart mensuel).

### Onglet Administration
- Sous-onglets : Rôles, Permissions, Utilisateurs, Types de biens, Catégories, Projets, Programmations, Services, Postes, Stockages. Même pattern liste/détail in-place.

## Formulaires métier (§4 du prompt)
Réutilisés à l'identique en contexte "bloc" ou "groupé" : Maintenance, Réévaluation, Dépréciation, Affectation (bascule automatique ancien détenteur → historique), Sortie/Mouvement. Aucun upload.

## Fichiers impactés (principaux)

**Nouveaux**
- `src/components/shared/InlineFormBlock.tsx`
- `src/components/shared/BulkActionBar.tsx`
- `src/components/shared/FilterPanel.tsx`
- `src/pages/patrimoine/BiensList.tsx`, `BienDetail.tsx`
- `src/pages/patrimoine/blocks/*` (un fichier par bloc de la fiche)
- `src/pages/patrimoine/forms/*` (Affectation, Maintenance, Reevaluation, Depreciation, Sortie)
- `src/pages/administration/Administration.tsx` + sous-modules

**Refondus**
- `src/App.tsx` / `src/router.tsx` : mono-page à onglets, plus de routes internes
- `src/components/shared/AppShell.tsx` : header + tabs, plus de sidebar
- `src/pages/Statistiques.tsx` : refonte complète widgets
- `src/pages/Biens.tsx` : remplacé par la nouvelle structure

**Supprimés / fusionnés**
- Pages Dashboard, Inventaires, Maintenance, Programmation, Projets standalone → réintégrées sous Administration ou Biens.

**Inchangés (visuellement)**
- `src/styles.css`, tokens, charte, mode sombre, i18n FR/EN.

## Étendue

C'est une **réécriture majeure** de la couche UI (probablement 25-40 fichiers touchés/créés). Le code métier existant (mock-data, api/) est conservé, seule la présentation change.

## Questions avant d'attaquer

1. **Portée immédiate** : je livre tout d'un coup (long, risque de régression) ou par phases (Phase 1 = shell mono-page + Biens liste/détail in-place + BulkActionBar ; Phase 2 = Statistiques refondues ; Phase 3 = Administration regroupée) ?
2. **Modules actuels supprimés** (Dashboard, Inventaires, Programmation, Maintenance, Projets en tant qu'onglets top-level) : confirmes-tu leur disparition de la nav principale au profit des 3 onglets Statistiques / Biens / Administration ?
3. **Onglet par défaut** au chargement : Biens (comme spécifié §1) — je confirme ?
