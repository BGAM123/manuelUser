import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import "./styles.css";

// Nettoyage ponctuel — l'ancien cache localStorage des biens (7 jours) a été
// retiré du code, mais la clé pouvait rester dans le navigateur des
// utilisateurs ayant déjà visité l'app avant sa suppression.
try {
  localStorage.removeItem("minepia_biens_cache_api");
} catch {}

// "Failed to fetch dynamically imported module" — se produit quand le
// navigateur a un chunk JS lazy-loadé (ex: Statistiques-<hash>.js) qui ne
// correspond plus au build déployé (chaque déploiement régénère des hash
// différents). Vite émet cet évènement dans ce cas précis : on recharge la
// page pour récupérer le nouvel index.html et les bons chunks, plutôt que
// de laisser l'utilisateur bloqué sur un écran d'erreur.
window.addEventListener("vite:preloadError", () => {
  window.location.reload();
});

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
