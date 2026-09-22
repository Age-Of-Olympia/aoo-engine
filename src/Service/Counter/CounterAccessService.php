<?php

namespace App\Service\Counter;

use App\Service\BuildingService;
use App\Service\EffectService;
use Classes\Player;
use Classes\View;

/**
 * La garde d'accès à un comptoir, écran et onglet : le bâtiment sert-il
 * ce comptoir, est-il ouvert, le joueur est-il à côté, un effet
 * l'empêche-t-il de commercer ?
 *
 * Une seule réponse pour l'écran, le corps de page et les API d'écriture :
 * une URL directe ne contourne rien. Les refus viennent du catalogue,
 * l'école de guerre et le marchand ne disent pas la même phrase.
 */
final class CounterAccessService
{
    private BuildingService $buildings;

    private EffectService $effects;

    public function __construct(?BuildingService $buildings = null, ?EffectService $effects = null)
    {
        $this->buildings = $buildings ?? new BuildingService();
        $this->effects = $effects ?? new EffectService();
    }

    /**
     * Le refus, ou null quand la voie est libre.
     *
     * @param string|null $tab l'onglet demandé ; null = l'écran seulement
     */
    public function check(Player $player, Player $target, string $script, ?string $tab = null): ?string
    {
        $messages = CounterCatalog::screens()[$script] ?? null;
        if ($messages === null) {
            throw new \InvalidArgumentException("Écran de comptoir inconnu : '{$script}'.");
        }

        $targetId = (int) $target->id;
        if (!$this->buildings->servesCounter($targetId, $script)) {
            return $messages['notServed'];
        }

        $closedNotice = $this->buildings->closedCounterNotice($target);
        if ($closedNotice !== null) {
            return $closedNotice;
        }

        // Distance à la case la plus proche : une bâtisse multi-cases sert
        // par chacun de ses côtés.
        if (View::get_distance_to_entity($player->getCoords(), $targetId, $target->getCoords()) > 1) {
            return ERROR_DISTANCE;
        }

        if ($blocker = $this->effects->tradingBlocker($player->getEffects())) {
            return sprintf($messages['selfBlocked'], $blocker->getLabel());
        }

        if ($blocker = $this->effects->tradingBlocker($target->getEffects())) {
            return sprintf($messages['targetBlocked'], $blocker->getLabel());
        }

        if ($tab !== null && !$this->buildings->servesCounter($targetId, $script, $tab)) {
            return $messages['wrongTab'];
        }

        return null;
    }

    /**
     * Les onglets de cet écran que le bâtiment sert, dans l'ordre du menu.
     *
     * @return array<int, string>
     */
    public function servedTabs(int $targetId, string $script): array
    {
        $served = [];
        foreach (array_keys(CounterCatalog::tabs($script)) as $tab) {
            if ($this->buildings->servesCounter($targetId, $script, (string) $tab)) {
                $served[] = (string) $tab;
            }
        }

        return $served;
    }
}
