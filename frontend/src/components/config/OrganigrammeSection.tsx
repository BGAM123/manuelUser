/**
 * Section Organigramme - Vue arbre + Gestion CRUD.
 * Deux onglets : "Schema" (arbre hierarchique) et "Gestion" (table CRUD).
 */

import { useState, useRef, useEffect, useMemo } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus, Pencil, Trash2, Save, X, ArrowLeft, Loader2, Network,
  ChevronRight, GitBranch, LayoutGrid, Tags, Search,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import {
  Popover, PopoverContent, PopoverTrigger,
} from "@/components/ui/popover";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { cn } from "@/utils/utils";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listServices, createService, updateService, deleteService,
  getOrganigramme, listTypeOrganigrammes, getServiceById,
  createTypeOrganigramme, updateTypeOrganigramme, deleteTypeOrganigramme,
  normalizeTypeOrganigrammes,
  type ApiService, type ApiOrgNode, type ApiTypeOrganigramme,
  type CreateServicePayload, type UpdateServicePayload,
  type CreateTypeOrganigrammePayload,
} from "@/api/services/services.api";
import {
  getCartographie, type CartographieRegion,
} from "@/api/regions/regions.api";

const TYPE_OPTIONS = ["Poste", "Service"];

function getServiceType(service: ApiService | null): string {
  if (!service) return "";
  if (service.type_service) return service.type_service;
  if (service.typeService) return service.typeService;
  if (typeof service.type === "string") return service.type;
  return service.type?.nom ?? service.type?.name ?? "";
}

/** Extrait le message d'erreur renvoyé par le backend. */
function getApiError(err: unknown): string {
  const data = (err as { response?: { data?: { message?: string; detail?: string } } })?.response?.data;
  return data?.message ?? data?.detail ?? "Une erreur est survenue";
}

/** Filtre récursivement l'arbre selon une recherche textuelle. */
function filterOrgTree(nodes: ApiOrgNode[], query: string): ApiOrgNode[] {
  if (!query.trim()) return nodes;
  const q = query.toLowerCase().trim();

  function filterNode(node: ApiOrgNode): ApiOrgNode | null {
    const selfMatch =
      node.nom.toLowerCase().includes(q) ||
      (node.sigle && node.sigle.toLowerCase().includes(q)) ||
      (node.code && node.code.toLowerCase().includes(q));
    const filteredChildren = node.children
      .map(filterNode)
      .filter((n): n is ApiOrgNode => n !== null);
    if (selfMatch || filteredChildren.length > 0) {
      return { ...node, children: filteredChildren };
    }
    return null;
  }

  return nodes.map(filterNode).filter((n): n is ApiOrgNode => n !== null);
}

// ─── Types pour le dialog inline ────────────────────────────────────────────

type OrgInlineDialog =
  | { mode: "edit"; service: ApiService }
  | { mode: "create"; parentId?: number; parentNom?: string }
  | { mode: "delete"; node: ApiOrgNode }
  | null;

// ============================================================
// Vue arbre - Organigramme hierarchique (avec actions inline)
// ============================================================

const LEVEL_COLORS = [
  { bg: "bg-primary/8 border-primary/30", text: "text-primary", dot: "bg-primary" },
  { bg: "bg-blue-50 border-blue-200 dark:bg-blue-900/15 dark:border-blue-700/40", text: "text-blue-700 dark:text-blue-300", dot: "bg-blue-500" },
  { bg: "bg-emerald-50 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-700/40", text: "text-emerald-700 dark:text-emerald-300", dot: "bg-emerald-500" },
  { bg: "bg-orange-50 border-orange-200 dark:bg-orange-900/15 dark:border-orange-700/40", text: "text-orange-700 dark:text-orange-300", dot: "bg-orange-500" },
];

function OrgNodeCard({
  node,
  level = 0,
  onEdit,
  onAddChild,
  onDelete,
  forceOpen = false,
}: {
  node: ApiOrgNode;
  level?: number;
  onEdit: (nodeId: number) => void;
  onAddChild: (parentId: number, parentNom: string) => void;
  onDelete: (node: ApiOrgNode) => void;
  forceOpen?: boolean;
}) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const hasChildren = node.children.length > 0;
  const effectiveOpen = forceOpen || open;
  const color = LEVEL_COLORS[Math.min(level, LEVEL_COLORS.length - 1)];

  // GET /organigramme peut renvoyer typeOrganigrammes sous forme d'IDs bruts
  // (number[]) plutôt que d'objets {id,nom} — on résout le nom réel via la
  // liste des types pour ne jamais afficher un badge vide.
  const { data: typeOrgData } = useQuery({ queryKey: ["type_organigrammes"], queryFn: listTypeOrganigrammes, staleTime: 300_000 });
  const typeOrgNames = useMemo(() => {
    const map = new Map<number, string>();
    (typeOrgData?.data?.data ?? []).forEach((t) => map.set(t.id, t.nom));
    return map;
  }, [typeOrgData]);

  return (
    <div className={cn("relative", level > 0 && "ml-5 pl-4 before:absolute before:left-0 before:top-0 before:h-full before:border-l-2 before:border-dashed before:border-border/60")}>
      <div
        className={cn(
          "group mb-1.5 rounded-xl border px-4 py-3 transition-all",
          color.bg,
          hasChildren && "cursor-pointer hover:shadow-sm",
        )}
        onClick={() => hasChildren && setOpen(!open)}
      >
        <div className="flex items-center gap-3">
          {/* Indicateur expand */}
          <div className="flex h-6 w-6 shrink-0 items-center justify-center">
            {hasChildren ? (
              <ChevronRight
                className={cn(
                  "h-4 w-4 transition-transform duration-200",
                  color.text,
                  effectiveOpen && "rotate-90",
                )}
              />
            ) : (
              <div className={cn("h-2 w-2 rounded-full", color.dot)} />
            )}
          </div>

          {/* Infos */}
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <span className={cn("font-semibold text-sm", color.text)}>{node.nom}</span>
              {(node.sigle || node.code) && (
                <code className="rounded bg-background/60 px-1.5 py-0.5 text-xs font-mono border border-border/50">
                  {[node.sigle, node.code].filter(Boolean).join(" - ")}
                </code>
              )}
              {!node.is_active && (
                <Badge variant="outline" className="text-xs text-muted-foreground border-muted">
                  {t("common.inactive")}
                </Badge>
              )}
            </div>
            <div className="mt-0.5 flex flex-wrap items-center gap-2">
              {node.type_service && (
                <span className="text-xs text-muted-foreground">{node.type_service}</span>
              )}
              {normalizeTypeOrganigrammes(node.typeOrganigrammes).map((to) => (
                <Badge key={to.id} variant="secondary" className="text-xs py-0 h-4">
                  {typeOrgNames.get(to.id) ?? to.nom}
                </Badge>
              ))}
            </div>
          </div>

          {/* Compteur enfants */}
          {hasChildren && (
            <span className="shrink-0 text-xs text-muted-foreground">
              {t("services.childCount", { count: node.children.length })}
            </span>
          )}

          {/* Boutons d'action (visibles au hover) */}
          <div
            className="flex shrink-0 items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
            onClick={(e) => e.stopPropagation()}
          >
            <CanAccess permission="creation_structure">
              <button
                type="button"
                title={t("services.addChild")}
                onClick={() => onAddChild(node.id, node.nom)}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-primary hover:bg-primary/10"
              >
                <Plus className="h-3.5 w-3.5" />
              </button>
            </CanAccess>
            <CanAccess permission="modification_structure">
              <button
                type="button"
                title={t("services.editTooltip")}
                onClick={() => onEdit(node.id)}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <Pencil className="h-3.5 w-3.5" />
              </button>
            </CanAccess>
            <CanAccess permission="suppression_structure">
              <button
                type="button"
                title={t("services.deleteTooltip")}
                onClick={() => onDelete(node)}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-destructive hover:bg-destructive/10"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </CanAccess>
          </div>
        </div>
      </div>

      {/* Enfants */}
      {effectiveOpen && hasChildren && (
        <div className="space-y-0">
          {node.children.map((child) => (
            <OrgNodeCard
              key={child.id}
              node={child}
              level={level + 1}
              onEdit={onEdit}
              onAddChild={onAddChild}
              onDelete={onDelete}
              forceOpen={forceOpen}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function OrgTreeView({
  onEdit,
  onAddChild,
  onDelete,
  onAddRoot,
}: {
  onEdit: (nodeId: number) => void;
  onAddChild: (parentId: number, parentNom: string) => void;
  onDelete: (node: ApiOrgNode) => void;
  onAddRoot: () => void;
}) {
  const t = useT();
  const [searchQuery, setSearchQuery] = useState("");
  const { data, isLoading, isError } = useQuery({
    queryKey: ["organigramme"],
    queryFn: () => getOrganigramme(),
  });

  const roots: ApiOrgNode[] = data?.data?.data ?? [];
  const displayedRoots = filterOrgTree(roots, searchQuery);
  const isSearching = searchQuery.trim().length > 0;

  if (isLoading) return (
    <div className="flex h-60 items-center justify-center gap-2 text-muted-foreground">
      <Loader2 className="h-5 w-5 animate-spin" />
      <span>{t("common.loading")}</span>
    </div>
  );

  if (isError) return (
    <div className="flex h-60 items-center justify-center text-destructive">{t("toast.error")}</div>
  );

  return (
    <div>
      <div className="mb-3 flex items-center justify-between border-b border-border pb-3">
        <div className="flex items-center gap-2">
          <GitBranch className="h-5 w-5 text-primary" />
          <div>
            <h2 className="font-semibold text-foreground">{t("services.treeTitle")}</h2>
            <p className="text-xs text-muted-foreground">{t("services.treeSubtitle")}</p>
          </div>
        </div>
        <CanAccess permission="creation_structure">
          <Button size="sm" className="gap-2" onClick={onAddRoot}>
            <Plus className="h-3.5 w-3.5" /> {t("services.create")}
          </Button>
        </CanAccess>
      </div>

      {/* Barre de recherche */}
      <div className="mb-3 flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2">
        <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
        <input
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder={t("services.searchPlaceholder")}
          className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
        />
        {isSearching && (
          <button type="button" onClick={() => setSearchQuery("")}>
            <X className="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
          </button>
        )}
      </div>

      {roots.length === 0 ? (
        <div className="flex h-48 flex-col items-center justify-center gap-3 text-muted-foreground">
          <GitBranch className="h-10 w-10 opacity-30" />
          <p className="text-sm">{t("services.emptyTree")}</p>
          <CanAccess permission="creation_structure">
            <Button size="sm" variant="outline" onClick={onAddRoot} className="gap-1">
              <Plus className="h-4 w-4" /> {t("services.createFirst")}
            </Button>
          </CanAccess>
        </div>
      ) : displayedRoots.length === 0 ? (
        <div className="flex h-32 flex-col items-center justify-center gap-2 text-muted-foreground">
          <p className="text-sm">{t("services.noSearchResults", { query: searchQuery })}</p>
          <button type="button" onClick={() => setSearchQuery("")} className="text-xs text-primary underline">
            {t("services.clearSearch")}
          </button>
        </div>
      ) : (
        <div className="space-y-2 py-2">
          {displayedRoots.map((node) => (
            <OrgNodeCard
              key={node.id}
              node={node}
              level={0}
              onEdit={onEdit}
              onAddChild={onAddChild}
              onDelete={onDelete}
              forceOpen={isSearching}
            />
          ))}
        </div>
      )}
    </div>
  );
}

// ============================================================
// Section principale avec onglets
// ============================================================

type OrgTab = "schema" | "gestion" | "types";

export function OrganigrammeSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  // Onglet contrôlé par l'URL (?tab=schema|types), défaut : schema
  const tab = (searchParams.get("tab") as OrgTab | null) ?? "schema";

  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiService | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiService | null>(null);

  // ─── État du dialog inline dans la vue Schéma ─────────────────────────
  const [inlineDialog, setInlineDialog] = useState<OrgInlineDialog>(null);
  const [inlineDeleteNode, setInlineDeleteNode] = useState<ApiOrgNode | null>(null);
  const [loadingEditId, setLoadingEditId] = useState<number | null>(null);

  const invalidateOrg = () => {
    queryClient.invalidateQueries({ queryKey: ["services"] });
    queryClient.invalidateQueries({ queryKey: ["organigramme"] });
  };

  // Ouvrir l'édition inline (charge le service complet depuis l'API)
  const handleInlineEdit = async (nodeId: number) => {
    setLoadingEditId(nodeId);
    try {
      const res = await getServiceById(nodeId);
      setInlineDialog({ mode: "edit", service: res.data });
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setLoadingEditId(null);
    }
  };

  const handleInlineAddChild = (parentId: number, parentNom: string) => {
    setInlineDialog({ mode: "create", parentId, parentNom });
  };

  const handleInlineAddRoot = () => {
    setInlineDialog({ mode: "create" });
  };

  const handleInlineDelete = (node: ApiOrgNode) => {
    setInlineDeleteNode(node);
  };

  const { data, isLoading, isError } = useQuery({
    queryKey: ["services"],
    queryFn: () => listServices({ page: 1, limit: 1000 }),
  });

  const services = [...(data?.data?.data ?? [])].sort((a, b) => {
    if (a.ordre !== b.ordre) return a.ordre - b.ordre;
    return a.nom.localeCompare(b.nom);
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      updateService(id, { is_active }),
    onSuccess: () => {
      invalidateOrg();
    },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteService(id),
    onSuccess: () => {
      invalidateOrg();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
      setInlineDeleteNode(null);
    },
    onError: (err) => toast.error(getApiError(err)),
  });

  const columns: Column<ApiService>[] = [
    {
      key: "nom",
      label: t("services.field.nom"),
      render: (s) => <span className="font-semibold">{s.nom}</span>,
      sortValue: (s) => s.nom,
    },
    {
      key: "sigle",
      label: t("services.field.sigle"),
      render: (s) => (
        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
          {s.sigle || "-"}
        </code>
      ),
    },
    {
      key: "code",
      label: t("common.code"),
      render: (s) => (
        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
          {s.code || "-"}
        </code>
      ),
    },
    {
      key: "type_service",
      label: t("services.field.type"),
      render: (s) => <span className="text-xs">{getServiceType(s) || "-"}</span>,
    },
    {
      key: "ordre",
      label: t("services.field.ordre"),
      render: (s) => <span className="text-xs tabular-nums">{s.ordre}</span>,
      sortValue: (s) => s.ordre,
    },
    {
      key: "parent",
      label: t("services.field.parent"),
      render: (s) => (
        <span className="text-xs text-muted-foreground">
          {s.parent_id ? s.parent_id.nom : "-"}
        </span>
      ),
      sortValue: (s) => s.parent_id?.nom ?? "",
    },
    {
      key: "typeOrganigrammes",
      label: t("services.field.programEcole"),
      render: (s) => (
        <div className="flex flex-wrap gap-1">
          {(s.typeOrganigrammes ?? []).length === 0
            ? <span className="text-xs text-muted-foreground">—</span>
            : (s.typeOrganigrammes ?? []).map((to) => (
                <Badge key={to.id} variant="secondary" className="text-xs">{to.nom}</Badge>
              ))
          }
        </div>
      ),
      exportFormat: (s) => (s.typeOrganigrammes ?? []).map((to) => to.nom).join(", ") || "—",
    },
    {
      key: "is_active",
      label: t("common.status"),
      render: (s) => (
        <label className="flex cursor-pointer items-center gap-2">
          <Switch
            checked={s.is_active}
            onCheckedChange={(checked) =>
              toggleMutation.mutate({ id: s.id, is_active: checked })
            }
          />
          <span className="text-xs font-medium">
            {s.is_active ? t("status.active") : t("common.inactive")}
          </span>
        </label>
      ),
      exportFormat: (s) => (s.is_active ? t("status.active") : t("common.inactive")),
    },
  ];

  return (
    <div className="space-y-4">
      {/* ─ Vue Schéma (avec actions inline) ─ */}
      {tab === "schema" && (
        <div className="rounded-2xl border border-border bg-card p-4 shadow-sm">
          <OrgTreeView
            onEdit={handleInlineEdit}
            onAddChild={handleInlineAddChild}
            onDelete={handleInlineDelete}
            onAddRoot={handleInlineAddRoot}
          />

          {/* Indicateur chargement édition */}
          {loadingEditId !== null && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/20">
              <div className="flex items-center gap-2 rounded-xl bg-white px-5 py-3 shadow-lg">
                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                <span className="text-sm font-medium">{t("common.loading")}</span>
              </div>
            </div>
          )}

          {/* Dialog Créer / Modifier (inline depuis schéma) */}
          <Dialog
            open={inlineDialog?.mode === "create" || inlineDialog?.mode === "edit"}
            onOpenChange={(v) => { if (!v) setInlineDialog(null); }}
          >
            <DialogContent
              key={inlineDialog ? `${inlineDialog.mode}-${"service" in inlineDialog ? inlineDialog.service.id : "parentId" in inlineDialog ? inlineDialog.parentId : "root"}` : "closed"}
              className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            >
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  <Network className="h-5 w-5 text-primary" />
                  {inlineDialog?.mode === "edit"
                    ? t("services.editWithName", { nom: (inlineDialog as { mode: "edit"; service: ApiService }).service.nom })
                    : inlineDialog && "parentNom" in inlineDialog && inlineDialog.parentNom
                    ? t("services.newChildOf", { nom: inlineDialog.parentNom })
                    : t("services.create")}
                </DialogTitle>
              </DialogHeader>
              {(inlineDialog?.mode === "create" || inlineDialog?.mode === "edit") && (
                <ServiceFormContent
                  initial={inlineDialog.mode === "edit" ? (inlineDialog as { mode: "edit"; service: ApiService }).service : null}
                  allServices={services}
                  defaultParentId={inlineDialog.mode === "create" && "parentId" in inlineDialog ? inlineDialog.parentId : undefined}
                  defaultParentNom={inlineDialog.mode === "create" && "parentNom" in inlineDialog ? inlineDialog.parentNom : undefined}
                  onCancel={() => setInlineDialog(null)}
                  onSaved={() => {
                    invalidateOrg();
                    toast.success(t("toast.saved"));
                    setInlineDialog(null);
                  }}
                />
              )}
            </DialogContent>
          </Dialog>

          {/* AlertDialog suppression inline */}
          <AlertDialog
            open={inlineDeleteNode !== null}
            onOpenChange={(open) => { if (!open) setInlineDeleteNode(null); }}
          >
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>{t("services.delete.title")}</AlertDialogTitle>
                <AlertDialogDescription>
                  {t("services.delete.desc")}{" "}
                  <span className="font-semibold text-foreground">{inlineDeleteNode?.nom}</span>
                  {inlineDeleteNode && inlineDeleteNode.children.length > 0 && (
                    <span className="block mt-1 text-destructive text-xs">
                      {t("services.deleteWarnChildren", { count: inlineDeleteNode.children.length })}
                    </span>
                  )}
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
                <AlertDialogAction
                  className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  onClick={() => { if (inlineDeleteNode) deleteMutation.mutate(inlineDeleteNode.id); }}
                >
                  {t("action.delete")}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>
      )}

      {/* Types d'organigramme CRUD */}
      {tab === "types" && <TypeOrganigrammeSection />}

      {/* Gestion CRUD */}
      {tab === "gestion" && (
        <>
          {view === "form" ? (
            <ServiceForm
              initial={selected}
              allServices={services}
              onCancel={() => setView("liste")}
              onSaved={() => {
                invalidateOrg();
                toast.success(t("toast.saved"));
                setView("liste");
              }}
            />
          ) : (
            <>
              {isLoading && (
                <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
                  <Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span>
                </div>
              )}
              {isError && (
                <div className="flex h-40 items-center justify-center text-destructive">{t("toast.error")}</div>
              )}
              {!isLoading && !isError && (
                <div>
                  <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold">{t("config.orga")}</h2>
                    <CanAccess permission="creation_structure">
                      <Button className="gap-2" onClick={() => { setSelected(null); setView("form"); }}>
                        <Plus className="h-4 w-4" /> {t("services.create")}
                      </Button>
                    </CanAccess>
                  </div>
                  <DataTable
                    data={services}
                    columns={columns}
                    getRowId={(s) => String(s.id)}
                    exportFilename="services-minepia"
                    exportTitle="MINEPIA - Organigramme"
                    searchKeys={["nom", "sigle", "code", "type_service"]}
                    rowActions={(s) => (
                      <>
                        <CanAccess permission="modification_structure">
                          <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(s); setView("form"); }} />
                        </CanAccess>
                        <CanAccess permission="suppression_structure">
                          <RowIconButton icon={Trash2} label={t("action.delete")} tone="danger" onClick={() => setDeleteTarget(s)} />
                        </CanAccess>
                      </>
                    )}
                  />
                </div>
              )}

              <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>{t("services.delete.title")}</AlertDialogTitle>
                    <AlertDialogDescription>
                      {t("services.delete.desc")}{" "}
                      <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
                    <AlertDialogAction
                      className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                      onClick={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                    >
                      {t("action.delete")}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </>
          )}
        </>
      )}
    </div>
  );
}
// ============================================================
// ServiceFormContent — logique commune du formulaire (page ET dialog)
// ============================================================

function ServiceFormContent({
  initial,
  allServices,
  defaultParentId,
  defaultParentNom,
  onCancel,
  onSaved,
}: {
  initial: ApiService | null;
  allServices: ApiService[];
  defaultParentId?: number;
  defaultParentNom?: string;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [sigle, setSigle] = useState(initial?.sigle ?? "");
  const [code, setCode] = useState(initial?.code ?? "");
  const initialTypeService = getServiceType(initial);
  const [typeService, setTypeService] = useState(initialTypeService);
  const typeOptions = initialTypeService
    ? [initialTypeService, ...TYPE_OPTIONS.filter((option) => option.toLowerCase() !== initialTypeService.toLowerCase())]
    : TYPE_OPTIONS;
  const [ordre, setOrdre] = useState<string>(String(initial?.ordre ?? 1));
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [parentId, setParentId] = useState<string>(
    initial?.parent_id
      ? String(initial.parent_id.id)
      : defaultParentId
      ? String(defaultParentId)
      : "",
  );
  const [parentSearch, setParentSearch] = useState("");
  const [parentOpen, setParentOpen] = useState(false);
  // Infinite scroll : nombre d'éléments affichés dans le dropdown parent
  const [displayCount, setDisplayCount] = useState(60);
  const parentListRef = useRef<HTMLDivElement>(null);
  const [localisationId, setLocalisationId] = useState<string>(
    initial?.localisation_id ? String(initial.localisation_id) : "",
  );
  const [cartoOpen, setCartoOpen] = useState(false);
  const [cartoSearch, setCartoSearch] = useState("");
  const [openRegions, setOpenRegions] = useState<Set<number>>(new Set());
  const [openDepts, setOpenDepts] = useState<Set<number>>(new Set());

  const { data: cartoData } = useQuery({
    queryKey: ["cartographie"],
    queryFn: () => getCartographie(),
  });
  const cartoRegions: CartographieRegion[] = cartoData?.data ?? [];

  const selectedLabel = (() => {
    if (!localisationId) return null;
    for (const r of cartoRegions) {
      for (const d of r.departements) {
        const a = d.arrondissements.find((x) => String(x.id) === localisationId);
        if (a) return `${a.nom} — ${d.nom}, ${r.nom}`;
      }
    }
    return null;
  })();

  const q = cartoSearch.trim().toLowerCase();
  const filteredRegions = q
    ? cartoRegions
        .map((r) => {
          const depts = r.departements
            .map((d) => {
              const aronds = d.arrondissements.filter((a) =>
                `${a.nom} ${d.nom} ${r.nom}`.toLowerCase().includes(q),
              );
              return aronds.length > 0 ? { ...d, arrondissements: aronds } : null;
            })
            .filter((d): d is CartographieRegion["departements"][number] => d !== null);
          return depts.length > 0 ? { ...r, departements: depts } : null;
        })
        .filter((r): r is CartographieRegion => r !== null)
    : cartoRegions;

  const toggleRegion = (id: number) =>
    setOpenRegions((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const toggleDept = (id: number) =>
    setOpenDepts((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });

  const parentOptions = allServices.filter((s) => s.id !== initial?.id);
  const filteredParents = parentSearch.trim()
    ? parentOptions.filter(
        (s) =>
          s.nom.toLowerCase().includes(parentSearch.toLowerCase()) ||
          (s.sigle && s.sigle.toLowerCase().includes(parentSearch.toLowerCase())),
      )
    : parentOptions;
  const visibleParents = filteredParents.slice(0, displayCount);
  const hasMoreParents = displayCount < filteredParents.length;

  // Réinitialise le nombre affiché quand la recherche change
  useEffect(() => {
    setDisplayCount(60);
    if (parentListRef.current) parentListRef.current.scrollTop = 0;
  }, [parentSearch]);

  // Chargement progressif au scroll dans le dropdown parent
  const handleParentScroll = () => {
    const el = parentListRef.current;
    if (!el || !hasMoreParents) return;
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 80) {
      setDisplayCount((prev) => Math.min(prev + 60, filteredParents.length));
    }
  };

  const selectedParent = parentOptions.find((s) => String(s.id) === parentId);
  // Fallback : si le service parent n'est pas dans allServices (hors page —
  // arrive pour les services profondément imbriqués, au-delà de la limite
  // de listServices), utiliser le nom déjà connu : soit celui transmis lors
  // du clic "+" (création), soit celui de la fiche déjà chargée (édition).
  const selectedParentLabel = selectedParent
    ? `${selectedParent.nom}${selectedParent.sigle ? ` (${selectedParent.sigle})` : ""}`
    : parentId && defaultParentNom
    ? defaultParentNom
    : parentId && initial?.parent_id
    ? initial.parent_id.nom
    : null;

  const createMutation = useMutation({
    mutationFn: (payload: CreateServicePayload) => createService(payload),
    onSuccess: () => onSaved(),
    onError: (err) => toast.error(getApiError(err)),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateServicePayload }) =>
      updateService(id, payload),
    onSuccess: () => onSaved(),
    onError: (err) => toast.error(getApiError(err)),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const buildPayload = (): CreateServicePayload => ({
    nom: nom.trim(),
    sigle: sigle.trim() || undefined,
    code: code.trim() || undefined,
    type_service: typeService || undefined,
    ordre: Number(ordre) || 1,
    is_active: isActive,
    parent_id: parentId ? Number(parentId) : null,
    localisation_id: localisationId ? Number(localisationId) : null,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    if (!initial) {
      createMutation.mutate(buildPayload());
    } else {
      // En modification, un champ vidé doit envoyer null explicitement —
      // omettre la clé (undefined) laisse l'ancienne valeur inchangée côté
      // backend, qui ne fait rien si la clé est absente du payload.
      updateMutation.mutate({
        id: initial.id,
        payload: { ...buildPayload(), sigle: sigle.trim() || null, code: code.trim() || null },
      });
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5 sm:col-span-2">
          <Label>{t("services.field.nom")} <span className="text-destructive">*</span></Label>
          <Input value={nom} onChange={(e) => setNom(e.target.value)} placeholder="Direction des Moyens Généraux" required />
        </div>
        <div className="space-y-1.5">
          <Label>{t("services.field.sigle")}</Label>
          <Input value={sigle} onChange={(e) => setSigle(e.target.value.toUpperCase())} placeholder="DMG" />
        </div>
        <div className="space-y-1.5">
          <Label>{t("common.code")}</Label>
          <Input type="number" value={code} onChange={(e) => setCode(e.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t("services.field.type")}</Label>
          <Select value={typeService} onValueChange={setTypeService}>
            <SelectTrigger><SelectValue placeholder={t("services.chooseOption")} /></SelectTrigger>
            <SelectContent>
              {typeOptions.map((opt) => (
                <SelectItem key={opt} value={opt}>{opt}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>{t("services.field.ordre")}</Label>
          <Input type="number" min={1} value={ordre} onChange={(e) => setOrdre(e.target.value)} placeholder="1" />
        </div>
        <div className="space-y-1.5">
          <Label>{t("services.field.parent")}</Label>
          <Popover open={parentOpen} onOpenChange={setParentOpen}>
            <PopoverTrigger asChild>
              <button
                type="button"
                className={cn(
                  "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring",
                  !selectedParentLabel && "text-muted-foreground",
                )}
              >
                <span className="flex-1 truncate text-left">
                  {selectedParentLabel ?? `— ${t("services.noParent")} —`}
                </span>
                <ChevronRight className={cn("h-4 w-4 shrink-0 text-muted-foreground transition-transform", parentOpen && "rotate-90")} />
              </button>
            </PopoverTrigger>
            <PopoverContent className="w-[400px] p-0 shadow-lg" align="start" sideOffset={4}>
              {/* Barre de recherche */}
              <div className="flex items-center gap-2 border-b border-border px-3 py-2">
                <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                <input
                  autoFocus
                  value={parentSearch}
                  onChange={(e) => setParentSearch(e.target.value)}
                  placeholder={t("services.searchParentPlaceholder")}
                  className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
                {parentSearch && (
                  <button type="button" onClick={() => setParentSearch("")}>
                    <X className="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                  </button>
                )}
              </div>

              {/* Liste avec scroll natif + chargement infini */}
              <div
                ref={parentListRef}
                onScroll={handleParentScroll}
                className="py-1"
                style={{
                  maxHeight: "220px",
                  overflowY: "auto",
                  overscrollBehavior: "contain",
                }}
              >
                {/* Option "aucun parent" */}
                <button
                  type="button"
                  onClick={() => { setParentId(""); setParentOpen(false); setParentSearch(""); }}
                  className={cn(
                    "flex w-full items-center px-3 py-2 text-sm transition-colors hover:bg-muted",
                    !parentId && "bg-primary/10 text-primary",
                  )}
                >
                  — {t("services.noParent")} —
                </button>

                {/* Aucun résultat */}
                {filteredParents.length === 0 && parentSearch && (
                  <p className="px-3 py-3 text-center text-xs text-muted-foreground">{t("services.noParentSearchResults", { query: parentSearch })}</p>
                )}

                {/* Services visibles */}
                {visibleParents.map((s) => (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => { setParentId(String(s.id)); setParentOpen(false); setParentSearch(""); }}
                    className={cn(
                      "flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-muted",
                      String(s.id) === parentId && "bg-primary/10 text-primary font-medium",
                    )}
                  >
                    <span className="flex-1 truncate text-left">
                      {s.nom}{s.sigle ? ` (${s.sigle})` : ""}
                    </span>
                  </button>
                ))}

                {/* Indicateur "chargement de plus" */}
                {hasMoreParents && (
                  <div className="flex items-center justify-center py-2">
                    <span className="text-xs text-muted-foreground">
                      {t("services.moreCount", { count: filteredParents.length - displayCount })}
                    </span>
                  </div>
                )}
              </div>

              {/* Compteur */}
              <div className="border-t border-border px-3 py-1.5 text-[11px] text-muted-foreground">
                {t("services.serviceCount", { count: filteredParents.length })}{" "}
                {parentSearch ? t("services.foundSuffix") : t("services.totalSuffix")}
              </div>
            </PopoverContent>
          </Popover>
        </div>

        {/* Localisation */}
        <div className="space-y-1.5 sm:col-span-2">
          <Label>{t("services.field.localisation")}</Label>
          <Popover open={cartoOpen} onOpenChange={setCartoOpen}>
            <PopoverTrigger asChild>
              <button
                type="button"
                className={cn(
                  "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-ring",
                  !selectedLabel && "text-muted-foreground",
                )}
              >
                <span className="flex-1 truncate text-left">
                  {selectedLabel ?? t("services.noLocalisation")}
                </span>
                <div className="flex shrink-0 items-center gap-1">
                  {localisationId && (
                    <span
                      role="button"
                      tabIndex={0}
                      onClick={(e) => { e.stopPropagation(); setLocalisationId(""); }}
                      onKeyDown={(e) => e.key === "Enter" && (e.stopPropagation(), setLocalisationId(""))}
                      className="rounded p-0.5 hover:bg-muted"
                    >
                      <X className="h-3.5 w-3.5 text-muted-foreground" />
                    </span>
                  )}
                  <ChevronRight className={cn("h-4 w-4 text-muted-foreground transition-transform", cartoOpen && "rotate-90")} />
                </div>
              </button>
            </PopoverTrigger>
            <PopoverContent className="w-[420px] p-0 shadow-lg" align="start" sideOffset={4}>
              <div className="flex items-center gap-2 border-b border-border px-3 py-2">
                <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                <input
                  autoFocus
                  value={cartoSearch}
                  onChange={(e) => {
                    setCartoSearch(e.target.value);
                    if (e.target.value.trim()) {
                      setOpenRegions(new Set(cartoRegions.map((r) => r.id)));
                      setOpenDepts(new Set(cartoRegions.flatMap((r) => r.departements.map((d) => d.id))));
                    }
                  }}
                  placeholder={t("common.searchEllipsis")}
                  className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
                {cartoSearch && (
                  <button type="button" onClick={() => setCartoSearch("")}>
                    <X className="h-3.5 w-3.5 text-muted-foreground" />
                  </button>
                )}
              </div>
              <div className="max-h-64 overflow-y-auto py-1">
                {filteredRegions.length === 0 && (
                  <p className="px-4 py-3 text-xs text-muted-foreground">{t("common.noResults")}</p>
                )}
                {filteredRegions.map((region) => {
                  const rOpen = cartoSearch.trim() ? true : openRegions.has(region.id);
                  return (
                    <div key={region.id}>
                      <button
                        type="button"
                        onClick={() => toggleRegion(region.id)}
                        className="flex w-full items-center gap-2 px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted/60"
                      >
                        <ChevronRight className={cn("h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform", rOpen && "rotate-90")} />
                        <span className="flex-1 text-left">{region.nom}</span>
                      </button>
                      {rOpen && region.departements.map((dept) => {
                        const dOpen = cartoSearch.trim() ? true : openDepts.has(dept.id);
                        return (
                          <div key={dept.id}>
                            <button
                              type="button"
                              onClick={() => toggleDept(dept.id)}
                              className="flex w-full items-center gap-2 pl-7 pr-3 py-1.5 text-sm text-foreground hover:bg-muted/60"
                            >
                              <ChevronRight className={cn("h-3 w-3 shrink-0 text-muted-foreground transition-transform", dOpen && "rotate-90")} />
                              <span className="flex-1 text-left">{dept.nom}</span>
                            </button>
                            {dOpen && dept.arrondissements.map((arond) => {
                              const sel = localisationId === String(arond.id);
                              return (
                                <button
                                  key={arond.id}
                                  type="button"
                                  onClick={() => { setLocalisationId(String(arond.id)); setCartoOpen(false); setCartoSearch(""); }}
                                  className={cn(
                                    "flex w-full items-center gap-2.5 pl-12 pr-3 py-1.5 text-sm transition-colors hover:bg-primary/8",
                                    sel && "bg-primary/10 text-primary",
                                  )}
                                >
                                  <span className={cn("flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors", sel ? "border-primary bg-primary" : "border-muted-foreground/40")}>
                                    {sel && <span className="h-1.5 w-1.5 rounded-full bg-white" />}
                                  </span>
                                  <span className="flex-1 text-left">{arond.nom}</span>
                                </button>
                              );
                            })}
                          </div>
                        );
                      })}
                    </div>
                  );
                })}
              </div>
              {localisationId && (
                <div className="border-t border-border px-3 py-2">
                  <button type="button" onClick={() => { setLocalisationId(""); setCartoOpen(false); }} className="text-xs text-muted-foreground hover:text-destructive">
                    {t("common.clearSelection")}
                  </button>
                </div>
              )}
            </PopoverContent>
          </Popover>
        </div>

        <div className="flex items-center gap-3 sm:col-span-2">
          <Switch id="svc-active-fc" checked={isActive} onCheckedChange={setIsActive} />
          <Label htmlFor="svc-active-fc">
            {isActive ? t("status.active") : t("common.inactive")}
          </Label>
        </div>
      </div>

      <div className="mt-5 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
        <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
          <X className="h-4 w-4" /> {t("action.cancel")}
        </Button>
        <Button type="submit" className="gap-2" disabled={isPending || !nom.trim()}>
          {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          {t("action.save")}
        </Button>
      </div>
    </form>
  );
}

// ============================================================
// Formulaire création / édition (vue page — onglet Gestion)
// ============================================================

function ServiceForm({
  initial,
  allServices,
  onCancel,
  onSaved,
}: {
  initial: ApiService | null;
  allServices: ApiService[];
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Network className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `${t("action.edit")} - ${initial.nom}` : t("services.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("services.subtitle")}</p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <ServiceFormContent
            initial={initial}
            allServices={allServices}
            onCancel={onCancel}
            onSaved={onSaved}
          />
        </div>
      </div>
    </div>
  );
}
// ============================================================
// CRUD — Types d'organigramme (Programme, École, etc.)
// ============================================================

type TOView = "liste" | "form";

function TypeOrganigrammeSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<TOView>("liste");
  const [selected, setSelected] = useState<ApiTypeOrganigramme | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiTypeOrganigramme | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["type_organigrammes"],
    queryFn: listTypeOrganigrammes,
  });

  const types: ApiTypeOrganigramme[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["type_organigrammes"] });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteTypeOrganigramme(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const columns: Column<ApiTypeOrganigramme>[] = [
    {
      key: "nom",
      label: t("common.name"),
      render: (to) => <span className="font-semibold">{to.nom}</span>,
      sortValue: (to) => to.nom,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (to) => <span className="text-sm text-muted-foreground">{to.description || "—"}</span>,
    },
  ];

  if (isLoading) return (
    <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
      <Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span>
    </div>
  );
  if (isError) return (
    <div className="flex h-40 items-center justify-center text-destructive">{t("toast.error")}</div>
  );

  if (view === "form") {
    return (
      <TypeOrgForm
        initial={selected}
        onCancel={() => setView("liste")}
        onSaved={() => {
          invalidate();
          toast.success(t("toast.saved"));
          setView("liste");
        }}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">{t("nav.group.orga.types")}</h2>
            <p className="text-xs text-muted-foreground">{t("typeOrga.subtitle")}</p>
          </div>
          <CanAccess permission="creation_structure">
            <Button className="gap-2" onClick={() => { setSelected(null); setView("form"); }}>
              <Plus className="h-4 w-4" /> {t("typeOrga.create")}
            </Button>
          </CanAccess>
        </div>
        <DataTable
          data={types}
          columns={columns}
          getRowId={(to) => String(to.id)}
          exportFilename="types-structure-minepia"
          exportTitle={`MINEPIA — ${t("nav.group.orga.types")}`}
          searchKeys={["nom", "description"]}
          rowActions={(to) => (
            <>
              <CanAccess permission="modification_structure">
                <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(to); setView("form"); }} />
              </CanAccess>
              <CanAccess permission="suppression_structure">
                <RowIconButton icon={Trash2} label={t("action.delete")} tone="danger" onClick={() => setDeleteTarget(to)} />
              </CanAccess>
            </>
          )}
        />
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("typeOrga.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("typeOrga.delete.descBefore")}{" "}
              <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
              {t("typeOrga.delete.descAfter")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire TypeOrganigramme ────────────────────────────────────────────

function TypeOrgForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiTypeOrganigramme | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");

  const createMutation = useMutation({
    mutationFn: (payload: CreateTypeOrganigrammePayload) => createTypeOrganigramme(payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: CreateTypeOrganigrammePayload }) =>
      updateTypeOrganigramme(id, payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    const payload: CreateTypeOrganigrammePayload = {
      nom: nom.trim(),
      description: description.trim() || undefined,
    };
    if (!initial) {
      createMutation.mutate(payload);
    } else {
      updateMutation.mutate({ id: initial.id, payload });
    }
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>
      <form onSubmit={handleSubmit} className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Tags className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-base font-semibold">
              {initial ? t("typeOrga.editWithName", { nom: initial.nom }) : t("typeOrga.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("typeOrga.formSubtitle")}</p>
          </div>
        </div>
        <div className="grid gap-4 px-5 py-5 sm:px-6 sm:py-6">
          <div className="space-y-1.5">
            <Label>{t("common.name")} <span className="text-destructive">*</span></Label>
            <Input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              placeholder="Ex : Programme National Lait"
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label>{t("common.description")}</Label>
            <Textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder={t("common.optionalDescription")}
              rows={3}
            />
          </div>
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
