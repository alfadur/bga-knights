<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * SevenKnightsBewitched game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = [
    State::GAME_START => [
        Fsm::NAME => 'gameSetup',
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stGameSetup',
        Fsm::TRANSITIONS => ['' => State::DEAL_TILES]
    ],

    State::DEAL_TILES => [
        Fsm::NAME => 'dealTiles',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => clienttranslate('Tiles are dealt'),
        Fsm::ACTION => 'stDealTiles',
        Fsm::TRANSITIONS => ['' => State::INSPECT]
    ],

    State::INSPECT => [
        Fsm::NAME => "inspect",
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must inspect another player\'s tile'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must inspect another player\'s tile'),
        Fsm::POSSIBLE_ACTIONS => ['inspect'],
        Fsm::TRANSITIONS => ['' => State::DISPATCH_ACTION]
    ],

    State::QUESTION => [
        Fsm::NAME => 'question',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must ask another player a question'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must ask another player a question'),
        Fsm::POSSIBLE_ACTIONS => ['ask'],
        Fsm::TRANSITIONS => ['' => State::DISPATCH_ACTION]
    ],

    State::ANSWER => [
        Fsm::NAME => 'answer',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must answer the question'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must answer the question'),
        Fsm::POSSIBLE_ACTIONS => ['answer'],
        Fsm::ARGUMENTS => 'argAnswer',
        Fsm::TRANSITIONS => ['' => State::DISPATCH_ACTION]
    ],

    State::VOTE => [
        Fsm::NAME => 'vote',
        Fsm::TYPE => FsmType::MULTIPLE_PLAYERS,
        Fsm::DESCRIPTION => clienttranslate('Other players must cast a vote for the captain'),
        Fsm::OWN_DESCRIPTION => clienttranslate('You must cast a vote for the captain'),
        Fsm::ACTION => 'stMakeEveryoneActive',
        Fsm::POSSIBLE_ACTIONS => ['vote', 'cancel'],
        Fsm::TRANSITIONS => ['' => State::APPOINT_CAPTAIN]
    ],

    State::APPOINT_CAPTAIN => [
        Fsm::NAME => 'appointCaptain',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stAppoint',
        Fsm::TRANSITIONS => [
            'appoint' => State::DEPLOY_KNIGHTS,
            'end' => State::ROUND_END
        ]
    ],

    State::DISPATCH_ACTION => [
        Fsm::NAME => 'dispatch',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stDispatch',
        Fsm::TRANSITIONS => [
            'inspect' => State::INSPECT,
            'question' => State::QUESTION,
            'answer' => State::ANSWER,
            'vote' => State::VOTE]
    ],

    State::DEPLOY_KNIGHTS => [
        Fsm::NAME => 'deployKnights',
        Fsm::TYPE => FsmType::SINGLE_PLAYER,
        Fsm::DESCRIPTION => clienttranslate('${actplayer} must choose a knight to deploy to position ${n}'),
        Fsm::OWN_DESCRIPTION => clienttranslate('${you} must choose a knight to deploy to position ${n}'),
        Fsm::ARGUMENTS => 'argDeployKnights',
        Fsm::TRANSITIONS => [
            'deploy' => State::DEPLOY_KNIGHTS,
            'check' => State::FINAL_CHECK]
    ],

    State::FINAL_CHECK => [
        Fsm::NAME => 'finalCheck',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stFinalCheck',
        Fsm::TRANSITIONS => ['' => State::ROUND_END]
    ],

    State::ROUND_END => [
        Fsm::NAME => 'roundEnd',
        Fsm::TYPE => FsmType::GAME,
        Fsm::DESCRIPTION => '',
        Fsm::ACTION => 'stRoundEnd',
        Fsm::TRANSITIONS => [
            'continue' => State::DEAL_TILES,
            'end' => State::GAME_END
        ]
    ],

    State::GAME_END => [
        Fsm::NAME => 'gameEnd',
        Fsm::TYPE => FsmType::MANAGER,
        Fsm::DESCRIPTION => clienttranslate("End of game"),
        Fsm::ACTION => 'stGameEnd',
        Fsm::ARGUMENTS => 'argGameEnd'
    ]
];