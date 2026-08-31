/**
 * Sélecteur Région > Département > Arrondissement sous forme d'organigramme
 * dans un SEUL champ (popover + recherche + arbre dépliable) — même pattern
 * que OrgTreeSelect (structures/postes), adapté à la hiérarchie fixe à 3
 * niveaux de la Cartographie (GET /cartographie).
 *
 * Seuls les arrondissements (dernier niveau) sont sélectionnables — région
 * et département ne servent qu'à déplier/filtrer, jamais à eux-mêmes une
 * valeur du champ.
 */
import { useMemo, useState } from "react";
import { ChevronRight, Search, X } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/utils/utils";
import type { CartographieRegion } from "@/api/regions/regions.api";

function filterRegions(regions: CartographieRegion[], q: string): CartographieRegion[] {
  if (!q) return regions;
  return regions.reduce<CartographieRegion[]>((acc, r) => {
    const departements = r.departements.reduce<CartographieRegion["departements"]>((accD, d) => {
      const matchingArrondissements = d.arrondissements.filter((a) => a.nom.toLowerCase().includes(q));
      if (d.nom.toLowerCase().includes(q)) {
        accD.push(d);
      } else if (matchingArrondissements.length > 0) {
        accD.push({ ...d, arrondissements: matchingArrondissements });
      }
      return accD;
    }, []);
    if (r.nom.toLowerCase().includes(q)) {
      acc.push(r);
    } else if (departements.length > 0) {
      acc.push({ ...r, departements });
    }
    return acc;
  }, []);
}

export function CartographieTreeSelect({
  value,
  onChange,
  regions,
  placeholder = "Sélectionner un arrondissement",
  disabled = false,
}: {
  /** Nom de l'arrondissement sélectionné (valeur du champ), ou "". */
  value: string;
  onChange: (arrondissementNom: string) => void;
  regions: CartographieRegion[];
  placeholder?: string;
  disabled?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [openRegions, setOpenRegions] = useState<Set<number>>(new Set());
  const [openDepartements, setOpenDepartements] = useState<Set<number>>(new Set());

  const q = search.trim().toLowerCase();
  const filtered = useMemo(() => filterRegions(regions, q), [regions, q]);

  const toggleRegion = (id: number) =>
    setOpenRegions((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  const toggleDept = (id: number) =>
    setOpenDepartements((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const handleSelect = (nom: string) => {
    onChange(nom);
    setOpen(false);
    setSearch("");
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          className={cn(
            "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60",
            !value && "text-muted-foreground",
          )}
        >
          <span className="flex-1 truncate text-left">{value || placeholder}</span>
          <div className="flex shrink-0 items-center gap-1">
            {value && (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => { e.stopPropagation(); onChange(""); }}
                onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), onChange(""))}
                className="rounded p-0.5 hover:bg-muted"
              >
                <X className="h-3.5 w-3.5 text-muted-foreground" />
              </span>
            )}
            <ChevronRight className={cn("h-4 w-4 text-muted-foreground transition-transform", open && "rotate-90")} />
          </div>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[380px] p-0 shadow-lg" align="start" sideOffset={4}>
        <div className="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Rechercher région, département, arrondissement..."
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
          {search && <button type="button" onClick={() => setSearch("")}><X className="h-3.5 w-3.5 text-muted-foreground" /></button>}
        </div>
        <div className="max-h-72 overflow-y-auto py-1">
          {filtered.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
          ) : (
            filtered.map((r) => {
              const rOpen = q ? true : openRegions.has(r.id);
              return (
                <div key={r.id}>
                  <div
                    role="button"
                    tabIndex={0}
                    onClick={() => toggleRegion(r.id)}
                    onKeyDown={(e) => e.key === "Enter" && toggleRegion(r.id)}
                    className="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-primary/8"
                  >
                    <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", rOpen && "rotate-90")} />
                    <span className="flex-1 text-left">{r.nom}</span>
                  </div>
                  {rOpen && r.departements.map((d) => {
                    const dOpen = q ? true : openDepartements.has(d.id);
                    return (
                      <div key={d.id}>
                        <div
                          role="button"
                          tabIndex={0}
                          onClick={() => toggleDept(d.id)}
                          onKeyDown={(e) => e.key === "Enter" && toggleDept(d.id)}
                          style={{ paddingLeft: "28px" }}
                          className="flex w-full cursor-pointer items-center gap-2 pr-3 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-primary/8"
                        >
                          <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", dOpen && "rotate-90")} />
                          <span className="flex-1 text-left">{d.nom}</span>
                        </div>
                        {dOpen && d.arrondissements.map((a) => {
                          const isSelected = value === a.nom;
                          return (
                            <div
                              key={a.id}
                              role="button"
                              tabIndex={0}
                              onClick={() => handleSelect(a.nom)}
                              onKeyDown={(e) => e.key === "Enter" && handleSelect(a.nom)}
                              style={{ paddingLeft: "44px" }}
                              className={cn(
                                "flex w-full cursor-pointer items-center gap-2 pr-3 py-1.5 text-sm transition-colors hover:bg-primary/8",
                                isSelected && "bg-primary/10 text-primary",
                              )}
                            >
                              <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors", isSelected ? "border-primary bg-primary" : "border-muted-foreground/40")}>
                                {isSelected && <span className="h-1.5 w-1.5 rounded-full bg-white" />}
                              </span>
                              <span className="flex-1 text-left">{a.nom}</span>
                            </div>
                          );
                        })}
                      </div>
                    );
                  })}
                </div>
              );
            })
          )}
        </div>
        {value && (
          <div className="border-t border-border px-3 py-2">
            <button type="button" onClick={() => { onChange(""); setOpen(false); }} className="text-xs text-muted-foreground hover:text-destructive">
              Effacer la sélection
            </button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}
