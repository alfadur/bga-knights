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
        'name'=> totranslate('Game Mode'),
        'default' => GameMode::STANDARD,
        'values' => [
            GameMode::STANDARD => [
                'name' => totranslate('Standard'),
                'tmdisplay' => totranslate('Standard')
            ],
            GameMode::TUTORIAL => [
                'name' => totranslate('Learning'),
                'is_coop' => true
            ],
            GameMode::DISORDER => [
                'name' => totranslate('Double Tiles'),
                'description' => totranslate('Each player has two tiles instead of one')
            ],
            GameMode::ADVANCED => [
                'name' => totranslate('Advanced'),
                'nobeginner' => true
            ]
        ],
        'displaycondition' => [
            GameMode::STANDARD => [
                [
                    'type' => 'minplayers',
                    'value' => 4
                ]
            ],
            GameMode::TUTORIAL => [
                [
                    'type' => 'minplayers',
                    'value' => 4
                ],
                [
                    'type' => 'maxplayers',
                    'value' => 7
                ]
            ],
            GameMode::DISORDER=> [
                [
                    'type' => 'maxplayers',
                    'value' => 4
                ]
            ],
            GameMode::ADVANCED => [
                [
                    'type' => 'minplayers',
                    'value' => 4
                ],
                [
                    'type' => 'maxplayers',
                    'value' => 7
                ]
            ]
        ]
    ]
];


