<?php declare(strict_types=1);

namespace HistoryLog;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use HistoryLog\Entity\HistoryEvent;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Stdlib\Message;

/**
 * History Log
 *
 * This Omeka S module logs curatorial actions such as adding, deleting, or
 * modifying items, collections and files.
 *
 * @copyright UCSC Library Digital Initiatives, 2014
 * @copyright Daniel Berthereau, 2015-2024
 * @license https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
 */

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Common',
    ];

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.51')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.51'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /**
         * @var \Omeka\Permissions\Acl $acl
         * @see \Omeka\Service\AclFactory
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $roles = $acl->getRoles();
        $backendRoles = array_diff($roles, ['guest']);

        $acl
            // Admin part.
            // Any back-end roles can read and search events.
            // User lower than editor cannot delete.
            ->allow(
                $backendRoles,
                [
                    \HistoryLog\Controller\Admin\HistoryChangeController::class,
                    \HistoryLog\Controller\Admin\IndexController::class,
                ]
            )
            ->allow(
                $backendRoles,
                [
                    \HistoryLog\Api\Adapter\HistoryChangeAdapter::class,
                    \HistoryLog\Api\Adapter\HistoryEventAdapter::class,
                ],
                ['search', 'read', 'create']
            )
            ->allow(
                $backendRoles,
                [
                    \HistoryLog\Entity\HistoryChange::class,
                    \HistoryLog\Entity\HistoryEvent::class,
                ],
                ['create', 'read']
            )
        ;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Store events. Only persists events without flushing.
        // Use entity events (persist, update, remove) to be sure that event is
        // logged when api is used.
        // Of course, direct sql queries are not logged.
        // TODO Genericize with Resource, that is available as identifier (when Annotate will be cleaned).
        /** @see \Omeka\Db\Event\Subscriber\Entity::trigger() */
        $entities = [
            \Omeka\Api\Adapter\ItemAdapter::class => \Omeka\Entity\Item::class,
            \Omeka\Api\Adapter\MediaAdapter::class => \Omeka\Entity\Media::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class => \Omeka\Entity\ItemSet::class,
            // \Omeka\Api\Adapter\ResourceAdapter::class => \Omeka\Entity\Resource::class,
        ];
        // These events occurs during entity manager flush().
        foreach ($entities as $adapter => $entityClass) {
            // Create event only if really flushed, so after other listeners.
            $sharedEventManager->attach(
                $entityClass,
                'entity.persist.post',
                [$this, 'handleEntityOperation'],
                -50
            );
            $sharedEventManager->attach(
                $entityClass,
                'entity.update.pre',
                [$this, 'handleEntityOperation'],
                -50
            );
            // For batch update, the entities are not flushed, so when the entity
            // events are triggered, there is no difference with previous
            // entities that are still in database.
            // To make comparison, it requires to use event "finalize" in that
            // case.
            // To check that is a batch update event (api batch_update
            // "initialize" events may have been skipped, at least in theory),
            // check for the sub request option "finalize", that is set to false
            // but nevertheless processed in a second time.
            /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::batchUpdate() */
            $sharedEventManager->attach(
                $adapter,
                'api.update.post',
                [$this, 'handleEntityOperation'],
                -50
            );
            $sharedEventManager->attach(
                $entityClass,
                'entity.remove.pre',
                [$this, 'handleEntityOperation'],
                -50
            );
        }

        // Display logs in specified pages.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Admin\ItemSet',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.sidebar',
                [$this, 'handleViewShowAfter']
            );
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'handleViewShowAfter']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }

    public function handleEntityOperation(Event $event)
    {
        static $entities = [];

        /**
         * @var \Omeka\Entity\Resource $resource
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Api\Request $request
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $eventName = $event->getName();

        // Event "entity.update.pre" can be used for all events, except for
        // batch update, because there is no flush during it. So use an api
        // event, api.update.post, only for batch update.
        // To know if it is a batch update without using event initialize, use
        // the option finalize, that is set to false even if the event is processed.
        /** @see \Omeka\Api\Adapter\AbstractEntityAdapter::batchUpdate() */
        $request = $event->getParam('request');
        $isApiUpdatePost = $eventName === 'api.update.post';
        $isBatchUpdate = $isApiUpdatePost
            // && $request->getOption('isPartial')
            // && !$request->getOption('flushEntityManager')
            // && $request->getOption('responseContent') === 'resource'
            && !$request->getOption('finalize');
        if ($isApiUpdatePost && !$isBatchUpdate) {
            return;
        }

        // Furthermore, the event "'api.update.post" can be called up to three
        // times during a batch update for specific action (replace, append,
        // remove) and it is not possible to determine if the call is the last
        // one.
        // So the history log event can be updated up to three times.
        if ($isApiUpdatePost) {
            $resource = $event->getParam('response')->getContent();
        } else {
            $resource = $event->getTarget();
        }

        $resourceName = $resource->getResourceName();
        $resourceId = $resource->getId();
        $identifier = $resourceName . '/' . $resourceId;

        if (!$isApiUpdatePost && isset($entities[$eventName][$identifier])) {
            return;
        }

        $isEntityUpdatePre = $eventName === 'entity.update.pre';
        if ($isEntityUpdatePre) {
            // The existing resource is not yet flushed, but validated.
            // Get the previous one via a second entity manager.
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            $secondEntityManager = \Doctrine\ORM\EntityManager::create(
                $entityManager->getConnection(),
                $entityManager->getConfiguration(),
                $entityManager->getEventManager()
            );
            /** @var \Omeka\Entity\Resource $secondResource */
            $secondResource = $secondEntityManager->find(get_class($resource), $resource->getId());

            // Because of doctrine lazyness, load all related metadata here.
            // TODO Replace the check bar representation here and in adapter? But representation does not convert all linked entities, unlike json_decode(json_encode(), true).
            $secondResource->isPublic();
            $secondResource->getOwner();
            $secondResource->getResourceClass();
            $secondResource->getResourceTemplate();
            switch ($resourceName) {
                case 'items':
                    /** @var $resource \Omeka\Entity\Item $secondResource */
                    $secondResource->getItemSets();
                    $secondResource->getPrimaryMedia();
                    break;

                case 'media':
                    /** @var $resource \Omeka\Entity\Media $secondResource */
                    $secondResource->getSource();
                    $secondResource->getMediaType();
                    $secondResource->getSha256();
                    $secondResource->getFilename();
                    $secondResource->getLang();
                    $secondResource->getData();
                    break;

                case 'item_sets':
                    /** @var $resource \Omeka\Entity\ItemSet $resource */
                    $secondResource->isOpen();
                    break;
            }
            /** @var \Omeka\Entity\Value $value */
            foreach ($secondResource->getValues() as $value) {
                $value->getProperty()->getVocabulary();
                $value->getIsPublic();
                $value->getLang();
                $value->getType();
                $value->getUri();
                $value->getValue();
                $value->getValueResource();
                $va = $value->getValueAnnotation();
                if ($va) {
                    $va->getOwner();
                    $va->getResourceClass();
                    $va->getResourceTemplate();
                    foreach ($va->getValues() as $value) {
                        $value->getProperty()->getVocabulary();
                        $value->getIsPublic();
                        $value->getLang();
                        $value->getType();
                        $value->getUri();
                        $value->getValue();
                        $value->getValueResource();
                    }
                }
            }
            $entities[$eventName][$identifier] = ['previousResource' => $secondResource, 'event_id' => null];
        } else {
            $entities[$eventName][$identifier] = true;
        }

        $eventNames = [
            'entity.persist.post' => HistoryEvent::OPERATION_CREATE,
            'entity.update.pre' => HistoryEvent::OPERATION_UPDATE,
            'api.update.post' => HistoryEvent::OPERATION_UPDATE,
            'entity.remove.pre' => HistoryEvent::OPERATION_DELETE,
        ];
        $historyLogOperation = $eventNames[$eventName];

        $data = [
            'o:entity' => $resource,
            'o:user' => $services->get('Omeka\AuthenticationService')->getIdentity(),
            'o-history-log:operation' => $historyLogOperation,
        ];

        try {
            // It is not possible to know inside event "entity.update.pre" if
            // this is a batch update or not. So create the history log event in
            // the first step, then update it with the changes.
            // TODO Don't create event if nothing is updated, in particular during batch update, except in this case.
            if ($isBatchUpdate) {
                $data['previousResource'] = $entities['entity.update.pre'][$identifier]['previousResource'];
                $data['newResource'] = $resource;
                $api->update('history_events', $entities['entity.update.pre'][$identifier]['event_id'], $data, [], ['responseContent' => 'resource'])->getContent();
                // Limit memory issue. Not possible: called one to three times.
                // $entities['entity.update.pre'][$identifier] = true;
            } else {
                $historyEvent = $api->create('history_events', $data, [], ['responseContent' => 'resource'])->getContent();
                if ($eventName === 'entity.update.pre') {
                    $entities['entity.update.pre'][$identifier]['event_id'] = $historyEvent->getId();
                }
            }
        } catch (\Exception $e) {
            $services->get('Omeka\Logger')->err(new Message(
                'Unable to store history log when deleting resource #%1$s: %2$s', // @translate
                $resource->getId(), $e
            ));
        }
    }

    public function handleViewShowAfter(Event $event): void
    {
        $view = $event->getTarget();
        $vars = $view->vars();
        $resource = $vars->offsetGet('resource');
        $link = $view->historyEventsLink($resource);
        if (!$link) {
            return;
        }

        /** @var \HistoryLog\Api\Representation\HistoryEventRepresentation$historyEvent */
        $historyEvent = $view->api()->search('history_events', [
            'entity_name' => $resource->resourceName(),
            'entity_id' => $resource->id(),
            'sort_by' => 'id',
            'sort_order' => 'DESC',
            'limit' => 1,
        ])->getContent();
        if (!$historyEvent) {
            return;
        }
        $historyEvent = reset($historyEvent);

        // TODO Add last change and buttons to reset and undelete.
        $html = <<<HTML
<div class="meta-group">
    <h4>%s</h4>
    <div class="value">%s</div>
    <div class="value">%s</div>
</div>
HTML;
        echo sprintf(
            $html,
            $view->translate('History Log'), // @translate
            $historyEvent->displayShortInfo(),
            $link
        );
    }
}
