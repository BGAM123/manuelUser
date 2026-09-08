/**
 * Génération du document Excel "Patrimoine Global" (multi-feuilles, mise en
 * forme avancée : bordures, couleurs MINEPIA, en-têtes figés, filtres auto).
 *
 * Utilise ExcelJS (contrairement à `xlsx`/SheetJS déjà utilisé ailleurs dans
 * l'app, qui ne supporte pas la mise en forme des cellules en version
 * communautaire). Ce module est autonome et n'affecte aucun export existant.
 */
import ExcelJS from "exceljs";
import type { PatrimoineGlobalResponse } from "@/api/patrimoine-global/patrimoine-global.api";
import { buildBarChartConfig, buildDoughnutChartConfig, renderChartPngBase64, topN } from "./patrimoineGlobalCharts";

// ─── Couleurs & styles communs ─────────────────────────────────────────────
const MINEPIA_GREEN_ARGB = "FF006837";
const HEADER_FONT_COLOR = "FFFFFFFF";
const ALT_ROW_FILL = "FFF1F7F3";
const TITLE_FILL = "FFE6F0EA";
const BORDER_COLOR = "FFB9C6BE";

const thinBorder: Partial<ExcelJS.Borders> = {
  top: { style: "thin", color: { argb: BORDER_COLOR } },
  left: { style: "thin", color: { argb: BORDER_COLOR } },
  bottom: { style: "thin", color: { argb: BORDER_COLOR } },
  right: { style: "thin", color: { argb: BORDER_COLOR } },
};

function styleHeaderRow(row: ExcelJS.Row) {
  row.eachCell((cell) => {
    cell.font = { bold: true, color: { argb: HEADER_FONT_COLOR }, size: 11 };
    cell.fill = { type: "pattern", pattern: "solid", fgColor: { argb: MINEPIA_GREEN_ARGB } };
    cell.alignment = { vertical: "middle", horizontal: "center", wrapText: true };
    cell.border = thinBorder;
  });
  row.height = 22;
}

function styleTitleRow(row: ExcelJS.Row, span: number) {
  row.eachCell((cell) => {
    cell.font = { bold: true, size: 13, color: { argb: MINEPIA_GREEN_ARGB } };
    cell.fill = { type: "pattern", pattern: "solid", fgColor: { argb: TITLE_FILL } };
    cell.alignment = { vertical: "middle", horizontal: "left" };
  });
  row.height = 26;
}

function autoFitColumns(sheet: ExcelJS.Worksheet, minWidth = 10, maxWidth = 42) {
  sheet.columns.forEach((col) => {
    let max = minWidth;
    col.eachCell?.({ includeEmpty: false }, (cell) => {
      const len = String(cell.value ?? "").length;
      if (len + 2 > max) max = len + 2;
    });
    col.width = Math.min(max, maxWidth);
  });
}

function applyZebraAndBorders(sheet: ExcelJS.Worksheet, firstDataRow: number, lastDataRow: number, colCount: number) {
  for (let r = firstDataRow; r <= lastDataRow; r++) {
    const row = sheet.getRow(r);
    const isEven = (r - firstDataRow) % 2 === 1;
    for (let c = 1; c <= colCount; c++) {
      const cell = row.getCell(c);
      cell.border = thinBorder;
      if (isEven) {
        cell.fill = { type: "pattern", pattern: "solid", fgColor: { argb: ALT_ROW_FILL } };
      }
      cell.alignment = { ...cell.alignment, vertical: "middle" };
    }
  }
}

function fmtNumber(v: unknown): number | string {
  if (v === null || v === undefined || v === "") return "";
  const n = Number(v);
  return Number.isFinite(n) ? n : String(v);
}

function fmtDate(v: unknown): string {
  if (!v) return "";
  const s = String(v);
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}

function nameOf(obj: any): string {
  if (!obj) return "—";
  if (typeof obj === "string") return obj;
  return obj.nom ?? obj.name ?? "—";
}

function responsableLabel(r: any): string {
  if (!r) return "—";
  if (typeof r === "string") return r;
  const first = r.firstName ?? r.prenom ?? "";
  const last = r.lastName ?? r.nom ?? "";
  const full = `${first} ${last}`.trim();
  return full || r.nom || "—";
}

function fmtBool(v: unknown): string {
  if (v === null || v === undefined) return "—";
  return v ? "Oui" : "Non";
}

// Hauteur de ligne par défaut ExcelJS (15pt ≈ 20px) — sert à réserver l'espace
// nécessaire sous une image insérée pour ne pas chevaucher le contenu suivant.
const ROW_HEIGHT_PX = 20;
function pxToRows(px: number): number {
  return Math.ceil(px / ROW_HEIGHT_PX) + 1;
}

/** Insère une image PNG (base64) dans la feuille, ancrée à la cellule (col, row) — indices 0-based. */
function addChartImage(
  workbook: ExcelJS.Workbook,
  sheet: ExcelJS.Worksheet,
  base64: string,
  col: number,
  row: number,
  width: number,
  height: number
) {
  const imageId = workbook.addImage({ base64, extension: "png" });
  sheet.addImage(imageId, { tl: { col, row }, ext: { width, height } });
}

/** Totaux par étiquette d'un regroupement — Format back-end : { [label]: { total, ... } }. */
function buildGroupTotals(groupObj: Record<string, any> | undefined): { labels: string[]; values: number[] } {
  if (!groupObj) return { labels: [], values: [] };
  const entries = Object.entries(groupObj).map(([label, entry]) => ({ label, total: Number(entry?.total ?? 0) }));
  entries.sort((a, b) => b.total - a.total);
  return { labels: entries.map((e) => e.label), values: entries.map((e) => e.total) };
}

// ─── Extraction générique d'un regroupement de biens ───────────────────────
// Format back-end : { [groupLabel]: { total, biens: { data: [...] } } }
function extractGroupedBienRows(groupObj: Record<string, any> | undefined, groupColumnLabel: string) {
  const rows: Record<string, any>[] = [];
  if (!groupObj) return rows;
  for (const [label, entry] of Object.entries(groupObj)) {
    const items: any[] = entry?.biens?.data ?? [];
    for (const b of items) {
      rows.push({
        [groupColumnLabel]: label,
        "Référence": b.reference ?? "—",
        "Nom": b.nom ?? "—",
        "Catégorie": nameOf(b.categorie),
        "Type": nameOf(b.typeBien),
        "Projet": nameOf(b.projet),
        "Structure": nameOf(b.structure),
        "Responsable": responsableLabel(b.responsable),
        "Statut": b.statut ?? "—",
        "Source de financement": b.sourceFinancement ?? "—",
        "Exercice": b.exercice ?? "—",
        "Valeur (FCFA)": fmtNumber(b.valeur),
        "Valeur initiale (FCFA)": fmtNumber(b.valeurInitiale),
        "Date d'acquisition": fmtDate(b.dateAcquisition),
        "État": nameOf(b.etatBien),
        "Sécurisé": fmtBool(b.securise),
        "Accusé de réception": fmtBool(b.received),
      });
    }
  }
  return rows;
}

function extractGroupedConsommableRows(groupObj: Record<string, any> | undefined, groupColumnLabel: string) {
  const rows: Record<string, any>[] = [];
  if (!groupObj) return rows;
  for (const [label, entry] of Object.entries(groupObj)) {
    const items: any[] = entry?.consommables?.data ?? [];
    for (const c of items) {
      rows.push({
        [groupColumnLabel]: label,
        "Nom": c.nom ?? "—",
        "Description": c.description ?? "—",
        "Catégorie": nameOf(c.categorie),
        "Type de bien": nameOf(c.assetType),
        "Service": nameOf(c.service),
        "Stock actuel": fmtNumber(c.stockActuel),
        "Prix initial (FCFA)": fmtNumber(c.prixInitial),
        "Prix total (FCFA)": fmtNumber(c.prixTotal),
      });
    }
  }
  return rows;
}

async function addTableSheet(
  workbook: ExcelJS.Workbook,
  sheetName: string,
  title: string,
  rows: Record<string, any>[],
  chart?: { labels: string[]; values: number[] } | null
) {
  const sheet = workbook.addWorksheet(sheetName.slice(0, 31));

  let titleRowIndex = 1;
  const chartWidth = 620;
  const chartHeight = 300;
  if (chart && chart.labels.length > 0) {
    const top = topN(chart.labels, chart.values, 15);
    const base64 = await renderChartPngBase64(
      buildBarChartConfig(top.labels, top.values, `Répartition — ${title}`, true),
      chartWidth,
      chartHeight
    );
    addChartImage(workbook, sheet, base64, 0, 0, chartWidth, chartHeight);
    titleRowIndex = pxToRows(chartHeight) + 1;
  }

  if (rows.length === 0) {
    sheet.getCell(titleRowIndex, 1).value = title;
    styleTitleRow(sheet.getRow(titleRowIndex), 1);
    sheet.getCell(titleRowIndex + 1, 1).value = "Aucune donnée disponible pour ce regroupement.";
    sheet.getColumn(1).width = 50;
    return sheet;
  }

  const columns = Object.keys(rows[0]);

  // Ligne de titre (fusionnée sur toute la largeur)
  sheet.mergeCells(titleRowIndex, 1, titleRowIndex, columns.length);
  sheet.getCell(titleRowIndex, 1).value = title;
  styleTitleRow(sheet.getRow(titleRowIndex), columns.length);

  // En-têtes de colonnes
  const headerRowIndex = titleRowIndex + 1;
  const headerRow = sheet.getRow(headerRowIndex);
  columns.forEach((c, i) => (headerRow.getCell(i + 1).value = c));
  styleHeaderRow(headerRow);

  // Données
  rows.forEach((r, idx) => {
    const dataRow = sheet.getRow(headerRowIndex + 1 + idx);
    columns.forEach((c, i) => (dataRow.getCell(i + 1).value = r[c]));
  });

  const firstDataRow = headerRowIndex + 1;
  const lastDataRow = headerRowIndex + rows.length;
  applyZebraAndBorders(sheet, firstDataRow, lastDataRow, columns.length);

  sheet.views = [{ state: "frozen", ySplit: headerRowIndex }];
  sheet.autoFilter = {
    from: { row: headerRowIndex, column: 1 },
    to: { row: headerRowIndex, column: columns.length },
  };
  autoFitColumns(sheet);
  return sheet;
}

async function addSyntheseSheet(workbook: ExcelJS.Workbook, biens: any, consommables: any, inclureGraphiques: boolean) {
  const sheet = workbook.addWorksheet("Synthèse");
  sheet.mergeCells(1, 1, 1, 3);
  sheet.getCell(1, 1).value = "Synthèse générale du patrimoine MINEPIA";
  styleTitleRow(sheet.getRow(1), 3);
  sheet.getColumn(1).width = 42;
  sheet.getColumn(2).width = 18;
  sheet.getColumn(3).width = 18;

  let r = 3;
  const kpiHeader = sheet.getRow(r);
  kpiHeader.getCell(1).value = "Indicateur";
  kpiHeader.getCell(2).value = "Valeur";
  styleHeaderRow(kpiHeader);
  sheet.mergeCells(r, 2, r, 3);
  r++;

  const addKpi = (label: string, value: number | string) => {
    const row = sheet.getRow(r);
    row.getCell(1).value = label;
    row.getCell(2).value = value;
    sheet.mergeCells(r, 2, r, 3);
    row.eachCell((cell) => (cell.border = thinBorder));
    r++;
  };

  addKpi("Total des biens", fmtNumber(biens?.totalBiens));
  addKpi("Total des consommables", fmtNumber(consommables?.totalConsommables));
  addKpi("Total des affectations", fmtNumber(biens?.affectations?.total));
  addKpi("Total des restitutions", fmtNumber(biens?.restitutions?.total));
  r++;

  // Statuts
  const statuts: Record<string, number> = biens?.statuts ?? {};
  if (Object.keys(statuts).length > 0) {
    sheet.mergeCells(r, 1, r, 3);
    sheet.getCell(r, 1).value = "Répartition des biens par statut";
    styleTitleRow(sheet.getRow(r), 3);
    r++;
    const head = sheet.getRow(r);
    head.getCell(1).value = "Statut";
    head.getCell(2).value = "Nombre de biens";
    sheet.mergeCells(r, 2, r, 3);
    styleHeaderRow(head);
    r++;
    const firstStatutRow = r;
    for (const [statut, total] of Object.entries(statuts)) {
      const row = sheet.getRow(r);
      row.getCell(1).value = statut;
      row.getCell(2).value = total;
      sheet.mergeCells(r, 2, r, 3);
      r++;
    }
    applyZebraAndBorders(sheet, firstStatutRow, r - 1, 3);
    r++;
  }

  // Affectations par service
  const affectationsParService: any[] = biens?.affectations?.parService ?? [];
  if (affectationsParService.length > 0) {
    sheet.mergeCells(r, 1, r, 3);
    sheet.getCell(r, 1).value = "Affectations en cours par service";
    styleTitleRow(sheet.getRow(r), 3);
    r++;
    const head = sheet.getRow(r);
    head.getCell(1).value = "Service";
    head.getCell(2).value = "Total affectations";
    sheet.mergeCells(r, 2, r, 3);
    styleHeaderRow(head);
    r++;
    const first = r;
    for (const entry of affectationsParService) {
      const row = sheet.getRow(r);
      row.getCell(1).value = nameOf(entry.service);
      row.getCell(2).value = entry.total;
      sheet.mergeCells(r, 2, r, 3);
      r++;
    }
    applyZebraAndBorders(sheet, first, r - 1, 3);
  }

  // Graphiques de synthèse (statuts + affectations par service)
  if (inclureGraphiques) {
    r += 2;
    const chartWidth = 560;
    const chartHeight = 320;

    if (Object.keys(statuts).length > 0) {
      const base64 = await renderChartPngBase64(
        buildDoughnutChartConfig(Object.keys(statuts), Object.values(statuts).map(Number), "Répartition des biens par statut"),
        chartWidth,
        chartHeight
      );
      addChartImage(workbook, sheet, base64, 0, r - 1, chartWidth, chartHeight);
      r += pxToRows(chartHeight);
    }

    if (affectationsParService.length > 0) {
      const top = topN(
        affectationsParService.map((e) => nameOf(e.service)),
        affectationsParService.map((e) => Number(e.total ?? 0)),
        10
      );
      const base64 = await renderChartPngBase64(
        buildBarChartConfig(top.labels, top.values, "Affectations en cours par service", true),
        chartWidth,
        chartHeight
      );
      addChartImage(workbook, sheet, base64, 0, r - 1, chartWidth, chartHeight);
      r += pxToRows(chartHeight);
    }
  }
}

export interface PatrimoineGlobalSections {
  synthese: boolean;
  parEtat: boolean;
  parService: boolean;
  parProjet: boolean;
  parCategorie: boolean;
  parType: boolean;
  parRegion: boolean;
  parDepartement: boolean;
  parArrondissement: boolean;
  consommablesParService: boolean;
  consommablesParCategorie: boolean;
  inclureGraphiques: boolean;
}

export const DEFAULT_PATRIMOINE_GLOBAL_SECTIONS: PatrimoineGlobalSections = {
  synthese: true,
  parEtat: true,
  parService: true,
  parProjet: true,
  parCategorie: true,
  parType: true,
  parRegion: false,
  parDepartement: false,
  parArrondissement: false,
  consommablesParService: true,
  consommablesParCategorie: true,
  inclureGraphiques: true,
};

export async function buildPatrimoineGlobalWorkbook(
  response: PatrimoineGlobalResponse,
  sections: PatrimoineGlobalSections
): Promise<ExcelJS.Workbook> {
  // Réponse déjà déballée par `getPatrimoineGlobal()` : { PATRIMOINE_GLOBAL: { BIENS, CONSOMMABLES } }.
  const root = response?.PATRIMOINE_GLOBAL;
  const biens = root?.BIENS ?? {};
  const consommables = root?.CONSOMMABLES ?? {};

  const workbook = new ExcelJS.Workbook();
  workbook.creator = "MINEPIA — Gestion du Patrimoine";
  workbook.created = new Date();

  const chart = (groupObj: Record<string, any> | undefined) =>
    sections.inclureGraphiques ? buildGroupTotals(groupObj) : null;

  if (sections.synthese) {
    await addSyntheseSheet(workbook, biens, consommables, sections.inclureGraphiques);
  }
  if (sections.parEtat) {
    await addTableSheet(workbook, "Biens par État", "Biens par état", extractGroupedBienRows(biens.Bien_parEtat, "État"), chart(biens.Bien_parEtat));
  }
  if (sections.parService) {
    await addTableSheet(workbook, "Biens par Service", "Biens par service", extractGroupedBienRows(biens.Bien_parService, "Service"), chart(biens.Bien_parService));
  }
  if (sections.parProjet) {
    await addTableSheet(workbook, "Biens par Projet", "Biens par projet", extractGroupedBienRows(biens.Bien_parProjet, "Projet"), chart(biens.Bien_parProjet));
  }
  if (sections.parCategorie) {
    await addTableSheet(workbook, "Biens par Catégorie", "Biens par catégorie", extractGroupedBienRows(biens.Bien_parCategorie, "Catégorie"), chart(biens.Bien_parCategorie));
  }
  if (sections.parType) {
    await addTableSheet(workbook, "Biens par Type", "Biens par type", extractGroupedBienRows(biens.Bien_parType, "Type"), chart(biens.Bien_parType));
  }
  if (sections.parRegion) {
    await addTableSheet(workbook, "Biens par Région", "Biens par région", extractGroupedBienRows(biens.Bien_parRegion, "Région"), chart(biens.Bien_parRegion));
  }
  if (sections.parDepartement) {
    await addTableSheet(workbook, "Biens par Département", "Biens par département", extractGroupedBienRows(biens.Bien_parDepartement, "Département"), chart(biens.Bien_parDepartement));
  }
  if (sections.parArrondissement) {
    await addTableSheet(workbook, "Biens par Arrondissement", "Biens par arrondissement", extractGroupedBienRows(biens.Bien_parArrondissement, "Arrondissement"), chart(biens.Bien_parArrondissement));
  }
  if (sections.consommablesParService) {
    await addTableSheet(workbook, "Consommables par Service", "Consommables par service", extractGroupedConsommableRows(consommables.Consommables_parService, "Service"), chart(consommables.Consommables_parService));
  }
  if (sections.consommablesParCategorie) {
    await addTableSheet(workbook, "Consommables par Catégorie", "Consommables par catégorie", extractGroupedConsommableRows(consommables.Consommables_parCategorie, "Catégorie"), chart(consommables.Consommables_parCategorie));
  }

  // Garde-fou : au moins une feuille (Excel refuse un classeur vide).
  if (workbook.worksheets.length === 0) {
    await addSyntheseSheet(workbook, biens, consommables, sections.inclureGraphiques);
  }

  return workbook;
}

export async function downloadPatrimoineGlobalExcel(
  response: PatrimoineGlobalResponse,
  sections: PatrimoineGlobalSections,
  filename = "Patrimoine_Global_MINEPIA.xlsx"
): Promise<void> {
  const workbook = await buildPatrimoineGlobalWorkbook(response, sections);
  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], {
    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
