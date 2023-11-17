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
            Globals::ANSWER => Globals::ANSWER_ID,

            GameOption::MODE => GameOption::MODE_ID
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

        $mode = self::getGameStateValue(GameOption::MODE);
        self::setupPlayers(self::loadPlayersBasicInfos(), $data['player_colors'], $mode);

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

    static function setupPlayers(array $players, array $colors, int $mode): void
    {
        $tilesPerPlayer = 1;
        $additionalCharacters =
            $mode === GameMode::DISORDER ? 3 :
                (count($players) < 6 ? 1 : 0);

        switch ($mode) {
            case GameMode::STANDARD:
                $characters = range(0,count($players) + $additionalCharacters - 1);
                break;
            case GameMode::TUTORIAL:
                $characters = range(1, count($players) + $additionalCharacters);
                break;
            case GameMode::ADVANCED:
                $characters = range(0, 7);
                shuffle($characters);
                $characters = array_slice($characters, 0, count($players) + $additionalCharacters);
                break;
            case GameMode::DISORDER:
                $batch = range(1, 7);
                shuffle($batch);
                $characters = array_slice($batch, 0, count($players));
                $batch = array_slice($batch, count($players));
                $batch[] = 0;
                shuffle($batch);

                $characters = array_merge($characters, array_slice($batch, 0, $additionalCharacters));
                break;
        }
        shuffle($characters);

        $playerValues = [];
        $tileValues = [];

        foreach ($players as $playerId => ['player_color' => $color]) {
            $token = array_search($color, $colors);

            $playerValues[] = "($playerId, $token)";

            for ($tile = 0; $tile < $tilesPerPlayer; ++$tile) {
                $character = array_shift($characters);
                $tileValues[] = "($playerId, $character)";
            }
        }

        for ($i = 0; $i < $additionalCharacters; ++$i) {
            $character = array_shift($characters);
            $tileValues[] = "(NULL, $character)";
        }

        $tileArgs = implode(',', $tileValues);
        self::DbQuery(<<<EOF
            INSERT INTO tile(player_id, `character`)
            VALUES $tileArgs
            EOF);

        $playerArgs = implode(',', $playerValues);
        self::DbQuery(<<<EOF
            INSERT INTO player_status(player_id, token)
            VALUES $playerArgs
            EOF);
    }

    function getGameProgression()
    {
        return 0;
    }

    protected function getAllDatas()
    {
        $currentPlayerId = self::getCurrentPlayerId();

        $players = self::getCollectionFromDb(<<<EOF
            SELECT player_id AS id, player_score AS score, 
                   player_name AS name, player_color AS color, 
                   player_no AS no, token, inspected
            FROM player NATURAL JOIN player_status
            EOF);

        $tiles = self::getObjectListFromDb(<<<EOF
            SELECT tile.player_id, tile_id AS id, 
                   (CASE WHEN tile.player_id = $currentPlayerId
                        OR tile_id = player_status.inspected
                    THEN tile.`character` END) AS `character` 
            FROM tile INNER JOIN player_status
                ON player_status.player_id = $currentPlayerId            
            EOF);

        $gameMode = (int)self::getGameStateValue(GameOption::MODE);
        $hasWitch = $gameMode !== GameMode::TUTORIAL;

        return [
            'players' => $players,
            'tiles' => $tiles,
            'hasWitch' => $hasWitch
        ];
    }

    function inspect(int $tileId): void
    {
        self::checkAction('inspect');
        $activePlayerId = (int)self::getActivePlayerId();

        $mode = (int)self::getGameStateValue(GameOption::MODE);

        if ($mode === GameMode::DISORDER) {
            $actionsTaken = (int)self::getGameStateValue(Globals::ACTIONS_TAKEN);

            if ($actionsTaken < self::getPlayersNumber()) {
                $targetCheck = "target.player_id <> $activePlayerId";
            } else {
                $targetCheck = 'target.player_id IS NULL';
            }
        } else {
            $targetCheck =
                "(target.player_id IS NULL OR target.player_id <> $activePlayerId)";
        }

        self::DbQuery(<<<EOF
            UPDATE player_status AS self 
                INNER JOIN tile AS target ON tile_id = $tileId
            SET inspected = $tileId
            WHERE self.player_id = $activePlayerId                 
                AND $targetCheck
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException('Invalid player');
        }

        $playerName = self::getPlayerNameById($activePlayerId);
        ['character' => $character, 'player_name' => $targetName] =
            self::getNonEmptyObjectFromDb(<<<EOF
                SELECT `character`, `player_name` 
                FROM tile NATURAL LEFT JOIN player
                WHERE tile_id = $tileId
                EOF);
        $targetName ??= 'Knight-Errant';

        self::notifyAllPlayers('message', clienttranslate('${player_name1} inspects ${player_name2}\'s tile'), [
            'player_name1' => $playerName,
            'player_name2' => $targetName
        ]);

        $message = $character === "0" ?
            clienttranslate('${player_name} turns out to be ${tileIcon}. You are now bewitched!') :
            clienttranslate('${player_name} turns out to be ${tileIcon}');
        self::notifyPlayer($activePlayerId, 'inspect', $message, [
            'player_name' => $targetName,
            'tileId' => $tileId,
            'character' => $character,
            'tileIcon' => $character,
            'preserve' => ['tileIcon']
        ]);

        self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
        $this->gamestate->nextState('');
    }

    function ask(int $playerId, int $tileId, int $valuesMask)
    {
        self::checkAction('ask');
        $activePlayerId = (int)self::getActivePlayerId();

        if ($playerId === $activePlayerId) {
            throw new BgaUserException('Invalid player');
        }

        $valuesMask = $valuesMask & 0b1111111;
        if ($valuesMask === 0 || $valuesMask === 0b1111111) {
            throw new BgaUserException("Invalid Number");
        }

        self::DbQuery(<<<EOF
            UPDATE player_status AS self
                INNER JOIN player_status as recipient
                    ON recipient.player_id = $playerId
                INNER JOIN tile AS target 
                    ON tile_id = $tileId
            SET self.asked = $playerId, 
                self.asked_tile = $tileId, 
                self.question = $valuesMask 
            WHERE self.player_id = $activePlayerId
                AND self.inspected <> tile_id
                AND (recipient.player_id = target.player_id 
                    OR recipient.inspected = tile_id)
            EOF);
        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException('Invalid player/tile');
        }

        self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
        self::setGameStateValue(Globals::ASKER, $activePlayerId);

        $this->gamestate->nextState('');
    }

    function answer(bool $answer): void
    {
        self::checkAction('answer');
        $answer = $answer ? 1 : 0;
        $asker = (int)self::getGameStateValue(Globals::ASKER);
        $expectedAnswer = (int)self::getGameStateValue(Globals::ANSWER);
        if ($expectedAnswer > 0 && $expectedAnswer - 1 !== $answer) {
            throw new BgaUserException('Invalid answer');
        }

        $activePlayerId = self::getActivePlayerId();
        self::DbQuery(<<<EOF
            UPDATE player_status
            SET answer = $answer
            WHERE player_id = $asker
            EOF);
        $this->gamestate->nextState('');
    }

    function vote(int $playerId): void
    {
        self::checkAction('vote');
        $currentPlayerId = (int)self::getCurrentPlayerId();
        if ($playerId === $currentPlayerId) {
            throw new BgaUserException('Invalid player');
        }

        self::DbQuery(<<<EOF
            UPDATE player_status 
            SET voted = $playerId
            WHERE player_id = $currentPlayerId
            EOF);
        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException('Invalid player');
        }

        $this->gamestate->setPlayerNonMultiactive($currentPlayerId, '');
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

    function determineAnswer(string $playerId, int $tileId, int $question): int
    {
        $witchTeam = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT COUNT(*)
            FROM player_status AS self INNER JOIN tile as target 
                ON tile_id = self.inspected 
                    OR target.player_id = self.player_id
            WHERE self.player_id = $playerId 
              AND target.`character` = 0
            EOF);
        if ($witchTeam) {
            return 0;
        }
        return (int)self::getUniqueValueFromDb(<<<EOF
            SELECT (`character` & $question) <> 0
            FROM tile WHERE tile_id = $tileId
            EOF) + 1;
    }

    function stAppoint(): void
    {
        $lastCaptain = (int)self::getGameStateValue(Globals::CAPTAIN);
        $playerCount = self::getPlayersNumber();
        $captain = self::getUniqueValueFromDb(<<<EOF
            SELECT self.player_id FROM (player_status NATURAL JOIN player) AS self
                INNER JOIN (player_status NATURAL JOIN player) AS voter
                    ON voter.voted = self.player_id
            GROUP BY self.player_id
            ORDER BY COUNT(*) DESC,
                COUNT(CASE WHEN voter.player_id = self.player_id THEN 1 END) ASC,
                (self.player_no 
                     - (SELECT player_no FROM player 
                        WHERE player_id = $lastCaptain)
                     + $playerCount) % $playerCount DESC
            LIMIT 1
            EOF);

        self::setGameStateValue(Globals::CAPTAIN, $captain);
        $playerName = self::getPlayerNameById($captain);

        self::notifyAllPlayers('message', clienttranslate('${player_name} is appointed as the captain'), [
            'player_name' => $playerName
        ]);
        $this->gamestate->nextState('');
    }

    function stDispatch(): void
    {
        $asker = (int)self::getGameStateValue(Globals::ASKER);
        if ($asker !== 0) {
            ['asked' => $asked, 'asked_tile' => $askedTile, 'question' => $question, 'answer' => $answer] =
                self::getNonEmptyObjectFromDb(<<<EOF
                    SELECT asked, asked_tile, question, answer 
                    FROM player_status
                    WHERE player_id = $asker; 
                    EOF);
            if ($answer === null) {
                self::setGameStateValue(Globals::ANSWER,
                    $this->determineAnswer($asked, $askedTile, $question));
                $this->gamestate->changeActivePlayer($asked);
                $this->gamestate->nextState('answer');
                return;
            }

            self::setGameStateValue(Globals::ASKER, 0);
            $this->gamestate->changeActivePlayer($asker);
        }

        $mode = (int)self::getGameStateValue(GameOption::MODE);
        $inspectsPerPlayer = $mode === GameMode::DISORDER ? 2 : 1;

        $playersCount = $this->getPlayersNumber();
        $actionsTaken = self::getGameStateValue(Globals::ACTIONS_TAKEN);
        if ($actionsTaken >= ($inspectsPerPlayer + 1) * $playersCount) {
            $this->gamestate->nextState('vote');
        } else if ($actionsTaken >= $inspectsPerPlayer * $playersCount) {
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
