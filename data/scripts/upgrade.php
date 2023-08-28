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
    $sqls = <<<'SQL'
ALTER TABLE `history_event`
    CHANGE `part_of` `part_of` int NULL DEFAULT NULL AFTER `entity_name`,
    CHANGE `operation` `operation` varchar(15) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `user_id`;

ALTER TABLE `history_change`
    CHANGE `action` `action` varchar(7) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `event_id`,
    CHANGE `is_public` `is_public` TINYINT(1) DEFAULT NULL AFTER `type`;

ALTER TABLE `history_event`
    DROP INDEX `IDX_621602C081257D5D`,
    DROP INDEX `IDX_621602C016EFC72D`,
    DROP INDEX `IDX_621602C0A76ED395`,
    DROP INDEX `IDX_621602C0B23DB7B8`,
    ADD INDEX `idx_entity` (`entity_name`, `entity_id`),
    ADD INDEX `idx_user_id` (`user_id`),
    ADD INDEX `idx_operation` (`operation`),
    ADD INDEX `idx_created` (`created`);

ALTER TABLE `history_change`
    DROP INDEX `IDX_93D8B2D371F7E88B`,
    DROP INDEX `IDX_93D8B2D35BF54558`,
    ADD INDEX `idx_event_id` (`event_id`),
    ADD INDEX `idx_field` (`field`);
SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
            $services->get('Omeka\Logger')->err($e);
        }
    }

    $message = new Message(
        'Structure of some stored data was updated in version 3.4.10-beta-2. If you installed a version previously, the module should be uninstalled and reinstalled.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'The existing resources are not yet stored on install. It will be available in the next version.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'This is a beta version. Take care of your data.' // @translate
    );
    $messenger->addWarning($message);
}
