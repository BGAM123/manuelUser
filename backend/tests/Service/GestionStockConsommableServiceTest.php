<?php

namespace App\Tests\Service;

use App\Repository\ConsumableEntryRepository;
use App\Repository\ConsumableRepository;
use App\Repository\ConsumableTransferRepository;
use App\Service\GestionStockConsommableService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class GestionStockConsommableServiceTest extends TestCase
{
    public function testGetStockSubstractsQuantityConsumed(): void
    {
        $consumableRepository = $this->createMock(ConsumableRepository::class);
        $entryRepository = $this->createMock(ConsumableEntryRepository::class);
        $transferRepository = $this->createMock(ConsumableTransferRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entryRepository->method('sumQuantiteByConsumableAndService')->willReturn('0');
        $transferRepository->method('sumQuantiteReceivedByConsumableAndService')->willReturn('100');
        $transferRepository->method('sumQuantiteSentByConsumableAndService')->willReturn('0');
        $transferRepository->method('sumSortiesByConsumableAndService')->willReturn('0');
        $transferRepository->expects($this->once())
            ->method('sumQuantityConsumedByConsumableAndService')
            ->with(12, 7)
            ->willReturn('20');

        $service = new GestionStockConsommableService(
            $consumableRepository,
            $entryRepository,
            $transferRepository,
            $entityManager
        );

        $this->assertSame('80', $service->getStock(12, 7));
    }
}
