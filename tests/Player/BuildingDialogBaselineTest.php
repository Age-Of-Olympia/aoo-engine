<?php

namespace Tests\Player;

use App\Service\BuildingService;
use App\Service\DialogService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Dialogue porté par un bâtiment (buildings.dialog) : le lien vit sur
 * l'ENTITÉ — attaché/détaché via BuildingService::setDialog, validé
 * contre le catalogue `dialogs`, et protégé par le garde de suppression
 * de DialogService (même règle que les déclencheurs map_dialogs).
 *
 * Skips propres quand les migrations structures/dialogues n'ont pas
 * tourné — même convention que BuildingVitalsBaselineTest.
 */
#[Group('entities-baseline')]
#[Group('entities-structure')]
class BuildingDialogBaselineTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'palissade';
    private const DIALOG = 'test_dialogue_batiment';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT dialog FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings.dialog unavailable (run migrations): ' . $e->getMessage());
        }

        // Dialogue jetable du catalogue — un nœud minimal valide.
        (new DialogService())->saveGameDialog(
            self::DIALOG,
            [['id' => 'bonjour', 'text' => 'Halte !', 'options' => [['go' => 'EXIT', 'text' => 'Partir']]]],
            ['npc_name' => 'Gardien de test']
        );
    }

    protected function tearDown(): void
    {
        try {
            $this->link->executeStatement('DELETE FROM dialogs WHERE name = ?', [self::DIALOG]);
        } catch (\Throwable $e) {
            // table absente : le setUp a déjà skippé
        }

        parent::tearDown();
    }

    private function placePalissade(): int
    {
        [$x, $y] = $this->farTile();

        return $this->placeStructure(self::TYPE, $x, $y);
    }

    public function testSetDialogAttachesValidatesAndDetaches(): void
    {
        $service = new BuildingService();
        $id = $this->placePalissade();

        // Posé sans dialogue.
        $this->assertSame('', $service->getDetails($id)?->getDialog());

        // Attache : persiste sur la ligne satellite.
        $service->setDialog($id, self::DIALOG);
        $this->assertSame(
            self::DIALOG,
            (string) $this->link->fetchOne('SELECT dialog FROM buildings WHERE player_id = ?', [$id])
        );

        // Détache avec ''.
        $service->setDialog($id, '');
        $this->assertSame(
            '',
            (string) $this->link->fetchOne('SELECT dialog FROM buildings WHERE player_id = ?', [$id])
        );
    }

    public function testSetDialogRejectsUnknownDialogAndNonBuilding(): void
    {
        $service = new BuildingService();
        $id = $this->placePalissade();

        try {
            $service->setDialog($id, 'dialogue_qui_n_existe_pas');
            $this->fail('un dialogue hors catalogue doit être rejeté');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('inconnu', $e->getMessage());
        }

        $character = $this->createRealPlayer('bdlg');
        try {
            $service->setDialog((int) $character->id, self::DIALOG);
            $this->fail('un personnage ne porte pas de dialogue de bâtiment');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('bâtiment', $e->getMessage());
        }
    }

    public function testDialogDeleteGuardCountsCarryingBuildings(): void
    {
        $buildingService = new BuildingService();
        $dialogService = new DialogService();
        $id = $this->placePalissade();

        $buildingService->setDialog($id, self::DIALOG);
        $this->assertSame(1, $dialogService->countBuildingDialogReferences(self::DIALOG));

        try {
            $dialogService->deleteGameDialog(self::DIALOG);
            $this->fail('un dialogue porté par un bâtiment ne doit pas être supprimable');
        } catch (\RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertStringContainsString('bâtiment', $e->getMessage());
        }

        // Détaché, la suppression passe.
        $buildingService->setDialog($id, '');
        $dialogService->deleteGameDialog(self::DIALOG);
        $this->assertFalse($dialogService->gameDialogExists(self::DIALOG));
    }
}
