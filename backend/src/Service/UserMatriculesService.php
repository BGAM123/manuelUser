<?php

namespace App\Service;

use App\Repository\UserRepository;

final class UserMatriculesService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PaginationFactory $paginationFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaginated(int $page, int $limit, ?string $search = null): array
    {
        $result = $this->userRepository->findMatriculesPaginated($page, $limit, $search);
        $total = $this->userRepository->countMatricules($search);

        $items = array_map(static fn ($user): array => [
            'id' => $user->getId(),
            'matricule' => $user->getMatricule(),
        ], $result);

        return $this->paginationFactory->createPaginatedResponse($items, $page, $limit, $total);
    }
}
