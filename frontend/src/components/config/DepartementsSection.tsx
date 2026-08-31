/**
 * Section Départements — CRUD avec soft-delete.
 * Formulaire extrait dans DeptFormView pour respecter les Rules of Hooks.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Pencil, Trash2, Loader2, Building2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { GeoForm } from "@/components/config/RegionsSection";
import { useT } from "@/utils/i18n";
import {
  listDepartements, createDepartement, updateDepartement,
  softDeleteDepartement, type ApiDepartement,
} from "@/api/departements/departements.api";
import { listRegions, type ApiRegion } from "@/api/regions/regions.api";

// --- Formulaire dedié (composant séparé pour respecter les Rules of Hooks) ---

function DeptFormView({
  selected,
  activeRegions,
  regionFilter,
  getRegionName,
  onSave,
  onBack,
}: {
  selected: ApiDepartement | null;
  activeRegions: ApiRegion[];
  regionFilter: string;
  getRegionName: (id: number) => string;
  onSave: (vals: Record<string, string>, regionId: string) => Promise<void> | void;
  onBack: () => void;
}) {
  const t = useT();
  const [regionId, setRegionId] = useState(
    selected ? String(selected.region_id) : (regionFilter !== "all" ? regionFilter : ""),
  );

  return (
    <GeoForm
      title={t("departements.create")}
      subtitle={t("departements.subtitle")}
      icon={<Building2 className="h-5 w-5" />}
      initial={selected ? { nom: selected.nom, code: selected.code } : null}
      fields={[
        { key: "nom", label: t("departements.field.nom"), placeholder: "Mfoundi" },
        { key: "code", label: t("departements.field.code"), placeholder: "MF", uppercase: true },
      ]}
      isEdit={!!selected}
      onCancel={onBack}
      onSaved={(vals) => onSave(vals, regionId)}
    >
      {!selected && (
        <div className="space-y-1.5 sm:col-span-2">
          <Label>{t("departements.field.region")} <span className="text-destructive">*</span></Label>
          <Select value={regionId} onValueChange={setRegionId}>
            <SelectTrigger><SelectValue placeholder={t("departements.selectRegion")} /></SelectTrigger>
            <SelectContent>
              {activeRegions.map((r) => (
                <SelectItem key={r.id} value={String(r.id)}>{r.nom} ({r.code})</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
      {selected && (
        <div className="space-y-1.5 sm:col-span-2">
          <Label>{t("departements.field.region")}</Label>
          <p className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
            {getRegionName(selected.region_id)}{" "}
            <span className="text-xs">({t("departements.regionReadOnly")})</span>
          </p>
        </div>
      )}
    </GeoForm>
  );
}

// --- Section principale ---

export function DepartementsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiDepartement | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiDepartement | null>(null);
  const [regionFilter, setRegionFilter] = useState<string>("all");

  const { data: regionsData } = useQuery({
    queryKey: ["regions", false],
    queryFn: () => listRegions({ page: 1, limit: 1000, is_delete: false }),
  });
  const activeRegions: ApiRegion[] = regionsData?.data?.data ?? [];
  const getRegionName = (id: number) => activeRegions.find((r) => r.id === id)?.nom ?? `#${id}`;

  const { data, isLoading, isError } = useQuery({
    queryKey: ["departements", regionFilter],
    queryFn: () =>
      listDepartements({
        page: 1, limit: 1000,
        is_delete: false,
        region_id: regionFilter !== "all" ? Number(regionFilter) : undefined,
      }),
  });

  const departements: ApiDepartement[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["departements"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteDepartement(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDeleteTarget(null); },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const columns: Column<ApiDepartement>[] = [
    {
      key: "nom", label: t("departements.field.nom"),
      render: (d) => <span className="font-semibold">{d.nom}</span>,
      sortValue: (d) => d.nom,
    },
    {
      key: "code", label: t("departements.field.code"),
      render: (d) => <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{d.code}</code>,
    },
    {
      key: "region_id", label: t("departements.field.region"),
      render: (d) => <span className="text-sm text-muted-foreground">{getRegionName(d.region_id)}</span>,
      sortValue: (d) => getRegionName(d.region_id),
      exportFormat: (d) => getRegionName(d.region_id),
    },
  ];

  if (isLoading) return <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground"><Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span></div>;
  if (isError) return <div className="flex h-40 items-center justify-center text-destructive">{t("toast.error")}</div>;

  if (view === "form") {
    return (
      <DeptFormView
        selected={selected}
        activeRegions={activeRegions}
        regionFilter={regionFilter}
        getRegionName={getRegionName}
        onBack={() => setView("liste")}
        onSave={(vals, regionId) => {
          if (selected) {
            return updateDepartement(selected.id, vals).then(() => {
              invalidate(); toast.success(t("toast.saved")); setView("liste");
            }).catch(() => { toast.error(t("toast.error")); });
          } else {
            if (!regionId) { toast.error(t("departements.regionRequired")); return; }
            return createDepartement({ ...vals, region_id: Number(regionId) } as never).then((res) => {
              invalidate();
              const { created, restored } = res.data as { created: number; restored: number };
              if (restored > 0 && created === 0) toast.success(t("departements.toast.restored"));
              else if (restored > 0) toast.success(`${created} créé(s), ${restored} restauré(s).`);
              else toast.success(t("toast.saved"));
              setView("liste");
            }).catch((err: unknown) => {
              const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
              const status = (err as { response?: { status?: number } })?.response?.status;
              toast.error(status === 409 ? (msg ?? t("toast.error")) : t("toast.error"));
            });
          }
        }}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("cartographie.departements")}</h2>
          <Button className="gap-2" onClick={() => { setSelected(null); setView("form"); }}>
            <Plus className="h-4 w-4" /> {t("departements.create")}
          </Button>
        </div>

        <div className="mb-3 flex items-center gap-2">
          <span className="text-sm text-muted-foreground">{t("departements.filterRegion")} :</span>
          <Select value={regionFilter} onValueChange={setRegionFilter}>
            <SelectTrigger className="h-8 w-52 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("common.all")}</SelectItem>
              {activeRegions.map((r) => (
                <SelectItem key={r.id} value={String(r.id)}>{r.nom}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <DataTable
          data={departements}
          columns={columns}
          getRowId={(d) => String(d.id)}
          exportFilename="departements-cameroun"
          exportTitle="Cameroun - Départements"
          searchKeys={["nom", "code"]}
          rowActions={(d) => (
            <>
              <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(d); setView("form"); }} />
              <RowIconButton icon={Trash2} label={t("departements.delete.title")} tone="danger" onClick={() => setDeleteTarget(d)} />
            </>
          )}
        />
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("departements.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>{t("departements.delete.desc")} <span className="font-semibold text-foreground">{deleteTarget?.nom}</span></AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => { if (deleteTarget) softDeleteMutation.mutate(deleteTarget.id); }}>
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
