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
 */
final class TileOccupancyService
{
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

        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, p.coords_id, p.race, p.player_type,
                    (SELECT 1 FROM players_options o
                      WHERE o.player_id = p.id AND o.name = 'invisibleMode') AS invisible
             FROM players p
             WHERE p.coords_id IN ({$in})"
        );

        foreach ($rows as $row) {
            if ((int) $row['id'] === $moverId) {
                continue;
            }

            if (in_array((string) $row['race'], $passable, true)) {
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
        return !(bool) $this->conn->fetchOne(
            'SELECT 1 FROM (
                 SELECT coords_id FROM players       WHERE coords_id = :c
                 UNION ALL
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
        if ($this->conn->fetchOne('SELECT id FROM players WHERE coords_id = ? LIMIT 1', [$coordsId]) !== false) {
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
