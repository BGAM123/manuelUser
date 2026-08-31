/**
 * FilterRegionTreeMulti — variante multi-sélection de FilterRegionTree
 * (cases à cocher sur région/département/arrondissement, indépendamment,
 * n'importe quelle combinaison de niveaux) — region_ids/departement_ids/
 * arrondissement_ids acceptent des tableaux côté API (StatisticsFilter).
 * Données réelles via GET /cartographie (useCartographie), recherche incluse.
 *
 * Sélection différée : cocher des cases ne déclenche pas de rechargement à
 * chaque clic — les changements ne sont appliqués (onChange) qu'au clic sur
 * "Appliquer" (ergonomie demandée explicitement, 2026-08-27).
 */
import { useEffect, useMemo, useState } from "react";
import { ChevronRight, Search, MapPin, X, Check } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/utils/utils";
import { useCartographie } from "@/hooks/useFilterReferentiel";
import type { CartographieRegion } from "@/api/regions/regions.api";

interface FilterRegionTreeMultiProps {
  regionIds: number[];
  departementIds: number[];
  arrondissementIds: number[];
  onChange: (regionIds: number[], departementIds: number[], arrondissementIds: number[]) => void;
}

function regionMatchesSearch(r: CartographieRegion, q: string): boolean {
  if (r.nom.toLowerCase().includes(q)) return true;
  return r.departements.some(
    (d) => d.nom.toLowerCase().includes(q) || d.arrondissements.some((a) => a.nom.toLowerCase().includes(q)),
  );
}

export function FilterRegionTreeMulti({ regionIds, departementIds, arrondissementIds, onChange }: FilterRegionTreeMultiProps) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const [openRegions, setOpenRegions] = useState<Set<number>>(new Set());
  const [openDepartements, setOpenDepartements] = useState<Set<number>>(new Set());
  const { data: regions = [], isLoading } = useCartographie();

  // Sélection en cours d'édition — réinitialisée sur la valeur appliquée à
  // chaque ouverture (un changement non appliqué est abandonné à la fermeture).
  const [stagedRegion, setStagedRegion] = useState<number[]>(regionIds);
  const [stagedDept, setStagedDept] = useState<number[]>(departementIds);
  const [stagedArr, setStagedArr] = useState<number[]>(arrondissementIds);
  useEffect(() => {
    if (open) { setStagedRegion(regionIds); setStagedDept(departementIds); setStagedArr(arrondissementIds); }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const q = search.trim().toLowerCase();
  const forceOpen = q.length > 0;
  const visibleRegions = useMemo(() => (q ? regions.filter((r) => regionMatchesSearch(r, q)) : regions), [regions, q]);

  const toggleRegionOpen = (id: number) =>
    setOpenRegions((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const toggleDeptOpen = (id: number) =>
    setOpenDepartements((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });

  const toggleRegion = (id: number) =>
    setStagedRegion((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  const toggleDept = (id: number) =>
    setStagedDept((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  const toggleArr = (id: number) =>
    setStagedArr((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  const apply = () => { onChange(stagedRegion, stagedDept, stagedArr); setOpen(false); };

  const totalSelected = regionIds.length + departementIds.length + arrondissementIds.length;
  const totalStaged = stagedRegion.length + stagedDept.length + stagedArr.length;

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
          open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
          totalSelected > 0 ? "text-foreground font-medium" : "text-muted-foreground",
        )}
      >
        <MapPin className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
        <span className="flex-1 truncate text-left">
          {isLoading ? "Chargement…" : totalSelected === 0 ? "Toutes les régions" : `${totalSelected} sélection${totalSelected > 1 ? "s" : ""}`}
        </span>
        {totalSelected > 0 && (
          <X
            className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
            onClick={(e) => { e.stopPropagation(); onChange([], [], []); }}
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

          <div className="max-h-72 overflow-y-auto py-1">
            {isLoading ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Chargement…</p>
            ) : visibleRegions.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              visibleRegions.map((r) => {
                const rOpen = forceOpen || openRegions.has(r.id);
                const isRSelected = stagedRegion.includes(r.id);
                return (
                  <div key={r.id}>
                    <div
                      className={cn(
                        "flex items-center gap-1.5 px-2 py-1.5 text-xs cursor-pointer transition-colors font-medium",
                        isRSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-foreground",
                      )}
                      onClick={() => toggleRegion(r.id)}
                    >
                      <button type="button" className="shrink-0 p-0.5 rounded hover:bg-muted" onClick={(e) => { e.stopPropagation(); toggleRegionOpen(r.id); }}>
                        <ChevronRight className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", rOpen && "rotate-90")} />
                      </button>
                      <span className={cn("flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-2", isRSelected ? "border-emerald-600 bg-emerald-600" : "border-muted-foreground/40")}>
                        {isRSelected && <Check className="h-2.5 w-2.5 text-white" />}
                      </span>
                      <span className="flex-1 truncate">{r.nom}</span>
                    </div>
                    {rOpen && r.departements.map((d) => {
                      const dOpen = forceOpen || openDepartements.has(d.id);
                      const isDSelected = stagedDept.includes(d.id);
                      return (
                        <div key={d.id}>
                          <div
                            className={cn(
                              "flex items-center gap-1.5 py-1.5 pr-2 text-xs cursor-pointer transition-colors font-medium",
                              isDSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-foreground",
                            )}
                            style={{ paddingLeft: "22px" }}
                            onClick={() => toggleDept(d.id)}
                          >
                            <button type="button" className="shrink-0 p-0.5 rounded hover:bg-muted" onClick={(e) => { e.stopPropagation(); toggleDeptOpen(d.id); }}>
                              <ChevronRight className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", dOpen && "rotate-90")} />
                            </button>
                            <span className={cn("flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-2", isDSelected ? "border-emerald-600 bg-emerald-600" : "border-muted-foreground/40")}>
                              {isDSelected && <Check className="h-2.5 w-2.5 text-white" />}
                            </span>
                            <span className="flex-1 truncate">{d.nom}</span>
                          </div>
                          {dOpen && d.arrondissements.map((a) => {
                            const isASelected = stagedArr.includes(a.id);
                            return (
                              <div
                                key={a.id}
                                className={cn(
                                  "flex items-center gap-1.5 py-1.5 pr-2 text-xs cursor-pointer transition-colors",
                                  isASelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-muted-foreground",
                                )}
                                style={{ paddingLeft: "38px" }}
                                onClick={() => toggleArr(a.id)}
                              >
                                <span className={cn("flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-2", isASelected ? "border-emerald-600 bg-emerald-600" : "border-muted-foreground/40")}>
                                  {isASelected && <Check className="h-2.5 w-2.5 text-white" />}
                                </span>
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
          <div className="flex items-center justify-between gap-2 border-t border-border px-3 py-2">
            <button
              type="button"
              onClick={() => { setStagedRegion([]); setStagedDept([]); setStagedArr([]); }}
              disabled={totalStaged === 0}
              className="text-xs text-muted-foreground hover:text-destructive disabled:opacity-40"
            >
              Effacer
            </button>
            <Button type="button" size="sm" className="h-7 text-xs" onClick={apply}>
              Appliquer{totalStaged > 0 ? ` (${totalStaged})` : ""}
            </Button>
          </div>
        </div>
      )}

      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />}
    </div>
  );
}
