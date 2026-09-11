<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Core\Service;
use App\Entity\Core\User;
use App\Repository\Cour\CourrierRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class ParcoursPublicController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository
    ) {}

    #[Route('/public/courrier/{numero}/parcours', name: 'app_public_courrier_parcours', requirements: ['numero' => '.+'], methods: ['GET'])]
    #[OA\Get(
        path: '/public/courrier/{numero}/parcours',
        summary: 'Parcours public d\'un courrier',
        description: 'Retourne le parcours complet d\'un courrier (transmissions et réponses) via son numéro. Accès public sans authentification.',
        tags: ['CourrierArrive'],
        parameters: [
            new OA\Parameter(
                name: 'numero',
                in: 'path',
                required: true,
                description: 'Numéro du courrier (registre)',
                schema: new OA\Schema(type: 'string', example: 'MINEPIA/2025/09/17/25/A')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Parcours du courrier',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'type', type: 'string', enum: ['transmission', 'reponse'], example: 'transmission'),
                            new OA\Property(property: 'service_source', type: 'string', example: 'Secrétariat Général'),
                            new OA\Property(property: 'responsable_source', type: 'string', example: 'Jean Dupont'),
                            new OA\Property(property: 'service_destination', type: 'string', example: 'Cabinet du Ministre'),
                            new OA\Property(property: 'responsable_destination', type: 'string', example: 'Marie Martin'),
                            new OA\Property(property: 'date_envoi', type: 'string', format: 'date-time', example: '2025-09-20 14:30:00'),
                            new OA\Property(property: 'date_reception', type: 'string', format: 'date-time', nullable: true, example: '2025-09-21 09:15:00'),
                            new OA\Property(property: 'commentaire', type: 'string', example: 'Transmission pour signature'),
                            new OA\Property(property: 'but', type: 'string', nullable: true, example: 'Pour traiter', description: 'Type de transmission (Pour traiter, Pour information, Pour avis...) - null pour les réponses'),
                            new OA\Property(property: 'statut', type: 'string', example: 'Reçu'),
                        ]
                    )
                )
            ),
            new OA\Response(response: 404, description: 'Courrier non trouvé')
        ]
    )]
    public function parcours(string $numero): Response
    {
        try {
            $courrier = $this->courrierRepository->findOneBy([
                'numero' => $numero,
                'isDelete' => false
            ]);

            if (!$courrier) {
                return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
            }

            $parcours = [];

            // Récupérer transmissions et réponses
            $transmissions = $courrier->getTransmissions()->toArray();
            $transmissionsActives = array_filter($transmissions, fn($t) => !$t->isDelete());
            usort($transmissionsActives, fn($a, $b) => 
                ($a->getDateInstruction() ?? $a->getCreatedAt())?->getTimestamp() <=> 
                ($b->getDateInstruction() ?? $b->getCreatedAt())?->getTimestamp()
            );

            $reponses = $courrier->getReponses()->toArray();
            $reponsesActives = array_filter($reponses, fn($r) => !$r->isDelete());

            // TRANSMISSIONS avec calcul automatique du statut
            foreach ($transmissionsActives as $index => $transmission) {
                // âœ… PROTECTION : RÃ©cupÃ©ration sÃ©curisÃ©e des services
                $serviceSourceNom = $this->getServiceNomFromUser($transmission->getIdEmetteur());
                $responsableSource = $transmission->getIdEmetteur()?->getFullName();

                $serviceDestination = $transmission->getIdServiceDestinataire();
                $serviceDestinationNom = $this->getServiceNomDirect($serviceDestination);
                $responsableDestination = $this->getUserFullNameSafe($serviceDestination?->getChefService());

                // âœ… CALCUL AUTOMATIQUE DU STATUT
                $statut = $this->calculerStatut($courrier, $transmission, $transmissionsActives, $index, $reponsesActives);

                $parcours[] = [
                    'type' => 'transmission',
                    'service_source' => $serviceSourceNom,
                    'responsable_source' => $responsableSource,
                    'service_destination' => $serviceDestinationNom,
                    'responsable_destination' => $responsableDestination,
                    'date_envoi' => ($transmission->getDateInstruction() ?? $transmission->getCreatedAt())?->format('Y-m-d H:i:s'),
                    'date_reception' => $transmission->getDateReception()?->format('Y-m-d H:i:s'),
                    'commentaire' => $transmission->getInstruction(),
                    'but' => $transmission->getTypeTransfert(),
                    'statut' => $statut,
                ];
            }

            // REPONSES
            usort($reponsesActives, fn($a, $b) => 
                ($a->getDateReponse() ?? $a->getCreatedAt())?->getTimestamp() <=> 
                ($b->getDateReponse() ?? $b->getCreatedAt())?->getTimestamp()
            );

            foreach ($reponsesActives as $reponse) {
                // âœ… PROTECTION : RÃ©cupÃ©ration sÃ©curisÃ©e des services
                $serviceSourceNom = $this->getServiceNomFromUser($reponse->getIdRedacteur());
                $responsableSource = $reponse->getIdRedacteur()?->getFullName();

                $serviceDestination = $reponse->getIdServiceDestinataire();
                $serviceDestinationNom = $this->getServiceNomDirect($serviceDestination);
                $responsableDestination = $this->getUserFullNameSafe($serviceDestination?->getChefService());

                $parcours[] = [
                    'type' => 'reponse',
                    'service_source' => $serviceSourceNom,
                    'responsable_source' => $responsableSource,
                    'service_destination' => $serviceDestinationNom,
                    'responsable_destination' => $responsableDestination,
                    'date_envoi' => ($reponse->getDateReponse() ?? $reponse->getCreatedAt())?->format('Y-m-d H:i:s'),
                    'date_reception' => null,
                    'commentaire' => $reponse->getCommentairePublic(),
                    'but' => null,
                    'statut' => 'Traité',
                ];
            }

            // Trier chronologiquement
            usort($parcours, function($a, $b) {
                $dateA = $a['date_envoi'] ?? '1970-01-01 00:00:00';
                $dateB = $b['date_envoi'] ?? '1970-01-01 00:00:00';
                return strtotime($dateA) <=> strtotime($dateB);
            });

            return $this->json($parcours, 200);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calcule automatiquement le statut d'une transmission selon les règles métier
     */
    private function calculerStatut($courrier, $transmission, array $transmissionsActives, int $index, array $reponsesActives): string
    {
        // Règle 1 : Si courrier clôturé → "Clos"
        if ($courrier->getDateCloture() !== null) {
            return 'Clos';
        }

        $dateTransmission = ($transmission->getDateInstruction() ?? $transmission->getCreatedAt())?->getTimestamp();

        // Règle 3 : Si réponse après cette transmission → "Traité"
        foreach ($reponsesActives as $reponse) {
            $dateReponse = ($reponse->getDateReponse() ?? $reponse->getCreatedAt())?->getTimestamp();
            if ($dateReponse && $dateTransmission && $dateReponse > $dateTransmission) {
                return 'Traité';
            }
        }

        // RÃ¨gle 4 : Si transmission suivante aprÃ¨s â†’ "Transmis"
        if (isset($transmissionsActives[$index + 1])) {
            return 'Transmis';
        }

        // RÃ¨gle 2 : Si accuseReception = true ET pas de rÃ©ponse aprÃ¨s â†’ "ReÃ§u"
        if ($transmission->isAccuseReception()) {
            $aReponseApres = false;
            foreach ($reponsesActives as $reponse) {
                $dateReponse = ($reponse->getDateReponse() ?? $reponse->getCreatedAt())?->getTimestamp();
                if ($dateReponse && $dateTransmission && $dateReponse > $dateTransmission) {
                    $aReponseApres = true;
                    break;
                }
            }
            
            if (!$aReponseApres) {
                // RÃ¨gle 5 : Si en traitement â†’ "En cours"
                // On considÃ¨re "en cours" si c'est la derniÃ¨re transmission et qu'elle est reÃ§ue
                if ($index === count($transmissionsActives) - 1) {
                    return 'Transmis';
                }
                return 'Reçu';
            }
        }

        // Règle 6 : Sinon → "En attente"
        return 'Transmis';
    }

    /**
     * âœ… RÃ©cupÃ¨re le nom du service d'un User de maniÃ¨re sÃ©curisÃ©e
     * GÃ¨re le cas oÃ¹ le service est supprimÃ© ou introuvable
     */
    private function getServiceNomFromUser(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        try {
            $service = $user->getIdService();
            if ($service && !$service->isDelete()) {
                return $service->getSigle();
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            // Le service a Ã©tÃ© supprimÃ© mais est encore rÃ©fÃ©rencÃ©
            return null;
        } catch (\Exception $e) {
            // Autre erreur
            return null;
        }

        return null;
    }

    /**
     * âœ… RÃ©cupÃ¨re le nom d'un Service directement de maniÃ¨re sÃ©curisÃ©e
     * GÃ¨re le cas oÃ¹ le service est supprimÃ© ou introuvable
     */
    private function getServiceNomDirect(?Service $service): ?string
    {
        if (!$service) {
            return null;
        }

        try {
            if (!$service->isDelete()) {
                return $service->getSigle();
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $e) {
            // Le service a Ã©tÃ© supprimÃ©
            return null;
        } catch (\Exception $e) {
            // Autre erreur
            return null;
        }

        return null;
    }

    /**
     * âœ… RÃ©cupÃ¨re le nom complet d'un User de maniÃ¨re sÃ©curisÃ©e
     * GÃ¨re le cas oÃ¹ le user ou son service est supprimÃ©
     */
    private function getUserFullNameSafe(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        try {
            return $user->getFullName();
        } catch (\Exception $e) {
            return null;
        }
    }
}