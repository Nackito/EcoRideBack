<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
  private const STATUT_CONDITION = 'r.statut = :statut';

  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Review::class);
  }

  /**
   * Trouve tous les avis validés
   */
  public function findValidatedReviews(): array
  {
    return $this->createQueryBuilder('r')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', 'valide')
      ->orderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve tous les avis en attente de validation
   */
  public function findPendingReviews(): array
  {
    return $this->createQueryBuilder('r')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', 'en_attente')
      ->orderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve tous les avis rejetés
   */
  public function findRejectedReviews(): array
  {
    return $this->createQueryBuilder('r')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', 'rejete')
      ->orderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les avis par statut
   */
  public function findByStatut(string $statut): array
  {
    return $this->createQueryBuilder('r')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', $statut)
      ->orderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les avis avec une note spécifique
   */
  public function findByNote(int $note): array
  {
    return $this->createQueryBuilder('r')
      ->where('r.note = :note')
      ->setParameter('note', $note)
      ->orderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Compte le nombre d'avis par statut
   */
  public function countByStatut(string $statut): int
  {
    return $this->createQueryBuilder('r')
      ->select('COUNT(r.id)')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', $statut)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Calcule la note moyenne de tous les avis validés
   */
  public function getAverageRating(): ?float
  {
    $result = $this->createQueryBuilder('r')
      ->select('AVG(r.note) as avgNote')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', 'valide')
      ->getQuery()
      ->getSingleScalarResult();

    return $result ? round((float) $result, 2) : null;
  }

  /**
   * Trouve les derniers avis validés
   */
  public function findLatestValidatedReviews(int $limit = 10): array
  {
    return $this->createQueryBuilder('r')
      ->where(self::STATUT_CONDITION)
      ->setParameter('statut', 'valide')
      ->orderBy('r.id', 'DESC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();
  }

  /**
   * Trouve les avis avec une note minimale (pour les bons avis)
   */
  public function findGoodReviews(int $minNote = 4): array
  {
    return $this->createQueryBuilder('r')
      ->where('r.note >= :minNote')
      ->andWhere(self::STATUT_CONDITION)
      ->setParameter('minNote', $minNote)
      ->setParameter('statut', 'valide')
      ->orderBy('r.note', 'DESC')
      ->addOrderBy('r.id', 'DESC')
      ->getQuery()
      ->getResult();
  }
}
