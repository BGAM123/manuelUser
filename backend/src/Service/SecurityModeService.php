<?php

namespace App\Service;

use App\Entity\SecurityMode;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationFailedException;
use App\Repository\SecurityModeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SecurityModeService
{
    public function __construct(
        private readonly SecurityModeRepository $securityModeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function create(string $nom, ?string $description = null): SecurityMode
    {
        // Vérifier l'unicité du nom
        if ($this->securityModeRepository->existsByNom($nom)) {
            throw new ValidationFailedException(['nom' => 'Un mode de sécurisation portant ce nom existe déjà.']);
        }

        $securityMode = new SecurityMode();
        $securityMode->setNom($nom);
        $securityMode->setDescription($description);

        $this->validate($securityMode);
        $this->securityModeRepository->save($securityMode, true);

        return $securityMode;
    }

    /**
     * Liste paginée des modes de sécurisation avec recherche
     */
    public function list(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $qb = $this->securityModeRepository->createQueryBuilder('sm')
            ->where('sm.isDelete = false')
            ->orderBy('sm.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Ajout de la recherche
        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(sm.nom) LIKE LOWER(:search) OR LOWER(sm.description) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        $modes = $qb->getQuery()->getResult();

        // Compter le total avec la recherche
        $countQb = $this->securityModeRepository->createQueryBuilder('sm')
            ->select('COUNT(sm.id)')
            ->where('sm.isDelete = false');

        if ($search && !empty(trim($search))) {
            $countQb->andWhere('LOWER(sm.nom) LIKE LOWER(:search) OR LOWER(sm.description) LIKE LOWER(:search)')
                    ->setParameter('search', '%' . trim($search) . '%');
        }

        $total = $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => $modes,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
            'search' => $search,
        ];
    }

    /**
     * ✅ NOUVEAU : Liste tous les modes de sécurisation avec recherche (sans pagination)
     * @param string|null $search
     * @return SecurityMode[]
     */
    public function listAll(?string $search = null): array
    {
        $qb = $this->securityModeRepository->createQueryBuilder('sm')
            ->where('sm.isDelete = false')
            ->orderBy('sm.nom', 'ASC');

        if ($search && !empty(trim($search))) {
            $qb->andWhere('LOWER(sm.nom) LIKE LOWER(:search) OR LOWER(sm.description) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function get(int $id): SecurityMode
    {
        $securityMode = $this->securityModeRepository->find($id);
        
        if (!$securityMode) {
            throw new ResourceNotFoundException('Le mode de sécurisation demandé n\'existe pas.');
        }

        if ($securityMode->isDelete()) {
            throw new ResourceNotFoundException('Ce mode de sécurisation a été supprimé.');
        }

        return $securityMode;
    }

    public function update(int $id, ?string $nom = null, ?string $description = null): SecurityMode
    {
        $securityMode = $this->get($id);

        if ($nom !== null && $nom !== $securityMode->getNom()) {
            // Vérifier l'unicité du nouveau nom
            if ($this->securityModeRepository->existsByNom($nom, $id)) {
                throw new ValidationFailedException(['nom' => 'Un mode de sécurisation portant ce nom existe déjà.']);
            }
            $securityMode->setNom($nom);
        }

        if ($description !== null) {
            $securityMode->setDescription($description);
        }

        $this->validate($securityMode);
        $this->securityModeRepository->save($securityMode, true);

        return $securityMode;
    }

    public function delete(int $id): void
    {
        $securityMode = $this->get($id);
        
        // Suppression logique
        $securityMode->setDelete(true);
        $this->securityModeRepository->save($securityMode, true);
    }

    private function validate(SecurityMode $securityMode): void
    {
        $errors = $this->validator->validate($securityMode);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new ValidationFailedException($errorMessages);
        }
    }
}