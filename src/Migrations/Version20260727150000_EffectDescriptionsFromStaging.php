<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vingt-trois descriptions d'effets, écrites sur staging pendant que le
 * catalogue passait en base ici.
 *
 * Les deux travaux se sont croisés : `Version20260719120000_EffectsFromConstants`
 * a sorti EFFECTS_TXT de `config/constants.php` vers la table `effects` le
 * 19 juillet, en reprenant les quatorze descriptions qui existaient alors ;
 * staging en a ajouté vingt-trois autres le 21, dans le fichier de
 * constantes. La fusion voit donc « ils ajoutent, nous supprimons », et sans
 * ce report les textes seraient perdus — les lignes correspondantes sont bien
 * dans la table, avec leur libellé, mais leur description est vide.
 *
 * Même forme que les quatorze premières : le libellé vit dans `label`, la
 * description ne garde que ce qui suivait le `<br />`.
 *
 * Idempotent : n'écrit que sur les descriptions restées vides, donc une
 * relecture ou une réécriture ultérieure par l'administration des effets ne
 * sera pas écrasée.
 *
 * Le vingt-quatrième texte de staging, `corruption_du_plantes`, n'est pas
 * repris : la migration du 19 juillet a corrigé cette faute de frappe en
 * `corruption_des_plantes`, qui porte déjà la même description.
 */
final class Version20260727150000_EffectDescriptionsFromStaging extends AbstractMigration
{
    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'dexterite'       => 'Augmente votre jet d\'attaque (plus de chances de toucher).',
        'maladresse'      => 'Diminue votre jet d\'attaque (moins de chances de toucher).',
        'protection'      => 'Augmente votre jet de défense (plus difficile à toucher).',
        'vulnerabilite'   => 'Diminue votre jet de défense (plus facile à toucher).',
        'agressivite'     => 'Augmente les dégâts que vous infligez.',
        'faiblesse'       => 'Diminue les dégâts que vous infligez.',
        'armure'          => 'Réduit les dégâts que vous subissez.',
        'fragilite'       => 'Augmente les dégâts que vous subissez.',
        'encaisse'        => 'Absorbe une partie des dégâts subis.',
        'renforcement'    => 'Augmente vos chances de réussir une bousculade.',
        'stabilite'       => 'Augmente votre résistance aux bousculades.',
        'instabilite'     => 'Diminue votre résistance aux bousculades.',
        'ralentissement'  => 'Réduit vos Mouvements.',
        'aveuglement'     => 'Diminue votre Perception.',
        'acuite_visuelle' => 'Augmente votre Perception.',
        'imposture'       => 'Vous n\'apparaissez plus sur la carte générale jusqu\'à votre prochain tour.',
        'leger'           => 'Vous ne laissez pas de traces de pas en vous déplaçant.',
        'feu'             => 'Diminue l\'Endurance de 1.',
        'poison'          => 'Empêche la récupération de PV au prochain tour.',
        'parade'          => 'Pare la prochaine attaque de corps-à-corps.',
        'leurre'          => 'Pare le prochain sort lancé sur vous.',
        'cle_de_bras'     => 'Si vous êtes attaqué au corps-à-corps à mains nues, immobilise l\'assaillant.',
        'pas_de_cote'     => 'Esquive la prochaine attaque physique en vous déplaçant sur une case adjacente.',
    ];

    public function getDescription(): string
    {
        return 'effects.description — carry over the 23 texts written on staging while the catalogue moved to DB';
    }

    public function up(Schema $schema): void
    {
        foreach (self::DESCRIPTIONS as $name => $description) {
            $this->addSql(
                'UPDATE effects
                    SET description = ' . $this->connection->quote($description) . '
                  WHERE name = ' . $this->connection->quote($name) . '
                    AND (description IS NULL OR description = \'\')'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $names = implode(', ', array_map(
            fn (string $name): string => $this->connection->quote($name),
            array_keys(self::DESCRIPTIONS)
        ));

        $this->addSql("UPDATE effects SET description = '' WHERE name IN ({$names})");
    }
}
