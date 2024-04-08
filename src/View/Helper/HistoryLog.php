<?php declare(strict_types=1);

namespace HistoryLog\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * HistoryLog full item log show page.
 *
 * @copyright Copyright 2014 UCSC Library Digital Initiatives
 * @Copyright 2015-2024 Daniel Berthereau
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */
class HistoryLog extends AbstractHelper
{
    /**
     * Create html with log information for a given entity.
     *
     * @param AbstractResourceEntityRepresentation|array $resource The resource
     * to retrieve info from. It may be deleted. For array, keys are "entity_name"
     * and "entity_id".
     * @param int $limit The maximum number of log entries to retrieve.
     * @return string An html table of requested log information.
     */
    public function __invoke($entity, int $limit = 0): string
    {
        if (empty($entity)) {
            return '';
        } elseif (is_array($entity)) {
            if (empty($entity['entity_name']) || empty($entity['entity_id'])) {
                return '';
            }
            $query = [
                'entity_name' => $entity['entity_name'],
                'entity_id' => $entity['entity_id'],
            ];
        } elseif (is_object($entity) && $entity instanceof AbstractResourceEntityRepresentation) {
            $query = [
                'entity_name' => $entity->resourceName(),
                'entity_id' => $entity->id(),
            ];
        } else {
            return '';
        }

        // The pagination is useless: most of the time, there are few events.
        if ($limit) {
            $query['limit'] = $limit;
        }

        // Reverse order because the most needed infos are recent ones.
        $query['sort_by'] = 'id';
        $query['sort_order'] = 'desc';

        $response = $this->api()->search('history_events', $query);
        $totalResults = $response->getTotalResults();
        // $this->getVView()->paginator($totalResults);

        if (!$totalResults) {
            return '';
        }

        $historyEvents = $response->getContent();

        try {
            $entity = $this->api()->read($query['entity_name'], ['id' => $query['entity_id']])->getContent();
        } catch (\Exception $e) {
            $entity = null;
        }

        $vars = [
            'entityName' => $query['entity_name'],
            'entityId' => $query['entity_id'],
            'entity' => $entity,
            'historyEvents' => $historyEvents,
            'resources' => $historyEvents,
        ];

        return $this->getView()->partial('common/history-log', $vars);
    }
}
