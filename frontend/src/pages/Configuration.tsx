import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Users,
  Network,
  ShieldCheck,
  UserCog,
  Box,
  FolderOpen,
  Briefcase,
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
  type LucideIcon,
} from "lucide-react";
import { Switch } from "@/components/ui/switch";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/utils/utils";
import {
  listUsers,
  createUser,
  updateUser,
  deleteUser,
  resetUserPassword,
  type ApiUser,
  type CreateUserPayload,
  type UpdateUserPayload,
  type ResetUserPasswordPayload,
} from "@/api/users/users.api";

type Section =
  | "orga"
  | "permissions"
  | "roles"
  | "users"
  | "types"
  | "categories"
  | "projets";

type SectionView = "liste" | "creation" | "edition";

const sections: { key: Section; labelKey: string; icon: LucideIcon }[] = [
  { key: "orga", labelKey: "config.orga", icon: Network },
  { key: "permissions", labelKey: "config.permissions", icon: ShieldCheck },
  { key: "roles", labelKey: "config.roles", icon: UserCog },
  { key: "users", labelKey: "config.users", icon: Users },
  { key: "types", labelKey: "config.types", icon: Box },
  { key: "categories", labelKey: "config.categories", icon: FolderOpen },
  { key: "projets", labelKey: "config.projets", icon: Briefcase },
];

function ConfigShell() {
  const t = useT();
  const [section, setSection] = useState<Section>("users");
  const [view, setView] = useState<SectionView>("liste");

  const changeSection = (s: Section) => {
    setSection(s);
    setView("liste");
  };

  const catBiens = ["Véhicules", "Bâtiments", "Matériel informatique", "Mobilier", "Matériel de bureau", "Matériel technique"];

  return (
    <AppShell>
      <ViewShell title={t("configuration.title")} subtitle={t("configuration.subtitle")}>
        <div className="space-y-4 sm:space-y-6">
          <nav className="flex justify-center gap-2 overflow-x-auto pb-1 sm:gap-3">
            {sections.map((s) => {
              const Icon = s.icon;
              const active = section === s.key;
              return (
                <button
                  key={s.key}
                  type="button"
                  onClick={() => changeSection(s.key)}
                  className={cn(
                    "inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold transition-colors sm:px-4",
                    active
                      ? "border-primary bg-primary/5 text-primary shadow-sm ring-1 ring-primary/20"
                      : "border-border bg-card text-foreground hover:bg-muted",
                  )}
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  <span className="whitespace-nowrap">{t(s.labelKey as never)}</span>
                </button>
              );
            })}
          </nav>

          <div className="min-w-0">
            {section === "users" && <UsersSection view={view} setView={setView} />}
            {section === "orga" && (
              <SimpleCrudSection
                title={t("config.orga")}
                initial={[
                  { id: "s1", nom: "Direction Générale", code: "DG", type: "Service", parent: "-", ordre: 1, statut: "Actif" },
                  { id: "s2", nom: "Direction des Moyens Généraux", code: "DMG", type: "Service", parent: "Direction Générale", ordre: 2, statut: "Actif" },
                  { id: "s3", nom: "Service Logistique", code: "SL", type: "Poste", parent: "Direction des Moyens Généraux", ordre: 1, statut: "Actif" },
                  { id: "s4", nom: "Bureau Transport", code: "BT", type: "Poste", parent: "Service Logistique", ordre: 1, statut: "Actif" },
                  { id: "s5", nom: "Magasin Central", code: "MC", type: "Poste", parent: "Service Logistique", ordre: 2, statut: "Actif" },
                  { id: "s6", nom: "Service du Patrimoine", code: "SP", type: "Poste", parent: "Direction des Moyens Généraux", ordre: 3, statut: "Actif" },
                ]}
                fields={[
                  { key: "nom", label: "Nom du service" },
                  { key: "code", label: "Libellé / Code" },
                  { key: "type", label: "Type", options: ["Service", "Poste"] },
                  { key: "parent", label: "Service parent" },
                  { key: "ordre", label: "N° d'ordre", type: "number" },
                  { key: "statut", label: "Statut", options: ["Actif", "Inactif"] },
                ]}
              />
            )}
            {section === "permissions" && (
              <SimpleCrudSection
                title={t("config.permissions")}
                initial={[
                  { id: "p1", nom: "archived", description: "Permet d'archiver le courrier" },
                  { id: "p2", nom: "assign_permission_to_role", description: "Permet d'assigner des permissions à un rôle" },
                  { id: "p3", nom: "classer_transmission", description: "Classer une transmission" },
                  { id: "p4", nom: "cloturer_courrier_depart", description: "Permet de clôturer un courrier au départ" },
                  { id: "p5", nom: "create_categorie", description: "Créer une nouvelle catégorie" },
                  { id: "p6", nom: "create_correspondant", description: "Créer un nouveau correspondant" },
                  { id: "p7", nom: "gerer_biens", description: "Permet de gérer les biens du parc" },
                  { id: "p8", nom: "voir_rapports", description: "Permet de consulter les rapports" },
                ]}
                fields={[
                  { key: "nom", label: "Nom de la permission" },
                  { key: "description", label: "Description" },
                ]}
              />
            )}
            {section === "roles" && (
              <SimpleCrudSection
                title={t("config.roles")}
                initial={[
                  { id: "r1", nom: "Administrateur", description: "Accès complet à toutes les fonctionnalités du système", statut: "Actif" },
                  { id: "r2", nom: "Gestionnaire de Biens", description: "Gestion des biens, structures, affectations et historiques", statut: "Actif" },
                  { id: "r3", nom: "Responsable Structure", description: "Gestion des biens et affectations de sa structure", statut: "Actif" },
                  { id: "r4", nom: "Agent Logistique", description: "Enregistrement des biens et gestion des affectations", statut: "Actif" },
                  { id: "r5", nom: "Consultation", description: "Consultation des informations sans modification", statut: "Actif" },
                  { id: "r6", nom: "Auditeur", description: "Accès en lecture aux journaux et rapports d'audit", statut: "Actif" },
                ]}
                fields={[
                  { key: "nom", label: "Nom du rôle" },
                  { key: "description", label: "Description" },
                  { key: "statut", label: "Statut", options: ["Actif", "Inactif"] },
                ]}
              />
            )}
            {section === "categories" && (
              <SimpleCrudSection
                title={t("config.categories")}
                initial={catBiens.map((c, i) => ({
                  id: `c${i + 1}`,
                  nom: c,
                  description: `Regroupe les biens de la catégorie ${c.toLowerCase()}.`,
                }))}
                fields={[
                  { key: "nom", label: "Nom de la catégorie" },
                  { key: "description", label: "Description" },
                ]}
              />
            )}
            {section === "types" && (
              <SimpleCrudSection
                title={t("config.types")}
                initial={[
                  { id: "t1", nom: "Véhicule léger", categorie: "Véhicules", description: "Véhicules destinés au transport de personnes avec un faible tonnage." },
                  { id: "t2", nom: "Véhicule utilitaire", categorie: "Véhicules", description: "Véhicules utilisés pour le transport de marchandises ou d'équipements." },
                  { id: "t3", nom: "Camion", categorie: "Véhicules", description: "Véhicules lourds utilisés pour le transport de charges importantes." },
                  { id: "t4", nom: "Moto", categorie: "Véhicules", description: "Motocycles à deux ou trois roues." },
                  { id: "t5", nom: "Bâtiment administratif", categorie: "Bâtiments", description: "Bâtiments utilisés pour les activités administratives." },
                  { id: "t6", nom: "Bâtiment technique", categorie: "Bâtiments", description: "Bâtiments destinés aux activités techniques et opérationnelles." },
                  { id: "t7", nom: "Ordinateur de bureau", categorie: "Matériel informatique", description: "Postes de travail fixes." },
                  { id: "t8", nom: "Imprimante", categorie: "Matériel informatique", description: "Périphérique d'impression." },
                ]}
                fields={[
                  { key: "nom", label: "Nom du type de bien" },
                  { key: "description", label: "Description" },
                ]}
              />
            )}
            {section === "projets" && (
              <SimpleCrudSection
                title={t("config.projets")}
                initial={[
                  { id: "pr1", nom: "Renouvellement des imprimantes et des ordinateurs", description: "Renouvellement du parc informatique (imprimantes et ordinateurs)", responsable: "Service Informatique", debut: "2025-05-15", fin: "2025-11-30", statut: "En cours" },
                  { id: "pr2", nom: "Acquisition de véhicules de service", description: "Acquisition de nouveaux véhicules pour les services régionaux", responsable: "Service Logistique", debut: "2025-04-01", fin: "2025-09-30", statut: "En cours" },
                  { id: "pr3", nom: "Réhabilitation des bâtiments administratifs", description: "Travaux de réhabilitation des bâtiments du siège", responsable: "Service des Infrastructures", debut: "2025-03-10", fin: "2025-08-30", statut: "Planifié" },
                  { id: "pr4", nom: "Digitalisation des archives", description: "Mise en place d'un système de gestion électronique des documents", responsable: "Service des Archives", debut: "2025-06-01", fin: "2025-12-31", statut: "Planifié" },
                  { id: "pr5", nom: "Aménagement du parking", description: "Aménagement et sécurisation du parking du siège", responsable: "Service Logistique", debut: "2025-05-20", fin: "2025-07-31", statut: "Terminé" },
                ]}
                fields={[
                  { key: "nom", label: "Nom du projet" },
                  { key: "description", label: "Description" },
                  { key: "responsable", label: "Responsable" },
                  { key: "debut", label: "Date de début", type: "date" },
                  { key: "fin", label: "Date de fin prévue", type: "date" },
                  { key: "statut", label: "Statut", options: ["Planifié", "En cours", "Terminé"] },
                ]}
              />
            )}
          </div>
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

  // ── Chargement de TOUS les utilisateurs en une seule requête ───────────
  // limit=1000 : récupère la totalité ; DataTable gère la pagination côté client (10/page)
  const { data, isLoading, isError } = useQuery({
    queryKey: ["users"],
    queryFn: () => listUsers({ page: 1, limit: 1000 }),
  });

  // Tri par défaut : plus récent d'abord (id desc), puis alphabétique sur le nom
  const users: ApiUser[] = [...(data?.data?.data ?? [])].sort((a, b) => {
    if (b.id !== a.id) return b.id - a.id;
    return `${a.lastName} ${a.firstName}`.localeCompare(`${b.lastName} ${b.firstName}`, "fr");
  });

  // ── Mutation : activer / désactiver ────────────────────────────────────
  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      updateUser(id, { is_active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["users"] }),
    onError: () => toast.error(t("toast.error")),
  });

  // ── Mutation : supprimer ───────────────────────────────────────────────
  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteUser(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success(t("toast.deleted"));
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiUser>[] = [
    {
      key: "nom",
      label: "Nom",
      render: (u) => (
        <span className="font-medium">{u.firstName} {u.lastName}</span>
      ),
      sortValue: (u) => `${u.lastName} ${u.firstName}`,
    },
    { key: "email", label: "Email" },
    {
      key: "service",
      label: "Service",
      render: (u) => <span className="text-xs">{u.service?.nom ?? "—"}</span>,
      sortValue: (u) => u.service?.nom ?? "",
    },
    {
      key: "is_active",
      label: "Statut",
      render: (u) => (
        <label className="flex cursor-pointer items-center gap-2">
          <Switch
            checked={u.is_active}
            onCheckedChange={(checked) =>
              toggleMutation.mutate({ id: u.id, is_active: checked })
            }
          />
          <span className="text-xs font-medium">
            {u.is_active ? "Activé" : "Désactivé"}
          </span>
        </label>
      ),
      exportFormat: (u) => (u.is_active ? "Activé" : "Désactivé"),
    },
  ];

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>Chargement des utilisateurs…</span>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-40 items-center justify-center text-destructive">
        Erreur lors du chargement des utilisateurs.
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
          <div className="mb-4 flex items-center justify-between">
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
          <DataTable
            data={users}
            columns={columns}
            getRowId={(u) => String(u.id)}
            exportFilename="utilisateurs-minepia"
            exportTitle="MINEPIA — Utilisateurs"
            searchKeys={["firstName", "lastName", "email"]}
            rowActions={(u) => (
              <>
                <RowIconButton
                  icon={Pencil}
                  label={t("action.edit")}
                  onClick={() => {
                    setSelected(u);
                    setView("edition");
                  }}
                />
                <RowIconButton
                  icon={KeyRound}
                  label="Réinitialiser le mot de passe"
                  onClick={() => setResetTarget(u)}
                />
                <RowIconButton
                  icon={Trash2}
                  label={t("action.delete")}
                  tone="danger"
                  onClick={() => setDeleteTarget(u)}
                />
              </>
            )}
          />
        </div>

      {/* ── Dialog de confirmation de suppression ──────────────────────── */}
      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Supprimer l'utilisateur ?</AlertDialogTitle>
            <AlertDialogDescription>
              Vous êtes sur le point de supprimer{" "}
              <span className="font-semibold text-foreground">
                {deleteTarget?.firstName} {deleteTarget?.lastName}
              </span>{" "}
              ({deleteTarget?.email}). L'utilisateur ne sera plus visible dans la plateforme.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Annuler</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) {
                  deleteMutation.mutate(deleteTarget.id);
                  setDeleteTarget(null);
                }
              }}
            >
              Supprimer
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
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [pwdError, setPwdError] = useState<string | null>(null);

  // ── Mutation création ──────────────────────────────────────────────────
  const createMutation = useMutation({
    mutationFn: (payload: CreateUserPayload) => createUser(payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  // ── Mutation édition ───────────────────────────────────────────────────
  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateUserPayload }) =>
      updateUser(id, payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!firstName.trim() || !lastName.trim() || !email.trim()) return;

    if (!initial) {
      // Création
      if (password.length < 6) {
        setPwdError("Le mot de passe doit contenir au moins 6 caractères.");
        return;
      }
      if (password !== confirm) {
        setPwdError("Les mots de passe ne correspondent pas.");
        return;
      }
      setPwdError(null);
      createMutation.mutate({
        firstName,
        lastName,
        email,
        password,
        is_active: isActive,
      });
    } else {
      // Édition
      setPwdError(null);
      updateMutation.mutate({
        id: initial.id,
        payload: { firstName, lastName, email, is_active: isActive },
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
              {initial ? "Modifier l'utilisateur" : "Nouvel utilisateur"}
            </h2>
            <p className="text-xs text-muted-foreground">
              Renseignez les informations et les accès de l'utilisateur.
            </p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>Prénom <span className="text-destructive">*</span></Label>
              <Input
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                placeholder="Jean"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>Nom <span className="text-destructive">*</span></Label>
              <Input
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                placeholder="DUPONT"
                required
              />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Email <span className="text-destructive">*</span></Label>
              <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="jean.dupont@minepia.cm"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>Statut</Label>
              <Select
                value={isActive ? "Activé" : "Désactivé"}
                onValueChange={(v) => setIsActive(v === "Activé")}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Activé">Activé</SelectItem>
                  <SelectItem value="Désactivé">Désactivé</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {!initial && (
            <div className="mt-6 border-t border-border pt-5">
              <div className="mb-3">
                <h3 className="text-sm font-semibold text-foreground">Sécurité</h3>
                <p className="text-xs text-muted-foreground">
                  Définissez un mot de passe initial pour l'utilisateur.
                </p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Mot de passe <span className="text-destructive">*</span></Label>
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
                      aria-label={showPwd ? "Masquer" : "Afficher"}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label>Confirmer <span className="text-destructive">*</span></Label>
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
                      aria-label={showConfirm ? "Masquer" : "Afficher"}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>
              {pwdError && (
                <p className="mt-3 text-xs font-medium text-destructive">{pwdError}</p>
              )}
            </div>
          )}
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
    mutationFn: (payload: ResetUserPasswordPayload) =>
      resetUserPassword(user.id, payload),
    onSuccess: () => {
      toast.success("Mot de passe réinitialisé avec succès.");
      onSaved();
    },
    onError: () => toast.error(t("toast.error")),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPwdError(null);
    if (password.length < 6) {
      setPwdError("Le mot de passe doit contenir au moins 6 caractères.");
      return;
    }
    if (password !== confirm) {
      setPwdError("Les mots de passe ne correspondent pas.");
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
              Réinitialiser le mot de passe
            </h2>
            <p className="text-xs text-muted-foreground">
              Définissez un nouveau mot de passe pour{" "}
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
                Nouveau mot de passe <span className="text-destructive">*</span>
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
                  aria-label={showPwd ? "Masquer" : "Afficher"}
                  className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                >
                  {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>
                Confirmer <span className="text-destructive">*</span>
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
                  aria-label={showConfirm ? "Masquer" : "Afficher"}
                  className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                >
                  {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
          </div>
          {pwdError && (
            <p className="mt-3 text-xs font-medium text-destructive">{pwdError}</p>
          )}
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
            Réinitialiser
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
    render: f.key === fields[0].key ? (r) => <span className="font-medium">{r[f.key]}</span> : undefined,
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
        setRows((prev) => (view === "creation" ? [row, ...prev] : prev.map((x) => (x.id === row.id ? row : x))));
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
                    setForm({ ...form, [f.key]: f.type === "number" ? Number(e.target.value) : e.target.value })
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
