<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class HistoryEventsLink implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'History log: link to events'; // @translate
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
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        return $view->historyEventsLink($resource);
    }
}
