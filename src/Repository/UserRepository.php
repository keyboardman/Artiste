<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return User[] */
    public function findCreatedSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return User[] */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'utilisateurs par mot-clé sur prénom, nom et pseudo.
     * Volontairement n'inclut pas l'email pour éviter d'exposer ce champ via l'URL.
     *
     * @return User[]
     */
    public function findByKeyword(string $keyword, int $limit = 20): array
    {
        $like = '%' . addcslashes($keyword, '%_\\') . '%';

        return $this->createQueryBuilder('u')
            ->andWhere('u.firstname LIKE :q OR u.lastname LIKE :q OR u.username LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
