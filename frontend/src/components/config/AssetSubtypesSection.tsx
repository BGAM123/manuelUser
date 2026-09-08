/**
 * Section de configuration — Gestion des sous-types de biens patrimoniaux.
 * CRUD complet : liste (avec filtre type de bien), création, édition, suppression logique.
 * Calquée exactement sur AssetTypesSection.
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
  Layers,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
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
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listAssetSubtypes,
  createAssetSubtype,
  updateAssetSubtype,
  softDeleteAssetSubtype,
  type ApiAssetSubtype,
  type CreateAssetSubtypePayload,
  type UpdateAssetSubtypePayload,
} from "@/api/asset-subtypes/asset-subtypes.api";
import {
  listAssetTypes,
  type ApiAssetType,
} from "@/api/asset-types/asset-types.api";

type ASTView = "liste" | "form";

// ─── Section principale ─────────────────────────────────────────────────────

export function AssetSubtypesSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<ASTView>("liste");
  const [selected, setSelected] = useState<ApiAssetSubtype | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiAssetSubtype | null>(null);
  const [typeFilter, setTypeFilter] = useState<string>("all");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["asset-subtypes", typeFilter],
    queryFn: () =>
      listAssetSubtypes({
        page: 1,
        limit: 1000,
        include_deleted: false,
        asset_type_id: typeFilter !== "all" ? Number(typeFilter) : undefined,
      }),
  });

  const subtypes: ApiAssetSubtype[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  // Tous les types de biens actifs pour les filtres et formulaires
  const { data: typesData } = useQuery({
    queryKey: ["asset-types"],
    queryFn: () => listAssetTypes({ page: 1, limit: 1000, include_deleted: false }),
  });
  const allTypes: ApiAssetType[] = typesData?.data?.data ?? [];
  const getTypeName = (id: number) =>
    allTypes.find((t) => t.id === id)?.nom ?? `#${id}`;

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ["asset-subtypes"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteAssetSubtype(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const columns: Column<ApiAssetSubtype>[] = [
    {
      key: "nom",
      label: t("assetSubtypes.field.nom"),
      render: (s) => <span className="font-semibold">{s.nom}</span>,
      sortValue: (s) => s.nom,
    },
    {
      key: "asset_type_id",
      label: t("assetSubtypes.field.type"),
      render: (s) => {
        const type = allTypes.find((at) => at.id === s.asset_type_id);
        return (
          <span className="text-sm">
            {type ? type.nom : `#${s.asset_type_id}`}
          </span>
        );
      },
      sortValue: (s) => getTypeName(s.asset_type_id),
      exportFormat: (s) => getTypeName(s.asset_type_id),
    },
    {
      key: "description",
      label: t("assetSubtypes.field.description"),
      render: (s) => (
        <span className="text-sm text-muted-foreground">{s.description || "—"}</span>
      ),
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
    return (
      <AssetSubtypeForm
        initial={selected}
        allTypes={allTypes}
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
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("config.subtypes")}</h2>
          <div className="flex flex-wrap items-center gap-3">
            <CanAccess permission="creation_type_bien">
              <Button
                className="gap-2"
                onClick={() => {
                  setSelected(null);
                  setView("form");
                }}
              >
                <Plus className="h-4 w-4" /> {t("assetSubtypes.create")}
              </Button>
            </CanAccess>
          </div>
        </div>

        {/* Filtre par type de bien */}
        <div className="mb-3 flex items-center gap-2">
          <span className="text-sm text-muted-foreground">
            {t("assetSubtypes.filterType")} :
          </span>
          <Select value={typeFilter} onValueChange={setTypeFilter}>
            <SelectTrigger className="h-8 w-56 text-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("common.all")}</SelectItem>
              {allTypes.map((at) => (
                <SelectItem key={at.id} value={String(at.id)}>
                  {at.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <DataTable
          data={subtypes}
          columns={columns}
          getRowId={(s) => String(s.id)}
          exportFilename="sous-types-biens-minepia"
          exportTitle="MINEPIA — Sous-types de biens"
          searchKeys={["nom", "description"]}
          rowActions={(s) => (
            <CanAccess permission="creation_type_bien">
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => {
                  setSelected(s);
                  setView("form");
                }}
              />
              <RowIconButton
                icon={Trash2}
                label={t("assetSubtypes.delete.title")}
                tone="danger"
                onClick={() => setDeleteTarget(s)}
              />
            </CanAccess>
          )}
        />
      </div>

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("assetSubtypes.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("assetSubtypes.delete.desc")}{" "}
              <span className="font-semibold text-foreground">
                {deleteTarget?.nom}
              </span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) softDeleteMutation.mutate(deleteTarget.id);
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

// ─── Formulaire création / édition ─────────────────────────────────────────

function AssetSubtypeForm({
  initial,
  allTypes,
  onCancel,
  onSaved,
}: {
  initial: ApiAssetSubtype | null;
  allTypes: ApiAssetType[];
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [assetTypeId, setAssetTypeId] = useState<string>(
    initial?.asset_type_id ? String(initial.asset_type_id) : "",
  );

  const createMutation = useMutation({
    mutationFn: (payload: CreateAssetSubtypePayload) => createAssetSubtype(payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateAssetSubtypePayload }) =>
      updateAssetSubtype(id, payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim() || !assetTypeId) return;
    const payload = {
      nom: nom.trim(),
      description: description.trim() || undefined,
      asset_type_id: Number(assetTypeId),
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
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>

      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Layers className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial
                ? `${t("action.edit")} — ${initial.nom}`
                : t("assetSubtypes.create")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("assetSubtypes.subtitle")}
            </p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("assetSubtypes.field.nom")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Ex : Véhicule léger 4x4"
                required
              />
            </div>

            <div className="space-y-1.5">
              <Label>
                {t("assetSubtypes.field.type")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Select value={assetTypeId} onValueChange={setAssetTypeId} required>
                <SelectTrigger>
                  <SelectValue placeholder={t("assetSubtypes.selectType")} />
                </SelectTrigger>
                <SelectContent>
                  {allTypes.map((at) => (
                    <SelectItem key={at.id} value={String(at.id)}>
                      {at.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label>{t("assetSubtypes.field.description")}</Label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Description facultative…"
                rows={3}
              />
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending || !assetTypeId}>
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
