<?php declare(strict_types=1);

namespace App\Tutorial;

/**
 * Maps a failed step-save $_POST back into the DB-row shapes rendered
 * by admin/tutorial-step-editor.php, so the form repopulates with the
 * submitted values instead of wiping them.
 *
 * Numeric fields are cast because the editor echoes them without
 * escaping; free-text fields stay raw (the editor escapes them).
 */
final class TutorialStepFormData
{
    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> tutorial_steps row shape
     */
    public static function step(array $post): array
    {
        return [
            'version' => self::str($post, 'version'),
            'step_id' => self::str($post, 'step_id'),
            'next_step' => self::str($post, 'next_step'),
            'step_number' => self::numOrNull($post, 'step_number'),
            'step_type' => self::str($post, 'step_type'),
            'title' => self::str($post, 'title'),
            'text' => self::str($post, 'text'),
            'xp_reward' => self::intOrNull($post, 'xp_reward') ?? 0,
            'is_active' => self::checkbox($post, 'is_active'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> tutorial_step_ui row shape
     */
    public static function ui(array $post): array
    {
        $caracsPanelState = self::str($post, 'caracs_panel_state');
        if ($caracsPanelState !== 'open' && $caracsPanelState !== 'closed') {
            $caracsPanelState = null;
        }

        return [
            'target_selector' => self::str($post, 'target_selector'),
            'target_description' => self::str($post, 'target_description'),
            'highlight_selector' => self::str($post, 'highlight_selector'),
            'tooltip_position' => self::str($post, 'tooltip_position'),
            'interaction_mode' => self::str($post, 'interaction_mode'),
            'blocked_click_message' => self::str($post, 'blocked_click_message'),
            'show_delay' => self::intOrNull($post, 'show_delay') ?? 0,
            'auto_advance_delay' => self::intOrNull($post, 'auto_advance_delay'),
            'allow_manual_advance' => self::checkbox($post, 'allow_manual_advance'),
            'auto_close_card' => self::checkbox($post, 'auto_close_card'),
            'highlight_padding' => self::intOrNull($post, 'highlight_padding') ?? 0,
            'caracs_panel_state' => $caracsPanelState,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> tutorial_step_validation row shape
     */
    public static function validation(array $post): array
    {
        return [
            'requires_validation' => self::checkbox($post, 'requires_validation'),
            'validation_type' => self::str($post, 'validation_type'),
            'validation_hint' => self::str($post, 'validation_hint'),
            'target_x' => self::intOrNull($post, 'target_x'),
            'target_y' => self::intOrNull($post, 'target_y'),
            'movement_count' => self::intOrNull($post, 'movement_count'),
            'panel_id' => self::str($post, 'panel_id'),
            'element_selector' => self::str($post, 'element_selector'),
            'element_clicked' => self::str($post, 'element_clicked'),
            'action_name' => self::str($post, 'action_name'),
            'action_charges_required' => self::intOrNull($post, 'action_charges_required') ?? 1,
            'combat_required' => self::checkbox($post, 'combat_required'),
            'dialog_id' => self::str($post, 'dialog_id'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> tutorial_step_prerequisites row shape
     */
    public static function prerequisites(array $post): array
    {
        return [
            'mvt_required' => self::intOrNull($post, 'mvt_required'),
            'pa_required' => self::intOrNull($post, 'pa_required'),
            'auto_restore' => self::checkbox($post, 'auto_restore'),
            'consume_movements' => self::checkbox($post, 'consume_movements'),
            'unlimited_mvt' => self::checkbox($post, 'unlimited_mvt'),
            'unlimited_pa' => self::checkbox($post, 'unlimited_pa'),
            'spawn_enemy' => self::str($post, 'spawn_enemy'),
            'ensure_harvestable_tree_x' => self::intOrNull($post, 'ensure_harvestable_tree_x'),
            'ensure_harvestable_tree_y' => self::intOrNull($post, 'ensure_harvestable_tree_y'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> tutorial_step_features row shape
     */
    public static function features(array $post): array
    {
        return [
            'celebration' => self::checkbox($post, 'celebration'),
            'show_rewards' => self::checkbox($post, 'show_rewards'),
            'redirect_delay' => self::intOrNull($post, 'redirect_delay'),
        ];
    }

    /**
     * Map a selector[] field to [['selector' => ...], ...], skipping blanks.
     *
     * @param array<string, mixed> $post
     * @return array<int, array{selector: string}>
     */
    public static function selectorList(array $post, string $field): array
    {
        $rows = [];
        foreach ((array)($post[$field] ?? []) as $selector) {
            if (is_string($selector) && trim($selector) !== '') {
                $rows[] = ['selector' => $selector];
            }
        }
        return $rows;
    }

    /**
     * Pair parallel key[]/value[] fields into row shapes, skipping blank keys.
     *
     * @param array<string, mixed> $post
     * @return array<int, array<string, string>>
     */
    public static function keyValueList(array $post, string $keysField, string $valuesField, string $keyColumn, string $valueColumn): array
    {
        $keys = (array)($post[$keysField] ?? []);
        $values = (array)($post[$valuesField] ?? []);

        $rows = [];
        foreach (array_values($keys) as $i => $key) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $value = array_values($values)[$i] ?? '';
            $rows[] = [
                $keyColumn => $key,
                $valueColumn => is_string($value) ? $value : '',
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function str(array $post, string $key): ?string
    {
        $value = $post[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function intOrNull(array $post, string $key): ?int
    {
        $value = $post[$key] ?? null;
        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @param array<string, mixed> $post
     * @return float|int|null
     */
    private static function numOrNull(array $post, string $key): float|int|null
    {
        $value = $post[$key] ?? null;
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function checkbox(array $post, string $key): int
    {
        return empty($post[$key]) ? 0 : 1;
    }
}
