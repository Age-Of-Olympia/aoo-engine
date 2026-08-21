<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use App\Service\ConstructionSiteService;
use App\Service\ItemInstanceService;
use App\Service\PlayerService;
use App\Service\RaceService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Bank lifecycle (chests & bank work, part 2):
 *
 * - last bank of the plan destroyed: every placed chest becomes public
 *   — no owner, no faction, lid open (nobody can turn a public chest's
 *   lock any more);
 * - while another bank still stands, nothing moves;
 * - a finished bank takes the ownerless chests for its faction, and
 *   only those — personal chests do not move;
 * - a factionless bank takes nothing.
 *
 * Every scene has its own plan: the rule reads the whole plan.
 */
#[Group('items-baseline')]
class BankLifecycleTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBuildingsOrSkip();
    }

    private function scenePlan(): string
    {
        return 'p_banque_' . bin2hex(random_bytes(3));
    }

    /** A chest standing on the plan, with the given owner/faction. */
    private function placeChest(string $plan, ?int $ownerId, string $faction, bool $open = true): int
    {
        [$x, $y] = $this->farTile();
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => $plan]);
        $item = $this->itemOrSkip('coffre_bois');

        $entityId = (new ItemInstanceService())
            ->installFromCatalogAt((int) $item->id, $coordsId, null, $ownerId, $faction);
        $this->trackEntityId($entityId);

        if (!$open) {
            $this->link->executeStatement('UPDATE players SET is_open = 0 WHERE id = ?', [$entityId]);
        }

        return $entityId;
    }

    /** owner_id / faction / is_open of an entity, straight from the base. */
    private function ownershipOf(int $entityId): array
    {
        return $this->link->fetchAssociative(
            'SELECT owner_id, faction, is_open FROM players WHERE id = ?',
            [$entityId]
        );
    }

    /** Smash a building through the real death path. */
    private function smash(int $buildingId): void
    {
        $attacker = $this->createRealPlayer('GmSapeurBanque');
        $attacker->get_data();
        $building = PlayerFactory::legacy($buildingId);
        $building->get_caracs();
        $building->putBonus(['pv' => -1000]);

        ob_start();
        try {
            PlayerService::ProcessTargetDeath($attacker, $building);
        } finally {
            ob_end_clean();
        }
    }

    public function testTheLastBankFallenFreesEveryChestOfThePlan(): void
    {
        $plan = $this->scenePlan();
        $this->sowFaction('gardiens_scene');

        [$bx, $by] = $this->farTile();
        $bankId = $this->placeStructure('banque', $bx, $by, $plan);

        $owner = $this->createRealPlayer('GmProprio');
        $personal = $this->placeChest($plan, (int) $owner->id, '');
        $factionChest = $this->placeChest($plan, null, 'gardiens_scene', open: false);

        $this->smash($bankId);

        foreach ([$personal, $factionChest] as $chestId) {
            $ownership = $this->ownershipOf($chestId);
            $this->assertNull($ownership['owner_id'], 'sans banque, le coffre n\'a plus de propriétaire');
            $this->assertSame('', (string) $ownership['faction'], 'sans banque, le coffre n\'a plus de faction');
            $this->assertSame(1, (int) $ownership['is_open'], 'le couvercle s\'ouvre : personne ne peut plus tourner la serrure');
        }
    }

    public function testASecondBankKeepsWatchOverTheChests(): void
    {
        $plan = $this->scenePlan();
        $this->sowFaction('gardiens_scene');

        [$x1, $y1] = $this->farTile();
        $first = $this->placeStructure('banque', $x1, $y1, $plan);
        [$x2, $y2] = $this->farTile();
        $this->placeStructure('banque', $x2, $y2, $plan);

        $factionChest = $this->placeChest($plan, null, 'gardiens_scene');

        $this->smash($first);

        $ownership = $this->ownershipOf($factionChest);
        $this->assertSame('gardiens_scene', (string) $ownership['faction'], 'une banque tient encore : rien ne bouge');
    }

    public function testAFinishedBankClaimsOnlyTheHomelessChests(): void
    {
        $plan = $this->scenePlan();
        $this->sowFaction('gardiens_scene');

        $owner = $this->createRealPlayer('GmProprio');
        $public = $this->placeChest($plan, null, '');
        $personal = $this->placeChest($plan, (int) $owner->id, '');

        // The chantier path: the bank rises stone by stone, the claim
        // fires on the LAST work gesture, not at the site opening.
        $this->link->executeStatement("UPDATE races SET build_work = 4 WHERE name = 'banque'");
        RaceService::clearCache();
        try {
            [$bx, $by] = $this->farTile();
            $bankId = $this->placeStructure('banque', $bx, $by, $plan, asConstructionSite: true);
            $this->link->executeStatement("UPDATE players SET faction = 'gardiens_scene' WHERE id = ?", [$bankId]);

            $this->assertSame('', (string) $this->ownershipOf($public)['faction'], 'un chantier ne garde rien encore');

            $total = (int) $this->link->fetchOne('SELECT work_total FROM construction_sites WHERE player_id = ?', [$bankId]);
            $result = (new ConstructionSiteService())->advance($bankId, $total);
            $this->assertTrue((bool) ($result['completed'] ?? false));
        } finally {
            $this->link->executeStatement("UPDATE races SET build_work = 0 WHERE name = 'banque'");
            RaceService::clearCache();
        }

        $this->assertSame('gardiens_scene', (string) $this->ownershipOf($public)['faction'], 'la banque récupère le coffre sans propriétaire');
        $this->assertNull($this->ownershipOf($public)['owner_id'], 'récupéré pour la faction, pas pour une personne');
        $this->assertSame((int) $owner->id, (int) $this->ownershipOf($personal)['owner_id'], 'le coffre personnel ne bouge pas');
        $this->assertSame('', (string) $this->ownershipOf($personal)['faction']);
    }

    public function testAFactionlessBankClaimsNothing(): void
    {
        $plan = $this->scenePlan();

        $public = $this->placeChest($plan, null, '');

        // placeStructure poses with no faction: rose() must be a no-op.
        [$bx, $by] = $this->farTile();
        $this->placeStructure('banque', $bx, $by, $plan);

        $ownership = $this->ownershipOf($public);
        $this->assertNull($ownership['owner_id']);
        $this->assertSame('', (string) $ownership['faction'], 'une banque sans faction ne récupère rien');
    }
}
