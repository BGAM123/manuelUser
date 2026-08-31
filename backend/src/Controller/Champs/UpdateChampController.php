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
final class UpdateChampController extends AbstractController
{
    #[Route('/{id}', name: 'app_champ_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/champs/{id}',
        summary: 'Mettre à jour un champ',
        description: 'Met à jour le nom, le type, le sous-type d\'un champ et peut modifier ses catégories associées.'
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Couleur primaire'),
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
                    nullable: true,
                    example: 'Rouge,Vert,Bleu',
                    description: 'Option(s) du champ (plusieurs valeurs séparées par des virgules)'
                ),
                new OA\Property(
                    property: 'subtype',
                    type: 'string',
                    nullable: true,
                    example: 'email',
                    description: 'Sous-type du champ'
                ),
                new OA\Property(
                    property: 'category_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2],
                    nullable: true,
                    description: 'IDs des catégories à associer au champ (remplace la liste existante)'
                ),
                new OA\Property(
                    property: 'ordre',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                    description: 'Numéro d\'ordre pour l\'affichage (si non fourni, auto-incrémenté)'
                ),
            ],

            example: ['nom' => 'Couleur primaire', 'type' => 'select', 'option' => 'Rouge,Vert,Bleu,Jaune', 'subtype' => 'multiple', 'category_ids' => [1, 2]]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 200,
                'message' => 'Champ mis à jour avec succès.',
                'data' => [
                    'id' => 1,
                    'nom' => 'Couleur primaire',
                    'type' => 'select',
                    'subtype' => 'multiple',
                    'option' => 'Rouge,Vert,Bleu,Jaune',
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
        Champ $champ,
        Request $request,
        ChampRepository $champRepository,
        CategoryRepository $categoryRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse
    ): JsonResponse {
        if ($champ->isDelete()) {
            return $apiResponse->error('Ce champ est supprimé.', Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Charge JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Vérifier l'unicité du nom (si modifié)
        if (!empty($payload['nom']) && $champRepository->existsByNom((string) $payload['nom'], $champ->getId())) {
            return $apiResponse->error('Ce nom de champ est déjà utilisé.', Response::HTTP_CONFLICT);
        }

        // Mettre à jour le nom uniquement
        if (isset($payload['nom'])) {
            $champ->setNom($payload['nom']);
            $champ->setUpdatedAt(new \DateTimeImmutable());
        }

        // ✅ Mettre à jour le type
        // if (isset($payload['type'])) {
        //     if (empty($payload['type'])) {
        //         return $apiResponse->error('Le type du champ ne peut pas être vide.', Response::HTTP_BAD_REQUEST);
        //     }
        //     $champ->setType($payload['type']);
        //     $champ->setUpdatedAt(new \DateTimeImmutable());
        // }
        
        // ✅ Mettre à jour l'option
        if (array_key_exists('option', $payload)) {
            if ($payload['option'] !== '' && $payload['option'] !== null) {
                // Parser les options séparées par des virgules
                $options = array_map('trim', explode(',', $payload['option']));
                $options = array_filter($options, fn($opt) => !empty($opt));
                $optionString = implode(',', $options);
                $champ->setOption($optionString);
            } else {
                $champ->setOption(null);
            }
            $champ->setUpdatedAt(new \DateTimeImmutable());
        }

        // ✅ Gérer le numéro d'ordre
        if (array_key_exists('ordre', $payload)) {
            if (is_int($payload['ordre']) && $payload['ordre'] >= 0) {
                $champ->setOrdre($payload['ordre']);
                $champ->setUpdatedAt(new \DateTimeImmutable());
            } else {
                return $apiResponse->error(
                    'Le numéro d\'ordre doit être un entier positif.',
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // ✅ Mettre à jour le sous-type
        if (array_key_exists('subtype', $payload)) {
            $champ->setSubtype($payload['subtype'] !== '' && $payload['subtype'] !== null ? $payload['subtype'] : null);
            $champ->setUpdatedAt(new \DateTimeImmutable());
        }

        // Gérer les catégories si fournies
        if (array_key_exists('category_ids', $payload)) {
            if (is_null($payload['category_ids'])) {
                // Si null, on vide les catégories
                $champ->clearCategories();
            } elseif (is_array($payload['category_ids'])) {
                $categoryIds = array_values(array_unique(array_map('intval', $payload['category_ids'])));

                if (!empty($categoryIds)) {
                    $categories = $categoryRepository->findActiveByIds($categoryIds);

                    if (count($categories) !== count($categoryIds)) {
                        return $apiResponse->error(
                            'Un ou plusieurs IDs de catégorie sont invalides.',
                            Response::HTTP_BAD_REQUEST
                        );
                    }
                } else {
                    $categories = [];
                }

                // Remplacer les catégories
                $champ->clearCategories();
                foreach ($categories as $category) {
                    $champ->addCategory($category);
                }
            } else {
                return $apiResponse->error(
                    'Le champ category_ids doit être un tableau ou null.',
                    Response::HTTP_BAD_REQUEST
                );
            }
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

        return $apiResponse->success($data, Response::HTTP_OK, 'Champ mis à jour avec succès.');
    }
}