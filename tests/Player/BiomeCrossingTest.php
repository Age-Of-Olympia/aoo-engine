<?php

namespace Tests\Player;

use App\Module\Module;
use App\Module\Modules;
use App\Service\EffectService;
use App\Service\Map\BiomeAdminService;
use App\Service\Map\BiomeEvents;
use App\Service\Map\CharacterMoveService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** Entering and leaving a biome: its message each way, its effect on entry. */
#[Group('database')]
class BiomeCrossingTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'test_crossing';
    private const NBSP = "\u{00A0}";
    private const TYPE = 'test_crossing_foret';
    private const EFFECT = 'test_crossing_brume';

    /** Cells 0..2 on both axes; the walker waits outside, at (-1, 0) */
    private const ZONE = '0,0 3,0 3,3 0,3';

    private BiomeAdminService $admin;
    private int $typeId;
    private int $biomeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->removeFixtures();
        $this->link->insert('plans', ['slug' => self::PLAN, 'name' => self::PLAN]);
        $this->link->executeStatement("INSERT INTO effects (name, label) VALUES (?, 'Brume')", [self::EFFECT]);
        EffectService::clearCache();
        Modules::force(Module::Biomes, true);

        $this->admin = new BiomeAdminService($this->link);
        $this->typeId = $this->admin->saveType(0, self::TYPE, 'Forêt de test', '#336633');
        $this->admin->saveCrossing('biome_types', $this->typeId, $this->settings(['enter_effect' => self::EFFECT]));
        $this->biomeId = $this->admin->saveBiome(0, self::PLAN, $this->typeId, 'Bois de test', self::ZONE, 0)['id'];
    }

    protected function tearDown(): void
    {
        Modules::reset();
        $this->removeFixtures();
        parent::tearDown();
    }

    /** Also run first: a run stopped halfway leaves its rows behind */
    private function removeFixtures(): void
    {
        // Characters leave the plan before it goes (the parent tearDown deletes them), with the log lines written there
        $this->link->executeStatement(
            "UPDATE players p JOIN coords c ON c.id = p.coords_id SET p.coords_id = ? WHERE c.plan = ? AND p.player_type = 'real'",
            [$this->coordsIdOn('gaia', 0, 0), self::PLAN]
        );
        $this->link->executeStatement('DELETE l FROM players_logs l JOIN coords c ON c.id = l.coords_id WHERE c.plan = ?', [self::PLAN]);
        $this->purgePlan($this->link, self::PLAN);
        $this->link->executeStatement('DELETE FROM biome_types WHERE name = ?', [self::TYPE]);
        $this->link->executeStatement('DELETE FROM effects WHERE name = ?', [self::EFFECT]);
        EffectService::clearCache();
    }

    public function testEnteringSaysItsMessageAndPutsItsEffectOn(): void
    {
        $walker = $this->walkerOutside();

        $this->assertSame(
            ['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Vous entrez dans le bois. Effet <span class="ra ra-fairy-wand"></span> Brume, jusqu\'à la sortie du biome.'],
            $walker->go($this->tile(0, 0, self::PLAN))
        );
        $this->assertNotEmpty($walker->have_effect(self::EFFECT));
        $this->assertSame([], $walker->go($this->tile(1, 1, self::PLAN)), 'a step inside the biome crosses nothing');

        $logged = $this->link->fetchFirstColumn("SELECT text FROM players_logs WHERE player_id = ? AND type = 'biome' ORDER BY id", [(int) $walker->id]);
        $this->assertSame(['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Vous entrez dans le bois. Effet <span class="ra ra-fairy-wand"></span> Brume, jusqu\'à la sortie du biome.'], $logged, 'the log keeps the same line');
    }

    public function testLeavingSaysItsMessageAndEndsTheEffect(): void
    {
        $walker = $this->walkerOutside();
        $walker->go($this->tile(0, 0, self::PLAN));

        $this->assertSame(['<strong class="biome-out" title="Sortie"><span class="ra ra-trail"></span> Bois de test</strong>' . self::NBSP . ': Vous sortez du bois. L\'effet <span class="ra ra-fairy-wand"></span> Brume prend fin.'], $walker->go($this->tile(-1, 0, self::PLAN)));
        $this->assertEmpty($walker->have_effect(self::EFFECT));
    }

    public function testStraightFromOneBiomeIntoAnotherEachLineNamesItsBiome(): void
    {
        $this->admin->saveBiome(0, self::PLAN, $this->typeId, 'Clairière', '-3,0 0,0 0,1 -3,1', 0);
        $walker = $this->walkerOutside();
        $walker->go($this->tile(0, 0, self::PLAN));

        $this->assertSame([
            '<strong class="biome-out" title="Sortie"><span class="ra ra-trail"></span> Bois de test</strong>' . self::NBSP . ': Vous sortez du bois. L\'effet <span class="ra ra-fairy-wand"></span> Brume prend fin.',
            '<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Clairière</strong>' . self::NBSP . ': Vous entrez dans le bois. Effet <span class="ra ra-fairy-wand"></span> Brume, jusqu\'à la sortie du biome.',
        ], $walker->go($this->tile(-1, 0, self::PLAN)));
    }

    public function testAnEffectLeftToRunOutStaysAfterTheExit(): void
    {
        $this->admin->saveCrossing('biome_types', $this->typeId, $this->settings([
            'enter_effect' => self::EFFECT, 'enter_effect_turns' => 3, 'enter_effect_ends_on_exit' => false,
        ]));
        $walker = $this->walkerOutside();
        $this->assertSame(['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Vous entrez dans le bois. Effet <span class="ra ra-fairy-wand"></span> Brume, pour 3 tours.'], $walker->go($this->tile(0, 0, self::PLAN)));
        $this->assertSame(['<strong class="biome-out" title="Sortie"><span class="ra ra-trail"></span> Bois de test</strong>' . self::NBSP . ': Vous sortez du bois.'], $walker->go($this->tile(-1, 0, self::PLAN)), 'nothing ends');

        $this->assertNotEmpty($walker->have_effect(self::EFFECT));
    }

    public function testABiomeReplacesWhatItsTypeSets(): void
    {
        $this->admin->saveCrossing('biomes', $this->biomeId, $this->settings([
            'enter_message' => 'Bienvenue au <b>bois</b> de test !', 'exit_message' => '', 'enter_effect' => '',
        ]));
        $walker = $this->walkerOutside();

        $this->assertSame(
            ['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Bienvenue au &lt;b&gt;bois&lt;/b&gt; de test' . self::NBSP . '!'],
            $walker->go($this->tile(0, 0, self::PLAN)),
            'the admin\'s message shows as typed, never as HTML, and its ! never starts a line alone'
        );
        $this->assertEmpty($walker->have_effect(self::EFFECT), 'no effect in this biome');
        $this->assertSame(['<strong class="biome-out" title="Sortie"><span class="ra ra-trail"></span> Bois de test</strong>' . self::NBSP . ': Vous sortez du bois.'], $walker->go($this->tile(-1, 0, self::PLAN)), 'an empty message: the type\'s');
    }

    public function testASilentPlacementCrossesNothingAndAnArrivalOnlyEnters(): void
    {
        $walker = $this->walkerOutside();
        $moves = new CharacterMoveService($this->link);

        $this->assertSame([], $moves->move((int) $walker->id, $this->coordsAt(0, 0), BiomeEvents::None)['messages']);
        $this->assertSame([], $moves->move((int) $walker->id, $this->coordsAt(-1, 0), BiomeEvents::None)['messages']);
        $this->assertEmpty($walker->have_effect(self::EFFECT));

        $this->assertSame(
            ['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Vous entrez dans le bois. Effet <span class="ra ra-fairy-wand"></span> Brume, jusqu\'à la sortie du biome.'],
            $moves->move((int) $walker->id, $this->coordsAt(1, 1), BiomeEvents::Arrive)['messages']
        );
    }

    public function testNothingHappensWithTheModuleOff(): void
    {
        Modules::force(Module::Biomes, false);
        $walker = $this->walkerOutside();

        $this->assertSame([], $walker->go($this->tile(0, 0, self::PLAN)));
        $this->assertEmpty($walker->have_effect(self::EFFECT));
    }

    public function testAnEffectGoneFromTheCatalogDoesNotStopTheStep(): void
    {
        $this->link->executeStatement('DELETE FROM effects WHERE name = ?', [self::EFFECT]);
        EffectService::clearCache();
        $walker = $this->walkerOutside();

        $this->assertSame(['<strong class="biome-in" title="Entrée"><span class="ra ra-footprint"></span> Bois de test</strong>' . self::NBSP . ': Vous entrez dans le bois.'], $walker->go($this->tile(0, 0, self::PLAN)));
        $this->assertSame($this->coordsAt(0, 0), (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [(int) $walker->id]));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{enter_message: string, exit_message: string, enter_effect: ?string, enter_effect_turns: ?int, enter_effect_value: int, enter_effect_ends_on_exit: bool}
     */
    private function settings(array $overrides): array
    {
        return $overrides + [
            'enter_message' => 'Vous entrez dans le bois.',
            'exit_message' => 'Vous sortez du bois.',
            'enter_effect' => '',
            'enter_effect_turns' => null,
            'enter_effect_value' => 1,
            'enter_effect_ends_on_exit' => true,
        ];
    }

    private function walkerOutside(): \Classes\Player
    {
        $walker = $this->createRealPlayer('GmBois');
        $walker->go($this->tile(-1, 0, self::PLAN));

        return $walker;
    }

    private function coordsAt(int $x, int $y): int
    {
        return (int) View::get_coords_id($this->tile($x, $y, self::PLAN));
    }
}
