<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\LootRulesService;
use PHPUnit\Framework\TestCase;

class LootRulesServiceTest extends TestCase
{
    /** @param array<string, string> $stored */
    private function rules(array $stored = []): LootRulesService
    {
        $settings = new class ($stored) extends AdminSettingsService {
            /** @param array<string, string> $stored */
            public function __construct(private array $stored)
            {
            }

            public function get(string $name, string $default = ''): string
            {
                return $this->stored[$name] ?? $default;
            }
        };

        return new LootRulesService($settings);
    }

    public function testAPlayerDropsTheItemChanceOrTheDefault(): void
    {
        $rules = $this->rules();

        $this->assertSame(30, $rules->chance(null, false, false), 'empty = the default');
        $this->assertSame(15, $rules->chance(null, true, false), 'equipped keeps half');
        $this->assertSame(0, $rules->chance(0, false, false), '0 = never');
        $this->assertSame(80, $rules->chance(80, false, false));
    }

    public function testAnNpcFollowsItsOwnRuleWhateverTheItem(): void
    {
        $rules = $this->rules();

        $this->assertSame(100, $rules->chance(0, false, true));
        $this->assertSame(0, $rules->chance(80, true, true));
    }

    public function testTheAdminSettingsReplaceTheShippedValues(): void
    {
        $rules = $this->rules([
            LootRulesService::DEFAULT_CHANCE => '40',
            LootRulesService::PLAYER_EQUIPPED_SHARE => '25',
            LootRulesService::NPC_CARRIED_CHANCE => '60',
        ]);

        $this->assertSame(10, $rules->chance(null, true, false));
        $this->assertSame(60, $rules->chance(null, false, true));
    }
}
