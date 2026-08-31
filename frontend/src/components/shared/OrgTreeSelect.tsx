/**
 * Sélecteur de structure sous forme d'organigramme (popover + recherche).
 *
 * Deux modes :
 *   - Général (selectableType absent) : seuls les noeuds sans enfant sont
 *     sélectionnables, les noeuds avec enfants ne servent qu'à déplier.
 *   - Restreint à un type (ex: selectableType="Poste") : seuls les noeuds
 *     dont type_service correspond sont sélectionnables (radio), les autres
 *     noeuds (typiquement "Service") ne servent qu'à déplier leurs enfants.
 *
 * Par défaut, l'étiquette d'un noeud "Poste" est formatée
 * "Nom du poste: Matricule - Prénom Nom" (ou "Vacant" si aucun utilisateur
 * n'y est rattaché) — voir formatPosteLabel, exporté pour être réutilisé.
 */
import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronRight, Search, X } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/utils/utils";
import { getOrganigramme, type ApiOrgNode } from "@/api/services/services.api";

export function formatPosteLabel(node: ApiOrgNode): string {
  if (node.type_service !== "Poste") return node.nom;
  if (!node.utilisateur) return `${node.nom}: Vacant`;
  const u = node.utilisateur;
  return `${node.nom}: ${u.matricule ?? "—"} - ${u.firstName} ${u.lastName}`;
}

/** Rendu enrichi de formatPosteLabel — le nom du poste ressort en gras,
 * le titulaire (matricule/nom, ou "Vacant") reste en poids normal, pour
 * éviter la confusion avec les noeuds "Service" (également en gras). */
function PosteLabel({ node }: { node: ApiOrgNode }) {
  if (node.type_service !== "Poste") return <>{node.nom}</>;
  return (
    <>
      <span className="font-semibold text-foreground">{node.nom}</span>
      {node.utilisateur ? (
        <span className="font-normal text-muted-foreground">
          {": "}{node.utilisateur.matricule ?? "—"} - {node.utilisateur.firstName} {node.utilisateur.lastName}
        </span>
      ) : (
        <span className="font-normal text-muted-foreground">: Vacant</span>
      )}
    </>
  );
}

function collectIds(nodes: ApiOrgNode[]): number[] {
  return nodes.flatMap((n) => [n.id, ...collectIds(n.children)]);
}

function filterTree(nodes: ApiOrgNode[], q: string): ApiOrgNode[] {
  if (!q) return nodes;
  return nodes.reduce<ApiOrgNode[]>((acc, n) => {
    const kids = filterTree(n.children, q);
    if (n.nom.toLowerCase().includes(q) || (n.sigle ?? "").toLowerCase().includes(q) || kids.length > 0) {
      acc.push({ ...n, children: kids });
    }
    return acc;
  }, []);
}

export function OrgTreeSelect({
  value,
  valueLabel,
  onSelect,
  onClear,
  selectableType,
  selectAnyNode = false,
  formatNodeLabel,
  placeholder = "Sélectionner une structure",
  searchPlaceholder = "Rechercher une structure...",
  widthClass = "w-[380px]",
  disabled = false,
}: {
  value: number | null;
  valueLabel: string;
  onSelect: (node: ApiOrgNode) => void;
  onClear: () => void;
  /** Type de structure sélectionnable (ex: "Poste"). Si absent, seuls les noeuds feuilles sont sélectionnables. */
  selectableType?: string;
  /** Autorise à sélectionner N'IMPORTE quel noeud (Service ou Poste), y
   * compris ceux ayant des enfants — le chevron reste utilisable pour
   * déplier sans sélectionner. Utilisé pour les filtres "par service" où
   * on veut récupérer tous les utilisateurs d'un service entier. Prioritaire
   * sur `selectableType`. */
  selectAnyNode?: boolean;
  formatNodeLabel?: (node: ApiOrgNode) => React.ReactNode;
  placeholder?: string;
  searchPlaceholder?: string;
  widthClass?: string;
  disabled?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [openNodes, setOpenNodes] = useState<Set<number>>(new Set());

  const { data: orgData } = useQuery({
    queryKey: ["organigramme"],
    queryFn: () => getOrganigramme(),
    staleTime: 300_000,
  });
  const orgNodes: ApiOrgNode[] = useMemo(() => orgData?.data?.data ?? [], [orgData]);

  const q = search.trim().toLowerCase();
  const filteredNodes = useMemo(() => filterTree(orgNodes, q), [orgNodes, q]);

  // Recherche serveur (débouncée) — l'API filtre aussi sur le matricule et le
  // nom de l'utilisateur rattaché à un poste, ce que le filtrage client
  // (filterTree, sur nom/sigle uniquement) ne peut pas faire. Ne se déclenche
  // qu'à partir de 2 caractères ; renvoie une liste à plat (pas d'arbre).
  const [debouncedSearch, setDebouncedSearch] = useState("");
  useEffect(() => {
    const h = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(h);
  }, [search]);
  const isServerSearching = debouncedSearch.length >= 2;

  const { data: searchData, isFetching: isSearchLoading } = useQuery({
    queryKey: ["organigramme-search", debouncedSearch, selectableType],
    queryFn: () => getOrganigramme({ search: debouncedSearch, typeService: selectableType }),
    enabled: isServerSearching,
    staleTime: 30_000,
  });
  const searchResults: ApiOrgNode[] = useMemo(() => searchData?.data?.data ?? [], [searchData]);

  const toggleNode = (id: number) =>
    setOpenNodes((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const labelFor = (node: ApiOrgNode): React.ReactNode =>
    formatNodeLabel
      ? formatNodeLabel(node)
      : selectableType
      ? <PosteLabel node={node} />
      : node.nom;

  const handleSearchChange = (v: string) => {
    setSearch(v);
    if (v.trim()) setOpenNodes(new Set(collectIds(orgNodes)));
  };

  const handleSelect = (node: ApiOrgNode) => {
    onSelect(node);
    setOpen(false);
    setSearch("");
  };

  // Résultats de recherche serveur — liste à plat, pas d'indentation/chevron
  // puisque le backend ne renvoie que les nœuds correspondants (pas leur
  // hiérarchie). Le service parent est affiché en contexte à droite.
  const renderFlatResults = (nodes: ApiOrgNode[]): React.ReactNode =>
    nodes
      .filter((node) => !selectableType || node.type_service === selectableType)
      .map((node) => {
        const isSelected = value === node.id;
        return (
          <div
            key={node.id}
            role="button"
            tabIndex={0}
            onClick={() => handleSelect(node)}
            onKeyDown={(e) => e.key === "Enter" && handleSelect(node)}
            className={cn(
              "flex w-full cursor-pointer items-center gap-2.5 px-3 py-1.5 text-sm transition-colors hover:bg-primary/8",
              isSelected && "bg-primary/10 text-primary",
            )}
          >
            <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors", isSelected ? "border-primary bg-primary" : "border-muted-foreground/40")}>
              {isSelected && <span className="h-1.5 w-1.5 rounded-full bg-white" />}
            </span>
            <span className="min-w-0 flex-1 truncate text-left">{labelFor(node)}</span>
            {node.parent_id?.nom && (
              <span className="shrink-0 truncate text-[11px] text-muted-foreground">{node.parent_id.nom}</span>
            )}
          </div>
        );
      });

  const renderTree = (nodes: ApiOrgNode[], depth: number): React.ReactNode =>
    nodes.map((node) => {
      const hasKids = node.children.length > 0;
      const canSelect = selectAnyNode ? true : selectableType ? node.type_service === selectableType : !hasKids;
      const isOpen = q ? true : openNodes.has(node.id);
      const isSelected = value === node.id;
      return (
        <div key={node.id}>
          <div
            role="button"
            tabIndex={0}
            onClick={() => (canSelect ? handleSelect(node) : hasKids && toggleNode(node.id))}
            onKeyDown={(e) => {
              if (e.key !== "Enter") return;
              if (canSelect) handleSelect(node);
              else if (hasKids) toggleNode(node.id);
            }}
            style={{ paddingLeft: `${12 + depth * 14}px` }}
            className={cn(
              "flex w-full items-center gap-2.5 pr-3 py-1.5 text-sm transition-colors hover:bg-primary/8",
              (canSelect || hasKids) && "cursor-pointer",
              hasKids && "font-medium text-foreground",
              isSelected && "bg-primary/10 text-primary",
            )}
          >
            {hasKids ? (
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); toggleNode(node.id); }}
                className="-ml-0.5 shrink-0 rounded p-0.5 hover:bg-muted"
              >
                <ChevronRight className={cn("h-3.5 w-3.5 text-muted-foreground transition-transform", isOpen && "rotate-90")} />
              </button>
            ) : canSelect ? (
              <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors", isSelected ? "border-primary bg-primary" : "border-muted-foreground/40")}>
                {isSelected && <span className="h-1.5 w-1.5 rounded-full bg-white" />}
              </span>
            ) : (
              <span className="h-4 w-4 shrink-0" />
            )}
            <span className="flex-1 text-left">{labelFor(node)}</span>
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
            !valueLabel && "text-muted-foreground",
          )}
        >
          <span className="flex-1 truncate text-left">{valueLabel || placeholder}</span>
          <div className="flex shrink-0 items-center gap-1">
            {valueLabel && (
              <span
                role="button"
                tabIndex={0}
                onClick={(e) => { e.stopPropagation(); onClear(); }}
                onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), onClear())}
                className="rounded p-0.5 hover:bg-muted"
              >
                <X className="h-3.5 w-3.5 text-muted-foreground" />
              </span>
            )}
            <ChevronRight className={cn("h-4 w-4 text-muted-foreground transition-transform", open && "rotate-90")} />
          </div>
        </button>
      </PopoverTrigger>
      <PopoverContent className={cn(widthClass, "p-0 shadow-lg")} align="start" sideOffset={4}>
        <div className="flex items-center gap-2 border-b border-border px-3 py-2">
          <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
          <input
            autoFocus
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder={searchPlaceholder}
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
          />
          {search && <button type="button" onClick={() => setSearch("")}><X className="h-3.5 w-3.5 text-muted-foreground" /></button>}
        </div>
        <div className="max-h-64 overflow-y-auto py-1">
          {isServerSearching ? (
            isSearchLoading ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Recherche…</p>
            ) : searchResults.length === 0 ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
            ) : (
              renderFlatResults(searchResults)
            )
          ) : orgNodes.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucune structure disponible</p>
          ) : filteredNodes.length === 0 ? (
            <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat</p>
          ) : renderTree(filteredNodes, 0)}
        </div>
        {valueLabel && (
          <div className="border-t border-border px-3 py-2">
            <button type="button" onClick={() => { onClear(); setOpen(false); }} className="text-xs text-muted-foreground hover:text-destructive">
              Effacer la sélection
            </button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}
