<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a navigational menu item in the platform's back-office menu tree.
 *
 * Stored in the `menus` table. Items form a self-referencing parent/children
 * hierarchy: a root item has `parent = null`; child items reference their parent
 * through the `parent` association. Children are ordered by `positionOrder` (ASC).
 *
 * Each item may carry a Symfony route name (`routeName`) and an optional JSON bag of
 * route parameters (`routeParams`) so that Twig can generate URLs via
 * `path(item.routeName, item.routeParams ?? {})`. The optional `entityName` field
 * allows linking a menu item to a specific Doctrine entity class for generic CRUD
 * listing pages.
 *
 * Items that should be hidden from the rendered menu can be toggled via `visible`.
 * Records are never physically deleted; the soft-delete pattern is applied via the
 * `deleted` flag and `deletedAt` timestamp. The `updatedAt` timestamp is maintained
 * automatically through the PreUpdate lifecycle callback.
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\Table(name: 'menus')]
#[ORM\HasLifecycleCallbacks]
class Menu
{
    /**
     * @var int|null Auto-generated surrogate primary key (BIGINT).
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    /**
     * @var Menu|null Parent menu item. Null for root-level items.
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Menu $parent = null;

    /**
     * @var string Display text shown in the rendered menu (max 120 characters).
     */
    #[ORM\Column(length: 120)]
    private string $label;

    /**
     * @var string|null Fully-qualified Doctrine entity class name this item links to (max 100 characters).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityName = null;

    /**
     * @var string|null Symfony route name used to generate the item's URL (max 200 characters).
     */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $routeName = null;

    /**
     * @var array<string, mixed>|null Key/value pairs passed as route parameters when generating the URL.
     *                                Stored as JSON. Null when no parameters are required.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $routeParams = null;

    /**
     * @var string|null CSS icon class or icon identifier (e.g. a FontAwesome class, max 100 characters).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    /**
     * @var int Sort index controlling the rendering order among sibling items. Defaults to 0.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $positionOrder = 0;

    /**
     * @var bool Whether this item is rendered in the menu. Defaults to true.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $visible = true;

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
     * @var bool Whether this menu item has been soft-deleted. Defaults to false.
     */
    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var \DateTime|null Timestamp recorded when {@see softDelete()} is called. Null when not deleted.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deletedAt = null;

    /**
     * @var Collection<int, Menu> Direct child items ordered by positionOrder ASC.
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['positionOrder' => 'ASC'])]
    private Collection $children;

    /**
     * Initialises the children collection and sets `createdAt` to the current instant.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->children = new ArrayCollection();
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
     * Returns the parent menu item, or null for root-level items.
     *
     * @return Menu|null
     */
    public function getParent(): ?Menu
    {
        return $this->parent;
    }

    /**
     * @param Menu|null $parent Pass null to promote this item to a root-level item.
     * @return static
     */
    public function setParent(?Menu $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Returns the ordered collection of direct child items.
     *
     * @return Collection<int, Menu>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label Display text for the menu item (max 120 characters).
     * @return static
     */
    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Returns the Doctrine entity class name this item is associated with, or null.
     *
     * @return string|null
     */
    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    /**
     * @param string|null $entityName Fully-qualified entity class name, e.g. 'App\Entity\Course'.
     * @return static
     */
    public function setEntityName(?string $entityName): static
    {
        $this->entityName = $entityName;
        return $this;
    }

    /**
     * Returns the Symfony route name used to generate this item's URL, or null.
     *
     * @return string|null
     */
    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /**
     * @param string|null $routeName A registered Symfony route name.
     * @return static
     */
    public function setRouteName(?string $routeName): static
    {
        $this->routeName = $routeName;
        return $this;
    }

    /**
     * Returns the route parameters to be passed when generating the URL, or null.
     *
     * @return array<string, mixed>|null
     */
    public function getRouteParams(): ?array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, mixed>|null $routeParams Key/value route parameter pairs. Pass null to clear.
     * @return static
     */
    public function setRouteParams(?array $routeParams): static
    {
        $this->routeParams = $routeParams;
        return $this;
    }

    /**
     * Returns the icon identifier (e.g. a CSS class), or null when no icon is set.
     *
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon CSS icon class or identifier. Pass null to remove the icon.
     * @return static
     */
    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Returns the sort index controlling rendering order among siblings.
     *
     * @return int
     */
    public function getPositionOrder(): int
    {
        return $this->positionOrder;
    }

    /**
     * @param int $positionOrder Lower values appear first among sibling items.
     * @return static
     */
    public function setPositionOrder(int $positionOrder): static
    {
        $this->positionOrder = $positionOrder;
        return $this;
    }

    /**
     * Returns true when this item is rendered in the menu.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param bool $visible Set to false to hide the item without deleting it.
     * @return static
     */
    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;
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
     * Returns true when the menu item has been soft-deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @return \DateTime|null Null when the item has not been soft-deleted.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Marks the menu item as soft-deleted and records the deletion timestamp.
     *
     * This method does not physically remove the record from the database. Child
     * items are not automatically soft-deleted; callers must handle cascade behaviour
     * explicitly if required.
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
