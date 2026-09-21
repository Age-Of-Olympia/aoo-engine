<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A numbered variant (arbre3, pic_glace2) is one look of one thing: its
 * label is the family's, without the digit. Instances still named after the
 * generated label follow, so the selection card shows « Arbre », not « Arbre3 ».
 *
 * Idempotent: only generated labels (ending with a digit, or « Unique … ») are
 * touched, so a label an admin has already edited (« Tombe ») is kept.
 */
final class Version20260919120000_VariantTypeLabels extends AbstractMigration
{
    private const FAMILIES = [
        'arbre' => 'Arbre',
        'arbre_petrifie' => 'Arbre pétrifié',
        'cocotier' => 'Cocotier',
        'glaise' => 'Glaise',
        'herbe' => 'Herbe',
        'jungle' => 'Arbre de la jungle',
        'pierre' => 'Pierre',
        'pierre_noire' => 'Pierre noire',
        'rocher_desert' => 'Rocher du désert',
        'jarre' => 'Jarre',
        'neige' => 'Neige',
        'pic_glace' => 'Pic de glace',
        'tombe' => 'Tombe',
        'animal_sacre' => 'Animal sacré',
        'debris' => 'Débris',
        'unique_mine' => 'Mine',
        'vache' => 'Vache',
    ];

    /** Unique sceneries: one label each, their generated one reads « Unique xxx ». */
    private const UNIQUES = [
        'unique_arbre_eryn_dolen' => "Arbre d'Eryn Dolen",
        'unique_barge' => 'Barge',
        'unique_bucheron' => 'Bûcheron',
        'unique_campement_redoraan' => 'Campement Redoraan',
        'unique_carriere_pierre' => 'Carrière de pierre',
        'unique_cascade' => 'Cascade',
        'unique_chantier_cuivre' => 'Chantier de cuivre',
        'unique_cimetiere' => 'Cimetière',
        'unique_disque_solaire' => 'Disque solaire',
        'unique_faille_naine' => 'Faille naine',
        'unique_flamme_originelle' => 'Flamme originelle',
        'unique_fort_lutin' => 'Fort lutin',
        'unique_fort_turok' => 'Fort Turok',
        'unique_praetorium' => 'Praetorium',
        'unique_pyramide' => 'Pyramide',
        'unique_salpetriere' => 'Salpêtrière',
        'unique_statue_leyrion' => 'Statue de Leyrion',
        'unique_statue_victoire_hermes' => "Statue de la victoire d'Hermès",
        'unique_taverne' => 'Taverne',
        'unique_temple' => 'Temple',
        'unique_tertre_sauvage' => 'Tertre sauvage',
        'unique_tourbiere' => 'Tourbière',
        'unique_volcan' => 'Volcan',
        'unique_zagnadar' => 'Zagnadar',
    ];

    public function getDescription(): string
    {
        return 'numbered variant types get their family label';
    }

    public function up(Schema $schema): void
    {
        foreach (self::FAMILIES as $family => $label) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT name, label FROM races
                  WHERE name REGEXP ? AND label REGEXP '[0-9]+$'",
                ['^' . $family . '[0-9]+$']
            );

            foreach ($rows as $row) {
                $this->relabel((string) $row['name'], (string) $row['label'], $label);
            }
        }

        foreach (self::UNIQUES as $name => $label) {
            $old = $this->connection->fetchOne(
                "SELECT label FROM races WHERE name = ? AND label LIKE 'Unique %'",
                [$name]
            );

            if ($old !== false) {
                $this->relabel($name, (string) $old, $label);
            }
        }
    }

    private function relabel(string $type, string $oldLabel, string $label): void
    {
        $this->addSql('UPDATE races SET label = ? WHERE name = ?', [$label, $type]);
        $this->addSql('UPDATE players SET name = ? WHERE race = ? AND name = ?', [$label, $type, $oldLabel]);
    }

    public function down(Schema $schema): void
    {
        // Generated labels are not worth restoring.
    }
}
