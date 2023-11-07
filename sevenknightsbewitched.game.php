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
  * sevenknightsbewitched.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');
require_once('modules/constants.inc.php');

class SevenKnightsBewitched extends Table
{
    function __construct()
    {
        parent::__construct();

        self::initGameStateLabels([
            Globals::CAPTAIN => Globals::CAPTAIN_ID,
            Globals::ACTIONS_TAKEN => Globals::ACTIONS_TAKEN_ID
        ]);
    }

    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "sevenknightsbewitched";
    }

    protected function setupNewGame($players, $options = [])
    {
        $data = self::getGameinfos();
        $defaultColors = $data['player_colors'];
        $playerValues = [];

        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);

            $name = addslashes($player['player_name']);
            $avatar = addslashes($player['player_avatar']);
            $playerValues[] = "('$playerId','$color','$player[player_canal]','$name','$avatar')";
        }

        $args = implode(',', $playerValues);
        $query = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES $args";
        self::DbQuery($query);

        self::reattributeColorsBasedOnPreferences($players, $data['player_colors']);
        self::reloadPlayersBasicInfos();

        self::setGameStateInitialValue(Globals::ACTIONS_TAKEN, 0);
        self::setGameStateInitialValue(Globals::CAPTAIN, 0);
    }

    function getGameProgression()
    {
        return 0;
    }

    protected function getAllDatas()
    {
        return [];
    }

    function inspect(int $playerId): void
    {
        self::checkAction('inspect');
        $activePlayerId = (int)self::getActivePlayerId();
        if ($playerId !== $activePlayerId) {
            self::DbQuery(<<<EOF
                UPDATE player_status 
                SET inspected = $playerId
                WHERE player_id = $activePlayerId  
                EOF);
            self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
            $this->gamestate->nextState('inspect');
        }
    }

    function ask(int $playerId, int $questionType, array $questionArgs)
    {
        self::checkAction('ask');
        $activePlayerId = (int)self::getActivePlayerId();
        if ($playerId !== $activePlayerId) {
            $questionJson = '';
            self::DbQuery(<<<EOF
                UPDATE player_status
                SET asked = $playerId AND question = $questionJson 
                WHERE player_id = $activePlayerId
                EOF);
            $this->gamestate->nextState('ask');
        }
    }

    function answer(int $answer): void
    {
        self::checkAction('answer');
        $answer = $answer | 0x1;
        $activePlayerId = self::getActivePlayerId();
        self::DbQuery(<<<EOF
            UPDATE player_status
            SET answer = $answer
            WHERE player_id = $activePlayerId
            EOF);
        $this->gamestate->nextState('answer');
    }

    function vote(int $playerId): void
    {
        self::checkAction('vote');
        $activePlayerId = (int)self::getActivePlayerId();
        if ($playerId !== $activePlayerId) {
            self::DbQuery(<<<EOF
                UPDATE player_status 
                SET voted = $playerId
                WHERE player_id = $activePlayerId
                EOF);
            $this->gamestate->setPlayerNonMultiactive($activePlayerId, 'vote');
            $this->gamestate->nextState('vote');
        }
    }

    function cancel(): void
    {
        $this->gamestate->checkPossibleAction('cancel');
        if (!(self::isSpectator() || self::isCurrentPlayerZombie())) {
            $playerId = (int)self::getCurrentPlayerId();
            if (!$this->gamestate->isPlayerActive($playerId)) {
                $this->gamestate->setPlayersMultiactive([$playerId], '');
            }
        }
    }

    function stDealTiles(): void
    {

    }

    function stAppoint(): void
    {

    }

    function stDispatch(): void
    {
        $playersCount = $this->getPlayersNumber();
        $actionsTaken = self::getGameStateValue(Globals::ACTIONS_TAKEN);
        if ($actionsTaken >= 2 * $playersCount) {
            $this->gamestate->nextState('vote');
        } else if ($actionsTaken > $playersCount) {
            self::activeNextPlayer();
            $this->gamestate->nextState('question');
        } else {
            self::activeNextPlayer();
            $this->gamestate->nextState('inspect');
        }
    }

    function stFinalCheck(): void
    {

    }

    function zombieTurn($state, $activePlayer)
    {
        $stateName = $state['name'];

        if ($state['type'] === FsmType::SINGLE_PLAYER) {
        } else if ($state['type'] === FsmType::MULTIPLE_PLAYERS) {
        } else {
            throw new feException("Zombie mode not supported at this game state: $stateName");
        }
    }

    function upgradeTableDb($fromVersion)
    {

    }    
}
