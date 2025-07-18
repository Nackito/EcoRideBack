<?php

namespace App\Entity;

use App\Repository\RideRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: RideRepository::class)]
#[OA\Schema(
  schema: 'Ride',
  title: 'Trajet',
  description: 'Entité représentant un trajet de covoiturage',
  type: 'object',
  properties: [
    new OA\Property(property: 'id', type: 'integer', description: 'Identifiant unique du trajet', example: 1),
    new OA\Property(property: 'origin', type: 'string', description: 'Lieu de départ', example: 'Paris'),
    new OA\Property(property: 'destination', type: 'string', description: 'Lieu d\'arrivée', example: 'Lyon'),
    new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', description: 'Heure de départ', example: '2023-12-25T14:30:00'),
    new OA\Property(property: 'availableSeats', type: 'integer', description: 'Nombre de places disponibles', example: 3),
    new OA\Property(property: 'price', type: 'number', format: 'float', description: 'Prix par personne', example: 25.50),
    new OA\Property(property: 'description', type: 'string', description: 'Description du trajet', example: 'Trajet direct, non-fumeur'),
    new OA\Property(property: 'status', type: 'string', enum: ['active', 'completed', 'cancelled'], description: 'Statut du trajet', example: 'active'),
    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Date de création'),
    new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', description: 'Date de modification')
  ]
)]
class Ride
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255)]
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 255)]
  private ?string $origin = null;

  #[ORM\Column(length: 255)]
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 255)]
  private ?string $destination = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  #[Assert\NotBlank]
  #[Assert\GreaterThan('now')]
  private ?\DateTimeInterface $departureTime = null;

  #[ORM\Column]
  #[Assert\NotBlank]
  #[Assert\Range(min: 1, max: 8)]
  private ?int $availableSeats = null;

  #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
  #[Assert\NotBlank]
  #[Assert\Range(min: 0, max: 9999.99)]
  private ?string $price = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  #[Assert\Length(max: 1000)]
  private ?string $description = null;

  #[ORM\ManyToOne(inversedBy: 'ridesAsDriver')]
  #[ORM\JoinColumn(nullable: false)]
  private ?User $driver = null;

  /**
   * @var Collection<int, Booking>
   */
  #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'ride', cascade: ['remove'])]
  private Collection $bookings;

  #[ORM\Column]
  private ?\DateTimeImmutable $createdAt = null;

  #[ORM\Column]
  private ?\DateTimeImmutable $updatedAt = null;

  #[ORM\Column(length: 50)]
  #[Assert\Choice(choices: ['active', 'completed', 'cancelled'])]
  private ?string $status = 'active';

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $originLatLng = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $destinationLatLng = null;

  #[ORM\Column(nullable: true)]
  #[Assert\Range(min: 1, max: 2000)]
  private ?int $estimatedDistance = null; // en km

  #[ORM\Column(nullable: true)]
  #[Assert\Range(min: 1, max: 24)]
  private ?int $estimatedDuration = null; // en heures

  #[ORM\Column(type: Types::JSON, nullable: true)]
  private ?array $waypoints = null; // Points d'arrêt intermédiaires

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  private ?string $conditions = null; // Conditions spéciales (non-fumeur, animaux, etc.)

  public function __construct()
  {
    $this->bookings = new ArrayCollection();
    $this->createdAt = new \DateTimeImmutable();
    $this->updatedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getOrigin(): ?string
  {
    return $this->origin;
  }

  public function setOrigin(string $origin): static
  {
    $this->origin = $origin;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getDestination(): ?string
  {
    return $this->destination;
  }

  public function setDestination(string $destination): static
  {
    $this->destination = $destination;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getDepartureTime(): ?\DateTimeInterface
  {
    return $this->departureTime;
  }

  public function setDepartureTime(\DateTimeInterface $departureTime): static
  {
    $this->departureTime = $departureTime;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getAvailableSeats(): ?int
  {
    return $this->availableSeats;
  }

  public function setAvailableSeats(int $availableSeats): static
  {
    $this->availableSeats = $availableSeats;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getPrice(): ?string
  {
    return $this->price;
  }

  public function setPrice(string $price): static
  {
    $this->price = $price;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getDescription(): ?string
  {
    return $this->description;
  }

  public function setDescription(?string $description): static
  {
    $this->description = $description;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getDriver(): ?User
  {
    return $this->driver;
  }

  public function setDriver(?User $driver): static
  {
    $this->driver = $driver;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  /**
   * @return Collection<int, Booking>
   */
  public function getBookings(): Collection
  {
    return $this->bookings;
  }

  public function addBooking(Booking $booking): static
  {
    if (!$this->bookings->contains($booking)) {
      $this->bookings->add($booking);
      $booking->setRide($this);
    }

    return $this;
  }

  public function removeBooking(Booking $booking): static
  {
    if ($this->bookings->removeElement($booking)) {
      // set the owning side to null (unless already changed)
      if ($booking->getRide() === $this) {
        $booking->setRide(null);
      }
    }

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

  public function getOriginLatLng(): ?string
  {
    return $this->originLatLng;
  }

  public function setOriginLatLng(?string $originLatLng): static
  {
    $this->originLatLng = $originLatLng;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getDestinationLatLng(): ?string
  {
    return $this->destinationLatLng;
  }

  public function setDestinationLatLng(?string $destinationLatLng): static
  {
    $this->destinationLatLng = $destinationLatLng;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getEstimatedDistance(): ?int
  {
    return $this->estimatedDistance;
  }

  public function setEstimatedDistance(?int $estimatedDistance): static
  {
    $this->estimatedDistance = $estimatedDistance;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getEstimatedDuration(): ?int
  {
    return $this->estimatedDuration;
  }

  public function setEstimatedDuration(?int $estimatedDuration): static
  {
    $this->estimatedDuration = $estimatedDuration;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getWaypoints(): ?array
  {
    return $this->waypoints;
  }

  public function setWaypoints(?array $waypoints): static
  {
    $this->waypoints = $waypoints;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getConditions(): ?string
  {
    return $this->conditions;
  }

  public function setConditions(?string $conditions): static
  {
    $this->conditions = $conditions;
    $this->updatedAt = new \DateTimeImmutable();

    return $this;
  }

  public function getRemainingSeats(): int
  {
    $bookedSeats = 0;
    foreach ($this->bookings as $booking) {
      if ($booking->getStatus() === 'confirmed') {
        $bookedSeats += $booking->getNumberOfSeats();
      }
    }

    return $this->availableSeats - $bookedSeats;
  }

  public function getTotalBookedSeats(): int
  {
    $bookedSeats = 0;
    foreach ($this->bookings as $booking) {
      if ($booking->getStatus() === 'confirmed') {
        $bookedSeats += $booking->getNumberOfSeats();
      }
    }

    return $bookedSeats;
  }

  public function isActive(): bool
  {
    return $this->status === 'active' && $this->departureTime > new \DateTime();
  }

  public function canBeBooked(): bool
  {
    return $this->isActive() && $this->getRemainingSeats() > 0;
  }
}
