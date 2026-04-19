<?php

namespace App\Tutorial;

use App\Entity\EntityManagerFactory;
use App\Entity\TutorialPlayer;
use Doctrine\DBAL\Connection;

/**
 * TutorialPlayerFactory — creates a fresh tutorial character
 * for a session and returns it as a Doctrine TutorialPlayer.
 *
 * Phase 4.4 replacement for `App\Tutorial\TutorialPlayer::create()`.
 * The original service-class static factory is retired; its logic
 * lives here unchanged except for the return type (entity instead
 * of the service class).
 *
 * Every step the original did is still done in the same order:
 *   1. Create isolated map instance via TutorialMapInstance
 *   2. Resolve race (defaults to real player's race)
 *   3. Validate race against RACES_EXT
 *   4. Compute avatar/portrait defaults
 *   5. Generate IDs via getNextEntityId/getNextDisplayId
 *   6. INSERT players row (tutorial discriminator)
 *   7. Delete stale JSON cache file if any
 *   8. INSERT players_actions (6 basic actions)
 *   9. INSERT players_options ('showActionDetails')
 *   10. INSERT tutorial_players bookkeeping row
 *   11. Hydrate the entity via Doctrine find() and return it
 *
 * Creation-time raw SQL + global functions (getNextEntityId etc.)
 * stay on this factory rather than moving to the entity itself;
 * the entity is a read/write model of a single row, not the
 * workflow coordinator. Future phase (5+) can replace raw SQL with
 * Doctrine persistence if a stable entity surface is needed, but
 * that's out of scope for 4.4.
 */
class TutorialPlayerFactory
{
    /**
     * Create a new tutorial character and return it as a Doctrine
     * entity. Throws on invalid race or any DB failure.
     */
    public static function create(
        Connection $conn,
        int $realPlayerId,
        string $tutorialSessionId,
        ?string $race = null,
        string $templatePlan = 'tutorial'
    ): TutorialPlayer {
        // Step 1: Create isolated map instance for this tutorial session
        error_log("[TutorialPlayerFactory] Creating map instance for session {$tutorialSessionId} from template {$templatePlan}");
        $mapInstance = new TutorialMapInstance($conn);
        $instanceData = $mapInstance->createInstance($tutorialSessionId, $templatePlan);
        $startingCoordsId = $instanceData['starting_coords_id'];

        error_log("[TutorialPlayerFactory] Map instance created: {$instanceData['plan_name']}, starting at coords_id {$startingCoordsId}");

        // Step 2: Resolve race (default to real player's race)
        if ($race === null) {
            $stmt = $conn->prepare('SELECT race FROM players WHERE id = ?');
            $stmt->bindValue(1, $realPlayerId);
            $result = $stmt->executeQuery();
            $playerData = $result->fetchAssociative();
            $race = $playerData['race'] ?? 'Humain';
        }

        // Step 3: Validate race against extended races list
        if (!in_array(strtolower($race), RACES_EXT, true)) {
            throw new \InvalidArgumentException(
                "Invalid race '{$race}'. Valid races: " . implode(', ', RACES_EXT)
            );
        }

        // Step 4: Avatar/portrait defaults
        $raceLower = strtolower($race);
        $defaultAvatar = "img/avatars/{$raceLower}/1.png";
        $defaultPortrait = "img/portraits/{$raceLower}/1.jpeg";

        // Step 5: Generate IDs via range-based system
        $actualPlayerId = getNextEntityId('tutorial');  // 10000000+ range
        $displayId = getNextDisplayId('tutorial');      // 1, 2, 3...
        $name = "Apprenti_" . substr($tutorialSessionId, 0, 8);

        // Step 6: Insert players row (tutorial discriminator).
        //
        // `tutorial_session_id` and `real_player_id_ref` on the
        // `players` row are what TutorialPlayer maps to via its
        // STI-subclass ORM\Column attributes. The original
        // service-class factory left these NULL and tracked the link
        // only through the `tutorial_players` table — fine for the
        // service-class path, broken for the entity path (Phase 4.3's
        // TutorialManager::completeTutorial reads
        // getRealPlayerIdRef() to find the real player). Populate
        // both columns here; tutorial_players keeps its parallel
        // link for the id-level bookkeeping.
        $conn->insert('players', [
            'id'                  => $actualPlayerId,
            'player_type'         => 'tutorial',
            'display_id'          => $displayId,
            'name'                => $name,
            'psw'                 => '',
            'mail'                => '',
            'plain_mail'          => '',
            'coords_id'           => $startingCoordsId,
            'race'                => $race,
            'xp'                  => 0,
            'pi'                  => 0,
            'energie'             => 100,
            'avatar'              => $defaultAvatar,
            'portrait'            => $defaultPortrait,
            'text'                => 'Personnage de tutoriel',
            'nextTurnTime'        => time() + 86400, // 24h future to skip NewTurn page
            'tutorial_session_id' => $tutorialSessionId,
            'real_player_id_ref'  => $realPlayerId,
        ]);

        // Step 7: Remove any stale JSON cache file
        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $actualPlayerId . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            error_log("[TutorialPlayerFactory] Deleted stale cache file for player {$actualPlayerId}");
        }

        // Step 8: Grant basic actions
        foreach (['fouiller', 'repos', 'attaquer', 'courir', 'prier', 'entrainement'] as $actionName) {
            $conn->insert('players_actions', [
                'player_id' => $actualPlayerId,
                'name'      => $actionName,
            ]);
        }

        // Step 9: Enable action details by default
        $conn->insert('players_options', [
            'player_id' => $actualPlayerId,
            'name'      => 'showActionDetails',
        ]);

        // Step 10: Tutorial tracking entry
        $conn->insert('tutorial_players', [
            'real_player_id'      => $realPlayerId,
            'tutorial_session_id' => $tutorialSessionId,
            'player_id'           => $actualPlayerId,
            'name'                => $name,
            'is_active'           => true,
        ]);

        // Step 11: Hydrate the entity via Doctrine and return it.
        // Clear the EntityManager first so the find() hits the DB
        // rather than the identity map — the row we just inserted
        // via raw SQL isn't in the map yet, but any OTHER stale
        // TutorialPlayer (e.g. from a prior failed session)
        // could misdirect the hydrate.
        $em = EntityManagerFactory::getEntityManager();
        $em->clear(TutorialPlayer::class);
        $entity = $em->find(TutorialPlayer::class, $actualPlayerId);

        if ($entity === null) {
            throw new \RuntimeException(
                "TutorialPlayer missing after create for player {$actualPlayerId} — row may have been rolled back"
            );
        }

        return $entity;
    }
}
