<?php

namespace App\Controller\Services;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use App\Service\ApiResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/services')]
#[OA\Tag(name: 'Services')]
final class DeleteServiceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/{id}', name: 'app_service_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/services/{id}',
        summary: 'Supprimer un service',
        description: 'Supprime définitivement un service de l\'organigramme.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 5)]
    #[OA\Response(
        response: 200,
        description: 'Success - Service supprimé',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Service deleted successfully.',
                'data' => null,
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'La validation a échoué.', 'data' => null])
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Service $service,
        ServiceRepository $serviceRepository,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        // Vérifier les dépendances avant suppression
        $dependencies = $this->checkDependencies($service);
        
        if (!empty($dependencies)) {
            return $apiResponse->error(
                'Impossible de supprimer ce service car il est utilisé dans le systeme.',
                Response::HTTP_BAD_REQUEST,
                [
                    'dependencies' => $dependencies,
                    'message' => 'Veuillez supprimer ou réaffecter les dépendances avant de supprimer ce service.'
                ]
            );
        }

        // Si pas de dépendances, suppression définitive
        $serviceRepository->remove($service);
        return $apiResponse->success(null, Response::HTTP_OK, 'Service supprimé avec succès.');
    }

    /**
     * Vérifie toutes les dépendances d'un service
     * 
     * @return array<string, mixed>
     */
    private function checkDependencies(Service $service): array
    {
        $dependencies = [];

        // 1. Vérifier les services enfants
        $children = $this->entityManager
            ->createQuery('SELECT COUNT(s.id) FROM App\Entity\Service s WHERE s.parent = :service')
            ->setParameter('service', $service)
            ->getSingleScalarResult();
        
        if ($children > 0) {
            $dependencies['services_enfants'] = $children . ' service(s) enfant(s)';
        }

        // 2. Vérifier les utilisateurs rattachés
        $users = $this->entityManager
            ->createQuery('SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.service = :service AND u.isDelete = false')
            ->setParameter('service', $service)
            ->getSingleScalarResult();
        
        if ($users > 0) {
            $dependencies['utilisateurs'] = $users . ' utilisateur(s) rattaché(s)';
        }

        // 3. Vérifier les transferts de consommables (destination) - C'est celle qui pose problème
        $transfers = $this->entityManager
            ->createQuery('SELECT COUNT(t.id) FROM App\Entity\ConsumableTransfer t WHERE t.serviceDestination = :service')
            ->setParameter('service', $service)
            ->getSingleScalarResult();
        
        if ($transfers > 0) {
            $dependencies['transferts_consommables'] = $transfers . ' transfert(s) de consommables';
        }

        // 4. Vérifier les autres tables possibles (ajoutez selon votre besoin)
        // Vous pouvez ajouter d'autres vérifications ici si nécessaire

        return $dependencies;
    }
}