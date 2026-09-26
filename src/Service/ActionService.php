<?php

namespace App\Service;

use App\Database\QueryCounter;
use App\Entity\Action;
use App\Factory\EntityManagerFactory;
use App\Service\Action\ActionTypeDiscovery;
use App\Interface\ActionInterface;

class ActionService
{
    private $entityManager;

    /**
     * Actions already read in this request, by name (null = no such action).
     * A card lists a dozen actions and asks for each more than once; any
     * write since the read may have changed one, so the map is dropped then.
     *
     * @var array<string, ?ActionInterface>
     */
    private static array $byName = [];
    private static int $readAtWrites = -1;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /** The action named so, hydrated as its concrete subclass, or null. */
    public function getActionByName(string $name): ?ActionInterface
    {
        $this->forgetIfWritten();
        if (!array_key_exists($name, self::$byName)) {
            $action = $this->entityManager->getRepository(Action::class)->findOneBy(['name' => $name]);
            if ($action !== null) {
                $action->setOrmType(ActionTypeDiscovery::typeOf($action));
            }
            self::$byName[$name] = $action;
        }

        return self::$byName[$name];
    }

    /**
     * Reads these actions in one query, with their outcomes and conditions,
     * for the next getActionByName() calls.
     *
     * @param list<string> $names
     */
    public function preload(array $names): void
    {
        $this->forgetIfWritten();
        $missing = array_values(array_diff($names, array_keys(self::$byName)));
        if ($missing === []) {
            return;
        }

        $actions = $this->entityManager->createQuery(
            'SELECT a, o, c FROM ' . Action::class . ' a LEFT JOIN a.outcomes o LEFT JOIN a.actionConditions c WHERE a.name IN (:names)'
        )->setParameter('names', $missing)->getResult();

        foreach ($missing as $name) {
            self::$byName[$name] = null;
        }
        foreach ($actions as $action) {
            $action->setOrmType(ActionTypeDiscovery::typeOf($action));
            self::$byName[$action->getName()] = $action;
        }
    }

    private function forgetIfWritten(): void
    {
        if (self::$readAtWrites !== QueryCounter::writes()) {
            self::$byName = [];
            self::$readAtWrites = QueryCounter::writes();
        }
    }

    public function getCostsArray(?string $actionName, ?ActionInterface $action) : array {
        return array_map(
            fn(array $part) => $part['text'],
            $this->getCostParts($actionName, $action)
        );
    }

    /**
     * The action's cost, derived from its RequiresTraitValue condition — the
     * same parameters the executor actually charges, so the display can never
     * drift from the real cost. Each entry is ['trait' => carac key,
     * 'text' => label], e.g. ['trait' => 'pm', 'text' => '10 PM'].
     *
     * Mirrors the parameter shapes of RequiresTraitValueCondition:
     * - numeric ({"pm":10}): flat cost
     * - "remaining" ({"remaining":"a"}): spends everything left
     * - "imposture" ({"imposture":[pm,mvt]}): base scaled by the actor's
     *   imposture stacks + 1, shown as a multiplier; such parts carry
     *   'effect' => 'imposture' so HTML renderers can show the effect icon
     *
     * @return array<int, array{trait: string, text: string, effect?: string}>
     */
    public function getCostParts(?string $actionName, ?ActionInterface $action) : array {
        if (!isset($action)) {
            $action = $this->getActionByName($actionName);
        }
        foreach ($action->getConditions() as $condition) {
            if ($condition->getConditionType() != 'RequiresTraitValue') {
                continue;
            }
            $parts = array();
            foreach ($condition->getParameters() as $key => $value) {
                if ($key == "energie") {
                    continue;
                }
                if ($key == "remaining" && isset(CARACS[$value])) {
                    $parts[] = ['trait' => $value, 'text' => 'Toutes les ' . CARACS[$value] . ' restantes'];
                } elseif ($key == "imposture" && is_array($value)) {
                    $parts[] = ['trait' => 'pm', 'text' => $this->formatMultiplier($value[0]) . 'x(+1) PM', 'effect' => 'imposture'];
                    $parts[] = ['trait' => 'mvt', 'text' => $this->formatMultiplier($value[1]) . 'x(+1) Mvt', 'effect' => 'imposture'];
                } elseif (is_numeric($value) && isset(CARACS[$key])) {
                    $parts[] = ['trait' => $key, 'text' => $value . ' ' . CARACS[$key]];
                }
            }
            return $parts;
        }
        return array();
    }

    /**
     * "2" for 2, "1/2" for 0.5 — matches how the imposture-scaled stealth
     * costs have always been shown to players.
     */
    private function formatMultiplier(float $value): string
    {
        if ($value > 0 && $value < 1) {
            return '1/' . (int) round(1 / $value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function getPrice($level) : int
        {

        switch ($level) {
            case 1:
                return 50;
            case 2:
                return 100;
            case 3:
                return 200;
            case 4:
                return 300;
            case 5:
                return 300;
            default:
                return 50;
        }
    }

    public function getActionsByCategory(string $category): array
    {
        $query = $this->entityManager->createQuery(
        'SELECT a FROM App\Entity\Action a
         WHERE a.category LIKE :cat
         ORDER BY a.level ASC, a.name ASC'
        )
        ->setParameter('cat', $category . '%');

        return $query->getResult();
    }

    /**
     * Every action name the game knows, for admin pickers/autocomplete:
     * the configured actions (with their type as label) plus the legacy
     * names that only exist as granted rows or race-list entries, sans
     * ligne dans `actions`.
     *
     * Merged in PHP: the four tables carry mixed collations, a SQL UNION
     * on them throws.
     *
     * @return array<string, string> name => type label ('' when unknown)
     */
    public function getKnownActionNames(): array
    {
        $connection = $this->entityManager->getConnection();

        $names = [];
        foreach ($connection->fetchAllAssociative('SELECT name, type FROM actions') as $row) {
            $names[$row['name']] = (string) $row['type'];
        }

        $legacySources = [
            'SELECT DISTINCT name FROM players_actions',
            'SELECT DISTINCT name FROM race_starter_actions',
            'SELECT DISTINCT name FROM race_spells',
        ];
        foreach ($legacySources as $sql) {
            foreach ($connection->fetchFirstColumn($sql) as $name) {
                $names[$name] ??= '';
            }
        }

        ksort($names);

        return $names;
    }

    /**
     * Tree data of the whole catalog in one query: name => category + level.
     * @return array<string, array{category: ?string, level: int}>
     */
    /**
     * The learned combat skills an item may carry, name => display name.
     *
     * The same classes PlayerActionsService marks as `type = 'sort'` when a
     * player learns one — so an item grants exactly what a war school could
     * teach, and nothing else (no `attaquer`, no `fouiller`).
     *
     * @return array<string, string>
     */
    public function getCastableSpellNames(): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT a.name, a.displayName FROM App\Entity\Action a
              WHERE a INSTANCE OF App\Action\SpellAction
                 OR a INSTANCE OF App\Action\BuffAction
                 OR a INSTANCE OF App\Action\HealAction
                 OR a INSTANCE OF App\Action\TechniqueAction
              ORDER BY a.name ASC'
        )->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[$row['name']] = (string) ($row['displayName'] ?: $row['name']);
        }

        return $names;
    }

    /**
     * Every action as name => display name.
     *
     * @return array<string, string>
     */
    public function getAllNames(): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT a.name, a.displayName FROM App\Entity\Action a'
        )->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[$row['name']] = (string) ($row['displayName'] ?: $row['name']);
        }

        return $names;
    }

    public function getCatalogMeta(): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT a.name, a.category, a.level FROM App\Entity\Action a'
        )->getArrayResult();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['name']] = ['category' => $row['category'], 'level' => (int) $row['level']];
        }

        return $meta;
    }
}
