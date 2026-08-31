<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Repository\AssetRepository;
use App\Repository\AssetSubTypeRepository;
use App\Repository\AssetTypeRepository;
use App\Repository\CategoryRepository;
use App\Service\DefaultAssetReferencesService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Couvre la résolution "category_id supprimé -> catégorie active ou catégorie par
 * défaut" utilisée par les listings filtrés par catégorie (assets, asset types,
 * inventaire). Ne dépend pas de la base : les repositories sont mockés.
 */
final class DefaultAssetReferencesServiceCategoryResolutionTest extends TestCase
{
    private function makeCategory(int $id, string $nom, bool $isDelete = false, bool $isDefault = false): Category
    {
        $category = new Category();
        $category->setNom($nom);
        $category->setIsDelete($isDelete);
        $category->setIsDefault($isDefault);

        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    private function makeService(CategoryRepository $categoryRepository): DefaultAssetReferencesService
    {
        return new DefaultAssetReferencesService(
            $categoryRepository,
            $this->createStub(AssetTypeRepository::class),
            $this->createStub(AssetRepository::class),
            $this->createStub(AssetSubTypeRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    /** CAS 1 / CAS 3 : catégorie active -> résolution vers elle-même, pas de fallback. */
    public function testResolvesToTheActiveCategoryWhenNotDeleted(): void
    {
        $active = $this->makeCategory(5, 'Matériel informatique');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects($this->once())
            ->method('getActiveCategoryById')
            ->with(5)
            ->willReturn($active);
        $categoryRepository->expects($this->never())->method('findDefaultCategory');

        $service = $this->makeService($categoryRepository);
        $resolved = $service->resolveActiveCategoryOrDefault(5);

        $this->assertSame($active, $resolved);

        $meta = $service->describeCategoryResolution(5, $resolved);
        $this->assertSame(5, $meta['requested_id']);
        $this->assertSame(5, $meta['resolved_id']);
        $this->assertSame('Matériel informatique', $meta['resolved_nom']);
        $this->assertFalse($meta['is_fallback']);
    }

    /** CAS 2 / CAS 4 / CAS 7 : catégorie supprimée -> fallback vers la catégorie par défaut. */
    public function testFallsBackToDefaultCategoryWhenRequestedCategoryIsDeleted(): void
    {
        $default = $this->makeCategory(99, DefaultAssetReferencesService::DEFAULT_CATEGORY_NAME, false, true);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects($this->once())
            ->method('getActiveCategoryById')
            ->with(5)
            ->willReturn(null); // catégorie 5 soft-deletée : getActiveCategoryById filtre is_delete=false
        $categoryRepository->expects($this->once())
            ->method('findDefaultCategory')
            ->willReturn($default);

        $service = $this->makeService($categoryRepository);
        $resolved = $service->resolveActiveCategoryOrDefault(5);

        $this->assertSame($default, $resolved);
        $this->assertNotSame(5, $resolved->getId());

        $meta = $service->describeCategoryResolution(5, $resolved);
        $this->assertSame(5, $meta['requested_id']);
        $this->assertSame(99, $meta['resolved_id']);
        $this->assertTrue($meta['is_fallback']);
    }

    /** CAS 7 : id inexistant (jamais créé) -> même comportement que supprimé, fallback par défaut. */
    public function testFallsBackToDefaultCategoryWhenRequestedIdDoesNotExist(): void
    {
        $default = $this->makeCategory(99, DefaultAssetReferencesService::DEFAULT_CATEGORY_NAME, false, true);

        $categoryRepository = $this->createStub(CategoryRepository::class);
        $categoryRepository->method('getActiveCategoryById')->willReturn(null);
        $categoryRepository->method('findDefaultCategory')->willReturn($default);

        $service = $this->makeService($categoryRepository);
        $resolved = $service->resolveActiveCategoryOrDefault(999999);

        $this->assertSame(99, $resolved->getId());
    }

    /** CAS 9 : deux catégories actives distinctes ne se contaminent pas l'une l'autre. */
    public function testTwoDifferentActiveCategoriesResolveIndependently(): void
    {
        $catA = $this->makeCategory(1, 'Véhicules');
        $catB = $this->makeCategory(2, 'Matériel informatique');

        $categoryRepository = $this->createStub(CategoryRepository::class);
        $categoryRepository->method('getActiveCategoryById')->willReturnMap([
            [1, $catA],
            [2, $catB],
        ]);

        $service = $this->makeService($categoryRepository);

        $resolvedA = $service->resolveActiveCategoryOrDefault(1);
        $resolvedB = $service->resolveActiveCategoryOrDefault(2);

        $this->assertSame($catA, $resolvedA);
        $this->assertSame($catB, $resolvedB);
        $this->assertNotSame($resolvedA->getId(), $resolvedB->getId());
    }
}
