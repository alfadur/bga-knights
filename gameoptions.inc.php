<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

require_once('modules/constants.inc.php');

$game_options = [
    GameOption::MODE_ID => [
        'name'=> totranslate('Game Variant'),
        'default' => GameMode::STANDARD,
        'values' => [
            GameMode::STANDARD => [
                'name' => totranslate('Standard Rules'),
                'description' => totranslate('Tiles are always sequential. The Witch is always present in competitive mode'),
                'tmdisplay' => totranslate('Standard')
            ],
            GameMode::ADVANCED => [
                'name' => totranslate('Advanced Rules'),
                'description' => totranslate("A random subset of tiles used each round. The Witch may or may not be present in competitive mode"),
                'tmdisplay' => totranslate('Advanced'),
                'nobeginner' => true
            ],
            GameMode::DARKNESS => [
                'name' => totranslate('Groping in the dark'),
                'description' => totranslate('Game mode for 3 and 4 players. Two inspections per round for each player'),
                'tmdisplay' => totranslate('Darkness')
            ],
        ],
        'startcondition' => [
            GameMode::STANDARD => [
                [
                    'type' => 'minplayers',
                    'value' => 4,
                    'message' => totranslate('This mode requires at least 4 players')
                ]
            ],
            GameMode::ADVANCED => [
                [
                    'type' => 'minplayers',
                    'value' => 4,
                    'message' => totranslate('This mode requires at least 4 players')
                ],
                [
                    'type' => 'maxplayers',
                    'value' => 7,
                    'message' => totranslate('This mode cannot be played with 8 players')
                ]
            ],
            GameMode::DARKNESS => [
                [
                    'type' => 'minplayers',
                    'value' => 3,
                    'message' => totranslate('This mode can only be played with 3 or 4 players')
                ],
                [
                    'type' => 'maxplayers',
                    'value' => 4,
                    'message' => totranslate('This mode can only be played with 3 or 4 players')
                ]
            ]
        ]
    ],
    GameOption::COOP_ID => [
        'name' => totranslate('Cooperative Game'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Disabled'),
            ],
            1 => [
                'name' => totranslate('Enabled'),
                'tmdisplay' => totranslate('Cooperative'),
                'description' => totranslate('Cooperative game without the Witch'),
                'is_coop' => true
            ]
        ],
        'startcondition' => [
            1 => [
                [
                    'type' => 'maxplayers',
                    'value' => 7,
                    'message' => totranslate('Cooperative mode cannot be played with 8 players')
                ]
            ]
        ]
    ]
];