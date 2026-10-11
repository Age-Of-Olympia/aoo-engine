<?php

namespace Tests\Various;

use App\Service\ViewService;
use PHPUnit\Framework\TestCase;

class ViewServiceGridStepsTest extends TestCase
{
    /** World map S3: 300 cells on 692 px, about 2.3 px a cell. */
    public function testWorldMapKeepsALineEveryTenAndANumberEveryTwenty(): void
    {
        $this->assertSame(['grid' => 10, 'label' => 20], ViewService::gridSteps(692 / 300));
    }

    /** Local map: 400 px for about 30 cells. */
    public function testLocalMapHasALinePerCellAndANumberEveryFive(): void
    {
        $this->assertSame(['grid' => 1, 'label' => 5], ViewService::gridSteps(400 / 30));
    }

    /** Numbers fall on lines whatever the scale, and are never closer than 32 px. */
    public function testTheLabelStepIsAMultipleOfTheGridStep(): void
    {
        foreach ([0.5, 1.0, 2.3, 4.0, 7.0, 13.3, 25.0, 60.0] as $scale) {
            $steps = ViewService::gridSteps($scale);

            $this->assertSame(0, $steps['label'] % $steps['grid'], "scale $scale");
            $this->assertGreaterThanOrEqual(32, $steps['label'] * $scale, "scale $scale");
            $this->assertGreaterThanOrEqual(12, $steps['grid'] * $scale, "scale $scale");
        }
    }
}
