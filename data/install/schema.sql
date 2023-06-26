CREATE TABLE `history_event` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `entity_id` INT NOT NULL,
    `entity_name` VARCHAR(31) NOT NULL,
    `part_of` INT DEFAULT 0 NOT NULL,
    `user_id` INT NOT NULL,
    `operation` VARCHAR(6) NOT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX IDX_621602C081257D5D (`entity_id`),
    INDEX IDX_621602C016EFC72D (`entity_name`),
    INDEX IDX_621602C0A76ED395 (`user_id`),
    INDEX IDX_621602C0B23DB7B8 (`created`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
CREATE TABLE `history_change` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `event_id` INT NOT NULL,
    `action` VARCHAR(6) NOT NULL,
    `field` VARCHAR(190) NOT NULL,
    `type` VARCHAR(190) DEFAULT NULL,
    `lang` VARCHAR(190) DEFAULT NULL,
    `value` LONGTEXT DEFAULT NULL,
    `uri` LONGTEXT DEFAULT NULL,
    `value_resource_id` INT DEFAULT NULL,
    `is_public` TINYINT(1) DEFAULT NULL,
    `value_annotation_id` INT DEFAULT NULL,
    INDEX IDX_93D8B2D371F7E88B (`event_id`),
    INDEX IDX_93D8B2D35BF54558 (`field`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
ALTER TABLE `history_change` ADD CONSTRAINT FK_93D8B2D371F7E88B FOREIGN KEY (`event_id`) REFERENCES `history_event` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `numeral` (
    `i` TINYINT unsigned NOT NULL,
    PRIMARY KEY (`i`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
INSERT INTO `numeral` (`i`)
VALUES (0), (1), (2), (3), (4), (5), (6), (7), (8), (9)
ON DUPLICATE KEY UPDATE
    `i` = `i`
;
