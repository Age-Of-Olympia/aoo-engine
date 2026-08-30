<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\ActionInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;

class ActionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a Action entity that matches the given type, or null if not found.
     */
    public function getActionByName(string $name): ?ActionInterface
    {
        $result = null;
        $type = $this->getType($name);
        if ($type != "") {
            $result = $this->getAction($type, $name);
            $result->setOrmType($type);
        }

        return $result;
    }

    private function getType($name)
    {
        $rsm1 = new ResultSetMapping();
        
        // Map only the 'type' field
        $rsm1->addScalarResult('type', 'type');
        
        // Define the native SQL query
        $sql = 'SELECT type FROM actions where name = :name';
        
        // Execute the native query with the ResultSetMapping
        $query1 = $this->entityManager->createNativeQuery($sql, $rsm1);
        $query1->setParameter("name", $name);
        try {
            $type = $query1->getSingleScalarResult();
        } catch (NoResultException $e) {
            $type = "";
        }

        return $type;
    }

    private function getAction($type, $name) : ActionInterface
    {
        $className = ucfirst(strtolower($type)) . 'Action';
        
        $query2 = $this->entityManager->createQuery(
            'SELECT action FROM App\\Action\\'.$className.' action where action.name = :name'
        );
        $query2->setParameter("name", $name);
        
        $result = $query2->getSingleResult();

        return $result;
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
