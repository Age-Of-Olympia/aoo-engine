<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Si `map_resources` peut partir, et ce qui la retient encore.
 *
 * Jumelle de {@see MapForegroundsRetirement}, pour la table dont le chantier
 * des ressources a fini de détacher tout le monde : plus un lecteur, plus un
 * écrivain, zéro ligne. Reste à la déposer — elle, et la vue de compatibilité
 * `map_walls` qui la regarde.
 *
 * Calculée, pas retenue de mémoire. Un « à supprimer plus tard » vit mal dans
 * une conversation ou un carnet : il vit ici, s'affiche au tableau de bord tant
 * que l'objet existe, et **disparaît tout seul le jour où il est déposé** —
 * sans qu'il faille penser à retirer l'avertissement.
 *
 * Ne pas confondre vide et déposable : la table peut être vide sans que le code
 * qui a cessé de la lire soit déployé partout. Le dépôt se fait APRÈS ce
 * déploiement (invariant migrations-avant-code), donc l'écran dit « prête »,
 * jamais « fais-le maintenant ».
 */
final class MapResourcesRetirement
{
    private ?Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @return array{
     *     present: bool,
     *     view: bool,
     *     rows: int,
     *     droppable: bool,
     *     blockers: list<string>
     * }
     */
    public function status(): array
    {
        $present = $this->objectExists('map_resources');
        $view = $this->objectExists('map_walls');

        if (!$present && !$view) {
            return ['present' => false, 'view' => false, 'rows' => 0, 'droppable' => false, 'blockers' => []];
        }

        $rows = $present ? (int) $this->conn()->fetchOne('SELECT COUNT(*) FROM map_resources') : 0;

        $blockers = [];

        if ($rows > 0) {
            $blockers[] = $rows . ' ' . ($rows > 1 ? 'lignes restent' : 'ligne reste')
                . ' dans la table : ces objets-là disparaîtraient du plateau';
        }

        return [
            'present'   => $present,
            'view'      => $view,
            'rows'      => $rows,
            'droppable' => $blockers === [],
            'blockers'  => $blockers,
        ];
    }

    /** Table ou vue : l'un comme l'autre est un reste à déposer. */
    private function objectExists(string $name): bool
    {
        return $this->conn()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ?',
            [$name]
        ) > 0;
    }
}
