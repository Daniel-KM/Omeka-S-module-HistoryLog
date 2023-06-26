<?php declare(strict_types=1);

namespace HistoryLog\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use HistoryLog\Entity\HistoryChange;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;

class HistoryChangeAdapter extends AbstractEntityAdapter
{
    use BuildQueryTrait;

    protected $sortFields = [
        'id' => 'id',
        'event' => 'event',
        'action' => 'action',
        'field' => 'field',
        'type' => 'type',
        'lang' => 'lang',
        'value' => 'value',
        'uri' => 'uri',
        'value_resource_id' => 'valueResourceId',
        'is_public' => 'isPublic',
        'value_annotation_id' => 'valueAnnotationId',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'event' => 'event',
        'action' => 'action',
        'field' => 'field',
        'type' => 'type',
        'lang' => 'lang',
        'value' => 'value',
        'uri' => 'uri',
        'value_resource_id' => 'valueResourceId',
        'is_public' => 'isPublic',
        'value_annotation_id' => 'valueAnnotationId',
    ];

    protected $queryTypeFields = [
        'event' => 'id',
        'action' => 'string',
        'field' => 'string',
        'type' => 'string',
        'lang' => 'string',
        'value' => 'string',
        'uri' => 'string',
        'value_resource_id' => 'id',
        'is_public' => 'boolean',
        'value_annotation_id' => 'id',
    ];

    public function getResourceName()
    {
        return 'history_changes';
    }

    public function getRepresentationClass()
    {
        return \HistoryLog\Api\Representation\HistoryChangeRepresentation::class;
    }

    public function getEntityClass()
    {
        return \HistoryLog\Entity\HistoryChange::class;
    }

    public function getQueryTypeFields(): array
    {
        return $this->queryTypeFields;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $this->buildQueryFields($qb, $query);

        /** @var \HistoryLog\Api\Adapter\HistoryEventAdapter $eventAdapter */
        $eventAdapter = $this->getAdapter('history_events');
        $eventAlias = $this->createAlias();
        $hasQueryOnEvent = $this->buildQueryFields($qb, $query, $eventAlias, $eventAdapter->getQueryTypeFields());
        if (!$hasQueryOnEvent) {
            $qb->innerJoin(
                'omeka_root.event', $eventAlias
            );
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \HistoryLog\Entity\HistoryChange $entity */
        // History Events and Changes are not updatable.
        if ($request->getOperation() === Request::CREATE) {
            $data = $request->getContent();

            $event = null;
            if (!empty($data['o-history-log:event'])) {
                if (is_array($data['o-history-log:event'])) {
                    if (!empty($data['o-history-log:event']['o:id'])) {
                        $event = $this->getAdapter('history_events')->findEntity($data['o-history-log:event']['o:id']);
                    }
                } elseif ($data['o-history-log:event'] instanceof \Omeka\Entity\AbstractEntity) {
                    $event = $data['o-history-log:event'];
                }
            }

            $action = empty($data['o-history-log:action']) ? '' : (string) $data['o-history-log:action'];
            $field = empty($data['o:field']) ? '' : (string) $data['o:field'];

            // Warning: data values can be "0".
            $changedDataDefault = [
                'type' => null,
                'is_public' => null,
                'lang' => null,
                'value' => null,
                'uri' => null,
                'value_resource_id' => null,
                'value_annotation_id' => null,
            ];
            $changedData = isset($data['o:data'])
                ? $data['o:data'] + $changedDataDefault
                : array_intersect_key($data, $changedDataDefault) + $changedDataDefault;
            $changedData['type'] = isset($changedData['type']) ? (string) $changedData['type'] : null;
            $changedData['is_public'] = isset($changedData['is_public']) && $changedData['is_public'] !== '' ? (bool) $changedData['is_public'] : null;
            $changedData['lang'] = isset($changedData['lang']) ? (string) $changedData['lang'] : null;
            $changedData['value'] = isset($changedData['value']) ? (string) $changedData['value'] : null;
            $changedData['uri'] = isset($changedData['uri']) ? (string) $changedData['uri'] : null;
            $changedData['value_resource_id'] = empty($changedData['value_resource_id']) ? null : (int) $changedData['value_resource_id'];
            $changedData['value_annotation_id'] = empty($changedData['value_annotation_id']) ? null : (int) $changedData['value_annotation_id'];

            $entity
                ->setEvent($event)
                ->setAction($action)
                ->setField($field)
                ->setType($changedData['type'])
                ->setIsPublic($changedData['is_public'])
                ->setLang($changedData['lang'])
                ->setValue($changedData['value'])
                ->setUri($changedData['uri'])
                ->setValueResourceId($changedData['value_resource_id'])
                ->setValueAnnotationId($changedData['value_annotation_id'])
            ;
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        /** @var \HistoryLog\Entity\HistoryChange $change */
        $field = $change->getField();
        if (empty($field)) {
            $errorStore->addError('o:field', new Message(
                'The history change requires a field.' // @translate
            ));
        }

        $action = $change->getAction();
        if (empty($action)) {
            $errorStore->addError('o-history-log:action', new Message(
                'The history change requires an action.' // @translate
            ));
        } elseif (!in_array($action, [
            HistoryChange::ACTION_NONE,
            HistoryChange::ACTION_CREATE,
            HistoryChange::ACTION_UPDATE,
            HistoryChange::ACTION_DELETE,
        ])) {
            $errorStore->addError('o-history-log:action', new Message(
                'The history change requires a managed action.' // @translate
            ));
        }
    }
}
