<?php

namespace App\Controller\Champs;

use App\Entity\Champ;
use App\Repository\CategoryRepository;
use App\Repository\ChampRepository;
use App\Service\ApiResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/champs')]
#[OA\Tag(name: 'Champs')]
final class CreateChampController extends AbstractController
{
    #[Route('', name: 'app_champ_create', methods: ['POST'])]
    #[OA\Post(
        path: '/champs',
        summary: 'Créer un champ',
        description: 'Crée un champ avec son nom, son type, son sous-type et l\'associe à des catégories.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['nom', 'type', 'option'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Couleur'),
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    enum: ['text', 'number', 'select', 'checkbox', 'radio', 'date', 'datetime', 'time', 'email', 'phone', 'url', 'textarea'],
                    example: 'text',
                    description: 'Type du champ'
                ),
                new OA\Property(
                    property: 'option',
                    type: 'string',
                    example: 'Rouge,Vert,Bleu',
                    description: 'Option(s) du champ (plusieurs valeurs séparées par des virgules)'
                ),
                new OA\Property(
                    property: 'subtype',
                    type: 'string',
                    nullable: true,
                    example: 'email',
                    description: 'Sous-type du champ (ex: pour type=select -> single, multiple)'
                ),
                new OA\Property(
                    property: 'category_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2],
                    nullable: true,
                    description: 'IDs des catégories à associer au champ'
                ),
                new OA\Property(
                    property: 'ordre',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                    description: 'Numéro d\'ordre pour l\'affichage (si non fourni, auto-incrémenté)'
                ),
            ],
            example: ['nom' => 'Couleur', 'type' => 'select', 'option' => 'Rouge,Vert,Bleu', 'subtype' => 'single', 'category_ids' => [1, 2]]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Created',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => 'Champ créé avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Couleur',
                    'type' => 'select',
                    'subtype' => 'single',
                    'option' => 'Rouge,Vert,Bleu',
                    // 'ordre' => 1,
                    'is_delete' => false,
                    'categories' => [
                        ['id' => 1, 'nom' => 'Catégorie 1'],
                        ['id' => 2, 'nom' => 'Catégorie 2']
                    ]
                ],
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Bad Request')]
    #[OA\Response(response: 409, description: 'Conflict')]
    public function __invoke(
        Request $request,
        ChampRepository $champRepository,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le nom est présent
        if (empty($payload['nom'])) {
            return $apiResponse->error('Le nom du champ est obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        // ✅ Vérifier que le type est présent
        if (empty($payload['type'])) {
            return $apiResponse->error('Le type du champ est obligatoire.', Response::HTTP_BAD_REQUEST);
        }
        
        // ✅ Vérifier que l'option est présent
        // if (empty($payload['option'])) {
        //     return $apiResponse->error('L\'option du champ est obligatoire.', Response::HTTP_BAD_REQUEST);
        // }

        // ✅ Parser les options séparées par des virgules
        $options = array_map('trim', explode(',', $payload['option']));
        $options = array_filter($options, fn($opt) => !empty($opt));
        $optionString = implode(',', $options);

        // Vérifier l'unicité du nom
        if ($champRepository->existsByNom((string) $payload['nom'])) {
            return $apiResponse->error('Ce nom de champ est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        // Créer le champ
        $champ = new Champ();
        $now = new \DateTimeImmutable();
        $champ->setNom($payload['nom']);
        $champ->setType($payload['type']);
        $champ->setOption($optionString);
        
        // ✅ Gérer le sous-type (optionnel)
        if (isset($payload['subtype'])) {
            $champ->setSubtype($payload['subtype'] !== '' ? $payload['subtype'] : null);
        }
        
        $champ->setCreatedAt($now);
        $champ->setUpdatedAt($now);

        // Gérer les catégories si fournies
        if (!empty($payload['category_ids']) && is_array($payload['category_ids'])) {
            $categoryIds = array_values(array_unique(array_map('intval', $payload['category_ids'])));
            $categories = $categoryRepository->findActiveByIds($categoryIds);

            if (count($categories) !== count($categoryIds)) {
                return $apiResponse->error(
                    'Un ou plusieurs IDs de catégorie sont invalides.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Associer les catégories au champ
            foreach ($categories as $category) {
                $champ->addCategory($category);
            }
        }

        // ✅ Gérer le numéro d'ordre
        if (isset($payload['ordre']) && is_int($payload['ordre']) && $payload['ordre'] >= 0) {
            $champ->setOrdre($payload['ordre']);
        } else {
            // Auto-incrémentation
            $champ->setOrdre($champRepository->getNextOrdre());
        }

        // Valider le champ
        $errors = $validator->validate($champ);
        if (count($errors) > 0) {
            return $apiResponse->error(
                'La validation a échoué.',
                Response::HTTP_BAD_REQUEST,
                json_decode($serializer->serialize($errors, 'json'), true)
            );
        }

        $champRepository->save($champ);
        $data = json_decode(
            $serializer->serialize($champ, 'json', ['groups' => ['champ:detail', 'category:list']]),
            true
        );

        return $apiResponse->success($data, Response::HTTP_CREATED, 'Champ créé avec succès.');
    }
}