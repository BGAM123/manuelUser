/**
 * FilterOrganigramme — Sélecteur organigramme sous forme d'arbre dépliable.
 * Barre de recherche en temps réel sur les noms et sigles.
 * Sélection d'un nœud → organigramme_service_id transmis au filtre.
 */
import React, { useState, useMemo } from "react";
import { ChevronRight, Search, Building2, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { cn } from "@/utils/utils";
import { useOrganigrammeTree } from "@/hooks/useFilterReferentiel";
import type { OrgNode } from "@/hooks/useFilterReferentiel";

interface FilterOrganigrammeProps {
  selectedId: number | null;
  onSelect: (id: number | null, label: string) => void;
}

// ── Recherche récursive dans un nœud ──────────────────────────────────────
function nodeMatchesSearch(node: OrgNode, term: string): boolean {
  const t = term.toLowerCase();
  if (node.nom.toLowerCase().includes(t)) return true;
  if (node.sigle && node.sigle.toLowerCase().includes(t)) return true;
  return node.children.some((c) => nodeMatchesSearch(c, term));
}

// ── Nœud de l'arbre ───────────────────────────────────────────────────────
function OrgTreeNode({
  node,
  level,
  selectedId,
  onSelect,
  searchTerm,
  forceOpen,
}: {
  node: OrgNode;
  level: number;
  selectedId: number | null;
  onSelect: (id: number | null, label: string) => void;
  searchTerm: string;
  forceOpen: boolean;
}) {
  const hasChildren = node.children.length > 0;
  const [open, setOpen] = useState(false);
  const isOpen = forceOpen || open;
  const isSelected = selectedId === node.id;
  const label = node.sigle ? `${node.sigle} — ${node.nom}` : node.nom;

  // Filtrer les enfants qui correspondent
  const visibleChildren = searchTerm
    ? node.children.filter((c) => nodeMatchesSearch(c, searchTerm))
    : node.children;

  if (searchTerm && !nodeMatchesSearch(node, searchTerm)) return null;

  return (
    <div>
      <div
        className={cn(
          "flex items-center gap-1.5 rounded-lg px-2 py-1.5 cursor-pointer transition-colors group text-xs",
          isSelected
            ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300"
            : "hover:bg-muted/60 text-foreground",
        )}
        style={{ paddingLeft: `${8 + level * 14}px` }}
        onClick={() => onSelect(isSelected ? null : node.id, label)}
      >
        {/* Chevron dépliage */}
        {hasChildren ? (
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

        <Building2 className={cn("h-3.5 w-3.5 shrink-0", isSelected ? "text-emerald-600" : "text-muted-foreground")} />

        <span className="flex-1 truncate font-medium leading-tight">{label}</span>

        {isSelected && (
          <span className="shrink-0 text-[9px] font-bold bg-emerald-600 text-white px-1.5 py-0.5 rounded-full">
            Sélectionné
          </span>
        )}
      </div>

      {/* Enfants */}
      {hasChildren && isOpen && (
        <div>
          {visibleChildren.map((child) => (
            <OrgTreeNode
              key={child.id}
              node={child}
              level={level + 1}
              selectedId={selectedId}
              onSelect={onSelect}
              searchTerm={searchTerm}
              forceOpen={forceOpen}
            />
          ))}
        </div>
      )}
    </div>
  );
}

// ── Composant principal ────────────────────────────────────────────────────
export function FilterOrganigramme({ selectedId, onSelect }: FilterOrganigrammeProps) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const { data: tree = [], isLoading } = useOrganigrammeTree();

  const forceOpen = search.trim().length > 0;

  const visibleRoots = useMemo(() => {
    if (!search) return tree;
    return tree.filter((n) => nodeMatchesSearch(n, search));
  }, [tree, search]);

  // Label affiché dans le trigger
  const selectedLabel = useMemo(() => {
    function findLabel(nodes: OrgNode[]): string | null {
      for (const n of nodes) {
        if (n.id === selectedId) return n.sigle ? `${n.sigle} — ${n.nom}` : n.nom;
        const found = findLabel(n.children);
        if (found) return found;
      }
      return null;
    }
    if (!selectedId) return null;
    return findLabel(tree);
  }, [selectedId, tree]);

  return (
    <div className="relative">
      {/* Trigger */}
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-9 w-full items-center gap-2 rounded-lg border px-3 text-xs transition-colors bg-background",
          open ? "border-emerald-500 ring-1 ring-emerald-500/30" : "border-input hover:border-emerald-400",
          selectedId ? "text-foreground font-medium" : "text-muted-foreground",
        )}
      >
        <Building2 className="h-3.5 w-3.5 shrink-0 text-emerald-600" />
        <span className="flex-1 truncate text-left">
          {isLoading ? "Chargement…" : selectedLabel ?? "Toutes les structures"}
        </span>
        {selectedId && (
          <X
            className="h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground"
            onClick={(e) => { e.stopPropagation(); onSelect(null, ""); setOpen(false); }}
          />
        )}
        <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", open && "rotate-90")} />
      </button>

      {/* Dropdown */}
      {open && (
        <div className="absolute z-50 mt-1 w-full min-w-[260px] rounded-xl border border-border bg-card shadow-lg">
          {/* Barre de recherche */}
          <div className="p-2 border-b border-border">
            <div className="relative">
              <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                autoFocus
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Rechercher une structure…"
                className="h-7 pl-8 text-xs bg-muted/30"
              />
              {search && (
                <button type="button" onClick={() => setSearch("")} className="absolute right-2 top-1.5">
                  <X className="h-3.5 w-3.5 text-muted-foreground" />
                </button>
              )}
            </div>
          </div>

          {/* Option "Toutes les structures" */}
          <div
            className={cn(
              "flex items-center gap-2 px-3 py-2 text-xs cursor-pointer transition-colors",
              !selectedId ? "bg-emerald-50 text-emerald-900 dark:bg-emerald-950/50 font-bold" : "hover:bg-muted/60 text-muted-foreground",
            )}
            onClick={() => { onSelect(null, ""); setOpen(false); }}
          >
            <Building2 className="h-3.5 w-3.5" />
            Toutes les structures
          </div>

          {/* Arbre */}
          <div className="max-h-64 overflow-y-auto py-1">
            {isLoading ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Chargement…</p>
            ) : visibleRoots.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              visibleRoots.map((n) => (
                <OrgTreeNode
                  key={n.id}
                  node={n}
                  level={0}
                  selectedId={selectedId}
                  onSelect={(id, lbl) => { onSelect(id, lbl); if (id) setOpen(false); }}
                  searchTerm={search}
                  forceOpen={forceOpen}
                />
              ))
            )}
          </div>
        </div>
      )}

      {/* Overlay pour fermer */}
      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />}
    </div>
  );
}
