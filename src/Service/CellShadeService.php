<?php

namespace App\Service;

use RuntimeException;

/**
 * L'assombrissement des cases — réglages globaux du tableau de bord admin
 * (`admin_settings`, clés `shade_*`).
 *
 * `coords.shade` porte un NIVEAU (0 à N) ; ce service dit ce qu'un niveau
 * vaut à l'écran. Les deux sont séparés exprès : changer l'apparence des
 * ombres ne doit pas demander de reprendre les 7 613 cases qui en portent
 * une.
 *
 * # Pourquoi ces valeurs ne sont pas dans le code
 *
 * Elles viennent de l'ancien décor `ombre` — un PNG noir uni à alpha 120 sur
 * 127, que les animateurs empilaient pour foncer. Reprendre 7/127 en dur
 * dans le rendu aurait figé dans le moteur une décision qui appartient au
 * décor : c'est l'intensité d'une ombre, pas une constante physique. Le jour
 * où quelqu'un trouve la carte trop sombre, il doit pouvoir le corriger sans
 * livraison.
 *
 * # La couleur
 *
 * L'ancien décor ne savait qu'assombrir. La couleur est ici pour que les
 * animateurs puissent réchauffer une zone ou bleuir une grotte sans qu'on
 * remette le sujet sur la table — le noir reste le défaut, donc rien ne
 * bouge tant que personne n'y touche.
 */
class CellShadeService
{
    public const SETTING_STEP  = 'shade_step';
    public const SETTING_MAX   = 'shade_max';
    public const SETTING_COLOR = 'shade_color';

    /**
     * 7/127 : l'opacité d'un calque de l'ancien `img/foregrounds/ombre.png`,
     * noir uni à alpha 120 sur 127. C'est cette valeur qui rend la carte
     * convertie identique à ce qu'elle était.
     */
    public const DEFAULT_STEP = 0.0551;

    /**
     * Huit calques valent 37 % d'opacité ; au-delà l'œil ne suit plus, et un
     * clic de plus doit rester sans effet plutôt que gonfler un compteur.
     */
    public const DEFAULT_MAX = 8;

    public const DEFAULT_COLOR = '#000000';

    /** Réglages lus une fois par requête : ils ne changent pas en cours de rendu. */
    private static ?float $stepCache = null;
    private static ?int $maxCache = null;
    private static ?string $colorCache = null;

    private AdminSettingsService $settings;

    /**
     * Le magasin de réglages s'injecte.
     *
     * Sans cela, la simple lecture d'une opacité exigerait une base : le
     * `db()` hérité ne lève pas quand la connexion manque, il fait `exit(1)`,
     * qu'aucun `catch` ne rattrape. Le calcul de fidélité — celui qui garantit
     * que la carte convertie est identique à l'ancienne — se teste donc sans
     * infrastructure.
     */
    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    /** Opacité d'UN niveau, entre 0 et 1. */
    public function step(): float
    {
        if (self::$stepCache === null) {
            $raw = (float) $this->settings->get(
                self::SETTING_STEP,
                (string) self::DEFAULT_STEP
            );

            self::$stepCache = ($raw > 0 && $raw < 1) ? $raw : self::DEFAULT_STEP;
        }

        return self::$stepCache;
    }

    /** Niveau maximal atteignable au pinceau. */
    public function maxLevel(): int
    {
        if (self::$maxCache === null) {
            $raw = (int) $this->settings->get(
                self::SETTING_MAX,
                (string) self::DEFAULT_MAX
            );

            self::$maxCache = ($raw >= 1 && $raw <= 255) ? $raw : self::DEFAULT_MAX;
        }

        return self::$maxCache;
    }

    /** Couleur de l'ombre, en notation `#rrggbb`. */
    public function color(): string
    {
        if (self::$colorCache === null) {
            $raw = $this->settings->get(self::SETTING_COLOR, self::DEFAULT_COLOR);
            self::$colorCache = preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? $raw : self::DEFAULT_COLOR;
        }

        return self::$colorCache;
    }

    /**
     * L'opacité résultante d'un niveau.
     *
     * N calques d'opacité `a` laissent passer `(1-a)^N` : le niveau se lit
     * donc `1-(1-a)^N`. C'est ce qui rend un rectangle unique indiscernable
     * des N images empilées d'avant.
     */
    public function opacityFor(int $level): float
    {
        if ($level < 1) {
            return 0.0;
        }

        return round(1 - pow(1 - $this->step(), min($level, $this->maxLevel())), 4);
    }

    /**
     * Enregistre les trois réglages (tableau de bord admin).
     *
     * @throws RuntimeException si une valeur sortirait de son domaine — mieux
     *         vaut refuser que rendre la carte noire par une virgule mal placée
     */
    public function save(float $step, int $maxLevel, string $color): void
    {
        if ($step <= 0 || $step >= 1) {
            throw new RuntimeException('L\'opacité d\'un niveau doit être strictement entre 0 et 1.');
        }

        if ($maxLevel < 1 || $maxLevel > 255) {
            throw new RuntimeException('Le niveau maximal doit être compris entre 1 et 255.');
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new RuntimeException('La couleur doit être au format #rrggbb.');
        }

        $this->settings->set(self::SETTING_STEP, (string) $step);
        $this->settings->set(self::SETTING_MAX, (string) $maxLevel);
        $this->settings->set(self::SETTING_COLOR, $color);

        self::$stepCache = $step;
        self::$maxCache = $maxLevel;
        self::$colorCache = $color;
    }

    /** Vide le cache de requête — les tests changent les réglages en cours de route. */
    public static function clearCache(): void
    {
        self::$stepCache = null;
        self::$maxCache = null;
        self::$colorCache = null;
    }
}
