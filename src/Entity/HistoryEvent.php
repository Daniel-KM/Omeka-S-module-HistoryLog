<?php declare(strict_types=1);

namespace HistoryLog\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;

/**
 * Main table to log events.
 *
 * Resources are not linked to other tables to be kept when source is deleted,
 * but they are indexed.
 *
 * Columns are not nullable to speed up requests.
 * @see \Statistics\Entity\Hit
 *
 * @todo Add operations batch create and batch update? Instead of import/export?
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(columns={"entity_id"}),
 *         @Index(columns={"entity_name"}),
 *         @Index(columns={"user_id"}),
 *         @Index(columns={"created"})
 *     }
 * )
 */
class HistoryEvent extends AbstractEntity
{
    /**#@+
     * History log events.
     *
     * OPERATION_CREATE: The resource is created.
     * OPERATION_UPDATE: The resource is updated.
     * OPERATION_DELETE: The resource is deleted.
     * OPERATION_IMPORT: The resource is imported.
     * OPERATION_EXPORT: The resource is exported.
     */
    const OPERATION_CREATE = 'create'; // @translate
    const OPERATION_UPDATE = 'update'; // @translate
    const OPERATION_DELETE = 'delete'; // @translate
    const OPERATION_IMPORT = 'import'; // @translate
    const OPERATION_EXPORT = 'export'; // @translate
    /**#@-*/

    /**
     * @var int
     *
     * @Id
     * @Column(
     *      type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * API resource id (not necessarily an Omeka main Resource).
     *
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $entityId;

    /**
     * API resource name (not necessarily an Omeka main Resource).
     *
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=31,
     *     nullable=false
     * )
     */
    protected $entityName;

    /**
     * To know the item from the media or the site from the page.
     *
     * @var string
     *
     * @Column(
     *     type="integer",
     *     nullable=false,
     *     options={
     *         "default":0
     *     }
     * )
     */
    protected $partOf = 0;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=false
     * )
     */
    protected $userId;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=6,
     *     nullable=false
     * )
     */
    protected $operation;

    /**
     * @var DateTime
     *
     * @Column(
     *      type="datetime",
     *      nullable=false,
     *      options={
     *          "default":"CURRENT_TIMESTAMP"
     *      }
     * )
     */
    protected $created;

    /**
     * @var HistoryChange[]|ArrayCollection
     *
     * @OneToMany(
     *     targetEntity=HistoryChange::class,
     *     mappedBy="event",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove"},
     *     indexBy="id"
     * )
     */
    protected $changes;

    public function __construct()
    {
        $this->changes = new ArrayCollection;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = (string) $entityName;
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setPartOf(?int $partOf): self
    {
        $this->partOf = (int) $partOf;
        return $this;
    }

    public function getPartOf(): ?int
    {
        return $this->partOf ?: null;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = (int) $userId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @return HistoryChange[]|ArrayCollection
     */
    public function getChanges(): ArrayCollection
    {
        return $this->changes;
    }
}
