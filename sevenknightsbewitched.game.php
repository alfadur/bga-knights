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
            Globals::ROUND => Globals::ROUND_ID,

            GameOption::MODE => GameOption::MODE_ID
        ]);
    }

    protected function getGameName()
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

        self::setupPlayers(self::loadPlayersBasicInfos(), $data['player_colors']);
        self::setupTiles();

        $captain = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT player_id AS id FROM player
            ORDER BY player_no ASC
            LIMIT 1
            EOF);

        self::setGameStateInitialValue(Globals::CAPTAIN, $captain);
        $this->gamestate->changeActivePlayer($captain);
    }

    static function setupPlayers(array $players, array $colors) {
        $values = [];
        foreach ($players as $playerId => ['player_color' => $color]) {
            $token = array_search($color, $colors);
            $values[] = "($playerId, $token)";
        }

        $args = implode(',', $values);
        self::DbQuery(<<<EOF
            INSERT INTO player_status(player_id, token)
            VALUES $args
            EOF);
    }

    function setupTiles(): void
    {
        $players = $this->loadPlayersBasicInfos();
        $mode = (int)self::getGameStateValue(GameOption::MODE);

        $tilesPerPlayer = 1;
        $additionalCharacters =
            $mode === GameMode::DISORDER ? 3 :
                (count($players) < 6 ? 1 : 0);

        switch ($mode) {
            case GameMode::STANDARD:
                $characters = range(0,count($players) + $additionalCharacters - 1);
                shuffle($characters);
                break;
            case GameMode::TUTORIAL:
                $characters = range(1, count($players) + $additionalCharacters);
                shuffle($characters);
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

        $tileIndex = 1;
        $values = [];

        foreach ($players as $playerId => $_) {
            for ($tile = 0; $tile < $tilesPerPlayer; ++$tile) {
                $character = array_shift($characters);
                $values[] = "($tileIndex, $playerId, $character)";

                self::notifyPlayer($playerId, 'inspect', clienttranslate('You are ${tileIcon}'), [
                    'tileId' => $tileIndex,
                    'character' => $character,
                    'tileIcon' => $character,
                    'preserve' => ['tileIcon']
                ]);

                ++$tileIndex;
            }
        }

        for ($i = 0; $i < $additionalCharacters; ++$i) {
            $character = array_shift($characters);
            $values[] = "($tileIndex, NULL, $character)";
            ++$tileIndex;
        }

        $args = implode(',', $values);
        self::DbQuery(<<<EOF
            INSERT INTO tile(tile_id, player_id, `character`)
            VALUES $args
            EOF);
    }

    function getGameProgression()
    {
        return 0;
    }

    static function getTiles(int $currentPlayerId): array {
        return self::getObjectListFromDb(<<<EOF
            SELECT tile.player_id, tile.tile_id AS id, 
               (CASE WHEN tile.player_id = $currentPlayerId
                    OR inspection.tile_id IS NOT NULL
                THEN tile.`character` END) AS `character` 
            FROM tile LEFT JOIN inspection
                ON inspection.player_id = $currentPlayerId
                    AND inspection.tile_id = tile.tile_id
            EOF);
    }

    protected function getAllDatas()
    {
        $currentPlayerId = self::getCurrentPlayerId();

        $players = self::getCollectionFromDb(<<<EOF
            SELECT player_id AS id, player_score AS score, 
                   player_name AS name, player_color AS color, 
                   player_no AS no, token
            FROM player NATURAL JOIN player_status
            EOF);

        $tiles = self::getTiles($currentPlayerId);

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
            INSERT INTO inspection(player_id, tile_id)
            SELECT self.player_id, tile_id
            FROM player_status AS self 
                INNER JOIN tile AS target ON tile_id = $tileId
            WHERE self.player_id = $activePlayerId                 
                AND $targetCheck
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException('Invalid player');
        }

        $playerName = self::getPlayerNameById($activePlayerId);
        ['tile_id' => $tileId, 'character' => $character, 'player_name' => $targetName] =
            self::getNonEmptyObjectFromDb(<<<EOF
                SELECT tile_id, `character`, `player_name` 
                FROM tile NATURAL LEFT JOIN player
                WHERE tile_id = $tileId
                EOF);
        $targetName ??= 'Knight-Errant';

        self::notifyAllPlayers('message', clienttranslate('${tokenIcon1}${player_name1} inspects ${tokenIcon2}${player_name2}\'s tile'), [
            'player_name1' => $playerName,
            'player_name2' => $targetName,
            'tokenIcon1' => $playerName,
            'tokenIcon2' => "@tile:$tileId",
            'preserve' => ['tokenIcon1', 'tokenIcon2']
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
            throw new BgaUserException('Invalid Number');
        }

        self::DbQuery(<<<EOF
            INSERT INTO question(player_id, recipient_id, tile_id, question)
            SELECT $activePlayerId, $playerId, $tileId, $valuesMask
            FROM player_status AS self
                INNER JOIN tile ON tile.tile_id = $tileId
                LEFT JOIN inspection as self_seen 
                    ON self_seen.player_id = $activePlayerId 
                       AND self_seen.tile_id = $tileId
                LEFT JOIN inspection as other_seen
                    ON other_seen.player_id = $playerId 
                       AND other_seen.tile_id = $tileId
            WHERE self.player_id = $activePlayerId
                AND (tile.player_id <> $activePlayerId 
                     OR tile.player_id IS NULL)
                AND self_seen.tile_id IS NULL 
                AND (tile.player_id = $playerId 
                    OR other_seen.tile_id IS NOT NULL)
            EOF);

        if (self::DbAffectedRow() <> 1) {
            throw new BgaUserException('Invalid player/tile');
        }

        self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
        self::setGameStateValue(Globals::ASKER, $activePlayerId);

        $numbersCount = 0;
        for ($i = 0; $i < 7; ++$i) {
            if ($valuesMask & (1 << $i)) {
                ++$numbersCount;
            }
        }

        $tileOwner = self::getUniqueValueFromDb(
            "SELECT player_id FROM tile WHERE tile_id = $tileId");

        $playerName = self::getActivePlayerName();
        $targetName = self::getPlayerNameById($playerId);
        $args = [
            'player_name1' => $playerName,
            'player_name2' => $targetName,
            'tokenIcon1' => $playerName,
            'tokenIcon2' => $targetName,
            'preserve' => ['tokenIcon1', 'tokenIcon2']
        ];

        if ($numbersCount > 1) {
            $args['numberIcons'] = $valuesMask;
            $args['preserve'][] = 'numberIcons';
        } else {
            $args['numberIcon'] = $valuesMask;
            $args['preserve'][] = 'numberIcon';
        }

        if ($tileOwner!== null && (int)$tileOwner === $playerId) {
            $message = $numbersCount === 1 ?
                clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is your tile ${numberIcon}?"') :
                clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is your tile one of ${numberIcons}?"');
        } else {
            $message = $numbersCount === 1 ?
                clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}${player_name3}\'s tile ${numberIcon}?"') :
                clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}${player_name3}\'s tile one of ${numberIcons}?"');

            if ($tileOwner === null) {
                $tileToken = "@tile:$tileId";
                $ownerName = 'Knight-Errant';
            } else {
                $tileToken = $tileOwner;
                $ownerName =  self::getPlayerNameById($tileOwner);
            }

            $args['player_name3'] = $ownerName;
            $args['tokenIcon3'] = $tileToken;
            $args['preserve'][] = 'tokenIcon3';
        }

        self::notifyAllPlayers('question', $message, $args);

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
            UPDATE question
            SET answer = $answer
            WHERE answer IS NULL
            EOF);

        $message = $answer ?
            '${tokenIcon}${player_name} answers "Yes"' :
            '${tokenIcon}${player_name} answers "No"';

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers('answer', $message, [
            'player_name' => $playerName,
            'answer' => $answer,
            'tokenIcon' => $playerName,
            'preserve' => ['tokenIcon']
        ]);
        $this->gamestate->nextState('');
    }

    function vote(int $playerId): void
    {
        self::checkAction('vote');
        $currentPlayerId = (int)self::getCurrentPlayerId();

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
                self::DbQuery(<<<EOF
                    UPDATE player_status 
                    SET voted = NULL
                    WHERE player_id = $playerId
                    EOF);

                $this->gamestate->setPlayersMultiactive([$playerId], '');
            }
        }
    }

    function deploy(int $tileId, int $position)
    {
        if ($position >= 0) {
            new BgaUserException('Invalid position');
        }
        self::DbQuery(<<<EOF
            UPDATE tile
            SET deployment = $position
            WHERE tile_id = $tileId
            EOF);

        $count = self::getUniqueValueFromDb(
            'SELECT COUNT(deployment) FROM tile');

        $continue = $count < self::getPlayersNumber();
        $this->gamestate->nextState(
            $continue ? 'deploy' : 'check');
    }

    static function isWitchTeam(int $playerId): int {
        return (int)self::getUniqueValueFromDb(<<<EOF
            SELECT COUNT(*)
            FROM player_status AS self 
                NATURAL JOIN inspection
                INNER JOIN tile 
                    ON tile.tile_id = inspection.tile_id 
                        OR tile.player_id = self.player_id
            WHERE self.player_id = $playerId 
              AND tile.`character` = 0
            EOF);
    }

    function determineAnswer(string $playerId, int $tileId, int $question): int
    {
        if (self::isWitchTeam($playerId)) {
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

        $votes = self::getObjectListFromDb(<<<EOF
            SELECT 
                self.player_id, 
                SUM(1 << voter.token) AS voters 
            FROM (SELECT * FROM player_status NATURAL JOIN player) AS self
                INNER JOIN (SELECT * FROM player_status NATURAL JOIN player) AS voter
                    ON voter.voted = self.player_id
            GROUP BY self.player_id
            ORDER BY COUNT(*) DESC,
                COUNT(CASE WHEN voter.player_id = self.player_id THEN 1 END) ASC,
                (self.player_no + $playerCount 
                     - (SELECT player_no FROM player 
                        WHERE player_id = $lastCaptain)) 
                    % $playerCount DESC
            EOF);

        foreach (array_reverse($votes) as ['player_id' => $player_id, 'voters' => $voters]) {
            self::notifyAllPlayers('vote', clienttranslate('${player_name} is recommended by ${tokenIcons}'), [
                'player_name' => self::getPlayerNameById($player_id),
                'tokenIcons' => $voters,
                'preserve' => ['tokenIcons']
            ]);
        }

        $captain = $votes[0]['player_id'];
        self::setGameStateValue(Globals::CAPTAIN, $captain);
        $playerName = self::getPlayerNameById($captain);

        self::notifyAllPlayers('message', clienttranslate('${tokenIcon}${player_name} is appointed as the captain'), [
            'player_name' => $playerName,
            'tokenIcon' => $playerName,
            'preserve' => ['tokenIcon']
        ]);

        $this->gamestate->changeActivePlayer($captain);

        if (self::isWitchTeam($captain)) {
            self::notifyAllPlayers('message', clienttranslate('${tokenIcon}${player_name} belongs to the Witch Team'), [
                'player_name' => $playerName,
                'tokenIcon' => $playerName,
                'preserve' => ['tokenIcon']
            ]);
            $this->gamestate->nextState('end');
        } else {
            $this->gamestate->nextState('appoint');
        }
    }

    function stDispatch(): void
    {
        $asker = (int)self::getGameStateValue(Globals::ASKER);
        if ($asker !== 0) {
            $questionData = self::getObjectFromDb(<<<EOF
                SELECT recipient_id, tile_id, question 
                FROM question
                WHERE answer IS NULL; 
                EOF);
            if ($questionData !== null) {
                ['recipient_id' => $asked, 'tile_id' => $askedTile, 'question' => $question] = $questionData;

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
        if ($actionsTaken > ($inspectsPerPlayer + 1) * $playersCount) {
            $this->gamestate->nextState('deploy');
        } else if ($actionsTaken >= ($inspectsPerPlayer + 1) * $playersCount) {
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
        $count = $deployment & 0b1111;

        $order = self::getObjectListFromDb(<<<EOF
            SELECT `character` FROM tile
            WHERE `character` > 0
            ORDER BY `character` ASC
            EOF, true);

        foreach ($order as $index => $character) {
            if ($index >= $count
                || ($deployment >> (($index + 1) * 4)) !== (int)$character)
            {

            }
        }
    }

    function stNextRound(): void
    {
        $round = self::getGameStateValue(Globals::ROUND);

        if ($round < MAX_ROUNDS) {
            self::incGameStateValue(Globals::ROUND, 1);

            if ($round > 0 ) {
                $nextPlayer = self::getUniqueValueFromDb(<<<EOF
                    SELECT player_id 
                    FROM player_status NATURAL JOIN tile
                    WHERE `character` > 0
                    ORDER BY `character` ASC
                    LIMIT 1
                    EOF);
                $this->gamestate->changeActivePlayer($nextPlayer);

                self::DbQuery('DELETE FROM tile');
                self::DbQuery('UPDATE player_status SET voted = NULL');

                self::notifyAllPlayers('round', clienttranslate("New round begins"), []);

                self::setupTiles();

                self::setGameStateValue(Globals::ACTIONS_TAKEN, 0);
                self::setGameStateValue(Globals::ASKER, 0);
                self::setGameStateValue(Globals::CAPTAIN, (int)$nextPlayer);
            }

            $this->gamestate->nextState('continue');
        } else {
            $this->gamestate->nextState('end');
        }
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

    function argDeployKnights(): array
    {
        return [];
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

    function __skipToVote()
    {
        $this->gamestate->jumpToState(State::VOTE);
    }

    function __skipRound()
    {
        $this->gamestate->jumpToState(State::NEXT_ROUND);
    }
}
