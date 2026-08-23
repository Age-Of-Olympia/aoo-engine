<?php

namespace App\Service;

/**
 * Règle unique des déclencheurs posables : ceux dont quelque chose se sert.
 *
 * Deux familles, et c'est le piège de cette classe :
 *
 *  - ceux du PAS. `go.php` inclut `scripts/map/triggers/<nom>.php` quand un
 *    joueur entre sur la case ; sans ce fichier, le déclencheur ne fait rien.
 *  - ceux que lit AUTRE CHOSE. `grow` n'a pas de gestionnaire et n'en a
 *    jamais eu : c'est un point de pousse, relu chaque nuit par le cron des
 *    plantes ({@see PlantsService::getTriggerGrow}), avec le nom de la plante
 *    en params. `go.php` l'écarte justement parce qu'il ne se déclenche pas au
 *    pas.
 *
 * Les deux palettes se construisaient en listant `img/triggers/*.png`, et
 * l'image survit au retrait du code : `altar` (déclencheur retiré quand
 * l'autel est devenu bâtiment + actions), `enter` et `exit` (voyage
 * inter-plans mort-né) restaient proposés aux animateurs. La palette suit
 * maintenant les consommateurs ; les lignes déjà posées, elles, restent
 * pullées et affichées, pour qu'on puisse les voir et les retirer.
 */
final class TriggerPaletteService
{
    /** Les gestionnaires qu'inclut go.php. */
    private const HANDLER_DIR = __DIR__ . '/../../scripts/map/triggers';

    /**
     * Déclencheurs sans gestionnaire, mais lus ailleurs — donc posables.
     *
     * `grow` : points de pousse des plantes, relus par
     * scripts/crons/daily/25_grow_crops_trigger.php. Sa disparition d'ici
     * retirerait aux animateurs le seul moyen de semer une plante.
     */
    private const OTHER_CONSUMERS = ['grow'];

    /** @var list<string>|null */
    private static ?array $names = null;

    /** Le jeu se sert-il de ce déclencheur, d'une façon ou d'une autre ? */
    public static function isKnown(string $name): bool
    {
        return in_array($name, self::playableNames(), true);
    }

    /** Se déclenche-t-il au pas, c'est-à-dire go.php a-t-il un fichier pour lui ? */
    public static function isStepTrigger(string $name): bool
    {
        return in_array($name, self::stepTriggerNames(), true);
    }

    /**
     * Les déclencheurs posables, triés : ceux du pas et les autres.
     *
     * @return list<string>
     */
    public static function playableNames(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $names = array_values(array_unique(array_merge(
            self::stepTriggerNames(),
            self::OTHER_CONSUMERS
        )));

        sort($names);

        return self::$names = $names;
    }

    /**
     * Les déclencheurs que go.php sait exécuter.
     *
     * @return list<string>
     */
    public static function stepTriggerNames(): array
    {
        $names = [];

        foreach (glob(self::HANDLER_DIR . '/*.php') ?: [] as $handler) {
            $names[] = pathinfo($handler, PATHINFO_FILENAME);
        }

        sort($names);

        return $names;
    }

    /**
     * Filtre une liste de noms sur ceux dont le jeu se sert.
     *
     * @param string[] $names
     * @return list<string>
     */
    public static function filterNames(array $names): array
    {
        return array_values(array_filter($names, self::isKnown(...)));
    }

    /** Test seam: la liste se recalcule au prochain appel. */
    public static function forget(): void
    {
        self::$names = null;
    }
}
