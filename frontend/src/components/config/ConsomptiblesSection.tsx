/**
 * Section de configuration — Gestion des consomptibles.
 * Gestion des consomptibles, entrées et transferts de stock (Administration > Consomptibles).
 *
 * CRUD complet : liste (toggle supprimés), création, édition (avec
 * pré-remplissage stable des champs et des pièces jointes existantes),
 * suppression logique, restauration.
 *
 * ⚠️ La quantité est saisie uniquement à la création — le backend ne permet
 * plus de la modifier ensuite (absente du schéma PATCH, confirmé aussi en
 * pratique). En édition, le champ est affiché en lecture seule, jamais
 * envoyé au serveur.
 *
 * 🔴 POST /consumables/{id} (édition) est un no-op côté backend pour nom,
 * description et pièces jointes (re-testé en direct le 2026-08-20) : la
 * réponse dit "succès" et `updatedAt` avance, mais rien n'est réellement
 * écrit. `updateMutation` envoie quand même le payload complet (au cas où le
 * backend soit corrigé un jour) mais compare la réponse post-update à ce qui
 * a été soumis et affiche un avertissement explicite si rien n'a changé,
 * plutôt que de mentir sur le succès de l'édition.
 *
 * ✅ Exception confirmée le 2026-08-25 : `service_id` sur cette même édition
 * fonctionne réellement — il alloue du stock du consomptible au service
 * choisi (même effet qu'à la création), ce qui rend ensuite un transfert
 * possible vers ce service. Le champ "Structure / Service (Poste)" du
 * formulaire d'édition sert donc à ajouter une allocation à un consomptible
 * déjà existant, pas seulement à la création.
 *
 * ⚠️ Prix unitaire (prixInitial) : confirmé en direct que c'est le vrai nom
 * de champ accepté au POST (voir consumables.api.ts). Prix total n'est
 * JAMAIS calculé par le backend (reste null même avec prixInitial et
 * quantite renseignés) — calculé ici côté client (prixInitial × quantite),
 * affiché en lecture seule plutôt que comme un champ à saisir.
 */

import { useEffect, useRef, useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQueries, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  ArrowLeftRight,
  BarChart3,
  CheckCircle2,
  CheckSquare,
  Loader2,
  Minus,
  MoreVertical,
  Package,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  Trash2,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Pagination } from "@/components/shared/Pagination";
import { useT } from "@/utils/i18n";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { CanAccess } from "@/components/auth/CanAccess";
import { cn } from "@/utils/utils";
import {
  listConsumables,
  getConsumableById,
  createConsumable,
  updateConsumable,
  deleteConsumable,
  restoreConsumable,
  deleteConsumablePieceJointe,
  type ApiConsumable,
  type CreateConsumablePayload,
  type UpdateConsumablePayload,
} from "@/api/consumables/consumables.api";
import { formatFCFA, formatAmount } from "@/api/common";
import { getConsumablesBilanGlobal, type ApiConsumableBilanGlobalEntry } from "@/api/consumables/consumables.api";
import {
  listConsumableTransfersByService,
  acknowledgeConsumableTransfersBatch,
  consumeConsumableTransfer,
  type ApiConsumableTransfer,
} from "@/api/consumables/consumable-transfers.api";
import { ConsumableDetailDialog } from "@/components/consumables/ConsumableDetailDialog";
import { listCategories, type ApiCategory } from "@/api/categories/categories.api";
import { OrgTreeSelect, formatPosteLabel } from "@/components/shared/OrgTreeSelect";
import { OrgTreeMultiSelect } from "@/components/shared/OrgTreeMultiSelect";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type View = "liste" | "form";

// ─── Section principale ─────────────────────────────────────────────────────

export function ConsomptiblesSection() {
  const t = useT();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useConnectedUser();
  const [view, setView] = useState<View>("liste");
  const [selected, setSelected] = useState<ApiConsumable | null>(null);
  const [loadingEditId, setLoadingEditId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiConsumable | null>(null);
  const [detailTarget, setDetailTarget] = useState<ApiConsumable | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);
  // Filtre Structure — plusieurs postes sélectionnables (demande explicite,
  // 2026-08-27). GET /consumables n'accepte qu'UN SEUL service_id par appel
  // (confirmé — pas de support tableau côté backend) : avec 0 ou 1 poste
  // sélectionné, requête paginée serveur normale (15/page) ; avec plusieurs
  // postes, un appel par poste (limit large, pas de pagination serveur),
  // fusionnés et dédupliqués côté client, puis paginés localement à 15/page.
  const [serviceFilterIds, setServiceFilterIds] = useState<number[]>([]);
  // Filtre Catégorie — ne propose que les catégories de consomptibles
  // (consommable=true), demande explicite (2026-08-29).
  const [categoryFilterId, setCategoryFilterId] = useState<number | null>(null);
  const [page, setPage] = useState(1);
  const pageSize = 15;
  const isMultiServiceFilter = serviceFilterIds.length > 1;

  const { data: filterCategoriesData } = useQuery({
    queryKey: ["categories", "consommable", "filter"],
    queryFn: () => listCategories({ limit: 1000, consommable: "true" }),
    staleTime: 300_000,
  });
  const filterCategories: ApiCategory[] = filterCategoriesData?.data?.data ?? [];

  const singleServiceQuery = useQuery({
    queryKey: ["consumables", showDeleted, serviceFilterIds[0] ?? null, categoryFilterId, page],
    queryFn: () =>
      listConsumables({
        page,
        limit: pageSize,
        is_delete: showDeleted,
        service_id: serviceFilterIds[0] ?? undefined,
        category_ids: categoryFilterId ?? undefined,
        // Ignoré côté backend si l'utilisateur connecté n'a pas un rôle
        // admin — sûr à envoyer systématiquement (voir ListConsumablesParams).
        all_services: true,
      }),
    enabled: !isMultiServiceFilter,
  });

  const multiServiceQueries = useQueries({
    queries: isMultiServiceFilter
      ? serviceFilterIds.map((serviceId) => ({
          queryKey: ["consumables-by-service", showDeleted, serviceId, categoryFilterId],
          queryFn: () => listConsumables({ page: 1, limit: 1000, is_delete: showDeleted, service_id: serviceId, category_ids: categoryFilterId ?? undefined, all_services: true }),
        }))
      : [],
  });
  const multiServiceLoading = isMultiServiceFilter && multiServiceQueries.some((q) => q.isLoading);
  const multiServiceMerged = useMemo<ApiConsumable[]>(() => {
    if (!isMultiServiceFilter) return [];
    const byId = new Map<number, ApiConsumable>();
    multiServiceQueries.forEach((q) => (q.data?.data?.data ?? []).forEach((c) => byId.set(c.id, c)));
    return Array.from(byId.values());
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isMultiServiceFilter, multiServiceQueries.map((q) => q.dataUpdatedAt).join(",")]);

  const isLoading = isMultiServiceFilter ? multiServiceLoading : singleServiceQuery.isLoading;
  const consumables: ApiConsumable[] = isMultiServiceFilter
    ? multiServiceMerged.slice((page - 1) * pageSize, page * pageSize)
    : singleServiceQuery.data?.data?.data ?? [];
  const totalConsumables = isMultiServiceFilter
    ? multiServiceMerged.length
    : singleServiceQuery.data?.data?.meta?.total_items ?? 0;

  // Filtrer/re-basculer la corbeille doit revenir à la page 1 — sinon on
  // peut se retrouver sur une page qui n'existe plus pour le nouveau filtre.
  useEffect(() => {
    setPage(1);
  }, [showDeleted, serviceFilterIds, categoryFilterId]);

  // Bilan global — quantité transférée + stock actuel par consomptible
  const { data: bilanGlobalData } = useQuery({
    queryKey: ["consumables-bilan-global"],
    queryFn: () => getConsumablesBilanGlobal(),
    staleTime: 60_000,
  });
  const bilanMap = useMemo<Map<number, ApiConsumableBilanGlobalEntry["bilan"]>>(() => {
    const m = new Map<number, ApiConsumableBilanGlobalEntry["bilan"]>();
    (bilanGlobalData?.data ?? []).forEach((entry) => m.set(entry.consumable.id, entry.bilan));
    return m;
  }, [bilanGlobalData]);

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["consumables"] });
    queryClient.invalidateQueries({ queryKey: ["consumables-by-service"] });
    queryClient.invalidateQueries({ queryKey: ["consumables-bilan-global"] });
  };

  // ── Transferts reçus par le service de l'utilisateur connecté ────────────
  // Source de vérité = l'API (isAcknowledged / quantityConsumed sur chaque
  // transfert) — jamais d'état local, un accusé ou une consommation
  // confirmés côté serveur déclenchent juste un refetch de cette requête.
  const myServiceId = user?.service?.id ?? null;
  const myTransfersQueryKey = ["consumable-transfers-by-service", myServiceId];
  const { data: myTransfersData, isLoading: myTransfersLoading } = useQuery({
    queryKey: myTransfersQueryKey,
    queryFn: () => listConsumableTransfersByService(myServiceId!),
    enabled: myServiceId != null,
    staleTime: 30_000,
  });
  const myTransfers: ApiConsumableTransfer[] = (myTransfersData ?? [])
    .filter((t) => t.serviceDestination?.id === myServiceId)
    .sort((a, b) => {
      if (a.isAcknowledged === b.isAcknowledged) return 0;
      return a.isAcknowledged ? 1 : -1;
    });
  const invalidateMyTransfers = () => queryClient.invalidateQueries({ queryKey: myTransfersQueryKey });

  // Les consomptibles transférés proviennent d'autres services.
  // singleServiceQuery ne les renvoie donc pas.
  // On doit les récupérer individuellement pour afficher leur prix, description et les actions.
  const missingConsumableIds = useMemo(() => {
    const ids = myTransfers.map((t) => t.consumable?.id).filter((id): id is number => id != null);
    const uniqueIds = Array.from(new Set(ids));
    return uniqueIds.filter((id) => !consumables.some((c) => c.id === id));
  }, [myTransfers, consumables]);

  const missingConsumablesQueries = useQueries({
    queries: missingConsumableIds.map((id) => ({
      queryKey: ["consumables", id],
      queryFn: () => getConsumableById(id),
      staleTime: 60_000,
    })),
  });

  const allConsumables = useMemo(() => {
    const fetched = missingConsumablesQueries.map((q) => q.data?.data).filter((c): c is import("@/api/consumables/consumables.api").ApiConsumable => !!c);
    return [...consumables, ...fetched];
  }, [consumables, missingConsumablesQueries]);

  const [selectedTransferIds, setSelectedTransferIds] = useState<Set<number>>(new Set());
  const toggleTransfer = (id: number) =>
    setSelectedTransferIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  // Consommation directe par le service propriétaire — pas de transfert
  // impliqué. Confirmé en direct le 2026-08-26 : `quantite` sur
  // POST /consumables/{id} est réellement modifiable (contrairement au reste
  // du payload, voir en-tête du fichier) et fait bouger le stock actuel en
  // conséquence. On envoie donc la nouvelle quantité restante (quantité
  // actuelle ± 1) plutôt que de garder un brouillon local qui se perdrait au
  // rafraîchissement.
  const consumeCatalogMutation = useMutation({
    mutationFn: ({ id, quantite }: { id: number; quantite: number }) =>
      updateConsumable(id, { quantite }),
    onSuccess: invalidate,
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("consumables.error.updateConsumption"));
    },
  });

  const acknowledgeMutation = useMutation({
    mutationFn: (ids: number[]) => acknowledgeConsumableTransfersBatch(ids),
    onMutate: async (ids: number[]) => {
      await queryClient.cancelQueries({ queryKey: myTransfersQueryKey });
      const previous = queryClient.getQueryData<ApiConsumableTransfer[]>(myTransfersQueryKey);
      if (previous) {
        queryClient.setQueryData<ApiConsumableTransfer[]>(
          myTransfersQueryKey,
          previous.map((t) => (ids.includes(t.id) ? { ...t, isAcknowledged: true } : t)),
        );
      }
      return { previous };
    },
    onSuccess: () => {
      setSelectedTransferIds(new Set());
      invalidateMyTransfers();
      queryClient.invalidateQueries({ queryKey: ["notifications"] });
      toast.success(t("consumables.toast.receiptAcknowledged"));
    },
    onError: (err: unknown, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(myTransfersQueryKey, context.previous);
      }
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("consumables.error.acknowledgeReceipt"));
    },
  });

  const consumeMutation = useMutation({
    mutationFn: ({ id, quantityConsumed }: { id: number; quantityConsumed: number }) =>
      consumeConsumableTransfer(id, quantityConsumed),
    onSuccess: invalidateMyTransfers,
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("consumables.error.updateConsumption"));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteConsumable(id),
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
    mutationFn: (id: number) => restoreConsumable(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.saved"));
    },
    onError: () => toast.error(t("toast.error")),
  });

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("common.loading")}</span>
      </div>
    );
  }

  if (view === "form") {
    return (
      <ConsomptibleForm
        initial={selected}
        onCancel={() => setView("liste")}
        onSaved={() => {
          invalidate();
          setView("liste");
        }}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("nav.consomptibles")}</h2>
          <div className="flex items-center gap-2">
            <CanAccess permission="consultation_bilan_consomptible">
              <Button
                variant="outline"
                className="gap-2"
                onClick={() => navigate("/consomptibles/bilan-global")}
              >
                <BarChart3 className="h-4 w-4" /> {t("consumables.bilanGlobal.button")}
              </Button>
            </CanAccess>
            <CanAccess permission="creation_consomptible">
              <Button
                className="gap-2"
                onClick={() => {
                  setSelected(null);
                  setView("form");
                }}
              >
                <Plus className="h-4 w-4" /> {t("consumables.new")}
              </Button>
            </CanAccess>
          </div>
        </div>
        <p className="mb-3 text-sm text-muted-foreground">
          {t("consumables.subtitle")}.
        </p>

        <div className="mb-3 flex flex-wrap items-end gap-4">
          <div className="w-72 space-y-1.5">
            <Label className="text-xs text-muted-foreground">{t("consumables.filter.structure")}</Label>
            <OrgTreeMultiSelect
              value={serviceFilterIds}
              onChange={setServiceFilterIds}
              placeholder={t("consumables.filter.allPostes")}
              searchPlaceholder={t("consumables.filter.searchPoste")}
              selectableType="Poste"
              deferApply
            />
          </div>
          <div className="w-64 space-y-1.5">
            <Label className="text-xs text-muted-foreground">{t("common.category")}</Label>
            <SearchableSelect
              options={filterCategories.map((c) => ({ value: c.id, label: c.nom }))}
              value={categoryFilterId}
              onChange={setCategoryFilterId}
              placeholder={t("consumables.filter.allCategories")}
              searchPlaceholder={t("consumables.filter.searchCategory")}
            />
          </div>
          <label className="flex cursor-pointer items-center gap-2 pb-2 text-sm">
            <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
            {t("consumables.showDeleted")}
          </label>
        </div>

        {/* Barre d'action groupée pour l'accusé de réception des transferts sélectionnés */}
        {selectedTransferIds.size > 0 && (
          <div className="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
            <span className="text-sm font-semibold text-primary">
              {t("consumables.myTransfers.selectedCount", { count: selectedTransferIds.size })}
            </span>
            <CanAccess permission="gestion_stock">
              <Button
                size="sm"
                className="gap-2 h-9"
                onClick={() => acknowledgeMutation.mutate(Array.from(selectedTransferIds))}
                disabled={acknowledgeMutation.isPending}
              >
                {acknowledgeMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <CheckSquare className="h-4 w-4" />
                )}
                {t("consumables.acknowledgeReceipt")}
              </Button>
            </CanAccess>
            <button
              type="button"
              onClick={() => setSelectedTransferIds(new Set())}
              className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {consumables.length === 0 && myTransfers.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border p-12 text-center">
            <Package className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">{t("consumables.empty")}</p>
            <CanAccess permission="creation_consomptible">
              <Button
                variant="outline"
                className="mt-4"
                onClick={() => {
                  setSelected(null);
                  setView("form");
                }}
              >
                {t("consumables.createFirst")}
              </Button>
            </CanAccess>
          </div>
        ) : (
          <div className="rounded-xl border border-border bg-card shadow-sm overflow-x-auto">
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                  {myTransfers.some((tr) => !tr.isAcknowledged) && <th className="w-8 px-3 py-3" />}
                  <th className="px-3 py-3 text-left">{t("common.name")}</th>
                  <th className="px-3 py-3 text-left">{t("common.category")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.service")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.initialQuantity")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.transferredQty")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.stockActuel")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.unitPrice")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.totalPrice")}</th>
                  <th className="px-3 py-3 text-left">{t("common.description")}</th>
                  <th className="px-3 py-3 text-left">{t("consumables.field.consumed")}</th>
                  <th className="px-3 py-3 text-left">{t("common.status")}</th>
                  <th className="px-3 py-3 text-right">{t("common.actions")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {/* ── 1. Consomptibles transférés vers mon service ── */}
                {myTransfers.map((tr) => {
                  const isGrise = !tr.isAcknowledged;
                  const matchC = allConsumables.find((c) => c.id === tr.consumable?.id);
                  const nom = tr.consumable?.nom ?? matchC?.nom ?? "—";
                  const categoryNom = matchC?.category?.nom ?? "—";
                  const serviceNom = tr.serviceDestination?.nom ?? user?.service?.nom ?? "—";
                  const stockActuel = Math.max(0, tr.quantite - tr.quantityConsumed);
                  const prix = matchC?.prixInitial ?? null;
                  const prixTotal = prix != null ? prix * tr.quantite : null;
                  const description = matchC?.description ?? tr.observations ?? "—";
                  const hasPendingInTable = myTransfers.some((t) => !t.isAcknowledged);

                  return (
                    <tr
                      key={`tr-${tr.id}`}
                      className={cn(
                        "transition-colors",
                        isGrise ? "bg-muted/40 opacity-60" : "hover:bg-muted/30",
                      )}
                    >
                      {hasPendingInTable && (
                        <td className="px-3 py-3">
                          {isGrise ? (
                            <input
                              type="checkbox"
                              checked={selectedTransferIds.has(tr.id)}
                              onChange={() => toggleTransfer(tr.id)}
                              className="h-4 w-4 cursor-pointer accent-primary"
                              aria-label={t("action.select")}
                            />
                          ) : null}
                        </td>
                      )}
                      <td className="px-3 py-3 font-semibold">
                        <span className="inline-flex items-center gap-1.5">
                          {nom}
                          {!isGrise && (
                            <CheckCircle2
                              className="h-3.5 w-3.5 shrink-0 text-emerald-600"
                              aria-label={t("consumables.receiptAcknowledgedLabel")}
                            />
                          )}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground">{categoryNom}</td>
                      <td className="px-3 py-3 text-xs text-muted-foreground">{serviceNom}</td>
                      <td className="px-3 py-3 font-medium tabular-nums">{tr.quantite.toLocaleString("fr-FR")}</td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">—</td>
                      <td className="px-3 py-3">
                        <span
                          className={cn(
                            "font-semibold tabular-nums text-xs",
                            stockActuel <= 5 ? "text-destructive" : "text-primary",
                          )}
                        >
                          {stockActuel.toLocaleString("fr-FR")}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">
                        {prix != null ? formatAmount(prix) : "—"}
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">
                        {prixTotal != null ? formatAmount(prixTotal) : "—"}
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground max-w-xs truncate" title={description}>
                        {description}
                      </td>
                      <td className="px-3 py-3">
                        {isGrise ? (
                          <span className="text-xs text-muted-foreground italic font-medium">
                            En attente
                          </span>
                        ) : (
                          <CanAccess
                            permission="enregistrement_consommation_consomptible"
                            fallback={
                              <span className="text-sm font-semibold tabular-nums text-muted-foreground">
                                {tr.quantityConsumed.toLocaleString("fr-FR")}
                              </span>
                            }
                          >
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              disabled={consumeMutation.isPending || tr.quantityConsumed <= 0}
                              onClick={() =>
                                consumeMutation.mutate({
                                  id: tr.id,
                                  quantityConsumed: Math.max(0, tr.quantityConsumed - 1),
                                })
                              }
                              className="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-sm hover:bg-muted disabled:opacity-40"
                              aria-label={t("consumables.decreaseConsumedFor", { nom })}
                            >
                              <Minus className="h-3 w-3" />
                            </button>
                            <input
                              key={`consumed-${tr.id}-${tr.quantityConsumed}`}
                              type="number"
                              min={0}
                              max={tr.quantite}
                              defaultValue={tr.quantityConsumed}
                              disabled={consumeMutation.isPending}
                              onInput={(e) => {
                                const target = e.currentTarget;
                                const clean = target.value.replace(/[^0-9]/g, "");
                                if (clean === "") {
                                  target.value = "";
                                  return;
                                }
                                const num = parseInt(clean, 10);
                                if (num > tr.quantite) {
                                  target.value = String(tr.quantite);
                                } else if (num < 0) {
                                  target.value = "0";
                                } else {
                                  target.value = String(num);
                                }
                              }}
                              onBlur={(e) => {
                                const raw = e.target.value.trim() === "" ? 0 : Number(e.target.value);
                                if (Number.isNaN(raw)) {
                                  e.target.value = String(tr.quantityConsumed);
                                  return;
                                }
                                const clamped = Math.max(0, Math.min(tr.quantite, Math.floor(raw)));
                                e.target.value = String(clamped);
                                if (clamped !== tr.quantityConsumed)
                                  consumeMutation.mutate({ id: tr.id, quantityConsumed: clamped });
                              }}
                              onKeyDown={(e) => {
                                if (
                                  e.key === "-" ||
                                  e.key === "e" ||
                                  e.key === "E" ||
                                  e.key === "+" ||
                                  e.key === "." ||
                                  e.key === ","
                                ) {
                                  e.preventDefault();
                                }
                                if (e.key === "Enter") (e.target as HTMLInputElement).blur();
                              }}
                              className="h-7 w-14 rounded border border-border bg-background text-center text-sm font-semibold tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            <button
                              type="button"
                              disabled={
                                consumeMutation.isPending || tr.quantityConsumed >= tr.quantite || stockActuel <= 0
                              }
                              onClick={() =>
                                consumeMutation.mutate({
                                  id: tr.id,
                                  quantityConsumed: Math.min(tr.quantite, tr.quantityConsumed + 1),
                                })
                              }
                              className="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-sm hover:bg-muted disabled:opacity-40"
                              aria-label={t("consumables.increaseConsumedFor", { nom })}
                              title={
                                tr.quantityConsumed >= tr.quantite
                                  ? t("consumables.maxQuantityReached")
                                  : undefined
                              }
                            >
                              <Plus className="h-3 w-3" />
                            </button>
                          </div>
                          </CanAccess>
                        )}
                      </td>
                      <td className="px-3 py-3">
                        {isGrise ? (
                          <Badge
                            variant="outline"
                            className="border-amber-300 bg-amber-500/15 text-xs text-amber-700"
                          >
                            En attente de réception
                          </Badge>
                        ) : (
                          <Badge
                            variant="default"
                            className="border-emerald-300 bg-emerald-500/15 text-xs text-emerald-700"
                          >
                            {t("status.active")}
                          </Badge>
                        )}
                      </td>
                      <td className="px-3 py-3 text-right">
                        {isGrise ? (
                          <CanAccess permission="gestion_stock">
                            <Button
                              size="sm"
                              variant="outline"
                              className="h-7 text-xs gap-1 border-primary/40 text-primary hover:bg-primary/10"
                              onClick={() => acknowledgeMutation.mutate([tr.id])}
                              disabled={acknowledgeMutation.isPending}
                            >
                              {acknowledgeMutation.isPending ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              ) : (
                                <CheckSquare className="h-3.5 w-3.5" />
                              )}
                              {t("consumables.acknowledgeReceipt")}
                            </Button>
                          </CanAccess>
                        ) : matchC ? (
                          <CanAccess anyOf={["creation_transfert_consomptible", "suppression_transfert_consomptible"]}>
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <button
                                  type="button"
                                  className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                                  aria-label={t("consumables.actionsFor", { nom })}
                                >
                                  <MoreVertical className="h-4 w-4" />
                                </button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => setDetailTarget(matchC)}>
                                  <ArrowLeftRight className="mr-2 h-4 w-4" /> {t("consumables.transfers")}
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </CanAccess>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}

                {/* ── 2. Consomptibles du catalogue ── */}
                {consumables.map((c) => {
                  const bilan = bilanMap.get(c.id);
                  const total = c.prixInitial != null ? c.prixInitial * c.quantite : null;
                  const totalReceived = myTransfers
                    .filter((tr) => tr.consumable?.nom === c.nom && tr.isAcknowledged)
                    .reduce((acc, tr) => acc + tr.quantite, 0);

                  const quantiteInitiale = Math.max(c.quantite, (bilan?.totalEntrees || 0) + totalReceived);
                  const consomme = Math.max(0, quantiteInitiale - c.quantite);
                  const stockActuel = c.stockActuel ?? c.quantite;
                  const isConsuming = consumeCatalogMutation.isPending && consumeCatalogMutation.variables?.id === c.id;
                  const isOwner = myServiceId != null && c.service?.id === myServiceId;
                  const maxConsommePossible = consomme + stockActuel;
                  const hasPendingInTable = myTransfers.some((t) => !t.isAcknowledged);

                  return (
                    <tr key={`c-${c.id}`} className="hover:bg-muted/30 transition-colors">
                      {hasPendingInTable && <td className="px-3 py-3" />}
                      <td className="px-3 py-3 font-semibold">{c.nom}</td>
                      <td className="px-3 py-3 text-xs text-muted-foreground">{c.category?.nom ?? "—"}</td>
                      <td className="px-3 py-3 text-xs text-muted-foreground">{c.service?.nom ?? "—"}</td>
                      <td className="px-3 py-3 font-medium tabular-nums">{quantiteInitiale.toLocaleString("fr-FR")}</td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">
                        {bilan?.totalTransfere != null ? bilan.totalTransfere.toLocaleString("fr-FR") : "—"}
                      </td>
                      <td className="px-3 py-3">
                        <span className={cn("font-semibold tabular-nums text-xs", stockActuel <= 5 ? "text-destructive" : "text-primary")}>
                          {stockActuel.toLocaleString("fr-FR")}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">
                        {c.prixInitial != null ? formatAmount(c.prixInitial) : "—"}
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground tabular-nums">
                        {total != null ? formatAmount(total) : "—"}
                      </td>
                      <td className="px-3 py-3 text-xs text-muted-foreground max-w-xs truncate" title={c.description ?? undefined}>
                        {c.description || "—"}
                      </td>
                      <td className="px-3 py-3">
                        {isOwner ? (
                          <CanAccess
                            permission="enregistrement_consommation_consomptible"
                            fallback={
                              <span className="text-sm font-semibold tabular-nums text-muted-foreground">
                                {consomme.toLocaleString("fr-FR")}
                              </span>
                            }
                          >
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              disabled={isConsuming || consomme <= 0}
                              onClick={() => consumeCatalogMutation.mutate({ id: c.id, quantite: c.quantite + 1 })}
                              className="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-sm hover:bg-muted disabled:opacity-40"
                              aria-label={t("consumables.decreaseConsumedFor", { nom: c.nom })}
                            >
                              <Minus className="h-3 w-3" />
                            </button>
                            {/* Saisie manuelle en plus des +/- — bornée entre 0 et le stock disponible */}
                            <input
                              key={`catalog-consumed-${c.id}-${consomme}`}
                              type="number"
                              min={0}
                              max={maxConsommePossible}
                              defaultValue={consomme}
                              disabled={isConsuming}
                              onInput={(e) => {
                                const target = e.currentTarget;
                                const clean = target.value.replace(/[^0-9]/g, "");
                                if (clean === "") {
                                  target.value = "";
                                  return;
                                }
                                const num = parseInt(clean, 10);
                                if (num > maxConsommePossible) {
                                  target.value = String(maxConsommePossible);
                                } else if (num < 0) {
                                  target.value = "0";
                                } else {
                                  target.value = String(num);
                                }
                              }}
                              onBlur={(e) => {
                                const raw = e.target.value.trim() === "" ? 0 : Number(e.target.value);
                                if (Number.isNaN(raw)) { e.target.value = String(consomme); return; }
                                const clampedConsomme = Math.max(0, Math.min(maxConsommePossible, Math.floor(raw)));
                                e.target.value = String(clampedConsomme);
                                if (clampedConsomme !== consomme) {
                                  consumeCatalogMutation.mutate({ id: c.id, quantite: quantiteInitiale - clampedConsomme });
                                }
                              }}
                              onKeyDown={(e) => {
                                if (e.key === "-" || e.key === "e" || e.key === "E" || e.key === "+" || e.key === "." || e.key === ",") {
                                  e.preventDefault();
                                }
                                if (e.key === "Enter") (e.target as HTMLInputElement).blur();
                              }}
                              className="h-7 w-14 rounded border border-border bg-background text-center text-sm font-semibold tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            <button
                              type="button"
                              disabled={isConsuming || stockActuel <= 0 || c.quantite <= 0}
                              onClick={() => consumeCatalogMutation.mutate({ id: c.id, quantite: c.quantite - 1 })}
                              className="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-sm hover:bg-muted disabled:opacity-40"
                              aria-label={t("consumables.increaseConsumedFor", { nom: c.nom })}
                              title={stockActuel <= 0 || c.quantite <= 0 ? t("consumables.nothingToConsume") : undefined}
                            >
                              <Plus className="h-3 w-3" />
                            </button>
                          </div>
                          </CanAccess>
                        ) : (
                          <span className="text-sm font-semibold tabular-nums text-muted-foreground">
                            {consomme.toLocaleString("fr-FR")}
                          </span>
                        )}
                      </td>
                      <td className="px-3 py-3">
                        {c.is_delete ? (
                          <Badge variant="outline" className="text-xs text-muted-foreground">{t("common.deleted")}</Badge>
                        ) : (
                          <Badge variant="default" className="border-emerald-300 bg-emerald-500/15 text-xs text-emerald-700">{t("status.active")}</Badge>
                        )}
                      </td>
                      <td className="px-3 py-3 text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              type="button"
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                              aria-label={t("consumables.actionsFor", { nom: c.nom })}
                              disabled={loadingEditId === c.id}
                            >
                              {loadingEditId === c.id ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                <MoreVertical className="h-4 w-4" />
                              )}
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <CanAccess anyOf={["creation_transfert_consomptible", "suppression_transfert_consomptible"]}>
                              <DropdownMenuItem onClick={() => setDetailTarget(c)}>
                                <ArrowLeftRight className="mr-2 h-4 w-4" /> {t("consumables.transfers")}
                              </DropdownMenuItem>
                            </CanAccess>
                            <CanAccess permission="modification_consomptible">
                              <DropdownMenuItem
                                onClick={async () => {
                                  // La liste ne renvoie pas les pièces jointes (seul le
                                  // détail les inclut) — on recharge toujours le détail
                                  // avant d'ouvrir l'édition, même pattern que ChampsSection.
                                  setLoadingEditId(c.id);
                                  try {
                                    const res = await getConsumableById(c.id);
                                    setSelected(res.data ?? c);
                                  } catch {
                                    setSelected(c);
                                  } finally {
                                    setLoadingEditId(null);
                                  }
                                  setView("form");
                                }}
                              >
                                <Pencil className="mr-2 h-4 w-4" /> {t("action.edit")}
                              </DropdownMenuItem>
                            </CanAccess>
                            {c.is_delete ? (
                              <CanAccess permission="suppression_consomptible">
                                <DropdownMenuItem onClick={() => restoreMutation.mutate(c.id)}>
                                  <RotateCcw className="mr-2 h-4 w-4" /> {t("action.restore")}
                                </DropdownMenuItem>
                              </CanAccess>
                            ) : (
                              <CanAccess permission="suppression_consomptible">
                                <DropdownMenuItem
                                  onClick={() => setDeleteTarget(c)}
                                  className="text-destructive focus:text-destructive"
                                >
                                  <Trash2 className="mr-2 h-4 w-4" /> {t("action.delete")}
                                </DropdownMenuItem>
                              </CanAccess>
                            )}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <Pagination
              page={page}
              pageSize={pageSize}
              total={totalConsumables}
              onPageChange={setPage}
              onPageSizeChange={() => {}}
              pageSizeOptions={[pageSize]}
            />
          </div>
        )}
      </div>

      <ConsumableDetailDialog
        consumable={detailTarget}
        open={detailTarget !== null}
        onClose={() => setDetailTarget(null)}
      />

      <AlertDialog open={!!deleteTarget} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("consumables.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("consumables.delete.descBefore")} <strong>{deleteTarget?.nom}</strong> {t("consumables.delete.descAfter")}
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

// ─── Formulaire création / édition ──────────────────────────────────────────

interface ConsomptibleFormState {
  nom: string;
  description: string;
  quantite: string;
  prixInitial: string;
  categoryId: string;
  posteId: string;
  posteLabel: string;
}

const emptyForm: ConsomptibleFormState = {
  nom: "",
  description: "",
  quantite: "",
  prixInitial: "",
  categoryId: "",
  posteId: "",
  posteLabel: "",
};

function buildFormFromConsumable(c: ApiConsumable): ConsomptibleFormState {
  return {
    nom: c.nom ?? "",
    description: c.description ?? "",
    quantite: c.quantite != null ? String(c.quantite) : "",
    prixInitial: c.prixInitial != null ? String(c.prixInitial) : "",
    categoryId: c.category?.id != null ? String(c.category.id) : "",
    posteId: c.service?.id != null ? String(c.service.id) : "",
    posteLabel: c.service?.nom ?? "",
  };
}

function ConsomptibleForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiConsumable | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const isEdit = !!initial;

  const { data: categoriesData } = useQuery({
    queryKey: ["categories", "consommable"],
    queryFn: () => listCategories({ limit: 1000, consommable: "true" }),
  });
  const consommableCategories: ApiCategory[] = categoriesData?.data?.data ?? [];
  // Init paresseuse depuis la prop, puis re-synchronisation uniquement quand
  // l'identité du consomptible édité change (pas à chaque frappe) — évite
  // que les champs se réinitialisent pendant que l'utilisateur modifie.
  const [form, setForm] = useState<ConsomptibleFormState>(() =>
    initial ? buildFormFromConsumable(initial) : emptyForm,
  );
  const [pieces, setPieces] = useState<Array<{ file: File; nom: string }>>([]);
  const [existingPieces, setExistingPieces] = useState(initial?.piecesJointes ?? []);
  const filePieceJointeInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setForm(initial ? buildFormFromConsumable(initial) : emptyForm);
    setExistingPieces(initial?.piecesJointes ?? []);
    setPieces([]);
    if (filePieceJointeInputRef.current) filePieceJointeInputRef.current.value = "";
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initial?.id]);

  const set = <K extends keyof ConsomptibleFormState>(key: K, value: ConsomptibleFormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  // Prix total — jamais calculé par le backend (voir en-tête du fichier),
  // recalculé ici à chaque frappe. Lecture seule, jamais envoyé au serveur.
  const prixUnitaireNum = Number(form.prixInitial);
  const quantiteNum = Number(form.quantite);
  const prixTotalCalcule =
    form.prixInitial.trim() && form.quantite.trim() && !Number.isNaN(prixUnitaireNum) && !Number.isNaN(quantiteNum)
      ? prixUnitaireNum * quantiteNum
      : null;

  // Choisir un ou plusieurs fichiers les ajoute directement à la liste, avec
  // leur nom de fichier comme libellé par défaut — chaque pièce a ensuite son
  // propre champ de nom éditable dans la liste ci-dessous (même pattern que
  // "Nouveau bien").
  const onPickPieceJointe = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? []);
    if (files.length === 0) return;
    setPieces((prev) => [...prev, ...files.map((file) => ({ file, nom: file.name }))]);
    e.target.value = "";
  };

  const removeExistingPiece = async (pieceJointeId: number) => {
    if (!initial) return;
    try {
      await deleteConsumablePieceJointe(initial.id, pieceJointeId);
      setExistingPieces((prev) => prev.filter((p) => p.id !== pieceJointeId));
      queryClient.invalidateQueries({ queryKey: ["consumables"] });
    } catch {
      toast.error(t("consumables.error.deleteAttachment"));
    }
  };

  const createMutation = useMutation({
    mutationFn: (payload: CreateConsumablePayload) => createConsumable(payload),
    onSuccess: () => {
      toast.success(t("consumables.toast.created"));
      onSaved();
    },
    onError: () => toast.error(t("consumables.error.create")),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateConsumablePayload }) =>
      updateConsumable(id, payload),
    onSuccess: async (_res, { id, payload }) => {
      // PATCH /consumables/{id} est un no-op confirmé côté backend (voir
      // l'en-tête du fichier) : la réponse dit "succès" même quand rien
      // n'est écrit. On revérifie en relisant l'enregistrement plutôt que
      // de faire confiance à la réponse du PATCH.
      let actuallyChanged = true;
      try {
        const fresh = await getConsumableById(id);
        const c = fresh.data;
        actuallyChanged =
          c?.nom === payload.nom &&
          (c?.description ?? "") === (payload.description ?? "") &&
          (payload.category_id === undefined || (c?.category?.id ?? null) === payload.category_id);
      } catch {
        // Revérification impossible — on ne peut pas se prononcer, on
        // laisse le message de succès générique plutôt que d'inventer un
        // échec.
      }
      if (!actuallyChanged) {
        toast.error(
          t("consumables.error.updateNoop"),
          { duration: 8000 },
        );
      } else {
        toast.success(t("consumables.toast.updated"));
      }
      onSaved();
    },
    onError: () => toast.error(t("consumables.error.update")),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.nom.trim()) {
      toast.error(t("consumables.error.nameRequired"));
      return;
    }
    if (!form.description.trim()) {
      toast.error(t("consumables.error.descriptionRequired"));
      return;
    }
    if (!isEdit) {
      if (!form.categoryId) {
        toast.error(t("consumables.error.categoryRequired"));
        return;
      }
      if (pieces.length === 0) {
        toast.error(t("consumables.error.fileRequired"));
        return;
      }
      const quantite = Number(form.quantite);
      if (!form.quantite.trim() || Number.isNaN(quantite) || quantite <= 0) {
        toast.error(t("consumables.error.quantityPositive"));
        return;
      }
      const prixInitial = form.prixInitial.trim() ? Number(form.prixInitial) : undefined;
      const payload: CreateConsumablePayload = {
        nom: form.nom.trim(),
        description: form.description.trim(),
        quantite,
        prixInitial,
        category_id: form.categoryId ? Number(form.categoryId) : undefined,
        service_id: form.posteId ? Number(form.posteId) : undefined,
        piecesJointes: pieces.length > 0 ? pieces.map((p) => p.file) : undefined,
        piecesJointesNoms: pieces.length > 0 ? pieces.map((p) => p.nom) : undefined,
      };
      createMutation.mutate(payload);
    } else {
      const payload: UpdateConsumablePayload = {
        nom: form.nom.trim(),
        description: form.description.trim(),
        // Quantité (stock) volontairement absente — voir en-tête du fichier :
        // non modifiable après création, le formulaire d'édition l'affiche en
        // lecture seule sous "Stock actuel" plutôt que de la renvoyer.
        ...(form.prixInitial.trim() ? { prixInitial: Number(form.prixInitial) } : {}),
        category_id: form.categoryId ? Number(form.categoryId) : null,
        service_id: form.posteId ? Number(form.posteId) : null,
        piecesJointes: pieces.length > 0 ? pieces.map((p) => p.file) : undefined,
        piecesJointesNoms: pieces.length > 0 ? pieces.map((p) => p.nom) : undefined,
      };
      updateMutation.mutate({ id: initial!.id, payload });
    }
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Package className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-base font-semibold">
              {initial ? t("consumables.editTitle", { nom: initial.nom }) : t("consumables.new")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("consumables.subtitle")}
            </p>
          </div>
        </div>

        <div className="space-y-4 px-5 py-5">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label>
                {t("consumables.field.name.label")} <span className="text-destructive">*</span>
              </Label>
              <Input
                value={form.nom}
                onChange={(e) => set("nom", e.target.value)}
                placeholder={t("consumables.field.name.label")}
                required
              />
            </div>
            <div className="space-y-1.5">
              {isEdit ? (
                <>
                  <Label>{t("consumables.field.stockActuel")}</Label>
                  <Input
                    type="text"
                    readOnly
                    value={initial?.stockActuel ?? initial?.quantite ?? "—"}
                    className="opacity-60"
                  />
                </>
              ) : (
                <>
                  <Label>
                    {t("consumables.field.initialQuantity")} <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    type="number"
                    min={0}
                    value={form.quantite}
                    onChange={(e) => set("quantite", e.target.value)}
                    placeholder="0"
                    required
                  />
                </>
              )}
            </div>
            <div className="space-y-1.5">
              <Label>
                {t("common.category")} {!isEdit && <span className="text-destructive">*</span>}
              </Label>
              <Select value={form.categoryId} onValueChange={(v) => set("categoryId", v)}>
                <SelectTrigger>
                  <SelectValue placeholder={t("consumables.noCategory")} />
                </SelectTrigger>
                <SelectContent>
                  {consommableCategories.map((cat) => (
                    <SelectItem key={cat.id} value={String(cat.id)}>
                      {cat.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>{t("consumables.field.unitPrice")}</Label>
              <Input
                type="number"
                min={0}
                value={form.prixInitial}
                onChange={(e) => set("prixInitial", e.target.value)}
                placeholder="0"
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("consumables.field.totalPrice")}</Label>
              <Input
                type="text"
                readOnly
                value={prixTotalCalcule != null ? formatFCFA(prixTotalCalcule) : "—"}
                className="opacity-60"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label>
              {t("consumables.field.structureService")}
            </Label>
            <OrgTreeSelect
              value={form.posteId ? Number(form.posteId) : null}
              valueLabel={form.posteLabel}
              onSelect={(node) => {
                setForm((prev) => ({
                  ...prev,
                  posteId: String(node.id),
                  posteLabel: formatPosteLabel(node),
                }));
              }}
              onClear={() => setForm((prev) => ({ ...prev, posteId: "", posteLabel: "" }))}
              selectableType="Poste"
              placeholder={t("consumables.selectPoste")}
              searchPlaceholder={t("consumables.filter.searchPoste")}
            />
            <p className="text-[10px] text-muted-foreground">
              {t("consumables.posteHint")}
            </p>
          </div>

          <div className="space-y-1.5">
            <Label>
              {t("common.description")} <span className="text-destructive">*</span>
            </Label>
            <Textarea
              value={form.description}
              onChange={(e) => set("description", e.target.value)}
              placeholder={t("consumables.descriptionPlaceholder")}
              rows={3}
              required
            />
          </div>

          <div className="space-y-1.5">
            <Label>
              {t("common.attachments")}{!isEdit && <span className="text-destructive"> *</span>}
            </Label>
            {!isEdit && (
              <p className="text-[10px] text-muted-foreground">
                {t("consumables.error.fileRequired")}
              </p>
            )}
            {existingPieces.length > 0 && (
              <ul className="space-y-1.5">
                {existingPieces.map((p) => (
                  <li
                    key={p.id}
                    className="flex items-center gap-2 rounded-md border border-border bg-muted/20 px-2 py-1.5 text-xs"
                  >
                    <span className="text-muted-foreground">📎</span>
                    <span className="flex-1 truncate">{p.nom}</span>
                    <button
                      type="button"
                      onClick={() => removeExistingPiece(p.id)}
                      className="text-destructive"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
            <label className="flex w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-3 text-center text-xs text-muted-foreground transition hover:bg-primary/10">
              <span>{t("consumables.addFiles")}</span>
              <Input
                ref={filePieceJointeInputRef}
                className="hidden"
                type="file"
                multiple
                onChange={onPickPieceJointe}
              />
            </label>
            {pieces.length > 0 && (
              <ul className="space-y-1.5">
                {pieces.map((p, i) => (
                  <li
                    key={i}
                    className="flex items-center gap-2 rounded-md border border-border bg-muted/30 px-2 py-1.5 text-xs"
                  >
                    <span className="text-muted-foreground">📄</span>
                    <Input
                      className="h-6 flex-1 text-[10px] px-1"
                      placeholder={t("common.labelPlaceholder")}
                      value={p.nom}
                      onChange={(e) => {
                        const u = [...pieces];
                        u[i] = { ...u[i], nom: e.target.value };
                        setPieces(u);
                      }}
                    />
                    <button
                      type="button"
                      onClick={() => setPieces((prev) => prev.filter((_, j) => j !== i))}
                      className="text-destructive"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 border-t border-border px-5 py-4">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            {t("action.cancel")}
          </Button>
          <Button type="submit" disabled={isPending} className="gap-2">
            {isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            {initial ? t("action.update") : t("action.create")}
          </Button>
        </div>
      </form>
    </div>
  );
}
