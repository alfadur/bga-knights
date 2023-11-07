
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS player_status(
    `player_id` INT UNSIGNED NOT NULL,
    `inspected` INT UNSIGNED NULL,
    `asked` INT UNSIGNED NULL,
    `voted` INT UNSIGNED NULL,
    `character` TINYINT UNSIGNED NOT NULL,
    `answer` TINYINT NULL
    `question` JSON NULL,
    PRIMARY KEY(`player_id`),
    FOREIGN KEY(`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1