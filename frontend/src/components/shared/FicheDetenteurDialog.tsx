/**
 * FicheDetenteurDialog — Recommandation 90.
 *
 * Pop-up listant tous les biens actuellement affectés à un utilisateur
 * donné (bien.utilisateur.id === userId), à l'instant présent. Réutilisé à
 * 3 endroits : tableau des affectations (BienDetail), liste des
 * utilisateurs (Configuration), liste des biens (Biens).
 *
 * Le backend n'expose pas de endpoint "biens d'un utilisateur" — GET
 * /assets ne filtre pas par user_id. On récupère donc la liste complète
 * (limit élevé, comme ailleurs dans l'app pour les listes exhaustives) et
 * on filtre côté client sur utilisateur.id.
 *
 * contextBien (facultatif) : le bien depuis lequel le popup a été ouvert
 * (ex. depuis sa propre fiche, tableau Affectation, ou sa ligne dans la
 * liste). On sait déjà avec certitude qu'il concerne cet utilisateur — donc
 * on l'inclut toujours, même si la requête globale échoue ou est
 * incomplète (GET /assets a montré des 404 intermittents dans ce backend).
 * Sans ça, un échec de la liste globale fait croire à tort qu'un
 * utilisateur n'a "aucun bien" alors qu'on est littéralement en train d'en
 * regarder un qui lui appartient.
 */

import { useQuery } from "@tanstack/react-query";
import { User } from "lucide-react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { listBiens, type ApiBien } from "@/api/biens/biens.api";

export function FicheDetenteurDialog({ userId, userName, open, onClose, contextBien }: {
  userId: number | null;
  userName: string;
  open: boolean;
  onClose: () => void;
  contextBien?: ApiBien | null;
}) {
  // queryKey: ["biens"] — délibérément la MÊME clé/requête que la liste
  // principale des biens (Biens.tsx), pas une clé à part. Une clé séparée
  // déclenchait une deuxième requête réseau en parallèle de celles déjà en
  // cours sur la page ; ce backend gère mal les requêtes simultanées
  // (confirmé plusieurs fois ailleurs dans l'app — BSP, réforme...) et ça
  // provoquait des 404 intermittents. En réutilisant la même clé, React
  // Query sert les données déjà en cache (aucun appel réseau si la liste
  // principale les a déjà chargées) ou, sinon, une seule requête partagée.
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["biens"],
    queryFn: () => listBiens({ limit: 200 }),
    enabled: open && userId != null,
    staleTime: 60_000,
    retry: 1,
  });
  // Number(...) des deux côtés : le backend n'est pas toujours cohérent sur
  // le type (string vs number) des ids selon l'endpoint qui les renvoie.
  const matches = (b: ApiBien) => b.utilisateur != null && Number(b.utilisateur.id) === Number(userId);

  const fromList = (data ?? []).filter(matches);
  const contextMatches = contextBien && matches(contextBien) && !fromList.some((b) => b.id === contextBien.id);
  const biens = contextMatches ? [contextBien!, ...fromList] : fromList;

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="flex max-h-[80vh] flex-col sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <User className="h-4 w-4" /> Fiche détenteur — {userName}
          </DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="py-8 text-center text-sm text-muted-foreground">Chargement…</div>
        ) : isError && biens.length === 0 ? (
          <p className="py-8 text-center text-sm text-destructive">
            Erreur lors du chargement des biens — {(error as { message?: string })?.message ?? "réessayez"}.
          </p>
        ) : biens.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            Aucun bien actuellement affecté à cet utilisateur.
          </p>
        ) : (
          <>
            <div className="overflow-y-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-muted/40">
                  <tr>
                    <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">Référence</th>
                    <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">Nom du bien</th>
                    <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">Catégorie</th>
                    <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold text-muted-foreground">État</th>
                    <th className="px-3 py-2 text-left text-xs font-semibold text-muted-foreground">Structure</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {biens.map((b) => (
                    <tr key={b.id}>
                      <td className="whitespace-nowrap px-3 py-2 font-mono text-xs">{b.reference}</td>
                      <td className="px-3 py-2">{b.nom}</td>
                      <td className="whitespace-nowrap px-3 py-2 text-xs">{b.category?.nom ?? "—"}</td>
                      <td className="whitespace-nowrap px-3 py-2 text-xs">{b.etatBien?.nom ?? "—"}</td>
                      <td className="px-3 py-2 text-xs">{b.service?.nom ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="text-xs text-muted-foreground">
              {biens.length} bien{biens.length > 1 ? "s" : ""} actuellement affecté{biens.length > 1 ? "s" : ""}.
            </p>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
