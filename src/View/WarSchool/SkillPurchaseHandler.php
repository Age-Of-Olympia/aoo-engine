<?php

namespace App\View\WarSchool;

use App\Service\ActionPassiveService;
use App\Service\ActionService;
use App\Service\WarSchool\SkillPrerequisiteService;
use Classes\Player;

/**
 * The one buy path of the war-school tree pages. Every guard the button
 * greys out on is enforced here again, so a forged POST buys nothing the
 * interface would refuse. Responses keep the pages' AJAX contract: a
 * single <div id="data"> then exit.
 */
final class SkillPurchaseHandler
{
    public static function handlePost(Player $player, SkillPrerequisiteService $prereqs): void
    {
        if (empty($_POST['buySkillId']) && empty($_POST['buyPassiveId'])) {
            return;
        }

        if (ob_get_length()) {
            ob_clean();
        }

        $type = !empty($_POST['buyPassiveId']) ? 'passive' : 'active';
        $skillName = $_POST['buyPassiveId'] ?? $_POST['buySkillId'];

        $skill = ($type === 'active')
            ? (new ActionService())->getActionByName($skillName)
            : (new ActionPassiveService())->getActionPassiveByName($skillName);

        if ($skill === null) {
            self::answer('Erreur : Compétence introuvable.');
        }

        // Spells are capped by their slots (a prerequisite below), the
        // others by the shared competence count.
        $isSpell = ($type === 'active' && SkillPrerequisiteService::tree($skill->getCategory()) === 'spell');
        if (!$isSpell && $prereqs->isFull()) {
            self::answer('Limite de compétences atteinte (max ' . NUMBER_MAX_COMP . ') !');
        }

        if ($prereqs->owns($skillName)) {
            self::answer('Compétence déjà connue.');
        }

        $skillRace = $skill->getRace();
        if (!empty($skillRace) && $player->data->race != $skillRace) {
            self::answer('Cette compétence est réservée à une autre race.');
        }

        $usable = ($type === 'active') ? $prereqs->isUsable($skill) : $prereqs->isPassiveUsable($skill);
        if (!$usable) {
            self::answer('Pré-requis non remplis pour apprendre cette compétence.');
        }

        $price = ($type === 'active')
            ? (new ActionService())->getPrice($skill->getLevel())
            : (new ActionPassiveService())->getPrice($skill->getLevel());

        if (!$player->spendGold($price)) {
            self::answer('Or insuffisant !');
        }

        if ($type === 'active') {
            $player->add_action($skillName);
        } else {
            $player->add_action_passive($skillName);
        }

        self::answer('Compétence apprise !');
    }

    /**
     * The buy cell of a tree row — one rendering for the six pages.
     * @param bool $isFull the cap that applies to THIS skill (competence
     *                     count, or the spell slot of its level)
     */
    public static function buyButton(
        string $name,
        string $type,
        int $price,
        int $playerGold,
        bool $alreadyLearned,
        bool $isRaceLearnable,
        bool $isFull,
        bool $hasPrerequisites
    ): string {
        if ($alreadyLearned) {
            return '<button class="create" disabled>Déjà apprise</button>';
        }

        if (!$isRaceLearnable) {
            return '<button class="create" disabled>Impossible à apprendre</button>';
        }

        $disabled = (($playerGold < $price) || $isFull || !$hasPrerequisites) ? 'disabled' : '';

        if ($isFull) {
            $btnText = 'Max atteint';
        } elseif (!$hasPrerequisites) {
            $btnText = 'Pré-requis manquants';
        } else {
            $btnText = 'Acheter : ' . $price . ' Po';
        }

        return '<button class="create buy-skill-btn" data-id="' . $name . '" data-type="' . $type . '" ' . $disabled . '>' . $btnText . '</button>';
    }

    private static function answer(string $message): never
    {
        echo '<div id="data">' . $message . '</div>';
        exit;
    }
}
