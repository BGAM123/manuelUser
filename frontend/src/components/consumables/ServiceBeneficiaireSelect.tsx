/**
 * ServiceBeneficiaireSelect — organigramme navigable.
 *
 * N'utilise PAS Popover/Radix car ce composant vit dans un Dialog :
 * Radix ferme le Popover à chaque clic interne quand il est imbriqué dans
 * un autre portal. On utilise un dropdown custom (div positionné) à la place.
 *
 * Navigation :
 * - Clic sur le nom d'un service avec enfants → déplier uniquement
 * - Clic sur le nom d'un service sans enfants → sélectionner + fermer
 * - Bouton ✓ (hover) → confirmer la sélection du service + fermer
 * - Clic sur un matricule → sélectionner + fermer
 */

import { useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Search, User, X } from "lucide-react";
import { cn } from "@/utils/utils";
import { getOrganigramme, type ApiOrgNode } from "@/api/services/services.api";
import { listAllUsers, type ApiUser } from "@/api/users/users.api";

export interface ServiceBeneficiaireValue {
  serviceId: number | null;
  beneficiaireId: number | null;
  label: string;
}

export const emptyServiceBeneficiaire: ServiceBeneficiaireValue = {
  serviceId: null,
  beneficiaireId: null,
  label: "",
};

export function ServiceBeneficiaireSelect({
  value,
  onChange,
  placeholder = "Dépliez l'organigramme jusqu'à une personne",
}: {
  value: ServiceBeneficiaireValue;
  onChange: (next: ServiceBeneficiaireValue) => void;
  placeholder?: string;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [openNodes, setOpenNodes] = useState<Set<number>>(new Set());
  const containerRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  // Fermer si clic en dehors du composant
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
        setSearch("");
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, [open]);

  // Focus sur la recherche à l'ouverture
  useEffect(() => {
    if (open) setTimeout(() => searchRef.current?.focus(), 50);
  }, [open]);

  const { data: orgData } = useQuery({
    queryKey: ["organigramme"],
    queryFn: () => getOrganigramme(),
    staleTime: 300_000,
  });
  const orgNodes: ApiOrgNode[] = useMemo(() => orgData?.data?.data ?? [], [orgData]);

  const { data: allUsers = [] } = useQuery({
    queryKey: ["users-all"],
    queryFn: listAllUsers,
    staleTime: 300_000,
  });

  const usersByService = useMemo(() => {
    const map = new Map<number, ApiUser[]>();
    for (const u of allUsers) {
      if (!u.service) continue;
      const list = map.get(u.service.id) ?? [];
      list.push(u);
      map.set(u.service.id, list);
    }
    return map;
  }, [allUsers]);

  const q = search.trim().toLowerCase();
  const matchesQuery = (text: string) => !q || text.toLowerCase().includes(q);

  // Trouve le nom d'un service par ID dans l'arbre
  const findServiceNom = (nodes: ApiOrgNode[], id: number | null): string | null => {
    if (id == null) return null;
    for (const n of nodes) {
      if (n.id === id) return n.nom;
      const found = findServiceNom(n.children ?? [], id);
      if (found) return found;
    }
    return null;
  };

  const filterTree = (nodes: ApiOrgNode[]): ApiOrgNode[] =>
    nodes.reduce<ApiOrgNode[]>((acc, n) => {
      const kids = filterTree(n.children);
      const ownUsers = usersByService.get(n.id) ?? [];
      const userMatch = ownUsers.some((u) => matchesQuery(`${u.firstName} ${u.lastName} ${u.matricule ?? ""}`));
      if (matchesQuery(n.nom) || matchesQuery(n.sigle ?? "") || kids.length > 0 || userMatch) {
        acc.push({ ...n, children: kids });
      }
      return acc;
    }, []);

  const filteredOrgNodes = q ? filterTree(orgNodes) : orgNodes;

  const toggleNode = (id: number) =>
    setOpenNodes((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const confirmService = (node: ApiOrgNode) => {
    onChange({ serviceId: node.id, beneficiaireId: null, label: node.nom });
    setOpen(false);
    setSearch("");
  };

  const pickUser = (u: ApiUser, serviceId: number | null) => {
    const serviceNom = orgNodes.length > 0 ? findServiceNom(orgNodes, serviceId) : null;
    // Même syntaxe que formatPosteLabel ("Poste : Matricule - Prénom Nom"),
    // uniformisée sur l'ensemble des sélecteurs de structure/personne.
    const label = `${serviceNom ?? "—"}: ${u.matricule ?? "—"} - ${u.firstName} ${u.lastName}`;
    onChange({ serviceId, beneficiaireId: u.id, label });
    setOpen(false);
    setSearch("");
  };

  const renderTree = (nodes: ApiOrgNode[], depth = 0): React.ReactNode =>
    nodes.map((node) => {
      const nodeUsers = (usersByService.get(node.id) ?? []).filter((u) =>
        matchesQuery(`${u.firstName} ${u.lastName} ${u.matricule ?? ""}`),
      );
      const hasChildren = node.children.length > 0 || nodeUsers.length > 0;
      const isExpanded = q ? true : openNodes.has(node.id);
      // Un service ne peut plus être sélectionné — seules les personnes le peuvent
      const isSelected = false;

      return (
        <div key={`svc-${node.id}`}>
          <div
            style={{ paddingLeft: `${8 + depth * 16}px` }}
            className={cn(
              "group flex w-full items-center gap-1 pr-2 py-1.5 text-sm",
              isSelected ? "bg-primary/10 text-primary" : "hover:bg-muted/60",
            )}
          >
            {/* Chevron — toujours présent, invisible si pas d'enfants */}
            <button
              type="button"
              onMouseDown={(e) => e.preventDefault()} // évite blur sur input recherche
              onClick={() => toggleNode(node.id)}
              className={cn(
                "flex h-5 w-5 shrink-0 items-center justify-center rounded hover:bg-muted",
                !hasChildren && "invisible pointer-events-none",
              )}
            >
              <ChevronRight
                className={cn(
                  "h-3.5 w-3.5 text-muted-foreground transition-transform duration-150",
                  isExpanded && "rotate-90",
                )}
              />
            </button>

            {/* Nom du service */}
            <button
              type="button"
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => {
                // On ne peut sélectionner QUE des personnes — un service ne
                // peut pas être sélectionné directement (feuille ou non).
                // Clic sur le nom = toujours déplier/replier.
                toggleNode(node.id);
              }}
              className={cn(
                "flex-1 truncate text-left text-sm",
                hasChildren ? "font-medium" : "font-normal text-muted-foreground",
              )}
            >
              {node.nom}
            </button>

            {/* Sigle */}
            {node.sigle && (
              <code className="shrink-0 rounded bg-muted px-1 py-0.5 text-[10px] font-mono text-muted-foreground">
                {node.sigle}
              </code>
            )}
            {/* Bouton ✓ retiré — sélection uniquement via les personnes */}
          </div>

          {/* Enfants */}
          {hasChildren && isExpanded && (
            <div>
              {renderTree(node.children, depth + 1)}
              {nodeUsers.map((u) => {
                const isSelUser = value.beneficiaireId === u.id;
                return (
                  <div
                    key={`usr-${u.id}`}
                    style={{ paddingLeft: `${8 + (depth + 1) * 16 + 20}px` }}
                    className={cn(
                      "flex w-full cursor-pointer items-center gap-2 pr-3 py-1.5 text-sm",
                      isSelUser
                        ? "bg-primary/10 text-primary"
                        : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                    )}
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => pickUser(u, node.id)}
                  >
                    <User className="h-3.5 w-3.5 shrink-0" />
                    <span className="flex-1 truncate">{u.firstName} {u.lastName}{u.matricule ? ` (${u.matricule})` : ""}</span>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      );
    });

  return (
    <div ref={containerRef} className="relative w-full">
      {/* Trigger */}
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className={cn(
          "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring",
          !value.label && "text-muted-foreground",
        )}
      >
        <span className="flex-1 truncate text-left">{value.label || placeholder}</span>
        <div className="flex shrink-0 items-center gap-1">
          {value.label && (
            <span
              role="button"
              tabIndex={0}
              onMouseDown={(e) => e.stopPropagation()}
              onClick={(e) => {
                e.stopPropagation();
                onChange(emptyServiceBeneficiaire);
              }}
              className="rounded p-0.5 hover:bg-muted"
            >
              <X className="h-3.5 w-3.5 text-muted-foreground" />
            </span>
          )}
          <ChevronDown
            className={cn(
              "h-4 w-4 text-muted-foreground transition-transform duration-150",
              open && "rotate-180",
            )}
          />
        </div>
      </button>

      {/* Dropdown custom — pas de Radix Popover pour éviter le conflit Dialog */}
      {open && (
        <div className="absolute left-0 top-full z-50 mt-1 w-full min-w-[320px] rounded-md border border-border bg-background shadow-lg">
          {/* Recherche */}
          <div className="flex items-center gap-2 border-b border-border px-3 py-2">
            <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
            <input
              ref={searchRef}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Rechercher un service, un nom ou un matricule..."
              className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
            {search && (
              <button type="button" onClick={() => setSearch("")} className="text-muted-foreground hover:text-foreground">
                <X className="h-3.5 w-3.5" />
              </button>
            )}
          </div>

          {/* Arbre scrollable */}
          <div
            className="overflow-y-auto py-1"
            style={{ maxHeight: "260px" }}
          >
            {orgNodes.length === 0 ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Chargement…</p>
            ) : filteredOrgNodes.length === 0 ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">Aucun résultat pour « {search} »</p>
            ) : (
              renderTree(filteredOrgNodes)
            )}
          </div>

          {/* Footer retiré — confirmation se fait directement au clic sur une personne */}
        </div>
      )}
    </div>
  );
}
