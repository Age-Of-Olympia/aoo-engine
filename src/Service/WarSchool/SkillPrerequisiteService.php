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
 *    passives whose carac holds the level;
 *  - an unknown or empty category carries no tree gate;
 *  - on top of the tree gate, a skill may declare {"need": [...],
 *    "forbidden": [...]} against the owned names.
 *
 * The NUMBER_MAX_COMP cap covers bought non-spell actives (type 'sort') and
 * passives; spells are capped by their slots instead.
 */
final class SkillPrerequisiteService
{
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
     * @param array<string, array{category: ?string, level: int}> $catalog every action's tree data, by name
     * @param array<int, array{name: string, category: ?string, level: int}> $passives owned passives' tree data
     * @param array<int, int> $spellSlots level => slot count
     */
    public function __construct(array $ownedActions, array $catalog, array $passives, array $spellSlots, int $maxComp)
    {
        $this->spellSlots = $spellSlots;
        $this->maxComp = $maxComp;

        foreach ($ownedActions as $name => $type) {
            $this->ownedNames[$name] = true;

            $meta = $catalog[$name] ?? null;
            if ($meta === null) {
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
                // carac holds the spell level the slot opens
                $level = (int) $passive->getCarac();
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

        $required = self::REQUIRED_PER_LEVEL[in_array($tree, self::PRIMARY_TREES, true) ? 'primary' : 'secondary'];

        for ($n = 1; $n < $level; $n++) {
            if (($this->treeCounts[$tree][$n] ?? 0) < $required) {
                return false;
            }
        }

        return true;
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
}
