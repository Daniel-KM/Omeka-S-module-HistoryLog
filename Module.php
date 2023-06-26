<?php declare(strict_types=1);

namespace HistoryLog;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use HistoryLog\Entity\HistoryEvent;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

/**
 * History Log
 *
 * This Omeka S module logs curatorial actions such as adding, deleting, or
 * modifying items, collections and files.
 *
 * @copyright UCSC Library Digital Initiatives, 2014
 * @copyright Daniel Berthereau, 2015-2023
 * @license https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
 */

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Store events. Only persists events without flushing.
        // Use entity events (persist, update, remove) to be sure that event is
        // logged when api is used.
        // Of course, direct sql queries are not logged.
        // TODO Genericize with Resource, that is available as identifier.
        /** @see \Omeka\Db\Event\Subscriber\Entity::trigger() */
        $entities = [
            \Omeka\Entity\Item::class,
            \Omeka\Entity\Media::class,
            \Omeka\Entity\ItemSet::class,
            // \Omeka\Entity\Resource::class,
        ];
        // These events occurs during entity manager flush().
        foreach ($entities as $entity) {
            // Create event only if really flushed.
            $sharedEventManager->attach(
                $entity,
                'entity.persist.post',
                [$this, 'handleEntityOperation'],
                -50
            );
            $sharedEventManager->attach(
                $entity,
                'entity.update.pre',
                [$this, 'handleEntityOperation'],
                -50
            );
            $sharedEventManager->attach(
                $entity,
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
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $representation
         */
        $resource = $event->getTarget();
        $resourceName = $resource->getResourceName();
        $resourceId = $resource->getId();
        $identifier = $resourceName . '/' . $resourceId;
        if (isset($entities[$identifier])) {
            return;
        }

        $entities[$identifier] = true;

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $adapterManager = $services->get('Omeka\ApiAdapterManager');

        $adapter = $adapterManager->get($resourceName);
        $representation = $adapter->getRepresentation($resource);

        $eventOperations = [
            'entity.persist.post' => HistoryEvent::OPERATION_CREATE,
            'entity.update.pre' => HistoryEvent::OPERATION_UPDATE,
            'entity.remove.pre' => HistoryEvent::OPERATION_DELETE,
        ];
        $operation = $eventOperations[$event->getName()];

        $data = [
            'o:entity' => $resource,
            'o:user' => $services->get('Omeka\AuthenticationService')->getIdentity(),
            'o-history-log:operation' => $operation,
        ];

        try {
            $api->create('history_events', $data, [], ['responseContent' => 'resource'])->getContent();
        } catch (\Exception $e) {
            $services->get('Omeka\Logget')->err(
                'Unable to store history log when deleting resource #%1$s: %2$s', // @translate
                $resource->getId(), $e
            );
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

        // TODO Add last change and buttons to reset and undelete.
        $html = <<<HTML
<div class="meta-group">
    <h4>%s</h4>
    <div class="value">%s</div>
</div>
HTML;
        echo sprintf(
            $html,
            $view->translate('History Log'), // @translate
            $link
        );
    }
}
