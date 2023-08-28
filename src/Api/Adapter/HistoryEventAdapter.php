<?php declare(strict_types=1);

namespace HistoryLog\Api\Adapter;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use HistoryLog\Entity\HistoryChange;
use HistoryLog\Entity\HistoryEvent;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Media;
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
        'created_until' => 'datetime',
        'created_since' => 'datetime',
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
                    $data['o:entity'] = $this->entityFromNameAndId($dataEntity['@type'] ?? $dataEntity['o:type'] ?? null, empty($dataEntity['o:id']) ? null : (int) $dataEntity['o:id']);
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
                    $partOf = empty($data['o-history-log:part_of']) ? (int) $data['o-history-log:part_of'] : null;
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

            $historyEventOperation = $data['o-history-log:operation'] ?? null;

            $entity
                ->setEntityId($entityId)
                ->setEntityName($entityName)
                ->setPartOf($partOf)
                ->setUserId( $userId)
                ->setOperation($historyEventOperation)
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
        } elseif ($operation === Request::UPDATE) {
            // Update is only allowed if there is a previous resource in order
            // to hack the fact that the previous or the new resource cannot be
            // determined at the same time during entity or api events for batch
            // update, because there is no flush during it.
            // Furthermore, during batch update, the update can be called up to
            // three times (replace, append, remove). The changes should be
            // attached to the same event.
            // TODO Simplify logs when multiple update of the same event.
            $data = $request->getContent();
            // Update only when the two resources are available.
            if (!empty($data['previousResource']) && !empty($data['newResource'])) {
                $changesData = $request->getValue('o-history-log:change', []);
                if (empty($changesData)) {
                    $currentResource = $this->entityFromNameAndId($entity->getEntityName(), $entity->getEntityId());
                    $changesData = $this->prepareChangesDataUpdate($entity, $currentResource, $errorStore, $data['previousResource']);
                }
                $changes = $entity->getChanges();
                // The change are cleared, because the check is done against
                // the same first resource three times during batch update.
                $changes->clear();
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

    /**
     * Undeleted the stored deleted entity. The id is kept.
     *
     * @todo Allow to undelete or to restore an entity for any event.
     */
    public function undeleteEntity(HistoryEvent $historyEvent, ErrorStore $errorStore): ?EntityInterface
    {
        // Create the resource via sql to keep the id (not possible via entity
        // manager), then fill it with standard api update, and check that new
        // history event is "undelete".

        // Quick checks.
        if ($historyEvent->getOperation() !== HistoryEvent::OPERATION_DELETE) {
            $message = new Message('Only the last event "Deleted" can be undeleted.'); // @translate
            $errorStore->addError('o-history-log:operation', $message);
            return null;
        }

        $entityId = $historyEvent->getEntityId();
        $entityName = $historyEvent->getEntityName();

        $entityClass = array_search($entityName, HistoryEvent::LOGGABLES);
        if (!$entityClass) {
            $message = new Message('The entity "%s" cannot be undeleted.', $entityName); // @translate
            $errorStore->addError('o:entity', $message);
            return null;
        }

        /**
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $resourceAdapter
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Omeka\Entity\Resource $entity
         */
        $resourceAdapter = $this->getAdapter($entityName);
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        $entity = $entityManager->getReference($entityName, $entityId);
        if ($entity) {
            $message = new Message('The entity "%1$s %2$d" exists cannot be undeleted!', $entityName, $entityId); // @translate
            $errorStore->addError('o:entity', $message);
            return null;
        }

        // Get last event.
        $request = new Request('search', 'history_events');
        $request
            ->setContent([
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'sort_by' => 'id',
                'sort_order' => 'DESC',
                'limit' => 1,
            ])
            ->setOption([
                'initialize' => false,
                'finalize' => false,
                'returnScalar' => 'id',
            ]);
        $result = $this->search($request)->getContent();
        if (!$result || (int) reset($result) !== $historyEvent->getId()) {
            $message = new Message('Only the last event "Deleted" can be undeleted.'); // @translate
            $errorStore->addError('o-history-log:operation', $message);
            return null;
        }

        $data = $this->prepareChangesDataUndelete($historyEvent, $errorStore);
        $qEntityClass = $connection->quote($entityClass);

        // Checks required and specific values.
        switch ($entityName) {
            case 'items':
                $message = new Message('The primary media cannot be set currently.'); // @translate
                $errorStore->addError('o:entity', $message);
                break;
            case 'media':
                $itemId = (int) $historyEvent->getPartOf();
                if (empty($itemId)) {
                    $message = new Message(
                        'The media #%1$d cannot be restored: the item is not set.', // @translate
                        $entityId
                    );
                    $errorStore->addError('o:entity', $message);
                    return null;
                }
                $item = $entityManager->getReference(\Omeka\Entity\Item::class, $itemId);
                if (!$item) {
                    $message = new Message(
                        'The media #%1$d cannot be restored: the item #%2$s is not undeleted.', // @translate
                        $entityId, $itemId
                    );
                    $errorStore->addError('o-entity:operation', $message);
                    return null;
                }
                $data['media']['item_id'] = $itemId;
                break;
            default:
                break;
        }

        // The main title and the primary id should be set in a second time.
        // Reindexation is required.

        $sqls = [];
        $sqls[] = <<<SQL
INSERT INTO `resource` (`id`, `owner_id`, `resource_class_id`, `resource_template_id`, `thumbnail_id`, `title`, `is_public`, `created`, `modified`, `resource_type`)
VALUES (
    $entityId,
    {$data['resource']['owner_id']},
    {$data['resource']['resource_class_id']},
    {$data['resource']['resource_template_id']},
    {$data['resource']['thumbnail_id']},
    NULL,
    {$data['resource']['is_public']},
    {$data['resource']['created']},
    {$data['resource']['modified']},
    $qEntityClass
);

SQL;
        switch ($entityName) {
            case 'items':
                // TODO The primary media id should be set after media undeletion.
                $sqls[] = <<<SQL
INSERT INTO `item` (`id`) VALUES ($entityId);

SQL;
                if (!empty($data['item']['item_item_set'])) {
                    $sql = <<<SQL
INSERT INTO `item_item_set` (`item_id`, `item_set_id`)
VALUES
SQL;
                    $sql .= "($entityId, " . implode("), ($entityId, ", $data['item']['item_item_set']) . ');';
                    $sqls[] = $sql;
                }
                break;
            case 'media':
                $sqls[] = <<<SQL
INSERT INTO `media` (`id`, `item_id`, `ingester`, `renderer`, `data`, `source`, `media_type`, `storage_id`, `extension`, `sha256`, `size`, `has_original`, `has_thumbnails`, `position`, `lang`, `alt_text`)
VALUES (
    $entityId, 
    {$data['media']['item_id']},
    {$data['media']['ingester']},
    {$data['media']['renderer']},
    {$data['media']['data']},
    {$data['media']['source']},
    {$data['media']['media_type']},
    {$data['media']['storage_id']},
    {$data['media']['extension']},
    {$data['media']['sha256']},
    {$data['media']['size']},
    {$data['media']['has_original']},
    {$data['media']['has_thumbnails']},
    {$data['media']['position']},
    {$data['media']['lang']},
    {$data['media']['alt_text']}
);

SQL;
                break;
            case 'item_sets':
                $sqls[] = <<<SQL
INSERT INTO `item_set` (`id`, `is_open`) VALUES ($entityId, {$data['item_set']['is_open']});

SQL;
                break;
            default:
                break;
        }

        if (!empty($data['value'])) {
            $sql = <<<SQL
INSERT INTO `value` (`resource_id`, `property_id`, `value_resource_id`, `type`, `lang`, `value`, `uri`, `is_public`, `value_annotation_id`)
VALUES

SQL;
            foreach ($data['value'] as $value) {
                $sql .= <<<SQL
(
    $entityId, 
    {$value['property_id']},
    {$value['value_resource_id']},
    {$value['type']},
    {$value['lang']},
    {$value['value']},
    {$value['uri']},
    {$value['is_public']},
    {$value['value_annotation_id']}
),

SQL;
            }
            $sql .= trim($sql, ", \n") . ";\n";
            $sqls[] = $sql;
        }

        try {
            $connection->transactional(function(Connection $connection) use ($sqls) {
                foreach ($sqls as $sql) {
                    $connection->executeStatement($sql);
                }
            });
        } catch (\Exception $e) {
            $message = new Message('An issue occurred during undeletion.'); // @translate
            $errorStore->addError('o:entity', $message);
            return null;
        }

        $entity = $entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            // Not possible or refresh issue.
            $message = new Message('An issue occurred during undeletion: no entity.'); // @translate
            $errorStore->addError('o:entity', $message);
            return null;
        }

        /*
        $message = new Message('The undeletion succeeded.'); // @translate
        $errorStore->addError('o:entity', $message);
        */

        // Store the new event and changes: They are the same than delete,
        // except operation and action.
        $request = new Request('create', 'history_events');
        $request->setContent([
            'o:entity' => $entity,
            'o-history-log:operation' => HistoryEvent::OPERATION_UNDELETE,
        ]);
        $this->create($request);

        return $entity;
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
     *
     * Details are stored individually for easier search and restoration.
     * Because most of  the changes are values, the default structure is value,
     * but this structure is adapted for other data too.
     *
     * It uses entity structure, not reprentation, for quicker process, because
     * all values are not exposed in representations ("has_original", "position"),
     * and because the json serialization can be heavy and may throw exception
     * when the sub-entities are deleted or unavailable for the current user.
     *
     * @todo Use a representation for main data, and entity for exceptions. See/factorize module BulkImport/Contribute? Here, the check and process is simple.
     */
    protected function prepareChangesData(HistoryEvent $historyEvent, ?Resource $resource, ErrorStore $errorStore): array
    {
        // Log is done during validation.
        if (!$resource) {
            return [];
        }

        $operation = $historyEvent->getOperation();
        switch ($operation) {
            case HistoryEvent::OPERATION_CREATE:
            case HistoryEvent::OPERATION_DELETE:
            case HistoryEvent::OPERATION_UNDELETE:
                return $this->prepareChangesDataFull($historyEvent, $resource, $errorStore);
            case HistoryEvent::OPERATION_UPDATE:
                return $this->prepareChangesDataUpdate($historyEvent, $resource, $errorStore);
                break;
            default:
                $errorStore->addError('o-history-log:operation', new Message(
                    'The history event does not manage operation "%s".', // @ŧranslate
                    $operation
                ));
                break;
        }
    }

    protected function prepareChangesDataFull(
        HistoryEvent $historyEvent,
        Resource $resource,
        ErrorStore $errorStore
    ): array {
        $operation = $historyEvent->getOperation();
        $operationsToActions = [
            HistoryEvent::OPERATION_CREATE => HistoryChange::ACTION_CREATE,
            // Action is create for update, because when update goes here, it
            // means that there is no previous resource.
            HistoryEvent::OPERATION_UPDATE => HistoryChange::ACTION_CREATE,
            HistoryEvent::OPERATION_DELETE => HistoryChange::ACTION_DELETE,
            HistoryEvent::OPERATION_UNDELETE => HistoryChange::ACTION_CREATE,
        ];
        $action = $operationsToActions[$operation];

        $classTerms = array_flip($this->getResourceClassIds());
        $propertyTerms = array_flip($this->getPropertyIds());

        $result = [];

        $result[] = [
            'o-history-log:action' => $action,
            'o:field' => 'o:is_public',
            'o:data' => [
                'value' => $resource->isPublic(),
            ],
        ];

        // Only created is stored, not modified, because available in event and
        // not restored anyway.
        $result[] = [
            'o-history-log:action' => $action,
            'o:field' => 'o:created',
            'o:data' => [
                'value' => $resource->getCreated()->format('Y-m-d H:i:s'),
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
                    'value' => $classTerms[$class->getId()] ?? null,
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

        $thumbnail = $resource->getThumbnail();
        if ($thumbnail) {
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => 'o:thumbnail',
                'o:data' => [
                    'value' => $thumbnail->getId(),
                    'uri' => $thumbnail->getName(),
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

                // The media are stored as a whole for file data because they
                // are immutable (but checked anyway during update). Data, lang
                // and alt text are stored separately. The item is stored in the
                // event as "part of".

                $result[] = [
                    'o-history-log:action' => $action,
                    'o:field' => 'o:media',
                    'o:data' => $this->mediaData($resource),
                ];

                $data = $resource->getData() ?: null;
                if ($data !== null && $data !== [] && $data !== '') {
                    $result[] = [
                        'o-history-log:action' => $action,
                        'o:field' => 'o:data',
                        'o:data' => [
                            'value' => $data,
                        ],
                    ];
                }

                $lang = $resource->getLang();
                if ($lang !== null && $lang !== '') {
                    $result[] = [
                        'o-history-log:action' => $action,
                        'o:field' => 'o:lang',
                        'o:data' => [
                            'lang' => $lang,
                            'value' => $lang,
                        ],
                    ];
                }

                $altText = $resource->getAltText();
                if ($altText !== null && $altText !== '') {
                    $result[] = [
                        'o-history-log:action' => $action,
                        'o:field' => 'o:alt_text',
                        'o:data' => [
                            'value' => $altText,
                        ],
                    ];
                }
                break;

            case 'item_sets':
                /** @var $resource \Omeka\Entity\ItemSet $resource */

                $result[] = [
                    'o-history-log:action' => $action,
                    'o:field' => 'o:is_open',
                    'o:data' => [
                        'value' => $resource->isOpen(),
                    ],
                ];
                break;

            default:
                break;
        }

        /** @var \Omeka\Entity\Value $value */
        foreach ($resource->getValues() as $value) {
            $propertyId = $value->getProperty()->getId();
            $term = $propertyTerms[$propertyId];
            $result[] = [
                'o-history-log:action' => $action,
                'o:field' => $term,
                'o:data' => $this->valueData($value),
            ];
        }

        return $result;
    }

    protected function prepareChangesDataUpdate(
        HistoryEvent $historyEvent,
        Resource $resource,
        ErrorStore $errorStore,
        ?Resource $previousResource = null
    ): array {
        // Support php 7.4.

        $result = [];

        // The existing resource is not yet flushed, but validated.
        // Get the previous one via a second entity manager.
        $entityManager = $this->getEntityManager();
        $secondEntityManager = \Doctrine\Orm\EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );

        /** @var \Omeka\Entity\Resource $prevResource */
        $prevResource = $previousResource
            ?? $secondEntityManager->getRepository($resource->getResourceId())->find($resource->getId());
        if (!$prevResource) {
            return $this->prepareChangesDataFull($historyEvent, $resource, $errorStore);
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

        $classTerms = array_flip($this->getResourceClassIds());
        $propertyTerms = array_flip($this->getPropertyIds());

        $prevValue = $prevResource->isPublic();
        $newValue = $newResource->isPublic();
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:is_public',
                'o:data' => [
                    'value' => $newValue,
                ],
            ];
        }

        // Only created is checked, not modified, because available in event and
        // not restored anyway.
        $prevCreated = $prevResource->getCreated();
        $prevValue = $prevCreated ? $prevCreated->format('Y-m-d H:i:s') : null;
        $newCreated = $newResource->getCreated();
        $newValue = $newCreated ? $newCreated->format('Y-m-d H:i:s') : null;
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:created',
                'o:data' => [
                    'value' => $newValue,
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
                    'value' => $newValue ? $classTerms[$newValue] : null,
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

        $prevThumbnail = $prevResource->getThumbnail();
        $prevValue = $prevThumbnail ? $prevThumbnail->getId() : null;
        $newThumbnail = $newResource->getThumbnail();
        $newValue = $newThumbnail ? $newThumbnail->getId() : null;
        if ($prevValue !== $newValue) {
            $result[] = [
                'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                'o:field' => 'o:thumbnail',
                'o:data' => [
                    'value' => $newValue,
                    'uri' => $newTemplate->getName(),
                ],
            ];
        }

        // TODO Medias are managed as sub event for media.
        // TODO Value annotations.
        // TODO Annotations.

        $entityName = $resource->getResourceName();
        switch ($entityName) {
            case 'items':
                /**
                 * @var $resource \Omeka\Entity\Item $resource
                 * @var $resource \Omeka\Entity\Item $prevResource
                 * @var $resource \Omeka\Entity\Item $newResource
                 */

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
                /**
                 * @var $resource \Omeka\Entity\Media $resource
                 * @var $resource \Omeka\Entity\Media $prevResource
                 * @var $resource \Omeka\Entity\Media $newResource
                 */

                // The media is stored as whole for the immutable part.
                $prevValue = $this->mediaData($prevResource);
                $newValue = $this->mediaData($newResource);
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:media',
                        'o:data' => $newValue,
                    ];
                }

                $prevValue = $prevResource->getData() ?: null;
                $newValue = $newResource->getData() ?: null;
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:data',
                        'o:data' => [
                            'value' => $newValue,
                        ],
                    ];
                }

                $prevValue = $prevResource->getLang();
                $prevValue = strlen((string) $prevValue) ? $prevValue : null;
                $newValue = $newResource->getLang() ?: null;
                $newValue = strlen((string) $newValue) ? $newValue : null;
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:lang',
                        'o:data' => [
                            'lang' => $newValue,
                            'value' => $newValue,
                        ],
                    ];
                }

                $prevValue = $prevResource->getAltText();
                $prevValue = strlen((string) $prevValue) ? $prevValue : null;
                $newValue = $newResource->getAltText() ?: null;
                $newValue = strlen((string) $newValue) ? $newValue : null;
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:alt_text',
                        'o:data' => [
                            'value' => $newValue,
                        ],
                    ];
                }
                break;

            case 'item_sets':
                /**
                 * @var $resource \Omeka\Entity\ItemSet $resource
                 * @var $resource \Omeka\Entity\ItemSet $prevResource
                 * @var $resource \Omeka\Entity\ItemSet $newResource
                 */

                $prevValue = $prevResource->isOpen();
                $newValue = $newResource->isOpen();
                if ($prevValue !== $newValue) {
                    $result[] = [
                        'o-history-log:action' => $determineHistoryAction($prevValue, $newValue),
                        'o:field' => 'o:is_open',
                        'o:data' => [
                            'value' => $newValue,
                        ],
                    ];
                }
                break;

            default:
                break;
        }

        /** @var \Omeka\Entity\Value $value */

        // To keep order of values inside a property: when a value is changed,
        // all values of this property are stored.
        // To avoid strict type issues for comparison, force types.

        // First, get all existing values by property term.
        $prevValues = [];
        foreach ($prevResource->getValues() as $value) {
            $propertyId = $value->getProperty()->getId();
            $term = $propertyTerms[$propertyId];
            $prevValues[$term][] = $this->valueData($value);
        }

        // Second, get all new values by property term.
        $newValues = [];
        foreach ($newResource->getValues() as $value) {
            $propertyId = $value->getProperty()->getId();
            $term = $propertyTerms[$propertyId];
            $newValues[$term][] = $this->valueData($value);
        }

        // Third, get the action by property for each value.
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

    /**
     * Get the data as sql data to insert directly in database.
     *
     * The representation is not used for now because the api cannot be used to
     * undelete a resource keeping its original id.
     *
     * @todo use a representation?
     */
    protected function prepareChangesDataUndelete(HistoryEvent $historyEvent, ErrorStore $errorStore): array
    {
        $entityName = $historyEvent->getEntityName();
        $entityId = $historyEvent->getEntityId();
        $entityClass = array_search($entityName, HistoryEvent::LOGGABLES);

        /**
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $resourceAdapter
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Omeka\Entity\Resource $entity
         */
        $resourceAdapter = $this->getAdapter($entityName);
        $entityManager = $this->getEntityManager();
        $connexion = $entityManager->getConnection();

        $propertyIds = $this->getPropertyIds();
        $dataTypes = $this->getDataTypeNames();

        // During deletion, all data are stored, so just refill them.
        // Set default values as sql values.
        $data = [
            'resource' => [
                'id' => $entityId,
                'owner_id' => 'NULL',
                'resource_class_id' => 'NULL',
                'resource_template_id' => 'NULL',
                'thumbnail_id' => 'NULL',
                'title' => 'NULL',
                'is_public' => 0,
                'created' => 'NOW()',
                'modified' => 'NOW()',
                'resource_type' => $connexion->quote($entityClass),
            ],
            'item' => [
                'id' => $entityId,
                // Should be set in a second step.
                'primary_media_id' => 'NULL',
                'item_item_set' => [],
            ],
            'media' => [
                'id' => $entityId,
                // Required.
                'item_id' => null,
                'ingester' => '',
                'renderer' => '',
                'data' => 'NULL',
                'source' => 'NULL',
                'media_type' => 'NULL',
                'storage_id' => 'NULL',
                'extension' => 'NULL',
                'sha256' => 'NULL',
                'size' => 'NULL',
                'has_original' => 0,
                'has_thumbnails' => 0,
                'position' => 'NULL',
                'lang' => 'NULL',
                'alt_text' => 'NULL',
            ],
            'item_set' => [
                'is_open' => 0,
            ],
            'value' => [],
        ];

        $changes = $historyEvent->getChanges();
        foreach ($changes as $change) {
            $field = $change->getField();
            switch ($field) {
                // Resources.

                case 'o:is_public':
                    $data['resource']['is_public'] = (int) in_array($change->getValue(), [true, 'true', 1, '1'], true);
                    break;
                case 'o:created':
                    $data['resource']['created'] = $change->getValue() ?: 'NOW()';
                    if ($data['resource']['created'] === 'NOW()') {
                        $errorStore->addError($field, new Message(
                            'The creation date was not stored.' // @translate
                        ));
                    }
                    break;
                case 'o:owner':
                    $valueId = (int) $change->getValue();
                    if ($valueId) {
                        $value = $entityManager->getReference(\Omeka\Entity\User::class, $valueId);
                        if ($value) {
                            $data['resource']['owner_id'] = $valueId;
                        } else {
                            $errorStore->addError($field, new Message(
                                'Deleted user #%1$d (%2$s).', // @translate
                                $valueId, $change->getUri()
                            ));
                        }
                    }
                    break;
                case 'o:resource_class':
                    $term = $change->getValue();
                    if ($term) {
                        $valueId = $this->getResourceClassIds()[$term] ?? null;
                        if ($valueId) {
                            $data['resource']['resource_class_id'] = $valueId;
                        } else {
                            $errorStore->addError($field, new Message(
                                'Deleted resource class %1$s.', // @translate
                                $term
                            ));
                        }
                    }
                    break;
                case 'o:resource_template':
                    $valueId = (int) $change->getValue();
                    if ($valueId) {
                        $value = $entityManager->getReference(\Omeka\Entity\ResourceTemplate::class, $valueId);
                        if ($value) {
                            $data['resource']['resource_template_id'] = $valueId;
                        } else {
                            $errorStore->addError($field, new Message(
                                'Deleted resource template #%1$d (%2$s).', // @translate
                                $valueId, $change->getUri()
                            ));
                        }
                    }
                    break;
                case 'o:thumbnail':
                    $valueId = (int) $change->getValue();
                    if ($valueId) {
                        $value = $entityManager->getReference(\Omeka\Entity\Asset::class, $valueId);
                        if ($value) {
                            $data['resource']['thumbnail_id'] = $valueId;
                        } else {
                            $errorStore->addError($field, new Message(
                                'Deleted thumbnail #%1$d.', // @translate
                                $valueId, $change->getUri()
                            ));
                        }
                    }
                    break;

                // Items.

                case 'o:item_set':
                    $valueId = (int) $change->getValue();
                    if ($valueId) {
                        $value = $entityManager->getReference(\Omeka\Entity\ItemSet::class, $valueId);
                        if ($value) {
                            $data['item']['item_item_set'][] = $valueId;
                        } else {
                            $errorStore->addError($field, new Message(
                                'Deleted item set #%1$d.', // @translate
                                $valueId
                            ));
                        }
                    }
                    break;

                case 'o:primary_media':
                    // Cannot be check and set before restoring medias.
                    $valueId = (int) $change->getValue();
                    if ($valueId) {
                        $data['item']['primary_media_id'] = $valueId;
                    }
                    break;

                // Media.
                // For the media, the item id is set in event.

                case 'o:media':
                    // Immutable data.
                    $value = $change->getType();
                    $data['media']['type'] = $value ? $connexion->quote($value) : 'NULL';
                    // Manage module ArchiveRepertory.
                    $value = (string) $change->getUri();
                    if (strlen($value)) {
                        $extension = (string) pathinfo($value, PATHINFO_EXTENSION);
                        if (strlen($extension)) {
                            $data['media']['storage_id'] = $connexion->quote(mb_substr($value, 0, -mb_strlen($extension) - 1));
                            $data['media']['extension'] = $connexion->quote($extension);
                        } else {
                            $data['media']['storage_id'] = $connexion->quote($value);
                        }
                    }
                    $value = $change->getValue();
                    $value = @json_decode($value, true);
                    if ($value && is_array($value)) {
                        $data['media']['ingester'] = empty($value['o:ingester']) ? 'NULL' : $connexion->quote($value['o:ingester']);
                        $data['media']['renderer'] = empty($value['o:renderer']) ? 'NULL' : $connexion->quote($value['o:renderer']);
                        $data['media']['source'] = empty($value['o:source']) ? 'NULL' : $connexion->quote($value['o:source']);
                        $data['media']['sha256'] = empty($value['o:sha256']) ? 'NULL' : $connexion->quote($value['o:sha256']);
                        $data['media']['size'] = isset($value['o:size']) && is_numeric($value['o:size']) ? (int) $value['o:size']: 'NULL';
                        $data['media']['has_original'] = in_array($value['o:has_original'], [true, 'true', 1, '1'], true)
                            ? 1
                            : (in_array($value['o:has_original'], [false, 'false', 0, '0'], true) ? 0 : 'NULL');
                        $data['media']['has_thumbnails'] = in_array($value['o:has_thumbnails'], [true, 'true', 1, '1'], true)
                            ? 1
                            : (in_array($value['o:has_thumbnails'], [false, 'false', 0, '0'], true) ? 0 : 'NULL');
                        $data['media']['position'] = isset($value['o:position']) && is_numeric($value['o:position']) ? (int) $value['o:position']: 'NULL';
                    }
                    break;

                case 'o:lang':
                case 'o:alt_text':
                    $value = (string) $change->getValue();
                    if (strlen($value)) {
                        $data['media']['lang'] = $connexion->quote($value);
                    }
                    break;

                case 'o:data':
                    $value = (string) $change->getValue();
                    if (strlen($value)) {
                        // Value is already json encoded.
                        $data['media']['data'] = $connexion->quote($value);
                    }
                    break;

                // Item sets.

                case 'o:is_open':
                    $data['item_set']['is_open'] = (int) in_array($change->getValue(), [true, 'true', 1, '1'], true);
                    break;

                // Properties.

                case isset($propertyIds[$field]):
                    $vType = (string) $change->getType();
                    // Warn in fact.
                    if (!strlen($vType)) {
                        $errorStore->addError($field, new Message(
                            'For property "%1$s", a data type is missing.', // @translate
                            $field
                        ));
                    } else if (!isset($dataTypes[$vType])) {
                        $errorStore->addError($field, new Message(
                            'For property "%1$s", the data type "%2$s" does not exist.', // @translate
                            $field, $vType
                        ));
                    }
                    $vIsPublic = (int) $change->getIsPublic();
                    $vLang = (string) $change->getLang();
                    $vValue = (string) $change->getValue();
                    $vUri = (string) $change->getUri();
                    $vVrId = (int) $change->getValueResourceId();
                    if ($vVrId) {
                        $vr = $entityManager->getReference(\Omeka\Entity\Resource::class, $vVrId);
                        if (!$vr) {
                            $errorStore->addError($field, new Message(
                                'For property "%1$s", the linked resource "%2$s" does not exist.', // @translate
                                $field, $vVrId
                            ));
                            $vVrId = null;
                        }
                    }
                    $vVaId = (int) $change->getValueAnnotationId();
                    if ($vVaId) {
                        $va = $entityManager->getReference(\Omeka\Entity\Resource::class, $vVrId);
                        if (!$va) {
                            // Warn in fact.
                            $errorStore->addError($field, new Message(
                                'For property "%1$s", the value annotation "%2$s" does not exist.', // @translate
                                $field, $vVaId
                            ));
                            $vVaId = null;
                        }
                    }
                    $data['value'][] = [
                        'property_id' => $propertyIds[$field],
                        'type' => strlen($vType) ? $connexion->quote($vType) : 'NULL',
                        'is_public' => $vIsPublic,
                        'lang' => strlen($vLang) ? $connexion->quote($vLang) : 'NULL',
                        'value' => strlen($vValue) ? $connexion->quote($vValue) : 'NULL',
                        'uri' => strlen($vUri) ? $connexion->quote($vUri) : 'NULL',
                        'value_resource_id' => $vVrId ?: 'NULL',
                        'value_annotation_id' => $vVaId ?: 'NULL',
                    ];
                    break;

                // Others.

                default;
                    $errorStore->addError($field, new Message(
                        'The field "#%1$s" does not exist anymore or is not managed currently.', // @translate
                        $field
                    ));
                    break;
            }
        }

        return $data;
    }

    protected function mediaData(?Media $media): ?array
    {
        if (!$media) {
            return null;
        }
        return [
            'type' => $media->getMediaType() ?: null,
            // Some data may be null.
            'value' => [
                'o:ingester' => $media->getIngester() ?: null,
                'o:renderer' => $media->getRenderer() ?: null,
                'o:source' => $media->getSource() ?: null,
                'o:sha256' => $media->getSha256() ?: null,
                'o:size' => is_numeric($media->getSize()) ? $media->getSize() : null,
                'has_original' => is_bool($media->hasOriginal()) ? $media->hasOriginal() : null,
                'has_thumbnails' => is_bool($media->hasThumbnails()) ? $media->hasThumbnails() : null,
                'o:position' => is_numeric($media->getPosition()) ? (int) $media->getPosition() : null,
            ],
            'uri' => $media->getFilename() ?: null,
        ];
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

    /**
     * Get all data type names.
     *
     * @return array Associative array of data type names by themselves.
     */
    protected function getDataTypeNames(): array
    {
        static $dataTypes;
        if (is_null($dataTypes)) {
            $dataTypes = $this->getServiceLocator()->get('Omeka\DataTypeManager')
                ->getRegisteredNames();
            $dataTypes = array_combine($dataTypes, $dataTypes);
        }
        return $dataTypes;
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds(): array
    {
        static $properties;

        if (is_array($properties)) {
            return $properties;
        }

        $qb = $this->getServiceLocator()->get('Omeka\Connection')->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        $properties = array_map('intval', $this->getServiceLocator()->get('Omeka\Connection')->executeQuery($qb)->fetchAllKeyValue());
        return $properties;
    }

    /**
     * Get all resource classes by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getResourceClassIds(): array
    {
        static $resourceClasses;

        if (is_array($resourceClasses)) {
            return $resourceClasses;
        }

        $qb = $this->getServiceLocator()->get('Omeka\Connection')->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                'resource_class.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('resource_class', 'resource_class')
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('resource_class.id', 'asc')
            ->addGroupBy('resource_class.id')
        ;
        $resourceClasses = array_map('intval', $this->getServiceLocator()->get('Omeka\Connection')->executeQuery($qb)->fetchAllKeyValue());
        return $resourceClasses;
    }


}
