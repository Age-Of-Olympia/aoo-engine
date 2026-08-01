<?php

namespace App\Service\Wiki;

/**
 * Registre des renderers wiki, clé = objectType — le miroir de
 * ExporterRegistry côté mise en forme. Ajouter une famille = une classe
 * WikiSheetRendererInterface + une entrée ici ; la page admin/wiki.php suit
 * toute seule.
 */
final class WikiRendererRegistry
{
    /** @var array<string, WikiSheetRendererInterface> */
    private array $renderers = [];

    /** @param WikiSheetRendererInterface[]|null $renderers injection de test */
    public function __construct(?array $renderers = null)
    {
        foreach ($renderers ?? [
            new ActionWikiRenderer(),
            new PassiveWikiRenderer(),
            new EffectWikiRenderer(),
            new ItemWikiRenderer(),
            new RaceWikiRenderer(),
        ] as $renderer) {
            $this->register($renderer);
        }
    }

    public function register(WikiSheetRendererInterface $renderer): void
    {
        $this->renderers[$renderer->objectType()] = $renderer;
    }

    public function rendererFor(string $objectType): ?WikiSheetRendererInterface
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
