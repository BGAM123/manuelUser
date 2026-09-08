/**
 * Génération de graphiques rasterisés (PNG) pour intégration dans le
 * document Excel "Patrimoine Global" (ExcelJS ne supporte pas les graphiques
 * natifs Excel interactifs — on rend donc les graphiques via Chart.js sur un
 * canvas hors-DOM, puis on les insère comme images dans les feuilles).
 */
import { Chart, type ChartConfiguration, registerables } from "chart.js";

Chart.register(...registerables);

export const CHART_PALETTE = [
  "#006837", "#2d8a4e", "#8bc34a", "#ffb300", "#1e88e5",
  "#e53935", "#8e24aa", "#00acc1", "#6d4c41", "#546e7a",
  "#43a047", "#fb8c00", "#3949ab", "#c0ca33", "#d81b60",
];

/**
 * Rend un graphique Chart.js sur un canvas détaché du DOM et retourne son
 * image au format PNG encodée en base64 (sans le préfixe `data:image/png;base64,`).
 */
export async function renderChartPngBase64(
  config: ChartConfiguration,
  width = 640,
  height = 360
): Promise<string> {
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Impossible de créer le contexte 2D pour le graphique.");

  const chart = new Chart(ctx, {
    ...config,
    options: {
      ...config.options,
      responsive: false,
      animation: false,
      devicePixelRatio: 2,
    },
  });

  // Laisse le temps au moteur de rendu de finaliser le tracé avant capture.
  await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

  const dataUrl = canvas.toDataURL("image/png");
  chart.destroy();
  return dataUrl.split(",")[1] ?? "";
}

/** Garde les N plus grandes valeurs d'une série (label, valeur) triée décroissante. */
export function topN(labels: string[], values: number[], n: number): { labels: string[]; values: number[] } {
  const combined = labels.map((label, i) => ({ label, value: values[i] ?? 0 }));
  combined.sort((a, b) => b.value - a.value);
  const sliced = combined.slice(0, n);
  return { labels: sliced.map((c) => c.label), values: sliced.map((c) => c.value) };
}

export function buildBarChartConfig(
  labels: string[],
  values: number[],
  title: string,
  horizontal = false
): ChartConfiguration {
  return {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: title,
          data: values,
          backgroundColor: "#006837",
          borderRadius: 4,
          maxBarThickness: 28,
        },
      ],
    },
    options: {
      indexAxis: horizontal ? "y" : "x",
      plugins: {
        legend: { display: false },
        title: { display: true, text: title, font: { size: 15, weight: "bold" }, color: "#006837", padding: { bottom: 12 } },
      },
      scales: {
        x: { ticks: { autoSkip: false, font: { size: 10 } }, beginAtZero: true },
        y: { ticks: { autoSkip: false, font: { size: 10 } }, beginAtZero: true },
      },
    },
  };
}

export function buildDoughnutChartConfig(labels: string[], values: number[], title: string): ChartConfiguration {
  return {
    type: "doughnut",
    data: {
      labels,
      datasets: [
        {
          data: values,
          backgroundColor: labels.map((_, i) => CHART_PALETTE[i % CHART_PALETTE.length]),
          borderColor: "#ffffff",
          borderWidth: 2,
        },
      ],
    },
    options: {
      plugins: {
        legend: { position: "right", labels: { boxWidth: 12, font: { size: 11 } } },
        title: { display: true, text: title, font: { size: 15, weight: "bold" }, color: "#006837", padding: { bottom: 12 } },
      },
    },
  };
}
