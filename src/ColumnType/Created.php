<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

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
}
