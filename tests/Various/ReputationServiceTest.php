<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\ReputationService;
use App\Service\ReputationSettingsService;
use App\Simulation\SimulationGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Reputation is stored in tenths of a PR and spoken in PR everywhere else.
 * These cases pin the conversion on every write, the reads, what players
 * see, and the forum reward drawn at every milestone. The gains, the
 * milestone and the titles are the seeded settings unless a case sets them.
 */
#[Group('entities-baseline')]
class ReputationServiceTest extends LegacyPlayerFixtureTestCase
{
    private ?int $playerId = null;
    private ?int $titleId = null;

    protected function tearDown(): void
    {
        if ($this->link !== null) {
            if ($this->playerId !== null) {
                $this->link->executeStatement('DELETE FROM players_forum_rewards WHERE to_player_id = ?', [$this->playerId]);
            }
            if ($this->titleId !== null) {
                (new ReputationSettingsService($this->link))->removeTitle($this->titleId);
            }
            $this->link->executeStatement('DELETE FROM admin_settings WHERE name = ?', [ReputationSettingsService::REWARD_EVERY]);
        }
        parent::tearDown();
    }

    public function testAGainIsStoredInTenths(): void
    {
        $id = $this->playerWithStoredPr(0);

        $this->reputation()->add($id, 1);

        $this->assertSame(10, $this->storedPr($id));
        $this->assertSame(1.0, $this->reputation()->of($id));
    }

    public function testAFractionalGainIsStoredExactly(): void
    {
        $id = $this->playerWithStoredPr(0);

        $this->reputation()->add($id, $this->reputation()->perCookie(false));
        $this->reputation()->add($id, $this->reputation()->perCookie(true));

        $this->assertSame(4, $this->storedPr($id));
        $this->assertSame(0.4, $this->reputation()->of($id));
    }

    /** 0.7 * 10 is 6.999… in floating point: truncating would lose a tenth. */
    public function testFloatNoiseIsRoundedNotTruncated(): void
    {
        $id = $this->playerWithStoredPr(0);

        $this->reputation()->add($id, 0.7);

        $this->assertSame(7, $this->storedPr($id));
    }

    public function testSetReplacesTheBalance(): void
    {
        $id = $this->playerWithStoredPr(37);

        $this->reputation()->set($id, 2);

        $this->assertSame(20, $this->storedPr($id));
    }

    public function testRemoveDebitsTheBalance(): void
    {
        $id = $this->playerWithStoredPr(50);

        $this->reputation()->remove($id, 1.5);

        $this->assertSame(35, $this->storedPr($id));
    }

    public function testASimulationWritesNothing(): void
    {
        $id = $this->playerWithStoredPr(10);

        SimulationGuard::run(function () use ($id): void {
            $this->reputation()->add($id, 5);
            $this->reputation()->set($id, 99);
        });

        $this->assertSame(10, $this->storedPr($id));
        $this->assertSame(0, $this->rewards());
    }

    public function testTheLegacyRowStillCarriesTheStoredValue(): void
    {
        $id = $this->playerWithStoredPr(0);
        $this->reputation()->add($id, 2.5);

        $player = $this->loadedCharacter($id);

        $this->assertSame(25, (int) $player->data->pr);
        $this->assertSame(2.5, ReputationService::fromStored((int) $player->data->pr));
    }

    public function testFromStoredReadsTenths(): void
    {
        $this->assertSame(0.2, ReputationService::fromStored(2));
        $this->assertSame(0.0, ReputationService::fromStored(0));
        $this->assertSame(12.5, ReputationService::fromStored(125));
    }

    /** The reported bug: 2 stored, 0.2 PR, shows 0 and the first title. */
    public function testTwoTenthsDisplayAsZero(): void
    {
        $pr = ReputationService::fromStored(2);

        $this->assertSame('0', ReputationService::format($pr));
        $this->assertSame('Inconnu', $this->reputation()->title($pr));
    }

    public function testTheDisplayIsFlooredAndNeverNegative(): void
    {
        $this->assertSame('12', ReputationService::format(12.9));
        $this->assertSame('1', ReputationService::format(1.0));
        $this->assertSame('0', ReputationService::format(-0.2));
    }

    #[DataProvider('titles')]
    public function testTitleThresholds(float $pr, string $title): void
    {
        $this->assertSame($title, $this->reputation()->title($pr));
    }

    /** @return array<string, array{float, string}> */
    public static function titles(): array
    {
        return [
            'nothing' => [0.0, 'Inconnu'],
            'just under Connu' => [24.9, 'Inconnu'],
            'Connu' => [25.0, 'Connu'],
            'just under Populaire' => [99.9, 'Connu'],
            'Populaire' => [100.0, 'Populaire'],
            'Héroïque' => [250.0, 'Héroïque'],
            'Légendaire' => [500.0, 'Légendaire'],
            'Mythologique' => [1250.0, 'Mythologique'],
            'Divin' => [2500.0, 'Divin'],
        ];
    }

    public function testAMilestoneIsRewardedOnce(): void
    {
        $id = $this->playerWithStoredPr(40);

        $this->reputation()->add($id, 1);
        $this->assertSame(1, $this->rewards(), '4 → 5 PR: the milestone');

        $this->reputation()->add($id, 1);
        $this->assertSame(1, $this->rewards(), '5 → 6 PR: already rewarded');
    }

    public function testTheFirstGainIsNoMilestone(): void
    {
        $this->reputation()->add($this->playerWithStoredPr(0), 1);

        $this->assertSame(0, $this->rewards());
    }

    public function testOneGainCanCrossTwoMilestones(): void
    {
        $this->reputation()->add($this->playerWithStoredPr(40), 7);

        $this->assertSame(2, $this->rewards(), '4 → 11 PR: 5 and 10, in one request');
    }

    public function testAFractionalGainReachesTheMilestone(): void
    {
        $this->reputation()->add($this->playerWithStoredPr(48), 0.3);

        $this->assertSame(1, $this->rewards(), '4.8 → 5.1 PR');
    }

    public function testTheMilestoneFollowsTheSetting(): void
    {
        (new AdminSettingsService())->set(ReputationSettingsService::REWARD_EVERY, '2');

        $this->reputation()->add($this->playerWithStoredPr(10), 1);

        $this->assertSame(1, $this->rewards(), '1 → 2 PR, a reward every 2 PR');
    }

    public function testATitleAddedInTheAdminIsShown(): void
    {
        $settings = new ReputationSettingsService($this->link);
        $settings->addTitle(60, 'Remarqué');
        $this->titleId = (int) $this->link->fetchOne('SELECT id FROM reputation_titles WHERE threshold = 60');

        $this->assertSame('Remarqué', $this->reputation()->title(60.0));
        $this->assertSame('Connu', $this->reputation()->title(59.9));
    }

    public function testALossRewardsNothing(): void
    {
        $id = $this->playerWithStoredPr(60);

        $this->reputation()->remove($id, 2);
        $this->reputation()->add($id, 0.5);

        $this->assertSame(0, $this->rewards(), '6 → 4 → 4.5 PR: no milestone crossed upward');
    }

    private function reputation(): ReputationService
    {
        return new ReputationService($this->link);
    }

    private function playerWithStoredPr(int $stored): int
    {
        $this->playerId = (int) $this->createRealPlayer('Reput')->id;
        $this->link->executeStatement('UPDATE players SET pr = ? WHERE id = ?', [$stored, $this->playerId]);

        return $this->playerId;
    }

    private function storedPr(int $id): int
    {
        return (int) $this->link->fetchOne('SELECT pr FROM players WHERE id = ?', [$id]);
    }

    private function rewards(): int
    {
        return (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_forum_rewards WHERE to_player_id = ?', [$this->playerId]);
    }
}
