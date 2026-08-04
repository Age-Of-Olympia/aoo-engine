<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One role of a faction (formerly an entry of the JSON `role[]` array).
 *
 * players.factionRole / players.secretFactionRole are 0-based indexes into
 * the faction's role list — mapped to $position, which FactionService keeps
 * contiguous (0..n-1) on every save. The permission flags are carried over
 * from the JSON for admin editability; no game code reads them yet.
 */
#[ORM\Entity]
#[ORM\Table(name: "faction_roles")]
#[ORM\UniqueConstraint(name: "UNIQ_faction_roles_position", columns: ["faction_id", "position"])]
class FactionRole
{
    /** Permission flags, in the order the legacy JSON files used them. */
    public const FLAG_KEYS = [
        'defaultRole', 'showPosition', 'showForum', 'addMember',
        'editRole', 'kickMember', 'initRole',
        'driveBuilding', 'useChest',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Faction::class, inversedBy: "roles")]
    #[ORM\JoinColumn(name: "faction_id", nullable: false, onDelete: "CASCADE")]
    private Faction $faction;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $position = 0;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    /** The rank's second name — Roi / Reine. '' = a single name. */
    #[ORM\Column(name: "name_alt", type: "string", length: 100, options: ["default" => ""])]
    private string $nameAlt = '';

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $defaultRole = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $showPosition = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $showForum = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $addMember = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $editRole = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $kickMember = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $initRole = false;

    /** May take the commands of the faction's playable buildings. */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $driveBuilding = false;

    /** May see inside, use and lock the faction's containers. */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $useChest = false;

    public function __construct(Faction $faction, string $name, int $position)
    {
        $this->faction = $faction;
        $this->name = $name;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFaction(): Faction
    {
        return $this->faction;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFlag(string $key): bool
    {
        $this->assertFlagKey($key);
        return $this->{$key};
    }

    public function setFlag(string $key, bool $value): void
    {
        $this->assertFlagKey($key);
        $this->{$key} = $value;
    }

    /**
     * @return array<string, bool> All 7 flags, keyed like the JSON files.
     */
    public function getFlags(): array
    {
        $flags = [];
        foreach (self::FLAG_KEYS as $key) {
            $flags[$key] = $this->{$key};
        }
        return $flags;
    }

    /**
     * Legacy JSON shape of the role: `{name, <flag>: 1}` with false flags
     * OMITTED, exactly like the datas/*\/factions files — call sites test
     * flags with isset()/!empty().
     */
    public function toJsonObject(): object
    {
        $role = ['name' => $this->name];
        if ($this->nameAlt !== '') {
            // Additive key: legacy readers keep testing name alone.
            $role['nameAlt'] = $this->nameAlt;
        }
        foreach (self::FLAG_KEYS as $key) {
            if ($this->{$key}) {
                $role[$key] = 1;
            }
        }
        return (object) $role;
    }

    private function assertFlagKey(string $key): void
    {
        if (!in_array($key, self::FLAG_KEYS, true)) {
            throw new \InvalidArgumentException("Unknown role flag '{$key}'");
        }
    }
}
