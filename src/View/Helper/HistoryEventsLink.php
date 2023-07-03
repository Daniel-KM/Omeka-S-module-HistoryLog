<?php declare(strict_types=1);

namespace HistoryLog\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class HistoryEventsLink extends AbstractHelper
{
    /**
     * Display the link to the history log events s for a resource, if any.
     */
    public function __invoke(?AbstractResourceEntityRepresentation $resource): ?string
    {
        if (!$resource) {
            return null;
        }

        $plugins = $this->getView()->getHelperPluginManager();
        $api = $plugins->get('api');

        $query = [
            'entity_name' => $resource->resourceName(),
            'entity_id' => $resource->id(),
        ];

        $total = $api->search('history_events', $query)->getTotalResults();
        if (!$total) {
            return null;
        }

        $url = $plugins->get('url');
        $hyperlink = $plugins->get('hyperlink');
        $translatePlural = $plugins->get('translatePlural');
        return $hyperlink(
            sprintf($translatePlural(
                '%d event', // @translate
                '%d events', // @translate
                $total
            ), $total),
            $url('admin/history-log/entity', [
                'entity-name' => $resource->getControllerName(),
                'entity-id' => $resource->id(),
            ])
        );
    }
}
