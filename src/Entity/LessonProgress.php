<?php

namespace App\Entity;

use App\Repository\LessonProgressRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records that a student has completed a specific lesson within an enrolment.
 *
 * Stored in the `lesson_progress` table. This entity is insert-only by design: one
 * row is created per (enrollment, lesson) pair the moment the student marks the
 * lesson as complete. A composite unique constraint (`idx_lesson_progress_unique`) on
 * (`enrollment_id`, `lesson_id`) prevents duplicate completion records.
 *
 * The `completedAt` timestamp is set in the constructor and must not be changed
 * after creation. Records are never physically deleted; the soft-delete pattern is
 * applied via the `deleted` flag and `deletedAt` timestamp.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: LessonProgressRepository::class)]
#[ORM\Table(name: 'lesson_progress')]
#[ORM\UniqueConstraint(name: 'idx_lesson_progress_unique', columns: ['enrollment_id', 'lesson_id'])]
class LessonProgress
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var Enrollment The enrolment context in which the lesson was completed.
     */
    #[ORM\ManyToOne(targetEntity: Enrollment::class, inversedBy: 'lessonProgresses')]
    #[ORM\JoinColumn(nullable: false)]
    private Enrollment $enrollment;

    /**
     * @var Lesson The lesson that was completed.
     */
    #[ORM\ManyToOne(targetEntity: Lesson::class, inversedBy: 'lessonProgresses')]
    #[ORM\JoinColumn(nullable: false)]
    private Lesson $lesson;

    /**
     * @var \DateTimeImmutable Timestamp recorded at the moment the lesson was completed (set in constructor).
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $completedAt;

    /**
     * @var bool Whether this progress record has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * Sets `completedAt` to the current instant upon creation.
     *
     * Do not call the constructor a second time; this entity is intended to be
     * immutable after the initial persist.
     */
    public function __construct()
    {
        $this->completedAt = new \DateTimeImmutable();
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
     * @return Enrollment
     */
    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
    }

    /**
     * @param Enrollment $enrollment
     * @return static
     */
    public function setEnrollment(Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    /**
     * @return Lesson
     */
    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    /**
     * @param Lesson $lesson
     * @return static
     */
    public function setLesson(Lesson $lesson): static
    {
        $this->lesson = $lesson;
        return $this;
    }

    /**
     * Returns the timestamp at which the student completed the lesson.
     *
     * @return \DateTimeImmutable
     */
    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * Returns true when the progress record has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the record has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the progress record as soft-deleted and records the deletion timestamp.
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
}
