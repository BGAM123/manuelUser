/**
 * Contexte RBAC — Charge et expose les permissions effectives de l'utilisateur connecté.
 *
 * Lit l'objet `user` depuis le localStorage, récupère les permissions de chaque rôle
 * (si elles ne sont pas déjà embarquées), puis les met à disposition via Context.
 */

import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { USER_KEY } from "@/api/axios";
import type { AuthUser } from "@/api/authentication/auth.api";
import { getRoleById } from "@/api/roles/roles.api";

interface PermissionContextValue {
  /** Noms techniques des permissions effectives (dédupliqués). */
  permissions: string[];
  isLoading: boolean;
  /** Déclenche un rechargement (ex : après connexion / mise à jour du profil). */
  refreshPermissions: () => void;
}

const PermissionContext = createContext<PermissionContextValue>({
  permissions: [],
  isLoading: false,
  refreshPermissions: () => {},
});

export function PermissionProvider({ children }: { children: ReactNode }) {
  const [permissions, setPermissions] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [trigger, setTrigger] = useState(0);

  const refreshPermissions = () => setTrigger((n) => n + 1);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const raw = localStorage.getItem(USER_KEY);
      if (!raw) {
        if (!cancelled) setPermissions([]);
        return;
      }

      // Une valeur corrompue (ex. écriture ratée après un déploiement — voir
      // Authentification.tsx) ne doit jamais faire planter le rendu de tout
      // l'arbre applicatif : on la nettoie et on retombe sur "pas de permissions"
      // plutôt que de laisser JSON.parse lever une exception non rattrapée.
      let user: AuthUser;
      try {
        user = JSON.parse(raw) as AuthUser;
      } catch {
        localStorage.removeItem(USER_KEY);
        if (!cancelled) setPermissions([]);
        return;
      }
      if (!user.assignedRoles?.length) {
        if (!cancelled) setPermissions([]);
        return;
      }

      if (!cancelled) setIsLoading(true);
      try {
        // Si les rôles embarquent déjà leurs permissions, on les utilise directement
        const hasEmbedded = user.assignedRoles.some(
          (r) => r.permissions && r.permissions.length > 0,
        );

        let allPerms: string[];

        if (hasEmbedded) {
          allPerms = user.assignedRoles.flatMap(
            (r) => r.permissions?.map((p) => p.nom) ?? [],
          );
        } else {
          // Récupère les détails de chaque rôle pour obtenir leurs permissions
          const details = await Promise.all(
            user.assignedRoles.map((r) => getRoleById(r.id).catch(() => null)),
          );
          allPerms = details
            .filter(Boolean)
            .flatMap((r) => r!.data.permissions?.map((p) => p.nom) ?? []);
        }

        if (!cancelled) setPermissions([...new Set(allPerms)]);
      } catch {
        if (!cancelled) setPermissions([]);
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    }

    load();

    const handler = () => load();
    window.addEventListener("storage", handler);
    return () => {
      cancelled = true;
      window.removeEventListener("storage", handler);
    };
  }, [trigger]);

  return (
    <PermissionContext.Provider value={{ permissions, isLoading, refreshPermissions }}>
      {children}
    </PermissionContext.Provider>
  );
}

export function usePermissionContext() {
  return useContext(PermissionContext);
}
