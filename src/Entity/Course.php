<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an online course offered on the platform.
 *
 * Stored in the `courses` table. Each course belongs to exactly one {@see Category}
 * and one instructor ({@see User}). The `slug` column carries a unique constraint
 * and is used for SEO-friendly URLs. The `level` column is a MySQL ENUM restricted
 * to 'beginner', 'intermediate', and 'advanced'. The `price` column is stored as a
 * DECIMAL(10,2) string to avoid floating-point rounding issues.
 *
 * Publishing behaviour: calling {@see setPublished()} with `true` automatically
 * records `publishedAt` the first time a course is published; subsequent calls do
 * not overwrite that timestamp. Records are never physically deleted; the soft-delete
 * pattern is applied via the `deleted` flag and `deletedAt` timestamp. The `updatedAt`
 * timestamp is maintained automatically through the PreUpdate lifecycle callback.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'courses')]
#[ORM\HasLifecycleCallbacks]
class Course
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var Category The subject category this course belongs to.
     */
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    /**
     * @var User The instructor who owns and teaches this course.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private User $instructor;

    /**
     * @var string Human-readable course title (max 255 characters).
     */
    #[ORM\Column(length: 255)]
    private string $title;

    /**
     * @var string URL-safe unique identifier derived from the title (max 255 characters).
     */
    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    /**
     * @var string|null Optional long-form description of the course content.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * @var string Enrolment price as a DECIMAL(10,2) string. Defaults to '0.00' for free courses.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $price = '0.00';

    /**
     * @var string Difficulty level. Allowed values: 'beginner', 'intermediate', 'advanced' (MySQL ENUM).
     */
    #[ORM\Column(type: 'string', columnDefinition: "ENUM('beginner','intermediate','advanced') NOT NULL")]
    private string $level;

    /**
     * @var bool Whether the course is publicly visible to students. Defaults to false.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $published = false;

    /**
     * @var \DateTime|null Timestamp of the first publication. Set automatically by {@see setPublished()}.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $publishedAt = null;

    /**
     * @var \DateTimeImmutable Timestamp recorded when the entity is first persisted.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var \DateTime|null Timestamp of the last update, set automatically by the PreUpdate callback.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    /**
     * @var bool Whether this course has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * @var Collection<int, Lesson> Ordered list of lessons belonging to this course (ASC by positionOrder).
     */
    #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'course')]
    #[ORM\OrderBy(['positionOrder' => 'ASC'])]
    private Collection $lessons;

    /**
     * @var Collection<int, Enrollment> All enrolments for this course.
     */
    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'course')]
    private Collection $enrollments;

    /**
     * @var Collection<int, Review> Student reviews submitted for this course.
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'course')]
    private Collection $reviews;

    /**
     * Initialises collections and sets `createdAt` to the current instant.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lessons = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    /**
     * Sets `updatedAt` to the current datetime before every UPDATE statement.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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
     * @return Category
     */
    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     * @param Category $category
     * @return static
     */
    public function setCategory(Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return User
     */
    public function getInstructor(): User
    {
        return $this->instructor;
    }

    /**
     * @param User $instructor Must have role 'instructor'.
     * @return static
     */
    public function setInstructor(User $instructor): static
    {
        $this->instructor = $instructor;
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
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug URL-safe unique slug derived from the title.
     * @return static
     */
    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description Pass null to clear the description.
     * @return static
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Returns the enrolment price as a DECIMAL string (e.g. '29.99').
     *
     * @return string
     */
    public function getPrice(): string
    {
        return $this->price;
    }

    /**
     * @param string $price Decimal-formatted price string, e.g. '29.99'.
     * @return static
     */
    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Returns the difficulty level ('beginner', 'intermediate', or 'advanced').
     *
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @param string $level Must be one of 'beginner', 'intermediate', 'advanced'.
     * @return static
     */
    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Returns true when the course is publicly visible to students.
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * Toggles the published state.
     *
     * When publishing for the first time (`$published === true` and the course was
     * previously unpublished and `publishedAt` is still null), `publishedAt` is set
     * to the current datetime. Subsequent calls with `true` do not overwrite
     * `publishedAt`.
     *
     * @param bool $published
     * @return static
     */
    public function setPublished(bool $published): static
    {
        if ($published && !$this->published && $this->publishedAt === null) {
            $this->publishedAt = new \DateTime();
        }
        $this->published = $published;
        return $this;
    }

    /**
     * Returns the timestamp of the first publication, or null if never published.
     *
     * @return \DateTime|null
     */
    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime|null Null until the first update occurs.
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Returns true when the course has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the course has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the course as soft-deleted and records the deletion timestamp.
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
     * Returns the ordered list of lessons for this course.
     *
     * @return Collection<int, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    /**
     * Returns all enrolments for this course.
     *
     * @return Collection<int, Enrollment>
     */
    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    /**
     * Returns all student reviews for this course.
     *
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }
}
