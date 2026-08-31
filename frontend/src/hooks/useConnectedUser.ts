/**
 * Hook — informations de l'utilisateur connecté.
 *
 * Lit l'objet `user` depuis le localStorage (mis à jour lors du login)
 * et retourne un objet prêt à l'emploi avec toutes les dérivées utiles.
 */

import { useCallback, useSyncExternalStore } from "react";
import { USER_KEY } from "@/api/axios";
import type { AuthUser } from "@/api/authentication/auth.api";

function getSnapshot(): string | null {
  return localStorage.getItem(USER_KEY);
}

function subscribe(cb: () => void) {
  window.addEventListener("storage", cb);
  return () => window.removeEventListener("storage", cb);
}

export function useConnectedUser() {
  const raw = useSyncExternalStore(subscribe, getSnapshot, () => null);

  const user: AuthUser | null = raw ? (JSON.parse(raw) as AuthUser) : null;

  const fullName = user ? `${user.firstName} ${user.lastName}` : "";

  const avatarInitials = user
    ? `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase()
    : "?";

  const serviceName = user?.service?.nom ?? "";

  /** Permet de forcer une mise à jour après avoir modifié le localStorage manuellement */
  const refresh = useCallback(() => {
    window.dispatchEvent(new Event("storage"));
  }, []);

  return { user, fullName, avatarInitials, serviceName, refresh };
}
