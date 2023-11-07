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
        $playerId = self::getArg('playerId', AT_posint, true);
        $this->game->inspect($playerId);
        self::ajaxResponse();
    }

    public function ask()
    {
        self::setAjaxMode();
        $playerId = self::getArg('playerId', AT_posint, true);
        $questionType = self::getArg('questionType', AT_posint, true);
        $this->game->ask($playerId, $questionType, []);
        self::ajaxResponse();
    }

    public function answer()
    {
        self::setAjaxMode();
        $answer = self::getArg('answer', AT_posint, true);
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

    public function cancel()
    {
        self::setAjaxMode();
        $this->game->cancel();
        self::ajaxResponse();
    }
}
  

