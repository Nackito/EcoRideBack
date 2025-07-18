<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Booking::class);
  }

  /**
   * Trouve les réservations d'un passager
   */
  public function findByPassenger(int $passengerId): array
  {
    return $this->createQueryBuilder('b')
      ->andWhere('b.passenger = :passenger')
      ->setParameter('passenger', $passengerId)
      ->orderBy('b.createdAt', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les réservations pour les trajets d'un conducteur
   */
  public function findByDriver(int $driverId): array
  {
    return $this->createQueryBuilder('b')
      ->join('b.ride', 'r')
      ->andWhere('r.driver = :driver')
      ->setParameter('driver', $driverId)
      ->orderBy('b.createdAt', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les réservations en attente
   */
  public function findPendingBookings(): array
  {
    return $this->createQueryBuilder('b')
      ->andWhere('b.status = :status')
      ->setParameter('status', 'pending')
      ->orderBy('b.createdAt', 'ASC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les réservations confirmées pour un trajet
   */
  public function findConfirmedByRide(int $rideId): array
  {
    return $this->createQueryBuilder('b')
      ->andWhere('b.ride = :ride')
      ->andWhere('b.status = :status')
      ->setParameter('ride', $rideId)
      ->setParameter('status', 'confirmed')
      ->getQuery()
      ->getResult();
  }
}
