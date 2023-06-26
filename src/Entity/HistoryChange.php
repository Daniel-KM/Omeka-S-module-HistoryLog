<?php declare(strict_types=1);

namespace HistoryLog\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * Resources are not linked to other tables to be kept when source is deleted,
 * but they are indexed.
 *
 * Most columns are not nullable to speed up requests.
 * @see \Reference\Entity\Metadata
 *
 * For changed data, there are two possibilities: one json column for any
 * metadata, or several columns according to changes.
 * Most of the times, the changed data are resource values. So the columns of
 * "Value" are used. Other metadata, mainly class, template and visibility, and
 * potentially any module data can use either use "value" for a single data, or
 * a json in "value", in which case json should be managed internally and not by
 * doctrine, or set a mapping with other columns (not recommended).
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(columns={"field"})
 *     }
 * )
 */
class HistoryChange extends AbstractEntity
{
    /**#@+
     * History log changes.
     *
     * ACTION_NONE: The resource metadata has no change, mainly for updated order.
     * ACTION_CREATE: The resource metadata is created.
     * ACTION_UPDATE: The resource metadata is updated.
     * ACTION_DELETE: The resource metadata is deleted.
     */
    const ACTION_NONE= 'none'; // @translate
    const ACTION_CREATE = 'create'; // @translate
    const ACTION_UPDATE = 'update'; // @translate
    const ACTION_DELETE = 'delete'; // @translate
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
     * @ManyToOne(
     *     targetEntity=HistoryEvent::class,
     *     inversedBy="changes"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $event;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=6,
     *     nullable=false
     * )
     */
    protected $action;

    /**
     * A field is generally a property, but may be other resource metadata.
     *
     * @var string
     *
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=false
     * )
     */
    protected $field;

    /*
     * Following columns are adapted from entity "Value".
     * "value" is used for other metadata. mainly class, template and visibility,
     * but potentially for any module data.
     * @todo For module data, either use json in value (in which case json should be managed internally) or set a mapping with other columns?
     *
     * @see \Omeka\Entity\Value
     */

    /**
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=true
     * )
     */
    protected $type;

    /**
     * @Column(
     *     type="boolean",
     *     nullable=true
     * )
     */
    protected $isPublic = true;

    /**
     * @Column(
     *     type="string",
     *     length=190,
     *     nullable=true
     * )
     */
    protected $lang;

    /**
     * @Column(
     *     name="`value`",
     *     type="text",
     *     nullable=true
     * )
     */
    protected $value;

    /**
     * @Column(
     *     type="text",
     *     nullable=true
     * )
     */
    protected $uri;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=true
     * )
     */
    protected $valueResourceId;

    /**
     * @var int
     *
     * @Column(
     *     type="integer",
     *     nullable=true
     * )
     */
    protected $valueAnnotationId;

    public function getId()
    {
        return $this->id;
    }

    public function setEvent(HistoryEvent $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getEvent(): HistoryEvent
    {
        return $this->event;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /*
     * Following columns are adapted from entity "Value".
     * "value" is used for other metadata. mainly class, template and visibility,
     * but potentially for any module data.
     * @todo For module data, either use json in value (in which case json should be managed internally) or set a mapping with other columns?
     *
     * @see \Omeka\Entity\Value
     */

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setIsPublic(?bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getIsPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setValueResourceId(?int $valueResourceId): self
    {
        $this->valueResourceId = $valueResourceId;
        return $this;
    }

    public function getValueResourceId(): ?int
    {
        return $this->valueResourceId;
    }

    public function setValueAnnotationId(?int $valueAnnotationId): self
    {
        $this->valueAnnotationId = $valueAnnotationId;
        return $this;
    }

    public function getValueAnnotationId(): ?int
    {
        return $this->valueAnnotationId;
    }
}
