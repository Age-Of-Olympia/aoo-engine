<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\ActionInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;
use App\Service\PlayerActionsService;
use App\Service\PlayerPassiveService;
use App\Service\ActionPassiveService;

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

    public function isActionUsable(int $playerId, string $actionName): bool
    {
        // 1) Récupération de l'action
        $action = $this->getActionByName($actionName);
        if ($action === null) {
            return false;
        }

        // 2) On récupère la liste des actions/passifs du personnage
        $skillList = $this->getSkillList($playerId);

        //3) On vérifie si les pré-requis par défaut sont respectés
        // Les pré-requis sont de la forme { "need": ["berserker"], "forbidden": ["voie_air","voie_terre"] }
        if (!$this->checkDefaultPrerequisites($playerId, $action, $skillList)) {
            return false;
        }

        //4) On regarde si le pré-requis spécifiques sont remplis
        $prerequisites = $action->getPrerequisites();
        $prerequisites = json_decode($prerequisites ?? '', true);
        
        $neededSkills = $prerequisites['need'] ?? [];
        $forbiddenSkills = $prerequisites['forbidden'] ?? [];

        //4a) Compétences/Passifs nécessaires
        if (!$this->checkNeededPrerequisites($skillList, $neededSkills)) {
            return false;
        }

        //4b) Compétences/Passifs interdits
        if (!$this->checkForbiddenPrerequisites($skillList, $forbiddenSkills)) {
            return false;
        }

        return true;
    }

    public function checkDefaultPrerequisites(int $playerId, ActionInterface $action, array $skillList): bool
    {
        //1) On regarde si l'action est d'un arbre principal ou secondaire
        $treeType = $this->getTreeType($action->getCategory());
        
        //2) On regarde si le pré-requis de nombre de compétences/sorts est rempli
        $defaultPrerequisites = $this->isChildNodesPrerequisitesOK($playerId,$action,$treeType,$skillList);
        
        return $defaultPrerequisites;
    }

    public function checkNeededPrerequisites(array $skillList, array $neededSkills): bool
    {
        $flatNames = array_column($skillList, 'name');

        foreach ($neededSkills as $need) {
            if (!in_array($need, $flatNames, true)) {
                return false;
            }
        }
        return true;
    }

    public function checkForbiddenPrerequisites(array $skillList, array $forbiddenSkills): bool
    {
        $flatNames = array_column($skillList, 'name');

        foreach ($forbiddenSkills as $forbid) {
            if (in_array($forbid, $flatNames, true)) {
                return false;
            }
        }
        return true;
    }

    public function getTreeType(string $category): string
    {

        // On extrait le nom de l'arbre
        $treeName = $this->extractTreeFromCategory($category);

        //On déduit du nom de l'arbre si c'est un arbre primaire (type de combat) ou secondaire
        if (in_array($treeName, ['melee', 'distance', 'magic'], true)) {
            return 'primary'; 
        }

        if (in_array($treeName, ['survival', 'stealth'], true)) {
            return 'secondary'; 
        }

        return 'spell';
    }

    public function extractTreeFromCategory(?string $category): string
    {
        if (empty($category)) {
            return '';
        }
        return explode('-', $category)[0];
    }

    public function getSkillList(int $playerId): array
    {
        $playerActionsService = new PlayerActionsService();
        $playerPassiveService = new PlayerPassiveService();
        
        $mergedList = [];
        foreach ($playerActionsService->getActions($playerId) as $name) {
            $mergedList[] = ['name' => $name, 'type' => 'action'];
        }
        foreach ($playerPassiveService->getPassivesByPlayerId($playerId) as $name) {
            $mergedList[] = ['name' => $name, 'type' => 'passive'];
        }

        return $mergedList;
    }

    private function isChildNodesPrerequisitesOK(int $playerId, ActionInterface $action, string $treeType, array $skillList): bool
    {
        $actionPassiveService = new ActionPassiveService();
        $actionTree = $this->extractTreeFromCategory($action->getCategory());

        $countSkills = array_fill(1, SKILL_MAX_LEVEL, 0);
        $countSpells = array_fill(1, SPELL_MAX_LEVEL, 0);

        foreach ($skillList as $skill) {
            $skillName = $skill['name'];

            if ($skill['type'] === 'action') {
                $sk = $this->getActionByName($skillName);
            } else {
                $sk = $actionPassiveService->getActionPassiveByName($skillName);
            }

            if ($sk === null) {
                continue;
            }

            $category = $sk->getCategory();
            if (empty($category)) {
                continue; 
            }

            $skillTree = $this->extractTreeFromCategory($category);
            
            if ($skillTree === $actionTree) {
                // Potentiellement à ajouter plus tard si on met l'activation/désactivation des compétences
                // Check si l'action est activée ou non
                $levelIndex = $sk->getLevel() - 1;
                
                if ($skill['type'] === 'action' && $skillTree === 'spell') {
                    $countSpells[$levelIndex]  += 1;
                } else {
                    $countSkills[$levelIndex] += 1;
                }
            }
        }

        $n = 1;
        switch ($treeType) {
            case 'primary':
                while($n < $action->getLevel()){
                    if (($countSkills[$n - 1] ?? 0) < 2) {
                        return false;
                    }
                    $n++;
                }
                return true;
            case 'secondary':
                while($n < $action->getLevel()){
                    if (($countSkills[$n - 1] ?? 0) < 1) {
                        return false;
                    }
                    $n++;
                }
                return true;
            case 'spell':
                $playerActionsService = new PlayerActionsService();
                return $countSpells[$action->getLevel() - 1] < $playerActionsService->getNbAuthorizedSpells($playerId,$action->getLevel());
            default:
                return false;
        }
    }

}
