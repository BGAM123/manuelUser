import { unites } from "./common";

const biensNoms = [
  "Ordinateur portable Dell", "Toyota Hilux", "Imprimante HP LaserJet",
  "Serveur Rack", "Bureau ministre", "Armoire métallique", "Onduleur 3kVA",
  "Peugeot 508", "Table de réunion", "Moto Yamaha DT",
];

export type Maintenance = {
  id: string;
  bien: string;
  poste: string;
  dateBesoin: string;
  evaluation: number;
  priorite: "Haute" | "Normale" | "Basse";
  statut: "En attente" | "Approuvé" | "Réalisé" | "Rejeté";
  unite: string;
};

export const mockMaintenances: Maintenance[] = Array.from({ length: 28 }, (_, i) => ({
  id: `M-${String(i + 1).padStart(3, "0")}`,
  bien: biensNoms[i % biensNoms.length],
  poste: `Poste ${((i % 6) + 1)}`,
  dateBesoin: `2026-${String((i % 12) + 1).padStart(2, "0")}-${String((i % 27) + 1).padStart(2, "0")}`,
  evaluation: (i + 1) * 85_000,
  priorite: (["Haute", "Normale", "Basse"] as const)[i % 3],
  statut: (["En attente", "Approuvé", "Réalisé", "Rejeté"] as const)[i % 4],
  unite: unites[i % unites.length],
}));