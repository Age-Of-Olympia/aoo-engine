<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\PlayerCaracsService;
use PHPUnit\Framework\TestCase;

/**
 * Buying back a rank costs what that rank cost, unless the admin setting
 * makes reassignment free. Settings are held in memory: no database.
 */
class ReassignCostTest extends TestCase
{
    /** @param array<string, string> $stored */
    private function service(array $stored = []): PlayerCaracsService
    {
        $settings = new class ($stored) extends AdminSettingsService {
            /** @param array<string, string> $values */
            public function __construct(private array $values)
            {
            }

            public function get(string $name, string $default = ''): string
            {
                return $this->values[$name] ?? $default;
            }
        };

        return new PlayerCaracsService($settings);
    }

    public function testTheLastRankBoughtIsPaidBack(): void
    {
        // f: 120 the first rank, 55 the second
        $this->assertSame(120, $this->service()->reassignCost('f', 1));
        $this->assertSame(175, $this->service()->reassignCost('f', 2));
    }

    public function testNoRankNothingToPay(): void
    {
        $this->assertSame(0, $this->service()->reassignCost('f', 0));
    }

    public function testTheSettingMakesItFree(): void
    {
        $free = $this->service([PlayerCaracsService::SETTING_FREE_REASSIGN => '1']);
        $this->assertSame(0, $free->reassignCost('f', 2));

        $paid = $this->service([PlayerCaracsService::SETTING_FREE_REASSIGN => '0']);
        $this->assertSame(175, $paid->reassignCost('f', 2));
    }
}
