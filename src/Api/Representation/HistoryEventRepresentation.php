<?php declare(strict_types=1);

namespace HistoryLog\Api\Representation;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class HistoryEventRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \HistoryLog\Entity\HistoryEvent
     */
    protected $resource;

    public function getControllerName()
    {
        return 'history-event';
    }

    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o:entity_id' => $this->entityId(),
            'o:entity_name' => $this->entityName(),
            'o-history-log:part_of' => $this->partOf(),
            'o:user' => $this->userId(),
            'o-history-log:operation' => $this->operation(),
            'o:created' => [
                '@value' => $this->getDateTime($this->created()),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ],
            'o-history-log:change' => $this->changes(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-history-log:Event';
    }

    public function entityId(): int
    {
        return $this->resource->getEntityId();
    }

    public function entityName(): string
    {
        return $this->resource->getEntityName();
    }

    public function partOf(): ?int
    {
        return $this->resource->getPartOf();
    }

    public function userId(): ?int
    {
        return $this->resource->getUserId() ?: null;
    }

    public function operation(): string
    {
        return $this->resource->getOperation();
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    /**
     * @return HistoryChangeRepresentation[]
     */
    public function changes(): array
    {
        $changes = [];
        $changeAdapter = $this->getAdapter('history_changes');
        foreach ($this->resource->getChanges() as $changeEntity) {
            $changes[] = $changeAdapter->getRepresentation($changeEntity);
        }
        return $changes;
    }

    /**
     * Get the entity concerned by the event if it still exists.
     *
     * @todo Manage representation of deleted entities?
     */
    public function entity(): ?AbstractResourceRepresentation
    {
        $id = $this->resource->getEntityId();
        $name = $this->resource->getEntityName();
        if (!$id || !$name) {
            return null;
        }

        // Manage any entity.
        /** @var 'Omeka\ApiAdapterManager $adapterManager */
        $adapterManager = $this->getServiceLocator()->get('Omeka\ApiAdapterManager');
        if (!$adapterManager->has($name)) {
            return null;
        }

        /** @var \Omeka\Api\Adapter\AdapterInterface $adapter */
        $adapter = $adapterManager->get($name);
        if (!method_exists($adapter, 'findEntity')) {
            return null;
        }

        try {
            $entity = $adapter->findEntity(['id' => $id]);
            return $adapter->getRepresentation($entity);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Get the user who done the event if it still exists.
     *
     * @todo Manage representation of deleted users?
     */
    public function user(): ?UserRepresentation
    {
        $id = $this->resource->getUserId();
        if (!$id) {
            return null;
        }
        try {
            $adapter = $this->getAdapter('users');
            $entity = $adapter->findEntity(['id' => $id]);
            return $adapter->getRepresentation($entity);
        } catch (NotFoundException $e) {
            return null;
        }
    }
}
