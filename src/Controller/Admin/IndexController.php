<?php declare(strict_types=1);

namespace HistoryLog\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

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
        $this->browse()->setDefaults('history_events');
        $response = $this->api()->search('history_events', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        // Set the return query for batch actions. Note that we remove the page
        // from the query because there's no assurance that the page will return
        // results once changes are made.
        $returnQuery = $this->params()->fromQuery();
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
}
