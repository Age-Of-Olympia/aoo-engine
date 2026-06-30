<?php

namespace App\View\Player;

/**
 * One character's loadout: the action and passive catalogs as two tables, each
 * row a checkbox for ownership. Built from the shared admin components (tables,
 * badges, buttons) so it matches the rest of the admin (see admin/actions.php).
 *
 * The whole thing is one form; a single Save posts the diff. Owned entries with
 * no catalog row — e.g. 'attaquer', the base attack the catalog does not model —
 * are shown read-only (no checkbox), so a save can never silently revoke them.
 *
 * @phpstan-type LoadoutItem array{key:string, label:string, sub:string, category:string, owned:bool, editable:bool, field?:string, value?:string}
 * @phpstan-type PlayerSummary array{id:int, name:string, race:string, player_type:string, xp:int}
 */
final class PlayerLoadoutView
{
    /**
     * @param PlayerSummary $summary
     * @param array<int, array{key:string, label:string, sub:string, category:string, owned:bool, editable:bool, field?:string, value?:string}> $actions
     * @param array<int, array{key:string, label:string, sub:string, category:string, owned:bool, editable:bool, field?:string, value?:string}> $passives
     */
    public function render(array $summary, array $actions, array $passives, string $csrfTokenField): string
    {
        return '<form method="post" action="/admin/player-loadout-save.php">'
            . $csrfTokenField
            . '<input type="hidden" name="player_id" value="' . (int) $summary['id'] . '">'
            . $this->header($summary, $actions, $passives)
            . '<div class="loadout-columns">'
            . $this->column('Actions', $actions)
            . $this->column('Passifs', $passives)
            . '</div>'
            . '<div class="mt-3 mb-4">'
            . '<button type="submit" class="btn btn-primary">Enregistrer le loadout</button> '
            . '<span class="text-muted ml-2">Les entrées « hors catalogue » ne sont pas modifiables ici.</span>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param PlayerSummary $summary
     * @param array<int, array{owned:bool}> $actions
     * @param array<int, array{owned:bool}> $passives
     */
    private function header(array $summary, array $actions, array $passives): string
    {
        $meta = implode(' · ', array_filter([
            $this->esc($summary['race']),
            $this->esc($summary['player_type']),
            (int) $summary['xp'] . ' XP',
        ]));

        return '<div class="d-flex justify-content-between align-items-center mb-1">'
            . '<h1 class="mb-0">' . $this->esc($summary['name'])
            . ' <small class="text-muted">#' . (int) $summary['id'] . '</small></h1>'
            . '<div>'
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/players.php">← Recherche</a> '
            . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
            . '</div>'
            . '</div>'
            . '<p class="text-muted mb-3">' . $meta
            . ' · <span class="badge badge-info">' . $this->countOwned($actions) . '/' . count($actions) . ' actions</span>'
            . ' <span class="badge badge-info">' . $this->countOwned($passives) . '/' . count($passives) . ' passifs</span>'
            . '</p>';
    }

    /**
     * @param array<int, array{key:string, label:string, sub:string, category:string, owned:bool, editable:bool, field?:string, value?:string}> $items
     */
    private function column(string $title, array $items): string
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['category']][] = $item;
        }

        $rows = '';
        foreach ($groups as $category => $groupItems) {
            $rows .= '<tr class="table-secondary">'
                . '<td></td>'
                . '<td><strong>' . $this->esc((string) $category) . '</strong></td>'
                . '<td class="text-right text-muted">' . $this->countOwned($groupItems) . '/' . count($groupItems) . '</td>'
                . '</tr>';
            foreach ($groupItems as $item) {
                $rows .= $this->row($item);
            }
        }

        return '<section class="loadout-col">'
            . '<h2 class="h5 mb-2">' . $this->esc($title)
            . ' <span class="badge badge-info">' . $this->countOwned($items) . '</span></h2>'
            . '<table class="table table-hover"><tbody>' . $rows . '</tbody></table>'
            . '</section>';
    }

    /**
     * @param array{key:string, label:string, sub:string, owned:bool, editable:bool, field?:string, value?:string} $item
     */
    private function row(array $item): string
    {
        $cell = $item['editable']
            ? '<input type="checkbox" class="loadout-checkbox" name="' . $this->esc(($item['field'] ?? '') . '[]')
                . '" value="' . $this->esc($item['value'] ?? '') . '"' . ($item['owned'] ? ' checked' : '')
                . ' aria-label="' . $this->esc($item['label']) . '">'
            : '<span class="text-muted" title="Possédé, hors catalogue — non modifiable ici">✓</span>';

        return '<tr>'
            . '<td class="loadout-check">' . $cell . '</td>'
            . '<td>' . $this->esc($item['label']) . ' <code class="text-muted">' . $this->esc($item['key']) . '</code></td>'
            . '<td class="text-right text-muted">' . $this->esc($item['sub']) . '</td>'
            . '</tr>';
    }

    /**
     * @param array<int, array{owned:bool}> $items
     */
    private function countOwned(array $items): int
    {
        $n = 0;
        foreach ($items as $item) {
            if ($item['owned']) {
                $n++;
            }
        }

        return $n;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
