import type jsPDF from "jspdf";

const LOGO_URL = "/minepia-logo.png";
let logoDataUrlPromise: Promise<string> | null = null;

export const MINEPIA_PDF_HEADER_HEIGHT = 31;

function loadLogoDataUrl(): Promise<string> {
  if (logoDataUrlPromise) return logoDataUrlPromise;

  logoDataUrlPromise = fetch(LOGO_URL)
    .then((response) => {
      if (!response.ok) throw new Error("Logo MINEPIA introuvable");
      return response.blob();
    })
    .then((blob) => new Promise<string>((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(reader.error);
      reader.readAsDataURL(blob);
    }))
    .catch(() => "");

  return logoDataUrlPromise;
}

export async function createMinepiaPdfHeaderRenderer(doc: jsPDF): Promise<() => number> {
  const logoDataUrl = await loadLogoDataUrl();

  return () => {
    const pageWidth = doc.internal.pageSize.getWidth();
    const leftX = 14;
    const rightX = pageWidth - 14;
    const centerX = pageWidth / 2;

    doc.setTextColor(20, 20, 20);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8.5);
    doc.text("REPUBLIC OF CAMEROON", leftX, 10);
    doc.text("RÉPUBLIQUE DU CAMEROUN", rightX, 10, { align: "right" });

    doc.setFont("helvetica", "normal");
    doc.setFontSize(7.5);
    doc.text("Peace - Work - Fatherland", leftX, 14.5);
    doc.text("Paix - Travail - Patrie", rightX, 14.5, { align: "right" });

    doc.setFont("helvetica", "bold");
    doc.setFontSize(6.4);
    doc.text(["MINISTRY OF LIVESTOCK, FISHERIES", "AND ANIMAL INDUSTRIES"], leftX, 19.5);
    doc.text(["MINISTÈRE DE L'ÉLEVAGE, DES PÊCHES", "ET DES INDUSTRIES ANIMALES"], rightX, 19.5, { align: "right" });

    if (logoDataUrl) {
      doc.addImage(logoDataUrl, "PNG", centerX - 10, 5, 20, 20);
    }

    doc.setDrawColor(22, 101, 52);
    doc.setLineWidth(0.45);
    doc.line(14, 28, pageWidth - 14, 28);
    doc.setTextColor(0, 0, 0);
    doc.setFont("helvetica", "normal");

    return MINEPIA_PDF_HEADER_HEIGHT;
  };
}

export async function addMinepiaPdfHeader(doc: jsPDF): Promise<number> {
  const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
  return renderHeader();
}

export function minepiaPrintHeaderHtml(): string {
  return `
    <header class="minepia-document-header">
      <div class="minepia-document-header__side minepia-document-header__side--left">
        <strong>REPUBLIC OF CAMEROON</strong>
        <span>Peace - Work - Fatherland</span>
        <small>MINISTRY OF LIVESTOCK, FISHERIES<br>AND ANIMAL INDUSTRIES</small>
      </div>
      <img class="minepia-document-header__logo" src="${LOGO_URL}" alt="MINEPIA">
      <div class="minepia-document-header__side minepia-document-header__side--right">
        <strong>RÉPUBLIQUE DU CAMEROUN</strong>
        <span>Paix - Travail - Patrie</span>
        <small>MINISTÈRE DE L'ÉLEVAGE, DES PÊCHES<br>ET DES INDUSTRIES ANIMALES</small>
      </div>
    </header>`;
}

export const MINEPIA_PRINT_HEADER_CSS = `
  .minepia-document-header{display:grid;grid-template-columns:1fr 82px 1fr;align-items:start;gap:16px;padding-bottom:10px;margin-bottom:18px;border-bottom:2px solid #166534;color:#111}
  .minepia-document-header__side{display:flex;flex-direction:column;font-family:Arial,sans-serif;font-size:12px;line-height:1.25}
  .minepia-document-header__side strong{font-size:13px}
  .minepia-document-header__side small{font-size:9px;font-weight:700;margin-top:4px}
  .minepia-document-header__side--right{text-align:right;align-items:flex-end}
  .minepia-document-header__logo{display:block;width:68px;height:68px;object-fit:contain;margin:0 auto}
  @media print{.minepia-document-header{break-inside:avoid;page-break-inside:avoid}}
`;
