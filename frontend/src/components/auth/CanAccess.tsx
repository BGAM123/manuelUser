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
import { useCanAccess } from "@/hooks/useCanAccess";

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
  const { can, canAny, canAll } = useCanAccess();

  // Un tableau explicitement vide (anyOf={[]}/allOf={[]}) doit refuser
  // l'accès, pas retomber sur le "true" par défaut — sinon une section sans
  // permission mappée (ou un bug de mapping) resterait accessible à tous.
  let allowed = true;
  if (permission !== undefined) allowed = can(permission);
  else if (anyOf !== undefined) allowed = canAny(anyOf);
  else if (allOf !== undefined) allowed = canAll(allOf);

  return allowed ? <>{children}</> : <>{fallback}</>;
}
