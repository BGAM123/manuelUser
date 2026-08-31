<?php

namespace App\Service;

use App\Entity\Arrondissement;
use App\Entity\Departement;
use App\Entity\Region;
use App\Repository\ArrondissementRepository;
use App\Repository\DepartementRepository;
use App\Repository\RegionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\ORM\EntityManagerInterface;

final class LocationRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RegionRepository $regionRepository,
        private readonly DepartementRepository $departementRepository,
        private readonly ArrondissementRepository $arrondissementRepository,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $regionsPayload
     * @return array<string, mixed>
     */
    public function registerRegions(array $regionsPayload): array
    {
        return $this->runSerializable(function () use ($regionsPayload): array {
            $input = $this->normalizeRegionCreatePayload($regionsPayload);

            if (count($input) < 1 || count($input) > 10) {
                throw new \InvalidArgumentException('Le lot de régions doit contenir entre 1 et 10 éléments.');
            }

            $activeCount = $this->regionRepository->countActiveRegions();
            if (($activeCount + count($input)) > 10) {
                throw new \DomainException('Impossible de créer ces régions: la limite globale de 10 régions actives serait dépassée.');
            }

            $created = 0;
            $restored = 0;
            $entities = [];

            foreach ($input as $item) {
                $nom = trim((string) ($item['nom'] ?? ''));
                if ('' === $nom) {
                    throw new \InvalidArgumentException('Le champ nom est obligatoire pour chaque région.');
                }

                $existing = $this->regionRepository->findOneByNom($nom);
                if (null !== $existing) {
                    if ($existing->isDelete()) {
                        if (($activeCount + $created + $restored + 1) > 10) {
                            throw new \DomainException('Impossible de restaurer cette région: la limite globale de 10 régions actives serait dépassée.');
                        }
                        $existing->setIsDelete(false);
                        if (array_key_exists('code', $item)) {
                            $existing->setCode(null !== $item['code'] ? (string) $item['code'] : null);
                        }
                        $existing->setUpdatedAt(new \DateTimeImmutable());
                        $restored++;
                    }
                    $entities[] = $existing;
                    continue;
                }

                $region = $this->regionRepository->buildRegionFromPayload($item);
                $this->regionRepository->save($region, false);
                $created++;
                $entities[] = $region;
            }

            $this->entityManager->flush();

            return [
                'created' => $created,
                'restored' => $restored,
                'regions' => $entities,
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function importFlatHierarchy(array $rows): array
    {
        return $this->runSerializable(function () use ($rows): array {
            $tripletsSeen = [];
            $ignoredRows = 0;

            $created = [
                'regions' => 0,
                'departements' => 0,
                'arrondissements' => 0,
            ];
            $reused = [
                'regions' => 0,
                'departements' => 0,
                'arrondissements' => 0,
            ];

            $activeRegions = $this->regionRepository->countActiveRegions();
            $restoredRegions = 0;

            foreach ($rows as $index => $row) {
                $regionNom = trim((string) ($row['region'] ?? ''));
                $departementNom = trim((string) ($row['departement'] ?? ''));
                $arrondissementNom = trim((string) ($row['arrondissement'] ?? ''));

                if ('' === $regionNom || '' === $departementNom || '' === $arrondissementNom) {
                    throw new \InvalidArgumentException(sprintf('Ligne %d invalide: region, departement et arrondissement sont obligatoires.', $index + 1));
                }

                $tripletKey = $this->normalizedKey($regionNom) . '|' . $this->normalizedKey($departementNom) . '|' . $this->normalizedKey($arrondissementNom);
                if (isset($tripletsSeen[$tripletKey])) {
                    $ignoredRows++;
                    continue;
                }
                $tripletsSeen[$tripletKey] = true;

                $region = $this->regionRepository->findOneByNom($regionNom);
                if (null === $region) {
                    if (($activeRegions + $created['regions']) >= 10) {
                        throw new \DomainException('Import impossible: la limite globale de 10 régions actives serait dépassée.');
                    }
                    $region = $this->regionRepository->buildRegionFromPayload([
                        'nom' => $regionNom,
                        'code' => $row['region_code'] ?? null,
                    ]);
                    $this->regionRepository->save($region, false);
                    $created['regions']++;
                } else {
                    if ($region->isDelete()) {
                        if (($activeRegions + $created['regions'] + $restoredRegions + 1) > 10) {
                            throw new \DomainException('Import impossible: restauration d\'une région au-delà de la limite globale de 10 régions actives.');
                        }
                        $region->setIsDelete(false);
                        $region->setUpdatedAt(new \DateTimeImmutable());
                        $restoredRegions++;
                    }
                    $reused['regions']++;
                }

                $departement = $this->departementRepository->findOneByNomAndRegion($departementNom, $region);
                if (null === $departement) {
                    $departement = $this->departementRepository->buildDepartementFromPayload([
                        'nom' => $departementNom,
                        'code' => $row['departement_code'] ?? null,
                    ]);
                    $departement->setRegion($region);
                    $this->departementRepository->save($departement, false);
                    $created['departements']++;
                } else {
                    if ($departement->isDelete()) {
                        $departement->setIsDelete(false);
                        $departement->setUpdatedAt(new \DateTimeImmutable());
                    }
                    $reused['departements']++;
                }

                $arrondissement = $this->arrondissementRepository->findOneByNomAndDepartement($arrondissementNom, $departement);
                if (null === $arrondissement) {
                    $arrondissement = $this->arrondissementRepository->buildArrondissementFromPayload([
                        'nom' => $arrondissementNom,
                        'code' => $row['arrondissement_code'] ?? null,
                    ]);
                    $arrondissement->setDepartement($departement);
                    $this->arrondissementRepository->save($arrondissement, false);
                    $created['arrondissements']++;
                } else {
                    if ($arrondissement->isDelete()) {
                        $arrondissement->setIsDelete(false);
                        $arrondissement->setUpdatedAt(new \DateTimeImmutable());
                    }
                    $reused['arrondissements']++;
                }
            }

            $this->entityManager->flush();

            return [
                'created' => $created,
                'reused' => $reused,
                'ignored_rows_in_payload' => $ignoredRows,
            ];
        });
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function runSerializable(callable $callback): array
    {
        $connection = $this->entityManager->getConnection();
        $previousIsolation = $connection->getTransactionIsolation();

        try {
            $connection->setTransactionIsolation(TransactionIsolationLevel::SERIALIZABLE);

            /** @var array<string, mixed> $result */
            $result = $this->entityManager->wrapInTransaction(function () use ($callback): array {
                return $callback();
            });

            return $result;
        } finally {
            $connection->setTransactionIsolation($previousIsolation);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRegionCreatePayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Chaque entrée de lot doit être un objet JSON.');
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    private function normalizedKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (false !== $ascii) {
            $value = mb_strtolower($ascii, 'UTF-8');
        }

        return $value;
    }
}
