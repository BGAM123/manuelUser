<?php

namespace App\Service;

use App\Entity\Champ;
use App\Entity\Input;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\ChampRepository;
use App\Repository\InputRepository;
use App\Service\ForceDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InputService
{
    public function __construct(
        private readonly InputRepository $inputRepository,
        private readonly ChampRepository $champRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ForceDeleteService $forceDeleteService,
    ) {
    }

    public function create(int $champId, string $valeur): Input
    {
        $champ = $this->champRepository->getActiveById($champId);
        if (!$champ) {
            throw new ResourceNotFoundException('Le champ spécifié n\'existe pas.');
        }

        $input = new Input();
        $input->setChamp($champ);
        $input->setValeur($valeur);

        $this->validate($input);
        $this->inputRepository->save($input);

        return $input;
    }

    public function list(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $items = $this->inputRepository->findPaginated($page, $limit, $search);
        $total = $this->inputRepository->countAll($search);

        return [
            'items' => $items,  // ✅ La clé est 'items'
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    public function listByChamp(int $champId, int $page = 1, int $limit = 20): array
    {
        $champ = $this->champRepository->getActiveById($champId);
        if (!$champ) {
            throw new ResourceNotFoundException('Le champ spécifié n\'existe pas.');
        }

        $items = $this->inputRepository->findPaginatedByChampId($champId, $page, $limit);
        $total = $this->inputRepository->countByChampId($champId);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    public function get(int $id): Input
    {
        $input = $this->inputRepository->findWithChamp($id);
        if (!$input || $input->isDelete()) {
            throw new ResourceNotFoundException('La valeur de champ demandée n\'existe pas.');
        }
        return $input;
    }

    public function update(int $id, ?string $valeur = null): Input
    {
        $input = $this->get($id);

        if ($valeur !== null) {
            $input->setValeur($valeur);
        }

        $this->validate($input);
        $this->inputRepository->save($input);

        return $input;
    }

    public function delete(int $id): void
    {
        $input = $this->get($id);
        $this->inputRepository->softDelete($input);
    }

    /**
     * Suppression définitive : agit même sur une valeur déjà soft-deleted, donc find()
     * plutôt que get() qui rejette les lignes déjà supprimées.
     */
    public function deleteForced(int $id): void
    {
        $input = $this->inputRepository->find($id);
        if (!$input) {
            throw new ResourceNotFoundException('La valeur de champ demandée n\'existe pas.');
        }

        $this->forceDeleteService->delete($input);
    }

    public function restore(int $id): void
    {
        $input = $this->inputRepository->find($id);
        if (!$input) {
            throw new ResourceNotFoundException('La valeur de champ demandée n\'existe pas.');
        }
        $this->inputRepository->restore($input);
    }

    private function validate(Input $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}