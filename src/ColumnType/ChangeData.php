<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class ChangeData implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'Change data'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
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
        return null;
    }

    public function renderHeader(PhpRenderer $view, array $data) : string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        return $view->partial('common/resource-page-block-layout/history-log-change-data', [
            'historyChange' => $resource,
            'resource' => $resource,
            'data' => $data,
        ]);
    }
}
