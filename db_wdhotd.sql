-- --------------------------------------------------------
-- CREATE DATABASE (for InfinityFree / phpMyAdmin)
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `wdhotd`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `wdhotd`;

-- --------------------------------------------------------
-- DROP old table (safe for re-import)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `OTDItem`;

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

