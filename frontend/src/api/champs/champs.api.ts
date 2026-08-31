/**
 * Appels API — Gestion des champs personnalisés.
 * Endpoints: GET/POST /champs, PATCH /champs/{id},
 *            POST /champs/{id}/restore, DELETE /champs/{id}/soft-delete
 *
 * ⚠️ Breaking change (spec v2) :
 *   - typeChamp et valeur supprimés du modèle
 *   - categories embarquées dans la réponse GET /champs/{id}
 *   - category_ids envoyé en écriture (remplacement total de l'association)
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiChampCategory {
  id: number;
  nom: string;
}

/** Grands types de champs prédéfinis — définis côté frontend (pas de liste fermée documentée côté API). */
export type ChampType = "text" | "textarea" | "number" | "date" | "select" | "file";

/**
 * Sous-types — significatifs uniquement pour type="select" :
 *   - "single"        : liste d'options personnalisée (voir champ `option`,
 *                        chaîne unique séparée par des virgules — confirmé
 *                        côté backend, ex: "Rouge,Vert,Bleu")
 *   - "boolean"       : Oui / Non
 *   - "region" / "departement" / "arrondissement" : options issues de la Cartographie
 *     (liées entre elles — sélectionner une région filtre les départements
 *     proposés à ceux de cette région, et de même pour arrondissement)
 *   - "structure"     : options issues de l'organigramme (services/postes)
 *   - "cartographie"  : un seul champ, sélection en cascade Région >
 *     Département > Arrondissement (mêmes données que la page Cartographie),
 *     mais SEULE la valeur de l'arrondissement (dernier niveau) est
 *     enregistrée comme valeur du champ — région/département ne servent
 *     qu'à filtrer visuellement les choix, contrairement à "region"/
 *     "departement"/"arrondissement" qui sont TROIS champs distincts.
 * Pour les autres types (text/textarea/number/date/file), "single" est envoyé
 * par défaut (sans effet côté affichage — `option` n'est utilisé pour
 * peupler des choix que si type="select" && subtype="single").
 *
 * ⚠️ Confirmé en direct (2026-08-27) : POST/PATCH /champs exige `option`
 * non vide dans TOUS les cas (400 "L'option du champ est obligatoire."
 * sinon), même pour des subtypes où le frontend ne l'utilise jamais
 * (region/departement/arrondissement/structure/boolean, ou type != select).
 * Toujours envoyer une valeur non vide, y compris un simple placeholder
 * quand elle n'a pas de sens fonctionnel (voir ChampsSection.tsx).
 */
export type ChampSubtype = "single" | "boolean" | "region" | "departement" | "arrondissement" | "structure" | "cartographie";

export interface ApiChamp {
  id: number;
  nom: string;
  is_delete: boolean;
  categories: ApiChampCategory[];
  type?: ChampType;
  subtype?: ChampSubtype;
  /** Options pour type="select" + subtype="single" — chaîne unique séparée
   * par des virgules (ex: "Rouge,Vert,Bleu"), confirmée côté backend. */
  option?: string;
}

export interface ListChampsParams extends PaginationParams {
  search?: string;
  is_delete?: boolean;
  /** IDs de catégories séparés par virgules (ex: "5,6") */
  category_ids?: string;
}

export interface ChampPayload {
  nom: string;
  type: ChampType;
  subtype: ChampSubtype;
  /** Remplace entièrement l'association champ ↔ catégories */
  category_ids: number[];
  /**
   * Voir ApiChamp.option — chaîne unique séparée par des virgules pour
   * type="select"+subtype="single". OBLIGATOIRE ET NON VIDE dans tous les
   * cas côté backend (voir avertissement ci-dessus) — ne jamais omettre ni
   * envoyer une chaîne vide, même quand la valeur n'a pas de sens pour le
   * subtype choisi.
   */
  option: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listChamps(
  params: ListChampsParams = {},
): Promise<ApiResponse<PaginatedData<ApiChamp>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiChamp>>>("/champs", { params });
  return response.data;
}

export async function getChampById(id: number): Promise<ApiResponse<ApiChamp>> {
  const response = await api.get<ApiResponse<ApiChamp>>(`/champs/${id}`);
  return response.data;
}

export async function createChamp(
  payload: ChampPayload,
): Promise<ApiResponse<ApiChamp>> {
  const response = await api.post<ApiResponse<ApiChamp>>("/champs", payload);
  return response.data;
}

export async function updateChamp(
  id: number,
  payload: ChampPayload,
): Promise<ApiResponse<ApiChamp>> {
  const response = await api.patch<ApiResponse<ApiChamp>>(`/champs/${id}`, payload);
  return response.data;
}

export async function restoreChamp(id: number): Promise<ApiResponse<ApiChamp>> {
  const response = await api.post<ApiResponse<ApiChamp>>(`/champs/${id}/restore`, {});
  return response.data;
}

export async function softDeleteChamp(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/champs/${id}/soft-delete`);
  return response.data;
}
