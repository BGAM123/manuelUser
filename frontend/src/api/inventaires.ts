export type Inventaire = {
  id: string;
  code: string;
  type: "Général de base" | "Fin d'exercice" | "Mutation";
  exercice: string;
  dateDebut: string;
  dateFin: string;
  statut: "Planifié" | "En cours" | "Clôturé";
  responsable: string;
  progression: number;
};

export const mockInventaires: Inventaire[] = [
  { id: "I-001", code: "INV-2025-GB", type: "Général de base", exercice: "2025", dateDebut: "2025-01-15", dateFin: "2025-03-30", statut: "Clôturé", responsable: "M. NDONGO Paul", progression: 100 },
  { id: "I-002", code: "INV-2025-FE", type: "Fin d'exercice", exercice: "2025", dateDebut: "2025-11-01", dateFin: "2025-12-31", statut: "En cours", responsable: "Mme MBALLA Rose", progression: 62 },
  { id: "I-003", code: "INV-2026-MUT-01", type: "Mutation", exercice: "2026", dateDebut: "2026-06-10", dateFin: "2026-07-20", statut: "Planifié", responsable: "M. FOUDA Emmanuel", progression: 0 },
  { id: "I-004", code: "INV-2026-GB", type: "Général de base", exercice: "2026", dateDebut: "2026-02-01", dateFin: "2026-04-15", statut: "En cours", responsable: "Mme ATANGANA Jeanne", progression: 34 },
];