<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use HistoryLog\Entity\HistoryEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class HistoryEventLastInfo implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'History log: info on last event'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'items',
            'media',
            'item_sets',
        ];
    }

    public function getMaxColumns() : ?int
    {
        return 1;
    }

    public function renderDataForm(PhpRenderer $view, array $data) : string
    {
        return '';
    }

    public function getSortBy(array $data) : ?string
    {
        return null;
    }

    public function renderHeader(PhpRenderer $view, array $data) : string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        /**
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         * @var \HistoryLog\Api\Representation\HistoryEventRepresentation $historyEvent
         */
        $historyEvent = $view->historyLog($resource, 1);
        if (!$historyEvent) {
            return null;
        }

        $operation = $historyEvent->operation();
        switch ($operation) {
            case HistoryEvent::OPERATION_CREATE:
                return sprintf(
                    $view->translate('Created on %1$s by %2$s'), // @translate
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
            case HistoryEvent::OPERATION_UPDATE:
                return sprintf(
                    $view->translate('Updated on %1$s by %2$s'), // @translate
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
            case HistoryEvent::OPERATION_DELETE:
                return sprintf(
                    $view->translate('Deleted on %1$s by %2$s'), // @translate
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
            case HistoryEvent::OPERATION_IMPORT:
                    return sprintf(
                    $view->translate('Imported on %1$s by %2$s'), // @translate
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
            case HistoryEvent::OPERATION_EXPORT:
                return sprintf(
                    $view->translate('Exported on %1$s by %2$s'), // @translate
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
            default:
                return sprintf(
                    $view->translate('Operation "%1$s" on %2$s by %3$s'), // @translate
                    $operation,
                    $view->i18n()->dateFormat($historyEvent->created(), 'medium', 'none'),
                    $historyEvent->displayUser()
                );
        }
    }
}
