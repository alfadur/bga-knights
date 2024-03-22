<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * sevenknightsbewitched.action.php
 *
 * SevenKnightsBewitched main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/sevenknightsbewitched/sevenknightsbewitched/myAction.html", ...)
 *
 */

class action_sevenknightsbewitched extends APP_GameAction
{
    // Constructor: please do not modify
    public function __default()
    {
        if(self::isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
        } else {
            $this->view = 'sevenknightsbewitched_sevenknightsbewitched';
            self::trace('Complete reinitialization of board game');
        }
    }

    public function inspect()
    {
        self::setAjaxMode();
        $tileId = self::getArg('tileId', AT_posint, true);
        $this->game->inspect($tileId);
        self::ajaxResponse();
    }

    public function ask()
    {
        self::setAjaxMode();
        $playerId = self::getArg('playerId', AT_posint, true);
        $tileId = self::getArg('tileId', AT_posint, true);
        $valuesMask = self::getArg('values', AT_posint, true);
        $this->game->ask($playerId, $tileId, $valuesMask);
        self::ajaxResponse();
    }

    public function askMany()
    {
        self::setAjaxMode();
        $playerId = self::getArg('playerId', AT_posint, true);
        $tileIds = self::getArg('tileIds', AT_numberlist, true);
        $expression = self::getArg('expression', AT_alphanum, true);
        $this->game->askMany($playerId, explode(',', $tileIds), $expression);
        self::ajaxResponse();
    }

    public function answer()
    {
        self::setAjaxMode();
        $answer = self::getArg('answer', AT_bool, true);
        $this->game->answer($answer);
        self::ajaxResponse();
    }

    public function vote()
    {
        self::setAjaxMode();
        $playerId = self::getArg('playerId', AT_posint, true);
        $this->game->vote($playerId);
        self::ajaxResponse();
    }

    public function deploy() {
        self::setAjaxMode();
        $tileId = self::getArg('tileId', AT_posint, true);
        $position = self::getArg('position', AT_posint, true);
        $this->game->deploy($tileId, $position);
        self::ajaxResponse();
    }

    public function check() {
        self::setAjaxMode();
        $this->game->check();
        self::ajaxResponse();
    }

    public function cancel()
    {
        self::setAjaxMode();
        $this->game->cancel();
        self::ajaxResponse();
    }

    public function updateNotes()
    {
        self::setAjaxMode();
        $notes = self::getArg("notes", AT_numberlist, true);
        $this->game->updateNotes($notes);
        self::ajaxResponse();
    }
}
  

