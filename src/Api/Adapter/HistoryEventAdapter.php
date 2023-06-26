<?php declare(strict_types=1);

namespace HistoryLog\Api\Adapter;

use DateTime;
use Doctrine\ORM\QueryBuilder;
use HistoryLog\Entity\HistoryChange;
use HistoryLog\Entity\HistoryEvent;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Entity\Value;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;

class HistoryEventAdapter extends AbstractEntityAdapter
{
    use BuildQueryTrait;

    protected $sortFields = [
        'id' => 'id',
        'entity_name' => 'entityName',
        'entity_id' => 'entityId',
        'part_of' => 'partOf',
        'user_id' => 'userId',
        'operation' => 'operation',
        'created' => 'created',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'entity_name' => 'entityName',
        'entity_id' => 'entityId',
        'part_of' => 'partOf',
        'user_id' => 'userId',
        'operation' => 'operation',
        'created' => 'created',
    ];

    protected $queryTypeFields = [
        'entity_name' => 'string',
        'entity_id' => 'id',
        'part_of' => 'id',
        'user_id' => 'id',
        'operation' => 'string',
        'created' => 'datetime',
        'created_before' => 'datetime',
        'created_after' => 'datetime',
        'created_before_on' => 'datetime',
        'created_after_on' => 'datetime',
    ];

    public function getResourceName()
    {
        return 'history_events';
    }

    public function getRepresentationClass()
    {
        return \HistoryLog\Api\Representation\HistoryEventRepresentation::class;
    }

    public function getEntityClass()
    {
        return \HistoryLog\Entity\HistoryEvent::class;
    }

    public function getQueryTypeFields(): array
    {
        return $this->queryTypeFields;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $this->buildQueryFields($qb, $query);
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \HistoryLog\Entity\HistoryEvent $entity */
        // History Events and Changes are not updatable.
        $operation = $request->getOperation();
        if ($operation === Request::CREATE) {
            $data = $request->getContent();

            $entityName = null;
            $entityId = null;
            if (empty($data['o:entity'])) {
                $entityName = empty($data['o:entity_name']) ? null : (string) $data['o:entity_name'];
                $entityId = empty($data['o:entity_id']) ? null : (int) $data['o:entity_id'];
                $data['o:entity'] = $entityName && $entityId
                    ? $this->entityFromNameAndId($entityName, $entityId)
                    : null;
            } else {
                $dataEntity = $data['o:entity'];
                $data['o:entity'] = null;
                if (is_array($dataEntity)) {
                    $data['o:entity'] = $this->entityFromNameAndId($dataEntity['o:type'] ?? null, empty($dataEntity['o:id']) ? null : (int) $dataEntity['o:id']);
                }
                if ($dataEntity instanceof \Omeka\Entity\AbstractEntity) {
                    $entityToApiNames = [
                        \Omeka\Entity\Item::class => 'items',
                        \Omeka\Entity\Media::class => 'media',
                        \Omeka\Entity\ItemSet::class => 'item_sets',
                    ];
                    $data['o:entity'] = $dataEntity;
                    $entityName = $dataEntity->getResourceId();
                    $entityName = $entityToApiNames[$entityName] ?? null;
                    $entityId = $dataEntity->getId();
                }
            }

            // Check them during validation.
            if (!$entityId || !$entityName) {
                return;
            }

            $partOf = null;
            if ($entityName === 'media') {
                // Allow to pass part of directly when rebuilding base.
                if (empty($data['o:entity'])) {
                    $partOf = $data['o-history-log:part_of'] ?? null;
                } else {
                    $partOf = $data['o:entity']->getItem()->getId();
                }
            }

            $userId = null;
            if (isset($data['o:user']) && !$data['o:user'] === '') {
                // Do not verify user id to allow rebuilding.
                if (is_numeric($data['o:user'])) {
                    $userId = (int) $data['o:user'];
                } elseif (is_array($data['o:user'])) {
                    $userId = isset($data['o:user']['o:id']) ? (int) $data['o:user']['o:id'] : null;
                } elseif ($data['o:user'] instanceof \Omeka\Entity\User) {
                    $userId = $data['o:user']->getId();
                } elseif ($data['o:user'] instanceof \Omeka\Api\Representation\UserRepresentation) {
                    $userId = $data['o:user']->id();
                }
            }
            if ($userId === null) {
                $user = $this->getServiceLocator()
                    ->get('Omeka\AuthenticationService')->getIdentity();
                $userId = $user ? $user->getId() : null;
            }

            $eventOperation = $data['o-history-log:operation'] ?? null;

            $entity
                ->setEntityId($entityId)
                ->setEntityName($entityName)
                ->setPartOf($partOf)
                ->setUserId( $userId)
                ->setOperation($eventOperation)
                ->setCreated(new DateTime('now'))
            ;

            $changesData = $request->getValue('o-history-log:change', []);
            if (empty($changesData)) {
                $changesData = $this->prepareChangesData($entity, $data['o:entity'], $errorStore);
            }
            $changes = $entity->getChanges();
            $changeAdapter = $this->getAdapter('history_changes');
            foreach ($changesData as $changeData) {
                $subErrorStore = new ErrorStore;
                $change = new HistoryChange;
                $change->setEvent($entity);
                $subrequest = new Request(Request::CREATE, 'history_changes');
                $subrequest->setContent($changeData);
                try {
                    $changeAdapter->hydrateEntity($subrequest, $change, $subErrorStore);
                } catch (Exception\ValidationException $e) {
                    $errorStore->mergeErrors($e->getErrorStore(), 'o-history-log:change');
                }
                // Won't be flushed in case of error anyway.
                $changes->add($change);
            }
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        // A entity may not exist during reconstruction.
        /** @var \HistoryLog\Entity\HistoryEvent $entity */
        if (empty($entity->getEntityId()) || empty($entity->getEntityName())) {
            $errorStore->addError('o:entity', new Message(
                'The history event requires an entity.' // @translate
            ));
        }

        $operation = $entity->getOperation();
        if (empty($operation)) {
            $errorStore->addError('o-history-log:operation', new Message(
                'The history event requires an operation.' // @translate
            ));
        } elseif (!in_array($operation, [
            HistoryEvent::OPERATION_CREATE,
            HistoryEvent::OPERATION_UPDATE,
            HistoryEvent::OPERATION_DELETE,
            HistoryEvent::OPERATION_IMPORT,
            HistoryEvent::OPERATION_EXPORT,
        ])) {
            $errorStore->addError('o-history-log:operation', new Message(
                'The history event does not manage operation "%s".', // @ŧranslate
                $operation
            ));
        }
    }

    protected function entityFromNameAndId(?string $name, ?int $id = null): ?Resource
    {
        if (!$name || !$id) {
            return null;
        }
        $entityToApiNames = [
            'items' => 'items',
            'media' => 'media',
            'item_sets' => 'item_sets',
            'o:Item' => 'items',
            'o:Media' => 'media',
            'o:ItemSet' => 'item_sets',
        ];
        if (!isset($entityToApiNames[$name])) {
            return null;
        }
        try {
            return $this->getAdapter($entityToApiNames[$name])->findEntity(['id' => $id]);
        } catch (Exception\NotFoundException $e) {
            return null;
        }
    }

    /**
     * Prepare the list of changes of a resource.
     */
    protected function prepareChangesData(HistoryEvent $event, ?Resource $resource, ErrorStore $errorStore): array
    {
        // Log is done during validation.
        if (!$resource) {
            return [];
        }

        $operation = $event->getOperation();
        switch ($operation) {
            case HistoryEvent::OPERATION_CREATE:
            case HistoryEvent::OPERATION_DELETE:
                return $this->prepareChangesDataFull($event, $resource, $errorStore);
            case HistoryEvent::OPERATION_UPDATE:
                return $this->prepareChangesDataUpdate($event, $resource, $errorStore);
            default:
                $errorStore->addError('o-history-log:operation', new Message(
                    'The history event does not manage operation "%s".', // @ŧranslate
                    $operation
                ));
                break;
        }
    }

    protected function prepareChangesDataFull(
        HistoryEvent $event,
        Resource $resource,
        ErrorStore $errorStore
    ): array {
        $operation = $event->getOperation();
        $operationsToActions = [
            HistoryEvent::OPERATION_CREATE => HistoryChange::ACTION_CREATE,
            // Action is create for update, because when update goes here, it
            // means that there is no previous resource.
            HistoryEvent::OPERATION_UPDATE => HistoryChange::ACTION_CREATE,
            HistoryEvent::OPERATION_DELETE => HistoryChange::ACTION_DELETE,
        ];
        $action = $operationsToActions[$operation];

        $result = [];

        $result[] = [
            'o-history-log:action' => $action,
            'o:field' => 'o:is_public',
            'o:data' => [
                'value' => (int) $resource->isPublic(),
            ],
        ];

        $owner = $resource->getOwner();
        if ($owner) {
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => 'o:owner',
                'o:data' => [
                    'value' => $owner->getId(),
                    'uri' => $owner->getEmail(),
                ],
            ];
        }

        $class = $resource->getResourceClass();
        if ($class) {
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => 'o:resource_class',
                'o:data' => [
                    'value' => $class->getVocabulary()->getPrefix() . ':' . $class->getLocalName(),
                ],
            ];
        }

        $template = $resource->getResourceTemplate();
        if ($template) {
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => 'o:resource_template',
                'o:data' => [
                    'value' => $template->getId(),
                    'uri' => $template->getLabel(),
                ],
            ];
        }

        // TODO Medias are managed as sub event for media.
        // TODO Value annotations.
        // TODO Annotations.

        $entityName = $resource->getResourceName();
        switch ($entityName) {
            case 'items':
                /** @var $resource \Omeka\Entity\Item $resource */

                foreach ($resource->getItemSets() as $itemSet) {
                    $result[] = [
                        'o-history-log:action' => $action,
                        'o:field' => 'o:item_set',
                        'o:data' => [
                            'value' => $itemSet->getId(),
                        ],
                    ];
                }

                $primaryMedia = $resource->getPrimaryMedia();
                if ($primaryMedia) {
                    $result[] = [
                        'o-history-log:action' => $action,
                        'o:field' => 'o:primary_media',
                        'o:data' => [
                            'value' => $primaryMedia->getId(),
                        ],
                    ];
                }
                break;

            case 'media':

                /** @var $resource \Omeka\Entity\Media $resource */

                $mediaChecks = [
                    'o:source' => 'getSource',
                    'o:media_type' => 'getMediaType',
                    'o:sha256' => 'getSha256',
                    'o:filename' => 'getFilename',
                    'o:lang' => 'getLang',
                    'o:data' => 'getData',
                ];
                foreach ($mediaChecks as $term => $method) {
                    $newValue = $resource->{$method}();
                    if ($newValue !== null && $newValue !== '' && $newValue !== []) {
                        $result[] = [
                            'o-history-log:action' => $action,
                            'o:field' => $term,
                            'o:data' => [
                                'value' => $newValue,
                            ],
                        ];
                    }
                }
                break;

            case 'item_sets':
                /** @var $resource \Omeka\Entity\ItemSet $resource */

                $result[] = [
                    'o-history-log:action' => $action,
                    'o:field' => 'o:is_open',
                    'o:data' => [
                        'value' => (int) $resource->isOpen(),
                    ],
                ];
                break;

            default:
                break;
        }

        /** @var \Omeka\Entity\Value $value */
        foreach ($resource->getValues() as $value) {
            $property = $value->getProperty();
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => $term,
                'o:data' => $this->valueData($value),
            ];
        }

        return $result;
    }

    protected function prepareChangesDataUpdate(
        HistoryEvent $event,
        Resource $resource,
        ErrorStore $errorStore
    ): array {
        // Support php 7.4.

        $result = [];

        // The existing resource is not yet flushed, but validated.
        // Get the previous one via a second entity manager.
        $entityManager = $this->getEntityManager();
        // Method create is deprecated: now, create it directly.
        $secondEntityManager = new \Doctrine\Orm\EntityManager(
            $entityManager->getConnection(),
            $entityManager->getConfiguration()
        );

        /** @var \Omeka\Entity\Resource $prevResource */
        $prevResource = $secondEntityManager->getRepository($resource->getResourceId())->find($resource->getId());
        if (!$prevResource) {
            return $this->prepareChangesDataFull($event, $resource, $errorStore);
        }

        $determineHistoryAction = function ($prevData, $newData): ?string {
            if ($prevData === $newData) {
                return HistoryChange::ACTION_NONE;
            } elseif (!$prevData) {
                return HistoryChange::ACTION_CREATE;
            } elseif (!$newData) {
                return HistoryChange::ACTION_DELETE;
            } else {
                return HistoryChange::ACTION_UPDATE;
            }
        };

        $newResource = $resource;

        $prevValue = $prevResource->isPublic();
        $newValue = $newResource->isPublic();
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:is_public',
                'o:data' => [
                    'value' => (int) $newValue,
                ],
            ];
        }

        $prevOwner = $prevResource->getOwner();
        $prevValue = $prevOwner ? $prevOwner->getId() : null;
        $newOwner = $newResource->getOwner();
        $newValue = $newOwner ? $newOwner->getId() : null;
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:owner',
                'o:data' => [
                    'value' => $newValue,
                    'uri' => $newOwner->getEmail(),
                ],
            ];
        }

        $prevClass = $prevResource->getResourceClass();
        $prevValue = $prevClass ? $prevClass->getId() : null;
        $newClass = $newResource->getResourceClass();
        $newValue = $newClass ? $newClass->getId() : null;
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:resource_class',
                'o:data' => [
                    'value' => $newClass->getVocabulary()->getPrefix() . ':' . $newClass->getLocalName(),
                ],
            ];
        }

        $prevTemplate = $prevResource->getResourceTemplate();
        $prevValue = $prevTemplate ? $prevTemplate->getId() : null;
        $newTemplate = $newResource->getResourceTemplate();
        $newValue = $newTemplate ? $newTemplate->getId() : null;
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:resource_template',
                'o:data' => [
                    'value' => $newValue,
                    'uri' => $newTemplate->getLabel(),
                ],
            ];
        }

        // TODO Medias are managed as sub event for media.
        // TODO Value annotations.
        // TODO Annotations.

        $entityName = $resource->getResourceName();
        switch ($entityName) {
            case 'items':
                /** @var $resource \Omeka\Entity\Item $resource */

                // First, get all existing item sets.
                $prevItemSetIds = [];
                foreach ($prevResource->getItemSets() as $itemSet) {
                    $prevItemSetIds[$itemSet->getId()] = $itemSet->getId();
                }
                // Second, add new item sets.
                foreach ($newResource->getItemSets() as $itemSet) {
                    $itemSetId = $itemSet->getId();
                    if (isset($prevItemSetIds[$itemSetId])) {
                        // Don't store unchanged data.
                        unset($prevItemSetIds[$itemSetId]);
                    } else {
                        $result[] = [
                            'o-history-log:action' => HistoryChange::ACTION_CREATE,
                            'o:field' => 'o:item_set',
                            'o:data' => [
                                'value' => $itemSetId,
                            ],
                        ];
                    }
                }
                // Third, remove deleted item sets.
                foreach ($prevItemSetIds as $itemSetId) {
                    $result[] = [
                        'o-history-log:action' => HistoryChange::ACTION_DELETE,
                        'o:field' => 'o:item_set',
                        'o:data' => [
                            'value' => $itemSetId,
                        ],
                    ];
                }

                $prevPrimaryMedia = $prevResource->getPrimaryMedia();
                $prevValue = $prevPrimaryMedia ? $prevPrimaryMedia->getId() : null;
                $newPrimaryMedia = $newResource->getPrimaryMedia();
                $newValue = $newPrimaryMedia ? $newPrimaryMedia->getId() : null;
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:primary_media',
                        'o:data' => [
                            'value' => $newValue,
                        ],
                    ];
                }
                break;

            case 'media':
                /** @var $resource \Omeka\Entity\Media $resource */

                $mediaChecks = [
                    'o:source' => 'getSource',
                    'o:media_type' => 'getMediaType',
                    'o:sha256' => 'getSha256',
                    'o:filename' => 'getFilename',
                    'o:lang' => 'getLang',
                    'o:data' => 'getData',
                ];
                foreach ($mediaChecks as $term => $method) {
                    $prevValue = $prevResource->{$method}();
                    $newValue = $newResource->{$method}();
                    if ($prevValue !== $newValue) {
                        $result[] = [
                            'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                            'o:field' => $term,
                            'o:data' => [
                                'value' => $newValue,
                            ],
                        ];
                    }
                }
                break;

            case 'item_sets':
                /** @var $resource \Omeka\Entity\ItemSet $resource */

                $prevValue = $prevResource->isOpen();
                $newValue = $newResource->isOpen();
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:is_open',
                        'o:data' => [
                            'value' => (int) $newValue,
                        ],
                    ];
                }
                break;
        }

        /** @var \Omeka\Entity\Value $value */

        // To keep order of values inside a property: when a value is changed,
        // all values of this property are stored.
        // To avoid strict type issues for comparison, force types.

        // First, get all existing values by property term.
        $prevValues = [];
        foreach ($prevResource->getValues() as $value) {
            $property = $value->getProperty();
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $prevValues[$term][] = $this->valueData($value);
        }

        // Second, get all new values by property term.
        $newValues = [];
        foreach ($newResource->getValues() as $value) {
            $property = $value->getProperty();
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $newValues[$term][] = $this->valueData($value);
        }

        // Third, get the action by property for each value .
        foreach ($newValues as $term => $newPropertyValues) {
            // Quick process for new properties.
            if (!isset($prevValues[$term])) {
                foreach ($newPropertyValues as $value) {
                    $result[] = [
                        'o-history-log:action' => HistoryChange::ACTION_CREATE,
                        'o:field' => $term,
                        'o:data' => $value,
                    ];
                }
                continue;
            } elseif ($prevValues[$term] === $newPropertyValues) {
                // No change.
                continue;
            }

            // Most of the time, there is a single value, so this is an update.
            $totalPrevValues = count($prevValues[$term]);
            $totalNewValues = count($newValues[$term]);
            if ($totalPrevValues === 1 && $totalNewValues === 1) {
                $result[] = [
                    'o-history-log:action' => HistoryChange::ACTION_UPDATE,
                    'o:field' => $term,
                    'o:data' => reset($newValues[$term]),
                ];
                continue;
            }

            // List the modified values in order to use action "update" when
            // possible instead of two actions "delete" and "create".
            $removedPropertyValues = $prevValues[$term];
            foreach ($newPropertyValues as $value) {
                $prevValueKey = array_search($value, $prevValues[$term], true);
                if ($prevValueKey !== false) {
                    unset($removedPropertyValues[$prevValueKey]);
                }
            }

            foreach ($newPropertyValues as $value) {
                $prevValueKey = array_search($value, $prevValues[$term], true);
                if ($prevValueKey !== false) {
                    $action = HistoryChange::ACTION_NONE;
                    unset($prevValues[$term][$prevValueKey]);
                } elseif (count($removedPropertyValues)) {
                    array_shift($removedPropertyValues);
                    $action = HistoryChange::ACTION_UPDATE;
                } else {
                    $action = HistoryChange::ACTION_CREATE;
                }
                $result[] = [
                    'o-history-log:action' => $action,
                    'o:field' => $term,
                    'o:data' => $value,
                ];
            }

            // Remove remaining values of this property.
            foreach ($removedPropertyValues as $value) {
                $result[] = [
                    'o-history-log:action' => HistoryChange::ACTION_DELETE,
                    'o:field' => $term,
                    'o:data' => $value,
                ];
            }
        }

        // Fourth, remove remaining values by property.
        foreach (array_diff_key($prevValues, $newValues) as $term => $values) {
            foreach ($values as $value) {
                $result[] = [
                    'o-history-log:action' => HistoryChange::ACTION_DELETE,
                    'o:field' => $term,
                    'o:data' => $value,
                ];
            }
        }

        return $result;
    }

    protected function valueData(Value $value): array
    {
        $valueType = trim((string) $value->getType());
        $valueLang = trim((string) $value->getLang());
        $valueValue = trim((string) $value->getValue());
        $valueUri = trim((string) $value->getUri());
        $valueResource = $value->getValueResource();
        $valueAnnotation = $value->getValueAnnotation();
        return [
            'type' => strlen($valueType) ? $valueType : null,
            'is_public' => (int) $value->isPublic(),
            'lang' => strlen($valueLang) ? $valueLang : null,
            'value' => strlen($valueValue) ? $valueValue : null,
            'uri' => strlen($valueUri) ? $valueUri : null,
            'value_resource_id' => $valueResource ? (int) $valueResource->getId() : null,
            'value_annotation_id' => $valueAnnotation ? (int) $valueAnnotation->getId() : null,
        ];
    }
}
