<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'bookings')]
  #[ORM\JoinColumn(nullable: false)]
  private ?User $passenger = null;

  #[ORM\ManyToOne(inversedBy: 'bookings')]
  #[ORM\JoinColumn(nullable: false)]
  private ?Ride $ride = null;

  #[ORM\Column]
  #[Assert\Range(min: 1, max: 8)]
  private ?int $numberOfSeats = 1;

  #[ORM\Column(length: 50)]
  #[Assert\Choice(choices: ['pending', 'confirmed', 'cancelled', 'completed'])]
  private ?string $status = 'pending';

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  #[Assert\Length(max: 500)]
  private ?string $message = null;

  #[ORM\Column]
  private ?\DateTimeImmutable $createdAt = null;

  #[ORM\Column]
  private ?\DateTimeImmutable $updatedAt = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
  private ?string $totalPrice = null;

  public function __construct()
  {
    $this->createdAt = new \DateTimeImmutable();
    $this->updatedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getPassenger(): ?User
  {
    return $this->passenger;
  }

  public function setPassenger(?User $passenger): static
  {
    $this->passenger = $passenger;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getRide(): ?Ride
  {
    return $this->ride;
  }

  public function setRide(?Ride $ride): static
  {
    $this->ride = $ride;
    $this->updatedAt = new \DateTimeImmutable();

    // Calculer automatiquement le prix total
    if ($ride && $this->numberOfSeats) {
      $this->totalPrice = (string) ($ride->getPrice() * $this->numberOfSeats);
    }

    return $this;
  }

  public function getNumberOfSeats(): ?int
  {
    return $this->numberOfSeats;
  }

  public function setNumberOfSeats(int $numberOfSeats): static
  {
    $this->numberOfSeats = $numberOfSeats;
    $this->updatedAt = new \DateTimeImmutable();

    // Recalculer le prix total
    if ($this->ride) {
      $this->totalPrice = (string) ($this->ride->getPrice() * $numberOfSeats);
    }

    return $this;
  }

  public function getStatus(): ?string
  {
    return $this->status;
  }

  public function setStatus(string $status): static
  {
    $this->status = $status;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getMessage(): ?string
  {
    return $this->message;
  }

  public function setMessage(?string $message): static
  {
    $this->message = $message;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getCreatedAt(): ?\DateTimeImmutable
  {
    return $this->createdAt;
  }

  public function setCreatedAt(\DateTimeImmutable $createdAt): static
  {
    $this->createdAt = $createdAt;

    return $this;
  }

  public function getUpdatedAt(): ?\DateTimeImmutable
  {
    return $this->updatedAt;
  }

  public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
  {
    $this->updatedAt = $updatedAt;

    return $this;
  }

  public function getTotalPrice(): ?string
  {
    return $this->totalPrice;
  }

  public function setTotalPrice(?string $totalPrice): static
  {
    $this->totalPrice = $totalPrice;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function isConfirmed(): bool
  {
    return $this->status === 'confirmed';
  }

  public function isPending(): bool
  {
    return $this->status === 'pending';
  }

  public function isCancelled(): bool
  {
    return $this->status === 'cancelled';
  }

  public function canBeCancelled(): bool
  {
    return in_array($this->status, ['pending', 'confirmed']) &&
      $this->ride &&
      $this->ride->getDepartureTime() > new \DateTime();
  }
}
