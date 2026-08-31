// Barrel de compatibilité — les mocks vivent désormais dans `src/api/*`.
// Chaque module métier a son propre fichier : biens, inventaires,
// programmation, maintenance, projets, configuration.
export * from "@/api/common";
export * from "@/api/biens";
export * from "@/api/inventaires";
export * from "@/api/programmation";
export * from "@/api/maintenance";
export * from "@/api/projets";
export * from "@/api/configuration";