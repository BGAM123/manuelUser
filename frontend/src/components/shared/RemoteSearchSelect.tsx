/**
 * Sélecteur mono-valeur avec recherche SERVEUR débouncée (popover), pour les
 * référentiels où filtrer côté client une liste chargée une fois ne suffit
 * pas ou ne correspond pas au contrat documenté (ex: GET /categories?search=,
 * GET /asset-types?search=). Générique : la fonction de récupération est
 * fournie par l'appelant.
 */
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Check, ChevronsUpDown, Search, X } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/utils/utils";

export interface RemoteOption {
  id: number;
  nom: string;
}

interface RemoteSearchSelectProps {
  value: number | null;
  onChange: (id: number | null, label: string) => void;
  /** Récupère les options — appelé sans terme au premier chargement, puis avec le terme débouncé (≥2 caractères). */
  fetchOptions: (search: string) => Promise<RemoteOption[]>;
  /** Préfixe de clé React Query — doit être unique par référentiel pour éviter les collisions de cache. */
  queryKeyPrefix: string;
  placeholder?: string;
  searchPlaceholder?: string;
  disabled?: boolean;
}

export function RemoteSearchSelect({
  value,
  onChange,
  fetchOptions,
  queryKeyPrefix,
  placeholder = "Tous",
  searchPlaceholder = "Rechercher...",
  disabled = false,
}: RemoteSearchSelectProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  useEffect(() => {
    const h = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(h);
  }, [search]);

  const { data: initialData, isLoading: initialLoading } = useQuery({
    queryKey: [queryKeyPrefix, "initial"],
    queryFn: () => fetchOptions(""),
    staleTime: 60_000,
  });
  const { data: searchData, isFetching: searchLoading } = useQuery({
    queryKey: [queryKeyPrefix, "search", debouncedSearch],
    queryFn: () => fetchOptions(debouncedSearch),
    enabled: debouncedSearch.length >= 2,
    staleTime: 30_000,
  });

  const isSearching = debouncedSearch.length >= 2;
  const options = isSearching ? (searchData ?? []) : (initialData ?? []);
  const isLoading = isSearching ? searchLoading : initialLoading;

  const selectedLabel = useMemo(
    () => (initialData ?? []).find((o) => o.id === value)?.nom ?? (value != null ? `#${value}` : null),
    [initialData, value],
  );

  return (
    <Popover open={open} onOpenChange={(v) => { setOpen(v); if (!v) setSearch(""); }}>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          className={cn(
            "flex h-9 w-full items-center justify-between rounded-md border border-input bg-background px-3 text-xs shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60",
            !selectedLabel && "text-muted-foreground",
          )}
        >
          <span className="flex-1 truncate text-left">{selectedLabel ?? placeholder}</span>
          <div className="flex shrink-0 items-center gap-1">
            {value != null && (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => { e.stopPropagation(); onChange(null, ""); }}
                onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), onChange(null, ""))}
                className="rounded p-0.5 hover:bg-muted"
              >
                <X className="h-3.5 w-3.5 text-muted-foreground" />
              </span>
            )}
            <ChevronsUpDown className="h-3.5 w-3.5 text-muted-foreground" />
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
        <div className="max-h-64 overflow-y-auto py-1">
          {isLoading ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Chargement…</p>
          ) : options.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
          ) : (
            options.map((o) => (
              <button
                key={o.id}
                type="button"
                onClick={() => { onChange(o.id, o.nom); setOpen(false); setSearch(""); }}
                className={cn(
                  "flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60",
                  o.id === value && "bg-primary/10 text-primary",
                )}
              >
                <span className="flex-1 truncate">{o.nom}</span>
                {o.id === value && <Check className="h-3.5 w-3.5 shrink-0" />}
              </button>
            ))
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
