<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class Created extends \Omeka\ColumnType\Created

{
    public function getLabel() : string
    {
        return 'Created'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'history_events',
            'history_changes',
        ];
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        return $view->i18n()->dateFormat($resource->created(), 'medium', 'medium');
    }
}
