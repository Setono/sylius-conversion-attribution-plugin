<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Webmozart\Assert\Assert;

/**
 * @extends EntityRepository<SourceInterface>
 */
class SourceRepository extends EntityRepository implements SourceRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function findPageViewToCredit(array $clientIds, \DateTimeInterface $from, \DateTimeInterface $until): ?SourceInterface
    {
        $pageView = $this->createQueryBuilder('o')
            ->addSelect('CASE WHEN o.source = :direct THEN 1 ELSE 0 END AS HIDDEN isDirect')
            ->andWhere('o.clientId IN (:clientIds)')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :until')
            ->setParameter('direct', SourceInterface::DIRECT)
            ->setParameter('clientIds', $clientIds)
            ->setParameter('from', \DateTime::createFromInterface($from), 'datetime')
            ->setParameter('until', \DateTime::createFromInterface($until), 'datetime')
            ->orderBy('isDirect')
            ->addOrderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        Assert::nullOrIsInstanceOf($pageView, SourceInterface::class);

        return $pageView;
    }

    public function countTrackedBefore(\DateTimeInterface $before): int
    {
        return $this->matching(Criteria::create()->where(Criteria::expr()->lt('createdAt', \DateTime::createFromInterface($before))))->count();
    }

    public function deleteTrackedBefore(\DateTimeInterface $before, int $limit): int
    {
        $ids = $this->createTrackedBeforeQueryBuilder($before)
            ->select('o.id')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        if ([] === $ids) {
            return 0;
        }

        $deleted = $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute()
        ;

        return is_int($deleted) ? $deleted : 0;
    }

    private function createTrackedBeforeQueryBuilder(\DateTimeInterface $before): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdAt < :before')
            ->setParameter('before', \DateTime::createFromInterface($before), 'datetime')
        ;
    }
}
