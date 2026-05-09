<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /** @return array<int, array{title: string, image: ?string, articleId: ?int, totalQty: int, totalRevenue: float}> */
    public function findTopArticles(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select(
                'i.title AS title',
                'i.image AS image',
                'IDENTITY(i.article) AS articleId',
                'SUM(i.quantity) AS totalQty',
                'SUM(i.unitPrice * i.quantity) AS totalRevenue'
            )
            ->join('i.order', 'o')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered])
            ->groupBy('i.title, i.image, articleId')
            ->orderBy('totalQty', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn ($r) => [
            'title'        => (string) $r['title'],
            'image'        => $r['image'] !== null ? (string) $r['image'] : null,
            'articleId'    => $r['articleId'] !== null ? (int) $r['articleId'] : null,
            'totalQty'     => (int) $r['totalQty'],
            'totalRevenue' => (float) $r['totalRevenue'],
        ], $rows);
    }
}
