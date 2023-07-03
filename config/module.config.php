<?php declare(strict_types=1);

namespace HistoryLog;

return [
    'api_adapters' => [
        'invokables' => [
            'history_events' => Api\Adapter\HistoryEventAdapter::class,
            // TODO Api HistoryChange is probably useless: check if it can be replaced by hydrator.
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
    'view_helpers' => [
        'invokables' => [
            'historyEventsLink' => View\Helper\HistoryEventsLink::class,
            'historyLog' => View\Helper\HistoryLog::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        /*
        'factories' => [
            Form\SearchForm::class => Service\Form\SearchFormFactory::class,
        ],
        */
    ],
    'controllers' => [
        'invokables' => [
            // TODO Controller HistoryChange is probably useless: check if it can be removed.
            Controller\Admin\HistoryChangeController::class => Controller\Admin\HistoryChangeController::class,
            Controller\Admin\IndexController::class => Controller\Admin\IndexController::class,
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
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => 'show-details|show',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                            'entity' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:entity-name/:entity-id[/:action]',
                                    'constraints' => [
                                        'entity-name' => 'resource|item-set|item|media',
                                        'id' => '\d+',
                                        'action' => 'log',
                                    ],
                                    'defaults' => [
                                        'action' => 'log',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'column_types' => [
        'invokables' => [
            'history_log_action' => ColumnType\Action::class,
            'history_log_changes' => ColumnType\Changes::class,
            'history_log_created' => ColumnType\Created::class,
            'history_log_entity' => ColumnType\Entity::class,
            'history_log_entity_id' => ColumnType\EntityId::class,
            'history_log_entity_name' => ColumnType\EntityName::class,
            'history_log_event' => ColumnType\Event::class,
            'history_log_event_last_info' => ColumnType\HistoryEventLastInfo::class,
            'history_log_events_link' => ColumnType\HistoryEventsLink::class,
            'history_log_field' => ColumnType\Field::class,
            'history_log_id' => ColumnType\Id::class,
            'history_log_operation' => ColumnType\Operation::class,
            'history_log_part_of' => ColumnType\PartOf::class,
            'history_log_user' => ColumnType\User::class,
            'history_log_user_id' => ColumnType\UserId::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'history_events' => [
                // ['type' => 'history_log_changes'],
                ['type' => 'history_log_operation'],
                ['type' => 'history_log_entity'],
                ['type' => 'history_log_part_of'],
                ['type' => 'history_log_user'],
                ['type' => 'history_log_created'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'history_events' => [
                'sort_by' => 'history_log_created',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'history_events' => [
                'history_log_created' => 'Created', // @translate
                'history_log_entity_name' => 'Entity name', // @translate
                'history_log_entity_id' => 'Entity id', // @translate
                'history_log_part_of' => 'Part of', // @translate
                'history_log_user_id' => 'User id', // @translate
                'history_log_operation' => 'Operation', // @translate
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'history-log' => [
                'label' => 'History logs', // @translate
                'class' => 'o-icon- fa-history',
                'route' => 'admin/history-log',
                'resource' => Controller\Admin\IndexController::class,
                'privilege' => 'browse',
            ],
        ],
    ],
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
        // Needed to display the config form.
        'config' => [
        ],
        'settings' => [
            'history_log_display' => [
                'items/show',
                'media/show',
                'item_sets/show',
            ],
        ],
    ],
];
