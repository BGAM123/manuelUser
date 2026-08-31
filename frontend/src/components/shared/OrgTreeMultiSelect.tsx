/**
 * Sélecteur multi-nœuds sous forme d'organigramme dépliable (popover +
 * recherche) — mêmes données que OrgTreeSelect (GET /organigramme), mais en
 * sélection MULTIPLE par cases à cocher. `selectableType` (optionnel)
 * restreint les nœuds cochables à un type précis (ex: "Poste" — les nœuds
 * Service/Direction ne servent alors qu'à déplier, même principe que
 * OrgTreeSelect) ; omis, n'importe quel nœud est cochable.
 *
 * `deferApply` (opt-in, défaut false) — pour les FILTRES qui déclenchent un
 * rechargement à chaque changement : les cases cochées ne sont appliquées
 * (onChange) qu'au clic sur "Appliquer", pas à chaque case cochée
 * individuellement (ergonomie demandée explicitement, 2026-08-27).
 */
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Check, ChevronRight, ChevronsUpDown, Search, X } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
import { cn } from "@/utils/utils";
import { getOrganigramme, type ApiOrgNode } from "@/api/services/services.api";
import { formatPosteLabel } from "@/components/shared/OrgTreeSelect";

interface OrgTreeMultiSelectProps {
  value: number[];
  onChange: (ids: number[]) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  disabled?: boolean;
  /** Restreint les nœuds cochables à ce type (ex: "Poste") — omis = tout nœud cochable. */
  selectableType?: string;
  /** Voir en-tête du fichier — n'applique les changements qu'au clic sur "Appliquer". */
  deferApply?: boolean;
  /**
   * Restreint l'arbre affiché (et la recherche) au sous-arbre de ce service
   * (lui-même + descendants) — utilisé pour les utilisateurs non-admin dont
   * les statistiques sont scopées côté backend à leur propre service : évite
   * de leur proposer des nœuds hors de leur périmètre qui ne renverraient
   * silencieusement aucune donnée. La vraie barrière de sécurité reste côté
   * serveur ; ceci n'est qu'un confort UI.
   */
  rootServiceId?: number;
}

function collectSelectable(nodes: ApiOrgNode[], type?: string): ApiOrgNode[] {
  return nodes.flatMap((n) => {
    const kids = collectSelectable(n.children, type);
    return !type || n.type_service === type ? [n, ...kids] : kids;
  });
}

function collectIds(nodes: ApiOrgNode[]): number[] {
  return nodes.flatMap((n) => [n.id, ...collectIds(n.children)]);
}

function findNode(nodes: ApiOrgNode[], id: number): ApiOrgNode | null {
  for (const n of nodes) {
    if (n.id === id) return n;
    const found = findNode(n.children, id);
    if (found) return found;
  }
  return null;
}

export function OrgTreeMultiSelect({
  value,
  onChange,
  placeholder = "Toutes les structures",
  searchPlaceholder = "Rechercher...",
  disabled = false,
  selectableType,
  deferApply = false,
  rootServiceId,
}: OrgTreeMultiSelectProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [openNodes, setOpenNodes] = useState<Set<number>>(new Set());
  const [staged, setStaged] = useState<number[]>(value);

  useEffect(() => {
    if (open) setStaged(value);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  // Arbre complet — pas de filtre type_service ici, sinon les nœuds
  // intermédiaires (qui servent à déplier jusqu'aux nœuds cochables)
  // disparaîtraient et l'arbre deviendrait une liste à plat.
  const { data: treeData, isLoading: treeLoading } = useQuery({
    queryKey: ["organigramme"],
    queryFn: () => getOrganigramme(),
    staleTime: 300_000,
  });
  const fullTree: ApiOrgNode[] = useMemo(() => treeData?.data?.data ?? [], [treeData]);
  // Sous-arbre restreint (rootServiceId) — le nœud lui-même est inclus (un
  // "gestionnaire de structure" doit pouvoir se sélectionner lui-même, pas
  // seulement ses sous-services), pas juste ses enfants.
  const tree: ApiOrgNode[] = useMemo(() => {
    if (rootServiceId == null) return fullTree;
    const root = findNode(fullTree, rootServiceId);
    return root ? [root] : [];
  }, [fullTree, rootServiceId]);
  const allSelectable = useMemo(() => collectSelectable(tree, selectableType), [tree, selectableType]);
  const allowedIds = useMemo(
    () => (rootServiceId != null ? new Set(collectIds(tree)) : null),
    [rootServiceId, tree],
  );

  const [debouncedSearch, setDebouncedSearch] = useState("");
  useEffect(() => {
    const h = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(h);
  }, [search]);
  const isServerSearching = debouncedSearch.length >= 2;

  const { data: searchData, isFetching: isSearchLoading } = useQuery({
    queryKey: ["organigramme", "multiselect-search", debouncedSearch, selectableType],
    queryFn: () => getOrganigramme({ search: debouncedSearch, typeService: selectableType }),
    enabled: isServerSearching,
    staleTime: 30_000,
  });
  const searchResults: ApiOrgNode[] = useMemo(() => {
    const results = searchData?.data?.data ?? [];
    return allowedIds ? results.filter((n) => allowedIds.has(n.id)) : results;
  }, [searchData, allowedIds]);

  // Étiquettes des éléments déjà APPLIQUÉS, pour le résumé du trigger.
  const selectedLabels = useMemo(() => {
    const byId = new Map(allSelectable.map((p) => [p.id, p]));
    return value.map((id) => {
      const node = byId.get(id);
      return node ? (node.sigle ? `${node.sigle} — ${node.nom}` : node.nom) : `#${id}`;
    });
  }, [value, allSelectable]);

  const active = deferApply ? staged : value;

  const toggle = (id: number) => {
    const next = active.includes(id) ? active.filter((v) => v !== id) : [...active, id];
    if (deferApply) setStaged(next);
    else onChange(next);
  };
  const apply = () => { onChange(staged); setOpen(false); };
  const clearApplied = () => onChange([]);

  const toggleOpen = (id: number) =>
    setOpenNodes((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const handleSearchChange = (v: string) => {
    setSearch(v);
    if (v.trim()) setOpenNodes(new Set(collectIds(tree)));
  };

  const nodeLabel = (node: ApiOrgNode) => (node.type_service === "Poste" ? formatPosteLabel(node) : node.nom);

  // Résultats de recherche serveur — liste à plat (le backend ne renvoie pas
  // leur hiérarchie), même pattern que OrgTreeSelect.renderFlatResults.
  const renderFlatResults = (nodes: ApiOrgNode[]) =>
    nodes.map((node) => {
      const isSelected = active.includes(node.id);
      return (
        <button
          key={node.id}
          type="button"
          onClick={() => toggle(node.id)}
          className={cn(
            "flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60",
            isSelected && "bg-primary/10 text-primary",
          )}
        >
          <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition-colors", isSelected ? "border-primary bg-primary" : "border-muted-foreground/40")}>
            {isSelected && <Check className="h-3 w-3 text-white" />}
          </span>
          <span className="min-w-0 flex-1 truncate text-left">{nodeLabel(node)}</span>
          {node.parent_id?.nom && (
            <span className="shrink-0 truncate text-[11px] text-muted-foreground">{node.parent_id.nom}</span>
          )}
        </button>
      );
    });

  // Arbre complet — seuls les nœuds correspondant à selectableType (ou tous,
  // si omis) sont cochables ; les autres ne servent qu'à déplier.
  const renderTree = (nodes: ApiOrgNode[], depth: number): React.ReactNode =>
    nodes.map((node) => {
      const hasKids = node.children.length > 0;
      const canSelect = !selectableType || node.type_service === selectableType;
      const isOpen = search.trim() ? true : openNodes.has(node.id);
      const isSelected = active.includes(node.id);
      return (
        <div key={node.id}>
          <div
            role="button"
            tabIndex={0}
            onClick={() => (canSelect ? toggle(node.id) : hasKids && toggleOpen(node.id))}
            onKeyDown={(e) => {
              if (e.key !== "Enter") return;
              if (canSelect) toggle(node.id);
              else if (hasKids) toggleOpen(node.id);
            }}
            style={{ paddingLeft: `${12 + depth * 14}px` }}
            className={cn(
              "flex w-full items-center gap-2 pr-3 py-1.5 text-sm transition-colors hover:bg-primary/8",
              (canSelect || hasKids) && "cursor-pointer",
              hasKids && "font-medium text-foreground",
              isSelected && "bg-primary/10 text-primary",
            )}
          >
            {hasKids ? (
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); toggleOpen(node.id); }}
                className="-ml-0.5 shrink-0 rounded p-0.5 hover:bg-muted"
              >
                <ChevronRight className={cn("h-3.5 w-3.5 text-muted-foreground transition-transform", isOpen && "rotate-90")} />
              </button>
            ) : (
              <span className="h-4 w-4 shrink-0" />
            )}
            {canSelect && (
              <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition-colors", isSelected ? "border-primary bg-primary" : "border-muted-foreground/40")}>
                {isSelected && <Check className="h-3 w-3 text-white" />}
              </span>
            )}
            <span className="flex-1 truncate text-left">{canSelect ? nodeLabel(node) : node.nom}</span>
            {node.sigle && <code className="rounded bg-muted px-1 py-0.5 text-[11px] font-mono">{node.sigle}</code>}
          </div>
          {hasKids && isOpen && <div>{renderTree(node.children, depth + 1)}</div>}
        </div>
      );
    });

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          className={cn(
            "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60",
            value.length === 0 && "text-muted-foreground",
          )}
        >
          <span className="flex-1 truncate text-left">
            {value.length === 0
              ? placeholder
              : value.length === 1
                ? selectedLabels[0]
                : `${value.length} sélectionnés`}
          </span>
          <div className="flex shrink-0 items-center gap-1">
            {value.length > 0 && (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => { e.stopPropagation(); clearApplied(); }}
                onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), clearApplied())}
                className="rounded p-0.5 hover:bg-muted"
              >
                <X className="h-3.5 w-3.5 text-muted-foreground" />
              </span>
            )}
            <ChevronsUpDown className="h-4 w-4 text-muted-foreground" />
          </div>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[--radix-popover-trigger-width] min-w-[320px] p-0 shadow-lg" align="start" sideOffset={4}>
        <div className="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder={searchPlaceholder}
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
          {search && (
            <button type="button" onClick={() => setSearch("")}>
              <X className="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          )}
        </div>
        <div className="max-h-72 overflow-y-auto py-1">
          {isServerSearching ? (
            isSearchLoading ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Recherche…</p>
            ) : searchResults.length === 0 ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              renderFlatResults(searchResults)
            )
          ) : treeLoading ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Chargement…</p>
          ) : tree.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucune structure disponible</p>
          ) : (
            renderTree(tree, 0)
          )}
        </div>
        {deferApply ? (
          <div className="flex items-center justify-between gap-2 border-t border-border px-3 py-2">
            <button
              type="button"
              onClick={() => setStaged([])}
              disabled={staged.length === 0}
              className="text-xs text-muted-foreground hover:text-destructive disabled:opacity-40"
            >
              Effacer
            </button>
            <Button type="button" size="sm" className="h-7 text-xs" onClick={apply}>
              Appliquer{staged.length > 0 ? ` (${staged.length})` : ""}
            </Button>
          </div>
        ) : (
          value.length > 0 && (
            <div className="border-t border-border px-3 py-2">
              <button type="button" onClick={clearApplied} className="text-xs text-muted-foreground hover:text-destructive">
                Effacer la sélection ({value.length})
              </button>
            </div>
          )
        )}
      </PopoverContent>
    </Popover>
  );
}
