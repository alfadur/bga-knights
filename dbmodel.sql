
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `tile`(
    `tile_id` TINYINT UNSIGNED NOT NULL,
    `player_id` INT UNSIGNED NULL,
    `character` TINYINT UNSIGNED NULL,
    `deployment` TINYINT UNSIGNED NULL,
    PRIMARY KEY(`tile_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS player_status(
    `player_id` INT UNSIGNED NOT NULL,
    `token` TINYINT UNSIGNED NOT NULL,
    `voted` INT UNSIGNED NULL,
    PRIMARY KEY(`player_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`voted`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `inspection`(
    `player_id` INT UNSIGNED NOT NULL,
    `tile_id` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY(`player_id`, `tile_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`tile_id`) REFERENCES `tile`(`tile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `question`(
    `question_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT UNSIGNED NOT NULL,
    `recipient_id` INT UNSIGNED NOT NULL,
    `tile_id` TINYINT UNSIGNED NOT NULL,
    `question` TINYINT UNSIGNED NOT NULL,
    `expression` VARCHAR(32) NULL,
    `expression_tiles` VARCHAR(8) NULL,
    `answer` TINYINT UNSIGNED NULL,
    PRIMARY KEY(`question_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`recipient_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`tile_id`) REFERENCES `tile`(`tile_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;