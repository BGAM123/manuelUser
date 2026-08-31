import * as XLSX from "xlsx";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { toast } from "sonner";

export function exportStatistiquesExcel(title: string, data: any[], filename = "Statistiques_MINEPIA.xlsx") {
  try {
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, title.slice(0, 30));
    XLSX.writeFile(wb, filename);
    toast.success("Export Excel généré avec succès !");
  } catch (err) {
    toast.error("Erreur lors de l'export Excel");
    console.error(err);
  }
}

export function exportStatistiquesPdf(
  title: string,
  columns: { header: string; dataKey: string }[],
  rows: Record<string, any>[],
  filename = "Rapport_Statistiques_MINEPIA.pdf"
) {
  try {
    const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });

    // En-tête officiel MINEPIA
    doc.setFillColor(0, 104, 55); // MINEPIA Green
    doc.rect(0, 0, 297, 24, "F");

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.text("RÉPUBLIQUE DU CAMEROUN — MINEPIA", 14, 11);

    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text("Direction des Ressources Financières et du Patrimoine — Tableau de bord des statistiques", 14, 18);

    doc.setTextColor(30, 41, 59);
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.text(title, 14, 34);

    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(100, 116, 139);
    const dateStr = new Date().toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
    doc.text(`Document généré le : ${dateStr}`, 14, 40);

    autoTable(doc, {
      startY: 45,
      columns: columns,
      body: rows,
      theme: "grid",
      headStyles: {
        fillColor: [0, 104, 55],
        textColor: [255, 255, 255],
        fontStyle: "bold",
        fontSize: 9,
      },
      bodyStyles: {
        fontSize: 8.5,
        textColor: [30, 41, 59],
      },
      alternateRowStyles: {
        fillColor: [248, 250, 252],
      },
      margin: { left: 14, right: 14 },
    });

    // Pied de page
    const pageCount = (doc as any).internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(148, 163, 184);
      doc.text(
        `MINEPIA — Gestion du Patrimoine | Page ${i} sur ${pageCount}`,
        148,
        205,
        { align: "center" }
      );
    }

    doc.save(filename);
    toast.success("Rapport PDF généré avec succès !");
  } catch (err) {
    toast.error("Erreur lors de la création du PDF");
    console.error(err);
  }
}
