/**
 * Page Bilan global des consomptibles — page dédiée /consomptibles/bilan-global
 * (pas un dialog), même schéma que /biens/amortissements (src/pages/Amortissements.tsx) :
 * panneau flottant au-dessus du fond de page, avec un lien de retour.
 *
 * GET /consumables/bilan-global — confirmé fonctionnel en direct le 2026-08-20.
 */
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, BarChart3, Loader2 } from "lucide-react";
import { AppShell } from "@/components/shared/AppShell";
import { Button } from "@/components/ui/button";
import { ExportButton, type ExportColumn } from "@/components/shared/ExportButton";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  getConsumablesBilanGlobal,
  type ApiConsumableBilanGlobalEntry,
} from "@/api/consumables/consumables.api";
import { useT } from "@/utils/i18n";

function formatQty(n: number) {
  return n.toLocaleString("fr-FR");
}

export default function ConsomptiblesBilanGlobalPage() {
  const t = useT();
  const { data, isLoading, isError } = useQuery({
    queryKey: ["consumables-bilan-global"],
    queryFn: () => getConsumablesBilanGlobal(),
  });
  // Le backend retourne quantite dans parService comme string ("6.00") — normaliser
  const rawEntries = data?.data ?? [];
  const entries: ApiConsumableBilanGlobalEntry[] = rawEntries.map((e) => ({
    ...e,
    bilan: {
      ...e.bilan,
      totalEntrees: Number(e.bilan.totalEntrees),
      totalTransfere: Number(e.bilan.totalTransfere),
      stockActuel: Number(e.bilan.stockActuel),
      parService: e.bilan.parService.map((s) => ({ ...s, quantite: Number(s.quantite) })),
    },
  }));

  const exportColumns: ExportColumn<ApiConsumableBilanGlobalEntry>[] = [
    { key: "nom", label: t("consumables.field.name"), format: (e) => e.consumable.nom },
    { key: "totalEntrees", label: t("consumables.field.entrees"), format: (e) => e.bilan.totalEntrees },
    { key: "totalTransfere", label: t("consumables.field.transfere"), format: (e) => e.bilan.totalTransfere },
    { key: "stockActuel", label: t("consumables.field.stockActuel"), format: (e) => e.bilan.stockActuel },
  ];

  return (
    <AppShell>
      <div className="mx-auto flex min-h-[calc(100vh-6rem)] w-full max-w-5xl flex-col items-center justify-start py-6">
        <div className="mb-4 flex w-full items-center gap-3">
          <Link to="/consomptibles">
            <Button variant="outline" size="sm" className="gap-1">
              <ArrowLeft className="h-4 w-4" /> {t("consumables.bilanGlobal.back")}
            </Button>
          </Link>
        </div>

        {/* Panneau flottant */}
        <CanAccess
          permission="consultation_bilan_consomptible"
          fallback={
            <div className="w-full rounded-2xl border border-border bg-card p-6 text-center shadow-xl">
              <p className="text-sm font-semibold text-foreground">{t("common.accessDenied.title")}</p>
              <p className="mt-1 text-xs text-muted-foreground">{t("common.accessDenied.desc")}</p>
            </div>
          }
        >
        <div className="w-full space-y-4 rounded-2xl border border-border bg-card p-6 shadow-xl">
          <div className="flex items-center justify-between gap-3">
            <h1 className="flex items-center gap-2 text-lg font-bold text-foreground">
              <BarChart3 className="h-5 w-5 text-primary" /> {t("consumables.bilanGlobal.title")}
            </h1>
            <ExportButton
              data={entries}
              columns={exportColumns}
              filename="bilan-global-consomptibles"
              title={`MINEPIA — ${t("consumables.bilanGlobal.title")}`}
            />
          </div>

          {isLoading ? (
            <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
              <Loader2 className="h-5 w-5 animate-spin" />
              <span>{t("common.loading")}</span>
            </div>
          ) : isError ? (
            <p className="py-8 text-center text-sm text-destructive">
              {t("consumables.bilanGlobal.error")}
            </p>
          ) : entries.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              {t("consumables.bilanGlobal.empty")}
            </p>
          ) : (
            <div className="max-h-[65vh] overflow-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-muted/70 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-3 py-2 text-left">{t("consumables.field.name")}</th>
                    <th className="px-3 py-2 text-right">{t("consumables.field.entrees")}</th>
                    <th className="px-3 py-2 text-right">{t("consumables.field.transfere")}</th>
                    <th className="px-3 py-2 text-right">{t("consumables.field.stockActuel")}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {entries.map(({ consumable, bilan }) => (
                    <tr key={consumable.id} className="hover:bg-muted/30">
                      <td className="px-3 py-2">
                        <span className="font-medium">{consumable.nom}</span>
                        {bilan.parService.length > 0 && (
                          <ul className="mt-1 space-y-0.5">
                            {bilan.parService.map((s) => (
                              <li
                                key={s.serviceId}
                                className="flex items-center justify-between text-[11px] text-muted-foreground"
                              >
                                <span>{s.serviceNom}</span>
                                <span className="tabular-nums">{formatQty(s.quantite)}</span>
                              </li>
                            ))}
                          </ul>
                        )}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums">
                        {formatQty(bilan.totalEntrees)}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums">
                        {formatQty(bilan.totalTransfere)}
                      </td>
                      <td className="px-3 py-2 text-right font-medium tabular-nums text-primary">
                        {formatQty(bilan.stockActuel)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
        </CanAccess>
      </div>
    </AppShell>
  );
}
