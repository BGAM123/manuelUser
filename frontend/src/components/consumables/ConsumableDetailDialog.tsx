/**
 * ConsumableDetailDialog — Recommandation 111 (suite) : gestion des
 * transferts de consomptibles (BSP uniquement, voir rec. 400) + bilan de
 * stock.
 *
 * Rec. 400-403 (2026-08-21) :
 *   - Transfert direct retiré entièrement — seul le BSP subsiste.
 *   - Le champ "Service destinataire ou bénéficiaire" (organigramme en
 *     cascade jusqu'à une personne) est remplacé par le champ exact utilisé
 *     dans le formulaire "Nouveau bien" : "Structure / Service (Poste)"
 *     (PosteOrgSelect / OrgTreeSelect, src/components/shared/OrgTreeSelect.tsx),
 *     qui ne montre que les nœuds de type "Poste" et affiche le titulaire
 *     (matricule + nom, ou "Vacant") — même pattern que AffectationFormInline.
 *   - Bouton "Confirmer le retour" et sa logique retirés (fonctionnalité
 *     abandonnée — endpoint confirmé cassé côté backend de toute façon).
 *   - Colonne "Statut" retirée du tableau des transferts.
 *
 * ⚠️ OrgTreeSelect utilise un vrai Popover Radix (pas le dropdown custom de
 * ServiceBeneficiaireSelect) — Radix a un bug connu de fermeture au moindre
 * clic quand un Popover est imbriqué dans un Dialog. Repris ici tel quel à
 * la demande explicite ("le champ exact"). À surveiller : si le popover se
 * ferme de façon intempestive en testant, il faudra le reconstruire sur le
 * même modèle "dropdown custom" que ServiceBeneficiaireSelect.
 */

import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Package, Plus, Trash2, X } from "lucide-react";
import { toast } from "sonner";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { createMinepiaPdfHeaderRenderer, MINEPIA_PDF_HEADER_HEIGHT } from "@/services/minepiaDocumentHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { OrgTreeSelect as PosteOrgSelect, formatPosteLabel } from "@/components/shared/OrgTreeSelect";
import { cn } from "@/utils/utils";
import { getConsumableBilan, type ApiConsumable } from "@/api/consumables/consumables.api";
import { formatFCFA } from "@/api/common";
import {
  listConsumableTransfersByConsumable,
  createConsumableTransfer,
  getConsumableTransferById,
  deleteConsumableTransfer,
  type ApiConsumableTransfer,
} from "@/api/consumables/consumable-transfers.api";
import type { ApiOrgNode } from "@/api/services/services.api";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";

function formatQty(n: number) {
  return n.toLocaleString("fr-FR");
}

/**
 * PDF du BSP d'un transfert de consomptible — même format que le BSP des
 * biens (generateBspPdf dans Biens.tsx), basé sur les documents de
 * référence réels (formulaire MINEPIA vierge + exemple rempli) : "ORDRE DE
 * SORTIE N°", titre en 2 lignes, "SERVICE DEMANDEUR"/"N° BSP", tableau N°
 * d'ordre/Désignation/Espèce des unités/Quantités/Observations, signatures
 * sans tirets, ligne "Acquitté le" en bas.
 *
 * Le nom du bénéficiaire et le nom du service sont résolus côté client (au
 * moment de la soumission) plutôt que relus depuis la réponse serveur —
 * ApiConsumableTransfer n'expose ni le nom du bénéficiaire ni celui du
 * service destinataire, seulement leurs ids.
 */
async function generateConsumableBspPdf(params: {
  consumableNom: string;
  serviceNom: string;
  destinataireNom: string;
  numero: string;
  transferId: number;
  quantiteDemandee?: number;
  quantiteAccordee?: number;
  quantiteServie: number;
  dateEtablissement?: string;
  observations?: string;
}): Promise<void> {
  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
  const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
  const pageW = doc.internal.pageSize.getWidth();
  renderHeader();
  let y = MINEPIA_PDF_HEADER_HEIGHT + 6;

  doc.setFontSize(9);
  doc.setFont("helvetica", "normal");
  doc.text(`ORDRE DE SORTIE N° ${params.transferId}`, 14, y);
  y += 8;

  doc.setFontSize(13);
  doc.setFont("helvetica", "bold");
  doc.text("BON DE SORTIE PROVISOIRE", pageW / 2, y, { align: "center" });
  y += 6;
  doc.text("DEMANDE DE MATERIEL", pageW / 2, y, { align: "center" });
  y += 10;

  doc.setFontSize(9);
  doc.setFont("helvetica", "normal");
  doc.text(`SERVICE DEMANDEUR : ${params.serviceNom || "—"}`, 14, y);
  doc.text(`N° BSP : ${params.numero}`, pageW - 14, y, { align: "right" });
  y += 6;
  doc.text(`Bénéficiaire : ${params.destinataireNom || "—"}`, 14, y);
  doc.text(`Date d'établissement : ${params.dateEtablissement || "—"}`, pageW - 14, y, { align: "right" });
  y += 10;

  autoTable(doc, {
    startY: y,
    head: [["N° d'ordre", "Désignation des matières, denrées et objets", "Espèce des unités", "Demandée", "Accordée", "Servie", "Observations"]],
    body: [[
      "1",
      params.consumableNom,
      "N",
      params.quantiteDemandee != null ? String(params.quantiteDemandee) : "—",
      params.quantiteAccordee != null ? String(params.quantiteAccordee) : "—",
      String(params.quantiteServie),
      params.observations || "",
    ]],
    styles: { fontSize: 8, cellPadding: 2.5 },
    headStyles: { fillColor: [230, 230, 230], textColor: 20, fontStyle: "bold", fontSize: 7.5 },
    columnStyles: { 0: { cellWidth: 14 }, 2: { cellWidth: 18 }, 3: { cellWidth: 16 }, 4: { cellWidth: 16 }, 5: { cellWidth: 16 } },
    margin: { top: MINEPIA_PDF_HEADER_HEIGHT + 4 },
    didDrawPage: () => { renderHeader(); },
  });

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const finalY = ((doc as any).lastAutoTable?.finalY ?? y) + 16;
  const today = new Date().toLocaleDateString("fr-FR");
  const colW = pageW / 3;
  doc.setFontSize(9);
  doc.text("Le demandeur", colW * 0.5, finalY, { align: "center" });
  doc.text("L'Ordonnateur Matières", colW * 1.5, finalY, { align: "center" });
  doc.text("le Comptable matières", colW * 2.5, finalY, { align: "center" });
  doc.text(`A Yaoundé le ${today}`, colW * 0.5, finalY + 6, { align: "center" });
  doc.text("A Yaoundé le……………….", colW * 1.5, finalY + 6, { align: "center" });
  doc.text("A Yaoundé le……………….", colW * 2.5, finalY + 6, { align: "center" });

  doc.setFontSize(9);
  doc.text("Acquitté le……………….", 14, finalY + 20);

  doc.save(`BSP-${params.numero}.pdf`);
}

export function ConsumableDetailDialog({
  consumable,
  open,
  onClose,
}: {
  consumable: ApiConsumable | null;
  open: boolean;
  onClose: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null);
  const consumableId = consumable?.id ?? null;

  const bilanQuery = useQuery({
    queryKey: ["consumable-bilan", consumableId],
    queryFn: () => getConsumableBilan(consumableId!),
    enabled: open && consumableId != null,
  });
  const bilan = bilanQuery.data?.data;

  const transfersQuery = useQuery({
    queryKey: ["consumable-transfers", "by-consumable", consumableId],
    queryFn: () => listConsumableTransfersByConsumable(consumableId!),
    enabled: open && consumableId != null,
  });
  const transfers: ApiConsumableTransfer[] = transfersQuery.data ?? [];

  const invalidateAll = () => {
    queryClient.invalidateQueries({ queryKey: ["consumable-bilan", consumableId] });
    queryClient.invalidateQueries({
      queryKey: ["consumable-transfers", "by-consumable", consumableId],
    });
  };

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteConsumableTransfer(id),
    onSuccess: () => {
      invalidateAll();
      toast.success(t("consumables.toast.transferDeleted"));
    },
    onError: () => toast.error(t("consumables.error.deleteTransfer")),
  });

  return (
    <>
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Package className="h-4 w-4" /> {consumable?.nom ?? t("consumables.field.name")}
          </DialogTitle>
          {(consumable?.prixInitial != null || consumable?.prixTotal != null) && (
            <p className="text-xs text-muted-foreground">
              {consumable?.prixInitial != null && t("consumables.priceInline.unit", { value: formatFCFA(consumable.prixInitial) })}
              {consumable?.prixInitial != null && consumable?.prixTotal != null && " · "}
              {consumable?.prixTotal != null && t("consumables.priceInline.total", { value: formatFCFA(consumable.prixTotal) })}
            </p>
          )}
        </DialogHeader>

        <div className="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1">
          {/* ── Bilan de stock ── */}
          <div>
            <h3 className="mb-2 text-sm font-semibold text-foreground">{t("consumables.stockSummary")}</h3>
            {bilanQuery.isLoading ? (
              <p className="text-sm text-muted-foreground">{t("common.loading")}</p>
            ) : !bilan ? (
              <p className="text-sm text-destructive">{t("consumables.bilanUnavailable")}</p>
            ) : (
              <>
                <div className="grid grid-cols-3 gap-3">
                  <div className="rounded-lg border border-border bg-muted/30 p-3 text-center">
                    <p className="text-xs text-muted-foreground">{t("consumables.field.initialQuantity")}</p>
                    <p className="text-lg font-semibold tabular-nums">
                      {formatQty(bilan.totalEntrees)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border bg-muted/30 p-3 text-center">
                    <p className="text-xs text-muted-foreground">{t("consumables.field.transferredQuantityFull")}</p>
                    <p className="text-lg font-semibold tabular-nums">
                      {formatQty(bilan.totalTransfere)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border bg-primary/5 p-3 text-center">
                    <p className="text-xs text-muted-foreground">{t("consumables.stockRemaining")}</p>
                    <p className="text-lg font-semibold tabular-nums text-primary">
                      {formatQty(bilan.stockActuel)}
                    </p>
                  </div>
                </div>
                {bilan.parService.length > 0 && (
                  <div className="mt-2 space-y-1">
                    <p className="text-xs font-medium text-muted-foreground">
                      {t("consumables.breakdownByService")}
                    </p>
                    <ul className="space-y-1">
                      {bilan.parService.map((s) => (
                        <li
                          key={s.serviceId}
                          className="flex items-center justify-between rounded-md border border-border bg-card px-2.5 py-1.5 text-xs"
                        >
                          <span>{s.serviceNom}</span>
                          <span className="font-medium tabular-nums">{formatQty(s.quantite)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </>
            )}
          </div>

          {/* ── Transferts — historique en lecture seule (aucune case à cocher
              ni action ici : l'accusé de réception et la consommation se font
              depuis "Mes transferts reçus" sur la liste principale, comme le
              bouton "Sécuriser" pour les biens). ── */}
          <div>
            <div className="mb-2 flex items-center justify-between gap-2">
              <h3 className="text-sm font-semibold text-foreground">{t("consumables.transfers")}</h3>
              <div className="flex items-center gap-2">
                {!showForm && (
                  <CanAccess permission="creation_transfert_consomptible">
                    <Button
                      size="sm"
                      variant="outline"
                      className="gap-1.5"
                      onClick={() => setShowForm(true)}
                    >
                      <Plus className="h-3.5 w-3.5" /> {t("consumables.newTransfer")}
                    </Button>
                  </CanAccess>
                )}
              </div>
            </div>

            {showForm && consumableId != null && (
              <TransferForm
                consumableId={consumableId}
                consumableNom={consumable?.nom ?? ""}
                stockDisponible={bilan?.stockActuel ?? 0}
                onCancel={() => setShowForm(false)}
                onSaved={() => {
                  setShowForm(false);
                  invalidateAll();
                }}
              />
            )}

            {transfersQuery.isLoading ? (
              <p className="text-sm text-muted-foreground">{t("common.loading")}</p>
            ) : transfers.length === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">
                {t("consumables.noTransfers")}
              </p>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/40">
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
                        {t("common.date")}
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
                        {t("consumables.field.type")}
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
                        {t("consumables.field.service")}
                      </th>
                      <th className="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">
                        {t("consumables.field.transferredQuantityFull")}
                      </th>
                      <th className="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">
                        {t("consumables.field.consumed")}
                      </th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
                        {t("common.status")}
                      </th>
                      <th className="w-14 px-3 py-2 text-right text-xs font-semibold text-muted-foreground">
                        {t("common.actions")}
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {transfers.map((tr) => {
                      const isPendingReceipt = !tr.isAcknowledged;
                      return (
                        <tr
                          key={tr.id}
                          className={cn(isPendingReceipt && "bg-muted/20 text-muted-foreground")}
                        >
                          <td className="whitespace-nowrap px-3 py-2 text-xs">{tr.dateTransfert ?? "—"}</td>
                          <td className="px-3 py-2 text-xs">
                            {tr.type === "BSP" ? (
                              <Badge variant="secondary" className="text-[10px]">
                                BSP {tr.consumableBsp?.bsp?.numero ?? ""}
                              </Badge>
                            ) : (
                              <Badge variant="outline" className="text-[10px]">
                                {t("consumables.directTransfer")}
                              </Badge>
                            )}
                          </td>
                          <td className="px-3 py-2 text-xs">{tr.serviceDestination?.nom ?? "—"}</td>
                          <td className="px-3 py-2 text-right text-xs font-medium tabular-nums">
                            {formatQty(tr.quantite)}
                          </td>
                          <td className="px-3 py-2 text-right text-xs tabular-nums">
                            {formatQty(tr.quantityConsumed)}
                          </td>
                          <td className="px-3 py-2">
                            {isPendingReceipt ? (
                              <Badge variant="outline" className="text-xs text-amber-600 border-amber-300 bg-amber-50">
                                {t("consumables.notReceived")}
                              </Badge>
                            ) : (
                              <Badge variant="default" className="border-emerald-300 bg-emerald-500/15 text-xs text-emerald-700">
                                {t("consumables.received")}
                              </Badge>
                            )}
                          </td>
                          <td className="px-3 py-2 text-right">
                            <CanAccess permission="suppression_transfert_consomptible">
                              <div className="flex items-center justify-end gap-1">
                                <button
                                  type="button"
                                  title={t("action.delete")}
                                  disabled={deleteMutation.isPending}
                                  onClick={() => setDeleteTarget(tr.id)}
                                  className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:opacity-40"
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </button>
                              </div>
                            </CanAccess>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <AlertDialog open={deleteTarget !== null} onOpenChange={(v) => !v && setDeleteTarget(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t("consumables.deleteTransfer.title")}</AlertDialogTitle>
          <AlertDialogDescription>
            {t("consumables.deleteTransfer.desc")}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            onClick={() => {
              if (deleteTarget !== null) deleteMutation.mutate(deleteTarget);
              setDeleteTarget(null);
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

// ─── Formulaire de nouveau transfert (BSP uniquement — rec. 400) ───────────

interface BspBlock {
  key: number;
  posteId: number | null;
  posteLabel: string;
  posteNom: string;
  beneficiaireId: number | null;
  beneficiaireNom: string;
  quantiteDemandee: string;
  quantiteAccordee: string;
  quantiteServie: string;
}

/** Un payload de création + les infos d'affichage nécessaires au PDF, pour un bénéficiaire déjà fusionné. */
interface MergedBspEntry {
  posteId: number;
  posteNom: string;
  beneficiaireId: number;
  beneficiaireNom: string;
  quantiteDemandee?: number;
  quantiteAccordee?: number;
  quantiteServie: number;
}

function TransferForm({
  consumableId,
  consumableNom,
  stockDisponible,
  onCancel,
  onSaved,
}: {
  consumableId: number;
  consumableNom: string;
  stockDisponible: number;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const keySeq = useRef(0);
  const emptyBlock = (): BspBlock => ({
    key: ++keySeq.current,
    posteId: null,
    posteLabel: "",
    posteNom: "",
    beneficiaireId: null,
    beneficiaireNom: "",
    quantiteDemandee: "",
    quantiteAccordee: "",
    quantiteServie: "",
  });
  // "Structure / Service (Poste)" — champ repris tel quel du formulaire
  // "Nouveau bien" (rec. 401). Le bénéficiaire est le titulaire du poste
  // sélectionné, pas une personne choisie indépendamment. Rec. 404 :
  // plusieurs blocs BSP empilables ("Nouveau BSP +"), un par bénéficiaire
  // visé — fusionnés par personne à la soumission (voir submit()).
  const [blocks, setBlocks] = useState<BspBlock[]>(() => [emptyBlock()]);
  const [dateTransfert, setDateTransfert] = useState(new Date().toISOString().slice(0, 10));
  const [dateEtablissement, setDateEtablissement] = useState(new Date().toISOString().slice(0, 10));
  const [observations, setObservations] = useState("");

  const updateBlock = (key: number, patch: Partial<BspBlock>) =>
    setBlocks((prev) => prev.map((b) => (b.key === key ? { ...b, ...patch } : b)));
  // Aucune quantité (demandée/accordée/servie) ne peut dépasser le stock
  // disponible — plafonnée dès la frappe, pas seulement à la soumission.
  const clampToStock = (raw: string) => {
    const num = Number(raw);
    if (raw.trim() !== "" && !Number.isNaN(num) && num > stockDisponible) {
      return String(stockDisponible);
    }
    return raw;
  };
  // Invariant demandée >= accordée >= servie — appliqué dès la frappe : si un
  // champ dépasse le plafond posé par le champ au-dessus, il est ramené à ce
  // plafond ; abaisser un champ du dessus fait redescendre en cascade ceux du
  // dessous s'ils le dépassent désormais.
  const updateQtyField = (
    key: number,
    field: "quantiteDemandee" | "quantiteAccordee" | "quantiteServie",
    rawValue: string,
  ) => {
    const clamped = clampToStock(rawValue);
    setBlocks((prev) =>
      prev.map((b) => {
        if (b.key !== key) return b;
        const next = { ...b, [field]: clamped };
        const demandeeNum = next.quantiteDemandee.trim() !== "" ? Number(next.quantiteDemandee) : null;
        let accordeeNum = next.quantiteAccordee.trim() !== "" ? Number(next.quantiteAccordee) : null;
        let servieNum = next.quantiteServie.trim() !== "" ? Number(next.quantiteServie) : null;
        if (demandeeNum != null && accordeeNum != null && accordeeNum > demandeeNum) {
          accordeeNum = demandeeNum;
          next.quantiteAccordee = String(accordeeNum);
        }
        const plafondServie = accordeeNum ?? demandeeNum;
        if (plafondServie != null && servieNum != null && servieNum > plafondServie) {
          servieNum = plafondServie;
          next.quantiteServie = String(servieNum);
        }
        return next;
      }),
    );
  };
  const addBlock = () => setBlocks((prev) => [...prev, emptyBlock()]);
  const removeBlock = (key: number) =>
    setBlocks((prev) => (prev.length > 1 ? prev.filter((b) => b.key !== key) : prev));

  const selectPosteForBlock = (key: number, node: ApiOrgNode) => {
    updateBlock(key, {
      posteId: node.id,
      posteLabel: formatPosteLabel(node),
      posteNom: node.nom,
      beneficiaireId: node.utilisateur?.id ?? null,
      beneficiaireNom: node.utilisateur ? `${node.utilisateur.firstName} ${node.utilisateur.lastName}` : "",
    });
  };
  const clearPosteForBlock = (key: number) =>
    updateBlock(key, { posteId: null, posteLabel: "", posteNom: "", beneficiaireId: null, beneficiaireNom: "" });

  // Un par un (pas en parallèle) — le serveur ne gère pas bien des créations
  // simultanées de BSP sur le même consomptible (déjà confirmé côté biens,
  // même mécanisme de numérotation partagé). Un vrai transfert + un PDF par
  // bénéficiaire DISTINCT — les blocs visant la même personne sont fusionnés
  // avant l'envoi (quantités additionnées), pas un transfert par bloc.
  const submitMutation = useMutation({
    mutationFn: async () => {
      const merged = new Map<number, MergedBspEntry & { hasDemandee: boolean; hasAccordee: boolean }>();
      const order: number[] = [];
      for (const b of blocks) {
        const demandee = b.quantiteDemandee.trim() ? Number(b.quantiteDemandee) : undefined;
        const accordee = b.quantiteAccordee.trim() ? Number(b.quantiteAccordee) : undefined;
        const servie = Number(b.quantiteServie);
        const key = b.beneficiaireId!;
        const existing = merged.get(key);
        if (existing) {
          existing.quantiteDemandee = (existing.quantiteDemandee ?? 0) + (demandee ?? 0);
          existing.quantiteAccordee = (existing.quantiteAccordee ?? 0) + (accordee ?? 0);
          existing.quantiteServie += servie;
          existing.hasDemandee = existing.hasDemandee || demandee != null;
          existing.hasAccordee = existing.hasAccordee || accordee != null;
        } else {
          merged.set(key, {
            posteId: b.posteId!,
            posteNom: b.posteNom,
            beneficiaireId: b.beneficiaireId!,
            beneficiaireNom: b.beneficiaireNom,
            quantiteDemandee: demandee,
            quantiteAccordee: accordee,
            quantiteServie: servie,
            hasDemandee: demandee != null,
            hasAccordee: accordee != null,
          });
          order.push(key);
        }
      }

      let createdCount = 0;
      const errors: string[] = [];
      for (const beneficiaireId of order) {
        const m = merged.get(beneficiaireId)!;
        let transferId: number;
        try {
          const res = await createConsumableTransfer({
            type: "BSP",
            consumable_id: consumableId,
            service_destination_id: m.posteId,
            quantite: m.quantiteServie,
            dateTransfert,
            observations: observations.trim() || undefined,
            service_id: m.posteId,
            beneficiaire_id: m.beneficiaireId,
            quantiteDemandee: m.hasDemandee ? m.quantiteDemandee : undefined,
            quantiteAccordee: m.hasAccordee ? m.quantiteAccordee : undefined,
            quantiteServie: m.quantiteServie,
            dateEtablissement,
          });
          transferId = res.data.id;
          createdCount++;
        } catch (err) {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          errors.push(`${m.beneficiaireNom} : ${msg ?? t("consumables.unknownError")}`);
          continue;
        }
        // Le numéro réel du BSP n'est pas fiable dans la réponse du POST
        // (déjà vu : réponse d'écriture incomplète sur ce backend) — on
        // relit le transfert fraîchement créé avant de générer le document.
        try {
          const fresh = await getConsumableTransferById(transferId);
          const numero = fresh.data?.consumableBsp?.bsp?.numero;
          if (numero) {
            await generateConsumableBspPdf({
              consumableNom,
              serviceNom: m.posteNom,
              destinataireNom: m.beneficiaireNom,
              numero,
              transferId,
              quantiteDemandee: m.hasDemandee ? m.quantiteDemandee : undefined,
              quantiteAccordee: m.hasAccordee ? m.quantiteAccordee : undefined,
              quantiteServie: m.quantiteServie,
              dateEtablissement,
              observations: observations.trim() || undefined,
            });
          } else {
            errors.push(t("consumables.bsp.numberNotFound", { nom: m.beneficiaireNom }));
          }
        } catch {
          errors.push(t("consumables.bsp.pdfFailed", { nom: m.beneficiaireNom }));
        }
      }
      return { createdCount, total: order.length, errors };
    },
    onSuccess: ({ createdCount, total, errors }) => {
      if (errors.length > 0) {
        toast.error(t("consumables.bsp.partialSuccess", { created: createdCount, total, errors: errors.join(" ; ") }));
      } else {
        toast.success(t("consumables.bsp.success", { created: createdCount }));
      }
      onSaved();
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("consumables.error.registerTransfer", { status: status ?? "" }));
    },
  });

  const submit = () => {
    for (let i = 0; i < blocks.length; i++) {
      const b = blocks[i];
      const label = blocks.length > 1 ? `BSP ${i + 1} : ` : "";
      if (!b.posteId) {
        toast.error(`${label}${t("consumables.validation.selectPoste")}`);
        return;
      }
      if (!b.beneficiaireId) {
        toast.error(`${label}${t("consumables.validation.posteVacant")}`);
        return;
      }
      const servieNum = Number(b.quantiteServie);
      if (!b.quantiteServie.trim() || Number.isNaN(servieNum) || servieNum <= 0) {
        toast.error(`${label}${t("consumables.validation.quantiteServieRequired")}`);
        return;
      }
    }
    submitMutation.mutate();
  };

  return (
    <div className="mb-3 space-y-3 rounded-lg border border-border bg-muted/20 p-3">
      <div className="flex items-center justify-between">
        <p className="text-xs font-medium text-foreground">{t("consumables.bspDetails")}</p>
        <Button type="button" size="sm" variant="outline" className="gap-1.5" onClick={addBlock}>
          <Plus className="h-3.5 w-3.5" /> {t("consumables.newBsp")}
        </Button>
      </div>

      {blocks.map((b, i) => (
        <div
          key={b.key}
          className={cn("space-y-3", blocks.length > 1 && "rounded-md border border-border bg-card p-3")}
        >
          {blocks.length > 1 && (
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">BSP {i + 1}</span>
              <button type="button" onClick={() => removeBlock(b.key)} className="text-destructive">
                <X className="h-3.5 w-3.5" />
              </button>
            </div>
          )}

          <div className="space-y-1">
            <Label className="text-xs">
              {t("consumables.field.structureService")} <span className="text-destructive">*</span>
            </Label>
            <PosteOrgSelect
              value={b.posteId}
              valueLabel={b.posteLabel}
              onSelect={(node) => selectPosteForBlock(b.key, node)}
              onClear={() => clearPosteForBlock(b.key)}
              selectableType="Poste"
              placeholder={t("consumables.selectPoste")}
              searchPlaceholder={t("consumables.filter.searchPoste")}
            />
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div className="space-y-1">
              <Label className="text-xs">{t("consumables.field.quantiteDemandee")}</Label>
              <Input
                type="number"
                min={0}
                max={stockDisponible}
                value={b.quantiteDemandee}
                onChange={(e) => updateQtyField(b.key, "quantiteDemandee", e.target.value)}
                placeholder="0"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("consumables.field.quantiteAccordee")}</Label>
              <Input
                type="number"
                min={0}
                max={b.quantiteDemandee.trim() !== "" ? Math.min(stockDisponible, Number(b.quantiteDemandee)) : stockDisponible}
                value={b.quantiteAccordee}
                onChange={(e) => updateQtyField(b.key, "quantiteAccordee", e.target.value)}
                placeholder={b.quantiteDemandee || "0"}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">
                {t("consumables.field.quantiteServie")} <span className="text-destructive">*</span>
              </Label>
              <Input
                type="number"
                min={0}
                max={(() => {
                  const plafond = b.quantiteAccordee.trim() !== "" ? Number(b.quantiteAccordee) : b.quantiteDemandee.trim() !== "" ? Number(b.quantiteDemandee) : stockDisponible;
                  return Math.min(stockDisponible, plafond);
                })()}
                value={b.quantiteServie}
                onChange={(e) => updateQtyField(b.key, "quantiteServie", e.target.value)}
                placeholder="0"
              />
            </div>
          </div>
          <p className="text-[10px] text-muted-foreground">
            {t("consumables.stockAvailable", { qty: formatQty(stockDisponible) })}
          </p>
        </div>
      ))}

      {/* Champs partagés par l'ensemble des BSP de cette soumission */}
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label className="text-xs">{t("consumables.field.dateTransfert")}</Label>
          <Input
            type="date"
            value={dateTransfert}
            onChange={(e) => setDateTransfert(e.target.value)}
          />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t("consumables.field.dateEtablissement")}</Label>
          <Input
            type="date"
            value={dateEtablissement}
            onChange={(e) => setDateEtablissement(e.target.value)}
          />
        </div>
      </div>

      <div className="space-y-1">
        <Label className="text-xs">{t("consumables.field.observations")}</Label>
        <Textarea
          rows={2}
          value={observations}
          onChange={(e) => setObservations(e.target.value)}
          placeholder={t("consumables.transferReasonPlaceholder")}
        />
      </div>

      <div className="flex justify-end gap-2 border-t border-border pt-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={onCancel}
          disabled={submitMutation.isPending}
        >
          {t("action.cancel")}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={submit}
          disabled={submitMutation.isPending}
          className="gap-1.5"
        >
          {submitMutation.isPending && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
          {submitMutation.isPending ? t("consumables.saving") : t("action.save")}
        </Button>
      </div>
    </div>
  );
}
