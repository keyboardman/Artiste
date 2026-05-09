<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Recherche d'articles par mot-clé sur le titre, la description, la catégorie et le nom de l'artiste.
     *
     * @return Article[]
     */
    public function findByKeyword(string $keyword, int $limit = 50): array
    {
        $like = '%' . addcslashes($keyword, '%_\\') . '%';

        return $this->createQueryBuilder('a')
            ->leftJoin('a.categoryEntity', 'c')
            ->leftJoin('a.user', 'u')
            ->andWhere(
                'a.title LIKE :q OR a.description LIKE :q OR a.category LIKE :q
                 OR c.name LIKE :q
                 OR u.firstname LIKE :q OR u.lastname LIKE :q OR u.username LIKE :q'
            )
            ->setParameter('q', $like)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
