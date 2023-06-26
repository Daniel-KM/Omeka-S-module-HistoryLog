<?php declare(strict_types=1);

namespace HistoryLog\Api\Representation;

use HistoryLog\Entity\HistoryChange;
use HistoryLog\Entity\HistoryEvent;
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

    /**
     * Retrieve displayable name of an action.
     */
    public function displayAction(): string
    {
        $translator = $this->getTranslator();

        if (!$this->field()) {
            $event = $this->event();
            $value = $this->data()['value'] ?? null;
            switch ($event->operation()) {
                case HistoryEvent::OPERATION_IMPORT:
                    return empty($value)
                        ? $translator->translate('Imported') // @translate
                        : $translator->translate('Imported from %s', $value); // @translate
                case HistoryEvent::OPERATION_EXPORT:
                    return empty($value)
                        ? $translator->translate('Exported') // @translate
                        : $translator->translate('Exported to %s', $value); // @translate
                default:
                    return '';
            }
        }

        $action = $this->action();
        switch ($action) {
            case HistoryChange::ACTION_NONE:
                return $translator->translate('None'); // @translate
            case HistoryChange::ACTION_CREATE:
                return $translator->translate('Created'); // @translate
            case HistoryChange::ACTION_UPDATE:
                return $translator->translate('Updated'); // @translate
            case HistoryChange::ACTION_DELETE:
                return $translator->translate('Deleted'); // @translate
            // Manage extra type of action.
            default:
                return ucfirst($action);
        }
    }
}
