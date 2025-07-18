<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
class Role
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 100)]
  #[Assert\NotBlank(message: 'Le libellé du rôle ne peut pas être vide')]
  #[Assert\Length(min: 2, max: 100, minMessage: 'Le libellé doit faire au moins {{ limit }} caractères', maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
  private ?string $libelle = null;

  /**
   * @var Collection<int, User>
   */
  #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'userRoles')]
  private Collection $users;

  public function __construct()
  {
    $this->users = new ArrayCollection();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getLibelle(): ?string
  {
    return $this->libelle;
  }

  public function setLibelle(string $libelle): static
  {
    $this->libelle = $libelle;

    return $this;
  }

  /**
   * @return Collection<int, User>
   */
  public function getUsers(): Collection
  {
    return $this->users;
  }

  public function addUser(User $user): static
  {
    if (!$this->users->contains($user)) {
      $this->users->add($user);
      $user->addUserRole($this);
    }

    return $this;
  }

  public function removeUser(User $user): static
  {
    if ($this->users->removeElement($user)) {
      $user->removeUserRole($this);
    }

    return $this;
  }

  public function __toString(): string
  {
    return $this->libelle ?? '';
  }
}
