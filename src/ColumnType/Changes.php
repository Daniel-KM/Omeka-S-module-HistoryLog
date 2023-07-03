<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class Changes implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'Changes'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'history_events',
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
        /** @var \HistoryLog\Api\Representation\HistoryEventRepresentation $resource */
        $data['template'] ??= 'common/resource-page-block-layout/history-log-changes';
        return $resource->displayChanges($data);
    }
}
