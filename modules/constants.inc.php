<?php

interface GameOption {
    const MODE = 'mode';
    const MODE_ID = 100;
    const COOP = 'coop';
    const COOP_ID = 101;
    const FIXED_SCORE = 'fixed_score';
    const FIXED_SCORE_ID = 102;
}

interface GameMode {
    const STANDARD = 1;
    const ADVANCED = 2;
    const DARKNESS = 3;
}

interface Fsm {
    const NAME = 'name';
    const DESCRIPTION = 'description';
    const OWN_DESCRIPTION = 'descriptionmyturn';
    const TYPE = 'type';
    const ACTION = 'action';
    const TRANSITIONS = 'transitions';
    const PROGRESSION = 'updateGameProgression';
    const POSSIBLE_ACTIONS = 'possibleactions';
    const ARGUMENTS = 'args';
}

interface FsmType {
    const MANAGER = 'manager';
    const GAME = 'game';
    const SINGLE_PLAYER = 'activeplayer';
    const MULTIPLE_PLAYERS = 'multipleactiveplayer';
}

interface State {
    const GAME_START = 1;

    const DEAL_TILES = 2;
    const INSPECT = 3;
    const QUESTION = 4;
    const ANSWER = 5;
    const VOTE = 6;
    const APPOINT_CAPTAIN = 7;
    const DISPATCH_ACTION = 8;
    const DEPLOY_KNIGHTS = 9;
    const FINAL_CHECK = 10;
    const NEXT_ROUND = 11;

    const GAME_END = 99;
}

interface Globals {
    const ACTIONS_TAKEN = 'actionsTaken';
    const ACTIONS_TAKEN_ID = 10;
    const CAPTAIN = 'captain';
    const CAPTAIN_ID = 11;
    const ASKER = 'asker';
    const ASKER_ID = 12;
    const ANSWER = 'answer';
    const ANSWER_ID = 13;
    const ROUND = 'round';
    const ROUND_ID = 14;
    const FIRST_PLAYER = 'first_player';
    const FIRST_PLAYER_ID = 15;
    const TEAM_WINS = 'team_wins';
    const TEAM_WINS_ID = 16;
}

interface Stats {
    const KNIGHT_WINS = 'knight_wins';
    const WITCH_WINS = 'witch_wins';

    const TABLE_STATS_LIST = [
        self::KNIGHT_WINS,
        self::WITCH_WINS
    ];

    const BEWITCHED = 'bewitched';
    const TRUTHS_TOLD = 'truths';
    const LIES_TOLD = 'lies';
    const APPOINTED = 'appointed';
    const MISTAKES = 'mistakes';

    const PLAYER_STATS_LIST = [
        self::KNIGHT_WINS,
        self::WITCH_WINS,
        self::BEWITCHED,
        self::TRUTHS_TOLD,
        self::LIES_TOLD,
        self::APPOINTED,
        self::MISTAKES
    ];
}

const MAX_ROUNDS = 3;