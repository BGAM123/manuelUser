/**
 * Appels API — Logs d'actions (Administration > Utilisateurs > Logs).
 *
 * GET /logs — lit les événements API_ACTION du fichier de log Symfony de
 * l'environnement courant.
 *
 * ⚠️ Confirmé en direct (2026-08-27) :
 *   - Réponse réelle : { success, status, message, data: { items: [...] } }
 *     — AUCUN champ `meta`/pagination, contrairement à tous les autres
 *     endpoints paginés de cette API. `page`/`limit` fonctionnent bien
 *     (offset + nombre exact retourné), mais sans total connu — voir
 *     LogsSection.tsx pour la navigation Précédent/Suivant sans compteur
 *     de pages fiable.
 *   - `search` n'a AUCUN effet côté serveur (résultats identiques avec ou
 *     sans, testé en direct) — retiré des paramètres, la recherche est
 *     faite côté client sur le lot chargé (voir LogsSection.tsx).
 *   - `resource_id` retiré du contrat frontend à la demande explicite du
 *     2026-08-27 (le filtre correspondant a été supprimé de l'UI).
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

export interface ApiLogEntry {
  timestamp: string;
  user_id: number | null;
  username: string | null;
  method: string;
  route: string | null;
  path: string;
  action: string;
  module: string;
  resource_id: number | null;
  ip: string;
  status_code: number;
  duration_ms: number;
  user_agent: string;
  environment: string;
  request_id: string;
}

export interface ListLogsParams {
  page?: number;
  limit?: number;
  user_id?: number;
  /** Format YYYY-MM-DD */
  date_start?: string;
  /** Format YYYY-MM-DD */
  date_end?: string;
}

export interface ApiLogsData {
  items: ApiLogEntry[];
}

export async function listLogs(params: ListLogsParams = {}): Promise<ApiResponse<ApiLogsData>> {
  const response = await api.get<ApiResponse<ApiLogsData>>("/logs", { params });
  return response.data;
}
