<?php

/**
 * ProjectNormalizer - Normalise les entités Project pour les réponses API.
 *
 * Gère la sérialisation des projets et affiche les responsables (utilisateurs liés)
 * au lieu d'un simple champ texte.
 */

namespace App\Serializer;

use App\Entity\Project;
use App\Service\ProjectExerciseService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProjectNormalizer implements NormalizerInterface
{
    /**
     * @param NormalizerInterface $normalizer Injected native ObjectNormalizer to read entity primitive properties.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack,
        private readonly ProjectExerciseService $exerciseService
    ) {
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // Force type safety and prevent normalization of unsupported objects
        if (!$object instanceof Project) {
            throw new \InvalidArgumentException('The object must be an instance of Project.');
        }

        $context[self::class . '_ALREADY_CALLED'] = true;

        // 1. Delegate core properties normalization to the native platform ObjectNormalizer
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !is_array($normalizedData)) {
            /** @var array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null $normalizedData */
            return $normalizedData;
        }

        // 2. Enrichir les responsables avec les informations utilisateurs
        $responsables = [];
        foreach ($object->getUsers() as $user) {
            $responsables[] = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                // 'email' => $user->getEmail(),
            ];
        }
        // $normalizedData['responsables'] = $responsables;

        // 3. Appliquer la règle métier pour l'exercice
        // Si projet EN COURS et exercice dépassé → retourner année actuelle
        // Sinon → retourner exercice enregistré
        $normalizedData['exercice'] = $this->exerciseService->getExerciseForApi($object);

        // Ensure any residual hypermedia links are removed.
        if (array_key_exists('_links', $normalizedData)) {
            unset($normalizedData['_links']);
        }

        /** @var array<string, mixed> $normalizedData */
        return $normalizedData;
    }

    /**
     * Validates whether the incoming payload qualifies for Project normalization.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Project && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    /**
     * Optimizes normalizer caching routines within the Symfony Dependency Injection component.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Project::class => true,
        ];
    }
}
