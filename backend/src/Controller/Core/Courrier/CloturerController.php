<?php

namespace App\Controller\Core\Courrier;

use App\Entity\Cour\PieceJointe;
use App\Repository\Cour\CourrierRepository;
use App\Repository\Cour\CourrierDepartRepository;
use App\Service\Core\AccessCheckerService;
use App\Service\Core\FileService;
use App\Service\Core\FunctionService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "CourrierArrive")]
class CloturerController extends AbstractController
{
    public function __construct(
        private CourrierRepository $courrierRepository,
        private CourrierDepartRepository $courrierDepartRepository,
        private AccessCheckerService $accessChecker,
        private FileService $fileService,
        private FunctionService $functionService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/core/courrier/{id}/cloturer', name: 'app_core_courrier_cloturer', methods: ['POST'])]
    #[OA\Post(
        path: '/core/courrier/{id}/cloturer',
        summary: 'Clôturer un courrier',
        tags: ['Courrier'],
        description: 'Permet de clôturer un courrier en renseignant date_remise_effective fournie par l\'utilisateur; date_cloture est générée automatiquement.',
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'date_remise_effective', type: 'string', format: 'date', example: '2025-11-05', description: 'Date de remise effective (optionnelle)'),
                        new OA\Property(property: 'id_courrier_depart', type: 'integer', example: 12, description: 'ID du courrier depart a cloturer (optionnel)'),
                        new OA\Property(property: 'piecesJointes[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary'))
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Courrier clôturé avec succès.'),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 404, description: 'Courrier non trouvé.'),
            new OA\Response(response: 401, description: 'Accès non autorisé.')
        ]
    )]
    public function cloturer(Request $request, int $id): Response
    {
        $this->accessChecker->checker($this->getUser(), $this->isGranted('ROLE_USER'), 'CloturerCourrier');

        $courrier = $this->courrierRepository->find($id);
        if (!$courrier) {
            return $this->json(['code' => 404, 'message' => 'Courrier non trouvé.'], 404);
        }

        // Récupérer les données du form-data
        $dateRemise = $request->request->get('date_remise_effective');
        $courrierDepartId = $request->request->get('id_courrier_depart') ?? $request->request->get('idCourrierDepart');

        try {
            // Convertir la date fournie si elle existe
            $dateObj = null;
            if (!empty($dateRemise)) {
                if (is_string($dateRemise)) {
                    $dateObj = new \DateTime($dateRemise);
                } elseif ($dateRemise instanceof \DateTimeInterface) {
                    $dateObj = new \DateTime($dateRemise->format('Y-m-d H:i:s'));
                }
                
                // VÃ©rifier si la conversion a Ã©chouÃ©
                if (!$dateObj && !empty($dateRemise)) {
                    return $this->json(['code' => 400, 'message' => 'Format de date invalide.'], 400);
                }
            }

            // Mettre Ã  jour date_remise_effective (peut Ãªtre null)
            $courrier->setDateRemiseEffective($dateObj);

            // GÃ©nÃ©rer automatiquement la date de clÃ´ture (maintenant)
            $courrier->setDateCloture(new \DateTimeImmutable());
            $courrier->setStatut('Clôturé');

            $courrierDepart = null;
            if (!empty($courrierDepartId)) {
                $courrierDepart = $this->courrierDepartRepository->find((int) $courrierDepartId);
                if (!$courrierDepart) {
                    return $this->json(['code' => 404, 'message' => 'Courrier depart non trouvé.'], 404);
                }
                $courrierDepart->setStatut('Clôturé');
            }

            // Gerer fichiers uploads
            $uploadedCount = 0;
            if (!empty($request->files->get('piecesJointes'))) {
                foreach ($request->files->get('piecesJointes') as $file) {
                    $filePath = $this->fileService->uploadFile(
                        $this->getParameter('app_uploads_courrier_piece_directory'),
                        $file
                    );

                    if ($filePath) {
                        $piece = new PieceJointe();
                        $piece->setNom($file->getClientOriginalName());
                        $piece->setChemin($this->getParameter('app_uploads_courrier_piece') . $filePath);
                        $piece->setType($file->getClientMimeType());
                        $piece->setIdParent($courrier->getId());
                        $piece->setTypeParent('Courrier');

                        $this->entityManager->persist($piece);
                        $uploadedCount++;
                    }
                }
            }

            // Persister et flush
            $this->entityManager->persist($courrier);
            if ($courrierDepart) {
                $this->entityManager->persist($courrierDepart);
            }
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Courrier cloture avec succes',
                'id' => $courrier->getId(),
                'date_remise_effective' => $courrier->getDateRemiseEffective()?->format('Y-m-d'),
                'dateCloture' => $courrier->getDateCloture()?->format('c'),
                'statut' => $courrier->getStatut(),
                'courrier_depart_id' => $courrierDepart?->getId(),
                'statut_courrier_depart' => $courrierDepart?->getStatut(),
                'piecesJointesCount' => $uploadedCount,
                'hasDateRemise' => $courrier->getDateRemiseEffective() !== null
            ], 200);

        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => 'Erreur lors de la cloture : ' . $e->getMessage()], 500);
        }
    }
}

