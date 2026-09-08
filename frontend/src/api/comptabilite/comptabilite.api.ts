/**
 * Appels API — Module Comptabilité (Comptables) :
 *  - Fiche de détenteur : GET /fiche-detenteur/{userId}
 *  - Livre Journal : GET /comptables/livre-journal
 *  - Fiches de stock : GET /comptables/fiches-stock
 *
 * Endpoints documentés sur le Swagger, tag "Comptables".
 */
import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Fiche de détenteur ─────────────────────────────────────────────────────

export type FicheDetenteurType = "biens" | "consommables" | "tous";

export interface FicheDetenteurLigne {
  numero: number;
  designation: string;
  description: string;
  dateAcquisition: string;
  quantite: number;
  prixUnitaire: number;
  valeur: number;
  dateAffectation: string;
  lieuAffectation: string;
  observation: string;
}

export interface FicheDetenteurService {
  id: number;
  nom: string;
}

export interface FicheDetenteurInfo {
  id: number;
  nom: string;
  prenom: string;
  matricule: string | null;
  service?: FicheDetenteurService | null;
}

export interface FicheDetenteurResponse {
  detenteur: FicheDetenteurInfo;
  BIENS: FicheDetenteurLigne[];
  CONSOMMABLES: FicheDetenteurLigne[];
}

/**
 * GET /fiche-detenteur/{userId} — Fiche de détenteur d'un utilisateur.
 */
export async function getFicheDetenteur(
  userId: number,
  type: FicheDetenteurType = "tous"
): Promise<FicheDetenteurResponse> {
  const response = await api.get<ApiResponse<FicheDetenteurResponse>>(`/fiche-detenteur/${userId}`, {
    params: { type },
  });
  return response.data.data;
}

// ─── Livre Journal ──────────────────────────────────────────────────────────

export interface LivreJournalFilters {
  type?: "BIEN" | "CONSOMMABLE";
  service?: string;
  categorie?: string;
  dateDebut?: string;
  dateFin?: string;
  exercice?: number;
}

export interface LivreJournalMontant {
  quantite: number;
  valeur: number;
}

export interface LivreJournalLigne {
  id: number;
  numeroOrdre: number;
  numeroOrdreClasse: number;
  type: "BIEN" | "CONSOMMABLE";
  date: string;
  origineDestination: string;
  imputationBudgetaire: number;
  designation: string;
  uniteMesure: string;
  prixUnitaire: number;
  entree: LivreJournalMontant;
  sortie: LivreJournalMontant;
  observations: string;
}

interface LivreJournalPagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

interface LivreJournalPage {
  data: LivreJournalLigne[];
  pagination: LivreJournalPagination;
}

// Limite maximale acceptée par le back-end (voir Swagger : max 1000).
const LIVRE_JOURNAL_MAX_LIMIT = 1000;

/**
 * GET /comptables/livre-journal — récupère TOUTES les lignes correspondant
 * aux filtres, en paginant automatiquement au besoin (limite back-end : 1000/page).
 */
export async function getLivreJournal(filters: LivreJournalFilters = {}): Promise<LivreJournalLigne[]> {
  const baseParams: Record<string, string | number> = { page: 1, limit: LIVRE_JOURNAL_MAX_LIMIT };
  if (filters.type) baseParams.type = filters.type;
  if (filters.service) baseParams.service = filters.service;
  if (filters.categorie) baseParams.categorie = filters.categorie;
  if (filters.dateDebut) baseParams.dateDebut = filters.dateDebut;
  if (filters.dateFin) baseParams.dateFin = filters.dateFin;
  if (filters.exercice) baseParams.exercice = filters.exercice;

  const first = await api.get<ApiResponse<LivreJournalPage>>("/comptables/livre-journal", { params: baseParams });
  const payload = first.data.data;
  const items: LivreJournalLigne[] = [...(payload.data ?? [])];
  const totalPages = payload.pagination?.totalPages ?? 1;

  if (totalPages > 1) {
    const rest = await Promise.all(
      Array.from({ length: totalPages - 1 }, (_, i) =>
        api.get<ApiResponse<LivreJournalPage>>("/comptables/livre-journal", {
          params: { ...baseParams, page: i + 2 },
        })
      )
    );
    for (const r of rest) items.push(...(r.data.data?.data ?? []));
  }

  return items;
}

// ─── Fiches de stock ────────────────────────────────────────────────────────

export interface FicheStockFilters {
  service_id?: number;
  consumable_id?: number;
  dateDebut?: string;
  dateFin?: string;
  search?: string;
}

export interface FicheStockQuantites {
  entrees: number;
  sorties: number;
  enStock: number;
}

export interface FicheStockMovement {
  date: string;
  origineDestination: string;
  stockInitial: number | null;
  quantites: FicheStockQuantites;
  numeroBlBsp: string | null;
  observations: string;
}

export interface FicheStockConsumable {
  id: number;
  nom: string;
  unite_mesure: string;
}

export interface FicheStockServiceInfo {
  id: number;
  nom: string;
}

export interface FicheStockFiche {
  consumable: FicheStockConsumable;
  service: FicheStockServiceInfo;
  movements: FicheStockMovement[];
}

interface FicheStockPagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

interface FicheStockPage {
  header: Record<string, unknown>;
  fiches: FicheStockFiche[];
  pagination: FicheStockPagination;
}

// Limite raisonnable par page (le Swagger ne documente pas de maximum explicite).
const FICHE_STOCK_PAGE_LIMIT = 100;

/**
 * GET /comptables/fiches-stock — récupère TOUTES les fiches correspondant aux
 * filtres, en paginant automatiquement au besoin.
 */
export async function getFichesStock(filters: FicheStockFilters = {}): Promise<FicheStockFiche[]> {
  const baseParams: Record<string, string | number> = { page: 1, limit: FICHE_STOCK_PAGE_LIMIT };
  if (filters.service_id) baseParams.service_id = filters.service_id;
  if (filters.consumable_id) baseParams.consumable_id = filters.consumable_id;
  if (filters.dateDebut) baseParams.date_debut = filters.dateDebut;
  if (filters.dateFin) baseParams.date_fin = filters.dateFin;
  if (filters.search) baseParams.search = filters.search;

  const first = await api.get<ApiResponse<FicheStockPage>>("/comptables/fiches-stock", { params: baseParams });
  const payload = first.data.data;
  const items: FicheStockFiche[] = [...(payload.fiches ?? [])];
  const totalPages = payload.pagination?.totalPages ?? 1;

  if (totalPages > 1) {
    const rest = await Promise.all(
      Array.from({ length: totalPages - 1 }, (_, i) =>
        api.get<ApiResponse<FicheStockPage>>("/comptables/fiches-stock", {
          params: { ...baseParams, page: i + 2 },
        })
      )
    );
    for (const r of rest) items.push(...(r.data.data?.fiches ?? []));
  }

  return items;
}
