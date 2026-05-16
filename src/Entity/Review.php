<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a student's star-rating and optional comment for a completed course.
 *
 * Stored in the `reviews` table. Each review is linked to exactly one
 * {@see Enrollment} (one-to-one), ensuring that only enrolled students can submit a
 * review and that each enrolment produces at most one review. The `course` and `user`
 * associations are denormalised foreign keys that mirror the parent enrolment for
 * query convenience.
 *
 * The `rating` field accepts integer values in the range 1–5; {@see setRating()}
 * throws an {@see \InvalidArgumentException} for out-of-range values. Records are
 * never physically deleted; the soft-delete pattern is applied via the `deleted` flag
 * and `deletedAt` timestamp.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var Enrollment The enrolment that entitles this student to leave a review.
     */
    #[ORM\OneToOne(targetEntity: Enrollment::class, inversedBy: 'review')]
    #[ORM\JoinColumn(nullable: false)]
    private Enrollment $enrollment;

    /**
     * @var Course The course being reviewed (denormalised from the enrolment for query convenience).
     */
    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $course;

    /**
     * @var User The student who submitted the review (denormalised from the enrolment for query convenience).
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /**
     * @var int Star rating in the range 1–5 (SMALLINT). Validated by {@see setRating()}.
     */
    #[ORM\Column(type: 'smallint')]
    private int $rating;

    /**
     * @var string|null Optional free-text comment accompanying the star rating.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    /**
     * @var \DateTimeImmutable Timestamp recorded when the entity is first persisted.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var bool Whether this review has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * Sets `createdAt` to the current instant upon creation.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
     * @param Enrollment $enrollment The enrolment that authorises this review.
     * @return static
     */
    public function setEnrollment(Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
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
     * Returns the star rating (1–5).
     *
     * @return int
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * Sets the star rating, enforcing the 1–5 constraint.
     *
     * @param int $rating Integer between 1 and 5 inclusive.
     * @return static
     * @throws \InvalidArgumentException When $rating is outside the range 1–5.
     */
    public function setRating(int $rating): static
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        }
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $comment Pass null to clear the comment.
     * @return static
     */
    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
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
     * Returns true when the review has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the review has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the review as soft-deleted and records the deletion timestamp.
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
