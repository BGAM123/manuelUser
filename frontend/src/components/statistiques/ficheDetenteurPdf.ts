/**
 * Génération du document PDF "Fiche de détenteur" (A4 paysage) — reproduit
 * fidèlement le formulaire officiel MINFI/Comptabilité-Matières (cf. maquette
 * papier partagée) : en-tête bilingue République du Cameroun / Republic of
 * Cameroon, bloc d'identification du détenteur (12 champs), puis un tableau
 * des matières détenues (colonnes 13 à 23).
 */
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import type { FicheDetenteurResponse } from "@/api/comptabilite/comptabilite.api";
import { formatAmount } from "@/api/common";

function fmtDate(v: unknown): string {
  if (!v) return "";
  const s = String(v);
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

/** Dessine un champ "N- Libellé ..........." avec sa traduction anglaise en dessous. */
function drawField(
  doc: jsPDF,
  x: number,
  y: number,
  width: number,
  numero: number,
  labelFr: string,
  labelEn: string,
  value: string
) {
  doc.setFont("helvetica", "bold");
  doc.setFontSize(8.5);
  const prefix = `${numero}- ${labelFr} `;
  doc.text(prefix, x, y);
  const prefixWidth = doc.getTextWidth(prefix);

  doc.setFont("helvetica", "normal");
  doc.setFontSize(8.5);
  const dotsX = x + prefixWidth;
  const dotsWidth = width - prefixWidth;
  if (value) {
    doc.text(value, dotsX + 1, y);
  } else if (dotsWidth > 4) {
    const dots = ".".repeat(Math.max(3, Math.floor(dotsWidth / 1.1)));
    doc.text(dots, dotsX, y);
  }

  doc.setFont("helvetica", "italic");
  doc.setFontSize(6.8);
  doc.setTextColor(90, 90, 90);
  doc.text(labelEn, x, y + 4);
  doc.setTextColor(0, 0, 0);
}

export async function buildFicheDetenteurPdf(
  response: FicheDetenteurResponse,
  type: "biens" | "consommables" | "tous" = "tous"
): Promise<{ doc: jsPDF; fileName: string }> {
  const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });
  const pageW = doc.internal.pageSize.getWidth();
  const marginX = 12;
  const usableW = pageW - marginX * 2;
  const colW = usableW / 3;

  const det = response.detenteur;
  const structureNom = det.service?.nom ?? "";

  const renderHeader = (): number => {
    let y = 10;
    doc.setTextColor(0, 0, 0);

    // ── Bloc bilingue République/Republic (2 colonnes centrées) ──────────
    const leftCenter = marginX + usableW * 0.27;
    const rightCenter = marginX + usableW * 0.73;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(10.5);
    doc.text("REPUBLIQUE DU CAMEROUN", leftCenter, y, { align: "center" });
    doc.text("REPUBLIC OF CAMEROON", rightCenter, y, { align: "center" });
    y += 4.2;

    doc.setFont("helvetica", "normal");
    doc.setFontSize(8.5);
    doc.text("Paix - Travail - Patrie", leftCenter, y, { align: "center" });
    doc.text("Peace - Work - Fatherland", rightCenter, y, { align: "center" });
    y += 6;

    const frLines = [
      "MINISTERE DES FINANCES",
      "DIRECTION DE LA COMPTABILITE-MATIERES",
      "SOUS-DIRECTION DE LA GESTION DU PATRIMOINE MOBILIER NATIONAL",
      "SERVICE DU FICHIER NATIONAL DES MATIERES",
    ];
    const enLines = [
      "MINISTRY OF FINANCE",
      "DEPARTMENT OF STORES ACCOUNTING",
      "SUB-DEPARTMENT FOR THE MANAGEMENT OF STATE MOVABLE ASSETS",
      "NATIONAL STORES INDEX-FILING SERVICE",
    ];
    frLines.forEach((line, i) => {
      doc.setFont("helvetica", i === 0 ? "bold" : "normal");
      doc.setFontSize(i === 0 ? 8.5 : 7);
      doc.text(line, leftCenter, y, { align: "center", maxWidth: usableW * 0.46 });
      doc.setFont("helvetica", i === 0 ? "bold" : "normal");
      doc.setFontSize(i === 0 ? 8.5 : 7);
      doc.text(enLines[i], rightCenter, y, { align: "center", maxWidth: usableW * 0.46 });
      y += i === 0 ? 4.4 : 3.6;
    });

    y += 3;
    doc.setDrawColor(20, 20, 20);
    doc.setLineWidth(0.3);
    doc.line(marginX, y, pageW - marginX, y);
    y += 7;

    // ── Titre ─────────────────────────────────────────────────────────────
    doc.setFont("helvetica", "bold");
    doc.setFontSize(13);
    doc.text(`FICHE DE DETENTEUR N° ${det.id}`, pageW / 2, y, { align: "center" });
    y += 5;
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9.5);
    doc.text(`INDIVIDUAL USER'S FORM No. ${det.id}`, pageW / 2, y, { align: "center" });
    y += 7;

    // ── Bloc d'identification (12 champs, 3 colonnes x 4 rangées) ─────────
    const rows: [number, string, string, string][] = [
      [1, "Chapitre budgétaire", "Budgetary head", ""],
      [2, "Poste de comptabilité-matières N°", "Stores accounting station No.", ""],
      [3, "Libellé du poste", "Denomination of station", ""],
      [4, "Structure", "", structureNom],
      [5, "Localité", "Location", ""],
      [6, "N° matricule solde", "Matricule No.", det.matricule ?? ""],
      [7, "Nom", "Name", det.nom ?? ""],
      [8, "Prénoms", "Surnames", det.prenom ?? ""],
      [9, "Nom de J. fille", "Maiden name", ""],
      [10, "N° CNI", "NIC No.", ""],
      [11, "Grade", "", ""],
      [12, "Fonction", "Duty post", ""],
    ];
    for (let r = 0; r < 4; r++) {
      for (let c = 0; c < 3; c++) {
        const [numero, labelFr, labelEn, value] = rows[r * 3 + c];
        drawField(doc, marginX + c * colW, y + r * 8.5, colW - 4, numero, labelFr, labelEn, value);
      }
    }
    y += 4 * 8.5 + 3;

    doc.setDrawColor(20, 20, 20);
    doc.line(marginX, y, pageW - marginX, y);
    y += 2;
    return y;
  };

  const lignes = [
    ...(type !== "consommables" ? response.BIENS : []),
    ...(type !== "biens" ? response.CONSOMMABLES : []),
  ];

  const startY = renderHeader();

  autoTable(doc, {
    startY,
    margin: { left: marginX, right: marginX, bottom: 12 },
    styles: { fontSize: 7, cellPadding: 1.3, valign: "middle", lineColor: [60, 60, 60], lineWidth: 0.15 },
    headStyles: { fillColor: [230, 230, 230], textColor: [0, 0, 0], fontStyle: "bold", halign: "center", fontSize: 6.6 },
    head: [
      [
        "13\nCode N°\nCode No.",
        "14\nDésignation des matières\net objets\nDesignation of assets",
        "15\nDescription, caractéristiques et N° marquage\ndes matières et objets\nDescription, characteristics and registered\nnumber of assets",
        "16\nDate d'acquisition\nDate of acquisition",
        "17\nQuantité\nQuantity",
        "18\nPrix unitaire\nUnit price",
        "19\nValeur\nValue",
        "20\nDate d'affectation\nDate of usage",
        "21\nLieu d'affectation\nPlace of usage",
        "22\nVisa détenteur\nUser's signature",
        "23\nObservation\nRemarks",
      ],
    ],
    body:
      lignes.length > 0
        ? lignes.map((l, idx) => [
            String(idx + 1),
            l.designation ?? "",
            l.description ?? "",
            fmtDate(l.dateAcquisition),
            String(l.quantite ?? ""),
            formatAmount(l.prixUnitaire ?? 0),
            formatAmount(l.valeur ?? 0),
            fmtDate(l.dateAffectation),
            l.lieuAffectation ?? "",
            "",
            l.observation ?? "",
          ])
        : [["", "", "", "", "", "", "", "", "", "", ""]],
    didDrawPage: () => {
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      doc.setFont("helvetica", "italic");
      doc.setFontSize(6.5);
      doc.setTextColor(70, 70, 70);
      doc.text(
        "1- Bon / Good   2- Vétuste obsolète / Bad obsolete   3- À réformer / For boarding",
        marginX,
        pageHeight - 6
      );
      doc.text(String(doc.getNumberOfPages()), pageWidth - marginX, pageHeight - 6, { align: "right" });
      doc.setTextColor(0, 0, 0);
    },
  });

  const fileName = `Fiche_Detenteur_${(det.nom ?? "").replace(/\s+/g, "_")}_${det.id}.pdf`;
  return { doc, fileName };
}

export async function downloadFicheDetenteurPdf(
  response: FicheDetenteurResponse,
  type: "biens" | "consommables" | "tous" = "tous"
): Promise<void> {
  const { doc, fileName } = await buildFicheDetenteurPdf(response, type);
  doc.save(fileName);
}
