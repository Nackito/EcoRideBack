<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(type: Types::TEXT)]
  #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide')]
  #[Assert\Length(min: 10, max: 1000, minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères', maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères')]
  private ?string $commentaire = null;

  #[ORM\Column(type: Types::SMALLINT)]
  #[Assert\NotBlank(message: 'La note ne peut pas être vide')]
  #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}')]
  private ?int $note = null;

  #[ORM\Column(length: 50)]
  #[Assert\NotBlank(message: 'Le statut ne peut pas être vide')]
  #[Assert\Choice(choices: ['en_attente', 'valide', 'rejete'], message: 'Le statut doit être : en_attente, valide ou rejete')]
  private ?string $statut = null;

  public function __construct()
  {
    $this->statut = 'en_attente'; // Statut par défaut
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getCommentaire(): ?string
  {
    return $this->commentaire;
  }

  public function setCommentaire(string $commentaire): static
  {
    $this->commentaire = $commentaire;

    return $this;
  }

  public function getNote(): ?int
  {
    return $this->note;
  }

  public function setNote(int $note): static
  {
    $this->note = $note;

    return $this;
  }

  public function getStatut(): ?string
  {
    return $this->statut;
  }

  public function setStatut(string $statut): static
  {
    $this->statut = $statut;

    return $this;
  }

  /**
   * Vérifie si l'avis est validé
   */
  public function isValide(): bool
  {
    return $this->statut === 'valide';
  }

  /**
   * Vérifie si l'avis est en attente
   */
  public function isEnAttente(): bool
  {
    return $this->statut === 'en_attente';
  }

  /**
   * Vérifie si l'avis est rejeté
   */
  public function isRejete(): bool
  {
    return $this->statut === 'rejete';
  }

  /**
   * Valider l'avis
   */
  public function valider(): static
  {
    $this->statut = 'valide';

    return $this;
  }

  /**
   * Rejeter l'avis
   */
  public function rejeter(): static
  {
    $this->statut = 'rejete';

    return $this;
  }

  /**
   * Remettre en attente
   */
  public function mettreEnAttente(): static
  {
    $this->statut = 'en_attente';

    return $this;
  }

  /**
   * Retourne un résumé de l'avis avec étoiles
   */
  public function getSummary(): string
  {
    $stars = str_repeat('⭐', $this->note);
    $statusLabel = match ($this->statut) {
      'valide' => '✅',
      'rejete' => '❌',
      'en_attente' => '⏳',
      default => '❓'
    };

    return sprintf('%s %s (%s)', $stars, substr($this->commentaire, 0, 50) . '...', $statusLabel);
  }

  public function __toString(): string
  {
    return $this->getSummary();
  }
}
