<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records a student's enrolment in a specific course.
 *
 * Stored in the `enrollments` table. A composite unique constraint
 * (`idx_enrollment_unique`) on (`user_id`, `course_id`) prevents a student from
 * enrolling in the same course more than once. Progress is tracked as a percentage
 * via `progressPercent` (0–100, clamped in the setter). When the course is completed
 * for the first time, `completedAt` is set automatically by {@see setCompleted()}.
 *
 * Each enrolment may have at most one {@see Review} (one-to-one inverse side) and
 * owns a cascade-managed collection of {@see LessonProgress} records. Records are
 * never physically deleted; the soft-delete pattern is applied via the `deleted` flag
 * and `deletedAt` timestamp.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollments')]
#[ORM\UniqueConstraint(name: 'idx_enrollment_unique', columns: ['user_id', 'course_id'])]
class Enrollment
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var User The student who enrolled in the course.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /**
     * @var Course The course the student has enrolled in.
     */
    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $course;

    /**
     * @var \DateTimeImmutable Timestamp recorded at the moment of enrolment.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $enrolledAt;

    /**
     * @var int Completion percentage in the range 0–100. Clamped by {@see setProgressPercent()}. Defaults to 0.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $progressPercent = 0;

    /**
     * @var bool Whether the student has completed all lessons. Defaults to false.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $completed = false;

    /**
     * @var \DateTime|null Timestamp of course completion. Set automatically by {@see setCompleted()}.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    /**
     * @var bool Whether this enrolment has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * @var Collection<int, LessonProgress> Per-lesson completion records owned by this enrolment (cascade persist/remove).
     */
    #[ORM\OneToMany(targetEntity: LessonProgress::class, mappedBy: 'enrollment', cascade: ['persist', 'remove'])]
    private Collection $lessonProgresses;

    /**
     * @var Review|null The single review the student may submit after completing the course.
     */
    #[ORM\OneToOne(targetEntity: Review::class, mappedBy: 'enrollment')]
    private ?Review $review = null;

    /**
     * Initialises the progress collection and sets `enrolledAt` to the current instant.
     */
    public function __construct()
    {
        $this->enrolledAt = new \DateTimeImmutable();
        $this->lessonProgresses = new ArrayCollection();
    }

    /**
     * Returns the surrogate primary key, or null before first persist.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Course
     */
    public function getCourse(): Course
    {
        return $this->course;
    }

    /**
     * @param Course $course
     * @return static
     */
    public function setCourse(Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    /**
     * Returns the timestamp at which the student enrolled.
     *
     * @return \DateTimeImmutable
     */
    public function getEnrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    /**
     * Returns the completion percentage (0–100).
     *
     * @return int
     */
    public function getProgressPercent(): int
    {
        return $this->progressPercent;
    }

    /**
     * Sets the completion percentage, clamping the value to the range 0–100.
     *
     * @param int $progressPercent Raw value; values below 0 are stored as 0, above 100 as 100.
     * @return static
     */
    public function setProgressPercent(int $progressPercent): static
    {
        $this->progressPercent = max(0, min(100, $progressPercent));
        return $this;
    }

    /**
     * Returns true when the student has completed all lessons.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Marks the enrolment as completed.
     *
     * When transitioning to `true` for the first time, `completedAt` is set to the
     * current datetime. Subsequent calls with `true` do not overwrite that timestamp.
     *
     * @param bool $completed
     * @return static
     */
    public function setCompleted(bool $completed): static
    {
        if ($completed && !$this->completed) {
            $this->completedAt = new \DateTime();
        }
        $this->completed = $completed;
        return $this;
    }

    /**
     * Returns the timestamp of course completion, or null if not yet completed.
     *
     * @return \DateTime|null
     */
    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    /**
     * Returns true when the enrolment has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the enrolment has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the enrolment as soft-deleted and records the deletion timestamp.
     *
     * This method does not physically remove the record from the database.
     *
     * @return static
     */
    public function softDelete(): static
    {
        $this->deleted = true;
        $this->deletedAt = new \DateTime();
        return $this;
    }

    /**
     * Returns the per-lesson completion records for this enrolment.
     *
     * @return Collection<int, LessonProgress>
     */
    public function getLessonProgresses(): Collection
    {
        return $this->lessonProgresses;
    }

    /**
     * Returns the student's review for this enrolment, or null if not yet submitted.
     *
     * @return Review|null
     */
    public function getReview(): ?Review
    {
        return $this->review;
    }
}
