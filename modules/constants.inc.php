<?php

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
    const ROUND_END = 11;

    const GAME_END = 99;
}

interface Globals {
    const ACTIONS_TAKEN = 'actionsTaken';
    const ACTIONS_TAKEN_ID = 10;
    const CAPTAIN = 'captain';
    const CAPTAIN_ID = 11;
    const ASKER = 'asker';
    const ASKER_ID = 12;
}