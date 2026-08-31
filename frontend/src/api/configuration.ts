import { unites } from "./common";
import { cats } from "./biens";

export type User = {
  id: string;
  nom: string;
  email: string;
  role: "Administrateur" | "Comptable-matières" | "Chef de département" | "Agent";
  unite: string;
  actif: boolean;
};

export const mockUsers: User[] = [
  { id: "U-001", nom: "M. NDONGO Paul", email: "p.ndongo@minepia.cm", role: "Administrateur", unite: "MINEPIA/Cabinet", actif: true },
  { id: "U-002", nom: "Mme MBALLA Rose", email: "r.mballa@minepia.cm", role: "Comptable-matières", unite: "DRPIA Centre", actif: true },
  { id: "U-003", nom: "M. FOUDA Emmanuel", email: "e.fouda@minepia.cm", role: "Chef de département", unite: "DDPIA Mfoundi", actif: true },
  { id: "U-004", nom: "Mme ATANGANA Jeanne", email: "j.atangana@minepia.cm", role: "Agent", unite: "Poste vétérinaire Yaoundé", actif: false },
];

export const mockUnites = unites.map((u, i) => ({
  id: `UN-${String(i + 1).padStart(3, "0")}`,
  code: u.split(/[\s/]+/)[0] + "-" + String(i + 1),
  nom: u,
  region: i % 2 === 0 ? "Centre" : "Littoral",
}));

export const mockCategories = cats.map((c, i) => ({
  id: `C-${String(i + 1).padStart(3, "0")}`,
  code: c.slice(0, 3).toUpperCase(),
  libelle: c,
  duree: [30, 10, 5, 7, 5][i],
}));

export const mockExercices = [2023, 2024, 2025, 2026].map((y) => ({
  id: `E-${y}`,
  annee: `${y}`,
  ouvert: y >= 2025,
}));