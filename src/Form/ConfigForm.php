<?php declare(strict_types=1);

namespace HistoryLog\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        /*
        $this
            ->add([
                'name' => 'bulkimport_missing_logs',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'This button displays a page that allows to check missing logs.', // @translate
                ],
            ])
        ;
        */
    }
}
