<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les trois maisons à dialogue — échoppe, banque, école de guerre.
 *
 * Ce que portaient des PNJ à option (isMerchant, isTrainer) devient des
 * BÂTIMENTS : trois types (races, édifices verrouillables — fermés, ils
 * ne servent personne, règle de fermeture unique) et leurs trois
 * dialogues de comptoir (catalogue `dialogs`, type 'building'). Chaque
 * dialogue mène aux écrans existants — merchant.php pour les étals et
 * les coffres de la banque, warschool.php pour les six disciplines ;
 * TARGET_ID est remplacé au rendu par l'exemplaire qui parle.
 *
 * Le dialogue porte le même nom que le type — et le rôle : un bâtiment
 * est marchand ou entraîneur parce que son dialogue mène à l'écran
 * (DialogService::opensScreen), plus par aucune option de personne.
 * Les options isMerchant / isTrainer quittent le jeu, et le suiveur
 * « marchand » (la mule du PNJ) avec elles.
 *
 * Pas de sprite : repli initiales (BuildingService), l'admin attachera
 * un visuel plus tard. Pas d'objet constructible non plus — ces maisons
 * se posent à la main, elles ne sortent pas d'un sac.
 */
final class Version20260806150000_TradeHallsEnterTheWorld extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Les types de bâtiment 'echoppe', 'banque', 'ecole_guerre' et leurs dialogues de comptoir";
    }

    /** @var array<string, array{code: string, label: string, description: string, pv: int}> */
    private const TYPES = [
        'echoppe' => [
            'code' => 'ECHOPPE',
            'label' => 'Échoppe',
            'description' => 'Des étals sous un toit : tout s\'y achète, tout s\'y vend, le reste s\'y échange.',
            'pv' => 150,
        ],
        'banque' => [
            'code' => 'BANQUE',
            'label' => 'Banque',
            'description' => 'Des murs épais et de bons coffres : les biens y dorment, l\'or y fructifie.',
            'pv' => 200,
        ],
        'ecole_guerre' => [
            'code' => 'ECOLE_GUERRE',
            'label' => 'École de guerre',
            'description' => 'Une cour, des mannequins de paille et des maîtres d\'armes : les techniques s\'apprennent ici.',
            'pv' => 150,
        ],
    ];

    /**
     * Même patron que auberge_olympienne : 2x2, l'ancre au sud-ouest, les
     * deux cases arrière bloquent, les deux cases avant portent le comptoir.
     */
    private const FOOTPRINT = [
        'w' => 2,
        'h' => 2,
        'offsets' => '[[0,0],[1,0],[0,-1],[1,-1]]',
        'roles' => '{"2":"block","3":"block"}',
    ];

    public function up(Schema $schema): void
    {
        foreach (self::TYPES as $name => $type) {
            $exists = $this->connection->fetchOne('SELECT id FROM races WHERE name = ?', [$name]);
            if ($exists === false) {
                $this->addSql(
                    "INSERT INTO races
                        (code, name, label, description, playable, hidden, kind, structure_nature,
                         bleeds, wound_color, blocks_passage, blocks_projectiles,
                         lockable, opens_the_way, readable_from_afar,
                         bgColor, color, faction, plan, pv)
                     VALUES
                        (?, ?, ?, ?,
                         0, 1, 'structure', 'edifice',
                         '', '#cd7f32', 1, 1,
                         1, 0, 1,
                         '#8b6d43', 'black', '', '', ?)",
                    [$type['code'], $name, $type['label'], $type['description'], $type['pv']]
                );
            }
        }

        foreach (array_keys(self::TYPES) as $name) {
            $exists = $this->connection->fetchOne(
                'SELECT type_name FROM entity_type_footprints WHERE type_name = ?', [$name]
            );
            if ($exists === false) {
                $this->addSql(
                    'INSERT INTO entity_type_footprints (type_name, w, h, offsets, roles)
                     VALUES (?, ?, ?, ?, ?)',
                    [$name, self::FOOTPRINT['w'], self::FOOTPRINT['h'],
                        self::FOOTPRINT['offsets'], self::FOOTPRINT['roles']]
                );
            }
        }

        foreach ($this->dialogs() as $name => $nodes) {
            $exists = $this->connection->fetchOne('SELECT id FROM dialogs WHERE name = ?', [$name]);
            if ($exists === false) {
                $this->addSql(
                    "INSERT INTO dialogs (name, npc_name, type, custom, dialog_data, is_active)
                     VALUES (?, 'TARGET_NAME', 'building', '', ?, 1)",
                    [$name, json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
                );
            }
        }

        // Les rôles de comptoir quittent les personnes : plus aucune ligne
        // isMerchant / isTrainer, et la mule du marchand (suiveur) part
        // avec — le dialogue du bâtiment fait foi désormais.
        $this->addSql("DELETE FROM players_followers WHERE name = 'marchand'");
        $this->addSql("DELETE FROM players_options WHERE name IN ('isMerchant', 'isTrainer')");
    }

    public function down(Schema $schema): void
    {
        // Un dialogue encore porté par un bâtiment reste (même garde que
        // DialogService::deleteGameDialog) ; le type reste si le monde en a.
        foreach (array_keys(self::TYPES) as $name) {
            $this->addSql(
                'DELETE FROM dialogs WHERE name = ?
                 AND NOT EXISTS (SELECT 1 FROM buildings b WHERE b.dialog = ?)',
                [$name, $name]
            );
            $this->addSql(
                'DELETE FROM entity_type_footprints WHERE type_name = ?
                 AND NOT EXISTS (SELECT 1 FROM players p WHERE p.race = ?)',
                [$name, $name]
            );
            $this->addSql(
                'DELETE FROM races WHERE name = ?
                 AND NOT EXISTS (SELECT 1 FROM players p WHERE p.race = ?)',
                [$name, $name]
            );
        }
    }

    /**
     * Les nœuds des trois dialogues — même palette que le marchand
     * historique : acheter #f39c12, vendre #3498db, échanger #1ba377.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function dialogs(): array
    {
        return [
            'echoppe' => [
                [
                    'id' => 'bonjour',
                    'text' => 'Bienvenue à l\'échoppe, PLAYER_NAME ! Regardez les étals : tout s\'achète, tout se vend — et le reste s\'échange.',
                    'options' => [
                        ['go' => 'acheter', 'text' => 'Je souhaite <font color=\'#f39c12\'>acheter</font> un objet.'],
                        ['go' => 'vendre', 'text' => 'Je souhaite <font color=\'#3498db\'>vendre</font> un objet.'],
                        ['go' => 'echange', 'text' => 'Je souhaite <font color=\'#1ba377\'>envoyer ou recevoir</font> un objet.'],
                    ],
                ],
                [
                    'id' => 'acheter',
                    'text' => 'Pour <font color=\'#f39c12\'>acheter</font> un objet, regardez les offres de Vente.',
                    'options' => [
                        ['url' => 'merchant.php?targetId=TARGET_ID&bids', 'text' => '[voir les offres de Vente]'],
                        ['go' => 'bonjour', 'text' => '[Retour]'],
                    ],
                ],
                [
                    'id' => 'vendre',
                    'text' => 'Pour <font color=\'#3498db\'>vendre</font> un objet, jetez un œil aux demandes d\'Achat.',
                    'options' => [
                        ['url' => 'merchant.php?targetId=TARGET_ID&asks', 'text' => '[voir les demandes d\'Achat]'],
                        ['go' => 'bonjour', 'text' => '[Retour]'],
                    ],
                ],
                [
                    'id' => 'echange',
                    'text' => 'Pour <font color=\'#1ba377\'>envoyer ou recevoir</font> un objet, jetez un œil aux échanges.',
                    'options' => [
                        ['url' => 'merchant.php?targetId=TARGET_ID&exchanges', 'text' => '[voir les échanges]'],
                        ['go' => 'bonjour', 'text' => '[Retour]'],
                    ],
                ],
            ],
            'banque' => [
                [
                    'id' => 'bonjour',
                    'text' => 'Bienvenue à la banque, PLAYER_NAME. Vos biens dorment ici en sécurité — et l\'or y fructifie un peu chaque jour.',
                    'options' => [
                        ['url' => 'merchant.php?targetId=TARGET_ID&bank', 'text' => 'Je veux <font color=\'#f39c12\'>accéder</font> à mon coffre.'],
                        ['go' => 'interets', 'text' => 'Comment fructifie mon or ?'],
                    ],
                ],
                [
                    'id' => 'interets',
                    'text' => 'Chaque jour à l\'aube, l\'or déposé dans nos coffres prend un peu de valeur. Déposez, patientez, revenez : la banque travaille pour vous.',
                    'options' => [
                        ['go' => 'bonjour', 'text' => '[Retour]'],
                    ],
                ],
            ],
            'ecole_guerre' => [
                [
                    'id' => 'bonjour',
                    'text' => 'Bienvenue à l\'école de guerre, PLAYER_NAME. Ici s\'apprennent les techniques et les sorts des combattants. Que voulez-vous travailler ?',
                    'options' => [
                        ['url' => 'warschool.php?targetId=TARGET_ID&melee', 'text' => 'La <font color=\'#c0392b\'>Mêlée</font>.'],
                        ['url' => 'warschool.php?targetId=TARGET_ID&distance', 'text' => 'Le combat à <font color=\'#f39c12\'>Distance</font>.'],
                        ['url' => 'warschool.php?targetId=TARGET_ID&magic', 'text' => 'La <font color=\'#8e44ad\'>Magie</font>.'],
                        ['url' => 'warschool.php?targetId=TARGET_ID&spells', 'text' => 'Les <font color=\'#2980b9\'>Sorts</font>.'],
                        ['url' => 'warschool.php?targetId=TARGET_ID&stealth', 'text' => 'La <font color=\'#7f8c8d\'>Furtivité</font>.'],
                        ['url' => 'warschool.php?targetId=TARGET_ID&survival', 'text' => 'La <font color=\'#27ae60\'>Survie</font>.'],
                    ],
                ],
            ],
        ];
    }
}
