<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\ReputationSettingsService;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * What reputation is worth is set from the admin page, not the code.
 *
 * Settings missing from admin_settings read the values the game shipped
 * with; a save is read back, and an amount the tenths storage cannot hold is
 * refused. The titles keep a base title at 0 PR, and the forum reward draw
 * only ever picks a tier with a weight.
 */
class ReputationSettingsServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private const SETTINGS = [
        ReputationSettingsService::PER_POST,
        ReputationSettingsService::PER_COOKIE,
        ReputationSettingsService::PER_COOKIE_SAME_FACTION,
        ReputationSettingsService::REWARD_EVERY,
        ReputationSettingsService::COOKIE_MAX_DAYS,
        ReputationSettingsService::MISSIVE_COOKIES,
    ];

    private ?Connection $conn = null;

    /** @var array<string, string|false> */
    private array $previousSettings = [];

    /** @var list<array<string, mixed>> */
    private array $previousTiers = [];

    /** @var list<int> */
    private array $previousTitleIds = [];

    protected function setUp(): void
    {
        $this->conn = $this->bootstrapLegacyOrSkip();

        foreach (self::SETTINGS as $name) {
            $this->previousSettings[$name] = $this->conn->fetchOne('SELECT value FROM admin_settings WHERE name = ?', [$name]);
        }
        $this->conn->executeStatement('DELETE FROM admin_settings WHERE name IN (?)', [self::SETTINGS], [\Doctrine\DBAL\ArrayParameterType::STRING]);
        $this->previousTiers = $this->conn->fetchAllAssociative('SELECT tier, pr, weight FROM reputation_reward_tiers');
        $this->previousTitleIds = array_map('intval', $this->conn->fetchFirstColumn('SELECT id FROM reputation_titles'));
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }

        $this->conn->executeStatement('DELETE FROM admin_settings WHERE name IN (?)', [self::SETTINGS], [\Doctrine\DBAL\ArrayParameterType::STRING]);
        foreach ($this->previousSettings as $name => $value) {
            if ($value !== false) {
                (new AdminSettingsService())->set($name, (string) $value);
            }
        }

        $this->conn->executeStatement('DELETE FROM reputation_reward_tiers');
        foreach ($this->previousTiers as $tier) {
            $this->conn->insert('reputation_reward_tiers', $tier);
        }

        if ($this->previousTitleIds !== []) {
            $this->conn->executeStatement(
                'DELETE FROM reputation_titles WHERE id NOT IN (?)',
                [$this->previousTitleIds],
                [\Doctrine\DBAL\ArrayParameterType::INTEGER]
            );
        }
        $this->conn = null;
    }

    public function testMissingSettingsReadTheShippedValues(): void
    {
        $settings = $this->settings();

        $this->assertSame(1.0, $settings->perPost());
        $this->assertSame(0.3, $settings->perCookie());
        $this->assertSame(0.1, $settings->perCookieSameFaction());
        $this->assertSame(5, $settings->rewardEvery());
        $this->assertSame(14, $settings->cookieMaxDays());
        $this->assertTrue($settings->missiveCookies());
    }

    public function testSavedGainsAreReadBack(): void
    {
        $this->settings()->saveGains(2.5, 0.5, 0.2, 10);

        $settings = $this->settings();
        $this->assertSame(2.5, $settings->perPost());
        $this->assertSame(0.5, $settings->perCookie());
        $this->assertSame(0.2, $settings->perCookieSameFaction());
        $this->assertSame(10, $settings->rewardEvery());
        $this->assertSame('2.5', (new AdminSettingsService())->get(ReputationSettingsService::PER_POST));
    }

    public function testSavedCookieSettingsAreReadBack(): void
    {
        $this->settings()->saveCookies(7, false);

        $this->assertSame(7, $this->settings()->cookieMaxDays());
        $this->assertFalse($this->settings()->missiveCookies());
    }

    public function testAGainFinerThanATenthIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->saveGains(1, 0.25, 0.1, 5);
    }

    public function testANegativeGainIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->saveGains(-1, 0.3, 0.1, 5);
    }

    public function testAMilestoneOfZeroIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->saveGains(1, 0.3, 0.1, 0);
    }

    public function testANegativeCookieDelayIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->saveCookies(-1, true);
    }

    public function testATitleIsAddedRenamedAndRemoved(): void
    {
        $settings = $this->settings();

        $settings->addTitle(60, 'Remarqué');
        $this->assertSame('Remarqué', $settings->title(60.0));

        $id = $this->titleIdAt(60);
        $settings->updateTitle($id, 70, 'Célèbre');
        $this->assertSame('Connu', $settings->title(69.9));
        $this->assertSame('Célèbre', $settings->title(70.0));

        $settings->removeTitle($id);
        $this->assertSame('Connu', $settings->title(70.0));
    }

    public function testTwoTitlesCannotShareAThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->addTitle(25, 'Doublon');
    }

    public function testTheBaseTitleCannotBeRemoved(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->removeTitle($this->titleIdAt(0));
    }

    public function testTheBaseTitleStaysAtZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->updateTitle($this->titleIdAt(0), 5, 'Inconnu');
    }

    public function testTheBaseTitleCanBeRenamed(): void
    {
        $settings = $this->settings();
        $id = $this->titleIdAt(0);

        $settings->updateTitle($id, 0, 'Anonyme');
        $this->assertSame('Anonyme', $settings->title(0.2));

        $settings->updateTitle($id, 0, 'Inconnu');
    }

    public function testATierWithoutWeightIsNeverDrawn(): void
    {
        $settings = $this->settings();
        foreach ([1, 2, 3] as $tier) {
            $settings->saveRewardTier($tier, $tier, 0);
        }
        $settings->saveRewardTier(4, 6, 1);

        for ($i = 0; $i < 50; $i++) {
            $this->assertSame(['tier' => 4, 'pr' => 6], $settings->drawRewardTier());
        }
    }

    public function testEveryTierCannotLoseItsWeight(): void
    {
        $settings = $this->settings();
        foreach ([1, 2, 3] as $tier) {
            $settings->saveRewardTier($tier, $tier, 0);
        }

        $this->expectException(InvalidArgumentException::class);
        $settings->saveRewardTier(4, 4, 0);
    }

    public function testAnUnknownTierIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->saveRewardTier(5, 5, 1);
    }

    private function settings(): ReputationSettingsService
    {
        return new ReputationSettingsService($this->conn);
    }

    private function titleIdAt(int $threshold): int
    {
        return (int) $this->conn->fetchOne('SELECT id FROM reputation_titles WHERE threshold = ?', [$threshold]);
    }
}
