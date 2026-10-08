<?php

namespace App\View\Admin;

use App\Service\TypeRepairService;

/**
 * The two repair recipes of a type, as form fields — shared by the type
 * page (races.php) and the item page (items.php, placed storage), and read
 * back by their save scripts. Materials are written "pierre:1, bois:2";
 * gold is one amount. An empty recipe means "does not mend that way".
 */
final class RepairRecipeFields
{
    /** @param array<string, array{percent: int, costs: array<string, int>}> $recipes TypeRepairService::recipesOf() */
    public static function render(array $recipes): string
    {
        $materials = $recipes[TypeRepairService::MATERIALS] ?? null;
        $gold = $recipes[TypeRepairService::GOLD] ?? null;
        $costs = implode(', ', array_map(
            static fn (string $item, int $quantity): string => $item . ':' . $quantity,
            array_keys($materials['costs'] ?? []),
            array_values($materials['costs'] ?? [])
        ));

        return '<div class="form-group"><label>Réparation (matériaux)</label> '
            . self::percent('repair_materials_percent', $materials) . ' % des PV par réparation, contre '
            . '<input type="text" class="form-control form-control-sm d-inline-block" style="width:240px" name="repair_materials_costs"'
            . ' value="' . htmlspecialchars($costs, ENT_QUOTES, 'UTF-8') . '" placeholder="pierre:1, bois:1"'
            . ' title="Matières payées à chaque réparation (code:quantité, séparés par des virgules). Vide : pas de réparation en matériaux.">'
            . '</div>'
            . '<div class="form-group"><label>Réparation (or)</label> '
            . self::percent('repair_gold_percent', $gold) . ' % des PV par réparation, contre '
            . '<input type="number" class="form-control form-control-sm d-inline-block" style="width:90px" name="repair_gold_amount" min="0"'
            . ' value="' . (int) ($gold['costs']['or'] ?? 0) . '" title="Or payé à chaque réparation. 0 : pas de réparation en or."> or'
            . '<small class="form-text text-muted">Une réparation paie une dose entière et rend sa part des PV max, plafonnée aux dégâts. Sans recette, le bouton n\'apparaît pas.</small>'
            . '</div>';
    }

    /**
     * Both recipes from the submitted form, or null when the form did not
     * carry the fields (nothing to change).
     *
     * @param array<string, mixed> $post
     * @return array<string, array{percent: int, costs: array<string, int>}>|null
     */
    public static function fromPost(array $post): ?array
    {
        if (!isset($post['repair_materials_percent'], $post['repair_gold_percent'])) {
            return null;
        }

        $materials = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($post['repair_materials_costs'] ?? '')))) as $line) {
            [$item, $quantity] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '1');
            $materials[$item] = ($materials[$item] ?? 0) + max(1, (int) $quantity);
        }

        return [
            TypeRepairService::MATERIALS => ['percent' => (int) $post['repair_materials_percent'], 'costs' => $materials],
            TypeRepairService::GOLD => ['percent' => (int) $post['repair_gold_percent'], 'costs' => ['or' => (int) ($post['repair_gold_amount'] ?? 0)]],
        ];
    }

    /** @param array{percent: int, costs: array<string, int>}|null $recipe */
    private static function percent(string $name, ?array $recipe): string
    {
        return '<input type="number" class="form-control form-control-sm d-inline-block" style="width:64px" name="' . $name . '"'
            . ' min="0" max="100" value="' . (int) ($recipe['percent'] ?? 15) . '">';
    }
}
