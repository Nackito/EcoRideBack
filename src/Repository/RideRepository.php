<?php

namespace App\Repository;

use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ride>
 */
class RideRepository extends ServiceEntityRepository
{
  private const STATUS_WHERE = 'r.status = :status';

  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Ride::class);
  }

  /**
   * Trouve les trajets disponibles selon les critères de recherche
   */
  public function findAvailableRides(?string $origin = null, ?string $destination = null, ?\DateTime $date = null): array
  {
    $qb = $this->createQueryBuilder('r')
      ->andWhere(self::STATUS_WHERE)
      ->andWhere('r.departureTime > :now')
      ->setParameter('status', 'active')
      ->setParameter('now', new \DateTime())
      ->orderBy('r.departureTime', 'ASC');

    if ($origin) {
      $qb->andWhere('r.origin LIKE :origin')
        ->setParameter('origin', '%' . $origin . '%');
    }

    if ($destination) {
      $qb->andWhere('r.destination LIKE :destination')
        ->setParameter('destination', '%' . $destination . '%');
    }

    if ($date) {
      $startOfDay = new \DateTime($date->format('Y-m-d'));
      $startOfDay->setTime(0, 0, 0);
      $endOfDay = new \DateTime($date->format('Y-m-d'));
      $endOfDay->setTime(23, 59, 59);

      $qb->andWhere('r.departureTime BETWEEN :startOfDay AND :endOfDay')
        ->setParameter('startOfDay', $startOfDay)
        ->setParameter('endOfDay', $endOfDay);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Trouve les trajets d'un conducteur
   */
  public function findByDriver(int $driverId): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere('r.driver = :driver')
      ->setParameter('driver', $driverId)
      ->orderBy('r.departureTime', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les trajets récents
   */
  public function findRecentRides(int $limit = 10): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere('r.status = :status')
      ->setParameter('status', 'active')
      ->orderBy('r.createdAt', 'DESC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les trajets par prix maximum
   */
  public function findByMaxPrice(float $maxPrice): array
  {
    return $this->createQueryBuilder('r')
      ->andWhere(self::STATUS_WHERE)
      ->andWhere('r.price <= :maxPrice')
      ->andWhere('r.departureTime > :now')
      ->setParameter('status', 'active')
      ->setParameter('maxPrice', $maxPrice)
      ->setParameter('now', new \DateTime())
      ->orderBy('r.price', 'ASC')
      ->getQuery()
      ->getResult();
  }
}
