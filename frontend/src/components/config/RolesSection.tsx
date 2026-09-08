/**
 * Section de configuration — Gestion des rôles.
 * CRUD complet + gestion des permissions affectées à chaque rôle.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Save,
  X,
  ArrowLeft,
  Loader2,
  UserCog,
  ShieldCheck,
  Minus,
  Check,
  ChevronDown,
  Archive,
  RotateCcw,
  Copy,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listRoles,
  createRoles,
  updateRole,
  deleteRole,
  softDeleteRole,
  restoreRole,
  getRoleById,
  assignPermissionToRole,
  removePermissionFromRole,
  type ApiRole,
  type CreateRolePayload,
  type UpdateRolePayload,
} from "@/api/roles/roles.api";
import {
  listPermissions,
  type ApiPermission,
} from "@/api/permissions/permissions.api";

type RolesView = "liste" | "form" | "permissions";

// ─── Section principale ─────────────────────────────────────────────────────

export function RolesSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<RolesView>("liste");
  const [selected, setSelected] = useState<ApiRole | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ApiRole | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<ApiRole | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiRole | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["roles", showDeleted],
    queryFn: () => listRoles({ page: 1, limit: 1000, is_delete: showDeleted }),
  });

  const roles = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["roles"] });

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      updateRole(id, { is_active }),
    onSuccess: invalidate,
    onError: () => toast.error(t("toast.error")),
  });

  const archiveMutation = useMutation({
    mutationFn: (id: number) => softDeleteRole(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setArchiveTarget(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const restoreMutation = useMutation({
    mutationFn: (id: number) => restoreRole(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setRestoreTarget(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteRole(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiRole>[] = [
    {
      key: "nom",
      label: t("roles.field.nom"),
      render: (r) => <span className="font-semibold">{r.nom}</span>,
      sortValue: (r) => r.nom,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (r) => (
        <span className="text-xs text-muted-foreground">{r.description || "—"}</span>
      ),
    },
    {
      key: "permissions",
      label: t("roles.field.permissions"),
      render: (r) => {
        const count = r.permissions?.length ?? 0;
        if (count === 0) {
          return (
            <span className="text-xs text-muted-foreground">
              {t("roles.noPermissions")}
            </span>
          );
        }
        return (
          <div className="flex flex-wrap gap-1">
            {r.permissions!.slice(0, 3).map((p) => (
              <Badge key={p.id} variant="secondary" className="font-mono text-xs">
                {p.nom}
              </Badge>
            ))}
            {count > 3 && (
              <Popover>
                <PopoverTrigger asChild>
                  <button type="button" onClick={(e) => e.stopPropagation()}>
                    <Badge variant="outline" className="cursor-pointer text-xs hover:bg-muted">
                      +{count - 3}
                    </Badge>
                  </button>
                </PopoverTrigger>
                <PopoverContent className="w-72 max-h-72 overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                  <p className="mb-2 text-xs font-semibold text-foreground">
                    {t("roles.allPermissions", { count })}
                  </p>
                  <div className="flex flex-wrap gap-1">
                    {r.permissions!.map((p) => (
                      <Badge key={p.id} variant="secondary" className="font-mono text-xs">
                        {p.nom}
                      </Badge>
                    ))}
                  </div>
                </PopoverContent>
              </Popover>
            )}
          </div>
        );
      },
    },
    {
      key: "is_active",
      label: t("common.status"),
      render: (r) =>
        showDeleted ? (
          <span className="text-xs font-medium text-muted-foreground">
            {r.is_active ? t("status.active") : t("common.inactive")}
          </span>
        ) : (
          <label className="flex cursor-pointer items-center gap-2">
            <Switch
              checked={r.is_active}
              onCheckedChange={(checked) =>
                toggleMutation.mutate({ id: r.id, is_active: checked })
              }
            />
            <span className="text-xs font-medium">
              {r.is_active ? t("status.active") : t("common.inactive")}
            </span>
          </label>
        ),
      exportFormat: (r) => (r.is_active ? t("status.active") : t("common.inactive")),
    },
  ];

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("common.loading")}</span>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-40 items-center justify-center text-destructive">
        {t("toast.error")}
      </div>
    );
  }

  if (view === "form") {
    const onSaved = () => {
      queryClient.invalidateQueries({ queryKey: ["roles"] });
      setView("liste");
    };
    return selected ? (
      <RoleForm initial={selected} onCancel={() => setView("liste")} onSaved={() => { toast.success(t("toast.saved")); onSaved(); }} />
    ) : (
      <RoleBulkCreateForm onCancel={() => setView("liste")} onSaved={onSaved} />
    );
  }

  if (view === "permissions" && selected) {
    return (
      <RolePermissionsManager
        role={selected}
        onBack={() => setView("liste")}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("config.roles")}</h2>
          <div className="flex items-center gap-3">
            <label className="flex cursor-pointer items-center gap-2 text-sm">
              <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
              {t("common.showDeleted")}
            </label>
            <CanAccess permission="creation_role">
              <Button
                className="gap-2"
                onClick={() => {
                  setSelected(null);
                  setView("form");
                }}
              >
                <Plus className="h-4 w-4" /> {t("roles.create")}
              </Button>
            </CanAccess>
          </div>
        </div>
        <DataTable
          data={roles}
          columns={columns}
          getRowId={(r) => String(r.id)}
          exportFilename="roles-minepia"
          exportTitle="MINEPIA — Rôles"
          searchKeys={["nom", "description"]}
          rowActions={(r) => (
            <>
              {!showDeleted && (
                <>
                  <CanAccess permission="creation_role">
                    <RowIconButton
                      icon={Pencil}
                      label={t("action.edit")}
                      onClick={() => {
                        setSelected(r);
                        setView("form");
                      }}
                    />
                  </CanAccess>
                  <CanAccess permission="affectation_permission_role">
                    <RowIconButton
                      icon={ShieldCheck}
                      label={t("roles.permissions.manage")}
                      onClick={() => {
                        setSelected(r);
                        setView("permissions");
                      }}
                    />
                  </CanAccess>
                </>
              )}
              <CanAccess permission="creation_role">
                {showDeleted ? (
                  <RowIconButton icon={RotateCcw} label={t("action.restore")} onClick={() => setRestoreTarget(r)} />
                ) : (
                  <RowIconButton icon={Archive} label={t("action.delete")} onClick={() => setArchiveTarget(r)} />
                )}
              </CanAccess>
              {showDeleted && (
                <CanAccess permission="creation_role">
                  <RowIconButton
                    icon={Trash2}
                    label={t("common.permanentDelete")}
                    tone="danger"
                    onClick={() => setDeleteTarget(r)}
                  />
                </CanAccess>
              )}
            </>
          )}
        />
      </div>

      {/* ── Suppression logique (corbeille) ── */}
      <AlertDialog open={archiveTarget !== null} onOpenChange={(open) => { if (!open) setArchiveTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("roles.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("roles.archive.desc.before")}{" "}
              <span className="font-semibold text-foreground">{archiveTarget?.nom}</span>{" "}
              {t("roles.archive.desc.after")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              disabled={archiveMutation.isPending}
              onClick={() => { if (archiveTarget) archiveMutation.mutate(archiveTarget.id); }}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Restauration ── */}
      <AlertDialog open={restoreTarget !== null} onOpenChange={(open) => { if (!open) setRestoreTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("roles.restore.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("roles.archive.desc.before")}{" "}
              <span className="font-semibold text-foreground">{restoreTarget?.nom}</span>{" "}
              {t("roles.restore.desc.after")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              disabled={restoreMutation.isPending}
              onClick={() => { if (restoreTarget) restoreMutation.mutate(restoreTarget.id); }}
            >
              <RotateCcw className="mr-2 h-4 w-4" /> {t("action.restore")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Suppression définitive ── */}
      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("common.permanentDelete")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("roles.delete.desc")}{" "}
              <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) deleteMutation.mutate(deleteTarget.id);
              }}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire édition (un seul rôle) ──────────────────────────────────────

function RoleForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiRole;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial.nom);
  const [description, setDescription] = useState(initial.description ?? "");
  const [isActive, setIsActive] = useState(initial.is_active);
  const [selectedPermissionIds, setSelectedPermissionIds] = useState<Set<number>>(
    new Set(initial.permissions?.map((p) => p.id) ?? []),
  );

  const { data: allPermsData } = useQuery({
    queryKey: ["permissions"],
    queryFn: () => listPermissions({ page: 1, limit: 1000 }),
  });
  const allPermissions: ApiPermission[] = [...(allPermsData?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );
  const togglePerm = (id: number) => {
    setSelectedPermissionIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const updateMutation = useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: number;
      payload: UpdateRolePayload;
    }) => {
      const result = await updateRole(id, payload);
      const initialIds = new Set(initial.permissions?.map((p) => p.id) ?? []);
      const toAdd = [...selectedPermissionIds].filter((pid) => !initialIds.has(pid));
      const toRemove = [...initialIds].filter((pid) => !selectedPermissionIds.has(pid));
      await Promise.all([
        ...toAdd.map((pid) => assignPermissionToRole(id, pid)),
        ...toRemove.map((pid) => removePermissionFromRole(id, pid)),
      ]);
      return result;
    },
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const isPending = updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    updateMutation.mutate({
      id: initial.id,
      payload: { nom: nom.trim(), description, is_active: isActive },
    });
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onCancel}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <UserCog className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {t("action.edit")} — {initial.nom}
            </h2>
            <p className="text-xs text-muted-foreground">{t("roles.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("roles.field.nom")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Administrateur"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("roles.field.description")}</Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder={t("roles.field.description.placeholder")}
              />
            </div>
            <div className="flex items-center gap-3">
              <Switch
                id="role-active"
                checked={isActive}
                onCheckedChange={setIsActive}
              />
              <Label htmlFor="role-active">
                {isActive ? t("status.active") : t("common.inactive")}
              </Label>
            </div>

            {/* ── Sélecteur de permissions ── */}
            <div className="space-y-1.5">
              <Label>{t("roles.field.permissions")}</Label>
              <RolePermissionsPicker
                allPermissions={allPermissions}
                selectedIds={selectedPermissionIds}
                onToggle={togglePerm}
                onSelectAll={() => setSelectedPermissionIds(new Set(allPermissions.map((p) => p.id)))}
                onClearAll={() => setSelectedPermissionIds(new Set())}
              />
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={isPending}
          >
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}

// ─── Formulaire création — plusieurs rôles en un seul envoi ────────────────
// POST /roles accepte un tableau, chaque rôle pouvant embarquer ses
// permissions directement (confirmé, 2026-08-29) : ce formulaire permet
// d'ajouter autant de lignes que nécessaire avant un unique envoi groupé.

let roleRowSeq = 0;
interface RoleDraftRow {
  key: number;
  nom: string;
  description: string;
  is_active: boolean;
  permissionIds: Set<number>;
}
function emptyRoleRow(): RoleDraftRow {
  return { key: ++roleRowSeq, nom: "", description: "", is_active: true, permissionIds: new Set() };
}

/** Sélecteur de permissions pour une ligne du formulaire groupé — même UI que
 * l'ancien sélecteur du formulaire d'édition, isolé par ligne. */
function RolePermissionsPicker({
  allPermissions,
  selectedIds,
  onToggle,
  onSelectAll,
  onClearAll,
}: {
  allPermissions: ApiPermission[];
  selectedIds: Set<number>;
  onToggle: (id: number) => void;
  onSelectAll: () => void;
  onClearAll: () => void;
}) {
  const t = useT();
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const filtered = allPermissions.filter(
    (p) =>
      p.nom.toLowerCase().includes(search.toLowerCase()) ||
      p.description?.toLowerCase().includes(search.toLowerCase()),
  );
  const selected = allPermissions.filter((p) => selectedIds.has(p.id));

  return (
    <div className="space-y-1.5">
      {selected.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selected.map((p) => (
            <Badge key={p.id} variant="secondary" className="gap-1 pr-1 font-mono text-xs">
              {p.nom}
              <button type="button" onClick={() => onToggle(p.id)} className="ml-0.5 flex h-4 w-4 items-center justify-center rounded hover:bg-muted-foreground/20">
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button type="button" variant="outline" className="w-full justify-between gap-2">
            <span className="text-muted-foreground">
              {selectedIds.size === 0 ? t("roles.selectPermissions") : t("roles.permissionsSelectedCount", { count: selectedIds.size })}
            </span>
            <ChevronDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
          <div className="flex items-center gap-2 border-b border-border p-2">
            <Input placeholder={t("common.searchEllipsis")} value={search} onChange={(e) => setSearch(e.target.value)} className="h-8" />
            <button
              type="button"
              onClick={onSelectAll}
              disabled={allPermissions.length === 0 || selectedIds.size === allPermissions.length}
              className="shrink-0 whitespace-nowrap text-xs font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-40"
            >
              {t("common.selectAll")}
            </button>
            <button
              type="button"
              onClick={onClearAll}
              disabled={selectedIds.size === 0}
              className="shrink-0 whitespace-nowrap text-xs font-medium text-muted-foreground hover:text-destructive hover:underline disabled:cursor-not-allowed disabled:opacity-40"
            >
              {t("common.deselectAll")}
            </button>
          </div>
          <div className="max-h-52 overflow-y-auto p-1">
            {filtered.length === 0 ? (
              <p className="py-4 text-center text-xs text-muted-foreground">{t("common.noResults")}</p>
            ) : (
              filtered.map((p) => (
                <button
                  key={p.id}
                  type="button"
                  onClick={() => onToggle(p.id)}
                  className="flex w-full items-center gap-2.5 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                >
                  <Checkbox checked={selectedIds.has(p.id)} className="pointer-events-none" />
                  <div className="min-w-0">
                    <code className="block truncate font-mono text-xs font-semibold">{p.nom}</code>
                    {p.description && <span className="block truncate text-xs text-muted-foreground">{p.description}</span>}
                  </div>
                  {selectedIds.has(p.id) && <Check className="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />}
                </button>
              ))
            )}
          </div>
        </PopoverContent>
      </Popover>
    </div>
  );
}

function RoleBulkCreateForm({
  onCancel,
  onSaved,
}: {
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [rows, setRows] = useState<RoleDraftRow[]>([emptyRoleRow()]);

  const { data: allPermsData } = useQuery({
    queryKey: ["permissions"],
    queryFn: () => listPermissions({ page: 1, limit: 1000 }),
  });
  const allPermissions: ApiPermission[] = [...(allPermsData?.data?.data ?? [])].sort((a, b) => a.nom.localeCompare(b.nom));

  const updateRow = (key: number, patch: Partial<RoleDraftRow>) =>
    setRows((prev) => prev.map((r) => (r.key === key ? { ...r, ...patch } : r)));
  const togglePermission = (key: number, permId: number) =>
    setRows((prev) => prev.map((r) => {
      if (r.key !== key) return r;
      const next = new Set(r.permissionIds);
      next.has(permId) ? next.delete(permId) : next.add(permId);
      return { ...r, permissionIds: next };
    }));
  const addRow = () => setRows((prev) => [...prev, emptyRoleRow()]);
  const duplicateRow = (row: RoleDraftRow) =>
    setRows((prev) => [...prev, { ...row, key: ++roleRowSeq, nom: "", permissionIds: new Set(row.permissionIds) }]);
  const removeRow = (key: number) => setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.key !== key) : prev));

  const createMutation = useMutation({
    mutationFn: (payloads: CreateRolePayload[]) => createRoles(payloads),
    onSuccess: (res) => {
      const count = res.data?.length ?? rows.length;
      toast.success(
        count > 1
          ? t("roles.bulkCreate.successPlural", { count })
          : t("roles.bulkCreate.success", { count }),
      );
      onSaved();
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("toast.error"));
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const emptyIdx = rows.findIndex((r) => !r.nom.trim());
    if (emptyIdx !== -1) {
      toast.error(t("permissions.bulkCreate.nameRequired", { line: emptyIdx + 1 }));
      return;
    }
    createMutation.mutate(
      rows.map((r) => ({
        nom: r.nom.trim(),
        description: r.description,
        is_active: r.is_active,
        permissions: r.permissionIds.size > 0 ? [...r.permissionIds] : undefined,
      })),
    );
  };

  return (
    <div className="mx-auto w-full max-w-3xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>
      <form onSubmit={handleSubmit} className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <UserCog className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">{t("roles.create")}</h2>
            <p className="text-xs text-muted-foreground">
              {t("roles.bulkCreate.subtitle")}
            </p>
          </div>
        </div>

        <div className="space-y-4 px-5 py-5 sm:px-6 sm:py-6">
          {rows.map((row, idx) => (
            <div key={row.key} className="rounded-xl border border-dashed border-border p-4">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-xs font-semibold text-muted-foreground">{t("roles.bulkCreate.rowLabel", { index: idx + 1 })}</span>
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => duplicateRow(row)}
                    title={t("common.duplicateRow")}
                    className="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                  >
                    <Copy className="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeRow(row.key)}
                    disabled={rows.length === 1}
                    title={t("common.removeRow")}
                    className="flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:opacity-30"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
              <div className="grid gap-3">
                <div className="space-y-1.5">
                  <Label>
                    {t("roles.field.nom")} <span className="text-destructive">*</span>
                  </Label>
                  <Input value={row.nom} onChange={(e) => updateRow(row.key, { nom: e.target.value })} placeholder="Gestionnaire de Biens" required />
                </div>
                <div className="space-y-1.5">
                  <Label>{t("roles.field.description")}</Label>
                  <Input value={row.description} onChange={(e) => updateRow(row.key, { description: e.target.value })} placeholder={t("roles.field.description.placeholder")} />
                </div>
                <div className="flex items-center gap-3">
                  <Switch checked={row.is_active} onCheckedChange={(v) => updateRow(row.key, { is_active: v })} />
                  <Label>{row.is_active ? t("status.active") : t("common.inactive")}</Label>
                </div>
                <div className="space-y-1.5">
                  <Label>{t("roles.field.permissions")}</Label>
                  <RolePermissionsPicker
                    allPermissions={allPermissions}
                    selectedIds={row.permissionIds}
                    onToggle={(permId) => togglePermission(row.key, permId)}
                    onSelectAll={() => updateRow(row.key, { permissionIds: new Set(allPermissions.map((p) => p.id)) })}
                    onClearAll={() => updateRow(row.key, { permissionIds: new Set() })}
                  />
                </div>
              </div>
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" className="gap-2" onClick={addRow}>
            <Plus className="h-4 w-4" /> {t("roles.bulkCreate.addRow")}
          </Button>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={createMutation.isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={createMutation.isPending}>
            {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {rows.length > 1 ? t("roles.bulkCreate.createCount", { count: rows.length }) : t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}

// ─── Gestionnaire de permissions du rôle ───────────────────────────────────

function RolePermissionsManager({
  role,
  onBack,
}: {
  role: ApiRole;
  onBack: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();

  // Détail du rôle avec ses permissions
  const { data: roleData, isLoading: roleLoading } = useQuery({
    queryKey: ["roles", role.id],
    queryFn: () => getRoleById(role.id),
  });

  // Toutes les permissions du système
  const { data: allPermsData, isLoading: permsLoading } = useQuery({
    queryKey: ["permissions"],
    queryFn: () => listPermissions({ page: 1, limit: 1000 }),
  });

  const assignedPermissions: ApiPermission[] = roleData?.data.permissions ?? [];
  const allPermissions: ApiPermission[] = [
    ...(allPermsData?.data?.data ?? []),
  ].sort((a, b) => a.nom.localeCompare(b.nom));

  const assignedIds = new Set(assignedPermissions.map((p) => p.id));
  const availablePermissions = allPermissions.filter((p) => !assignedIds.has(p.id));

  const [searchAssigned, setSearchAssigned] = useState("");
  const [searchAvailable, setSearchAvailable] = useState("");

  const filteredAssigned = assignedPermissions.filter(
    (p) =>
      p.nom.toLowerCase().includes(searchAssigned.toLowerCase()) ||
      p.description?.toLowerCase().includes(searchAssigned.toLowerCase()),
  );
  const filteredAvailable = availablePermissions.filter(
    (p) =>
      p.nom.toLowerCase().includes(searchAvailable.toLowerCase()) ||
      p.description?.toLowerCase().includes(searchAvailable.toLowerCase()),
  );

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["roles", role.id] });
    queryClient.invalidateQueries({ queryKey: ["roles"] });
  };

  const assignMutation = useMutation({
    mutationFn: (permissionId: number) =>
      assignPermissionToRole(role.id, permissionId),
    onSuccess: invalidate,
    onError: () => toast.error(t("toast.error")),
  });

  const removeMutation = useMutation({
    mutationFn: (permissionId: number) =>
      removePermissionFromRole(role.id, permissionId),
    onSuccess: invalidate,
    onError: () => toast.error(t("toast.error")),
  });

  const isLoading = roleLoading || permsLoading;

  return (
    <div className="mx-auto w-full max-w-4xl space-y-4">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onBack}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>

      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {t("roles.permissions.manage")} —{" "}
              <span className="text-primary">{role.nom}</span>
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("roles.permissions.assign")}
            </p>
          </div>
        </div>

        {isLoading ? (
          <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
            <Loader2 className="h-5 w-5 animate-spin" />
            <span>{t("common.loading")}</span>
          </div>
        ) : (
          <div className="grid gap-6 px-5 py-5 sm:grid-cols-2 sm:px-6 sm:py-6">
            {/* ── Permissions affectées ── */}
            <div>
              <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/30">
                  {assignedPermissions.length}
                </span>
                {t("roles.permissions.assigned")}
              </h3>
              <Input
                placeholder={t("action.search")}
                value={searchAssigned}
                onChange={(e) => setSearchAssigned(e.target.value)}
                className="mb-2 h-8 text-xs"
              />
              <div className="min-h-[120px] rounded-xl border border-border bg-muted/20 p-2">
                {filteredAssigned.length === 0 ? (
                  <p className="py-6 text-center text-xs text-muted-foreground">
                    {searchAssigned ? t("common.empty") : t("roles.noPermissions")}
                  </p>
                ) : (
                  <ul className="max-h-[280px] space-y-1 overflow-y-auto">
                    {filteredAssigned.map((p) => (
                      <li
                        key={p.id}
                        className="flex items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2"
                      >
                        <div className="min-w-0">
                          <code className="block truncate font-mono text-xs font-semibold">
                            {p.nom}
                          </code>
                          {p.description && (
                            <span className="block truncate text-xs text-muted-foreground">
                              {p.description}
                            </span>
                          )}
                        </div>
                        <button
                          type="button"
                          onClick={() => removeMutation.mutate(p.id)}
                          disabled={removeMutation.isPending}
                          title={t("action.delete")}
                          className="flex h-6 w-6 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                        >
                          <Minus className="h-3.5 w-3.5" />
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>

            {/* ── Permissions disponibles ── */}
            <div>
              <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary">
                  {availablePermissions.length}
                </span>
                {t("roles.permissions.available")}
              </h3>
              <Input
                placeholder={t("action.search")}
                value={searchAvailable}
                onChange={(e) => setSearchAvailable(e.target.value)}
                className="mb-2 h-8 text-xs"
              />
              <div className="min-h-[120px] rounded-xl border border-border bg-muted/20 p-2">
                {filteredAvailable.length === 0 ? (
                  <p className="py-6 text-center text-xs text-muted-foreground">
                    {searchAvailable ? t("common.empty") : t("roles.permissions.allAssigned")}
                  </p>
                ) : (
                  <ul className="max-h-[280px] space-y-1 overflow-y-auto">
                    {filteredAvailable.map((p) => (
                      <li
                        key={p.id}
                        className="flex items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2"
                      >
                        <div className="min-w-0">
                          <code className="block truncate font-mono text-xs font-semibold">
                            {p.nom}
                          </code>
                          {p.description && (
                            <span className="block truncate text-xs text-muted-foreground">
                              {p.description}
                            </span>
                          )}
                        </div>
                        <button
                          type="button"
                          onClick={() => assignMutation.mutate(p.id)}
                          disabled={assignMutation.isPending}
                          title={t("roles.assign")}
                          className="flex h-6 w-6 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary disabled:opacity-50"
                        >
                          <Plus className="h-3.5 w-3.5" />
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
