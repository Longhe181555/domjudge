<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

/**
 * Contest phases (e.g., for hackathons or multi-phase contests).
 */
#[ORM\Entity]
#[ORM\Table(name: "phase", options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Phases for contests (multi-phase/hackathon support)',
],
    indexes: [
        new ORM\Index(columns: ["cid", "phase_order"], name: "cid_phaseorder"),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(columns: ["cid", "phase_order"], name: "unique_phase_per_order_per_contest"),
    ]
)]
class Phase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer", options: ['unsigned' => true, 'comment' => 'Phase ID'])]
    private ?int $phaseid = null;

    #[ORM\ManyToOne(targetEntity: Contest::class)]
    #[ORM\JoinColumn(name: "cid", referencedColumnName: "cid", nullable: false, onDelete: "CASCADE")]
    private ?Contest $contest = null;

    #[ORM\Column(type: "string", length: 100, options: ['comment' => 'Phase name'])]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: "decimal", precision: 32, scale: 9, nullable: true, options: ['comment' => 'Phase start time (epoch)', 'unsigned' => true])]
    private string|float|null $starttime = null;

    #[ORM\Column(type: "decimal", precision: 32, scale: 9, nullable: true, options: ['comment' => 'Phase end time (epoch)', 'unsigned' => true])]
    private string|float|null $endtime = null;

    #[ORM\Column(type: "text", nullable: true, options: ['comment' => 'Phase description'])]
    private ?string $description = null;

    #[ORM\Column(type: "boolean", options: ['default' => 0, 'comment' => 'Allow submissions in this phase'])]
    private bool $allow_submit = false;

    #[ORM\Column(type: "boolean", options: ['default' => 0, 'comment' => 'Allow manual judging in this phase'])]
    private bool $allow_manual_judge = false;

    #[ORM\Column(type: "boolean", options: ['default' => 0, 'comment' => 'Allow automatic judging in this phase'])]
    private bool $allow_automatic_judge = false;

    #[ORM\Column(type: "integer", options: ['unsigned' => true, 'comment' => 'Order of this phase in the contest'])]
    private int $phase_order = 1;

    #[ORM\Column(type: "json", nullable: true, options: ['comment' => 'Extra metadata for this phase'])]
    private ?array $metadata = null;

    // Getters and setters
    public function getPhaseid(): ?int { return $this->phaseid; }
    public function getContest(): ?Contest { return $this->contest; }
    public function setContest(?Contest $contest): self { $this->contest = $contest; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getStarttime(): string|float|null { return $this->starttime; }
    public function setStarttime(string|float|null $starttime): self { $this->starttime = $starttime; return $this; }
    public function getEndtime(): string|float|null { return $this->endtime; }
    public function setEndtime(string|float|null $endtime): self { $this->endtime = $endtime; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getAllowSubmit(): bool { return $this->allow_submit; }
    public function setAllowSubmit(bool $allow_submit): self { $this->allow_submit = $allow_submit; return $this; }
    public function getAllowManualJudge(): bool { return $this->allow_manual_judge; }
    public function setAllowManualJudge(bool $allow_manual_judge): self { $this->allow_manual_judge = $allow_manual_judge; return $this; }
    public function getAllowAutomaticJudge(): bool { return $this->allow_automatic_judge; }
    public function setAllowAutomaticJudge(bool $allow_automatic_judge): self { $this->allow_automatic_judge = $allow_automatic_judge; return $this; }
    public function getPhaseOrder(): int { return $this->phase_order; }
    public function setPhaseOrder(int $phase_order): self { $this->phase_order = $phase_order; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): self { $this->metadata = $metadata; return $this; }
}
