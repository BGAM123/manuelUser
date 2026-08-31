import { createContext, useContext, useState, type ReactNode } from "react";

type Language = "fr" | "en";

interface Translation {
  fr: string;
  en: string;
}

interface Translations {
  [key: string]: Translation;
}

interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (key: string, params?: Record<string, string>) => string;
}

/**
 * Recensement des libellés des pages Statistiques, Biens,
 * Biens consommables et Administration.
 *
 * Ce dictionnaire est volontairement isolé dans ce fichier.
 * L'intégration dans les pages pourra être faite ultérieurement.
 */
export const translations: Translations = {
  // Général et navigation
  appName: { fr: "MINEPIA — Patrimoine", en: "MINEPIA — Assets" },
  dashboard: { fr: "Tableau de bord", en: "Dashboard" },
  statistics: { fr: "Statistiques", en: "Statistics" },
  assets: { fr: "Biens", en: "Assets" },
  consumables: { fr: "Biens consommables", en: "Consumable assets" },
  administration: { fr: "Administration", en: "Administration" },
  configuration: { fr: "Configuration", en: "Configuration" },
  inventory: { fr: "Inventaire", en: "Inventory" },
  management: { fr: "Gestion", en: "Management" },
  list: { fr: "Liste", en: "List" },
  details: { fr: "Détails", en: "Details" },
  close: { fr: "Fermer", en: "Close" },
  yes: { fr: "Oui", en: "Yes" },
  no: { fr: "Non", en: "No" },
  all: { fr: "Tous", en: "All" },
  none: { fr: "Aucun", en: "None" },
  loading: { fr: "Chargement…", en: "Loading…" },
  noData: { fr: "Aucune donnée", en: "No data" },
  required: { fr: "Obligatoire", en: "Required" },

  // Actions communes
  add: { fr: "Ajouter", en: "Add" },
  new: { fr: "Nouveau", en: "New" },
  create: { fr: "Créer", en: "Create" },
  view: { fr: "Voir", en: "View" },
  viewDetails: { fr: "Voir les détails", en: "View details" },
  edit: { fr: "Modifier", en: "Edit" },
  delete: { fr: "Supprimer", en: "Delete" },
  archive: { fr: "Archiver", en: "Archive" },
  restore: { fr: "Restaurer", en: "Restore" },
  save: { fr: "Enregistrer", en: "Save" },
  cancel: { fr: "Annuler", en: "Cancel" },
  back: { fr: "Retour", en: "Back" },
  confirm: { fr: "Confirmer", en: "Confirm" },
  search: { fr: "Rechercher", en: "Search" },
  filter: { fr: "Filtrer", en: "Filter" },
  reset: { fr: "Réinitialiser", en: "Reset" },
  export: { fr: "Exporter", en: "Export" },
  exportExcel: { fr: "Exporter en Excel", en: "Export to Excel" },
  exportPdf: { fr: "Exporter en PDF", en: "Export to PDF" },
  download: { fr: "Télécharger", en: "Download" },
  downloadThisFile: { fr: "Télécharger ce fichier", en: "Download this file" },
  print: { fr: "Imprimer", en: "Print" },
  select: { fr: "Sélectionner", en: "Select" },
  selectAll: { fr: "Tout sélectionner", en: "Select all" },
  previous: { fr: "Précédent", en: "Previous" },
  next: { fr: "Suivant", en: "Next" },
  actions: { fr: "Actions", en: "Actions" },
  columns: { fr: "Colonnes", en: "Columns" },
  visibleColumns: { fr: "Colonnes visibles", en: "Visible columns" },

  // Page Biens
  assetsTitle: { fr: "Gestion des biens", en: "Asset management" },
  assetsSubtitle: { fr: "Inventaire, acquisitions, mouvements et valorisation", 
   en: "Inventory, acquisitions, movements and valuation" },
  addAsset: { fr: "Ajouter un bien", en: "Add asset" },
  newAsset: { fr: "Nouvelle acquisition", en: "New acquisition" },
  assetDetails: { fr: "Détail du bien", en: "Asset details" },
  editAsset: { fr: "Modifier le bien", en: "Edit asset" },
  assetSheet: { fr: "Fiche de bien", en: "Asset sheet" },
  reference: { fr: "Référence", en: "Reference" },
  designation: { fr: "Désignation", en: "Designation" },
  category: { fr: "Catégorie", en: "Category" },
  assetType: { fr: "Type de bien", en: "Asset type" },
  currentStructure: { fr: "Structure actuelle", en: "Current structure" },
  registrationNumber: { fr: "Matricule", en: "Registration number" },
  status: { fr: "Statut", en: "Status" },
  fundingSource: { fr: "Source de financement", en: "Funding source" },
  fiscalYear: { fr: "Exercice", en: "Fiscal year" },
  value: { fr: "Valeur", en: "Value" },
  valueFcfa: { fr: "Valeur (FCFA)", en: "Value (XAF)" },
  acquisitionDate: { fr: "Date d'acquisition", en: "Acquisition date" },
  assetCondition: { fr: "État du bien", en: "Asset condition" },
  location: { fr: "Localisation", en: "Location" },
  holder: { fr: "Détenteur", en: "Holder" },
  administrativeUnit: { fr: "Unité administrative", en: "Administrative unit" },
  litigationStatus: { fr: "Statut litige", en: "Litigation status" },
  secured: { fr: "Sécurisé", en: "Secured" },
  notSecured: { fr: "Non sécurisé", en: "Not secured" },
  current: { fr: "Actuel", en: "Current" },
  assignment: { fr: "Affectation", en: "Assignment" },
  newAssignment: { fr: "Nouvelle affectation", en: "New assignment" },
  movement: { fr: "Mouvement", en: "Movement" },
  maintenance: { fr: "Maintenance", en: "Maintenance" },
  addMaintenance: { fr: "Ajouter une maintenance", en: "Add maintenance" },
  reform: { fr: "Mise en réforme", en: "Retirement" },
  depreciation: { fr: "Amortissement", en: "Depreciation" },
  disposal: { fr: "Sortie", en: "Disposal" },
  valuation: { fr: "Valorisation", en: "Valuation" },
  exitAsset: { fr: "Sortir le bien", en: "Dispose of asset" },
  secureAssets: { fr: "Sécuriser les biens", en: "Secure assets" },
  inventorySheet: { fr: "Fiche de bien patrimonial", en: "Patrimonial asset sheet" },
  inventoryReport: { fr: "Bordereau des biens patrimoniaux", en: "Patrimonial assets report" },
  exportFormat: { fr: "Format d'export", en: "Export format" },
  allCategories: { fr: "Toutes les catégories", en: "All categories" },
  allStatuses: { fr: "Tous les statuts", en: "All statuses" },
  allConditions: { fr: "Tous les états", en: "All conditions" },
  searchAssetPlaceholder: { fr: "Rechercher un bien, une référence, une catégorie...", en: "Search an asset, reference or category..." },
  noAssetFound: { fr: "Aucun bien trouvé", en: "No asset found" },
  assetLoadingError: { fr: "Impossible de charger la liste des biens. Réessayez ou contactez l'équipe backend si le problème persiste.", en: "Unable to load the asset list. Try again or contact the backend team if the problem persists." },
  attachFiles: { fr: "Joindre des fichiers pour ce bien", en: "Attach files for this asset" },
  importMapFile: { fr: "Importer un fichier cartographique", en: "Import a map file" },
  reformValidation: { fr: "Validation de réforme", en: "Retirement validation" },
  reformReason: { fr: "Raison de la réforme", en: "Reason for retirement" },
  attachments: { fr: "Pièces jointes", en: "Attachments" },
  validateReform: { fr: "Valider la réforme", en: "Validate retirement" },

  // Page Biens consommables
  consumablesTitle: { fr: "Gestion des biens consommables", en: "Consumable asset management" },
  consumablesSubtitle: { fr: "Suivi des stocks, entrées, sorties et bénéficiaires", en: "Track inventory, receipts, issues and beneficiaries" },
  consumable: { fr: "Bien consommable", en: "Consumable asset" },
  consumablePlural: { fr: "Biens consommables", en: "Consumable assets" },
  consumableName: { fr: "Nom du consommable", en: "Consumable name" },
  consumableCode: { fr: "Code consommable", en: "Consumable code" },
  stock: { fr: "Stock", en: "Stock" },
  initialStock: { fr: "Stock initial", en: "Initial stock" },
  availableStock: { fr: "Stock disponible", en: "Available stock" },
  minimumStock: { fr: "Stock minimum", en: "Minimum stock" },
  quantity: { fr: "Quantité", en: "Quantity" },
  unit: { fr: "Unité", en: "Unit" },
  entry: { fr: "Entrée", en: "Receipt" },
  exit: { fr: "Sortie", en: "Issue" },
  stockEntry: { fr: "Entrée en stock", en: "Stock receipt" },
  stockExit: { fr: "Sortie de stock", en: "Stock issue" },
  beneficiary: { fr: "Bénéficiaire", en: "Beneficiary" },
  service: { fr: "Service", en: "Service" },
  beneficiaryService: { fr: "Service bénéficiaire", en: "Beneficiary service" },
  issueSlip: { fr: "Bon de sortie", en: "Issue slip" },
  provisionalIssueSlip: { fr: "Bon de sortie provisoire", en: "Provisional issue slip" },
  materialRequest: { fr: "Demande de matériel", en: "Material request" },
  requester: { fr: "Le demandeur", en: "Requester" },
  materialsOfficer: { fr: "L'Ordonnateur Matières", en: "Materials officer" },
  materialsAccountant: { fr: "Le Comptable matières", en: "Materials accountant" },
  receiptAcknowledged: { fr: "Réception accusée", en: "Receipt acknowledged" },
  acknowledgeReceipt: { fr: "Accuser réception", en: "Acknowledge receipt" },

  // Page Statistiques
  statisticsTitle: { fr: "Statistiques & Consultation", en: "Statistics & Reports" },
  statisticsSubtitle: { fr: "États consultables et exports", en: "Consultable reports and exports" },
  globalOverview: { fr: "Vue globale", en: "Global overview" },
  vehicles: { fr: "Véhicules", en: "Vehicles" },
  rollingStock: { fr: "Matériel roulant", en: "Rolling stock" },
  land: { fr: "Terrains", en: "Land" },
  buildings: { fr: "Bâtiments", en: "Buildings" },
  computerEquipment: { fr: "Matériel informatique", en: "Computer equipment" },
  structures: { fr: "Structures", en: "Structures" },
  attentionTracking: { fr: "Suivi des alertes", en: "Alert tracking" },
  activeFilters: { fr: "Filtres actifs", en: "Active filters" },
  period: { fr: "Période", en: "Period" },
  year: { fr: "Année", en: "Year" },
  organization: { fr: "Organigramme", en: "Organization chart" },
  project: { fr: "Projet", en: "Project" },
  region: { fr: "Région", en: "Region" },
  department: { fr: "Département", en: "Department" },
  district: { fr: "Arrondissement", en: "District" },
  managementStatus: { fr: "Statut de gestion", en: "Management status" },
  landTitle: { fr: "Titre foncier", en: "Land title" },
  registrationCard: { fr: "Carte grise", en: "Registration card" },
  inLitigationOnly: { fr: "Uniquement les biens en litige", en: "Litigation assets only" },
  resetAllFilters: { fr: "Réinitialiser tous les filtres", en: "Reset all filters" },
  showAllWidgets: { fr: "Afficher tous les indicateurs", en: "Show all indicators" },
  categoriesBreakdown: { fr: "Répartition par catégorie", en: "Breakdown by category" },
  regionalBreakdown: { fr: "Répartition régionale", en: "Regional breakdown" },
  totalRecorded: { fr: "Total recensé", en: "Total recorded" },
  declaredValue: { fr: "Valeur déclarée", en: "Declared value" },
  activeAssets: { fr: "Biens actifs", en: "Active assets" },
  attentionPoints: { fr: "Points d'attention", en: "Attention points" },
  maintenanceCost: { fr: "Coût de maintenance", en: "Maintenance cost" },
  toRetire: { fr: "À réformer", en: "To retire" },
  exportExcelSuccess: { fr: "Export Excel officiel téléchargé avec succès.", en: "Official Excel export downloaded successfully." },
  exportPdfSuccess: { fr: "Export PDF officiel téléchargé avec succès.", en: "Official PDF export downloaded successfully." },

  // Page Administration
  administrationTitle: { fr: "Configuration", en: "Configuration" },
  administrationSubtitle: { fr: "Paramétrage du système", en: "System settings" },
  userAccounts: { fr: "Comptes utilisateurs", en: "User accounts" },
  users: { fr: "Utilisateurs", en: "Users" },
  organizationChart: { fr: "Organigramme", en: "Organization chart" },
  permissions: { fr: "Permissions", en: "Permissions" },
  roles: { fr: "Rôles", en: "Roles" },
  groups: { fr: "Groupes", en: "Groups" },
  assetTypes: { fr: "Types de biens", en: "Asset types" },
  categories: { fr: "Catégories", en: "Categories" },
  projects: { fr: "Projets", en: "Projects" },
  cartography: { fr: "Cartographie", en: "Cartography" },
  assetStates: { fr: "États des biens", en: "Asset states" },
  customFields: { fr: "Champs personnalisés", en: "Custom fields" },
  securedAssets: { fr: "Biens sécurisés", en: "Secured assets" },
  fundingSources: { fr: "Sources de financement", en: "Funding sources" },
  subtypes: { fr: "Sous-types de biens", en: "Asset sub-types" },
  name: { fr: "Nom", en: "Name" },
  fullName: { fr: "Nom complet", en: "Full name" },
  email: { fr: "Email", en: "Email" },
  password: { fr: "Mot de passe", en: "Password" },
  technicalName: { fr: "Nom technique", en: "Technical name" },
  description: { fr: "Description", en: "Description" },
  acronymCode: { fr: "Sigle / Code", en: "Acronym / Code" },
  order: { fr: "N° d'ordre", en: "Order" },
  parentService: { fr: "Service parent", en: "Parent service" },
  assignedRoles: { fr: "Rôles affectés", en: "Assigned roles" },
  noRole: { fr: "Aucun rôle", en: "No role" },
  selectRoles: { fr: "Sélectionner des rôles", en: "Select roles" },
  noService: { fr: "Aucun service", en: "No service" },
  twoFactor: { fr: "Authentification à double facteur (2FA)", en: "Two-factor authentication (2FA)" },
  twoFactorHelp: { fr: "Si activé, cet utilisateur devra confirmer son identité par un second facteur à chaque connexion.", en: "If enabled, this user will need to confirm their identity with a second factor on each login." },
  privileges: { fr: "Privilèges", en: "Privileges" },
  members: { fr: "Membres", en: "Members" },
  selectPermissions: { fr: "Sélectionner des permissions", en: "Select permissions" },
  noPermissions: { fr: "Aucune permission affectée", en: "No permissions assigned" },
  managePermissions: { fr: "Gérer les permissions", en: "Manage permissions" },
  assignPermissions: { fr: "Affecter des permissions", en: "Assign permissions" },
  manageMembers: { fr: "Gérer les membres", en: "Manage members" },
  showArchived: { fr: "Afficher les archivés", en: "Show archived" },
  archived: { fr: "Archivé", en: "Archived" },
  deleted: { fr: "Supprimé", en: "Deleted" },
  parentCategory: { fr: "Catégorie parente", en: "Parent category" },
  chooseAssetType: { fr: "Choisir un type de bien", en: "Choose an asset type" },
  filterByCategory: { fr: "Filtrer par catégorie", en: "Filter by category" },
  filterByType: { fr: "Filtrer par type de bien", en: "Filter by asset type" },

  // Notifications et confirmations
  savedSuccessfully: { fr: "Enregistré avec succès", en: "Saved successfully" },
  deletedSuccessfully: { fr: "Supprimé avec succès", en: "Deleted successfully" },
  errorOccurred: { fr: "Une erreur est survenue", en: "Something went wrong" },
  confirmDelete: { fr: "Confirmer la suppression ?", en: "Confirm deletion?" },
  noAssetAvailable: { fr: "Aucune donnée d'inventaire disponible.", en: "No inventory data available." },
  assetCreated: { fr: "Bien enregistré avec succès dans la base de données !", en: "Asset saved successfully in the database!" },
  assetUpdated: { fr: "Bien mis à jour avec succès", en: "Asset updated successfully" },
  assetDeleted: { fr: "Bien supprimé", en: "Asset deleted" },
  inventoryExported: { fr: "Inventaire exporté avec succès.", en: "Inventory exported successfully." },
  mandatoryDecision: { fr: "La décision est obligatoire", en: "The decision is required" },
  mandatoryAttachment: { fr: "Au moins une pièce jointe est obligatoire", en: "At least one attachment is required" },
};

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export const LanguageProvider = ({ children }: { children: ReactNode }) => {
  const [language, setLanguage] = useState<Language>("fr");

  const t = (key: string, params?: Record<string, string>): string => {
    let translation = translations[key]?.[language] ?? key;

    if (params) {
      Object.keys(params).forEach((param) => {
        translation = translation.replace(
          new RegExp(`\\{${param}\\}`, "g"),
          params[param],
        );
      });
    }

    return translation;
  };

  return (
    <LanguageContext.Provider value={{ language, setLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = (): LanguageContextType => {
  const context = useContext(LanguageContext);

  if (!context) {
    throw new Error("useLanguage must be used within a LanguageProvider");
  }

  return context;
};
