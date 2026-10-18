<?php

namespace App\Service\WarSchool;

use App\Entity\ActionPassive;
use App\Interface\ActionInterface;
use App\Service\ActionService;
use App\Service\PlayerActionsService;
use App\Service\PlayerPassiveService;

/**
 * The one place that says whether a war-school skill can be bought.
 *
 * The player's holdings are loaded once (three queries) and every check runs
 * on those arrays, so a tree page can ask per row without going back to the
 * database. Rules:
 *  - primary trees (melee, distance, magic): level N needs 2 owned skills or
 *    passives of that tree at every level below N;
 *  - secondary trees (survival, stealth): same, with 1 per level;
 *  - spells: level N needs a free spell slot at N, opened by 'spell-slot'
 *    passives of level N;
 *  - an unknown or empty category carries no tree gate;
 *  - on top of the tree gate, a skill may declare {"need": [...],
 *    "forbidden": [...]} against the owned names.
 *
 * The NUMBER_MAX_COMP cap covers bought non-spell actives (type 'sort') and
 * passives; spells are capped by their slots instead. Innate spells and the
 * actions acquired by default (actions.innate) escape both.
 */
final class SkillPrerequisiteService
{
    public const INNATE_SPELLS = ['pic_magique'];
    public const MAX_TREE_LEVEL = 4;
    private const PRIMARY_TREES = ['melee', 'distance', 'magic'];
    private const SECONDARY_TREES = ['survival', 'stealth'];
    private const REQUIRED_PER_LEVEL = ['primary' => 2, 'secondary' => 1];

    /** @var array<string, true> owned action and passive names */
    private array $ownedNames = [];

    /** @var array<string, array<int, int>> tree => [level => count], non-spell skills and passives */
    private array $treeCounts = [];

    /** @var array<int, int> level => owned spells */
    private array $spellCounts = [];

    /** @var array<int, int> level => slots opened by spell-slot passives */
    private array $spellSlots;

    /** Bought non-spell actives + passives, against $maxComp. */
    private int $capCount = 0;

    private int $maxComp;

    /**
     * @param array<string, ?string> $ownedActions name => players_actions.type ('sort' marks a bought skill)
     * @param array<string, array{category: ?string, level: int, innate?: bool}> $catalog every action's tree data, by name
     * @param array<int, array{name: string, category: ?string, level: int}> $passives owned passives' tree data
     * @param array<int, int> $spellSlots level => slot count
     */
    public function __construct(array $ownedActions, array $catalog, array $passives, array $spellSlots, int $maxComp)
    {
        $this->spellSlots = $spellSlots;
        $this->maxComp = $maxComp;

        foreach ($ownedActions as $name => $type) {
            $this->ownedNames[$name] = true;

            if (self::isInnate($name)) {
                continue;
            }

            $meta = $catalog[$name] ?? null;
            // An action acquired by default is nobody's purchase
            if ($meta === null || !empty($meta['innate'])) {
                continue;
            }

            $tree = self::tree($meta['category']);
            if ($tree === 'spell') {
                $this->spellCounts[$meta['level']] = ($this->spellCounts[$meta['level']] ?? 0) + 1;
            } elseif ($tree !== null) {
                $this->treeCounts[$tree][$meta['level']] = ($this->treeCounts[$tree][$meta['level']] ?? 0) + 1;
            }
            if ($type === 'sort' && $tree !== 'spell') {
                $this->capCount++;
            }
        }

        foreach ($passives as $passive) {
            $this->ownedNames[$passive['name']] = true;
            $this->capCount++;

            $tree = self::tree($passive['category']);
            if ($tree !== null && $tree !== 'spell') {
                $this->treeCounts[$tree][$passive['level']] = ($this->treeCounts[$tree][$passive['level']] ?? 0) + 1;
            }
        }
    }

    /** Load a player's holdings — the single entry point outside tests. */
    public static function forPlayer(int $playerId): self
    {
        $passives = [];
        $spellSlots = [];
        foreach ((new PlayerPassiveService())->getPassivesByPlayerId($playerId) as $passive) {
            if ($passive->getType() === 'spell-slot') {
                // a slot passive opens slots at its own level
                $level = $passive->getLevel();
                $spellSlots[$level] = ($spellSlots[$level] ?? 0) + (int) $passive->getValue();
            }
            $passives[] = [
                'name' => $passive->getName(),
                'category' => $passive->getCategory(),
                'level' => $passive->getLevel(),
            ];
        }

        return new self(
            (new PlayerActionsService())->getActionsWithType($playerId),
            (new ActionService())->getCatalogMeta(),
            $passives,
            $spellSlots,
            NUMBER_MAX_COMP
        );
    }

    public function owns(string $name): bool
    {
        return isset($this->ownedNames[$name]);
    }

    // Un sort inné ne compte pas dans les limites de sorts ou compétences
    public static function isInnate(string $name): bool
    {
        return in_array($name, self::INNATE_SPELLS,true);
    }

    /** Bought non-spell actives + passives — what NUMBER_MAX_COMP caps. */
    public function capCount(): int
    {
        return $this->capCount;
    }

    public function isFull(): bool
    {
        return $this->capCount >= $this->maxComp;
    }

    public function spellCountAt(int $level): int
    {
        return $this->spellCounts[$level] ?? 0;
    }

    public function spellSlotsAt(int $level): int
    {
        return $this->spellSlots[$level] ?? 0;
    }

    public function hasFreeSpellSlot(int $level): bool
    {
        return $this->spellCountAt($level) < $this->spellSlotsAt($level);
    }

    /** Owned non-spell skills and passives of a tree at one level. */
    public function treeCountAt(string $tree, int $level): int
    {
        return $this->treeCounts[$tree][$level] ?? 0;
    }

    /** How many skills each level below must hold before the next opens. */
    public static function requiredPerLevel(string $tree): int
    {
        return self::REQUIRED_PER_LEVEL[in_array($tree, self::PRIMARY_TREES, true) ? 'primary' : 'secondary'];
    }

    /** The tree gate alone: is this level reachable, regardless of need/forbidden lists? */
    public function isLevelOpen(?string $category, int $level): bool
    {
        return $this->passesTreeGate($category, $level);
    }

    public function isUsable(ActionInterface $action): bool
    {
        return $this->isSkillUsable($action->getCategory(), $action->getLevel(), $action->getPrerequisites());
    }

    public function isPassiveUsable(ActionPassive $passive): bool
    {
        return $this->isSkillUsable($passive->getCategory(), $passive->getLevel(), $passive->getPrerequisites());
    }

    public function isSkillUsable(?string $category, int $level, ?string $prerequisitesJson): bool
    {
        if (!$this->passesTreeGate($category, $level)) {
            return false;
        }

        $prerequisites = json_decode($prerequisitesJson ?? '', true) ?: [];

        foreach ($prerequisites['need'] ?? [] as $need) {
            if (!$this->owns($need)) {
                return false;
            }
        }

        foreach ($prerequisites['forbidden'] ?? [] as $forbidden) {
            if ($this->owns($forbidden)) {
                return false;
            }
        }

        return true;
    }

    private function passesTreeGate(?string $category, int $level): bool
    {
        $tree = self::tree($category);

        if ($tree === null) {
            return true;
        }

        if ($tree === 'spell') {
            return $this->hasFreeSpellSlot($level);
        }

        if ($level === self::MAX_TREE_LEVEL && in_array($tree, self::PRIMARY_TREES, true)) {
            if ($this->hasOtherPrimaryLevelMax($tree)) {
                return false;
            }
        }
        
        $required = self::requiredPerLevel($tree);

        for ($n = 1; $n < $level; $n++) {
            if ($this->treeCountAt($tree, $n) < $required) {
                return false;
            }
        }

        return true;
    }

    private function hasOtherPrimaryLevelMax(string $currentTree): bool
    {
        foreach (self::PRIMARY_TREES as $primaryTree) {
            if ($primaryTree !== $currentTree && $this->treeCountAt($primaryTree, self::MAX_TREE_LEVEL) > 0) {
                return true;
            }
        }

        return false;
    }
    
    /** 'melee-off' => 'melee'; anything outside the known trees carries no gate. */
    public static function tree(?string $category): ?string
    {
        if (empty($category)) {
            return null;
        }

        $tree = explode('-', $category)[0];

        if ($tree === 'spell' || in_array($tree, self::PRIMARY_TREES, true) || in_array($tree, self::SECONDARY_TREES, true)) {
            return $tree;
        }

        return null;
    }

    public static function treeLevel(?string $category, int $level): int
    {
        $tree = self::tree($category);

        if ($tree === null || $tree === 'spell') {
            return $level;
        }

        return min($level, self::MAX_TREE_LEVEL);
    }
}
