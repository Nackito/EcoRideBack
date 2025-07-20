<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_PSEUDO', fields: ['pseudo'])]
#[OA\Schema(
    schema: 'User',
    title: 'Utilisateur',
    description: 'Entité représentant un utilisateur de la plateforme de covoiturage',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Identifiant unique de l\'utilisateur', example: 1),
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Adresse email', example: 'user@example.com'),
        new OA\Property(property: 'pseudo', type: 'string', description: 'Pseudo de l\'utilisateur', example: 'john_doe'),
        new OA\Property(property: 'firstName', type: 'string', description: 'Prénom', example: 'John'),
        new OA\Property(property: 'lastName', type: 'string', description: 'Nom de famille', example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', description: 'Numéro de téléphone', example: '+33123456789'),
        new OA\Property(property: 'birthDate', type: 'string', format: 'date', description: 'Date de naissance', example: '1990-01-15'),
        new OA\Property(property: 'bio', type: 'string', description: 'Biographie', example: 'Passionné de covoiturage'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), description: 'Rôles de l\'utilisateur'),
        new OA\Property(property: 'isVerified', type: 'boolean', description: 'Compte vérifié', example: true),
        new OA\Property(property: 'rating', type: 'number', format: 'float', description: 'Note moyenne', example: 4.5),
        new OA\Property(property: 'totalRides', type: 'integer', description: 'Nombre total de trajets', example: 25),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Date de création'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', description: 'Date de modification')
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_.-]+$/', message: 'Le pseudo ne peut contenir que des lettres, chiffres, tirets, points et underscores')]
    private ?string $pseudo = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9\+\-\s\(\)]+$/', message: 'Le numéro de téléphone n\'est pas valide')]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $bio = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Ride>
     */
    #[ORM\OneToMany(targetEntity: Ride::class, mappedBy: 'driver')]
    private Collection $ridesAsDriver;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'passenger')]
    private Collection $bookings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    private ?string $rating = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalRides = 0;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $userRoles;

    public function __construct()
    {
        $this->ridesAsDriver = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->userRoles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // Ajouter les rôles depuis la relation Many-to-Many
        foreach ($this->userRoles as $userRole) {
            $roles[] = $userRole->getLibelle();
        }

        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
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

    /**
     * @return Collection<int, Ride>
     */
    public function getRidesAsDriver(): Collection
    {
        return $this->ridesAsDriver;
    }

    public function addRidesAsDriver(Ride $ridesAsDriver): static
    {
        if (!$this->ridesAsDriver->contains($ridesAsDriver)) {
            $this->ridesAsDriver->add($ridesAsDriver);
            $ridesAsDriver->setDriver($this);
        }

        return $this;
    }

    public function removeRidesAsDriver(Ride $ridesAsDriver): static
    {
        if ($this->ridesAsDriver->removeElement($ridesAsDriver)) {
            // set the owning side to null (unless already changed)
            if ($ridesAsDriver->getDriver() === $this) {
                $ridesAsDriver->setDriver(null);
            }
        }

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
            $booking->setPassenger($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getPassenger() === $this) {
                $booking->setPassenger(null);
            }
        }

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(?string $rating): static
    {
        $this->rating = $rating;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTotalRides(): ?int
    {
        return $this->totalRides;
    }

    public function setTotalRides(?int $totalRides): static
    {
        $this->totalRides = $totalRides;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(Role $userRole): static
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function removeUserRole(Role $userRole): static
    {
        if ($this->userRoles->removeElement($userRole)) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * Vérifie si l'utilisateur a un des rôles spécifiés
     */
    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->getRoles()));
    }

    public function getAge(): ?int
    {
        if (!$this->birthDate) {
            return null;
        }

        return $this->birthDate->diff(new \DateTime())->y;
    }

    // ...existing code...

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'email' => $this->getEmail(),
        ];
    }
}
