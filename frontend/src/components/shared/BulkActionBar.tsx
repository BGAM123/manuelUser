import type { ReactNode } from "react";
import { X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useT } from "@/utils/i18n";

export type BulkAction = {
  key: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  onClick: () => void;
  tone?: "default" | "danger" | "primary";
};

/**
 * Barre d'actions groupées affichée EN PLACE au-dessus du tableau
 * quand une ou plusieurs lignes sont sélectionnées. Aucune modale.
 */
export function BulkActionBar({
  count,
  actions,
  onClear,
  label,
}: {
  count: number;
  actions: BulkAction[];
  onClear: () => void;
  label?: string;
}) {
  const t = useT();
  if (count === 0) return null;
  return (
    <div className="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-primary/40 bg-primary/5 p-2.5 shadow-sm sm:p-3">
      <div className="flex items-center gap-2 pl-1">
        <span className="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md bg-primary px-1.5 text-xs font-semibold text-primary-foreground">
          {count}
        </span>
        <span className="text-sm font-medium">
          {label ?? t("bulk.selected")}
        </span>
      </div>
      <div className="flex flex-1 flex-wrap items-center justify-end gap-1.5">
        {actions.map((a) => {
          const Icon = a.icon;
          const tone =
            a.tone === "danger"
              ? "text-destructive border-destructive/40 hover:bg-destructive/10"
              : a.tone === "primary"
                ? "text-primary border-primary/40 hover:bg-primary/10"
                : "";
          return (
            <Button
              key={a.key}
              type="button"
              variant="outline"
              size="sm"
              onClick={a.onClick}
              className={"h-8 gap-1.5 " + tone}
            >
              <Icon className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">{a.label}</span>
            </Button>
          );
        })}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={onClear}
          className="h-8 gap-1.5"
        >
          <X className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">{t("action.cancel")}</span>
        </Button>
      </div>
    </div>
  );
}