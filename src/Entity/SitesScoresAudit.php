<?php

namespace App\Entity;

use App\Repository\SitesScoresAuditRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SitesScoresAuditRepository::class)]
#[ORM\Table(name: 'sites_scores_audit')]
class SitesScoresAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(type: 'text')]
    private ?string $targets = null;

    #[ORM\Column(type: 'text')]
    private ?string $prompt = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $grade = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reportJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $executionLog = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getTargets(): ?string
    {
        return $this->targets;
    }

    public function setTargets(string $targets): static
    {
        $this->targets = $targets;
        return $this;
    }

    public function getPrompt(): ?string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): static
    {
        $this->grade = $grade;
        return $this;
    }

    public function getReportJson(): ?string
    {
        return $this->reportJson;
    }

    public function setReportJson(?string $reportJson): static
    {
        $this->reportJson = $reportJson;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;
        return $this;
    }

    public function getExecutionLog(): ?string
    {
        return $this->executionLog;
    }

    public function setExecutionLog(?string $executionLog): static
    {
        $this->executionLog = $executionLog;
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

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    public function getTargetList(): array
    {
        $lines = preg_split('/\R/u', $this->targets ?? '') ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#')));
    }
}
