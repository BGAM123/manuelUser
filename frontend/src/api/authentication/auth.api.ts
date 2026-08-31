/**
 * Appels API liés à l'authentification.
 * Ces fonctions ne gèrent ni le stockage du token ni les erreurs globales.
 * C'est le rôle de axios.ts pour les tokens et des composants pour les erreurs.
 */

import axios from "axios";
import type { ApiResponse } from "@/api/types";
import { TOKEN_KEY, REFRESH_TOKEN_KEY, USER_KEY } from "@/api/axios";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface LoginPayload {
  email: string;
  password: string;
}

export interface AuthTokenData {
  token: string;
  refresh_token: string;
}

export interface AuthUser {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  is_active: boolean;
  createdAt: string;
  service?: {
    id: number;
    nom: string;
    sigle?: string;
    type_service?: string;
    ordre: number;
    is_active: boolean;
  };
}

// ─── Fonctions ─────────────────────────────────────────────────────────────

/**
 * Connexion — POST /login_check
 * Stocke le token, le refresh token et le profil dans le localStorage.
 */
export async function loginApi(payload: LoginPayload): Promise<AuthTokenData> {
  const baseURL = import.meta.env.VITE_API_URL as string;

  const response = await axios.post<ApiResponse<AuthTokenData>>(
    `${baseURL}/login_check`,
    payload,
    { headers: { "Content-Type": "application/json" } },
  );

  const { token, refresh_token } = response.data.data;
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(REFRESH_TOKEN_KEY, refresh_token);

  return { token, refresh_token };
}

/**
 * Déconnexion locale — supprime les données de session du localStorage.
 */
export function logoutApi(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}
