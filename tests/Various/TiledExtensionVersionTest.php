<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\TiledExtensionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The minimum version of the Tiled extension an instance accepts.
 *
 * The bar lives in the settings so that raising it takes a dashboard edit
 * rather than a deployment, and the endpoints refuse anything below it.
 *
 * Settings are held in memory here: no database needed.
 */
class TiledExtensionVersionTest extends TestCase
{
    private function service(array $stored = []): TiledExtensionService
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

            public function set(string $name, string $value): void
            {
                $this->values[$name] = $value;
            }
        };

        return new TiledExtensionService($settings);
    }

    public function testUnsetTheBarIsTheFirstVersionedRelease(): void
    {
        $this->assertSame(TiledExtensionService::FIRST_VERSIONED, $this->service()->minimum());
    }

    public function testItRoundTripsThroughTheSettings(): void
    {
        $service = $this->service();
        $service->setMinimum('1.2.3');

        $this->assertSame('1.2.3', $service->minimum());
    }

    /** The tag's v reaches the form: version_compare would read v0.5 as older than 0.5. */
    public function testTheTagPrefixIsStripped(): void
    {
        $service = $this->service();
        $service->setMinimum('v0.5');

        $this->assertSame('0.5', $service->minimum());
        $this->assertSame('0.5', TiledExtensionService::normalize(' v0.5 '));
    }

    public function testAnUnreadableNumberIsRefusedRatherThanStored(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->setMinimum('dernière');
    }

    /** A damaged stored value must not shut the editor for everyone. */
    public function testAnUnreadableStoredValueFallsBackOnTheDefault(): void
    {
        $service = $this->service([TiledExtensionService::SETTING => 'n importe quoi']);

        $this->assertSame(TiledExtensionService::FIRST_VERSIONED, $service->minimum());
    }

    #[DataProvider('announcedVersions')]
    public function testItAcceptsFromTheBarUp(string $announced, bool $accepted): void
    {
        $service = $this->service([TiledExtensionService::SETTING => '0.4.0']);

        $this->assertSame($accepted, $service->accepts(TiledExtensionService::normalize($announced)));
    }

    /** @return array<string, array{string, bool}> */
    public static function announcedVersions(): array
    {
        return [
            'nothing announced' => ['', false],
            'older'             => ['0.3.0', false],
            'the bar itself'    => ['0.4.0', true],
            'tag-prefixed'      => ['v0.4.0', true],
            'next patch'        => ['0.4.1', true],
            'next major'        => ['1.0.0', true],
            'unreadable'        => ['not-a-number', false],
        ];
    }

    /** A refusal that does not say what to do leaves the mapmaker at a closed door. */
    public function testTheRefusalSaysWhatToDownload(): void
    {
        $message = $this->service([TiledExtensionService::SETTING => '0.6.0'])->refusalMessage('0.4.0');

        $this->assertStringContainsString('v0.4.0', $message, 'the version seen');
        $this->assertStringContainsString('v0.6.0', $message, 'the version demanded');
        $this->assertStringContainsString(TiledExtensionService::DOWNLOAD_URL, $message);
    }

    /**
     * The guard lives in the shared base every endpoint requires, so a new
     * route cannot forget it. Keep it that way.
     */
    public function testEveryTiledEndpointGoesThroughTheGuard(): void
    {
        $common = (string) file_get_contents(__DIR__ . '/../../api/admin/map/_common.php');

        $this->assertStringContainsString('tiledRequireExtensionVersion();', $common);

        foreach (glob(__DIR__ . '/../../api/admin/map/*.php') ?: [] as $endpoint) {
            if (basename($endpoint) === '_common.php') {
                continue;
            }

            $this->assertStringContainsString(
                "require_once __DIR__ . '/_common.php';",
                (string) file_get_contents($endpoint),
                basename($endpoint) . ' must go through the shared base'
            );
        }
    }
}
