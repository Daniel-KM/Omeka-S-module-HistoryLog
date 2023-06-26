<?php declare(strict_types=1);

namespace HistoryLog\Api\Representation;

use HistoryLog\Entity\HistoryEvent;
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
            'o:entity' => $this->entityReference(),
            // TODO Replace partOf by the real term?
            'o-history-log:part_of' => $this->partOfReference(),
            'o:user' => $this->userReference(),
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
     * Get the parent resource who done the event if it still exists.
     *
     * @todo Manage representation of deleted users?
     */
    public function entityPartOf(): ?AbstractResourceRepresentation
    {
        $partOf = $this->resource->getPartOf();
        if (!$partOf) {
            return null;
        }

        $partOfs = [
            // 'items' => 'item_sets',
            'media' => 'items',
            /*
            'value_annotations' => 'resources',
            'site_pages' => 'sites',
            'annotations' => 'resources',
            */
        ];
        $entityName = $this->resource->getEntityName();
        if (!isset($partOfs[$entityName])) {
            return null;
        }

        $partOfEntityName = $partOfs[$entityName];

        // Manage any entity.
        /** @var 'Omeka\ApiAdapterManager $adapterManager */
        $adapterManager = $this->getServiceLocator()->get('Omeka\ApiAdapterManager');
        if (!$adapterManager->has($partOfEntityName)) {
            return null;
        }

        /** @var \Omeka\Api\Adapter\AdapterInterface $adapter */
        $adapter = $adapterManager->get($partOfEntityName);
        if (!method_exists($adapter, 'findEntity')) {
            return null;
        }

        try {
            $entity = $adapter->findEntity(['id' => $partOf]);
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


    /**
     * Get the reference to the entity, including when entity was removed.
     *
     * The json-ld type is included for simplicity.
     */
    public function entityReference(): array
    {
        $entity = $this->entity();
        if ($entity) {
            return [
                '@type' => $entity->getJsonLdType(),
            ] + $entity->getReference();
        }

        $jsonTypes = [
            'items' => 'o:Item',
            'media' => 'o:Media',
            'item_sets' => 'o:ItemSet',
        ];
        $entityName = $this->entityName();
        return [
            '@type' => $jsonTypes[$entityName] ?? $entityName,
            'o:id' => $this->entityId(),
        ];
    }

    /**
     * Get the reference to the part of, including when entity was removed.
     *
     * The json-ld type is included for simplicity.
     */
    public function partOfReference(): ?array
    {
        $partOf = $this->partOf();
        if ($partOf) {
            return [
                '@type' => $partOf->getJsonLdType(),
            ] + $partOf->getReference();
        }

        $partOf = $this->resource->getPartOf();
        if (!$partOf) {
            return null;
        }

        $entityName = $this->entityName();
        if ($entityName !== 'media') {
            return [
                'o:id' => $partOf,
            ];
        }

        return [
            '@type' => 'o:Item',
            'o:id' => $partOf,
        ];
    }

    /**
     * Get the reference to the user, including when entity was removed.
     *
     * The json-ld type is included for simplicity.
     */
    public function userReference(): ?array
    {
        $user = $this->user();
        if ($user) {
            return [
                '@type' => 'o:User',
            ] + $user->getReference();
        }

        $user = $this->resource->getUser();
        if (!$user) {
            return null;
        }

        return [
            '@type' => 'o:User',
            'o:id' => $user,
        ];
    }

    /**
     * Retrieve displayable name of an entity.
     */
    public function displayEntity(): string
    {
        $entity = $this->entity();
        if ($entity) {
            if (method_exists($entity, 'linkPretty')) {
                return $entity->linkPretty();
            }
            $nameEntity = $this->entityName($entity);
            return $entity->link($nameEntity);
        }

        $translator = $this->getTranslator();

        $entityId = $this->entityId();
        $entityName = $this->entityName();
        return $entityId && $entityName
            ? sprintf($translator->translate('Deleted %1$s #%2$d'), $entityName, $entityId) // @translate
            : $this->getTranslator->translate('[No entity]'); // @translate
    }

    /**
     * Retrieve displayable name of the entity part of.
     */
    public function displayEntityPartOf(): string
    {
        $entity = $this->entityPartOf();
        if ($entity) {
            if (method_exists($entity, 'linkPretty')) {
                return $entity->linkPretty();
            }
            $nameEntity = $this->entityName($entity);
            return $entity->link($nameEntity);
        }

        $partOf = $this->resource->getPartOf();
        if (!$partOf) {
            return '';
        }

        $partOfs = [
            'items' => 'item_sets',
            'media' => 'items',
            /*
             'value_annotations' => 'resources',
             'site_pages' => 'sites',
             'annotations' => 'resources',
             */
        ];

        $translator = $this->getTranslator();

        $entityName = $this->entityName();
        return isset($partOfs[$entityName])
            ? sprintf($translator->translate('Deleted %1$s #%2$d'), $partOfs[$entityName], $partOf) // @translate
            : sprintf($translator->translate('Deleted part of %1$s #%2$d'), $entityName, $partOf); // @translate;
    }

    /**
     * Retrieve displayable name of a user.
     */
    public function displayUser(): string
    {
        $user = $this->user();
        if ($user) {
            return $user->link($user->name());
        }

        $translator = $this->getTranslator();

        $userId = $this->userId();
        return $userId
            ? sprintf($translator->translate('Deleted user #%d', $userId)) // @translate
            : $translator->translate('Anonymous user'); // @translate
    }

    /**
     * Retrieve displayable name of an operation.
     */
    public function displayOperation(): string
    {
        $translator = $this->getTranslator();
        $operation = $this->operation();
        switch ($operation) {
            case HistoryEvent::OPERATION_CREATE:
                return $translator->translate('Create'); // @translate
            case HistoryEvent::OPERATION_UPDATE:
                return $translator->translate('Update'); // @translate
            case HistoryEvent::OPERATION_DELETE:
                return $translator->translate('Delete'); // @translate
            case HistoryEvent::OPERATION_IMPORT:
                return $translator->translate('Import'); // @translate
            case HistoryEvent::OPERATION_EXPORT:
                return $translator->translate('Export'); // @translate
            // Manage extra type of operation.
            default:
                return ucfirst($operation);
        }
    }

    protected function nameEntity(AbstractResourceRepresentation $entity): string
    {
        if (method_exists($entity, 'displayTitle')) {
            return $entity->displayTitle();
        }
        if (method_exists($entity, 'title')) {
            return $entity->title();
        }
        if (method_exists($entity, 'label')) {
            return $entity->label();
        }
        if (method_exists($entity, 'name')) {
            return $entity->name();
        }
        return $entity->getControllerName() . ' #' . $entity->id();
    }
}
