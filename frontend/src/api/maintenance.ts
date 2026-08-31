import { unites } from "./common";
import { mockBiens } from "./biens";

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
  bien: mockBiens[i * 3]?.designation ?? "Bien",
  poste: `Poste ${((i % 6) + 1)}`,
  dateBesoin: `2026-${String((i % 12) + 1).padStart(2, "0")}-${String((i % 27) + 1).padStart(2, "0")}`,
  evaluation: (i + 1) * 85_000,
  priorite: (["Haute", "Normale", "Basse"] as const)[i % 3],
  statut: (["En attente", "Approuvé", "Réalisé", "Rejeté"] as const)[i % 4],
  unite: unites[i % unites.length],
}));