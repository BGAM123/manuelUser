/**
 * Composant de garde RBAC — affiche ses enfants uniquement si la permission est accordée.
 *
 * @example
 * <CanAccess permission="USER_CREATE">
 *   <Button>Ajouter</Button>
 * </CanAccess>
 *
 * <CanAccess anyOf={["ROLE_ADMIN", "ROLE_MANAGER"]} fallback={<p>Accès refusé</p>}>
 *   <SensitiveContent />
 * </CanAccess>
 */

import type { ReactNode } from "react";
import { usePermission } from "@/hooks/usePermission";

interface CanAccessProps {
  /** Permission unique requise. */
  permission?: string;
  /** Au moins une de ces permissions est requise. */
  anyOf?: string[];
  /** Toutes ces permissions sont requises. */
  allOf?: string[];
  /** Contenu affiché si l'accès est refusé (défaut : rien). */
  fallback?: ReactNode;
  children: ReactNode;
}

export function CanAccess({
  permission,
  anyOf,
  allOf,
  fallback = null,
  children,
}: CanAccessProps) {
  const { hasPermission, hasAnyPermission, hasAllPermissions } = usePermission();

  let allowed = true;
  if (permission) allowed = hasPermission(permission);
  else if (anyOf?.length) allowed = hasAnyPermission(anyOf);
  else if (allOf?.length) allowed = hasAllPermissions(allOf);

  return allowed ? <>{children}</> : <>{fallback}</>;
}
