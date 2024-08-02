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
            Globals::TEAM_WINS => Globals::TEAM_WINS_ID,

            GameOption::MODE => GameOption::MODE_ID,
            GameOption::COOP => GameOption::COOP_ID,
            GameOption::FIXED_SCORE => GameOption::FIXED_SCORE_ID,
            GameOption::REVIEW => GameOption::REVIEW_ID
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

        foreach (Stats::TABLE_STATS_LIST as $stat) {
            self::initStat('table', $stat, 0);
        }

        foreach (Stats::PLAYER_STATS_LIST as $stat) {
            self::initStat('player', $stat, 0);
        }

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

        $additionalCharacters = $this->extraCharactersCount(count($players));

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
        $round = (int)self::getGameStateValue(Globals::ROUND);
        $mode = (int)self::getGameStateValue(GameOption::MODE);
        $inspectsPerPlayer = $mode === GameMode::DARKNESS ? 2 : 1;
        $playersCount = (int)$this->getPlayersNumber();
        $actionsTaken = (int)self::getGameStateValue(Globals::ACTIONS_TAKEN);
        return 33.3 * ($round - 1) + 33.3 * ($actionsTaken) / (1 + ($inspectsPerPlayer + 1) * $playersCount);
    }

    function getTiles(int $currentPlayerId, bool $show): array {
        if ($show) {
            return self::getObjectListFromDb(<<<EOF
                SELECT player_id, tile_id AS id, `character`, deployment
                FROM tile 
                EOF);
        } else {
            return self::getObjectListFromDb(<<<EOF
                SELECT tile.player_id, tile.tile_id AS id, 
                   (CASE WHEN tile.player_id = $currentPlayerId
                        OR inspection.tile_id IS NOT NULL
                    THEN tile.`character` END) AS `character`,
                    tile.deployment
                FROM tile LEFT JOIN inspection
                    ON inspection.player_id = $currentPlayerId
                        AND inspection.tile_id = tile.tile_id
                EOF);
        }
    }

    protected function getAllDatas()
    {
        $currentPlayerId = self::getCurrentPlayerId();
        $captain = self::getGameStateValue(Globals::CAPTAIN);
        $fixed_score = (int)self::getGameStateValue(GameOption::FIXED_SCORE);
        $review = (int)self::getGameStateValue(GameOption::REVIEW);
        $hasCaptain = (int)$captain !== 0 ? 1 : 0;

        $state = (int)$this->gamestate->state_id();
        $showNotes = $review !== 0 && ($state === State::REVIEW || $state === State::GAME_END);

        $notesSelector = $showNotes ?
            'notes' :
            "IF(player_id = $currentPlayerId, notes, NULL) AS notes";

        $players = self::getCollectionFromDb(<<<EOF
            SELECT player_id AS id, player_score AS score, 
                   player_name AS name, player_color AS color, 
                   player_no AS no, token, 
                   IF($hasCaptain, voted, NULL) AS voted,
                   $notesSelector
            FROM player NATURAL JOIN player_status
            EOF);

        $tiles = self::getTiles($currentPlayerId, $showNotes || $state === State::GAME_END);

        $inspections = self::getObjectListFromDb('SELECT * FROM inspection');
        $questions = self::getObjectListFromDb('SELECT * FROM question');

        return [
            'players' => $players,
            'tiles' => $tiles,
            'inspections' => $inspections,
            'questions' => $questions,
            'mode' => self::getGameStateValue(GameOption::MODE),
            'coop' => self::getGameStateValue(GameOption::COOP),
            'round' => self::getGameStateValue(Globals::ROUND),
            'wins' => self::getGameStateValue(Globals::TEAM_WINS),
            'firstPlayer' => self::getGameStateValue(Globals::FIRST_PLAYER),
            'captain' => $captain,
            'fixedScore' => $fixed_score !== 0,
            'hasReview' => $review !== 0
        ];
    }

    function recordAnswer(int $playerId, int $answer): void
    {
        self::DbQuery(<<<EOF
            UPDATE question
            SET answer = $answer
            WHERE answer IS NULL
            EOF);

        $message = $answer ?
            clienttranslate('${tokenIcon}${player_name} answers "Yes"') :
            clienttranslate('${tokenIcon}${player_name} answers "No"');

        $playerName = self::getPlayerNameById($playerId);
        self::notifyAllPlayers('answer', $message, [
            'player_name' => $playerName,
            'playerId' => $playerId,
            'answer' => $answer,
            'tokenIcon' => "player,$playerName",
            'preserve' => ['tokenIcon']
        ]);
    }

    function extraCharactersCount(int $playerCount): int
    {
        $mode = (int)self::getGameStateValue(GameOption::MODE);
        $coop = (int)self::getGameStateValue(GameOption::COOP);

        if ($mode === GameMode::DARKNESS) {
            return $coop ? 7 - $playerCount : 3;
        } else {
            return $playerCount < 6 ? 1 : 0;
        }
    }

    function isDeploymentReady(): bool
    {
        $optionalTiles = 1 - (int)self::getGameStateValue(GameOption::COOP);

        return (int)self::getUniqueValueFromDb(<<<EOF
            SELECT BIT_COUNT(SUM(1 << deployment)) >= (SELECT COUNT(*) FROM tile) - $optionalTiles 
            FROM tile
            WHERE deployment IS NOT NULL  
            EOF) > 0;
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

        $message = $targetName === null ?
            clienttranslate('${tokenIcon1}${player_name1} inspects ${tokenIcon2}Knight-errant\'s tile') :
            clienttranslate('${tokenIcon1}${player_name1} inspects ${tokenIcon2}${player_name2}\'s tile');

        self::notifyAllPlayers('inspect', $message, [
            'player_name1' => $playerName,
            'player_name2' => $targetName,
            'tokenIcon1' => "player,$playerName",
            'tokenIcon2' => "tile,$tileId",
            'playerId' => self::getActivePlayerId(),
            'tileId' => $tileId,
            'preserve' => ['tokenIcon1', 'tokenIcon2']
        ]);

        if ($character === 0) {
            self::incStat(1, Stats::BEWITCHED, $activePlayerId);
        }

        $message = $character === 0 ?
            clienttranslate('${tokenIcon}${player_name} turns out to be ${numberIcon}. You are now bewitched!') :
            clienttranslate('${tokenIcon}${player_name} turns out to be ${numberIcon}');
        self::notifyPlayer($activePlayerId, 'reveal', $message, [
            'player_name' => $targetName ?? 'Knight-errant',
            'tokenIcon' => "tile,$tileId",
            'tileId' => $tileId,
            'character' => $character,
            'bewitch' => $character === 0,
            'numberIcon' => $character === 0 ? $character : (1 << ($character - 1)),
            'preserve' => ['numberIcon', 'tokenIcon']
        ]);

        self::giveExtraTime($activePlayerId);

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
            if ($tileOwner === null) {
                $tileToken = "tile,$tileId";
                $message = $numbersCount === 1 ?
                    clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}Knight-errant\'s tile ${numberIcon}?"') :
                    clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}Knight-errant\'s tile one of ${numberIcons}?"');
            } else {
                $ownerName = self::getPlayerNameById($tileOwner);
                $args['player_name3'] = $ownerName;
                $message = $numbersCount === 1 ?
                    clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}${player_name3}\'s tile ${numberIcon}?"') :
                    clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is ${tokenIcon3}${player_name3}\'s tile one of ${numberIcons}?"');
                $tileToken = "player,$ownerName";
            }

            $args['tokenIcon3'] = $tileToken;
            $args['preserve'][] = 'tokenIcon3';
        }

        self::giveExtraTime($activePlayerId);

        $coop = (int)self::getGameStateValue(GameOption::COOP);
        $args['question'] = [
            'player_id' => $activePlayerId,
            'recipient_id' => $playerId,
            'tile_id' => $tileId,
            'question' => $valuesMask
        ];
        self::notifyAllPlayers('question', $message, $args);

        $answer = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT ((1 << `character`) & ($valuesMask << 1)) <> 0
            FROM tile WHERE tile_id = $tileId
            EOF);
        if (self::isWitchTeam($playerId)) {
            $answer |= 0b10;
        }

        if ($coop) {
            $this->recordAnswer($playerId, $answer & 0b1);
        } else {
            self::setGameStateValue(Globals::ANSWER, $answer);
        }

        $this->gamestate->nextState('');
    }

    function askMany(int $playerId, array $tileIds, string $expression) {
        if (strlen($expression) > 16) {
            throw new BgaUserException("Expression too long");
        }

        $activePlayerId = self::getActivePlayerId();

        $tileSet = implode(",", $tileIds);
        $characters = self::getObjectListFromDb(<<<EOF
            SELECT `character` 
            FROM tile LEFT JOIN inspection 
                    ON (inspection.tile_id = tile.tile_id AND inspection.player_id = $playerId)
                LEFT JOIN inspection AS self_inspection 
                    ON (self_inspection.tile_id = tile.tile_id AND self_inspection.player_id = $activePlayerId)
            WHERE tile.tile_id IN ($tileSet) 
                AND (tile.player_id = $playerId OR inspection.player_id IS NOT NULL)
                AND self_inspection.player_id IS NULL
            ORDER BY tile.tile_id ASC
            EOF, true);

        if (count($characters) !== count($tileIds)) {
            throw new BgaUserException("Invalid tile list");
        }

        $stack = [];

        for ($i = 0; $i < strlen($expression); ++$i) {
            $c = ord($expression[$i]);

            if (ord('a') <= $c && $c <= ord('c')) {
                $character = (int)$characters[$c - ord('a')];
                if ($character === 0) {
                    $stack = [false];
                    break;
                }
                $stack[] = $character;
            } else if (ord('0') <= $c && $c <= ord('9')) {
                $stack[] = $c - ord('0');
            } else if ($c === ord('p')) {
                $stack[] = array_pop($stack) + array_pop($stack);
            } else if ($c === ord('e')) {
                $stack[] = array_pop($stack) === array_pop($stack);
            } else if ($c === ord('l')) {
                $stack[] = array_pop($stack) > array_pop($stack);
            } else if ($c === ord('o')) {
                $stack[] = array_pop($stack) | array_pop($stack);
            } else if ($c === ord('n')) {
                $stack[] = array_pop($stack) & array_pop($stack);
            } else {
                throw new BgaUserException("Invalid symbol: $expression[$i]");
            }
        }

        if (count($stack) !== 1 && !is_bool($stack[0])) {
            throw new BgaUserException("Malformed expression");
        }

        self::DbQuery(<<<EOF
            INSERT INTO question(player_id, recipient_id, tile_id, question, expression, expression_tiles)
                VALUES($activePlayerId, $playerId, 1, 0, '$expression', '$tileSet')
            EOF);

        self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
        self::setGameStateValue(Globals::ASKER, $activePlayerId);

        $playerName = self::getActivePlayerName();
        $targetName = self::getPlayerNameById($playerId);
        $args = [
            'player_name1' => $playerName,
            'player_name2' => $targetName,
            'tokenIcon1' => "player,$playerName",
            'tokenIcon2' => "player,$targetName",
            'expressionIcon' => "$expression,$tileSet",
            'preserve' => ['tokenIcon1', 'tokenIcon2', 'expressionIcon']
        ];

        $coop = (int)self::getGameStateValue(GameOption::COOP);
        $args['question'] = [
            'player_id' => $activePlayerId,
            'recipient_id' => $playerId,
            'expression' => $expression,
            'expression_tiles' => $tileIds
        ];
        
        self::notifyAllPlayers('question', clienttranslate('${tokenIcon1}${player_name1} asks ${tokenIcon2}${player_name2}, "is this true?" ${expressionIcon}'), $args);

        $answer = $stack[0] ? 1 : 0;

        if (self::isWitchTeam($playerId)) {
            $answer |= 0b10;
        }

        if ($coop) {
            $this->recordAnswer($playerId, $answer & 0b1);
        } else {
            self::setGameStateValue(Globals::ANSWER, $answer);
        }

        $this->gamestate->nextState('');
    }

    function answer(bool $answer): void
    {
        self::checkAction('answer');
        $activePlayerId = self::getActivePlayerId();

        $answer = $answer ? 1 : 0;
        $expectedAnswer = (int)self::getGameStateValue(Globals::ANSWER);
        $isTruth = ($expectedAnswer & 0b1) === $answer;

        if (($expectedAnswer & 0b10) === 0 && !$isTruth) {
            throw new BgaUserException('Invalid answer');
        }

        if (($expectedAnswer & 0b10) !== 0) {
            $answerStat = $isTruth ? Stats::TRUTHS_TOLD : Stats::LIES_TOLD;
            self::incStat(1, $answerStat, $activePlayerId);
        }

        self::giveExtraTime($activePlayerId);
        $this->recordAnswer($activePlayerId, $answer);
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

        self::giveExtraTime($currentPlayerId);

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

        $mode = (int)self::getGameStateValue(GameOption::MODE);
        $coop = (int)self::getGameStateValue(GameOption::COOP);

        if ($position === 0) {
            self::DbQuery(<<<EOF
                UPDATE tile
                SET tile.deployment = NULL
                WHERE tile.tile_id = $tileId AND tile.deployment IS NOT NULL 
                EOF);
        } else {
            $playerCount = self::getPlayersNumber();
            $remainingTiles = $mode === GameMode::STANDARD && $coop === 0 ? 1 : 0;
            $positionsCount = $playerCount + $this->extraCharactersCount($playerCount) - $remainingTiles;

            self::DbQuery(<<<EOF
                UPDATE tile INNER JOIN tile AS previous ON (previous.tile_id = $tileId)
                SET tile.deployment = IF(tile.tile_id = $tileId, $position, previous.deployment)
                WHERE tile.tile_id = $tileId OR tile.deployment = $position
                    AND $position <= $positionsCount 
                EOF);
        }

        if (self::DbAffectedRow() === 0) {
            throw new BgaUserException("Invalid position");
        }

        $owner = self::getUniqueValueFromDb(
            "SELECT player_id FROM tile WHERE tile_id = $tileId");

        if ($owner === null) {
            $message = $position === 0 ?
                clienttranslate('${tokenIcon1}${player_name1} returns ${tokenIcon2}Knight-errant\'s tile back') :
                clienttranslate('${tokenIcon1}${player_name1} places ${tokenIcon2}Knight-errant\'s tile on position ${positionIcon}');
            $ownerName = null;
        } else {
            $message = $position === 0 ?
                clienttranslate('${tokenIcon1}${player_name1} returns ${tokenIcon2}${player_name2}\'s tile back') :
                clienttranslate('${tokenIcon1}${player_name1} places ${tokenIcon2}${player_name2}\'s tile on position ${positionIcon}');
            $ownerName = self::getPlayerNameById($owner);
        }

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers("move", $message, [
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

        if (!$this->isDeploymentReady()) {
            throw new BgaUserException('Not enough tiles');
        }

        $playerName = self::getActivePlayerName();
        self::notifyAllPlayers('message', clienttranslate('${tokenIcon}${player_name} reveals the Knights'), [
            'player_name' => $playerName,
            'tokenIcon' => "player,$playerName"
        ]);

        self::giveExtraTime(self::getCurrentPlayerId());

        $this->gamestate->nextState('check');
    }

    function updateNotes(string $notes): void {
        if (!(self::isSpectator() || self::isCurrentPlayerZombie())) {
            if ((int)$this->gamestate->state_id() === State::REVIEW) {
                throw new BgaUserException('Notes are locked');
            }

            $playerId = self::getCurrentPlayerId();
            self::DbQuery("UPDATE player SET notes = '$notes' WHERE player_id = $playerId");
            if (self::DbAffectedRow() !== 0) {
                self::notifyPlayer($playerId, 'notes', '', ['notes' => $notes]);
            }
        }
    }

    function confirm()
    {
        self::checkAction('confirm');
        $this->gamestate->setPlayerNonMultiactive(self::getCurrentPlayerId(), '');
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

    function applyScore(bool $knightsWin): void
    {
        $round = self::getGameStateValue(Globals::ROUND);
        $fixed_score = (int)self::getGameStateValue(GameOption::FIXED_SCORE);
        $score = $round >= MAX_ROUNDS && $fixed_score === 0 ? 7 : ($knightsWin ? 2 : 3);
        $check = $knightsWin ?
            '(tile.player_id IS NULL OR tile.player_id <> player_status.player_id) AND inspection.player_id IS NULL' :
            'tile.player_id = player_status.player_id OR inspection.player_id IS NOT NULL';

        $winners = self::getObjectListFromDb(<<<EOF
            SELECT player_status.player_id, player_status.token 
                 FROM player_status 
                    LEFT JOIN tile ON (tile.`character` = 0)
                    LEFT JOIN inspection 
                        ON (inspection.player_id = player_status.player_id 
                            AND inspection.tile_id = tile.tile_id)                    
                 WHERE $check 
            EOF);

        if (count($winners) > 0) {
            $tokens = 0;
            $conditions = [];
            $players = [];
            foreach ($winners as ['player_id' => $playerId, 'token' => $token]) {
                $tokens |= 1 << (int)$token;
                $conditions[] = "player_id = $playerId";
                $players[] = $playerId;
            }

            $teamWinValue = $tokens << 1 | ($knightsWin ? 1 : 0);
            self::incGameStateValue(Globals::TEAM_WINS,
                $teamWinValue << ($round - 1) * 9);

            $args = implode(' OR ', $conditions);

            self::DbQuery(<<<EOF
                UPDATE player
                SET player_score = player_score + $score
                WHERE $args
                EOF);

            $winStat = $knightsWin ? Stats::KNIGHT_WINS : Stats::WITCH_WINS;
            self::incStat(1, $winStat);
            foreach ($players as $playerId) {
                self::incStat(1, $winStat, $playerId);
            }

            $message = $knightsWin ?
                clienttranslate('The Knights team wins the round! ${tokenIcons} receive(s) ${score} points') :
                clienttranslate('The Witch team wins the round! ${tokenIcons} receive(s) ${score} points');

            self::notifyAllPlayers('score', $message, [
                'tokenIcons' => "bitset,$tokens",
                'score' => $score,
                'players' => $players,
                'knightsWin' => $knightsWin,
                'preserve' => ['tokenIcons']
            ]);
        } else {
            self::notifyAllPlayers('score', 'The Knights team loses, so no points are awarded', [
                'knightsWin' => false,
            ]);
        }
    }

    function determineAnswer(string $playerId, int $tileId, int $question): int
    {
        $answer = (int)self::getUniqueValueFromDb(<<<EOF
            SELECT ((1 << `character`) & ($question << 1)) <> 0
            FROM tile WHERE tile_id = $tileId
            EOF);
        if (self::isWitchTeam($playerId)) {
            $answer |= 0b10;
        }
        return $answer;
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
                'playerId' => $player_id,
                'voters' => $voters,
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

        self::incStat(1, Stats::APPOINTED, $captain);

        self::notifyAllPlayers('appoint', clienttranslate('${tokenIcon}${player_name} is appointed Captain'), [
            'player_name' => $playerName,
            'playerId' => $captain,
            'tokenIcon' => "player,$playerName",
            'numberIcon' => $character === 0 ? $character : (1 << ($character - 1)),
            'preserve' => ['tokenIcon', 'numberIcon']
        ]);

        $this->gamestate->changeActivePlayer($captain);

        if ($character === 0 || self::isWitchTeam($captain)) {
            if ($character === 0) {
                self::notifyAllPlayers('reveal', clienttranslate('${tokenIcon}${player_name} turns out to be ${numberIcon}'), [
                    'player_name' => $playerName,
                    'tokenIcon' => "tile,$tileId",
                    'tileId' => $tileId,
                    'character' => 0,
                    'numberIcon' => 0,
                    'preserve' => ['numberIcon', 'tokenIcon']
                ]);
            } else {
                self::notifyAllPlayers('reveal', '', [
                    'player_name' => $playerName,
                    'tileId' => $tileId,
                    'character' => $character,
                ]);

                ['player_name' => $witchName, 'tile_id' => $tileId] =
                    self::getObjectFromDb(<<<'EOF'
                        SELECT player_name, tile_id
                        FROM tile NATURAL LEFT JOIN player 
                        WHERE tile.`character` = 0
                        EOF);

                $message = $witchName === null ?
                    clienttranslate('${tokenIcon1}${player_name1} is bewitched by ${tokenIcon2}Knight-errant!') :
                    clienttranslate('${tokenIcon1}${player_name1} is bewitched by ${tokenIcon2}${player_name2}!');

                self::notifyAllPlayers('reveal', $message, [
                    'player_name1' => $playerName,
                    'player_name2' => $witchName,
                    'tokenIcon1' => "player,$playerName",
                    'tokenIcon2' => "tile,$tileId",
                    'tileId' => $tileId,
                    'character' => 0,
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
            $asked = self::getUniqueValueFromDb(<<<EOF
                SELECT recipient_id FROM question
                WHERE answer IS NULL
                ORDER BY question_id DESC
                LIMIT 1
                EOF);

            if ($asked !== null) {
                $coop = (int)self::getGameStateValue(GameOption::COOP);

                if (!$coop) {
                    $this->gamestate->changeActivePlayer($asked);
                    $this->gamestate->nextState('answer');
                    return;
                }
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

        $list = [];
        $previous = 0;
        $mistakeCount = 0;
        $missingTile = null;

        foreach ($order as $tile) {
            $character = (int)$tile['character'];
            if ((int)$tile['deployed']) {
                if ($character <= $previous) {
                    ++$mistakeCount;
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
                ++$mistakeCount;
                break;
            }
        }

        self::notifyAllPlayers('order', clienttranslate('Revealed order is ${listIcon}'), [
            'listIcon' => implode(',', $list)
        ]);

        if ($missingTile !== null) {
            if ($missingTile['player_id'] === null) {
                $message = clienttranslate('${tokenIcon}Knight-errant\'s tile ${numberIcon} has not been included');
                $playerName = null;
            } else {
                $message = clienttranslate('${tokenIcon}${player_name}\'s tile ${numberIcon} has not been included');
                $playerName = self::getPlayerNameById($missingTile['player_id']);
            }

            self::notifyAllPlayers('reveal', $message, [
                'player_name' => $playerName,
                'tokenIcon' => "tile,$missingTile[tile_id]",
                'numberIcon' => 1 << ((int)$missingTile['character'] - 1),
                'tileId' => $missingTile['tile_id'],
                'character' => $missingTile['character'],
                'preserve' => ['tokenIcon', 'numberIcon'],
            ]);
        }

        if ($mistakeCount > 0) {
            $captain = self::getGameStateValue(Globals::CAPTAIN);
            self::incStat($mistakeCount, Stats::MISTAKES, $captain);
        }

        $this::applyScore($mistakeCount === 0);

        $review = (int)self::getGameStateValue(GameOption::REVIEW);

        if ($review) {
            $notes = self::getObjectListFromDb(<<<EOF
                SELECT player_id AS id, notes 
                FROM player NATURAL JOIN player_status
                EOF);
            self::notifyAllPlayers('review',  '', [
                'notes' => $notes
            ]);
        }

        if ($review && self::getGameStateValue(Globals::ROUND) < MAX_ROUNDS) {
            $this->gamestate->nextState('review');
        } else {
            $this->gamestate->nextState('round');
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
                self::DbQuery('UPDATE player_status NATURAL JOIN player SET voted = NULL, notes = NULL');

                self::notifyAllPlayers('round', clienttranslate('New round begins'), [
                    'firstPlayer' => $nextPlayer
                ]);
                self::notifyAllPlayers('notes', '', ['notes' => null]);

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

    function argInspect(): array
    {
        $mode = (int)self::getGameStateValue(GameOption::MODE);
        if ($mode === GameMode::DARKNESS) {
            $actionsTaken = self::getGameStateValue(Globals::ACTIONS_TAKEN);
            $stage = $actionsTaken < $this->getPlayersNumber() ? 0 : 1;
            return ['stage' => $stage];
        } else {
            return [];
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

    function argDeployKnights(): array {
        return ['ready' => $this->isDeploymentReady()];
    }

    function zombieTurn($state, $activePlayer)
    {
        $stateName = $state['name'];

        if ($state['type'] === FsmType::SINGLE_PLAYER) {
            if ($stateName === 'deployKnights') {
                $this->gamestate->jumpToState(State::NEXT_ROUND);
            } else {
                if ($stateName === 'answer') {
                    $expectedAnswer = (int)self::getGameStateValue(Globals::ANSWER);
                    $this->recordAnswer($activePlayer, $expectedAnswer & 0b1);
                } else {
                    self::incGamestateValue(Globals::ACTIONS_TAKEN, 1);
                }
                $this->gamestate->jumpToState(State::DISPATCH_ACTION);
            }
        } else if ($state['type'] === FsmType::MULTIPLE_PLAYERS) {
            $this->gamestate->setPlayerNonMultiactive($activePlayer, '');
        } else {
            throw new feException("Zombie mode not supported at this game state: $stateName");
        }
    }

    function upgradeTableDb($fromVersion)
    {
        if ($fromVersion <= 240310_1702) {
            self::applyDbUpgradeToAllDB(
                'ALTER TABLE DBPREFIX_player ADD `notes` VARCHAR(64) NULL');
        }
    }

    function __skipToVote()
    {
        self::setGameStateValue(Globals::ACTIONS_TAKEN, self::getPlayersNumber() * 3);
        self::DbQuery("UPDATE player_status SET voted = NULL");
        $this->gamestate->jumpToState(State::VOTE);
    }

    function __skipToDeploy()
    {
        self::setGameStateValue(Globals::CAPTAIN, self::getCurrentPlayerId());
        $this->gamestate->jumpToState(State::DEPLOY_KNIGHTS);
    }

    function __skipRound()
    {
        $this->gamestate->jumpToState(State::NEXT_ROUND);
    }

    function __getOrder()
    {
        $tiles = self::getObjectListFromDb("SELECT * FROM tile");
        $string = [];
        $args = [];
        foreach ($tiles as $i => $tile) {
            $string[] = "\${tokenIcon$i}: $tile[character]";
            $args["tokenIcon$i"] = "tile,$tile[tile_id]";
        }
        self::notifyAllPlayers('message', implode(', ', $string), $args);
    }
}
