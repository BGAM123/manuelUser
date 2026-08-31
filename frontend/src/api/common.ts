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

export const formatFCFA = (n: number) =>
  new Intl.NumberFormat("fr-FR").format(n) + " FCFA";