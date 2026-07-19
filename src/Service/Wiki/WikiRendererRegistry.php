<?php

namespace App\Service\Wiki;

/**
 * Registre des renderers wiki, clé = objectType — le miroir de
 * ExporterRegistry côté mise en forme. Ajouter une famille = une classe
 * WikiSheetRenderer + une entrée ici ; la page admin/wiki.php suit
 * toute seule.
 */
final class WikiRendererRegistry
{
    /** @var array<string, WikiSheetRenderer> */
    private array $renderers = [];

    /** @param WikiSheetRenderer[]|null $renderers injection de test */
    public function __construct(?array $renderers = null)
    {
        foreach ($renderers ?? [
            new ActionWikiRenderer(),
            new EffectWikiRenderer(),
            new ItemWikiRenderer(),
            new RaceWikiRenderer(),
        ] as $renderer) {
            $this->register($renderer);
        }
    }

    public function register(WikiSheetRenderer $renderer): void
    {
        $this->renderers[$renderer->objectType()] = $renderer;
    }

    public function rendererFor(string $objectType): ?WikiSheetRenderer
    {
        return $this->renderers[$objectType] ?? null;
    }

    /** @return array<string, string> objectType => titre humain */
    public function titles(): array
    {
        $out = [];
        foreach ($this->renderers as $type => $renderer) {
            $out[$type] = $renderer->title();
        }

        return $out;
    }
}
