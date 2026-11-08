<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Module\Module;
use App\Module\Modules;
use App\Service\PlayerEffectService;
use Classes\Log;
use Classes\Player;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Leaving and entering a biome, when a character changes cell: the biome
 * on top of each cell, compared. A different one (or none) on either side
 * is an exit from the first and an entry into the second; each says its
 * message and the entry puts its effect on, which the exit ends unless it
 * is left to run out. Moving within one biome does nothing.
 *
 * Called by {@see CharacterMoveService} only: every move goes through it.
 *
 * Each biome crossed says one HTML line, its name first and coloured by
 * the way: the character reads it after the move, and its log keeps it.
 *
 * @phpstan-type Biome array{id: int, name: string, enter_message: string, exit_message: string, enter_effect: string, turns: ?int, value: int, ends_on_exit: bool}
 */
final class BiomeCrossingService
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @param ?int $left coords id of the cell left (null: the character appears)
     * @param ?int $reached coords id of the cell reached (null: off the board)
     * @return list<string> what the character reads after the move, as HTML lines
     */
    public function cross(int $characterId, ?int $left, ?int $reached): array
    {
        if (!Modules::on(Module::Biomes) || $left === $reached) {
            return [];
        }
        $biomes = $this->topBiomes(array_values(array_filter([$left, $reached], static fn (?int $id): bool => $id !== null)));
        $exited = $left === null ? null : ($biomes[$left] ?? null);
        $entered = $reached === null ? null : ($biomes[$reached] ?? null);
        if (($exited['id'] ?? null) === ($entered['id'] ?? null)) {
            return [];
        }

        $character = PlayerFactory::legacy($characterId);
        $character->get_data();
        $lines = array_values(array_filter([
            $exited === null ? null : $this->leave($character, $exited),
            $entered === null ? null : $this->enter($character, $entered),
        ]));
        foreach ($lines as $line) {
            Log::put($character, $character, $line, 'biome');
        }

        return $lines;
    }

    /**
     * "Micro-désert : L'effet [icon] Brûlure prend fin." — the exit
     * message, and the end of the entry effect when the exit ends it.
     *
     * @param Biome $biome
     */
    private function leave(Player $character, array $biome): ?string
    {
        $said = [self::text($biome['exit_message'])];
        $effects = $character->effectService;
        $effect = $biome['enter_effect'];
        if ($effect !== '' && $biome['ends_on_exit'] && $character->have_effect($effect)) {
            $character->end_effect($effect);
            $said[] = $effects->endHtml([$effect]);
        }

        return self::line(false, $biome['name'], $said);
    }

    /**
     * "Bois du Nord : Bienvenue. Effet [icon] Brume, pour 3 tours." — the
     * entry message, then the effect put on: what it is and for how long,
     * what it takes or gives, the effects it cancels.
     *
     * @param Biome $biome
     */
    private function enter(Player $character, array $biome): ?string
    {
        $said = [self::text($biome['enter_message'])];
        $effects = $character->effectService;
        $effect = $biome['enter_effect'];
        // An effect removed from the catalog since would stop the move (add_effect exits on an unknown name)
        if ($effect !== '' && $effects->exists($effect)) {
            $duration = $biome['turns'] ?? PlayerEffectService::DURATION_INFINITE;
            $ended = $character->add_effect($effect, $duration, $biome['value']);
            $until = $biome['turns'] === null && $biome['ends_on_exit'] ? 'jusqu\'à la sortie du biome' : '';
            $said[] = $effects->landingNotice($effect, $duration, $biome['value'], $ended, $until);
        }

        return self::line(true, $biome['name'], $said);
    }

    /**
     * One HTML line per biome crossed: its name first, with the way's icon
     * and colour (css/modal.css), then what it says; null when it says nothing.
     *
     * @param list<string> $said HTML sentences, escaped where they were written
     */
    private static function line(bool $entering, string $biome, array $said): ?string
    {
        $said = array_filter($said, static fn (string $text): bool => $text !== '');
        if ($said === []) {
            return null;
        }

        return '<strong class="' . ($entering ? 'biome-in" title="Entrée"><span class="ra ra-footprint"></span> ' : 'biome-out" title="Sortie"><span class="ra ra-trail"></span> ')
            . self::text($biome) . '</strong> : ' . implode(' ', $said);
    }

    /** Plain text made safe inside the HTML line */
    private static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * The biome on top at each of these cells, its own settings replacing
     * its type's where set.
     *
     * @param list<int> $coordsIds
     * @return array<int, Biome>
     */
    private function topBiomes(array $coordsIds): array
    {
        $biomes = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT c.id AS coords_id, b.id, b.name,
                    COALESCE(b.enter_message, t.enter_message, \'\') AS enter_message,
                    COALESCE(b.exit_message, t.exit_message, \'\') AS exit_message,
                    COALESCE(b.enter_effect, t.enter_effect, \'\') AS enter_effect,
                    IF(b.enter_effect IS NULL, t.enter_effect_turns, b.enter_effect_turns) AS turns,
                    IF(b.enter_effect IS NULL, t.enter_effect_value, COALESCE(b.enter_effect_value, 1)) AS value,
                    IF(b.enter_effect IS NULL, t.enter_effect_ends_on_exit, COALESCE(b.enter_effect_ends_on_exit, 1)) AS ends_on_exit
             FROM coords c
             JOIN plans p ON p.slug = c.plan
             JOIN biome_cells bc ON bc.plan_id = p.id AND bc.x = c.x AND bc.y = c.y
             JOIN biomes b ON b.id = bc.biome_id
             JOIN biome_types t ON t.id = b.biome_type_id
             WHERE c.id IN (?)
             ORDER BY b.stack_order DESC, b.id DESC',
            [$coordsIds],
            [ArrayParameterType::INTEGER]
        ) as $row) {
            // Highest stack order first: the first row of a cell is its top biome
            $biomes[(int) $row['coords_id']] ??= [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'enter_message' => (string) $row['enter_message'],
                'exit_message' => (string) $row['exit_message'],
                'enter_effect' => (string) $row['enter_effect'],
                'turns' => $row['turns'] === null ? null : (int) $row['turns'],
                'value' => max(1, (int) $row['value']),
                'ends_on_exit' => (bool) $row['ends_on_exit'],
            ];
        }

        return $biomes;
    }
}
