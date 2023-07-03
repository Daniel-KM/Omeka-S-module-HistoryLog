<?php declare(strict_types=1);

namespace HistoryLog\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Stdlib\Message;

/**
 * Adapted from Omeka controllers.
 */
class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $query = $this->params()->fromQuery();

        $this->browse()->setDefaults('history_events');
        $response = $this->api()->search('history_events', $query);
        $this->paginator($response->getTotalResults());

        // Set the return query for batch actions. Note that we remove the page
        // from the query because there's no assurance that the page will return
        // results once changes are made.
        $returnQuery = $query;
        unset($returnQuery['page']);

        $historyEvents = $response->getContent();

        return new ViewModel([
            'historyEvents' => $historyEvents,
            'resources' => $historyEvents,
            'returnQuery' => $returnQuery,
        ]);
    }

    public function showAction()
    {
        $historyEvent = $this->api()->read('history_events', ['id' => $this->params('id')])->getContent();
        return new ViewModel([
            'historyEvent' => $historyEvent,
            'resource' => $historyEvent,
        ]);
    }

    public function showDetailsAction()
    {
        $historyEvent = $this->api()->read('history_events', ['id' => $this->params('id')])->getContent();

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'historyEvent' => $historyEvent,
            'resource' => $historyEvent,
            'linkTitle' => $linkTitle,
        ]);
        $view->setTerminal(true);
        return $view;
    }

    /**
     * "log" is between browse and show: display all events of an entity.
     */
    public function logAction()
    {
        [$entityName, $entityId, $entity] = array_values($this->getEntityFromParams());

        $this->browse()->setDefaults('history_events');
        $response = $this->api()->search('history_events', [
            'entity_name' => $entityName,
            'entity_id' => $entityId,
        ]);
        $this->paginator($response->getTotalResults());

        $historyEvents = $response->getContent();

        return new ViewModel([
            'entityName' => $entityName,
            'entityId' => $entityId,
            'entity' => $entity,
            'historyEvents' => $historyEvents,
            'resources' => $historyEvents,
        ]);
    }

    public function undeleteAction()
    {
        /**
         * @var \HistoryLog\Api\Representation\HistoryEventRepresentation $historyEvent
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $entity
         */
        [$entityName, $entityId, $entity] = array_values($this->getEntityFromParams());

        if ($entity) {
            $message = new Message(
                'The %1$s #%2$d (%3$s) exists and cannot be undeleted!', // @translate
                $this->translate($entityName), $entityId, $entity->link($entity->displayTitle())
            );
            $message->setEscapeHtml(false);
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/history-log');
        }

        // Last event should be the deletion.
        $response = $this->api()->search('history_events', [
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'sort_by' => 'id',
            'sort_order' => 'DESC',
            'limit' => 1,
        ]);
        $response->getTotalResults();
        $historyEvent = $response->getContent();
        $historyEvent = $historyEvent ? reset($historyEvent) : null;

        if (!$historyEvent || $historyEvent->isUndeletableEntity()) {
            $message = new Message(
                'The deletion of the %1$s #%2$d has not been logged and cannot be undeleted!', // @translate
                $this->translate($entityName), $entityId
            );
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/history-log');
        }

        // Rebuild the item.
        $undeletedEntity = $historyEvent->undeleteEntity();
        if (!$undeletedEntity) {
            $message = new Message(
                'The undeletion of the %1$s #%2$d failed!', // @translate
                $this->translate($entityName), $entityId
            );
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/history-log');
        }

        $message = new Message(
            'The %1$s #%2$d (%3$s) is recovered (metadata only)!', // @translate
            $this->translate($entityName), $entityId, $entity->link($entity->displayTitle())
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);
        $message = new Message(
            'See logs for possible notices.' // @translate
        );
        $this->messenger()->addWarning($message);
        return $this->redirect()->toRoute('admin/history-log');
    }

    protected function getEntityFromParams(): array
    {
        // Values are checked via route.
        $params = $this->params();

        $id = (int) $params->fromRoute('id');
        if ($id) {
            /* TODO For omeka url item/#id/log.
            $resourceType = $params->fromRoute('controller') ?: $params->fromRoute('controller');
            $resourceNames = [
                'item' => 'items',
                'media' => 'media',
                'item_sets' => 'item_sets',
                'Omeka\Controller\Admin\Item' => 'items',
                'Omeka\Controller\Admin\Media' => 'media',
                'Omeka\Controller\Admin\ItemSet' => 'item_sets',
            ];
            $entityName = $resourceNames[$resourceType] ?? null;
            */

            /** @var \HistoryLog\Api\Representation\HistoryEventRepresentation $historyEvent */
            $historyEvent = $this->api()->read('history_events', ['id' => $id])->getContent();
            $entityId = $historyEvent->entityId();
            $entityName = $historyEvent->entityName();
        } else {
            $entityId = (int) $params->fromRoute('entity-id');
            $entityName = $params->fromRoute('entity-name');
            $resourceNames = [
                'item' => 'items',
                'media' => 'media',
                'item-set' => 'item_sets',
                'resource' => 'resources',
            ];
            $entityName = $resourceNames[$entityName] ?? null;
        }

        // Not found or invalid.
        if (!$entityId || !$entityName) {
            throw new NotFoundException();
        }

        $query = [
            'entity_name' => $entityName,
            'entity_id' => $entityId,
        ];
        $entity = $query;

        try {
            $entity = $this->api()->read($entityName, ['id' => $entityId])->getContent();
            if ($entityName === 'resources') {
                $entityName = $entity->resourceName();
            }
        } catch (\Exception $e) {
            $entity = null;
        }

        return [
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'entity' => $entity,
        ];
    }
}
