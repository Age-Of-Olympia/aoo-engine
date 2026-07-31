<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les clés de jointure LEGACY parlent utf8mb4, comme le catalogue.
 *
 * {@see Version20260727110000_JoinKeyCollations} avait aligné le catalogue sur
 * les données — races, actions, resource_types — en s'appuyant sur une mesure
 * faite en développement : « les couches map_* sont déjà en utf8mb4 ». C'est
 * vrai ici, et faux ailleurs. Un serveur ancien garde des colonnes en latin1,
 * héritées d'une époque où c'était le défaut du serveur.
 *
 * La différence ne se voit pas tant qu'on lit : latin1 est un sous-ensemble,
 * MariaDB élargit tout seul. Elle se voit quand on plaque une collation :
 * `COLLATE utf8mb4_general_ci` sur une colonne latin1 n'est pas un
 * rapprochement, c'est une ERREUR — et le déploiement s'arrête là.
 *
 * On place donc la suite du travail À CÔTÉ de celui qu'elle complète, et non à
 * la fin de la pile : rejouer toute la pile sur une copie de production doit
 * traverser ce point avant les migrations qui joignent, pas après.
 *
 * PRUDENCE — on ne convertit que ce qui est prouvé sûr.
 *
 * Changer le jeu de caractères d'une colonne RÉENCODE ses octets. C'est ce
 * qu'on veut si le contenu est vraiment du latin1 ; c'est une destruction s'il
 * s'agit d'UTF-8 rangé de force dans une colonne latin1 — le défaut classique
 * d'un code ancien qui écrivait sans `SET NAMES`. Rien, dans le schéma, ne
 * distingue les deux cas.
 *
 * Une colonne dont le contenu est purement ASCII, elle, se lit pareil dans les
 * deux hypothèses : la conversion ne peut alors rien abîmer. C'est le test que
 * fait cette migration, colonne par colonne, sur les données de l'environnement
 * où elle tourne. Une colonne accentuée est LAISSÉE en l'état et signalée : le
 * code compare en `CONVERT(... USING utf8mb4)` et n'en dépend pas.
 *
 * utf8mb3 échappe à ce doute, et c'est le cas le plus répandu en production :
 * c'est le même encodage qu'utf8mb4, borné au plan multilingue de base. Les
 * octets sont déjà de l'UTF-8 valide ; les relire en utf8mb4 ne réécrit rien.
 * Le contrôle ASCII ne s'applique donc qu'aux jeux non-UTF-8.
 */
final class Version20260727111500_LegacyJoinKeysSpeakUtf8mb4 extends AbstractMigration
{
    /**
     * Les colonnes que le code compare à une autre table, par leur chaîne.
     *
     * Une table ou une colonne absente est ignorée : la pile se rejoue sur des
     * bases d'âges différents, et toutes n'ont pas les mêmes couches.
     *
     * @var array<string, string[]>
     */
    private const JOIN_KEYS = [
        'players' => ['race'],
        'coords' => ['plan'],
        'items' => ['name'],
        'map_resources' => ['name'],
        'map_foregrounds' => ['name'],
        'map_plants' => ['name'],
        'map_elements' => ['name'],
        'resource_types' => ['name'],
        'players_actions' => ['name'],
    ];

    public function getDescription(): string
    {
        return 'les clés de jointure legacy passent en utf8mb4 quand c est sans risque';
    }

    public function up(Schema $schema): void
    {
        foreach (self::JOIN_KEYS as $table => $columns) {
            foreach ($columns as $column) {
                $this->align($table, $column);
            }
        }
    }

    /**
     * Aligne une colonne, si elle existe, si elle en a besoin, et si son
     * contenu prouve que la conversion est sans perte.
     */
    private function align(string $table, string $column): void
    {
        $definition = $this->connection->fetchAssociative(
            "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        if ($definition === false) {
            return;
        }

        if ($definition['CHARACTER_SET_NAME'] === null || $definition['CHARACTER_SET_NAME'] === 'utf8mb4') {
            return;
        }

        /* utf8mb3 est un SOUS-ENSEMBLE strict d'utf8mb4 : même encodage, borné
         * au plan multilingue de base. Les octets sont déjà de l'UTF-8 valide,
         * la conversion ne peut rien réécrire — accents compris. Le doute qui
         * suit ne concerne que les jeux non-UTF-8, latin1 en tête. */
        $utf8Subset = in_array($definition['CHARACTER_SET_NAME'], ['utf8', 'utf8mb3'], true);

        /* Non-ASCII hors UTF-8 : convertir supposerait connaître l'encodage
         * réel du contenu, ce que le schéma ne dit pas. On laisse, et on le dit. */
        $accented = $utf8Subset ? 0 : (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` <> CONVERT(`{$column}` USING ascii)"
        );

        if ($accented > 0) {
            $this->write(sprintf(
                '  <comment>%s.%s laissée en %s : %d valeur(s) non-ASCII, conversion non prouvée sûre</comment>',
                $table,
                $column,
                $definition['CHARACTER_SET_NAME'],
                $accented
            ));

            return;
        }

        /* La définition est reconstruite telle quelle : seul le jeu de
         * caractères change. Un DEFAULT et la nullabilité perdus ici seraient
         * une régression silencieuse sur une colonne de production. */
        $null = $definition['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';

        /* MariaDB rend COLUMN_DEFAULT sous forme d'EXPRESSION SQL — `''` pour
         * une chaîne vide, apostrophes comprises. La citer à nouveau donnerait
         * pour défaut une paire d'apostrophes littérale. */
        $default = $definition['COLUMN_DEFAULT'] === null
            ? ''
            : ' DEFAULT ' . $definition['COLUMN_DEFAULT'];

        $this->addSql(sprintf(
            'ALTER TABLE `%s` MODIFY COLUMN `%s` %s CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci %s%s',
            $table,
            $column,
            $definition['COLUMN_TYPE'],
            $null,
            $default
        ));
    }

    /**
     * Pas de retour en latin1 : on ne rejoue pas un réencodage à l'envers pour
     * revenir à un défaut de serveur des années 2000. La migration ne retire
     * rien, il n'y a rien à rendre.
     */
    public function down(Schema $schema): void
    {
    }
}
