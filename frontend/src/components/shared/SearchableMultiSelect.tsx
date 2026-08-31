/**
 * Composant réutilisable — Select multiple avec barre de recherche textuelle.
 * Utilise un Popover shadcn/ui avec checkboxes et filtrage instantané.
 *
 * `deferApply` (opt-in, défaut false) — pour les FILTRES qui déclenchent un
 * rechargement de données à chaque changement : les cases cochées ne sont
 * appliquées (onChange appelé) qu'au clic sur "Appliquer", pas à chaque case
 * cochée individuellement (ergonomie demandée explicitement, 2026-08-27 —
 * cocher 5 options ne doit pas provoquer 5 rechargements de page). Les
 * formulaires de saisie (ex: association champ ↔ catégories) gardent le
 * comportement immédiat par défaut, où il n'y a pas de rechargement réseau
 * à chaque case cochée.
 *
 * displayStyle ("chips" par défaut, inchangé — utilisé par les formulaires de
 * configuration où voir/retirer chaque valeur individuellement est utile) vs
 * "compact" (résumé texte façon FilterCategorieTreeMulti / FilterRegionTreeMulti
 * — "N sélections" — ajouté le 2026-08-28 pour le filtre "Source de
 * financement" des Statistiques, où des libellés de projet longs faisaient
 * déborder les badges individuels).
 */

import { useMemo, useEffect, useState } from "react";
import { Check, Search, X, ChevronsUpDown } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/utils/utils";

export interface MultiSelectOption {
  value: number;
  label: string;
}

interface SearchableMultiSelectProps {
  options: MultiSelectOption[];
  value: number[];
  onChange: (ids: number[]) => void;
  placeholder?: string;
  disabled?: boolean;
  /** Voir en-tête du fichier — n'applique les changements qu'au clic sur "Appliquer". */
  deferApply?: boolean;
  displayStyle?: "chips" | "compact";
}

export function SearchableMultiSelect({
  options,
  value,
  onChange,
  placeholder = "Rechercher...",
  disabled = false,
  deferApply = false,
  displayStyle = "chips",
}: SearchableMultiSelectProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [staged, setStaged] = useState<number[]>(value);

  // Ré-initialise la sélection en cours d'édition sur la valeur réellement
  // appliquée à chaque ouverture — un changement non appliqué (fermeture sans
  // cliquer "Appliquer") est ainsi silencieusement abandonné.
  useEffect(() => {
    if (open) setStaged(value);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const q = search.trim().toLowerCase();
  const filtered = q
    ? options.filter((o) => o.label.toLowerCase().includes(q))
    : options;

  const active = deferApply ? staged : value;
  const activeSet = new Set(active);

  const toggle = (id: number) => {
    const next = activeSet.has(id) ? active.filter((v) => v !== id) : [...active, id];
    if (deferApply) setStaged(next);
    else onChange(next);
  };

  // "Tout sélectionner" — porte sur la totalité des options (pas seulement
  // celles visibles après recherche), demande explicite (2026-08-29) : ce
  // bouton vit dans le composant de sélection lui-même (pas ajouté au coup
  // par coup par chaque formulaire appelant).
  const selectAll = () => {
    const allIds = options.map((o) => o.value);
    if (deferApply) setStaged(allIds);
    else onChange(allIds);
  };
  const clearAll = () => {
    if (deferApply) setStaged([]);
    else onChange([]);
  };

  // Les puces du trigger reflètent toujours la sélection APPLIQUÉE (value),
  // jamais la sélection en cours d'édition dans le popover.
  const selectedLabels = value
    .map((id) => options.find((o) => o.value === id)?.label)
    .filter(Boolean) as string[];

  const summaryLabel = useMemo(() => {
    if (selectedLabels.length === 0) return null;
    if (selectedLabels.length === 1) return selectedLabels[0];
    return `${selectedLabels.length} sélections`;
  }, [selectedLabels]);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        {displayStyle === "compact" ? (
          <button
            type="button"
            disabled={disabled}
            className={cn(
              "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
              open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
              summaryLabel ? "text-foreground font-medium" : "text-muted-foreground",
              disabled && "cursor-not-allowed opacity-50",
            )}
          >
            <span className="flex-1 truncate text-left">{summaryLabel ?? placeholder}</span>
            {value.length > 0 && (
              <X
                className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
                onClick={(e) => { e.stopPropagation(); onChange([]); }}
              />
            )}
            <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
          </button>
        ) : (
          <button
            type="button"
            disabled={disabled}
            className={cn(
              "flex min-h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50",
              value.length === 0 && "text-muted-foreground",
            )}
          >
            <div className="flex flex-1 flex-wrap gap-1 text-left">
              {selectedLabels.length === 0 ? (
                <span>{placeholder}</span>
              ) : selectedLabels.length <= 3 ? (
                selectedLabels.map((label, i) => (
                  <Badge key={i} variant="secondary" className="gap-1 text-xs">
                    {label}
                    <span
                      role="button"
                      tabIndex={0}
                      onClick={(e) => { e.stopPropagation(); toggle(value[i]); }}
                      onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), toggle(value[i]))}
                      className="ml-0.5 cursor-pointer rounded-sm opacity-60 hover:opacity-100"
                    >
                      <X className="h-3 w-3" />
                    </span>
                  </Badge>
                ))
              ) : (
                <Badge variant="secondary" className="text-xs">
                  {selectedLabels.length} sélectionnés
                </Badge>
              )}
            </div>
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 text-muted-foreground" />
          </button>
        )}
      </PopoverTrigger>
      <PopoverContent className="w-[320px] p-0 shadow-lg" align="start" sideOffset={4}>
        {/* Barre de recherche */}
        <div className="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={placeholder}
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
          {search && (
            <button type="button" onClick={() => setSearch("")}>
              <X className="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          )}
        </div>
        {options.length > 0 && (
          <div className="flex items-center gap-2 border-b border-border px-3 py-1.5">
            <button
              type="button"
              onClick={selectAll}
              disabled={active.length === options.length}
              className="text-xs font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-40"
            >
              Tout sélectionner
            </button>
            <span className="text-xs text-muted-foreground">·</span>
            <button
              type="button"
              onClick={clearAll}
              disabled={active.length === 0}
              className="text-xs font-medium text-muted-foreground hover:text-destructive hover:underline disabled:cursor-not-allowed disabled:opacity-40"
            >
              Tout désélectionner
            </button>
          </div>
        )}

        {/* Liste avec checkboxes */}
        <div className="max-h-56 overflow-y-auto py-1">
          {filtered.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
          ) : (
            filtered.map((o) => {
              const checked = activeSet.has(o.value);
              return (
                <button
                  key={o.value}
                  type="button"
                  onClick={() => toggle(o.value)}
                  className={cn(
                    "flex w-full items-center gap-2.5 px-3 py-2 text-sm transition-colors hover:bg-muted/60",
                    checked && "bg-primary/5",
                  )}
                >
                  <span
                    className={cn(
                      "flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors",
                      checked
                        ? "border-primary bg-primary text-primary-foreground"
                        : "border-muted-foreground/40",
                    )}
                  >
                    {checked && <Check className="h-3 w-3" />}
                  </span>
                  <span className="flex-1 text-left">{o.label}</span>
                </button>
              );
            })
          )}
        </div>

        {/* Footer — "Tout sélectionner"/"Tout désélectionner" vivent déjà
            au-dessus (sous la recherche), pas besoin d'un "Effacer" redondant ici. */}
        {deferApply ? (
          <div className="flex items-center justify-end gap-2 border-t border-border px-3 py-2">
            <Button
              type="button"
              size="sm"
              className="h-7 text-xs"
              onClick={() => { onChange(staged); setOpen(false); }}
            >
              Appliquer{staged.length > 0 ? ` (${staged.length})` : ""}
            </Button>
          </div>
        ) : (
          value.length > 0 && (
            <div className="border-t border-border px-3 py-2">
              <span className="text-xs text-muted-foreground">
                {value.length} sélectionné{value.length > 1 ? "s" : ""}
              </span>
            </div>
          )
        )}
      </PopoverContent>
    </Popover>
  );
}
