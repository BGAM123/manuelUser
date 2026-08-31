/**
 * StatistiquesFilterBar — Barre de filtres du module statistiques.
 *
 * Changements vs ancienne version :
 * - "Organigramme" renommé "Structure / Service", remplacé par OrgTreeSelect
 *   (même composant que les formulaires de biens — GET /organigramme, arbre
 *   réel + recherche serveur), au lieu de l'ancien arbre reconstruit à la main
 *   depuis GET /services (incomplet/pas fiable).
 * - "Catégorie" + "Type de bien", "Région" et "Source de financement" sont
 *   passés en MULTI-sélection (demande explicite, 2026-08-27). Vérifié sur le
 *   Swagger (2026-08-28) : l'API accepte bien des listes pour type_bien_ids/
 *   region_ids/departement_ids/arrondissement_ids/projet_ids, MAIS pas pour
 *   categorie_id — un seul ID à la fois (voir commentaire dans StatisticsFilter
 *   et filterMapper.ts). Le multi-sélection catégorie ne filtre donc l'API
 *   que lorsqu'une seule catégorie est cochée ; au-delà, sans effet côté API.
 * - Bouton "+ Plus de filtres" retiré de l'interface (demande explicite).
 */
import { Label } from "@/components/ui/label";
import type { FilterState } from "./types";
import { FilterCategorieTreeMulti } from "./FilterCategorieTreeMulti";
import { FilterRegionTreeMulti } from "./FilterRegionTreeMulti";
import { OrgTreeMultiSelect } from "@/components/shared/OrgTreeMultiSelect";
import { SearchableMultiSelect } from "@/components/shared/SearchableMultiSelect";
import { useProjetOptions } from "@/hooks/useFilterReferentiel";
import { useIsAdmin } from "@/hooks/useIsAdmin";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { YearStepper } from "@/components/shared/YearStepper";
import { useT } from "@/utils/i18n";

interface StatistiquesFilterBarProps {
  filters: FilterState;
  onChangeFilter: <K extends keyof FilterState>(key: K, value: FilterState[K]) => void;
  onApplyFilters: (filters: FilterState) => void;
  onResetFilters: () => void;
}

export function StatistiquesFilterBar({
  filters,
  onChangeFilter,
}: StatistiquesFilterBarProps) {
  const t = useT();
  // ── Référentiel projets (orga, catégories et régions gérés par leurs arbres dédiés) ──
  const { data: projetOptions = [] } = useProjetOptions();
  const projetMultiOptions = projetOptions
    .filter((o) => o.value !== "all" && o.id != null)
    .map((o) => ({ value: o.id as number, label: o.label }));

  // Non-admin : le backend applique une barrière de sécurité (son service +
  // descendants, cf. StatisticsService::buildSecuredFilters) — l'arbre
  // proposé ici est restreint à ce même périmètre pour éviter de proposer un
  // choix qui ne renverrait silencieusement aucune donnée (demande explicite
  // 2026-08-29). La vraie restriction reste côté serveur.
  const isAdmin = useIsAdmin();
  const { user: connectedUser } = useConnectedUser();
  const rootServiceId = !isAdmin ? connectedUser?.service?.id : undefined;

  return (
    <div className="flex flex-wrap items-end justify-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-xs">

      {/* ── Filtre 1 : Structure / Service (arbre organigramme réel, multi-sélection) ── */}
      <div className="flex min-w-[220px] max-w-[320px] flex-1 flex-col gap-1">
        <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
          {t("stats.filter.structure")}
        </Label>
        <OrgTreeMultiSelect
          value={filters.organigrammeServiceIds}
          onChange={(ids) => onChangeFilter("organigrammeServiceIds", ids)}
          rootServiceId={rootServiceId}
          placeholder={isAdmin ? t("stats.filter.allStructures") : t("stats.filter.myService")}
          searchPlaceholder={t("stats.filter.searchStructure")}
          deferApply
        />
      </div>

      {/* ── Filtre 2 : Catégorie + Type imbriqué (arbre, multi-sélection) ── */}
      <div className="flex min-w-[220px] max-w-[320px] flex-1 flex-col gap-1">
        <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
          {t("stats.filter.categoryType")}
        </Label>
        <FilterCategorieTreeMulti
          categorieIds={filters.categorieIds}
          assetTypeIds={filters.assetTypeIds}
          onChange={(categorieIds, assetTypeIds) => {
            onChangeFilter("categorieIds", categorieIds);
            onChangeFilter("assetTypeIds", assetTypeIds);
          }}
        />
      </div>

      {/* ── Filtre 3 : Source de financement (multi-sélection) ────────────── */}
      <div className="flex min-w-[220px] max-w-[320px] flex-1 flex-col gap-1">
        <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
          {t("stats.filter.fundingSource")}
        </Label>
        <SearchableMultiSelect
          options={projetMultiOptions}
          value={filters.projectIds}
          onChange={(ids) => onChangeFilter("projectIds", ids)}
          placeholder={t("stats.filter.allFundingSources")}
          displayStyle="compact"
          deferApply
        />
      </div>

      {/* ── Filtre 4 : Région (arbre Région > Département > Arrondissement, multi) ── */}
      <div className="flex min-w-[220px] max-w-[320px] flex-1 flex-col gap-1">
        <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
          {t("stats.filter.region")}
        </Label>
        <FilterRegionTreeMulti
          regionIds={filters.regionIds}
          departementIds={filters.departementIds}
          arrondissementIds={filters.arrondissementIds}
          onChange={(regionIds, departementIds, arrondissementIds) => {
            onChangeFilter("regionIds", regionIds);
            onChangeFilter("departementIds", departementIds);
            onChangeFilter("arrondissementIds", arrondissementIds);
          }}
        />
      </div>

      {/* ── Filtre 5 : Exercice — année en cours par défaut, GET /api/stats/dashboard?exercice= (2026-08-29) ── */}
      <div className="flex w-32 flex-col gap-1">
        <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
          {t("stats.filter.exercice")}
        </Label>
        <YearStepper value={filters.exercice} onChange={(y) => onChangeFilter("exercice", y)} />
      </div>
    </div>
  );
}
