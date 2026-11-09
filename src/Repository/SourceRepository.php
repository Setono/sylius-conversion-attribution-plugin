<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;

/**
 * @extends EntityRepository<SourceInterface>
 */
class SourceRepository extends EntityRepository implements SourceRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function findNewestNonDirectPageView(string $clientId, \DateTimeInterface $from, \DateTimeInterface $until): ?SourceInterface
    {
        return $this->findNewestPageView($clientId, $from, $until, Criteria::expr()->neq('source', SourceInterface::DIRECT));
    }

    public function findNewestDirectPageView(string $clientId, \DateTimeInterface $from, \DateTimeInterface $until): ?SourceInterface
    {
        return $this->findNewestPageView($clientId, $from, $until, Criteria::expr()->eq('source', SourceInterface::DIRECT));
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

    private function findNewestPageView(string $clientId, \DateTimeInterface $from, \DateTimeInterface $until, Comparison $sourceCondition): ?SourceInterface
    {
        $pageView = $this->matching(
            Criteria::create()
                ->where(Criteria::expr()->eq('clientId', $clientId))
                ->andWhere($sourceCondition)
                ->andWhere(Criteria::expr()->gte('createdAt', \DateTime::createFromInterface($from)))
                ->andWhere(Criteria::expr()->lte('createdAt', \DateTime::createFromInterface($until)))
                ->orderBy(['createdAt' => Criteria::DESC, 'id' => Criteria::DESC])
                ->setMaxResults(1),
        )->first();

        return false === $pageView ? null : $pageView;
    }

    private function createTrackedBeforeQueryBuilder(\DateTimeInterface $before): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdAt < :before')
            ->setParameter('before', \DateTime::createFromInterface($before), 'datetime')
        ;
    }
}
