<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les ressources de l'arène du tutoriel ne s'épuisent jamais.
 *
 * Le rendement par défaut du TYPE porte une chance d'épuisement — un
 * fouiller sur l'arbre du tutoriel pouvait le tuer AVANT l'étape de
 * récolte (« Cet arbre est récoltable ! » … pastille « Épuisé »), et un
 * arbre mort ne repousse pas dans une instance sans tours : l'étape 18
 * devenait infranchissable.
 *
 * Le plan modèle reçoit donc ses surcharges `race_harvest` : rendement
 * explicite (bois / pierre, indépendant des défauts de type du monde) et
 * `exhaust = 0` — jamais épuisé. TutorialMapInstance recopie ces
 * surcharges sur chaque plan d'instance à la création.
 */
final class Version20260820162000_TheArenaNeverRunsDry extends AbstractMigration
{
    /** type => item récolté (les biomes de tutorial.json) */
    private const YIELDS = [
        'arbre1'  => 'bois',
        'arbre2'  => 'bois',
        'pierre1' => 'pierre',
        'pierre2' => 'pierre',
    ];

    public function getDescription(): string
    {
        return "race_harvest: surcharges du plan tutorial — rendement explicite, jamais épuisé";
    }

    public function up(Schema $schema): void
    {
        foreach (self::YIELDS as $race => $item) {
            $this->addSql(
                "INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow)
                 SELECT 'tutorial', r.id, ?, 0, NULL
                   FROM races r
                  WHERE r.name = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM race_harvest h
                         WHERE h.plan = 'tutorial' AND h.race_id = r.id
                    )",
                [$item, $race]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE h FROM race_harvest h
               JOIN races r ON r.id = h.race_id
              WHERE h.plan = 'tutorial'
                AND r.name IN ('arbre1', 'arbre2', 'pierre1', 'pierre2')"
        );
    }
}
