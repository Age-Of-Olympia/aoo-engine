<?php

namespace App\View\Action;

use App\Action\Schema\OptionCatalog;
use App\Service\Action\SimulationWeaponCatalog;

/**
 * Structured editor for a passive's `conditions` JSON. The engine
 * ({@see \App\Service\PlayerPassiveService::checkPassiveConditionsByPlayerById})
 * only ever reads one of two keys — `weapon` (an equipped-weapon whitelist, with
 * "poing" meaning bare hands) or `category` (an action-category whitelist) — so
 * the picker offers exactly those two shapes, plus a raw-JSON escape hatch for
 * anything hand-authored. A "mode" select decides which panel is active; the
 * matching one is revealed client-side (see admin/js/action-workbench.js).
 */
final class PassiveConditionEditorView
{
    use RendersOptions;

    /** Bare-handed pseudo-weapon: the engine treats "poing" as "no weapon equipped". */
    private const BARE_HANDS = 'poing';

    /** @var array<string, array<string, string>>|null subtype => [weapon name => label] */
    private ?array $weaponGroups;

    /** @var array<string, string>|null category => label */
    private ?array $categories;

    /**
     * @param array<string, array<string, string>>|null $weaponGroups null = load from the real item catalog
     * @param array<string, string>|null                $categories   null = load the distinct action categories
     */
    public function __construct(?array $weaponGroups = null, ?array $categories = null)
    {
        $this->weaponGroups = $weaponGroups;
        $this->categories = $categories;
    }

    /**
     * @param array<string, mixed>|null $conditions the passive's stored conditions
     */
    public function render(?array $conditions): string
    {
        [$mode, $weapons, $categories, $rawJson] = $this->dissect($conditions);

        return '<div class="wb-cond">'
            . '<label class="wb-field wb-field--wide"><span>Conditions</span>'
            . '<select class="form-control wb-cond-mode" name="passive[conditions_mode]">'
            . $this->modeOption('none', 'Aucune', $mode)
            . $this->modeOption('weapon', 'Arme équipée', $mode)
            . $this->modeOption('category', "Catégorie d'action", $mode)
            . $this->modeOption('raw', 'JSON (avancé)', $mode)
            . '</select></label>'
            . $this->panel('weapon', $mode, $this->weaponPanel($weapons))
            . $this->panel('category', $mode, $this->categoryPanel($categories))
            . $this->panel('raw', $mode, $this->rawPanel($rawJson))
            . '</div>';
    }

    /**
     * Derive the active mode and its pre-filled values from stored conditions.
     *
     * @param array<string, mixed>|null $conditions
     * @return array{0: string, 1: array<int, string>, 2: array<int, string>, 3: string}
     */
    private function dissect(?array $conditions): array
    {
        if (!is_array($conditions) || $conditions === []) {
            return ['none', [], [], ''];
        }
        if (isset($conditions['weapon']) && is_array($conditions['weapon'])) {
            return ['weapon', array_map('strval', $conditions['weapon']), [], ''];
        }
        if (isset($conditions['category']) && is_array($conditions['category'])) {
            return ['category', [], array_map('strval', $conditions['category']), ''];
        }

        // Anything else is hand-authored — fall back to the raw editor.
        return ['raw', [], [], (string) json_encode($conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /**
     * @param array<int, string> $selected
     */
    private function weaponPanel(array $selected): string
    {
        $groups = $this->weaponGroups ?? (new SimulationWeaponCatalog())->groupedBySubtype();

        // The roster gets long on prod, so a live filter sits above the list and
        // checked weapons float to the top of their group (CSS :has — see the CSS).
        $out = '<input type="text" class="form-control wb-cond-search" placeholder="Filtrer les armes…" autocomplete="off">';

        // Bare hands first — it's a pseudo-weapon, not a real item in the catalog.
        $out .= '<div class="wb-cond-group"><span class="wb-cond-group-title">Mains nues</span>'
            . $this->checkbox('conditions_weapon', self::BARE_HANDS, 'Poing (mains nues)', in_array(self::BARE_HANDS, $selected, true))
            . '</div>';

        foreach ($groups as $subtype => $weapons) {
            $opts = '';
            foreach ($weapons as $name => $label) {
                $opts .= $this->checkbox('conditions_weapon', (string) $name, (string) $label, in_array((string) $name, $selected, true));
            }
            $out .= '<div class="wb-cond-group"><span class="wb-cond-group-title">' . $this->esc((string) $subtype) . '</span>' . $opts . '</div>';
        }

        return $out;
    }

    /**
     * @param array<int, string> $selected
     */
    private function categoryPanel(array $selected): string
    {
        $categories = $this->categories ?? (new OptionCatalog())->actionCategories();
        if ($categories === []) {
            return '<p class="wb-muted">Aucune catégorie d\'action disponible.</p>';
        }

        $out = '';
        foreach ($categories as $name => $label) {
            $out .= $this->checkbox('conditions_category', (string) $name, (string) $label, in_array((string) $name, $selected, true));
        }

        return '<div class="wb-cond-group">' . $out . '</div>';
    }

    private function rawPanel(string $json): string
    {
        return '<textarea class="form-control wb-cond-raw" name="passive[conditions]" rows="3"'
            . ' placeholder="{&quot;weapon&quot;: [&quot;arc&quot;]}">' . $this->esc($json) . '</textarea>';
    }

    private function panel(string $mode, string $current, string $body): string
    {
        $hidden = $mode === $current ? '' : ' hidden';

        return '<div class="wb-cond-panel" data-cond-mode="' . $this->esc($mode) . '"' . $hidden . '>' . $body . '</div>';
    }

    private function modeOption(string $value, string $label, string $current): string
    {
        return $this->option($value, $label, $value === $current);
    }

    private function checkbox(string $field, string $value, string $label, bool $checked): string
    {
        return '<label class="wb-cond-opt"><input type="checkbox" name="passive[' . $this->esc($field) . '][]"'
            . ' value="' . $this->esc($value) . '"' . ($checked ? ' checked' : '') . '> ' . $this->esc($label) . '</label>';
    }

}
