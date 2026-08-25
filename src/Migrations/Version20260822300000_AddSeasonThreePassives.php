<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de nouveaux passifs pour la saison 3 et mise à jour de textes existants
 */
final class Version20260822300000_AddSeasonThreePassives extends AbstractMigration
{
    private const PASSIVES_DATA = [
        [
            'name'          => 'brute',
            'traits'        => '[]',
            'type'          => 'poussée',
            'carac'         => 'fixed',
            'value'         => 2.00,
            'level'         => 3,
            'category'      => 'melee',
            'prerequisites' => '{"forbidden": ["inebranlable"]}',
            'display_name'  => 'Brute',
            'text'          => 'Bonus de +2 aux jets de Poussée',
        ],
        [
            'name'          => 'musculeux',
            'traits'        => '[]',
            'type'          => 'poussée',
            'carac'         => 'fixed',
            'value'         => 2.00,
            'level'         => 3,
            'category'      => 'distance',
            'prerequisites' => '{"forbidden": ["inebranlable"]}',
            'display_name'  => 'Musculeux',
            'text'          => 'Bonus de +2 aux jets de Poussée',
        ],
        [
            'name'          => 'inebranlable',
            'traits'        => '[]',
            'type'          => 'poussée',
            'carac'         => 'fixed',
            'value'         => 2.00,
            'level'         => 2,
            'category'      => 'survival',
            'prerequisites' => '{"forbidden": ["brute","musculeux"]}',
            'display_name'  => 'Inébranlable',
            'text'          => 'Bonus de +2 aux jets pour résister aux Poussées',
        ],
        [
            'name'          => 'focus_mental',
            'traits'        => '["fm"]',
            'type'          => 'att',
            'carac'         => 'advantage',
            'value'         => 0.00,
            'level'         => 4,
            'category'      => 'magic',
            'prerequisites' => '{"forbidden": ["volonte_fer"]}',
            'display_name'  => 'Focus mental',
            'text'          => 'Gagne Avantage sur la FM en attaquant',
        ],
        [
            'name'          => 'meditation_arcanique',
            'traits'        => '["rm"]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 8.00,
            'level'         => 1,
            'category'      => 'magic',
            'prerequisites' => '{"forbidden": ["meditation_somatique"]}',
            'display_name'  => 'Méditation arcanique',
            'text'          => 'Ajoute RM/8 PM par action dépensée lors d\'un Repos',
        ],
        [
            'name'          => 'meditation_somatique',
            'traits'        => '["r"]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 8.00,
            'level'         => 1,
            'category'      => 'magic',
            'prerequisites' => '{"forbidden": ["meditation_arcanique"]}',
            'display_name'  => 'Méditation somatique',
            'text'          => 'Ajoute R/8 PM par action dépensée lors d\'un Repos',
        ],
        [
            'name'          => 'recuperation_arcanique',
            'traits'        => '["rm"]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 8.00,
            'level'         => 1,
            'category'      => 'survival',
            'prerequisites' => '{"forbidden": ["recuperation_somatique"]}',
            'display_name'  => 'Récupération arcanique',
            'text'          => 'Ajoute RM/8 PV par action dépensée lors d\'un Repos',
        ],
        [
            'name'          => 'recuperation_somatique',
            'traits'        => '["r"]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 8.00,
            'level'         => 1,
            'category'      => 'survival',
            'prerequisites' => '{"forbidden": ["recuperation_arcanique"]}',
            'display_name'  => 'Récupération somatique',
            'text'          => 'Ajoute R/8 PV par action dépensée lors d\'un Repos',
        ],
        [
            'name'          => 'retablissement_rapide',
            'traits'        => '["r"]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 5.00,
            'level'         => 2,
            'category'      => 'survival',
            'prerequisites' => '',
            'display_name'  => 'Rétablissement rapide',
            'text'          => 'Ajoute R/5 Malus par action dépensée lors d\'un Repos',
        ],
        [
            'name'          => 'pickpocket',
            'traits'        => '[]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 2.00,
            'level'         => 2,
            'category'      => 'stealth',
            'prerequisites' => '',
            'display_name'  => 'Pickpocket',
            'text'          => 'Le vol récupère deux fois plus d\'or des poches de la victime',
        ],
        [
            'name'          => 'oeil_percant',
            'traits'        => '[]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 5.00,
            'level'         => 2,
            'category'      => 'stealth',
            'prerequisites' => '',
            'display_name'  => 'Oeil perçant',
            'text'          => 'Le personnage voit à +5 cases sur la carte générale',
        ],
        [
            'name'          => 'oeil_aigle',
            'traits'        => '[]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 5.00,
            'level'         => 3,
            'category'      => 'stealth',
            'prerequisites' => '{"need": ["oeil_percant"]}',
            'display_name'  => 'Oeil d\'aigle',
            'text'          => 'Le personnage voit à +5 cases sur la carte générale',
        ],
        [
            'name'          => 'oeil_ultime',
            'traits'        => '[]',
            'type'          => 'buff',
            'carac'         => '',
            'value'         => 5.00,
            'level'         => 4,
            'category'      => 'stealth',
            'prerequisites' => '{"need": ["oeil_aigle"]}',
            'display_name'  => 'Oeil ultime',
            'text'          => 'Le personnage voit à +5 cases sur la carte générale',
        ],
    ];

    private const UPDATES_DATA = [
        [
            'name'     => 'duelliste',
            'old_text' => 'Gagne Avantage sur la CC',
            'new_text' => 'Gagne Avantage en attaquant avec la CC',
        ],
        [
            'name'     => 'lancer',
            'old_text' => 'Gagne Avantage sur la CT avec les armes de jet',
            'new_text' => 'Gagne Avantage en attaquant avec la CT avec les armes de jet',
        ],
        [
            'name'     => 'tireur_elite',
            'old_text' => 'Gagne Avantage sur la CC',
            'new_text' => 'Gagne Avantage en attaquant avec la CC',
        ],
        [
            'name'     => 'lancer', // TODO: Vérifier s'il ne s'agit pas d'un autre nom (ex: archerie)
            'old_text' => 'Gagne Avantage sur la CT avec les armes à munitions',
            'new_text' => 'Gagne Avantage en attaquant avec la CT avec les armes à munitions',
        ],
    ];

    public function getDescription(): string
    {
        return 'Ajout des compétences passives pour la saison 3 et mise à jour de la description de certains passifs existants.';
    }

    public function up(Schema $schema): void
    {
        // 1. Insertion des nouveaux passifs
        foreach (self::PASSIVES_DATA as $passive) {
            $columns = implode(', ', array_keys($passive));
            $placeholders = implode(', ', array_fill(0, count($passive), '?'));
            
            $this->addSql(
                "INSERT INTO action_passives ($columns) VALUES ($placeholders)",
                array_values($passive)
            );
        }

        // 2. Mise à jour des textes des passifs existants
        foreach (self::UPDATES_DATA as $update) {
            $this->addSql(
                'UPDATE action_passives SET text = ? WHERE name = ?',
                [$update['new_text'], $update['name']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // 2. Annulation des mises à jour (on remet les anciens textes)
        foreach (self::UPDATES_DATA as $update) {
            $this->addSql(
                'UPDATE action_passives SET text = ? WHERE name = ?',
                [$update['old_text'], $update['name']]
            );
        }

        // 1. Suppression des nouveaux passifs insérés
        $names = array_map(fn($p) => "'" . $p['name'] . "'", self::PASSIVES_DATA);
        $inClause = implode(', ', $names);

        $this->addSql("DELETE FROM action_passives WHERE name IN ($inClause)");
    }
}