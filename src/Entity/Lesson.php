<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a single lesson (unit of content) within a course.
 *
 * Stored in the `lessons` table. A composite unique constraint
 * (`idx_lessons_course_position`) on (`course_id`, `position_order`) ensures no two
 * lessons occupy the same slot within the same course. Lessons may contain text
 * content, an external video URL, or both. The `positionOrder` value drives the
 * default sort order applied by the Course association's `@OrderBy` clause.
 *
 * Records are never physically deleted; the soft-delete pattern is applied via the
 * `deleted` flag and `deletedAt` timestamp.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\Table(name: 'lessons')]
#[ORM\UniqueConstraint(name: 'idx_lessons_course_position', columns: ['course_id', 'position_order'])]
class Lesson
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var Course The course this lesson belongs to.
     */
    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $course;

    /**
     * @var string Lesson title displayed in the course outline (max 255 characters).
     */
    #[ORM\Column(length: 255)]
    private string $title;

    /**
     * @var string|null Rich-text or Markdown body of the lesson. Null when video-only.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    /**
     * @var string|null External video URL (e.g. YouTube embed link, max 500 characters).
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    /**
     * @var int Estimated viewing/reading time in minutes. Defaults to 0.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $durationMinutes = 0;

    /**
     * @var int 1-based ordering index within the parent course. Must be unique per course.
     */
    #[ORM\Column(type: 'integer')]
    private int $positionOrder;

    /**
     * @var \DateTimeImmutable Timestamp recorded when the entity is first persisted.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var bool Whether this lesson has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * @var Collection<int, LessonProgress> Progress records tracking student completion of this lesson.
     */
    #[ORM\OneToMany(targetEntity: LessonProgress::class, mappedBy: 'lesson')]
    private Collection $lessonProgresses;

    /**
     * Initialises the progress collection and sets `createdAt` to the current instant.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content Pass null to clear the text content.
     * @return static
     */
    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    /**
     * @param string|null $videoUrl Pass null to remove the video link.
     * @return static
     */
    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;
        return $this;
    }

    /**
     * @return int
     */
    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    /**
     * @param int $durationMinutes Non-negative integer representing estimated duration.
     * @return static
     */
    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    /**
     * Returns the 1-based sort index of this lesson within its parent course.
     *
     * @return int
     */
    public function getPositionOrder(): int
    {
        return $this->positionOrder;
    }

    /**
     * @param int $positionOrder Must be unique within the parent course.
     * @return static
     */
    public function setPositionOrder(int $positionOrder): static
    {
        $this->positionOrder = $positionOrder;
        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns true when the lesson has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the lesson has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the lesson as soft-deleted and records the deletion timestamp.
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
     * Returns progress records for all students who have completed this lesson.
     *
     * @return Collection<int, LessonProgress>
     */
    public function getLessonProgresses(): Collection
    {
        return $this->lessonProgresses;
    }
}
