<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Module\Module;
use App\Module\Modules;
use App\Service\PlayerEffectService;
use Classes\Log;
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
     * @return list<string> the messages the character reads
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
        $messages = [];
        if ($exited !== null) {
            if ($exited['enter_effect'] !== '' && $exited['ends_on_exit']) {
                $character->end_effect($exited['enter_effect']);
            }
            $messages[] = $exited['exit_message'];
        }
        if ($entered !== null) {
            // An effect removed from the catalog since would stop the move (add_effect exits on an unknown name)
            if ($entered['enter_effect'] !== '' && $character->effectService->exists($entered['enter_effect'])) {
                $character->add_effect($entered['enter_effect'], $entered['turns'] ?? PlayerEffectService::DURATION_INFINITE, $entered['value']);
            }
            $messages[] = $entered['enter_message'];
        }

        $messages = array_values(array_filter($messages, static fn (string $message): bool => $message !== ''));
        foreach ($messages as $message) {
            Log::put($character, $character, $message, 'biome');
        }

        return $messages;
    }

    /**
     * The biome on top at each of these cells, its own settings replacing
     * its type's where set.
     *
     * @param list<int> $coordsIds
     * @return array<int, array{id: int, enter_message: string, exit_message: string, enter_effect: string, turns: ?int, value: int, ends_on_exit: bool}>
     */
    private function topBiomes(array $coordsIds): array
    {
        $biomes = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT c.id AS coords_id, b.id,
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
