<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Role::class);
  }

  /**
   * Trouve un rôle par son libellé
   */
  public function findByLibelle(string $libelle): ?Role
  {
    return $this->findOneBy(['libelle' => $libelle]);
  }

  /**
   * Trouve tous les rôles triés par libellé
   */
  public function findAllOrderedByLibelle(): array
  {
    return $this->createQueryBuilder('r')
      ->orderBy('r.libelle', 'ASC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les rôles par pattern de libellé (ex: 'ADMIN%')
   */
  public function findByLibellePattern(string $pattern): array
  {
    return $this->createQueryBuilder('r')
      ->where('r.libelle LIKE :pattern')
      ->setParameter('pattern', $pattern)
      ->orderBy('r.libelle', 'ASC')
      ->getQuery()
      ->getResult();
  }
}
