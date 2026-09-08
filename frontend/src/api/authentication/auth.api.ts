/**
 * Appels API liés à l'authentification.
 * Ces fonctions ne gèrent ni le stockage du token ni les erreurs globales.
 * C'est le rôle de axios.ts pour les tokens et des composants pour les erreurs.
 */

import axios from "axios";
import type { ApiResponse } from "@/api/types";
import { TOKEN_KEY, REFRESH_TOKEN_KEY, USER_KEY } from "@/api/axios";
import { emitAuthChanged } from "@/utils/authEvents";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface LoginPayload {
  email: string;
  password: string;
}

export interface AuthTokenData {
  token: string;
  refresh_token: string | null;
}

// Résultat de login_check : soit un token direct, soit une demande OTP
export type LoginResult =
  | { requiresOtp: false; token: string; refresh_token: string | null }
  | { requiresOtp: true; email: string };

export interface AuthUserRole {
  id: number;
  nom: string;
  permissions?: { id: number; nom: string }[];
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
  assignedRoles?: AuthUserRole[];
}

// ─── Fonctions ─────────────────────────────────────────────────────────────

/**
 * Connexion — POST /login_check
 * Retourne soit les tokens (2FA désactivé), soit { requiresOtp: true } (2FA actif).
 */
export async function loginApi(payload: LoginPayload): Promise<LoginResult> {
  const baseURL = import.meta.env.VITE_API_URL as string;

  const response = await axios.post<
    ApiResponse<{ token?: string; refresh_token?: string | null; requires_otp?: boolean }> & {
      token?: string;
      refresh_token?: string | null;
      requires_otp?: boolean;
    }
  >(`${baseURL}/login_check`, payload, {
    headers: { "Content-Type": "application/json" },
  });

  const body = response.data as unknown;

  // L'aperçu Lovable peut renvoyer une page HTML (chaîne) au lieu du JSON de
  // l'API lorsque le relais ne joint pas le serveur : on le détecte pour
  // afficher un message clair au lieu d'un plantage « requires_otp ».
  if (typeof body !== "object" || body === null) {
    throw new Error("INVALID_API_RESPONSE");
  }

  const envelope = body as {
    data?: { token?: string; refresh_token?: string | null; requires_otp?: boolean } | null;
    token?: string;
    refresh_token?: string | null;
    requires_otp?: boolean;
  };
  // Certains déploiements renvoient les champs à la racine, d'autres sous "data".
  const data = envelope.data ?? envelope;

  if (data.requires_otp) {
    return { requiresOtp: true, email: payload.email };
  }

  const token = data.token;
  if (!token) throw new Error("INVALID_API_RESPONSE");
  const refresh_token = data.refresh_token ?? null;
  localStorage.setItem(TOKEN_KEY, token);
  if (refresh_token) localStorage.setItem(REFRESH_TOKEN_KEY, refresh_token);

  return { requiresOtp: false, token, refresh_token };
}

/**
 * Vérification OTP — POST /auth/verify-otp
 * Valide le code reçu par mail et retourne le token JWT.
 */
export async function verifyOtpApi(payload: { email: string; otp: string }): Promise<AuthTokenData> {
  const baseURL = import.meta.env.VITE_API_URL as string;

  const response = await axios.post<ApiResponse<{ token: string; refresh_token: string | null }>>(
    `${baseURL}/auth/verify-otp`,
    payload,
    { headers: { "Content-Type": "application/json" } },
  );

  const { token, refresh_token } = response.data.data;
  localStorage.setItem(TOKEN_KEY, token);
  if (refresh_token) localStorage.setItem(REFRESH_TOKEN_KEY, refresh_token);

  return { token, refresh_token: refresh_token ?? "" };
}

/**
 * Déconnexion locale — supprime les données de session du localStorage.
 */
export function logoutApi(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  emitAuthChanged();
}
