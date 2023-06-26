<?php declare(strict_types=1);

namespace HistoryLog\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Adapted from Omeka controllers.
 */
class HistoryChangeController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $this->browse()->setDefaults('history_changes');
        $response = $this->api()->search('history_changes', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        // Set the return query for batch actions. Note that we remove the page
        // from the query because there's no assurance that the page will return
        // results once changes are made.
        $returnQuery = $this->params()->fromQuery();
        unset($returnQuery['page']);

        $historyChanges = $response->getContent();

        return new ViewModel([
            'historyChanges' => $historyChanges,
            'resources' => $historyChanges,
            'returnQuery' => $returnQuery,
        ]);
    }

    public function showAction()
    {
        $historyChange = $this->api()->read('history_changes', ['id' => $this->params('id')])->getContent();
        return new ViewModel([
            'historyChange' => $historyChange,
            'resource' => $historyChange,
        ]);
    }

    public function showDetailsAction()
    {
        $historyChange = $this->api()->read('history_changes', ['id' => $this->params('id')])->getContent();

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'historyChange' => $historyChange,
            'resource' => $historyChange,
            'linkTitle' => $linkTitle,
        ]);
        $view->setTerminal(true);
        return $view;
    }
}
