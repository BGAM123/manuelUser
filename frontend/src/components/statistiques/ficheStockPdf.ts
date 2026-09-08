/**
 * Génération du document PDF "Fiche de stock" (A4 portrait) — reproduit le
 * formulaire officiel MINFI/Comptabilité-Matières (cf. maquette papier
 * partagée, fiche rose) : bloc d'identification du matériel à gauche, bloc
 * République du Cameroun + "Instruction générale" à droite, puis le tableau
 * des mouvements de stock. Une page par fiche (un consommable x un service).
 */
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import type { FicheStockFiche } from "@/api/comptabilite/comptabilite.api";

function fmtDate(v: unknown): string {
  if (!v) return "";
  const s = String(v);
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

/** Dessine un champ "LIBELLÉ : ..........." sur une ligne. */
function drawLabeledLine(doc: jsPDF, x: number, y: number, width: number, label: string, value: string) {
  doc.setFont("helvetica", "bold");
  doc.setFontSize(9.5);
  const prefix = `${label} : `;
  doc.text(prefix, x, y);
  const prefixWidth = doc.getTextWidth(prefix);

  doc.setFont("helvetica", "normal");
  doc.setFontSize(9.5);
  const dotsX = x + prefixWidth;
  const dotsWidth = width - prefixWidth;
  if (value) {
    doc.text(value, dotsX + 1, y);
  } else if (dotsWidth > 4) {
    const dots = ".".repeat(Math.max(3, Math.floor(dotsWidth / 1.3)));
    doc.text(dots, dotsX, y);
  }
}

function renderFichePage(doc: jsPDF, fiche: FicheStockFiche, numero: number): number {
  const pageW = doc.internal.pageSize.getWidth();
  const marginX = 14;
  const leftW = pageW * 0.56 - marginX;
  const rightX = pageW * 0.56 + 6;
  const rightW = pageW - marginX - rightX;

  let y = 20;

  // ── Bloc gauche : identification du matériel ───────────────────────────
  drawLabeledLine(doc, marginX, y, leftW, "MINISTÈRE", "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "SERVICE", fiche.service?.nom ?? "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "NUMÉRO NOMENCLATURE", "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "DÉSIGNATION MATÉRIEL ET OBJETS", fiche.consumable?.nom ?? "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "CODE MATÉRIEL", "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "ESPÈCES DES UNITÉS", fiche.consumable?.unite_mesure ?? "");
  y += 9;
  drawLabeledLine(doc, marginX, y, leftW, "PRIX UNITAIRE", "");

  // ── Bloc droit : République du Cameroun + Instruction générale ─────────
  let ry = 14;
  doc.setFont("helvetica", "bold");
  doc.setFontSize(11);
  doc.text("RÉPUBLIQUE DU CAMEROUN", rightX + rightW, ry, { align: "right" });
  ry += 5;
  doc.setFont("helvetica", "normal");
  doc.setFontSize(8.5);
  doc.text("Paix - Travail - Patrie", rightX + rightW, ry, { align: "right" });
  ry += 12;

  doc.setFontSize(9);
  doc.text("Instruction générale", rightX + rightW, ry, { align: "right" });
  ry += 6;
  drawLabeledLine(doc, rightX, ry, rightW, "du", "");
  ry += 6;
  drawLabeledLine(doc, rightX, ry, rightW, "Art.", "");
  ry += 10;

  doc.setFont("helvetica", "bold");
  doc.setFontSize(14);
  doc.text("FICHE DE STOCK", rightX + rightW, ry, { align: "right" });
  ry += 6;
  doc.setFontSize(10);
  doc.text(`N° ${numero}`, rightX + rightW, ry, { align: "right" });

  const headerBottom = Math.max(y, ry) + 6;
  doc.setDrawColor(20, 20, 20);
  doc.setLineWidth(0.3);
  doc.line(marginX, headerBottom, pageW - marginX, headerBottom);

  return headerBottom + 4;
}

export async function buildFicheStockPdf(
  fiches: FicheStockFiche[]
): Promise<{ doc: jsPDF; fileName: string }> {
  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
  const marginX = 14;
  const list = fiches.length > 0 ? fiches : [];

  if (list.length === 0) {
    renderFichePage(doc, { consumable: { id: 0, nom: "", unite_mesure: "" }, service: { id: 0, nom: "" }, movements: [] }, 1);
    autoTable(doc, {
      startY: 95,
      margin: { left: marginX, right: marginX },
      styles: { fontSize: 8, cellPadding: 1.5, valign: "middle", lineColor: [60, 60, 60], lineWidth: 0.15, halign: "center" },
      headStyles: { fillColor: [230, 230, 230], textColor: [0, 0, 0], fontStyle: "bold" },
      head: [["Date", "Origine des entrées ou destination des sorties", "Stock initial", "Entrées", "Sorties", "En stocks", "N° B.L / N° B.S.P", "Observations"]],
      body: [["", "", "", "", "", "", "", ""]],
    });
  } else {
    list.forEach((fiche, idx) => {
      if (idx > 0) doc.addPage("a4", "portrait");
      const startY = renderFichePage(doc, fiche, idx + 1);

      autoTable(doc, {
        startY,
        margin: { left: marginX, right: marginX, bottom: 14 },
        styles: { fontSize: 8, cellPadding: 1.5, valign: "middle", lineColor: [60, 60, 60], lineWidth: 0.15, halign: "center" },
        headStyles: { fillColor: [230, 230, 230], textColor: [0, 0, 0], fontStyle: "bold", fontSize: 7.5 },
        columnStyles: {
          1: { halign: "left", cellWidth: 42 },
          7: { halign: "left", cellWidth: 38 },
        },
        head: [
          [
            { content: "DATE", rowSpan: 2 },
            { content: "ORIGINE DES ENTRÉES\nOU DESTINATION DES SORTIES", rowSpan: 2 },
            { content: "STOCK\nINITIAL", rowSpan: 2 },
            { content: "QUANTITÉS", colSpan: 3 },
            { content: "N° B.L\nN° B.S.P (2)", rowSpan: 2 },
            { content: "OBSERVATIONS", rowSpan: 2 },
          ],
          [
            { content: "ENTRÉES" },
            { content: "SORTIES" },
            { content: "EN STOCKS" },
          ],
        ],
        body:
          fiche.movements.length > 0
            ? fiche.movements.map((m) => [
                fmtDate(m.date),
                m.origineDestination ?? "",
                m.stockInitial != null ? String(m.stockInitial) : "",
                String(m.quantites?.entrees ?? 0),
                String(m.quantites?.sorties ?? 0),
                String(m.quantites?.enStock ?? 0),
                m.numeroBlBsp ?? "",
                m.observations ?? "",
              ])
            : [["", "", "", "", "", "", "", ""]],
        didDrawPage: () => {
          const pageWidth = doc.internal.pageSize.getWidth();
          const pageHeight = doc.internal.pageSize.getHeight();
          doc.setFont("helvetica", "italic");
          doc.setFontSize(6.5);
          doc.setTextColor(90, 90, 90);
          doc.text("(2) N° Bon de Livraison / N° Bon de Sortie Provisoire", marginX, pageHeight - 6);
          doc.text(String(doc.getNumberOfPages()), pageWidth - marginX, pageHeight - 6, { align: "right" });
          doc.setTextColor(0, 0, 0);
        },
      });
    });
  }

  const dateStr = new Date().toISOString().slice(0, 10);
  const fileName = `Fiches_Stock_MINEPIA_${dateStr}.pdf`;
  return { doc, fileName };
}

export async function downloadFicheStockPdf(fiches: FicheStockFiche[]): Promise<void> {
  const { doc, fileName } = await buildFicheStockPdf(fiches);
  doc.save(fileName);
}
