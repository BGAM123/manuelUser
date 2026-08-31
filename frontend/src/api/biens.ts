/**
 * Types de compatibilité pour les pages qui référencent encore l'ancien
 * modèle Bien (Projets.tsx, Statistiques.tsx, programmation.ts).
 *
 * mockBiens est désormais un tableau vide — les données réelles viennent
 * de l'API via listBiens() dans biens.api.ts.
 */

import { unites, detenteurs } from "./common";

// Re-export pour rétrocompatibilité (programmation.ts l'utilise)
export const cats = [
  "Immobilier",
  "Mobilier",
  "Informatique",
  "Roulant",
  "Cheptel",
] as const;

export type BienCategorie = (typeof cats)[number];

export const designationsBy: Record<BienCategorie, string[]> = {
  Immobilier: ["Bâtiment administratif", "Hangar de stockage", "Terrain lot 42", "Logement de fonction"],
  Mobilier: ["Bureau ministre", "Chaise visiteur", "Armoire métallique", "Table de réunion"],
  Informatique: ["Ordinateur portable Dell", "Imprimante HP LaserJet", "Serveur Rack", "Onduleur 3kVA"],
  Roulant: ["Toyota Hilux", "Peugeot 508", "Moto Yamaha DT", "Camion Isuzu"],
  Cheptel: ["Bovins reproducteurs", "Ovins race locale", "Volailles pondeuses", "Caprins"],
};

/**
 * Type Bien utilisé par les pages Projets et Statistiques (UI locale).
 * Distinct de ApiBien (src/api/biens.api.ts) qui représente le backend.
 */
export type Bien = {
  id: string;
  numero: string;
  designation: string;
  categorie: BienCategorie;
  acquisitionDate: string;
  valeurAcquisition: number;
  detenteur: string;
  unite: string;
  etat: "Bon" | "Passable" | "Mauvais";
  statut: "Actif" | "En litige" | "En maintenance" | "À réformer" | "Réformé";
  localisation: string;
};

/**
 * mockBiens est vide — les données réelles proviennent du backend.
 * Conservé pour que Projets.tsx et Statistiques.tsx compilent sans erreur.
 * Ces pages devront être connectées à l'API dans une prochaine étape.
 */
export const mockBiens: Bien[] = [];

// Conservé pour éviter des erreurs d'import dans les autres modules
export { unites, detenteurs };
