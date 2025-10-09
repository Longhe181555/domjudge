<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(
    name: 'phase_problem',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Links problems to phases',
    ],
    indexes: [
        new ORM\Index(columns: ['phase_id'], name: 'phase_id_idx'),
        new ORM\Index(columns: ['problem_id'], name: 'problem_id_idx'),
    ]
)]
class PhaseProblem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'comment' => 'Primary key'])]
    #[Serializer\Expose]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Phase::class)]
    #[ORM\JoinColumn(name: 'phase_id', referencedColumnName: 'phaseid', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    private ?Phase $phase = null;

    #[ORM\ManyToOne(targetEntity: Problem::class)]
    #[ORM\JoinColumn(name: 'problem_id', referencedColumnName: 'probid', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    private ?Problem $problem = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhase(): ?Phase
    {
        return $this->phase;
    }
    public function setPhase(?Phase $phase): self
    {
        $this->phase = $phase;
        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }
    public function setProblem(?Problem $problem): self
    {
        $this->problem = $problem;
        return $this;
    }
}
