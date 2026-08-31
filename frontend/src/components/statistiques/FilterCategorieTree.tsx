/**
 * FilterCategorieTree — Sélecteur Catégorie + Type de bien sous forme d'arbre.
 *
 * - "Type de bien" n'est plus un select séparé.
 * - Chaque Catégorie peut être dépliée pour révéler ses Types imbriqués.
 * - Barre de recherche unique : filtre catégories ET types en temps réel.
 *   Si un type correspond, sa catégorie parente s'ouvre automatiquement.
 * - Sélection possible au niveau catégorie (parent) ou type (enfant).
 */
import React, { useState, useMemo } from "react";
import { ChevronRight, Search, Layers, Tag, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { cn } from "@/utils/utils";
import { useCategorieHierarchie } from "@/hooks/useFilterReferentiel";
import type { CatTypeNode } from "@/hooks/useFilterReferentiel";

export type CategorySelection =
  | { type: "all" }
  | { type: "categorie"; id: number; nom: string }
  | { type: "assetType"; id: number; nom: string; categorieId: number; categorieNom: string };

interface FilterCategorieTreeProps {
  selection: CategorySelection;
  onSelect: (sel: CategorySelection) => void;
}

// ── Vérifie si un nœud correspond à la recherche ──────────────────────────
function catMatchesSearch(node: CatTypeNode, term: string): boolean {
  const t = term.toLowerCase();
  if (node.categorie.nom.toLowerCase().includes(t)) return true;
  return node.types.some((tp) => tp.nom.toLowerCase().includes(t));
}

// ── Label affiché dans le trigger ─────────────────────────────────────────
function selectionLabel(sel: CategorySelection): string {
  if (sel.type === "all") return "Toutes les catégories";
  if (sel.type === "categorie") return sel.nom;
  return `${sel.categorieNom} › ${sel.nom}`;
}

// ── Ligne catégorie (parent) ───────────────────────────────────────────────
function CatRow({
  node,
  selection,
  onSelect,
  search,
  forceOpen,
  onCloseDropdown,
}: {
  node: CatTypeNode;
  selection: CategorySelection;
  onSelect: (sel: CategorySelection) => void;
  search: string;
  forceOpen: boolean;
  onCloseDropdown: () => void;
}) {
  const [open, setOpen] = useState(false);
  const isOpen = forceOpen || open;

  const isCatSelected =
    selection.type === "categorie" && selection.id === node.categorie.id;
  const isChildSelected =
    selection.type === "assetType" && selection.categorieId === node.categorie.id;

  // Filtrer les types enfants
  const visibleTypes = useMemo(() => {
    if (!search) return node.types;
    const t = search.toLowerCase();
    return node.types.filter((tp) => tp.nom.toLowerCase().includes(t));
  }, [node.types, search]);

  // Masquer si aucune correspondance en mode recherche
  if (search && !catMatchesSearch(node, search)) return null;

  const hasSub = node.types.length > 0;

  return (
    <div>
      {/* Ligne catégorie */}
      <div
        className={cn(
          "flex items-center gap-1.5 rounded-lg px-2 py-1.5 cursor-pointer transition-colors text-xs group",
          isCatSelected || isChildSelected
            ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300"
            : "hover:bg-muted/60 text-foreground",
        )}
        onClick={() => {
          onSelect({ type: "categorie", id: node.categorie.id, nom: node.categorie.nom });
          onCloseDropdown();
        }}
      >
        {hasSub ? (
          <button
            type="button"
            className="shrink-0 p-0.5 rounded hover:bg-muted"
            onClick={(e) => { e.stopPropagation(); setOpen((v) => !v); }}
          >
            <ChevronRight
              className={cn("h-3 w-3 transition-transform duration-200 text-muted-foreground", isOpen && "rotate-90")}
            />
          </button>
        ) : (
          <span className="h-4 w-4 shrink-0" />
        )}
        <Layers className={cn("h-3.5 w-3.5 shrink-0", isCatSelected ? "text-emerald-600" : "text-muted-foreground")} />
        <span className="flex-1 truncate font-semibold leading-tight">{node.categorie.nom}</span>
        {hasSub && (
          <span className="shrink-0 text-[9px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded-full">
            {node.types.length}
          </span>
        )}
      </div>

      {/* Types imbriqués */}
      {hasSub && isOpen && (
        <div className="ml-2 pl-2 border-l border-border/60">
          {visibleTypes.map((tp) => {
            const isTypeSelected =
              selection.type === "assetType" && selection.id === tp.id;
            return (
              <div
                key={tp.id}
                className={cn(
                  "flex items-center gap-1.5 rounded-lg px-2 py-1 cursor-pointer transition-colors text-xs",
                  isTypeSelected
                    ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300"
                    : "hover:bg-muted/50 text-foreground/80",
                )}
                onClick={() => {
                  onSelect({
                    type: "assetType",
                    id: tp.id,
                    nom: tp.nom,
                    categorieId: node.categorie.id,
                    categorieNom: node.categorie.nom,
                  });
                  onCloseDropdown();
                }}
              >
                <span className="h-4 w-4 shrink-0" />
                <Tag className={cn("h-3 w-3 shrink-0", isTypeSelected ? "text-emerald-600" : "text-muted-foreground")} />
                <span className="flex-1 truncate">{tp.nom}</span>
                {isTypeSelected && (
                  <span className="shrink-0 text-[9px] font-bold bg-emerald-600 text-white px-1.5 py-0.5 rounded-full">✓</span>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

// ── Composant principal ────────────────────────────────────────────────────
export function FilterCategorieTree({ selection, onSelect }: FilterCategorieTreeProps) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const { data: tree = [], isLoading } = useCategorieHierarchie();

  const forceOpen = search.trim().length > 0;

  const visibleNodes = useMemo(() => {
    if (!search) return tree;
    return tree.filter((n) => catMatchesSearch(n, search));
  }, [tree, search]);

  const isActive = selection.type !== "all";

  return (
    <div className="relative">
      {/* Trigger */}
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
          open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
          isActive ? "text-foreground font-medium" : "text-muted-foreground",
        )}
      >
        <Layers className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
        <span className="flex-1 truncate text-left max-w-[180px]">
          {isLoading ? "Chargement…" : selectionLabel(selection)}
        </span>
        {isActive && (
          <X
            className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
            onClick={(e) => {
              e.stopPropagation();
              onSelect({ type: "all" });
              setOpen(false);
            }}
          />
        )}
        <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", open && "rotate-90")} />
      </button>

      {/* Dropdown */}
      {open && (
        <div className="absolute z-50 mt-1 w-[300px] rounded-xl border border-border bg-card shadow-lg">
          {/* Barre de recherche */}
          <div className="p-2 border-b border-border">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                autoFocus
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Rechercher catégorie ou type…"
                className="h-7 pl-8 text-xs bg-muted/30"
              />
              {search && (
                <button type="button" onClick={() => setSearch("")} className="absolute right-2 top-1.5">
                  <X className="h-3.5 w-3.5 text-muted-foreground" />
                </button>
              )}
            </div>
          </div>

          {/* Option "Toutes les catégories" */}
          <div
            className={cn(
              "flex items-center gap-2 px-3 py-2 text-xs cursor-pointer transition-colors",
              selection.type === "all"
                ? "bg-emerald-50 text-emerald-900 dark:bg-emerald-950/50 font-bold"
                : "hover:bg-muted/60 text-muted-foreground",
            )}
            onClick={() => { onSelect({ type: "all" }); setOpen(false); }}
          >
            <Layers className="h-3.5 w-3.5" />
            Toutes les catégories
          </div>

          {/* Arbre catégories → types */}
          <div className="max-h-72 overflow-y-auto py-1 px-1">
            {isLoading ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Chargement…</p>
            ) : visibleNodes.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              visibleNodes.map((node) => (
                <CatRow
                  key={node.categorie.id}
                  node={node}
                  selection={selection}
                  onSelect={onSelect}
                  search={search}
                  forceOpen={forceOpen}
                  onCloseDropdown={() => setOpen(false)}
                />
              ))
            )}
          </div>
        </div>
      )}

      {/* Overlay */}
      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />}
    </div>
  );
}
