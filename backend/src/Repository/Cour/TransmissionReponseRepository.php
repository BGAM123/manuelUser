<?php

namespace App\Repository\Cour;

use App\Entity\Cour\TransmissionReponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransmissionReponse>
 */
class TransmissionReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransmissionReponse::class);
    }

    public function countActiveByReponseIdExcludingId(int $reponseId, int $excludeTransmissionReponseId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT COUNT(*) 
                FROM cour_transmission_reponse 
                WHERE JSON_CONTAINS(id_reponses, :reponseId)
                  AND is_delete = 0
                  AND id != :excludeId';

        return (int) $conn->fetchOne($sql, [
            'reponseId' => json_encode($reponseId),
            'excludeId' => $excludeTransmissionReponseId,
        ]);
    }

    public function findLatestByCourrierInterneId(int $courrierInterneId): ?TransmissionReponse
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id 
                FROM cour_transmission_reponse 
                WHERE JSON_CONTAINS(id_courrier_interne, :courrierInterneId) 
                ORDER BY created_at DESC 
                LIMIT 1';

        $id = $conn->fetchOne($sql, [
            'courrierInterneId' => json_encode($courrierInterneId),
        ]);

        if (!$id) {
            return null;
        }

        return $this->find((int) $id);
    }

    public function findLatestByCourrierInterneIdAndRedacteurId(int $courrierInterneId, int $redacteurId): ?TransmissionReponse
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id 
                FROM cour_transmission_reponse 
                WHERE JSON_CONTAINS(id_courrier_interne, :courrierInterneId) 
                  AND id_redacteur = :redacteurId
                  AND is_delete = 0
                ORDER BY created_at DESC 
                LIMIT 1';

        $id = $conn->fetchOne($sql, [
            'courrierInterneId' => json_encode($courrierInterneId),
            'redacteurId' => $redacteurId,
        ]);

        if (!$id) {
            return null;
        }

        return $this->find((int) $id);
    }

    public function findLatestByReponseId(int $reponseId): ?TransmissionReponse
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id 
                FROM cour_transmission_reponse 
                WHERE JSON_CONTAINS(id_reponses, :reponseId) 
                ORDER BY created_at DESC 
                LIMIT 1';

        $id = $conn->fetchOne($sql, [
            'reponseId' => json_encode($reponseId),
        ]);

        if (!$id) {
            return null;
        }

        return $this->find((int) $id);
    }

    public function findLatestByReponseIdAndRedacteurId(int $reponseId, int $redacteurId): ?TransmissionReponse
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT id 
                FROM cour_transmission_reponse 
                WHERE JSON_CONTAINS(id_reponses, :reponseId) 
                  AND id_redacteur = :redacteurId
                  AND is_delete = 0
                ORDER BY created_at DESC 
                LIMIT 1';

        $id = $conn->fetchOne($sql, [
            'reponseId' => json_encode($reponseId),
            'redacteurId' => $redacteurId,
        ]);

        if (!$id) {
            return null;
        }

        return $this->find((int) $id);
    }
}
