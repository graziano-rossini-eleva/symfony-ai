<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a platform user, either a student or an instructor.
 *
 * Stored in the `users` table. The `role` column is a MySQL ENUM restricted to
 * 'student' and 'instructor'. Records are never physically deleted; instead the
 * soft-delete pattern is applied via the `deleted` flag and `deletedAt` timestamp.
 * The `updatedAt` timestamp is maintained automatically through the PreUpdate
 * lifecycle callback.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var string The user's given name (max 100 characters).
     */
    #[ORM\Column(length: 100)]
    private string $firstName;

    /**
     * @var string The user's family name (max 100 characters).
     */
    #[ORM\Column(length: 100)]
    private string $lastName;

    /**
     * @var string Unique email address used for authentication (max 180 characters).
     */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /**
     * @var string Bcrypt/Argon2 hash of the user's password.
     */
    #[ORM\Column(length: 255)]
    private string $passwordHash;

    /**
     * @var string Platform role. Allowed values: 'student', 'instructor' (MySQL ENUM).
     */
    #[ORM\Column(type: 'string', enumType: null, columnDefinition: "ENUM('student','instructor') NOT NULL")]
    private string $role;

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
     * @var bool Whether this user has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * @var Collection<int, Course> Courses this user teaches as an instructor.
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'instructor')]
    private Collection $courses;

    /**
     * @var Collection<int, Enrollment> Active and historical enrollments for this user.
     */
    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'user')]
    private Collection $enrollments;

    /**
     * @var Collection<int, Review> Reviews written by this user.
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'user')]
    private Collection $reviews;

    /**
     * Initialises collections and sets `createdAt` to the current instant.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->courses = new ArrayCollection();
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
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     * @return static
     */
    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     * @return static
     */
    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return static
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * @param string $passwordHash Pre-hashed password string (never store plain text).
     * @return static
     */
    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    /**
     * Returns the platform role ('student' or 'instructor').
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param string $role Must be one of 'student' or 'instructor'.
     * @return static
     */
    public function setRole(string $role): static
    {
        $this->role = $role;
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
     * @return \DateTime|null Null until the first update occurs.
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Returns true when the user has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the user has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the user as soft-deleted and records the deletion timestamp.
     *
     * This method does not physically remove the record from the database.
     * Calling it multiple times is idempotent with respect to `deleted`, but
     * `deletedAt` will be overwritten on each call.
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
     * Returns the collection of courses this user instructs.
     *
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    /**
     * Returns all enrollments associated with this user.
     *
     * @return Collection<int, Enrollment>
     */
    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    /**
     * Returns all reviews written by this user.
     *
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }
}
