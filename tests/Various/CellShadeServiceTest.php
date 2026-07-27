<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\CellShadeService;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'un niveau d'ombre vaut à l'écran — réglable, et pas dans le code.
 *
 * `coords.shade` porte un niveau ; ce service dit ce que ce niveau donne.
 * Les deux sont séparés pour que changer l'apparence des ombres n'oblige
 * jamais à reprendre les 7 613 cases qui en portent une.
 *
 * Le cas qui compte le plus est celui du défaut : il doit reproduire
 * EXACTEMENT l'ancien décor empilé, sans quoi la conversion aurait changé
 * la carte au passage.
 */
class CellShadeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        CellShadeService::clearCache();
    }

    /**
     * Un magasin de réglages en mémoire.
     *
     * Le `db()` hérité ne lève pas quand la connexion manque, il fait
     * `exit(1)` — impossible à rattraper. Les cas de calcul se passent donc
     * de base, ce qui est de toute façon leur nature.
     *
     * @param array<string, string> $values
     */
    private function store(array $values = []): AdminSettingsService
    {
        return new class ($values) extends AdminSettingsService {
            /** @param array<string, string> $values */
            public function __construct(private array $values) {}

            public function get(string $name, string $default = ''): string
            {
                return $this->values[$name] ?? $default;
            }

            public function set(string $name, string $value): void
            {
                $this->values[$name] = $value;
            }
        };
    }

    protected function tearDown(): void
    {
        CellShadeService::clearCache();
    }

    /**
     * Le défaut reproduit l'empilement d'avant, au dix-millième.
     *
     * L'ancien `img/foregrounds/ombre.png` est un noir uni à alpha 120 sur
     * 127 ; N calques laissent passer (1-a)^N. Ce sont ces valeurs que la
     * carte convertie doit montrer.
     */
    public function testTheDefaultReproducesTheOldStack(): void
    {
        $service = new CellShadeService($this->store());

        $attendu = [1 => 0.0551, 2 => 0.1071, 3 => 0.1564, 4 => 0.2030, 5 => 0.2470];

        foreach ($attendu as $level => $opacity) {
            $this->assertEqualsWithDelta(
                $opacity,
                $service->opacityFor($level),
                0.0005,
                'niveau ' . $level
            );
        }
    }

    /** Sans ombre, pas d'opacité — et pas de rectangle à dessiner. */
    public function testLevelZeroIsTransparent(): void
    {
        $this->assertSame(0.0, (new CellShadeService($this->store()))->opacityFor(0));
        $this->assertSame(0.0, (new CellShadeService($this->store()))->opacityFor(-3));
    }

    /** L'opacité croît avec le niveau, sans jamais atteindre l'opaque. */
    public function testOpacityGrowsButNeverReachesOne(): void
    {
        $service = new CellShadeService($this->store());
        $previous = 0.0;

        foreach (range(1, 8) as $level) {
            $opacity = $service->opacityFor($level);
            $this->assertGreaterThan($previous, $opacity, 'niveau ' . $level);
            $this->assertLessThan(1.0, $opacity);
            $previous = $opacity;
        }
    }

    /** Au-delà du plafond, un niveau de plus ne change plus rien. */
    public function testTheCeilingHolds(): void
    {
        $service = new CellShadeService($this->store());
        $max = $service->maxLevel();

        $this->assertSame($service->opacityFor($max), $service->opacityFor($max + 40));
    }

    /**
     * Les réglages aberrants sont refusés plutôt qu'appliqués.
     *
     * Une virgule mal placée sur l'opacité rendrait la carte entièrement
     * noire, et il faudrait aller en base pour la récupérer.
     */
    public function testAbsurdSettingsAreRefused(): void
    {
        $service = new CellShadeService($this->store());

        foreach ([[0.0, 8, '#000000'], [1.0, 8, '#000000'], [0.05, 0, '#000000'],
                  [0.05, 999, '#000000'], [0.05, 8, 'noir'], [0.05, 8, '#gggggg']] as $args) {
            try {
                $service->save(...$args);
                $this->fail('accepté : ' . json_encode($args));
            } catch (\RuntimeException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Un plan qui ne dit rien suit le défaut global.
     *
     * C'est le cas de la quasi-totalité des cartes : personne ne doit avoir à
     * régler quarante plans pour que les ombres existent.
     */
    public function testAPlanThatSaysNothingFollowsTheDefault(): void
    {
        $service = new CellShadeService($this->store([
            CellShadeService::SETTING_STEP => '0.2',
            CellShadeService::SETTING_MAX  => '4',
        ]));

        $config = $service->forPlan('plan_qui_n_existe_pas');

        $this->assertSame(0.2, $config['step'], 'le défaut global descend');
        $this->assertSame(4, $config['max']);
        $this->assertSame(CellShadeService::DEFAULT_COLOR, $config['color']);
        $this->assertEqualsWithDelta(
            0.36,
            $service->opacityOnPlan('plan_qui_n_existe_pas', 2),
            0.0005,
            'deux calques à 20 %'
        );
    }

    /** Le plafond du plan borne aussi l'opacité, pas seulement le pinceau. */
    public function testThePlanCeilingCapsTheOpacity(): void
    {
        $service = new CellShadeService($this->store([
            CellShadeService::SETTING_MAX => '2',
        ]));

        $this->assertSame(
            $service->opacityOnPlan(null, 2),
            $service->opacityOnPlan(null, 9),
            'au-delà du plafond, rien ne bouge'
        );
    }

    /**
     * Un réglage enregistré est celui que le rendu applique aussitôt.
     */
    public function testASavedSettingDrivesTheRender(): void
    {
        $service = new CellShadeService($this->store());
        $service->save(0.5, 3, '#112233');

        $this->assertSame(0.5, $service->step());
        $this->assertSame(3, $service->maxLevel());
        $this->assertSame('#112233', $service->color());
        $this->assertEqualsWithDelta(0.75, $service->opacityFor(2), 0.0005, 'deux calques à 50 %');

    }
}
