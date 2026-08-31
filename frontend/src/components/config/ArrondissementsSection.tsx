/**
 * Section Arrondissements - CRUD avec soft-delete.
 * Formulaire extrait dans ArrondFormView pour respecter les Rules of Hooks.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Pencil, Trash2, Loader2, Landmark } from "lucide-react";
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
  listArrondissements, createArrondissement, updateArrondissement,
  softDeleteArrondissement, type ApiArrondissement,
} from "@/api/arrondissements/arrondissements.api";
import { listDepartements, type ApiDepartement } from "@/api/departements/departements.api";
import { listRegions, type ApiRegion } from "@/api/regions/regions.api";

// --- Formulaire dedié (composant séparé pour respecter les Rules of Hooks) ---

function ArrondFormView({
  selected,
  activeRegions,
  activeDepts,
  deptFilter,
  getDeptName,
  onSave,
  onBack,
}: {
  selected: ApiArrondissement | null;
  activeRegions: ApiRegion[];
  activeDepts: ApiDepartement[];
  deptFilter: string;
  getDeptName: (id: number) => string;
  onSave: (vals: Record<string, string>, deptId: string) => Promise<void> | void;
  onBack: () => void;
}) {
  const t = useT();
  const initialDept = selected
    ? String(selected.departement_id)
    : (deptFilter !== "all" ? deptFilter : "");
  const initialRegion = selected
    ? String(activeDepts.find((d) => d.id === selected.departement_id)?.region_id ?? "")
    : "all";

  const [deptId, setDeptId] = useState(initialDept);
  const [regionId, setRegionId] = useState(initialRegion);

  const visibleDepts = regionId === "all"
    ? activeDepts
    : activeDepts.filter((d) => d.region_id === Number(regionId));

  return (
    <GeoForm
      title={t("arrondissements.create")}
      subtitle={t("arrondissements.subtitle")}
      icon={<Landmark className="h-5 w-5" />}
      initial={selected ? { nom: selected.nom, code: selected.code } : null}
      fields={[
        { key: "nom", label: t("arrondissements.field.nom"), placeholder: "Yaoundé 1er" },
        { key: "code", label: t("arrondissements.field.code"), placeholder: "YDE1", uppercase: true },
      ]}
      isEdit={!!selected}
      onCancel={onBack}
      onSaved={(vals) => onSave(vals, deptId)}
    >
      {!selected && (
        <>
          <div className="space-y-1.5 sm:col-span-2">
            <Label>{t("arrondissements.field.region")}</Label>
            <Select value={regionId} onValueChange={(v) => { setRegionId(v); setDeptId(""); }}>
              <SelectTrigger><SelectValue placeholder={t("common.all")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("common.all")}</SelectItem>
                {activeRegions.map((r) => (
                  <SelectItem key={r.id} value={String(r.id)}>{r.nom}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label>{t("arrondissements.field.departement")} <span className="text-destructive">*</span></Label>
            <Select value={deptId} onValueChange={setDeptId}>
              <SelectTrigger><SelectValue placeholder={t("arrondissements.selectDept")} /></SelectTrigger>
              <SelectContent>
                {visibleDepts.map((d) => (
                  <SelectItem key={d.id} value={String(d.id)}>{d.nom} ({d.code})</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </>
      )}
      {selected && (
        <div className="space-y-1.5 sm:col-span-2">
          <Label>{t("arrondissements.field.departement")}</Label>
          <p className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
            {getDeptName(selected.departement_id)}{" "}
            <span className="text-xs">({t("departements.regionReadOnly")})</span>
          </p>
        </div>
      )}
    </GeoForm>
  );
}

// --- Section principale ---

export function ArrondissementsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiArrondissement | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiArrondissement | null>(null);
  const [deptFilter, setDeptFilter] = useState<string>("all");

  const { data: regionsData } = useQuery({
    queryKey: ["regions", false],
    queryFn: () => listRegions({ page: 1, limit: 1000, is_delete: false }),
  });
  const activeRegions: ApiRegion[] = regionsData?.data?.data ?? [];

  const { data: deptsData } = useQuery({
    queryKey: ["departements", "all"],
    queryFn: () => listDepartements({ page: 1, limit: 1000, is_delete: false }),
  });
  const activeDepts: ApiDepartement[] = deptsData?.data?.data ?? [];
  const getDeptName = (id: number) => activeDepts.find((d) => d.id === id)?.nom ?? `#${id}`;

  const { data, isLoading, isError } = useQuery({
    queryKey: ["arrondissements", deptFilter],
    queryFn: () =>
      listArrondissements({
        page: 1, limit: 1000,
        is_delete: false,
        departement_id: deptFilter !== "all" ? Number(deptFilter) : undefined,
      }),
  });

  const arrondissements: ApiArrondissement[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["arrondissements"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteArrondissement(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDeleteTarget(null); },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const columns: Column<ApiArrondissement>[] = [
    {
      key: "nom", label: t("arrondissements.field.nom"),
      render: (a) => <span className="font-semibold">{a.nom}</span>,
      sortValue: (a) => a.nom,
    },
    {
      key: "code", label: t("arrondissements.field.code"),
      render: (a) => <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{a.code}</code>,
    },
    {
      key: "departement_id", label: t("arrondissements.field.departement"),
      render: (a) => <span className="text-sm text-muted-foreground">{getDeptName(a.departement_id)}</span>,
      sortValue: (a) => getDeptName(a.departement_id),
      exportFormat: (a) => getDeptName(a.departement_id),
    },
  ];

  if (isLoading) return <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground"><Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span></div>;
  if (isError) return <div className="flex h-40 items-center justify-center text-destructive">{t("toast.error")}</div>;

  if (view === "form") {
    return (
      <ArrondFormView
        selected={selected}
        activeRegions={activeRegions}
        activeDepts={activeDepts}
        deptFilter={deptFilter}
        getDeptName={getDeptName}
        onBack={() => setView("liste")}
        onSave={(vals, deptId) => {
          if (selected) {
            return updateArrondissement(selected.id, vals).then(() => {
              invalidate(); toast.success(t("toast.saved")); setView("liste");
            }).catch(() => { toast.error(t("toast.error")); });
          } else {
            if (!deptId) { toast.error(t("arrondissements.deptRequired")); return; }
            return createArrondissement({ ...vals, departement_id: Number(deptId) } as never).then((res) => {
              invalidate();
              const { created, restored } = res.data as { created: number; restored: number };
              if (restored > 0 && created === 0) toast.success(t("arrondissements.toast.restored"));
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
          <h2 className="text-lg font-semibold">{t("cartographie.arrondissements")}</h2>
          <Button className="gap-2" onClick={() => { setSelected(null); setView("form"); }}>
            <Plus className="h-4 w-4" /> {t("arrondissements.create")}
          </Button>
        </div>

        <div className="mb-3 flex flex-wrap items-center gap-2">
          <span className="text-sm text-muted-foreground">{t("arrondissements.filterDept")} :</span>
          <Select value={deptFilter} onValueChange={setDeptFilter}>
            <SelectTrigger className="h-8 w-52 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("common.all")}</SelectItem>
              {activeDepts.map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>{d.nom}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <DataTable
          data={arrondissements}
          columns={columns}
          getRowId={(a) => String(a.id)}
          exportFilename="arrondissements-cameroun"
          exportTitle="Cameroun - Arrondissements"
          searchKeys={["nom", "code"]}
          rowActions={(a) => (
            <>
              <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(a); setView("form"); }} />
              <RowIconButton icon={Trash2} label={t("arrondissements.delete.title")} tone="danger" onClick={() => setDeleteTarget(a)} />
            </>
          )}
        />
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("arrondissements.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>{t("arrondissements.delete.desc")} <span className="font-semibold text-foreground">{deleteTarget?.nom}</span></AlertDialogDescription>
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
