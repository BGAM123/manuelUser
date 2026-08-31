/**
 * Hook RBAC — vérification des permissions de l'utilisateur connecté.
 *
 * @example
 * const { hasPermission } = usePermission();
 * if (hasPermission("USER_CREATE")) { ... }
 */

import { usePermissionContext } from "@/contexts/PermissionContext";

export function usePermission() {
  const { permissions, isLoading } = usePermissionContext();

  return {
    permissions,
    isLoading,
    hasPermission: (p: string): boolean => permissions.includes(p),
    hasAnyPermission: (ps: string[]): boolean =>
      ps.some((p) => permissions.includes(p)),
    hasAllPermissions: (ps: string[]): boolean =>
      ps.every((p) => permissions.includes(p)),
  };
}
