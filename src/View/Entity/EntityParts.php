<?php

namespace App\View\Entity;

use App\Entity\BuildingDetails;
use App\Service\BuildingService;
use App\Service\ContainerService;
use App\Service\PlayerEffectService;
use Classes\Ui;

/**
 * The pieces the tile card and the sheet share, drawn from one
 * EntityProfile: effects, factions, state, owner, contents. A piece
 * changed here changes on every view of an entity.
 */
final class EntityParts
{
    /** Effect icons beside the name (card). */
    public static function effectIconsHtml(EntityProfile $profile): string
    {
        $html = '';
        foreach ($profile->effects() as $effect) {
            $html .= ' <a href="infos.php?targetId=' . $profile->id() . '"><span class="ra '
                . $profile->target->effectService->getIcon($effect->getName()) . '"></span></a>';
        }

        return '<div class="effects">' . $html . '</div>';
    }

    /** Effects with their strength, what they do and, to those allowed, when they end (sheet). */
    public static function effectsListHtml(EntityProfile $profile): string
    {
        $effectService = new \App\Service\EffectService();
        $withTimers = $profile->seesEffectTimers();

        $html = '';
        foreach ($profile->effects() as $effect) {
            $endTime = $withTimers ? PlayerEffectService::describeRemaining($effect->getEndTime()) : '';
            $what = $effectService->describe($effect->getName(), (int) ($effect->getValue() ?? 1), false);

            $html .= '<a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets#' . $effect->getName() . '" title="'
                . htmlspecialchars(ucfirst($effect->getName()) . ($what !== '' ? ' : ' . $what : ''), ENT_QUOTES) . '">'
                . '<span class="ra ' . $effectService->getIcon($effect->getName()) . '"></span>'
                . '<span style="font-size: 88%;">(' . $effect->getValue() . ') ' . $endTime . ($what !== '' ? ' · ' . $what : '') . '</span></a><br />';
        }

        return $html;
    }

    /** Faction crests, public then secret (card). */
    public static function factionIconsHtml(EntityProfile $profile): string
    {
        $html = '';
        foreach ($profile->factions() as $faction) {
            if (isset($faction['json']->raFont)) {
                $html .= '<a href="faction.php?faction=' . $faction['code'] . '"><span class="ra '
                    . $faction['json']->raFont . '"></span></a>';
            }
        }

        return $html;
    }

    /** Faction name, crest and rank, one line each (character sheet). */
    public static function factionLinesHtml(EntityProfile $profile): string
    {
        $html = '';
        foreach ($profile->factions() as $faction) {
            $rank = $faction['json']->role[$faction['role']]->name ?? '';
            $html .= '<div' . ($faction['secret'] ? ' class="secret-faction"' : '') . '>'
                . '<a href="faction.php?faction=' . $faction['code'] . '">' . $faction['json']->name . '</a>'
                . ' <span style="font-size: 1.3em" class="ra ' . ($faction['json']->raFont ?? '') . '"></span>'
                . ($rank !== '' ? ' (<i>' . htmlspecialchars((string) $rank, ENT_QUOTES, 'UTF-8') . '</i>)' : '')
                . ' </div>';
        }

        return $html;
    }

    /**
     * The state pastille of a building or a lockable thing: Ouvert/Fermé
     * for what can be shut, the build state of a building, and its PV.
     */
    public static function statusHtml(EntityProfile $profile): string
    {
        if ($profile->details === null && !$profile->isLockable) {
            return '';
        }

        $state = '';
        if ($profile->details !== null) {
            $labels = [
                BuildingDetails::STATE_BUILT => 'Construit',
                BuildingDetails::STATE_CONSTRUCTION => 'En construction',
                BuildingDetails::STATE_RUIN => 'Ruine',
            ];
            $state = $labels[$profile->details->getBuildState()] ?? ucfirst($profile->details->getBuildState());

            $progress = (new \App\Service\ConstructionSiteService())->progressOf($profile->id());
            if ($progress !== null) {
                $state .= ' (' . $progress['done'] . '/' . $progress['total'] . ')';
            }
            $state .= ' · ';
        }

        $door = '';
        if ($profile->isLockable) {
            $door = $profile->closure === null
                ? '<span class="building-status-door building-status-door--open">Ouvert</span>'
                : '<span class="building-status-door building-status-door--closed">Fermé'
                    . ($profile->closure !== BuildingService::CLOSED_BY_HAND ? ' (' . $profile->closure . ')' : '') . '</span>';
        }

        return '<div class="building-status' . ($profile->isLockable && $profile->closure !== null ? ' building-status--closed' : '') . '">'
            . $door
            . '<span class="building-status-state">' . $state . 'PV ' . (int) $profile->pvPct . '%</span></div>';
    }

    /** Owner and faction of a structure (sheet). */
    public static function ownerHtml(EntityProfile $profile): string
    {
        if (!$profile->isStructure) {
            return '';
        }

        $html = '';
        $owner = $profile->owner();
        if ($owner !== null) {
            $html .= '<p><small>Propriétaire : <a href="infos.php?targetId=' . $owner->id . '">'
                . htmlspecialchars((string) $owner->data->name, ENT_QUOTES, 'UTF-8') . '</a></small></p>';
        }
        $crest = self::factionIconsHtml($profile);

        return $html . ($crest !== '' ? '<p><small>Faction : ' . $crest . '</small></p>' : '');
    }

    /**
     * What a container holds, drawn like the bag (read-only), or why it
     * is not shown. Nothing for what holds nothing, or is shut (the
     * state already says it).
     */
    public static function contentsHtml(EntityProfile $profile): string
    {
        if (!$profile->isContainer || $profile->closure !== null) {
            return '';
        }

        if (!$profile->visibility->seesContentsOf($profile->id())) {
            return '<div class="building-status" style="margin: 14px auto; text-align: center;">'
                . '<span class="building-status-state">Approchez-vous pour voir ce qu\'il contient.</span></div>';
        }

        $container = new ContainerService();
        $capacity = $container->capacityOf($profile->id());

        return Ui::print_inventory(
            \Classes\Item::get_item_list($profile->id()),
            bagLabel: 'Contenu (' . $container->lineCountOf($profile->id())
                . ($capacity !== null ? '/' . $capacity : '') . ' lignes)'
        )
            // Swaps in the item images, as on the bag (no inventory.js: no Use/Drop here).
            . '<script src="js/progressive_loader.js?v=20260716"></script>';
    }
}
