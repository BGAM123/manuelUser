<?php

namespace App\Service;

use App\Entity\ExitType;
use App\Exception\ValidationFailedException;
use App\Repository\ExitTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ExitTypeService
{
    public function __construct(
        private readonly ExitTypeRepository $exitTypeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function create(array $payload): ExitType
    {
        // 1. Vérifier les champs obligatoires
        if (empty($payload['nom'])) {
            throw new ValidationFailedException(['nom' => 'Le nom est requis.']);
        }

        if (empty($payload['code'])) {
            throw new ValidationFailedException(['code' => 'Le code est requis.']);
        }

        // 2. Vérifier l'unicité du nom (insensible à la casse)
        $existingByName = $this->exitTypeRepository->findOneBy(['nom' => $payload['nom']]);
        if ($existingByName) {
            throw new ValidationFailedException(['nom' => 'Un type de sortie avec ce nom existe déjà.']);
        }

        // 3. Vérifier l'unicité du code (insensible à la casse)
        $existingByCode = $this->exitTypeRepository->findOneBy(['code' => $payload['code']]);
        if ($existingByCode) {
            throw new ValidationFailedException(['code' => 'Un type de sortie avec ce code existe déjà.']);
        }

        $exitType = new ExitType();
        $this->applyPayload($exitType, $payload);
        $this->validate($exitType);

        $this->exitTypeRepository->save($exitType);
        return $exitType;
    }

    public function update(ExitType $exitType, array $payload): ExitType
    {
        // 1. Vérifier l'unicité du nom (sauf pour le même type)
        if (isset($payload['nom'])) {
            $existingByName = $this->exitTypeRepository->findOneBy(['nom' => $payload['nom']]);
            if ($existingByName && $existingByName->getId() !== $exitType->getId()) {
                throw new ValidationFailedException(['nom' => 'Un type de sortie avec ce nom existe déjà.']);
            }
        }

        // 2. Vérifier l'unicité du code (sauf pour le même type)
        if (isset($payload['code'])) {
            $existingByCode = $this->exitTypeRepository->findOneBy(['code' => $payload['code']]);
            if ($existingByCode && $existingByCode->getId() !== $exitType->getId()) {
                throw new ValidationFailedException(['code' => 'Un type de sortie avec ce code existe déjà.']);
            }
        }

        $this->applyPayload($exitType, $payload);
        $this->validate($exitType);

        $this->exitTypeRepository->save($exitType);
        return $exitType;
    }

    public function delete(ExitType $exitType): void
    {
        // Vérifier si le type de sortie est utilisé par des sorties
        $assetExitsCount = $exitType->getAssetExits()->count();
        if ($assetExitsCount > 0) {
            throw new ValidationFailedException(
                ['general' => 'Ce type de sortie est utilisé par ' . $assetExitsCount . ' sortie(s). Impossible de le supprimer.']
            );
        }

        $exitType->setIsDelete(true);
        $this->exitTypeRepository->save($exitType);
    }

    private function applyPayload(ExitType $exitType, array $payload): void
    {
        if (isset($payload['nom'])) {
            $exitType->setNom(trim($payload['nom']));
        }
        if (isset($payload['code'])) {
            $exitType->setCode(strtoupper(trim($payload['code']))); // Mettre en majuscules
        }
        if (isset($payload['description'])) {
            $exitType->setDescription(trim($payload['description']));
        }
        if (isset($payload['isActive'])) {
            $exitType->setIsActive((bool) $payload['isActive']);
        }
        if (isset($payload['beneficiaire'])) {
            $exitType->setBeneficiaire((bool) $payload['beneficiaire']);
        }
    }

    private function validate(ExitType $exitType): void
    {
        $errors = $this->validator->validate($exitType);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}