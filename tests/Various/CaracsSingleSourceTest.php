<?php

namespace Tests\Various;

use App\Entity\Race;
use App\Enum\Caracs;
use App\Service\ItemStatsSeeder;
use PHPUnit\Framework\TestCase;

/**
 * Épingle la SOURCE UNIQUE des 16 clés de caracs (App\Enum\Caracs) :
 * la constante globale CARACS (libellés UI) et toutes les listes du
 * code doivent en dériver — une clé ajoutée/retirée d'un seul côté
 * doit faire échouer la suite, pas diverger en silence.
 */
class CaracsSingleSourceTest extends TestCase
{
    public function testTheGlobalCaracsConstantMatchesTheSource(): void
    {
        // CARACS vient de tests/bootstrap.php (miroir de config/constants.php).
        $this->assertSame(Caracs::KEYS, array_keys(CARACS));
    }

    public function testRaceColumnsAliasTheSource(): void
    {
        $this->assertSame(Caracs::KEYS, Race::CARAC_KEYS);
    }

    public function testItemScalarKeysEndWithTheSource(): void
    {
        $this->assertSame(
            Caracs::KEYS,
            array_slice(ItemStatsSeeder::SCALAR_KEYS, -count(Caracs::KEYS)),
            'les colonnes de caracs des items dérivent de la source (spread), pas d\'une copie'
        );
    }
}
