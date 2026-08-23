<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les endpoints Tiled chargent la même configuration qu'une page du jeu.
 *
 * `_common.php` chargeait bootstrap.php et functions.php, mais pas
 * constants.php. `getNextEntityId()` (functions.php) y lit ENTITY_ID_RANGES
 * sans garde : poser un bâtiment depuis Tiled levait « Undefined constant »
 * et le push échouait entièrement.
 *
 * Les tests ne l'ont pas vu parce qu'ils amorcent eux-mêmes constants.php :
 * leur amorçage était plus complet que celui de l'endpoint. D'où un test qui
 * regarde l'endpoint plutôt que le service.
 */
class TiledEndpointBootstrapTest extends TestCase
{
    private const COMMON = __DIR__ . '/../../api/admin/map/_common.php';

    /**
     * Fichiers de configuration qu'une page charge (config.php), hors
     * optionnels et hors ceux que bootstrap.php tire lui-même.
     *
     * @return array<string, array{string}>
     */
    public static function configFiles(): array
    {
        return [
            'constants.php' => ['constants.php'],
            'bootstrap.php' => ['bootstrap.php'],
            'functions.php' => ['functions.php'],
        ];
    }

    #[DataProvider('configFiles')]
    public function testTheSharedBaseLoadsTheSameConfigAsAPage(string $file): void
    {
        $this->assertStringContainsString(
            "config/" . $file,
            (string) file_get_contents(self::COMMON),
            '_common.php doit charger config/' . $file . ', comme config.php'
        );
    }

    /**
     * Le lien entre les deux : si ENTITY_ID_RANGES déménage, ce test tombe et
     * rappelle que l'endpoint doit suivre son nouveau domicile.
     */
    public function testTheEntityRangesLiveInTheFileTheEndpointLoads(): void
    {
        $this->assertStringContainsString(
            "define('ENTITY_ID_RANGES'",
            (string) file_get_contents(__DIR__ . '/../../config/constants.php'),
            'ENTITY_ID_RANGES doit rester dans constants.php, que _common.php charge'
        );
    }
}
