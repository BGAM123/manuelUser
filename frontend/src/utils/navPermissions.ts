/**
 * Table de correspondance navigation → permissions du catalogue RBAC.
 *
 * Chaque entrée liste les codes de permission (voir GET /permissions,
 * `nom`) dont AU MOINS UN accorde l'accès à l'élément de menu correspondant
 * (sémantique "anyOf" — un utilisateur qui peut faire au moins une chose
 * dans une section doit pouvoir y naviguer). Basé sur la Fiche d'affectation
 * des permissions par rôle (2026-08-21) : le libellé d'une permission décrit
 * l'action qu'elle autorise, donc la section qui l'expose doit rester
 * visible pour quiconque la détient.
 *
 * Un compte administrateur (détecté via useIsAdmin — nom de rôle contenant
 * "admin") voit toujours tout, indépendamment de cette table : c'est le
 * filet de sécurité déjà utilisé ailleurs dans l'app (voir useIsAdmin.ts).
 */

/** Éléments de la barre de navigation principale (navItems dans AppShell). */
export const TOP_NAV_PERMISSIONS: Record<string, string[]> = {
  "/statistiques": [
    "consultation_tableau_bord",
    "consultation_statistiques",
    "edition_etat",
  ],
  "/biens": [
    "creation_bien", "liste_bien", "consultation_bien", "modification_bien",
    "voir_fiche_bien", "modifier_bien", "supprimer_bien",
    "imprimer_fiche_bien", "imprimer_bordereau", "export_bien",
    "exporter_excel", "exporter_pdf", "voir_biens_carte",
    "consulter_mercuriale", "generer_qrcode_bien", "modifier_etat_bien",
    "affectation_bien", "creer_affectation_bien", "dotation_bien",
    "restitution_bien", "consultation_detenteur", "voir_fiche_detenteur",
    "historique_dotation", "accuser_reception_bien",
    "creation_mouvement", "consultation_mouvement",
    "creation_sortie_provisoire", "retour_bien",
    "proposition_reforme", "sortir_bien", "edition_pv_reforme",
    "sortie_definitive_bien", "validation_reforme",
    "creation_inventaire", "saisie_inventaire", "validation_inventaire",
    "consultation_inventaire",
    "consultation_amortissement", "calcul_amortissement",
    "voir_tableau_amortissement",
    "creation_reevaluation", "reevaluer_bien", "consultation_valorisation",
    "creation_besoin_maintenance", "saisie_depense_maintenance",
    "enregistrer_maintenance_bien", "consultation_maintenance",
    "voir_besoins_maintenance",
    "creation_fiche_animal", "mouvement_cheptel", "suivi_sanitaire_cheptel",
    "creation_projet", "consultation_projet",
    "creation_programmation", "consultation_programmation",
    "creation_programme", "consultation_programme",
  ],
  "/consomptibles": [
    "liste_consomptible", "creation_consomptible", "modification_consomptible",
    "suppression_consomptible", "creation_transfert_consomptible",
    "suppression_transfert_consomptible", "consultation_bilan_consomptible",
    "enregistrement_consommation_consomptible", "gestion_stock",
  ],
};

/**
 * Éléments sous Administration — clés = AdminItem.key dans AppShell
 * (accordéons ET liens directs partagent le même espace de clés).
 * L'onglet /configuration lui-même est visible dès qu'AU MOINS UNE de ces
 * clés est accordée.
 */
export const ADMIN_ITEM_PERMISSIONS: Record<string, string[]> = {
  orga: ["gestion_organigramme", "creation_structure", "liste_structure", "modification_structure", "suppression_structure"],
  users: ["liste_utilisateur", "creation_utilisateur", "attribution_profil"],
  permissions: ["liste_permission", "creation_permission", "affectation_permission_role"],
  roles: ["liste_role", "creation_role", "affectation_permission_role"],
  groupes: ["liste_groupe", "creation_groupe"],
  logs: ["consultation_logs", "consultation_journal_connexion", "consultation_audit"],
  categories: ["liste_categorie", "creation_categorie", "gestion_referentiel"],
  exitTypes: ["liste_type_sortie", "creation_type_sortie"],
  etatBiens: ["liste_etat_bien", "creation_etat_bien"],
  champs: ["liste_champ_personnalise", "creation_champ_personnalise"],
  cartographie: ["liste_cartographie", "creation_cartographie"],
  projets: ["liste_source_financement", "creation_source_financement"],
  notifications: ["liste_notification"],
  types: ["liste_type_bien", "creation_type_bien", "gestion_referentiel"],
  subtypes: ["liste_type_bien", "creation_type_bien", "gestion_referentiel"],
  securisations: ["parametrage_securite"],
};
