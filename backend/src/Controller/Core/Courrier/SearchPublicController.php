<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class SearchPublicController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/public/courrier/search', name: 'app_public_courrier_search', methods: ['GET'])]
    #[OA\Get(
        path: '/public/courrier/search',
        summary: 'Rechercher un courrier (accès public)',
        description: 'Permet de rechercher des courriers par correspondance exacte sur numéro, référence, objet, nom, matricule, email, téléphone du courrier OU du correspondant. Recherche stricte - le terme doit correspondre exactement au contenu du champ. Accès public sans authentification.',
        tags: ['CourrierArrive'],
        parameters: [
            new OA\Parameter(
                name: 'q',
                in: 'query',
                required: true,
                description: 'Terme de recherche exact (numéro, référence, objet, nom, matricule, email, téléphone...)',
                schema: new OA\Schema(type: 'string', example: 'MINEPIA/2025/09/17/25/A')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'RÃ©sultats de la recherche',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'registre', type: 'string', example: 'MINEPIA/2025/09/17/25/A'),
                            new OA\Property(property: 'datearrivee', type: 'string', format: 'date-time', example: '2025-09-17 10:30:00'),
                            new OA\Property(property: 'date_signature', type: 'string', format: 'date-time', example: '2025-09-18 14:00:00'),
                            new OA\Property(property: 'emetteur_nom_prenom', type: 'string', example: 'MINEPIA/SG - Secrétariat Général', description: 'Sigle et nom du service de l\'émetteur (créateur du courrier)'),
                            new OA\Property(property: 'objetcourrier', type: 'string', example: 'Demande d\'autorisation'),
                            new OA\Property(property: 'dernier_service_emetteur_libelle', type: 'string', example: 'Direction Générale'),
                            new OA\Property(property: 'dernier_service_recu_libelle', type: 'string', example: 'Service du courrier entrant'),
                            new OA\Property(property: 'type_diffusion_libelle', type: 'string', example: 'Copie'),
                            new OA\Property(property: 'commentaire', type: 'string', example: 'Traitement en cours'),
                            new OA\Property(property: 'commentaire_reponse', type: 'string', example: 'Traitement effectué le 25/09.', description: 'Dernier commentaire public d\'une réponse (vide si aucune réponse)'),
                            new OA\Property(property: 'numeroActe', type: 'string', example: '3'),
                            new OA\Property(property: 'dateSignature', type: 'string', format: 'date-time', example: '2025-09-20 16:00:00'),
                            new OA\Property(
                                property: 'signataire',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Dr. Marie DUBOIS'),
                                    new OA\Property(property: 'service', type: 'string', example: 'Direction des Ressources Humaines'),
                                ]
                            ),
                        ]
                    )
                )
            ),
            new OA\Response(response: 400, description: 'Paramètre de recherche manquant')
        ]
    )]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');

        if (!$query || trim($query) === '') {
            return $this->json(['code' => 400, 'message' => 'Paramètre de recherche "q" requis.'], 400);
        }

        try {
            $trimmedQuery = trim($query);
            
            $courriers = $this->courrierRepository->createQueryBuilder('c')
                ->leftJoin('c.idProvenance', 'correspondant')
                ->leftJoin('c.courrierDeparts', 'cd')
                ->leftJoin('cd.idSignataire', 'sig')
                ->leftJoin('sig.idService', 'sigService')
                ->addSelect('correspondant', 'cd', 'sig', 'sigService')
                ->where('c.isDelete = false')
                ->andWhere('c.isConfidentiel = false')
                ->andWhere('
                    c.numero = :query OR 
                    c.reference = :query OR 
                    c.objet = :query OR 
                    c.nom = :query OR 
                    c.matricule = :query OR 
                    c.email = :query OR 
                    c.telephone = :query OR 
                    c.commentaire = :query OR 
                    correspondant.nom = :query OR 
                    correspondant.email = :query OR 
                    correspondant.matricule = :query OR 
                    correspondant.telephone = :query OR
                    c.search = :query OR
                    correspondant.search = :query
                ')
                ->setParameter('query', $trimmedQuery)
                ->orderBy('c.dateArrivee', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getResult();

            $results = [];

            foreach ($courriers as $courrier) {
                // DerniÃ¨re transmission (la plus rÃ©cente)
                $transmissions = $courrier->getTransmissions()->toArray();
                $transmissionsActives = array_filter($transmissions, fn($t) => !$t->isDelete());
                usort($transmissionsActives, fn($a, $b) =>
                    ($b->getDateInstruction() ?? $b->getCreatedAt())?->getTimestamp() <=>
                    ($a->getDateInstruction() ?? $a->getCreatedAt())?->getTimestamp()
                );
                $derniereTransmission = $transmissionsActives[0] ?? null;

                // Dernier courrier dÃ©part
                $courrierDeparts = $courrier->getCourrierDeparts()->toArray();
                $courrierDepartsActifs = array_filter($courrierDeparts, fn($cd) => !$cd->isDelete());
                usort($courrierDepartsActifs, fn($a, $b) =>
                    ($b->getDateSignature() ?? $b->getCreatedAt())?->getTimestamp() <=>
                    ($a->getDateSignature() ?? $a->getCreatedAt())?->getTimestamp()
                );
                $dernierCourrierDepart = $courrierDepartsActifs[0] ?? null;

                // NumÃ©ro d'ordre (incrÃ©ment par type et annÃ©e)
                $numeroActe = null;
                if ($dernierCourrierDepart) {
                    $typeCourrier = $dernierCourrierDepart->getTypeCourrier();
                    $annee = $dernierCourrierDepart->getDateSignature()?->format('Y') ?? date('Y');

                    $increment = $this->em->createQueryBuilder()
                        ->select('COUNT(cd2.id)')
                        ->from('App\Entity\Cour\CourrierDepart', 'cd2')
                        ->where('cd2.typeCourrier = :type')
                        ->andWhere('cd2.isDelete = false')
                        ->andWhere('
                            (SUBSTRING(cd2.dateSignature, 1, 4) = :year) OR 
                            (cd2.dateSignature IS NULL AND SUBSTRING(cd2.createdAt, 1, 4) = :year)
                        ')
                        ->andWhere('cd2.id <= :currentId')
                        ->setParameter('type', $typeCourrier)
                        ->setParameter('year', $annee)
                        ->setParameter('currentId', $dernierCourrierDepart->getId())
                        ->getQuery()
                        ->getSingleScalarResult();

                    $numeroActe = (string) $increment;
                }

                // Dernier commentaire de rÃ©ponse (public)
                $dernierCommentaireReponse = '';
                $reponses = $courrier->getReponses()->toArray();
                $reponsesAvecCommentaire = array_filter($reponses, fn($r) => !$r->isDelete() && trim((string) $r->getCommentairePublic()));
                if (!empty($reponsesAvecCommentaire)) {
                    usort($reponsesAvecCommentaire, fn($a, $b) =>
                        ($b->getDateReponse() ?? $b->getCreatedAt())?->getTimestamp() <=>
                        ($a->getDateReponse() ?? $a->getCreatedAt())?->getTimestamp()
                    );
                    $dernierCommentaireReponse = trim($reponsesAvecCommentaire[0]->getCommentairePublic() ?? '');
                }

                // Services sÃ©curisÃ©s avec debug pour le crÃ©ateur
                $serviceEmetteurLibelle = $this->getServiceNomFromUser($derniereTransmission?->getIdEmetteur());
                $serviceDestinataireLibelle = $this->getServiceNomDirect($derniereTransmission?->getIdServiceDestinataire());
                $serviceSignataireLibelle = $this->getServiceNomFromUser($dernierCourrierDepart?->getIdSignataire());
                
                // Debug pour le crÃ©ateur - retourner sigle ET nom
                $createur = $courrier->getIdCreateur();
                $serviceCreateurLibelle = null;
                if ($createur) {
                    $serviceCreateur = $createur->getIdService();
                    if ($serviceCreateur && !$serviceCreateur->isDelete()) {
                        $sigle = $serviceCreateur->getSigle();
                        $nom = $serviceCreateur->getNom();
                        
                        if ($sigle && $nom) {
                            $serviceCreateurLibelle = $sigle . ' - ' . $nom;
                        } elseif ($sigle) {
                            $serviceCreateurLibelle = $sigle;
                        } elseif ($nom) {
                            $serviceCreateurLibelle = $nom;
                        }
                    }
                }

                $results[] = [
                    'registre' => $courrier->getNumero(),
                    'datearrivee' => $courrier->getDateArrivee()?->format('Y-m-d H:i:s'),
                    'date_signature' => $dernierCourrierDepart?->getDateSignature()?->format('Y-m-d H:i:s'),
                    'emetteur_nom_prenom' => $serviceCreateurLibelle ?: 'Service non renseigné',
                    'objetcourrier' => $courrier->getObjet(),
                    'dernier_service_emetteur_libelle' => $serviceEmetteurLibelle,
                    'dernier_service_recu_libelle' => $serviceDestinataireLibelle,
                    'type_diffusion_libelle' => $derniereTransmission?->getTypeTransfert(),
                    'commentaire' => $courrier->getCommentaire(),
                    'commentaire_public' => $dernierCommentaireReponse, // NOUVEAU CHAMP
                    'numeroActe' => $numeroActe,
                    'dateSignature' => $dernierCourrierDepart?->getDateSignature()?->format('Y-m-d H:i:s'),
                    'signataire' => $dernierCourrierDepart?->getIdSignataire() ? [
                        'id' => $dernierCourrierDepart->getIdSignataire()->getId(),
                        'nom' => $dernierCourrierDepart->getIdSignataire()->getFullName(),
                        'service' => $serviceSignataireLibelle,
                    ] : null,
                ];
            }

            return $this->json($results, 200);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => 'Erreur interne du serveur.'], 500);
        }
    }

    // MÃ©thodes de protection amÃ©liorÃ©es
    private function getServiceNomFromUser(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        
        try {
            $service = $user->getIdService();
            if ($service && !$service->isDelete()) {
                $sigle = $service->getSigle();
                return !empty($sigle) ? $sigle : $service->getNom();
            }
        } catch (\Exception $e) {
            // Log l'erreur si nÃ©cessaire
            return null;
        }
        
        return null;
    }

    private function getServiceNomDirect(?Service $service): ?string
    {
        if (!$service) return null;
        try {
            return !$service->isDelete() ? $service->getSigle() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}