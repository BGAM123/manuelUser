/**
 * Page Biens en maintenance — page dédiée (panneau flottant), présente
 * GET /assets/maintenances/en-cours : biens ayant une maintenance ouverte,
 * regroupés par catégorie (coût total, nombre de maintenances, seuil hérité
 * de la catégorie). Vue à deux niveaux :
 *   1. Liste des catégories (agrégats)
 *   2. Détail des biens en maintenance d'une catégorie, au clic sur sa ligne
 *      — lignes surlignées en rouge si bien.seuil_depasse est vrai.
 */
import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ChevronLeft, ChevronRight, Wrench } from "lucide-react";
import { AppShell } from "@/components/shared/AppShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ExportButton, type ExportColumn } from "@/components/shared/ExportButton";
import { RemoteSearchSelect } from "@/components/shared/RemoteSearchSelect";
import { OrgTreeSelect } from "@/components/shared/OrgTreeSelect";
import { YearStepper } from "@/components/shared/YearStepper";
import { cn } from "@/utils/utils";
import { useIsAdmin } from "@/hooks/useIsAdmin";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { formatFCFA, formatAmount } from "@/api/common";
import { listCategories } from "@/api/categories/categories.api";
import { listAssetTypes } from "@/api/asset-types/asset-types.api";
import {
  listMaintenancesEnCours,
  type ApiMaintenanceEnCoursCategorie, type ApiMaintenanceEnCoursRow, type ListMaintenancesEnCoursParams,
} from "@/api/biens/biens.api";

export default function MaintenancesEnCoursPage() {
  const isAdmin = useIsAdmin();
  const { user } = useConnectedUser();
  const [page, setPage] = useState(1);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [assetTypeId, setAssetTypeId] = useState<number | null>(null);
  const [exercice, setExercice] = useState(new Date().getFullYear());
  // Un utilisateur non-admin n'a accès qu'aux biens de son propre service —
  // le filtre lui est verrouillé sur ce service et n'est pas éditable.
  const [serviceId, setServiceId] = useState<number | null>(null);
  const [serviceLabel, setServiceLabel] = useState("");
  // Catégorie sélectionnée pour la vue détail — null = vue liste des catégories.
  const [selectedCategorie, setSelectedCategorie] = useState<ApiMaintenanceEnCoursCategorie | null>(null);
  const limit = 20;

  const fetchCategoryOptions = async (search: string) => {
    const res = await listCategories({ limit: search ? 50 : 200, search: search || undefined, consommable: "false" });
    return (res.data?.data ?? []).map((c) => ({ id: c.id, nom: c.nom }));
  };
  const fetchAssetTypeOptions = async (search: string) => {
    const res = await listAssetTypes({ limit: search ? 50 : 200, search: search || undefined, category_id: categoryId ?? undefined });
    return (res.data?.data ?? []).map((t) => ({ id: t.id, nom: t.nom }));
  };

  const effectiveServiceId = isAdmin ? serviceId : (user?.service?.id ?? null);

  const params: ListMaintenancesEnCoursParams = {
    page, limit,
    category_id: categoryId ?? undefined,
    asset_type_id: assetTypeId ?? undefined,
    exercice,
    service_id: effectiveServiceId ?? undefined,
  };

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["maintenances-en-cours", params],
    queryFn: () => listMaintenancesEnCours(params),
  });

  const categorieGroups: ApiMaintenanceEnCoursCategorie[] = data?.data?.data ?? [];
  const meta = data?.data?.meta;
  const totalPages = meta?.total_pages ?? 1;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const errMsg = (error as any)?.response?.data?.message ?? "Une erreur est survenue.";

  // La ligne "catégorie" sélectionnée peut être devenue obsolète après un
  // refetch (filtre changé) — on retrouve la version à jour par id, avec
  // repli sur la sélection connue pour ne pas perdre le détail affiché.
  const activeCategorie = selectedCategorie
    ? categorieGroups.find((g) => g.categorie.id === selectedCategorie.categorie.id) ?? selectedCategorie
    : null;

  const resetFilters = () => {
    setCategoryId(null); setAssetTypeId(null); setExercice(new Date().getFullYear()); setPage(1);
    if (isAdmin) { setServiceId(null); setServiceLabel(""); }
  };

  const categorieExportColumns: ExportColumn<ApiMaintenanceEnCoursCategorie>[] = [
    { key: "categorie", label: "Catégorie", format: (g) => g.categorie.nom },
    { key: "nombre_maintenances", label: "Nombre de maintenances", format: (g) => g.nombre_maintenances },
    { key: "cout_total", label: "Coût total (FCFA)", format: (g) => formatFCFA(g.cout_total) },
    { key: "seuil", label: "Seuil de maintenance", format: (g) => g.categorie.seuil ?? "—" },
  ];

  const detailExportColumns: ExportColumn<ApiMaintenanceEnCoursRow>[] = [
    { key: "reference", label: "Référence", format: (r) => r.bien.reference },
    { key: "designation", label: "Désignation", format: (r) => r.bien.designation },
    { key: "typeBien", label: "Type de bien", format: (r) => r.typeBien?.nom ?? "—" },
    { key: "etatBien", label: "État du bien", format: (r) => r.maintenance.etatBien?.nom ?? "—" },
    { key: "motif", label: "Motif", format: (r) => r.maintenance.motif },
    { key: "cout", label: "Coût (FCFA)", format: (r) => formatFCFA(Number(r.maintenance.cout)) },
    { key: "dateIntervention", label: "Date d'intervention", format: (r) => r.maintenance.dateIntervention },
    { key: "statut", label: "Statut", format: (r) => r.maintenance.statut },
    { key: "seuil_depasse", label: "Seuil dépassé", format: (r) => (r.bien.seuil_depasse ? "Oui" : "Non") },
  ];

  return (
    <AppShell>
      <div className="mx-auto flex min-h-[calc(100vh-6rem)] w-full max-w-[1600px] flex-col items-center justify-start py-6">
        <div className="mb-4 flex w-full items-center gap-3">
          <Link to="/biens">
            <Button variant="outline" size="sm" className="gap-1">
              <ArrowLeft className="h-4 w-4" /> Retour aux biens
            </Button>
          </Link>
        </div>

        {/* Panneau flottant */}
        <div className="w-full space-y-4 rounded-2xl border border-border bg-card p-6 shadow-xl">
          <div className="flex items-center justify-between gap-3">
            <h1 className="flex items-center gap-2 text-lg font-bold text-foreground">
              <Wrench className="h-5 w-5 text-primary" /> Biens en maintenance
            </h1>
            {activeCategorie ? (
              <ExportButton
                data={activeCategorie.maintenances}
                columns={detailExportColumns}
                filename={`biens-en-maintenance-${activeCategorie.categorie.nom}`}
                title={`MINEPIA — Biens en maintenance — ${activeCategorie.categorie.nom}`}
              />
            ) : (
              <ExportButton
                data={categorieGroups}
                columns={categorieExportColumns}
                filename="biens-en-maintenance-par-categorie"
                title="MINEPIA — Biens en maintenance par catégorie"
              />
            )}
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56 space-y-1">
              <Label className="text-xs text-muted-foreground">Catégorie</Label>
              <RemoteSearchSelect
                value={categoryId}
                onChange={(id) => { setCategoryId(id); setAssetTypeId(null); setPage(1); setSelectedCategorie(null); }}
                fetchOptions={fetchCategoryOptions}
                queryKeyPrefix="categories-maintenances-en-cours"
                placeholder="Toutes les catégories"
                searchPlaceholder="Rechercher une catégorie..."
              />
            </div>
            <div className="w-56 space-y-1">
              <Label className="text-xs text-muted-foreground">Type de bien</Label>
              <RemoteSearchSelect
                value={assetTypeId}
                onChange={(id) => { setAssetTypeId(id); setPage(1); }}
                fetchOptions={fetchAssetTypeOptions}
                queryKeyPrefix={`asset-types-maintenances-en-cours-${categoryId ?? "all"}`}
                placeholder="Tous les types"
                searchPlaceholder="Rechercher un type de bien..."
              />
            </div>
            <div className="w-64 space-y-1">
              <Label className="text-xs text-muted-foreground">Service</Label>
              {isAdmin ? (
                <OrgTreeSelect
                  value={serviceId}
                  valueLabel={serviceLabel}
                  onSelect={(node) => { setServiceId(node.id); setServiceLabel(node.nom); setPage(1); }}
                  onClear={() => { setServiceId(null); setServiceLabel(""); setPage(1); }}
                  selectAnyNode
                  placeholder="Tous les services"
                  searchPlaceholder="Rechercher une structure ou un poste..."
                />
              ) : (
                <div className="flex h-9 w-full items-center rounded-md border border-input bg-muted/30 px-3 text-xs text-muted-foreground">
                  {user?.service?.nom ?? "—"}
                </div>
              )}
            </div>
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Exercice</Label>
              <YearStepper className="w-28" value={exercice} onChange={(y) => { setExercice(y); setPage(1); }} />
            </div>
            <Button type="button" variant="outline" size="sm" onClick={resetFilters}>Réinitialiser</Button>
          </div>

          {activeCategorie ? (
            <>
              <div className="flex items-center justify-between">
                <Button type="button" variant="ghost" size="sm" className="gap-1 -ml-2" onClick={() => setSelectedCategorie(null)}>
                  <ArrowLeft className="h-4 w-4" /> Retour aux catégories
                </Button>
                <p className="text-xs text-muted-foreground">
                  Catégorie : <span className="font-semibold text-foreground">{activeCategorie.categorie.nom}</span>
                  {" — "}
                  {activeCategorie.nombre_maintenances} maintenance{activeCategorie.nombre_maintenances > 1 ? "s" : ""},{" "}
                  {formatFCFA(activeCategorie.cout_total)} au total
                </p>
              </div>

              <div className="max-h-[60vh] overflow-auto rounded-lg border border-border">
                <table className="w-full min-w-[1100px] text-xs">
                  <thead className="sticky top-0 bg-muted/70 text-[11px] uppercase text-muted-foreground">
                    <tr>
                      <th className="px-3 py-2 text-left">Référence</th>
                      <th className="px-3 py-2 text-left">Désignation</th>
                      <th className="px-3 py-2 text-left">Type de bien</th>
                      <th className="px-3 py-2 text-left">État du bien</th>
                      <th className="px-3 py-2 text-left">Motif</th>
                      <th className="px-3 py-2 text-right">Coût (FCFA)</th>
                      <th className="px-3 py-2 text-left">Date d&apos;intervention</th>
                      <th className="px-3 py-2 text-left">Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    {activeCategorie.maintenances.length === 0 ? (
                      <tr><td colSpan={8} className="px-3 py-8 text-center text-muted-foreground">Aucun bien en maintenance.</td></tr>
                    ) : activeCategorie.maintenances.map((r) => (
                      <tr
                        key={r.maintenance.id}
                        className={cn(
                          "border-t border-border hover:bg-muted/30",
                          r.bien.seuil_depasse && "bg-red-100 hover:bg-red-100/80 dark:bg-red-900/30",
                        )}
                        title={r.bien.seuil_depasse ? "Coût de maintenance supérieur à la valeur du bien" : undefined}
                      >
                        <td className="px-3 py-2 font-mono">{r.bien.reference}</td>
                        <td className="px-3 py-2">{r.bien.designation}</td>
                        <td className="px-3 py-2">{r.typeBien?.nom ?? "—"}</td>
                        <td className="px-3 py-2">{r.maintenance.etatBien?.nom ?? "—"}</td>
                        <td className="px-3 py-2">{r.maintenance.motif}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{formatAmount(Number(r.maintenance.cout))}</td>
                        <td className="px-3 py-2">{r.maintenance.dateIntervention}</td>
                        <td className="px-3 py-2">
                          <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            {r.maintenance.statut}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <div className="max-h-[60vh] overflow-auto rounded-lg border border-border">
              <table className="w-full min-w-[900px] text-xs">
                <thead className="sticky top-0 bg-muted/70 text-[11px] uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">Catégorie</th>
                    <th className="px-3 py-2 text-right">Nombre de maintenances</th>
                    <th className="px-3 py-2 text-right">Coût total (FCFA)</th>
                    <th className="px-3 py-2 text-right">Seuil de maintenance</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan={4} className="px-3 py-8 text-center text-muted-foreground">Chargement…</td></tr>
                  ) : isError ? (
                    <tr><td colSpan={4} className="px-3 py-8 text-center text-destructive">{errMsg}</td></tr>
                  ) : categorieGroups.length === 0 ? (
                    <tr><td colSpan={4} className="px-3 py-8 text-center text-muted-foreground">Aucun bien en maintenance.</td></tr>
                  ) : categorieGroups.map((g) => (
                    <tr
                      key={g.categorie.id}
                      className="cursor-pointer border-t border-border hover:bg-muted/30"
                      onClick={() => setSelectedCategorie(g)}
                    >
                      <td className="px-3 py-2 font-medium">{g.categorie.nom}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{g.nombre_maintenances}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{formatAmount(g.cout_total)}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{g.categorie.seuil ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {!activeCategorie && totalPages > 1 && (
            <div className="flex items-center justify-between text-xs text-muted-foreground">
              <span>Page {meta?.current_page ?? page} / {totalPages}</span>
              <div className="flex gap-2">
                <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <Button type="button" variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((p) => Math.min(totalPages, p + 1))}>
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AppShell>
  );
}
