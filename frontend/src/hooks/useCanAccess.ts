/**
 * Vérification RBAC centralisée, avec le même filet de sécurité partout
 * (AppShell, CanAccess, gardes de page) : un compte admin (nom de rôle
 * contenant "admin", voir useIsAdmin) passe toujours ; tant que les
 * permissions n'ont pas fini de charger, on n'applique aucune restriction
 * (évite un clignotement du menu au premier rendu). Une fois chargées, un
 * rôle avec 0 permission (ou aucun rôle) est bien restreint — demande
 * explicite 2026-08-31 : une permission comme synchronisation_offline ne
 * doit donner accès qu'à ce qu'elle décrit, jamais au menu Administration.
 */
import { usePermission } from "@/hooks/usePermission";
import { useIsAdmin } from "@/hooks/useIsAdmin";

export function useCanAccess() {
  const isAdminUser = useIsAdmin();
  const { permissions, isLoading } = usePermission();

  const isUnfiltered = isAdminUser || isLoading;

  return {
    isUnfiltered,
    canAny: (keys: string[]): boolean =>
      isUnfiltered || keys.some((k) => permissions.includes(k)),
    canAll: (keys: string[]): boolean =>
      isUnfiltered || keys.every((k) => permissions.includes(k)),
    can: (key: string): boolean => isUnfiltered || permissions.includes(key),
  };
}
