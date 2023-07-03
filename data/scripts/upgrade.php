<?php declare(strict_types=1);

namespace HistoryLog;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var array $config
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.4.10', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `history_event`
CHANGE `part_of` `part_of` int NULL DEFAULT NULL AFTER `entity_name`;
ALTER TABLE `history_event`
CHANGE `operation` `operation` varchar(15) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `user_id`;
ALTER TABLE `history_change`
CHANGE `action` `action` varchar(7) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `event_id`;
SQL;
    $connection->executeStatement($sql);

    $message = new Message(
        'Structure of some stored data was updated. You need to reinstall the module.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'This is a beta version. Take care of your data.' // @translate
    );
    $messenger->addWarning($message);
}
