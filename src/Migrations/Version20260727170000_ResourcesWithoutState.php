<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les ressources sans état deviennent de vraies ressources.
 *
 * `map_resources.damages` dit trois choses : `-1` récoltable, `-2` épuisée,
 * et `0`… rien. Ce zéro est l'ancien « mur à pleine vie » hérité de
 * `map_walls`, resté sur des lignes qui ne sont plus des murs depuis que
 * ceux-ci sont devenus des entités. Une ressource à zéro ne se récolte pas,
 * ne s'épuise pas, ne repousse pas : elle est du décor qui a l'air d'une
 * ressource.
 *
 * Mesuré sur la carte de production du 26 juillet : **2 147 lignes sur
 * 26 656**, soit 8 % de la couche.
 *
 * # Ce qui les distingue, et pourquoi toutes ne se valent pas
 *
 * Elles se partagent en deux, et les données le disent sans ambiguïté :
 *
 * - **2 115 lignes sur 22 familles** ont des voisines récoltables. `pierre1`
 *   compte 695 lignes à zéro pour 4 162 récoltables, `arbre2` en compte 107
 *   pour 1 144. Ce sont les mêmes rochers, les mêmes arbres, posés sans leur
 *   état — des ressources sous-configurées, rien d'autre. Elles passent à
 *   `-1`.
 * - **32 lignes sur 6 familles** n'ont AUCUNE voisine récoltable : `altar`
 *   (22), `altar_broken` (4), `bronze` (3), et trois objets uniques (disque
 *   solaire, statue de la Victoire d'Hermès, flamme originelle). Celles-là ne
 *   sont pas des ressources mal réglées : ce sont des objets rangés dans la
 *   mauvaise couche. Les autels relèvent du lot qui leur est consacré, les
 *   uniques de celui du décor. **On n'y touche pas** — les rendre récoltables
 *   laisserait un joueur miner un autel.
 *
 * Le critère n'est pas une liste de noms figée mais la question posée aux
 * données : « cette famille se récolte-t-elle ailleurs sur la carte ? ». Il
 * s'adapte donc à la base sur laquelle il tourne, et il reste juste si un
 * type de ressource apparaît ou disparaît d'ici le déploiement.
 *
 * Idempotente : une seconde exécution ne trouve plus rien à zéro.
 */
final class Version20260727170000_ResourcesWithoutState extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_resources.damages = 0 → -1 for families that are gathered elsewhere (altars and uniques left alone)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE map_resources r
            JOIN (
                SELECT name
                FROM map_resources
                GROUP BY name
                HAVING SUM(damages = -1) > 0
            ) gathered ON gathered.name = r.name
            SET r.damages = -1
            WHERE r.damages = 0
        ");
    }

    /**
     * Pas de retour en arrière possible, et c'est assumé.
     *
     * Rien ne distingue après coup une ressource passée de 0 à -1 d'une
     * ressource qui était déjà récoltable : les remettre toutes à zéro
     * casserait la carte bien plus sûrement que de les laisser récoltables.
     * Le retour, s'il faut le faire, se fait depuis une sauvegarde.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('SELECT 1');
    }
}
