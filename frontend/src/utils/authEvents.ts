/**
 * Événement personnalisé émis à chaque changement d'état de connexion
 * (login réussi ou logout). `PermissionContext` l'écoute pour recharger
 * immédiatement les permissions de l'utilisateur courant — sans ça, après
 * une connexion avec un utilisateur différent (même onglet), les permissions
 * de l'ancien utilisateur restent affichées tant que la page n'est pas
 * rechargée manuellement (l'événement natif "storage" ne se déclenche que
 * pour les AUTRES onglets, jamais pour celui qui a écrit la valeur).
 */
export const AUTH_CHANGED_EVENT = "auth:changed";

export function emitAuthChanged(): void {
  window.dispatchEvent(new Event(AUTH_CHANGED_EVENT));
}
