/**
 * ============================================================
 * asset-events.api.ts — Événements liés à un bien patrimonial
 * ============================================================
 *
 * 4 types d'événements enregistrables sur un bien :
 *
 *  1. AFFECTATIONS  : affecter le bien à une structure/service
 *     → GET /assets/{id}/affectations
 *     → POST /assets/{id}/affectations
 *     Réponse réelle : { typeAffectation, dateDebut, dateFin, commentaire,
 *                        service: { id, nom }, utilisateur, piecesJointes[] }
 *
 *  2. MAINTENANCES  : enregistrer une intervention technique
 *     → GET /assets/{id}/maintenances
 *     → POST /assets/{id}/maintenances
 *     Réponse réelle : { etatBien: {id, nom}, motif, cout, dateIntervention,
 *                        dateRecuperation, observations, piecesJointes[] }
 *
 *  3. RÉÉVALUATIONS : mettre à jour la valeur du bien
 *     → GET /assets/{id}/reevaluations
 *     → POST /assets/{id}/reevaluations
 *     Réponse réelle : { valeurActuelle, nouvelleValeur, methodeEvaluation,
 *                        dateReevaluation, motif, observations, piecesJointes[] }
 *
 *  4. DÉPRÉCIATIONS : amortissement comptable du bien
 *     → GET /assets/{id}/depreciations
 *     → POST /assets/{id}/depreciations
 *     Réponse réelle : { typeDepreciation, methodeAmortissement, dureeVie,
 *                        valeurActuelle, tauxDepreciation, montantDepreciation,
 *                        dateDepreciation, motif, observations, piecesJointes[] }
 *
 * PIÈCES JOINTES des événements :
 *   Chaque événement peut avoir des pièces jointes.
 *   Elles sont retournées dans le champ piecesJointes[] de la réponse.
 *   Format : { id, nom, chemin }
 */


import api from "../axios";

// Types referenced inline via `import("../types").ApiResponse` to avoid static imports
async function getApi() {
  return api;
}
// ─── Type commun pour les pièces jointes des événements ─────────────────────

export interface ApiPieceJointe {
  id: number;
  nom: string;
  chemin: string;
}

// ─── Affectation ─────────────────────────────────────────────────────────────
// Représente l'affectation d'un bien à une structure/service.

export interface ApiAffectation {
  id: number;
  typeAffectation?: string;
  dateDebut: string;
  dateFin?: string | null;
  commentaire?: string;
  service?: { id: number; nom: string };
  localisation?: {
    region?: { id: number; nom: string };
    departement?: { id: number; nom: string };
    arrondissement?: { id: number; nom: string };
  };
  /**
   * `utilisateur.service` (service/poste de rattachement de l'utilisateur
   * affecté) ajouté côté backend le 2026-09-01 — nécessaire pour la colonne
   * "Destination" du tableau des affectations quand le bien est affecté à
   * un individu plutôt qu'à un service (voir Biens.tsx).
   */
  utilisateur?: { id: number; firstName: string; lastName: string; service?: { id: number; nom: string } | null };
  piecesJointes?: ApiPieceJointe[];
  createdAt?: string;
  /** Auteur de l'affectation (qui l'a créée) — ajouté côté backend le 2026-08-31. */
  createdBy?: { id: number; firstName: string; lastName: string } | null;
  /** Confirmé en direct (2026-08-27) sur GET /assets/{id}/assignments — absent du Swagger fourni initialement. */
  accuseReception?: { effectue: boolean; date: string | null; par: unknown | null; commentaire: string | null } | null;
}

// Payload envoyé au backend — champs exacts confirmés par le Swagger
// Route : POST /asset-assignments ("Tous les champs sont facultatifs")
// user_id est "prioritaire sur service_id" d'après la description du champ —
// affecter à une structure OU à un individu est donc bien deux choix distincts
// côté backend (recommandation 50 : l'UI ne doit permettre que l'un des deux).
export interface CreateAffectationPayload {
  asset_id: number;           // ID du bien (integer)
  service_id?: number;        // ID du service
  user_id?: number;           // ID de l'utilisateur (prioritaire sur service_id)
  typeAffectation?: string;   // ex: "AFFECTATION" (string, optionnel — défaut: "AFFECTATION")
  dateDebut: string;          // format YYYY-MM-DD (requis)
  dateFin?: string;           // format YYYY-MM-DD (optionnel)
  commentaire?: string;       // (optionnel)
}

// ─── Maintenance ──────────────────────────────────────────────────────────────
// Représente une intervention technique sur le bien.

export interface ApiMaintenance {
  id: number;
  // etatBien est un objet { id, nom } côté backend
  etatBien?: { id: number; nom: string };
  etat?: string;                       // fallback si le backend envoie une string directe
  motif?: string;
  cout?: number;
  dateIntervention: string;
  dateRecuperation?: string | null;
  observations?: string;
  piecesJointes?: ApiPieceJointe[];
  createdAt?: string;
}

// Payload envoyé au backend pour créer une maintenance
// Route : POST /asset-maintenances
// asset_ids[] : tableau d'IDs de biens (le bien courant est toujours le seul élément)
export interface CreateMaintenancePayload {
  asset_ids: number[];                 // tableau d'IDs des biens concernés
  etat_bien_id?: number;               // ID de l'état du bien après maintenance
  motif: string;
  cout: number;
  dateIntervention: string;
  dateRecuperation?: string;
  observations?: string;
}

// ─── Réévaluation ────────────────────────────────────────────────────────────
// Représente une mise à jour de la valeur du bien.

export interface ApiReevaluation {
  id: number;
  valeurActuelle?: number;             // valeur avant la réévaluation
  nouvelleValeur: number;              // valeur après
  methodeEvaluation?: string;          // ex: "Expertise", "Indice"
  service?: { id: number; nom: string };
  dateReevaluation: string;
  motif: string;
  observations?: string | null;
  piecesJointes?: ApiPieceJointe[];
  createdAt?: string;
}

// Payload envoyé au backend pour créer une réévaluation
export interface CreateReevaluationPayload {
  nouvelleValeur: number;
  dateReevaluation: string;
  motif: string;
  methodeEvaluation?: string;
  observations?: string;
  service_id?: number;
  /** Au moins une pièce jointe est obligatoire côté formulaire (pas côté
   * API, où tous les champs sont facultatifs — voir POST /asset-reevaluations). */
  piecesJointes?: File[];
  /** Un nom par fichier, même index — voir piecesJointes. */
  piecesJointesNoms?: string[];
}

// ─── Dépréciation ────────────────────────────────────────────────────────────
// Représente l'amortissement comptable du bien.

export interface ApiDepreciation {
  id: number;
  typeDepreciation?: string;           // ex: "Usure", "Obsolescence"
  methodeAmortissement?: string;       // ex: "Linéaire", "Dégressif"
  dureeVie?: number;                   // en années
  valeurActuelle?: number;
  tauxDepreciation?: number;           // en %
  montantDepreciation?: number;        // en FCFA
  dateDepreciation: string;
  motif: string;
  observations?: string | null;
  piecesJointes?: ApiPieceJointe[];
  createdAt?: string;
}

// Payload envoyé au backend pour créer une dépréciation
export interface CreateDepreciationPayload {
  typeDepreciation?: string;
  methodeAmortissement?: string;
  dureeVie?: number;
  tauxDepreciation?: number;
  montantDepreciation?: number;
  dateDepreciation: string;
  motif: string;
  observations?: string;
}

// ─── Helper : extraction de liste ────────────────────────────────────────────
// Le backend peut retourner un tableau direct OU une réponse paginée.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function extractList<T>(data: any): T[] {
  if (Array.isArray(data)) return data as T[];
  if (data && Array.isArray(data.data)) return data.data as T[];
  return [];
}

// ─── Normaliseurs ────────────────────────────────────────────────────────────
// Chaque normalise* garantit que les champs ont les bons noms,
// peu importe la convention retournée par le backend (camelCase / snake_case).

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function normalizeAffectation(raw: any): ApiAffectation {
  return {
    ...raw,
    // dateDebut : "dateDebut" ou "date_debut" ou "date_affectation"
    dateDebut: raw.dateDebut ?? raw.date_debut ?? raw.date_affectation ?? "",
    // "" doit valoir "pas de date de fin" au même titre que null/undefined —
    // le backend renvoie parfois une chaîne vide plutôt qu'un null explicite,
    // ce qui cassait le filtre "affectation active" (dateFin == null) côté UI.
    dateFin:   raw.dateFin || raw.date_fin || null,
    // commentaire : singulier (backend) — on accepte aussi le pluriel
    commentaire: raw.commentaire ?? raw.commentaires ?? raw.observation ?? undefined,
    service: raw.service
      ? { id: raw.service.id, nom: raw.service.nom ?? raw.service.name ?? "" }
      : undefined,
    // GET /assets/{id}/assignments renvoie la personne sous forme "nom"/"prenom"
    // (pas "firstName"/"lastName" comme documenté ailleurs) — on absorbe les
    // deux conventions ici plutôt que de le refaire à chaque endroit d'affichage.
    utilisateur: raw.utilisateur
      ? {
          id: raw.utilisateur.id,
          firstName: raw.utilisateur.firstName ?? raw.utilisateur.prenom ?? "",
          lastName: raw.utilisateur.lastName ?? raw.utilisateur.nom ?? "",
          service: raw.utilisateur.service
            ? { id: raw.utilisateur.service.id, nom: raw.utilisateur.service.nom ?? raw.utilisateur.service.name ?? "" }
            : null,
        }
      : undefined,
    createdBy: raw.createdBy
      ? {
          id: raw.createdBy.id,
          firstName: raw.createdBy.firstName ?? raw.createdBy.prenom ?? "",
          lastName: raw.createdBy.lastName ?? raw.createdBy.nom ?? "",
        }
      : null,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function normalizeMaintenance(raw: any): ApiMaintenance {
  return {
    ...raw,
    // etatBien peut être un objet { id, nom } ou une string directe
    etatBien: raw.etatBien
      ? { id: raw.etatBien.id, nom: raw.etatBien.nom ?? raw.etatBien.name ?? "" }
      : undefined,
    etat: raw.etat ?? (raw.etatBien?.nom) ?? raw.statut ?? undefined,
    motif:            raw.motif            ?? raw.description       ?? undefined,
    cout:             raw.cout             ?? raw.cost              ?? raw.montant ?? undefined,
    dateIntervention: raw.dateIntervention ?? raw.date_intervention ?? raw.date   ?? "",
    dateRecuperation: raw.dateRecuperation ?? raw.date_recuperation ?? null,
    observations:     raw.observations     ?? raw.observation        ?? undefined,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function normalizeReevaluation(raw: any): ApiReevaluation {
  return {
    ...raw,
    valeurActuelle:   raw.valeurActuelle   ?? raw.valeur_actuelle   ?? undefined,
    nouvelleValeur:   raw.nouvelleValeur   ?? raw.nouvelle_valeur   ?? raw.valeur ?? 0,
    methodeEvaluation: raw.methodeEvaluation ?? raw.methode_evaluation ?? undefined,
    dateReevaluation: raw.dateReevaluation ?? raw.date_reevaluation ?? raw.date   ?? "",
    motif:            raw.motif            ?? raw.description        ?? "",
    observations:     raw.observations     ?? raw.observation        ?? null,
    service: raw.service
      ? { id: raw.service.id, nom: raw.service.nom ?? raw.service.name ?? "" }
      : undefined,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function normalizeDepreciation(raw: any): ApiDepreciation {
  return {
    ...raw,
    typeDepreciation:    raw.typeDepreciation    ?? raw.type_depreciation    ?? undefined,
    methodeAmortissement: raw.methodeAmortissement ?? raw.methode_amortissement ?? undefined,
    dureeVie:            raw.dureeVie            ?? raw.duree_vie            ?? undefined,
    valeurActuelle:      raw.valeurActuelle       ?? raw.valeur_actuelle      ?? undefined,
    tauxDepreciation:    raw.tauxDepreciation     ?? raw.taux_depreciation    ?? undefined,
    montantDepreciation: raw.montantDepreciation  ?? raw.montant_depreciation ?? undefined,
    dateDepreciation:    raw.dateDepreciation     ?? raw.date_depreciation    ?? raw.date ?? "",
    motif:               raw.motif               ?? raw.description           ?? "",
    observations:        raw.observations        ?? raw.observation           ?? null,
  };
}

// ─── Affectations ────────────────────────────────────────────────────────────
// Route POST : POST /asset-assignments (avec asset_id dans le body)
// Route GET  : GET /assets/{id}/assignments

/**
 * GET /assets/{id}/assignments — Liste les affectations d'un bien.
 *
 * Réponse : { success, status, message, data: [...] }
 */
export async function listAffectations(assetId: number): Promise<ApiAffectation[]> {
  try {
    const api = await getApi();
    const res = await api.get<import("../types").ApiResponse<ApiAffectation[]>>(`/assets/${assetId}/assignments`);
    const list = extractList<ApiAffectation>(res.data.data ?? res.data);
    return list.map(normalizeAffectation);
  } catch (err) {
    console.error("[listAffectations] erreur:", err);
    return [];
  }
}

/**
 * POST /asset-assignments — Enregistre une nouvelle affectation en base.
 *
 * Payload envoyé :
 *   { asset_id, service_id, typeAffectation?, dateDebut, dateFin?, commentaire? }
 *
 * Réponse 201 :
 *   { id, typeAffectation, dateDebut, dateFin, commentaire,
 *     service: { id, nom }, localisation, utilisateur, piecesJointes[], createdAt }
 */
export async function createAffectation(
  assetId: number,
  payload: Omit<CreateAffectationPayload, "asset_id">
): Promise<ApiAffectation> {
  const api = await getApi();
  const resolvedAssetId = Number(assetId);
  if (!resolvedAssetId || isNaN(resolvedAssetId)) {
    throw new Error(`createAffectation: asset_id invalide (reçu: ${assetId})`);
  }

  // Le backend Symfony lit les champs via un FormType — il faut envoyer
  // multipart/form-data (comme pour /assets), pas du JSON.
  const fd = new FormData();
  fd.append("asset_id",        String(resolvedAssetId));
  fd.append("typeAffectation", payload.typeAffectation ?? "AFFECTATION");
  fd.append("dateDebut",       payload.dateDebut);
  if (payload.service_id != null) fd.append("service_id", String(payload.service_id));
  if (payload.user_id != null)    fd.append("user_id",    String(payload.user_id));
  if (payload.dateFin)        fd.append("dateFin",         payload.dateFin);
  if (payload.commentaire)    fd.append("commentaire",     payload.commentaire);

  const res = await api.post<import("../types").ApiResponse<ApiAffectation>>(`/asset-assignments`, fd, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return normalizeAffectation(res.data.data);
}

// ─── Maintenances ────────────────────────────────────────────────────────────
// Pas de route GET /asset-maintenances?asset_id=X — la liste des maintenances
// d'un bien s'obtient via le champ "maintenances" embarqué dans GET /assets/{id}
// (voir ApiBien.maintenances dans biens.api.ts), pas via cette API.

/** GET /asset-maintenances/{id} — Charge une maintenance par son ID */
export async function getMaintenanceById(id: number): Promise<ApiMaintenance> {
  const api = await getApi();
  const res = await api.get<import("../types").ApiResponse<ApiMaintenance>>(`/asset-maintenances/${id}`);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeMaintenance(raw);
}

/** POST /asset-maintenances — Enregistre une nouvelle maintenance en base */
export async function createMaintenance(
  assetId: number,
  payload: Omit<CreateMaintenancePayload, "asset_ids">
): Promise<ApiMaintenance> {
    const api = await getApi();
  // Symfony FormType — envoyer multipart/form-data
  const fd = new FormData();
  fd.append("asset_ids[]",      String(Number(assetId)));
  if (payload.etat_bien_id)     fd.append("etat_bien_id",     String(payload.etat_bien_id));
  fd.append("motif",             payload.motif);
  // cout doit être envoyé comme decimal string ex: "100000.00"
  // parseFloat + toFixed(2) évite l'overflow MySQL DECIMAL
  fd.append("cout",              parseFloat(String(payload.cout)).toFixed(2));
  fd.append("dateIntervention",  payload.dateIntervention);
  if (payload.dateRecuperation)  fd.append("dateRecuperation", payload.dateRecuperation);
  if (payload.observations)      fd.append("observations",     payload.observations);

  const res = await api.post<import("../types").ApiResponse<ApiMaintenance>>(
    `/asset-maintenances`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeMaintenance(raw);
}

// ─── Réévaluations ───────────────────────────────────────────────────────────
// Pas de route GET /asset-reevaluations?asset_id=X — la liste des réévaluations
// d'un bien s'obtient via le champ "reevaluations" embarqué dans GET /assets/{id}.

/** GET /asset-reevaluations/{id} — Charge une réévaluation par son ID */
export async function getReevaluationById(id: number): Promise<ApiReevaluation> {
  const api = await getApi();
  const res = await api.get<import("../types").ApiResponse<ApiReevaluation>>(`/asset-reevaluations/${id}`);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeReevaluation(raw);
}

/**
 * POST /asset-reevaluations — Enregistre une nouvelle réévaluation en base.
 *
 * Champs Swagger :
 *   asset_ids[], valeurActuelle, nouvelleValeur, methodeEvaluation,
 *   service_id, dateReevaluation, motif, observations, piecesJointes[]
 */
export async function createReevaluation(
  assetId: number,
  payload: CreateReevaluationPayload
): Promise<ApiReevaluation> {
    const api = await getApi();
  const fd = new FormData();
  fd.append("asset_ids[]",       String(Number(assetId)));
  fd.append("nouvelleValeur",    parseFloat(String(payload.nouvelleValeur)).toFixed(2));
  fd.append("dateReevaluation",  payload.dateReevaluation);
  fd.append("motif",             payload.motif);
  if (payload.methodeEvaluation) fd.append("methodeEvaluation", payload.methodeEvaluation);
  if (payload.service_id)        fd.append("service_id",        String(payload.service_id));
  if (payload.observations)      fd.append("observations",      payload.observations);
  (payload.piecesJointes ?? []).forEach((file) => fd.append("piecesJointes[]", file));
  (payload.piecesJointesNoms ?? []).forEach((nom) => fd.append("piecesJointesNoms[]", nom));

  const res = await api.post<import("../types").ApiResponse<ApiReevaluation>>(
    `/asset-reevaluations`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeReevaluation(raw);
}

// ─── Dépréciations ───────────────────────────────────────────────────────────
// Pas de route GET /asset-depreciations?asset_id=X — la liste des dépréciations
// d'un bien s'obtient via le champ "depreciations" embarqué dans GET /assets/{id}.

/** GET /asset-depreciations/{id} — Charge une dépréciation par son ID */
export async function getDepreciationById(id: number): Promise<ApiDepreciation> {
    const api = await getApi();
  const res = await api.get<import("../types").ApiResponse<ApiDepreciation>>(`/asset-depreciations/${id}`);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeDepreciation(raw);
}

/**
 * POST /asset-depreciations — Enregistre une nouvelle dépréciation en base.
 *
 * Champs Swagger :
 *   asset_ids[], typeDepreciation, methodeAmortissement, dureeVie,
 *   valeurActuelle, tauxDepreciation, montantDepreciation,
 *   dateDepreciation, motif, observations
 */
export async function createDepreciation(
  assetId: number,
  payload: CreateDepreciationPayload
): Promise<ApiDepreciation> {
  const api = await getApi();
  const fd = new FormData();
  fd.append("asset_ids[]",        String(Number(assetId)));
  if (payload.typeDepreciation)    fd.append("typeDepreciation",    payload.typeDepreciation);
  if (payload.methodeAmortissement) fd.append("methodeAmortissement", payload.methodeAmortissement);
  if (payload.dureeVie != null)    fd.append("dureeVie",            String(payload.dureeVie));
  if (payload.tauxDepreciation != null)
    fd.append("tauxDepreciation",  parseFloat(String(payload.tauxDepreciation)).toFixed(2));
  if (payload.montantDepreciation != null)
    fd.append("montantDepreciation", parseFloat(String(payload.montantDepreciation)).toFixed(2));
  fd.append("dateDepreciation",    payload.dateDepreciation);
  fd.append("motif",               payload.motif);
  if (payload.observations)        fd.append("observations",        payload.observations);

  const res = await api.post<import("../types").ApiResponse<ApiDepreciation>>(
    `/asset-depreciations`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeDepreciation(raw);
}

// ─── Fonctions UPDATE + SOFT-DELETE ──────────────────────────────────────────
// Même pattern que create : FormData multipart/form-data pour tous les endpoints.

// ── Affectation ───────────────────────────────────────────────────────────────

/** PUT /asset-assignments/{id} — Met à jour une affectation (FormData) */
export async function updateAffectation(
  id: number,
  payload: Omit<CreateAffectationPayload, "asset_id">
): Promise<ApiAffectation> {
    const api = await getApi();
  const fd = new FormData();
  fd.append("typeAffectation", payload.typeAffectation ?? "AFFECTATION");
  fd.append("dateDebut",       payload.dateDebut);
  if (payload.service_id != null) fd.append("service_id", String(payload.service_id));
  if (payload.user_id != null)    fd.append("user_id",    String(payload.user_id));
  if (payload.dateFin)        fd.append("dateFin",         payload.dateFin);
  if (payload.commentaire)    fd.append("commentaire",     payload.commentaire);

  const res = await api.put<import("../types").ApiResponse<ApiAffectation>>(
    `/asset-assignments/${id}`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeAffectation(raw);
}

/** DELETE /asset-assignments/{id} — Soft-delete une affectation */
export async function deleteAffectation(id: number): Promise<void> {
    const api = await getApi();
  await api.delete(`/asset-assignments/${id}`);
}

/**
 * POST /asset-assignments/acknowledge-batch — accuse réception d'une
 * affectation. Réservé au destinataire (ou à un administrateur), une seule
 * fois par affectation. Il n'existe PAS de route unitaire
 * POST /asset-assignments/{id}/acknowledge côté backend (confirmé en lisant
 * AcknowledgeAssetAssignmentController.php — seule /acknowledge-batch est
 * déclarée, d'où le 404 "No route found" avant ce correctif) : on réutilise
 * donc la route batch avec un seul id.
 */
export async function acknowledgeAffectation(id: number): Promise<void> {
  const api = await getApi();
  await api.post(`/asset-assignments/acknowledge-batch`, { ids: String(id) });
}

// ── Maintenance ───────────────────────────────────────────────────────────────

/** PUT /asset-maintenances/{id} — Met à jour une maintenance (FormData) */
export async function updateMaintenance(
  id: number,
  payload: Omit<CreateMaintenancePayload, "asset_ids">
): Promise<ApiMaintenance> {
    const api = await getApi();
  const fd = new FormData();
  if (payload.etat_bien_id)    fd.append("etat_bien_id",    String(payload.etat_bien_id));
  fd.append("motif",            payload.motif);
  fd.append("cout",             parseFloat(String(payload.cout)).toFixed(2));
  fd.append("dateIntervention", payload.dateIntervention);
  if (payload.dateRecuperation) fd.append("dateRecuperation", payload.dateRecuperation);
  if (payload.observations)     fd.append("observations",     payload.observations);

  const res = await api.put<import("../types").ApiResponse<ApiMaintenance>>(
    `/asset-maintenances/${id}`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeMaintenance(raw);
}

/** DELETE /asset-maintenances/{id} — Soft-delete une maintenance */
export async function deleteMaintenance(id: number): Promise<void> {
  const api = await getApi();
  await api.delete(`/asset-maintenances/${id}`);
}

// ── Réévaluation ──────────────────────────────────────────────────────────────

/** PUT /asset-reevaluations/{id} — Met à jour une réévaluation (FormData) */
export async function updateReevaluation(
  id: number,
  payload: CreateReevaluationPayload
): Promise<ApiReevaluation> {
    const api = await getApi();
  const fd = new FormData();
  fd.append("nouvelleValeur",   parseFloat(String(payload.nouvelleValeur)).toFixed(2));
  fd.append("dateReevaluation", payload.dateReevaluation);
  fd.append("motif",            payload.motif);
  if (payload.methodeEvaluation) fd.append("methodeEvaluation", payload.methodeEvaluation);
  if (payload.service_id)        fd.append("service_id",        String(payload.service_id));
  if (payload.observations)      fd.append("observations",      payload.observations);
  (payload.piecesJointes ?? []).forEach((file) => fd.append("piecesJointes[]", file));
  (payload.piecesJointesNoms ?? []).forEach((nom) => fd.append("piecesJointesNoms[]", nom));

  const res = await api.put<import("../types").ApiResponse<ApiReevaluation>>(
    `/asset-reevaluations/${id}`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeReevaluation(raw);
}

/** DELETE /asset-reevaluations/{id} — Soft-delete une réévaluation */
export async function deleteReevaluation(id: number): Promise<void> {
  const api = await getApi();
  await api.delete(`/asset-reevaluations/${id}`);
}

// ── Dépréciation ──────────────────────────────────────────────────────────────

/** PUT /asset-depreciations/{id} — Met à jour une dépréciation (FormData) */
export async function updateDepreciation(
  id: number,
  payload: CreateDepreciationPayload
): Promise<ApiDepreciation> {
    const api = await getApi();
  const fd = new FormData();
  if (payload.typeDepreciation)     fd.append("typeDepreciation",     payload.typeDepreciation);
  if (payload.methodeAmortissement) fd.append("methodeAmortissement", payload.methodeAmortissement);
  if (payload.dureeVie != null)     fd.append("dureeVie",             String(payload.dureeVie));
  if (payload.tauxDepreciation != null)
    fd.append("tauxDepreciation",   parseFloat(String(payload.tauxDepreciation)).toFixed(2));
  if (payload.montantDepreciation != null)
    fd.append("montantDepreciation", parseFloat(String(payload.montantDepreciation)).toFixed(2));
  fd.append("dateDepreciation", payload.dateDepreciation);
  fd.append("motif",            payload.motif);
  if (payload.observations)     fd.append("observations", payload.observations);

  const res = await api.put<import("../types").ApiResponse<ApiDepreciation>>(
    `/asset-depreciations/${id}`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const raw = (res.data as any)?.data ?? res.data;
  return normalizeDepreciation(raw);
}

/** DELETE /asset-depreciations/{id} — Soft-delete une dépréciation */
export async function deleteDepreciation(id: number): Promise<void> {
  const api = await getApi();
  await api.delete(`/asset-depreciations/${id}`);
}

// ─── Historique des détenteurs ──────────────────────────────────────────────
// Vue en lecture seule : qui a détenu le bien au fil du temps (personne ou
// service), dérivée des affectations côté backend. Complète (sans remplacer)
// la section Affectation, qui elle reste éditable.

export interface ApiHolderHistoryEntry {
  id: number;
  typeAffectation?: string;
  dateDebut: string;
  /** null = détenteur actuel */
  dateFin?: string | null;
  commentaire?: string;
  detenteur:
    | { type: "user"; id: number; nom: string; prenom: string; matricule?: string; email?: string }
    | { type: "service"; id: number; nom: string; sigle?: string }
    | null;
  createdAt?: string;
}

/** GET /assets/{id}/holders-history — historique complet, trié du plus récent au plus ancien. */
export async function getHoldersHistory(assetId: number): Promise<ApiHolderHistoryEntry[]> {
  const api = await getApi();
  const res = await api.get<import("../types").ApiResponse<ApiHolderHistoryEntry[]>>(
    `/assets/${assetId}/holders-history`,
  );
  return res.data.data ?? [];
}
