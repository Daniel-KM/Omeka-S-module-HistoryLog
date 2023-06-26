<?php declare(strict_types=1);

namespace HistoryLog\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'History Log'; // @translate

    protected $elementGroups = [
        'history_log' => 'History Log', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'history-log')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'history_log_display',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'element_group' => 'history_log',
                    'label' => 'Pages where to display logs', // @translate
                    'value_options' => [
                        'items/show' => 'Item / show', // @translate
                        'media/show' => 'Media / show', // @translate
                        'item_sets/show' => 'Item / show', // @translate
                    ],
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'history_log_display',
                ],
            ])
        ;
    }
}
