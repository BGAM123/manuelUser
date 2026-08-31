/**
 * Composant réutilisable — Select simple (valeur unique) avec barre de
 * recherche textuelle. Pendant équivalent mono-sélection de SearchableMultiSelect.
 */

import { useState } from "react";
import { Check, Search, X, ChevronsUpDown } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/utils/utils";

export interface SelectOption<T extends string | number = number> {
  value: T;
  label: string;
  disabled?: boolean;
}

interface SearchableSelectProps<T extends string | number = number> {
  options: SelectOption<T>[];
  value: T | null;
  onChange: (id: T | null) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  disabled?: boolean;
}

export function SearchableSelect<T extends string | number = number>({
  options,
  value,
  onChange,
  placeholder = "Sélectionner...",
  searchPlaceholder = "Rechercher...",
  disabled = false,
}: SearchableSelectProps<T>) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");

  const q = search.trim().toLowerCase();
  const filtered = q ? options.filter((o) => o.label.toLowerCase().includes(q)) : options;
  const selected = options.find((o) => o.value === value);

  return (
    <Popover
      open={open}
      onOpenChange={(o) => {
        setOpen(o);
        if (!o) setSearch("");
      }}
    >
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          className={cn(
            "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50",
            !selected && "text-muted-foreground",
          )}
        >
          <span className="flex-1 truncate text-left">{selected?.label ?? placeholder}</span>
          <div className="flex shrink-0 items-center gap-1">
            {selected && (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => { e.stopPropagation(); onChange(null); }}
                onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), onChange(null))}
                className="rounded p-0.5 hover:bg-muted"
              >
                <X className="h-3.5 w-3.5 text-muted-foreground" />
              </span>
            )}
            <ChevronsUpDown className="h-4 w-4 text-muted-foreground" />
          </div>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-0 shadow-lg" align="start" sideOffset={4}>
        <div className="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={searchPlaceholder}
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
          {search && (
            <button type="button" onClick={() => setSearch("")}>
              <X className="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          )}
        </div>
        <div className="max-h-56 overflow-y-auto py-1">
          {filtered.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
          ) : (
            filtered.map((o) => (
              <button
                key={o.value}
                type="button"
                disabled={o.disabled}
                onClick={() => { onChange(o.value); setOpen(false); setSearch(""); }}
                className={cn(
                  "flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent",
                  o.value === value && "bg-primary/10 text-primary",
                )}
              >
                <span className="flex-1">{o.label}</span>
                {o.value === value && <Check className="h-3.5 w-3.5 shrink-0" />}
              </button>
            ))
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
