    import React, { useMemo, useState, useEffect, useCallback, useRef, lazy, Suspense, type ReactNode } from "react";

import {
  Plus, Search, Download, MoreVertical, ChevronLeft, ChevronRight,
  Columns as ColumnsIcon, ArrowRightLeft, Printer, FileCheck2, X,
  Pencil, DoorOpen, Trash2, Upload, Eye, User as UserIcon, ArrowLeft,
  ChevronDown, Package, Calendar as CalendarIcon, ClipboardList, Loader2 as Loader2Icon,
  FileSpreadsheet, FileText, ExternalLink, Map as MapIcon, QrCode, ShieldCheck,
  TrendingDown,
  Wrench, Lock, LockOpen, CheckCircle2, PackageCheck, Undo2,
} from "lucide-react";
import { toast } from "sonner";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import * as XLSX from "xlsx";
import QRCode from "qrcode";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Checkbox } from "@/components/ui/checkbox";
import { AppShell } from "@/components/shared/AppShell";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter,
} from "@/components/ui/sheet";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
  DropdownMenuSeparator, DropdownMenuTrigger, DropdownMenuCheckboxItem,
  DropdownMenuSub, DropdownMenuSubTrigger, DropdownMenuSubContent, DropdownMenuPortal,
} from "@/components/ui/dropdown-menu";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import { OrgTreeSelect as PosteOrgSelect, formatPosteLabel } from "@/components/shared/OrgTreeSelect";
import { CartographieTreeSelect } from "@/components/shared/CartographieTreeSelect";
import { OrgTreeMultiSelect } from "@/components/shared/OrgTreeMultiSelect";
import { useIsAdmin } from "@/hooks/useIsAdmin";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { listAssetAssignmentsPage } from "@/api/biens/asset-assignments-list.api";
import { SearchableMultiSelect } from "@/components/shared/SearchableMultiSelect";
import { RemoteSearchSelect } from "@/components/shared/RemoteSearchSelect";
import { FicheDetenteurDialog } from "@/components/shared/FicheDetenteurDialog";
import { useExportActions, type ExportColumn } from "@/components/shared/ExportButton";
import { cn } from "@/utils/utils";
import { resolveFileUrl } from "@/utils/files";
import {
  createMinepiaPdfHeaderRenderer,
  MINEPIA_PDF_HEADER_HEIGHT,
  MINEPIA_PRINT_HEADER_CSS,
  minepiaPrintHeaderHtml,
} from "@/services/minepiaDocumentHeader";
import { useMutation, useQuery, useQueries, useQueryClient } from "@tanstack/react-query";
import { useT, type Key } from "@/utils/i18n";
import { useNavigate } from "react-router-dom";
import { formatFCFA } from "@/api/common";
import {
  createBien, listBiens, listBiensPage, updateBien, deleteBien, softDeleteBien, getBienById,
  getAssetMercuriale, patchBienEtat,
  deletePieceJointe, renamePieceJointe,
  setAssetAmortissementActive,
  setAssetReevaluationActive,
  setAssetDepreciationActive,
  restituerBien,
  type ApiBien, type CreateBienPayload, type RestituerBienPayload,
} from "@/api/biens/biens.api";
import { listCategories, listCategoryThresholds } from "@/api/categories/categories.api";
import { listAssetTypes, getEtatBiensForAssetType } from "@/api/asset-types/asset-types.api";
import { listChamps, type ApiChamp, type ChampSubtype } from "@/api/champs/champs.api";
import { getCartographie, type CartographieRegion } from "@/api/regions/regions.api";
import { listAssetSubtypes, type ApiAssetSubtype } from "@/api/asset-subtypes/asset-subtypes.api";
import { listEtatBiens, type ApiEtatBien } from "@/api/etat-biens/etat-biens.api";
import { getOrganigramme, type ApiOrgNode } from "@/api/services/services.api";
import { listProjects } from "@/api/projects/projects.api";
import { listAllUsers, listUsers } from "@/api/users/users.api";
import { getInventaire, type InventaireCategorie, type InventaireData } from "@/api/inventaire/inventaire.api";
import {
  listAffectations, createAffectation, updateAffectation, deleteAffectation, acknowledgeAffectation,
  createMaintenance, updateMaintenance, deleteMaintenance,
  createReevaluation, updateReevaluation, deleteReevaluation,
  createDepreciation, updateDepreciation, deleteDepreciation,
  type ApiAffectation, type ApiMaintenance, type ApiReevaluation, type ApiDepreciation,
} from "@/api/biens/asset-events.api";
import {
  createAssetExit,
  type ApiAssetExit,
  getAssetExit,
} from "@/api/biens/asset-exits.api";
import { listExitTypes, type ApiExitType } from "@/api/exit-types/exit-types.api";
import {
  createLocationPoint, createLocationFile, getCurrentLocation, getLocationsHistory,
  type ApiAssetLocation, type CreateLocationPointPayload,
} from "@/api/biens/asset-locations.api";
import {
  createBsp,
  type ApiBsp,
} from "@/api/biens/bsps.api";
import {
  createSecurity, type SecurityMode,
} from "@/api/securities/securities.api";

// Carte Leaflet — chargée en dynamique (recommandation 33), pas dans le
// paquet principal : elle ne se télécharge que si la section Localisation
// est ouverte, jamais pour un utilisateur qui n'y touche pas.
const LocationMap = lazy(() =>
  import("@/components/shared/LocationMap").then((m) => ({ default: m.LocationMap })),
);
const LocationPickerMap = lazy(() =>
  import("@/components/shared/LocationMap").then((m) => ({ default: m.LocationPickerMap })),
);
function MapLoadingFallback({ height = 300 }: { height?: number }) {
  const t = useT();
  return (
    <div
      style={{ height }}
      className="flex items-center justify-center rounded-lg border border-border bg-muted/20 text-xs text-muted-foreground"
    >
      {t("biens.map.loading")}
    </div>
  );
}

// Normalise la valeur brute du statut renvoyée par l'API — le backend
// renvoie tantôt "SORTIS" tantôt "SORTIE" selon l'origine de l'enregistrement ;
// on unifie toujours vers "SORTIE". Reste au format brut (snake_case) attendu
// par l'API pour les comparaisons/filtres — voir displayStatut() pour l'affichage.
function normalizeStatut(statut?: string | null): string {
  if (!statut) return "";
  return statut === "SORTIS" ? "SORTIE" : statut;
}

// Libellé lisible d'un statut — convertit le snake_case de l'API
// (ex: "EN_MAINTENANCE") en texte espacé ("EN MAINTENANCE") pour l'affichage.
function displayStatut(statut?: string | null): string {
  if (!statut) return "—";
  return normalizeStatut(statut).replace(/_/g, " ");
}

  /* =========================================================
    Types locaux (UI seulement — pas de mock)
    ========================================================= */

  type Piece = { file: File; libelle: string };

  type AffectationBspData = {
    quantiteDemandee?: string;
    quantiteAccordee?: string;
    quantiteServie?: string;
    dateBsp?: string;
    observations?: string;
  };

  type AffectationSubmitPayload = {
    service_id?: number;
    user_id?: number;
    dateDebut: string;
    dateFin?: string;
    commentaire?: string;
    typeAffectation?: string;
    methodConso?: string;
    transferPieces?: Array<{ file: File; nom: string }>;
    bspData?: AffectationBspData;
  };

  /* =========================================================
    Demandes de réforme — enregistrées dans la table AssetExit
    =========================================================
    Quand un utilisateur demande la réforme d'un bien, une sortie
    (motifSortie = "REFORME") est créée via POST /asset-exits SANS
    basculer l'état du bien. L'administrateur valide ensuite la
    demande depuis le panneau de validation → le backend passe le
    bien à l'état "Réformé" (statut SORTIS).
    ========================================================= */

  // ── Helpers sécurisation ────────────────────────────────────────────────
  // Définis au niveau module pour être accessibles aussi bien dans
  // BiensList (bouton + openSecurisation) que dans SecurisationDialog
  // (affichage conditionnel carte).
  function normalizeCategorieSecurite(nom?: string) {
    return (nom ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
  }
  function isImmobilier(nom?: string) {
    // Les vraies cat\u00e9gories en base sont au pluriel ("B\u00c2TIMENTS", "TERRAINS",
    // confirm\u00e9 en direct) \u2014 comparer uniquement au singulier faisait \u00e9chouer
    // ce test pour toutes les vraies donn\u00e9es, jamais juste pour les biens
    // non-immobiliers comme pr\u00e9vu. On accepte singulier et pluriel.
    const n = normalizeCategorieSecurite(nom);
    return n === "batiment" || n === "batiments" || n === "terrain" || n === "terrains";
  }

  /**
   * Une sortie est considérée comme une demande de réforme si elle porte
   * la mention "réform" dans :
   *   - son motif (motifSortie),
   *   - le code de son type de sortie (exitType.code),
   *   - le nom de son type de sortie (exitType.nom).
   * La comparaison est insensible à la casse et aux espaces, et accepte les
   * variantes ("REFORME", "reforme", "Réforme", "reformation", etc.).
   */
  function isReformeExit(e: { motifSortie?: string; exitType?: { code?: string; nom?: string } }): boolean {
    const motif = String(e.motifSortie ?? "").toUpperCase();
    const code  = String(e.exitType?.code ?? "").toUpperCase();
    const nom   = String(e.exitType?.nom ?? "").toUpperCase();
    return motif.includes("REFORM") || code.includes("REFORM") || nom.includes("REFORM");
  }

  /* =========================================================
    BienFormState — champs du formulaire UI
    ========================================================= */

  type BienFormState = {
    nom: string;
    numeroSerie: string;
    description: string;
    dateAcquisition: string;
    valeur: string;
    sourceFinancement: string;
    // Recommandation 84/rec-83-86-follow-up — exercice du projet sélectionné,
    // capturé directement à la sélection (pas re-déduit au rendu) pour
    // s'afficher de façon fiable sous le champ Source de financement.
    sourceFinancementExercice: number | string | null;
    modeAcquisition: string;
    statut: string;
    // Mercuriale (référence de prix officielle — https://www.mercuriale.cm/)
    code: string;
    prixMercurial: string;
    // Fournisseur
    typeFournisseur: string;
    fournisseurNom: string;
    fournisseurEmail: string;
    fournisseurTelephone: string;
    fournisseurAdresse: string;
    fournisseurVille: string;
    fournisseurPays: string;
    // Relations (IDs)
    category_id: number | null;
    asset_type_id: number | null;
    // Recommandation 6.a — sous-type lié au type de bien
    asset_sub_type_id: number | null;
    etat_bien_id: number | null;
    service_id: number | null;
    user_id: number | null;   // peut être combiné à service_id — les deux sont envoyés au backend
    // Utilisateur de restitution par défaut pour ce bien (préremplissage
    // côté GET via userRestitution) — indépendant de user_id.
    user_restitution_id: number | null;
    service_nom: string; // affiché dans le popover Structure
    matricule_nom: string; // affiché dans le popover Matricule
    category_nom: string;
    asset_type_nom: string;
    asset_sub_type_nom: string;
    etat_bien_nom: string;
    project_ids: number[];
    // Recommandation 6.a — valeurs des champs personnalisés (champ_id → valeur)
    champsValues: Record<number, string>;
    // Recommandation 92 — id de l'enregistrement de valeur existant, par champ_id (édition uniquement)
    champsValueIds: Record<number, number>;
    // Fichiers
    photosFiles: File[];
    photoPreview: string | null;
    piecesJointes: Piece[];
    // Localisation (Terrains/Bâtiments uniquement) — POST /assets et
    // POST /assets/{id} acceptent latitude/longitude directement dans la
    // même requête que le reste du bien, pas d'appel séparé nécessaire.
    latitude: string;
    longitude: string;
    // Terrains/Bâtiments uniquement — alternative à la saisie manuelle :
    // importer un fichier géospatial (CSV, Excel, GeoJSON, KML, GPX,
    // Shapefile) via POST /assets/{id}/locations. Nécessite un bien déjà
    // créé (id) : envoyé juste après la création/modification, pas dans la
    // même requête que POST /assets.
    locationMode: "manual" | "file";
    locationFile: File | null;
    // Activation de la réévaluation, de la dépréciation et de l'amortissement
    // — actifs par défaut.
    activeReevaluation: boolean;
    activeDepreciation: boolean;
    activeAmortissement: boolean;
    // Sécurisation à la création — checkboxes, un bien peut être sécurisé
    // juridiquement ET physiquement à la fois (deux modes cochables en même
    // temps). Envoyée après création via POST /securities, un appel par mode
    // coché (même mécanisme que SecurisationDialog) — le champ securityMode
    // de POST /assets ne supporte qu'une seule valeur, insuffisant ici.
    securityModes: Set<SecurityMode>;
  };

  const emptyForm: BienFormState = {
    nom: "", numeroSerie: "", description: "",
    dateAcquisition: "", valeur: "",
    sourceFinancement: "", sourceFinancementExercice: null, modeAcquisition: "", statut: "ACTIF",
    code: "", prixMercurial: "",
    typeFournisseur: "ENTREPRISE",
    fournisseurNom: "", fournisseurEmail: "", fournisseurTelephone: "",
    fournisseurAdresse: "", fournisseurVille: "", fournisseurPays: "Cameroun",
    category_id: null, asset_type_id: null, asset_sub_type_id: null, etat_bien_id: null,
    service_id: null, user_id: null, user_restitution_id: null, service_nom: "", matricule_nom: "",
    category_nom: "",
    asset_type_nom: "",
    asset_sub_type_nom: "",
    etat_bien_nom: "",
    project_ids: [],
    champsValues: {},
    champsValueIds: {},
    photosFiles: [], photoPreview: null,
    piecesJointes: [],
    latitude: "", longitude: "",
    locationMode: "manual",
    locationFile: null,
    activeReevaluation: true,
    activeDepreciation: true,
    activeAmortissement: true,
    securityModes: new Set<SecurityMode>(),
  };

  // const sourcesFinancement = ["Budget Etat", "Ressources propres", "Partenaires", "BIP", "Don"];
  // const modesAcquisition = ["Achat", "Don", "Legs", "Transfert", "Fabrication interne"];
  const typesFournisseur = ["ENTREPRISE", "INDIVIDU", "ONG", "ADMINISTRATION"];
  const statutsBien = ["ACTIF", "SORTIE", "EN_MAINTENANCE", "INACTIF"];

  /* =========================================================
    buildBienPayload — convertit BienFormState → CreateBienPayload
    =========================================================
    BienFormState est l'état interne du formulaire (tout en string/null pour les inputs).
    CreateBienPayload est ce qu'on envoie au backend (types corrects, champs vides exclus).
    ========================================================= */

  function buildBienPayload(form: BienFormState): CreateBienPayload {
    return {
      nom: form.nom,
      numeroSerie: form.numeroSerie || undefined,
      description: form.description || undefined,
      dateAcquisition: form.dateAcquisition,
      valeur: Number(form.valeur) || 0,
      sourceFinancement: form.sourceFinancement || undefined,
      modeAcquisition: form.modeAcquisition || undefined,
      statut: form.statut || undefined,
      code: form.code || undefined,
      prixMercurial: form.prixMercurial ? Number(form.prixMercurial) : undefined,
      typeFournisseur: form.typeFournisseur || undefined,
      fournisseurNom: form.fournisseurNom,
      fournisseurEmail: form.fournisseurEmail,
      fournisseurTelephone: form.fournisseurTelephone,
      fournisseurAdresse: form.fournisseurAdresse || undefined,
      fournisseurVille: form.fournisseurVille || undefined,
      fournisseurPays: form.fournisseurPays || undefined,
      category_id: form.category_id ?? 0,
      asset_type_id: form.asset_type_id ?? 0,
      // Recommandation 6.a — sous-type + valeurs des champs personnalisés.
      // On n'envoie que les valeurs non vides pour ne pas polluer le backend.
      asset_sub_type_id: form.asset_sub_type_id ?? undefined,
      champsValues: Object.keys(form.champsValues).length > 0
        ? Object.fromEntries(
            Object.entries(form.champsValues).filter(([, v]) => String(v).trim() !== ""),
          )
        : undefined,
      champsValueIds: Object.keys(form.champsValueIds).length > 0 ? form.champsValueIds : undefined,
      etat_bien_id: form.etat_bien_id ?? undefined,
      service_id: form.service_id ?? 0,
      user_id: form.user_id ?? undefined,
      user_restitution_id: form.user_restitution_id ?? undefined,
      project_ids: form.project_ids.length > 0 ? form.project_ids : undefined,
      // Localisation (Terrains/Bâtiments) — envoyée avec le reste du bien,
      // dans la même requête (voir buildFormData).
      latitude: form.latitude.trim() ? Number(form.latitude) : undefined,
      longitude: form.longitude.trim() ? Number(form.longitude) : undefined,
      activeReevaluation: form.activeReevaluation,
      activeDepreciation: form.activeDepreciation,
      activeAmortissement: form.activeAmortissement,
      photosFiles: form.photosFiles.length > 0 ? form.photosFiles : undefined,
      piecesJointesFiles: form.piecesJointes.length > 0
        ? form.piecesJointes.map((p) => p.file) : undefined,
      piecesJointesNoms: form.piecesJointes.length > 0
        ? form.piecesJointes.map((p) => p.libelle) : undefined,
    };
  }

  /* =========================================================
    Colonnes du tableau pour la creation de la table
    ========================================================= */

  type ColKey =
    | "reference" | "nom" | "categorie" | "assetType" | "service" | "matricule"
    | "statut" | "sourceFinancement" | "exercice" | "valeur" | "dateAcquisition" | "etatBien";

  const allColumns: {
    key: ColKey; label: string; render: (b: ApiBien, labels?: { secured: string; notSecured: string }) => ReactNode; className?: string;
  }[] = [
    { key: "reference", label: "Référence", render: (b) => <span className="font-mono text-xs">{b.reference}</span> },
    {
      key: "nom",
      label: "Désignation",
      render: (b, labels) => (
        <span className="inline-flex items-center gap-1.5">
          <span className="font-medium">{b.nom}</span>
          {b.securise ? (
            <Lock className="h-3.5 w-3.5 shrink-0 text-emerald-600" aria-label={labels?.secured ?? "Sécurisé"} />
          ) : (
            <LockOpen className="h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-label={labels?.notSecured ?? "Non sécurisé"} />
          )}
        </span>
      ),
    },
    { key: "categorie", label: "Catégorie", render: (b) => b.category?.nom ?? "—" },
    { key: "assetType", label: "Type de bien", render: (b) => b.assetType?.nom ?? "—" },
    {
      key: "service",
      label: "Structure actuelle",
      render: (b) => b.service?.nom ?? "—",
    },
    {
      key: "matricule",
      label: "Matricule",
      // Valeur par défaut (pas d'accès à la liste des utilisateurs à ce niveau) —
      // remplacée par BiensList via dynamicColumns avec le vrai matricule résolu.
      render: (b) => b.utilisateur?.matricule ?? "—",
    },
    { key: "statut", label: "Statut", render: (b) => <StatusBadge tone={statutTone(b.statut ?? "")}>{displayStatut(b.statut)}</StatusBadge> },
    { key: "sourceFinancement", label: "Source de financement", render: (b) => {
      const src = b.sourceFinancement ?? b.projects?.[0]?.nom;
      return src ? <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{src}</span> : <span className="text-muted-foreground">—</span>;
    }},
    { key: "exercice", label: "Exercice", render: (b) => b.exercice || "—" },
    {
      key: "valeur", label: "Valeur (FCFA)",
      render: (b) => <span className="tabular-nums">{formatFCFA(b.valeur)}</span>,
      className: "text-right",
    },
    { key: "dateAcquisition", label: "Date d'acquisition", render: (b) => b.dateAcquisition },
    { key: "etatBien", label: "État du bien", render: (b) => b.etatBien?.nom ?? "—" },
  ];

  // Clés de traduction des libellés de colonnes — allColumns est défini au
  // niveau module (pas de hook disponible) ; les composants qui affichent
  // ces libellés (BiensList) résolvent via t(columnLabelKeys[col.key]).
  const columnLabelKeys: Record<ColKey, Key> = {
    reference: "biens.list.col.reference",
    nom: "biens.field.designation",
    categorie: "biens.field.categorie",
    assetType: "biens.list.col.assetType",
    service: "biens.list.col.serviceActuel",
    matricule: "biens.list.col.matricule",
    statut: "common.status",
    sourceFinancement: "biens.list.col.sourceFinancement",
    exercice: "biens.list.col.exercice",
    valeur: "biens.list.col.valeurFcfa",
    dateAcquisition: "biens.field.acquisition",
    etatBien: "biens.list.col.etatBien",
  };

  const defaultVisible: ColKey[] = [
    "reference", "nom", "categorie", "assetType", "service", "matricule",
    "statut", "sourceFinancement", "exercice", "valeur", "dateAcquisition", "etatBien",
  ];

  /** URL de recherche mercuriale.cm pour un bien, construite côté client à
   * partir de son nom (formule confirmée via l'exemple Swagger de
   * GET /assets/{id}/mercurriale, cf. recommandations 34/35). */
  function mercurialeUrl(nom: string): string {
    return `https://www.mercuriale.cm/#/articles-found?key=${encodeURIComponent(nom)}`;
  }

  /** Fenêtre (75% de l'écran) affichant la mercuriale dans un iframe, plutôt
   * que de naviguer vers un nouvel onglet. */
  function MercurialeIframeDialog({ url, nom, onClose }: { url: string | null; nom?: string; onClose: () => void }) {
    const t = useT();
    return (
      <Dialog open={!!url} onOpenChange={(v) => !v && onClose()}>
        <DialogContent className="flex h-[75vh] w-[75vw] max-w-none flex-col gap-0 p-0">
          <DialogHeader className="border-b border-border px-4 py-3">
            <DialogTitle className="text-sm">
              {t("biens.mercuriale.label")}{nom ? ` — ${nom}` : ""}
            </DialogTitle>
          </DialogHeader>
          {url && (
            <iframe src={url} title={t("biens.mercuriale.label")} className="w-full flex-1 border-0" />
          )}
        </DialogContent>
      </Dialog>
    );
  }

  /* =========================================================
    Page principale BiensPage
    =========================================================
    RESPONSABILITÉS :
    - Charge les biens depuis GET /assets (biensData)
    - Charge les référentiels (catégories, types, états, services)
      → staleTime: Infinity car ces données changent rarement
      → servent uniquement au formulaire et à enrichBien()
    - Gère les 3 vues : liste / détail / formulaire
    - Gère les mutations : create / update / delete
    - Injecte le bien créé/modifié directement dans le cache React Query
      via setQueryData() → pas de refetch inutile
    ========================================================= */

  type View = "liste" | "detail" | "formulaire";
  type DrawerKey = null | "affectation" | "sortie" | "maintenance" | "reevaluation" | "depreciation";

  export default function BiensPage() {
    const queryClient = useQueryClient();
    const t = useT();

    // ── Biens ────────────────────────────────────────────────────────────────
    // BiensList charge et pagine ses propres données côté serveur (voir
    // queryKey ["biens-list"]) — plus de fetch centralisé ici, ce composant
    // ne charge que les référentiels partagés (catégories, types, états,
    // organigramme) utilisés par le formulaire de création/modification.

    // ── Référentiels ─────────────────────────────────────────────────────────
    // Chargés une seule fois (staleTime: Infinity) — servent à :
    //   1. Peupler les selects du formulaire de création/modification
    //   2. enrichBien() : résoudre les noms quand le backend ne les retourne pas
    // NOTE : allBiens = biensData directement car normalizeBien() fait déjà le mapping.
    const { data: categoriesData, isLoading: catLoading } = useQuery({ queryKey: ["categories"], queryFn: () => listCategories({ limit: 200 }), staleTime: Infinity, refetchOnWindowFocus: false });
    const { data: assetTypesData, isLoading: typLoading } = useQuery({ queryKey: ["asset-types"], queryFn: () => listAssetTypes({ limit: 200 }), staleTime: Infinity, refetchOnWindowFocus: false });
    const { data: etatBiensData,  isLoading: etatLoading } = useQuery({ queryKey: ["etat-biens"],  queryFn: () => listEtatBiens({ limit: 200 }),  staleTime: Infinity, refetchOnWindowFocus: false });
    const { data: orgData,        isLoading: orgLoading  } = useQuery({ queryKey: ["organigramme"], queryFn: () => getOrganigramme(),              staleTime: Infinity, refetchOnWindowFocus: false });

    // Vrai tant qu'au moins un référentiel n'est pas encore chargé.
    // On attend tous les référentiels avant d'afficher le tableau pour éviter les "—" temporaires.
    const refsLoading = catLoading || typLoading || etatLoading || orgLoading;

    const refCategories: Array<{ id: number; nom: string }> = useMemo(() => categoriesData?.data?.data ?? [], [categoriesData]);
    const refAssetTypes: Array<{ id: number; nom: string; category_id?: number }> = useMemo(() => assetTypesData?.data?.data ?? [], [assetTypesData]);
    const refEtatBien: ApiEtatBien[] = useMemo(() => etatBiensData?.data?.data ?? [], [etatBiensData]);
    const refServices: Array<{ id: number; nom: string; sigle?: string }> = useMemo(() => {
      const nodes: ApiOrgNode[] = orgData?.data?.data ?? [];
      const flatten = (list: ApiOrgNode[]): Array<{ id: number; nom: string; sigle?: string }> =>
        list.flatMap((n) => [{ id: n.id, nom: n.nom, sigle: n.sigle }, ...flatten(n.children ?? [])]);
      return flatten(nodes);
    }, [orgData]);

    const [view, setView] = useState<View>("liste");
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [current, setCurrent] = useState<ApiBien | null>(null);
    const [drawer, setDrawer] = useState<DrawerKey>(null);
    const [formMode, setFormMode] = useState<"create" | "edit">("create");
    // Sortie directe depuis le tableau — sans ouvrir la fiche détail
    const [sortieDirectTarget, setSortieDirectTarget] = useState<ApiBien | null>(null);

    // Extrait le message lisible depuis une erreur Axios
    function extractErrorMessage(err: unknown): string {
      if (err && typeof err === "object" && "response" in err) {
        const res = (err as { response?: { data?: { message?: string; detail?: string; error?: string; errors?: Record<string, string[]> }; status?: number } }).response;
        if (res) {
          const msg = res.data?.message ?? res.data?.detail ?? res.data?.error;
          if (msg) return t("biens.error.withMessage", { status: res.status ?? "", msg });
          if (res.data?.errors) {
            const first = Object.values(res.data.errors).flat()[0];
            if (first) return t("biens.error.validation", { msg: first });
          }
          return t("biens.error.httpGeneric", { status: res.status ?? t("common.unknown") });
        }
      }
      if (err instanceof Error) return err.message;
      return t("biens.error.unknown");
    }

    // ── enrichBien ───────────────────────────────────────────────────────────
    // Utilisé après create/update pour s'assurer que le bien dans le cache
    // a bien ses noms de relations même si le backend ne les retourne pas.
    // Ordre de priorité : données API complètes > référentiels locaux > preview (noms du formulaire)
    function enrichBien(apiBien: ApiBien, preview?: Partial<ApiBien>): ApiBien {
      const rawAny = apiBien as unknown as Record<string, unknown>;

      const category = apiBien.category?.nom
        ? apiBien.category
        : refCategories.find((c) => c.id === (apiBien.category?.id ?? (rawAny.category_id as number)))
          ?? preview?.category;

      const assetType = apiBien.assetType?.nom
        ? apiBien.assetType
        : refAssetTypes.find((a) => a.id === (apiBien.assetType?.id ?? (rawAny.asset_type_id as number)))
          ?? preview?.assetType;

      const etatBien = apiBien.etatBien?.nom
        ? apiBien.etatBien
        : refEtatBien.find((e) => e.id === (apiBien.etatBien?.id ?? (rawAny.etat_bien_id as number)))
          ?? preview?.etatBien;

      const service = apiBien.service?.nom
        ? apiBien.service
        : refServices.find((s) => s.id === (apiBien.service?.id ?? (rawAny.service_id as number)))
          ?? preview?.service;

      // Enrichir utilisateur depuis preview si absent dans l'API
      const utilisateur = apiBien.utilisateur ?? preview?.utilisateur;

      // Enrichir projects depuis preview si absent dans l'API
      const projects = apiBien.projects ?? preview?.projects;

      // Enrichir sourceFinancement depuis projects si absent
      const sourceFinancement = apiBien.sourceFinancement 
        ?? (projects && projects.length > 0 ? projects[0].nom : undefined)
        ?? preview?.sourceFinancement;

      return { 
        ...apiBien, 
        category, 
        assetType, 
        etatBien, 
        service, 
        utilisateur,
        projects,
        sourceFinancement,
      };
    }

    // ── Mutation : créer un bien ─────────────────────────────────────────────
    // 1. POST /assets → createBien() → bien créé (parfois sans relations complètes)
    // 2. GET /assets/{id} → getBienById() → bien complet avec toutes les relations
    // 3. setQueryData() → injection directe dans le cache (pas de refetch réseau)
    // 4. enrichBien() → fallback si getBienById() retourne quand même sans noms
    const createBienMutation = useMutation<
      ApiBien,
      unknown,
      { payload: CreateBienPayload; preview?: Partial<ApiBien>; locationFile?: File; securityModes?: Set<SecurityMode> }
    >({
      mutationFn: async ({ payload, locationFile, securityModes }) => {
        const created = await createBien(payload);
        if (locationFile) {
          try {
            await createLocationFile(created.id, locationFile);
          } catch (err) {
            const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
            toast.error(msg ?? t("biens.error.locationImportCreate"), { duration: 8000 });
          }
        }
        // Sécurisation à la création — un POST /securities par mode coché
        // (voir SecurisationDialog, même mécanisme : l'API n'accepte qu'un
        // security_mode par appel, pas de mode combiné).
        if (securityModes && securityModes.size > 0) {
          const today = new Date().toISOString().slice(0, 10);
          for (const mode of securityModes) {
            try {
              await createSecurity({ asset_ids: [created.id], security_mode: mode, date_securisation: today });
            } catch (err) {
              const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
              toast.error(msg ?? t("biens.error.securityFailed", { mode }), { duration: 8000 });
            }
          }
        }
        try {
          return await getBienById(created.id);
        } catch {
          return created;
        }
      },
      onSuccess: () => {
        // BiensList est paginé côté serveur (voir queryKey ["biens-list"]) — un
        // simple bien créé n'a pas de position déterminable dans une page
        // triée par le backend ; on invalide/refetch la page courante plutôt
        // que de le spliced dans un tableau local qui n'existe plus.
        queryClient.invalidateQueries({ queryKey: ["biens-list"] });
        toast.success(t("biens.toast.createSuccess"));
        setView("liste");
      },
      onError: (err) => {
        const msg = extractErrorMessage(err);
        toast.error(msg, { duration: 8000 });
        console.error("[createBien] erreur:", err);
      },
    });

    // ── Mutation : modifier un bien ──────────────────────────────────────────
    // Même logique que createBienMutation.
    // setCurrent() met aussi à jour la vue détail si elle est ouverte.
    const updateBienMutation = useMutation<ApiBien, unknown, { id: number; payload: CreateBienPayload; preview?: Partial<ApiBien>; locationFile?: File }>({
      mutationFn: async ({ id, payload, locationFile }) => {
        const updated = await updateBien(id, payload);
        if (locationFile) {
          try {
            await createLocationFile(id, locationFile);
          } catch (err) {
            const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
            toast.error(msg ?? t("biens.error.locationImportUpdate"), { duration: 8000 });
          }
        }
        try {
          return await getBienById(updated.id);
        } catch {
          return updated;
        }
      },
      onSuccess: (updatedBien, variables) => {
        const enrichedBien = enrichBien(updatedBien, variables?.preview);
        queryClient.invalidateQueries({ queryKey: ["biens-list"] });
        // Invalide le cache du bien individuel pour forcer un refetch dans BienDetail
        queryClient.invalidateQueries({ queryKey: ["bien", enrichedBien.id] });
        setCurrent(enrichedBien);
        toast.success(t("biens.toast.updateSuccess"));
        setView("liste");
      },
      onError: (err) => {
        const msg = extractErrorMessage(err);
        toast.error(msg, { duration: 8000 });
        console.error("[updateBien] erreur:", err);
      },
    });

    // ── Mutation : supprimer un bien ─────────────────────────────────────────
    // invalidateQueries force un refetch complet — normal pour une suppression.
    // ── Mutation : supprimer un bien (soft-delete) ───────────────────────────
    // Appelle DELETE /assets/{id}/soft-delete → le bien est marqué is_delete=true
    // en base, il disparaît de la liste mais reste restaurable.
    const deleteBienMutation = useMutation<null, Error, number>({
      mutationFn: softDeleteBien,
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ["biens-list"] });
        toast.success(t("biens.toast.deleteSuccess"));
        setView("liste");
        setCurrent(null);
      },
      onError: (err) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) {
          toast.error(t("biens.error.softDeleteUnavailable"));
        } else {
          toast.error(t("biens.error.deleteFailed"));
        }
      },
    });

    // openDetail : affiche immédiatement avec les données résumées.
    // BienDetail fait son propre useQuery pour charger les détails complets (fournisseur etc.).
    const openDetail = (b: ApiBien) => {
      setCurrent(b);
      setView("detail");
    };

    // openForm en mode edit : charge d'abord le bien complet depuis l'API
    // pour avoir les champs fournisseur etc., puis ouvre le formulaire.
    const openForm = async (mode: "create" | "edit", b: ApiBien | null = null) => {
      setFormMode(mode);
      if (mode === "edit" && b) {
        try {
          const full = await getBienById(b.id);
          setCurrent(full);
        } catch {
          setCurrent(b); // fallback sur les données résumées
        }
      }
      setView("formulaire");
    };

  // handlePrint : charge le bien complet (GET /assets/{id} embarque déjà
  // maintenances/reevaluations/depreciations) puis les affectations séparément.
  const handlePrint = async (b: ApiBien) => {
    let fullBien = b;
    try { fullBien = await getBienById(b.id); } catch { /* fallback sur les données résumées */ }

    let affectations: ApiAffectation[] = [];
    try { affectations = await listAffectations(b.id); } catch { /* skip */ }

    await printBien({
      bien: fullBien,
      affectations,
      maintenances: fullBien.maintenances ?? [],
      reevals: fullBien.reevaluations ?? [],
      deprecs: fullBien.depreciations ?? [],
    }, t);
  };

  // Impression groupée depuis la sélection multiple — charge chaque bien
  // complet puis regroupe toutes les fiches dans un seul document (voir
  // printBiensBulk). Avec un seul bien sélectionné, comportement identique
  // à l'impression individuelle.
  const handleBulkPrint = async (biens: ApiBien[]) => {
    if (biens.length === 0) return;
    const dataList = await Promise.all(
      biens.map(async (b) => {
        let fullBien = b;
        try { fullBien = await getBienById(b.id); } catch { /* fallback sur les données résumées */ }
        let affectations: ApiAffectation[] = [];
        try { affectations = await listAffectations(b.id); } catch { /* skip */ }
        return {
          bien: fullBien,
          affectations,
          maintenances: fullBien.maintenances ?? [],
          reevals: fullBien.reevaluations ?? [],
          deprecs: fullBien.depreciations ?? [],
        };
      }),
    );
    await printBiensBulk(dataList, t);
  };

    return (
      <AppShell>
        {view === "liste" && (
          <BiensList
            selectedIds={selectedIds}
            onSelectionChange={setSelectedIds}
            onOpen={openDetail}
            onNew={() => openForm("create")}
            onEdit={(b) => openForm("edit", b)}
            onDelete={(id) => deleteBienMutation.mutate(id)}
            onPrint={(b) => handlePrint(b)}
            onPrintSelected={(biens) => handleBulkPrint(biens)}
            etatBiens={refEtatBien}
            onChangeEtat={(id, etatId) => {
              patchBienEtat(id, etatId)
                .then(() => queryClient.invalidateQueries({ queryKey: ["biens-list"] }))
                .catch((err: unknown) => {
                  const status = (err as { response?: { status?: number } })?.response?.status;
                  toast.error(`Erreur ${status ?? ""} lors de la mise à jour de l'état.`);
                });
            }}
            onSortir={(b) => setSortieDirectTarget(b)}
          />
        )}

        {view === "detail" && current && (
          <BienDetail
            bien={current}
            onBack={() => setView("liste")}
            onEdit={() => openForm("edit", current)}
            onDelete={() => deleteBienMutation.mutate(current.id)}
            onPrint={() => handlePrint(current)}
          />
        )}

        {view === "formulaire" && (
          <BienFormPage
            mode={formMode}
            bien={formMode === "edit" ? current : null}
            isSaving={createBienMutation.isPending || updateBienMutation.isPending}
            onCancel={() => setView("liste")}
            onSave={async (formState) => {
              const payload = buildBienPayload(formState);
              
              // Charger les données des utilisateurs et projets pour construire le preview complet
              const usersResp = await listUsers({ page: 1, limit: 1000 });
              const projectsResp = await listProjects({ limit: 1000 });
              
              const usersList = usersResp?.data?.data ?? [];
              const projectsList = projectsResp?.data ?? [];
              
              // Construire utilisateur pour le preview
              const selectedUser = formState.user_id 
                ? usersList.find((u: { id: number }) => u.id === formState.user_id)
                : undefined;
              
              // Construire projects pour le preview
              const selectedProjects = formState.project_ids.length > 0
                ? formState.project_ids.map(pid => projectsList.find((p: { id: number; nom: string }) => p.id === pid)).filter(Boolean).map((p: { id: number; nom: string } | undefined) => p ? ({ id: p.id, nom: p.nom }) : null).filter((p): p is { id: number; nom: string } => p !== null)
                : undefined;
              
              // Construire sourceFinancement depuis le premier projet
              const sourceFinancement = selectedProjects && selectedProjects.length > 0
                ? selectedProjects[0].nom
                : formState.sourceFinancement || undefined;
              // updates
              const preview: Partial<ApiBien> = {   
                category: formState.category_id ? { id: formState.category_id, nom: formState.category_nom } : undefined,
                assetType: formState.asset_type_id ? { id: formState.asset_type_id, nom: formState.asset_type_nom } : undefined,
                service: formState.service_id ? { id: formState.service_id, nom: formState.service_nom } : undefined,
                etatBien: formState.etat_bien_id ? { id: formState.etat_bien_id, nom: formState.etat_bien_nom } : undefined,
                utilisateur: selectedUser ? {
                  id: selectedUser.id,
                  firstName: selectedUser.firstName,
                  lastName: selectedUser.lastName,
                  email: selectedUser.email,
                  matricule: selectedUser.matricule,
                  assignedRoles: selectedUser.assignedRoles,
                  service: selectedUser.service,
                } : undefined,
                projects: selectedProjects,
                sourceFinancement,
              };
              
              const locationFile = formState.locationMode === "file" ? formState.locationFile ?? undefined : undefined;
              if (formMode === "edit" && current) {
                updateBienMutation.mutate({ id: current.id, payload, preview, locationFile });
              } else {
                createBienMutation.mutate({ payload, preview, locationFile, securityModes: formState.securityModes });
              }
            }}
          />
        )}

        <AffectationDrawer
          open={drawer === "affectation"}
          bienId={current?.id ?? null}
          onOpenChange={(v) => !v && setDrawer(null)}
          onSave={() => { setDrawer(null); }}
        />
        <SortieDrawer
          open={drawer === "sortie"}
          onOpenChange={(v) => !v && setDrawer(null)}
          onSave={() => { setDrawer(null); toast.success(t("biens.toast.sortieSuccess")); }}
        />
        <MaintenanceDrawer
          open={drawer === "maintenance"}
          bienId={current?.id ?? null}
          onOpenChange={(v) => !v && setDrawer(null)}
          onSave={() => { setDrawer(null); }}
        />
        <ReevaluationDrawer
          open={drawer === "reevaluation"}
          bienId={current?.id ?? null}
          onOpenChange={(v) => !v && setDrawer(null)}
          onSave={() => { setDrawer(null); }}
        />
        <DepreciationDrawer
          open={drawer === "depreciation"}
          bienId={current?.id ?? null}
          onOpenChange={(v) => !v && setDrawer(null)}
          onSave={() => { setDrawer(null); }}
        />

      {/* Sortie directe depuis le tableau — sans ouvrir la fiche détail */}
      {sortieDirectTarget && (
        <SortieDirectDialog
          bien={sortieDirectTarget}
          onClose={() => setSortieDirectTarget(null)}
          onSaved={() => {
            setSortieDirectTarget(null);
            // Le backend a déjà basculé le statut à SORTIS — un simple
            // rechargement de la page courante suffit (le bien sorti disparaît
            // de la liste par défaut, voir le filtre statut SORTIE dans BiensList).
            queryClient.invalidateQueries({ queryKey: ["biens-list"] });
            queryClient.invalidateQueries({ queryKey: ["asset-exits"] });
          }}
        />
      )}
    </AppShell>
  );
}

  /* =========================================================
    printBien — fiche complète : bien + affectations + maintenances
                                + réévaluations + dépréciations
    ========================================================= */

  // ═══════════════════════════════════════════════════════════════════════════
  // EXPORT INVENTAIRE — PDF + Excel
  // ═══════════════════════════════════════════════════════════════════════════

  function formatValeur(v: number | null | undefined): string {
    if (v == null) return "—";
    // Formatage sans espaces insécables (problème jsPDF)
    return Number(v).toLocaleString("fr-FR").replace(/\u202f/g, " ").replace(/\u00a0/g, " ");
  }

  async function exportInventairePDF(inv: InventaireData): Promise<void> {
    const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });
    const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
    const pageW = doc.internal.pageSize.getWidth();
    const today = inv.date ?? new Date().toLocaleDateString("fr-FR");

    inv.categories.forEach((cat, catIdx) => {
      if (catIdx > 0) {
        doc.addPage("a4", "landscape");
      }

      renderHeader();
      let y = MINEPIA_PDF_HEADER_HEIGHT + 5;

      // ─── En-tête administratif ────────────────────────────────
      doc.setFontSize(10);
      doc.setFont("helvetica", "normal");
      doc.text(`Région : ${inv.region?.nom ?? "……………………"}`, 14, y);
      doc.text(`Date : ${today}`, pageW - 14, y, { align: "right" });
      y += 7;
      doc.text(`Département : ${inv.departement?.nom ?? "……………………"}`, 14, y);
      y += 7;
      doc.text(`Arrondissement : ${inv.arrondissement?.nom ?? "……………………"}`, 14, y);
      doc.text(`Structure : ${inv.service?.nom ?? "……………………"}`, pageW / 2 + 10, y);
      y += 7;
      doc.text("Localité : ……………………", 14, y);
      y += 10;

      // ─── Titre ───────────────────────────────────────────────
      doc.setFontSize(14);
      doc.setFont("helvetica", "bold");
      doc.text("INVENTAIRE DU PATRIMOINE DU MINEPIA", pageW / 2, y, { align: "center" });
      y += 8;

      doc.setFontSize(11);
      doc.setFont("helvetica", "bolditalic");
      doc.text(cat.categorie.nom.toUpperCase(), pageW / 2, y, { align: "center" });
      y += 7;

      // ─── Note légale ─────────────────────────────────────────
      doc.setFontSize(8.5);
      doc.setFont("helvetica", "italic");
      doc.text(
        "NB : La dissimulation expresse d'objets ou l'omission d'un des critères ci-dessous cités expose le contrevenant aux sanctions prévues par la loi.",
        14, y,
      );
      y += 7;

      // ─── Tableau ──────────────────────────────────────────────
      autoTable(doc, {
        startY: y,
        head: [
          [
            { content: "Informations générales", colSpan: 7, styles: { halign: "center", fontStyle: "bold", fillColor: [220, 220, 220] } },
            { content: "Détenteur", colSpan: 4, styles: { halign: "center", fontStyle: "bold", fillColor: [200, 200, 200] } },
          ],
          [
            "Type matériel (3)",
            "Valeur acquisition (FCFA)",
            "Année acquisition",
            "État (1)",
            "Observations (2)",
            "Imputation budgétaire",
            "Projet / Donateur",
            "Nom et prénom",
            "Matricule",
            "N° CNI",
            "Fonction (Durée)",
          ],
        ],
        body: cat.biens.map((b) => [
          b.type?.nom ?? "—",
          formatValeur(b.valeur),
          b.annee_acquisition ?? "—",
          b.etat ?? "—",
          b.description ?? "",
          formatValeur(b.imputation_budgetaire),
          b.projet ? `${b.projet.nom} (${b.projet.duree})` : "—",
          b.detenteur?.nom_prenom ?? "—",
          b.detenteur?.matricule ?? "—",
          b.detenteur?.numero_cni ?? "—",
          b.detenteur?.fonction ?? "—",
        ]),
        styles: {
          fontSize: 9,
          cellPadding: 3,
          overflow: "linebreak",
          valign: "middle",
        },
        headStyles: {
          fontSize: 9,
          fontStyle: "bold",
          halign: "center",
          valign: "middle",
          fillColor: [180, 180, 180],
        },
        columnStyles: {
          0: { cellWidth: 28 },
          1: { cellWidth: 24, halign: "right" },
          2: { cellWidth: 17, halign: "center" },
          3: { cellWidth: 17, halign: "center" },
          4: { cellWidth: 22 },
          5: { cellWidth: 24, halign: "right" },
          6: { cellWidth: 28 },
          7: { cellWidth: 30 },
          8: { cellWidth: 18 },
          9: { cellWidth: 18 },
          10: { cellWidth: 25 },
        },
        tableWidth: "wrap",
        margin: { top: MINEPIA_PDF_HEADER_HEIGHT + 4, left: 8, right: 8 },
        didDrawPage: () => { renderHeader(); },
      });
    });

    doc.save(`inventaire-minepia-${today}.pdf`);
  }

  function exportInventaireExcel(inv: InventaireData): void {
    const wb = XLSX.utils.book_new();
    const today = inv.date ?? new Date().toLocaleDateString("fr-FR");

    inv.categories.forEach((cat) => {
      const rows: (string | number)[][] = [];

      // En-têtes administratifs
      rows.push([`Région : ${inv.region?.nom ?? ""}`, "", "", "", "", "", "", `Date : ${today}`]);
      rows.push([`Département : ${inv.departement?.nom ?? ""}`]);
      rows.push([`Arrondissement : ${inv.arrondissement?.nom ?? ""}`, "", "", "", `Structure : ${inv.service?.nom ?? ""}`]);
      rows.push([`Localité :`]);
      rows.push([]);
      rows.push(["INVENTAIRE DU PATRIMOINE DU MINEPIA"]);
      rows.push([cat.categorie.nom.toUpperCase()]);
      rows.push([
        "NB: La dissimulation expresse d'objets ou l'omission d'un des critères ci-dessous cités expose le contrevenant aux sanctions prévues par la loi.",
      ]);
      rows.push([]);
      // Groupes de colonnes
      rows.push(["Informations générales", "", "", "", "", "", "", "Détenteur", "", "", ""]);
      // En-têtes colonnes
      rows.push([
        "Type matériel (3)",
        "Valeur acquisition (FCFA)",
        "Année acquisition",
        "État (1)",
        "Observations (2)",
        "Imputation budgétaire",
        "Projet donateur",
        "Nom et prénom",
        "Matricule",
        "N° CNI",
        "Fonction (Durée du projet)",
      ]);
      // Données
      cat.biens.forEach((b) => {
        rows.push([
          b.type?.nom ?? "",
          b.valeur ?? "",
          b.annee_acquisition ?? "",
          b.etat ?? "",
          b.description ?? "",
          b.imputation_budgetaire ?? "",
          b.projet ? `${b.projet.nom} (${b.projet.duree})` : "",
          b.detenteur?.nom_prenom ?? "",
          b.detenteur?.matricule ?? "",
          b.detenteur?.numero_cni ?? "",
          b.detenteur?.fonction ?? "",
        ]);
      });

      // Nom de feuille (max 31 chars, sans caractères spéciaux)
      const sheetName = cat.categorie.nom.slice(0, 30).replace(/[\\/?*[\]:]/g, "-");
      const ws = XLSX.utils.aoa_to_sheet(rows);
      ws["!cols"] = [
        { wch: 25 }, { wch: 20 }, { wch: 16 }, { wch: 14 }, { wch: 20 },
        { wch: 22 }, { wch: 28 }, { wch: 24 }, { wch: 14 }, { wch: 14 }, { wch: 26 },
      ];
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
    });

    XLSX.writeFile(wb, `inventaire-minepia-${today}.xlsx`);
  }

  // ─── Dialog Export Inventaire ────────────────────────────────────────────────

  interface ExportInventaireDialogProps {
    open: boolean;
    onClose: () => void;
    allCategories: Array<{ id: number; nom: string }>;
  }

  function ExportInventaireDialog({ open, onClose, allCategories }: ExportInventaireDialogProps) {
    const t = useT();
    const [selectedCatIds, setSelectedCatIds] = useState<number[]>([]);
    const [format, setFormat] = useState<"pdf" | "excel">("pdf");
    const [loading, setLoading] = useState(false);

    const toggleCat = (id: number) => {
      setSelectedCatIds((prev) =>
        prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
      );
    };

    const handleExport = async () => {
      setLoading(true);
      try {
        const catParam =
          selectedCatIds.length === 0
            ? "all"
            : selectedCatIds.join(",");
        const res = await getInventaire(catParam);
        const inv: InventaireData = res.data;
        if (!inv?.categories?.length) {
          toast.error(t("biens.inventaire.noData"));
          return;
        }
        if (format === "pdf") {
          await exportInventairePDF(inv);
        } else {
          exportInventaireExcel(inv);
        }
        toast.success(t("biens.inventaire.exportSuccess"));
        onClose();
      } catch {
        toast.error(t("biens.inventaire.loadFailed"));
      } finally {
        setLoading(false);
      }
    };

    return (
      <Dialog open={open} onOpenChange={(v) => { if (!v) onClose(); }}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ClipboardList className="h-5 w-5 text-primary" />
              {t("biens.list.exportInventories")}
            </DialogTitle>
          </DialogHeader>

          <div className="space-y-5 py-1">
            {/* Format */}
            <div className="space-y-2">
              <p className="text-sm font-semibold">{t("biens.detail.exportFormat")}</p>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setFormat("pdf")}
                  className={cn(
                    "flex flex-1 items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-colors",
                    format === "pdf"
                      ? "border-primary bg-primary/5 text-primary"
                      : "border-border hover:bg-muted",
                  )}
                >
                  <FileText className="h-4 w-4 shrink-0" /> PDF ({t("biens.inventaire.a4Paysage")})
                </button>
                <button
                  type="button"
                  onClick={() => setFormat("excel")}
                  className={cn(
                    "flex flex-1 items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-colors",
                    format === "excel"
                      ? "border-primary bg-primary/5 text-primary"
                      : "border-border hover:bg-muted",
                  )}
                >
                  <FileSpreadsheet className="h-4 w-4 shrink-0" /> Excel ({t("biens.inventaire.onglet")})
                </button>
              </div>
            </div>

            {/* Catégories */}
            <div className="space-y-2">
              <p className="text-sm font-semibold">
                {t("biens.field.categorie")}{" "}
                <span className="font-normal text-muted-foreground">
                  {t("biens.inventaire.leaveEmptyForAll")}
                </span>
              </p>
              <div className="max-h-52 overflow-y-auto rounded-lg border border-border bg-muted/30 p-3">
                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                  {allCategories.map((cat) => (
                    <label
                      key={cat.id}
                      className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                    >
                      <Checkbox
                        checked={selectedCatIds.includes(cat.id)}
                        onCheckedChange={() => toggleCat(cat.id)}
                      />
                      <span className="truncate">{cat.nom}</span>
                    </label>
                  ))}
                </div>
              </div>
              {selectedCatIds.length > 0 && (
                <p className="text-xs text-muted-foreground">
                  {t("biens.inventaire.selectedCount", { count: selectedCatIds.length })}
                </p>
              )}
            </div>
          </div>

          <DialogFooter className="gap-2">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-muted disabled:opacity-50"
            >
              {t("action.cancel")}
            </button>
            <button
              type="button"
              onClick={handleExport}
              disabled={loading}
              className="inline-flex h-9 items-center gap-2 justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
            >
              {loading ? <Loader2Icon className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
              {t("action.export")}
            </button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    );
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // FIN EXPORT INVENTAIRE
  // ═══════════════════════════════════════════════════════════════════════════

  interface PrintBienData {
    bien: ApiBien;
    affectations: ApiAffectation[];
    maintenances: ApiMaintenance[];
    reevals: ApiReevaluation[];
    deprecs: ApiDepreciation[];
  }

  // Construit le PDF de la fiche bien — utilisé à la fois pour "Imprimer"
  // (autoPrint sur un blob, aucun dialogue navigateur impliqué) et "Exporter
  // PDF" (téléchargement). Générer un vrai PDF plutôt que d'utiliser
  // window.print() sur du HTML est le seul moyen fiable d'obtenir un
  // document sans en-tête/pied de page injectés par le navigateur (date,
  // URL localhost, pagination native) : ceux-ci sont un réglage du
  // navigateur ("En-têtes et pieds de page" dans la boîte d'impression), pas
  // quelque chose qu'une page web peut désactiver via CSS/JS.
  // Dessine la fiche d'UN bien sur la page courante d'un doc jsPDF déjà créé
  // (en-tête déjà rendu par l'appelant) — factorisé pour être réutilisé aussi
  // bien pour un PDF à un seul bien (buildBienPdf) que pour un PDF multi-biens
  // regroupant plusieurs fiches en un seul document (buildMultiBienPdf).
  function drawBienFiche(doc: jsPDF, renderHeader: () => void, { bien: b, affectations, maintenances, reevals, deprecs }: PrintBienData, t: (k: Key, params?: Record<string, string | number>) => string): void {
    let cursorY = MINEPIA_PDF_HEADER_HEIGHT + 6;

    doc.setFontSize(16);
    doc.setTextColor(22, 101, 52);
    doc.text(t("biens.print.ficheTitle"), 14, cursorY);
    cursorY += 7;
    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.text(`MINEPIA - ${t("biens.print.generatedOn")} ${new Date().toLocaleDateString("fr-FR")}${b.reference ? ` — ${t("biens.list.col.reference")} : ${b.reference}` : ""}`, 14, cursorY);

    const addTable = (title: string, head: string[], body: Array<Array<string | number>>) => {
      cursorY += 8;
      doc.setFontSize(11);
      doc.setTextColor(22, 101, 52);
      doc.text(title, 14, cursorY);
      autoTable(doc, {
        startY: cursorY + 3,
        head: [head],
        body: body.length > 0 ? body : [[t("biens.detail.noRecord"), ...head.slice(1).map(() => "")]],
        theme: "grid",
        styles: { fontSize: 7.5, cellPadding: 2, overflow: "linebreak" },
        headStyles: { fillColor: [22, 101, 52], textColor: 255 },
        margin: { top: MINEPIA_PDF_HEADER_HEIGHT + 4, left: 14, right: 14 },
        didDrawPage: () => { renderHeader(); },
      });
      cursorY = (doc as jsPDF & { lastAutoTable?: { finalY: number } }).lastAutoTable?.finalY ?? cursorY + 10;
    };

    addTable(t("biens.detail.tab.general"), [t("biens.print.champ"), t("biens.print.valeur")], [
      [t("biens.list.col.reference"), b.reference || "—"],
      [t("biens.detail.field.nomBien"), b.nom || "—"],
      [t("biens.detail.field.numeroSerie"), b.numeroSerie || "—"],
      [t("biens.field.categorie"), b.category?.nom || "—"],
      [t("biens.list.col.assetType"), b.assetType?.nom || "—"],
      [t("biens.detail.field.etat"), b.etatBien?.nom || "—"],
      [t("common.status"), displayStatut(b.statut)],
      [t("users.service"), b.service?.nom || "—"],
      [t("biens.field.acquisition"), b.dateAcquisition || "—"],
      [t("biens.field.valeur"), formatFCFA(b.valeur)],
      [t("biens.list.col.sourceFinancement"), b.sourceFinancement ?? b.projects?.[0]?.nom ?? "—"],
      [t("biens.detail.fournisseur"), b.fournisseurNom || "—"],
    ]);
    addTable(t("biens.detail.tab.affectations"), [t("biens.print.debut"), t("biens.print.fin"), t("biens.detail.col.structure"), t("biens.detail.col.type"), t("biens.detail.col.responsable")], affectations.map((item) => [
      item.dateDebut || "—", item.dateFin || "—", item.service?.nom || "—", item.typeAffectation || "—",
      item.utilisateur ? `${item.utilisateur.firstName} ${item.utilisateur.lastName}` : "—",
    ]));
    addTable(t("biens.block.maintenance"), [t("biens.print.intervention"), t("biens.detail.col.recuperation"), t("biens.detail.field.etat"), t("biens.print.cout"), t("biens.detail.col.motif")], maintenances.map((item) => [
      item.dateIntervention || "—", item.dateRecuperation || "—", item.etatBien?.nom ?? item.etat ?? "—",
      formatFCFA(item.cout ?? 0), item.motif || "—",
    ]));
    addTable(t("biens.detail.tab.reevaluations"), [t("common.date"), t("biens.print.ancienneValeur"), t("biens.print.nouvelleValeur"), t("biens.detail.col.methode"), t("biens.detail.col.motif")], reevals.map((item) => [
      item.dateReevaluation || "—", formatFCFA(item.valeurActuelle ?? 0), formatFCFA(item.nouvelleValeur),
      item.methodeEvaluation || "—", item.motif || "—",
    ]));
    addTable(t("biens.detail.tab.depreciations"), [t("common.date"), t("biens.detail.col.type"), t("biens.detail.col.methode"), t("biens.detail.col.taux"), t("biens.print.montant")], deprecs.map((item) => [
      item.dateDepreciation || "—", item.typeDepreciation || "—", item.methodeAmortissement || "—",
      item.tauxDepreciation != null ? `${item.tauxDepreciation}%` : "—", formatFCFA(item.montantDepreciation ?? 0),
    ]));
  }

  // Pied de page — uniquement le numéro de page (pas de lien/adresse serveur),
  // appliqué sur toutes les pages du document une fois son contenu terminé.
  function addPageFooters(doc: jsPDF): void {
    const pageCount = doc.getNumberOfPages();
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    for (let i = 1; i <= pageCount; i++) {
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(140);
      doc.text(`Page ${i} / ${pageCount}`, pageWidth / 2, pageHeight - 8, { align: "center" });
    }
  }

  async function buildBienPdf(data: PrintBienData, t: (k: Key, params?: Record<string, string | number>) => string): Promise<{ doc: jsPDF; fileName: string }> {
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
    const fileName = `fiche-bien-${data.bien.reference || data.bien.id}.pdf`;
    renderHeader();
    drawBienFiche(doc, renderHeader, data, t);
    addPageFooters(doc);
    return { doc, fileName };
  }

  /** Regroupe les fiches de plusieurs biens dans un seul document PDF — une
   * nouvelle page par bien, chacune avec l'entête MINEPIA. */
  async function buildMultiBienPdf(dataList: PrintBienData[], t: (k: Key, params?: Record<string, string | number>) => string): Promise<{ doc: jsPDF; fileName: string }> {
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
    dataList.forEach((data, idx) => {
      if (idx > 0) doc.addPage("a4", "portrait");
      renderHeader();
      drawBienFiche(doc, renderHeader, data, t);
    });
    addPageFooters(doc);
    return { doc, fileName: `fiches-biens-${dataList.length}.pdf` };
  }

  async function exportBienPDF(data: PrintBienData, t: (k: Key, params?: Record<string, string | number>) => string): Promise<void> {
    const { doc, fileName } = await buildBienPdf(data, t);
    doc.save(fileName);
  }

  /** "Imprimer" — ouvre le PDF dans un nouvel onglet et déclenche directement
   * l'impression du visualiseur PDF natif du navigateur (pas de fenêtre HTML
   * intermédiaire ni de dialogue d'impression avec en-tête/pied de page
   * injectés par le navigateur). */
  async function printBien(data: PrintBienData, t: (k: Key, params?: Record<string, string | number>) => string): Promise<void> {
    const { doc } = await buildBienPdf(data, t);
    doc.autoPrint();
    const blobUrl = doc.output("bloburl");
    window.open(String(blobUrl), "_blank");
  }

  /** Impression groupée — un seul document réunissant la fiche de chacun des
   * biens sélectionnés. Un seul bien sélectionné → comportement inchangé
   * (délègue à printBien). */
  async function printBiensBulk(dataList: PrintBienData[], t: (k: Key, params?: Record<string, string | number>) => string): Promise<void> {
    if (dataList.length === 0) return;
    if (dataList.length === 1) {
      await printBien(dataList[0], t);
      return;
    }
    const { doc } = await buildMultiBienPdf(dataList, t);
    doc.autoPrint();
    const blobUrl = doc.output("bloburl");
    window.open(String(blobUrl), "_blank");
  }

  function exportBienExcel({ bien: b, affectations, maintenances, reevals, deprecs }: PrintBienData, t: (k: Key, params?: Record<string, string | number>) => string): void {
    const workbook = XLSX.utils.book_new();
    const appendSheet = (name: string, rows: Array<Record<string, string | number>>) => {
      XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(rows), name);
    };

    appendSheet(t("biens.detail.tab.general"), [
      { [t("biens.print.champ")]: t("biens.list.col.reference"), [t("biens.print.valeur")]: b.reference || "—" },
      { [t("biens.print.champ")]: t("biens.detail.field.nomBien"), [t("biens.print.valeur")]: b.nom || "—" },
      { [t("biens.print.champ")]: t("biens.detail.field.numeroSerie"), [t("biens.print.valeur")]: b.numeroSerie || "—" },
      { [t("biens.print.champ")]: t("biens.field.categorie"), [t("biens.print.valeur")]: b.category?.nom || "—" },
      { [t("biens.print.champ")]: t("biens.list.col.assetType"), [t("biens.print.valeur")]: b.assetType?.nom || "—" },
      { [t("biens.print.champ")]: t("biens.detail.field.etat"), [t("biens.print.valeur")]: b.etatBien?.nom || "—" },
      { [t("biens.print.champ")]: t("common.status"), [t("biens.print.valeur")]: displayStatut(b.statut) },
      { [t("biens.print.champ")]: t("users.service"), [t("biens.print.valeur")]: b.service?.nom || "—" },
      { [t("biens.print.champ")]: t("biens.field.acquisition"), [t("biens.print.valeur")]: b.dateAcquisition || "—" },
      { [t("biens.print.champ")]: t("biens.field.valeur"), [t("biens.print.valeur")]: b.valeur },
      { [t("biens.print.champ")]: t("biens.list.col.sourceFinancement"), [t("biens.print.valeur")]: b.sourceFinancement ?? b.projects?.[0]?.nom ?? "—" },
      { [t("biens.print.champ")]: t("biens.detail.fournisseur"), [t("biens.print.valeur")]: b.fournisseurNom || "—" },
    ]);
    appendSheet(t("biens.detail.tab.affectations"), affectations.map((item) => ({
      [t("biens.print.debut")]: item.dateDebut || "—", [t("biens.print.fin")]: item.dateFin || "—", [t("biens.detail.col.structure")]: item.service?.nom || "—",
      [t("biens.detail.col.type")]: item.typeAffectation || "—", [t("biens.detail.col.responsable")]: item.utilisateur ? `${item.utilisateur.firstName} ${item.utilisateur.lastName}` : "—",
    })));
    appendSheet(t("biens.block.maintenance"), maintenances.map((item) => ({
      [t("biens.print.intervention")]: item.dateIntervention || "—", [t("biens.detail.col.recuperation")]: item.dateRecuperation || "—",
      [t("biens.detail.field.etat")]: item.etatBien?.nom ?? item.etat ?? "—", [t("biens.print.cout")]: item.cout ?? 0, [t("biens.detail.col.motif")]: item.motif || "—",
    })));
    appendSheet(t("biens.detail.tab.reevaluations"), reevals.map((item) => ({
      [t("common.date")]: item.dateReevaluation || "—", [t("biens.print.ancienneValeur")]: item.valeurActuelle ?? 0,
      [t("biens.print.nouvelleValeur")]: item.nouvelleValeur, [t("biens.detail.col.methode")]: item.methodeEvaluation || "—", [t("biens.detail.col.motif")]: item.motif || "—",
    })));
    appendSheet(t("biens.detail.tab.depreciations"), deprecs.map((item) => ({
      [t("common.date")]: item.dateDepreciation || "—", [t("biens.detail.col.type")]: item.typeDepreciation || "—", [t("biens.detail.col.methode")]: item.methodeAmortissement || "—",
      [t("biens.deprecForm.tauxDepreciation")]: item.tauxDepreciation ?? 0, [t("biens.print.montant")]: item.montantDepreciation ?? 0,
    })));

    XLSX.writeFile(workbook, `fiche-bien-${b.reference || b.id}.xlsx`);
  }

  /* =========================================================
    BiensList — tableau paginé (sans données mock)
    ========================================================= */

// Le champ statut stocké côté backend ne correspond pas toujours au libellé
// affiché/normalisé côté app (voir normalizeStatut) — confirmé en lisant
// AssetRepository/AssetExitService/AssetMaintenanceService directement :
// la vraie valeur stockée est "SORTIS" (pas "SORTIE") et "EN MAINTENANCE"
// (avec un espace, pas un underscore). Le filtre serveur fait une comparaison
// exacte (statut = :statut) — envoyer la valeur affichée telle quelle ne
// matcherait jamais rien pour ces deux cas.
const STATUT_TO_API: Record<string, string> = {
  ACTIF: "ACTIF",
  SORTIE: "SORTIS",
  EN_MAINTENANCE: "EN MAINTENANCE",
  INACTIF: "INACTIF",
};

function BiensList({
  selectedIds, onSelectionChange, onOpen, onNew, onEdit, onDelete, onPrint, onPrintSelected, etatBiens, onChangeEtat, onSortir,
}: {
  selectedIds: number[];
  onSelectionChange: (ids: number[]) => void;
  onOpen: (b: ApiBien) => void;
  onNew: () => void;
  onEdit: (b: ApiBien) => void;
  onDelete: (id: number) => void;
  onPrint: (b: ApiBien) => void;
  onPrintSelected: (biens: ApiBien[]) => void | Promise<void>;
  etatBiens: ApiEtatBien[];
  onChangeEtat: (bienId: number, etatId: number, etatNom: string) => void;
  onSortir: (b: ApiBien) => void;
}) {
  const queryClient = useQueryClient();
  const t = useT();
  // Non-admin : le tableau vient de GET /asset-assignments (ne retourne que
  // les biens dont l'utilisateur connecté est encore le détenteur actuel),
  // pas de GET /assets — demande explicite (2026-08-29). Mêmes filtres,
  // même forme de données après normalisation (voir asset-assignments-list.api.ts).
  const isAdmin = useIsAdmin();
  // L'accusé de réception concerne le service/poste destinataire du bien —
  // l'administrateur voit tous les biens mais ne doit pouvoir accuser
  // réception que sur ceux affectés à SON PROPRE service (demande explicite
  // 2026-08-29) ; comparé à b.service (structure actuelle du bien).
  const { user: connectedUser } = useConnectedUser();
  const ownServiceId = connectedUser?.service?.id;
  const [query, setQuery] = useState("");
  const [fCategorie, setFCategorie] = useState("all");
  const [fStatut, setFStatut] = useState("all");
  const [fEtatBien, setFEtatBien] = useState("all");
  const [fSecurise, setFSecurise] = useState<"all" | "true" | "false">("all");
  const [fReceived, setFReceived] = useState<"all" | "true" | "false">("all");
  const [fServiceIds, setFServiceIds] = useState<number[]>([]);
  const [fProjectIds, setFProjectIds] = useState<number[]>([]);
  const [visible, setVisible] = useState<ColKey[]>(defaultVisible);
  const [page, setPage] = useState(1);
  const [inventaireOpen, setInventaireOpen] = useState(false);
  const [mapDialogOpen, setMapDialogOpen] = useState(false);
  const navigate = useNavigate();
  const [mercurialeTarget, setMercurialeTarget] = useState<ApiBien | null>(null);
  // Cible de la popup QR code (recommandation 6) — null = popup fermée.
  const [qrTarget, setQrTarget] = useState<ApiBien | null>(null);
  // Biens ciblés par la sécurisation groupée (dialog "Sécuriser") — null = dialog fermée.
  const [securisationTarget, setSecurisationTarget] = useState<ApiBien[] | null>(null);
  // Bien ciblé par la restitution (POST /assets/{id}/restituer) — null = dialog fermée.
  const [restitutionTarget, setRestitutionTarget] = useState<ApiBien | null>(null);
  const [isPrintingSelected, setIsPrintingSelected] = useState(false);

  // Recommandation 90 — fiche détenteur (biens actuellement affectés à un utilisateur).
  // bien = le bien depuis lequel le popup a été ouvert (contextBien) — on sait déjà
  // avec certitude qu'il concerne cet utilisateur, donc FicheDetenteurDialog l'inclut
  // toujours, même si la requête globale des biens échoue.
  const [detenteurTarget, setDetenteurTarget] = useState<{ id: number; nom: string; bien: ApiBien } | null>(null);
  // GET /assets (liste allégée) ne renvoie pas toujours le responsable — si absent
  // ici, on va chercher la fiche complète du bien avant de conclure qu'il n'en a pas.
  const openDetenteur = async (b: ApiBien) => {
    if (b.utilisateur) {
      setDetenteurTarget({ id: b.utilisateur.id, nom: `${b.utilisateur.firstName} ${b.utilisateur.lastName}`, bien: b });
      return;
    }
    try {
      const full = await getBienById(b.id);
      if (full.utilisateur) {
        setDetenteurTarget({ id: full.utilisateur.id, nom: `${full.utilisateur.firstName} ${full.utilisateur.lastName}`, bien: full });
      } else {
        toast.error(t("biens.error.noDetenteur"));
      }
    } catch {
      toast.error(t("biens.error.detenteurFetchFailed"));
    }
  };
  const pageSize = 10;

  // Catégories disponibles pour le dialog d'inventaire
  const { data: catsInv } = useQuery({
    queryKey: ["categories"],
    queryFn: () => listCategories({ limit: 200 }),
    staleTime: Infinity,
  });
  const allCatsForInv = useMemo(
    () => (catsInv?.data?.data ?? []).filter((c) => !c.is_delete),
    [catsInv],
  );

  // Options du filtre Source de financement — projets réels (pas dérivés des
  // biens déjà chargés, pour proposer la liste complète même hors page en cours).
  const { data: projectsForFilter } = useQuery({
    queryKey: ["projects", "filter-options"],
    queryFn: () => listProjects({ limit: 200 }),
    staleTime: 300_000,
  });
  const projectFilterOptions = (projectsForFilter?.data ?? []).map((p) => ({ value: p.id, label: p.nom }));

  // ── Pagination + filtres côté SERVEUR (GET /assets) ────────────────────────
  // Remplace l'ancien filtrage 100% client sur un lot fixe de 200 biens, qui
  // ne fonctionnait plus correctement au-delà de 200 biens au total. Tous les
  // filtres ci-dessous sont confirmés supportés par le backend en lisant
  // directement ListAssetsController.php/AssetRepository.php — sauf État du
  // bien, sans équivalent serveur (voir plus bas).
  const categoryIdForFilter = fCategorie !== "all" ? allCatsForInv.find((c) => c.nom === fCategorie)?.id : undefined;
  const statutForFilter = fStatut !== "all" ? (STATUT_TO_API[fStatut] ?? fStatut) : undefined;
  const securiseForFilter = fSecurise !== "all" ? fSecurise === "true" : undefined;
  const receivedForFilter = fReceived !== "all" ? fReceived === "true" : undefined;
  const serviceIdForFilter = fServiceIds.length > 0 ? fServiceIds.join(",") : undefined;
  const projectIdForFilter = fProjectIds.length > 0 ? fProjectIds.join(",") : undefined;

  // Recherche débouncée — évite un appel réseau à chaque frappe.
  const [debouncedQuery, setDebouncedQuery] = useState("");
  useEffect(() => {
    const h = setTimeout(() => setDebouncedQuery(query.trim()), 300);
    return () => clearTimeout(h);
  }, [query]);
  const searchForFilter = debouncedQuery || undefined;

  // État du bien : aucun paramètre serveur équivalent (confirmé dans
  // ListAssetsController.php) — quand ce filtre est actif, on charge un lot
  // plus large (avec tous les AUTRES filtres déjà appliqués côté serveur) et
  // on filtre/pagine ce lot côté client, même technique que la page Logs.
  const hasEtatFilter = fEtatBien !== "all";
  const ETAT_BATCH_SIZE = 500;

  const listQuery = useQuery({
    queryKey: [
      "biens-list",
      isAdmin,
      hasEtatFilter ? "batch" : page,
      pageSize,
      searchForFilter,
      categoryIdForFilter,
      statutForFilter,
      securiseForFilter,
      receivedForFilter,
      serviceIdForFilter,
      projectIdForFilter,
      hasEtatFilter,
    ],
    queryFn: () =>
      (isAdmin ? listBiensPage : listAssetAssignmentsPage)({
        page: hasEtatFilter ? 1 : page,
        limit: hasEtatFilter ? ETAT_BATCH_SIZE : pageSize,
        search: searchForFilter,
        category_id: categoryIdForFilter,
        statut: statutForFilter,
        securise: securiseForFilter,
        received: receivedForFilter,
        service_id: serviceIdForFilter,
        project_id: projectIdForFilter,
      }),
    placeholderData: (prev) => prev,
  });

  // Enrichissement complémentaire (exercice/matricule depuis les caches
  // projets/utilisateurs) — même logique que l'ancien allBiens de BiensPage,
  // rapatriée ici puisque BiensList charge maintenant ses propres données.
  const { data: projectsCacheForEnrich } = useQuery({
    queryKey: ["projects"],
    queryFn: () => listProjects({ limit: 200 }),
    staleTime: 300_000,
    refetchOnWindowFocus: false,
  });
  const projectsMapForEnrich = useMemo(() => {
    const m = new Map<number, { nom: string; exercice: number | string }>();
    (projectsCacheForEnrich?.data ?? []).forEach((p) => m.set(p.id, { nom: p.nom, exercice: p.exercice }));
    return m;
  }, [projectsCacheForEnrich]);
  const { data: usersCacheForEnrich = [] } = useQuery({
    queryKey: ["users-all"],
    queryFn: listAllUsers,
    staleTime: 300_000,
    refetchOnWindowFocus: false,
  });
  const usersMapForEnrich = useMemo(() => new Map(usersCacheForEnrich.map((u) => [u.id, u])), [usersCacheForEnrich]);

  const rawBatch: ApiBien[] = useMemo(() => (listQuery.data?.data ?? []).map((b) => {
    const projId = b.projects?.[0]?.id;
    const project = projId ? projectsMapForEnrich.get(projId) : undefined;
    const user = b.utilisateur?.id ? usersMapForEnrich.get(b.utilisateur.id) : undefined;
    return {
      ...b,
      sourceFinancement: b.sourceFinancement ?? project?.nom,
      exercice: Number(b.exercice) > 0 ? b.exercice : project?.exercice,
      utilisateur: b.utilisateur
        ? { ...b.utilisateur, matricule: b.utilisateur.matricule ?? user?.matricule }
        : undefined,
    };
  }), [listQuery.data, projectsMapForEnrich, usersMapForEnrich]);

  // Sous-filtre client État du bien, uniquement quand actif — voir plus haut.
  const etatFiltered = useMemo(
    () => (hasEtatFilter ? rawBatch.filter((b) => b.etatBien?.nom === fEtatBien) : rawBatch),
    [rawBatch, hasEtatFilter, fEtatBien],
  );

  const total = hasEtatFilter ? etatFiltered.length : (listQuery.data?.meta?.total_items ?? 0);
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const currentPage = Math.min(page, totalPages);
  const paged = hasEtatFilter
    ? etatFiltered.slice((currentPage - 1) * pageSize, currentPage * pageSize)
    : etatFiltered;
  const loading = listQuery.isLoading;
  // Rechargement en arrière-plan (changement de page/filtre) — grâce à
  // placeholderData, l'ancienne page reste affichée (figée) pendant ce temps
  // au lieu de disparaître ; un spinner overlay indique juste qu'un
  // chargement est en cours, sans faire clignoter le tableau.
  const isRefetching = listQuery.isFetching && !listQuery.isLoading;
  const error = listQuery.isError;
  const errorMessage = (listQuery.error as { response?: { data?: { message?: string } } })?.response?.data?.message;
  // "data" = la page actuellement affichée — nom conservé pour ne pas devoir
  // renommer toutes les références existantes plus bas dans ce composant.
  const data = paged;

  // Réinitialise la sélection à chaque changement de page ou de filtre — la
  // sélection ne peut porter que sur la page actuellement affichée (les
  // objets bien complets des autres pages ne sont plus en mémoire avec la
  // pagination serveur, contrairement à l'ancien chargement complet à 200).
  useEffect(() => {
    onSelectionChange([]);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentPage, categoryIdForFilter, statutForFilter, securiseForFilter, receivedForFilter, serviceIdForFilter, projectIdForFilter, hasEtatFilter, fEtatBien, searchForFilter]);

  // Bien pas encore accusé réception par le détenteur actuel — toutes les
  // actions sont bloquées à l'exception de l'accusé de réception et des
  // impressions (bordereau/fiche). Pour un admin, ne s'applique que si le
  // bien est affecté à son propre service (voir plus haut, même règle que
  // dans le rendu des lignes). Factorisée ici pour être réutilisable par la
  // sélection (checkbox + barre d'actions groupées).
  const isBienNotReceived = (b: ApiBien) =>
    b.received === false && (isAdmin ? b.service?.id === ownServiceId : true);

  // Bouton "Sécuriser" grisé si au moins un bien sélectionné est déjà sécurisé
  // OU pas encore accusé réception (action interdite tant que non accusé —
  // seuls Accusé de réception / Bordereau / Impression restent disponibles).
  // Champ direct GET /assets → bien.securise (plus fiable qu'un détour par
  // GET /securities + recherche dans assets[] de chaque enregistrement).
  const selectedBiens = useMemo(() => data.filter((b) => new Set(selectedIds).has(b.id)), [data, selectedIds]);
  const hasNotReceivedSelected = selectedBiens.some(isBienNotReceived);
  const hasAlreadySecurised = selectedBiens.some((b) => b.securise) || hasNotReceivedSelected;
  const securiserTooltip = hasNotReceivedSelected
    ? t("biens.tooltip.acknowledgeBeforeSecure")
    : hasAlreadySecurised
      ? t("biens.tooltip.alreadySecured")
      : undefined;

  // Dialog demande de réforme
  const [reformeTarget, setReformeTarget] = useState<ApiBien | null>(null);

  // États autorisés par type de bien — via GET /asset-types/{id}/etat-biens,
  // la même source que le formulaire de création (l'ancien filtrage client
  // sur etatBiens[].assetTypes se fiait à une association pas toujours
  // renseignée côté backend et retombait alors sur la liste complète, non
  // filtrée — le bug initialement signalé). useQueries : un type de bien
  // peut apparaître sur plusieurs lignes, on ne veut qu'un appel par type
  // distinct présent sur la page, pas un hook par ligne (interdit) ni un
  // appel par ligne (redondant).
  const distinctAssetTypeIds = useMemo(
    () => Array.from(new Set(data.map((b) => b.assetType?.id).filter((id): id is number => id != null))),
    [data],
  );
  const etatTypeQueries = useQueries({
    queries: distinctAssetTypeIds.map((id) => ({
      queryKey: ["asset-type-etat-biens", id],
      queryFn: () => getEtatBiensForAssetType(id),
      staleTime: 300_000,
    })),
  });
  const allowedEtatIdsByAssetType = useMemo(() => {
    const map = new Map<number, Set<number>>();
    distinctAssetTypeIds.forEach((id, i) => {
      const ids = etatTypeQueries[i]?.data?.data?.map((e) => e.id);
      if (ids) map.set(id, new Set(ids));
    });
    return map;
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [distinctAssetTypeIds, etatTypeQueries]);

  // Accusé de réception — un appel par bien affiché sur la page (pas par
  // ligne visible dans le rendu, ni pour toute la liste : juste les 10 de la
  // page courante). L'icône "reçu" (colonne nom, plus bas) lit directement
  // b.received — un champ déjà renvoyé par GET /assets (voir
  // AssetResponseBuilder::resolveCurrentReceived côté backend, confirmé en
  // lisant le code) — plus besoin d'un appel /affectations par bien affiché
  // rien que pour cette icône, contrairement à l'ancienne implémentation.
  //
  // Prend une liste de BIENS (pas d'affectations) — la sélection peut couvrir
  // plusieurs pages : on résout l'affectation actuelle de chaque bien à la
  // demande plutôt que de dépendre d'un cache page-courante.
  const acknowledgeMutation = useMutation({
    mutationFn: async (biens: ApiBien[]) => {
      const errors: string[] = [];
      let successCount = 0;
      for (const b of biens) {
        try {
          const affectations = await listAffectations(b.id);
          const current = [...affectations].sort((x, y) => (y.dateDebut || "").localeCompare(x.dateDebut || ""))[0];
          if (!current) { errors.push(`${b.nom} : ${t("biens.error.noAffectation")}`); continue; }
          if (current.accuseReception?.effectue) { errors.push(`${b.nom} : ${t("biens.error.alreadyAcknowledged")}`); continue; }
          await acknowledgeAffectation(current.id);
          successCount++;
        } catch (err: unknown) {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          errors.push(`${b.nom} : ${msg ?? t("biens.error.generic")}`);
        }
      }
      return { successCount, errors };
    },
    onSuccess: ({ successCount, errors }) => {
      queryClient.invalidateQueries({ queryKey: ["affectations"] });
      if (errors.length === 0) {
        toast.success(t("biens.toast.acknowledgeSuccess", { count: successCount }));
      } else if (successCount > 0) {
        toast.error(t("biens.toast.acknowledgePartial", { success: successCount, failed: errors.length, errors: errors.join(" ; ") }));
      } else {
        toast.error(t("biens.toast.acknowledgeFailed", { errors: errors.join(" ; ") }));
      }
      onSelectionChange([]);
    },
  });

  const restituerMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: RestituerBienPayload }) =>
      restituerBien(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["biens-list"] });
      queryClient.invalidateQueries({ queryKey: ["affectations"] });
      toast.success(t("biens.toast.restitutionSuccess"));
      setRestitutionTarget(null);
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("biens.error.generic"));
    },
  });

  // Colonnes avec override de etatBien pour afficher un select inline
  const dynamicColumns = allColumns.map((c) => {
    if (c.key === "nom") {
      return {
        ...c,
        render: (b: ApiBien) => (
          <span className="inline-flex items-center gap-1.5">
            {c.render(b, { secured: t("biens.secure.secured"), notSecured: t("biens.secure.notSecured") })}
            {/* Uniquement une confirmation positive (accusé effectué) —
                pas d'indicateur pour "non accusé", qui concernerait la
                quasi-totalité des lignes (affectation initiale à la
                création) et n'apporterait donc aucune information utile. */}
            {b.received === true && (
              <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-600" aria-label={t("biens.list.receptionAcknowledged")} />
            )}
          </span>
        ),
      };
    }
    if (c.key !== "etatBien" || etatBiens.length === 0) return c;
    return {
      ...c,
      render: (b: ApiBien) => {
        const currentEtat = etatBiens.find((e) => e.id === b.etatBien?.id);
        // Restreint aux états autorisés pour le type de bien enregistré ;
        // l'état actuel reste toujours présent même s'il n'est plus autorisé
        // (pour ne pas faire disparaître la valeur affichée du trigger).
        const allowedIds = b.assetType?.id ? allowedEtatIdsByAssetType.get(b.assetType.id) : undefined;
        const etatsDisponibles = allowedIds
          ? etatBiens.filter((e) => allowedIds.has(e.id) || e.id === b.etatBien?.id)
          : etatBiens;
        return (
          <Select
            value={b.etatBien?.id ? String(b.etatBien.id) : ""}
            onValueChange={(v) => {
              const etat = etatBiens.find((e) => e.id === Number(v));
              if (!etat) return;
              if (/réform/i.test(etat.nom)) {
                // Ouvre le formulaire de demande de réforme
                setReformeTarget(b);
                return;
              }
              // Recommandation 4 : l'état ne peut que se dégrader — un bien
              // passé de "Neuf" à "Bon" ne peut plus revenir à "Neuf".
              // L'ordre est donné par numeroOrdre (croissant = dégradation).
              if (
                currentEtat?.numeroOrdre != null &&
                etat.numeroOrdre != null &&
                etat.numeroOrdre < currentEtat.numeroOrdre
              ) {
                toast.error(`L'état d'un bien ne peut que se dégrader — impossible de revenir à "${etat.nom}".`);
                return;
              }
              onChangeEtat(b.id, etat.id, etat.nom);
            }}
          >
            {/* Recommandation 5 : hover vert sur l'état actuel (trigger) */}
            <SelectTrigger
              className="h-7 min-w-[130px] border-none bg-transparent p-0 text-xs shadow-none focus:ring-0 focus:ring-offset-0 rounded px-2 text-green-700 hover:bg-green-100"
              onClick={(e) => e.stopPropagation()}
            >
              <SelectValue placeholder="—" />
            </SelectTrigger>
            <SelectContent onClick={(e) => e.stopPropagation()}>
              {currentEtat && currentEtat.numeroOrdre == null && (
                <p className="px-2 py-1.5 text-[10px] leading-snug text-amber-600">
                  Numéro d'ordre non configuré pour "{currentEtat.nom}" — le verrouillage des états
                  précédents est désactivé pour ce bien. Configurez-le dans Administration &gt; États des biens.
                </p>
              )}
              {[...etatsDisponibles]
                .sort((a, e2) => (a.numeroOrdre ?? Number.MAX_SAFE_INTEGER) - (e2.numeroOrdre ?? Number.MAX_SAFE_INTEGER))
                .map((e) => {
                const isCurrent = e.id === b.etatBien?.id;
                // États "meilleurs" que l'état actuel → non sélectionnables
                const bloquant =
                  !isCurrent &&
                  currentEtat?.numeroOrdre != null &&
                  e.numeroOrdre != null &&
                  e.numeroOrdre < currentEtat.numeroOrdre;
                return (
                  <SelectItem
                    key={e.id}
                    value={String(e.id)}
                    disabled={bloquant && !/réform/i.test(e.nom)}
                    className={cn(
                      "text-xs focus:text-white",
                      // Recommandation 5 : hover vert pour l'état actuel,
                      // hover vert foncé pour les autres états.
                      isCurrent ? "font-semibold text-green-700 focus:bg-green-500" : "focus:bg-green-800",
                    )}
                  >
                    {e.nom}
                  </SelectItem>
                );
              })}
            </SelectContent>
          </Select>
        );
      },
    };
  });

    const cols = dynamicColumns.filter((c) => visible.includes(c.key));

    const selectedSet = new Set(selectedIds);
    const allPagedSelected = paged.length > 0 && paged.every((r) => selectedSet.has(r.id));
    const somePagedSelected = paged.some((r) => selectedSet.has(r.id)) && !allPagedSelected;
    const togglePage = () => {
      const ids = paged.map((r) => r.id);
      if (allPagedSelected) onSelectionChange(selectedIds.filter((id) => !ids.includes(id)));
      else onSelectionChange(Array.from(new Set([...selectedIds, ...ids])));
    };

    const pageNumbers = pageList(currentPage, totalPages);
    const exportColumns: ExportColumn<ApiBien>[] = dynamicColumns
      .filter((column) => visible.includes(column.key))
      .map((column) => ({
        key: column.key,
        label: t(columnLabelKeys[column.key]),
        format: (bien) => {
          switch (column.key) {
            case "categorie": return bien.category?.nom ?? "";
            case "assetType": return bien.assetType?.nom ?? "";
            case "service": return bien.service?.nom ?? "";
            case "matricule": return bien.utilisateur?.matricule ?? "";
            case "sourceFinancement": return bien.sourceFinancement ?? bien.projects?.[0]?.nom ?? "";
            case "etatBien": return bien.etatBien?.nom ?? "";
            case "valeur": return formatFCFA(bien.valeur ?? 0);
            default: return String(bien[column.key as keyof ApiBien] ?? "");
          }
        },
      }));

    // L'export doit couvrir TOUS les biens correspondant aux filtres, pas
    // seulement la page affichée — requête dédiée, plus large, découplée de
    // la pagination de l'écran (voir data ci-dessus, limitée à pageSize).
    const { data: exportBatch } = useQuery({
      queryKey: ["biens-export", isAdmin, searchForFilter, categoryIdForFilter, statutForFilter, securiseForFilter, receivedForFilter, serviceIdForFilter, projectIdForFilter],
      queryFn: () =>
        (isAdmin ? listBiensPage : listAssetAssignmentsPage)({
          page: 1,
          limit: 1000,
          search: searchForFilter,
          category_id: categoryIdForFilter,
          statut: statutForFilter,
          securise: securiseForFilter,
          received: receivedForFilter,
          service_id: serviceIdForFilter,
          project_id: projectIdForFilter,
        }),
      staleTime: 30_000,
    });
    const exportData = useMemo(() => {
      const items = exportBatch?.data ?? [];
      return hasEtatFilter ? items.filter((b) => b.etatBien?.nom === fEtatBien) : items;
    }, [exportBatch, hasEtatFilter, fEtatBien]);

    const { exportCSV: exportBiensCSV, exportXLSX: exportBiensXLSX, exportPDF: exportBiensPDF } = useExportActions({
      data: exportData,
      columns: exportColumns,
      filename: "biens-minepia",
      title: t("biens.export.title"),
    });

    const printBordereau = (biensOverride?: ApiBien[]) => {
      const selectedBiens = biensOverride ?? data.filter((bien) => selectedSet.has(bien.id));
      if (selectedBiens.length === 0) return;
      const escapeHtml = (value: unknown) => String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
      const printWindow = window.open("", "_blank", "width=1100,height=750");
      if (!printWindow) {
        toast.error(t("biens.print.popupBlocked"));
        return;
      }
      const rows = selectedBiens.map((bien, index) => `
        <tr>
          <td>${index + 1}</td><td>${escapeHtml(bien.reference)}</td><td>${escapeHtml(bien.nom)}</td>
          <td>${escapeHtml(bien.category?.nom)}</td><td>${escapeHtml(bien.service?.nom)}</td>
          <td>${escapeHtml(bien.utilisateur?.matricule)}</td>
          <td>${escapeHtml(bien.sourceFinancement ?? bien.projects?.[0]?.nom)}</td>
          <td>${escapeHtml(bien.exercice)}</td><td>${escapeHtml(formatFCFA(bien.valeur))}</td>
        </tr>`).join("");
      printWindow.document.write(`<!doctype html><html><head><title>${t("biens.print.bordereauTitle")}</title><style>
        ${MINEPIA_PRINT_HEADER_CSS}
        body{font-family:Arial,sans-serif;color:#172018;margin:28px}h1{font-size:20px;margin:0 0 4px}
        p{font-size:12px;color:#55605a;margin:0 0 18px}table{width:100%;border-collapse:collapse;font-size:10px}
        th,td{border:1px solid #b8c2ba;padding:6px;text-align:left;vertical-align:top}th{background:#e8f1e9}
        .signatures{display:flex;justify-content:space-between;margin-top:55px;font-size:12px}.signature{width:32%;text-align:center}
        @media print{body{margin:12mm}}
      </style></head><body>${minepiaPrintHeaderHtml()}<h1>${t("biens.print.bordereauTitle")}</h1>
      <p>${t("biens.print.bordereauCount", { count: selectedBiens.length })} - ${t("biens.print.generatedOn")} ${new Date().toLocaleString("fr-FR")}</p>
      <table><thead><tr><th>N°</th><th>${t("biens.list.col.reference")}</th><th>${t("biens.field.designation")}</th><th>${t("biens.field.categorie")}</th><th>${t("biens.print.structure")}</th><th>${t("biens.list.col.matricule")}</th><th>${t("biens.print.source")}</th><th>${t("biens.list.col.exercice")}</th><th>${t("biens.list.col.valeurFcfa")}</th></tr></thead>
      <tbody>${rows}</tbody></table><div class="signatures"><div class="signature">${t("biens.print.issuer")}<br><br><br>${t("biens.print.signature")}</div><div class="signature">${t("biens.print.receiver")}<br><br><br>${t("biens.print.signature")}</div><div class="signature">${t("biens.print.visa")}<br><br><br>${t("biens.print.signature")}</div></div>
      <script>window.onload=()=>{window.print();}</script></body></html>`);
      printWindow.document.close();
    };

    // ── Sécurisation groupée ────────────────────────────────────────────────
    // Chaque bien a son propre formulaire dans SecurisationDialog (rec. 410),
    // avec sa propre carte affichée ou non selon sa catégorie — plus besoin
    // d'interdire de mélanger Bâtiment/Terrain et autres catégories dans une
    // même sélection, chaque bloc gère déjà ça indépendamment.
    const openSecurisation = () => {
      const selectedBiens = data.filter((bien) => selectedSet.has(bien.id));
      if (selectedBiens.length === 0) return;
      setSecurisationTarget(selectedBiens);
    };

    return (
      <div className="space-y-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={query}
              onChange={(e) => { setQuery(e.target.value); setPage(1); }}
              placeholder={t("biens.list.searchPlaceholder")}
              className="h-11 pl-9"
            />
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button onClick={onNew} className="h-11 gap-2 bg-primary text-primary-foreground hover:bg-primary/90">
              <Plus className="h-4 w-4" /> {t("biens.add")}
            </Button>
            <Button variant="outline" className="h-11 gap-2" onClick={() => navigate("/biens/amortissements")}>
              <TrendingDown className="h-4 w-4" /> {t("biens.amortissement")}
            </Button>
            <Button variant="outline" className="h-11 gap-2" onClick={() => navigate("/biens/maintenances-en-cours")}>
              <Wrench className="h-4 w-4" /> {t("biens.list.needsMaintenance")}
            </Button>
            <Button variant="outline" className="h-11 gap-2" onClick={() => setMapDialogOpen(true)}>
              <MapIcon className="h-4 w-4" /> {t("biens.list.viewOnMap")}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="h-11 gap-2">
                  <Download className="h-4 w-4" /> {t("biens.list.exportChoice")}
                  <ChevronDown className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>{t("biens.list.exportChoice")}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuSub>
                  <DropdownMenuSubTrigger className="gap-2">
                    <Download className="h-4 w-4" /> {t("biens.list.exportAssets")}
                  </DropdownMenuSubTrigger>
                  <DropdownMenuPortal>
                    <DropdownMenuSubContent>
                      <DropdownMenuItem onClick={exportBiensPDF} disabled={exportData.length === 0} className="gap-2">
                        <FileText className="h-4 w-4" /> PDF
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={exportBiensCSV} disabled={exportData.length === 0} className="gap-2">
                        <FileSpreadsheet className="h-4 w-4" /> CSV
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={exportBiensXLSX} disabled={exportData.length === 0} className="gap-2">
                        <FileSpreadsheet className="h-4 w-4" /> Excel
                      </DropdownMenuItem>
                    </DropdownMenuSubContent>
                  </DropdownMenuPortal>
                </DropdownMenuSub>
                <DropdownMenuItem onClick={() => setInventaireOpen(true)} className="gap-2">
                  <ClipboardList className="h-4 w-4" /> {t("biens.list.exportInventories")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {/* Dialog export inventaire */}
        <ExportInventaireDialog
          open={inventaireOpen}
          onClose={() => setInventaireOpen(false)}
          allCategories={allCatsForInv}
        />

        {/* Dialog carte — tous les biens localisés */}
        <AllBiensLocationsDialog
          open={mapDialogOpen}
          onClose={() => setMapDialogOpen(false)}
          biens={data}
        />


        {/* Fenêtre mercuriale (iframe, 75% de l'écran) */}
        <MercurialeIframeDialog
          url={mercurialeTarget ? mercurialeUrl(mercurialeTarget.nom) : null}
          nom={mercurialeTarget?.nom}
          onClose={() => setMercurialeTarget(null)}
        />

        {/* Popup QR code (recommandation 6) — encode les détails du bien. */}
        <QrCodeDialog
          bien={qrTarget}
          onClose={() => setQrTarget(null)}
        />

        {/* Recommandation 90 — fiche détenteur */}
        <FicheDetenteurDialog
          userId={detenteurTarget?.id ?? null}
          userName={detenteurTarget?.nom ?? ""}
          open={detenteurTarget !== null}
          onClose={() => setDetenteurTarget(null)}
          contextBien={detenteurTarget?.bien}
        />

        {/* Dialog sécurisation groupée — sélection multiple depuis le tableau */}
        {securisationTarget && (
          <SecurisationDialog
            biens={securisationTarget}
            onClose={() => setSecurisationTarget(null)}
            onSaved={() => {
              setSecurisationTarget(null);
              onSelectionChange([]);
            }}
          />
        )}

        {/* Dialog restitution — POST /assets/{id}/restituer */}
        {restitutionTarget && (
          <RestitutionDialog
            bien={restitutionTarget}
            isSaving={restituerMutation.isPending}
            onClose={() => setRestitutionTarget(null)}
            onSave={(payload) => restituerMutation.mutate({ id: restitutionTarget.id, payload })}
          />
        )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="flex flex-col gap-1.5">
          <Label className="text-xs font-medium text-muted-foreground">{t("biens.field.categorie")}</Label>
          <SearchableSelect
            value={fCategorie !== "all" ? fCategorie : null}
            onChange={(v) => { setFCategorie(v ?? "all"); setPage(1); }}
            options={allCatsForInv.map((c) => ({ value: c.nom, label: c.nom }))}
            placeholder={t("biens.list.filter.allCategories")}
            searchPlaceholder={t("biens.list.filter.searchCategory")}
          />
        </div>
        <FilterSelect
          label={t("common.status")}
          value={fStatut}
          onChange={(v) => { setFStatut(v); setPage(1); }}
          options={[{ value: "all", label: t("biens.list.filter.allStatuts") }, ...statutsBien.map((s) => ({ value: s, label: displayStatut(s) }))]}
        />
        <div className="flex flex-col gap-1.5">
          <Label className="text-xs font-medium text-muted-foreground">{t("biens.list.col.etatBien")}</Label>
          <SearchableSelect
            value={fEtatBien !== "all" ? fEtatBien : null}
            onChange={(v) => { setFEtatBien(v ?? "all"); setPage(1); }}
            options={etatBiens.map((e) => ({ value: e.nom, label: e.nom }))}
            placeholder={t("biens.list.filter.allEtats")}
            searchPlaceholder={t("biens.list.filter.searchEtat")}
          />
        </div>
        <FilterSelect
          label={t("biens.list.filter.securisation")}
          value={fSecurise}
          onChange={(v) => { setFSecurise(v as "all" | "true" | "false"); setPage(1); }}
          options={[
            { value: "all", label: t("common.all") },
            { value: "true", label: t("biens.list.filter.secured") },
            { value: "false", label: t("biens.list.filter.notSecured") },
          ]}
        />
        <FilterSelect
          label={t("biens.list.receptionAcknowledged")}
          value={fReceived}
          onChange={(v) => { setFReceived(v as "all" | "true" | "false"); setPage(1); }}
          options={[
            { value: "all", label: t("common.all") },
            { value: "true", label: t("biens.list.filter.received") },
            { value: "false", label: t("biens.list.filter.notReceived") },
          ]}
        />
        <div className="flex flex-col gap-1.5">
          <Label className="text-xs font-medium text-muted-foreground">{t("biens.list.col.serviceActuel")}</Label>
          <OrgTreeMultiSelect
            value={fServiceIds}
            onChange={(ids) => { setFServiceIds(ids); setPage(1); }}
            placeholder={t("biens.list.filter.allServices")}
            searchPlaceholder={t("biens.list.filter.searchStructure")}
            deferApply
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label className="text-xs font-medium text-muted-foreground">{t("biens.list.col.sourceFinancement")}</Label>
          <SearchableMultiSelect
            options={projectFilterOptions}
            value={fProjectIds}
            onChange={(ids) => { setFProjectIds(ids); setPage(1); }}
            placeholder={t("biens.list.filter.allSources")}
            deferApply
          />
        </div>
        <div className="col-span-1 flex flex-col justify-end">
          <span className="mb-1.5 block h-4" />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" className="h-10 justify-between gap-2">
                <span className="inline-flex items-center gap-2"><ColumnsIcon className="h-4 w-4" /> {t("biens.list.columns")}</span>
                <ChevronDown className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>{t("biens.list.visibleColumns")}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {allColumns.map((c) => (
                <DropdownMenuCheckboxItem
                  key={c.key}
                  checked={visible.includes(c.key)}
                  onCheckedChange={() => setVisible((v) => v.includes(c.key) ? v.filter((x) => x !== c.key) : [...v, c.key])}
                  onSelect={(e) => e.preventDefault()}
                >
                  {t(columnLabelKeys[c.key])}
                </DropdownMenuCheckboxItem>
              ))}
              <DropdownMenuSeparator />
              <DropdownMenuItem onSelect={() => setVisible(defaultVisible)}>{t("action.reset")}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

        {selectedIds.length > 0 && (
          <div className="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
            <span className="text-sm font-semibold text-primary">
              {t("biens.list.selectedCount", { count: selectedIds.length })}
            </span>
            {/* <Button size="sm" variant="outline" className="h-9 gap-2" onClick={() => toast.success("Sélection exportée")}>
              <Download className="h-4 w-4" /> Exporter
            </Button> */}
            <Button size="sm" variant="outline" className="h-9 gap-2" onClick={() => printBordereau()}>
              <Printer className="h-4 w-4" /> {t("biens.print.bordereau")}
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-9 gap-2"
              disabled={isPrintingSelected}
              onClick={async () => {
                setIsPrintingSelected(true);
                try {
                  await onPrintSelected(selectedBiens);
                } finally {
                  setIsPrintingSelected(false);
                }
              }}
            >
              {isPrintingSelected ? <Loader2Icon className="h-4 w-4 animate-spin" /> : <Printer className="h-4 w-4" />}
              {selectedIds.length > 1 ? t("biens.print.printFiches") : t("biens.print.printFiche")}
            </Button>
            <Button size="sm" variant="outline" className="h-9 gap-2" onClick={openSecurisation}
              disabled={hasAlreadySecurised}
              title={securiserTooltip}
            >
              <ShieldCheck className="h-4 w-4" /> {t("biens.list.secure")}
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-9 gap-2"
              disabled={acknowledgeMutation.isPending}
              onClick={() => acknowledgeMutation.mutate(selectedBiens)}
            >
              {acknowledgeMutation.isPending ? <Loader2Icon className="h-4 w-4 animate-spin" /> : <PackageCheck className="h-4 w-4" />}
              {t("biens.list.receptionAcknowledged")}
            </Button>
            <button
              type="button"
              onClick={() => onSelectionChange([])}
              className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
              aria-label={t("biens.list.cancelSelection")}
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        <div className="relative rounded-xl border border-border bg-card shadow-sm">
          {isRefetching && (
            <div className="absolute inset-0 z-10 flex items-start justify-center bg-background/60 pt-10">
              <Loader2Icon className="h-6 w-6 animate-spin text-primary" />
            </div>
          )}
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                  <th className="w-10 px-3 py-3">
                    <input
                      type="checkbox"
                      checked={allPagedSelected}
                      ref={(el) => { if (el) el.indeterminate = somePagedSelected; }}
                      onChange={togglePage}
                      className="h-4 w-4 cursor-pointer accent-primary"
                      aria-label={t("biens.list.selectAll")}
                    />
                  </th>
                  {cols.map((c) => (
                    <th key={c.key} className={cn("whitespace-nowrap px-3 py-3 text-left font-semibold", c.className)}>{t(columnLabelKeys[c.key])}</th>
                  ))}
                  <th className="w-16 px-3 py-3 text-right font-semibold">{t("common.actions")}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  // Skeleton — 8 lignes simulées pour éviter le saut visuel
                  Array.from({ length: 8 }).map((_, i) => (
                    <tr key={i} className="border-t border-border">
                      <td className="px-3 py-3"><div className="h-4 w-4 animate-pulse rounded bg-muted" /></td>
                      {cols.map((c) => (
                        <td key={c.key} className="px-3 py-3">
                          <div className={`h-3.5 animate-pulse rounded bg-muted ${i % 3 === 0 ? "w-3/4" : i % 3 === 1 ? "w-1/2" : "w-2/3"}`} />
                        </td>
                      ))}
                      <td className="px-3 py-3"><div className="ml-auto h-8 w-8 animate-pulse rounded bg-muted" /></td>
                    </tr>
                  ))
                ) : error ? (
                  <tr>
                    <td colSpan={cols.length + 2} className="px-4 py-12 text-center">
                      <div className="flex flex-col items-center gap-2">
                        <p className="text-sm font-semibold text-destructive">{t("biens.list.loadError")}</p>
                        <p className="text-xs text-muted-foreground max-w-md">{t("biens.list.loadErrorDesc")}</p>
                        {errorMessage && (
                          <p className="text-xs font-mono bg-muted rounded px-2 py-1 text-muted-foreground">{errorMessage}</p>
                        )}
                      </div>
                    </td>
                  </tr>
                ) : paged.length === 0 ? (
                  <tr><td colSpan={cols.length + 2} className="px-4 py-12 text-center text-sm text-muted-foreground">{t("biens.list.empty")}</td></tr>
                ) : paged.map((b, idx) => {
                  const sel = selectedSet.has(b.id);
                  // Un bien sorti (statut SORTIE — inclut les biens réformés,
                  // voir ReformeRequestDialog) n'est plus actionnable : toutes
                  // les actions (modifier, sortir, supprimer...) sont masquées.
                  // Ne concerne que la vue filtrée explicitement sur ce statut,
                  // puisque ces biens sont exclus du tableau par défaut.
                  const isSortie = normalizeStatut(b.statut) === "SORTIE";
                  // Bien pas encore accusé réception — voir isBienNotReceived
                  // ci-dessus. La case à cocher reste sélectionnable (pas
                  // disabled) : la sélection groupée sert justement à accuser
                  // réception en masse (bouton "Accusé de réception" de la
                  // barre d'actions) — seules les AUTRES actions groupées
                  // (Sécuriser...) sont bloquées tant que non reçu.
                  const isNotReceived = isBienNotReceived(b);
                  return (
                    <tr
                      key={b.id}
                      className={cn(
                        "border-t border-border transition-colors hover:bg-accent/40 cursor-pointer",
                        idx % 2 === 1 && "bg-muted/20", sel && "bg-primary/5",
                        isNotReceived && "opacity-60 bg-muted/30",
                      )}
                      onClick={() => onOpen(b)}
                    >
                      <td className="px-3 py-3" onClick={(e) => e.stopPropagation()}>
                        <input
                          type="checkbox"
                          checked={sel}
                          onChange={() => onSelectionChange(sel ? selectedIds.filter((x) => x !== b.id) : [...selectedIds, b.id])}
                          className="h-4 w-4 cursor-pointer accent-primary"
                          aria-label={t("action.select")}
                        />
                      </td>
                      {cols.map((c) => (
                        <td key={c.key} className={cn("px-3 py-3 align-middle", c.className)}>{c.render(b)}</td>
                      ))}
                      <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                        {isSortie ? (
                          <span className="text-xs italic text-muted-foreground" title={t("biens.list.noActionsSortie")}>—</span>
                        ) : isNotReceived ? (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <button type="button" className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted" aria-label={t("common.actions")}>
                                <MoreVertical className="h-4 w-4" />
                              </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                              <DropdownMenuItem onSelect={() => onOpen(b)}><Eye className="mr-2 h-4 w-4" /> {t("biens.list.viewFiche")}</DropdownMenuItem>
                              <DropdownMenuItem
                                disabled={acknowledgeMutation.isPending}
                                onSelect={() => acknowledgeMutation.mutate([b])}
                              >
                                <CheckCircle2 className="mr-2 h-4 w-4 text-emerald-600" /> {t("biens.list.receptionAcknowledged")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => printBordereau([b])}><FileText className="mr-2 h-4 w-4" /> {t("biens.print.printBordereau")}</DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => onPrint(b)}><Printer className="mr-2 h-4 w-4" /> {t("biens.print.printFiche")}</DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        ) : (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <button type="button" className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted" aria-label={t("common.actions")}>
                                <MoreVertical className="h-4 w-4" />
                              </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                              <DropdownMenuItem onSelect={() => onOpen(b)}><Eye className="mr-2 h-4 w-4" /> {t("biens.list.viewFiche")}</DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => onEdit(b)}><Pencil className="mr-2 h-4 w-4" /> {t("action.edit")}</DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => onSortir(b)}><DoorOpen className="mr-2 h-4 w-4 text-destructive" /> <span className="text-destructive">{t("biens.list.exitAsset")}</span></DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => openDetenteur(b)}>
                                <UserIcon className="mr-2 h-4 w-4" /> {t("biens.list.detenteurFiche")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => setRestitutionTarget(b)}>
                                <Undo2 className="mr-2 h-4 w-4" /> {t("biens.list.restituer")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => onPrint(b)}><Printer className="mr-2 h-4 w-4" /> {t("biens.print.printFiche")}</DropdownMenuItem>
                              <DropdownMenuItem onSelect={() => setMercurialeTarget(b)}><ExternalLink className="mr-2 h-4 w-4" /> {t("biens.list.viewMercuriale")}</DropdownMenuItem>
                              {/* Recommandation 6 — génération d'un QR code
                                  reprenant les détails du bien (référence, nom,
                                  catégorie, valeur, structure, etc.). */}
                              <DropdownMenuItem onSelect={() => setQrTarget(b)}>
                                <QrCode className="mr-2 h-4 w-4" /> {t("biens.list.qrCode")}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem className="text-destructive" onSelect={() => {
                                toast(t("biens.list.deleteConfirmTitle"), {
                                  description: t("biens.list.deleteConfirmDesc", { nom: b.nom }),
                                  action: { label: t("action.delete"), onClick: () => onDelete(b.id) },
                                  cancel: { label: t("action.cancel"), onClick: () => {} },
                                  duration: 8000,
                                });
                              }}>
                                <Trash2 className="mr-2 h-4 w-4" /> {t("action.delete")}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted-foreground sm:text-sm">
              {t("biens.list.count", {
                count: data.length.toLocaleString("fr-FR"),
                total: total.toLocaleString("fr-FR"),
              })}
            </p>
            <div className="flex items-center gap-1">
              <button type="button" disabled={currentPage <= 1} onClick={() => setPage(currentPage - 1)}
                className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground disabled:opacity-40 hover:bg-muted" aria-label={t("biens.list.previous")}>
                <ChevronLeft className="h-4 w-4" />
              </button>
              {pageNumbers.map((p, i) =>
                p === "..." ? (
                  <span key={`e${i}`} className="px-2 text-xs text-muted-foreground">…</span>
                ) : (
                  <button key={p} type="button" onClick={() => setPage(p as number)}
                    className={cn("inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-semibold",
                      p === currentPage ? "bg-primary text-primary-foreground" : "border border-border text-foreground/80 hover:bg-muted"
                    )}>
                    {p}
                  </button>
                )
              )}
              <button type="button" disabled={currentPage >= totalPages} onClick={() => setPage(currentPage + 1)}
                className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground disabled:opacity-40 hover:bg-muted" aria-label={t("biens.list.next")}>
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

      {/* ── Popup de réforme ──────────────────────────────────────────────── */}
      {reformeTarget && (
        <ReformeRequestDialog
          bien={reformeTarget}
          onClose={() => setReformeTarget(null)}
          onSent={() => {
            setReformeTarget(null);
            toast.success(t("biens.toast.etatChanged"), { duration: 6000 });
          }}
        />
      )}
    </div>
  );
}

/**
 * "La validation a échoué" (data.message) est générique côté backend — le
 * détail par champ est ailleurs selon le cas : data.errors[].constraints
 * (array) ou data.data en tant qu'objet { champ: "message" }. On privilégie
 * le détail le plus précis avant de retomber sur le message générique
 * (même pattern que ProjetsSection.tsx).
 */
function assetExitErrorMessage(err: unknown, fallback?: string): string {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const data = (err as { response?: { data?: any } })?.response?.data;
  const details = Array.isArray(data?.errors)
    ? (data.errors as Array<{ constraints?: Record<string, string> }>)
        .flatMap((e) => Object.values(e.constraints ?? {})).join(" · ")
    : undefined;
  const fieldErrors = data?.data && typeof data.data === "object" && !Array.isArray(data.data)
    ? Object.values(data.data as Record<string, string>).join(" · ")
    : undefined;
  return fieldErrors || details || data?.message || fallback || "Erreur lors de la sortie";
}

  /* ─── SortieDirectDialog ─────────────────────────────────────────────────────
    Dialog de sortie accessible directement depuis le tableau (sans ouvrir la fiche).
    Réutilise SortieFormInline avec un wrapper modal.
    ──────────────────────────────────────────────────────────────────────────── */

function SortieDirectDialog({ bien, onClose, onSaved }: {
  bien: ApiBien;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [saving, setSaving] = useState(false);
  const t = useT();

  const handleSave = async (payload: {
    exitTypeId?: number; motifSortie?: string; dateSortie?: string; observations?: string;
    piecesJointes: Array<{ file: File; nom: string }>;
    beneficiaires: Array<{ beneficiaireId: number | null; serviceId: number | null; quantiteDemandee?: number; quantiteAccordee?: number; quantiteServie: number; pieces: Array<{ file: File; nom: string }>; destinataireNom: string }>;
  }) => {
    setSaving(true);
    try {
      const exit = await createAssetExit({
        asset_id: bien.id,
        exit_type_id: payload.exitTypeId,
        motifSortie: payload.motifSortie,
        dateSortie: payload.dateSortie,
        observations: payload.observations,
        piecesJointes: payload.piecesJointes.map((p) => p.file),
        piecesJointesNoms: payload.piecesJointes.map((p) => p.nom),
      });
      const exitId = exit.data.id;
      // Un par un (pas en parallèle) — le serveur ne gère pas bien des
      // créations simultanées sur la même sortie. Un BSP par individu/
      // structure, avec son propre PDF généré côté client juste après
      // chaque création (pas le PDF officiel du serveur — modèle à
      // tableau fixe de 14 lignes, bénéficiaire non affiché).
      const createdBsps: ApiBsp[] = [];
      const createdForBordereau: Array<{ bsp: ApiBsp; destinataireNom: string }> = [];
      const bspErrors: string[] = [];
      for (const b of payload.beneficiaires) {
        let bsp: ApiBsp;
        try {
          bsp = await createBsp(exitId, {
            service_id: b.serviceId ?? undefined,
            beneficiaire_id: b.beneficiaireId ?? undefined,
            quantiteDemandee: b.quantiteDemandee,
            quantiteAccordee: b.quantiteAccordee,
            quantiteServie: b.quantiteServie,
            piecesJointes: b.pieces.length > 0 ? b.pieces.map((p) => p.file) : undefined,
            piecesJointesNoms: b.pieces.length > 0 ? b.pieces.map((p) => p.nom) : undefined,
          });
          createdBsps.push(bsp);
          createdForBordereau.push({ bsp, destinataireNom: b.destinataireNom });
        } catch (err) {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          bspErrors.push(msg ?? t("biens.error.unknown"));
          console.error("[bsp] échec de création:", err);
          continue;
        }
        try {
          await generateBspPdf({ reference: bien.reference, nom: bien.nom }, exit.data, bsp, b.destinataireNom);
        } catch (err) {
          bspErrors.push(t("biens.error.bspPdfFailed", { numero: bsp.numero }));
          console.error("[bsp] échec de génération du PDF:", err);
        }
      }
      // Bordereau récapitulatif en plus des PDF individuels — un ou plusieurs BSP.
      if (createdForBordereau.length > 0) {
        try {
          await generateBspBordereauPdf({ reference: bien.reference, nom: bien.nom }, exit.data, createdForBordereau);
        } catch (err) {
          console.error("[bsp] échec de génération du bordereau récapitulatif:", err);
        }
      }
      if (bspErrors.length > 0) {
        toast.error(t("biens.toast.sortieBspPartial", { created: createdBsps.length, total: payload.beneficiaires.length, errors: bspErrors.join(" ; ") }));
      } else {
        toast.success(t("biens.toast.sortieSuccessRef", { reference: bien.reference }));
      }
      onSaved();
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status;
      let msg = assetExitErrorMessage(err, t("biens.error.sortieGeneric"));
      if (status === 400 && msg === "La validation a échoué") {
        msg = t("biens.error.sortieAlreadyExists");
      }
      toast.error(msg, { duration: 8000 });
    } finally {
      setSaving(false);
    }
  };

    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={onClose}>
        <div className="mx-4 w-full max-w-lg rounded-2xl border border-border bg-card shadow-2xl" onClick={(e) => e.stopPropagation()}>
          <div className="flex items-center justify-between border-b border-border px-5 py-3">
            <div>
              <h3 className="text-sm font-bold text-foreground">{t("biens.list.exitAsset")}</h3>
              <p className="text-xs text-muted-foreground">{bien.reference} — {bien.nom}</p>
            </div>
            <button type="button" onClick={onClose} className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted">
              <X className="h-4 w-4" />
            </button>
          </div>
          <div className="p-5">
            <SortieFormInline
              isSaving={saving}
              onCancel={onClose}
              bienNom={bien.nom}
              bienReference={bien.reference}
              onSave={handleSave}
            />
          </div>
        </div>
      </div>
    );
  }

  /* ─── SecurisationDialog ─────────────────────────────────────────────────────
    Popup : sécurisation groupée de biens (POST /securities, multipart).
    Mode (Physique/Juridique), date, position optionnelle (lat/lng, carte
    cliquable) et pièces jointes multiples. La liste des biens ciblés est
    déjà validée en amont (voir openSecurisation dans BiensList).
    ──────────────────────────────────────────────────────────────────────────── */

  function SecurisationDialog({ biens, onClose, onSaved }: {
    biens: ApiBien[];
    onClose: () => void;
    onSaved: () => void;
  }) {
    const t = useT();
    // Rec. 410 — un bloc de formulaire par bien, chacun avec ses propres champs
    type BienForm = {
      id: number;
      // Un bien peut être sécurisé juridiquement ET physiquement — chaque
      // mode coché déclenche son propre POST /securities (l'API n'accepte
      // qu'un security_mode par appel, pas de mode combiné).
      securityModes: Set<SecurityMode>;
      dateSecurisation: string;
      latitude: string;
      longitude: string;
      pieces: Array<{ file: File; nom: string }>;
    };

    const today = new Date().toISOString().slice(0, 10);

    const [forms, setForms] = useState<BienForm[]>(() =>
      biens.map((b) => ({
        id: b.id,
        securityModes: new Set<SecurityMode>(["Physique"]),
        dateSecurisation: today,
        latitude: "",
        longitude: "",
        pieces: [],
      }))
    );
    const [saving, setSaving] = useState(false);
    const [geoLoading, setGeoLoading] = useState(false);

    // Rec. 410 — pré-remplir lat/lng avec la localisation enregistrée sur le bien
    // (GET /assets/{id}/location). Pertinent surtout pour Terrain/Bâtiment qui ont
    // une position fixe enregistrée. Chaque bien reçoit sa propre position.
    useEffect(() => {
      setGeoLoading(true);
      Promise.allSettled(
        biens.map(async (b) => {
          const loc = await getCurrentLocation(b.id);
          if (loc) {
            setForms((prev) =>
              prev.map((f) =>
                f.id === b.id
                  ? { ...f, latitude: String(loc.latitude), longitude: String(loc.longitude) }
                  : f,
              ),
            );
          }
        }),
      ).finally(() => setGeoLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const updateForm = (id: number, patch: Partial<BienForm>) =>
      setForms((prev) => prev.map((f) => (f.id === id ? { ...f, ...patch } : f)));

    const toggleSecurityMode = (id: number, mode: SecurityMode, checked: boolean) =>
      setForms((prev) => prev.map((f) => {
        if (f.id !== id) return f;
        const next = new Set(f.securityModes);
        if (checked) next.add(mode); else next.delete(mode);
        return { ...f, securityModes: next };
      }));

    const onPickFiles = (id: number, e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      updateForm(id, {
        pieces: [...(forms.find((f) => f.id === id)?.pieces ?? []), ...files.map((file) => ({ file, nom: file.name }))],
      });
      e.target.value = "";
    };

    const removePiece = (id: number, i: number) =>
      updateForm(id, { pieces: forms.find((f) => f.id === id)!.pieces.filter((_, j) => j !== i) });

    const submit = async () => {
      // Valider tous les blocs
      for (const f of forms) {
        const bien = biens.find((b) => b.id === f.id);
        const label = `"${bien?.nom ?? f.id}"`;
        if (f.securityModes.size === 0) { toast.error(t("biens.securisation.error.mode", { label })); return; }
        if (!f.dateSecurisation) { toast.error(t("biens.securisation.error.date", { label })); return; }
        if (f.pieces.length === 0) { toast.error(t("biens.securisation.error.pieces", { label })); return; }
        const lat = f.latitude.trim() ? Number(f.latitude) : undefined;
        const lng = f.longitude.trim() ? Number(f.longitude) : undefined;
        if (f.latitude.trim() && (Number.isNaN(lat!) || lat! < -90 || lat! > 90)) {
          toast.error(t("biens.securisation.error.latitude", { label })); return;
        }
        if (f.longitude.trim() && (Number.isNaN(lng!) || lng! < -180 || lng! > 180)) {
          toast.error(t("biens.securisation.error.longitude", { label })); return;
        }
      }

      setSaving(true);
      let successCount = 0;
      const errors: string[] = [];

      // Un POST /securities par bien ET par mode coché (l'API ne prend qu'un
      // security_mode par appel — juridique + physique = deux appels).
      for (const f of forms) {
        const bien = biens.find((b) => b.id === f.id);
        const lat = f.latitude.trim() ? Number(f.latitude) : undefined;
        const lng = f.longitude.trim() ? Number(f.longitude) : undefined;
        try {
          for (const mode of f.securityModes) {
            await createSecurity({
              asset_ids: [f.id],
              security_mode: mode,
              date_securisation: f.dateSecurisation,
              latitude: lat,
              longitude: lng,
              piecesJointes: f.pieces.length > 0 ? f.pieces.map((p) => p.file) : undefined,
              piecesJointesNoms: f.pieces.length > 0 ? f.pieces.map((p) => p.nom) : undefined,
            });
          }
          successCount++;
        } catch (err: unknown) {
          const res = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
          errors.push(`${bien?.nom ?? f.id} : ${res?.data?.message ?? t("biens.error.withStatus", { status: res?.status ?? "" })}`);
        }
      }

      setSaving(false);
      if (errors.length === 0) {
        toast.success(t("biens.securisation.success", { count: successCount }));
        onSaved();
      } else if (successCount > 0) {
        toast.error(t("biens.toast.acknowledgePartial", { success: successCount, failed: errors.length, errors: errors.join(" ; ") }));
        onSaved();
      } else {
        toast.error(t("biens.toast.acknowledgeFailed", { errors: errors.join(" ; ") }));
      }
    };

    return (
      <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 backdrop-blur-sm py-8 px-4" onClick={onClose}>
        <div className="w-full max-w-2xl rounded-2xl border border-border bg-card shadow-2xl" onClick={(e) => e.stopPropagation()}>
          {/* En-tête */}
          <div className="flex items-center justify-between border-b border-border px-5 py-3">
            <div>
              <h3 className="text-sm font-bold text-foreground">{t("biens.securisation.title")}</h3>
              <p className="text-xs text-muted-foreground">
                {t("biens.securisation.subtitle", { count: biens.length })}
                {geoLoading && <span className="ml-2 text-primary">· {t("biens.securisation.locating")}</span>}
              </p>
            </div>
            <button type="button" onClick={onClose} className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted">
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Rec. 410.2 — un bloc par bien, empilés verticalement */}
          <div className="divide-y divide-border">
            {forms.map((f, idx) => {
              const bien = biens.find((b) => b.id === f.id)!;
              return (
                <div key={f.id} className="space-y-4 p-5">
                  {/* Titre du bloc */}
                  <div className="flex items-center gap-2">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                      {idx + 1}
                    </span>
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold text-foreground">{bien.nom}</p>
                      <p className="text-xs text-muted-foreground">{bien.reference} · {bien.category?.nom ?? "—"}</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <Field label={t("biens.securisation.field.mode")}>
                      <div className="flex h-10 items-center gap-4">
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                          <input
                            type="checkbox"
                            className="h-4 w-4 accent-primary"
                            checked={f.securityModes.has("Physique")}
                            onChange={(e) => toggleSecurityMode(f.id, "Physique", e.target.checked)}
                          />
                          {t("biens.securisation.mode.physique")}
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                          <input
                            type="checkbox"
                            className="h-4 w-4 accent-primary"
                            checked={f.securityModes.has("Juridique")}
                            onChange={(e) => toggleSecurityMode(f.id, "Juridique", e.target.checked)}
                          />
                          {t("biens.securisation.mode.juridique")}
                        </label>
                      </div>
                    </Field>
                    <Field label={t("biens.securisation.field.date")}>
                      <Input type="date" value={f.dateSecurisation} onChange={(e) => updateForm(f.id, { dateSecurisation: e.target.value })} />
                    </Field>
                  </div>

                  {/* Carte + coordonnées — uniquement pour Terrain/Bâtiment */}
                  {isImmobilier(bien.category?.nom) && (
                    <>
                      <p className="text-[11px] text-muted-foreground">
                        {t("biens.securisation.positionHint")}
                        {geoLoading && <span className="ml-1 text-primary">{t("biens.securisation.locating")}</span>}
                      </p>
                      <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2 text-xs text-muted-foreground hover:bg-primary/10">
                        <MapIcon className="h-4 w-4 shrink-0 text-primary" />
                        <span>{t("biens.securisation.importFile")}</span>
                        <input
                          type="file"
                          accept=".csv,.xlsx,.xls,.json,.geojson,.kml,.gpx"
                          className="hidden"
                          onChange={async (e) => {
                            const file = e.target.files?.[0] ?? null;
                            e.target.value = "";
                            if (!file) return;
                            const loc = await parseLocationFileClientSide(file);
                            if (loc) {
                              updateForm(f.id, { latitude: String(loc.latitude), longitude: String(loc.longitude) });
                              toast.success(t("biens.securisation.positionUpdated"));
                            } else {
                              toast.error(t("biens.securisation.positionExtractFailed"));
                            }
                          }}
                        />
                      </label>
                      <Suspense fallback={<MapLoadingFallback height={180} />}>
                        <LocationPickerMap
                          value={
                            f.latitude.trim() && f.longitude.trim() && !Number.isNaN(Number(f.latitude)) && !Number.isNaN(Number(f.longitude))
                              ? { latitude: Number(f.latitude), longitude: Number(f.longitude) }
                              : null
                          }
                          onChange={(v) => updateForm(f.id, { latitude: String(v.latitude), longitude: String(v.longitude) })}
                        />
                      </Suspense>
                      <div className="grid grid-cols-2 gap-3">
                        <Field label={t("biens.field.latitude")}>
                          <Input type="number" step="any" value={f.latitude} onChange={(e) => updateForm(f.id, { latitude: e.target.value })} placeholder="Ex : 3.8667" />
                        </Field>
                        <Field label={t("biens.field.longitude")}>
                          <Input type="number" step="any" value={f.longitude} onChange={(e) => updateForm(f.id, { longitude: e.target.value })} placeholder="Ex : 11.5167" />
                        </Field>
                      </div>
                    </>
                  )}

                  {/* Pièces jointes individuelles */}
                  <Field label={t("biens.securisation.field.pieces")}>
                    <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2 text-xs text-muted-foreground hover:bg-primary/10">
                      <Upload className="h-4 w-4 shrink-0 text-primary" />
                      <span>{t("biens.securisation.attachForAsset")}</span>
                      <input type="file" multiple className="hidden" onChange={(e) => onPickFiles(f.id, e)} />
                    </label>
                    {f.pieces.length > 0 && (
                      <ul className="mt-2 space-y-1.5">
                        {f.pieces.map((p, i) => (
                          <li key={i} className="flex items-center gap-2">
                            <Input
                              className="h-7 flex-1 text-xs"
                              value={p.nom}
                              onChange={(e) => {
                                const updated = [...f.pieces];
                                updated[i] = { ...updated[i], nom: e.target.value };
                                updateForm(f.id, { pieces: updated });
                              }}
                            />
                            <button type="button" onClick={() => removePiece(f.id, i)} className="text-muted-foreground hover:text-destructive">
                              <X className="h-3.5 w-3.5" />
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </Field>
                </div>
              );
            })}
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
            <Button variant="outline" size="sm" onClick={onClose} disabled={saving}>{t("action.cancel")}</Button>
            <Button size="sm" onClick={submit} disabled={saving} className="gap-2">
              {saving && <Loader2Icon className="h-3.5 w-3.5 animate-spin" />}
              {saving ? t("biens.securisation.saving") : (biens.length > 1 ? t("biens.securisation.secureMany", { count: biens.length }) : t("biens.securisation.secureOne"))}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  /* ─── RestitutionDialog ──────────────────────────────────────────────────────
    Popup : restitution d'un bien (POST /assets/{id}/restituer). Confirmé en
    lisant AssetAssignmentService::restituerAsset directement (pas juste le
    Swagger) : seuls user_id (obligatoire), dateDebut, dateFin et commentaire
    (tous facultatifs) sont acceptés — pas de champ service.
    ──────────────────────────────────────────────────────────────────────────── */

  function RestitutionDialog({ bien, isSaving, onClose, onSave }: {
    bien: ApiBien;
    isSaving: boolean;
    onClose: () => void;
    onSave: (payload: RestituerBienPayload) => void;
  }) {
    const t = useT();
    const { data: users = [] } = useQuery({
      queryKey: ["users-all"],
      queryFn: listAllUsers,
      staleTime: 5 * 60 * 1000,
    });

    // Préremplissage — utilisateur de restitution par défaut du bien (userRestitution),
    // modifiable avant confirmation.
    const [userId, setUserId] = useState<number | null>(bien.userRestitution?.id ?? null);
    const [dateDebut, setDateDebut] = useState("");
    const [dateFin, setDateFin] = useState("");
    const [commentaire, setCommentaire] = useState("");

    const submit = () => {
      if (userId == null) {
        toast.error(t("biens.restitution.error.user"));
        return;
      }
      onSave({
        user_id: userId,
        dateDebut: dateDebut.trim() || undefined,
        dateFin: dateFin.trim() || undefined,
        commentaire: commentaire.trim() || undefined,
      });
    };

    return (
      <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 backdrop-blur-sm py-8 px-4" onClick={onClose}>
        <div className="w-full max-w-lg rounded-2xl border border-border bg-card shadow-2xl" onClick={(e) => e.stopPropagation()}>
          <div className="flex items-center justify-between border-b border-border px-5 py-3">
            <div>
              <h3 className="text-sm font-bold text-foreground">{t("biens.restitution.title")}</h3>
              <p className="text-xs text-muted-foreground">{bien.nom} · {bien.reference}</p>
            </div>
            <button type="button" onClick={onClose} className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted">
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="space-y-4 p-5">
            <div className="space-y-1.5">
              <ReqLabel required>{t("biens.restitution.field.user")}</ReqLabel>
              <SearchableSelect
                value={userId}
                onChange={(v) => setUserId(v)}
                options={users.map((u) => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))}
                placeholder={t("biens.restitution.selectUser")}
                searchPlaceholder={t("biens.restitution.searchUser")}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <Field label={t("biens.restitution.field.dateDebut")}>
                <Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} />
              </Field>
              <Field label={t("biens.restitution.field.dateFin")}>
                <Input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} />
              </Field>
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-medium text-muted-foreground">{t("biens.restitution.field.commentaire")}</Label>
              <Textarea value={commentaire} onChange={(e) => setCommentaire(e.target.value)} rows={3} placeholder={t("biens.restitution.commentairePlaceholder")} />
            </div>
          </div>

          <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
            <Button variant="outline" size="sm" onClick={onClose} disabled={isSaving}>{t("action.cancel")}</Button>
            <Button size="sm" onClick={submit} disabled={isSaving} className="gap-2">
              {isSaving && <Loader2Icon className="h-3.5 w-3.5 animate-spin" />}
              {isSaving ? t("biens.restitution.saving") : t("biens.restitution.confirm")}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  /* ─── ReformeRequestDialog ──────────────────────────────────────────────────
    Popup : décision (Motif) textarea + pièces jointes multiples.
    Passe par POST /asset-exits avec reforme: true — le backend force alors
    automatiquement statut=SORTIS, état=Réformé et type de sortie=Réforme
    (voir createAssetExit / asset-exits.api.ts). On n'envoie que asset_id,
    motifSortie (le texte de décision), les pièces jointes et reforme: true.
    L'ancienne implémentation mettait à jour etat_bien_id directement via
    une recherche par regex sur le nom de l'état, laquelle matchait parfois
    "A Reformer" au lieu de "Réformé" (le mauvais état) — en laissant le
    backend gérer le changement d'état via reforme: true, ce problème
    disparaît complètement.
    ──────────────────────────────────────────────────────────────────────────── */

function ReformeRequestDialog({ bien, onClose, onSent }: {
  bien: ApiBien;
  onClose: () => void;
  onSent: () => void;
}) {
  const queryClient = useQueryClient();
  const t = useT();
  const [decision, setDecision] = useState("");
  const [pieces, setPieces]     = useState<Array<{ file: File; nom: string }>>([]);
  const [loading, setLoading]   = useState(false);

    const onPickFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      setPieces((prev) => [...prev, ...files.map((f) => ({ file: f, nom: f.name }))]);
      e.target.value = "";
    };

  const submit = async () => {
    if (!decision.trim()) { toast.error(t("biens.reforme.error.decisionRequired")); return; }
    if (pieces.length === 0) { toast.error(t("biens.reforme.error.piecesRequired")); return; }
    setLoading(true);
    try {
      await createAssetExit({
        asset_id: bien.id,
        motifSortie: decision.trim(),
        reforme: true,
        piecesJointes: pieces.map((p) => p.file),
        piecesJointesNoms: pieces.map((p) => p.nom),
      });

      // Le bien passe immédiatement à l'état "Réformé" (statut SORTIS) — il
      // sort donc de la liste générale, comme une sortie normale.
      queryClient.invalidateQueries({ queryKey: ["biens-list"] });
      queryClient.invalidateQueries({ queryKey: ["bien", bien.id] });
      onSent();
    } catch (err: unknown) {
      console.error("Erreur lors de l'enregistrement de la réforme:", err);
      const response = (err as {
        response?: {
          status?: number;
          data?: {
            message?: string;
            errors?: Array<{ constraints?: Record<string, string> }>;
            data?: Record<string, string>;
          };
        };
      })?.response;
      const status = response?.status;
      const data = response?.data;

      // Message lisible : ce backend renvoie parfois le vrai motif dans
      // data.errors[].constraints (array), parfois dans data.data en tant
      // qu'objet { champ: "message" } sous un data.message générique. On
      // privilégie toujours le détail le plus précis avant de retomber sur
      // le message générique.
      const details = Array.isArray(data?.errors)
        ? (data?.errors as Array<{ constraints?: Record<string, string> }>)
            .flatMap((e) => Object.values(e.constraints ?? {})).join(" · ")
        : undefined;
      const fieldErrors = data?.data && typeof data.data === "object" && !Array.isArray(data.data)
        ? Object.values(data.data as Record<string, string>).join(" · ")
        : undefined;
      toast.error(
        fieldErrors || details || data?.message || t("biens.reforme.error.saveFailed", { status: status ?? "" }),
        { duration: 8000 },
      );
    } finally {
      setLoading(false);
    }
  };

    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={onClose}>
        <div className="mx-4 w-full max-w-md rounded-2xl border border-border bg-card shadow-2xl" onClick={(e) => e.stopPropagation()}>
          {/* Header */}
          <div className="flex items-start gap-3 border-b border-border bg-destructive/5 px-5 py-4 rounded-t-2xl">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
              <ArrowRightLeft className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <h3 className="text-sm font-bold text-foreground">{t("biens.reforme.title")}</h3>
              <p className="text-xs text-muted-foreground mt-0.5">
                <span className="font-medium text-foreground">{bien.reference}</span> - {bien.nom}
              </p>
            </div>
            <button type="button" onClick={onClose}
              className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted">
              <X className="h-4 w-4" />
            </button>
          </div>

        {/* Body */}
        <div className="space-y-4 px-5 py-4">
          {/* Décision de réforme — textarea unique */}
          <div className="space-y-1.5">
            <Label className="text-xs font-semibold">{t("biens.reforme.field.reason")} <span className="text-destructive"></span></Label>
            <Textarea
              rows={4}
              value={decision}
              onChange={(e) => setDecision(e.target.value)}
              placeholder={t("biens.reforme.field.reasonPlaceholder")}
            />
          </div>

            {/* Pièces jointes multiples */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold">{t("biens.securisation.field.pieces")} <span className="text-destructive">*</span></Label>
              <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2.5 text-xs text-muted-foreground hover:bg-primary/10 transition">
                <Upload className="h-4 w-4 text-primary" />
                <span>{t("biens.reforme.attachFiles")} <span className="text-destructive">*</span></span>
                <span className="ml-auto text-[10px]">PDF, Image, Word</span>
                <input type="file" multiple className="hidden" onChange={onPickFiles} />
              </label>
              {pieces.length > 0 && (
                <ul className="space-y-1">
                  {pieces.map((p, i) => (
                    <li key={i} className="flex items-center gap-2 rounded border border-border bg-muted/20 px-2 py-1.5 text-xs">
                      <span className="text-muted-foreground">📄</span>
                      <Input className="h-6 flex-1 text-[10px] px-1" value={p.nom}
                        onChange={(e) => { const u = [...pieces]; u[i] = { ...u[i], nom: e.target.value }; setPieces(u); }} />
                      <button type="button" onClick={() => setPieces((prev) => prev.filter((_, j) => j !== i))}
                        className="text-destructive"><X className="h-3.5 w-3.5" /></button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={loading}>{t("action.cancel")}</Button>
            <Button type="button" size="sm" variant="destructive" onClick={submit} disabled={loading} className="gap-2">
              {loading
                ? <><span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" /> {t("biens.reforme.validating")}</>
                : <><FileCheck2 className="h-3.5 w-3.5" /> {t("biens.reforme.validate")}</>}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  function pageList(current: number, total: number): (number | "...")[] {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const out: (number | "...")[] = [1];
    const s = Math.max(2, current - 1);
    const e = Math.min(total - 1, current + 1);
    if (s > 2) out.push("...");
    for (let i = s; i <= e; i++) out.push(i);
    if (e < total - 1) out.push("...");
    out.push(total);
    return out;
  }

  // La section Localisation ne concerne que les biens des catégories "Terrains"
  // et "Bâtiments" (Administration > Catégories) — comparaison exacte du nom
  // (insensible à la casse uniquement, pas une recherche par mot-clé).
  const CATEGORIES_LOCALISATION = ["terrains", "bâtiments"];
  function isTerrainOuBatiment(categorieNom?: string): boolean {
    return !!categorieNom && CATEGORIES_LOCALISATION.includes(categorieNom.trim().toLowerCase());
  }

  // Aperçu client d'un point à partir d'un fichier cartographique, AVANT
  // envoi au serveur (utile surtout en création, où le bien n'existe pas
  // encore pour appeler POST /assets/{id}/locations). L'extraction réelle et
  // faisant foi reste côté backend à l'enregistrement — ceci n'est qu'une
  // prévisualisation best-effort côté client pour les formats faciles à lire
  // en JS (GeoJSON, CSV, Excel, KML, GPX). Les Shapefile (.shp/.zip), binaires,
  // ne sont pas prévisualisables ainsi et restent affichés qu'après création.
  function findLatLngKeys(row: Record<string, unknown>): { lat?: string; lng?: string } {
    const keys = Object.keys(row);
    const lat = keys.find((k) => /^lat(itude)?$/i.test(k.trim()));
    const lng = keys.find((k) => /^lon(g|gitude)?$/i.test(k.trim()) || /^lng$/i.test(k.trim()));
    return { lat, lng };
  }

  function extractLatLngFromRow(row: Record<string, unknown>): { latitude: number; longitude: number } | null {
    const { lat, lng } = findLatLngKeys(row);
    if (!lat || !lng) return null;
    const latitude = Number(row[lat]);
    const longitude = Number(row[lng]);
    if (Number.isNaN(latitude) || Number.isNaN(longitude)) return null;
    return { latitude, longitude };
  }

  function extractLatLngFromGeoJson(obj: unknown): { latitude: number; longitude: number } | null {
    const geo = obj as { type?: string; coordinates?: number[]; geometry?: { type?: string; coordinates?: number[] }; features?: Array<{ geometry?: { type?: string; coordinates?: number[] } }> };
    let geometry: { type?: string; coordinates?: number[] } | undefined;
    if (geo?.type === "Point") geometry = geo;
    else if (geo?.type === "Feature") geometry = geo.geometry;
    else if (geo?.type === "FeatureCollection") geometry = geo.features?.find((f) => f.geometry?.type === "Point")?.geometry;
    if (geometry?.type !== "Point" || !Array.isArray(geometry.coordinates) || geometry.coordinates.length < 2) return null;
    // GeoJSON : ordre [longitude, latitude], l'inverse de la convention usuelle.
    const [longitude, latitude] = geometry.coordinates;
    if (typeof longitude !== "number" || typeof latitude !== "number") return null;
    return { latitude, longitude };
  }

  async function parseLocationFileClientSide(file: File): Promise<{ latitude: number; longitude: number } | null> {
    const name = file.name.toLowerCase();
    try {
      if (name.endsWith(".json") || name.endsWith(".geojson")) {
        const text = await file.text();
        return extractLatLngFromGeoJson(JSON.parse(text));
      }
      if (name.endsWith(".csv")) {
        const text = await file.text();
        const lines = text.split(/\r?\n/).filter((l) => l.trim().length > 0);
        if (lines.length < 2) return null;
        const headers = lines[0].split(",").map((h) => h.trim());
        const values = lines[1].split(",").map((v) => v.trim());
        const row: Record<string, unknown> = {};
        headers.forEach((h, i) => { row[h] = values[i]; });
        return extractLatLngFromRow(row);
      }
      if (name.endsWith(".xlsx") || name.endsWith(".xls")) {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: "array" });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet);
        return rows.length > 0 ? extractLatLngFromRow(rows[0]) : null;
      }
      if (name.endsWith(".kml")) {
        const text = await file.text();
        const match = text.match(/<coordinates>\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)/i);
        if (!match) return null;
        return { longitude: Number(match[1]), latitude: Number(match[2]) };
      }
      if (name.endsWith(".gpx")) {
        const text = await file.text();
        const match = text.match(/<(?:wpt|trkpt)[^>]*\blat="(-?[\d.]+)"[^>]*\blon="(-?[\d.]+)"/i);
        if (!match) return null;
        return { latitude: Number(match[1]), longitude: Number(match[2]) };
      }
    } catch {
      return null;
    }
    return null;
  }

  /* =========================================================
    BienDetail — fiche détail d'un bien (données réelles)
    ========================================================= */

  function BienDetail({
    bien, onBack, onEdit, onDelete, onPrint,
  }: {
    bien: ApiBien;
    onBack: () => void;
    onEdit: () => void;
    onDelete: () => void;
    onPrint: () => void;
  }) {
    type InlineKey = "sortie" | "maintenance" | "reevaluation" | "depreciation" | "affectation" | "localisation" | null;
    const t = useT();
    const [inline, setInline] = useState<InlineKey>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    // Item en cours d'édition pour chaque type d'événement
    const [editingAffectation, setEditingAffectation]   = useState<ApiAffectation | null>(null);
    const [editingMaintenance, setEditingMaintenance]   = useState<ApiMaintenance | null>(null);
    const [editingReeval, setEditingReeval]             = useState<ApiReevaluation | null>(null);
    const [editingDepreciation, setEditingDepreciation] = useState<ApiDepreciation | null>(null);
    // Recommandation 90 — fiche détenteur (biens actuellement affectés à un utilisateur).
    const [detenteurTarget, setDetenteurTarget] = useState<{ id: number; nom: string } | null>(null);

    const closeInline = () => {
      setInline(null);
      setEditingAffectation(null);
      setEditingMaintenance(null);
      setEditingReeval(null);
      setEditingDepreciation(null);
    };

    // Charge le bien complet depuis GET /assets/{id} pour avoir les champs fournisseur.
    // "bien" passé en prop = version résumée de la liste (sans fournisseur).
    // placeholderData permet d'afficher immédiatement la version résumée
    // SANS bloquer le fetch — contrairement à initialData qui marque les données comme fraîches.
    const { data: fullBien, isLoading: bienLoading, isError: bienError } = useQuery<ApiBien>({
      queryKey: ["bien", bien.id],
      queryFn: () => getBienById(bien.id),
      placeholderData: bien,  // affichage immédiat depuis les données de la liste
      staleTime: 30_000,      // 30s avant de re-fetch
      retry: 1,
      // Quand la fiche complète arrive (avec projets/sourceFinancement),
      // on enrichit l'entrée correspondante dans le cache de la liste
      // pour que "Source de financement" s'affiche dans le tableau dès le prochain retour.
      select: (data) => {
        const src = data.sourceFinancement ?? data.projects?.[0]?.nom;
        return src ? { ...data, sourceFinancement: src } : data;
      },
    });

    // On utilise fullBien (données complètes) pour tout l'affichage.
    // isFull = true quand on a reçu la réponse complète (pas juste le placeholder)
    const b = fullBien ?? bien;
    const isFull = !!fullBien && fullBien !== bien;

    const queryClient = useQueryClient();

    // ── Affectations ─────────────────────────────────────────────────────────
    // Priorité : GET /assets/{id}/assignments (endpoint dédié) — la relation
    // "service" embarquée dans GET /assets/{id}.affectations n'est pas fiable
    // (déjà constaté sur d'autres relations embarquées dans cette page : état
    // du bien, seuil de catégorie...) — juste après la création d'une
    // affectation, le bien complet peut la renvoyer avec service manquant,
    // faisant disparaître la colonne "Structure" du tableau jusqu'à un
    // rechargement complet. L'endpoint dédié la renvoie de façon fiable.
    // Fallback : données embarquées dans fullBien.affectations, uniquement
    // tant que la query dédiée n'a pas encore répondu.
    const { data: affectationsFromQuery = [] } = useQuery<ApiAffectation[]>({
      queryKey: ["affectations", bien.id],
      queryFn: () => listAffectations(bien.id),
      staleTime: 60_000,
      retry: 0,
      enabled: isFull,
    });

    const [allAffectations, setAllAffectations] = useState<ApiAffectation[]>([]);

    useEffect(() => {
      if (affectationsFromQuery.length > 0) {
        setAllAffectations(affectationsFromQuery);
      } else {
        const embedded = fullBien?.affectations;
        if (embedded && embedded.length > 0) setAllAffectations(embedded as ApiAffectation[]);
      }
    }, [fullBien?.affectations, affectationsFromQuery]);

    // Un bien n'est affecté qu'à une seule entité à la fois : la plus
    // récente (par date de début) est le détenteur actuel, toutes les
    // autres sont de l'historique — un seul tableau, trié du plus récent
    // au plus ancien. Comparaison en chaîne YYYY-MM-DD, valide car
    // lexicographique = chronologique pour ce format.
    const sortedAffectations = [...allAffectations]
      .sort((a, b) => (b.dateDebut ?? "").localeCompare(a.dateDebut ?? ""));

  // ── Maintenances / Réévaluations / Dépréciations ────────────────────────────
  // Embarquées dans GET /assets/{id} (fullBien) — aucune route
  // GET .../maintenances|reevaluations|depreciations?asset_id=X n'existe côté
  // backend, inutile d'y ajouter un fallback. Rafraîchies via invalidateBien()
  // après chaque mutation ci-dessous.
  const allMaintenances = fullBien?.maintenances ?? [];
  const allReevals = fullBien?.reevaluations ?? [];
  const allDepreciations = fullBien?.depreciations ?? [];

  const invalidateBien = () => queryClient.invalidateQueries({ queryKey: ["bien", bien.id] });

  // Bien pas encore accusé réception par le détenteur actuel — toutes les
  // actions bloquées à l'exception de l'impression et de l'accusé de
  // réception, même règle que sur le tableau (voir BiensList). b.received
  // vient de GET /assets/{id}, fiable quelle que soit la source de la liste
  // d'où on est arrivé sur cette fiche. Pour un admin, ne s'applique que si
  // le bien est affecté à son propre service (demande explicite 2026-08-29).
  const isAdmin = useIsAdmin();
  const { user: connectedUserDetail } = useConnectedUser();
  const isNotReceived = b.received === false
    && (isAdmin ? b.service?.id === connectedUserDetail?.service?.id : true);
  const acknowledgeDetailMutation = useMutation({
    mutationFn: () => {
      const current = sortedAffectations[0];
      if (!current) throw new Error(t("biens.detail.error.noAffectationFound"));
      return acknowledgeAffectation(current.id);
    },
    onSuccess: () => {
      invalidateBien();
      queryClient.invalidateQueries({ queryKey: ["affectations", bien.id] });
      toast.success(t("biens.detail.toast.receptionSaved"));
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("biens.detail.error.receptionFailed"));
    },
  });

  // ── RÉÉVALUATION — activation/désactivation ─────────────────────────────────
  const toggleReevaluationMutation = useMutation({
    mutationFn: (active: boolean) => setAssetReevaluationActive(bien.id, active),
    onSuccess: (res) => {
      invalidateBien();
      toast.success(res.data?.activeReevaluation ? t("biens.detail.toast.reevalActivated") : t("biens.detail.toast.reevalDeactivated"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      if (status === 409) { toast.error(msg ?? t("biens.detail.error.assetDeleted")); return; }
      if (status === 404) { toast.error(msg ?? t("biens.detail.error.assetNotFound")); return; }
      toast.error(msg ?? t("biens.detail.error.reevalUpdateFailed"));
    },
  });

  // ── DÉPRÉCIATION — activation/désactivation ─────────────────────────────────
  const toggleDepreciationMutation = useMutation({
    mutationFn: (active: boolean) => setAssetDepreciationActive(bien.id, active),
    onSuccess: (res) => {
      invalidateBien();
      toast.success(res.data?.activeDepreciation ? t("biens.detail.toast.deprecActivated") : t("biens.detail.toast.deprecDeactivated"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      if (status === 409) { toast.error(msg ?? t("biens.detail.error.assetDeleted")); return; }
      if (status === 404) { toast.error(msg ?? t("biens.detail.error.assetNotFound")); return; }
      toast.error(msg ?? t("biens.detail.error.deprecUpdateFailed"));
    },
  });

  // ── AMORTISSEMENT — activation/désactivation ────────────────────────────────
  const toggleAmortissementMutation = useMutation({
    mutationFn: (active: boolean) => setAssetAmortissementActive(bien.id, active),
    onSuccess: (res) => {
      invalidateBien();
      toast.success(res.data?.activeAmortissement ? t("biens.detail.toast.amortActivated") : t("biens.detail.toast.amortDeactivated"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      if (status === 409) { toast.error(msg ?? t("biens.detail.error.assetDeleted")); return; }
      if (status === 404) { toast.error(msg ?? t("biens.detail.error.assetNotFound")); return; }
      toast.error(msg ?? t("biens.detail.error.amortUpdateFailed"));
    },
  });

  // ── Mutations inline ──────────────────────────────────────────────────────

    // ── AFFECTATION ────────────────────────────────────────────────────────────
    // Ne force plus aucune règle métier ("un seul détenteur à la fois") côté
    // frontend — cette hypothèse n'est plus certaine, et la clôture
    // automatique qui en découlait a réellement fait perdre des données sur
    // des affectations existantes. On se contente de créer, sans toucher aux
    // autres enregistrements : le backend fait foi tel quel.
    const affectationMutation = useMutation({
      mutationFn: async (payload: AffectationSubmitPayload) => {
        const affectationPayload: Parameters<typeof createAffectation>[1] = {
          service_id: payload.service_id,
          user_id: payload.user_id,
          typeAffectation: payload.typeAffectation,
          dateDebut: payload.dateDebut,
          dateFin: payload.dateFin,
          commentaire: payload.commentaire,
        };

        if (payload.methodConso === "TRANSFER_DIRECT" && payload.transferPieces && payload.transferPieces.length > 0) {
          affectationPayload.commentaire = `${payload.commentaire || ""} [Méthode: Transfer Direct, Pièces: ${payload.transferPieces.map((p) => p.nom).join(", ")}]`.trim();
        } else if (payload.methodConso === "BSP" && payload.bspData) {
          affectationPayload.commentaire = `${payload.commentaire || ""} [Méthode: BSP, Qté demandée: ${payload.bspData.quantiteDemandee}, Qté accordée: ${payload.bspData.quantiteAccordee}, Qté servie: ${payload.bspData.quantiteServie}]`.trim();
        }

        return createAffectation(bien.id, affectationPayload);
      },
      onSuccess: (created) => {
        // Mise à jour optimiste immédiate — la réponse backend peut être partielle,
        // on invalide aussi le bien complet pour récupérer les relations complètes.
        setAllAffectations((prev) => [...prev, created]);
        queryClient.invalidateQueries({ queryKey: ["affectations", bien.id] });
        invalidateBien();
        closeInline();
        toast.success(t("biens.detail.toast.affectationSaved"));
      },
      onError: (err: unknown) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        const msg = (err as { response?: { data?: { message?: string; detail?: string; errors?: Record<string, string[]> } } })?.response?.data;
        console.error("[affectation] erreur:", err);
        if (status === 500) { toast.error(t("biens.detail.error.error500", { msg: msg?.message ?? msg?.detail ?? t("biens.detail.error.internalServer") })); }
        else if (status === 422 || status === 400) { const first = msg?.errors ? Object.values(msg.errors).flat()[0] : msg?.message; toast.error(t("biens.error.validation", { msg: first ?? t("biens.detail.error.invalidData") })); }
        else if (status === 405) { toast.error(t("biens.detail.error.methodNotAllowed")); }
        else { toast.error(t("biens.detail.error.saveFailed", { status: status ?? "" })); }
      },
    });

    const updateAffectationMutation = useMutation({
      mutationFn: ({ id, payload }: { id: number; payload: AffectationSubmitPayload }) => {
        const affectationPayload: Parameters<typeof updateAffectation>[1] = {
          service_id: payload.service_id,
          user_id: payload.user_id,
          typeAffectation: payload.typeAffectation,
          dateDebut: payload.dateDebut,
          dateFin: payload.dateFin,
          commentaire: payload.commentaire,
        };

        if (payload.methodConso === "TRANSFER_DIRECT" && payload.transferPieces && payload.transferPieces.length > 0) {
          affectationPayload.commentaire = `${payload.commentaire || ""} [Méthode: Transfer Direct, Pièces: ${payload.transferPieces.map((p) => p.nom).join(", ")}]`.trim();
        } else if (payload.methodConso === "BSP" && payload.bspData) {
          affectationPayload.commentaire = `${payload.commentaire || ""} [Méthode: BSP, Qté demandée: ${payload.bspData.quantiteDemandee}, Qté accordée: ${payload.bspData.quantiteAccordee}, Qté servie: ${payload.bspData.quantiteServie}]`.trim();
        }

        return updateAffectation(id, affectationPayload);
      },
      onSuccess: (updated, variables) => {
        const safeId = updated?.id ?? variables.id;
        const safeItem: ApiAffectation = updated?.id ? updated : { id: safeId, typeAffectation: variables.payload.typeAffectation, dateDebut: variables.payload.dateDebut, dateFin: variables.payload.dateFin ?? null, commentaire: variables.payload.commentaire };
        setAllAffectations((prev) => prev.map((a) => a.id === safeId ? safeItem : a));
        invalidateBien();
        closeInline();
        toast.success(t("biens.detail.toast.affectationUpdated"));
      },
      onError: (err: unknown) => {
        console.error("[affectation update] erreur:", err);
        toast.error(t("biens.detail.error.affectationUpdateFailed"));
      },
    });

    const deleteAffectationMutation = useMutation({
      mutationFn: (id: number) => deleteAffectation(id),
      onSuccess: (_, id) => {
        setAllAffectations((prev) => prev.filter((a) => a.id !== id));
        invalidateBien();
        toast.success(t("biens.detail.toast.affectationDeleted"));
      },
      onError: () => toast.error(t("biens.error.deleteFailed")),
    });

  // ── MAINTENANCE ────────────────────────────────────────────────────────────
  const maintenanceMutation = useMutation({
    mutationFn: (payload: Parameters<typeof createMaintenance>[1]) =>
      createMaintenance(bien.id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.maintenanceSaved"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string; detail?: string } } })?.response?.data;
      console.error("[maintenance] erreur:", err);
      if (status === 500) { toast.error(t("biens.detail.error.error500", { msg: msg?.message ?? msg?.detail ?? t("biens.detail.error.internalServer") })); }
      else if (status === 422 || status === 400) { toast.error(t("biens.error.validation", { msg: msg?.message ?? t("biens.detail.error.invalidData") })); }
      else { toast.error(t("biens.detail.error.saveFailed", { status: status ?? "" })); }
    },
  });

  const updateMaintenanceMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateMaintenance>[1] }) =>
      updateMaintenance(id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.maintenanceUpdated"));
    },
    onError: (err: unknown) => {
      console.error("[maintenance update] erreur:", err);
      toast.error(t("biens.detail.error.maintenanceUpdateFailed"));
    },
  });

  const deleteMaintenanceMutation = useMutation({
    mutationFn: (id: number) => deleteMaintenance(id),
    onSuccess: () => {
      invalidateBien();
      toast.success(t("biens.detail.toast.maintenanceDeleted"));
    },
    onError: () => toast.error(t("biens.error.deleteFailed")),
  });

  // ── RÉÉVALUATION ───────────────────────────────────────────────────────────
  const reevalMutation = useMutation({
    mutationFn: (payload: Parameters<typeof createReevaluation>[1]) =>
      createReevaluation(bien.id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.reevalSaved"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string; detail?: string } } })?.response?.data;
      console.error("[reevaluation] erreur:", err);
      if (status === 500) { toast.error(t("biens.detail.error.error500", { msg: msg?.message ?? msg?.detail ?? t("biens.detail.error.internalServer") })); }
      else if (status === 422 || status === 400) { toast.error(t("biens.error.validation", { msg: msg?.message ?? t("biens.detail.error.invalidData") })); }
      else { toast.error(t("biens.detail.error.saveFailed", { status: status ?? "" })); }
    },
  });

  const updateReevalMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateReevaluation>[1] }) =>
      updateReevaluation(id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.reevalUpdated"));
    },
    onError: (err: unknown) => {
      console.error("[reevaluation update] erreur:", err);
      toast.error(t("biens.detail.error.reevalEditFailed"));
    },
  });

  const deleteReevalMutation = useMutation({
    mutationFn: (id: number) => deleteReevaluation(id),
    onSuccess: () => {
      invalidateBien();
      toast.success(t("biens.detail.toast.reevalDeleted"));
    },
    onError: () => toast.error(t("biens.error.deleteFailed")),
  });

  // ── DÉPRÉCIATION ───────────────────────────────────────────────────────────
  const deprecMutation = useMutation({
    mutationFn: (payload: Parameters<typeof createDepreciation>[1]) =>
      createDepreciation(bien.id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.deprecSaved"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string; detail?: string } } })?.response?.data;
      console.error("[depreciation] erreur:", err);
      if (status === 500) { toast.error(t("biens.detail.error.error500", { msg: msg?.message ?? msg?.detail ?? t("biens.detail.error.internalServer") })); }
      else if (status === 422 || status === 400) { toast.error(t("biens.error.validation", { msg: msg?.message ?? t("biens.detail.error.invalidData") })); }
      else { toast.error(t("biens.detail.error.saveFailed", { status: status ?? "" })); }
    },
  });

  const updateDeprecMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateDepreciation>[1] }) =>
      updateDepreciation(id, payload),
    onSuccess: () => {
      invalidateBien();
      closeInline();
      toast.success(t("biens.detail.toast.deprecUpdated"));
    },
    onError: (err: unknown) => {
      console.error("[depreciation update] erreur:", err);
      toast.error(t("biens.detail.error.deprecEditFailed"));
    },
  });

  const deleteDeprecMutation = useMutation({
    mutationFn: (id: number) => deleteDepreciation(id),
    onSuccess: () => {
      invalidateBien();
      toast.success(t("biens.detail.toast.deprecDeleted"));
    },
    onError: () => toast.error(t("biens.error.deleteFailed")),
  });

  // ── LOCALISATION ───────────────────────────────────────────────────────────
  // Historique jamais écrasé — pas d'endpoint d'édition/suppression, uniquement
  // la création (point saisi ou fichier géospatial importé).
  const locationsHistoryQuery = useQuery({
    queryKey: ["asset-locations", bien.id],
    queryFn: () => getLocationsHistory(bien.id),
  });
  const locationsHistory = locationsHistoryQuery.data ?? [];

  const currentLocationQuery = useQuery({
    queryKey: ["asset-location-current", bien.id],
    queryFn: () => getCurrentLocation(bien.id),
  });

  const invalidateLocations = () => {
    queryClient.invalidateQueries({ queryKey: ["asset-locations", bien.id] });
    queryClient.invalidateQueries({ queryKey: ["asset-location-current", bien.id] });
  };

  const createLocationPointMutation = useMutation({
    mutationFn: (payload: CreateLocationPointPayload) => createLocationPoint(bien.id, payload),
    onSuccess: () => {
      invalidateLocations();
      closeInline();
      toast.success(t("biens.detail.toast.locationSaved"));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("biens.detail.error.locationSaveFailed", { status: status ?? "" }));
    },
  });

  const createLocationFileMutation = useMutation({
    mutationFn: (file: File) => createLocationFile(bien.id, file),
    onSuccess: (result) => {
      invalidateLocations();
      closeInline();
      const count = result.data?.length ?? 0;
      toast.success(t("biens.detail.toast.locationsSaved", { count }));
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg ?? t("biens.detail.error.fileImportFailed", { status: status ?? "" }));
    },
  });

  // ── SORTIE ─────────────────────────────────────────────────────────────────
  // Enregistre la sortie du bien via POST /asset-exits (table asset_exits).
  const sortieMutation = useMutation({
    mutationFn: async (payload: {
      exitTypeId?: number; motifSortie?: string; dateSortie?: string; observations?: string;
      piecesJointes: Array<{ file: File; nom: string }>;
      beneficiaires: Array<{ beneficiaireId: number | null; serviceId: number | null; quantiteDemandee?: number; quantiteAccordee?: number; quantiteServie: number; pieces: Array<{ file: File; nom: string }>; destinataireNom: string }>;
    }) => {
      const exit = await createAssetExit({
        asset_id: bien.id,
        exit_type_id: payload.exitTypeId,
        motifSortie: payload.motifSortie,
        dateSortie: payload.dateSortie,
        observations: payload.observations,
        piecesJointes: payload.piecesJointes.map((p) => p.file),
        piecesJointesNoms: payload.piecesJointes.map((p) => p.nom),
      });
      const exitId = exit.data.id;
      // Un par un (pas en parallèle) — le serveur ne gère pas bien des
      // créations simultanées sur la même sortie. Un BSP par individu/
      // structure, avec son propre PDF généré côté client juste après
      // chaque création (pas le PDF officiel du serveur — modèle à
      // tableau fixe de 14 lignes, bénéficiaire non affiché).
      const createdBsps: ApiBsp[] = [];
      const createdForBordereau: Array<{ bsp: ApiBsp; destinataireNom: string }> = [];
      const bspErrors: string[] = [];
      for (const b of payload.beneficiaires) {
        let bsp: ApiBsp;
        try {
          bsp = await createBsp(exitId, {
            service_id: b.serviceId ?? undefined,
            beneficiaire_id: b.beneficiaireId ?? undefined,
            quantiteDemandee: b.quantiteDemandee,
            quantiteAccordee: b.quantiteAccordee,
            quantiteServie: b.quantiteServie,
            piecesJointes: b.pieces.length > 0 ? b.pieces.map((p) => p.file) : undefined,
            piecesJointesNoms: b.pieces.length > 0 ? b.pieces.map((p) => p.nom) : undefined,
          });
          createdBsps.push(bsp);
          createdForBordereau.push({ bsp, destinataireNom: b.destinataireNom });
        } catch (err) {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
          bspErrors.push(msg ?? t("biens.error.unknown"));
          console.error("[bsp] échec de création:", err);
          continue;
        }
        try {
          await generateBspPdf({ reference: bien.reference, nom: bien.nom }, exit.data, bsp, b.destinataireNom);
        } catch (err) {
          bspErrors.push(t("biens.error.bspPdfFailed", { numero: bsp.numero }));
          console.error("[bsp] échec de génération du PDF:", err);
        }
      }
      // Bordereau récapitulatif en plus des PDF individuels — un ou plusieurs BSP.
      if (createdForBordereau.length > 0) {
        try {
          await generateBspBordereauPdf({ reference: bien.reference, nom: bien.nom }, exit.data, createdForBordereau);
        } catch (err) {
          console.error("[bsp] échec de génération du bordereau récapitulatif:", err);
        }
      }
      return { createdCount: createdBsps.length, total: payload.beneficiaires.length, dateSortie: payload.dateSortie, bspErrors };
    },
    onSuccess: ({ createdCount, total, dateSortie, bspErrors }) => {
      // Le bien sort de la liste générale — le backend a enregistré la
      // sortie (AssetExit) et basculé le statut à SORTIS.
      queryClient.invalidateQueries({ queryKey: ["bien", bien.id] });
      queryClient.invalidateQueries({ queryKey: ["biens-list"] });
      queryClient.invalidateQueries({ queryKey: ["asset-exits"] });
      queryClient.invalidateQueries({ queryKey: ["asset-exit", bien.id] });
      queryClient.invalidateQueries({ queryKey: ["bsps"] });
      closeInline();
      if (bspErrors.length > 0) {
        toast.error(t("biens.detail.toast.sortieBspPartial", { date: dateSortie ?? "", created: createdCount, total, errors: bspErrors.join(" ; ") }));
      } else {
        toast.success(t("biens.detail.toast.sortieSuccessDate", { date: dateSortie ?? "" }));
      }
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      console.error("[sortie] erreur:", err);
      let msg = assetExitErrorMessage(err, t("biens.error.sortieGeneric"));
      if (status === 400 && msg === "La validation a échoué") {
        msg = t("biens.error.sortieAlreadyExists");
      }
      toast.error(msg, { duration: 8000 });
    },
  });

  // ── SORTIE (détail) + BSP ────────────────────────────────────────────────
  // Un bien n'a qu'une seule sortie (GET /assets/{id}/exit) ; jusqu'ici cette
  // donnée n'était jamais affichée après création. On l'affiche désormais
  // (section 10) avec la gestion des BSP qui lui sont rattachés.
  const exitQuery = useQuery({
    queryKey: ["asset-exit", bien.id],
    queryFn: () => getAssetExit(bien.id),
    staleTime: 60_000,
  });
  const assetExit: ApiAssetExit | null = exitQuery.data?.data?.sortie ?? null;

  // Le panneau de gestion des BSP après-coup ("Nouveau BSP" dans
  // Affectation > Sortie) a été retiré (rec. 303, 2026-08-21) — un bien
  // n'est plus géré via BSP ici, seul le champ "Remis à" (Donation/Réforme/
  // Vente) subsiste, porté par SortieFormInline. generateBspPdf reste
  // utilisé par ce champ (il crée toujours un BSP en coulisses, seul
  // mécanisme backend existant pour associer une personne précise à une
  // sortie), mais le formulaire BspFormInline et son panneau de liste ont
  // disparu avec leurs mutations dédiées.

    return (
      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Button variant="outline" size="sm" onClick={onBack} className="gap-1">
            <ArrowLeft className="h-4 w-4" /> {t("action.backToList")}
          </Button>
          <div className="flex items-center gap-2">
            {isNotReceived && (
              <Button
                size="sm"
                className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                disabled={acknowledgeDetailMutation.isPending}
                onClick={() => acknowledgeDetailMutation.mutate()}
              >
                <CheckCircle2 className="h-4 w-4" /> {t("biens.detail.acknowledgeReception")}
              </Button>
            )}
            <Button variant="outline" size="sm" className="gap-2"
              onClick={onPrint}>
              <Printer className="h-4 w-4" /> {t("biens.print.printFiche")}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="gap-2" disabled={isNotReceived}>
                  <Download className="h-4 w-4" /> {t("action.export")}
                  <ChevronDown className="h-3.5 w-3.5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel>{t("biens.detail.exportFormat")}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="gap-2" onSelect={() => exportBienPDF({
                  bien: b,
                  affectations: sortedAffectations,
                  maintenances: allMaintenances,
                  reevals: allReevals,
                  deprecs: allDepreciations,
                }, t)}>
                  <FileText className="h-4 w-4" /> PDF
                </DropdownMenuItem>
                <DropdownMenuItem className="gap-2" onSelect={() => exportBienExcel({
                  bien: b,
                  affectations: sortedAffectations,
                  maintenances: allMaintenances,
                  reevals: allReevals,
                  deprecs: allDepreciations,
                }, t)}>
                  <FileSpreadsheet className="h-4 w-4" /> Excel
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button variant="outline" size="sm" className="gap-2 border-destructive/50 text-destructive hover:bg-destructive/10"
              disabled={isNotReceived}
              onClick={() => setDeleteDialogOpen(true)}>
              <Trash2 className="h-4 w-4" /> {t("action.delete")}
            </Button>
          </div>
        </div>

        {isNotReceived && (
          <div className="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            {t("biens.detail.mustAcknowledgeFirst")}
          </div>
        )}

        <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t("biens.list.deleteConfirmTitle")}</AlertDialogTitle>
              <AlertDialogDescription>
                {t("biens.detail.deleteConfirmDesc", { nom: b.nom })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={onDelete}
              >
                {t("action.delete")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        <div className="relative">
        {isNotReceived && (
          <div
            className="absolute inset-0 z-10 cursor-not-allowed"
            title={t("biens.detail.acknowledgeFirstTooltip")}
          />
        )}
        <div className={cn(isNotReceived && "pointer-events-none select-none opacity-50")}>
        <Tabs defaultValue="general" className="w-full">
          <div className="overflow-x-auto border-b border-border pb-2">
            <TabsList className="h-auto min-w-max justify-start bg-transparent p-0">
              <TabsTrigger value="general">{t("biens.detail.tab.general")}</TabsTrigger>
              <TabsTrigger value="affectations">{t("biens.detail.tab.affectations")}</TabsTrigger>
              <TabsTrigger value="fournisseur">{t("biens.detail.tab.financial")}</TabsTrigger>
              <TabsTrigger value="maintenance">{t("biens.block.maintenance")}</TabsTrigger>
              {fullBien?.activeReevaluation && (
                <TabsTrigger value="reevaluations">{t("biens.detail.tab.reevaluations")}</TabsTrigger>
              )}
              {fullBien?.activeDepreciation && (
                <TabsTrigger value="depreciations">{t("biens.detail.tab.depreciations")}</TabsTrigger>
              )}
              {fullBien?.activeAmortissement && (
                <TabsTrigger value="amortissements">{t("biens.amortissement")}</TabsTrigger>
              )}
            </TabsList>
          </div>

        <TabsContent value="general" className="mt-4 space-y-4">
        {/* 1. Informations générales */}
        <Section num={1} title={t("biens.detail.tab.general")}
          action={
            <div className="flex flex-wrap items-center gap-2">
              <MercurialeButton bien={b} />
              <Button variant="outline" size="sm" className="gap-2" onClick={onEdit}><Pencil className="h-4 w-4" /> {t("action.edit")}</Button>
            </div>
          }
        >
          <div className="grid gap-6 md:grid-cols-[1fr_auto]">
            <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
              <KV k={t("biens.list.col.reference")} v={b.reference} />
              <KV k={t("biens.detail.field.nomBien")} v={b.nom} />
              <KV k={t("biens.detail.field.numeroSerie")} v={b.numeroSerie} />
              <KV k={t("biens.field.categorie")} v={b.category?.nom} />
              <KV k={t("biens.list.col.assetType")} v={b.assetType?.nom} />
              <KV k={t("biens.detail.field.etat")} v={b.etatBien?.nom} />
              <KV k={t("common.status")} v={<StatusBadge tone={statutTone(b.statut ?? "")}>{displayStatut(b.statut)}</StatusBadge>} />
              <KV k={t("users.service")} v={b.service?.nom} />
              <KV k={t("biens.field.acquisition")} v={b.dateAcquisition} />
              <KV k={t("biens.field.valeur")} v={formatFCFA(b.valeur)} />
              <KV k={t("biens.detail.field.coutMaintenance")} v={b.coutTotalMaintenance != null ? formatFCFA(b.coutTotalMaintenance) : "—"} />
              <KV k={t("biens.detail.field.sourceFinancementProjet")} v={
                (b.sourceFinancement ?? b.projects?.[0]?.nom)
                  ? <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{b.sourceFinancement ?? b.projects?.[0]?.nom}</span>
                  : "—"
              } />
              {b.description && <KV k={t("common.description")} v={b.description} />}
            </div>
            <div className="w-full max-w-[240px] overflow-hidden rounded-lg border border-border bg-muted md:w-60">
              {b.photos && b.photos.length > 0 ? (
                <img
                  src={resolveFileUrl(b.photos[0].chemin)}
                  alt={t("biens.detail.photoAlt")}
                  className="h-40 w-full object-cover"
                  onError={(e) => { (e.target as HTMLImageElement).style.display = "none"; }}
                />
              ) : (
                <div className="flex h-40 items-center justify-center text-xs text-muted-foreground">{t("biens.detail.photoAlt")}</div>
              )}
            </div>
          </div>
          <div className="mt-4 space-y-2">
            <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3">
              <div>
                <p className="text-sm font-medium text-foreground">{t("biens.detail.reevaluation")}</p>
                <p className="text-xs text-muted-foreground">{t("biens.detail.reevaluationHint")}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">{fullBien?.activeReevaluation ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
                <Switch
                  checked={!!fullBien?.activeReevaluation}
                  disabled={toggleReevaluationMutation.isPending}
                  onCheckedChange={(v) => toggleReevaluationMutation.mutate(v)}
                />
              </div>
            </div>
            <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3">
              <div>
                <p className="text-sm font-medium text-foreground">{t("biens.detail.depreciation")}</p>
                <p className="text-xs text-muted-foreground">{t("biens.detail.depreciationHint")}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">{fullBien?.activeDepreciation ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
                <Switch
                  checked={!!fullBien?.activeDepreciation}
                  disabled={toggleDepreciationMutation.isPending}
                  onCheckedChange={(v) => toggleDepreciationMutation.mutate(v)}
                />
              </div>
            </div>
            <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3">
              <div>
                <p className="text-sm font-medium text-foreground">{t("biens.amortissement")}</p>
                <p className="text-xs text-muted-foreground">{t("biens.detail.amortissementHint")}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">{fullBien?.activeAmortissement ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
                <Switch
                  checked={!!fullBien?.activeAmortissement}
                  disabled={toggleAmortissementMutation.isPending}
                  onCheckedChange={(v) => toggleAmortissementMutation.mutate(v)}
                />
              </div>
            </div>
          </div>

          <PiecesJointesSection
          bienId={b.id}
          piecesJointes={b.piecesJointes ?? []}
          loading={bienLoading && !isFull}
          onDeleted={(pjId) => {
            // Mettre à jour le cache local immédiatement
            const updated = (queryClient.getQueryData<ApiBien>(["bien", b.id]) ?? b);
            queryClient.setQueryData<ApiBien>(["bien", b.id], {
              ...updated,
              piecesJointes: (updated.piecesJointes ?? []).filter((pj) => pj.id !== pjId),
            });
            queryClient.invalidateQueries({ queryKey: ["bien", b.id] });
          }}
          onRenamed={(pjId, newNom) => {
            const updated = (queryClient.getQueryData<ApiBien>(["bien", b.id]) ?? b);
            queryClient.setQueryData<ApiBien>(["bien", b.id], {
              ...updated,
              piecesJointes: (updated.piecesJointes ?? []).map((pj) =>
                pj.id === pjId ? { ...pj, nom: newNom } : pj
              ),
            });
          }}
        />

        {isTerrainOuBatiment(b.category?.nom) && (
        <SubSection title={t("biens.detail.localisation")}
          action={
            inline !== "localisation" ? (
              <Button size="sm" variant="outline" className="gap-2" onClick={() => setInline("localisation")}>
                <Plus className="h-4 w-4" /> {currentLocationQuery.data ? t("biens.detail.editLocalisation") : t("biens.detail.newLocalisation")}
              </Button>
            ) : null
          }
        >
          {inline === "localisation" ? (
            <div className="space-y-3">
              {currentLocationQuery.data && (
                <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs">
                  <span className="rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">{t("biens.detail.currentPosition")}</span>
                  <span className="text-muted-foreground">{currentLocationQuery.data.geometry_type}</span>
                  <LocationSummary location={currentLocationQuery.data} />
                </div>
              )}
              <LocalisationFormInline
                isSavingPoint={createLocationPointMutation.isPending}
                isSavingFile={createLocationFileMutation.isPending}
                onCancel={closeInline}
                onSavePoint={(payload) => createLocationPointMutation.mutate(payload)}
                onSaveFile={(file) => createLocationFileMutation.mutate(file)}
              />
            </div>
          ) : (
            <div className="space-y-2">
              {locationsHistory.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted-foreground">{t("biens.detail.noRecord")}</p>
              ) : (
                <div className="overflow-x-auto rounded-lg border border-border">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/40">
                      <tr>
                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">ID</th>
                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{t("biens.field.latitude")}</th>
                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{t("biens.field.longitude")}</th>
                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{t("biens.detail.geometryType")}</th>
                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{t("biens.detail.geometry")}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {locationsHistory.map((loc, i) => (
                        <tr key={loc.id} className={i === 0 ? "bg-primary/5" : undefined}>
                          <td className="whitespace-nowrap px-3 py-2 text-xs font-mono">
                            {loc.id}
                            {i === 0 && <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">{t("biens.detail.current")}</span>}
                          </td>
                          <td className="whitespace-nowrap px-3 py-2 text-xs tabular-nums">{loc.latitude}</td>
                          <td className="whitespace-nowrap px-3 py-2 text-xs tabular-nums">{loc.longitude}</td>
                          <td className="whitespace-nowrap px-3 py-2 text-xs">{loc.geometry_type ?? "—"}</td>
                          <td className="px-3 py-2 text-xs">
                            {loc.geometry
                              ? <span className="font-mono text-muted-foreground" title={JSON.stringify(loc.geometry)}>{loc.geometry.type} ({JSON.stringify(loc.geometry.coordinates).length} car.)</span>
                              : <span className="text-muted-foreground">—</span>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </SubSection>
        )}
        </Section>
        </TabsContent>

        <TabsContent value="affectations" className="mt-4">
        {/* 2. Affectation / Suivi */}
        <Section num={2} title={t("biens.affectation")}
          action={
            inline === "sortie" || inline === "affectation" ? null : (
              <div className="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" className="gap-2 border-destructive/50 text-destructive hover:bg-destructive/10" onClick={() => setInline("sortie")}>
                  <DoorOpen className="h-4 w-4" /> {assetExit ? t("biens.sortie") : t("biens.detail.sortir")}
                </Button>
                <Button size="sm" className="gap-2" onClick={() => { setEditingAffectation(null); setInline("affectation"); }}>
                  <Plus className="h-4 w-4" /> {t("biens.newAffectation")}
                </Button>
              </div>
            )
          }
        >
          {inline === "sortie" ? (
            assetExit ? (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                  <KV k={t("biens.detail.exitType")} v={assetExit.exitType?.nom ?? "—"} />
                  <KV k={t("biens.detail.motif")} v={assetExit.motifSortie ?? "—"} />
                  <KV k={t("biens.detail.exitDate")} v={assetExit.dateSortie ?? "—"} />
                  <KV k={t("biens.list.col.reference")} v={assetExit.protocoleReference ?? "—"} />
                  <KV k={t("users.service")} v={assetExit.service?.nom ?? "—"} />
                </div>
                {assetExit.observations && (
                  <p className="text-sm text-muted-foreground">{assetExit.observations}</p>
                )}

                <div className="flex justify-end border-t border-border pt-3">
                  <Button variant="outline" size="sm" onClick={closeInline}>{t("biens.detail.close")}</Button>
                </div>
              </div>
            ) : (
              <SortieFormInline
                isSaving={sortieMutation.isPending}
                onCancel={closeInline}
                bienNom={bien.nom}
                bienReference={bien.reference}
                onSave={(payload) => sortieMutation.mutate(payload)}
              />
            )
          ) : inline === "affectation" ? (
            <AffectationFormInline
              isSaving={affectationMutation.isPending || updateAffectationMutation.isPending}
              onCancel={closeInline}
              initial={editingAffectation}
              onSave={(payload: AffectationSubmitPayload) => {
                if (editingAffectation) {
                  updateAffectationMutation.mutate({ id: editingAffectation.id, payload });
                } else {
                  affectationMutation.mutate(payload);
                }
              }}
            />
          ) : (
            <div className="space-y-2">
              {sortedAffectations.length > 0 && (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <span className="h-2 w-2 rounded-full bg-green-500" /> {t("biens.detail.currentHolder")}
                </p>
              )}
              {bien.isRestitue != null && (
                <p className="flex items-center gap-1.5 text-xs">
                  <Undo2 className={cn("h-3.5 w-3.5", bien.isRestitue ? "text-emerald-600" : "text-muted-foreground")} />
                  <span className={bien.isRestitue ? "text-emerald-600" : "text-muted-foreground"}>
                    {bien.isRestitue ? t("biens.detail.restitue") : t("biens.detail.notRestitue")}
                  </span>
                </p>
              )}
              <EventTable
                columns={[t("biens.detail.col.dateAffectation"), t("biens.detail.col.origine"), t("biens.detail.col.destination"), t("biens.detail.col.type"), t("biens.detail.col.responsable"), t("biens.detail.motif"), t("biens.detail.col.auteur")]}
                rows={sortedAffectations.map((a, idx) => {
                  // Origine = structure de l'affectation chronologiquement
                  // précédente (sortedAffectations est trié du plus récent au
                  // plus ancien, donc l'élément suivant dans le tableau est
                  // l'affectation antérieure) — aucun champ "origine" n'existe
                  // côté backend, dérivé ici. Rien pour la toute première
                  // affectation d'un bien (pas d'origine antérieure).
                  const previous = sortedAffectations[idx + 1];
                  const origine = previous ? (previous.service?.nom || "—") : "—";
                  return [
                    a.dateDebut,
                    origine,
                    a.service?.nom || "—",
                    a.typeAffectation ?? "—",
                    a.utilisateur ? `${a.utilisateur.firstName} ${a.utilisateur.lastName}` : "—",
                    a.commentaire ?? "—",
                    a.createdBy ? `${a.createdBy.firstName} ${a.createdBy.lastName}` : "—",
                  ];
                })}
                items={sortedAffectations}
                onEdit={(a) => { setEditingAffectation(a); setInline("affectation"); }}
                onDelete={(a) => deleteAffectationMutation.mutate(a.id)}
                isDeleting={deleteAffectationMutation.isPending}
                highlightFirstRow
                useDropdownActions
                extraMenuItem={(a) => (
                  <DropdownMenuItem onSelect={() => {
                    if (a.utilisateur) {
                      setDetenteurTarget({ id: a.utilisateur.id, nom: `${a.utilisateur.firstName} ${a.utilisateur.lastName}` });
                    } else {
                      toast.error(t("biens.detail.error.notIndividual"));
                    }
                  }}>
                    <UserIcon className="mr-2 h-4 w-4" /> {t("biens.list.detenteurFiche")}
                  </DropdownMenuItem>
                )}
              />
            </div>
          )}
        </Section>
        </TabsContent>

        <TabsContent value="fournisseur" className="mt-4 space-y-4">
        {/* 3-4. Information financière (fournisseur + valeur/coûts/financement) */}
        <Section num={3} title={t("biens.detail.tab.financial")}>
          {bienLoading && !isFull ? (
            <div className="flex items-center gap-2 py-3 text-xs text-muted-foreground"><span className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" /> {t("common.loading")}</div>
          ) : bienError && !isFull ? (
            <p className="py-2 text-xs text-destructive">{t("biens.detail.error.financialLoadFailed")}</p>
          ) : (
            <>
              <SubSection title={t("biens.detail.tab.financial")}>
                <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                  <KV k={t("biens.field.valeur")} v={formatFCFA(b.valeur)} />
                  <KV k={t("biens.detail.field.coutMaintenance")} v={b.coutTotalMaintenance != null ? formatFCFA(b.coutTotalMaintenance) : "—"} />
                  <KV k={t("biens.detail.field.sourceFinancementProjet")} v={
                    (b.sourceFinancement ?? b.projects?.[0]?.nom)
                      ? <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{b.sourceFinancement ?? b.projects?.[0]?.nom}</span>
                      : "—"
                  } />
                  {b.code && <KV k={t("biens.detail.field.codeMercuriale")} v={b.code} />}
                  {b.prixMercurial != null && <KV k={t("biens.detail.field.prixMercuriale")} v={formatFCFA(b.prixMercurial)} />}
                </div>
              </SubSection>

              {!b.fournisseurNom && !b.fournisseurEmail && !b.fournisseurTelephone ? (
                <SubSection title={t("biens.detail.fournisseur")}>
                  <p className="py-2 text-sm text-muted-foreground">{t("biens.detail.noFournisseurInfo")}</p>
                </SubSection>
              ) : (
                <SubSection title={t("biens.detail.fournisseur")}>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <KV k={t("biens.detail.field.raisonSociale")} v={b.fournisseurNom} />
                    <KV k="Email" v={b.fournisseurEmail} />
                    <KV k={t("biens.detail.field.telephone")} v={b.fournisseurTelephone} />
                    <KV k={t("biens.detail.field.typeFournisseur")} v={b.typeFournisseur} />
                    <KV k={t("biens.detail.field.ville")} v={b.fournisseurVille} />
                    <KV k={t("biens.detail.field.pays")} v={b.fournisseurPays} />
                    {b.fournisseurAdresse && <KV k={t("biens.detail.field.adresse")} v={b.fournisseurAdresse} />}
                  </div>
                </SubSection>
              )}
            </>
          )}
        </Section>
        </TabsContent>

        <TabsContent value="maintenance" className="mt-4">
        {/* 5. Maintenance */}
        <Section num={5} title={t("biens.block.maintenance")}
          action={
            inline !== "maintenance" ? (
              <Button size="sm" variant="outline" className="gap-2" onClick={() => { setEditingMaintenance(null); setInline("maintenance"); }}>
                <Plus className="h-4 w-4" /> {t("action.add")}
              </Button>
            ) : null
          }
        >
          {inline === "maintenance" ? (
            <MaintenanceFormInline
              isSaving={maintenanceMutation.isPending || updateMaintenanceMutation.isPending}
              onCancel={closeInline}
              initial={editingMaintenance}
              onSave={(payload) => {
                if (editingMaintenance) {
                  updateMaintenanceMutation.mutate({ id: editingMaintenance.id, payload });
                } else {
                  maintenanceMutation.mutate(payload);
                }
              }}
            />
          ) : (
            <EventTable
              columns={[t("biens.detail.col.dateIntervention"), t("biens.detail.col.recuperation"), t("biens.list.col.etatBien"), t("biens.detail.col.coutFcfa"), t("biens.detail.col.motif"), t("biens.detail.col.observations")]}
              rows={allMaintenances.map((m) => [
                m.dateIntervention,
                m.dateRecuperation ?? "—",
                m.etatBien?.nom ?? m.etat ?? "—",
                formatFCFA(m.cout ?? 0),
                m.motif ?? "—",
                m.observations ?? "—",
              ])}
              items={allMaintenances}
              onEdit={(m) => { setEditingMaintenance(m); setInline("maintenance"); }}
              onDelete={(m) => deleteMaintenanceMutation.mutate(m.id)}
              isDeleting={deleteMaintenanceMutation.isPending}
              // Surligne en rouge les interventions dont le coût dépasse la
              // valeur du bien — signal d'alerte visuel demandé explicitement.
              rowClassName={(m) => (m.cout != null && m.cout > bien.valeur ? "bg-red-100 dark:bg-red-950/40" : undefined)}
            />
          )}
        </Section>
        </TabsContent>

        <TabsContent value="reevaluations" className="mt-4">
        {/* 7. Réévaluations */}
        <Section num={7} title={t("biens.detail.tab.reevaluations")}
          action={
            inline !== "reevaluation" ? (
              <Button size="sm" variant="outline" className="gap-2" onClick={() => { setEditingReeval(null); setInline("reevaluation"); }}>
                <Plus className="h-4 w-4" /> {t("biens.detail.newReeval")}
              </Button>
            ) : null
          }
        >
          {inline === "reevaluation" ? (
            <ReevaluationFormInline
              isSaving={reevalMutation.isPending || updateReevalMutation.isPending}
              onCancel={closeInline}
              initial={editingReeval}
              onSave={(payload) => {
                if (editingReeval) {
                  updateReevalMutation.mutate({ id: editingReeval.id, payload });
                } else {
                  reevalMutation.mutate(payload);
                }
              }}
            />
          ) : (
            <EventTable
              columns={[t("common.date"), t("biens.detail.col.valeurAvant"), t("biens.detail.col.nouvelleValeur"), t("biens.detail.col.methode"), t("users.service"), t("biens.detail.col.motif"), t("biens.detail.col.observations")]}
              rows={allReevals.map((r) => [
                r.dateReevaluation,
                formatFCFA(r.valeurActuelle ?? 0),
                formatFCFA(r.nouvelleValeur),
                r.methodeEvaluation ?? "—",
                r.service?.nom ?? "—",
                r.motif,
                r.observations ?? "—",
              ])}
              items={allReevals}
              onEdit={(r) => { setEditingReeval(r); setInline("reevaluation"); }}
              onDelete={(r) => deleteReevalMutation.mutate(r.id)}
              isDeleting={deleteReevalMutation.isPending}
            />
          )}
        </Section>
        </TabsContent>

        <TabsContent value="depreciations" className="mt-4">
        {/* 8. Dépréciations */}
        <Section num={8} title={t("biens.detail.tab.depreciations")}
          action={
            inline !== "depreciation" ? (
              <Button size="sm" variant="outline" className="gap-2" onClick={() => { setEditingDepreciation(null); setInline("depreciation"); }}>
                <Plus className="h-4 w-4" /> {t("biens.detail.newDeprec")}
              </Button>
            ) : null
          }
        >
          {inline === "depreciation" ? (
            <DepreciationFormInline
              isSaving={deprecMutation.isPending || updateDeprecMutation.isPending}
              onCancel={closeInline}
              initial={editingDepreciation}
              onSave={(payload) => {
                if (editingDepreciation) {
                  updateDeprecMutation.mutate({ id: editingDepreciation.id, payload });
                } else {
                  deprecMutation.mutate(payload);
                }
              }}
            />
          ) : (
            <EventTable
              columns={[t("common.date"), t("biens.detail.col.type"), t("biens.detail.col.methode"), t("biens.detail.col.valeurActuelle"), t("biens.detail.col.montant"), t("biens.detail.col.taux"), t("biens.detail.col.dureeVie"), t("biens.detail.col.motif"), t("biens.detail.col.observations")]}
              rows={allDepreciations.map((d) => [
                d.dateDepreciation,
                d.typeDepreciation ?? "—",
                d.methodeAmortissement ?? "—",
                formatFCFA(d.valeurActuelle ?? 0),
                formatFCFA(d.montantDepreciation ?? 0),
                d.tauxDepreciation != null ? `${d.tauxDepreciation}%` : "—",
                d.dureeVie != null ? `${d.dureeVie}` : "—",
                d.motif,
                d.observations ?? "—",
              ])}
              items={allDepreciations}
              onEdit={(d) => { setEditingDepreciation(d); setInline("depreciation"); }}
              onDelete={(d) => deleteDeprecMutation.mutate(d.id)}
              isDeleting={deleteDeprecMutation.isPending}
            />
          )}
        </Section>
        </TabsContent>

        {fullBien?.activeAmortissement && (
        <TabsContent value="amortissements" className="mt-4">
        {/* 9. Amortissement comptable */}
        <Section num={9} title={t("biens.detail.amortissementComptable")}>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.field.valeur")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.valeurAcquisition != null
                    ? formatFCFA(fullBien.amortissement.valeurAcquisition)
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.valeurActuelle")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.valeurActuelle != null
                    ? formatFCFA(fullBien.amortissement.valeurActuelle)
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.amortAnnuel")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.amortissementAnnuel != null
                    ? formatFCFA(fullBien.amortissement.amortissementAnnuel)
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.amortCumule")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.amortissementCumule != null
                    ? formatFCFA(fullBien.amortissement.amortissementCumule)
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.dureeVieAns")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.dureeVie != null
                    ? t("biens.detail.nAns", { n: fullBien.amortissement.dureeVie })
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.tauxAmort")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.taux != null
                    ? `${fullBien.amortissement.taux} %`
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.anneesEcoulees")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.anneesEcoulees != null
                    ? t("biens.detail.nAnsParenth", { n: fullBien.amortissement.anneesEcoulees })
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-muted/30 p-4">
                <p className="text-xs text-muted-foreground">{t("biens.detail.dateAcquisitionCompta")}</p>
                <p className="mt-1 text-base font-semibold text-foreground">
                  {fullBien.amortissement?.dateAcquisition ?? "—"}
                </p>
              </div>
            </div>
        </Section>
        </TabsContent>
        )}

        </Tabs>
        </div>
        </div>

        {/* Recommandation 90 — fiche détenteur */}
        <FicheDetenteurDialog
          userId={detenteurTarget?.id ?? null}
          userName={detenteurTarget?.nom ?? ""}
          open={detenteurTarget !== null}
          onClose={() => setDetenteurTarget(null)}
          contextBien={b}
        />
      </div>
    );
  }

  /* =========================================================
    BienFormPage — Formulaire création / modification
    Utilise les vraies données des API (catégories, types, états, services, projets)
    ========================================================= */

  function BienFormPage({
    mode, bien, isSaving, onCancel, onSave,
  }: {
    mode: "create" | "edit";
    bien: ApiBien | null;
    isSaving: boolean;
    onCancel: () => void;
    onSave: (form: BienFormState) => void;
  }) {
    const t = useT();
    // ── Chargement des données de référence ───────────────────────────────────
    const { data: categoriesData } = useQuery({
      queryKey: ["categories"],
      queryFn: () => listCategories({ limit: 200 }),
      staleTime: 300_000,
    });
    const categories = categoriesData?.data?.data ?? [];

    const { data: assetTypesData } = useQuery({
      queryKey: ["asset-types", "all"],
      queryFn: () => listAssetTypes({ limit: 1000, include_deleted: false }),
      staleTime: Infinity,
    });
    const allAssetTypes = assetTypesData?.data?.data ?? [];

    // Recommandation 6.a — champs personnalisés (avec leurs catégories associées)
    const { data: champsData } = useQuery({
      queryKey: ["champs", false],
      queryFn: () => listChamps({ page: 1, limit: 200, is_delete: false }),
      staleTime: 300_000,
    });
    const allChamps: ApiChamp[] = champsData?.data?.data ?? [];

    // Recommandation 6.a — sous-types de biens (liés aux types via asset_type_id)
    const { data: subtypesData } = useQuery({
      queryKey: ["asset-sub-types", "all"],
      queryFn: () => listAssetSubtypes({ page: 1, limit: 1000 }),
      staleTime: Infinity,
    });
    const allSubtypes: ApiAssetSubtype[] = subtypesData?.data?.data ?? [];

    const { data: etatBiensData } = useQuery({
      queryKey: ["etat-biens"],
      queryFn: () => listEtatBiens({ limit: 200 }),
      staleTime: 300_000,
    });
    const etatBiens = useMemo(() => etatBiensData?.data?.data ?? [], [etatBiensData]);

    const { data: projectsData } = useQuery({
      queryKey: ["projects"],
      queryFn: () => listProjects({ limit: 200 }),
      staleTime: 300_000,
    });
    const projects = projectsData?.data ?? [];

    // Utilisateur de restitution par défaut — même cache que BiensList
    // (queryKey partagée : pas de requête supplémentaire si déjà chargée).
    const { data: usersCacheForEnrich = [] } = useQuery({
      queryKey: ["users-all"],
      queryFn: listAllUsers,
      staleTime: 300_000,
      refetchOnWindowFocus: false,
    });

    // ── État du formulaire ────────────────────────────────────────────────────
    const buildFormFromBien = (b: ApiBien): BienFormState => ({
      nom: b.nom ?? "",
      numeroSerie: b.numeroSerie ?? "",
      description: b.description ?? "",
      dateAcquisition: b.dateAcquisition ?? "",
      valeur: String(b.valeur ?? ""),
      sourceFinancement: b.sourceFinancement ?? "",
      sourceFinancementExercice: projects.find((p) => p.nom === b.sourceFinancement)?.exercice ?? null,
      modeAcquisition: b.modeAcquisition ?? "",
      statut: b.statut ?? "ACTIF",
      code: b.code ?? "",
      prixMercurial: b.prixMercurial != null ? String(b.prixMercurial) : "",
      typeFournisseur: b.typeFournisseur ?? "ENTREPRISE",
      fournisseurNom: b.fournisseurNom ?? "",
      fournisseurEmail: b.fournisseurEmail ?? "",
      fournisseurTelephone: b.fournisseurTelephone ?? "",
      fournisseurAdresse: b.fournisseurAdresse ?? "",
      fournisseurVille: b.fournisseurVille ?? "",
      fournisseurPays: b.fournisseurPays ?? "Cameroun",
      category_id: b.category?.id ?? null,
      asset_type_id: b.assetType?.id ?? null,
      asset_sub_type_id: b.assetSubType?.id ?? null,
      etat_bien_id: b.etatBien?.id ?? null,
      service_id: b.service?.id ?? null,
      user_id: b.utilisateur?.id ?? null,
      user_restitution_id: b.userRestitution?.id ?? null,
      service_nom: b.service?.nom ?? "",
      matricule_nom: b.utilisateur
        ? `${b.utilisateur.matricule ?? ""} — ${b.utilisateur.firstName} ${b.utilisateur.lastName}`
        : "",
      category_nom: b.category?.nom ?? "",
      asset_type_nom: b.assetType?.nom ?? "",
      asset_sub_type_nom: b.assetSubType?.nom ?? "",
      etat_bien_nom: b.etatBien?.nom ?? "",
      project_ids: b.projects?.map((p) => p.id) ?? [],
      champsValues: b.champsValues ?? {},
      champsValueIds: b.champsValueIds ?? {},
      photosFiles: [],
      photoPreview: b.photos?.[0]?.chemin ? resolveFileUrl(b.photos[0].chemin) : null,
      piecesJointes: [],
      latitude: "", longitude: "",
      locationMode: "manual",
      locationFile: null,
      activeReevaluation: b.activeReevaluation ?? true,
      activeDepreciation: b.activeDepreciation ?? true,
      activeAmortissement: b.activeAmortissement ?? true,
      securityModes: new Set<SecurityMode>(),
    });

    const [form, setForm] = useState<BienFormState>(() =>
      mode === "edit" && bien ? buildFormFromBien(bien) : emptyForm
    );

    // Re-sync when bien prop changes (e.g. after full data loads in parent)
    useEffect(() => {
      if (mode === "edit" && bien) {
        setForm(buildFormFromBien(bien));
      }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bien?.id, mode]);

    // Recommandation 4 : à la création, l'état par défaut est "Neuf"
    // (ou à défaut l'état avec le plus petit numéro d'ordre).
    useEffect(() => {
      if (mode !== "create" || etatBiens.length === 0) return;
      setForm((f) => {
        if (f.etat_bien_id != null) return f;
        const neuf = etatBiens.find((e) => /neuf/i.test(e.nom))
          ?? [...etatBiens].sort((a, b) => (a.numeroOrdre ?? 999) - (b.numeroOrdre ?? 999))[0];
        return neuf ? { ...f, etat_bien_id: neuf.id, etat_bien_nom: neuf.nom } : f;
      });
    }, [mode, etatBiens]);

    // Localisation en modification — préremplie en lisant la position actuelle
    // (GET /assets/{id} ne l'embarque pas) ; l'enregistrement, lui, se fait
    // avec le reste du bien via POST /assets/{id} (latitude/longitude),
    // pas via un appel séparé.
    const queryClient = useQueryClient();
    const currentLocationQuery = useQuery({
      queryKey: ["asset-location-current", bien?.id],
      queryFn: () => getCurrentLocation(bien!.id),
      enabled: mode === "edit" && !!bien?.id,
    });
    useEffect(() => {
      if (currentLocationQuery.data) {
        setForm((f) => ({
          ...f,
          latitude: String(currentLocationQuery.data!.latitude),
          longitude: String(currentLocationQuery.data!.longitude),
        }));
      }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentLocationQuery.data]);

    const [openSection, setOpenSection] = useState(1);
    const [mercurialeOpen, setMercurialeOpen] = useState(false);

    const set = <K extends keyof BienFormState>(k: K, v: BienFormState[K]) =>
      setForm((f) => ({ ...f, [k]: v }));

    // Types de biens filtrés par catégorie sélectionnée
    const filteredAssetTypes = form.category_id
      ? allAssetTypes.filter((at) => at.category_id === form.category_id)
      : allAssetTypes;

    // Recommandation 6.a — champs personnalisés associés à la catégorie choisie.
    // Un champ porte sa liste de catégories (champ.categories) ; on retient ceux
    // dont au moins une catégorie correspond à la catégorie du bien.
    const champsForCategory = form.category_id
      ? allChamps.filter((c) => (c.categories ?? []).some((cat) => cat.id === form.category_id))
      : [];

    // Les champs géographiques liés (région/département/arrondissement)
    // doivent s'afficher à la suite les uns des autres, dans cet ordre
    // hiérarchique — peu importe l'ordre renvoyé par l'API. Purement
    // frontend : pas besoin d'un numéro d'ordre côté backend, le sous-type
    // suffit à identifier chaque niveau (un seul champ par sous-type et par
    // catégorie, en pratique).
    const GEO_SUBTYPE_RANK: Partial<Record<ChampSubtype, number>> = { region: 0, departement: 1, arrondissement: 2 };
    const orderedChampsForCategory = useMemo(() => {
      const isGeo = (c: ApiChamp) => c.type === "select" && c.subtype != null && c.subtype in GEO_SUBTYPE_RANK;
      const geoFields = champsForCategory
        .filter(isGeo)
        .sort((a, b) => (GEO_SUBTYPE_RANK[a.subtype!] ?? 0) - (GEO_SUBTYPE_RANK[b.subtype!] ?? 0));
      if (geoFields.length <= 1) return champsForCategory;
      let inserted = false;
      const result: ApiChamp[] = [];
      for (const c of champsForCategory) {
        if (isGeo(c)) {
          if (!inserted) { result.push(...geoFields); inserted = true; }
          continue;
        }
        result.push(c);
      }
      return result;
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [champsForCategory]);

    // Champs personnalisés de type select+région/département/arrondissement —
    // options issues de la Cartographie existante (pas de saisie manuelle).
    const needsCartographie = champsForCategory.some(
      (c) => c.type === "select" && (c.subtype === "region" || c.subtype === "departement" || c.subtype === "arrondissement" || c.subtype === "cartographie"),
    );
    const { data: champCartoData } = useQuery({
      queryKey: ["cartographie"],
      queryFn: () => getCartographie(),
      enabled: needsCartographie,
      staleTime: Infinity,
    });
    const cartoRegions: CartographieRegion[] = champCartoData?.data ?? [];
    const cartoDepartements = useMemo(
      () => cartoRegions.flatMap((r) => r.departements),
      [cartoRegions],
    );
    const cartoArrondissements = useMemo(
      () => cartoDepartements.flatMap((d) => d.arrondissements),
      [cartoDepartements],
    );

    // Seuil de maintenance de la catégorie choisie — affiché en indication
    // sous le select Catégorie (Administration > Catégories de biens).
    const { data: categoryThresholdsData } = useQuery({
      queryKey: ["category-thresholds"],
      queryFn: () => listCategoryThresholds({ limit: 500 }),
      staleTime: 300_000,
    });
    const currentCategoryThreshold = form.category_id != null
      ? categoryThresholdsData?.data?.data.find((c) => c.id === form.category_id)?.seuil ?? null
      : null;

    // États de bien autorisés pour le type de bien sélectionné — via
    // GET /asset-types/{id}/etat-biens, plutôt qu'un filtrage client sur
    // etatBiens[].assetTypes (qui pouvait être incomplet/obsolète).
    const { data: assetTypeEtatBiensData, isLoading: etatBiensForTypeLoading } = useQuery({
      queryKey: ["asset-type-etat-biens", form.asset_type_id],
      queryFn: () => getEtatBiensForAssetType(form.asset_type_id!),
      enabled: form.asset_type_id != null,
    });
    const etatBiensForType = assetTypeEtatBiensData?.data ?? [];

    // Auto-sélection du premier état disponible dès que la liste du type
    // choisi arrive — seulement si aucun état n'est déjà renseigné (ne
    // touche pas à l'état existant en édition).
    useEffect(() => {
      if (form.etat_bien_id != null) return;
      if (etatBiensForType.length === 0) return;
      const choisi = etatBiensForType[0];
      set("etat_bien_id", choisi.id);
      set("etat_bien_nom", choisi.nom);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [etatBiensForType]);

    // Point(s) extraits d'un fichier cartographique importé — connus
    // seulement APRÈS envoi au serveur (l'extraction se fait côté backend,
    // impossible à prévisualiser avant). En édition, le bien existe déjà :
    // on importe immédiatement à la sélection du fichier pour pouvoir
    // afficher le résultat sur la carte tout de suite (voir uploadLocationFileMutation).
    const [uploadedFileLocations, setUploadedFileLocations] = useState<ApiAssetLocation[] | null>(null);
    // Aperçu client (best-effort) du point extrait d'un fichier importé en
    // CRÉATION — voir parseLocationFileClientSide. En édition, l'import est
    // immédiat et le résultat serveur (uploadedFileLocations) fait foi.
    const [clientPreviewLocation, setClientPreviewLocation] = useState<{ latitude: number; longitude: number } | null>(null);
    const uploadLocationFileMutation = useMutation({
      mutationFn: (file: File) => createLocationFile(bien!.id, file),
      onSuccess: (res) => {
        setUploadedFileLocations(res.data);
        set("locationFile", null);
        toast.success(t("biens.form.locationsImported", { count: res.data.length }));
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
        toast.error(msg ?? t("biens.form.locationImportFailed"));
      },
    });

    const setChampValue = (champId: number, v: string) =>
      set("champsValues", { ...form.champsValues, [champId]: v });

    // Trouve, parmi les champs de la catégorie, celui portant ce sous-type
    // (région/département/arrondissement) et renvoie sa valeur actuellement
    // saisie — utilisé pour filtrer les niveaux géographiques liés entre eux.
    const findChampBySubtype = (subtype: ChampSubtype) =>
      champsForCategory.find((c) => c.type === "select" && c.subtype === subtype);
    const findChampValueBySubtype = (subtype: ChampSubtype): string | null => {
      const champ = findChampBySubtype(subtype);
      return champ ? (form.champsValues[champ.id] || null) : null;
    };

    // Sélectionner une région/un département doit réinitialiser les niveaux
    // géographiques en dessous (sinon un couple région/département
    // incohérent pourrait rester enregistré).
    const setGeoChampValue = (champ: ApiChamp, v: string) => {
      const updated = { ...form.champsValues, [champ.id]: v };
      if (champ.subtype === "region") {
        const dept = findChampBySubtype("departement");
        const arrond = findChampBySubtype("arrondissement");
        if (dept) delete updated[dept.id];
        if (arrond) delete updated[arrond.id];
      } else if (champ.subtype === "departement") {
        const arrond = findChampBySubtype("arrondissement");
        if (arrond) delete updated[arrond.id];
      }
      set("champsValues", updated);
    };

    // Rendu du contrôle de saisie d'un champ personnalisé — dépend de son
    // type/sous-type (Administration > Champs personnalisés).
    function renderChampValueInput(champ: ApiChamp) {
      const value = form.champsValues[champ.id] ?? "";
      const placeholder = t("biens.form.champPlaceholder", { nom: champ.nom.toLowerCase() });

      if (champ.type === "textarea") {
        return <Textarea value={value} onChange={(e) => setChampValue(champ.id, e.target.value)} placeholder={placeholder} rows={3} />;
      }
      if (champ.type === "number") {
        return <Input type="number" value={value} onChange={(e) => setChampValue(champ.id, e.target.value)} placeholder="0" />;
      }
      if (champ.type === "file") {
        return (
          <Input
            type="file"
            onChange={(e) => setChampValue(champ.id, e.target.files?.[0]?.name ?? "")}
          />
        );
      }
      if (champ.type === "date") {
        return <Input type="date" value={value} onChange={(e) => setChampValue(champ.id, e.target.value)} />;
      }
      if (champ.type === "select") {
        if (champ.subtype === "boolean") {
          return (
            <Select value={value} onValueChange={(v) => setChampValue(champ.id, v)}>
              <SelectTrigger><SelectValue placeholder={t("biens.form.select")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="true">{t("biens.form.yes")}</SelectItem>
                <SelectItem value="false">{t("biens.form.no")}</SelectItem>
              </SelectContent>
            </Select>
          );
        }
        if (champ.subtype === "single") {
          const opts = champ.option ? champ.option.split(",").map((s) => s.trim()).filter(Boolean) : [];
          return (
            <Select value={value} onValueChange={(v) => setChampValue(champ.id, v)} disabled={opts.length === 0}>
              <SelectTrigger><SelectValue placeholder={opts.length ? t("biens.form.select") : t("biens.form.noOptionDefined")} /></SelectTrigger>
              <SelectContent>
                {opts.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}
              </SelectContent>
            </Select>
          );
        }
        if (champ.subtype === "region" || champ.subtype === "departement" || champ.subtype === "arrondissement") {
          // Niveaux liés : département filtré par la région choisie, arrondissement
          // par le département choisi. Tant que le niveau parent n'est pas
          // renseigné, la liste complète reste proposée (pas de blocage).
          let list: Array<{ id: number; nom: string }> = cartoRegions;
          if (champ.subtype === "departement") {
            const regionNom = findChampValueBySubtype("region");
            const region = regionNom ? cartoRegions.find((r) => r.nom === regionNom) : null;
            list = region ? region.departements : cartoDepartements;
          } else if (champ.subtype === "arrondissement") {
            const deptNom = findChampValueBySubtype("departement");
            const dept = deptNom ? cartoDepartements.find((d) => d.nom === deptNom) : null;
            list = dept ? dept.arrondissements : cartoArrondissements;
          }
          return (
            <Select value={value} onValueChange={(v) => setGeoChampValue(champ, v)}>
              <SelectTrigger><SelectValue placeholder={t("biens.form.select")} /></SelectTrigger>
              <SelectContent>
                {list.map((item) => <SelectItem key={item.id} value={item.nom}>{item.nom}</SelectItem>)}
              </SelectContent>
            </Select>
          );
        }
        if (champ.subtype === "structure") {
          return (
            <PosteOrgSelect
              value={null}
              valueLabel={value}
              onSelect={(node) => setChampValue(champ.id, node.nom)}
              onClear={() => setChampValue(champ.id, "")}
              placeholder={t("biens.form.selectStructure")}
              searchPlaceholder={t("biens.list.filter.searchStructure")}
            />
          );
        }
        if (champ.subtype === "cartographie") {
          // Un seul champ, organigramme dépliable Région > Département >
          // Arrondissement (même pattern que OrgTreeSelect) — seul
          // l'arrondissement (dernier niveau) est sélectionnable et devient
          // la valeur enregistrée (à l'inverse des sous-types region/
          // departement/arrondissement, qui sont trois champs distincts).
          return <CartographieTreeSelect value={value} regions={cartoRegions} onChange={(v) => setChampValue(champ.id, v)} />;
        }
      }
      // Texte simple (type="text") et select+"single" (générique) — champ libre.
      return <Input value={value} onChange={(e) => setChampValue(champ.id, e.target.value)} placeholder={placeholder} />;
    }

    // Recommandation 6.a — sous-types associés au type de bien choisi.
    const subtypesForType = form.asset_type_id
      ? allSubtypes.filter((st) => st.asset_type_id === form.asset_type_id)
      : [];

    // Sélection d'une structure de type Poste — identifie directement le
    // responsable (utilisateur rattaché au poste) sans champ Matricule séparé.
    const selectPoste = (node: ApiOrgNode) => {
      set("service_id", node.id);
      set("service_nom", formatPosteLabel(node));
      set("user_id", node.utilisateur?.id ?? null);
      set("matricule_nom", "");
    };
    const clearPoste = () => {
      set("service_id", null);
      set("service_nom", "");
      set("user_id", null);
      set("matricule_nom", "");
    };

    // ── Gestion photo ─────────────────────────────────────────────────────────
    const onPickPhoto = (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        set("photoPreview", String(reader.result));
        set("photosFiles", [file]);
      };
      reader.readAsDataURL(file);
    };

    // ── Gestion pièces jointes ────────────────────────────────────────────────
    const onPickPiece = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      const newPieces = files.map((file) => ({ file, libelle: file.name }));
      set("piecesJointes", [...form.piecesJointes, ...newPieces]);
      e.target.value = "";
    };

    // ── Validation et soumission ──────────────────────────────────────────────
    const submit = () => {
      const required: [keyof BienFormState, string, number][] = [
        ["nom", t("biens.detail.field.nomBien"), 1],
        ["dateAcquisition", t("biens.field.acquisition"), 1],
        ["category_id", t("biens.field.categorie"), 1],
        ["asset_type_id", t("biens.list.col.assetType"), 1],
        ["etat_bien_id", t("biens.list.col.etatBien"), 1],
        ["fournisseurNom", t("biens.form.fournisseurNom"), 2],
        ["fournisseurEmail", t("biens.form.fournisseurEmail"), 2],
        ["fournisseurTelephone", t("biens.form.fournisseurTelephone"), 2],
        ["valeur", t("biens.form.valeurBien"), 3],
        ["sourceFinancement", t("biens.list.col.sourceFinancement"), 3],
      ];
      if (!form.service_id) {
        toast.error(t("biens.form.error.serviceRequired"));
        setOpenSection(1);
        return;
      }
      for (const [k, label, section] of required) {
        const v = form[k];
        if (v === null || v === undefined || String(v).trim() === "" || v === 0) {
          toast.error(t("biens.form.error.fieldRequired", { label }));
          setOpenSection(section);
          return;
        }
      }
      if (isTerrainOuBatiment(form.category_nom) && form.locationMode === "file") {
        // En édition, le fichier est déjà importé immédiatement à la
        // sélection (voir uploadLocationFileMutation) — form.locationFile
        // est alors vidé ; uploadedFileLocations atteste que l'import a eu lieu.
        if (!form.locationFile && uploadedFileLocations == null) {
          toast.error(t("biens.form.error.locationFileRequired"));
          setOpenSection(2);
          return;
        }
      } else if (isTerrainOuBatiment(form.category_nom)) {
        const hasLat = form.latitude.trim() !== "";
        const hasLng = form.longitude.trim() !== "";
        if (hasLat !== hasLng) {
          toast.error(t("biens.form.error.latLngTogether"));
          setOpenSection(2);
          return;
        }
        if (hasLat && hasLng) {
          const lat = Number(form.latitude);
          const lng = Number(form.longitude);
          if (Number.isNaN(lat) || lat < -90 || lat > 90) {
            toast.error(t("biens.form.error.latInvalid"));
            setOpenSection(2);
            return;
          }
          if (Number.isNaN(lng) || lng < -180 || lng > 180) {
            toast.error(t("biens.form.error.lngInvalid"));
            setOpenSection(2);
            return;
          }
        }
      }
      onSave(form);
    };

    const toggleSection = (n: number) => setOpenSection((s) => (s === n ? 0 : n));

    return (
      <div className="mx-auto w-full max-w-5xl space-y-4">
        <div className="flex items-center gap-3">
          <Button variant="outline" size="sm" onClick={onCancel} className="gap-1">
            <ArrowLeft className="h-4 w-4" /> {t("action.back")}
          </Button>
        </div>

        <div className="rounded-xl border border-border bg-card shadow-sm">
          <div className="flex items-start gap-3 border-b border-border p-5">
            <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
              <Package className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <h2 className="text-base font-bold uppercase tracking-wide text-primary">
                {mode === "edit" ? t("biens.edition") : t("biens.form.newBien")}
              </h2>
              <p className="text-xs text-muted-foreground">
                {mode === "edit" ? t("biens.form.updateInfo") : t("biens.form.saveNewBien")}
              </p>
            </div>
          </div>

          {/* Activation réévaluation / dépréciation / amortissement — actifs par
              défaut, rebasculables ensuite via POST /assets/{id}/reevaluation,
              /depreciation, /amortissement. */}
          <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
            <div>
              <p className="text-sm font-medium text-foreground">{t("biens.detail.reevaluation")}</p>
              <p className="text-xs text-muted-foreground">{t("biens.detail.reevaluationHint")}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground">{form.activeReevaluation ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
              <Switch checked={form.activeReevaluation} onCheckedChange={(v) => set("activeReevaluation", v)} />
            </div>
          </div>
          <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
            <div>
              <p className="text-sm font-medium text-foreground">{t("biens.detail.depreciation")}</p>
              <p className="text-xs text-muted-foreground">{t("biens.detail.depreciationHint")}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground">{form.activeDepreciation ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
              <Switch checked={form.activeDepreciation} onCheckedChange={(v) => set("activeDepreciation", v)} />
            </div>
          </div>
          <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
            <div>
              <p className="text-sm font-medium text-foreground">{t("biens.amortissement")}</p>
              <p className="text-xs text-muted-foreground">{t("biens.detail.amortissementHint")}</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground">{form.activeAmortissement ? t("biens.detail.enabled") : t("biens.detail.disabled")}</span>
              <Switch checked={form.activeAmortissement} onCheckedChange={(v) => set("activeAmortissement", v)} />
            </div>
          </div>

          {/* Sécurisation à la création — un bien peut être sécurisé
              juridiquement ET physiquement (les deux cases sont cochables en
              même temps). Uniquement à la création : la modification passe
              par le flux dédié (bouton "Sécuriser" du tableau des biens). */}
          {mode === "create" && (
            <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-3">
              <div>
                <p className="text-sm font-medium text-foreground">{t("biens.list.filter.securisation")}</p>
                <p className="text-xs text-muted-foreground">{t("biens.form.securisationHint")}</p>
              </div>
              <div className="flex items-center gap-4">
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-primary"
                    checked={form.securityModes.has("Physique")}
                    onChange={(e) => {
                      const next = new Set(form.securityModes);
                      e.target.checked ? next.add("Physique") : next.delete("Physique");
                      set("securityModes", next);
                    }}
                  />
                  {t("biens.securisation.mode.physique")}
                </label>
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-primary"
                    checked={form.securityModes.has("Juridique")}
                    onChange={(e) => {
                      const next = new Set(form.securityModes);
                      e.target.checked ? next.add("Juridique") : next.delete("Juridique");
                      set("securityModes", next);
                    }}
                  />
                  {t("biens.securisation.mode.juridique")}
                </label>
              </div>
            </div>
          )}

          <div className="px-5">

            {/* ─── Section 1 : Informations générales ─── */}
            <AccordionSection index={1} title={t("biens.detail.tab.general")} open={openSection === 1} onToggle={() => toggleSection(1)}>
              <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div className="grid flex-1 gap-4 sm:grid-cols-2">

                  <div className="space-y-1.5">
                    <ReqLabel required>{t("biens.detail.field.nomBien")}</ReqLabel>
                    <Input value={form.nom} onChange={(e) => set("nom", e.target.value)} placeholder={t("biens.form.nomBienPlaceholder")} />
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel>{t("biens.detail.field.numeroSerie")}</ReqLabel>
                    <Input value={form.numeroSerie} onChange={(e) => set("numeroSerie", e.target.value)} placeholder={t("biens.form.numeroSeriePlaceholder")} />
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel required>{t("biens.field.categorie")}</ReqLabel>
                    <SearchableSelect
                      value={form.category_id}
                      onChange={(categoryId) => {
                        const category = categories.find((c) => c.id === categoryId);
                        set("category_id", categoryId);
                        set("category_nom", category?.nom ?? "");
                        set("asset_type_id", null);
                        set("asset_type_nom", "");
                        // Recommandation 6.a — la catégorie change : les champs
                        // personnalisés affichés ne sont plus les mêmes, et le type
                        // (donc le sous-type) est réinitialisé.
                        set("asset_sub_type_id", null);
                        set("asset_sub_type_nom", "");
                        set("champsValues", {});
                      }}
                      options={categories.map((c) => ({ value: c.id, label: c.nom }))}
                      placeholder={t("biens.form.selectCategory")}
                      searchPlaceholder={t("biens.list.filter.searchCategory")}
                    />
                    {form.category_id != null && currentCategoryThreshold != null && (
                      <p className="text-[10px] text-muted-foreground">
                        {t("biens.form.maintenanceThreshold", { value: currentCategoryThreshold })}
                      </p>
                    )}
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel required>{t("biens.list.col.assetType")}</ReqLabel>
                    <SearchableSelect
                      value={form.asset_type_id}
                      onChange={(assetTypeId) => {
                        const assetType = allAssetTypes.find((t) => t.id === assetTypeId);
                        set("asset_type_id", assetTypeId);
                        set("asset_type_nom", assetType?.nom ?? "");
                        // Recommandation 6.a — le type change : le sous-type choisi
                        // n'est plus forcément valide, on le réinitialise.
                        set("asset_sub_type_id", null);
                        set("asset_sub_type_nom", "");
                        // Le type change : l'état choisi n'est plus forcément
                        // valide pour ce type — on le réinitialise. La liste
                        // des états autorisés (GET /asset-types/{id}/etat-biens)
                        // se recharge automatiquement et présélectionne le
                        // premier état dès qu'elle arrive (voir useEffect ci-dessus).
                        set("etat_bien_id", null);
                        set("etat_bien_nom", "");
                      }}
                      options={filteredAssetTypes.map((t) => ({ value: t.id, label: t.nom }))}
                      placeholder={t("biens.form.selectType")}
                      searchPlaceholder={t("biens.form.searchType")}
                    />
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel required>{t("biens.list.col.etatBien")}</ReqLabel>
                    <SearchableSelect
                      value={form.etat_bien_id}
                      onChange={(etatId) => {
                        const source = form.asset_type_id ? etatBiensForType : etatBiens;
                        const etat = source.find((e) => e.id === etatId);
                        set("etat_bien_id", etatId);
                        set("etat_bien_nom", etat?.nom ?? "");
                      }}
                      options={(() => {
                        // États autorisés pour le type de bien sélectionné —
                        // GET /asset-types/{id}/etat-biens. Sans type
                        // sélectionné, la liste complète est proposée. La
                        // réforme ne se déclenche jamais directement depuis ce
                        // formulaire (création OU édition) : elle passe toujours
                        // par la demande de réforme (bouton dédié sur le tableau
                        // des biens), qui exige une validation avant que l'état
                        // ne change réellement.
                        const source = form.asset_type_id ? etatBiensForType : etatBiens;
                        return source.map((e) => {
                          const isReforme = /réform|reforme/i.test(e.nom);
                          return {
                            value: e.id,
                            label: `${e.nom}${isReforme ? ` (${t("biens.form.goesThroughReforme")})` : ""}`,
                            disabled: isReforme,
                          };
                        });
                      })()}
                      placeholder={
                        form.asset_type_id
                          ? t("biens.form.selectEtatForType")
                          : t("biens.form.selectEtat")
                      }
                      searchPlaceholder={t("biens.list.filter.searchEtat")}
                    />
                    {form.asset_type_id && !etatBiensForTypeLoading && (
                      <p className={cn("text-[10px]", etatBiensForType.length === 0 ? "text-amber-600" : "text-muted-foreground")}>
                        {etatBiensForType.length === 0
                          ? t("biens.form.noEtatForType")
                          : t("biens.form.etatsAvailable", { count: etatBiensForType.length })}
                      </p>
                    )}
                  </div>

                  {/* Sous-type de bien : affiché dès qu'un type est sélectionné */}
                  {form.asset_type_id && (
                    <div className="space-y-1.5">
                      <ReqLabel>{t("biens.form.sousTypeBien")}</ReqLabel>
                      <SearchableSelect
                        value={form.asset_sub_type_id}
                        onChange={(subId) => {
                          const sub = subtypesForType.find((s) => s.id === subId);
                          set("asset_sub_type_id", subId);
                          set("asset_sub_type_nom", sub?.nom ?? "");
                        }}
                        options={subtypesForType.map((s) => ({ value: s.id, label: s.nom }))}
                        placeholder={subtypesForType.length === 0 ? t("biens.form.noSousTypeAvailable") : t("biens.form.selectSousType")}
                        searchPlaceholder={t("biens.form.searchSousType")}
                        disabled={subtypesForType.length === 0}
                      />
                      {subtypesForType.length === 0 ? (
                        <p className="text-[10px] text-muted-foreground">
                          {t("biens.form.noSousTypeDefined")}
                        </p>
                      ) : (
                        <p className="text-[10px] text-muted-foreground">
                          {t("biens.form.sousTypesAvailable", { count: subtypesForType.length })}
                        </p>
                      )}
                    </div>
                  )}

                  <div className="space-y-1.5 sm:col-span-2">
                    <ReqLabel>{t("biens.form.structureServicePoste")}</ReqLabel>
                    <PosteOrgSelect
                      value={form.service_id}
                      valueLabel={form.service_nom}
                      onSelect={selectPoste}
                      onClear={clearPoste}
                      selectableType="Poste"
                      placeholder={t("biens.form.selectPoste")}
                      searchPlaceholder={t("biens.form.searchPoste")}
                    />
                  </div>

                  <div className="space-y-1.5 sm:col-span-2">
                    <ReqLabel>{t("biens.form.userRestitution")}</ReqLabel>
                    <SearchableSelect
                      value={form.user_restitution_id}
                      onChange={(v) => set("user_restitution_id", v)}
                      options={usersCacheForEnrich.map((u) => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))}
                      placeholder={t("biens.restitution.selectUser")}
                      searchPlaceholder={t("biens.restitution.searchUser")}
                    />
                    <p className="text-[10px] text-muted-foreground">{t("biens.form.userRestitutionHint")}</p>
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel required>{t("biens.field.acquisition")}</ReqLabel>
                    <div className="relative">
                      <Input type="date" value={form.dateAcquisition} onChange={(e) => set("dateAcquisition", e.target.value)} />
                      <CalendarIcon className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <ReqLabel>{t("common.status")}</ReqLabel>
                    <Select value={form.statut} onValueChange={(v) => set("statut", v)}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {statutsBien.map((s) => <SelectItem key={s} value={s}>{displayStatut(s)}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>

                  {/* Projets — supprimés de la section 1, gérés via Source de financement (Section 3) */}
                </div>

                {/* Colonne droite : photo + pièces jointes */}
                <div className="flex w-full flex-col gap-4 lg:w-56 lg:shrink-0">
                  <div className="space-y-1.5">
                    <ReqLabel>{t("biens.detail.photoAlt")}</ReqLabel>
                    {form.photoPreview ? (
                      <div className="relative overflow-hidden rounded-lg border border-border">
                        <img src={form.photoPreview} alt={t("biens.form.preview")} className="h-40 w-full object-cover" />
                        <button type="button" aria-label={t("biens.form.removePhoto")}
                          onClick={() => { set("photoPreview", null); set("photosFiles", []); }}
                          className="absolute right-2 top-2 grid h-6 w-6 place-items-center rounded-full bg-background/90 text-foreground shadow hover:bg-background">
                          <X className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    ) : (
                      <label className="flex h-40 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary/40 bg-primary/5 text-center text-xs text-muted-foreground transition hover:bg-primary/10">
                        <Upload className="h-5 w-5 text-primary" />
                        <span className="px-2 leading-relaxed">{t("biens.form.clickToSelectPhoto")}<br />{t("biens.form.aPhoto")}</span>
                        <input type="file" accept="image/*" className="hidden" onChange={onPickPhoto} />
                      </label>
                    )}
                  </div>

                  <div className="space-y-2">
                    <p className="text-xs font-medium text-foreground">{t("biens.form.piecesJustificatives")}</p>
                    <label className="flex w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-primary/40 bg-primary/5 px-3 py-3 text-center text-xs text-muted-foreground transition hover:bg-primary/10">
                      <Upload className="h-4 w-4 text-primary" />
                      <span>{t("biens.form.addFiles")}</span>
                      <span className="text-[10px]">PDF, Image, Word (Max 10 Mo)</span>
                      <input type="file" multiple className="hidden" onChange={onPickPiece} />
                    </label>
                    {form.piecesJointes.length > 0 && (
                      <ul className="space-y-1.5">
                        {form.piecesJointes.map((p, i) => (
                          <li key={i} className="flex items-center gap-1.5 rounded-md border border-border bg-background px-2 py-1.5 text-xs">
                            <span className="flex-1 truncate text-primary">{p.libelle}</span>
                            <Input
                              className="h-6 w-28 text-[10px] px-1"
                              placeholder={t("biens.form.libellePlaceholder")}
                              value={p.libelle}
                              onChange={(e) => {
                                const updated = [...form.piecesJointes];
                                updated[i] = { ...updated[i], libelle: e.target.value };
                                set("piecesJointes", updated);
                              }}
                            />
                            <button type="button" aria-label={t("action.delete")}
                              onClick={() => set("piecesJointes", form.piecesJointes.filter((_, j) => j !== i))}
                              className="shrink-0 text-destructive hover:text-destructive/80">
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </div>
              </div>

              {/* Description et Champs personnalisés — pleine largeur de la
                  section (hors de la ligne flex partagée avec la colonne
                  photo/pièces justificatives, pour ne pas être rétrécis). */}
              <div className="mt-4 space-y-1.5">
                <ReqLabel>{t("common.description")}</ReqLabel>
                <Textarea value={form.description} onChange={(e) => set("description", e.target.value)}
                  placeholder={t("biens.form.descriptionPlaceholder")} rows={3} />
              </div>

              {/* Recommandation 6.a — Champs personnalisés : affichés uniquement si
                  une catégorie est sélectionnée et que des champs y sont associés
                  (Administration > Champs personnalisés). Chaque champ = label + input. */}
              {form.category_id && champsForCategory.length > 0 && (
                <div className="mt-4 space-y-3 rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4">
                  <div className="flex items-center gap-2">
                    <ClipboardList className="h-4 w-4 text-primary" />
                    <p className="text-xs font-semibold text-primary">
                      {t("biens.form.customFields")} — {form.category_nom || t("biens.form.selectedCategory")}
                    </p>
                  </div>
                  <div className="grid gap-4 sm:grid-cols-2">
                    {orderedChampsForCategory.map((champ) => (
                      <div key={champ.id} className="space-y-1.5">
                        <ReqLabel>{champ.nom}</ReqLabel>
                        {renderChampValueInput(champ)}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </AccordionSection>

            {/* Localisation, uniquement pour les catégories Terrains/Bâtiments.
                Un simple point (latitude/longitude) envoyé avec le reste du
                bien dans la même requête POST /assets ou POST /assets/{id} —
                pas d'appel séparé. Positionnée juste après les Informations
                générales pour ces catégories. */}
            {isTerrainOuBatiment(form.category_nom) && (
              <AccordionSection index={2} title={t("biens.detail.localisation")} open={openSection === 2} onToggle={() => toggleSection(2)}>
                {mode === "edit" && currentLocationQuery.isLoading ? (
                  <p className="text-xs text-muted-foreground">{t("biens.form.loadingCurrentLocation")}</p>
                ) : (
                  <div className="space-y-3">
                    <div className="inline-flex rounded-lg border border-border p-0.5 text-xs">
                      <button
                        type="button"
                        onClick={() => set("locationMode", "manual")}
                        className={cn(
                          "rounded-md px-3 py-1.5 font-medium transition-colors",
                          form.locationMode === "manual" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted",
                        )}
                      >
                        {t("biens.form.manualEntry")}
                      </button>
                      <button
                        type="button"
                        onClick={() => set("locationMode", "file")}
                        className={cn(
                          "rounded-md px-3 py-1.5 font-medium transition-colors",
                          form.locationMode === "file" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted",
                        )}
                      >
                        {t("biens.form.importFile")}
                      </button>
                    </div>

                    {form.locationMode === "manual" ? (
                      <>
                        <p className="text-[11px] text-muted-foreground">
                          {t("biens.form.clickMapHint")}
                        </p>
                        <Suspense fallback={<MapLoadingFallback />}>
                          <LocationPickerMap
                            value={
                              form.latitude.trim() && form.longitude.trim()
                                && !Number.isNaN(Number(form.latitude)) && !Number.isNaN(Number(form.longitude))
                                ? { latitude: Number(form.latitude), longitude: Number(form.longitude) }
                                : null
                            }
                            onChange={(v) => { set("latitude", String(v.latitude)); set("longitude", String(v.longitude)); }}
                          />
                        </Suspense>
                        <div className="grid grid-cols-2 gap-3">
                          <div className="space-y-1.5">
                            <Label className="text-xs font-medium text-muted-foreground">{t("biens.field.latitude")}</Label>
                            <Input type="number" step="any" value={form.latitude} onChange={(e) => set("latitude", e.target.value)} placeholder="Ex : 3.8480" />
                          </div>
                          <div className="space-y-1.5">
                            <Label className="text-xs font-medium text-muted-foreground">{t("biens.field.longitude")}</Label>
                            <Input type="number" step="any" value={form.longitude} onChange={(e) => set("longitude", e.target.value)} placeholder="Ex : 11.5021" />
                          </div>
                        </div>
                        {(form.latitude || form.longitude) && (
                          <Button type="button" variant="outline" size="sm" onClick={() => { set("latitude", ""); set("longitude", ""); }}>
                            {t("biens.form.clear")}
                          </Button>
                        )}
                      </>
                    ) : (
                      <div className="space-y-2">
                        <p className="text-[11px] text-muted-foreground">
                          {t("biens.form.fileFormatsHint")}
                          {mode === "create" && ` ${t("biens.form.fileFormatsHintCreate")}`}
                        </p>
                        <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2.5 text-xs text-muted-foreground transition hover:bg-primary/10">
                          <Upload className="h-4 w-4 shrink-0 text-primary" />
                          <span className="flex-1 truncate">
                            {uploadLocationFileMutation.isPending
                              ? t("biens.form.importInProgress")
                              : form.locationFile
                                ? form.locationFile.name
                                : uploadedFileLocations
                                  ? t("biens.form.locationsImportedCount", { count: uploadedFileLocations.length })
                                  : t("biens.form.clickToChooseFile")}
                          </span>
                          <input
                            type="file"
                            accept=".csv,.xlsx,.xls,.json,.geojson,.kml,.gpx,.zip,.shp"
                            className="hidden"
                            disabled={uploadLocationFileMutation.isPending}
                            onChange={(e) => {
                              const file = e.target.files?.[0] ?? null;
                              setUploadedFileLocations(null);
                              setClientPreviewLocation(null);
                              if (mode === "edit" && file) {
                                uploadLocationFileMutation.mutate(file);
                              } else {
                                set("locationFile", file);
                                if (file) {
                                  parseLocationFileClientSide(file).then((loc) => {
                                    if (loc) setClientPreviewLocation(loc);
                                  });
                                }
                              }
                            }}
                          />
                        </label>
                        {(form.locationFile || uploadedFileLocations) && (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => { set("locationFile", null); setUploadedFileLocations(null); setClientPreviewLocation(null); }}
                          >
                            {t("biens.form.removeFile")}
                          </Button>
                        )}
                        {mode === "create" && form.locationFile && !clientPreviewLocation && (
                          <p className="text-[11px] italic text-muted-foreground">
                            {t("biens.form.noPointExtracted")}
                          </p>
                        )}
                        <Suspense fallback={<MapLoadingFallback />}>
                          <LocationPickerMap
                            readOnly
                            value={
                              uploadedFileLocations && uploadedFileLocations.length > 0
                                ? { latitude: uploadedFileLocations[0].latitude, longitude: uploadedFileLocations[0].longitude }
                                : clientPreviewLocation
                            }
                            onChange={() => {}}
                          />
                        </Suspense>
                      </div>
                    )}
                  </div>
                )}
              </AccordionSection>
            )}

            {/* ─── Section 3 : Fournisseur ─── */}
            <AccordionSection index={3} title={t("biens.form.fournisseurInfo")} open={openSection === 3} onToggle={() => toggleSection(3)}>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div className="space-y-1.5">
                  <ReqLabel>{t("biens.detail.field.typeFournisseur")}</ReqLabel>
                  <Select value={form.typeFournisseur} onValueChange={(v) => set("typeFournisseur", v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {typesFournisseur.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <ReqLabel required>{t("biens.detail.field.raisonSociale")}</ReqLabel>
                  <Input value={form.fournisseurNom} onChange={(e) => set("fournisseurNom", e.target.value)} placeholder="Ex : CAMTEL TECHNOLOGIES" />
                </div>
                <div className="space-y-1.5">
                  <ReqLabel required>Email</ReqLabel>
                  <Input type="email" value={form.fournisseurEmail} onChange={(e) => set("fournisseurEmail", e.target.value)} placeholder="contact@fournisseur.cm" />
                </div>
                <div className="space-y-1.5">
                  <ReqLabel required>{t("biens.detail.field.telephone")}</ReqLabel>
                  <Input value={form.fournisseurTelephone} onChange={(e) => set("fournisseurTelephone", e.target.value)} placeholder="Ex : +237 6XX XXX XXX" />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs font-medium text-muted-foreground">{t("biens.detail.field.adresse")}</Label>
                  <Input value={form.fournisseurAdresse} onChange={(e) => set("fournisseurAdresse", e.target.value)} placeholder={t("biens.form.adresseCompletePlaceholder")} />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs font-medium text-muted-foreground">{t("biens.detail.field.ville")}</Label>
                  <Input value={form.fournisseurVille} onChange={(e) => set("fournisseurVille", e.target.value)} placeholder="Ex : Yaoundé" />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs font-medium text-muted-foreground">{t("biens.detail.field.pays")}</Label>
                  <Input value={form.fournisseurPays} onChange={(e) => set("fournisseurPays", e.target.value)} placeholder="Ex : Cameroun" />
                </div>
              </div>
            </AccordionSection>

            {/* ─── Section 4 : Informations financières ─── */}
            <AccordionSection index={4} title={t("biens.detail.tab.financial")} open={openSection === 4} onToggle={() => toggleSection(4)}>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div className="space-y-1.5">
                  <ReqLabel required>{t("biens.form.valeurBienFcfa")}</ReqLabel>
                  <Input type="number" value={form.valeur} onChange={(e) => set("valeur", e.target.value)} placeholder="Ex : 850000" />
                </div>
                <div className="space-y-1.5">
                  <ReqLabel required>{t("biens.list.col.sourceFinancement")}</ReqLabel>
                  <Select
                    value={(() => {
                      // 1. On a un ID explicite
                      if (form.project_ids.length > 0) return String(form.project_ids[0]);
                      // 2. Mode édition : retrouver l'ID depuis le nom stocké (sourceFinancement)
                      if (form.sourceFinancement) {
                        const match = projects.find((p) => p.nom === form.sourceFinancement);
                        if (match) return String(match.id);
                      }
                      return "";
                    })()}
                    onValueChange={(v) => {
                      const projectId = Number(v);
                      const project = projects.find((p) => p.id === projectId);
                      set("sourceFinancement", project?.nom ?? "");
                      set("sourceFinancementExercice", project?.exercice ?? null);
                      set("project_ids", projectId ? [projectId] : []);
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={projects.length === 0 ? t("biens.form.loadingSources") : t("biens.form.selectSource")} />
                    </SelectTrigger>
                    <SelectContent>
                      {projects.length === 0 ? (
                        <div className="px-3 py-4 text-center text-xs text-muted-foreground">
                          {t("biens.form.noProjectAvailable")}
                        </div>
                      ) : projects.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          <div className="flex items-center gap-2">
                            <span className="flex-1">{p.nom}</span>
                            <span className={cn(
                              "rounded-full px-1.5 py-0.5 text-[10px] font-medium",
                              p.statut === "EN_COURS"  ? "bg-green-100 text-green-700" :
                              p.statut === "PLANIFIE"  ? "bg-blue-100 text-blue-700"  :
                                                        "bg-muted text-muted-foreground"
                            )}>{p.statut}</span>
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {form.sourceFinancement && (
                    <p className="text-[11px] text-muted-foreground">
                      {(() => {
                        // form.sourceFinancementExercice est capturé directement à la
                        // sélection (fiable) ; on retombe sur une recherche live
                        // seulement si absent (ex. fiche en cours de chargement).
                        const exercice = form.sourceFinancementExercice
                          ?? projects.find((project) =>
                            project.id === form.project_ids[0] || project.nom === form.sourceFinancement
                          )?.exercice
                          ?? null;
                        return exercice != null
                          ? <span>{t("biens.form.exerciceSource")} <strong className="text-foreground">{exercice}</strong></span>
                          : null;
                      })()}
                    </p>
                  )}
                </div>
                <div className="space-y-1.5">
                  <ReqLabel>{t("biens.detail.field.codeMercuriale")}</ReqLabel>
                  <Input value={form.code} onChange={(e) => set("code", e.target.value)} placeholder="Ex : MC-2026-00123" />
                  {form.nom.trim() && (
                    <button
                      type="button"
                      onClick={() => setMercurialeOpen(true)}
                      className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                      <ExternalLink className="h-3 w-3" /> {t("biens.form.consultMercurialeFor", { nom: form.nom.trim() })}
                    </button>
                  )}
                </div>
                <div className="space-y-1.5">
                  <ReqLabel>{t("biens.form.prixMercurialeFcfa")}</ReqLabel>
                  <Input type="number" value={form.prixMercurial} onChange={(e) => set("prixMercurial", e.target.value)} placeholder="Ex : 800000" />
                </div>
              </div>
            </AccordionSection>

            <MercurialeIframeDialog
              url={mercurialeOpen && form.nom.trim() ? mercurialeUrl(form.nom.trim()) : null}
              nom={form.nom.trim()}
              onClose={() => setMercurialeOpen(false)}
            />
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border p-4">
            <Button type="button" variant="outline" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
            <Button type="button" onClick={submit} disabled={isSaving} className="gap-2">
              <FileCheck2 className="h-4 w-4" />
              {isSaving ? t("biens.securisation.saving") : mode === "edit" ? t("biens.form.saveChanges") : t("biens.form.saveBien")}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  /* =========================================================
    Composants UI partagés
    ========================================================= */

  function AccordionSection({ index, title, open, onToggle, children }: {
    index: number; title: string; open: boolean; onToggle: () => void; children: ReactNode;
  }) {
    return (
      <section className="border-b border-border last:border-b-0">
        <button type="button" onClick={onToggle} className="flex w-full items-center justify-between gap-3 py-3 text-left">
          <span className="text-sm font-bold text-primary">{index}. {title}</span>
          <ChevronDown className={cn("h-4 w-4 shrink-0 text-primary transition-transform", open && "rotate-180")} />
        </button>
        {open ? <div className="pb-5 pt-1">{children}</div> : null}
      </section>
    );
  }

  function ReqLabel({ children, required }: { children: ReactNode; required?: boolean }) {
    return (
      <Label className="text-xs font-semibold text-foreground">
        {children}{required ? <span className="ml-0.5 text-destructive">*</span> : null}
      </Label>
    );
  }

  function Section({ num, title, action, children }: {
    num: number; title: string; action?: ReactNode; children: ReactNode;
  }) {
    return (
      <section className="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
          <h2 className="text-base font-bold text-foreground sm:text-lg"><span className="mr-1">{num}.</span>{title}</h2>
          {action}
        </div>
        {children}
      </section>
    );
  }

  /** Bloc interne à une Section (sans sa propre carte/bordure) — utilisé pour
   * regrouper Pièces jointes et Localisation dans la section Information
   * générale, et Informations financières dans la section Fournisseur. */
  function SubSection({ title, action, children }: {
    title: string; action?: ReactNode; children: ReactNode;
  }) {
    return (
      <div className="mt-5 border-t border-border pt-4">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h3 className="text-sm font-semibold text-foreground">{title}</h3>
          {action}
        </div>
        {children}
      </div>
    );
  }

  function KV({ k, v }: { k: string; v: ReactNode }) {
    return (
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{k}</p>
        <div className="mt-0.5 text-sm text-foreground">{v || "—"}</div>
      </div>
    );
  }

  /* =========================================================
    LocationSummary — résumé textuel d'une localisation (pas de carte).
    Point : coordonnées directes. Géométrie complexe (Polygon, LineString...) :
    nombre de sommets + coordonnées du centroïde pour repère rapide.
    ========================================================= */

  function LocationSummary({ location }: { location: ApiAssetLocation }) {
    const t = useT();
    const coords = `${location.latitude.toFixed(5)}, ${location.longitude.toFixed(5)}`;
    if (location.geometry_type === "Point" || !location.geometry) {
      return <span className="font-mono tabular-nums">{coords}</span>;
    }
    const rawCoords = location.geometry.coordinates as unknown[];
    const countVertices = (arr: unknown): number =>
      Array.isArray(arr)
        ? (typeof arr[0] === "number" ? 1 : arr.reduce((sum: number, a) => sum + countVertices(a), 0))
        : 0;
    const vertices = countVertices(rawCoords);
    return (
      <span>
        {t("biens.form.vertices", { count: vertices })}
        <span className="ml-1.5 text-muted-foreground">{t("biens.form.centroid", { coords })}</span>
      </span>
    );
  }

  /* =========================================================
    LocalisationFormInline — deux modes : point saisi (lat/lng) ou upload
    d'un fichier géospatial (CSV, Excel, GeoJSON, KML, GPX, Shapefile).
    ========================================================= */

  function LocalisationFormInline({ isSavingPoint, isSavingFile, onCancel, onSavePoint, onSaveFile }: {
    isSavingPoint: boolean;
    isSavingFile: boolean;
    onCancel: () => void;
    onSavePoint: (payload: CreateLocationPointPayload) => void;
    onSaveFile: (file: File) => void;
  }) {
    const t = useT();
    const [mode, setMode] = useState<"point" | "fichier">("point");
    const [latitude, setLatitude] = useState("");
    const [longitude, setLongitude] = useState("");
    const [file, setFile] = useState<File | null>(null);

    const isSaving = isSavingPoint || isSavingFile;

    const submitPoint = () => {
      const lat = Number(latitude);
      const lng = Number(longitude);
      if (!latitude.trim() || Number.isNaN(lat) || lat < -90 || lat > 90) {
        toast.error(t("biens.form.error.latInvalid"));
        return;
      }
      if (!longitude.trim() || Number.isNaN(lng) || lng < -180 || lng > 180) {
        toast.error(t("biens.form.error.lngInvalid"));
        return;
      }
      onSavePoint({ latitude: lat, longitude: lng });
    };

    const submitFile = () => {
      if (!file) { toast.error(t("biens.form.selectFileToImport")); return; }
      onSaveFile(file);
    };

    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <div className="flex rounded-md border border-border text-xs w-fit overflow-hidden">
          <button type="button" onClick={() => setMode("point")}
            className={cn("px-3 py-1.5 transition-colors", mode === "point" ? "bg-primary text-primary-foreground" : "bg-background text-muted-foreground hover:bg-muted")}>
            {t("biens.form.pointLatLng")}
          </button>
          <button type="button" onClick={() => setMode("fichier")}
            className={cn("px-3 py-1.5 transition-colors border-l border-border", mode === "fichier" ? "bg-primary text-primary-foreground" : "bg-background text-muted-foreground hover:bg-muted")}>
            {t("biens.form.fichierGeospatial")}
          </button>
        </div>

        {mode === "point" ? (
          <>
            <p className="text-[11px] text-muted-foreground">
              {t("biens.form.clickMapHint")}
            </p>
            <Suspense fallback={<MapLoadingFallback />}>
              <LocationPickerMap
                value={
                  latitude.trim() && longitude.trim() && !Number.isNaN(Number(latitude)) && !Number.isNaN(Number(longitude))
                    ? { latitude: Number(latitude), longitude: Number(longitude) }
                    : null
                }
                onChange={(v) => { setLatitude(String(v.latitude)); setLongitude(String(v.longitude)); }}
              />
            </Suspense>
            <div className="grid grid-cols-2 gap-3">
              <Field label={`${t("biens.field.latitude")} *`}>
                <Input type="number" step="any" value={latitude} onChange={(e) => setLatitude(e.target.value)} placeholder="Ex : 3.848" />
              </Field>
              <Field label={`${t("biens.field.longitude")} *`}>
                <Input type="number" step="any" value={longitude} onChange={(e) => setLongitude(e.target.value)} placeholder="Ex : 11.5021" />
              </Field>
            </div>
            <div className="flex justify-end gap-2 border-t border-border pt-3">
              <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
              <Button size="sm" onClick={submitPoint} disabled={isSaving}>
                {isSavingPoint ? t("biens.securisation.saving") : t("biens.form.savePoint")}
              </Button>
            </div>
          </>
        ) : (
          <>
            <Field label={`${t("biens.form.fichier")} *`}>
              <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2.5 text-xs text-muted-foreground transition hover:bg-primary/10">
                <Upload className="h-4 w-4 shrink-0 text-primary" />
                <span>{file ? file.name : t("biens.form.clickToChooseFileGeneric")}</span>
                <span className="ml-auto text-[10px]">CSV, Excel, GeoJSON, KML, GPX, Shapefile (.zip)</span>
                <input
                  type="file"
                  className="hidden"
                  accept=".csv,.xlsx,.xls,.json,.geojson,.kml,.gpx,.shp,.zip"
                  onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                />
              </label>
              <p className="mt-1 text-[11px] text-muted-foreground">
                {t("biens.form.oneLocationPerGeometry")}
              </p>
            </Field>
            <div className="flex justify-end gap-2 border-t border-border pt-3">
              <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
              <Button size="sm" onClick={submitFile} disabled={isSaving}>
                {isSavingFile ? t("biens.form.importing") : t("biens.form.importFileAction")}
              </Button>
            </div>
          </>
        )}
      </div>
    );
  }

  /* =========================================================
    AllBiensLocationsDialog — carte globale, sur demande (bouton "Voir les
    biens sur la carte" dans la liste). Aucun endpoint ne renvoie toutes les
    localisations en un coup — on interroge GET /assets/{id}/location pour
    chaque bien actuellement chargé, en parallèle, uniquement à l'ouverture
    du dialogue (jamais au chargement de la page).
    ========================================================= */

  function AllBiensLocationsDialog({ open, onClose, biens }: {
    open: boolean;
    onClose: () => void;
    biens: ApiBien[];
  }) {
    const t = useT();
    // Seuls les Terrains/Bâtiments peuvent avoir une localisation — filtrer
    // ici évite des dizaines d'appels GET .../location inutiles pour le reste.
    const locatableBiens = useMemo(
      () => biens.filter((b) => isTerrainOuBatiment(b.category?.nom)),
      [biens],
    );

    const { data: results, isLoading } = useQuery({
      queryKey: ["all-biens-locations", locatableBiens.map((b) => b.id).join(",")],
      queryFn: async () => {
        const settled = await Promise.allSettled(
          locatableBiens.map(async (b) => ({ bien: b, location: await getCurrentLocation(b.id) })),
        );
        return settled
          .filter((r): r is PromiseFulfilledResult<{ bien: ApiBien; location: ApiAssetLocation | null }> => r.status === "fulfilled")
          .map((r) => r.value)
          .filter((r): r is { bien: ApiBien; location: ApiAssetLocation } => r.location !== null);
      },
      enabled: open && locatableBiens.length > 0,
      staleTime: 60_000,
    });

    const located = results ?? [];

    return (
      <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
        <DialogContent className="max-h-[90vh] max-w-[92vw] overflow-y-auto xl:max-w-[1400px]">
          <DialogHeader>
            <DialogTitle>{t("biens.form.biensLocatedOnMap")}</DialogTitle>
          </DialogHeader>
          {isLoading ? (
            <div className="flex h-72 items-center justify-center gap-2 text-muted-foreground">
              <Loader2Icon className="h-5 w-5 animate-spin" />
              <span>{t("biens.form.loadingLocations")}</span>
            </div>
          ) : locatableBiens.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              {t("biens.form.noTerrainBatiment")}
            </p>
          ) : located.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              {t("biens.form.noneLocated", { count: locatableBiens.length })}
            </p>
          ) : (
            <>
              <p className="text-xs text-muted-foreground">
                {t("biens.form.locatedSummary", { located: located.length, total: locatableBiens.length })}
              </p>
              <Suspense fallback={<MapLoadingFallback height={720} />}>
                <LocationMap
                  height={720}
                  locations={located.map(({ bien, location }) => ({
                    id: bien.id,
                    latitude: location.latitude,
                    longitude: location.longitude,
                    isCurrent: true,
                    tooltip: (
                      <div className="min-w-44 space-y-0.5 text-xs">
                        <div className="font-semibold">{bien.nom}</div>
                        <div className="font-mono text-muted-foreground">{bien.reference}</div>
                        <div>{bien.category?.nom ?? "—"}{bien.assetType?.nom ? ` — ${bien.assetType.nom}` : ""}</div>
                        <div>{t("biens.detail.field.etat")} : {bien.etatBien?.nom ?? "—"}</div>
                        <div>{t("common.status")} : {displayStatut(bien.statut)}</div>
                        <div>{t("biens.detail.col.structure")} : {bien.service?.nom ?? "—"}</div>
                        {bien.utilisateur && (
                          <div>{t("biens.detail.currentHolder")} : {bien.utilisateur.firstName} {bien.utilisateur.lastName}</div>
                        )}
                        <div className="tabular-nums">{formatFCFA(bien.valeur)}</div>
                        {bien.dateAcquisition && <div>{t("biens.form.acquiredOn")} {bien.dateAcquisition}</div>}
                      </div>
                    ),
                  }))}
                />
              </Suspense>
            </>
          )}
        </DialogContent>
      </Dialog>
    );
  }

  /* Amortissements — page dédiée /biens/amortissements (src/pages/Amortissements.tsx),
     plus de dialog imbriqué ici. */

  /* =========================================================
    QrCodeDialog — affiche un QR code encodant les détails d'un bien.
    Le QR code est généré côté client (lib `qrcode`) à partir d'un
    résumé textuel du bien. L'utilisateur peut télécharger l'image
    PNG, l'imprimer, ou simplement scanner le code pour récupérer
    les informations sur le terrain.
    ========================================================= */

  /**
   * Construit le payload textuel encodé dans le QR code.
   * Format volontairement lisible : un scan + lecture rapide permettent
   * d'identifier le bien même sans connexion à l'application.
   */
  function buildBienQrPayload(b: ApiBien, t: (k: Key, params?: Record<string, string | number>) => string): string {
    const lines: string[] = [];
    lines.push(t("biens.qr.header"));
    lines.push("=========================");
    lines.push(`${t("biens.qr.ref")}      : ${b.reference ?? "—"}`);
    lines.push(`${t("biens.qr.designation")} : ${b.nom ?? "—"}`);
    if (b.numeroSerie) lines.push(`${t("biens.qr.numeroSerie")} : ${b.numeroSerie}`);
    if (b.description) lines.push(`${t("biens.qr.description")} : ${b.description}`);
    if (b.category?.nom)     lines.push(`${t("biens.qr.categorie")}   : ${b.category.nom}`);
    if (b.assetType?.nom)    lines.push(`${t("biens.qr.type")}       : ${b.assetType.nom}`);
    if (b.assetSubType?.nom) lines.push(`${t("biens.qr.sousType")}    : ${b.assetSubType.nom}`);
    if (b.etatBien?.nom)     lines.push(`${t("biens.qr.etat")}       : ${b.etatBien.nom}`);
    if (b.service?.nom)      lines.push(`${t("biens.qr.structure")}  : ${b.service.nom}`);
    if (b.utilisateur) {
      const u = b.utilisateur;
      const fullName = `${u.firstName ?? ""} ${u.lastName ?? ""}`.trim();
      if (fullName) lines.push(`${t("biens.qr.detenteur")} : ${fullName}`);
    }
    if (b.dateAcquisition) lines.push(`${t("biens.qr.dateAcquisition")} : ${b.dateAcquisition}`);
    lines.push(`${t("biens.qr.valeurFcfa")} : ${formatFCFA(b.valeur)}`);
    if (b.sourceFinancement) lines.push(`${t("biens.qr.sourceFinance")} : ${b.sourceFinancement}`);
    if (b.statut) lines.push(`${t("common.status")}          : ${displayStatut(b.statut)}`);
    if (b.fournisseurNom) {
      lines.push(`${t("biens.detail.fournisseur")}   : ${b.fournisseurNom}`);
      if (b.fournisseurTelephone) lines.push(`${t("biens.qr.telFournisseur")} : ${b.fournisseurTelephone}`);
    }
    lines.push("=========================");
    lines.push(`${t("biens.qr.idInterne")}   : #${b.id}`);
    return lines.join("\n");
  }

  function QrCodeDialog({ bien, onClose }: { bien: ApiBien | null; onClose: () => void }) {
    const t = useT();
    const [pngUrl, setPngUrl] = useState<string | null>(null);
    const [error, setError]   = useState<string | null>(null);

    // Génération du QR code à l'ouverture (ou au changement de bien).
    useEffect(() => {
      if (!bien) {
        setPngUrl(null);
        setError(null);
        return;
      }
      let cancelled = false;
      setError(null);
      QRCode.toDataURL(buildBienQrPayload(bien, t), {
        errorCorrectionLevel: "M",
        margin: 2,
        width: 360,
        color: { dark: "#0f172a", light: "#ffffff" },
      })
        .then((url) => { if (!cancelled) setPngUrl(url); })
        .catch((err: unknown) => {
          if (cancelled) return;
          setError(err instanceof Error ? err.message : t("biens.qr.genError"));
        });
      return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bien]);

    const fileBase = `QRCode-${(bien?.reference ?? "bien").replace(/[^\w-]/g, "_")}`;

    const handleDownload = () => {
      if (!pngUrl) return;
      const a = document.createElement("a");
      a.href = pngUrl;
      a.download = `${fileBase}.png`;
      document.body.appendChild(a);
      a.click();
      a.remove();
    };

    const handlePrint = () => {
      if (!bien || !pngUrl) return;
      const payload = buildBienQrPayload(bien, t);
      const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
      const w = window.open("", "_blank", "width=480,height=640");
      if (!w) {
        toast.error(t("biens.qr.printBlocked"));
        return;
      }
      w.document.write(`<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>${t("biens.qr.title")} — ${esc(bien.reference ?? bien.nom ?? "")}</title>
<style>
  ${MINEPIA_PRINT_HEADER_CSS}
  body { font-family: Arial, sans-serif; margin: 0; padding: 24px; color: #111827; }
  .frame { max-width: 420px; margin: 0 auto; border: 2px solid #166534; border-radius: 12px; padding: 20px; }
  .head { text-align: center; border-bottom: 2px solid #166534; padding-bottom: 12px; margin-bottom: 16px; }
  .head .org { font-size: 12px; color: #6b7280; }
  .head .title { font-size: 18px; font-weight: 700; color: #166534; margin-top: 4px; }
  .qr { text-align: center; margin: 12px 0; }
  .qr img { width: 260px; height: 260px; }
  pre.payload { font-size: 11px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; background: #f9fafb; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; }
  .footer { text-align: center; font-size: 10px; color: #6b7280; margin-top: 12px; }
  @media print { body { padding: 0; } .frame { border-radius: 0; border: none; } }
</style>
</head>
<body>
${minepiaPrintHeaderHtml()}
<div class="frame">
  <div class="head">
    <div class="org">MINEPIA — ${t("app.tagline")}</div>
    <div class="title">${t("biens.qr.title")}</div>
  </div>
  <div class="qr"><img src="${pngUrl}" alt="QR code" /></div>
  <pre class="payload">${esc(payload)}</pre>
  <div class="footer">${t("biens.qr.editedOn")} ${new Date().toLocaleDateString("fr-FR")} — MINEPIA</div>
</div>
<script>window.onload = function () { setTimeout(function () { window.print(); }, 200); };</script>
</body>
</html>`);
      w.document.close();
    };

    return (
      <Dialog open={!!bien} onOpenChange={(v) => !v && onClose()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <QrCode className="h-5 w-5 text-primary" />
              {t("biens.qr.title")}
            </DialogTitle>
          </DialogHeader>

          {bien && (
            <div className="space-y-4">
              <div className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs">
                <p className="font-mono font-semibold text-foreground">{bien.reference}</p>
                <p className="text-muted-foreground">{bien.nom}</p>
                {bien.category?.nom && (
                  <p className="mt-0.5 text-muted-foreground">{bien.category.nom}</p>
                )}
              </div>

              <div className="flex items-center justify-center rounded-lg border border-dashed border-border bg-background p-4">
                {error ? (
                  <p className="text-sm text-destructive">{error}</p>
                ) : pngUrl ? (
                  <img
                    src={pngUrl}
                    alt={t("biens.qr.altText", { reference: bien.reference ?? "" })}
                    className="h-56 w-56"
                    width={224}
                    height={224}
                  />
                ) : (
                  <div className="flex h-56 w-56 items-center justify-center text-xs text-muted-foreground">
                    <Loader2Icon className="h-5 w-5 animate-spin" />
                  </div>
                )}
              </div>

              <p className="text-center text-xs text-muted-foreground">
                {t("biens.qr.scanHint")}
              </p>
            </div>
          )}

          <DialogFooter className="gap-2">
            <button
              type="button"
              onClick={onClose}
              className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-muted"
            >
              {t("biens.detail.close")}
            </button>
            <button
              type="button"
              onClick={handlePrint}
              disabled={!pngUrl}
              className="inline-flex h-9 items-center gap-2 justify-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-muted disabled:opacity-50"
            >
              <Printer className="h-4 w-4" /> {t("biens.qr.print")}
            </button>
            <button
              type="button"
              onClick={handleDownload}
              disabled={!pngUrl}
              className="inline-flex h-9 items-center gap-2 justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
            >
              <Download className="h-4 w-4" /> {t("biens.qr.download")}
            </button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    );
  }

  /* =========================================================
    MercurialeButton — bouton "Consulter la mercuriale" sur la fiche d'un bien.
    Récupère le lien de recherche mercuriale.cm propre au bien (généré par le
    backend à partir de son nom, via GET /assets/{id}/mercurriale) et l'ouvre
    dans un nouvel onglet.
    ========================================================= */

  function MercurialeButton({ bien }: { bien: ApiBien }) {
    const t = useT();
    const mutation = useMutation({
      mutationFn: () => getAssetMercuriale(bien.id),
      onSuccess: (data) => window.open(data.lien, "_blank", "noopener,noreferrer"),
      onError: (err: unknown) => {
        const apiMsg = (err as { response?: { data?: { message?: string } } })
          ?.response?.data?.message;
        toast.error(apiMsg ?? t("biens.qr.mercurialeLinkFailed"));
      },
    });

    return (
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="gap-1.5"
        disabled={mutation.isPending}
        onClick={() => mutation.mutate()}
      >
        {mutation.isPending ? (
          <Loader2Icon className="h-3.5 w-3.5 animate-spin" />
        ) : (
          <ExternalLink className="h-3.5 w-3.5" />
        )}
        {t("biens.list.viewMercuriale")}
      </Button>
    );
  }

  function SimpleTable({ columns, rows }: { columns: string[]; rows: ReactNode[][] }) {
    const t = useT();
    return (
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-xs">
          <thead className="text-[11px] uppercase tracking-wide text-muted-foreground">
            <tr className="border-b border-border">
              {columns.map((c) => <th key={c} className="px-2 py-2 text-left font-semibold">{c}</th>)}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr><td colSpan={columns.length} className="px-2 py-4 text-center text-muted-foreground">{t("biens.detail.noRecord")}</td></tr>
            ) : (
              rows.map((r, i) => (
                <tr key={i} className="border-b border-border/60">
                  {r.map((cell, j) => <td key={j} className="px-2 py-2.5 align-middle">{cell}</td>)}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    );
  }

  function FilterSelect({ label, value, onChange, options }: {
    label: string; value: string; onChange: (v: string) => void;
    options: { value: string; label: string }[];
  }) {
    return (
      <div className="flex flex-col gap-1.5">
        <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
        <Select value={value} onValueChange={onChange}>
          <SelectTrigger className="h-10"><SelectValue /></SelectTrigger>
          <SelectContent>
            {options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>
    );
  }

  function InlineFormWrapper({ title, subtitle, onCancel, onSave, children }: {
    title: string; subtitle?: string; onCancel: () => void; onSave: () => void; children: ReactNode;
  }) {
    const t = useT();
    return (
      <div className="animate-in fade-in-50 duration-150">
        <div className="mb-4 flex items-center gap-2 rounded-md bg-primary/5 px-3 py-2">
          <Button variant="ghost" size="sm" onClick={onCancel} className="gap-1 -ml-1">
            <ArrowLeft className="h-4 w-4" /> {t("action.back")}
          </Button>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-primary">{title}</p>
            {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
          </div>
        </div>
        <div className="space-y-5">{children}</div>
        <div className="mt-5 flex justify-end gap-2 border-t border-border pt-4">
          <Button variant="outline" size="sm" onClick={onCancel}>{t("action.cancel")}</Button>
          <Button size="sm" onClick={onSave}>{t("action.save")}</Button>
        </div>
      </div>
    );
  }

  function Field({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
      <div className={cn("space-y-1.5", className)}>
        <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
        {children}
      </div>
    );
  }

  /* =========================================================
    Drawers secondaires (Affectation, Sortie, Maintenance, etc.)
    ========================================================= */

  function DrawerShell({ open, onOpenChange, title, subtitle, icon, children, onCancel, onSave, saveLabel }: {
    open: boolean; onOpenChange: (v: boolean) => void; title: string; subtitle?: string;
    icon: React.ComponentType<{ className?: string }>; children: ReactNode;
    onCancel: () => void; onSave: () => void; saveLabel?: string;
  }) {
    const t = useT();
    const Icon = icon;
    return (
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
          <SheetHeader className="border-b border-border p-5 text-left">
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="h-5 w-5" />
              </div>
              <div className="min-w-0 flex-1">
                <SheetTitle className="text-base font-bold uppercase tracking-wide">{title}</SheetTitle>
                {subtitle ? <SheetDescription className="text-xs">{subtitle}</SheetDescription> : null}
              </div>
            </div>
          </SheetHeader>
          <div className="flex-1 overflow-y-auto p-5">{children}</div>
          <SheetFooter className="border-t border-border p-4 sm:justify-end">
            <Button type="button" variant="outline" onClick={onCancel}>{t("action.cancel")}</Button>
            <Button type="button" onClick={onSave} className="gap-2">
              <FileCheck2 className="h-4 w-4" />{saveLabel ?? t("action.save")}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    );
  }

  function SortieFormBody() {
    const t = useT();
    const [motif, setMotif] = useState("");
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    return (
      <>
        <Field label={`${t("biens.detail.exitType")} *`}><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Ex : Réforme, Vol, Perte..." /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label={`${t("biens.detail.exitDate")} *`}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label={t("biens.sortie.protocole")}><Input placeholder={t("biens.sortie.numeroDocumentPlaceholder")} /></Field>
        </div>
        <Field label={t("biens.detail.col.observations")}><Textarea rows={2} placeholder={t("biens.sortie.observationsPlaceholder")} /></Field>
      </>
    );
  }

  // ── PiecesJointesSection — affiche les pièces jointes avec renommer + supprimer ──

  function PiecesJointesSection({
    bienId: _bienId, piecesJointes, loading, onDeleted, onRenamed,
  }: {
    bienId: number;
    piecesJointes: Array<{ id?: number; nom?: string; chemin: string }>;
    loading: boolean;
    onDeleted: (id: number) => void;
    onRenamed: (id: number, nom: string) => void;
  }) {
    const t = useT();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editNom, setEditNom] = useState("");
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const deleteMutation = useMutation({
      mutationFn: deletePieceJointe,
      onSuccess: (_, id) => { setDeletingId(null); onDeleted(id); toast.success(t("biens.pieces.deleted")); },
      onError: () => { setDeletingId(null); toast.error(t("biens.error.deleteFailed")); },
    });

    const renameMutation = useMutation({
      mutationFn: ({ id, nom }: { id: number; nom: string }) => renamePieceJointe(id, nom),
      onSuccess: (_, { id, nom }) => { setEditingId(null); onRenamed(id, nom); toast.success(t("biens.pieces.renamed")); },
      onError: () => { setEditingId(null); toast.error(t("biens.pieces.renameFailed")); },
    });

    return (
      <SubSection title={t("biens.securisation.field.pieces")}>
        {loading ? (
          <div className="flex items-center gap-2 py-3 text-xs text-muted-foreground">
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" /> {t("common.loading")}
          </div>
        ) : piecesJointes.length > 0 ? (
          <ul className="space-y-2">
            {piecesJointes.map((pj, i) => {
              const id    = pj.id ?? i;
              const chemin = pj.chemin ?? "";
              const nom    = pj.nom ?? chemin.split("/").pop() ?? t("biens.pieces.fichier");
              const href   = resolveFileUrl(chemin);
              const isEditing = editingId === id;

              return (
                <li key={id} className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-xs">
                  {isEditing ? (
                    <>
                      <Input
                        autoFocus
                        className="h-7 flex-1 text-xs"
                        value={editNom}
                        onChange={(e) => setEditNom(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === "Enter" && pj.id) renameMutation.mutate({ id: pj.id, nom: editNom });
                          if (e.key === "Escape") setEditingId(null);
                        }}
                      />
                      <button
                        type="button"
                        disabled={renameMutation.isPending}
                        onClick={() => pj.id && renameMutation.mutate({ id: pj.id, nom: editNom })}
                        className="inline-flex h-7 items-center gap-1 rounded bg-primary px-2 text-[11px] font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                      >
                        {renameMutation.isPending ? "…" : "OK"}
                      </button>
                      <button type="button" onClick={() => setEditingId(null)}
                        className="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-muted">
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </>
                  ) : (
                    <>
                      <span className="flex-1 truncate font-medium text-primary">📄 {nom}</span>
                      {/* Télécharger */}
                      <a href={href} target="_blank" rel="noopener noreferrer"
                        className="inline-flex h-7 w-7 items-center justify-center rounded border border-primary/40 text-primary hover:bg-primary/10"
                        title={t("action.export")}>
                        <Download className="h-3.5 w-3.5" />
                      </a>
                      {/* Renommer */}
                      {pj.id && (
                        <button type="button" title={t("action.edit")}
                          onClick={() => { setEditingId(pj.id!); setEditNom(nom); }}
                          className="inline-flex h-7 w-7 items-center justify-center rounded border border-border text-muted-foreground hover:bg-primary/10 hover:text-primary">
                          <Pencil className="h-3.5 w-3.5" />
                        </button>
                      )}
                      {/* Supprimer */}
                      {pj.id && (
                        <button type="button" title={t("action.delete")}
                          disabled={deletingId === pj.id || deleteMutation.isPending}
                          onClick={() => {
                            toast(t("biens.pieces.deleteConfirmTitle"), {
                              description: nom,
                              action: { label: t("action.delete"), onClick: () => { setDeletingId(pj.id!); deleteMutation.mutate(pj.id!); } },
                              cancel: { label: t("action.cancel"), onClick: () => {} },
                              duration: 8000,
                            });
                          }}
                          className="inline-flex h-7 w-7 items-center justify-center rounded border border-border text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:opacity-40">
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </>
                  )}
                </li>
              );
            })}
          </ul>
        ) : (
          <p className="text-sm text-muted-foreground">{t("biens.pieces.none")}</p>
        )}
      </SubSection>
    );
  }

  // ── SortieFormInline — formulaire contrôlé avec soumission réelle ────────────

/**
 * Champs alignés strictement sur le Swagger :
 *   POST /asset-exits — "Tous les champs sont facultatifs sauf asset_id."
 *   POST /asset-exits/{id}/bsps — seul quantiteServie est requis.
 *
 * Un bien patrimonial N'EST PAS DIVISIBLE — un seul bénéficiaire possible,
 * quantiteServie = 1 toujours (envoyé automatiquement), pas de saisie de
 * quantités. Le BSP matérialise simplement la remise physique du bien
 * à la personne/structure désignée.
 */
interface BeneficiaireRow {
  // Service ou individu qui reçoit physiquement le bien — même sélecteur en
  // cascade (organigramme) que le transfert BSP des consomptibles, service_id
  // et beneficiaire_id restant indépendants comme sur ce même modèle.
  bspTarget: import("@/components/consumables/ServiceBeneficiaireSelect").ServiceBeneficiaireValue;
  // Champs BSP
  dateEtablissement: string;
  observations: string;
  pieces: Array<{ file: File; nom: string }>;
}

function emptyBeneficiaireRow(): BeneficiaireRow {
  return {
    bspTarget: { serviceId: null, beneficiaireId: null, label: "" },
    dateEtablissement: new Date().toISOString().slice(0, 10),
    observations: "",
    pieces: [],
  };
}

function SortieFormInline({ isSaving, onCancel, onSave, bienNom, bienReference }: {
  isSaving: boolean;
  onCancel: () => void;
  bienNom: string;
  bienReference: string;
  onSave: (p: {
    exitTypeId?: number;
    motifSortie?: string;
    dateSortie?: string;
    observations?: string;
    piecesJointes: Array<{ file: File; nom: string }>;
    beneficiaires: Array<{ beneficiaireId: number | null; serviceId: number | null; quantiteDemandee?: number; quantiteAccordee?: number; quantiteServie: number; pieces: Array<{ file: File; nom: string }>; destinataireNom: string; dateEtablissement?: string; observations?: string }>;
  }) => void;
}) {
  const t = useT();
  const today = new Date().toISOString().slice(0, 10);
  const [exitTypeId, setExitTypeId]     = useState<number | null>(null);
  const [dateSortie, setDateSortie]     = useState(today);
  const [observations, setObservations] = useState("");
  const [pieces, setPieces]             = useState<Array<{ file: File; nom: string }>>([]);
  const [beneficiaires, setBeneficiaires] = useState<BeneficiaireRow[]>([]);

  // Types de sortie définis dans Administration > Types de sortie
  const { data: exitTypesData } = useQuery({
    queryKey: ["exit-types"],
    queryFn: () => listExitTypes({ limit: 200, include_inactive: false }),
    staleTime: Infinity,
  });
  const exitTypes: ApiExitType[] = exitTypesData?.data?.items ?? [];

  // Le sélecteur service/bénéficiaire (ServiceBeneficiaireSelect) charge
  // lui-même organigramme + utilisateurs — plus besoin de les récupérer ici.

  const updateRow = (i: number, patch: Partial<BeneficiaireRow>) =>
    setBeneficiaires((prev) => prev.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
  const removeRow = (i: number) => setBeneficiaires((prev) => prev.filter((_, idx) => idx !== i));

    const onPickFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      setPieces((prev) => [...prev, ...files.map((f) => ({ file: f, nom: f.name }))]);
      e.target.value = "";
    };

  const submit = () => {
    const selectedType = exitTypes.find((t) => t.id === exitTypeId);

    // Un bien N'EST PAS DIVISIBLE — max 1 bénéficiaire, quantiteServie = 1 toujours.
    // Si une ligne BSP est ajoutée, un service ou un bénéficiaire doit être sélectionné.
    const row = beneficiaires[0] ?? null;
    if (row && row.bspTarget.serviceId == null && row.bspTarget.beneficiaireId == null) {
      toast.error(t("biens.sortie.error.serviceOrBeneficiaireRequired"));
      return;
    }

    onSave({
      exitTypeId: selectedType?.id,
      motifSortie: selectedType?.code,
      dateSortie: dateSortie || undefined,
      observations: observations || undefined,
      piecesJointes: pieces,
      beneficiaires: row ? [{
        beneficiaireId: row.bspTarget.beneficiaireId,
        serviceId: row.bspTarget.serviceId,
        // Bien non divisible — quantiteServie = 1, pas de quantités demandée/accordée
        quantiteServie: 1,
        pieces: row.pieces,
        destinataireNom: row.bspTarget.label,
        dateEtablissement: row.dateEtablissement || undefined,
        observations: row.observations || undefined,
      }] : [],
    });
  };

    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <p className="text-sm font-semibold text-destructive">{t("biens.sortie.title")}</p>

        <div className="space-y-4">
          <Field label={t("biens.detail.exitType")}>
            <Select value={exitTypeId ? String(exitTypeId) : ""} onValueChange={(v) => setExitTypeId(Number(v))}>
              <SelectTrigger><SelectValue placeholder={t("biens.sortie.selectExitType")} /></SelectTrigger>
              <SelectContent>
                {exitTypes.length === 0 ? (
                  <div className="px-2 py-2 text-xs text-muted-foreground">
                    {t("biens.sortie.noExitTypeConfigured")}
                  </div>
                ) : (
                  exitTypes.map((t) => (
                    <SelectItem key={t.id} value={String(t.id)}>{t.nom}</SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
          </Field>

          {/* Remis à (Structure) — visible uniquement quand le type de sortie
              sélectionné a le champ "beneficiaire" activé (Administration >
              Types de sortie), au lieu d'une liste de noms codée en dur. */}
          {exitTypeId && (() => {
            const selectedType = exitTypes.find((t) => t.id === exitTypeId);
            const showRemis = selectedType?.beneficiaire === true;
            const bspTarget = beneficiaires[0]?.bspTarget ?? { serviceId: null, beneficiaireId: null, label: "" };
            return showRemis ? (
              <Field label={t("biens.sortie.remisA")}>
                <PosteOrgSelect
                  value={bspTarget.serviceId}
                  valueLabel={bspTarget.label}
                  selectableType="Poste"
                  placeholder={t("biens.form.selectPoste")}
                  searchPlaceholder={t("biens.form.searchPoste")}
                  onSelect={(node) => {
                    const v = { serviceId: node.id, beneficiaireId: node.utilisateur?.id ?? null, label: formatPosteLabel(node) };
                    if (!beneficiaires[0]) {
                      setBeneficiaires([{ ...emptyBeneficiaireRow(), bspTarget: v }]);
                    } else {
                      updateRow(0, { bspTarget: v });
                    }
                  }}
                  onClear={() => {
                    if (beneficiaires[0]) updateRow(0, { bspTarget: { serviceId: null, beneficiaireId: null, label: "" } });
                  }}
                />
              </Field>
            ) : null;
          })()}

          <Field label={t("biens.detail.exitDate")}>
            <Input type="date" value={dateSortie} onChange={(e) => setDateSortie(e.target.value)} />
          </Field>

          <Field label={t("biens.sortie.piecesMultiple")}>
            <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2.5 text-xs text-muted-foreground transition hover:bg-primary/10">
              <Upload className="h-4 w-4 shrink-0 text-primary" />
              <span>{t("biens.sortie.clickToAttach")}</span>
              <span className="ml-auto text-[10px]">PDF, Image, Word</span>
              <input type="file" multiple className="hidden" onChange={onPickFiles} />
            </label>
            {pieces.length > 0 && (
              <ul className="mt-2 space-y-1.5">
                {pieces.map((p, i) => (
                  <li key={i} className="flex items-center gap-2 rounded-md border border-border bg-muted/30 px-2 py-1.5 text-xs">
                    <span className="text-muted-foreground">📄</span>
                    <Input className="h-6 flex-1 text-[10px] px-1" value={p.nom}
                      onChange={(e) => { const u = [...pieces]; u[i] = { ...u[i], nom: e.target.value }; setPieces(u); }} />
                    <button type="button" onClick={() => setPieces((prev) => prev.filter((_, j) => j !== i))}
                      className="text-destructive"><X className="h-3.5 w-3.5" /></button>
                  </li>
                ))}
              </ul>
            )}
          </Field>

          <Field label={t("biens.detail.col.observations")}>
            <Textarea rows={2} value={observations} onChange={(e) => setObservations(e.target.value)} placeholder={t("biens.sortie.observationsPlaceholder")} />
          </Field>
        </div>

        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
          <Button size="sm" variant="destructive" onClick={submit} disabled={isSaving}>
            {isSaving ? t("biens.securisation.saving") : t("biens.sortie.confirm")}
          </Button>
        </div>
      </div>
    );
  }

  /* =========================================================
    BspFormInline — création/modification d'un BSP rattaché à une sortie.
    Un BSP est destiné soit à un individu, soit à un service — jamais les
    deux à la fois. La quantité effectivement servie (obligatoire côté
    backend) n'est plus un champ saisi séparément : elle reprend la
    quantité accordée (ou, à défaut, la quantité demandée). Le PDF est
    généré côté client (voir generateBspPdf ci-dessous), pas via le PDF
    officiel du serveur : celui-ci a un modèle à tableau fixe (14 lignes)
    et n'affiche pas le bénéficiaire.
    ========================================================= */

  /**
   * Un PDF par BSP, généré côté client — remplace le PDF officiel du serveur
   * (GET /bsps/{id}/document), dont le modèle a un tableau à 14 lignes fixes
   * et n'affiche pas le bénéficiaire. Le nom du destinataire est résolu côté
   * client (fiable) plutôt que lu depuis la réponse du serveur.
   */
  async function generateBspPdf(
    bien: { reference: string; nom: string },
    assetExit: ApiAssetExit,
    bsp: ApiBsp,
    destinataireNom: string,
  ): Promise<void> {
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
    const pageW = doc.internal.pageSize.getWidth();
    renderHeader();
    let y = MINEPIA_PDF_HEADER_HEIGHT + 6;

    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.text(`ORDRE DE SORTIE N° ${assetExit.id}`, 14, y);
    y += 8;

    doc.setFontSize(13);
    doc.setFont("helvetica", "bold");
    doc.text("BON DE SORTIE PROVISOIRE", pageW / 2, y, { align: "center" });
    y += 6;
    doc.text("DEMANDE DE MATERIEL", pageW / 2, y, { align: "center" });
    y += 10;

    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.text(`SERVICE DEMANDEUR : ${assetExit.service?.nom || "—"}`, 14, y);
    doc.text(`N° BSP : ${bsp.numero}`, pageW - 14, y, { align: "right" });
    y += 6;
    doc.text(`Bénéficiaire : ${destinataireNom || "—"}`, 14, y);
    doc.text(`Date d'établissement : ${bsp.dateEtablissement || "—"}`, pageW - 14, y, { align: "right" });
    y += 10;

    autoTable(doc, {
      startY: y,
      head: [["N° d'ordre", "Désignation des matières, denrées et objets", "Espèce des unités", "Demandée", "Accordée", "Servie", "Observations"]],
      body: [[
        "1",
        `${bien.reference} — ${bien.nom}`,
        "N",
        bsp.quantiteDemandee != null ? String(bsp.quantiteDemandee) : "—",
        bsp.quantiteAccordee != null ? String(bsp.quantiteAccordee) : "—",
        bsp.quantiteServie != null ? String(bsp.quantiteServie) : "—",
        bsp.observations || "",
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

    doc.save(`BSP-${bsp.numero}.pdf`);
  }

  /**
   * Bordereau récapitulatif — un seul PDF listant tous les BSP d'une même
   * soumission (un ou plusieurs bénéficiaires), au format du BON DE SORTIE
   * PROVISOIRE / DEMANDE DE MATERIEL officiel (tableau N° d'ordre /
   * Désignation / Espèce des unités / Quantités Demandée-Accordée-Servie /
   * Observations). Généré EN PLUS du PDF individuel de chaque BSP
   * (generateBspPdf), pas à sa place — fonctionnalité qui existait puis avait
   * été retirée, réintroduite ici sur la base des documents de référence
   * réels (formulaire MINEPIA vierge + exemple rempli).
   */
  async function generateBspBordereauPdf(
    bien: { reference: string; nom: string },
    assetExit: ApiAssetExit,
    items: Array<{ bsp: ApiBsp; destinataireNom: string }>,
  ): Promise<void> {
    if (items.length === 0) return;
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
    const pageW = doc.internal.pageSize.getWidth();
    renderHeader();
    let y = MINEPIA_PDF_HEADER_HEIGHT + 6;

    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.text(`ORDRE DE SORTIE N° ${assetExit.id}`, 14, y);
    y += 8;

    doc.setFontSize(13);
    doc.setFont("helvetica", "bold");
    doc.text("BON DE SORTIE PROVISOIRE", pageW / 2, y, { align: "center" });
    y += 6;
    doc.text("DEMANDE DE MATERIEL", pageW / 2, y, { align: "center" });
    y += 10;

    doc.setFontSize(9);
    doc.setFont("helvetica", "normal");
    doc.text(`SERVICE DEMANDEUR : ${assetExit.service?.nom || "—"}`, 14, y);
    doc.text(`BSP inclus : ${items.map((i) => i.bsp.numero).join(", ")}`, pageW - 14, y, { align: "right" });
    y += 10;

    autoTable(doc, {
      startY: y,
      head: [["N° d'ordre", "Désignation des matières, denrées et objets", "Bénéficiaire", "Espèce des unités", "Demandée", "Accordée", "Servie", "Observations"]],
      body: items.map(({ bsp, destinataireNom }, i) => [
        String(i + 1),
        `${bien.reference} — ${bien.nom}`,
        destinataireNom || "—",
        "N",
        bsp.quantiteDemandee != null ? String(bsp.quantiteDemandee) : "—",
        bsp.quantiteAccordee != null ? String(bsp.quantiteAccordee) : "—",
        bsp.quantiteServie != null ? String(bsp.quantiteServie) : "—",
        bsp.observations || "",
      ]),
      styles: { fontSize: 8, cellPadding: 2.5 },
      headStyles: { fillColor: [230, 230, 230], textColor: 20, fontStyle: "bold", fontSize: 7.5 },
      columnStyles: { 0: { cellWidth: 8 }, 3: { cellWidth: 16 }, 4: { cellWidth: 16 }, 5: { cellWidth: 16 }, 6: { cellWidth: 16 } },
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

    doc.save(`Bordereau-BSP-${bien.reference}-${new Date().toISOString().slice(0, 10)}.pdf`);
  }

  // ── EventTable — tableau générique avec boutons Modifier / Supprimer ─────────

  function EventTable<T extends { id: number }>({
    columns, rows, items, onEdit, onDelete, isDeleting, highlightFirstRow, useDropdownActions, extraMenuItem, rowClassName,
  }: {
    columns: string[];
    rows: (string | React.ReactNode)[][];
    items: T[];
    onEdit: (item: T) => void;
    onDelete: (item: T) => void;
    isDeleting?: boolean;
    /** Surligne légèrement la première ligne (ex. détenteur actuel d'une affectation). */
    highlightFirstRow?: boolean;
    /** Actions sous forme de menu "..." (3 points) au lieu des boutons icônes — utilisé par Affectation pour accueillir un item de menu supplémentaire sans surcharger la ligne. */
    useDropdownActions?: boolean;
    /** Item de menu additionnel par ligne, uniquement en mode useDropdownActions (Recommandation 90 — fiche détenteur, sur chaque ligne qui a un utilisateur, pas seulement le détenteur actuel). */
    extraMenuItem?: (item: T) => React.ReactNode;
    /** Classes additionnelles par ligne (ex: surligner en rouge une maintenance dont le coût dépasse la valeur du bien). Prioritaire sur highlightFirstRow/l'alternance. */
    rowClassName?: (item: T, idx: number) => string | undefined;
  }) {
    const t = useT();
    if (rows.length === 0) {
      return <p className="py-4 text-center text-sm text-muted-foreground">{t("biens.detail.noRecord")}</p>;
    }
    return (
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/40">
            <tr>
              {columns.map((col) => (
                <th key={col} className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">{col}</th>
              ))}
              <th className="w-24 px-3 py-2 text-right text-xs font-semibold text-muted-foreground">{t("common.actions")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, idx) => (
              <tr key={items[idx]?.id ?? idx} className={cn(
                "border-t border-border",
                rowClassName?.(items[idx], idx) ??
                  (highlightFirstRow && idx === 0 ? "bg-green-50 dark:bg-green-950/30" : idx % 2 === 1 && "bg-muted/10"),
              )}>
                {row.map((cell, ci) => (
                  <td key={ci} className="px-3 py-2 align-middle">{cell}</td>
                ))}
                <td className="px-3 py-2 text-right">
                  {useDropdownActions ? (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <button type="button" className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted" aria-label={t("common.actions")}>
                          <MoreVertical className="h-4 w-4" />
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => onEdit(items[idx])}><Pencil className="mr-2 h-4 w-4" /> {t("action.edit")}</DropdownMenuItem>
                        {extraMenuItem?.(items[idx])}
                        <DropdownMenuItem
                          className="text-destructive"
                          disabled={isDeleting}
                          onSelect={() => {
                            toast(t("biens.event.deleteConfirmTitle"), {
                              description: t("biens.event.deleteConfirmDesc"),
                              action: { label: t("action.delete"), onClick: () => onDelete(items[idx]) },
                              cancel: { label: t("action.cancel"), onClick: () => {} },
                              duration: 8000,
                            });
                          }}
                        >
                          <Trash2 className="mr-2 h-4 w-4" /> {t("action.delete")}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  ) : (
                    <div className="flex items-center justify-end gap-1">
                      <button
                        type="button"
                        onClick={() => onEdit(items[idx])}
                        className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-primary/10 hover:text-primary"
                        title={t("action.edit")}
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        disabled={isDeleting}
                        onClick={() => {
                          toast(t("biens.event.deleteConfirmTitle"), {
                            description: t("biens.event.deleteConfirmDesc"),
                            action: {
                              label: t("action.delete"),
                              onClick: () => onDelete(items[idx]),
                            },
                            cancel: {
                              label: t("action.cancel"),
                              onClick: () => {},
                            },
                            duration: 8000,
                          });
                        }}
                        className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive disabled:opacity-40"
                        title={t("action.delete")}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  }

  // ── Formulaires inline contrôlés ─────────────────────────────────────────────

  function AffectationFormInline({ isSaving, onCancel, onSave, initial }: {
    isSaving: boolean;
    onCancel: () => void;
    onSave: (p: AffectationSubmitPayload) => void;
    initial?: ApiAffectation | null;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    // L'affectation cible toujours le poste sélectionné dans l'organigramme —
    // l'utilisateur qui y est rattaché est identifié automatiquement, plus
    // besoin d'un champ Individu séparé.
    const [serviceId, setServiceId]         = useState<number | null>(initial?.service?.id ?? null);
    const [serviceNom, setServiceNom]       = useState(initial?.service?.nom ?? "");
    const [userId, setUserId]               = useState<number | null>(initial?.utilisateur?.id ?? null);
    const [typeAffectation, setTypeAffectation] = useState(initial?.typeAffectation ?? "AFFECTATION");
    const [dateDebut, setDateDebut]         = useState(initial?.dateDebut ?? today);
    const [commentaire, setCommentaire]     = useState(initial?.commentaire ?? "");
    const [methodConso, setMethodConso]     = useState<string>("");
    const [transferPieces, setTransferPieces] = useState<Array<{ file: File; nom: string }>>([]);
    const [bspData, setBspData]             = useState<AffectationBspData>({});
    const isEdit = !!initial;

    const selectPoste = (node: ApiOrgNode) => {
      setServiceId(node.id);
      setServiceNom(formatPosteLabel(node));
      setUserId(node.utilisateur?.id ?? null);
    };
    const clearPoste = () => {
      setServiceId(null);
      setServiceNom("");
      setUserId(null);
    };

    const submit = () => {
      if (!serviceId) { toast.error(t("biens.affectationForm.error.posteRequired")); return; }
      if (!userId) { toast.error(t("biens.affectationForm.error.posteNoUser")); return; }
      if (!dateDebut) { toast.error(t("biens.affectationForm.error.dateDebutRequired")); return; }
      onSave({
        service_id: serviceId ?? undefined,
        user_id: userId,
        typeAffectation,
        dateDebut,
        commentaire: commentaire || undefined,
        methodConso: methodConso || undefined,
        transferPieces: transferPieces.length > 0 ? transferPieces : undefined,
        bspData: methodConso === "BSP" ? bspData : undefined,
      });
    };

    const handleTransferFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      setTransferPieces((prev) => [...prev, ...files.map((f) => ({ file: f, nom: f.name }))]);
      e.target.value = "";
    };

    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <p className="text-sm font-semibold text-primary">{isEdit ? t("biens.affectationForm.editTitle") : t("biens.newAffectation")}</p>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label={t("biens.affectationForm.typeAffectation")}>
            <Select value={typeAffectation} onValueChange={setTypeAffectation}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["AFFECTATION", "TRANSFERT"].map((tv) => <SelectItem key={tv} value={tv}>{tv}</SelectItem>)}</SelectContent>
            </Select>
          </Field>

          <Field label={t("biens.affectationForm.methodeConsomptible")}>
            <Select value={methodConso} onValueChange={setMethodConso}>
              <SelectTrigger><SelectValue placeholder={t("biens.affectationForm.selectMethode")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="TRANSFER_DIRECT">Transfer Direct</SelectItem>
                <SelectItem value="BSP">BSP</SelectItem>
              </SelectContent>
            </Select>
          </Field>
        </div>

        {methodConso === "TRANSFER_DIRECT" && (
          <div className="space-y-4 p-4 rounded-lg border border-border bg-muted/30">
            <p className="text-sm font-medium text-foreground">Transfer Direct</p>
            <Field label={t("biens.affectationForm.piecesTransfer")}>
              <div className="flex items-center gap-2">
                <Input
                  type="file"
                  multiple
                  onChange={handleTransferFileChange}
                  className="flex-1"
                />
                <Button type="button" variant="outline" size="sm">
                  <Upload className="h-4 w-4 mr-2" />
                  {t("action.add")}
                </Button>
              </div>
              {transferPieces.length > 0 && (
                <ul className="space-y-2 mt-2">
                  {transferPieces.map((p, i) => (
                    <li key={i} className="flex items-center gap-2 text-sm">
                      <span className="flex-1 truncate">📎 {p.nom}</span>
                      <button
                        type="button"
                        onClick={() => setTransferPieces((prev) => prev.filter((_, j) => j !== i))}
                        className="text-destructive"
                      >
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Field>
          </div>
        )}

        {methodConso === "BSP" && (
          <div className="space-y-4 p-4 rounded-lg border border-border bg-muted/30">
            <p className="text-sm font-medium text-foreground">BSP ({t("biens.affectationForm.bonSortieProvisionnel")})</p>
            <div className="grid grid-cols-2 gap-3">
              <Field label={t("biens.affectationForm.quantiteDemandee")}>
                <Input
                  type="number"
                  value={bspData.quantiteDemandee ?? ""}
                  onChange={(e) => setBspData({ ...bspData, quantiteDemandee: e.target.value })}
                  placeholder="0"
                />
              </Field>
              <Field label={t("biens.affectationForm.quantiteAccordee")}>
                <Input
                  type="number"
                  value={bspData.quantiteAccordee ?? ""}
                  onChange={(e) => setBspData({ ...bspData, quantiteAccordee: e.target.value })}
                  placeholder="0"
                />
              </Field>
              <Field label={t("biens.affectationForm.quantiteServie")}>
                <Input
                  type="number"
                  value={bspData.quantiteServie ?? ""}
                  onChange={(e) => setBspData({ ...bspData, quantiteServie: e.target.value })}
                  placeholder="0"
                />
              </Field>
              <Field label={t("biens.affectationForm.dateBsp")}>
                <Input
                  type="date"
                  value={bspData.dateBsp || ""}
                  onChange={(e) => setBspData({ ...bspData, dateBsp: e.target.value })}
                />
              </Field>
            </div>
            <Field label={t("biens.affectationForm.observationsBsp")}>
              <Textarea
                rows={2}
                value={bspData.observations || ""}
                onChange={(e) => setBspData({ ...bspData, observations: e.target.value })}
                placeholder={t("biens.affectationForm.observationsBspPlaceholder")}
              />
            </Field>
          </div>
        )}

        {/* L'affectation cible le poste sélectionné dans l'organigramme —
            l'utilisateur rattaché à ce poste est le destinataire de
            l'affectation. */}
        <Field label={t("biens.affectationForm.affecterA")}>
          <PosteOrgSelect
            value={serviceId}
            valueLabel={serviceNom}
            onSelect={selectPoste}
            onClear={clearPoste}
            selectableType="Poste"
            placeholder={t("biens.form.selectPoste")}
            searchPlaceholder={t("biens.form.searchPoste")}
          />
        </Field>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label={`${t("biens.detail.col.dateAffectation")} *`}><Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} /></Field>
          <Field label={t("biens.detail.col.commentaire")}><Textarea rows={2} value={commentaire} onChange={(e) => setCommentaire(e.target.value)} placeholder={t("biens.affectationForm.observationsPlaceholder")} /></Field>
        </div>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
          <Button size="sm" onClick={submit} disabled={isSaving}>{isSaving ? t("biens.securisation.saving") : isEdit ? t("biens.form.saveVerb") : t("action.save")}</Button>
        </div>
      </div>
    );
  }

  function MaintenanceFormInline({ isSaving, onCancel, onSave, initial }: {
    isSaving: boolean;
    onCancel: () => void;
    onSave: (p: { etat_bien_id?: number; motif: string; cout: number; dateIntervention: string; dateRecuperation?: string; observations?: string }) => void;
    initial?: ApiMaintenance | null;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [motif, setMotif]                       = useState(initial?.motif ?? "");
    const [cout, setCout]                         = useState(initial?.cout != null ? String(initial.cout) : "");
    const [dateIntervention, setDateIntervention] = useState(initial?.dateIntervention ?? today);
    const [dateRecuperation, setDateRecuperation] = useState(initial?.dateRecuperation ?? "");
    const [observations, setObservations]         = useState(initial?.observations ?? "");
    const [etatBienId, setEtatBienId]             = useState<number | null>(initial?.etatBien?.id ?? null);
    const isEdit = !!initial;
    const fetchEtatBienOptions = async (search: string) => {
      const res = await listEtatBiens({ limit: search ? 50 : 200, search: search || undefined, is_delete: false });
      return (res.data?.data ?? []).map((e) => ({ id: e.id, nom: e.nom }));
    };
    const submit = () => {
      if (!motif.trim()) { toast.error(t("biens.form.motifRequired")); return; }
      const coutNum = parseFloat(cout);
      if (!cout || isNaN(coutNum) || coutNum <= 0) { toast.error(t("biens.maintenanceForm.error.coutRequired")); return; }
      if (coutNum > 99_999_999.99) { toast.error(t("biens.maintenanceForm.error.coutTooHigh")); return; }
      onSave({ etat_bien_id: etatBienId ?? undefined, motif, cout: coutNum, dateIntervention, dateRecuperation: dateRecuperation || undefined, observations: observations || undefined });
    };
    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <p className="text-sm font-semibold text-primary">{isEdit ? t("biens.maintenanceForm.editTitle") : t("biens.addMaintenance")}</p>
        <Field label={t("biens.list.col.etatBien")}>
          <RemoteSearchSelect
            value={etatBienId}
            onChange={(id) => setEtatBienId(id)}
            fetchOptions={fetchEtatBienOptions}
            queryKeyPrefix="etat-biens-maintenance"
            placeholder={t("biens.form.select")}
            searchPlaceholder={t("biens.list.filter.searchEtat")}
          />
        </Field>
        <Field label={`${t("biens.detail.col.motif")} *`}><Textarea rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.maintenanceForm.motifPlaceholder")} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label={`${t("biens.detail.col.coutFcfa")} *`}><Input type="number" value={cout} onChange={(e) => setCout(e.target.value)} placeholder="0" /></Field>
          <Field label={`${t("biens.detail.col.dateIntervention")} *`}><Input type="date" value={dateIntervention} onChange={(e) => setDateIntervention(e.target.value)} /></Field>
          <Field label={t("biens.maintenanceForm.dateRecuperation")}><Input type="date" value={dateRecuperation as string} onChange={(e) => setDateRecuperation(e.target.value)} /></Field>
        </div>
        <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations} onChange={(e) => setObservations(e.target.value)} placeholder={t("biens.sortie.observationsPlaceholder")} /></Field>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
          <Button size="sm" onClick={submit} disabled={isSaving}>{isSaving ? t("biens.securisation.saving") : isEdit ? t("biens.form.saveVerb") : t("action.save")}</Button>
        </div>
      </div>
    );
  }

  function ReevaluationFormInline({ isSaving, onCancel, onSave, initial }: {
    isSaving: boolean;
    onCancel: () => void;
    onSave: (p: { nouvelleValeur: number; dateReevaluation: string; motif: string; methodeEvaluation?: string; observations?: string; piecesJointes: File[]; piecesJointesNoms: string[] }) => void;
    initial?: ApiReevaluation | null;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [valeur, setValeur]             = useState(initial?.nouvelleValeur != null ? String(initial.nouvelleValeur) : "");
    const [date, setDate]                 = useState(initial?.dateReevaluation ?? today);
    const [methode, setMethode]           = useState(initial?.methodeEvaluation ?? "Expertise");
    const [motif, setMotif]               = useState(initial?.motif ?? "");
    const [observations, setObservations] = useState(initial?.observations ?? "");
    // Champ "Structure" retiré du formulaire (demande explicite, 2026-08-27) —
    // remplacé par des pièces jointes, désormais obligatoires (au moins une).
    const [pieces, setPieces] = useState<Array<{ file: File; nom: string }>>([]);
    const isEdit = !!initial;

    const onPickPiece = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      setPieces((prev) => [...prev, ...files.map((file) => ({ file, nom: file.name }))]);
      e.target.value = "";
    };
    const removePiece = (i: number) => setPieces((prev) => prev.filter((_, j) => j !== i));

    const submit = () => {
      if (!valeur || Number(valeur) <= 0) { toast.error(t("biens.reevalForm.error.valeurRequired")); return; }
      if (!motif.trim()) { toast.error(t("biens.form.motifRequired")); return; }
      if (pieces.length === 0) { toast.error(t("biens.reevalForm.error.piecesRequired")); return; }
      onSave({
        nouvelleValeur: Number(valeur),
        dateReevaluation: date,
        methodeEvaluation: methode,
        motif,
        observations: observations || undefined,
        piecesJointes: pieces.map((p) => p.file),
        piecesJointesNoms: pieces.map((p) => p.nom),
      });
    };
    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <p className="text-sm font-semibold text-primary">{isEdit ? t("biens.reevalForm.editTitle") : t("biens.detail.newReeval")}</p>
        <div className="grid grid-cols-2 gap-3">
          <Field label={`${t("biens.reevalForm.nouvelleValeurFcfa")} *`}><Input type="number" value={valeur} onChange={(e) => setValeur(e.target.value)} placeholder="0" /></Field>
          <Field label={`${t("biens.reevalForm.dateReevaluation")} *`}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
        </div>
        <Field label={t("biens.detail.col.methode")}>
          <Select value={methode} onValueChange={setMethode}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>{["Expertise", "Indice", "Marché", "Comptable"].map((m) => <SelectItem key={m} value={m}>{m}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label={`${t("biens.detail.col.motif")} *`}><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.form.motifPlaceholder")} /></Field>
        <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations as string} onChange={(e) => setObservations(e.target.value)} placeholder={t("biens.affectationForm.observationsPlaceholder")} /></Field>
        <Field label={`${t("biens.securisation.field.pieces")}`}>
          <label className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 px-3 py-2 text-xs text-muted-foreground hover:bg-primary/10">
            <Upload className="h-4 w-4 shrink-0 text-primary" />
            <span>{t("biens.form.joinFiles")}</span>
            <input type="file" multiple className="hidden" onChange={onPickPiece} />
          </label>
          {pieces.length > 0 && (
            <ul className="mt-2 space-y-1.5">
              {pieces.map((p, i) => (
                <li key={i} className="flex items-center gap-2">
                  <Input
                    className="h-7 flex-1 text-xs"
                    value={p.nom}
                    onChange={(e) => {
                      const updated = [...pieces];
                      updated[i] = { ...updated[i], nom: e.target.value };
                      setPieces(updated);
                    }}
                  />
                  <button type="button" onClick={() => removePiece(i)} className="text-muted-foreground hover:text-destructive">
                    <X className="h-3.5 w-3.5" />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </Field>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
          <Button size="sm" onClick={submit} disabled={isSaving}>{isSaving ? t("biens.securisation.saving") : isEdit ? t("biens.form.saveVerb") : t("action.save")}</Button>
        </div>
      </div>
    );
  }

  function DepreciationFormInline({ isSaving, onCancel, onSave, initial }: {
    isSaving: boolean;
    onCancel: () => void;
    onSave: (p: { typeDepreciation?: string; methodeAmortissement?: string; montantDepreciation?: number; tauxDepreciation?: number; dureeVie?: number; dateDepreciation: string; motif: string; observations?: string }) => void;
    initial?: ApiDepreciation | null;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [type, setType]             = useState(initial?.typeDepreciation ?? "Usure");
    const [methode, setMethode]       = useState(initial?.methodeAmortissement ?? "Linéaire");
    const [montant, setMontant]       = useState(initial?.montantDepreciation != null ? String(initial.montantDepreciation) : "");
    const [taux, setTaux]             = useState(initial?.tauxDepreciation != null ? String(initial.tauxDepreciation) : "");
    const [dureeVie, setDureeVie]     = useState(initial?.dureeVie != null ? String(initial.dureeVie) : "");
    const [date, setDate]             = useState(initial?.dateDepreciation ?? today);
    const [motif, setMotif]           = useState(initial?.motif ?? "");
    const [observations, setObservations] = useState(initial?.observations ?? "");
    const isEdit = !!initial;
    const submit = () => {
      if (!motif.trim()) { toast.error(t("biens.form.motifRequired")); return; }
      onSave({
        typeDepreciation: type,
        methodeAmortissement: methode,
        montantDepreciation: montant ? Number(montant) : undefined,
        tauxDepreciation: taux ? Number(taux) : undefined,
        dureeVie: dureeVie ? Number(dureeVie) : undefined,
        dateDepreciation: date,
        motif,
        observations: observations || undefined,
      });
    };
    return (
      <div className="animate-in fade-in-50 space-y-4 duration-150">
        <p className="text-sm font-semibold text-primary">{isEdit ? t("biens.deprecForm.editTitle") : t("biens.detail.newDeprec")}</p>
        <div className="grid grid-cols-2 gap-3">
          <Field label={t("biens.deprecForm.typeDepreciation")}>
            <Select value={type} onValueChange={setType}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Usure", "Obsolescence", "Dommage"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
            </Select>
          </Field>
          <Field label={t("biens.deprecForm.methodeAmortissement")}>
            <Select value={methode} onValueChange={setMethode}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Linéaire", "Dégressif", "Progressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
            </Select>
          </Field>
          <Field label={t("biens.detail.col.montant")}><Input type="number" value={montant} onChange={(e) => setMontant(e.target.value)} placeholder="0" /></Field>
          <Field label={t("biens.deprecForm.tauxDepreciation")}><Input type="number" value={taux} onChange={(e) => setTaux(e.target.value)} placeholder="0" /></Field>
          <Field label={t("biens.deprecForm.dureeVieAnnees")}><Input type="number" value={dureeVie} onChange={(e) => setDureeVie(e.target.value)} placeholder="0" /></Field>
          <Field label={`${t("common.date")} *`}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
        </div>
        <Field label={`${t("biens.detail.col.motif")} *`}><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.form.motifPlaceholder")} /></Field>
        <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations as string} onChange={(e) => setObservations(e.target.value)} /></Field>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={isSaving}>{t("action.cancel")}</Button>
          <Button size="sm" onClick={submit} disabled={isSaving}>{isSaving ? t("biens.securisation.saving") : isEdit ? t("biens.form.saveVerb") : t("action.save")}</Button>
        </div>
      </div>
    );
  }

  function AffectationDrawer({ open, bienId, onOpenChange, onSave }: {
    open: boolean; bienId: number | null; onOpenChange: (v: boolean) => void; onSave: () => void;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [serviceId, setServiceId] = useState<number | null>(null);
    const [serviceNom, setServiceNom] = useState("");
    const [userId, setUserId] = useState<number | null>(null);
    const [typeAffectation, setTypeAffectation] = useState("AFFECTATION");
    const [dateDebut, setDateDebut] = useState(today);
    const [dateFin, setDateFin] = useState("");
    const [commentaire, setCommentaire] = useState("");
    const [methodConso, setMethodConso] = useState<string>("");
    const [transferPieces, setTransferPieces] = useState<Array<{ file: File; nom: string }>>([]);
    const [bspData, setBspData] = useState<AffectationBspData>({});

    const selectPoste = (node: ApiOrgNode) => {
      setServiceId(node.id);
      setServiceNom(formatPosteLabel(node));
      setUserId(node.utilisateur?.id ?? null);
    };
    const clearPoste = () => {
      setServiceId(null);
      setServiceNom("");
      setUserId(null);
    };

    const handleTransferFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = Array.from(e.target.files ?? []);
      if (files.length === 0) return;
      setTransferPieces((prev) => [...prev, ...files.map((f) => ({ file: f, nom: f.name }))]);
      e.target.value = "";
    };

    const mutation = useMutation({
      mutationFn: () => {
        if (!bienId || !serviceId) throw new Error(t("biens.drawer.missingData"));

        let finalCommentaire = commentaire || "";
        
        // Handle consumable method logic
        if (methodConso === "TRANSFER_DIRECT" && transferPieces.length > 0) {
          finalCommentaire = `${finalCommentaire} [Méthode: Transfer Direct, Pièces: ${transferPieces.map(p => p.nom).join(", ")}]`.trim();
        } else if (methodConso === "BSP" && bspData) {
          finalCommentaire = `${finalCommentaire} [Méthode: BSP, Qté demandée: ${bspData.quantiteDemandee}, Qté accordée: ${bspData.quantiteAccordee}, Qté servie: ${bspData.quantiteServie}]`.trim();
        }

        return createAffectation(bienId, {
          service_id: serviceId,
          user_id: userId ?? undefined,
          typeAffectation,
          dateDebut,
          dateFin: dateFin || undefined,
          commentaire: finalCommentaire || undefined,
        });
      },
      onSuccess: () => {
        toast.success(t("biens.detail.toast.affectationSaved2"));
        // Reset consumable-specific states
        setMethodConso("");
        setTransferPieces([]);
        setBspData({});
        onSave();
      },
      onError: (err: unknown) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) toast.error(t("biens.drawer.affectationRouteUnavailable"));
        else toast.error(t("biens.detail.error.saveFailed", { status: "" }));
      },
    });
    return (
      <DrawerShell open={open} onOpenChange={onOpenChange} title={t("biens.newAffectation")}
        subtitle={t("biens.drawer.affectationSubtitle")} icon={UserIcon}
        onCancel={() => {
          setMethodConso("");
          setTransferPieces([]);
          setBspData({});
          onOpenChange(false);
        }}
        onSave={() => {
          if (!serviceId) { toast.error(t("biens.affectationForm.error.posteRequired")); return; }
          if (!userId) { toast.error(t("biens.affectationForm.error.posteNoUser")); return; }
          mutation.mutate();
        }}>
        <div className="space-y-4">
          <Field label={t("biens.affectationForm.typeAffectation")}>
            <Select value={typeAffectation} onValueChange={setTypeAffectation}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {["AFFECTATION", "TRANSFERT", "MISE_EN_GARDE"].map((tv) => (
                  <SelectItem key={tv} value={tv}>{tv}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label={t("biens.affectationForm.methodeConsomptible")}>
            <Select value={methodConso} onValueChange={setMethodConso}>
              <SelectTrigger><SelectValue placeholder={t("biens.affectationForm.selectMethode")} /></SelectTrigger>
              <SelectContent>
                <SelectItem value="TRANSFER_DIRECT">Transfer Direct</SelectItem>
                <SelectItem value="BSP">BSP</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          {methodConso === "TRANSFER_DIRECT" && (
            <div className="space-y-4 p-4 rounded-lg border border-border bg-muted/30">
              <p className="text-sm font-medium text-foreground">Transfer Direct</p>
              <Field label={t("biens.affectationForm.piecesTransfer")}>
                <div className="flex items-center gap-2">
                  <Input
                    type="file"
                    multiple
                    onChange={handleTransferFileChange}
                    className="flex-1"
                  />
                  <Button type="button" variant="outline" size="sm">
                    <Upload className="h-4 w-4 mr-2" />
                    {t("action.add")}
                  </Button>
                </div>
                {transferPieces.length > 0 && (
                  <ul className="space-y-2 mt-2">
                    {transferPieces.map((p, i) => (
                      <li key={i} className="flex items-center gap-2 text-sm">
                        <span className="flex-1 truncate">📎 {p.nom}</span>
                        <button
                          type="button"
                          onClick={() => setTransferPieces((prev) => prev.filter((_, j) => j !== i))}
                          className="text-destructive"
                        >
                          <X className="h-3.5 w-3.5" />
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </Field>
            </div>
          )}

          {methodConso === "BSP" && (
            <div className="space-y-4 p-4 rounded-lg border border-border bg-muted/30">
              <p className="text-sm font-medium text-foreground">BSP ({t("biens.affectationForm.bonSortieProvisionnel")})</p>
              <div className="grid grid-cols-2 gap-3">
                <Field label={t("biens.affectationForm.quantiteDemandee")}>
                  <Input
                    type="number"
                    value={bspData.quantiteDemandee ?? ""}
                    onChange={(e) => setBspData({ ...bspData, quantiteDemandee: e.target.value })}
                    placeholder="0"
                  />
                </Field>
                <Field label={t("biens.affectationForm.quantiteAccordee")}>
                  <Input
                    type="number"
                    value={bspData.quantiteAccordee ?? ""}
                    onChange={(e) => setBspData({ ...bspData, quantiteAccordee: e.target.value })}
                    placeholder="0"
                  />
                </Field>
                <Field label={t("biens.affectationForm.quantiteServie")}>
                  <Input
                    type="number"
                    value={bspData.quantiteServie ?? ""}
                    onChange={(e) => setBspData({ ...bspData, quantiteServie: e.target.value })}
                    placeholder="0"
                  />
                </Field>
                <Field label={t("biens.affectationForm.dateBsp")}>
                  <Input
                    type="date"
                    value={bspData.dateBsp || ""}
                    onChange={(e) => setBspData({ ...bspData, dateBsp: e.target.value })}
                  />
                </Field>
              </div>
              <Field label={t("biens.affectationForm.observationsBsp")}>
                <Textarea
                  rows={2}
                  value={bspData.observations || ""}
                  onChange={(e) => setBspData({ ...bspData, observations: e.target.value })}
                  placeholder={t("biens.affectationForm.observationsBspPlaceholder")}
                />
              </Field>
            </div>
          )}

          <Field label={t("biens.form.structureServicePosteRequired")}>
            <PosteOrgSelect
              value={serviceId}
              valueLabel={serviceNom}
              onSelect={selectPoste}
              onClear={clearPoste}
              selectableType="Poste"
              placeholder={t("biens.form.selectPoste")}
              searchPlaceholder={t("biens.form.searchPoste")}
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label={t("biens.drawer.dateDebutRequired")}><Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} /></Field>
            <Field label={t("biens.drawer.dateFin")}><Input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} /></Field>
          </div>
          <Field label={t("biens.detail.col.commentaire")}><Textarea rows={3} value={commentaire} onChange={(e) => setCommentaire(e.target.value)} /></Field>
        </div>
      </DrawerShell>
    );
  }

  function SortieDrawer({ open, onOpenChange, onSave }: {
    open: boolean; onOpenChange: (v: boolean) => void; onSave: () => void;
  }) {
    const t = useT();
    return (
      <DrawerShell open={open} onOpenChange={onOpenChange} title={t("biens.drawer.newSortie")}
        subtitle={t("biens.drawer.sortieSubtitle")} icon={DoorOpen}
        onCancel={() => onOpenChange(false)} onSave={onSave}>
        <div className="space-y-5"><SortieFormBody /></div>
      </DrawerShell>
    );
  }

  function MaintenanceDrawer({ open, bienId, onOpenChange, onSave }: {
    open: boolean; bienId: number | null; onOpenChange: (v: boolean) => void; onSave: () => void;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [etatBienId, setEtatBienId] = useState<number | null>(null);
    const [motif, setMotif] = useState("");
    const [cout, setCout] = useState("");
    const [dateIntervention, setDateIntervention] = useState(today);
    const [dateRecuperation, setDateRecuperation] = useState("");
    const [observations, setObservations] = useState("");

    const fetchEtatBienOptions = async (search: string) => {
      const res = await listEtatBiens({ limit: search ? 50 : 200, search: search || undefined, is_delete: false });
      return (res.data?.data ?? []).map((e) => ({ id: e.id, nom: e.nom }));
    };

    const mutation = useMutation({
      mutationFn: () => {
        if (!bienId) throw new Error(t("biens.drawer.bienIdMissing"));
        return createMaintenance(bienId, {
          etat_bien_id: etatBienId ?? undefined,
          motif,
          cout: Number(cout),
          dateIntervention,
          dateRecuperation: dateRecuperation || undefined,
          observations: observations || undefined,
        });
      },
      onSuccess: () => { toast.success(t("biens.drawer.maintenanceSaved")); onSave(); },
      onError: (err: unknown) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) toast.error(t("biens.drawer.maintenanceRouteUnavailable"));
        else toast.error(t("biens.detail.error.saveFailed", { status: "" }));
      },
    });
    return (
      <DrawerShell open={open} onOpenChange={onOpenChange} title={t("biens.addMaintenance")}
        subtitle={t("biens.drawer.maintenanceSubtitle")} icon={FileCheck2}
        onCancel={() => onOpenChange(false)}
        onSave={() => { if (!motif.trim() || !cout) { toast.error(t("biens.drawer.motifCoutRequired")); return; } mutation.mutate(); }}>
        <div className="space-y-4">
          <Field label={t("biens.list.col.etatBien")}>
            <RemoteSearchSelect
              value={etatBienId}
              onChange={(id) => setEtatBienId(id)}
              fetchOptions={fetchEtatBienOptions}
              queryKeyPrefix="etat-biens-maintenance-drawer"
              placeholder={t("biens.form.select")}
              searchPlaceholder={t("biens.list.filter.searchEtat")}
            />
          </Field>
          <Field label={`${t("biens.detail.col.motif")} *`}><Textarea rows={2} value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.maintenanceForm.motifPlaceholder")} /></Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label={`${t("biens.detail.col.coutFcfa")} *`}><Input type="number" value={cout} onChange={(e) => setCout(e.target.value)} placeholder="0" /></Field>
            <Field label={`${t("biens.detail.col.dateIntervention")} *`}><Input type="date" value={dateIntervention} onChange={(e) => setDateIntervention(e.target.value)} /></Field>
            <Field label={t("biens.maintenanceForm.dateRecuperation")}><Input type="date" value={dateRecuperation} onChange={(e) => setDateRecuperation(e.target.value)} /></Field>
          </div>
          <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations} onChange={(e) => setObservations(e.target.value)} placeholder={t("biens.sortie.observationsPlaceholder")} /></Field>
        </div>
      </DrawerShell>
    );
  }

  function ReevaluationDrawer({ open, bienId, onOpenChange, onSave }: {
    open: boolean; bienId: number | null; onOpenChange: (v: boolean) => void; onSave: () => void;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [valeur, setValeur] = useState("");
    const [date, setDate] = useState(today);
    const [methode, setMethode] = useState("Expertise");
    const [motif, setMotif] = useState("");
    const [observations, setObservations] = useState("");
    const mutation = useMutation({
      mutationFn: () => {
        if (!bienId) throw new Error(t("biens.drawer.bienIdMissing"));
        return createReevaluation(bienId, {
          nouvelleValeur: Number(valeur),
          dateReevaluation: date,
          methodeEvaluation: methode,
          motif,
          observations: observations || undefined,
        });
      },
      onSuccess: () => { toast.success(t("biens.drawer.reevalSaved")); onSave(); },
      onError: (err: unknown) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) toast.error(t("biens.drawer.reevalRouteUnavailable"));
        else toast.error(t("biens.detail.error.saveFailed", { status: "" }));
      },
    });
    return (
      <DrawerShell open={open} onOpenChange={onOpenChange} title={t("biens.detail.newReeval")}
        subtitle={t("biens.drawer.reevalSubtitle")} icon={FileCheck2}
        onCancel={() => onOpenChange(false)}
        onSave={() => { if (!valeur || Number(valeur) <= 0 || !motif.trim()) { toast.error(t("biens.drawer.valeurMotifRequired")); return; } mutation.mutate(); }}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Field label={`${t("biens.reevalForm.nouvelleValeurFcfa")} *`}><Input type="number" value={valeur} onChange={(e) => setValeur(e.target.value)} placeholder="0" /></Field>
            <Field label={`${t("common.date")} *`}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          </div>
          <Field label={t("biens.detail.col.methode")}>
            <Select value={methode} onValueChange={setMethode}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {["Expertise", "Indice", "Marché", "Comptable"].map((m) => (
                  <SelectItem key={m} value={m}>{m}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label={`${t("biens.detail.col.motif")} *`}><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.form.motifPlaceholder")} /></Field>
          <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations} onChange={(e) => setObservations(e.target.value)} /></Field>
        </div>
      </DrawerShell>
    );
  }

  function DepreciationDrawer({ open, bienId, onOpenChange, onSave }: {
    open: boolean; bienId: number | null; onOpenChange: (v: boolean) => void; onSave: () => void;
  }) {
    const t = useT();
    const today = new Date().toISOString().slice(0, 10);
    const [type, setType] = useState("Usure");
    const [methode, setMethode] = useState("Linéaire");
    const [duree, setDuree] = useState("");
    const [taux, setTaux] = useState("");
    const [montant, setMontant] = useState("");
    const [date, setDate] = useState(today);
    const [motif, setMotif] = useState("");
    const [observations, setObservations] = useState("");
    const mutation = useMutation({
      mutationFn: () => {
        if (!bienId) throw new Error(t("biens.drawer.bienIdMissing"));
        return createDepreciation(bienId, {
          typeDepreciation: type,
          methodeAmortissement: methode,
          dureeVie: duree ? Number(duree) : undefined,
          tauxDepreciation: taux ? Number(taux) : undefined,
          montantDepreciation: montant ? Number(montant) : undefined,
          dateDepreciation: date,
          motif,
          observations: observations || undefined,
        });
      },
      onSuccess: () => { toast.success(t("biens.drawer.deprecSaved")); onSave(); },
      onError: (err: unknown) => {
        const status = (err as { response?: { status?: number } })?.response?.status;
        if (status === 404) toast.error(t("biens.drawer.deprecRouteUnavailable"));
        else toast.error(t("biens.detail.error.saveFailed", { status: "" }));
      },
    });
    return (
      <DrawerShell open={open} onOpenChange={onOpenChange} title={t("biens.detail.newDeprec")}
        subtitle={t("biens.drawer.deprecSubtitle")} icon={FileCheck2}
        onCancel={() => onOpenChange(false)}
        onSave={() => { if (!motif.trim()) { toast.error(t("biens.form.motifRequired")); return; } mutation.mutate(); }}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Field label={t("biens.deprecForm.typeDepreciation")}>
              <Select value={type} onValueChange={setType}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{["Usure", "Obsolescence", "Dommage"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <Field label={t("biens.deprecForm.methodeAmortissement")}>
              <Select value={methode} onValueChange={setMethode}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{["Linéaire", "Dégressif", "Progressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <Field label={t("biens.deprecForm.dureeVieAnnees")}><Input type="number" value={duree} onChange={(e) => setDuree(e.target.value)} placeholder="0" /></Field>
            <Field label={t("biens.detail.col.taux")}><Input type="number" value={taux} onChange={(e) => setTaux(e.target.value)} placeholder="0" /></Field>
            <Field label={t("biens.detail.col.montant")}><Input type="number" value={montant} onChange={(e) => setMontant(e.target.value)} placeholder="0" /></Field>
            <Field label={`${t("common.date")} *`}><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          </div>
          <Field label={`${t("biens.detail.col.motif")} *`}><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder={t("biens.form.motifPlaceholder")} /></Field>
          <Field label={t("biens.detail.col.observations")}><Textarea rows={2} value={observations} onChange={(e) => setObservations(e.target.value)} /></Field>
        </div>
      </DrawerShell>
    );
  }

