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
            Globals::ACTIONS_TAKEN => Globals::ACTIONS_TAKEN_ID,
            Globals::ASKER => Globals::ASKER_ID,
            Globals::ANSWER => Globals::ANSWER_ID
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
        shuffle($defaultColors);
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

        self::setupPlayers(self::loadPlayersBasicInfos(), $data['player_colors'], false);

        $captain = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT player_id AS id FROM player
            ORDER BY player_no ASC
            LIMIT 1
            EOF);
        self::setGameStateInitialValue(Globals::ACTIONS_TAKEN, 0);
        self::setGameStateInitialValue(Globals::CAPTAIN, $captain);
        self::setGameStateInitialValue(Globals::ASKER, 0);
        self::setGameStateInitialValue(Globals::ANSWER, 0);
    }

    static function setupPlayers(array $players, array $colors, bool $tutorial): void
    {
        $values = [];
        $characters = range($tutorial ? 1 : 0, count($players) - 1);
        shuffle($characters);

        foreach ($players as $playerId => ['player_color' => $color]) {
            $character = array_shift($characters);
            $token = array_search($color, $colors);
            $values[] = "($playerId, $character, $token)";
        }

        $args = implode(',', $values);
        self::DbQuery(<<<EOF
            INSERT INTO player_status
                (player_id, `character`, token)
            VALUES $args
            EOF);
    }

    function getGameProgression()
    {
        return 0;
    }

    protected function getAllDatas()
    {
        $currentPlayerId = self::getCurrentPlayerId();

        $fullPlayers = self::getCollectionFromDb(<<<EOF
            SELECT player_id AS id, player_score AS score, 
                   player_name AS name, player_color AS color, 
                   player_no AS no, `character`, token, inspected
            FROM player NATURAL JOIN player_status
            EOF);
        $players = [];

        foreach ($fullPlayers as $playerId => $fullPlayer) {
            $players[$playerId] = [
                'id' => $playerId,
                'name' => $fullPlayer['name'],
                'no' => $fullPlayer['no'],
                'color' => $fullPlayer['color'],
                'score' => $fullPlayer['score'],
                'token' => $fullPlayer['token'],
            ];
        }

        $players[$currentPlayerId]['character'] =
            $fullPlayers[$currentPlayerId]['character'];
        $inspectedId = $fullPlayers[$currentPlayerId]['inspected'];

        if ($inspectedId !== null) {
            $players[$inspectedId]['character'] =
                $fullPlayers[$inspectedId]['character'];
        }

        return ['players' => $players];
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

            $playerName = self::getPlayerNameById($activePlayerId);
            $targetName = self::getPlayerNameById($playerId);
            self::notifyAllPlayers('message', clienttranslate('${player_name1} inspects ${player_name2}\'s tile'), [
                'player_name1' => $playerName,
                'player_name2' => $targetName
            ]);

            $character = self::getUniqueValueFromDb(<<<EOF
                    SELECT `character` FROM player_status
                    WHERE player_id = $playerId
                    EOF);
            $message = $character === "0" ?
                clienttranslate('${player_name} turns out to be ${tileIcon}. You are now bewitched!') :
                clienttranslate('${player_name} turns out to be ${tileIcon}');
            self::notifyPlayer($activePlayerId, 'inspect', $message, [
                'player_name' => $targetName,
                'playerId' => $playerId,
                'character' => $character,
                'tileIcon' => $character,
                'preserve' => ['tileIcon']
            ]);

            self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
            $this->gamestate->nextState('');
        } else {
            throw new BgaUserException('Invalid player');
        }
    }

    function ask(int $playerId, int $valuesMask)
    {
        self::checkAction('ask');
        $activePlayerId = (int)self::getActivePlayerId();

        if ($playerId !== $activePlayerId) {
            $valuesMask = $valuesMask & 0b1111111;
            if ($valuesMask === 0 || $valuesMask === 0b1111111) {
                throw new BgaUserException("Invalid Number");
            }

            self::DbQuery(<<<EOF
                UPDATE player_status
                SET asked = $playerId, question = $valuesMask 
                WHERE player_id = $activePlayerId
                EOF);

            self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
            self::setGameStateValue(Globals::ASKER, $activePlayerId);

            $this->gamestate->nextState('');
        } else {
            throw new BgaUserException('Invalid player');
        }
    }

    function answer(bool $answer): void
    {
        self::checkAction('answer');
        $answer = $answer ? 1 : 0;
        $expectedAnswer = (int)self::getGameStateValue(Globals::ANSWER);
        if ($expectedAnswer > 0 && $expectedAnswer - 1 !== $answer) {
            throw new BgaUserException('Invalid answer');
        }

        $activePlayerId = self::getActivePlayerId();
        self::DbQuery(<<<EOF
            UPDATE player_status
            SET answer = $answer
            WHERE player_id = $activePlayerId
            EOF);
        $this->gamestate->nextState('');
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
        self::notifyAllPlayers('tiles', clienttranslate('Tiles are dealt'), []);
        $captain = self::getGameStateValue(Globals::CAPTAIN);
        $this->gamestate->changeActivePlayer($captain);
        $this->gamestate->nextState('');
    }

    function stAppoint(): void
    {

    }

    function determineAnswer(string $playerId, int $question): int
    {
        $witchTeam = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT COUNT(*)
            FROM player_status AS self 
                LEFT JOIN player_status as other
                ON other.player_id = self.inspected
            WHERE self.player_id = $playerId 
              AND (self.`character` = 0 OR other.`character` = 0)
            EOF);
        if ($witchTeam) {
            return 0;
        }
        return (int)self::getUniqueValueFromDb(<<<EOF
            SELECT (`character` & $question) <> 0
            FROM player_status 
            WHERE player_id = $playerId
            EOF) + 1;
    }

    function stDispatch(): void
    {
        $asker = (int)self::getGameStateValue(Globals::ASKER);
        if ($asker !== 0) {
            self::setGameStateValue(Globals::ASKER, 0);
            ['asked' => $asked, 'question' => $question, 'answer' => $answer] =
                self::getNonEmptyObjectFromDb(<<<EOF
                    SELECT self.asked, self.question, other.answer 
                    FROM player_status AS self 
                        LEFT JOIN player_status AS other
                        ON other.player_id = self.asked
                    WHERE self.player_id = $asker; 
                    EOF);
            if ($answer === null) {
                self::setGameStateValue(Globals::ANSWER,
                    $this->determineAnswer($asked, $question));
                $this->gamestate->changeActivePlayer($asked);
                $this->gamestate->nextState('answer');
                return;
            }
            $this->gamestate->changeActivePlayer($asker);
        }

        $playersCount = $this->getPlayersNumber();
        $actionsTaken = self::getGameStateValue(Globals::ACTIONS_TAKEN);
        if ($actionsTaken >= 2 * $playersCount) {
            $this->gamestate->nextState('vote');
        } else if ($actionsTaken >= $playersCount) {
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

    function argAnswer(): array
    {
        return [
            '_private' => [
                'active' => [
                    'answer' => self::getGameStateValue(Globals::ANSWER)
                ]
            ]
        ];
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
