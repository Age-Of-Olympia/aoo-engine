<?php

namespace App\Service;

use RuntimeException;

/**
 * L'assombrissement des cases — réglé PAR PLAN, avec un défaut global.
 *
 * `coords.shade` porte un NIVEAU (0 à N) ; ce service dit ce qu'un niveau
 * vaut à l'écran. Les deux sont séparés exprès : changer l'apparence des
 * ombres ne doit pas demander de reprendre les 7 613 cases qui en portent
 * une.
 *
 * # Trois étages, du plus précis au plus général
 *
 * 1. **Le plan** (table `plans`, colonnes `shade_*`, éditées depuis
 *    admin → Plans et depuis Tiled). C'est le bon niveau : une grotte
 *    se veut plus sombre qu'une plaine, un plan de glace plus bleu. L'ombre
 *    est un réglage de CARTE.
 * 2. **Le tableau de bord admin** (`admin_settings`) : le défaut de tous les
 *    plans qui ne disent rien, pour n'avoir pas à régler quarante cartes.
 * 3. **Le code**, en dernier recours — les valeurs qui reproduisent
 *    exactement l'ancien décor empilé.
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

    /**
     * Défauts globaux lus une fois par requête. Les surcharges de plan, elles,
     * sont mémorisées par plan : un rendu ne montre qu'une carte, mais la
     * console et l'admin en parcourent plusieurs.
     *
     * @var array<string, array{step: float, max: int, color: string}>
     */
    private static array $planCache = [];

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

    /**
     * Les trois réglages effectifs d'un plan : sa surcharge si elle existe,
     * sinon le défaut global.
     *
     * @return array{step: float, max: int, color: string}
     */
    public function forPlan(?string $plan): array
    {
        $key = (string) $plan;

        if (!isset(self::$planCache[$key])) {
            /* `plans()` est une fonction globale du bootstrap hérité
             * (config/functions.php). Hors de ce contexte — console isolée,
             * test unitaire — il n'y a tout simplement pas de config de plan
             * à lire, donc pas de surcharge : le défaut global s'applique. */
            $json = ($plan === null || !function_exists('plans'))
                ? null
                : plans()->read($plan);

            $step  = isset($json->shade_step) ? (float) $json->shade_step : $this->step();
            $max   = isset($json->shade_max) ? (int) $json->shade_max : $this->maxLevel();
            $color = isset($json->shade_color) ? (string) $json->shade_color : $this->color();

            self::$planCache[$key] = [
                'step'  => ($step > 0 && $step < 1) ? $step : $this->step(),
                'max'   => ($max >= 1 && $max <= 255) ? $max : $this->maxLevel(),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $this->color(),
            ];
        }

        return self::$planCache[$key];
    }

    /**
     * L'opacité d'un niveau SUR UN PLAN donné.
     *
     * C'est la forme que le rendu utilise : la carte affichée est celle d'un
     * plan, et c'est lui qui décide de la profondeur de ses ombres.
     */
    public function opacityOnPlan(?string $plan, int $level): float
    {
        if ($level < 1) {
            return 0.0;
        }

        $config = $this->forPlan($plan);

        return round(1 - pow(1 - $config['step'], min($level, $config['max'])), 4);
    }

    /** Opacité d'UN niveau, entre 0 et 1 — défaut global, tous plans confondus. */
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
        self::$planCache = [];
    }
}
