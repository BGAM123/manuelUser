/**
 * Composant d'état vide pour les widgets statistiques.
 * Remplace les données mock quand le backend retourne 0 résultats.
 */
import { BarChart3 } from "lucide-react";

interface EmptyStateProps {
  message?: string;
  height?: string;
}

export function EmptyState({
  message = "Aucune donnée disponible",
  height = "h-32",
}: EmptyStateProps) {
  return (
    <div
      className={`${height} flex flex-col items-center justify-center gap-2 rounded-lg bg-muted/20 border border-dashed border-border`}
    >
      <BarChart3 className="h-6 w-6 text-muted-foreground/40" />
      <p className="text-xs text-muted-foreground text-center px-4">{message}</p>
    </div>
  );
}
