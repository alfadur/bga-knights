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
            Globals::FIRST_PLAYER => Globals::FIRST_PLAYER_ID,

            GameOption::MODE => GameOption::MODE_ID,
            GameOption::COOP => GameOption::COOP_ID
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

        $firstPlayer = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT player_id AS id FROM player
            ORDER BY player_no ASC
            LIMIT 1
            EOF);

        self::setGameStateInitialValue(Globals::FIRST_PLAYER, $firstPlayer);
        $this->gamestate->changeActivePlayer($firstPlayer);
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
        $coop = (int)self::getGameStateValue(GameOption::COOP);

        $tilesPerPlayer = 1;
        if ($mode === GameMode::DARKNESS) {
            $additionalCharacters = $coop ? 7 - count($players) : 3;
        } else {
            $additionalCharacters = count($players) < 6 ? 1 : 0;
        }

        switch ($mode) {
            case GameMode::STANDARD:
                $characters = range($coop,count($players) + $additionalCharacters + $coop - 1);
                shuffle($characters);
                break;
            case GameMode::ADVANCED:
                $characters = range($coop, 7);
                shuffle($characters);
                $characters = array_slice($characters, 0, count($players) + $additionalCharacters);
                break;
            case GameMode::DARKNESS:
                $batch = range(1, 7);
                shuffle($batch);
                $characters = array_slice($batch, 0, count($players));
                $batch = array_slice($batch, count($players));
                if (!$coop) {
                    $batch[] = 0;
                    shuffle($batch);
                }

                $characters = array_merge($characters, array_slice($batch, 0, $additionalCharacters));
                break;
            default:
                return;
        }

        $tileIndex = 1;
        $values = [];

        foreach ($players as $playerId => $_) {
            for ($tile = 0; $tile < $tilesPerPlayer; ++$tile) {
                $character = array_shift($characters);
                $values[] = "($tileIndex, $playerId, $character)";

                self::notifyPlayer($playerId, 'reveal', clienttranslate('You are ${numberIcon}'), [
                    'tileId' => $tileIndex,
                    'character' => $character,
                    'numberIcon' => $character === 0 ? $character : (1 << ($character - 1)),
                    'preserve' => ['numberIcon']
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

    function getTiles(int $currentPlayerId): array {
        $captain = self::getGameStateValue(Globals::CAPTAIN);
        return self::getObjectListFromDb(<<<EOF
            SELECT tile.player_id, tile.tile_id AS id, 
               (CASE WHEN tile.player_id = $currentPlayerId
                    OR inspection.tile_id IS NOT NULL
                    OR tile.player_id = $captain
                THEN tile.`character` END) AS `character`,
                tile.deployment
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

        $inspections = self::getObjectListFromDb('SELECT * FROM inspection');
        $questions = self::getObjectListFromDb('SELECT * FROM question');

        return [
            'players' => $players,
            'tiles' => $tiles,
            'inspections' => $inspections,
            'questions' => $questions,
            'mode' => self::getGameStateValue(GameOption::MODE),
            'coop' => self::getGameStateValue(GameOption::COOP)
        ];
    }

    function inspect(int $tileId): void
    {
        self::checkAction('inspect');
        $activePlayerId = (int)self::getActivePlayerId();

        $mode = (int)self::getGameStateValue(GameOption::MODE);

        if ($mode === GameMode::DARKNESS) {
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
        $character = (int)$character;
        $targetName ??= 'Knight-Errant';

        self::notifyAllPlayers('inspect', clienttranslate('${tokenIcon1}${player_name1} inspects ${tokenIcon2}${player_name2}\'s tile'), [
            'player_name1' => $playerName,
            'player_name2' => $targetName,
            'tokenIcon1' => "player,$playerName",
            'tokenIcon2' => "tile,$tileId",
            'playerId' => self::getActivePlayerId(),
            'tileId' => $tileId,
            'preserve' => ['tokenIcon1', 'tokenIcon2']
        ]);

        $message = $character === 0 ?
            clienttranslate('${tokenIcon}${player_name} turns out to be ${numberIcon}. You are now bewitched!') :
            clienttranslate('${tokenIcon}${player_name} turns out to be ${numberIcon}');
        self::notifyPlayer($activePlayerId, 'reveal', $message, [
            'player_name' => $targetName,
            'tokenIcon' => "tile,$tileId",
            'tileId' => $tileId,
            'character' => $character,
            'numberIcon' => $character === 0 ? $character : (1 << ($character - 1)),
            'preserve' => ['numberIcon', 'tokenIcon']
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
            'tokenIcon1' => "player,$playerName",
            'tokenIcon2' => "player,$targetName",
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
                $tileToken = "tile,$tileId";
                $ownerName = 'Knight-Errant';
            } else {
                $ownerName =  self::getPlayerNameById($tileOwner);
                $tileToken = "player,$ownerName";
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

        $expectedAnswer = (int)self::getGameStateValue(Globals::ANSWER);
        if ($expectedAnswer > 0 && $expectedAnswer - 1 !== $answer) {
            throw new BgaUserException('Invalid answer');
        }

        self::DbQuery(<<<EOF
            UPDATE question
            SET answer = $answer
            WHERE answer IS NULL
            EOF);

        $message = $answer ?
            clienttranslate('${tokenIcon}${player_name} answers "Yes"') :
            clienttranslate('${tokenIcon}${player_name} answers "No"');

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers('answer', $message, [
            'player_name' => $playerName,
            'answer' => $answer,
            'tokenIcon' => "player,$playerName",
            'preserve' => ['tokenIcon']
        ]);
        $this->gamestate->nextState('');
    }

    function vote(int $playerId): void
    {
        self::checkAction('vote');
        $currentPlayerId = (int)self::getCurrentPlayerId();

        self::DbQuery(<<<EOF
            UPDATE player_status AS self 
                INNER JOIN tile USING (player_id)
                INNER JOIN player_status AS target 
                    ON (target.player_id = $playerId)
                INNER JOIN tile AS target_tile
                    ON (target_tile.player_id = target.player_id)
                LEFT JOIN inspection 
                    ON (inspection.player_id = self.player_id
                        AND inspection.tile_id = target_tile.tile_id)
                LEFT JOIN inspection AS target_inspection 
                    ON (target_inspection.player_id = target.player_id
                        AND target_inspection.tile_id = tile.tile_id)
            SET self.voted = $playerId
            WHERE self.player_id = $currentPlayerId
                AND (tile.character <> 0 OR target_inspection.tile_id IS NULL)
                AND (target_tile.character <> 0 OR inspection.tile_id IS NULL)
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException('Invalid vote');
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
        self::checkAction('deploy');
        if ($position >= self::getPlayersNumber() - 1) {
            new BgaUserException('Invalid position');
        }
        self::DbQuery(<<<EOF
            UPDATE tile INNER JOIN tile AS previous ON (previous.tile_id = $tileId)
            SET tile.deployment = IF(tile.tile_id = $tileId, $position, previous.deployment)
            WHERE tile.tile_id = $tileId OR tile.deployment = $position
            EOF);

        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException("Occupied position");
        }

        $owner = self::getUniqueValueFromDb(
            "SELECT player_id FROM tile WHERE tile_id = $tileId");
        $ownerName = $owner ? self::getPlayerNameById($owner) : 'Knight-Errant';

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers("move", clienttranslate('${tokenIcon1}${player_name1} places ${tokenIcon2}${player_name2}\'s tile on position ${positionIcon}'), [
            'player_name1' => self::getActivePlayerName(),
            'tokenIcon1' => "player,$playerName",
            'player_name2' => $ownerName,
            'tokenIcon2' => "tile,$tileId",
            'positionIcon' => $position,
            'tileId' => $tileId,
            'position' => $position,
            'preserve' => ['tileIcon1', 'tileIcon2', 'positionIconS']
        ]);

        $this->gamestate->nextState('deploy');
    }

    function check()
    {
        self::checkAction('check');
        $count = self::getUniqueValueFromDb(
            'SELECT COUNT(deployment) FROM tile');
        if ($count < self::getPlayersNumber() - 1) {
            throw new BgaUserException('Not enough tiles');
        }

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers('message', clienttranslate('${tokenIcon}${player_name} reveals the Knights'), [
            'player_name' => $playerName,
            'tokenIcon' => "player,$playerName"
        ]);

        $this->gamestate->nextState('check');
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

    function applyScore($knightsWin): void
    {
        $round = self::getGameStateValue(Globals::ROUND);
        $score = $round >= MAX_ROUNDS ? 7 : ($knightsWin ? 2 : 3);
        $check = $knightsWin ? 'NOT' : '';

        $winners = self::getObjectListFromDb(<<<EOF
            SELECT player_status.player_id, player_status.token 
                 FROM player_status 
                    INNER JOIN tile ON (tile.`character` = 0)
                    LEFT JOIN inspection ON (inspection.tile_id = tile.tile_id)                    
                 WHERE $check (tile.player_id = player_status.player_id 
                    OR inspection.player_id IS NOT NULL 
                        AND inspection.player_id = player_status.player_id) 
            EOF);

        self::dump('winners', $winners);

        if (count($winners) > 0) {
            $tokens = 0;
            $conditions = [];
            $players = [];
            foreach ($winners as ['player_id' => $playerId, 'token' => $token]) {
                $tokens |= 1 << (int)$token;
                $conditions[] = "player_id = $playerId";
                $players[] = $playerId;
            }
            $args = implode(' OR ', $conditions);

            self::DbQuery(<<<EOF
                UPDATE player
                SET player_score = player_score + $score
                WHERE $args
                EOF);

            $message =  $knightsWin ?
                clienttranslate('The Knights team wins the round! ${tokenIcons} receive(s) ${score} points') :
                clienttranslate('The Witch team wins the round! ${tokenIcons} receive(s) ${score} points');

            self::notifyAllPlayers('score', $message, [
                'tokenIcons' => "bitset,$tokens",
                'score' => $score,
                'players' => $players,
                'preserve' => ['tokenIcons']
            ]);
        } else {
            self::notifyAllPlayers('message', 'The Knights team loses, so no points are awarded', []);
        }
    }

    function determineAnswer(string $playerId, int $tileId, int $question): int
    {
        if (self::isWitchTeam($playerId)) {
            return 0;
        }
        return (int)self::getUniqueValueFromDb(<<<EOF
            SELECT ((1 << `character`) & ($question << 1)) <> 0
            FROM tile WHERE tile_id = $tileId
            EOF) + 1;
    }

    function stAppoint(): void
    {
        $firstPlayer = (int)self::getGameStateValue(Globals::FIRST_PLAYER);
        $playerCount = self::getPlayersNumber();

        $votes = self::getObjectListFromDb(<<<EOF
            SELECT 
                self.player_id, 
                MAX(self.`character`) AS `character`,
                MAX(self.tile_id) AS tile_id,
                SUM(1 << voter.token) AS voters
            FROM (SELECT * FROM player_status NATURAL JOIN player NATURAL JOIN tile) AS self
                INNER JOIN player_status AS voter ON voter.voted = self.player_id
            GROUP BY self.player_id
            ORDER BY COUNT(*) DESC,
                COUNT(CASE WHEN voter.player_id = self.player_id THEN 1 END) ASC,
                (self.player_no + $playerCount 
                     - (SELECT player_no FROM player 
                        WHERE player_id = $firstPlayer)) 
                    % $playerCount DESC
            EOF);

        foreach (array_reverse($votes) as ['player_id' => $player_id, 'voters' => $voters]) {
            $playerName = self::getPlayerNameById($player_id);
            self::notifyAllPlayers('vote', clienttranslate('${tokenIcon}${player_name} is recommended by ${tokenIcons}'), [
                'player_name' => $playerName,
                'tokenIcon' => "player,$playerName",
                'tokenIcons' => "bitset,$voters",
                'preserve' => ['tokenIcon', 'tokenIcons']
            ]);
        }

        $captain = $votes[0]['player_id'];
        $tileId = $votes[0]['tile_id'];
        $character = (int)$votes[0]['character'];
        self::setGameStateValue(Globals::CAPTAIN, $captain);
        $playerName = self::getPlayerNameById($captain);

        self::notifyAllPlayers('reveal', clienttranslate('${tokenIcon}${player_name} is appointed as the captain and is revealed to be ${numberIcon}'), [
            'player_name' => $playerName,
            'tokenIcon' => "player,$playerName",
            'numberIcon' => $character === 0 ? $character : (1 << ($character - 1)),
            'tileId' => $tileId,
            'character' => $character,
            'preserve' => ['tokenIcon', 'numberIcon']
        ]);

        $this->gamestate->changeActivePlayer($captain);

        if ($character === 0 || self::isWitchTeam($captain)) {
            if ($character !== 0) {
                ['player_name' => $witchName, 'tile_id' => $tileId] =
                    self::getObjectFromDb(<<<'EOF'
                        SELECT player_name, tile_id
                        FROM player NATURAL JOIN tile 
                        WHERE tile.`character` = 0
                        EOF);
                self::notifyAllPlayers('reveal', clienttranslate('${tokenIcon1}${player_name1} is bewitched by ${tokenIcon2}${player_name2}!'), [
                    'player_name1' => $playerName,
                    'player_name2' => $witchName,
                    'tokenIcon1' => "player,$playerName",
                    'tokenIcon2' => "player,$witchName",
                    'tileId' => $tileId,
                    'character' => $tileId,
                    'preserve' => ['tokenIcon1', 'tokenIcon2']
                ]);
            }

            $this::applyScore(false);
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
        $inspectsPerPlayer = $mode === GameMode::DARKNESS ? 2 : 1;

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
        $order = self::getObjectListFromDb(<<<EOF
            SELECT player_id, tile_id, `character`, deployment IS NOT NULL AS deployed  
            FROM tile
            ORDER BY -deployment DESC
            EOF);

        $knightsWin = true;
        self::dump("deployment order", $order);

        $list = [];
        $previous = 0;
        $missingTile = null;

        foreach ($order as $tile) {
            $character = (int)$tile['character'];
            if ((int)$tile['deployed']) {
                if ($character <= $previous) {
                    $knightsWin = false;
                } else {
                    $previous = $character;
                }
                self::notifyAllPlayers("reveal", '', [
                    'tileId' => $tile['tile_id'],
                    'character' => $character
                ]);
                $list[] = $character;
            } else if ($character !== 0) {
                $missingTile = $tile;
                break;
            }
        }

        self::notifyAllPlayers('message', clienttranslate('Revealed order is ${listIcon}'), [
            'listIcon' => implode(',', $list)
        ]);

        if ($missingTile !== null) {
            self::notifyAllPlayers("reveal", clienttranslate('${tokenIcon}${player_name}\'s tile ${numberIcon} has not been included'), [
                'player_name' => self::getPlayerNameById($missingTile['player_id']),
                'tokenIcon' => "tile,$missingTile[tile_id]",
                'numberIcon' => 1 << ((int)$missingTile['character'] - 1),
                'tileId' => $missingTile['tile_id'],
                'character' => $missingTile['character'],
                'preserve' => ['tokenIcon', 'numberIcon'],
            ]);
        }

        $this::applyScore($knightsWin);

        $this->gamestate->nextState('');
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

                self::notifyAllPlayers('round', clienttranslate('New round begins'), []);

                self::setupTiles();

                self::setGameStateValue(Globals::ACTIONS_TAKEN, 0);
                self::setGameStateValue(Globals::ASKER, 0);
                self::setGameStateValue(Globals::FIRST_PLAYER, (int)$nextPlayer);
                self::setGameStateValue(Globals::CAPTAIN, 0);
            }

            $this->gamestate->nextState('continue');
        } else {
            $this->gamestate->nextState('end');
        }
    }

    function argAnswer(): array
    {
        $question = self::getObjectFromDb(
            'SELECT * FROM question ORDER BY question_id DESC LIMIT 1');
        return [
            'question' => $question,
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

    function __skipToVote()
    {
        self::setGameStateInitialValue(Globals::ACTIONS_TAKEN, self::getPlayersNumber() * 2);
        $this->gamestate->jumpToState(State::VOTE);
    }

    function __skipRound()
    {
        $this->gamestate->jumpToState(State::NEXT_ROUND);
    }
}
