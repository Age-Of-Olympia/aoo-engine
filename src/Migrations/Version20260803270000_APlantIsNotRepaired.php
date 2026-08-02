<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qui se répare, et ce qui ne se répare pas.
 *
 * `reparer` est né avec `TargetType{allowed:['structure']}` — la BRANCHE. Tout
 * ce qui n'est pas un personnage y vit : bâtiments, décors, mais aussi filons
 * et plantes. On remettait donc un arbre en état à coups de marteau et de
 * planches, au meilleur rapport XP du jeu (3 XP par point d'action).
 *
 * L'autre moitié de la règle avait pourtant été resserrée en son temps
 * (`RequiresDamagedTarget` : quelque chose doit être entamé, et pas brisé) ;
 * ce qu'aucune condition ne disait, c'est QUOI se répare.
 *
 * La visée nomme désormais les familles. Un décor se répare — une statue
 * ébréchée se retaille ; une ressource et une plante, non : un filon
 * s'épuise et repousse, il ne se répare pas.
 *
 * L'attaque, elle, garde `['character','structure']` : abattre un arbre est
 * voulu, c'est le sens INVERSE qui n'avait rien à faire là.
 *
 * Fenêtre de déploiement : entre cette migration et le code qui lit les
 * familles, l'ancien code ne reconnaît plus `building` et refuse `reparer`
 * partout. Une action momentanément bloquée, jamais une donnée fausse — et
 * l'ordre inverse (code d'abord) aurait laissé la plante réparable.
 */
final class Version20260803270000_APlantIsNotRepaired extends AbstractMigration
{
    private const REPAIRABLE = '{"allowed":["building","scenery","item"]}';

    public function getDescription(): string
    {
        return "reparer vise les familles réparables, plus la branche entière";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = ?
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'",
            [self::REPAIRABLE]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{\"allowed\":[\"structure\"]}'
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'"
        );
    }
}
