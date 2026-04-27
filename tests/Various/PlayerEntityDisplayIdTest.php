<?php

namespace Tests\Various;

use App\Entity\RealPlayer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Characterization tests for the displayId field added to PlayerEntity.
 *
 * Pins the fallback contract: when display_id is unset (legacy rows where
 * the column was added later) or zero, getDisplayId() returns the raw id.
 * View code relies on this to never render "mat.0" for legacy players.
 *
 * RealPlayer is used as the concrete subclass; the field and its
 * getter/setter are inherited from PlayerEntity, so any subclass
 * exercises the same code path.
 */
class PlayerEntityDisplayIdTest extends TestCase
{
    private function setEntityField(object $entity, string $field, $value): void
    {
        $prop = new ReflectionProperty($entity, $field);
        $prop->setValue($entity, $value);
    }

    #[Group('player-display-id')]
    public function testGetterReturnsExplicitDisplayIdWhenSet(): void
    {
        $player = new RealPlayer();
        $player->setDisplayId(42);
        $this->setEntityField($player, 'id', 1234567);

        $this->assertSame(42, $player->getDisplayId());
    }

    #[Group('player-display-id')]
    public function testGetterFallsBackToIdWhenDisplayIdIsZero(): void
    {
        // Legacy/real-player row: display_id is NULL in DB (column is
        // nullable; only tutorial/NPC rows get a sequential value).
        // View code must show something usable rather than "mat.0".
        $player = new RealPlayer();
        $this->setEntityField($player, 'id', 7);
        // displayId left at default null

        $this->assertSame(7, $player->getDisplayId());
    }

    #[Group('player-display-id')]
    public function testSetterIsFluentForChaining(): void
    {
        $player = new RealPlayer();
        $this->assertSame($player, $player->setDisplayId(99));
    }

    #[Group('player-display-id')]
    public function testFallbackHandlesNullId(): void
    {
        // A freshly-instantiated entity that was never persisted has id=null.
        // getDisplayId casts via (int) so null becomes 0 — safe degenerate.
        $player = new RealPlayer();
        // both id and displayId at default

        $this->assertSame(0, $player->getDisplayId());
    }
}
