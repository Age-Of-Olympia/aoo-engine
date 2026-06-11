<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialCatalogService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression: a failed create/update POST on admin/tutorial-catalog.php
 * re-rendered the form from $editTutorial (DB row or defaults), wiping
 * everything the admin typed. The form must repopulate from the
 * submitted values, mapped by TutorialCatalogService::mapFormData().
 */
class TutorialCatalogFormRepopulationTest extends TestCase
{
    #[Group('tutorial-catalog-repopulation')]
    public function testMapFormDataMapsSubmittedValues(): void
    {
        $data = TutorialCatalogService::mapFormData([
            'version' => ' 2.0.0-craft ',
            'name' => ' Tutoriel Artisanat ',
            'description' => ' Apprendre le craft ',
            'icon' => 'ra-anvil',
            'difficulty' => 'advanced',
            'estimated_minutes' => '25',
            'prerequisites' => '["1.0.0"]',
            'plan' => 'craft_map',
            'spawn_x' => '3',
            'spawn_y' => '-2',
            'is_active' => 'on',
            'display_order' => '7',
        ]);

        $this->assertSame('2.0.0-craft', $data['version']);
        $this->assertSame('Tutoriel Artisanat', $data['name']);
        $this->assertSame('Apprendre le craft', $data['description']);
        $this->assertSame('ra-anvil', $data['icon']);
        $this->assertSame('advanced', $data['difficulty']);
        $this->assertSame(25, $data['estimated_minutes']);
        $this->assertSame('["1.0.0"]', $data['prerequisites']);
        $this->assertSame('craft_map', $data['plan']);
        $this->assertSame(3, $data['spawn_x']);
        $this->assertSame(-2, $data['spawn_y']);
        $this->assertSame(1, $data['is_active']);
        $this->assertSame(7, $data['display_order']);
    }

    #[Group('tutorial-catalog-repopulation')]
    public function testMapFormDataAppliesDefaults(): void
    {
        $data = TutorialCatalogService::mapFormData([]);

        $this->assertSame('', $data['version']);
        $this->assertSame('', $data['name']);
        $this->assertSame('ra-book', $data['icon']);
        $this->assertSame('beginner', $data['difficulty']);
        $this->assertSame(10, $data['estimated_minutes']);
        $this->assertNull($data['prerequisites']);
        $this->assertSame('tutorial', $data['plan']);
        $this->assertSame(0, $data['spawn_x']);
        $this->assertSame(0, $data['spawn_y']);
        $this->assertSame(0, $data['is_active']);
        $this->assertSame(0, $data['display_order']);
    }
}
