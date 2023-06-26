<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class EntityName implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'Logged entity name'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'history_events',
            'history_changes',
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
        return 'entity_name';
    }

    public function renderHeader(PhpRenderer $view, array $data) : string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        $event = $resource instanceof \HistoryLog\Api\Representation\HistoryChangeRepresentation
            ? $resource->event()
            : $resource;
        return $event->entityName();
    }
}
