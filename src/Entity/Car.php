<?php

namespace App\Entity;

use App\Repository\CarRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

#[ORM\Entity(repositoryClass: CarRepository::class)]
#[OA\Schema(
    schema: 'Car',
    title: 'Véhicule',
    description: 'Entité représentant un véhicule utilisé pour le covoiturage',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Identifiant unique du véhicule', example: 1),
        new OA\Property(property: 'modele', type: 'string', description: 'Modèle du véhicule', example: 'Peugeot 308'),
        new OA\Property(property: 'immatriculation', type: 'string', description: 'Numéro d\'immatriculation', example: 'AB-123-CD'),
        new OA\Property(property: 'energie', type: 'string', description: 'Type d\'énergie du véhicule', example: 'Essence'),
        new OA\Property(property: 'color', type: 'string', description: 'Couleur du véhicule', example: 'Bleu'),
        new OA\Property(property: 'date_first_immatriculation', type: 'string', format: 'date', description: 'Date de première immatriculation', example: '2020-01-15'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Date de création', example: '2023-01-01T10:00:00Z')
    ]
)]
class Car
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $modele = null;

    #[ORM\Column(length: 50)]
    private ?string $immatriculation = null;

    #[ORM\Column(length: 50)]
    private ?string $energie = null;

    #[ORM\Column(length: 50)]
    private ?string $color = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateFirstImmatriculation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): static
    {
        $this->immatriculation = $immatriculation;

        return $this;
    }

    public function getEnergie(): ?string
    {
        return $this->energie;
    }

    public function setEnergie(string $energie): static
    {
        $this->energie = $energie;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getDateFirstImmatriculation(): ?\DateTime
    {
        return $this->dateFirstImmatriculation;
    }

    public function setDateFirstImmatriculation(\DateTime $dateFirstImmatriculation): static
    {
        $this->dateFirstImmatriculation = $dateFirstImmatriculation;

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
}
