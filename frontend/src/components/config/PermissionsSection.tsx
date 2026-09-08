/**
 * Section de configuration — Gestion des permissions.
 * CRUD complet : liste, création, édition, suppression avec confirmation.
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
  ShieldCheck,
  Archive,
  RotateCcw,
  Copy,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
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
  listPermissions,
  createPermissions,
  updatePermission,
  deletePermission,
  softDeletePermission,
  restorePermission,
  type ApiPermission,
  type CreatePermissionPayload,
  type UpdatePermissionPayload,
} from "@/api/permissions/permissions.api";

// ─── Section principale ─────────────────────────────────────────────────────

export function PermissionsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiPermission | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ApiPermission | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<ApiPermission | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiPermission | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["permissions", showDeleted],
    queryFn: () => listPermissions({ page: 1, limit: 1000, is_delete: showDeleted }),
  });

  const permissions = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["permissions"] });

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      updatePermission(id, { is_active }),
    onSuccess: invalidate,
    onError: () => toast.error(t("toast.error")),
  });

  const archiveMutation = useMutation({
    mutationFn: (id: number) => softDeletePermission(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setArchiveTarget(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const restoreMutation = useMutation({
    mutationFn: (id: number) => restorePermission(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setRestoreTarget(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deletePermission(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiPermission>[] = [
    {
      key: "nom",
      label: t("permissions.field.nom"),
      render: (p) => (
        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold">
          {p.nom}
        </code>
      ),
      sortValue: (p) => p.nom,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (p) => (
        <span className="text-xs text-muted-foreground">{p.description || "—"}</span>
      ),
    },
    {
      key: "is_active",
      label: t("common.status"),
      render: (p) =>
        showDeleted ? (
          <span className="text-xs font-medium text-muted-foreground">
            {p.is_active ? t("status.active") : t("common.inactive")}
          </span>
        ) : (
          <label className="flex cursor-pointer items-center gap-2">
            <Switch
              checked={p.is_active}
              onCheckedChange={(checked) =>
                toggleMutation.mutate({ id: p.id, is_active: checked })
              }
            />
            <span className="text-xs font-medium">
              {p.is_active ? t("status.active") : t("common.inactive")}
            </span>
          </label>
        ),
      exportFormat: (p) => (p.is_active ? t("status.active") : t("common.inactive")),
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
      queryClient.invalidateQueries({ queryKey: ["permissions"] });
      setView("liste");
    };
    return selected ? (
      <PermissionForm initial={selected} onCancel={() => setView("liste")} onSaved={() => { toast.success(t("toast.saved")); onSaved(); }} />
    ) : (
      <PermissionBulkCreateForm onCancel={() => setView("liste")} onSaved={onSaved} />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("config.permissions")}</h2>
          <div className="flex items-center gap-3">
            <label className="flex cursor-pointer items-center gap-2 text-sm">
              <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
              {t("common.showDeleted")}
            </label>
            <CanAccess permission="creation_permission">
              <Button
                className="gap-2"
                onClick={() => {
                  setSelected(null);
                  setView("form");
                }}
              >
                <Plus className="h-4 w-4" /> {t("permissions.create")}
              </Button>
            </CanAccess>
          </div>
        </div>
        <DataTable
          data={permissions}
          columns={columns}
          getRowId={(p) => String(p.id)}
          exportFilename="permissions-minepia"
          exportTitle="MINEPIA — Permissions"
          searchKeys={["nom", "description"]}
          rowActions={(p) => (
            <>
              {!showDeleted && (
                <CanAccess permission="creation_permission">
                  <RowIconButton
                    icon={Pencil}
                    label={t("action.edit")}
                    onClick={() => {
                      setSelected(p);
                      setView("form");
                    }}
                  />
                </CanAccess>
              )}
              <CanAccess permission="creation_permission">
                {showDeleted ? (
                  <RowIconButton icon={RotateCcw} label={t("action.restore")} onClick={() => setRestoreTarget(p)} />
                ) : (
                  <RowIconButton icon={Archive} label={t("action.delete")} onClick={() => setArchiveTarget(p)} />
                )}
              </CanAccess>
              {showDeleted && (
                <CanAccess permission="creation_permission">
                  <RowIconButton
                    icon={Trash2}
                    label={t("common.permanentDelete")}
                    tone="danger"
                    onClick={() => setDeleteTarget(p)}
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
            <AlertDialogTitle>{t("permissions.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("permissions.archive.desc.before")}{" "}
              <span className="font-semibold text-foreground">{archiveTarget?.nom}</span>{" "}
              {t("permissions.archive.desc.after")}
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
            <AlertDialogTitle>{t("permissions.restore.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("permissions.archive.desc.before")}{" "}
              <span className="font-semibold text-foreground">{restoreTarget?.nom}</span>{" "}
              {t("permissions.restore.desc.after")}
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
              {t("permissions.delete.desc")}{" "}
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

// ─── Formulaire édition (une seule permission) ─────────────────────────────

function PermissionForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiPermission;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial.nom);
  const [description, setDescription] = useState(initial.description ?? "");
  const [isActive, setIsActive] = useState(initial.is_active);

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePermissionPayload }) =>
      updatePermission(id, payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    updateMutation.mutate({
      id: initial.id,
      payload: { nom: nom.trim().toUpperCase(), description, is_active: isActive },
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
            <ShieldCheck className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {t("action.edit")} — {initial.nom}
            </h2>
            <p className="text-xs text-muted-foreground">{t("permissions.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("permissions.field.nom")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value.toUpperCase())}
                placeholder="USER_CREATE"
                required
              />
              <p className="text-xs text-muted-foreground">
                {t("permissions.field.nom.hint")}
              </p>
            </div>
            <div className="space-y-1.5">
              <Label>{t("common.description")}</Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder={t("permissions.field.description.placeholder")}
              />
            </div>
            <div className="flex items-center gap-3">
              <Switch
                id="perm-active"
                checked={isActive}
                onCheckedChange={setIsActive}
              />
              <Label htmlFor="perm-active">
                {isActive ? t("status.active") : t("common.inactive")}
              </Label>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={updateMutation.isPending}
          >
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={updateMutation.isPending}>
            {updateMutation.isPending ? (
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

// ─── Formulaire création — plusieurs permissions en un seul envoi ──────────
// POST /permissions accepte un tableau (confirmé, 2026-08-29) : ce formulaire
// permet d'ajouter autant de lignes que nécessaire avant un unique envoi
// groupé, plutôt qu'un aller-retour par permission.

let bulkRowSeq = 0;
interface PermissionDraftRow {
  key: number;
  nom: string;
  description: string;
  is_active: boolean;
}
function emptyPermissionRow(): PermissionDraftRow {
  return { key: ++bulkRowSeq, nom: "", description: "", is_active: true };
}

function PermissionBulkCreateForm({
  onCancel,
  onSaved,
}: {
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [rows, setRows] = useState<PermissionDraftRow[]>([emptyPermissionRow()]);

  const updateRow = (key: number, patch: Partial<PermissionDraftRow>) =>
    setRows((prev) => prev.map((r) => (r.key === key ? { ...r, ...patch } : r)));
  const addRow = () => setRows((prev) => [...prev, emptyPermissionRow()]);
  const duplicateRow = (row: PermissionDraftRow) =>
    setRows((prev) => [...prev, { ...row, key: ++bulkRowSeq, nom: "" }]);
  const removeRow = (key: number) => setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.key !== key) : prev));

  const createMutation = useMutation({
    mutationFn: (payloads: CreatePermissionPayload[]) => createPermissions(payloads),
    onSuccess: (res) => {
      const count = res.data?.length ?? rows.length;
      toast.success(
        count > 1
          ? t("permissions.bulkCreate.successPlural", { count })
          : t("permissions.bulkCreate.success", { count }),
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
      rows.map((r) => ({ nom: r.nom.trim().toUpperCase(), description: r.description, is_active: r.is_active })),
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
            <ShieldCheck className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">{t("permissions.create")}</h2>
            <p className="text-xs text-muted-foreground">
              {t("permissions.bulkCreate.subtitle")}
            </p>
          </div>
        </div>

        <div className="space-y-4 px-5 py-5 sm:px-6 sm:py-6">
          {rows.map((row, idx) => (
            <div key={row.key} className="rounded-xl border border-dashed border-border p-4">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-xs font-semibold text-muted-foreground">{t("permissions.bulkCreate.rowLabel", { index: idx + 1 })}</span>
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
                    {t("permissions.field.nom")} <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={row.nom}
                    onChange={(e) => updateRow(row.key, { nom: e.target.value.toUpperCase() })}
                    placeholder="USER_CREATE"
                    required
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>{t("common.description")}</Label>
                  <Input
                    value={row.description}
                    onChange={(e) => updateRow(row.key, { description: e.target.value })}
                    placeholder={t("permissions.field.description.placeholder")}
                  />
                </div>
                <div className="flex items-center gap-3">
                  <Switch checked={row.is_active} onCheckedChange={(v) => updateRow(row.key, { is_active: v })} />
                  <Label>{row.is_active ? t("status.active") : t("common.inactive")}</Label>
                </div>
              </div>
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" className="gap-2" onClick={addRow}>
            <Plus className="h-4 w-4" /> {t("permissions.bulkCreate.addRow")}
          </Button>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={createMutation.isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={createMutation.isPending}>
            {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {rows.length > 1 ? t("permissions.bulkCreate.createCount", { count: rows.length }) : t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
