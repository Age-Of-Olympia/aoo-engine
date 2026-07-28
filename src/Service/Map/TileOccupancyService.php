<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;

/**
 * « Peut-on poser le pas sur cette case ? » — source unique.
 *
 * La règle vivait jusqu'ici dans `go.php`, en trois morceaux qui se
 * refusaient chacun à leur façon : une requête pour les ressources et les
 * entités, un script inclus (`scripts/map/triggers/forbidden.php`) pour les
 * cases interdites, et dans les deux cas un `alert()` suivi d'un `exit()`.
 * Rien de tout cela n'était appelable ailleurs, donc rien n'était testable —
 * le test étalon du blocage a dû laisser `go.php` de côté pour cette raison.
 *
 * # Bloquer, c'est être vu
 *
 * La règle d'entité s'aligne sur celle du RENDU, et c'est ce qui corrige au
 * passage un défaut hérité de la conversion des murs.
 *
 * `go.php` ne construisait sa sous-requête d'entités qu'à l'intérieur d'un
 * `if ($planJson = json()->decode(...))`. Sur les vingt plans dépourvus de
 * fichier JSON, aucune entité ne bloquait donc le pas : on traversait les
 * murs de `praetorium_save`, `praetorium_dark`, `temple`… Le correctif naïf —
 * sortir la sous-requête du `if` — aurait produit l'inverse : le rendu CACHE
 * les joueurs réels de ces plans, on aurait obtenu des murs invisibles.
 *
 * D'où la règle : **une chose bloque le pas si elle bloque ET si celui qui
 * avance peut la voir.** Les structures font partie du décor et sont donc
 * toujours vues ; les personnages suivent la visibilité du plan et leur mode
 * discret.
 *
 * # Une entité tient plusieurs cases
 *
 * L'occupation se lisait sur `players.coords_id` : une entité, une case. Un
 * bâtiment de 2×2 ne bloquait donc qu'un quart de lui-même, et on lui entrait
 * dedans par trois côtés. `entity_cells` dit toutes les cases qu'une entité
 * tient, et c'est elle qu'on interroge désormais.
 *
 * Le changement est sans effet visible tant que chaque entité n'a qu'une
 * ancre — c'est l'état que L3 a laissé, et c'est ce qui rend ce lot sûr : il
 * ouvre la voie sans rien déplacer. Ce qui change vraiment, c'est qu'une
 * emprise posée sera désormais respectée.
 */
final class TileOccupancyService
{
    /**
     * Ce que le rôle d'une case dit du pas, quand il dit quelque chose.
     *
     * `anchor` ne décide rien : c'est la case de référence d'une entité, une
     * position et non une nature. Elle laisse donc le type trancher, comme
     * avant que les emprises n'existent — d'où son absence de cette table.
     *
     * Les autres rôles priment sur le type, et c'est tout leur intérêt : la
     * base d'un décor barre le chemin pendant que sa partie haute se traverse,
     * une porte s'ouvre dans un mur qui, lui, bloque partout ailleurs.
     */
    private const ROLE_VERDICTS = [
        'block' => true,
        'cover' => false,
        'door'  => false,
        'open'  => false,
    ];

    private Connection $conn;
    private RaceService $raceService;

    public function __construct(?Connection $conn = null, ?RaceService $raceService = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->raceService = $raceService ?? new RaceService();
    }

    /**
     * Le motif de refus du pas, ou null si la case est franchissable.
     *
     * @param int      $coordsId  la case visée
     * @param int      $moverId   qui avance (id players ; négatif = PNJ)
     * @param bool     $charactersVisible visibilité des personnages sur ce
     *                 plan — `player_visibility` du JSON, faux quand le plan
     *                 n'a pas de JSON du tout (mêmes conditions que le rendu)
     */
    public function stepRefusal(int $coordsId, int $moverId, bool $charactersVisible): ?string
    {
        return $this->blockedForStep([$coordsId], $moverId, $charactersVisible)[$coordsId] ?? null;
    }

    /**
     * Le même verdict, pour TOUT un champ de vision d'un coup.
     *
     * Le damier a besoin de marquer ses cases bloquées — six cent vingt-cinq
     * pour une vue p=12. Les interroger une par une ferait trois requêtes
     * chacune ; cette forme en fait trois pour l'ensemble.
     *
     * C'est la même règle : stepRefusal() n'est plus qu'un appel à un seul
     * élément. Deux formes, une vérité.
     *
     * @param list<int> $coordsIds
     * @return array<int, string> coords_id => motif, pour les seules cases bloquées
     */
    public function blockedForStep(array $coordsIds, int $moverId, bool $charactersVisible): array
    {
        $coordsIds = array_values(array_unique(array_map('intval', $coordsIds)));
        if ($coordsIds === []) {
            return [];
        }

        $in = implode(',', $coordsIds);
        $blocked = [];

        foreach ($this->conn->fetchFirstColumn(
            "SELECT coords_id FROM map_triggers WHERE name = 'forbidden' AND coords_id IN ({$in})"
        ) as $id) {
            $blocked[(int) $id] = 'Impossible de se rendre à cet endroit.';
        }

        foreach ($this->conn->fetchFirstColumn(
            "SELECT coords_id FROM map_resources WHERE coords_id IN ({$in})"
        ) as $id) {
            $blocked[(int) $id] ??= 'Quelque chose obstrue ton chemin.';
        }

        $passable = $this->raceService->getPassableStructureNames();

        foreach ($this->occupations($in) as $row) {
            if ((int) $row['id'] === $moverId) {
                continue;
            }

            $verdict = self::ROLE_VERDICTS[(string) $row['role']] ?? null;

            if ($verdict === false) {
                continue; /* la case se traverse, quoi que fasse le type */
            }

            if ($verdict === null && in_array((string) $row['race'], $passable, true)) {
                continue;
            }

            $isStructure = in_array($row['player_type'] ?? 'real', ['building', 'unique'], true);

            if (!$isStructure) {
                if ($row['invisible'] !== null || !$charactersVisible) {
                    continue;
                }
            }

            $blocked[(int) $row['coords_id']] ??= 'Quelque chose obstrue ton chemin.';
        }

        return $blocked;
    }

    /**
     * Ce que chaque entité occupe parmi les cases visées, case par case.
     *
     * `entity_cells` dit toutes les cases qu'une entité tient, et non la seule
     * où son ancre est plantée : un bâtiment de 2×2 ne bloquait jusqu'ici
     * qu'un quart de lui-même, et on lui entrait dedans par trois côtés.
     *
     * # Les deux sources s'ajoutent
     *
     * `players.coords_id` reste consulté à côté d'`entity_cells`, et le choix
     * n'est pas timide. Une entité déplacée sans que `syncAnchor()` soit
     * appelé garde ses cases à son ancienne position : la retirer de la
     * seconde source la rendrait traversable là où elle se trouve, ce qui est
     * la pire façon de découvrir une dérive. En s'ajoutant, les deux sources
     * ne peuvent que bloquer davantage, jamais moins — la propriété qui rend
     * ce lot sûr à déployer.
     *
     * `entitycells drift` nomme ce qui a échappé ; le jour où elle ne dit plus
     * rien en production, la seconde branche de l'union s'enlève seule.
     *
     * @param string $in liste de coords_id déjà assainis en entiers
     * @return list<array{id: int|string, coords_id: int|string, role: string, race: string, player_type: ?string, invisible: ?int}>
     */
    private function occupations(string $in): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, occupied.coords_id, occupied.role, p.race, p.player_type,
                    (SELECT 1 FROM players_options o
                      WHERE o.player_id = p.id AND o.name = 'invisibleMode') AS invisible
               FROM (" . self::heldSql($in) . ") AS occupied
               JOIN players p ON p.id = occupied.player_id"
        );

        /* Les deux sources décrivent souvent la même paire (entité, case) —
         * l'ancre y figure des deux côtés. Un rôle explicite l'emporte alors
         * sur `anchor`, qui ne décide rien : sans quoi une case ouverte dans
         * un mur se refermerait par la seule grâce de `players.coords_id`. */
        $occupations = [];

        foreach ($rows as $row) {
            $pair = $row['id'] . ':' . $row['coords_id'];

            if (!isset($occupations[$pair]) || (string) $row['role'] !== 'anchor') {
                $occupations[$pair] = $row;
            }
        }

        return array_values($occupations);
    }

    /**
     * « Une entité tient-elle cette case ? » — la définition, à un seul endroit.
     *
     * Les trois verbes se la posent, et il serait fâcheux qu'ils n'y répondent
     * pas pareil : on bâtirait dans le corps d'un bâtiment dont on ne peut pas
     * franchir la façade.
     *
     * @param string $in liste de coords_id déjà assainis en entiers
     */
    private static function heldSql(string $in): string
    {
        return "SELECT player_id, coords_id, role
                  FROM entity_cells
                 WHERE coords_id IN ({$in})
                 UNION ALL
                SELECT id, coords_id, 'anchor'
                  FROM players
                 WHERE coords_id IN ({$in})";
    }

    /** Une entité — n'importe laquelle, à n'importe quel titre — est-elle là ? */
    private function heldByAnEntity(int $coordsId): bool
    {
        return (bool) $this->conn->fetchOne(
            'SELECT 1 FROM (' . self::heldSql((string) $coordsId) . ') AS held LIMIT 1'
        );
    }

    /**
     * La case est-elle VIDE ? — question des atterrissages.
     *
     * Ce n'est pas celle du pas. Une téléportation, une esquive, un objet qui
     * tombe ou une réapparition cherchent une case où RIEN ne se trouve, pas
     * une case franchissable : on n'atterrit pas sur un téléporteur, même si
     * on peut le traverser à pied.
     *
     * Comportement d'origine conservé au détail près, y compris ses angles :
     * TOUS les déclencheurs comptent — un `grow` ou un `tp` rend la case
     * occupée —, et ni la discrétion ni la visibilité de plan n'entrent en
     * ligne de compte, contrairement au pas.
     */
    public function isVacant(int $coordsId): bool
    {
        if ($this->heldByAnEntity($coordsId)) {
            return false;
        }

        return !(bool) $this->conn->fetchOne(
            'SELECT 1 FROM (
                 SELECT coords_id FROM map_resources WHERE coords_id = :c
                 UNION ALL
                 SELECT coords_id FROM map_triggers  WHERE coords_id = :c
             ) AS occupants LIMIT 1',
            ['c' => $coordsId]
        );
    }

    /**
     * Le motif de refus de CONSTRUCTION, ou null si la case est constructible.
     *
     * Troisième question, troisième réponse : elle compte les éléments —
     * l'eau, la lave, le sang — sauf ceux que le catalogue déclare
     * constructibles par-dessus, et elle ignore les déclencheurs, que les deux
     * autres verbes comptent.
     *
     * Cette dernière divergence n'a pas été décidée, elle tombe d'une absence
     * de filtre : on peut bâtir sur un téléporteur, mais pas y atterrir. Elle
     * est reprise telle quelle ici — l'aligner est un changement de règle, pas
     * une extraction.
     */
    public function buildRefusal(int $coordsId): ?string
    {
        if ($this->heldByAnEntity($coordsId)) {
            return 'Case occupée par une entité.';
        }

        if ($this->hasResource($coordsId)) {
            return 'Case occupée par un mur.';
        }

        $effectService = new \App\Service\EffectService();
        foreach ($this->conn->fetchFirstColumn('SELECT name FROM map_elements WHERE coords_id = ?', [$coordsId]) as $element) {
            if (!$effectService->isBuildableOver((string) $element)) {
                return 'Case occupée par un élément (' . $element . ').';
            }
        }

        return null;
    }

    /**
     * Toute ressource bloque, sans distinction d'état : un filon épuisé barre
     * le passage comme un filon plein. Comportement d'origine, conservé tel
     * quel — le changer relève de l'arbitrage « les plantes sont
     * franchissables », pas de cette extraction.
     */
    private function hasResource(int $coordsId): bool
    {
        return (bool) $this->conn->fetchOne(
            'SELECT 1 FROM map_resources WHERE coords_id = ? LIMIT 1',
            [$coordsId]
        );
    }

    /**
     * Les personnages sont-ils visibles sur ce plan ?
     *
     * Reprend mot pour mot la condition du rendu (`Classes/View.php`) : pas de
     * JSON de plan, ou `player_visibility` explicitement à faux, et les autres
     * personnages disparaissent — donc ne bloquent plus.
     */
    public static function charactersVisibleOn(?object $planJson): bool
    {
        if (!$planJson) {
            return false;
        }

        return !(isset($planJson->player_visibility) && $planJson->player_visibility === false);
    }
}
