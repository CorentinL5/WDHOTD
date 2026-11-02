-- --------------------------------------------------------
-- CREATE DATABASE (for InfinityFree / phpMyAdmin)
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `wdhotd`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `wdhotd`;

-- --------------------------------------------------------
-- Preserve existing tables and data
-- --------------------------------------------------------

-- Existing data is preserved. For an existing installation run: php migrate.php

-- --------------------------------------------------------
-- TABLE: OTDItem
-- Single-table model for all On This Day data
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `OTDItem` (
                           `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Type of entry
                           `type` ENUM('events','births','deaths','holidays') NOT NULL,

    -- Date information
                           `year` INT NULL,
                           `month` INT NOT NULL,
                           `day` INT NOT NULL,

    -- Wikipedia identifiers
                           `pageid` INT NULL,
                           `canonical_title` VARCHAR(255) NULL,
                           `normalized_title` VARCHAR(255) NULL,
                           `selected_title` VARCHAR(255) NULL,

    -- Extracts
                           `extract_short` TEXT NULL,
                           `event_text` TEXT NULL,
                           `featured` TINYINT(1) NOT NULL DEFAULT 0,
                           `extract_html` MEDIUMTEXT NULL,

    -- Visual data
                           `thumbnail` TEXT NULL,
                           `wikipedia_url` TEXT NULL,

    -- Raw JSON full entry
                           `raw_json` LONGTEXT NULL,

    -- Timestamp
                           `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                           PRIMARY KEY (`id`),

    -- Useful indexes for fast search
                           INDEX `idx_type` (`type`),
                           INDEX `idx_pageid` (`pageid`),
                           INDEX `idx_day_month` (`month`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `OTDImport` (
    `month` TINYINT UNSIGNED NOT NULL,
    `day` TINYINT UNSIGNED NOT NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
    `attempted_at` DATETIME NULL,
    `succeeded_at` DATETIME NULL,
    `item_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message` TEXT NULL,
    PRIMARY KEY (`month`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------
-- Table: WDLogs (for page visit logs)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `WDLogs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page` VARCHAR(255) NOT NULL,
    `referer` TEXT NULL,
    `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_agent` TEXT NULL,
    `os` VARCHAR(64) NULL,
    `browser` VARCHAR(64) NULL,
    `device` VARCHAR(32) NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

