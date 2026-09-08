// Données de référence partagées et utilitaires de formatage.
export const unites = [
  "MINEPIA/Cabinet",
  "DRPIA Centre",
  "DRPIA Littoral",
  "DDPIA Mfoundi",
  "DDPIA Wouri",
  "Poste vétérinaire Yaoundé",
];

export const detenteurs = [
  "M. NDONGO Paul",
  "Mme MBALLA Rose",
  "M. FOUDA Emmanuel",
  "Mme ATANGANA Jeanne",
  "M. NKOMO André",
  "Service commun",
];

// Intl.NumberFormat("fr-FR") sépare les milliers par une espace fine
// insécable (U+202F). Cette espace n'existe pas dans les polices utilisées
// par jsPDF (Helvetica) et s'y affiche comme un "/" dans les documents
// PDF exportés (ex. fiche bien, inventaire) — on la remplace donc toujours
// par une espace normale (demande explicite, 2026-09-01).
const stripNarrowSpaces = (s: string) => s.replace(/[\u202f\u00a0]/g, " ");

export const formatFCFA = (n: number) =>
  stripNarrowSpaces(new Intl.NumberFormat("fr-FR").format(n)) + " FCFA";

/**
 * Même formatage numérique que formatFCFA() mais SANS l'unité — à utiliser
 * dans les cellules de tableaux dont l'en-tête de colonne indique déjà
 * l'unité (ex. "Valeur (FCFA)"), pour ne pas la répéter sur chaque ligne
 * (demande explicite, 2026-09-01).
 */
export const formatAmount = (n: number) =>
  stripNarrowSpaces(new Intl.NumberFormat("fr-FR").format(n));