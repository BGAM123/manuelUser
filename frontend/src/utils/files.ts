/**
 * Résolution des URLs de fichiers uploadés (photos, pièces jointes).
 * Le backend renvoie un chemin relatif ("chemin", ex: "/uploads/assets/photos/x.jpg")
 * qu'il faut préfixer avec l'origine de l'API (pas celle du front).
 */
export function resolveFileUrl(chemin: string | undefined | null): string {
  if (!chemin) return "";
  if (chemin.startsWith("http://") || chemin.startsWith("https://")) return chemin;

  const apiUrl = (import.meta.env.VITE_API_URL as string) ?? "";
  const origin = apiUrl.replace(/\/api\/?$/, "");
  return `${origin}/${chemin.replace(/^\//, "")}`;
}
