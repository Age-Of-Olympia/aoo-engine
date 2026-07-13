<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Canonicalise la casse des noms d'options dans players_options.
 *
 * Des lignes historiques portent une casse divergente (ex. « isadmin »
 * au lieu de « isAdmin » sur des PNJ porteurs de droits, les
 * « oiseaux »). La comparaison SQL (collation _ci) les acceptait ;
 * le cache PHP de PlayerOptionsService compare désormais les clés en
 * PHP, sensible à la casse — les lignes mal casées ne matchent plus
 * et le porteur perd ses droits.
 *
 * Le correctif est dans la donnée : chaque nom connu est réécrit dans
 * sa casse canonique (WHERE name = '<canon>' matche sans casse via la
 * collation ; BINARY <> exclut les lignes déjà correctes).
 */
final class Version20260707120000_CanonicalizeOptionNames extends AbstractMigration
{
    private const CANONICAL_OPTIONS = [
        'isAdmin',
        'isSuperAdmin',
        'isMerchant',
        'isTrainer',
        'showActionDetails',
        'showBlockedTiles',
        'alreadyChanged',
        'alreadyFished',
        'anonymeMode',
        'dlag',
        'doubleUpload',
        'hideGrid',
        'incognitoMode',
        'invisibleMode',
        'newHud',
        'noMask',
        'noPrompt',
        'noTrain',
        'raceHint',
        'raceHintMax',
    ];

    public function getDescription(): string
    {
        return 'Canonicalize players_options.name casing (isadmin → isAdmin, …)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CANONICAL_OPTIONS as $canonical) {
            $this->addSql(
                'UPDATE players_options SET name = :canonical WHERE name = :canonical AND BINARY name <> :canonical',
                ['canonical' => $canonical]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // La casse d'origine des lignes divergentes est perdue — et la
        // casse canonique reste valide pour l'ancien code (comparaison
        // SQL insensible à la casse) : rien à défaire.
        $this->warnIf(true, 'Canonicalisation de casse non réversible (et sans objet).');
    }
}
