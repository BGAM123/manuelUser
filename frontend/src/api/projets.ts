import { mockBiens } from "./biens";

export type ProjetStatut = "Planifié" | "En cours" | "Suspendu" | "Clôturé";

export type Projet = {
  id: string;
  code: string;
  intitule: string;
  description: string;
  responsable: string;
  structure: string;
  dateDebut: string;
  dateFin: string;
  budget: number;
  statut: ProjetStatut;
};

export type ProjetBien = {
  id: string;
  projetId: string;
  bienId: string;
  dateAffectation: string;
  utilisateur: string;
  commentaire: string;
  actif: boolean;
};

export type MouvementProjetType =
  | "Entrée"
  | "Sortie"
  | "Transfert"
  | "Retour stock"
  | "Réforme"
  | "Perte"
  | "Vol"
  | "Destruction";

export type MouvementProjet = {
  id: string;
  projetId: string;
  bienId: string;
  type: MouvementProjetType;
  date: string;
  utilisateur: string;
  observation: string;
};

export type HistoriqueProjet = {
  id: string;
  projetId: string;
  date: string;
  utilisateur: string;
  action: string;
  ancienneValeur: string;
  nouvelleValeur: string;
};

const projetsSeed: Omit<Projet, "id">[] = [
  { code: "PRJ-2024-VAC", intitule: "Campagne nationale de vaccination bovine", description: "Déploiement de la campagne de vaccination contre la PPCB dans 4 régions.", responsable: "M. NDONGO Paul", structure: "MINEPIA/DSV", dateDebut: "2024-03-01", dateFin: "2025-02-28", budget: 450_000_000, statut: "En cours" },
  { code: "PRJ-2025-AQUA", intitule: "Aquaculture continentale — Phase II", description: "Extension des sites piscicoles pilotes du Centre et du Littoral.", responsable: "Mme MBALLA Rose", structure: "DRPIA Littoral", dateDebut: "2025-05-01", dateFin: "2027-04-30", budget: 1_200_000_000, statut: "En cours" },
  { code: "PRJ-2023-LAB", intitule: "Renforcement du laboratoire vétérinaire national", description: "Acquisition d'équipements de diagnostic et de biosécurité.", responsable: "M. FOUDA Emmanuel", structure: "LANAVET", dateDebut: "2023-01-15", dateFin: "2024-06-30", budget: 780_000_000, statut: "Clôturé" },
  { code: "PRJ-2026-PORC", intitule: "Appui à la filière porcine", description: "Distribution de géniteurs et modernisation des porcheries.", responsable: "Mme ATANGANA Jeanne", structure: "DDPIA Mfoundi", dateDebut: "2026-01-10", dateFin: "2027-12-31", budget: 320_000_000, statut: "Planifié" },
  { code: "PRJ-2024-INF", intitule: "Numérisation des postes vétérinaires", description: "Dotation informatique et connectivité de 40 postes.", responsable: "M. NKOMO André", structure: "MINEPIA/DSI", dateDebut: "2024-09-01", dateFin: "2025-08-31", budget: 210_000_000, statut: "Suspendu" },
];

export const mockProjets: Projet[] = projetsSeed.map((p, i) => ({
  ...p,
  id: `PRJ-${String(i + 1).padStart(4, "0")}`,
}));

export const mockProjetBiens: ProjetBien[] = mockProjets.flatMap((p, pi) => {
  const count = 4 + (pi % 3);
  return Array.from({ length: count }, (_, j) => {
    const bien = mockBiens[(pi * 7 + j * 3) % mockBiens.length];
    return {
      id: `PB-${p.id}-${j + 1}`,
      projetId: p.id,
      bienId: bien.id,
      dateAffectation: p.dateDebut,
      utilisateur: p.responsable,
      commentaire: "Affectation initiale du projet",
      actif: true,
    };
  });
});

const mouvementTypes: MouvementProjetType[] = [
  "Entrée",
  "Entrée",
  "Sortie",
  "Transfert",
  "Retour stock",
  "Réforme",
  "Perte",
];

export const mockMouvementsProjet: MouvementProjet[] = mockProjets.flatMap((p, pi) => {
  const count = 3 + (pi % 3);
  return Array.from({ length: count }, (_, j) => {
    const bien = mockBiens[(pi * 5 + j * 4) % mockBiens.length];
    const type = mouvementTypes[(pi + j) % mouvementTypes.length];
    return {
      id: `MP-${p.id}-${j + 1}`,
      projetId: p.id,
      bienId: bien.id,
      type,
      date: `${p.dateDebut.slice(0, 4)}-${String(((pi + j) % 12) + 1).padStart(2, "0")}-${String(((j * 5) % 27) + 1).padStart(2, "0")}`,
      utilisateur: p.responsable,
      observation: `Mouvement ${type.toLowerCase()} enregistré pour ${bien.designation}`,
    };
  });
});

export const mockHistoriqueProjet: HistoriqueProjet[] = mockProjets.flatMap((p) => [
  { id: `HP-${p.id}-1`, projetId: p.id, date: `${p.dateDebut}T09:00:00`, utilisateur: p.responsable, action: "Création du projet", ancienneValeur: "—", nouvelleValeur: p.intitule },
  { id: `HP-${p.id}-2`, projetId: p.id, date: `${p.dateDebut}T10:15:00`, utilisateur: p.responsable, action: "Changement de statut", ancienneValeur: "Planifié", nouvelleValeur: p.statut },
]);