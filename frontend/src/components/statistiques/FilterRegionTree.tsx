/**
 * FilterRegionTree — Sélecteur Région > Département > Arrondissement sous
 * forme d'arbre dépliable (même pattern visuel que FilterOrganigramme), avec
 * recherche en temps réel. N'importe quel niveau est sélectionnable (pas
 * seulement l'arrondissement) — utile en filtre de statistiques, où on veut
 * pouvoir restreindre à toute une région, un département, ou un arrondissement
 * précis. Données réelles via GET /cartographie (useCartographie).
 */
import { useMemo, useState } from "react";
import { ChevronRight, Search, MapPin, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { cn } from "@/utils/utils";
import { useCartographie } from "@/hooks/useFilterReferentiel";
import type { CartographieRegion } from "@/api/regions/regions.api";

export interface RegionTreeSelection {
  level: "region" | "departement" | "arrondissement";
  id: number;
  nom: string;
}

interface FilterRegionTreeProps {
  selection: RegionTreeSelection | null;
  onSelect: (sel: RegionTreeSelection | null) => void;
}

function regionMatchesSearch(r: CartographieRegion, q: string): boolean {
  if (r.nom.toLowerCase().includes(q)) return true;
  return r.departements.some(
    (d) => d.nom.toLowerCase().includes(q) || d.arrondissements.some((a) => a.nom.toLowerCase().includes(q)),
  );
}

export function FilterRegionTree({ selection, onSelect }: FilterRegionTreeProps) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const [openRegions, setOpenRegions] = useState<Set<number>>(new Set());
  const [openDepartements, setOpenDepartements] = useState<Set<number>>(new Set());
  const { data: regions = [], isLoading } = useCartographie();

  const q = search.trim().toLowerCase();
  const forceOpen = q.length > 0;

  const visibleRegions = useMemo(
    () => (q ? regions.filter((r) => regionMatchesSearch(r, q)) : regions),
    [regions, q],
  );

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

  const handleSelect = (sel: RegionTreeSelection) => {
    onSelect(sel);
    setOpen(false);
    setSearch("");
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
          open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
          selection ? "text-foreground font-medium" : "text-muted-foreground",
        )}
      >
        <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
        <span className="flex-1 truncate text-left">
          {isLoading ? "Chargement…" : selection?.nom ?? "Toutes les régions"}
        </span>
        {selection && (
          <X
            className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
            onClick={(e) => { e.stopPropagation(); onSelect(null); setOpen(false); }}
          />
        )}
        <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", open && "rotate-90")} />
      </button>

      {open && (
        <div className="absolute z-50 mt-1 w-full min-w-[280px] rounded-xl border border-border bg-card shadow-lg">
          <div className="p-2 border-b border-border">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                autoFocus
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Rechercher région, département, arrondissement…"
                className="h-7 pl-8 text-xs bg-muted/30"
              />
              {search && (
                <button type="button" onClick={() => setSearch("")} className="absolute right-2 top-1.5">
                  <X className="h-3.5 w-3.5 text-muted-foreground" />
                </button>
              )}
            </div>
          </div>

          <div
            className={cn(
              "flex items-center gap-2 px-3 py-2 text-xs cursor-pointer transition-colors",
              !selection ? "bg-emerald-50 text-emerald-900 dark:bg-emerald-950/50 font-bold" : "hover:bg-muted/60 text-muted-foreground",
            )}
            onClick={() => { onSelect(null); setOpen(false); }}
          >
            <MapPin className="h-3.5 w-3.5" />
            Toutes les régions
          </div>

          <div className="max-h-72 overflow-y-auto py-1">
            {isLoading ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Chargement…</p>
            ) : visibleRegions.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              visibleRegions.map((r) => {
                const rOpen = forceOpen || openRegions.has(r.id);
                const isRegionSelected = selection?.level === "region" && selection.id === r.id;
                return (
                  <div key={r.id}>
                    <div
                      className={cn(
                        "flex items-center gap-1.5 px-2 py-1.5 text-xs cursor-pointer transition-colors font-medium",
                        isRegionSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-foreground",
                      )}
                      onClick={() => handleSelect({ level: "region", id: r.id, nom: r.nom })}
                    >
                      <button
                        type="button"
                        className="shrink-0 p-0.5 rounded hover:bg-muted"
                        onClick={(e) => { e.stopPropagation(); toggleRegion(r.id); }}
                      >
                        <ChevronRight className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", rOpen && "rotate-90")} />
                      </button>
                      <span className="flex-1 truncate">{r.nom}</span>
                    </div>
                    {rOpen && r.departements.map((d) => {
                      const dOpen = forceOpen || openDepartements.has(d.id);
                      const isDeptSelected = selection?.level === "departement" && selection.id === d.id;
                      return (
                        <div key={d.id}>
                          <div
                            className={cn(
                              "flex items-center gap-1.5 py-1.5 pr-2 text-xs cursor-pointer transition-colors font-medium",
                              isDeptSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-foreground",
                            )}
                            style={{ paddingLeft: "22px" }}
                            onClick={() => handleSelect({ level: "departement", id: d.id, nom: d.nom })}
                          >
                            <button
                              type="button"
                              className="shrink-0 p-0.5 rounded hover:bg-muted"
                              onClick={(e) => { e.stopPropagation(); toggleDept(d.id); }}
                            >
                              <ChevronRight className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", dOpen && "rotate-90")} />
                            </button>
                            <span className="flex-1 truncate">{d.nom}</span>
                          </div>
                          {dOpen && d.arrondissements.map((a) => {
                            const isArrSelected = selection?.level === "arrondissement" && selection.id === a.id;
                            return (
                              <div
                                key={a.id}
                                className={cn(
                                  "flex items-center gap-1.5 py-1.5 pr-2 text-xs cursor-pointer transition-colors",
                                  isArrSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300 font-medium" : "hover:bg-muted/60 text-muted-foreground",
                                )}
                                style={{ paddingLeft: "38px" }}
                                onClick={() => handleSelect({ level: "arrondissement", id: a.id, nom: a.nom })}
                              >
                                <span className="flex-1 truncate">{a.nom}</span>
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
        </div>
      )}

      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />}
    </div>
  );
}
