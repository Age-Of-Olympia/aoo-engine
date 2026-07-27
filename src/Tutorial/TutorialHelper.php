<?php

namespace App\Tutorial;

/**
 * Tutorial Helper - Centralized utilities for tutorial mode
 *
 * Provides static methods to handle common tutorial operations like:
 * - Getting the correct active player ID (tutorial vs main player)
 * - Checking if currently in tutorial mode
 * - Managing tutorial session state
 *
 * This eliminates scattered manual checks across the codebase.
 */
class TutorialHelper
{
    /**
     * Get the active player ID
     *
     * Returns tutorial player ID if in tutorial mode, otherwise main player ID.
     * Use this instead of manually checking $_SESSION['playerId'].
     *
     * @return int Active player ID
     */
    public static function getActivePlayerId(): int
    {
        // If in tutorial mode, use tutorial player ID
        if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_player_id'])) {
            $tutorialPlayerId = (int) $_SESSION['tutorial_player_id'];

            // Validate that the tutorial player still exists
            if (self::validateTutorialPlayer($tutorialPlayerId)) {
                return $tutorialPlayerId;
            }

            // Stale session detected: the tutorial_player_id in $_SESSION
            // points at a row that no longer exists in `players`. Emit
            // one structured line per occurrence so the divergence rate
            // can be estimated by grepping production logs:
            //   grep '"event":"tutorial_session_stale"' apache_error.log | wc -l
            self::logTelemetry('tutorial_session_stale', [
                'tutorial_player_id'  => $tutorialPlayerId,
                'main_player_id'      => (int) ($_SESSION['playerId'] ?? 0),
                'tutorial_session_id' => $_SESSION['tutorial_session_id'] ?? null,
            ]);
            self::exitTutorialMode();
        }

        // Otherwise use main player ID
        return (int) ($_SESSION['playerId'] ?? 0);
    }

    /**
     * Emit a single JSON line of structured telemetry to error_log.
     *
     * Format: `{"event":"...","ts":"...","...":...}` — one line, no trailing
     * newline (error_log adds it). Designed to be grep-friendly:
     *   grep '"event":"<name>"' apache_error.log
     *
     * @param string               $event   Discriminator key for log scrapers
     * @param array<string, mixed> $context Additional fields to include
     */
    private static function logTelemetry(string $event, array $context): void
    {
        $line = json_encode(array_merge(
            ['event' => $event, 'ts' => date('c')],
            $context
        ));
        if ($line !== false) {
            error_log($line);
        }
    }

    /**
     * Validate that a tutorial player exists in the database
     *
     * @param int $tutorialPlayerId Tutorial player ID to validate
     * @return bool True if player exists, false otherwise
     */
    private static function validateTutorialPlayer(int $tutorialPlayerId): bool
    {
        try {
            $db = new \Classes\Db();
            $result = $db->exe("SELECT id FROM players WHERE id = ?", [$tutorialPlayerId]);
            return $result && $result->num_rows > 0;
        } catch (\Exception $e) {
            error_log("[TutorialHelper] Error validating tutorial player: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if currently in tutorial mode
     *
     * @return bool True if in tutorial, false otherwise
     */
    public static function isInTutorial(): bool
    {
        return !empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_session_id']);
    }

    /**
     * Get tutorial session ID if in tutorial
     *
     * @return string|null Session ID or null if not in tutorial
     */
    public static function getSessionId(): ?string
    {
        if (self::isInTutorial()) {
            return $_SESSION['tutorial_session_id'] ?? null;
        }
        return null;
    }

    /**
     * Les PNJ que le tutoriel a fait apparaître pour la session en cours.
     *
     * Le damier a besoin de les reconnaître : il pose sur eux la marque
     * `.tutorial-enemy`, à laquelle les étapes accrochent leur surlignage
     * (« approche-toi », « clique dessus »). Il les reconnaissait à leur NOM,
     * « Âme d'entraînement » — une chaîne éditable depuis l'administration du
     * tutoriel, qui ne servait qu'à l'affichage, et dont personne ne pouvait
     * deviner qu'un renommage éteindrait le surlignage sans rien signaler.
     *
     * L'inscription en base, elle, est posée par l'apparition elle-même
     * (TutorialResourceManager) et ne dépend d'aucun libellé.
     *
     * Portée : les PNJ apparus pour la session avec le rôle `enemy`. Les
     * lignes antérieures à la colonne `role` restent à NULL et comptent comme
     * telles — le seul PNJ dynamique configuré à ce jour est l'adversaire.
     *
     * @return array<int, true> ids en CLÉS (pour un `isset()` par occupant) ;
     *                          tableau vide hors tutoriel
     */
    public static function getSessionEnemyIds(): array
    {
        $sessionId = self::getSessionId();

        if ($sessionId === null) {
            return [];
        }

        try {
            $db = new \Classes\Db();
            $result = $db->exe(
                "SELECT enemy_player_id
                   FROM tutorial_enemies
                  WHERE tutorial_session_id = ?
                    AND (role IS NULL OR role = 'enemy')",
                [$sessionId]
            );

            $ids = [];

            while ($result && $row = $result->fetch_object()) {
                $ids[(int) $row->enemy_player_id] = true;
            }

            return $ids;
        } catch (\Exception $e) {
            error_log('[TutorialHelper] Error listing session enemies: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tutorial player ID if in tutorial
     *
     * @return int|null Tutorial player ID or null if not in tutorial
     */
    public static function getTutorialPlayerId(): ?int
    {
        if (self::isInTutorial()) {
            return (int) $_SESSION['tutorial_player_id'];
        }
        return null;
    }

    /**
     * Start tutorial mode
     *
     * Sets session variables for tutorial mode
     *
     * @param string $sessionId Tutorial session ID
     * @param int $tutorialPlayerId Tutorial player ID
     */
    public static function startTutorialMode(string $sessionId, int $tutorialPlayerId): void
    {
        $_SESSION['in_tutorial'] = true;
        $_SESSION['tutorial_session_id'] = $sessionId;
        $_SESSION['tutorial_player_id'] = $tutorialPlayerId;
    }

    /**
     * Exit tutorial mode
     *
     * Clears tutorial session variables
     */
    public static function exitTutorialMode(): void
    {
        unset($_SESSION['in_tutorial']);
        unset($_SESSION['tutorial_session_id']);
        unset($_SESSION['tutorial_player_id']);
    }

    /**
     * Get main player ID (ignoring tutorial mode)
     *
     * @return int Main player ID
     */
    public static function getMainPlayerId(): int
    {
        return (int) ($_SESSION['playerId'] ?? 0);
    }

    /**
     * Load player with full data and validation
     *
     * Loads the active player (tutorial or main) with all necessary data.
     * This is a centralized method to avoid duplicate player loading code.
     *
     * @param bool $loadCaracs Whether to load characteristics (turn data)
     * @param bool $throwOnFailure Whether to throw exception if data load fails
     * @return \Classes\Player Loaded player instance
     * @throws \RuntimeException If player data fails to load and throwOnFailure is true
     */
    public static function loadActivePlayer(bool $loadCaracs = false, bool $throwOnFailure = false): \Classes\Player
    {
        $activePlayerId = self::getActivePlayerId();
        $player = new \Classes\Player($activePlayerId);

        // Load player data
        $player->get_data();

        // Validate data loaded successfully
        if (!$player->data || $player->data === false) {
            $errorMsg = "Failed to load player data for player {$activePlayerId}";

            if ($throwOnFailure) {
                throw new \RuntimeException($errorMsg);
            }
        }

        // Load characteristics if requested
        if ($loadCaracs) {
            $player->get_caracs();
        }

        return $player;
    }

    /**
     * Load specific player with full data and validation
     *
     * @param int $playerId Player ID to load
     * @param bool $loadCaracs Whether to load characteristics (turn data)
     * @param bool $throwOnFailure Whether to throw exception if data load fails
     * @return \Classes\Player Loaded player instance
     * @throws \RuntimeException If player data fails to load and throwOnFailure is true
     */
    public static function loadPlayer(int $playerId, bool $loadCaracs = false, bool $throwOnFailure = false): \Classes\Player
    {
        $player = new \Classes\Player($playerId);

        // Load player data
        $player->get_data();

        // Validate data loaded successfully
        if (!$player->data || $player->data === false) {
            $errorMsg = "Failed to load player data for player {$playerId}";

            if ($throwOnFailure) {
                throw new \RuntimeException($errorMsg);
            }
        }

        // Load characteristics if requested
        if ($loadCaracs) {
            $player->get_caracs();
        }

        return $player;
    }

    /**
     * Finalize a player's exit from the tutorial into the real game.
     *
     * This is the shared tail of the complete / skip / cancel endpoints,
     * which previously each copy-pasted (and had started to drift on) the
     * same four steps: drop invisibleMode, teleport out of waiting_room to
     * the faction respawn plan, grant the race's starter actions, and — on
     * the first run only — award the reward XP/PI plus the starter pack.
     *
     * The only thing that varies between callers is the reward table, so it
     * is passed in. Each endpoint keeps its own unique concerns (auth gates,
     * session/resource cleanup, output buffering) and delegates this tail.
     *
     * @param \Classes\Player      $player            The main (real) player.
     * @param array{xp:int,pi:int} $reward            TUTORIAL_COMPLETION_REWARD or TUTORIAL_SKIP_REWARD.
     * @param bool                 $hasCompletedBefore True on a replay — suppresses the reward + starter pack.
     * @return array{xp:int,pi:int} XP/PI actually awarded (zeros on replay), for the caller's JSON response.
     */
    public static function finalizeExitToGame(
        \Classes\Player $player,
        array $reward,
        bool $hasCompletedBefore
    ): array {
        $player->get_data();

        self::removeInvisibleMode($player);
        self::teleportFromWaitingRoom($player);
        self::grantRaceActions($player);

        $earned = ['xp' => 0, 'pi' => 0];
        if (!$hasCompletedBefore) {
            $player->put_xp($reward['xp']); /* This adds both XP and PI */
            $earned = ['xp' => $reward['xp'], 'pi' => $reward['pi']];
            self::grantStarterPack($player);
        }

        $player->refresh_data();
        $player->refresh_view();
        $player->getCoords();

        return $earned;
    }

    /**
     * Drop invisibleMode so the player can be seen/interact normally.
     */
    private static function removeInvisibleMode(\Classes\Player $player): void
    {
        if ($player->have_option('invisibleMode')) {
            $player->end_option('invisibleMode');
        }
    }

    /**
     * Teleport the player off the waiting_room plan to their faction's
     * respawn plan (default "olympia") at/around 0,0,0. No-op if they have
     * already left waiting_room. Falls back to creating the destination
     * coords row when none is free — previously only cancel.php did this,
     * so the same teleport behaved differently per endpoint.
     */
    private static function teleportFromWaitingRoom(\Classes\Player $player): void
    {
        $player->getCoords();
        if ($player->coords->plan !== 'waiting_room') {
            return;
        }

        $factionJson = (new \App\Service\FactionService())->getFactionData($player->data->faction);
        $respawnPlan = isset($factionJson->respawnPlan) ? $factionJson->respawnPlan : 'olympia';

        $goCoords = (object) array('x' => 0, 'y' => 0, 'z' => 0, 'plan' => $respawnPlan);

        $db = new \Classes\Db();
        $coordsId = \Classes\View::get_free_coords_id_arround($goCoords);

        if ($coordsId === null) {
            $db->exe(
                'INSERT INTO coords (x, y, z, plan) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
                array(0, 0, 0, $respawnPlan)
            );
            /* LAST_INSERT_ID() works for both the INSERT and the UPDATE path */
            $row = $db->exe('SELECT LAST_INSERT_ID() as id')->fetch_assoc();
            $coordsId = $row['id'];
        }

        $db->exe('UPDATE players SET coords_id = ? WHERE id = ?', array($coordsId, $player->id));

        /* getCoords() est mémoïsé : invalider après l'écriture directe
         * (null plutôt qu'unset — PHPStan unset.possiblyHookedProperty). */
        $player->coords = null;
    }

    /**
     * Grant the race's starter actions, idempotently (shared logic in
     * PlayerActionsService::grantRaceStarterPack).
     */
    private static function grantRaceActions(\Classes\Player $player): void
    {
        (new \App\Service\PlayerActionsService())
            ->grantRaceStarterPack($player->id, $player->data->race);
    }

    /**
     * Grant the one-time starter pack a brand-new character used to receive
     * from the old gaia2 rez trigger (scripts/map/triggers/rez.php) before
     * the tutorial replaced that flow: a flat 20 gold, a walking stick, and
     * the default first-spawn avatar.
     *
     * The 20 gold is ON TOP of the race bonus already granted at
     * registration (register.php); the walking stick and avatar were not
     * carried over anywhere else. Only called from the first-time branch of
     * finalizeExitToGame(), so it fires exactly once per character.
     *
     * @param \Classes\Player $player The main (real) player.
     */
    private static function grantStarterPack(\Classes\Player $player): void
    {
        if ($gold = \Classes\Item::get_item_by_name('or')) {
            $gold->add_item($player, 20);
        }
        if ($stick = \Classes\Item::get_item_by_name('baton_marche')) {
            $stick->add_item($player, 1);
        }

        // Avatar is written directly rather than via Player::change_avatar():
        // that method's file_exists() check is relative to the CWD and
        // hard-exits on a miss, which would corrupt this JSON response when
        // the CWD is not the document root or the race has no 1.png (e.g.
        // 'ame'). Guard with an absolute path, store the same relative value
        // the rest of the app uses, and refresh the caches change_avatar
        // would have refreshed.
        $race = $player->data->race ?? '';
        $avatar = 'img/avatars/' . $race . '/1.png';
        if ($race !== '' && is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $avatar)) {
            (new \Classes\Db())->exe(
                'UPDATE players SET avatar = ? WHERE id = ?',
                [$avatar, $player->id]
            );
            $player->refresh_data();
            $player->refresh_view();
        }
    }
}
