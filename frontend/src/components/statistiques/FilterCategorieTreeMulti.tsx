/**
 * FilterCategorieTreeMulti — variante multi-sélection de FilterCategorieTree
 * (cases à cocher sur les catégories ET les types de biens, indépendamment),
 * pour le filtre statistiques (type_bien_ids accepte une liste côté API ;
 * categorie_id n'accepte qu'un seul ID pour l'instant — voir StatisticsFilter
 * et filterMapper.ts). Une évolution du backend pour accepter une liste de
 * catégories est annoncée (2026-08-28) mais pas encore livrée : en attendant,
 * l'UI reste multi-sélection mais seule la catégorie la plus récemment cochée
 * est réellement appliquée côté API, avec un badge "Actif" + une note visible
 * pour éviter toute confusion. Recherche en temps réel conservée, sur données
 * réelles (useCategorieHierarchie).
 *
 * Sélection différée : cocher des cases ne déclenche pas de rechargement à
 * chaque clic — les changements ne sont appliqués (onChange) qu'au clic sur
 * "Appliquer" (ergonomie demandée explicitement, 2026-08-27).
 */
import { useEffect, useMemo, useState } from "react";
import { ChevronRight, Search, Layers, X, Check } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/utils/utils";
import { useCategorieHierarchie } from "@/hooks/useFilterReferentiel";
import type { CatTypeNode } from "@/hooks/useFilterReferentiel";

interface FilterCategorieTreeMultiProps {
  categorieIds: number[];
  assetTypeIds: number[];
  onChange: (categorieIds: number[], assetTypeIds: number[]) => void;
}

function catMatchesSearch(node: CatTypeNode, term: string): boolean {
  const t = term.toLowerCase();
  if (node.categorie.nom.toLowerCase().includes(t)) return true;
  return node.types.some((tp) => tp.nom.toLowerCase().includes(t));
}

export function FilterCategorieTreeMulti({ categorieIds, assetTypeIds, onChange }: FilterCategorieTreeMultiProps) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const [openCats, setOpenCats] = useState<Set<number>>(new Set());
  const { data: tree = [], isLoading } = useCategorieHierarchie();

  // Sélection en cours d'édition — réinitialisée sur la valeur appliquée à
  // chaque ouverture (un changement non appliqué est abandonné à la fermeture).
  const [stagedCat, setStagedCat] = useState<number[]>(categorieIds);
  const [stagedType, setStagedType] = useState<number[]>(assetTypeIds);
  useEffect(() => {
    if (open) { setStagedCat(categorieIds); setStagedType(assetTypeIds); }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const q = search.trim().toLowerCase();
  const forceOpen = q.length > 0;
  const visibleNodes = useMemo(() => (q ? tree.filter((n) => catMatchesSearch(n, q)) : tree), [tree, q]);

  const toggleOpen = (id: number) =>
    setOpenCats((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  // Sélection additive (multi-catégories) conservée à la demande explicite du
  // 2026-08-28, MÊME SI le backend actuel n'accepte qu'un seul `categorie_id`
  // par requête (vérifié sur le Swagger) — une évolution serveur pour accepter
  // une liste est prévue prochainement. En attendant, filterMapper.ts n'envoie
  // que la catégorie la plus récemment cochée (dernier élément du tableau) ;
  // StatistiquesFilterBar affiche un badge indiquant laquelle est active pour
  // éviter la confusion silencieuse du 2026-08-28 (2e catégorie cochée sans
  // effet visible). Repasser à un vrai multi-filtrage dès que le paramètre API
  // accepte une liste.
  const toggleCategorie = (id: number) => {
    setStagedCat((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  };
  const toggleType = (id: number) => {
    setStagedType((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  };
  const apply = () => { onChange(stagedCat, stagedType); setOpen(false); };

  // Le résumé du trigger reflète toujours la sélection APPLIQUÉE, jamais la
  // sélection en cours d'édition dans le popover.
  const totalSelected = categorieIds.length + assetTypeIds.length;
  // Catégorie réellement envoyée à l'API tant que categorie_id n'accepte
  // qu'une seule valeur — voir filterMapper.ts (dernier élément coché).
  const activeCategorieId = categorieIds.length > 0 ? categorieIds[categorieIds.length - 1] : null;
  const activeCategorieNom = useMemo(
    () => tree.find((n) => n.categorie.id === activeCategorieId)?.categorie.nom,
    [tree, activeCategorieId],
  );
  const totalStaged = stagedCat.length + stagedType.length;
  const summaryLabel = useMemo(() => {
    if (totalSelected === 0) return null;
    if (totalSelected === 1) {
      const cat = tree.find((n) => n.categorie.id === categorieIds[0]);
      if (cat) return cat.categorie.nom;
      const type = tree.flatMap((n) => n.types).find((t) => t.id === assetTypeIds[0]);
      return type?.nom ?? `${totalSelected} sélection(s)`;
    }
    if (categorieIds.length > 1 && activeCategorieNom) {
      return `${totalSelected} sélections — Actif : ${activeCategorieNom}`;
    }
    return `${totalSelected} sélections`;
  }, [totalSelected, categorieIds, assetTypeIds, tree, activeCategorieNom]);

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
          open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
          summaryLabel ? "text-foreground font-medium" : "text-muted-foreground",
        )}
      >
        <Layers className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
        <span className="flex-1 truncate text-left">
          {isLoading ? "Chargement…" : summaryLabel ?? "Toutes les catégories"}
        </span>
        {totalSelected > 0 && (
          <X
            className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
            onClick={(e) => { e.stopPropagation(); onChange([], []); }}
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
                placeholder="Rechercher une catégorie ou un type…"
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
            ) : visibleNodes.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              visibleNodes.map((node) => {
                const isOpen = forceOpen || openCats.has(node.categorie.id);
                const isCatSelected = stagedCat.includes(node.categorie.id);
                const isCatActive = node.categorie.id === activeCategorieId;
                const visibleTypes = q ? node.types.filter((t) => t.nom.toLowerCase().includes(q)) : node.types;
                return (
                  <div key={node.categorie.id}>
                    <div
                      className={cn(
                        "flex items-center gap-1.5 rounded-lg px-2 py-1.5 cursor-pointer transition-colors text-xs",
                        isCatSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-foreground",
                        isCatSelected && !isCatActive && "opacity-60",
                      )}
                      onClick={() => toggleCategorie(node.categorie.id)}
                    >
                      {node.types.length > 0 ? (
                        <button type="button" className="shrink-0 p-0.5 rounded hover:bg-muted" onClick={(e) => { e.stopPropagation(); toggleOpen(node.categorie.id); }}>
                          <ChevronRight className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", isOpen && "rotate-90")} />
                        </button>
                      ) : (
                        <span className="h-4 w-4 shrink-0" />
                      )}
                      <span className={cn("flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-2", isCatSelected ? "border-emerald-600 bg-emerald-600" : "border-muted-foreground/40")}>
                        {isCatSelected && <Check className="h-2.5 w-2.5 text-white" />}
                      </span>
                      <span className="flex-1 truncate font-medium leading-tight">{node.categorie.nom}</span>
                      {isCatActive && categorieIds.length > 1 && (
                        <span className="shrink-0 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">
                          Actif
                        </span>
                      )}
                    </div>
                    {node.types.length > 0 && isOpen && (
                      <div>
                        {visibleTypes.map((t) => {
                          const isTypeSelected = stagedType.includes(t.id);
                          return (
                            <div
                              key={t.id}
                              className={cn(
                                "flex items-center gap-1.5 py-1.5 pr-2 text-xs cursor-pointer transition-colors",
                                isTypeSelected ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300" : "hover:bg-muted/60 text-muted-foreground",
                              )}
                              style={{ paddingLeft: "30px" }}
                              onClick={() => toggleType(t.id)}
                            >
                              <span className={cn("flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border-2", isTypeSelected ? "border-emerald-600 bg-emerald-600" : "border-muted-foreground/40")}>
                                {isTypeSelected && <Check className="h-2.5 w-2.5 text-white" />}
                              </span>
                              <span className="flex-1 truncate">{t.nom}</span>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
          <div className="flex items-center justify-between gap-2 border-t border-border px-3 py-2">
            <button
              type="button"
              onClick={() => { setStagedCat([]); setStagedType([]); }}
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
