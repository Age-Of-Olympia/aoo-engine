<?php

namespace App\Service;

use App\Entity\BuildingDetails;
use App\Entity\EntityManagerFactory;
use App\Service\DialogService;
use Classes\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creation and lookup of buildings — `players` rows with
 * player_type='building' plus their 1:1 `buildings` satellite row
 * (docs/design-buildings-entities.md §4.7).
 *
 * Mirrors Player::put_player(): reserved id range, display id, coords
 * row reuse. The building's max PV comes from its type's
 * non-playable pseudo-race (§4.6) through the untouched caracs
 * pipeline, so damage works with zero new code
 * (putBonus / getRemaining on the legacy Player).
 */
class BuildingService extends BaseService
{
    /**
     * Tile/portrait image used when the type has no dedicated asset
     * yet — an existing wall sprite, so a freshly placed building renders
     * on the damier without any art step.
     */
    public const DEFAULT_IMAGE = 'img/walls/barricade.png';

    private EntityManagerInterface $entityManager;
    private RaceService $raceService;
    private FactionService $factionService;
    private DialogService $dialogService;

    public function __construct(
        ?RaceService $raceService = null,
        ?FactionService $factionService = null,
        ?DialogService $dialogService = null,
    ) {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
        $this->raceService = $raceService ?? new RaceService();
        $this->factionService = $factionService ?? new FactionService();
        $this->dialogService = $dialogService ?? new DialogService();
    }

    /**
     * Démontage COMPLET d'une ligne d'entité : composants, logs (les
     * deux sens de la FK), puis la ligne players — appelé DANS la
     * transaction du site (retrait de bâtiment, reprise/destruction
     * d'objet unique : trois sites, une seule séquence).
     */
    public static function deleteEntityRows(\Doctrine\DBAL\Connection $conn, int $playerId): void
    {
        foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
            $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$playerId]);
        }
        $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$playerId, $playerId]);
        $conn->executeStatement('DELETE FROM players WHERE id = ?', [$playerId]);
    }

    /**
     * Sprite of a structure type, in fallback order: dedicated avatar
     * (img/avatars/{type}.webp) → the map-wall sprite of the same name
     * (img/walls/{type}.png — a built mur_bois looks like a mur_bois) →
     * the generic placeholder. View.php renders players.avatar directly.
     */
    public static function resolveAvatar(string $type, bool $broken = false): string
    {
        $root = dirname(__DIR__, 2);
        $suffix = $broken ? '_broken' : '';

        foreach ([
            'img/avatars/' . $type . $suffix . '.webp',
            'img/walls/' . $type . $suffix . '.png',
        ] as $candidate) {
            if (is_file($root . '/' . $candidate)) {
                return $candidate;
            }
        }

        return $broken ? self::resolveAvatar($type) : self::DEFAULT_IMAGE;
    }

    /**
     * Purge every per-entity file cache of an id (.json = get_data,
     * .svg = damier, .turn/.caracs/.invent) — appelée à la POSE (id
     * recyclé) comme au retrait, sinon la nouvelle entité ressert
     * l'identité de l'ancienne.
     */
    public static function purgeEntityCaches(int $playerId): void
    {
        foreach (['.json', '.svg', '.turn.json', '.caracs.json', '.invent.html'] as $suffix) {
            @unlink(\Classes\Player::cachePath($playerId, $suffix));
        }
        json()->forget('players', (string) $playerId);
    }

    /**
     * Place a building of the given type on the map.
     *
     * The type is a races row of kind 'structure' (the races table is the
     * catalog of entity base stats); it lands in players.race like any
     * entity — no duplicate "archetype" storage.
     *
     * @param string      $type     structure-kind race name ('palissade', …)
     * @param object      $goCoords stdClass {x, y, z, plan} — the target tile
     * @param int|null    $ownerId  players.id of the owning character, if any
     * @param string      $faction  faction CODE from the catalog, '' = neutral
     * @param string|null $name     display name; defaults to the race label
     *
     * @return int the new building's players.id (ENTITY_ID_RANGES['building'])
     *
     * @throws \InvalidArgumentException on unknown/non-structure type,
     *                                   unknown faction code or unknown owner
     */
    public function place(
        string $type,
        object $goCoords,
        ?int $ownerId = null,
        string $faction = '',
        ?string $name = null
    ): int {
        $race = $this->raceService->getRaceByName($type);
        if ($race === null) {
            throw new \InvalidArgumentException(
                "Type inconnu : '{$type}' (aucune entrée de ce nom au catalogue races)."
            );
        }
        if (!$race->isStructureKind()) {
            throw new \InvalidArgumentException(
                "'{$type}' n'est pas un type de structure (races.kind) — une race de personnage ne peut pas être posée en bâtiment."
            );
        }

        if ($faction !== '' && $this->factionService->getFactionByCode($faction) === null) {
            throw new \InvalidArgumentException("Faction inconnue : '{$faction}'.");
        }

        $conn = $this->entityManager->getConnection();

        if ($ownerId !== null) {
            $ownerExists = $conn->fetchOne('SELECT id FROM players WHERE id = ?', [$ownerId]);
            if ($ownerExists === false) {
                throw new \InvalidArgumentException("Propriétaire inconnu : joueur #{$ownerId}.");
            }
        }

        $id = getNextEntityId('building');
        $displayId = getNextDisplayId('building');
        $coordsId = View::get_coords_id($goCoords);

        // Un id recyclé (fixture de test, entité retirée hors remove())
        // peut laisser de vieux caches par-entité : sans purge, le
        // nouveau bâtiment ressert l'IDENTITÉ du précédent (get_data lit
        // le .json avant la base).
        self::purgeEntityCaches($id);

        $avatar = self::resolveAvatar($type);

        // Une seule transaction pour la paire players + buildings : un échec
        // du satellite ne doit pas laisser une ligne players orpheline qui
        // occupe la case sans apparaître dans listBuildings(). La case doit
        // être LIBRE (ni entité, ni mur) — vérifié ici, source unique de la
        // règle, sous verrou pour resserrer la fenêtre concurrente.
        $conn->transactional(function ($conn) use ($id, $displayId, $name, $race, $type, $avatar, $coordsId, $ownerId, $faction, $goCoords): void {
            $occupant = $conn->fetchOne('SELECT id FROM players WHERE coords_id = ? FOR UPDATE', [$coordsId]);
            if ($occupant !== false) {
                throw new \InvalidArgumentException(
                    "Case ({$goCoords->x}, {$goCoords->y}, {$goCoords->plan}) occupée par l'entité #{$occupant}."
                );
            }
            if ($conn->fetchOne('SELECT coords_id FROM map_walls WHERE coords_id = ?', [$coordsId]) !== false) {
                throw new \InvalidArgumentException(
                    "Case ({$goCoords->x}, {$goCoords->y}, {$goCoords->plan}) occupée par un mur."
                );
            }

            $conn->executeStatement(
                'INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
                [
                    $id,
                    'building',
                    $displayId,
                    $name ?? $race->getLabel(),
                    $type,
                    $avatar,
                    $avatar,
                    $coordsId,
                    time(),
                ]
            );

            $conn->executeStatement(
                'INSERT INTO buildings (player_id, owner_id, faction, build_state)
                 VALUES (?, ?, ?, ?)',
                [$id, $ownerId, $faction, BuildingDetails::STATE_BUILT]
            );
        });

        // Le damier de chaque joueur est un SVG caché : invalider le
        // voisinage pour que le bâtiment apparaisse sans attendre un
        // déplacement.
        View::refresh_players_svg($goCoords);

        $this->addAuditLog("BuildingService::place {$type} #{$id} at ({$goCoords->x},{$goCoords->y},{$goCoords->plan})");

        return $id;
    }

    /**
     * The building's satellite row, or null when the id is not a building.
     */
    public function getDetails(int $playerId): ?BuildingDetails
    {
        return $this->entityManager->find(BuildingDetails::class, $playerId);
    }

    /**
     * Seuil de fermeture sur dégâts, en % de PV restants : en dessous, le
     * bâtiment est fermé (dialogue tu) — aligné sur le seuil du sprite
     * « brisé » des murs legacy (moitié des PV).
     */
    public const CLOSED_BELOW_PV_PCT = 50;

    /**
     * Pourquoi ce bâtiment est-il fermé — ou null s'il est OUVERT.
     * Source unique de la règle d'ouverture (observe, HUD, admin) :
     * un bâtiment fermé tait son dialogue.
     *
     * @param int $pvPct PV restants en % (l'appelant les a déjà —
     *                   observe.php les calcule pour le voile de dégâts)
     */
    public function closureReason(BuildingDetails $details, int $pvPct): ?string
    {
        if ($details->getBuildState() === BuildingDetails::STATE_RUIN) {
            return 'en ruine';
        }
        if ($details->getBuildState() === BuildingDetails::STATE_CONSTRUCTION) {
            return 'en construction';
        }
        if ($pvPct < self::CLOSED_BELOW_PV_PCT) {
            return 'endommagé';
        }
        if (!$details->isOpen()) {
            return 'fermé volontairement';
        }

        return null;
    }

    /**
     * Édition d'identité d'un bâtiment posé (admin → Bâtiments →
     * Éditer) : nom affiché, description (l'équivalent du « message du
     * jour » des joueurs — players.text, visible sur la carte et la
     * fiche), propriétaire et faction. Les mêmes validations que
     * place() ; le cache par-entité est purgé (get_data lit le .json).
     *
     * @throws \InvalidArgumentException id non-bâtiment, faction ou
     *                                   propriétaire inconnus
     */
    public function updateInfo(int $playerId, string $name, string $text, ?int $ownerId, string $faction): void
    {
        $details = $this->getDetails($playerId);
        if ($details === null) {
            throw new \InvalidArgumentException("#{$playerId} n'est pas un bâtiment.");
        }

        if ($faction !== '' && $this->factionService->getFactionByCode($faction) === null) {
            throw new \InvalidArgumentException("Faction inconnue : '{$faction}'.");
        }

        $conn = $this->entityManager->getConnection();

        if ($ownerId !== null
            && $conn->fetchOne('SELECT id FROM players WHERE id = ?', [$ownerId]) === false) {
            throw new \InvalidArgumentException("Propriétaire inconnu : joueur #{$ownerId}.");
        }

        if ($name === '') {
            $race = $this->raceService->getRaceByName(
                (string) $conn->fetchOne('SELECT race FROM players WHERE id = ?', [$playerId])
            );
            $name = $race !== null ? $race->getLabel() : "Bâtiment #{$playerId}";
        }

        $conn->executeStatement('UPDATE players SET name = ?, text = ? WHERE id = ?', [$name, $text, $playerId]);

        $details->setOwnerId($ownerId);
        $details->setFaction($faction);
        $this->entityManager->flush();

        // Seul le cache de données (.json, lu par get_data) est concerné —
        // pas le .svg du damier, que rien ne re-générerait ici.
        @unlink(\Classes\Player::cachePath($playerId, '.json'));
        json()->forget('players', (string) $playerId);

        $this->addAuditLog("BuildingService::updateInfo #{$playerId} '{$name}'");
    }

    /**
     * Fermeture/ouverture volontaire (admin — un jour le propriétaire).
     *
     * @throws \InvalidArgumentException id non-bâtiment
     */
    public function setOpen(int $playerId, bool $open): void
    {
        $details = $this->getDetails($playerId);
        if ($details === null) {
            throw new \InvalidArgumentException("#{$playerId} n'est pas un bâtiment.");
        }

        $details->setIsOpen($open);
        $this->entityManager->flush();

        $this->addAuditLog('BuildingService::setOpen #' . $playerId . ' ' . ($open ? 'ouvert' : 'fermé'));
    }

    /**
     * Attache un dialogue au bâtiment ('' pour le détacher). Le code doit
     * exister dans le catalogue `dialogs` — le lien vit sur l'entité et la
     * suit (ruine = muet, suppression = lien disparu), contrairement aux
     * déclencheurs map_dialogs qui restent collés à la case.
     *
     * @throws \InvalidArgumentException id non-bâtiment ou dialogue inconnu
     */
    public function setDialog(int $playerId, string $dialogName): void
    {
        $details = $this->getDetails($playerId);
        if ($details === null) {
            throw new \InvalidArgumentException("#{$playerId} n'est pas un bâtiment.");
        }

        if ($dialogName !== '' && !$this->dialogService->gameDialogExists($dialogName)) {
            throw new \InvalidArgumentException("Dialogue inconnu : « {$dialogName} ».");
        }

        $details->setDialog($dialogName);
        $this->entityManager->flush();

        $this->addAuditLog("BuildingService::setDialog #{$playerId} '{$dialogName}'");
    }

    /**
     * Every building with its position, state, owner and PV, for the admin
     * dashboard. Current PV = pseudo-race max + the players_bonus 'pv'
     * ledger (buildings have no upgrades/items, so the race base IS max).
     *
     * @return array<int, array{id:int, name:string, type:string, build_state:string,
     *                          dialog:string, is_open:bool, faction:string, owner_id:?int,
     *                          owner_name:?string, x:int, y:int, z:int, plan:string,
     *                          max_pv:int, current_pv:int}>
     */
    public function listBuildings(): array
    {
        // races is joined in PHP via the cached RaceService: the table was
        // created under a newer default collation than players and a SQL
        // join on r.name = p.race trips "illegal mix of collations".
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT p.id, p.name, p.race, b.build_state, b.faction, b.owner_id, b.dialog, b.is_open,
                    o.name AS owner_name, c.x, c.y, c.z, c.plan,
                    COALESCE(pb.n, 0) AS pv_bonus
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             LEFT JOIN players o ON o.id = b.owner_id
             LEFT JOIN players_bonus pb ON pb.player_id = p.id AND pb.name = 'pv'
             ORDER BY c.plan, p.id"
        );

        $raceService = $this->raceService;

        return array_map(static function (array $row) use ($raceService): array {
            $race = $raceService->getRaceByName((string) $row['race']);
            $maxPv = $race !== null ? $race->getCarac('pv') : 0;

            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'type' => (string) $row['race'],
                'build_state' => (string) $row['build_state'],
                'dialog' => (string) $row['dialog'],
                'is_open' => (bool) $row['is_open'],
                'faction' => (string) $row['faction'],
                'owner_id' => $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
                'owner_name' => $row['owner_name'] !== null ? (string) $row['owner_name'] : null,
                'x' => (int) $row['x'],
                'y' => (int) $row['y'],
                'z' => (int) $row['z'],
                'plan' => (string) $row['plan'],
                'max_pv' => $maxPv,
                'current_pv' => $maxPv + (int) $row['pv_bonus'],
            ];
        }, $rows);
    }

    /**
     * Admin full-restore of any STRUCTURE (building or unique object):
     * clear the PV wound ledger and, for buildings, flip build_state back
     * to 'built'.
     *
     * NOT the future in-game repair: PV restoration is the HEAL mechanic
     * (putBonus(['pv' => +x]) works on structures like on characters), so
     * the player-facing action will be a heal-type action gated by
     * TargetType ['structure'] — no parallel pipeline.
     */
    public function restore(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $isStructure = $conn->fetchOne(
            "SELECT id FROM players WHERE id = ? AND player_type IN ('building', 'unique')",
            [$playerId]
        );
        if ($isStructure === false) {
            return false;
        }

        $conn->executeStatement(
            "DELETE FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$playerId]
        );
        // No-op for unique objects: only buildings carry a lifecycle state.
        $conn->executeStatement(
            'UPDATE buildings SET build_state = ? WHERE player_id = ?',
            [BuildingDetails::STATE_BUILT, $playerId]
        );
        $this->swapAvatar($playerId, broken: false);

        $this->addAuditLog("BuildingService::restore #{$playerId}");

        return true;
    }

    /**
     * Flip the building to its destroyed state (build_state = 'ruin').
     * The players row STAYS: logs keep their FK targets and the ruin
     * still occupies the tile. Death-path callers only — admin removal
     * is remove().
     */
    public function markDestroyed(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $affected = $conn->executeStatement(
            'UPDATE buildings SET build_state = ? WHERE player_id = ?',
            [BuildingDetails::STATE_RUIN, $playerId]
        );

        if ($affected > 0) {
            // Bascule visuelle : la ruine prend le sprite _broken de son type
            // quand il existe (même mécanisme que les murs de carte).
            $this->swapAvatar($playerId, broken: true);
            $this->addAuditLog("BuildingService::markDestroyed #{$playerId}");
        }

        return $affected > 0;
    }

    /**
     * Point the entity's avatar at its type sprite (broken variant or
     * base) and refresh the neighbourhood render.
     */
    private function swapAvatar(int $playerId, bool $broken): void
    {
        $conn = $this->entityManager->getConnection();

        $race = $conn->fetchOne('SELECT race FROM players WHERE id = ?', [$playerId]);
        if ($race === false) {
            return;
        }

        $avatar = self::resolveAvatar((string) $race, $broken);
        $conn->executeStatement(
            'UPDATE players SET avatar = ?, portrait = ? WHERE id = ?',
            [$avatar, $avatar, $playerId]
        );
        @unlink(\Classes\Player::cachePath($playerId, '.json'));
        json()->forget('players', (string) $playerId);

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$playerId]
        );
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }
        @unlink(\Classes\Player::cachePath($playerId, '.svg'));
    }

    /**
     * Remove a building: satellite row + players row. Wounds and other
     * component rows are deleted first so no FK is left dangling. The
     * destruction GAME flow (drop materials, ruin state…) is the death-path
     * branch of roadmap step 6 — this is only the bare removal primitive
     * it will build on.
     */
    public function remove(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $isBuilding = $conn->fetchOne(
            "SELECT id FROM players WHERE id = ? AND player_type = 'building'",
            [$playerId]
        );
        if ($isBuilding === false) {
            return false;
        }

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$playerId]
        );

        // Même hygiène transactionnelle que takeInstance() : la séquence de
        // DELETE est tout-ou-rien, pas de démontage à moitié fait.
        $conn->transactional(function ($conn) use ($playerId): void {
            $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$playerId]);
            self::deleteEntityRows($conn, $playerId);
        });

        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        // refresh_players_svg ne balaie que les lignes ENCORE présentes :
        // purger explicitement les caches du bâtiment supprimé, sinon un id
        // recyclé ressert le vieux SVG.
        self::purgeEntityCaches($playerId);

        $this->addAuditLog("BuildingService::remove #{$playerId}");

        return true;
    }
}
