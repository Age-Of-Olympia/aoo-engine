<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;

/**
 * Single gateway to race definitions, now stored in the DB (races,
 * race_starter_actions, race_spells) instead of datas/*\/races/*.json.
 *
 * Race lookups happen on hot paths (Player::get_caracs on every request), so
 * loaded entities are kept in a per-request cache.
 */
class RaceService
{
    private const DEFAULT_COLOR = '#000000';
    private const DEFAULT_BG_COLOR = '#FFFFFF';
    private const DEFAULT_MAX_MVT = 4;

    /** @var array<string, Race|null> Per-request cache, keyed by lowercase race name. */
    private static array $cache = [];

    private $entityManager;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a Race entity that matches the given name (the lowercase code
     * stored in players.race), or null if not found.
     */
    public function getRaceByName(string $name): ?Race
    {
        $name = strtolower($name);

        if (!array_key_exists($name, self::$cache)) {
            self::$cache[$name] = $this->entityManager
                ->getRepository(Race::class)
                ->findOneBy(['name' => $name]);
        }

        return self::$cache[$name];
    }

    /**
     * Returns the ID of the Race that matches the given name, or null if not found.
     */
    public function getRaceIdByName(string $name): ?int
    {
        $race = $this->getRaceByName($name);
        return $race ? $race->getId() : null;
    }

    /**
     * Full race data in the shape the race JSON files used to have — the
     * historical read model most call sites consume (->name is the display
     * label, ->text the lore, ->actions/->spells the name lists,
     * ->actionsPack their union).
     */
    public function getRaceData(string $raceName): ?object
    {
        $race = $this->getRaceByName($raceName);
        if ($race === null) {
            return null;
        }

        return (object) array_merge(
            [
                'name' => $race->getLabel(),
                'text' => $race->getDescription(),
                'bgColor' => $race->getBgColor(),
                'color' => $race->getColor(),
                'faction' => $race->getFaction(),
                'plan' => $race->getPlan(),
                'animateur' => $race->getAnimateurId(),
                'actions' => $race->getStarterActionNames(),
                'spells' => $race->getSpellNames(),
                'actionsPack' => $race->getActionsPackNames(),
            ],
            $race->getCaracs()
        );
    }

    /**
     * Returns the colour used to display a race-gated action/passive name.
     * Empty races (all races / "commun") and unknown races fall back to black.
     */
    public static function getRaceColor(?string $race): string
    {
        if (empty($race)) {
            return self::DEFAULT_COLOR;
        }

        $entity = (new self())->getRaceByName($race);

        return $entity ? $entity->getBgColor() : self::DEFAULT_COLOR;
    }

    /**
     * Returns the background color of the Race that matches the given name.
     */
    public function getRaceBackgroundColor(string $raceName): string
    {
        $race = $this->getRaceByName($raceName);

        return $race ? $race->getBgColor() : self::DEFAULT_BG_COLOR;
    }

    /**
     * Returns the max movement points for a race.
     */
    public function getRaceMaxMvt(string $raceName): int
    {
        $race = $this->getRaceByName($raceName);

        return $race ? $race->getCarac('mvt') : self::DEFAULT_MAX_MVT;
    }

    /**
     * Races offered at registration (replaces the RACES constant).
     *
     * @return Race[]
     */
    public function getPlayableRaces(): array
    {
        return $this->entityManager->getRepository(Race::class)
            ->findBy(['playable' => true], ['id' => 'ASC']);
    }

    /**
     * Every race the game knows, PNJ/system ones included (replaces the
     * RACES_EXT constant).
     *
     * @return Race[]
     */
    public function getAllRaces(): array
    {
        return $this->entityManager->getRepository(Race::class)
            ->findBy([], ['id' => 'ASC']);
    }

    /**
     * @return string[] Lowercase names of playable races (RACES replacement
     *                  for in_array()-style validation and select lists).
     */
    public function getPlayableRaceNames(): array
    {
        return array_map(static fn (Race $race): string => $race->getName(), $this->getPlayableRaces());
    }

    /**
     * @return string[] Lowercase names of all races (RACES_EXT replacement).
     */
    public function getAllRaceNames(): array
    {
        return array_map(static fn (Race $race): string => $race->getName(), $this->getAllRaces());
    }

    /**
     * @return array<string, string> race name => bgColor, for every race.
     *                               Used by map layer rendering (one query
     *                               instead of one per player row).
     */
    public function getBgColorMap(): array
    {
        $map = [];
        foreach ($this->getAllRaces() as $race) {
            $map[$race->getName()] = $race->getBgColor();
        }

        return $map;
    }

    /**
     * Replace a race's starter-action and spell lists (admin edit). Plain SQL
     * delete+insert: entries are name strings without FK identity, and this
     * sidesteps ORM insert-before-delete collisions on the unique key.
     *
     * @param string[] $starterActions
     * @param string[] $spells
     */
    public function replaceNameLists(Race $race, array $starterActions, array $spells): void
    {
        $connection = $this->entityManager->getConnection();

        foreach (['race_starter_actions' => $starterActions, 'race_spells' => $spells] as $table => $names) {
            $names = array_values(array_unique(array_filter(array_map('trim', $names))));

            $connection->executeStatement("DELETE FROM {$table} WHERE race_id = ?", [$race->getId()]);
            foreach ($names as $position => $name) {
                $connection->executeStatement(
                    "INSERT INTO {$table} (race_id, name, position) VALUES (?, ?, ?)",
                    [$race->getId(), $name, $position]
                );
            }
        }

        // The identity-mapped entity still holds the pre-edit collections;
        // refresh so same-request readers see the new lists.
        $this->entityManager->refresh($race);
        self::clearCache();
    }

    /**
     * Persist a (new or edited) race and drop the request cache.
     */
    public function save(Race $race): void
    {
        $this->entityManager->persist($race);
        $this->entityManager->flush();
        self::clearCache();
    }

    /**
     * Drop the per-request cache (after admin edits or in tests).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
