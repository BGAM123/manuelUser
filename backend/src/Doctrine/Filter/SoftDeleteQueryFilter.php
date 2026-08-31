<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Applique le filtre is_delete=false|true|all à un QueryBuilder, de façon uniforme
 * sur tous les listings. Le nom de propriété Doctrine (isDelete) est constant même
 * quand le setter PHP diffère d'une entité à l'autre (setIsDelete vs setDelete).
 */
final class SoftDeleteQueryFilter
{
    public static function apply(QueryBuilder $qb, string $alias, ?string $rawValue, string $field = 'isDelete'): void
    {
        $value = null !== $rawValue ? trim($rawValue) : 'false';
        if ('' === $value) {
            $value = 'false';
        }

        if ('all' === strtolower($value)) {
            return;
        }

        $isDelete = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $qb->andWhere(sprintf('%s.%s = :isDelete', $alias, $field))
            ->setParameter('isDelete', $isDelete);
    }
}
