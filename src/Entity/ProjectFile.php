<?php

namespace App\Entity;

use App\Repository\ProjectFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectFileRepository::class)]
class ProjectFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Repo::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $project;

    #[ORM\Column(type: 'string', length: 255)]
    private $fileName;

    #[ORM\Column(type: 'string', length: 255)]
    private $originalName;

    #[ORM\Column(type: 'datetime')]
    private $fechaSubida;

    public function __construct()
    {
        $this->fechaSubida = new \DateTime();
    }

    // ...getters y setters para cada propiedad...
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): ?\App\Entity\User
    {
        return $this->user;
    }
    public function setUser(?\App\Entity\User $user): self
    {
        $this->user = $user;
        return $this;
    }
    public function getProject(): ?\App\Entity\Repo
    {
        return $this->project;
    }
    public function setProject(?\App\Entity\Repo $project): self
    {
        $this->project = $project;
        return $this;
    }
    public function getFileName(): ?string
    {
        return $this->fileName;
    }
    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }
    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }
    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }
    public function getFechaSubida(): ?\DateTimeInterface
    {
        return $this->fechaSubida;
    }
    public function setFechaSubida(\DateTimeInterface $fechaSubida): self
    {
        $this->fechaSubida = $fechaSubida;
        return $this;
    }
    public function __toString(): string
    {
        return $this->originalName . ' (' . $this->fileName . ')';
    }
   
}
