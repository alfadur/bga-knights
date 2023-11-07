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
  
require_once(APP_BASE_PATH."view/common/game.view.php");
  
class view_sevenknightsbewitched_sevenknightsbewitched extends game_view
{
    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "sevenknightsbewitched";
    }
    function build_page($viewArgs)
    {
    }
}
