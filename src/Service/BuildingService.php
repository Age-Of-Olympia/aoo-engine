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
     * Valeur d'avatar/portrait d'un type sans visuel dédié : vide — le
     * rendu (View.php damier, StructureSheetView) dessine alors le repli
     * « initiales dans un cadre » (View::structureInitialsAvatar).
     */
    public const NO_IMAGE = '';

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
        // Kills et assists référencent players des deux côtés : une entité
        // qui a COMBATTU (assist enregistré sur elle) bloquait le DELETE.
        foreach (['players_logs', 'players_kills', 'players_assists'] as $table) {
            $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ? OR target_id = ?", [$playerId, $playerId]);
        }
        $conn->executeStatement('DELETE FROM players WHERE id = ?', [$playerId]);
    }

    /**
     * A cell whose role is only a drawing order never screens anything.
     *
     * The rest is left to `races.blocks_projectiles`, which already tells a
     * wall from a table — an arch stops the step on its base while arrows
     * pass through its opening.
     */
    private const TRANSPARENT_ROLE = 'cover';

    /**
     * Ligne de tir entre deux points du même plan : les cases traversées
     * (Bresenham, extrémités exclues) et le premier obstacle — toute
     * entité dont la race arrête les projectiles
     * (races.blocks_projectiles : les structures par défaut, un
     * personnage seulement si sa race est cochée — par défaut les tirs
     * passent). Les ressources en font partie depuis leur conversion :
     * arbre, pilier, statue arrêtent la flèche parce que leur type le dit,
     * et non plus parce qu'ils vivaient dans une table à part. Sert au refus
     * du tir (DistanceCompute) et à l'affichage de la trajectoire sur le
     * damier (observe).
     *
     * @return array{tiles: list<array{int,int}>, blocker: ?array{int,int}, blockerName: ?string, blockers: list<array{int,int}>}
     */
    public function lineOfFireReport(object $from, object $to, ?int $targetEntityId = null): array
    {
        $tiles = \App\Action\Combat\LineOfFire::tilesBetween(
            (int) $from->x, (int) $from->y, (int) $to->x, (int) $to->y
        );

        if ($tiles === [] || ($from->plan ?? '') !== ($to->plan ?? '') || (int) ($from->z ?? 0) !== (int) ($to->z ?? 0)) {
            return ['tiles' => $tiles, 'blocker' => null, 'blockerName' => null, 'blockers' => []];
        }

        $conn = $this->entityManager->getConnection();
        $pairs = [];
        $tileParams = [(int) ($from->z ?? 0), (string) $from->plan];
        foreach ($tiles as [$x, $y]) {
            $pairs[] = '(c.x = ? AND c.y = ?)';
            $tileParams[] = $x;
            $tileParams[] = $y;
        }
        $tileFilter = 'c.z = ? AND c.plan = ? AND (' . implode(' OR ', $pairs) . ')';

        $blockersByTile = [];

        $blocking = $this->raceService->getProjectileBlockingRaceNames();
        if ($blocking !== []) {
            // An entity screens every cell it HOLDS, not just the one it
            // stands on: a 2×2 wall used to stop arrows on a quarter of
            // itself. `cover` is excluded — it is a drawing order, so the
            // back of a building must not make whoever stands there
            // unreachable.
            $rows = $conn->fetchAllAssociative(
                'SELECT c.x, c.y, p.name
                 FROM players p
                 JOIN entity_cells ec ON ec.player_id = p.id
                 JOIN coords c ON c.id = ec.coords_id
                 WHERE ' . $tileFilter . '
                   AND ec.role <> ?
                   AND p.race IN (' . implode(',', array_fill(0, count($blocking), '?')) . ')',
                array_merge($tileParams, [self::TRANSPARENT_ROLE], $blocking)
            );
            /* A target does not screen itself. With a single cell the
             * question did not arise — that cell is an endpoint, excluded
             * from the corridor — but a multi-cell object stopped a shot
             * meant for it as soon as a far cell was aimed at. */
            $ownCells = $targetEntityId === null ? [] : $this->cellKeysOf($targetEntityId);

            foreach ($rows as $row) {
                $key = $row['x'] . ',' . $row['y'];

                if (!isset($ownCells[$key])) {
                    $blockersByTile[$key] = (string) $row['name'];
                }
            }
        }

        /* La passe `map_resources` a disparu : ses objets sont des entités,
           et la passe ci-dessus les voit déjà — les 42 types de ressource
           portent `blocks_projectiles`, donc un arbre arrête toujours la
           flèche, par la règle du catalogue au lieu d'une table à part. */

        /* A shot passes if some traversal is CLEAR — the shooter threads
         * through. Asking it per CELL ("is this one on both traversals?")
         * does not compose: each cell of a three-wide base is individually
         * avoidable, no traversal avoids them all, and the shot went through
         * the wall. On exact 1:2 slopes the intersection was even empty, so
         * nothing could block at all. */
        $blockers = [];

        foreach (\App\Action\Combat\LineOfFire::paths(
            (int) $from->x, (int) $from->y, (int) $to->x, (int) $to->y
        ) as $path) {
            $hit = null;

            foreach ($path as $tile) {
                if (isset($blockersByTile[$tile[0] . ',' . $tile[1]])) {
                    $hit = $tile;
                    break;
                }
            }

            /* One clear traversal is enough. */
            if ($hit === null) {
                return ['tiles' => $tiles, 'blocker' => null, 'blockerName' => null, 'blockers' => []];
            }

            $blockers[] = $hit;
        }
        /* Both traversals are barred. Name the one the shooter sees step in
         * FIRST, and "first" is measured along the shot line, not in corridor
         * order — the board draws by projecting onto that line, and departing
         * from it ran the green trace past the first impact. */
        $first = self::nearestAlongTheShot($blockers, $from, $to);

        return [
            'tiles' => $tiles,
            'blocker' => $first,
            'blockerName' => $blockersByTile[$first[0] . ',' . $first[1]],
            'blockers' => $blockers,
        ];
    }

    /**
     * The tiles an entity holds, keyed "x,y", for excluding it from its own
     * line of fire.
     *
     * @return array<string, true>
     */
    private function cellKeysOf(int $entityId): array
    {
        $keys = [];

        foreach ((new \App\Service\Map\EntityCellService($this->entityManager->getConnection()))->cellsOf($entityId) as $cell) {
            $keys[$cell['x'] . ',' . $cell['y']] = true;
        }

        return $keys;
    }

    /**
     * The blocker whose projection on the shot line is closest to the shooter.
     *
     * @param non-empty-list<array{int, int}> $blockers
     * @return array{int, int}
     */
    private static function nearestAlongTheShot(array $blockers, object $from, object $to): array
    {
        $dx = (int) $to->x - (int) $from->x;
        $dy = (int) $to->y - (int) $from->y;

        $along = static fn(array $tile): int =>
            ($tile[0] - (int) $from->x) * $dx + ($tile[1] - (int) $from->y) * $dy;

        $nearest = $blockers[0];

        foreach ($blockers as $tile) {
            if ($along($tile) < $along($nearest)) {
                $nearest = $tile;
            }
        }

        return $nearest;
    }

    /**
     * Sprite of a structure type, in fallback order: dedicated avatar
     * (img/avatars/{type}.webp) → the FIRST image of the type's stock
     * (img/avatars/{type}/, admin → Bâtiments → Images — same thumbnail
     * as the admin lists, one visual everywhere) → the map-wall sprite of
     * the same name (img/walls/{type}.png — a built mur_bois looks like a
     * mur_bois) → the generic placeholder. View.php renders
     * players.avatar directly.
     */
    public static function resolveAvatar(string $type, bool $broken = false): string
    {
        $root = dirname(__DIR__, 2);
        $suffix = $broken ? '_broken' : '';

        $candidates = ['img/avatars/' . $type . $suffix . '.webp'];
        if (!$broken) {
            // Le stock n'a pas de convention _broken : variante cassée
            // servie par les fichiers plats seulement.
            try {
                $stock = (new RaceImageService())->firstImagePath(\App\Enum\ImageType::AVATAR, $type);
            } catch (\RuntimeException) {
                $stock = null; // nom hors canon (PNJ legacy…) : pas de stock
            }
            if ($stock !== null) {
                $candidates[] = $stock;
            }
        }
        $candidates[] = 'img/walls/' . $type . $suffix . '.png';

        foreach ($candidates as $candidate) {
            if (is_file($root . '/' . $candidate)) {
                return $candidate;
            }
        }

        return $broken ? self::resolveAvatar($type) : self::NO_IMAGE;
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
        ?string $name = null,
        bool $overScenery = false
    ): int {
        $race = $this->raceService->getRaceByName($type);
        if ($race === null) {
            throw new \InvalidArgumentException(
                "Type inconnu : '{$type}' (aucune entrée de ce nom au catalogue races)."
            );
        }
        /* La CLASSE tranche, plus la colonne `kind` : un type qui n'est pas une
         * structure ne peut pas être posé, et l'analyse statique le sait — ce
         * qui rend lisible, plus bas, la lecture de son inscription par défaut. */
        if (!$race instanceof \App\Entity\StructureType) {
            throw new \InvalidArgumentException(
                "'{$type}' n'est pas un type de structure — une race de personnage ne peut pas être posée en bâtiment."
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
        $conn->transactional(function ($conn) use ($id, $displayId, $name, $race, $type, $avatar, $coordsId, $ownerId, $faction, $goCoords, $overScenery): void {
            /* Le verrou reste ICI : c'est lui qui resserre la fenêtre entre
             * deux poses concurrentes, et il doit vivre dans la transaction.
             * La RÈGLE, elle, est partie dans TileOccupancyService avec les
             * deux autres questions d'occupation. */
            $conn->fetchOne('SELECT id FROM players WHERE coords_id = ? FOR UPDATE', [$coordsId]);

            $refusal = (new \App\Service\Map\TileOccupancyService($conn))->buildRefusal((int) $coordsId, $overScenery);
            if ($refusal !== null) {
                throw new \InvalidArgumentException(
                    "Case ({$goCoords->x}, {$goCoords->y}, {$goCoords->plan}) : " . lcfirst($refusal)
                );
            }

            $conn->executeStatement(
                /* L'inscription vient de la NATURE de l'objet
                 * (races.default_text), vide par défaut : un bâtiment
                 * neuf n'a rien d'écrit dessus. Il faut le poser
                 * explicitement, la colonne ayant pour défaut « Je suis
                 * nouveau, frappez-moi! » — qui a du sens pour un
                 * personnage naissant et aucun pour un mur. */
                'INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait, coords_id, slot, nextTurnTime, registerTime, text)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
                [
                    $id,
                    'building',
                    $displayId,
                    $name ?? $race->getLabel(),
                    $type,
                    $avatar,
                    $avatar,
                    $coordsId,
                    \App\Service\Map\EntityLocationService::SLOT_INSTALLED,
                    time(),
                    $race->getDefaultText(),
                ]
            );

            /* The structure's cells: its origin, plus whatever its type's
             * cut-out adds around it. A type without one holds a single cell. */
            (new \App\Service\Map\EntityCellService($conn))->syncCells((int) $id);

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
     * Défaut de `players.text` : tant qu'il vaut ça, l'entité n'a rien
     * à dire. C'est la valeur posée par le schéma à la création, et les
     * 13 549 bâtiments de l'expérimental la portaient tous — la place
     * était donc libre pour y écrire les inscriptions.
     */
    public const DEFAULT_TEXT = 'Je suis nouveau, frappez-moi!';

    /**
     * Ce qu'on annonce quand il y a quelque chose à lire mais qu'on est
     * trop loin. Dire qu'il y a un texte ne suffit pas : il faut dire
     * quoi faire, sinon le joueur reste devant une phrase qui constate
     * sans indiquer. Une seule formulation, partagée par la carte de la
     * case et la fiche — deux endroits qui doivent dire la même chose.
     */
    public const OUT_OF_REACH_NOTICE = 'Quelque chose est inscrit ici. Vous devez vous approcher pour lire.';

    /**
     * L'inscription de cet objet se lit-elle d'où l'on est ?
     *
     * La portée tient de la NATURE (races.readable_from_afar) ; un
     * exemplaire peut y déroger, et c'est ce que porte le drapeau
     * nullable du bâtiment. NULL veut dire « comme sa nature », pas
     * « non » — sans quoi changer le défaut d'un type ne rattraperait
     * jamais ce qui est déjà posé.
     */
    public static function readsFromAfar(\Classes\Player $entity, ?BuildingDetails $details): bool
    {
        $exception = $details?->isReadableFromAfar();
        if ($exception !== null) {
            return $exception;
        }

        $race = (new RaceService())->getRaceByName((string) ($entity->data->race ?? ''));

        /* Seule une chose posée porte une inscription : un peuple n'en a pas,
         * et la question ne se pose plus pour lui. */
        return $race instanceof \App\Entity\StructureType && $race->isReadableFromAfar();
    }

    /**
     * Ce qui est écrit sur l'objet, ou '' s'il n'a rien à dire.
     *
     * C'est le MDJ (`players.text`), pas un champ de plus : un
     * personnage y met son message du jour, une pancarte ce qui est
     * gravé dessus. Même colonne, même emplacement dans la fiche.
     */
    public static function inscriptionOf(\Classes\Player $entity): string
    {
        $text = trim((string) ($entity->data->text ?? ''));

        return ($text === '' || $text === self::DEFAULT_TEXT) ? '' : $text;
    }

    /**
     * Exception d'un exemplaire sur la portée de sa nature. null =
     * aucune exception, il suit son type.
     */
    public function setReadableFromAfar(int $playerId, ?bool $readable): void
    {
        $details = $this->getDetails($playerId);
        if ($details === null) {
            throw new \InvalidArgumentException("#{$playerId} n'est pas un bâtiment.");
        }

        $details->setReadableFromAfar($readable);
        $this->entityManager->flush();

        $this->addAuditLog('BuildingService::setReadableFromAfar #' . $playerId . ' '
            . ($readable === null ? 'comme sa nature' : ($readable ? 'oui' : 'non')));
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
                    b.readable_from_afar,
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
                /* null = suit son type ; le rendu doit pouvoir faire la
                 * différence avec un « non » explicite. */
                'readable_from_afar' => $row['readable_from_afar'] !== null
                    ? (bool) $row['readable_from_afar']
                    : null,
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
     * Aligne le sprite d'un bâtiment sur son état de blessure : _broken
     * sous la moitié des PV, sprite de base au-dessus — la bascule
     * visuelle des murs de carte (destroy.php), portée aux entités.
     * Appelée à chaque putBonus pv d'un bâtiment : no-op tant que le
     * sprite affiché est déjà le bon.
     */
    public function refreshWoundSprite(int $playerId): void
    {
        $conn = $this->entityManager->getConnection();

        // Pas de jointure races en SQL : les deux tables n'ont pas la même
        // collation (utf8mb4_general_ci × uca1400) — le catalogue se lit
        // par RaceService, comme partout.
        $row = $conn->fetchAssociative(
            "SELECT p.race, p.avatar, COALESCE(b.n, 0) AS wound
             FROM players p
             LEFT JOIN players_bonus b ON b.player_id = p.id AND b.name = 'pv'
             WHERE p.id = ? AND p.player_type IN ('building', 'scenery')",
            [$playerId]
        );
        if ($row === false) {
            return;
        }

        $maxPv = (int) ($this->raceService->getRaceByName((string) $row['race'])?->getCaracs()['pv'] ?? 0);
        if ($maxPv <= 0) {
            return;
        }

        $remaining = $maxPv + (int) $row['wound'];
        $broken = $remaining <= $maxPv / 2;

        if (self::resolveAvatar((string) $row['race'], $broken) === (string) $row['avatar']) {
            return;
        }

        $this->swapAvatar($playerId, $broken);
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
     * Mort d'un bâtiment : il DISPARAÎT du plateau — pas d'enfers, c'est
     * le chemin des personnages. Satellite et composants supprimés, la
     * ligne players remisée NULLE PART. Chemin de mort seulement — le
     * retrait administratif complet est remove().
     *
     * Elle SURVIT pour que les événements restent vrais (les FK de
     * players_logs pointent toujours une ligne réelle) et pour que l'id ne
     * soit jamais recyclé (getNextEntityId lit MAX(id)).
     *
     * Elle ne part plus sur un plan `limbes_batiments` inventé pour
     * l'occasion : « hors du plateau » se dit maintenant, au lieu de se
     * mimer par une case où personne ne va. Les requêtes de plateau ne la
     * voyaient pas parce qu'elles filtraient un plan ; elles ne la voient
     * plus parce qu'elle n'est nulle part.
     */
    public function vanish(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $playerType = $conn->fetchOne('SELECT player_type FROM players WHERE id = ?', [$playerId]);

        /* Every structure takes this path, scenery included: the `players`
         * row is SHELVED, not deleted, so the events naming it stay true. */
        if ($playerType === false
            || \App\Enum\EntityCategory::fromPlayerType((string) $playerType) !== \App\Enum\EntityCategory::Structure) {
            return false;
        }

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$playerId]
        );

        $conn->transactional(function ($conn) use ($playerId): void {
            $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$playerId]);
            foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$playerId]);
            }

            /* Leftover piece rows go with it: a shelved decor must not stay
             * on screen. Read through the cells, so this runs BEFORE they are
             * dropped. */
            $conn->executeStatement(
                'DELETE m FROM map_foregrounds m
                   JOIN entity_cells ec ON ec.coords_id = m.coords_id
                  WHERE ec.player_id = ?',
                [$playerId]
            );

            /* Shelving drops the cells with the location: what is nowhere
             * occupies nothing. Le faire ici, dans la transaction, plutôt que
             * de laisser le service rouvrir sa propre connexion. */
            (new \App\Service\Map\EntityLocationService($conn))->shelve((int) $playerId);
        });

        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        // refresh_players_svg ne balaie que la case désormais vide : les
        // caches par-entité du bâtiment disparu se purgent explicitement.
        self::purgeEntityCaches($playerId);

        $this->addAuditLog("BuildingService::vanish #{$playerId}");

        return true;
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
