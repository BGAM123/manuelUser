<?php

namespace App\Service;

use App\Entity\Asset;
use App\Entity\AssetType;
use App\Entity\Category;
use App\Repository\AssetRepository;
use App\Repository\AssetSubTypeRepository;
use App\Repository\AssetTypeRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Guarantees the internal fallback category and asset type used to preserve
 * valid business references. These records are deliberately hidden from lists.
 */
final class DefaultAssetReferencesService
{
    public const DEFAULT_CATEGORY_NAME = 'Catégorie par défaut';
    public const DEFAULT_ASSET_TYPE_NAME = 'Type de bien par défaut';

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly AssetTypeRepository $assetTypeRepository,
        private readonly AssetRepository $assetRepository,
        private readonly AssetSubTypeRepository $assetSubTypeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveCategory(mixed $reference): ?Category
    {
        $value = $this->normalizeReference($reference);
        if (null === $value) {
            return null;
        }

        if (self::DEFAULT_CATEGORY_NAME === $value) {
            return $this->getDefaultCategory();
        }

        return ctype_digit($value)
            ? $this->categoryRepository->getActiveCategoryById((int) $value)
            : $this->categoryRepository->findActiveByNom($value);
    }

    public function resolveAssetType(mixed $reference): ?AssetType
    {
        $value = $this->normalizeReference($reference);
        if (null === $value) {
            return null;
        }

        if (self::DEFAULT_ASSET_TYPE_NAME === $value) {
            return $this->getDefaultAssetType();
        }

        return ctype_digit($value)
            ? $this->assetTypeRepository->getActiveAssetTypeById((int) $value)
            : $this->assetTypeRepository->findActiveByNom($value);
    }

    public function getDefaultCategory(): Category
    {
        $category = $this->categoryRepository->findDefaultCategory();
        if (null === $category) {
            $category = $this->categoryRepository->findByNom(self::DEFAULT_CATEGORY_NAME);
        }

        if (null === $category) {
            $category = $this->categoryRepository->buildCategoryFromPayload([
                'nom' => self::DEFAULT_CATEGORY_NAME,
                'description' => 'Référence interne utilisée lorsqu’aucune catégorie n’est fournie.',
            ]);
            $category->setIsDefault(true);
            $this->categoryRepository->save($category, false);
            $this->entityManager->flush();

            return $category;
        }

        if (!$category->isDefault()) {
            $category->setIsDefault(true);
        }

        if ($category->isDelete()) {
            $category->setIsDelete(false);
        }

        $category->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $category;
    }

    public function getDefaultAssetType(): AssetType
    {
        $defaultCategory = $this->getDefaultCategory();
        $assetType = $this->assetTypeRepository->findDefaultAssetType();
        if (null === $assetType) {
            $assetType = $this->assetTypeRepository->findByNom(self::DEFAULT_ASSET_TYPE_NAME);
        }

        if (null === $assetType) {
            $assetType = $this->assetTypeRepository->buildAssetTypeFromPayload([
                'nom' => self::DEFAULT_ASSET_TYPE_NAME,
                'description' => 'Référence interne utilisée lorsqu’aucun type de bien n’est fourni.',
            ]);
            $assetType->setCategory($defaultCategory);
            $assetType->setIsDefault(true);
            $this->assetTypeRepository->save($assetType, false);
            $this->entityManager->flush();

            return $assetType;
        }

        if (!$assetType->isDefault()) {
            $assetType->setIsDefault(true);
        }

        if ($assetType->isDelete() || $assetType->getCategory() !== $defaultCategory) {
            $assetType->setIsDelete(false);
            $assetType->setCategory($defaultCategory);
        }

        $assetType->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $assetType;
    }

    public function isDefaultCategory(Category $category): bool
    {
        return $category->isDefault() || self::DEFAULT_CATEGORY_NAME === $category->getNom();
    }

    /**
     * Résout un category_id reçu en filtre de listing : renvoie la catégorie active
     * correspondante, ou la catégorie par défaut si l'id pointe vers une catégorie
     * supprimée (ou inexistante). Ne jamais faire confiance à un category_id qui peut
     * être soft-deleted : centralise cette règle pour tous les endpoints de listing
     * filtrés par catégorie (assets, asset types, inventaire...).
     */
    public function resolveActiveCategoryOrDefault(int $categoryId): Category
    {
        return $this->categoryRepository->getActiveCategoryById($categoryId) ?? $this->getDefaultCategory();
    }

    /**
     * Construit les métadonnées de résolution à exposer dans une réponse de listing,
     * pour que le client sache si l'id demandé a été retombé sur la catégorie par défaut.
     *
     * @return array{requested_id: int, resolved_id: ?int, resolved_nom: ?string, is_fallback: bool}
     */
    public function describeCategoryResolution(int $requestedCategoryId, Category $resolvedCategory): array
    {
        return [
            'requested_id' => $requestedCategoryId,
            'resolved_id' => $resolvedCategory->getId(),
            'resolved_nom' => $resolvedCategory->getNom(),
            'is_fallback' => $resolvedCategory->getId() !== $requestedCategoryId,
        ];
    }

    public function isDefaultAssetType(AssetType $assetType): bool
    {
        return $assetType->isDefault() || self::DEFAULT_ASSET_TYPE_NAME === $assetType->getNom();
    }

    public function reassignAssetTypesToDefaultCategory(Category $category): void
    {
        $defaultCategory = $this->getDefaultCategory();
        if ($category->getId() === $defaultCategory->getId()) {
            return;
        }

        // Réassigner les types de biens à la catégorie par défaut
        foreach ($this->assetTypeRepository->findByCategory($category) as $assetType) {
            $assetType->setCategory($defaultCategory);
            $assetType->setUpdatedAt(new \DateTimeImmutable());
        }

        // Réassigner aussi les Assets qui ont une relation directe à cette catégorie
        // pour éviter des incohérences de sérialisation
        foreach ($this->assetRepository->findByCategory($category) as $asset) {
            $asset->removeCategory($category);
            $asset->addCategory($defaultCategory);
            $asset->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    public function reassignAssetsToDefaultAssetType(AssetType $assetType): void
    {
        $defaultAssetType = $this->getDefaultAssetType();
        foreach ($this->assetRepository->findByAssetType($assetType) as $asset) {
            $asset->removeAssetType($assetType);
            $asset->addAssetType($defaultAssetType);
            $asset->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    /**
     * Réassigne les AssetSubType actifs rattachés à un type de bien vers le type de bien
     * par défaut, avant sa suppression logique — même principe que
     * reassignAssetTypesToDefaultCategory() pour les catégories.
     */
    public function reassignAssetSubTypesToDefaultAssetType(AssetType $assetType): void
    {
        $defaultAssetType = $this->getDefaultAssetType();
        if ($assetType->getId() === $defaultAssetType->getId()) {
            return;
        }

        foreach ($this->assetSubTypeRepository->findActiveByAssetTypeId((int) $assetType->getId()) as $subType) {
            $subType->setAssetType($defaultAssetType);
            $subType->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    public function ensureAssetHasAssetType(Asset $asset): void
    {
        if ($asset->getAssetTypes()->isEmpty()) {
            $asset->addAssetType($this->getDefaultAssetType());
        }
    }

    public function ensureAssetHasCategory(Asset $asset): void
    {
        if ($asset->getCategories()->isEmpty()) {
            $asset->addCategory($this->getDefaultCategory());
        }
    }

    private function normalizeReference(mixed $reference): ?string
    {
        if (null === $reference || is_array($reference) || is_object($reference)) {
            return null;
        }

        $value = trim((string) $reference);

        return '' === $value ? null : $value;
    }
}