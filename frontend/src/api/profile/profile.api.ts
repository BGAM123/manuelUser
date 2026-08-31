/**
 * Appels API — Profil de l'utilisateur connecté.
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiProfileService {
  id: number;
  nom: string;
  sigle: string;
  type_service: string;
  ordre: number;
  is_active: boolean;
}

export interface ApiProfile {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  is_active: boolean;
  twoFactorEnabled: boolean;
  createdAt: string;
  service: ApiProfileService | null;
  assignedRoles: { id: number; nom: string }[];
}

export interface UpdateProfilePasswordPayload {
  password: string;
  passwordConfirm: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function getProfile(): Promise<ApiResponse<ApiProfile>> {
  const response = await api.get<ApiResponse<ApiProfile>>("/profile");
  return response.data;
}

export async function updateProfilePassword(
  payload: UpdateProfilePasswordPayload,
): Promise<ApiResponse<null>> {
  const response = await api.patch<ApiResponse<null>>("/profile/password", payload);
  return response.data;
}
