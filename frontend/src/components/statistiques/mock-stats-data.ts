import {
  Truck,
  Mountain,
  Home,
  Monitor,
  Armchair,
  CheckCircle2,
  AlertTriangle,
  HelpCircle,
  Gavel,
  ShieldCheck,
  Building,
  FileCheck,
  FileX,
  Clock,
  Layers,
  MapPin,
  Flame,
} from "lucide-react";

// ─── Couleurs thématiques MINEPIA ──────────────────────────────────────────
export const MINEPIA_GREEN = "#006837";
export const MINEPIA_GREEN_LIGHT = "#2d8a4e";
export const STAT_BLUE = "#2563eb";
export const STAT_ORANGE = "#ea580c";
export const STAT_PURPLE = "#7c3aed";
export const STAT_RED = "#dc2626";
export const STAT_TEAL = "#0d9488";
export const STAT_AMBER = "#d97706";
export const STAT_CYAN = "#0891b2";
export const STAT_ROSE = "#e11d48";

export const MONTHS = [
  "Jan",
  "Fév",
  "Mar",
  "Avr",
  "Mai",
  "Juin",
  "Juil",
  "Août",
  "Sep",
  "Oct",
  "Nov",
  "Déc",
];

// ─── Vue Globale (Capture d'écran) ─────────────────────────────────────────

export const GLOBAL_KPIS = [
  {
    id: "total",
    label: "NOMBRE TOTAL DE BIENS",
    val: "12 458",
    numVal: 12458,
    hint: "Tous types confondus",
    color: MINEPIA_GREEN,
    bg: `${MINEPIA_GREEN}18`,
  },
  {
    id: "valeur",
    label: "VALEUR TOTALE DU PATRIMOINE",
    val: "24,75 Mds FCFA",
    numVal: 24.75,
    hint: "Valeur d'acquisition",
    color: STAT_BLUE,
    bg: `${STAT_BLUE}18`,
  },
  {
    id: "actifs",
    label: "BIENS ACTIFS",
    val: "10 254",
    numVal: 10254,
    hint: "82,3% du total",
    color: STAT_TEAL,
    bg: `${STAT_TEAL}18`,
  },
  {
    id: "maintenance",
    label: "BIENS EN MAINTENANCE",
    val: "1 256",
    numVal: 1256,
    hint: "10,1% du total",
    color: STAT_ORANGE,
    bg: `${STAT_ORANGE}18`,
  },
  {
    id: "sortis",
    label: "BIENS SORTIS",
    val: "948",
    numVal: 948,
    hint: "7,6% du total",
    color: STAT_RED,
    bg: `${STAT_RED}18`,
  },
  {
    id: "structures",
    label: "STRUCTURES CONCERNÉES",
    val: "196",
    numVal: 196,
    hint: "Services / structures",
    color: STAT_PURPLE,
    bg: `${STAT_PURPLE}18`,
  },
];

export const CATEGORIES_BREAKDOWN = [
  {
    id: "vehicules",
    name: "Matériel roulant (Véhicules)",
    value: 5425,
    pct: "43,6%",
    color: MINEPIA_GREEN,
    icon: Truck,
  },
  {
    id: "terrains",
    name: "Terrains",
    value: 1245,
    pct: "10,0%",
    color: STAT_BLUE,
    icon: Mountain,
  },
  {
    id: "batiments",
    name: "Bâtiments",
    value: 1890,
    pct: "15,2%",
    color: STAT_ORANGE,
    icon: Home,
  },
  {
    id: "informatique",
    name: "Matériel informatique",
    value: 2153,
    pct: "17,3%",
    color: STAT_PURPLE,
    icon: Monitor,
  },
  {
    id: "mobilier",
    name: "Mobilier de bureau",
    value: 1745,
    pct: "14,0%",
    color: STAT_TEAL,
    icon: Armchair,
  },
];

export const SERVICES_PIE = [
  {
    name: "Services centraux",
    value: 3245,
    pct: "26,0%",
    color: MINEPIA_GREEN,
  },
  {
    name: "Services déconcentrés",
    value: 9213,
    pct: "74,0%",
    color: STAT_BLUE,
  },
];

export const REGIONS_STATS = [
  { name: "Centre", value: 3245, surfaceHa: 420.5, valeurMds: 6.8 },
  { name: "Littoral", value: 2124, surfaceHa: 285.2, valeurMds: 5.4 },
  { name: "Ouest", value: 1987, surfaceHa: 310.8, valeurMds: 3.9 },
  { name: "Nord", value: 1156, surfaceHa: 540.0, valeurMds: 2.8 },
  { name: "Sud-Ouest", value: 1024, surfaceHa: 195.4, valeurMds: 2.1 },
  { name: "Sud", value: 890, surfaceHa: 260.1, valeurMds: 1.98 },
  { name: "Est", value: 680, surfaceHa: 410.3, valeurMds: 1.2 },
  { name: "Adamaoua", value: 540, surfaceHa: 390.0, valeurMds: 0.95 },
  { name: "Extrême-Nord", value: 490, surfaceHa: 320.0, valeurMds: 0.8 },
  { name: "Nord-Ouest", value: 322, surfaceHa: 150.0, valeurMds: 0.72 },
];

export const TOP10_STRUCTURES = [
  { name: "Délégation Régionale Centre", v: 980 },
  { name: "Délégation Régionale Littoral", v: 872 },
  { name: "Délégation Régionale Ouest", v: 654 },
  { name: "Délégation Régionale Nord", v: 534 },
  { name: "Direction des Ressources", v: 430 },
  { name: "Délégation Régionale Sud", v: 412 },
  { name: "Délégation Régionale Est", v: 367 },
  { name: "Délégation Régionale Extrême-Nord", v: 295 },
  { name: "Délégation Régionale Adamaoua", v: 256 },
  { name: "Délégation Régionale Sud-Ouest", v: 240 },
];

export const TOP5_PROJECTS = [
  { name: "Aquaculture continentale — Phase II", v: 2400 },
  { name: "Renforcement du laboratoire vétérinaire", v: 1560 },
  { name: "Campagne nationale de vaccination bovine", v: 900 },
  { name: "Appui à la filière porcine", v: 640 },
  { name: "Numérisation des postes vétérinaires", v: 420 },
];

export const TOP6_VALEUR = [
  { name: "Direction des Ressources", v: 4.25, color: STAT_PURPLE },
  { name: "Délégation Régionale Littoral", v: 3.86, color: STAT_PURPLE },
  { name: "Délégation Régionale Centre", v: 2.21, color: STAT_PURPLE },
  { name: "Délégation Régionale Ouest", v: 2.75, color: STAT_PURPLE },
  { name: "Délégation Régionale Nord", v: 2.12, color: STAT_PURPLE },
  { name: "Délégation Régionale Sud", v: 1.98, color: STAT_PURPLE },
];

export const PATRIMOINE_VALEUR_MOIS = [
  { m: "Jan", v: 21.2 },
  { m: "Fév", v: 21.5 },
  { m: "Mar", v: 21.6 },
  { m: "Avr", v: 22.1 },
  { m: "Mai", v: 22.4 },
  { m: "Juin", v: 22.8 },
  { m: "Juil", v: 23.1 },
  { m: "Août", v: 23.5 },
  { m: "Sep", v: 23.9 },
  { m: "Oct", v: 24.2 },
  { m: "Nov", v: 24.5 },
  { m: "Déc", v: 24.75 },
];

export const MAINTENANCE_MOIS = [
  { m: "Jan", v: 4.8 },
  { m: "Fév", v: 4.5 },
  { m: "Mar", v: 4.2 },
  { m: "Avr", v: 2.4 },
  { m: "Mai", v: 2.5 },
  { m: "Juin", v: 2.3 },
  { m: "Juil", v: 2.4 },
  { m: "Août", v: 2.4 },
  { m: "Sep", v: 2.4 },
  { m: "Oct", v: 2.4 },
  { m: "Nov", v: 2.5 },
  { m: "Déc", v: 2.5 },
];

export const MOUVEMENTS_MOIS = [
  { m: "Jan", v: 0.8 },
  { m: "Fév", v: 1.2 },
  { m: "Mar", v: 1.4 },
  { m: "Avr", v: 1.1 },
  { m: "Mai", v: 0.9 },
  { m: "Juin", v: 1.3 },
  { m: "Juil", v: 1.0 },
  { m: "Août", v: 1.2 },
  { m: "Sep", v: 1.5 },
  { m: "Oct", v: 1.3 },
  { m: "Nov", v: 1.2 },
  { m: "Déc", v: 1.4 },
];

// ─── Section 2 : Véhicules (Matériel Roulant) ──────────────────────────────
export const VEHICULES_DATA = {
  total: 5425,
  valeurTotale: "11,85 Mds FCFA",
  ageMoyen: "6,4 ans",
  aReformerTotal: 412,
  disparusTotal: 38,
  carteGriseManquante: 184,
  parEtat: [
    { etat: "Neuf", count: 820, pct: "15,1%", color: MINEPIA_GREEN },
    { etat: "Bon", count: 2150, pct: "39,6%", color: STAT_TEAL },
    { etat: "Passable", count: 1120, pct: "20,6%", color: STAT_AMBER },
    { etat: "En panne", count: 480, pct: "8,8%", color: STAT_ORANGE },
    { etat: "Vétuste", count: 320, pct: "5,9%", color: STAT_ROSE },
    { etat: "Hors d'usage", count: 123, pct: "2,3%", color: STAT_RED },
    { etat: "À réformer", count: 312, pct: "5,8%", color: STAT_PURPLE },
    { etat: "Aucune information", count: 100, pct: "1,8%", color: "#94a3b8" },
  ],
  parType: [
    { type: "Pick-up Double Cabine 4x4", count: 2180, pct: "40,2%" },
    { type: "Berline / Citadine", count: 890, pct: "16,4%" },
    { type: "Station Wagon / SUV", count: 640, pct: "11,8%" },
    { type: "Motocyclette tout-terrain", count: 1250, pct: "23,0%" },
    { type: "Camionnette frigorifique", count: 280, pct: "5,2%" },
    { type: "Engin agricole / Tracteur", count: 185, pct: "3,4%" },
  ],
  parMarque: [
    { marque: "Toyota (Hilux, Land Cruiser)", count: 2450 },
    { marque: "Yamaha / Honda (Motos)", count: 1250 },
    { marque: "Nissan (Hardbody, Patrol)", count: 620 },
    { marque: "Mitsubishi (L200, Pajero)", count: 510 },
    { marque: "Renault / Dacia", count: 340 },
    { marque: "Autres", count: 255 },
  ],
  parFinancement: [
    { source: "BIP (Budget d'Investissement Public)", count: 2410, pct: "44,4%" },
    { source: "Projets Donateurs (BAD, BM, UE, FAO)", count: 2180, pct: "40,2%" },
    { source: "Fonds Propres MINEPIA", count: 580, pct: "10,7%" },
    { source: "Dons & Cessions diverses", count: 255, pct: "4,7%" },
  ],
  tranchesAge: [
    { tranche: "< 2 ans (Récent)", count: 980, color: MINEPIA_GREEN },
    { tranche: "2 à 5 ans (Opérationnel)", count: 2140, color: STAT_TEAL },
    { tranche: "5 à 8 ans (Amorti)", count: 1350, color: STAT_AMBER },
    { tranche: "8 à 12 ans (Vétuste)", count: 640, color: STAT_ORANGE },
    { tranche: "> 12 ans (À réformer)", count: 315, color: STAT_RED },
  ],
  aReformerParRegion: [
    { region: "Centre", count: 92 },
    { region: "Littoral", count: 74 },
    { region: "Nord", count: 58 },
    { region: "Ouest", count: 51 },
    { region: "Extrême-Nord", count: 46 },
    { region: "Sud", count: 35 },
    { region: "Est", count: 30 },
    { region: "Adamaoua", count: 26 },
  ],
  disparusParRegion: [
    { region: "Extrême-Nord", count: 14, motif: "Insécurité frontalière" },
    { region: "Nord-Ouest", count: 10, motif: "Vol / zone de crise" },
    { region: "Sud-Ouest", count: 8, motif: "Vol / zone de crise" },
    { region: "Centre", count: 4, motif: "Non restitué après mutation" },
    { region: "Littoral", count: 2, motif: "Sinistre non élucidé" },
  ],
  detenteursTop: [
    { matricule: "612450-X", nom: "Dr MBARGA Jean-Pierre", fonction: "Délégué Régional Centre", vehicules: 3, modeles: "Toyota Hilux (CE-452-AA), Yamaha DT 125, Toyota Prado" },
    { matricule: "598320-K", nom: "Mme FOTSO Clarisse", fonction: "Chef Division Projets", vehicules: 2, modeles: "Toyota Hilux (LT-890-BB), Nissan Patrol" },
    { matricule: "704112-M", nom: "Dr ABOUBAKAR Sali", fonction: "Délégué Départemental Bénoué", vehicules: 2, modeles: "Toyota Land Cruiser, Yamaha AG 100" },
    { matricule: "645990-A", nom: "M. NDOUMBE Joseph", fonction: "Inspecteur Général", vehicules: 2, modeles: "Toyota Prado TX, Renault Duster" },
  ],
  croisementDepartement: [
    { dep: "Mfoundi (Yaoundé)", neuf: 210, bon: 480, passable: 180, enPanne: 45, vetuste: 30, horsUsage: 12, reformer: 38, total: 995 },
    { dep: "Wouri (Douala)", neuf: 140, bon: 350, passable: 140, enPanne: 35, vetuste: 25, horsUsage: 8, reformer: 28, total: 726 },
    { dep: "Mifi (Bafoussam)", neuf: 85, bon: 220, passable: 110, enPanne: 28, vetuste: 18, horsUsage: 6, reformer: 22, total: 489 },
    { dep: "Bénoué (Garoua)", neuf: 65, bon: 190, passable: 95, enPanne: 24, vetuste: 15, horsUsage: 5, reformer: 18, total: 412 },
    { dep: "Fako (Limbé/Buea)", neuf: 50, bon: 140, passable: 80, enPanne: 20, vetuste: 12, horsUsage: 4, reformer: 14, total: 320 },
    { dep: "Vina (Ngaoundéré)", neuf: 45, bon: 120, passable: 65, enPanne: 18, vetuste: 10, horsUsage: 3, reformer: 12, total: 273 },
    { dep: "Diamaré (Maroua)", neuf: 40, bon: 110, passable: 60, enPanne: 16, vetuste: 9, horsUsage: 3, reformer: 10, total: 248 },
    { dep: "Océan (Kribi)", neuf: 38, bon: 95, passable: 52, enPanne: 12, vetuste: 8, horsUsage: 2, reformer: 9, total: 216 },
  ]
};

// ─── Section 3 : Terrains ──────────────────────────────────────────────────
export const TERRAINS_DATA = {
  total: 1245,
  superficieTotaleHa: "3 245,8 Ha",
  valeurTotaleDeclaree: "5,42 Mds FCFA",
  sansValeurCount: 382,
  batiCount: 742,
  nonBatiCount: 423,
  sansInfoBatiCount: 80,
  litigeCount: 48,
  titreFoncierDispo: 520,
  titreFoncierManquant: 725,
  terrainsLoues: 24,
  totalLoyersMensuels: "18,6 M FCFA/mois",
  securisation: [
    { label: "Juridique & Physique (Complet)", count: 340, pct: "27,3%", color: MINEPIA_GREEN },
    { label: "Juridique seule (Titre sans clôture)", count: 280, pct: "22,5%", color: STAT_BLUE },
    { label: "Physique seule (Clôture sans titre)", count: 215, pct: "17,3%", color: STAT_AMBER },
    { label: "Aucune sécurisation (Vulnérable)", count: 410, pct: "32,9%", color: STAT_RED },
  ],
  occupation: [
    { label: "Occupation Régulière (Services MINEPIA)", count: 860, pct: "69,1%", color: MINEPIA_GREEN },
    { label: "Occupation Irrégulière (Empiètements)", count: 185, pct: "14,8%", color: STAT_RED },
    { label: "Inoccupé / Réserve foncière", count: 140, pct: "11,2%", color: STAT_BLUE },
    { label: "Sans information", count: 60, pct: "4,9%", color: "#94a3b8" },
  ],
  acquisitionsParAnnee: [
    { annee: "2018", count: 32, surfaceHa: 84 },
    { annee: "2019", count: 45, surfaceHa: 120 },
    { annee: "2020", count: 28, surfaceHa: 65 },
    { annee: "2021", count: 54, surfaceHa: 195 },
    { annee: "2022", count: 72, surfaceHa: 280 },
    { annee: "2023", count: 88, surfaceHa: 340 },
    { annee: "2024", count: 95, surfaceHa: 410 },
  ],
  litigesList: [
    { site: "Station d'Elevage de Louguéré", region: "Nord", dep: "Bénoué", superficie: "120 Ha", motif: "Empiètement éleveurs sédentaires", statut: "En procédure judiciaire" },
    { site: "Poste Vétérinaire d'Idenau", region: "Sud-Ouest", dep: "Fako", superficie: "1,5 Ha", motif: "Double attribution communale", statut: "Médiation préfectorale" },
    { site: "Centre Zootechnique de Wakwa", region: "Adamaoua", dep: "Vina", superficie: "85 Ha", motif: "Revendication coutumière", statut: "Commission mixte" },
    { site: "Débarcadère de Youpwé", region: "Littoral", dep: "Wouri", superficie: "4,2 Ha", motif: "Squat d'opérateurs privés", statut: "Arrêté d'expulsion en cours" },
  ],
  croisementDepartement: [
    { dep: "Mfoundi", bati: 145, nonBati: 35, sansInfo: 8, reg: 160, irreg: 18, total: 188, tf: 110 },
    { dep: "Wouri", bati: 110, nonBati: 28, sansInfo: 6, reg: 118, irreg: 20, total: 144, tf: 85 },
    { dep: "Bénoué", bati: 82, nonBati: 74, sansInfo: 12, reg: 130, irreg: 26, total: 168, tf: 54 },
    { dep: "Mifi", bati: 78, nonBati: 32, sansInfo: 5, reg: 98, irreg: 12, total: 115, tf: 62 },
    { dep: "Vina", bati: 64, nonBati: 60, sansInfo: 10, reg: 105, irreg: 19, total: 134, tf: 41 },
    { dep: "Fako", bati: 55, nonBati: 30, sansInfo: 7, reg: 72, irreg: 15, total: 92, tf: 38 },
  ]
};

// ─── Section 4 : Bâtiments ─────────────────────────────────────────────────
export const BATIMENTS_DATA = {
  total: 1890,
  valeurTotale: "6,24 Mds FCFA",
  totalPieces: 11340,
  moyennePiecesParBatiment: 6.0,
  aRefectionnerTotal: 345,
  enLitigeTotal: 29,
  titreFoncierDispo: 780,
  louesTotal: 38,
  totalLoyersMensuels: "24,8 M FCFA",
  parEtat: [
    { etat: "Neuf", count: 280, pct: "14,8%", color: MINEPIA_GREEN },
    { etat: "Bon état", count: 720, pct: "38,1%", color: STAT_TEAL },
    { etat: "Passable", count: 440, pct: "23,3%", color: STAT_AMBER },
    { etat: "Vétuste", count: 210, pct: "11,1%", color: STAT_ROSE },
    { etat: "À réfectionner d'urgence", count: 135, pct: "7,1%", color: STAT_ORANGE },
    { etat: "Hors d'usage", count: 45, pct: "2,4%", color: STAT_RED },
    { etat: "Travaux inachevés", count: 38, pct: "2,0%", color: STAT_PURPLE },
    { etat: "Sans information", count: 22, pct: "1,2%", color: "#94a3b8" },
  ],
  refectionParRegion: [
    { region: "Centre", count: 68, coutEstimeM: 185 },
    { region: "Nord", count: 54, coutEstimeM: 142 },
    { region: "Littoral", count: 48, coutEstimeM: 160 },
    { region: "Ouest", count: 42, coutEstimeM: 110 },
    { region: "Extrême-Nord", count: 39, coutEstimeM: 98 },
    { region: "Est", count: 34, coutEstimeM: 88 },
    { region: "Sud", count: 32, coutEstimeM: 82 },
    { region: "Adamaoua", count: 28, coutEstimeM: 74 },
  ],
  occupation: [
    { statut: "Régulière (MINEPIA exclusif)", count: 1320, pct: "69,8%", color: MINEPIA_GREEN },
    { statut: "Cohabitation interministérielle", count: 280, pct: "14,8%", color: STAT_BLUE },
    { statut: "Inoccupé / Vacant", count: 140, pct: "7,4%", color: STAT_AMBER },
    { statut: "Occupation irrégulière", count: 95, pct: "5,0%", color: STAT_RED },
    { statut: "Sans information", count: 55, pct: "2,9%", color: "#94a3b8" },
  ],
  financement: [
    { source: "BIP État du Cameroun", count: 1140, pct: "60,3%" },
    { source: "Projets Bailleurs (BM, BAD, AFD)", count: 490, pct: "25,9%" },
    { source: "Fonds Spéciaux & Coopération bilatérale", count: 180, pct: "9,5%" },
    { source: "Dons / Rétrocession", count: 80, pct: "4,2%" },
  ],
  repartitionAnnees: [
    { tranche: "Avant 1980 (Colonial / Post-indép.)", count: 310 },
    { tranche: "1980 - 1999 (Réseau historique)", count: 560 },
    { tranche: "2000 - 2014 (Modernisation)", count: 620 },
    { tranche: "2015 - 2024 (Récents)", count: 400 },
  ],
  croisementDepartement: [
    { dep: "Mfoundi", neuf: 65, bon: 160, passable: 70, vetuste: 25, refection: 18, inacheve: 4, total: 342 },
    { dep: "Wouri", neuf: 48, bon: 120, passable: 55, vetuste: 20, refection: 14, inacheve: 3, total: 260 },
    { dep: "Bénoué", neuf: 32, bon: 85, passable: 50, vetuste: 24, refection: 16, inacheve: 5, total: 212 },
    { dep: "Mifi", neuf: 30, bon: 80, passable: 42, vetuste: 18, refection: 12, inacheve: 4, total: 186 },
    { dep: "Vina", neuf: 24, bon: 62, passable: 38, vetuste: 15, refection: 11, inacheve: 3, total: 153 },
    { dep: "Fako", neuf: 20, bon: 54, passable: 34, vetuste: 16, refection: 10, inacheve: 3, total: 137 },
  ]
};

// ─── Section 5 : Matériel Informatique ─────────────────────────────────────
export const INFORMATIQUE_DATA = {
  total: 2153,
  valeurTotale: "1,24 Md FCFA",
  aRenouvelerPrioritaire: 540,
  parFamille: [
    { famille: "Informatique & Calcul (PC, Portables, Serveurs)", count: 1280, pct: "59,5%", color: STAT_PURPLE },
    { famille: "Bureautique & Impression (Imprimantes, Copieurs)", count: 620, pct: "28,8%", color: STAT_BLUE },
    { famille: "Équipements spécialisés (GPS, Scanners, Vidéo)", count: 253, pct: "11,7%", color: STAT_TEAL },
  ],
  parType: [
    { type: "Ordinateurs de bureau (Desktop)", code: "OD", count: 740, famille: "Informatique" },
    { type: "Ordinateurs portables (Laptop)", code: "OP", count: 480, famille: "Informatique" },
    { type: "Imprimantes laser / jet d'encre", code: "IM", count: 390, famille: "Bureautique" },
    { type: "Photocopieurs multifonctions", code: "PH", count: 160, famille: "Bureautique" },
    { type: "Scanners de documents", code: "SC", count: 70, famille: "Bureautique" },
    { type: "Vidéoprojecteurs", code: "VP", count: 85, famille: "Spécialisé" },
    { type: "Terminaux GPS / Carto élevage", code: "GP", count: 110, famille: "Spécialisé" },
    { type: "Appareils photo numériques / Suivi", code: "AP", count: 58, famille: "Spécialisé" },
    { type: "Serveurs & Stockage NAS", code: "SV", count: 60, famille: "Informatique" },
  ],
  parEtat: [
    { etat: "Neuf (< 1 an)", count: 380, pct: "17,6%", color: MINEPIA_GREEN },
    { etat: "Bon état de fonctionnement", count: 980, pct: "45,5%", color: STAT_TEAL },
    { etat: "Passable (Ralentissements)", count: 420, pct: "19,5%", color: STAT_AMBER },
    { etat: "En panne (Réparable)", count: 185, pct: "8,6%", color: STAT_ORANGE },
    { etat: "Vétuste / Obsolète", count: 118, pct: "5,5%", color: STAT_ROSE },
    { etat: "Hors d'usage", count: 70, pct: "3,3%", color: STAT_RED },
  ],
  parAnneeAcquisition: [
    { annee: "2019 et avant (À remplacer d'urgence)", count: 540, color: STAT_RED },
    { annee: "2020", count: 280, color: STAT_ORANGE },
    { annee: "2021", count: 320, color: STAT_AMBER },
    { annee: "2022", count: 380, color: STAT_TEAL },
    { annee: "2023", count: 410, color: STAT_BLUE },
    { annee: "2024", count: 223, color: MINEPIA_GREEN },
  ],
  codesLegende: [
    { code: "OD", libelle: "Ordinateur de bureau", categorie: "Informatique", dureeVie: "4 ans" },
    { code: "OP", libelle: "Ordinateur portable", categorie: "Informatique", dureeVie: "3 ans" },
    { code: "IM", libelle: "Imprimante réseau / autonome", categorie: "Bureautique", dureeVie: "4 ans" },
    { code: "PH", libelle: "Photocopieur multifonction professionnel", categorie: "Bureautique", dureeVie: "5 ans" },
    { code: "SC", libelle: "Scanner de numérisation rapide", categorie: "Bureautique", dureeVie: "4 ans" },
    { code: "VP", libelle: "Vidéoprojecteur de salle de réunion", categorie: "Audio/Vidéo", dureeVie: "5 ans" },
    { code: "GP", libelle: "GPS de terrain pour suivi épidémiologique", categorie: "Équipement terrain", dureeVie: "5 ans" },
    { code: "AP", libelle: "Appareil photo de documentation", categorie: "Multimédia", dureeVie: "4 ans" },
    { code: "SV", libelle: "Serveur informatique / Baie réseau", categorie: "Infrastructure IT", dureeVie: "6 ans" },
  ],
  croisementDepartement: [
    { dep: "Mfoundi", neuf: 120, bon: 280, passable: 90, enPanne: 35, vetuste: 20, total: 545 },
    { dep: "Wouri", neuf: 85, bon: 190, passable: 70, enPanne: 25, vetuste: 15, total: 385 },
    { dep: "Bénoué", neuf: 45, bon: 120, passable: 55, enPanne: 22, vetuste: 14, total: 256 },
    { dep: "Mifi", neuf: 40, bon: 110, passable: 50, enPanne: 20, vetuste: 12, total: 232 },
    { dep: "Fako", neuf: 30, bon: 85, passable: 42, enPanne: 18, vetuste: 10, total: 185 },
  ]
};

// ─── Section 6 : Structures & Organisation ─────────────────────────────────
export const STRUCTURES_DATA = {
  totalStructures: 196,
  valeurInfrastructures: "14,8 Mds FCFA",
  coutRefectionEstimeTotal: "980 M FCFA",
  avecBatimentConstruit: 164,
  sansBatimentConstruit: 32,
  aRefectionnerCount: 44,
  avecPlanDisponible: 118,
  avecDevisValide: 36,
  parTypeService: [
    { type: "Administration Centrale (Directions, Divisions, Cellules)", count: 28, color: MINEPIA_GREEN },
    { type: "Délégations Régionales (10 Régions)", count: 10, color: STAT_BLUE },
    { type: "Délégations Départementales (58 Départements)", count: 58, color: STAT_PURPLE },
    { type: "Centres Zootechniques & Vétérinaires (CZV / Postes)", count: 76, color: STAT_TEAL },
    { type: "Centres de Pêche & Débarcadères rattachés", count: 16, color: STAT_AMBER },
    { type: "Organismes sous tutelle (LANAVET, CDPM, etc.)", count: 8, color: STAT_ROSE },
  ],
  plansDisponibilite: [
    { statut: "Plan-type officiel MINEPIA conforme", count: 72, pct: "36,7%", color: MINEPIA_GREEN },
    { statut: "Plan d'architecte spécifique validé", count: 46, pct: "23,5%", color: STAT_BLUE },
    { statut: "Plan sommaire ou non certifié", count: 28, pct: "14,3%", color: STAT_AMBER },
    { statut: "Aucun plan archivé", count: 50, pct: "25,5%", color: STAT_RED },
  ],
  coutRefectionParRegion: [
    { region: "Centre", coutM: 210, structures: 8 },
    { region: "Nord", coutM: 165, structures: 7 },
    { region: "Littoral", coutM: 145, structures: 6 },
    { region: "Ouest", coutM: 120, structures: 5 },
    { region: "Extrême-Nord", coutM: 110, structures: 6 },
    { region: "Sud", coutM: 90, structures: 4 },
    { region: "Est", coutM: 80, structures: 4 },
    { region: "Adamaoua", coutM: 60, structures: 4 },
  ],
  structuresSansSiegeExemples: [
    { nom: "Poste de Contrôle Sanitaire Vétérinaire de Moloundou", region: "Est", dep: "Boumba-et-Ngoko", motif: "Local d'emprunt précaire", priorite: "Haute" },
    { nom: "Centre Zootechnique et Vétérinaire de Blangoua", region: "Extrême-Nord", dep: "Logone-et-Chari", motif: "Destruction suite aux inondations", priorite: "Urgente" },
    { nom: "Poste de Pêche de Mouanko", region: "Littoral", dep: "Sanaga-Maritime", motif: "Bâtiment loué sans commodité", priorite: "Moyenne" },
    { nom: "Délégation d'Arrondissement de Kolofata", region: "Extrême-Nord", dep: "Mayo-Sava", motif: "Délocalisation temporaire", priorite: "Haute" },
  ]
};

// ─── Section 7 : Suivi, Évolution & Alertes ────────────────────────────────
export const SUIVI_ALERTES_DATA = {
  evolutionAnnuelle: [
    { annee: "2021", totalBiens: 9840, vehicules: 4300, terrains: 980, batiments: 1540, informatique: 1620, gap: "+6,5%" },
    { annee: "2022", totalBiens: 10620, vehicules: 4680, terrains: 1060, batiments: 1670, informatique: 1810, gap: "+7,9%" },
    { annee: "2023", totalBiens: 11080, vehicules: 4950, terrains: 1150, batiments: 1780, informatique: 1950, gap: "+4,3%" },
    { annee: "2024", totalBiens: 12458, vehicules: 5425, terrains: 1245, batiments: 1890, informatique: 2153, gap: "+12,4%" },
  ],
  collecteSemaines: [
    { sem: "S1 (Jan)", fichesTraitees: 240, cumul: 240 },
    { sem: "S4 (Fév)", fichesTraitees: 450, cumul: 1120 },
    { sem: "S8 (Mar)", fichesTraitees: 680, cumul: 2600 },
    { sem: "S12 (Avr)", fichesTraitees: 850, cumul: 4800 },
    { sem: "S16 (Mai)", fichesTraitees: 920, cumul: 7200 },
    { sem: "S20 (Juin)", fichesTraitees: 1100, cumul: 9500 },
    { sem: "S24 (Juil)", fichesTraitees: 980, cumul: 11200 },
    { sem: "S28 (Août)", fichesTraitees: 650, cumul: 12458 },
  ],
  departementsSansDeclaration: [
    { dep: "Donga-Mantung", region: "Nord-Ouest", categorieManquante: "Véhicules & Informatique", motif: "Difficultés d'accès et de connectivité", dateDernierRapport: "14/11/2023" },
    { dep: "Menchum", region: "Nord-Ouest", categorieManquante: "Toutes catégories", motif: "Mission de recensement reportée", dateDernierRapport: "Non renseigné" },
    { dep: "Koung-Khi", region: "Ouest", categorieManquante: "Terrains & Bâtiments", motif: "Attente de validation préfectorale", dateDernierRapport: "02/02/2024" },
    { dep: "Mayo-Louti", region: "Nord", categorieManquante: "Matériel informatique", motif: "Fiches en cours de numérisation", dateDernierRapport: "15/05/2024" },
  ],
  sitesPrioritairesASecuriser: [
    { site: "Station Expérimentale Avicole de Mvog-Betsi", region: "Centre", dep: "Mfoundi", arr: "Yaoundé VII", superficie: "18 Ha", menace: "Tentative de lotissement clandestin", urgence: "CRITIQUE", statut: "Dossier transmis au MINCAF" },
    { site: "Complexe de Pêche de Maga", region: "Extrême-Nord", dep: "Mayo-Danay", arr: "Maga", superficie: "45 Ha", menace: "Empiètement maraîcher et perte de bornes", urgence: "HAUTE", statut: "Bornage contradictoire programmé" },
    { site: "Ranch d'État de Faro", region: "Nord", dep: "Faro", arr: "Poli", superficie: "850 Ha", menace: "Occupation pastorale non autorisée", urgence: "HAUTE", statut: "Commission de conciliation" },
    { site: "Poste Vétérinaire de Kribi Port", region: "Sud", dep: "Océan", arr: "Kribi II", superficie: "2,5 Ha", menace: "Revendication d'héritiers coutumiers", urgence: "MOYENNE", statut: "Enquête foncière en cours" },
  ]
};

// ─── Listes de sélection pour les filtres ──────────────────────────────────
export const FILTER_OPTIONS = {
  organigrammes: [
    { value: "all", label: "Toutes les structures" },
    { value: "dir_ressources", label: "Direction des Ressources Financières et du Patrimoine" },
    { value: "dir_elevage", label: "Direction des Productions et des Industries Animales" },
    { value: "dir_veterinaire", label: "Direction des Services Vétérinaires" },
    { value: "dir_peche", label: "Direction des Pêches et de l'Aquaculture" },
    { value: "del_centre", label: "Délégation Régionale du Centre" },
    { value: "del_littoral", label: "Délégation Régionale du Littoral" },
    { value: "del_ouest", label: "Délégation Régionale de l'Ouest" },
    { value: "del_nord", label: "Délégation Régionale du Nord" },
    { value: "del_sud", label: "Délégation Régionale du Sud" },
  ],
  categoriesParent: [
    { value: "all", label: "Toutes les catégories" },
    { value: "vehicules", label: "Matériel roulant (Véhicules)" },
    { value: "terrains", label: "Terrains" },
    { value: "batiments", label: "Bâtiments" },
    { value: "informatique", label: "Matériel informatique" },
    { value: "mobilier", label: "Mobilier de bureau" },
  ],
  typesParCategorie: {
    all: [{ value: "all", label: "Tous les types" }],
    vehicules: [
      { value: "all", label: "Tous les types" },
      { value: "pickup", label: "Pick-up 4x4" },
      { value: "berline", label: "Berline" },
      { value: "suv", label: "Station Wagon / SUV" },
      { value: "moto", label: "Motocyclette" },
      { value: "camion", label: "Camionnette frigorifique" },
      { value: "agricole", label: "Engin agricole" },
    ],
    terrains: [
      { value: "all", label: "Tous les types" },
      { value: "urbain", label: "Terrain urbain bâti" },
      { value: "rural", label: "Terrain rural d'élevage" },
      { value: "station", label: "Station zootechnique" },
      { value: "portuaire", label: "Emprise portuaire/pêche" },
    ],
    batiments: [
      { value: "all", label: "Tous les types" },
      { value: "siege", label: "Bâtiment administratif (Siège)" },
      { value: "labo", label: "Laboratoire vétérinaire" },
      { value: "magasin", label: "Magasin de stockage" },
      { value: "logement", label: "Logement d'astreinte" },
    ],
    informatique: [
      { value: "all", label: "Tous les types" },
      { value: "desktop", label: "Ordinateur de bureau" },
      { value: "laptop", label: "Ordinateur portable" },
      { value: "imprimante", label: "Imprimante / Scanner" },
      { value: "serveur", label: "Serveur informatique" },
      { value: "gps", label: "Terminal GPS" },
    ],
    mobilier: [
      { value: "all", label: "Tous les types" },
      { value: "bureau", label: "Table de bureau" },
      { value: "fauteuil", label: "Fauteuil ergonomique" },
      { value: "armoire", label: "Armoire métallique sécurisée" },
      { value: "salle", label: "Mobilier de salle de conférence" },
    ],
  },
  projets: [
    { value: "all", label: "Tous les projets / donateurs" },
    { value: "aqua2", label: "Aquaculture continentale — Phase II" },
    { value: "labovet", label: "Renforcement du laboratoire vétérinaire" },
    { value: "vaccin", label: "Campagne nationale de vaccination bovine" },
    { value: "porcin", label: "Appui à la filière porcine" },
    { value: "numerique", label: "Numérisation des postes vétérinaires" },
    { value: "bip", label: "BIP (Budget d'Investissement Public)" },
    { value: "fao", label: "Fonds FAO / Nations Unies" },
  ],
  regions: [
    { value: "all", label: "Toutes les régions" },
    { value: "Centre", label: "Centre" },
    { value: "Littoral", label: "Littoral" },
    { value: "Ouest", label: "Ouest" },
    { value: "Nord", label: "Nord" },
    { value: "Sud-Ouest", label: "Sud-Ouest" },
    { value: "Sud", label: "Sud" },
    { value: "Est", label: "Est" },
    { value: "Adamaoua", label: "Adamaoua" },
    { value: "Extrême-Nord", label: "Extrême-Nord" },
    { value: "Nord-Ouest", label: "Nord-Ouest" },
  ],
  departementsParRegion: {
    all: [{ value: "all", label: "Tous les départements" }],
    Centre: [
      { value: "all", label: "Tous les départements" },
      { value: "Mfoundi", label: "Mfoundi" },
      { value: "Lekié", label: "Lekié" },
      { value: "Mbam-et-Inoubou", label: "Mbam-et-Inoubou" },
      { value: "Méfou-et-Afamba", label: "Méfou-et-Afamba" },
      { value: "Nyong-et-Mfoumou", label: "Nyong-et-Mfoumou" },
    ],
    Littoral: [
      { value: "all", label: "Tous les départements" },
      { value: "Wouri", label: "Wouri" },
      { value: "Sanaga-Maritime", label: "Sanaga-Maritime" },
      { value: "Moungo", label: "Moungo" },
      { value: "Nkam", label: "Nkam" },
    ],
    Ouest: [
      { value: "all", label: "Tous les départements" },
      { value: "Mifi", label: "Mifi" },
      { value: "Bamboutos", label: "Bamboutos" },
      { value: "Hauts-Plateaux", label: "Hauts-Plateaux" },
      { value: "Menoua", label: "Menoua" },
      { value: "Noun", label: "Noun" },
    ],
    Nord: [
      { value: "all", label: "Tous les départements" },
      { value: "Bénoué", label: "Bénoué" },
      { value: "Faro", label: "Faro" },
      { value: "Mayo-Louti", label: "Mayo-Louti" },
      { value: "Mayo-Rey", label: "Mayo-Rey" },
    ],
    "Sud-Ouest": [
      { value: "all", label: "Tous les départements" },
      { value: "Fako", label: "Fako" },
      { value: "Meme", label: "Meme" },
      { value: "Ndian", label: "Ndian" },
      { value: "Manyu", label: "Manyu" },
    ],
    Sud: [
      { value: "all", label: "Tous les départements" },
      { value: "Mvila", label: "Mvila" },
      { value: "Océan", label: "Océan" },
      { value: "Dja-et-Lobo", label: "Dja-et-Lobo" },
      { value: "Vallée-du-Ntem", label: "Vallée-du-Ntem" },
    ],
  },
  etatsBien: [
    { value: "neuf", label: "Neuf" },
    { value: "bon", label: "Bon" },
    { value: "passable", label: "Passable" },
    { value: "panne", label: "En panne" },
    { value: "vetuste", label: "Vétuste" },
    { value: "hors_usage", label: "Hors d'usage" },
    { value: "reformer", label: "À réformer" },
  ],
  statutsGestion: [
    { value: "all", label: "Tous les statuts de gestion" },
    { value: "actif", label: "Biens Actifs (En service)" },
    { value: "maintenance", label: "En Maintenance / Réparation" },
    { value: "sorti", label: "Sortis du patrimoine (Réformés/Cédés)" },
  ],
};
