/**
 * ============================================================
 * biens.api.ts — Gestion des biens patrimoniaux
 * ============================================================
 *
 * FLUX COMPLET D'UN BIEN :
 *
 *  1. CRÉER   : BienFormPage → buildBienPayload() → createBien() → POST /assets (multipart/form-data)
 *  2. LISTER  : BiensPage → listBiens() → GET /assets → normalizeBien() → tableau
 *  3. DÉTAIL  : BienDetail → getBienById() → GET /assets/{id} → normalizeBien() → fiche
 *  4. MODIFIER : BienFormPage → buildBienPayload() → updateBien() → POST /assets/{id} (multipart/form-data)
 *  5. SUPPRIMER : deleteBien() → DELETE /assets/{id}
 *
 * PROBLÈME RÉSOLU — Nommage incohérent backend/frontend :
 *   Le backend retourne : "categorie", "typeBien", "structure"
 *   Le frontend attend  : "category",  "assetType", "service"
 *   → normalizeBien() fait le mapping à la réception de chaque réponse.
 *
 * CHAMPS ENVOYÉS AU BACKEND (Swagger) :
 *   reference?, nom, numeroSerie?, description?, dateAcquisition, valeur,
 *   sourceFinancement, modeAcquisition, statut?,
 *   typeFournisseur?, fournisseurNom, fournisseurEmail, fournisseurTelephone,
 *   fournisseurAdresse?, fournisseurVille?, fournisseurPays?,
 *   category_id, asset_type_id, etat_bien_id, service_id,
 *   asset_sub_type_id?,
 *   project_ids[]?, photos[]?, piecesJointes[]?, piecesJointesNoms[]?
 *
 * CHAMPS PERSONNALISÉS (Recommandation 92 — corrigé, ne suivait aucun de ces
 * formats avant) : champsValues[{champ_id}] n'est PAS ce que le backend
 * attend. Format réel, une chaîne unique "champId:valeur;champId:valeur" :
 *   - POST /assets       → champ "champs_valeurs"
 *   - POST /assets/{id}  → "champs_valeurs_update" (valeur déjà existante,
 *     clé = id de l'enregistrement de valeur) + "champs_valeurs_ajout"
 *     (première valeur pour ce champ, clé = champ_id)
 */

import api from "../axios";
import type { ApiResponse, PaginatedData, PaginatedMeta } from "../types";
import {
  normalizeAffectation,
  normalizeMaintenance,
  normalizeReevaluation,
  normalizeDepreciation,
} from "./asset-events.api";

// ─── Type frontend d'un bien (après normalisation) ─────────────────────────
// Ce type est ce que le reste de l'app utilise — les noms sont stables.
// normalizeBien() garantit que les données du backend y correspondent.

export interface ApiBien {
  id: number;
  reference: string;
  nom: string;
  numeroSerie?: string;
  description?: string;
  dateAcquisition: string;
  valeur: number;
  sourceFinancement?: string;
  exercice?: number | string;
  modeAcquisition?: string;
  statut?: string;
  // Mercuriale (référence de prix officielle — https://www.mercuriale.cm/)
  code?: string;
  prixMercurial?: number;
  // Fournisseur — aplati depuis l'objet imbriqué { type, nom, email, ... }
  typeFournisseur?: string;
  fournisseurNom?: string;
  fournisseurEmail?: string;
  fournisseurTelephone?: string;
  fournisseurAdresse?: string;
  fournisseurVille?: string;
  fournisseurPays?: string;
  // Relations — toujours des objets { id, nom } après normalisation
  category?: { id: number; nom: string };
  assetType?: { id: number; nom: string };
  // Recommandation 6.a — sous-type associé au type de bien sélectionné
  assetSubType?: { id: number; nom: string };
  etatBien?: { id: number; nom: string };
  service?: { id: number; nom: string; sigle?: string };
  utilisateur?: {
    id: number;
    firstName: string;
    lastName: string;
    email: string;
    matricule?: string | null;
    assignedRoles?: Array<{ id: number; nom: string }>;
    service?: { id: number; nom: string; typeService?: string };
  };
  projects?: Array<{ id: number; nom: string }>;
  // Recommandation 6.a — valeurs des champs personnalisés (champ_id → valeur)
  champsValues?: Record<number, string>;
  // Recommandation 92 — id de l'enregistrement de valeur, par champ_id (voir CreateBienPayload)
  champsValueIds?: Record<number, number>;
  // Fichiers — normalisés en tableaux d'objets { id, nom, chemin }
  photos?: Array<{ id?: number; nom?: string; chemin: string }>;
  piecesJointes?: Array<{ id?: number; nom?: string; chemin: string }>;
  // Données embarquées dans GET /assets/{id} — évite des requêtes séparées
  affectations?: import("./asset-events.api").ApiAffectation[];
  maintenances?: import("./asset-events.api").ApiMaintenance[];
  reevaluations?: import("./asset-events.api").ApiReevaluation[];
  depreciations?: import("./asset-events.api").ApiDepreciation[];
  // Réévaluation / dépréciation / amortissement comptable — présents dans
  // GET /assets/{id} uniquement
  activeReevaluation?: boolean;
  activeDepreciation?: boolean;
  activeAmortissement?: boolean;
  amortissement?: {
    valeurAcquisition: number | null;
    valeurActuelle: number | null;
    amortissementAnnuel: number | null;
    amortissementCumule: number | null;
    dureeVie: number | null;
    taux: number | null;
    anneesEcoulees: number | null;
    dateAcquisition: string | null;
  };
  createdAt?: string;
  updatedAt?: string;
  // Confirmé en direct sur GET /assets : champ booléen direct, plus fiable
  // que de dériver "sécurisé ou pas" depuis GET /securities + assets[].
  securise?: boolean;
  /** Somme des coûts de maintenance de ce bien — GET /assets/{id} uniquement, peut être null. */
  coutTotalMaintenance?: number | null;
  /**
   * Accusé de réception du DÉTENTEUR ACTUEL — confirmé côté backend
   * (AssetResponseBuilder::resolveCurrentReceived) : null si aucune
   * affectation, ou si la dernière affectation n'est pas celle du détenteur
   * actuel ; sinon true/false selon l'accusé réel. Présent sur GET /assets
   * (liste) ET GET /assets/{id} (détail) — pas besoin d'un appel séparé par
   * bien pour l'icône "reçu" (voir allColumns "nom" dans Biens.tsx).
   */
  received?: boolean | null;
  /**
   * Présents UNIQUEMENT quand ce bien vient de GET /asset-assignments (liste
   * des biens d'un utilisateur non-admin, voir listAssetAssignmentsPage) —
   * absents (undefined) pour un bien chargé via GET /assets classique.
   *   - assignmentId : id de l'AFFECTATION (pas du bien) — nécessaire pour
   *     POST /asset-assignments/{assignmentId}/acknowledge, qui opère sur
   *     l'affectation, alors que toutes les autres actions bien continuent
   *     de prendre l'id du bien (assets/{id}).
   *   - detenteur : true si l'utilisateur connecté est le détenteur direct
   *     de cette affectation (par opposition à un membre du même service
   *     sans être personnellement désigné).
   */
  assignmentId?: number;
  detenteur?: boolean;
  /**
   * Service de restitution par défaut pour ce bien (préremplissage du
   * formulaire) — présent sur GET /assets et GET /assets/{id}, peut être
   * null si aucun n'a été défini. À l'issue de la date de fin de la source
   * de financement liée au bien, celui-ci est restitué automatiquement à ce
   * service (logique 100% backend, voir AssetAssignmentService::restituerAsset).
   * Remplace l'ancien champ "utilisateur de restitution" (user_restitution_id),
   * qui n'est plus celui accepté par le backend (2026-09-01).
   */
  serviceRestitution?: { id: number; nom: string } | null;
  /** true si le bien a déjà été restitué (POST /assets/{id}/restituer). */
  isRestitue?: boolean;
  /** true si un service de restitution a été défini sur ce bien (sera restitué automatiquement à la fin du financement). */
  doitEtreRestitue?: boolean;
}

// ─── Payload de création/modification ──────────────────────────────────────
// Ce sont les champs envoyés au backend via multipart/form-data.
// Les relations sont envoyées comme IDs (category_id, asset_type_id, etc.)

export interface CreateBienPayload {
  reference?: string;
  nom: string;
  numeroSerie?: string;
  description?: string;
  dateAcquisition: string;           // format YYYY-MM-DD
  valeur: number;
  sourceFinancement?: string;
  modeAcquisition?: string;
  statut?: string;
  code?: string;
  prixMercurial?: number;
  typeFournisseur?: string;
  fournisseurNom: string;
  fournisseurEmail: string;
  fournisseurTelephone: string;
  fournisseurAdresse?: string;
  fournisseurVille?: string;
  fournisseurPays?: string;
  category_id: number;
  asset_type_id: number;
  // Recommandation 6.a — sous-type lié au type de bien (facultatif)
  asset_sub_type_id?: number;
  // Recommandation 6.a — valeurs des champs personnalisés liés à la catégorie
  champsValues?: Record<number, string>;
  // Recommandation 92 — id de l'enregistrement de valeur existant, par champ_id
  // (nécessaire pour distinguer "modifier une valeur existante" de "ajouter
  // une première valeur" lors d'un update — le backend utilise deux champs
  // différents pour ça, voir buildFormData ci-dessous).
  champsValueIds?: Record<number, number>;
  etat_bien_id?: number;
  service_id: number;
  user_id?: number;       // prioritaire sur service_id si fourni
  /**
   * Optionnel. ID du service de restitution par défaut pour ce bien —
   * remplace user_restitution_id (le backend n'accepte plus un utilisateur,
   * confirmé sur POST /assets, POST /assets/{id} et POST /assets/{id}/restituer).
   */
  service_restitution_id?: number;
  project_ids?: number[];
  // Localisation (Terrains/Bâtiments) — POST /assets et POST /assets/{id}
  // acceptent directement latitude/longitude dans la même requête (voir
  // AssetManagementService::applyPayload) : pas besoin d'un appel séparé à
  // POST /assets/{id}/locations pour un simple point.
  latitude?: number;
  longitude?: number;
  // Activation de la réévaluation, de la dépréciation et de l'amortissement
  // — actifs par défaut. Peuvent aussi être basculés ultérieurement via
  // POST /assets/{id}/reevaluation, /depreciation, /amortissement.
  activeReevaluation?: boolean;
  activeDepreciation?: boolean;
  activeAmortissement?: boolean;
  // Fichiers — passés via buildFormData(), pas directement dans le JSON
  photosFiles?: File[];
  piecesJointesFiles?: File[];
  piecesJointesNoms?: string[];
}

export type UpdateBienPayload = Partial<CreateBienPayload>;

// ─── Paramètres de filtrage pour la liste ──────────────────────────────────

export interface ListBiensParams {
  page?: number;
  limit?: number;
  search?: string;
  /** Un ou plusieurs ids, séparés par virgule (ex: "49,324") — confirmé en direct (2026-08-27). */
  service_id?: number | string;
  category_id?: number;
  statut?: string;
  /** true = biens sécurisés uniquement, false = non sécurisés uniquement, absent = tous. */
  securise?: boolean;
  /** Un ou plusieurs ids, séparés par virgule (ex: "2,5") — confirmé en direct (2026-08-27). */
  project_id?: number | string;
  /** true = accusé de réception effectué par le détenteur actuel, false = non accusé, absent = tous. */
  received?: boolean;
  /** true = biens ayant un service de restitution défini (restituables), false = sans, absent = tous. */
  restituable?: boolean;
}

// ─── Construction du FormData ───────────────────────────────────────────────
// Convertit CreateBienPayload en FormData pour l'envoi multipart/form-data.
// Nécessaire car le backend accepte aussi des fichiers (photos, pièces jointes).

function buildFormData(payload: CreateBienPayload, isUpdate = false): FormData {
  const fd = new FormData();

  // Champs texte/date optionnels — on ne les envoie que s'ils ont une valeur
  const scalars: Array<keyof CreateBienPayload> = [
    "reference", "nom", "numeroSerie", "description", "dateAcquisition",
    "sourceFinancement", "modeAcquisition", "statut", "code",
    "typeFournisseur", "fournisseurNom", "fournisseurEmail",
    "fournisseurTelephone", "fournisseurAdresse", "fournisseurVille", "fournisseurPays",
  ];
  for (const key of scalars) {
    const v = payload[key];
    if (v !== undefined && v !== null && v !== "") {
      fd.append(key, String(v));
    }
  }

  // Prix mercuriale — optionnel, non envoyé si absent (contrairement à "valeur" qui est obligatoire)
  if (payload.prixMercurial !== undefined && payload.prixMercurial !== null) {
    fd.append("prixMercurial", String(payload.prixMercurial));
  }

  // IDs des relations — toujours présents
  fd.append("valeur", String(payload.valeur));
  fd.append("category_id", String(payload.category_id));
  fd.append("asset_type_id", String(payload.asset_type_id));
  fd.append("etat_bien_id", String(payload.etat_bien_id));
  fd.append("service_id", String(payload.service_id));
  if (payload.user_id) fd.append("user_id", String(payload.user_id));
  if (payload.service_restitution_id) fd.append("service_restitution_id", String(payload.service_restitution_id));

  // Recommandation 6.a — sous-type de bien (si sélectionné)
  if (payload.asset_sub_type_id) {
    fd.append("asset_sub_type_id", String(payload.asset_sub_type_id));
  }

  // Localisation (Terrains/Bâtiments) — toujours envoyées ensemble, dans la
  // même requête que la création/modification du bien.
  if (payload.latitude != null && payload.longitude != null) {
    fd.append("latitude", String(payload.latitude));
    fd.append("longitude", String(payload.longitude));
  }

  if (payload.activeReevaluation !== undefined) {
    fd.append("activeReevaluation", String(payload.activeReevaluation));
  }
  if (payload.activeDepreciation !== undefined) {
    fd.append("activeDepreciation", String(payload.activeDepreciation));
  }
  if (payload.activeAmortissement !== undefined) {
    fd.append("activeAmortissement", String(payload.activeAmortissement));
  }

  // Recommandation 6.a / 92 — valeurs des champs personnalisés (champ_id → valeur).
  // Le format champsValues[{champ_id}] n'est PAS ce que le backend attend
  // (vérifié au Swagger) — il veut une chaîne unique "champId:valeur;champId:valeur",
  // et le nom du champ diffère selon création/modification :
  //   - POST /assets       → "champs_valeurs"
  //   - POST /assets/{id}  → "champs_valeurs_update" (valeur déjà existante,
  //                           clé = id de l'enregistrement de valeur, pas du champ)
  //                          + "champs_valeurs_ajout" (première valeur pour ce
  //                           champ, clé = champ_id)
  if (payload.champsValues) {
    const entries = Object.entries(payload.champsValues).filter(([, v]) => String(v).trim() !== "");
    if (!isUpdate) {
      if (entries.length > 0) {
        fd.append("champs_valeurs", entries.map(([champId, v]) => `${champId}:${v}`).join(";"));
      }
    } else {
      const updates: string[] = [];
      const ajouts: string[] = [];
      for (const [champId, v] of entries) {
        const inputId = payload.champsValueIds?.[Number(champId)];
        if (inputId != null) {
          updates.push(`${inputId}:${v}`);
        } else {
          ajouts.push(`${champId}:${v}`);
        }
      }
      if (updates.length > 0) fd.append("champs_valeurs_update", updates.join(";"));
      if (ajouts.length > 0) fd.append("champs_valeurs_ajout", ajouts.join(";"));
    }
  }

  // Projets associés (tableau d'IDs)
  if (payload.project_ids && payload.project_ids.length > 0) {
    payload.project_ids.forEach((id) => fd.append("project_ids[]", String(id)));
  }

  // Fichiers photos
  if (payload.photosFiles && payload.photosFiles.length > 0) {
    payload.photosFiles.forEach((file) => fd.append("photos[]", file));
  }

  // Fichiers pièces jointes + leurs libellés
  if (payload.piecesJointesFiles && payload.piecesJointesFiles.length > 0) {
    payload.piecesJointesFiles.forEach((file) => fd.append("piecesJointes[]", file));
  }
  if (payload.piecesJointesNoms && payload.piecesJointesNoms.length > 0) {
    payload.piecesJointesNoms.forEach((nom) => fd.append("piecesJointesNoms[]", nom));
  }

  return fd;
}

// ─── Normalisation de la réponse du backend ────────────────────────────────
//
//   Backend retourne  →  Frontend attend
//   ─────────────────────────────────────
//   "categorie"       →  "category"
//   "typeBien"        →  "assetType"
//   "structure"       →  "service"      (liste)
//   "service"         →  "service"      (détail)
//   "responsable"      →  "utilisateur"  (liste — {id,nom,prenom,email})
//   "utilisateur"      →  "utilisateur"  (détail — {id,firstName,lastName,email,...})
//   "projets"         →  "projects"
//   "fournisseur"     →  fournisseurNom / fournisseurEmail / ...  (aplati)
//   photos[{chemin}]  →  photos[{chemin}]  (objets normalisés)
//   piecesJointes[{chemin}] → piecesJointes[{chemin}]
//   affectations[]    →  affectations[]   (embarquées dans détail)
//   maintenances[]    →  maintenances[]
//   reevaluations[]   →  reevaluations[]
//   depreciations[]   →  depreciations[]

// eslint-disable-next-line @typescript-eslint/no-explicit-any
/**
 * Exportée pour être réutilisée par asset-assignments.api.ts : le listing
 * des affectations (GET /asset-assignments, utilisé pour le tableau des
 * biens d'un utilisateur non-admin) renvoie des items avec exactement les
 * mêmes noms de champs bruts (categorie/typeBien/structure/responsable...)
 * que GET /assets — confirmé en comparant les deux réponses documentées.
 */
export function normalizeBien(raw: any): ApiBien {
  const resolvedId: number = raw.id ?? raw.asset_id ?? 0;

  // Catégorie : "categorie" (liste) ou "category" (déjà normalisé)
  const rawCategory = raw.categorie ?? raw.category ?? null;
  const category = rawCategory
    ? { id: rawCategory.id, nom: rawCategory.nom ?? rawCategory.name ?? "" }
    : raw.category_id ? { id: raw.category_id, nom: "" } : undefined;

  // Type de bien : "typeBien" (liste/détail) ou "assetType"
  const rawAssetType = raw.typeBien ?? raw.assetType ?? raw.asset_type ?? null;
  const assetType = rawAssetType
    ? { id: rawAssetType.id, nom: rawAssetType.nom ?? rawAssetType.name ?? "" }
    : raw.asset_type_id ? { id: raw.asset_type_id, nom: "" } : undefined;

  // Sous-type de bien : "sousType"/"assetSubType"/"asset_sub_type"/"subType"
  const rawSubType = raw.sousType ?? raw.assetSubType ?? raw.subType ?? raw.asset_sub_type ?? null;
  const assetSubType = rawSubType
    ? { id: rawSubType.id, nom: rawSubType.nom ?? rawSubType.name ?? "" }
    : raw.asset_sub_type_id ? { id: raw.asset_sub_type_id, nom: "" } : undefined;

  // Valeurs des champs personnalisés — GET /assets/{id} embarque "champs" :
  // [{ id, nom, inputs: [{ id, valeur }, ...] }] (même forme que
  // GET /assets/{id}/champs). champsValues retient la PREMIÈRE valeur par
  // champ (le formulaire n'édite qu'une valeur par champ) ; champsValueIds
  // garde l'id de CET enregistrement (Input) — nécessaire pour PUT /inputs/{id}
  // / "champs_valeurs_update" lors d'une modification, sans quoi chaque
  // sauvegarde recréait une nouvelle valeur au lieu de mettre à jour l'existante.
  let champsValues: Record<number, string> | undefined;
  let champsValueIds: Record<number, number> | undefined;
  const rawChamps = raw.champs ?? raw.champsValues ?? raw.champs_values ?? raw.customFields ?? raw.valeursChamps;
  if (Array.isArray(rawChamps) && rawChamps.length > 0) {
    champsValues = {};
    champsValueIds = {};
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    for (const c of rawChamps as any[]) {
      const inputs = Array.isArray(c?.inputs) ? c.inputs : null;
      if (inputs) {
        // Forme réelle backend : { id: champId, nom, inputs: [{id, valeur}] }
        const champId = c?.id;
        if (champId == null || inputs.length === 0) continue;
        champsValues[Number(champId)] = String(inputs[0]?.valeur ?? inputs[0]?.value ?? "");
        if (inputs[0]?.id != null) champsValueIds[Number(champId)] = Number(inputs[0].id);
      } else {
        // Forme alternative à plat (compat) : { champ_id, valeur, id }
        const champId = c?.champ_id ?? c?.champId ?? c?.champ?.id;
        if (champId == null) continue;
        champsValues[Number(champId)] = String(c?.valeur ?? c?.value ?? "");
        if (c?.id != null) champsValueIds[Number(champId)] = Number(c.id);
      }
    }
  } else if (rawChamps && typeof rawChamps === "object") {
    champsValues = {};
    for (const [k, v] of Object.entries(rawChamps)) {
      if (k && !Number.isNaN(Number(k))) champsValues[Number(k)] = String(v ?? "");
    }
  }

  // Service / Structure : "structure" (liste) ou "service" (détail)
  const rawService = raw.structure ?? raw.service ?? null;
  const service = rawService
    ? { id: rawService.id, nom: rawService.nom ?? rawService.name ?? "", sigle: rawService.sigle }
    : raw.service_id ? { id: raw.service_id, nom: "" } : undefined;

  // État du bien
  const rawEtat = raw.etatBien ?? raw.etat_bien ?? null;
  const etatBien = rawEtat
    ? { id: rawEtat.id, nom: rawEtat.nom ?? rawEtat.name ?? "" }
    : raw.etat_bien_id ? { id: raw.etat_bien_id, nom: "" } : undefined;

  // Utilisateur responsable : "responsable" (liste — {id,nom,prenom,email})
  // ou "utilisateur" (détail — {id,firstName,lastName,email,assignedRoles,service})
  const rawResp = raw.utilisateur ?? raw.responsable ?? raw.user ?? null;
  const utilisateur = rawResp
    ? {
        id: rawResp.id,
        firstName: rawResp.firstName ?? rawResp.prenom ?? "",
        lastName: rawResp.lastName ?? rawResp.nom ?? "",
        email: rawResp.email ?? "",
        matricule: rawResp.matricule ?? null,
        assignedRoles: rawResp.assignedRoles ?? rawResp.assigned_roles ?? undefined,
        service: rawResp.service ?? undefined,
      }
    : undefined;

  // Projets : "projets"/"projects" (tableau, détail) ou "projet" (objet singulier, liste)
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawProjets: any[] = raw.projets ?? raw.projects ?? (raw.projet ? [raw.projet] : []);
  const projects = Array.isArray(rawProjets) && rawProjets.length > 0
    ? rawProjets.map((p: { id: number; nom: string }) => ({ id: p.id, nom: p.nom ?? "" }))
    : undefined;

  // Fournisseur : l'API détail retourne un objet imbriqué { type, nom, email, telephone, adresse, ville, pays }
  // L'API liste peut retourner les champs à plat en camelCase
  const f = raw.fournisseur;
  const fournisseurNom       = raw.fournisseurNom       ?? raw.fournisseur_nom       ?? f?.nom       ?? f?.name       ?? undefined;
  const fournisseurEmail     = raw.fournisseurEmail     ?? raw.fournisseur_email     ?? f?.email     ?? undefined;
  const fournisseurTelephone = raw.fournisseurTelephone ?? raw.fournisseur_telephone ?? f?.telephone ?? f?.phone      ?? undefined;
  const fournisseurAdresse   = raw.fournisseurAdresse   ?? raw.fournisseur_adresse   ?? f?.adresse   ?? f?.address    ?? undefined;
  const fournisseurVille     = raw.fournisseurVille     ?? raw.fournisseur_ville     ?? f?.ville     ?? f?.city       ?? undefined;
  const fournisseurPays      = raw.fournisseurPays      ?? raw.fournisseur_pays      ?? f?.pays      ?? f?.country    ?? undefined;
  const typeFournisseur      = raw.typeFournisseur      ?? raw.type_fournisseur      ?? f?.type      ?? undefined;

  // Photos : tableau d'objets { id?, nom?, chemin } ou de strings
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawPhotos: any[] = raw.photos ?? raw.fichiers ?? raw.images ?? [];
  const photos = Array.isArray(rawPhotos) && rawPhotos.length > 0
    ? rawPhotos.map((p: unknown) => {
        if (typeof p === "string") return { chemin: p };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const o = p as any;
        return { id: o.id, nom: o.nom ?? o.name, chemin: o.chemin ?? o.url ?? o.path ?? "" };
      }).filter((p) => p.chemin)
    : undefined;

  // Pièces jointes : tableau d'objets { id?, nom?, chemin } ou de strings
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawPJ: any[] = raw.piecesJointes ?? raw.pieces_jointes ?? raw.documents ?? [];
  const piecesJointes = Array.isArray(rawPJ) && rawPJ.length > 0
    ? rawPJ.map((p: unknown) => {
        if (typeof p === "string") return { chemin: p };
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const o = p as any;
        return { id: o.id, nom: o.nom ?? o.name, chemin: o.chemin ?? o.url ?? o.path ?? "" };
      }).filter((p) => p.chemin)
    : undefined;

  // Données embarquées dans GET /assets/{id}
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawAffect: any[] = raw.affectations ?? [];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawMaint:  any[] = raw.maintenances  ?? [];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawReeval: any[] = raw.reevaluations ?? [];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const rawDeprec: any[] = raw.depreciations ?? [];

  return {
    id: resolvedId,
    reference:         raw.reference        ?? "",
    nom:               raw.nom              ?? "",
    numeroSerie:       raw.numeroSerie       ?? raw.numero_serie       ?? undefined,
    description:       raw.description      ?? undefined,
    dateAcquisition:   raw.dateAcquisition  ?? raw.date_acquisition   ?? "",
    valeur:            raw.valeur           ?? 0,
    // Fallback : si le backend ne retourne pas sourceFinancement, on le dérive
    // du premier projet associé (déjà normalisé plus haut, gère projets[]/projet objet seul)
    sourceFinancement: raw.sourceFinancement ?? raw.source_financement ?? projects?.[0]?.nom ?? undefined,
    exercice:          raw.exercice          ?? undefined,
    modeAcquisition:   raw.modeAcquisition  ?? raw.mode_acquisition   ?? undefined,
    statut:            raw.statut           ?? undefined,
    code:              raw.code              ?? undefined,
    prixMercurial:     raw.prixMercurial     ?? raw.prix_mercurial ?? undefined,
    typeFournisseur,
    fournisseurNom,
    fournisseurEmail,
    fournisseurTelephone,
    fournisseurAdresse,
    fournisseurVille,
    fournisseurPays,
    category,
    assetType,
    assetSubType,
    etatBien,
    service,
    utilisateur,
    projects,
    champsValues,
    champsValueIds,
    photos,
    piecesJointes,
    // Embedded arrays — présents seulement dans GET /assets/{id}. Passées par
    // les mêmes normalize* que les endpoints /asset-*/{id} pour que les champs
    // snake_case/alternatifs éventuellement renvoyés par le backend soient
    // mappés vers les noms camelCase attendus par l'UI (sinon colonnes à "—").
    affectations:  rawAffect.length > 0 ? rawAffect.map(normalizeAffectation)   : undefined,
    maintenances:  rawMaint.length  > 0 ? rawMaint.map(normalizeMaintenance)   : undefined,
    reevaluations: rawReeval.length > 0 ? rawReeval.map(normalizeReevaluation) : undefined,
    depreciations: rawDeprec.length > 0 ? rawDeprec.map(normalizeDepreciation) : undefined,
    activeReevaluation: raw.activeReevaluation ?? undefined,
    activeDepreciation: raw.activeDepreciation ?? undefined,
    activeAmortissement: raw.activeAmortissement ?? undefined,
    amortissement: raw.amortissement ?? undefined,
    createdAt:  raw.createdAt  ?? undefined,
    updatedAt:  raw.updatedAt  ?? undefined,
    securise:   raw.securise   ?? false,
    coutTotalMaintenance: raw.coutTotalMaintenance ?? null,
    received: raw.received ?? null,
    serviceRestitution: raw.serviceRestitution
      ? { id: raw.serviceRestitution.id, nom: raw.serviceRestitution.nom ?? raw.serviceRestitution.name ?? "" }
      : null,
    isRestitue: raw.isRestitue ?? undefined,
    doitEtreRestitue: raw.doitEtreRestitue ?? undefined,
  };
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

/** GET /assets — Charge tous les biens paginés, normalise chaque item */
export async function listBiens(params: ListBiensParams = {}): Promise<ApiBien[]> {
  const response = await api.get<ApiResponse<PaginatedData<ApiBien> | ApiBien[]>>("/assets", {
    params: { page: 1, limit: 200, is_delete: false, ...params },
  });
  const payload = response.data.data;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw: any[] = Array.isArray(payload) ? payload : (payload as PaginatedData<ApiBien>).data ?? [];
  const biens = raw.map(normalizeBien);

  // Essayer de récupérer les projets en parallèle pour enrichir sourceFinancement
  // Le backend ne retourne pas projets[] dans la liste — on les charge séparément
  // et on les merge dans le cache. Ça ne bloque PAS l'affichage (biens already returned).
  try {
    const projResp = await api.get<{ data: { data: Array<{ id: number; nom: string }> } }>(
      "/projects",
      { params: { page: 1, limit: 200 } },
    );
    const projMap = new Map<number, string>();
    (projResp.data?.data?.data ?? []).forEach((p) => projMap.set(p.id, p.nom));
    const enriched = biens.map((b) => {
      if (b.sourceFinancement) return b;
      const projId = b.projects?.[0]?.id;
      if (projId && projMap.has(projId)) return { ...b, sourceFinancement: projMap.get(projId) };
      return b;
    });
    return enriched;
  } catch {
    // Si le fetch projets échoue, on retourne les biens sans enrichissement
    return biens;
  }
}
/**
 * GET /assets — Version paginée conservant `meta` (contrairement à listBiens()
 * qui ne renvoie que le tableau). Utilisée par BiensList pour une pagination
 * réellement côté serveur (voir ListAssetsController.php côté backend, dont
 * les paramètres supportés — search/category_id/service_id/project_id/
 * statut/securise — sont confirmés en lisant directement le contrôleur).
 */
export async function listBiensPage(
  params: ListBiensParams = {},
): Promise<{ data: ApiBien[]; meta: PaginatedMeta }> {
  const response = await api.get<ApiResponse<PaginatedData<ApiBien>>>("/assets", {
    params: { page: 1, limit: 10, is_delete: false, ...params },
  });
  const payload = response.data.data;
  const limit = params.limit ?? 10;
  return {
    data: (payload?.data ?? []).map(normalizeBien),
    meta: payload?.meta ?? { current_page: params.page ?? 1, limit, total_items: 0, total_pages: 1 },
  };
}

/** GET /assets/{id} — Charge un bien complet avec toutes ses relations */
export async function getBienById(id: number): Promise<ApiBien> {
  const response = await api.get(`/assets/${id}`);
  // Le backend peut retourner { success, data: { ...bien } } ou directement { ...bien }
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw: any = response.data;
  const bienRaw = raw?.data ?? raw;
  return normalizeBien(bienRaw);
}

/**
 * POST /assets — Crée un nouveau bien.
 * Envoie les données en multipart/form-data (pour supporter les fichiers).
 * Après création, BiensPage appelle aussi getBienById() pour récupérer
 * les relations complètes si le backend ne les retourne pas dans le 201.
 */
export async function createBien(payload: CreateBienPayload): Promise<ApiBien> {
  const fd = buildFormData(payload, false);
  const response = await api.post(`/assets`, fd, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw: any = response.data;
  const bienRaw = raw?.data ?? raw;
  return normalizeBien(bienRaw);
}

/**
 * POST /assets/{id} — Modifie un bien existant.
 * Utilise POST (pas PUT) car multipart/form-data ne supporte pas bien PATCH/PUT.
 */
export async function updateBien(id: number, payload: UpdateBienPayload): Promise<ApiBien> {
  const fd = buildFormData(payload as CreateBienPayload, true);
  const response = await api.post(`/assets/${id}`, fd, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw: any = response.data;
  const bienRaw = raw?.data ?? raw;
  return normalizeBien(bienRaw);
}

/**
 * Mise à jour de l'état d'un bien via POST /assets/{id} (FormData).
 * Le backend n'accepte pas PATCH ni PUT — il utilise POST avec multipart/form-data.
 */
export async function patchBienEtat(id: number, etat_bien_id: number): Promise<void> {
  const fd = new FormData();
  fd.append("etat_bien_id", String(etat_bien_id));
  await api.post(`/assets/${id}`, fd, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

/**
 * Réforme d'un bien via POST /assets/{id} (FormData) — direct sur le bien,
 * sans passer par POST /asset-exits. Volontairement construit à la main
 * (pas via buildFormData/updateBien) pour n'envoyer QUE ces champs : une
 * vraie mise à jour partielle qui ne touche à rien d'autre sur le bien.
 */
export async function patchBienReforme(id: number, payload: {
  etat_bien_id: number;
  statut: string;
  description?: string;
  piecesJointesFiles?: File[];
  piecesJointesNoms?: string[];
}): Promise<void> {
  const fd = new FormData();
  fd.append("etat_bien_id", String(payload.etat_bien_id));
  fd.append("statut", payload.statut);
  if (payload.description) fd.append("description", payload.description);
  (payload.piecesJointesFiles ?? []).forEach((file) => fd.append("piecesJointes[]", file));
  (payload.piecesJointesNoms ?? []).forEach((nom) => fd.append("piecesJointesNoms[]", nom));
  await api.post(`/assets/${id}`, fd, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

export async function deleteBien(id: number): Promise<null> {
  const response = await api.delete<ApiResponse<null>>(`/assets/${id}`);
  return response.data.data;
}

/**
 * DELETE /assets/{id}/soft-delete — Supprime un bien de façon réversible.
 * Le bien est marqué is_delete=true côté backend mais reste en base.
 * Il n'apparaîtra plus dans la liste sauf si include_deleted=true.
 */
export async function softDeleteBien(id: number): Promise<null> {
  const response = await api.delete<ApiResponse<null>>(`/assets/${id}/soft-delete`);
  return response.data.data;
}

/**
 * POST /assets/{id}/restore — Restaure un bien soft-supprimé.
 * Remet is_delete=false, le bien réapparaît dans la liste.
 */
export async function restoreBien(id: number): Promise<ApiBien> {
  const response = await api.post<ApiResponse<ApiBien>>(`/assets/${id}/restore`, {});
  return normalizeBien(response.data.data);
}

/**
 * POST /assets/{id}/restituer — Restitue un bien : crée une affectation de
 * type RESTITUTION. Confirmé en lisant AssetAssignmentService::restituerAsset
 * directement (pas juste le Swagger, 2026-09-01) : SEUL service_id (optionnel
 * — si absent, utilise le serviceRestitution déjà défini sur le bien),
 * dateDebut, dateFin et commentaire sont acceptés. user_id n'est PAS/PLUS
 * accepté par cet endpoint (remplacé par service_id).
 */
export interface RestituerBienPayload {
  service_id?: number;
  dateDebut?: string;
  dateFin?: string;
  commentaire?: string;
}
export async function restituerBien(
  id: number,
  payload: RestituerBienPayload,
): Promise<ApiResponse<{ id: number; reference: string; nom: string }>> {
  const response = await api.post<ApiResponse<{ id: number; reference: string; nom: string }>>(
    `/assets/${id}/restituer`,
    payload,
  );
  return response.data;
}

/**
 * DELETE /asset-documents/{id} — Supprime une pièce jointe d'un bien.
 * Si la route n'existe pas, retourne une erreur que le composant gère en affichage local.
 */
export async function deletePieceJointe(pieceJointeId: number): Promise<void> {
  await api.delete(`/asset-documents/${pieceJointeId}`);
}

/**
 * PUT /asset-documents/{id} — Renomme une pièce jointe.
 */
export async function renamePieceJointe(pieceJointeId: number, nom: string): Promise<void> {
  await api.put(`/asset-documents/${pieceJointeId}`, { nom });
}

export interface ApiAssetMercuriale {
  id: number;
  nom: string;
  lien: string;
}

/**
 * GET /assets/{id}/mercurriale (typo backend conservée telle quelle) —
 * Génère un lien de recherche mercuriale.cm à partir du nom du bien.
 * 400 si le bien n'a pas de nom, 404 si le bien n'existe pas.
 */
export async function getAssetMercuriale(id: number): Promise<ApiAssetMercuriale> {
  const response = await api.get<ApiResponse<ApiAssetMercuriale>>(`/assets/${id}/mercurriale`);
  return response.data.data;
}

// ─── Amortissement ───────────────────────────────────────────────────────────

export interface ApiAmortissementDetail {
  id: number;
  reference: string;
  nom: string;
  valeur: number;
  valeurInitiale: number;
  dateAcquisition: string;
  typeBien?: { id: number; nom: string };
  amortissement: {
    valeur: number;
    valeurActuelle: number;
    amortissementAnnuel: number;
    amortissementCumule: number;
    anneesEcoulees: number;
    dateAcquisition: string;
    dureeVieRestante: number;
    moisEcoules: number;
    typeCalcul: string;
  };
  statistiques: {
    pourcentageAmorti: number;
    pourcentageRestant: number;
  };
}

/** GET /assets/{id}/amortissement — détail de l'amortissement d'un bien. */
export async function getAssetAmortissement(id: number): Promise<ApiResponse<ApiAmortissementDetail>> {
  const response = await api.get<ApiResponse<ApiAmortissementDetail>>(`/assets/${id}/amortissement`);
  return response.data;
}

export interface ApiAmortissementTableRow {
  bien: { id: number; reference: string; designation: string };
  categorie?: { id: number; nom: string } | null;
  valeur_acquisition: number;
  date_acquisition: string;
  duree_vie: number;
  taux_amortissement: number;
  amortissement_annuel: number;
  amortissement_cumule: number;
  vnc: number;
  annees_ecoulees: number;
  duree_vie_restante: number;
  type_calcul: string;
}

export interface ListAmortissementsTableParams {
  page?: number;
  limit?: number;
  category?: number;
  exercice?: number;
  date_debut?: string;
  date_fin?: string;
}

/**
 * GET /assets/amortissements/tableau — tableau paginé d'amortissement de
 * tous les biens actifs (biens sans données suffisantes exclus par le backend).
 */
export async function listAmortissementsTable(
  params: ListAmortissementsTableParams = {},
): Promise<ApiResponse<PaginatedData<ApiAmortissementTableRow>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiAmortissementTableRow>>>(
    "/assets/amortissements/tableau",
    { params },
  );
  return response.data;
}

/** Un bien apparaissant dans une maintenance en cours — infos de seuil héritées de sa catégorie. */
export interface ApiMaintenanceEnCoursBien {
  id: number;
  reference: string;
  designation: string;
  /** Nombre total de maintenances (toutes, pas seulement en cours) pour ce bien. */
  nombre_maintenances_total: number;
  /** Seuil hérité de la catégorie du bien. */
  seuil: number | null;
  /** true si le coût de maintenance de ce bien dépasse sa valeur — calculé côté backend. */
  seuil_depasse: boolean;
  reste_avant_depassement?: number | null;
}

export interface ApiMaintenanceEnCoursRow {
  bien: ApiMaintenanceEnCoursBien;
  typeBien?: { id: number; nom: string } | null;
  maintenance: {
    id: number;
    etatBien?: { id: number; nom: string } | null;
    motif: string;
    cout: number | string;
    dateIntervention: string;
    dateRecuperation?: string | null;
    observations?: string | null;
    statut: string;
  };
}

/** Un groupe "catégorie" tel que renvoyé par GET /assets/maintenances/en-cours. */
export interface ApiMaintenanceEnCoursCategorie {
  categorie: { id: number; nom: string; seuil: number | null };
  nombre_maintenances: number;
  cout_total: number;
  maintenances: ApiMaintenanceEnCoursRow[];
}

export interface ListMaintenancesEnCoursParams {
  page?: number;
  limit?: number;
  category_id?: number;
  asset_type_id?: number;
  exercice?: number;
  service_id?: number;
}

/**
 * GET /assets/maintenances/en-cours — biens ayant une maintenance ouverte
 * (AssetMaintenance sans date de récupération), regroupés par catégorie avec
 * coût total, nombre de maintenances et seuil hérité de la catégorie.
 */
export async function listMaintenancesEnCours(
  params: ListMaintenancesEnCoursParams = {},
): Promise<ApiResponse<PaginatedData<ApiMaintenanceEnCoursCategorie>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiMaintenanceEnCoursCategorie>>>(
    "/assets/maintenances/en-cours",
    { params },
  );
  return response.data;
}

/** POST /assets/{id}/reevaluation — active/désactive la réévaluation d'un bien. */
export async function setAssetReevaluationActive(
  id: number,
  active: boolean,
): Promise<ApiResponse<{ id: number; reference: string; nom: string; activeReevaluation: boolean }>> {
  const response = await api.post<ApiResponse<{ id: number; reference: string; nom: string; activeReevaluation: boolean }>>(
    `/assets/${id}/reevaluation`,
    { active },
  );
  return response.data;
}

/** POST /assets/{id}/depreciation — active/désactive la dépréciation d'un bien. */
export async function setAssetDepreciationActive(
  id: number,
  active: boolean,
): Promise<ApiResponse<{ id: number; reference: string; nom: string; activeDepreciation: boolean }>> {
  const response = await api.post<ApiResponse<{ id: number; reference: string; nom: string; activeDepreciation: boolean }>>(
    `/assets/${id}/depreciation`,
    { active },
  );
  return response.data;
}

/** POST /assets/{id}/amortissement — active/désactive l'amortissement d'un bien. */
export async function setAssetAmortissementActive(
  id: number,
  active: boolean,
): Promise<ApiResponse<{ id: number; reference: string; nom: string; activeAmortissement: boolean }>> {
  const response = await api.post<ApiResponse<{ id: number; reference: string; nom: string; activeAmortissement: boolean }>>(
    `/assets/${id}/amortissement`,
    { active },
  );
  return response.data;
}
