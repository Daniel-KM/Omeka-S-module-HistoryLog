<?php declare(strict_types=1);

namespace HistoryLog\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class HistoryChangeRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \HistoryLog\Entity\HistoryChange
     */
    protected $resource;

    public function getControllerName()
    {
        return 'history-change';
    }

    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o-history-log:event' => $this->event()->getReference(),
            'o-history-log:action' => $this->action(),
            'o:field' => $this->field(),
            'o:data' => $this->data(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-history-log:Change';
    }

    public function event(): HistoryEventRepresentation
    {
        return $this->getAdapter('history_events')
            ->getRepresentation($this->resource->getEvent());
    }

    public function action(): string
    {
        return $this->resource->getAction();
    }

    public function field(): string
    {
        return $this->resource->getField();
    }

    public function data()
    {
        // Data are adapted to the entity type and field.
        // Here for resource values.
        $data = [
            'type' => $this->type(),
            'is_public' => $this->isPublic(),
            'lang' => $this->lang(),
            'value' => $this->value(),
            'uri' => $this->uri(),
            'value_resource_id' => $this->valueResourceId(),
            'value_annotation_id' => $this->valueAnnotation(),
        ];
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}
