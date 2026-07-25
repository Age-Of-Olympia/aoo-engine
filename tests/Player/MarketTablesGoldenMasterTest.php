<?php

namespace Tests\Player;

use Classes\Market;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Market::get() sert DEUX tables qui n'ont pas les mêmes colonnes.
 *
 * L'état d'un exemplaire ne concerne que les offres de vente : seules
 * elles séquestrent un objet individualisé, et seule items_bids porte
 * instance_id. Une demande d'achat n'entiercit que de l'or et vise un
 * objet de catalogue. Joindre item_instances pour les deux faisait
 * planter toute la page des demandes sur « Unknown column
 * o.instance_id » — d'où ce test, qui lit simplement les deux.
 */
#[Group('items-golden-master')]
class MarketTablesGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    public function testBothMarketTablesCanBeRead(): void
    {
        $market = new Market(null);

        foreach (['asks', 'bids'] as $table) {
            $rows = $market->get($table);
            $this->assertIsArray($rows, "la table {$table} se lit sans erreur SQL");
        }
    }

    public function testOnlySaleOffersCarryInstanceState(): void
    {
        $bois = $this->itemOrSkip('bois');
        $seller = $this->createRealPlayer('GmSeller');

        $this->link->executeStatement(
            'INSERT INTO items_asks (item_id, player_id, price, n, stock) VALUES (?, ?, 10, 1, 1)',
            [$bois->id, $seller->id]
        );
        $this->link->executeStatement(
            'INSERT INTO items_bids (item_id, player_id, price, n, stock) VALUES (?, ?, 10, 1, 1)',
            [$bois->id, $seller->id]
        );

        try {
            $asks = (new Market(null))->get('asks');
            $bids = (new Market(null))->get('bids');

            $this->assertArrayHasKey($bois->id, $asks);
            $this->assertArrayHasKey($bois->id, $bids);

            $askRow = $asks[$bois->id][0];
            $bidRow = $bids[$bois->id][0];

            $this->assertObjectNotHasProperty('durability', $askRow, 'une demande ne porte pas d\'état');
            $this->assertObjectHasProperty('durability', $bidRow, 'une offre de vente le porte');
        } finally {
            $this->link->executeStatement(
                'DELETE FROM items_asks WHERE player_id = ?',
                [$seller->id]
            );
            $this->link->executeStatement(
                'DELETE FROM items_bids WHERE player_id = ?',
                [$seller->id]
            );
        }
    }
}
