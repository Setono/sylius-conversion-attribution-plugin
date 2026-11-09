<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Webmozart\Assert\Assert;

/**
 * @extends EntityRepository<AttributionInterface>
 */
class AttributionRepository extends EntityRepository implements AttributionRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function findOneByOrder(BaseOrderInterface $order): ?AttributionInterface
    {
        return $this->findOneBy(['order' => $order]);
    }

    public function findOrdersToAttribute(int $limit, ?\DateTimeInterface $completedSince = null): array
    {
        // The order class is the application's, which Doctrine resolved the attribution's association to
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('o')
            ->from($this->getClassMetadata()->getAssociationTargetClass('order'), 'o')
            ->andWhere('o.checkoutCompletedAt IS NOT NULL')
            ->andWhere('o.clientId IS NOT NULL')
            ->andWhere(sprintf('NOT EXISTS (SELECT a.id FROM %s a WHERE a.order = o)', $this->getEntityName()))
            ->orderBy('o.id')
            ->setMaxResults($limit)
        ;

        if (null !== $completedSince) {
            $queryBuilder
                ->andWhere('o.checkoutCompletedAt >= :completedSince')
                ->setParameter('completedSince', \DateTime::createFromInterface($completedSince), 'datetime')
            ;
        }

        $orders = $queryBuilder->getQuery()->getResult();
        Assert::isList($orders);
        Assert::allIsInstanceOf($orders, OrderInterface::class);

        return $orders;
    }
}
