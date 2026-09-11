<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Cour\Courrier;
use App\Repository\Core\UserRepository;
use App\Repository\Core\ServiceRepository;
use App\Repository\Core\CorrespondantRepository;
use App\Service\Core\CrudService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use App\Service\Core\AccessCheckerService;
use App\Service\Cour\CourrierService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class DeleteController extends AbstractController
{
    public function __construct(
        private CrudService $crudService,
        private FileService $fileService,
        private FunctionService $functionService,
        private AccessCheckerService $accessChecker,
        private CourrierService $courrierService,
        private UserRepository $userRepository,
        private ServiceRepository $serviceRepository,
        private CorrespondantRepository $correspondantRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/core/courrier/{id<([1-9][0-9]*)>}', name: 'app_core_courrier_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/core/courrier/{id}',
        summary: 'Supprimer un courrier',
        tags: ['CourrierArrive'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Courrier supprimé avec succès.'
            ),
            new OA\Response(
                response: 404,
                description: 'Courrier non trouvé.'
            ),
            new OA\Response(
                response: 401,
                description: 'Accès non autorisé.'
            )
        ]
    )]
    public function data(Courrier $entity): Response
    {

        // RÃ©cupÃ©ration de l'utilisateur connectÃ© et vÃ©rification des droits d'accÃ¨s
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_ADMIN'), 'DeleteActualite');

        // Supprimer explicitement toutes les transmissions associÃ©es Ã  ce courrier
        foreach ($entity->getTransmissions() as $transmission) {
            $this->entityManager->remove($transmission);
        }

        // Supprimer le courrier
        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        // Retourner la rÃ©ponse JSON
        return $this->json(['code' => 204, 'message' => 'Delete successfully.'], 204);
    }
}
