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
        switch ($this->field()) {
            case 'o:is_public':
            case 'o:is_open':
                $data = [
                    'value' => (bool) $this->resource->getValue(),
                ];
                break;
            case 'o:owner':
                $data = [
                    'o:id' => $this->resource->getValue(),
                    'o:email' => $this->resource->getUri(),
                ];
                break;
            case 'o:resource_class':
                $data = [
                    'o:term' => $this->resource->getValue(),
                ];
                break;
            case 'o:resource_template':
                $data = [
                    'o:id' => $this->resource->getValue(),
                    'o:label' => $this->resource->getUri(),
                ];
                break;
            // Item.
            case 'oitem_set':
            case 'o:primary_media':
                $data = [
                    'o:id' => $this->resource->getValue(),
                ];
                break;
            // Media.
            case 'o:source':
            case 'o:media_type':
            case 'o:sha256':
            case 'o:filename':
            case 'o:lang':
            case 'o:data':
                $data = [
                    'value' => $this->resource->getValue(),
                ];
                break;
            // Property.
            default:
                $data = [
                    'type' => $this->resource->getType(),
                    'is_public' => $this->resource->getIsPublic(),
                    'lang' => $this->resource->getLang(),
                    'value' => $this->resource->getValue(),
                    'uri' => $this->resource->getUri(),
                    'value_resource_id' => $this->resource->getValueResourceId(),
                    'value_annotation_id' => $this->resource->getValueAnnotationId(),
                ];
                break;
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

    /**
     * Display details of this change.
     */
    public function displayData(array $options = []): string
    {
        $options['historyChange'] = $this;
        $options['resource'] = $this;
        $template = $options['template'] ?? 'common/history-log-change-data';
        $partial = $this->getViewHelper('partial');
        return $partial($template, $options);
    }
}
