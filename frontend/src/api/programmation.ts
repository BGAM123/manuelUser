import { unites } from "./common";
import { cats, designationsBy } from "./biens";

export type Programmation = {
  id: string;
  designation: string;
  categorie: (typeof cats)[number];
  exercice: string;
  maturation: "Idée" | "Étude" | "Prêt à engager" | "Engagé";
  datePrevue: string;
  montantEnvisage: number;
  unite: string;
  statut: "Planifié" | "Réalisé" | "Reporté";
};

export const mockProgrammations: Programmation[] = Array.from({ length: 22 }, (_, i) => ({
  id: `P-${String(i + 1).padStart(3, "0")}`,
  designation: `Acquisition ${designationsBy[cats[i % cats.length]][i % 4]}`,
  categorie: cats[i % cats.length],
  exercice: `${2025 + (i % 3)}`,
  maturation: (["Idée", "Étude", "Prêt à engager", "Engagé"] as const)[i % 4],
  datePrevue: `${2025 + (i % 3)}-${String((i % 12) + 1).padStart(2, "0")}-15`,
  montantEnvisage: (i + 1) * 1_250_000,
  unite: unites[i % unites.length],
  statut: (["Planifié", "Réalisé", "Reporté"] as const)[i % 3],
}));
