/**
 * Page Amortissements — page dédiée (pas un dialog imbriqué dans la liste des
 * biens), présentée sous forme de panneau flottant (carte centrée, ombrée)
 * au-dessus du fond de la page. Regroupe :
 *   - GET /assets/amortissements/tableau : tableau paginé + filtres
 *   - GET /assets/{id}/amortissement     : détail d'un bien (popup)
 */
import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ChevronLeft, ChevronRight, Eye, Loader2, TrendingDown } from "lucide-react";
import { AppShell } from "@/components/shared/AppShell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ExportButton, type ExportColumn } from "@/components/shared/ExportButton";
import { RemoteSearchSelect } from "@/components/shared/RemoteSearchSelect";
import { YearStepper } from "@/components/shared/YearStepper";
import { cn } from "@/utils/utils";
import { formatFCFA, formatAmount } from "@/api/common";
import { listCategories } from "@/api/categories/categories.api";
import {
  getAssetAmortissement, listAmortissementsTable,
  type ApiAmortissementTableRow, type ListAmortissementsTableParams,
} from "@/api/biens/biens.api";

function AmortissementDetailDialog({ assetId, onClose }: { assetId: number | null; onClose: () => void }) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["asset-amortissement", assetId],
    queryFn: () => getAssetAmortissement(assetId!),
    enabled: assetId != null,
  });

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const errData = (error as any)?.response?.data;
  const fieldErrors: Record<string, string> | null =
    errData?.data && typeof errData.data === "object" ? errData.data : null;
  const status = (error as { response?: { status?: number } })?.response?.status;

  const detail = data?.data;

  return (
    <Dialog open={assetId != null} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Détail de l&apos;amortissement</DialogTitle>
        </DialogHeader>
        {isLoading ? (
          <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
            <Loader2 className="h-5 w-5 animate-spin" /> Chargement…
          </div>
        ) : isError ? (
          <div className="space-y-2 py-4">
            <p className="text-sm text-destructive">
              {status === 404 ? "Le bien demandé est introuvable."
                : status === 409 ? "Ce bien est supprimé."
                : errData?.message ?? "Une erreur est survenue."}
            </p>
            {fieldErrors && (
              <ul className="list-disc space-y-1 pl-5 text-xs text-muted-foreground">
                {Object.entries(fieldErrors).map(([k, v]) => <li key={k}>{v}</li>)}
              </ul>
            )}
          </div>
        ) : detail ? (
          <div className="space-y-4">
            <div>
              <p className="text-sm font-semibold text-foreground">{detail.nom}</p>
              <p className="font-mono text-xs text-muted-foreground">{detail.reference}</p>
              {detail.typeBien?.nom && <p className="text-xs text-muted-foreground">{detail.typeBien.nom}</p>}
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Valeur d&apos;acquisition</p>
                <p className="mt-0.5 text-sm font-semibold">{formatFCFA(detail.amortissement.valeur)}</p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Valeur actuelle</p>
                <p className="mt-0.5 text-sm font-semibold">{formatFCFA(detail.amortissement.valeurActuelle)}</p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Amortissement annuel</p>
                <p className="mt-0.5 text-sm font-semibold">{formatFCFA(detail.amortissement.amortissementAnnuel)}</p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Amortissement cumulé</p>
                <p className="mt-0.5 text-sm font-semibold">{formatFCFA(detail.amortissement.amortissementCumule)}</p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Années écoulées</p>
                <p className="mt-0.5 text-sm font-semibold">{detail.amortissement.anneesEcoulees} an(s) ({detail.amortissement.moisEcoules} mois)</p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-3">
                <p className="text-[11px] text-muted-foreground">Durée de vie restante</p>
                <p className="mt-0.5 text-sm font-semibold">{detail.amortissement.dureeVieRestante} an(s)</p>
              </div>
            </div>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>Amorti : {detail.statistiques.pourcentageAmorti}%</span>
                <span>Restant : {detail.statistiques.pourcentageRestant}%</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div className="h-full bg-primary" style={{ width: `${Math.min(100, Math.max(0, detail.statistiques.pourcentageAmorti))}%` }} />
              </div>
            </div>
            <p className="text-[11px] text-muted-foreground">Type de calcul : {detail.amortissement.typeCalcul}</p>
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

export default function AmortissementsPage() {
  const [page, setPage] = useState(1);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [exercice, setExercice] = useState(new Date().getFullYear());
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");
  const [detailTargetId, setDetailTargetId] = useState<number | null>(null);
  const limit = 20;

  const fetchCategoryOptions = async (search: string) => {
    const res = await listCategories({ limit: search ? 50 : 200, search: search || undefined, consommable: "false" });
    return (res.data?.data ?? []).map((c) => ({ id: c.id, nom: c.nom }));
  };

  const params: ListAmortissementsTableParams = {
    page, limit,
    category: categoryId ?? undefined,
    exercice,
    date_debut: dateDebut || undefined,
    date_fin: dateFin || undefined,
  };

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["amortissements-tableau", params],
    queryFn: () => listAmortissementsTable(params),
  });

  const rows: ApiAmortissementTableRow[] = data?.data?.data ?? [];
  const meta = data?.data?.meta;
  const totalPages = meta?.total_pages ?? 1;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const errMsg = (error as any)?.response?.data?.message ?? "Une erreur est survenue.";

  const resetFilters = () => {
    setCategoryId(null); setExercice(new Date().getFullYear()); setDateDebut(""); setDateFin(""); setPage(1);
  };

  const exportColumns: ExportColumn<ApiAmortissementTableRow>[] = [
    { key: "reference", label: "Référence", format: (r) => r.bien.reference },
    { key: "designation", label: "Désignation", format: (r) => r.bien.designation },
    { key: "categorie", label: "Catégorie", format: (r) => r.categorie?.nom ?? "—" },
    { key: "valeur_acquisition", label: "Valeur d'acquisition (FCFA)", format: (r) => r.valeur_acquisition },
    { key: "date_acquisition", label: "Date d'acquisition", format: (r) => r.date_acquisition },
    { key: "duree_vie", label: "Durée de vie (ans)", format: (r) => r.duree_vie },
    { key: "taux_amortissement", label: "Taux (%)", format: (r) => r.taux_amortissement },
    { key: "amortissement_annuel", label: "Amortissement annuel (FCFA)", format: (r) => r.amortissement_annuel },
    { key: "amortissement_cumule", label: "Amortissement cumulé (FCFA)", format: (r) => r.amortissement_cumule },
    { key: "vnc", label: "VNC (FCFA)", format: (r) => r.vnc },
    { key: "duree_vie_restante", label: "Durée de vie restante (ans)", format: (r) => r.duree_vie_restante },
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
              <TrendingDown className="h-5 w-5 text-primary" /> Tableau d&apos;amortissement
            </h1>
            <ExportButton
              data={rows}
              columns={exportColumns}
              filename="tableau-amortissement"
              title="MINEPIA — Tableau d'amortissement"
            />
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56 space-y-1">
              <Label className="text-xs text-muted-foreground">Catégorie</Label>
              <RemoteSearchSelect
                value={categoryId}
                onChange={(id) => { setCategoryId(id); setPage(1); }}
                fetchOptions={fetchCategoryOptions}
                queryKeyPrefix="categories-amortissements"
                placeholder="Toutes les catégories"
                searchPlaceholder="Rechercher une catégorie..."
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Exercice</Label>
              <YearStepper className="w-28" value={exercice} onChange={(y) => { setExercice(y); setPage(1); }} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Acquis à partir du</Label>
              <Input type="date" className="h-9" value={dateDebut} onChange={(e) => { setDateDebut(e.target.value); setPage(1); }} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Acquis jusqu&apos;au</Label>
              <Input type="date" className="h-9" value={dateFin} onChange={(e) => { setDateFin(e.target.value); setPage(1); }} />
            </div>
            <Button type="button" variant="outline" size="sm" onClick={resetFilters}>Réinitialiser</Button>
          </div>

          <div className="max-h-[60vh] overflow-auto rounded-lg border border-border">
            <table className="w-full min-w-[1000px] text-xs">
              <thead className="sticky top-0 bg-muted/70 text-[11px] uppercase text-muted-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Référence</th>
                  <th className="px-3 py-2 text-left">Désignation</th>
                  <th className="px-3 py-2 text-left">Catégorie</th>
                  <th className="px-3 py-2 text-right">Valeur d&apos;acq. (FCFA)</th>
                  <th className="px-3 py-2 text-left">Date d&apos;acq.</th>
                  <th className="px-3 py-2 text-right">Durée de vie (ans)</th>
                  <th className="px-3 py-2 text-right">Taux (%)</th>
                  <th className="px-3 py-2 text-right">Amort. annuel (FCFA)</th>
                  <th className="px-3 py-2 text-right">Amort. cumulé (FCFA)</th>
                  <th className="px-3 py-2 text-right">VNC (FCFA)</th>
                  <th className="px-3 py-2 text-right">Restant (ans)</th>
                  <th className="px-3 py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr><td colSpan={11} className="px-3 py-8 text-center text-muted-foreground">Chargement…</td></tr>
                ) : isError ? (
                  <tr><td colSpan={11} className="px-3 py-8 text-center text-destructive">{errMsg}</td></tr>
                ) : rows.length === 0 ? (
                  <tr><td colSpan={11} className="px-3 py-8 text-center text-muted-foreground">Aucun bien à afficher.</td></tr>
                ) : rows.map((r) => {
                  const restant = r.duree_vie_restante;
                  // typeof-guard : duree_vie_restante peut être null/undefined
                  // quand le type de bien n'a pas de durée de vie configurée —
                  // "null <= 0" vaut true en JS (null coercé en 0), ce qui
                  // marquait à tort ces biens comme totalement amortis (rouge).
                  const hasRestant = typeof restant === "number";
                  const isDepleted = hasRestant && restant <= 0;
                  const isNearlyDepleted = hasRestant && !isDepleted && restant <= 2;
                  return (
                  <tr
                    key={r.bien.id}
                    className={cn(
                      "border-t border-border hover:bg-muted/30",
                      isDepleted && "bg-red-100 hover:bg-red-100/80 dark:bg-red-900/30",
                      isNearlyDepleted && "bg-orange-100 hover:bg-orange-100/80 dark:bg-orange-900/30",
                    )}
                  >
                    <td className="px-3 py-2 font-mono">{r.bien.reference}</td>
                    <td className="px-3 py-2">{r.bien.designation}</td>
                    <td className="px-3 py-2">{r.categorie?.nom ?? "—"}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{formatAmount(r.valeur_acquisition)}</td>
                    <td className="px-3 py-2">{r.date_acquisition || "—"}</td>
                    <td className="px-3 py-2 text-right">{r.duree_vie ?? "—"}</td>
                    <td className="px-3 py-2 text-right">{r.taux_amortissement ?? "—"}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{formatAmount(r.amortissement_annuel)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{formatAmount(r.amortissement_cumule)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{formatAmount(r.vnc)}</td>
                    <td className="px-3 py-2 text-right font-semibold">{hasRestant ? restant : "—"}</td>
                    <td className="px-3 py-2 text-right">
                      <Button type="button" variant="ghost" size="sm" className="h-7 gap-1 px-2" onClick={() => setDetailTargetId(r.bien.id)}>
                        <Eye className="h-3.5 w-3.5" /> Détails
                      </Button>
                    </td>
                  </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
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

      <AmortissementDetailDialog assetId={detailTargetId} onClose={() => setDetailTargetId(null)} />
    </AppShell>
  );
}
