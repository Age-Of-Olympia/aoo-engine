<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Module\Module;
use App\Module\Modules;
use App\Service\EffectService;
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
 * Each line said has two forms: what the character reads after the move
 * (plain text) and what its log keeps (HTML); null when it goes to one only.
 *
 * @phpstan-type Biome array{id: int, name: string, enter_message: string, exit_message: string, enter_effect: string, turns: ?int, value: int, ends_on_exit: bool}
 * @phpstan-type Line array{notice: ?string, log: ?string}
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
     * @return list<string> what the character reads after the move
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
        $lines = array_merge(
            $exited === null ? [] : $this->leave($character, $exited),
            $entered === null ? [] : $this->enter($character, $entered),
        );
        foreach (self::said($lines, 'log') as $log) {
            Log::put($character, $character, $log, 'biome');
        }

        return self::said($lines, 'notice');
    }

    /**
     * The exit message, and the end of the entry effect when the exit ends it.
     *
     * @param Biome $biome
     * @return list<Line>
     */
    private function leave(Player $character, array $biome): array
    {
        $lines = [self::both($biome['exit_message'])];
        $effect = $biome['enter_effect'];
        if ($effect !== '' && $biome['ends_on_exit'] && $character->have_effect($effect)) {
            $character->end_effect($effect);
            $lines[] = self::both($character->effectService->cancelNotice([$effect], $biome['name']));
        }

        return $lines;
    }

    /**
     * The entry message, then the effect put on: what it is and for how
     * long, what it takes or gives, the effects it cancels.
     *
     * @param Biome $biome
     * @return list<Line>
     */
    private function enter(Player $character, array $biome): array
    {
        $lines = [self::both($biome['enter_message'])];
        $effects = $character->effectService;
        $effect = $biome['enter_effect'];
        // An effect removed from the catalog since would stop the move (add_effect exits on an unknown name)
        if ($effect === '' || !$effects->exists($effect)) {
            return $lines;
        }

        $duration = $biome['turns'] ?? PlayerEffectService::DURATION_INFINITE;
        $ended = $character->add_effect($effect, $duration, $biome['value']);
        $name = (string) $character->data->name;
        $lines[] = [
            'notice' => $effects->isHidden($effect) ? null : self::effectNotice($biome, $effects->getLabel($effect), $duration),
            'log' => $effects->landingMessage($effect, $name, $name, $duration, $biome['value']),
        ];
        // The log line above already counts what the effect takes or gives
        $lines[] = ['notice' => $effects->stepNotice($effect, $biome['value'], $biome['name']), 'log' => null];
        $lines[] = self::both($effects->cancelNotice($ended, $biome['name']));

        return $lines;
    }

    /**
     * "Bois du Nord : effet Brume, pour 3 tours."
     *
     * @param Biome $biome
     */
    private static function effectNotice(array $biome, string $label, int $duration): string
    {
        $until = $biome['turns'] === null && $biome['ends_on_exit'] ? 'jusqu\'à la sortie du biome' : EffectService::durationText($duration);

        return $biome['name'] . ' : effet ' . $label . ', ' . $until . '.';
    }

    /** @return Line one line for the character and, escaped, for its log */
    private static function both(string $text): array
    {
        return ['notice' => $text, 'log' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * @param list<Line> $lines
     * @param 'notice'|'log' $where
     * @return list<string> the lines said there, empty ones left out
     */
    private static function said(array $lines, string $where): array
    {
        return array_values(array_filter(array_column($lines, $where), static fn (?string $text): bool => $text !== null && $text !== ''));
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
