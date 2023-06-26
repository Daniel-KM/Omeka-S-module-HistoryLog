<?php declare(strict_types=1);

namespace HistoryLog\ColumnType;

class Id extends \Omeka\ColumnType\Id

{
    public function getLabel() : string
    {
        return 'Id'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'history_events',
            'history_changes',
        ];
    }
}
