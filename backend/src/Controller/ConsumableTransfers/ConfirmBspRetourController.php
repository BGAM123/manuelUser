<?php

namespace App\Controller\ConsumableTransfers;

use App\Entity\Bsp;
use App\Entity\ConsumableBsp;
use App\Entity\ConsumableTransfer;
use App\Repository\ConsumableBspRepository;
use App\Repository\ConsumableTransferRepository;
use App\Service\ApiResponseFactory;
use App\Service\ConsumableStockManager;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consumable-transfers')]
#[OA\Tag(name: 'Consomptibles-Transferts')]
final class ConfirmBspRetourController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/{id}/bsp/retour', name: 'app_consumable_transfer_confirm_bsp_retour', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/consumable-transfers/{id}/bsp/retour',
        summary: 'Confirmer le retour d\'un BSP',
        description: 'Permet à l\'utilisateur connecté de confirmer qu\'un BSP est revenu. L\'utilisateur connecté est automatiquement enregistré comme validateur du retour.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1)]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Retour du BSP confirmé avec succès.',
                'data' => [
                    'id' => 1,
                    'numero' => 'BSP-2026-00025',
                    'retour' => true,
                    'dateRetourEffective' => '2026-08-16',
                    'validateurRetour' => [
                        'id' => 15,
                        'nom' => 'Dupont',
                        'prenom' => 'Jean',
                        'matricule' => 'MAT001'
                    ]
                ]
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Ce BSP est déjà marqué comme retourné.', 'data' => null]))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'Le transfert demandé est introuvable.', 'data' => null]))]
    public function __invoke(
        int $id,
        ConsumableTransferRepository $consumableTransferRepository,
        ConsumableBspRepository $consumableBspRepository,
        ConsumableStockManager $stockManager,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $transfer = $consumableTransferRepository->getActiveById($id);
        if (!$transfer instanceof ConsumableTransfer) {
            return $apiResponse->error('Le transfert demandé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        if ($transfer->getType() !== 'BSP') {
            return $apiResponse->error('Ce transfert n\'est pas de type BSP.', Response::HTTP_BAD_REQUEST);
        }

        $consumableBsp = $consumableBspRepository->findOneBy(['consumableTransfer' => $transfer]);
        if (!$consumableBsp instanceof ConsumableBsp) {
            return $apiResponse->error('Aucun BSP associé à ce transfert.', Response::HTTP_NOT_FOUND);
        }

        $bsp = $consumableBsp->getBsp();
        if (!$bsp instanceof Bsp) {
            return $apiResponse->error('Le BSP associé est introuvable.', Response::HTTP_NOT_FOUND);
        }

        if ($bsp->isRetour()) {
            return $apiResponse->error('Ce BSP est déjà marqué comme retourné.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user) {
            return $apiResponse->error('Utilisateur non authentifié.', Response::HTTP_UNAUTHORIZED);
        }

        $bsp->setRetour(true);
        $bsp->setValidateurRetour($user);
        $bsp->setDateRetourEffective(new \DateTime());

        $this->entityManager->persist($bsp);
        $this->entityManager->flush();

        $stockManager->recalculateAndPersist($transfer->getConsumable());

        return $apiResponse->success(
            $this->normalizeBsp($bsp),
            Response::HTTP_OK,
            'Retour du BSP confirmé avec succès.'
        );
    }

    private function normalizeBsp(Bsp $bsp): array
    {
        return [
            'id' => $bsp->getId(),
            'numero' => $bsp->getNumero(),
            'retour' => $bsp->isRetour(),
            'dateRetourEffective' => $bsp->getDateRetourEffective()?->format('Y-m-d'),
            'validateurRetour' => $bsp->getValidateurRetour() ? [
                'id' => $bsp->getValidateurRetour()->getId(),
                'nom' => $bsp->getValidateurRetour()->getLastName(),
                'prenom' => $bsp->getValidateurRetour()->getFirstName(),
                'matricule' => $bsp->getValidateurRetour()->getMatricule(),
            ] : null,
        ];
    }
}
