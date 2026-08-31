import { useState } from "react";
import React from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Users,
  Users2,
  User,
  Network,
  ShieldCheck,
  UserCog,
  Box,
  FolderOpen,
  Briefcase,
  Map,
  Tag,
  Sliders,
  Plus,
  Pencil,
  Trash2,
  Save,
  Eye,
  EyeOff,
  X,
  ArrowLeft,
  Loader2,
  KeyRound,
  ChevronDown,
  Lock,
  MoreVertical,
  RotateCcw,
  ScrollText,
  type LucideIcon,
} from "lucide-react";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { toast } from "sonner";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { FicheDetenteurDialog } from "@/components/shared/FicheDetenteurDialog";
import { cn } from "@/utils/utils";
import {
  listUsers,
  createUser,
  updateUser,
  deleteUser,
  forceDeleteUser,
  resetUserPassword,
  getUserPermissions,
  type ApiUser,
  type CreateUserPayload,
  type UpdateUserPayload,
  type ResetUserPasswordPayload,
} from "@/api/users/users.api";
import { listRoles, type ApiRole } from "@/api/roles/roles.api";
import { OrgTreeSelect } from "@/components/shared/OrgTreeSelect";
import { RolesSection } from "@/components/config/RolesSection";
import { GroupesSection } from "@/components/config/GroupesSection";
import { PermissionsSection } from "@/components/config/PermissionsSection";
import { OrganigrammeSection } from "@/components/config/OrganigrammeSection";
import { ProjetsSection } from "@/components/config/ProjetsSection";
import { CategoriesSection } from "@/components/config/CategoriesSection";
import { AssetTypesSection } from "@/components/config/AssetTypesSection";
import { AssetSubtypesSection } from "@/components/config/AssetSubtypesSection";
import { CartographieSection } from "@/components/config/CartographieSection";
import { EtatBiensSection } from "@/components/config/EtatBiensSection";
import { NotificationsSection } from "@/components/config/NotificationsSection";
import { ChampsSection } from "@/components/config/ChampsSection";
import { CategoriesBienSection } from "@/components/config/CategoriesBienSection";
import { ExitTypesSection } from "@/components/config/ExitTypesSection";
import { SecurisationsSection } from "@/components/config/SecurisationsSection";
import { LogsSection } from "@/components/config/LogsSection";

type Section =
  | "orga"
  | "permissions"
  | "roles"
  | "groupes"
  | "users"
  | "types"
  | "subtypes"
  | "categories"
  | "projets"
  | "cartographie"
  | "etatBiens"
  | "champs"
  | "exitTypes"
  | "securisations"
  | "notifications"
  | "logs";

type SectionView = "liste" | "creation" | "edition";

const sections: { key: Section; labelKey: string; icon: LucideIcon }[] = [
  { key: "orga", labelKey: "config.orga", icon: Network },
  { key: "permissions", labelKey: "config.permissions", icon: ShieldCheck },
  { key: "roles", labelKey: "config.roles", icon: UserCog },
  { key: "groupes", labelKey: "config.groupes", icon: Users2 },
  { key: "users", labelKey: "config.users", icon: Users },
  { key: "types", labelKey: "config.types", icon: Box },
  { key: "categories", labelKey: "config.categories", icon: FolderOpen },
  { key: "projets", labelKey: "config.sourcesFinancement", icon: Briefcase },
  { key: "cartographie", labelKey: "config.cartographie", icon: Map },
  { key: "etatBiens", labelKey: "config.etatBiens", icon: Tag },
  { key: "champs", labelKey: "config.champs", icon: Sliders },
  { key: "securisations", labelKey: "config.securisations", icon: Lock },
];

function ConfigShell() {
  const t = useT();
  const [searchParams, setSearchParams] = useSearchParams();
  const [section, setSection] = useState<Section>(
    (searchParams.get("s") as Section | null) ?? "users",
  );
  const [view, setView] = useState<SectionView>("liste");

  // Synchronise la section active avec les changements de l'URL
  // (navigation depuis la sidebar ou bouton précédent)
  React.useEffect(() => {
    const s = searchParams.get("s") as Section | null;
    if (s && s !== section) {
      setSection(s);
      setView("liste");
    }
  }, [searchParams]);

  const changeSection = (s: Section) => {
    setSection(s);
    setView("liste");
    setSearchParams({ s }, { replace: true });
  };

  return (
    <AppShell>
      <ViewShell title={t("configuration.title")} subtitle={t("configuration.subtitle")}>
        <div className="min-w-0">
          {section === "users" && <UsersSection view={view} setView={setView} />}
          {section === "orga" && <OrganigrammeSection />}
          {section === "permissions" && <PermissionsSection />}
          {section === "roles" && <RolesSection />}
          {section === "groupes" && <GroupesSection />}
          {section === "categories" && <CategoriesBienSection />}
          {section === "types" && <AssetTypesSection />}
          {section === "subtypes" && <AssetSubtypesSection />}
          {section === "cartographie" && <CartographieSection />}
          {section === "projets" && <ProjetsSection />}
          {section === "etatBiens" && <EtatBiensSection />}
          {section === "champs" && <ChampsSection />}
          {section === "exitTypes" && <ExitTypesSection />}
          {section === "securisations" && <SecurisationsSection />}
          {section === "notifications" && <NotificationsSection />}
          {section === "logs" && <LogsSection />}
        </div>
      </ViewShell>
    </AppShell>
  );
}

function UsersSection({ view, setView }: { view: SectionView; setView: (v: SectionView) => void }) {
  const t = useT();
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState<ApiUser | null>(null);
  const [resetTarget, setResetTarget] = useState<ApiUser | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiUser | null>(null);
  const [logicalDeleteTarget, setLogicalDeleteTarget] = useState<ApiUser | null>(null);
  // Recommandation 90 — fiche détenteur (biens actuellement affectés à un utilisateur).
  const [detenteurTarget, setDetenteurTarget] = useState<ApiUser | null>(null);
  // Filtre statut : "all" / "active" / "inactive" — filtre d'affichage
  // classique (is_active), indépendant de la corbeille. Filtre service : id
  // exact (API service_id). Filtre 2FA : pas encore de paramètre côté API —
  // filtré côté client.
  const [statusFilter, setStatusFilter] = useState<"all" | "active" | "inactive">("active");
  const [serviceFilterId, setServiceFilterId] = useState<number | null>(null);
  const [serviceFilterLabel, setServiceFilterLabel] = useState("");
  const [twoFactorFilter, setTwoFactorFilter] = useState<"all" | "on" | "off">("all");
  // Corbeille — toggle dédié comme sur les autres pages d'administration,
  // utilisant le paramètre is_dlet (alias de is_active dédié aux
  // utilisateurs supprimés logiquement). Prioritaire sur le filtre Statut.
  const [showDeletedUsers, setShowDeletedUsers] = useState(false);

  // ── Chargement des utilisateurs (filtres statut + service côté API) ────
  // limit=1000 : récupère la totalité ; DataTable gère la pagination côté client (10/page)
  const { data, isLoading, isError } = useQuery({
    queryKey: ["users", statusFilter, serviceFilterId, showDeletedUsers],
    queryFn: () =>
      listUsers(
        showDeletedUsers
          ? { page: 1, limit: 1000, is_dlet: true, service_id: serviceFilterId ?? undefined }
          : {
              page: 1,
              limit: 1000,
              is_active: statusFilter === "all" ? undefined : statusFilter === "active",
              service_id: serviceFilterId ?? undefined,
            },
      ),
  });

  // Tri par défaut : plus récent d'abord (id desc), puis alphabétique sur le nom,
  // puis filtre 2FA côté client (pas de paramètre API disponible pour l'instant).
  const users: ApiUser[] = [...(data?.data?.data ?? [])]
    .filter((u) =>
      twoFactorFilter === "all" ? true : twoFactorFilter === "on" ? !!u.twoFactorEnabled : !u.twoFactorEnabled,
    )
    .sort((a, b) => {
      if (b.id !== a.id) return b.id - a.id;
      return `${a.lastName} ${a.firstName}`.localeCompare(`${b.lastName} ${b.firstName}`, "fr");
    });

  const reportUserError = (err: unknown) => {
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
    toast.error(msg ?? t("toast.error"));
  };

  // PUT /users/{id} — un payload partiel (juste is_active ou juste
  // twoFactorEnabled) ne suffit pas à faire persister le changement sur ce
  // backend (même famille de problème déjà rencontrée sur d'autres endpoints
  // PUT/PATCH de ce projet) : on renvoie tous les champs déjà connus de
  // l'utilisateur en plus du champ modifié, par sécurité.
  //
  // ⚠️ granted_permission_ids / revoked_permission_ids doivent AUSSI être
  // présents (sinon warning PHP "Undefined array key" — confirmé en direct)
  // ET refléter l'état réel actuel, jamais un tableau vide au hasard : ces
  // deux champs sont un remplacement complet côté backend (confirmé sur
  // l'écran Groupes > membres), donc envoyer [] effacerait les permissions
  // individuelles réelles de la personne. La liste des utilisateurs
  // (listUsers) ne les inclut pas — on va les rechercher juste avant chaque
  // changement de statut/2FA via GET /users/{id}/permissions.
  const fullUserPayload = (u: ApiUser, granted: number[], revoked: number[]) => ({
    firstName: u.firstName,
    lastName: u.lastName,
    email: u.email,
    matricule: u.matricule ?? undefined,
    cni: u.cni ?? undefined,
    is_active: u.is_active,
    twoFactorEnabled: u.twoFactorEnabled,
    service_id: u.service?.id ?? null,
    role_ids: (u.assignedRoles ?? []).map((r) => r.id),
    granted_permission_ids: granted,
    revoked_permission_ids: revoked,
  });

  const fetchCurrentPermissionIds = async (userId: number) => {
    const res = await getUserPermissions(userId);
    return {
      granted: (res.data?.granted ?? []).map((p) => p.id),
      revoked: (res.data?.revoked ?? []).map((p) => p.id),
    };
  };

  // ── Mutation : activer / désactiver ────────────────────────────────────
  const toggleMutation = useMutation({
    mutationFn: async ({ user, is_active }: { user: ApiUser; is_active: boolean }) => {
      const { granted, revoked } = await fetchCurrentPermissionIds(user.id);
      return updateUser(user.id, { ...fullUserPayload(user, granted, revoked), is_active });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["users"] }),
    onError: reportUserError,
  });

  // ── Mutation : toggle 2FA ──────────────────────────────────────────────
  const toggle2FAMutation = useMutation({
    mutationFn: async ({ user, twoFactorEnabled }: { user: ApiUser; twoFactorEnabled: boolean }) => {
      const { granted, revoked } = await fetchCurrentPermissionIds(user.id);
      return updateUser(user.id, { ...fullUserPayload(user, granted, revoked), twoFactorEnabled });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["users"] }),
    onError: reportUserError,
  });

  // ── Mutation : suppression logique (DELETE /users/{id} — désactive) ────
  const logicalDeleteMutation = useMutation({
    mutationFn: (id: number) => deleteUser(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success(t("toast.deleted"));
    },
    onError: reportUserError,
  });

  // ── Mutation : suppression définitive (DELETE /users/{id}/force) ───────
  const deleteMutation = useMutation({
    mutationFn: (id: number) => forceDeleteUser(id),
    // Retrait optimiste — la ligne disparaît immédiatement au lieu d'attendre
    // le round-trip DELETE puis le refetch complet de la liste (limit=1000),
    // qui donnait l'impression que la suppression "durait trop".
    onMutate: async (id: number) => {
      await queryClient.cancelQueries({ queryKey: ["users"] });
      const previous = queryClient.getQueryData<{ data: { data: ApiUser[] } }>(["users"]);
      queryClient.setQueryData<{ data: { data: ApiUser[] } } | undefined>(["users"], (old) =>
        old ? { ...old, data: { ...old.data, data: old.data.data.filter((u) => u.id !== id) } } : old,
      );
      return { previous };
    },
    onSuccess: () => {
      toast.success(t("toast.deleted"));
    },
    onError: (err, _id, context) => {
      if (context?.previous) queryClient.setQueryData(["users"], context.previous);
      reportUserError(err);
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["users"] }),
  });

  const columns: Column<ApiUser>[] = [
    {
      key: "nom",
      label: t("common.name"),
      render: (u) => (
        <span className="font-medium">
          {u.firstName} {u.lastName}
        </span>
      ),
      sortValue: (u) => `${u.lastName} ${u.firstName}`,
    },
    { key: "email", label: t("common.email") },
    {
      key: "matricule",
      label: t("common.matricule"),
      render: (u) => <span className="text-xs">{u.matricule ?? "—"}</span>,
    },
    {
      key: "service",
      label: t("users.service"),
      render: (u) => <span className="text-xs">{u.service?.nom ?? "—"}</span>,
      sortValue: (u) => u.service?.nom ?? "",
    },
    {
      key: "roles",
      label: t("users.roles"),
      render: (u) => {
        const roles = u.assignedRoles ?? [];
        if (roles.length === 0) {
          return <span className="text-xs text-muted-foreground">{t("users.noRole")}</span>;
        }
        return (
          <div className="flex flex-wrap gap-1">
            {roles.map((r) => (
              <Badge key={r.id} variant="secondary" className="text-xs">
                {r.nom}
              </Badge>
            ))}
          </div>
        );
      },
      exportFormat: (u) =>
        (u.assignedRoles ?? []).map((r) => r.nom).join(", ") || t("users.noRole"),
    },
    {
      key: "is_active",
      label: t("common.status"),
      render: (u) => (
        <label className="flex cursor-pointer items-center gap-2">
          <Switch
            checked={u.is_active}
            disabled={toggleMutation.isPending}
            onCheckedChange={(checked) => toggleMutation.mutate({ user: u, is_active: checked })}
          />
          <span className="text-xs font-medium">{u.is_active ? t("status.active") : t("common.inactive")}</span>
        </label>
      ),
      exportFormat: (u) => (u.is_active ? t("status.active") : t("common.inactive")),
    },
    {
      key: "twoFactorEnabled",
      label: t("users.field.twoFactorShort"),
      render: (u) => (
        <label className="flex cursor-pointer items-center gap-2">
          <Switch
            checked={u.twoFactorEnabled ?? false}
            disabled={toggle2FAMutation.isPending}
            onCheckedChange={(checked) =>
              toggle2FAMutation.mutate({ user: u, twoFactorEnabled: checked })
            }
          />
          <span className="text-xs font-medium">
            {u.twoFactorEnabled ? t("profile.twoFactor.on") : t("profile.twoFactor.off")}
          </span>
        </label>
      ),
      exportFormat: (u) =>
        u.twoFactorEnabled ? t("profile.twoFactor.on") : t("profile.twoFactor.off"),
    },
  ];

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("users.loading")}</span>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-40 items-center justify-center text-destructive">
        {t("users.loadError")}
      </div>
    );
  }

  // ── Vue réinitialisation mot de passe ──────────────────────────────────
  if (resetTarget) {
    return (
      <ResetPasswordForm
        user={resetTarget}
        onCancel={() => setResetTarget(null)}
        onSaved={() => setResetTarget(null)}
      />
    );
  }

  if (view === "liste") {
    return (
      <>
        <div>
          <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-lg font-semibold">{t("config.users")}</h2>
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("creation");
              }}
            >
              <Plus className="h-4 w-4" /> {t("action.add")}
            </Button>
          </div>

          <div className="mb-4 flex flex-wrap items-end gap-3">
            <div className="w-64 space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("users.service")}</Label>
              <OrgTreeSelect
                value={serviceFilterId}
                valueLabel={serviceFilterLabel}
                onSelect={(node) => { setServiceFilterId(node.id); setServiceFilterLabel(node.nom); }}
                onClear={() => { setServiceFilterId(null); setServiceFilterLabel(""); }}
                selectAnyNode
                placeholder={t("users.filter.allServices")}
                searchPlaceholder={t("users.filter.searchService")}
              />
            </div>
            <div className="w-44 space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("common.status")}</Label>
              <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v as typeof statusFilter)} disabled={showDeletedUsers}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t("common.all")}</SelectItem>
                  <SelectItem value="active">{t("users.filter.activeUsers")}</SelectItem>
                  <SelectItem value="inactive">{t("users.filter.inactiveUsers")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="w-44 space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("users.field.twoFactorFull")}</Label>
              <Select value={twoFactorFilter} onValueChange={(v) => setTwoFactorFilter(v as typeof twoFactorFilter)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t("users.filter.allTwoFactor")}</SelectItem>
                  <SelectItem value="on">{t("profile.twoFactor.on")}</SelectItem>
                  <SelectItem value="off">{t("profile.twoFactor.off")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <label className="flex cursor-pointer items-center gap-2 pb-2 text-sm">
              <Switch checked={showDeletedUsers} onCheckedChange={setShowDeletedUsers} />
              {t("common.showDeleted")}
            </label>
          </div>

          <DataTable
            data={users}
            columns={columns}
            getRowId={(u) => String(u.id)}
            exportFilename="utilisateurs-minepia"
            exportTitle="MINEPIA — Utilisateurs"
            searchKeys={["firstName", "lastName", "email"]}
            rowActions={(u) => (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    type="button"
                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                    aria-label={t("common.actions")}
                  >
                    <MoreVertical className="h-4 w-4" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  {u.is_active ? (
                    <>
                      <DropdownMenuItem
                        onSelect={() => {
                          setSelected(u);
                          setView("edition");
                        }}
                      >
                        <Pencil className="mr-2 h-4 w-4" /> {t("action.edit")}
                      </DropdownMenuItem>
                      <DropdownMenuItem onSelect={() => setDetenteurTarget(u)}>
                        <User className="mr-2 h-4 w-4" /> {t("users.action.ficheDetenteur")}
                      </DropdownMenuItem>
                      <DropdownMenuItem onSelect={() => setResetTarget(u)}>
                        <KeyRound className="mr-2 h-4 w-4" /> {t("users.action.resetPassword")}
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className="text-destructive"
                        onSelect={() => setLogicalDeleteTarget(u)}
                      >
                        <Trash2 className="mr-2 h-4 w-4" /> {t("users.action.softDelete")}
                      </DropdownMenuItem>
                    </>
                  ) : (
                    <>
                      <DropdownMenuItem onSelect={() => toggleMutation.mutate({ user: u, is_active: true })}>
                        <RotateCcw className="mr-2 h-4 w-4" /> {t("users.action.reactivate")}
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className="text-destructive"
                        onSelect={() => setDeleteTarget(u)}
                      >
                        <Trash2 className="mr-2 h-4 w-4" /> {t("common.permanentDelete")}
                      </DropdownMenuItem>
                    </>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          />
        </div>

        {/* Recommandation 90 — fiche détenteur */}
        <FicheDetenteurDialog
          userId={detenteurTarget?.id ?? null}
          userName={
            detenteurTarget ? `${detenteurTarget.firstName} ${detenteurTarget.lastName}` : ""
          }
          open={detenteurTarget !== null}
          onClose={() => setDetenteurTarget(null)}
        />

        {/* ── Dialog de confirmation de suppression ──────────────────────── */}
        <AlertDialog
          open={deleteTarget !== null}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null);
          }}
        >
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t("common.permanentDelete")}</AlertDialogTitle>
              <AlertDialogDescription>
                {t("users.delete.desc.before")}{" "}
                <span className="font-semibold text-foreground">
                  {deleteTarget?.firstName} {deleteTarget?.lastName}
                </span>{" "}
                ({deleteTarget?.email}) {t("users.delete.desc.after")}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={() => {
                  if (deleteTarget) {
                    deleteMutation.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                  }
                }}
              >
                {t("action.delete")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        {/* ── Dialog de confirmation de suppression logique ──────────────── */}
        <AlertDialog
          open={logicalDeleteTarget !== null}
          onOpenChange={(open) => {
            if (!open) setLogicalDeleteTarget(null);
          }}
        >
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t("users.action.softDelete")}</AlertDialogTitle>
              <AlertDialogDescription>
                <span className="font-semibold text-foreground">
                  {logicalDeleteTarget?.firstName} {logicalDeleteTarget?.lastName}
                </span>{" "}
                ({logicalDeleteTarget?.email}) {t("users.logicalDelete.desc.after")}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={() => {
                  if (logicalDeleteTarget) {
                    logicalDeleteMutation.mutate(logicalDeleteTarget.id);
                    setLogicalDeleteTarget(null);
                  }
                }}
              >
                {t("action.delete")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </>
    );
  }

  return (
    <UserForm
      initial={view === "edition" ? selected : null}
      onCancel={() => setView("liste")}
      onSaved={() => {
        queryClient.invalidateQueries({ queryKey: ["users"] });
        toast.success(t("toast.saved"));
        setView("liste");
      }}
    />
  );
}

/**
 * Extrait un message d'erreur lisible depuis une réponse d'échec de
 * validation. Sur POST/PUT /users, le backend laisse parfois remonter le
 * format RFC7807 natif de Symfony (`ValidationFailedException` non
 * intercepté) au lieu de l'enveloppe {success,status,message,data} habituelle
 * de l'API — sous la forme `data: {type, title, detail, violations:[...]}`.
 * Object.values(data)[0] tombait alors sur `type` (l'URL
 * "https://symfony.com/errors/validation"), pas sur le vrai message. On
 * cherche ici en priorité `violations[].propertyPath/title`, puis `detail`.
 */
function extractApiValidationMessage(err: unknown): string | undefined {
  const res = (
    err as {
      response?: {
        data?: {
          message?: string;
          detail?: string;
          errors?: Record<string, string[]>;
          data?: unknown;
        };
      };
    }
  )?.response;

  const data = res?.data?.data;
  if (data && typeof data === "object") {
    const d = data as { violations?: { propertyPath?: string; title?: string; message?: string }[]; detail?: string };
    if (Array.isArray(d.violations) && d.violations.length > 0) {
      return d.violations
        .map((v) => [v.propertyPath, v.title ?? v.message].filter(Boolean).join(": "))
        .join(" — ");
    }
    if (typeof d.detail === "string" && d.detail && !d.detail.startsWith("http")) {
      return d.detail;
    }
  }

  if (res?.data?.errors) {
    const first = Object.values(res.data.errors).flat()[0];
    if (typeof first === "string" && !first.startsWith("http")) return first;
  }

  if (res?.data?.detail && !res.data.detail.startsWith("http")) return res.data.detail;
  if (res?.data?.message && !res.data.message.startsWith("http")) return res.data.message;

  return undefined;
}

function UserForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiUser | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();

  const [firstName, setFirstName] = useState(initial?.firstName ?? "");
  const [lastName, setLastName] = useState(initial?.lastName ?? "");
  const [email, setEmail] = useState(initial?.email ?? "");
  const [matricule, setMatricule] = useState(initial?.matricule ?? "");
  const [cni, setCni] = useState(initial?.cni ?? "");
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(initial?.twoFactorEnabled ?? false);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [pwdError, setPwdError] = useState<string | null>(null);
  const [selectedRoleIds, setSelectedRoleIds] = useState<Set<number>>(
    new Set(initial?.assignedRoles?.map((r) => r.id) ?? []),
  );
  const [roleSearch, setRoleSearch] = useState("");
  const [rolePopoverOpen, setRolePopoverOpen] = useState(false);
  const [serviceId, setServiceId] = useState<string>(
    initial?.service?.id ? String(initial.service.id) : "",
  );
  const [serviceLabel, setServiceLabel] = useState(initial?.service?.nom ?? "");

  // Chargement des rôles disponibles
  const { data: rolesData } = useQuery({
    queryKey: ["roles"],
    queryFn: () => listRoles({ page: 1, limit: 1000 }),
  });
  const availableRoles: ApiRole[] = [...(rolesData?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );
  const filteredRoles = availableRoles.filter(
    (r) =>
      r.nom.toLowerCase().includes(roleSearch.toLowerCase()) ||
      r.description?.toLowerCase().includes(roleSearch.toLowerCase()),
  );

  const toggleRole = (id: number) => {
    setSelectedRoleIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  // ── Mutation création ──────────────────────────────────────────────────
  const createMutation = useMutation({
    mutationFn: async (payload: CreateUserPayload) => {
      const res = await createUser(payload);
      // Le POST ignore silencieusement twoFactorEnabled=true (la réponse le
      // renvoie toujours à false, confirmé en direct) — contrairement au PUT,
      // qui lui le respecte. On le repasse donc via un PUT juste après la
      // création si l'utilisateur l'a demandé activé.
      if (payload.twoFactorEnabled && res.data) {
        const created = res.data;
        await updateUser(created.id, {
          firstName: created.firstName,
          lastName: created.lastName,
          email: created.email,
          matricule: created.matricule ?? undefined,
          cni: created.cni ?? undefined,
          is_active: created.is_active,
          twoFactorEnabled: true,
          service_id: created.service?.id ?? null,
          role_ids: (created.assignedRoles ?? []).map((r) => r.id),
          granted_permission_ids: [],
          revoked_permission_ids: [],
        });
      }
      return res;
    },
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const msg = extractApiValidationMessage(err);
      toast.error(
        msg ? t("users.form.validationError", { message: msg }) : t("users.form.createError"),
        { duration: 10000 },
      );
    },
  });

  // ── Mutation édition ───────────────────────────────────────────────────
  // ⚠️ granted_permission_ids / revoked_permission_ids sont un remplacement
  // complet côté backend (confirmé en direct) — ce formulaire envoyait []
  // pour les deux à chaque sauvegarde, ce qui effaçait silencieusement
  // toute permission individuelle déjà accordée/révoquée pour la personne,
  // même pour une simple modification de nom/email/service. On récupère
  // l'état réel juste avant d'envoyer, comme pour les toggles statut/2FA.
  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: UpdateUserPayload }) => {
      const current = await getUserPermissions(id);
      return updateUser(id, {
        ...payload,
        granted_permission_ids: (current.data?.granted ?? []).map((p) => p.id),
        revoked_permission_ids: (current.data?.revoked ?? []).map((p) => p.id),
      });
    },
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const msg = extractApiValidationMessage(err);
      toast.error(
        msg ? t("users.form.validationError", { message: msg }) : t("users.form.updateError"),
        { duration: 10000 },
      );
    },
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!firstName.trim() || !lastName.trim() || !email.trim()) return;

    if (!initial) {
      // Création
      if (!serviceId) {
        toast.error(t("users.form.posteRequired"));
        return;
      }
      if (password.length < 6) {
        setPwdError(t("users.form.passwordMinLength"));
        return;
      }
      if (password !== confirm) {
        setPwdError(t("users.form.passwordMismatch"));
        return;
      }
      setPwdError(null);
      createMutation.mutate({
        firstName,
        lastName,
        email,
        password,
        matricule: matricule || undefined,
        cni: cni || undefined,
        is_active: isActive,
        twoFactorEnabled,
        service_id: serviceId ? Number(serviceId) : undefined,
        // Omis (pas []) si aucun rôle sélectionné — le backend assigne alors
        // automatiquement le rôle "Utilisateur" par défaut. Envoyer un
        // tableau vide explicite désactivait ce comportement côté backend et
        // créait des utilisateurs sans aucun rôle.
        role_ids: selectedRoleIds.size > 0 ? [...selectedRoleIds] : undefined,
        // Le backend attend ces clés même vides (cf. payload de référence
        // du swagger) — leur absence a provoqué un 400 côté création.
        group_ids: [],
        granted_permission_ids: [],
        revoked_permission_ids: [],
      });
    } else {
      // Édition
      setPwdError(null);
      updateMutation.mutate({
        id: initial.id,
        payload: {
          firstName,
          lastName,
          email,
          matricule: matricule || undefined,
          cni: cni || undefined,
          is_active: isActive,
          twoFactorEnabled,
          service_id: serviceId ? Number(serviceId) : null,
          role_ids: [...selectedRoleIds],
          // granted_permission_ids / revoked_permission_ids : injectés par
          // updateMutation lui-même à partir de l'état réel (voir sa
          // définition) — ne pas les hardcoder ici, ça effacerait les
          // permissions individuelles de la personne à chaque édition.
        },
      });
    }
  };

  return (
    <div className="mx-auto w-full max-w-3xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="gap-1 -ml-2">
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Users className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? t("users.form.editTitle") : t("users.form.createTitle")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("users.form.subtitle")}
            </p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>
                {t("users.field.firstName")} <span className="text-destructive">*</span>
              </Label>
              <Input
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                placeholder="Jean"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>
                {t("common.name")} <span className="text-destructive">*</span>
              </Label>
              <Input
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                placeholder="DUPONT"
                required
              />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>
                {t("common.email")} <span className="text-destructive">*</span>
              </Label>
              <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="jean.dupont@minepia.cm"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("common.matricule")}</Label>
              <Input
                value={matricule}
                onChange={(e) => setMatricule(e.target.value)}
                placeholder="Ex : MAT-2026-0001"
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("users.field.cni")}</Label>
              <Input
                value={cni}
                onChange={(e) => setCni(e.target.value)}
                placeholder="Ex : 123456789"
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("common.status")}</Label>
              <Select
                value={isActive ? "Activé" : "Désactivé"}
                onValueChange={(v) => setIsActive(v === "Activé")}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Activé">{t("status.active")}</SelectItem>
                  <SelectItem value="Désactivé">{t("common.inactive")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>
                {t("users.field.poste")}{!initial && <span className="text-destructive"> *</span>}
              </Label>
              <OrgTreeSelect
                value={serviceId ? Number(serviceId) : null}
                valueLabel={serviceLabel}
                onSelect={(node) => { setServiceId(String(node.id)); setServiceLabel(node.nom); }}
                onClear={() => { setServiceId(""); setServiceLabel(""); }}
                selectableType="Poste"
                placeholder={t("users.field.poste.none")}
                searchPlaceholder={t("users.field.poste.search")}
              />
            </div>
          </div>

          {/* ── Toggle 2FA ── */}
          <div className="mt-4 rounded-xl border border-border bg-muted/20 p-4">
            <div className="flex items-start gap-3">
              <Switch
                id="user-2fa"
                checked={twoFactorEnabled}
                onCheckedChange={setTwoFactorEnabled}
                className="mt-0.5"
              />
              <div className="min-w-0">
                <Label htmlFor="user-2fa" className="cursor-pointer">
                  {t("users.twoFactor")}
                </Label>
                <p className="mt-0.5 text-xs text-muted-foreground">{t("users.twoFactor.help")}</p>
              </div>
            </div>
          </div>

          {!initial && (
            <div className="mt-6 border-t border-border pt-5">
              <div className="mb-3">
                <h3 className="text-sm font-semibold text-foreground">{t("users.form.security")}</h3>
                <p className="text-xs text-muted-foreground">
                  {t("users.form.securitySubtitle")}
                </p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>
                    {t("users.field.password")} <span className="text-destructive">*</span>
                  </Label>
                  <div className="relative">
                    <Input
                      type={showPwd ? "text" : "password"}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="pr-10"
                      autoComplete="new-password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPwd((v) => !v)}
                      aria-label={showPwd ? t("common.hide") : t("common.show")}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label>
                    {t("users.field.confirmPassword")} <span className="text-destructive">*</span>
                  </Label>
                  <div className="relative">
                    <Input
                      type={showConfirm ? "text" : "password"}
                      value={confirm}
                      onChange={(e) => setConfirm(e.target.value)}
                      className="pr-10"
                      autoComplete="new-password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirm((v) => !v)}
                      aria-label={showConfirm ? t("common.hide") : t("common.show")}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>
              {pwdError && <p className="mt-3 text-xs font-medium text-destructive">{pwdError}</p>}
            </div>
          )}

          {/* ── Sélection des rôles ── */}
          <div className="mt-6 border-t border-border pt-5">
            <div className="mb-3">
              <h3 className="text-sm font-semibold text-foreground">{t("users.roles")}</h3>
              <p className="text-xs text-muted-foreground">{t("users.selectRoles")}</p>
            </div>

            {/* Tags des rôles sélectionnés */}
            {selectedRoleIds.size > 0 && (
              <div className="mb-2 flex flex-wrap gap-1.5">
                {availableRoles
                  .filter((r) => selectedRoleIds.has(r.id))
                  .map((r) => (
                    <Badge key={r.id} variant="secondary" className="gap-1 pr-1">
                      {r.nom}
                      <button
                        type="button"
                        onClick={() => toggleRole(r.id)}
                        className="ml-0.5 flex h-4 w-4 items-center justify-center rounded hover:bg-muted-foreground/20"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </Badge>
                  ))}
              </div>
            )}

            <Popover open={rolePopoverOpen} onOpenChange={setRolePopoverOpen}>
              <PopoverTrigger asChild>
                <Button type="button" variant="outline" className="w-full justify-between gap-2">
                  <span className="text-muted-foreground">{t("users.selectRoles")}</span>
                  <ChevronDown className="h-4 w-4 shrink-0 opacity-50" />
                </Button>
              </PopoverTrigger>
              <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                <div className="border-b border-border p-2">
                  <Input
                    placeholder={t("action.search")}
                    value={roleSearch}
                    onChange={(e) => setRoleSearch(e.target.value)}
                    className="h-8"
                  />
                </div>
                <div className="max-h-52 overflow-y-auto p-1">
                  {filteredRoles.length === 0 ? (
                    <p className="py-4 text-center text-xs text-muted-foreground">
                      {t("common.empty")}
                    </p>
                  ) : (
                    filteredRoles.map((role) => (
                      <div
                        key={role.id}
                        role="option"
                        aria-selected={selectedRoleIds.has(role.id)}
                        onClick={() => toggleRole(role.id)}
                        className="flex w-full cursor-pointer items-center gap-2.5 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                      >
                        <Checkbox
                          checked={selectedRoleIds.has(role.id)}
                          className="pointer-events-none"
                        />
                        <div>
                          <span className="font-medium">{role.nom}</span>
                          {role.description && (
                            <span className="block text-xs text-muted-foreground">
                              {role.description}
                            </span>
                          )}
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </PopoverContent>
            </Popover>
          </div>
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}

function ResetPasswordForm({
  user,
  onCancel,
  onSaved,
}: {
  user: ApiUser;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [pwdError, setPwdError] = useState<string | null>(null);

  const resetMutation = useMutation({
    mutationFn: (payload: ResetUserPasswordPayload) => resetUserPassword(user.id, payload),
    onSuccess: () => {
      toast.success(t("users.resetPassword.success"));
      onSaved();
    },
    onError: () => toast.error(t("toast.error")),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPwdError(null);
    if (password.length < 6) {
      setPwdError(t("users.form.passwordMinLength"));
      return;
    }
    if (password !== confirm) {
      setPwdError(t("users.form.passwordMismatch"));
      return;
    }
    resetMutation.mutate({ password, passwordConfirm: confirm });
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
            <KeyRound className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {t("users.action.resetPassword")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("users.resetPassword.subtitle")}{" "}
              <span className="font-semibold text-foreground">
                {user.firstName} {user.lastName}
              </span>{" "}
              — {user.email}
            </p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>
                {t("users.field.newPassword")} <span className="text-destructive">*</span>
              </Label>
              <div className="relative">
                <Input
                  type={showPwd ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="pr-10"
                  autoComplete="new-password"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPwd((v) => !v)}
                  aria-label={showPwd ? t("common.hide") : t("common.show")}
                  className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                >
                  {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>
                {t("users.field.confirmPassword")} <span className="text-destructive">*</span>
              </Label>
              <div className="relative">
                <Input
                  type={showConfirm ? "text" : "password"}
                  value={confirm}
                  onChange={(e) => setConfirm(e.target.value)}
                  className="pr-10"
                  autoComplete="new-password"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowConfirm((v) => !v)}
                  aria-label={showConfirm ? t("common.hide") : t("common.show")}
                  className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                >
                  {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
          </div>
          {pwdError && <p className="mt-3 text-xs font-medium text-destructive">{pwdError}</p>}
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={resetMutation.isPending}
          >
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button
            type="submit"
            className="gap-2 bg-amber-600 hover:bg-amber-700"
            disabled={resetMutation.isPending}
          >
            {resetMutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <KeyRound className="h-4 w-4" />
            )}
            {t("action.reset")}
          </Button>
        </div>
      </form>
    </div>
  );
}

type CrudField = { key: string; label: string; type?: string; options?: string[] };
type CrudRow = { id: string } & Record<string, string | number>;

function SimpleCrudSection({
  title,
  initial,
  fields,
}: {
  title: string;
  initial: readonly CrudRow[] | CrudRow[];
  fields: CrudField[];
}) {
  const t = useT();
  const [rows, setRows] = useState<CrudRow[]>(initial as CrudRow[]);
  const [view, setView] = useState<SectionView>("liste");
  const [selected, setSelected] = useState<CrudRow | null>(null);

  const columns: Column<CrudRow>[] = fields.map((f) => ({
    key: f.key,
    label: f.label,
    render:
      f.key === fields[0].key ? (r) => <span className="font-medium">{r[f.key]}</span> : undefined,
  }));

  if (view === "liste") {
    return (
      <div>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold">{title}</h2>
          <Button
            className="gap-2"
            onClick={() => {
              setSelected(null);
              setView("creation");
            }}
          >
            <Plus className="h-4 w-4" /> {t("action.add")}
          </Button>
        </div>
        <DataTable
          data={rows}
          columns={columns}
          getRowId={(r) => r.id}
          exportFilename={title.toLowerCase().replace(/\s+/g, "-")}
          exportTitle={`MINEPIA — ${title}`}
          rowActions={(r) => (
            <>
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => {
                  setSelected(r);
                  setView("edition");
                }}
              />
              <RowIconButton
                icon={Trash2}
                label={t("action.delete")}
                tone="danger"
                onClick={() => {
                  setRows((p) => p.filter((x) => x.id !== r.id));
                  toast.success(t("toast.deleted"));
                }}
              />
            </>
          )}
        />
      </div>
    );
  }

  return (
    <CrudForm
      title={title}
      fields={fields}
      initial={view === "edition" ? selected : null}
      onCancel={() => setView("liste")}
      onSave={(row) => {
        setRows((prev) =>
          view === "creation" ? [row, ...prev] : prev.map((x) => (x.id === row.id ? row : x)),
        );
        toast.success(t("toast.saved"));
        setView("liste");
      }}
    />
  );
}

function CrudForm({
  title,
  fields,
  initial,
  onCancel,
  onSave,
}: {
  title: string;
  fields: CrudField[];
  initial: CrudRow | null;
  onCancel: () => void;
  onSave: (row: CrudRow) => void;
}) {
  const t = useT();
  const [form, setForm] = useState<CrudRow>(
    initial ??
      ({
        id: `R-${Date.now().toString().slice(-5)}`,
        ...Object.fromEntries(fields.map((f) => [f.key, ""])),
      } as CrudRow),
  );
  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="gap-1 -ml-2">
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          onSave(form);
        }}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            {initial ? <Pencil className="h-5 w-5" /> : <Plus className="h-5 w-5" />}
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `Modifier — ${title}` : `Nouveau — ${title}`}
            </h2>
            <p className="text-xs text-muted-foreground">Renseignez les informations ci-dessous.</p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            {fields.map((f) => (
              <div key={f.key} className="space-y-1.5">
                <Label>{f.label}</Label>
                {f.options ? (
                  <Select
                    value={String(form[f.key] ?? f.options[0])}
                    onValueChange={(v) => setForm({ ...form, [f.key]: v })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {f.options.map((o) => (
                        <SelectItem key={o} value={o}>
                          {o}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : (
                  <Input
                    type={f.type ?? "text"}
                    value={String(form[f.key] ?? "")}
                    onChange={(e) =>
                      setForm({
                        ...form,
                        [f.key]: f.type === "number" ? Number(e.target.value) : e.target.value,
                      })
                    }
                  />
                )}
              </div>
            ))}
          </div>
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} className="gap-2">
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2">
            <Save className="h-4 w-4" /> {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}

export default ConfigShell;
