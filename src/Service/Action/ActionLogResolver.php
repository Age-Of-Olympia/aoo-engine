<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\ActionPassive;
use App\Entity\ActionTypeLog;
use App\Service\RecipeService;
use Classes\Item;
use Classes\Player;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds an action's log lines from the type-level templates
 * ({@see ActionTypeLog}) — the data-driven replacement for the per-subclass
 * getLogMessages() methods.
 *
 * The template is taken from the closest type in the action's class ancestry
 * that has a row (e.g. a spell has no "spell" row → it inherits "technique").
 * Placeholders: {actor}, {target}, {action} (display name), {weapon} (the
 * " avec <arme>" clause, empty for animals / bare hands) and {item} (the item
 * picked at execution, else what the posted recipe makes). A type with no
 * configured template produces no log line — the data-driven replacement for the
 * removed per-subclass getLogMessages().
 */
final class ActionLogResolver
{
    private TypeConfigLocator $locator;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?TypeConfigLocator $locator = null,
    ) {
        $this->locator = $locator ?? new TypeConfigLocator($entityManager, $registry);
    }

    /**
     * @return array{actor: string, target: string}
     */
    public function resolve(Action $action, Player $actor, Player $target, ?Item $pickedItem = null): array
    {
        $config = $this->configFor($action);
        if ($config === null) {
            return ['actor' => '', 'target' => '']; // no configured template -> no log line
        }

        return [
            'actor' => $this->render($config->getActorTemplate(), $action, $actor, $target, $pickedItem),
            'target' => $this->render($config->getTargetTemplate(), $action, $actor, $target, $pickedItem),
        ];
    }

    /**
     * The ActionTypeLog of the closest type in the action's ancestry, or null.
     */
    private function configFor(Action $action): ?ActionTypeLog
    {
        return $this->locator->closest($action, ActionTypeLog::class, 'log');
    }

    /**
     * A passive's journal line: {actor} is the passive's holder, {target} the
     * other party, {passive} its display name; {action} is empty outside an action.
     */
    public function renderPassive(?string $template, ActionPassive $passive, Player $holder, Player $other, ?Action $action = null): string
    {
        return str_replace('{passive}', $passive->getDisplayName(), $this->render($template, $action, $holder, $other));
    }

    public function render(?string $template, ?Action $action, Player $actor, Player $target, ?Item $pickedItem = null): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return strtr($template, [
            '{actor}' => (string) ($actor->data->name ?? ''),
            '{target}' => (string) ($target->data->name ?? ''),
            '{action}' => $action !== null ? $this->displayName($action) : '',
            '{weapon}' => $this->weaponClause($actor),
            // The item picked at execution (ItemPick), else what the posted recipe makes.
            '{item}' => match (true) {
                !str_contains($template, '{item}') => '',
                $pickedItem !== null => (string) ($pickedItem->data->name ?? ''),
                default => $this->craftedItems(),
            },
        ]);
    }

    /** "Épée (x1), Copeaux (x2)": what the posted recipe makes, as the craft outcome reads it. */
    private function craftedItems(): string
    {
        $recipeId = $_POST['recipeId'] ?? null;
        $recipe = is_numeric($recipeId) ? (new RecipeService())->getRecipeById((int) $recipeId) : null;
        if ($recipe === null) {
            return '';
        }

        $parts = [];
        foreach ($recipe->getRecipeResults() as $result) {
            $parts[] = (new Item((int) $result->getItem()->getId()))->get_data()->name . ' (x' . $result->getCount() . ')';
        }

        return implode(', ', $parts);
    }

    /**
     * displayName is NOT NULL, but a legacy row can leave the typed property
     * uninitialized (throws on read) — guard it like the rest of the workbench.
     */
    private function displayName(Action $action): string
    {
        $property = new \ReflectionProperty(Action::class, 'displayName');

        return $property->isInitialized($action) ? (string) $property->getValue($action) : '';
    }

    /**
     * The " avec <arme>" fragment for the actor's main hand. Empty for animals
     * (which fight bare) and when nothing is equipped — matching the old
     * AttackAction::getLogMessages weapon handling.
     */
    private function weaponClause(Player $actor): string
    {
        if (($actor->data->race ?? '') === 'animal') {
            return '';
        }
        $main1 = $actor->emplacements->main1 ?? null;
        if ($main1 === null || !isset($main1->data->name)) {
            return '';
        }

        return ' avec ' . $main1->data->name;
    }
}
