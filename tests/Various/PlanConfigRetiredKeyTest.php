<?php

namespace Tests\Various;

use App\Service\PlanConfigService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `biomes` left the plan config; maps pulled and bundles exported before that
 * still send it, and their push must not fail on it. Any other unknown key is
 * still refused.
 */
class PlanConfigRetiredKeyTest extends TestCase
{
    public function testTheRetiredBiomesKeyIsIgnored(): void
    {
        $parsed = (new PlanConfigService())->parse(['name' => 'Gaia', 'biomes' => '[{"wall":"arbre1"}]']);

        $this->assertSame(['name' => 'Gaia'], $parsed);
    }

    public function testAnUnknownKeyIsStillRefused(): void
    {
        $this->expectException(RuntimeException::class);

        (new PlanConfigService())->parse(['biome' => 'x']);
    }
}
