/**
 * Génération du document PDF "Livre Journal" (A3 paysage) — reproduit le
 * formulaire officiel MINFI/Comptabilité-Matières (cf. maquette papier
 * partagée) : en-tête Poste comptable / Classe de rattachement + bloc
 * République du Cameroun, puis le tableau à 13 colonnes (dont Entrées /
 * Sorties en quantité et valeur).
 */
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import type { LivreJournalLigne } from "@/api/comptabilite/comptabilite.api";
import { formatAmount } from "@/api/common";

function fmtDate(v: unknown): string {
  if (!v) return "";
  const s = String(v);
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

const HEADER_HEIGHT = 34;

export async function buildLivreJournalPdf(
  lignes: LivreJournalLigne[]
): Promise<{ doc: jsPDF; fileName: string }> {
  const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a3" });
  const pageW = doc.internal.pageSize.getWidth();
  const marginX = 12;

  const renderHeader = () => {
    let y = 9;
    doc.setTextColor(0, 0, 0);

    // ── Bloc gauche : Poste comptable ─────────────────────────────────────
    doc.setFont("helvetica", "bold");
    doc.setFontSize(9);
    doc.text("MINISTERE DES FINANCES", marginX, y);
    doc.setFont("helvetica", "normal");
    doc.setFontSize(7);
    doc.text("MINISTRY OF FINANCE", marginX, y + 3.4);

    doc.setFontSize(7.5);
    doc.text("POSTE COMPTABLE DE ......................................  ACCOUNTING POST OF ......................................", marginX, y + 9);
    doc.text("CLASSE AUPRES D ......................................  CLASS AT ......................................", marginX, y + 13.5);
    const codeLabel = "CODE POSTE COMPTABLE (ACCOUNTING POST CODE) :";
    doc.text(codeLabel, marginX, y + 18);
    const codeLabelWidth = doc.getTextWidth(codeLabel);
    doc.rect(marginX + codeLabelWidth + 3, y + 14.3, 24, 4.5);

    // ── Bloc droit : République du Cameroun ───────────────────────────────
    const rightX = pageW - marginX;
    doc.setFont("helvetica", "bold");
    doc.setFontSize(9.5);
    doc.text("REPUBLIQUE DU CAMEROUN", rightX, y, { align: "right" });
    doc.setFont("helvetica", "normal");
    doc.setFontSize(7.5);
    doc.text("REPUBLIC OF CAMEROON", rightX, y + 3.4, { align: "right" });
    doc.text("Paix - Travail - Patrie / Peace - Work - Fatherland", rightX, y + 7, { align: "right" });
    doc.setFont("helvetica", "bold");
    doc.text(`Feuillet N° ......................................`, rightX, y + 13, { align: "right" });

    y += 22;
    doc.setDrawColor(20, 20, 20);
    doc.setLineWidth(0.3);
    doc.line(marginX, y, pageW - marginX, y);
    y += 6;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(13);
    doc.text("LIVRE JOURNAL", pageW / 2, y, { align: "center" });

    return HEADER_HEIGHT;
  };

  renderHeader();

  autoTable(doc, {
    startY: HEADER_HEIGHT + 4,
    margin: { left: marginX, right: marginX, bottom: 12, top: HEADER_HEIGHT + 4 },
    styles: { fontSize: 6.6, cellPadding: 1.2, valign: "middle", lineColor: [60, 60, 60], lineWidth: 0.15, halign: "center" },
    headStyles: { fillColor: [230, 230, 230], textColor: [0, 0, 0], fontStyle: "bold", halign: "center", fontSize: 6.4 },
    bodyStyles: { halign: "center" },
    columnStyles: {
      2: { halign: "left" },
      5: { halign: "left" },
      12: { halign: "left", cellWidth: 70 },
    },
    head: [
      [
        { content: "1\nNuméro d'ordre des opérations\nOrder Number of Operations", rowSpan: 2 },
        { content: "2\nDate d'enregistrement\ndes opérations\nDate of Operations", rowSpan: 2 },
        { content: "3\nOrigine des entrées et des destinations\ndes sorties\nOrigin of entries and destination of issues", rowSpan: 2 },
        { content: "4\nImputation budgétaire\nBudgetary head", rowSpan: 2 },
        { content: "5\nN° d'ordre de la classe de la\nnomenclature sommaire\nOrder number of the class of\nthe summary nomenclature", rowSpan: 2 },
        { content: "6\nDésignation des matières et objets\nDesignation of objects", rowSpan: 2 },
        { content: "7\nEspèce des unités d'inscription\nou d'articles\nUnit species of registration or articles", rowSpan: 2 },
        { content: "8\nPrix de l'unité\nUnit price", rowSpan: 2 },
        { content: "ENTREES\nENTRIES", colSpan: 2 },
        { content: "SORTIES\nISSUES", colSpan: 2 },
        { content: "13\nObservations\nRemark", rowSpan: 2 },
      ],
      [
        { content: "9\nQuantité\nQuantity" },
        { content: "10\nValeurs\nValue" },
        { content: "11\nQuantité\nQuantity" },
        { content: "12\nValeurs\nValue" },
      ],
    ],
    body:
      lignes.length > 0
        ? lignes.map((l) => [
            String(l.numeroOrdre ?? ""),
            fmtDate(l.date),
            l.origineDestination ?? "",
            formatAmount(l.imputationBudgetaire ?? 0),
            String(l.numeroOrdreClasse ?? ""),
            l.designation ?? "",
            l.uniteMesure ?? "",
            formatAmount(l.prixUnitaire ?? 0),
            String(l.entree?.quantite ?? 0),
            formatAmount(l.entree?.valeur ?? 0),
            String(l.sortie?.quantite ?? 0),
            formatAmount(l.sortie?.valeur ?? 0),
            l.observations ?? "",
          ])
        : [["", "", "", "", "", "", "", "", "", "", "", "", ""]],
    didDrawPage: (data) => {
      if (data.pageNumber > 1) renderHeader();
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      doc.setFont("helvetica", "normal");
      doc.setFontSize(7);
      doc.setTextColor(70, 70, 70);
      doc.text(`Page ${doc.getNumberOfPages()}`, pageWidth - marginX, pageHeight - 6, { align: "right" });
      doc.setTextColor(0, 0, 0);
    },
  });

  const dateStr = new Date().toISOString().slice(0, 10);
  const fileName = `Livre_Journal_MINEPIA_${dateStr}.pdf`;
  return { doc, fileName };
}

export async function downloadLivreJournalPdf(lignes: LivreJournalLigne[]): Promise<void> {
  const { doc, fileName } = await buildLivreJournalPdf(lignes);
  doc.save(fileName);
}
