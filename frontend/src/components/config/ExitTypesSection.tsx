/**
 * Section Types de sortie — CRUD complet.
 * Champs : Nom, Code, Description, Statut (actif/inactif).
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Loader2,
  LogOut,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
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
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listExitTypes,
  getExitTypeById,
  createExitType,
  updateExitType,
  deleteExitType,
  type ApiExitType,
} from "@/api/exit-types/exit-types.api";

// ─── Helper erreur API ────────────────────────────────────────────────────────

function getApiError(err: unknown): string {
  const res = (
    err as {
      response?: {
        data?: {
          message?: string;
          detail?: string;
          data?: Record<string, string>;
        };
        status?: number;
      };
    }
  )?.response;
  // Erreurs de validation avec champ → message de champ
  if (res?.data?.data && typeof res.data.data === "object") {
    const firstField = Object.values(res.data.data)[0];
    if (firstField) return `Validation : ${firstField}`;
  }
  return res?.data?.message ?? res?.data?.detail ?? "Une erreur est survenue";
}

// ─── Formulaire création / édition ───────────────────────────────────────────

function ExitTypeForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiExitType | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();

  const [nom, setNom] = useState(initial?.nom ?? "");
  const [code, setCode] = useState(initial?.code ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [isActive, setIsActive] = useState(initial?.isActive ?? true);
  const [beneficiaire, setBeneficiaire] = useState(initial?.beneficiaire ?? false);

  const createMutation = useMutation({
    mutationFn: () =>
      createExitType({
        nom: nom.trim(),
        code: code.trim().toUpperCase(),
        description: description.trim() || undefined,
        isActive,
        beneficiaire,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["exitTypes"] });
      toast.success(t("toast.saved"));
      onSaved();
    },
    onError: (err) => toast.error(getApiError(err)),
  });

  const updateMutation = useMutation({
    mutationFn: () =>
      updateExitType(initial!.id, {
        nom: nom.trim(),
        code: code.trim().toUpperCase(),
        description: description.trim() || undefined,
        isActive,
        beneficiaire,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["exitTypes"] });
      toast.success(t("toast.saved"));
      onSaved();
    },
    onError: (err) => toast.error(getApiError(err)),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSave = () => {
    if (!nom.trim() || !code.trim()) return;
    if (initial) updateMutation.mutate();
    else createMutation.mutate();
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label>
            {t("common.name")} <span className="text-destructive">*</span>
          </Label>
          <Input
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            placeholder="Ex : Réforme"
            autoFocus
          />
        </div>
        <div className="space-y-1.5">
          <Label>
            {t("common.code")} <span className="text-destructive">*</span>
          </Label>
          <Input
            value={code}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
            placeholder="Ex : REFORME"
          />
          <p className="text-xs text-muted-foreground">{t("exitTypes.form.codeHint")}</p>
        </div>
      </div>
      <div className="space-y-1.5">
        <Label>{t("common.description")}</Label>
        <Textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder={t("common.optionalDescription")}
          rows={2}
        />
      </div>
      <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
        <Switch id="exit-type-beneficiaire" checked={beneficiaire} onCheckedChange={setBeneficiaire} />
        <div>
          <Label htmlFor="exit-type-beneficiaire" className="cursor-pointer">
            {t("exitTypes.form.beneficiaireLabel")}
          </Label>
          <p className="text-xs text-muted-foreground">
            {t("exitTypes.form.beneficiaireHint")}
          </p>
        </div>
      </div>
      <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
        <Switch id="exit-type-active" checked={isActive} onCheckedChange={setIsActive} />
        <Label htmlFor="exit-type-active" className="cursor-pointer">
          {t("exitTypes.form.activeLabel")}
        </Label>
      </div>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="ghost" onClick={onCancel} disabled={isPending}>
          {t("action.cancel")}
        </Button>
        <Button
          type="button"
          onClick={handleSave}
          disabled={!nom.trim() || !code.trim() || isPending}
          className="gap-2"
        >
          {isPending && <Loader2 className="h-4 w-4 animate-spin" />}
          {t("action.save")}
        </Button>
      </div>
    </div>
  );
}

// ─── Section principale exportée ─────────────────────────────────────────────

export function ExitTypesSection() {
  const t = useT();
  const queryClient = useQueryClient();

  const [dialog, setDialog] = useState<{ open: boolean; item: ApiExitType | null }>({
    open: false,
    item: null,
  });
  const [deleteTarget, setDeleteTarget] = useState<ApiExitType | null>(null);
  const [loadingEditId, setLoadingEditId] = useState<number | null>(null);
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("all");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["exitTypes"],
    queryFn: () => listExitTypes({ limit: 1000, include_inactive: true, order_by: "nom", order_dir: "ASC" }),
  });

  const allItems: ApiExitType[] = data?.data?.items ?? [];
  const items: ApiExitType[] = allItems.filter((et) =>
    statusFilter === "all" ? true : statusFilter === "active" ? et.isActive : !et.isActive,
  );

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteExitType(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["exitTypes"] });
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: (err) => toast.error(getApiError(err)),
  });

  const toggleActiveMutation = useMutation({
    mutationFn: ({ id, isActive }: { id: number; isActive: boolean }) =>
      updateExitType(id, { isActive }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["exitTypes"] }),
    onError: (err) => toast.error(getApiError(err)),
  });

  // Ouvre le dialog édition en chargeant les données fraîches via l'API
  const handleEdit = async (item: ApiExitType) => {
    setLoadingEditId(item.id);
    try {
      const res = await getExitTypeById(item.id);
      setDialog({ open: true, item: res.data });
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setLoadingEditId(null);
    }
  };

  const columns: Column<ApiExitType>[] = [
    {
      key: "nom",
      label: t("common.name"),
      render: (et) => <span className="font-medium">{et.nom}</span>,
      sortValue: (et) => et.nom,
    },
    {
      key: "code",
      label: t("common.code"),
      render: (et) => (
        <code className="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">{et.code}</code>
      ),
      sortValue: (et) => et.code,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (et) => (
        <span className="text-sm text-muted-foreground">{et.description ?? "—"}</span>
      ),
    },
    {
      key: "isActive",
      label: t("common.status"),
      render: (et) => (
        <CanAccess
          permission="creation_type_sortie"
          fallback={
            <Badge variant={et.isActive ? "default" : "secondary"} className="text-xs">
              {et.isActive ? t("status.active") : t("common.inactive")}
            </Badge>
          }
        >
          <label className="flex cursor-pointer items-center gap-2">
            <Switch
              checked={et.isActive}
              onCheckedChange={(v) => toggleActiveMutation.mutate({ id: et.id, isActive: v })}
              aria-label={et.isActive ? t("status.active") : t("common.inactive")}
            />
            <Badge variant={et.isActive ? "default" : "secondary"} className="text-xs">
              {et.isActive ? t("status.active") : t("common.inactive")}
            </Badge>
          </label>
        </CanAccess>
      ),
    },
    {
      key: "actions",
      label: t("common.actions"),
      render: (et) => (
        <div className="flex items-center gap-1">
          <CanAccess permission="creation_type_sortie">
            {loadingEditId === et.id ? (
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
              </span>
            ) : (
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => handleEdit(et)}
              />
            )}
            <RowIconButton
              icon={Trash2}
              label={t("action.delete")}
              onClick={() => setDeleteTarget(et)}
              tone="danger"
            />
          </CanAccess>
        </div>
      ),
    },
  ];

  return (
    <>
      {/* En-tête */}
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <LogOut className="h-5 w-5 text-primary" />
          <div>
            <h2 className="font-semibold text-foreground">{t("nav.group.categories.exitTypes")}</h2>
            <p className="text-xs text-muted-foreground">
              {t("exitTypes.subtitle")}
            </p>
          </div>
        </div>
        <CanAccess permission="creation_type_sortie">
          <Button
            size="sm"
            className="gap-2"
            onClick={() => setDialog({ open: true, item: null })}
          >
            <Plus className="h-4 w-4" /> {t("exitTypes.create")}
          </Button>
        </CanAccess>
      </div>

      <div className="mb-3 flex items-center gap-2">
        <span className="text-sm text-muted-foreground">{t("exitTypes.filterByStatus")}</span>
        <div className="w-40">
          <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v as typeof statusFilter)}>
            <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("common.all")}</SelectItem>
              <SelectItem value="active">{t("status.active")}</SelectItem>
              <SelectItem value="inactive">{t("common.inactive")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-48 items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>{t("common.loading")}</span>
        </div>
      ) : isError ? (
        <div className="flex h-48 items-center justify-center text-destructive">
          {t("toast.error")}
        </div>
      ) : (
        <DataTable<ApiExitType>
          data={items}
          columns={columns}
          getRowId={(et) => String(et.id)}
          exportFilename="types-de-sortie"
          exportTitle={t("nav.group.categories.exitTypes")}
          searchKeys={["nom", "code", "description"]}
          emptyMessage={t("exitTypes.empty")}
        />
      )}

      {/* Dialog création / édition */}
      <Dialog
        open={dialog.open}
        onOpenChange={(o) => !o && setDialog({ open: false, item: null })}
      >
        <DialogContent
          key={dialog.item ? `edit-${dialog.item.id}` : "create"}
          className="max-w-lg"
        >
          <DialogHeader>
            <DialogTitle>
              {dialog.item ? t("exitTypes.dialog.edit") : t("exitTypes.dialog.create")}
            </DialogTitle>
          </DialogHeader>
          {dialog.open && (
            <ExitTypeForm
              initial={dialog.item}
              onCancel={() => setDialog({ open: false, item: null })}
              onSaved={() => setDialog({ open: false, item: null })}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* Confirmation de suppression */}
      <AlertDialog
        open={!!deleteTarget}
        onOpenChange={(o) => !o && setDeleteTarget(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("exitTypes.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("exitTypes.delete.prefix")} <strong>« {deleteTarget?.nom} »</strong>{" "}
              {t("exitTypes.delete.suffix", { code: deleteTarget?.code ?? "" })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>
              {t("action.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                t("action.delete")
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
