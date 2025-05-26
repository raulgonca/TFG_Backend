<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'email_unique', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user:read'])]
    private ?string $username = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:read'])]
    private $email;

    #[ORM\Column(length: 255)]
    // No exponer la contraseña en user:read
    private $password;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    private array $roles = [];

    /**
     * @var Collection<int, Repo>
     */
    #[ORM\OneToMany(targetEntity: Repo::class, mappedBy: 'owner')]
    #[Groups(['user:read'])]
    private Collection $repos;

    public function __construct()
    {
        $this->repos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection<int, Repo>
     */
    public function getRepos(): Collection
    {
        return $this->repos;
    }

    public function addRepo(Repo $repo): static
    {
        if (!$this->repos->contains($repo)) {
            $this->repos->add($repo);
            $repo->setOwner($this);
        }

        return $this;
    }

    public function removeRepo(Repo $repo): static
    {
        if ($this->repos->removeElement($repo)) {
            // set the owning side to null (unless already changed)
            if ($repo->getOwner() === $this) {
                $repo->setOwner(null);
            }
        }

        return $this;
    }

    // Métodos requeridos por UserInterface y PasswordAuthenticatedUserInterface

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        
        $roles[] = 'ROLE_USER';
        
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'email' => $this->getEmail(),
            'roles' => $this->getRoles(),
            // NO incluimos username aquí
        ];
    }

    public static function createFromPayload($email, array $payload): self
    {
        $user = new self();
        $user->setEmail($email); // El $email viene del payload JWT

        // Si el payload tiene roles, los asignamos
        if (isset($payload['roles'])) {
            $user->setRoles($payload['roles']);
        }

        return $user;
    }
}
