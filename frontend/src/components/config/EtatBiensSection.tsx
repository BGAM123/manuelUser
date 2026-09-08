/**
 * Section de configuration — Gestion des états des biens.
 * CRUD complet : liste (avec filtre type de bien, toggle supprimés),
 * création, édition, suppression logique, restauration.
 *
 * ⚠️ PATCH /etat-biens/{id} effectue une synchronisation COMPLÈTE de
 * asset_type_ids — le formulaire d'édition pré-remplit toujours la liste
 * complète des types actuels et envoie la liste entière mise à jour.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Save,
  ArrowLeft,
  Loader2,
  RotateCcw,
  AlertCircle,
  Tag,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
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
import { Switch } from "@/components/ui/switch";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { SearchableMultiSelect } from "@/components/shared/SearchableMultiSelect";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listEtatBiens,
  createEtatBien,
  updateEtatBien,
  softDeleteEtatBien,
  restoreEtatBien,
  type ApiEtatBien,
  type EtatBienPayload,
} from "@/api/etat-biens/etat-biens.api";
import {
  listAssetTypes,
  type ApiAssetType,
} from "@/api/asset-types/asset-types.api";

type EBView = "liste" | "form";

// ─── Section principale ─────────────────────────────────────────────────────

export function EtatBiensSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<EBView>("liste");
  const [selected, setSelected] = useState<ApiEtatBien | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiEtatBien | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);
  const [assetTypeFilter, setAssetTypeFilter] = useState<string>("all");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["etat-biens", showDeleted, assetTypeFilter],
    queryFn: () =>
      listEtatBiens({
        page: 1,
        limit: 1000,
        is_delete: showDeleted,
        asset_type_id: assetTypeFilter !== "all" ? Number(assetTypeFilter) : undefined,
      }),
  });

  // Tri par numéro d'ordre — les états sans numéro passent en fin de liste,
  // triés entre eux par nom.
  const etatBiens: ApiEtatBien[] = [...(data?.data?.data ?? [])].sort((a, b) => {
    if (a.numeroOrdre != null && b.numeroOrdre != null) return a.numeroOrdre - b.numeroOrdre;
    if (a.numeroOrdre != null) return -1;
    if (b.numeroOrdre != null) return 1;
    return a.nom.localeCompare(b.nom, "fr");
  });

  // Types de biens actifs pour les filtres
  const { data: atData } = useQuery({
    queryKey: ["asset-types"],
    queryFn: () => listAssetTypes({ page: 1, limit: 1000, include_deleted: false }),
  });
  const allAssetTypes: ApiAssetType[] = atData?.data?.data ?? [];

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["etat-biens"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteEtatBien(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => {
      toast.error(t("toast.error"));
      setDeleteTarget(null);
    },
  });

  const restoreMutation = useMutation({
    mutationFn: (id: number) => restoreEtatBien(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.saved"));
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiEtatBien>[] = [
    {
      key: "nom",
      label: t("common.name"),
      render: (eb) => <span className="font-semibold">{eb.nom}</span>,
      sortValue: (eb) => eb.nom,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (eb) => (
        <span className="text-xs text-muted-foreground">{eb.description ?? "—"}</span>
      ),
    },
    {
      key: "numeroOrdre",
      label: t("etatBiens.field.numeroOrdre"),
      render: (eb) => (
        <span className="tabular-nums text-xs text-muted-foreground">
          {eb.numeroOrdre ?? "—"}
        </span>
      ),
      sortValue: (eb) => eb.numeroOrdre ?? Number.MAX_SAFE_INTEGER,
    },
    {
      key: "assetTypes",
      label: t("etatBiens.field.assetTypes"),
      render: (eb) => {
        const types = eb.assetTypes ?? [];
        if (types.length === 0)
          return <span className="text-xs text-muted-foreground">—</span>;
        const visible = types.slice(0, 3);
        const rest = types.length - visible.length;
        return (
          <div className="flex flex-wrap gap-1">
            {visible.map((at) => (
              <Badge key={at.id} variant="secondary" className="text-xs">
                {at.nom}
              </Badge>
            ))}
            {rest > 0 && (
              <Popover>
                <PopoverTrigger asChild>
                  <button type="button" onClick={(e) => e.stopPropagation()}>
                    <Badge variant="outline" className="cursor-pointer text-xs hover:bg-muted">
                      +{rest}
                    </Badge>
                  </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-64 p-3" onClick={(e) => e.stopPropagation()}>
                  <p className="mb-2 text-xs font-semibold text-foreground">
                    {t("etatBiens.assetTypesCount", { count: types.length })}
                  </p>
                  <div className="flex max-h-52 flex-wrap gap-1 overflow-y-auto">
                    {types.map((at) => (
                      <Badge key={at.id} variant="secondary" className="text-xs">
                        {at.nom}
                      </Badge>
                    ))}
                  </div>
                </PopoverContent>
              </Popover>
            )}
          </div>
        );
      },
      exportFormat: (eb) =>
        (eb.assetTypes ?? []).map((at) => at.nom).join(", ") || "—",
    },
    {
      key: "is_delete",
      label: t("common.status"),
      render: (eb) =>
        eb.is_delete ? (
          <Badge variant="outline" className="text-xs text-muted-foreground">
            {t("common.deleted")}
          </Badge>
        ) : (
          <Badge
            variant="default"
            className="border-emerald-300 bg-emerald-500/15 text-xs text-emerald-700"
          >
            {t("status.active")}
          </Badge>
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
      <div className="flex h-40 items-center justify-center gap-2 text-destructive">
        <AlertCircle className="h-5 w-5" />
        <span>{t("toast.error")}</span>
      </div>
    );
  }

  if (view === "form") {
    return (
      <EtatBienForm
        initial={selected}
        allAssetTypes={allAssetTypes}
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
          <h2 className="text-lg font-semibold">{t("config.etatBiens")}</h2>
          <CanAccess permission="creation_etat_bien">
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("etatBiens.create")}
            </Button>
          </CanAccess>
        </div>

        {/* Filtres */}
        <div className="mb-3 flex flex-wrap items-center gap-4">
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">{t("etatBiens.filterByType")}</span>
            <div className="w-52">
              <SearchableSelect
                value={assetTypeFilter !== "all" ? Number(assetTypeFilter) : null}
                onChange={(v) => setAssetTypeFilter(v != null ? String(v) : "all")}
                options={allAssetTypes.map((at) => ({ value: at.id, label: at.nom }))}
                placeholder={t("etatBiens.allTypes")}
                searchPlaceholder={t("biens.form.searchType")}
              />
            </div>
          </div>
          <label className="flex cursor-pointer items-center gap-2 text-sm">
            <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
            {t("consumables.showDeleted")}
          </label>
        </div>

        <DataTable
          data={etatBiens}
          columns={columns}
          getRowId={(eb) => String(eb.id)}
          exportFilename="etat-biens-minepia"
          exportTitle={`MINEPIA — ${t("config.etatBiens")}`}
          searchKeys={["nom"]}
          rowActions={(eb) =>
            eb.is_delete ? (
              <CanAccess permission="creation_etat_bien">
                <RowIconButton
                  icon={RotateCcw}
                  label={t("action.restore")}
                  onClick={() => restoreMutation.mutate(eb.id)}
                />
              </CanAccess>
            ) : (
              <CanAccess permission="creation_etat_bien">
                <RowIconButton
                  icon={Pencil}
                  label={t("action.edit")}
                  onClick={() => {
                    setSelected(eb);
                    setView("form");
                  }}
                />
                <RowIconButton
                  icon={Trash2}
                  label={t("action.delete")}
                  tone="danger"
                  onClick={() => setDeleteTarget(eb)}
                />
              </CanAccess>
            )
          }
        />
      </div>

      <AlertDialog
        open={!!deleteTarget}
        onOpenChange={(v) => !v && setDeleteTarget(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("etatBiens.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("etatBiens.delete.descPrefix")} <strong>{deleteTarget?.nom}</strong>{" "}
              {t("etatBiens.delete.descSuffix")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() =>
                deleteTarget && softDeleteMutation.mutate(deleteTarget.id)
              }
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire création / édition ──────────────────────────────────────────

function EtatBienForm({
  initial,
  allAssetTypes,
  onCancel,
  onSaved,
}: {
  initial: ApiEtatBien | null;
  allAssetTypes: ApiAssetType[];
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [numeroOrdre, setNumeroOrdre] = useState(
    initial?.numeroOrdre != null ? String(initial.numeroOrdre) : "",
  );
  const [description, setDescription] = useState(initial?.description ?? "");
  const [assetTypeIds, setAssetTypeIds] = useState<number[]>(
    (initial?.assetTypes ?? []).map((at) => at.id),
  );

  const atOptions = allAssetTypes.map((at) => ({ value: at.id, label: at.nom }));

  const createMutation = useMutation({
    mutationFn: (payload: EtatBienPayload) => createEtatBien(payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response
        ?.data?.message;
      toast.error(
        status === 409
          ? (msg ?? t("etatBiens.error.duplicateName"))
          : status === 400
            ? t("etatBiens.error.invalidType")
            : t("toast.error"),
      );
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: EtatBienPayload }) =>
      updateEtatBien(id, payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) {
      toast.error(t("etatBiens.error.nameRequired"));
      return;
    }
    if (numeroOrdre.trim() && Number(numeroOrdre) < 0) {
      toast.error(t("etatBiens.error.negativeOrder"));
      return;
    }
    const payload: EtatBienPayload = {
      nom: nom.trim(),
      numeroOrdre: numeroOrdre.trim() ? Number(numeroOrdre) : undefined,
      // Toujours envoyer une chaîne explicite (jamais undefined) — le
      // backend traite un champ absent du payload comme "ne pas y toucher",
      // pas comme "l'effacer". Omettre description quand le champ est vidé
      // laissait donc l'ancienne valeur en place côté serveur malgré le
      // toast de succès affiché.
      description: description.trim(),
      asset_type_ids: assetTypeIds,
    };
    if (!initial) {
      createMutation.mutate(payload);
    } else {
      updateMutation.mutate({ id: initial.id, payload });
    }
  };

  return (
    <div className="mx-auto w-full max-w-xl space-y-3">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onCancel}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Tag className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-base font-semibold">
              {initial ? t("etatBiens.form.editTitle", { name: initial.nom }) : t("etatBiens.form.createTitle")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("etatBiens.form.subtitle")}
            </p>
          </div>
        </div>

        <div className="space-y-4 px-5 py-5">
          <div className="space-y-1.5">
            <Label>
              {t("common.name")} <span className="text-destructive">*</span>
            </Label>
            <Input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              placeholder="Ex : En panne, En maintenance, Hors service..."
              required
            />
          </div>

          <div className="space-y-1.5">
            <Label>{t("etatBiens.field.numeroOrdre")}</Label>
            <p className="text-xs text-muted-foreground">
              {t("etatBiens.form.numeroOrdreHint")}
            </p>
            <Input
              type="number"
              min={0}
              value={numeroOrdre}
              onChange={(e) => setNumeroOrdre(e.target.value)}
              placeholder="Ex : 1"
            />
          </div>

          <div className="space-y-1.5">
            <Label>{t("common.description")}</Label>
            <Textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Ex : Bien en panne de fonctionnement"
              rows={3}
            />
          </div>

          <div className="space-y-1.5">
            <Label>{t("etatBiens.field.assetTypes")}</Label>
            <p className="text-xs text-muted-foreground">
              {t("etatBiens.form.assetTypesHint")}
            </p>
            <SearchableMultiSelect
              options={atOptions}
              value={assetTypeIds}
              onChange={setAssetTypeIds}
              placeholder={t("etatBiens.form.searchAssetType")}
            />
          </div>
        </div>

        <div className="flex justify-end gap-2 border-t border-border px-5 py-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            {t("action.cancel")}
          </Button>
          <Button type="submit" disabled={isPending} className="gap-2">
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
