/**
 * Section de configuration — Liste des biens sécurisés (Administration > Sécurisations).
 * Lecture des sécurisations créées depuis la liste des biens (bouton
 * "Sécuriser" sur une sélection multiple) — table `securities`.
 */

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, ShieldCheck, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
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
  listSecurities, deleteSecurity, type ApiSecurity,
} from "@/api/securities/securities.api";

export function SecurisationsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<ApiSecurity | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["securities"],
    queryFn: () => listSecurities({ limit: 1000 }),
  });
  const securities: ApiSecurity[] = data?.data?.data ?? [];

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteSecurity(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["securities"] });
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => {
      toast.error(t("toast.error"));
      setDeleteTarget(null);
    },
  });

  const columns: Column<ApiSecurity>[] = [
    {
      key: "securityMode",
      label: "Mode",
      render: (s) => (
        <Badge
          variant="outline"
          className={s.securityMode === "Juridique" ? "border-blue-300 bg-blue-500/10 text-blue-700" : "border-emerald-300 bg-emerald-500/10 text-emerald-700"}
        >
          {s.securityMode}
        </Badge>
      ),
      sortValue: (s) => s.securityMode,
    },
    {
      key: "dateSecurisation",
      label: "Date de sécurisation",
      render: (s) => <span className="text-xs">{s.dateSecurisation ?? "—"}</span>,
      sortValue: (s) => s.dateSecurisation ?? "",
    },
    {
      key: "assets",
      label: "Biens concernés",
      render: (s) => (
        <span className="text-xs text-muted-foreground" title={s.assets.map((a) => `${a.reference ?? ""} ${a.nom ?? ""}`.trim()).join(", ")}>
          {s.assets.length === 0
            ? "—"
            : s.assets.length <= 2
              ? s.assets.map((a) => a.reference ?? a.nom).join(", ")
              : `${s.assets.slice(0, 2).map((a) => a.reference ?? a.nom).join(", ")} +${s.assets.length - 2}`}
        </span>
      ),
      sortValue: (s) => s.assets.length,
    },
    {
      key: "location",
      label: "Position",
      render: (s) => (
        <span className="text-xs tabular-nums text-muted-foreground">
          {s.location ? `${s.location.latitude}, ${s.location.longitude}` : "—"}
        </span>
      ),
    },
    {
      key: "documents",
      label: "Pièces jointes",
      render: (s) => <span className="text-xs tabular-nums text-muted-foreground">{s.documents?.length ?? 0}</span>,
      sortValue: (s) => s.documents?.length ?? 0,
    },
    {
      key: "createdAt",
      label: "Créé le",
      render: (s) => <span className="text-xs text-muted-foreground">{s.createdAt ?? "—"}</span>,
      sortValue: (s) => s.createdAt ?? "",
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

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">Biens sécurisés</h2>
        </div>
        <p className="mb-3 text-sm text-muted-foreground">
          Historique des sécurisations enregistrées depuis la liste des biens (bouton « Sécuriser » sur une sélection).
        </p>

        {securities.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border p-12 text-center">
            <ShieldCheck className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">Aucune sécurisation enregistrée</p>
          </div>
        ) : (
          <DataTable
            data={securities}
            columns={columns}
            getRowId={(s) => String(s.id)}
            exportFilename="securisations-minepia"
            exportTitle="MINEPIA — Biens sécurisés"
            searchKeys={["securityMode", "dateSecurisation"]}
            rowActions={(s) => (
              <CanAccess permission="parametrage_securite">
                <RowIconButton icon={Trash2} label={t("action.delete")} tone="danger" onClick={() => setDeleteTarget(s)} />
              </CanAccess>
            )}
          />
        )}
      </div>

      <AlertDialog open={!!deleteTarget} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Supprimer cette sécurisation ?</AlertDialogTitle>
            <AlertDialogDescription>
              La sécurisation du {deleteTarget?.dateSecurisation} portant sur {deleteTarget?.assets.length ?? 0} bien(s) sera définitivement supprimée.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
