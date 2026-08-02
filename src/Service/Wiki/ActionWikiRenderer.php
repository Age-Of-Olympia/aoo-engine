<?php

namespace App\Service\Wiki;

use App\Interface\WikiSheetRendererInterface;
use App\Service\ImportExport\ExporterRegistry;
use Classes\Db;

/**
 * Fiche wiki du catalogue des ACTIONS : tableaux DokuWiki par
 * catégorie, coûts et visée DÉRIVÉS des conditions (RequiresTraitValue,
 * RequiresItem, ItemPick, TargetType) — pas d'un champ rédigé qui peut
 * mentir. Remplace scripts/tools/generate_actions_wiki.php (mort :
 * condition toujours vraie, propriété indéfinie).
 */
final class ActionWikiRenderer implements WikiSheetRendererInterface
{
    /**
     * Targeting labels come from the condition itself: a copy here would fall
     * behind the day a new target kind becomes declarable.
     *
     * @return array<string, string>
     */
    private static function targetLabels(): array
    {
        return \App\Action\Condition\TargetTypeCondition::targetLabels();
    }

    private const TRAIT_LABELS = [
        'a' => 'A', 'ae' => 'Ae', 'mvt' => 'Mvt', 'pm' => 'PM',
        'pv' => 'PV', 'pf' => 'PF', 'energie' => 'Énergie',
    ];

    public function objectType(): string
    {
        return 'action';
    }

    public function title(): string
    {
        return 'Actions';
    }

    public function render(): string
    {
        $exporter = (new ExporterRegistry())->exporterFor('action');
        if ($exporter === null) {
            return '(exporter des actions indisponible)';
        }

        $itemNames = $this->itemNames();

        // Groupées par catégorie (le classement du workbench et des
        // listes du jeu) ; sans catégorie → le type d'action.
        $byCategory = [];
        foreach ($exporter->exportAll() as $action) {
            $category = trim((string) ($action['category'] ?? ''));
            if ($category === '') {
                $category = '(' . (string) ($action['type'] ?? 'divers') . ')';
            }
            $byCategory[$category][] = $action;
        }
        ksort($byCategory);

        $out = "====== Actions ======\n\n";
        $out .= "Fiche générée depuis le catalogue (admin → Wiki) — coûts et visées dérivés des conditions réelles.\n\n";

        foreach ($byCategory as $category => $actions) {
            usort($actions, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            $out .= '===== ' . $category . " =====\n\n";
            $out .= "^ Nom ^ Type ^ Visée ^ Coût ^ Description ^\n";

            foreach ($actions as $action) {
                $out .= '| ' . $this->cell((string) (($action['displayName'] ?? '') !== '' ? $action['displayName'] : $action['name']))
                    . ' | ' . $this->cell((string) ($action['type'] ?? ''))
                    . ' | ' . $this->cell($this->targetLabel($action['conditions'] ?? []))
                    . ' | ' . $this->cell($this->costLabel($action['conditions'] ?? [], $itemNames))
                    . ' | ' . $this->cell((string) ($action['text'] ?? ''))
                    . " |\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /** @param array<int, array<string, mixed>> $conditions */
    private function targetLabel(array $conditions): string
    {
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') !== 'TargetType') {
                continue;
            }
            $allowed = $condition['parameters']['allowed'] ?? [];
            if (!is_array($allowed) || $allowed === []) {
                $allowed = ['character'];
            }

            $labels = self::targetLabels();

            return implode(' / ', array_map(
                static fn ($kind): string => $labels[$kind] ?? (string) $kind,
                $allowed
            ));
        }

        // Sans condition TargetType : personnages seulement (défaut sûr
        // d'ActionTargeting) — le wiki dit la même chose que le moteur.
        return self::targetLabels()['character'];
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @param array<int, string>               $itemNames
     */
    private function costLabel(array $conditions, array $itemNames): string
    {
        $parts = [];
        foreach ($conditions as $condition) {
            $params = $condition['parameters'] ?? [];

            switch ($condition['type'] ?? '') {
                case 'RequiresTraitValue':
                    foreach ((array) $params as $trait => $value) {
                        if (isset(self::TRAIT_LABELS[$trait]) && (int) $value > 0) {
                            $parts[] = (int) $value . ' ' . self::TRAIT_LABELS[$trait];
                        }
                    }
                    break;

                case 'RequiresItem':
                    $n = max(1, (int) ($params['n'] ?? 1));
                    $item = isset($params['item'])
                        ? ($itemNames[(int) $params['item']] ?? ('objet #' . (int) $params['item']))
                        : "l'objet du geste";
                    $parts[] = (($params['consume'] ?? true) ? $n . ' × ' : 'porte ') . $item;
                    break;

                case 'ItemPick':
                    $parts[] = 'objet au choix (' . (string) ($params['kind'] ?? '?') . ')';
                    break;
            }
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /** @return array<int, string> id => nom technique du catalogue d'objets */
    private function itemNames(): array
    {
        $names = [];
        $res = (new Db())->exe('SELECT id, name FROM items');
        while ($row = $res->fetch_object()) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /** Une cellule DokuWiki ne survit ni aux pipes ni aux retours ligne. */
    private function cell(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ['∣', ' ', ' '], strip_tags($value)));
    }
}
