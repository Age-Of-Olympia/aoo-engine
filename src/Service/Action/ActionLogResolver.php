<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\ActionTypeLog;
use App\Entity\EntityManagerFactory;
use Classes\Player;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds an action's log lines from the type-level templates
 * ({@see ActionTypeLog}) — the data-driven replacement for the per-subclass
 * getLogMessages() methods.
 *
 * The template is taken from the closest type in the action's class ancestry
 * that has a row (e.g. a spell has no "spell" row → it inherits "technique").
 * Placeholders: {actor}, {target}, {action} (display name) and {weapon} (the
 * " avec <arme>" clause, empty for animals / bare hands). A type with no
 * configured template produces no log line — the data-driven replacement for the
 * removed per-subclass getLogMessages().
 */
final class ActionLogResolver
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ActionTypeRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
    }

    /**
     * @return array{actor: string, target: string}
     */
    public function resolve(Action $action, Player $actor, Player $target): array
    {
        $config = $this->configFor($action);
        if ($config === null) {
            return ['actor' => '', 'target' => '']; // no configured template -> no log line
        }

        return [
            'actor' => $this->render($config->getActorTemplate(), $action, $actor, $target),
            'target' => $this->render($config->getTargetTemplate(), $action, $actor, $target),
        ];
    }

    /**
     * The ActionTypeLog of the closest type in the action's ancestry, or null.
     */
    private function configFor(Action $action): ?ActionTypeLog
    {
        $keys = $this->registry->typeKeysForAction($action);
        if ($keys === []) {
            return null;
        }

        /** @var array<int, ActionTypeLog> $rows */
        $rows = $this->entityManager->getRepository(ActionTypeLog::class)->findBy(['typeKey' => $keys]);
        if ($rows === []) {
            TypeConfigWarning::once('log', $keys);
            return null;
        }

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->getTypeKey()] = $row;
        }
        // typeKeysForAction is closest-first, so the first hit is the most specific.
        foreach ($keys as $key) {
            if (isset($byKey[$key])) {
                return $byKey[$key];
            }
        }

        return null;
    }

    public function render(?string $template, Action $action, Player $actor, Player $target): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return strtr($template, [
            '{actor}' => (string) ($actor->data->name ?? ''),
            '{target}' => (string) ($target->data->name ?? ''),
            '{action}' => $this->displayName($action),
            '{weapon}' => $this->weaponClause($actor),
        ]);
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
