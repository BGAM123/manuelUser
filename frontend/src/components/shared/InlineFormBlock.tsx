import { useState, type ReactNode } from "react";
import { Pencil, X, Save, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useT } from "@/utils/i18n";
import { cn } from "@/utils/utils";

export type InlineFormBlockProps = {
  title: string;
  description?: string;
  icon?: React.ComponentType<{ className?: string }>;
  read: ReactNode;
  form: (ctx: { close: () => void }) => ReactNode;
  className?: string;
  editLabel?: string;
  editIcon?: "edit" | "add";
  extraActions?: ReactNode;
  defaultOpen?: boolean;
};

/**
 * Bloc à deux états : lecture ("read") et édition ("form").
 * L'édition se fait EN PLACE dans la même carte — jamais dans une modale.
 */
export function InlineFormBlock({
  title,
  description,
  icon: Icon,
  read,
  form,
  className,
  editLabel,
  editIcon = "edit",
  extraActions,
  defaultOpen = false,
}: InlineFormBlockProps) {
  const t = useT();
  const [editing, setEditing] = useState(defaultOpen);
  const ActionIcon = editIcon === "add" ? Plus : Pencil;

  return (
    <section
      className={cn(
        "rounded-xl border bg-card shadow-sm transition-colors",
        editing ? "border-primary/40 ring-1 ring-primary/10" : "border-border",
        className
      )}
    >
      <header className="flex items-start justify-between gap-3 border-b border-border/70 p-4">
        <div className="min-w-0 flex items-start gap-3">
          {Icon ? (
            <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
              <Icon className="h-4 w-4" />
            </span>
          ) : null}
          <div className="min-w-0">
            <h3 className="truncate text-sm font-semibold">{title}</h3>
            {description ? (
              <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
            ) : null}
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-1.5">
          {!editing ? extraActions : null}
          {!editing ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-8 gap-1.5"
              onClick={() => setEditing(true)}
            >
              <ActionIcon className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">
                {editLabel ?? (editIcon === "add" ? t("action.add") : t("action.edit"))}
              </span>
            </Button>
          ) : (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-8 gap-1.5"
              onClick={() => setEditing(false)}
            >
              <X className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">{t("action.cancel")}</span>
            </Button>
          )}
        </div>
      </header>
      <div className="p-4">
        {editing ? form({ close: () => setEditing(false) }) : read}
      </div>
    </section>
  );
}

/** Barre d'actions du formulaire in-place (annuler / enregistrer). */
export function InlineFormActions({
  onCancel,
  submitLabel,
  submitting,
}: {
  onCancel: () => void;
  submitLabel?: string;
  submitting?: boolean;
}) {
  const t = useT();
  return (
    <div className="mt-4 flex justify-end gap-2 border-t border-border pt-3">
      <Button type="button" variant="outline" size="sm" onClick={onCancel}>
        {t("action.cancel")}
      </Button>
      <Button type="submit" size="sm" disabled={submitting} className="gap-1.5">
        <Save className="h-3.5 w-3.5" />
        {submitting ? "…" : (submitLabel ?? t("action.save"))}
      </Button>
    </div>
  );
}