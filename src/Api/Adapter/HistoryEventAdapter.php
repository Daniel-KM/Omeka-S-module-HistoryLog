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
        if ($request->getOperation() === Request::CREATE) {
            $data = $request->getContent();

            $entityName = null;
            $entityId = null;
            if (!empty($data['o:entity'])) {
                if (is_array($data['entity'])) {
                    $entityName = $data['entity']['o:type'] ?? null;
                    $entityId = $data['entity']['o:id'] ?? null;
                } elseif ($data['entity'] instanceof \Omeka\Entity\AbstractEntity) {
                    $entityName = $data['entity']->getResourceId();
                    $entityId = $data['entity']->getId();
                }
            }

            $partOf = $data['o-history-log:part_of'] ?? null;

            $userId = null;
            if (isset($data['o:user']) && !$data['o:user'] === '') {
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

            $operation = $data['o-history-log:operation'] ?? null;

            $entity
                ->setEntityId($entityId)
                ->setEntityName($entityName)
                ->setPartOf($partOf)
                ->setUserId( $userId)
                ->setOperation($operation)
                ->setCreated(new DateTime('now'))
            ;

            $changesData = $request->getValue('o-history-log:change', []);
            $changes = $entity->getChanges();
            $adapter = $this->getAdapter('history_changes');
            foreach ($changesData as $changeData) {
                $subErrorStore = new ErrorStore;
                $change = new HistoryChange;
                $change->setEvent($entity);
                $subrequest = new Request(Request::CREATE, 'history_changes');
                $subrequest->setContent($changeData);
                try {
                    $adapter->hydrateEntity($subrequest, $change, $subErrorStore);
                } catch (Exception\ValidationException $e) {
                    $errorStore->mergeErrors($e->getErrorStore(), 'o-history-log:change');
                }
                $changes->add($change);
            }
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
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
                'The history event requires a managed operation.' // @translate
            ));
        }
    }
}
