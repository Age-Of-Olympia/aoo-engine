<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\ActionPassive;

class ActionPassiveService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getActionPassiveCount(int $playerId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
        ->select('COUNT(pp.passive)')
        ->from(\App\Entity\PlayerPassive::class, 'pp')
        ->where('pp.playerId = :playerId')
        ->setParameter('playerId', $playerId)
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function getActionPassiveByName(string $name): ?ActionPassive
    {
        $query = $this->entityManager->createQuery(
        'SELECT p FROM App\Entity\ActionPassive p WHERE p.name = :name'
        );
        $query->setParameter("name", $name);
        
        try {
            return $query->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }

    public function getIdByName($name): int
    {
        $query = $this->entityManager->createQuery(
        'SELECT p.id FROM App\Entity\ActionPassive p WHERE p.name = :name'
        );
        $query->setParameter("name", $name);

        try {
            return (int) $query->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            return 0;
        }
    }

    /**
     * Every passive as name => display_name, for schema/simulation dropdowns.
     *
     * @return array<string, string>
     */
    public function getAllNames(): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT a.name AS name, a.displayName AS displayName FROM App\Entity\ActionPassive a ORDER BY a.category ASC, a.name ASC'
        )->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[$row['name']] = $row['displayName'] !== '' ? $row['displayName'] : $row['name'];
        }

        return $names;
    }

    public function getActionPassivesByCategory(string $category): array
    {
        $query = $this->entityManager->createQuery(
        'SELECT a FROM App\Entity\ActionPassive a 
         WHERE a.category = :cat 
         ORDER BY a.level ASC, a.name ASC'
        )
        ->setParameter('cat', $category);

        return $query->getResult();
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

    public function isActionPassiveUsable(int $playerId, string $passiveName): bool
    {
        $passive = $this->getActionPassiveByName($passiveName);
        if ($passive === null) {
            return false; 
        }

        $actionService = new ActionService();
        $skillList = $actionService->getSkillList($playerId);

        if (!$this->checkDefaultPassivePrerequisites($passive, $skillList)) {
            return false;
        }

        $prerequisites = $passive->getPrerequisites(); 
        if (is_string($prerequisites)) {
            $prerequisites = json_decode($prerequisites, true);
        }
        
        $neededSkills = $prerequisites['need'] ?? [];
        $forbiddenSkills = $prerequisites['forbidden'] ?? [];

        if (!$actionService->checkNeededPrerequisites($skillList, $neededSkills)) {
            return false;
        }

        if (!$actionService->checkForbiddenPrerequisites($skillList, $forbiddenSkills)) {
            return false;
        }

        return true;
    }

    public function checkDefaultPassivePrerequisites(ActionPassive $passive, array $skillList): bool
    {
        $actionService = new ActionService();
        
        return $this->isChildNodesPassivePrerequisitesOK($passive, $actionService->getTreeType($passive->getCategory()), $skillList);
    }

    function isChildNodesPassivePrerequisitesOK(ActionPassive $passive, string $treeType, array $skillList): bool
    {
        $actionService = new ActionService();
        $actionTree = $actionService->extractTreeFromCategory($passive->getCategory());

        $countLvl = [0,0,0,0];

        foreach ($skillList as $skill) {
            $skillName = $skill['name'];

            if ($skill['type'] === 'action') {
                $sk = $actionService->getActionByName($skillName);
            } else {
                $sk = $this->getActionPassiveByName($skillName);
            }

            if ($sk === null) {
                continue;
            }

            $category = $sk->getCategory();
            if (empty($category)) {
                continue; 
            }

            $skillTree = $actionService->extractTreeFromCategory($category);
            
            if ($skillTree === $actionTree) {
                // Potentiellement à ajouter plus tard si on met l'activation/désactivation des compétences
                // Check si l'action est activée ou non
                $levelIndex = $sk->getLevel() - 1;
                $countLvl[$levelIndex] += 1;
            }
        }

        $n = 1;
        switch ($treeType) {
            case 'primary':
                while($n < $passive->getLevel()){
                    if (($countLvl[$n - 1] ?? 0) < 2) {
                        return false;
                    }
                    $n++;
                }
                return true;
            case 'secondary':
                while($n < $passive->getLevel()){
                    if (($countLvl[$n - 1] ?? 0) < 1) {
                        return false;
                    }
                    $n++;
                }
                return true;
            default:
                return false;
        }
    }

}
