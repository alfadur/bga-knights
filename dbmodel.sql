
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `tile`(
    `tile_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT UNSIGNED NULL,
    `character` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY(`tile_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS player_status(
    `player_id` INT UNSIGNED NOT NULL,
    `token` TINYINT UNSIGNED NOT NULL,
    `inspected` TINYINT UNSIGNED NULL,
    `asked` INT UNSIGNED NULL,
    `asked_tile` TINYINT UNSIGNED NULL,
    `question` TINYINT UNSIGNED NULL,
    `answer` TINYINT UNSIGNED NULL,
    `voted` INT UNSIGNED NULL,
    PRIMARY KEY(`player_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`voted`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`inspected`) REFERENCES `tile`(`tile_id`),
    FOREIGN KEY(`asked`) REFERENCES `player`(`player_id`),
    FOREIGN KEY(`asked_tile`) REFERENCES `tile`(`tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;