<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollments')]
#[ORM\UniqueConstraint(name: 'idx_enrollment_unique', columns: ['user_id', 'course_id'])]
class Enrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $course;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $enrolledAt;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $progressPercent = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $completed = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    #[ORM\OneToMany(targetEntity: LessonProgress::class, mappedBy: 'enrollment', cascade: ['persist', 'remove'])]
    private Collection $lessonProgresses;

    #[ORM\OneToOne(targetEntity: Review::class, mappedBy: 'enrollment')]
    private ?Review $review = null;

    public function __construct()
    {
        $this->enrolledAt = new \DateTimeImmutable();
        $this->lessonProgresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    public function getEnrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function getProgressPercent(): int
    {
        return $this->progressPercent;
    }

    public function setProgressPercent(int $progressPercent): static
    {
        $this->progressPercent = max(0, min(100, $progressPercent));
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): static
    {
        if ($completed && !$this->completed) {
            $this->completedAt = new \DateTime();
        }
        $this->completed = $completed;
        return $this;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    public function softDelete(): static
    {
        $this->deleted = true;
        $this->deletedAt = new \DateTime();
        return $this;
    }

    public function getLessonProgresses(): Collection
    {
        return $this->lessonProgresses;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }
}
