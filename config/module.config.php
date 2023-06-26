<?php declare(strict_types=1);

namespace HistoryLog;

return [
    'api_adapters' => [
        'invokables' => [
            'history_events' => Api\Adapter\HistoryEventAdapter::class,
            'history_changes' => Api\Adapter\HistoryChangeAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        /*
        'filters' => [
            'history_event_visibility' => Db\Filter\HistoryEventVisibilityFilter::class,
        ],
        */
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    /*
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
        'factories' => [
            Form\SearchForm::class => Service\Form\SearchFormFactory::class,
        ],
    ],
    'controllers' => [
        // TODO Will be simplified later.
        'invokables' => [
            Controller\Admin\AdminController::class => Controller\Admin\AdminController::class,
            Controller\Admin\IndexController::class => Controller\Admin\IndexController::class,
            Controller\Admin\LogController::class => Controller\Admin\LogController::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    // TODO See module BulkExport to add an action "log" to standard routes, if useful.
                    'history-log' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/history-log',
                            'defaults' => [
                                '__NAMESPACE__' => 'HistoryLog\Controller\Admin',
                                'controller' => Controller\Admin\IndexController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'history-log-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:resource/:id/log',
                                    'constraints' => [
                                        'resource' => 'item-set|item|media|resource',
                                        'action' => 'log|undelete',
                                    ],
                                    'defaults' => [
                                        'controller' => Controller\Admin\LogController::class,
                                        'resource' => 'resource',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Logs', // @translate
                'class' => 'o-icon- fa-history',
                'route' => 'admin/history-log',
                'resource' => Controller\Admin\IndexController::class,
                'privilege' => 'browse',
            ],
        ],
    ],
    */
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'historylog' => [
        'settings' => [
            'history_log_display' => [
                'items/browse',
                'items/show',
                'media/browse',
                'media/show',
                'item_sets/browse',
                'item_sets/show',
            ],
        ],
    ],
];
