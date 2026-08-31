/**
 * Détermine si l'utilisateur connecté est administrateur — utilisé pour
 * n'exposer certaines actions (export des tableaux, filtre inter-services...)
 * qu'aux comptes portant un rôle "Administrateur" (ex : "Administrateur",
 * "Super Administrateur").
 */
import { useConnectedUser } from "./useConnectedUser";

export function useIsAdmin(): boolean {
  const { user } = useConnectedUser();
  return (user?.assignedRoles ?? []).some((r) => /admin/i.test(r.nom));
}
