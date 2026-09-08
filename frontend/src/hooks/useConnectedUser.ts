/**
 * Hook — informations de l'utilisateur connecté.
 *
 * Lit l'objet `user` depuis le localStorage (mis à jour lors du login)
 * et retourne un objet prêt à l'emploi avec toutes les dérivées utiles.
 */

import { useCallback, useSyncExternalStore } from "react";
import { USER_KEY } from "@/api/axios";
import type { AuthUser } from "@/api/authentication/auth.api";
import { AUTH_CHANGED_EVENT } from "@/utils/authEvents";

function getSnapshot(): string | null {
  return localStorage.getItem(USER_KEY);
}

function subscribe(cb: () => void) {
  window.addEventListener("storage", cb);
  window.addEventListener(AUTH_CHANGED_EVENT, cb);
  return () => {
    window.removeEventListener("storage", cb);
    window.removeEventListener(AUTH_CHANGED_EVENT, cb);
  };
}

export function useConnectedUser() {
  const raw = useSyncExternalStore(subscribe, getSnapshot, () => null);

  // Une valeur corrompue (ex. écriture ratée après un déploiement — voir
  // Authentification.tsx) ne doit jamais faire planter tout le rendu :
  // on la traite comme "non connecté" plutôt que de laisser JSON.parse lever.
  let user: AuthUser | null = null;
  if (raw) {
    try {
      user = JSON.parse(raw) as AuthUser;
    } catch {
      localStorage.removeItem(USER_KEY);
    }
  }

  const fullName = user ? `${user.firstName} ${user.lastName}` : "";

  const avatarInitials = user
    ? `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase()
    : "?";

  const serviceName = user?.service?.nom ?? "";
  const roleName = user?.assignedRoles?.[0]?.nom ?? "";

  /** Permet de forcer une mise à jour après avoir modifié le localStorage manuellement */
  const refresh = useCallback(() => {
    window.dispatchEvent(new Event("storage"));
  }, []);

  return { user, fullName, avatarInitials, serviceName, roleName, refresh };
}
