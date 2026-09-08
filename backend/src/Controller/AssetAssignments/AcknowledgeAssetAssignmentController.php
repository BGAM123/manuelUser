<?php

namespace App\Controller\AssetAssignments;

use App\Entity\AssetAssignment;
use App\Entity\Notification;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\AssetAssignmentRepository;
use App\Service\AcknowledgementService;
use App\Service\ApiResponseFactory;
use App\Service\AssetAssignmentResponseBuilder;
use App\Service\AssetAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/asset-assignments')]
#[OA\Tag(name: 'Asset Assignments')]
final class AcknowledgeAssetAssignmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/acknowledge-batch', name: 'app_asset_assignment_acknowledge_batch', methods: ['POST'])]
    #[OA\Post(
        path: '/asset-assignments/acknowledge-batch',
        summary: 'Accuser réception de plusieurs affectations en une seule fois',
        description: "Valide uniquement les affectations non encore accusées. Les affectations déjà accusées sont ignorées."
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [
                new OA\Property(property: 'ids', type: 'string', example: '1,2,3', description: 'IDs des affectations séparés par des virgules'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success - toutes les affectations valides ont été accusées',
        content: new OA\JsonContent(example: [
            'success' => true, 
            'status' => 200, 
            'message' => '2 affectation(s) accusée(s) réception avec succès.',
            'data' => [
                'acknowledged' => [1, 3],
                'ignored' => [
                    ['id' => 2, 'reason' => 'Déjà accusée']
                ],
                'total_requested' => 3,
                'total_processed' => 2
            ]
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - aucun ID valide fourni',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 400, 'message' => 'Aucune affectation valide à traiter.'])
    )]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        AssetAssignmentRepository $assignmentRepository,
        AssetAssignmentService $assignmentService,
        AcknowledgementService $acknowledgementService,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $idsString = is_array($payload) ? ($payload['ids'] ?? null) : null;

        if (!is_string($idsString) || '' === trim($idsString)) {
            return $apiResponse->error(
                'ids est obligatoire et doit être une chaîne non vide.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Convertir la chaîne séparée par des virgules en tableau d'IDs
        $assignmentIds = array_map('trim', explode(',', $idsString));
        $assignmentIds = array_filter($assignmentIds, 'is_numeric');
        $assignmentIds = array_map('intval', $assignmentIds);

        if ([] === $assignmentIds) {
            return $apiResponse->error(
                'Aucun ID valide trouvé dans le paramètre ids.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $comment = is_array($payload) ? ($payload['commentaire'] ?? null) : null;

        $this->entityManager->beginTransaction();

        try {
            $acknowledgedIds = [];
            $ignoredIds = [];
            $errors = [];

            foreach ($assignmentIds as $assignmentId) {
                $assignment = $assignmentRepository->find($assignmentId);
                
                // Vérification 1: L'affectation existe
                if (!$assignment instanceof AssetAssignment || $assignment->isDelete()) {
                    $errors[] = [
                        'id' => $assignmentId,
                        'reason' => "Affectation introuvable"
                    ];
                    continue;
                }

                // ✅ Vérifier si l'utilisateur est administrateur
                $isAdmin = false;
                $userRoles = $user->getAssignedRoles();
                $adminRoleNames = ['Administrateur', 'Administrateur patrimonial', 'Administrateur système'];
                foreach ($userRoles as $role) {
                    if (in_array($role->getNom(), $adminRoleNames, true)) {
                        $isAdmin = true;
                        break;
                    }
                }

                // Vérification 2: L'utilisateur est autorisé (destinataire direct),
                // OU administrateur — mais un admin ne peut accuser réception que
                // des affectations de son PROPRE poste (service), pas de n'importe
                // quel bien du patrimoine (demande explicite 2026-08-31 : "il ne
                // peut qu'accuser réception des biens qui sont directement
                // affectés à son poste").
                if ($isAdmin) {
                    $adminService = $user->getService();
                    $assignmentService_ = $assignment->getService();
                    if (!$adminService || !$assignmentService_ || $assignmentService_->getId() !== $adminService->getId()) {
                        $errors[] = [
                            'id' => $assignmentId,
                            'reason' => "Ce bien n'est pas affecté à votre poste"
                        ];
                        continue;
                    }
                } else {
                    $recipient = $assignmentService->resolveRecipient($assignment);
                    if (!$recipient || $recipient->getId() !== $user->getId()) {
                        $errors[] = [
                            'id' => $assignmentId,
                            'reason' => "Non autorisé pour cet utilisateur"
                        ];
                        continue;
                    }
                }

                // NOUVEAU: Vérifier si déjà accusée et l'ignorer (champ received)
                if ($assignment->isReceived()) {
                    $ignoredIds[] = [
                        'id' => $assignmentId,
                        'reason' => "Déjà accusée réception"
                    ];
                    continue;
                }

                // Traiter l'affectation (seulement si non déjà accusée)
                $acknowledgementService->acknowledge(
                    $user, 
                    Notification::SUBJECT_ASSET_ASSIGNMENT, 
                    $assignment->getId(), 
                    $comment
                );
                $acknowledgedIds[] = $assignment->getId();
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Construction du message
            $totalProcessed = count($acknowledgedIds) + count($ignoredIds) + count($errors);
            
            if (empty($acknowledgedIds) && empty($ignoredIds)) {
                // Tout a échoué
                return $apiResponse->error(
                    'Aucune affectation n\'a pu être traitée.',
                    Response::HTTP_BAD_REQUEST,
                    ['errors' => $errors]
                );
            }

            $message = '';
            if (!empty($acknowledgedIds)) {
                $message .= sprintf('%d affectation(s) accusée(s) réception avec succès.', count($acknowledgedIds));
            }
            if (!empty($ignoredIds)) {
                if (!empty($message)) $message .= ' ';
                $message .= sprintf('%d affectation(s) ignorée(s) car déjà accusée(s).', count($ignoredIds));
            }
            if (!empty($errors)) {
                if (!empty($message)) $message .= ' ';
                $message .= sprintf('%d affectation(s) en erreur.', count($errors));
            }

            return $apiResponse->success(
                [
                    'acknowledged' => $acknowledgedIds,
                    'ignored' => $ignoredIds,
                    'errors' => $errors,
                    'total_requested' => count($assignmentIds),
                    'total_processed' => count($acknowledgedIds)
                ],
                Response::HTTP_OK,
                $message
            );

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return $apiResponse->error(
                'Une erreur est survenue lors du traitement.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['message' => $e->getMessage()]
            );
        }
    }
}