/**
 * API — Inventaire du patrimoine
 * GET /inventaire?categories=<ids|all>
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ──────────────────────────────────────────────────────────────────

export interface InventaireBien {
  id: number;
  nom: string;
  type: { id: number; nom: string } | null;
  valeur: number | null;
  annee_acquisition: number | null;
  etat: string | null;
  description: string | null;
  imputation_budgetaire: number | null;
  projet: {
    id: number;
    nom: string;
    date_debut: string;
    date_fin: string;
    duree: string;
  } | null;
  detenteur: {
    nom_prenom: string;
    matricule?: string;
    numero_cni?: string;
    fonction?: string;
  } | null;
}

export interface InventaireCategorie {
  categorie: { id: number; nom: string };
  biens: InventaireBien[];
}

export interface InventaireData {
  date: string;
  service: { id: number; nom: string } | null;
  region: { id: number; nom: string } | null;
  departement: { id: number; nom: string } | null;
  arrondissement: { id: number; nom: string } | null;
  categories: InventaireCategorie[];
}

// ─── Fonction ────────────────────────────────────────────────────────────────

export async function getInventaire(
  categories: string = "all",
): Promise<ApiResponse<InventaireData>> {
  const response = await api.get<ApiResponse<InventaireData>>("/inventaire", {
    params: { categories },
  });
  return response.data;
}
