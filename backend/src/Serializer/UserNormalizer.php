<?php

// src/Serializer/UserNormalizer.php

namespace App\Serializer;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface
{
    /**
     * @param NormalizerInterface $normalizer Injected native ObjectNormalizer to read entity primitive properties.
     */
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack
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
        //force type safety and prevent normalization of unsupported objects
        if (!$object instanceof User) {
            throw new \InvalidArgumentException('The object must be an instance of User.');
        }

        $context[self::class . '_ALREADY_CALLED'] = true;

        // 1. Delegate core properties normalization to the native platform ObjectNormalizer
        $normalizedData = $this->normalizer->normalize($object, $format, $context);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !is_array($normalizedData)) {
            /** @var array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null $normalizedData */
            return $normalizedData;
        }

        // Ensure any residual hypermedia links are removed.
        if (array_key_exists('_links', $normalizedData)) {
            unset($normalizedData['_links']);
        }

        /** @var array<string, mixed> $normalizedData */
        return $normalizedData;
    }

    /**
     * Validates whether the incoming payload qualifies for User hypermedia enrichment.
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User && !isset($context[self::class . '_ALREADY_CALLED']);
    }

    /**
     * Optimizes normalizer caching routines within the Symfony Dependency Injection component.
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
        ];
    }
}
