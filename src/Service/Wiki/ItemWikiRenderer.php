<?php

namespace App\Service\Wiki;

use App\Service\ImportExport\ExporterRegistry;

/**
 * Fiche wiki des OBJETS : tableaux DokuWiki par type (équipement,
 * consommable, constructible, matière…), bonus et charge de
 * consommation DÉRIVÉS des colonnes du catalogue — remplace le
 * générateur JSON legacy (scripts/tools/generate_items_wiki.php, races
 * codées en dur, source crafts obsolète).
 */
final class ItemWikiRenderer implements WikiSheetRenderer
{
    private const TYPE_ORDER = ['equipement', 'consommable', 'constructible', 'matiere'];

    private const PAYLOAD_LABELS = [
        'pv' => 'PV', 'pm' => 'PM', 'mvt' => 'Mvt', 'a' => 'A', 'ae' => 'Ae',
        'pr' => 'PR', 'pf' => 'PF', 'malus' => 'malus',
    ];

    public function objectType(): string
    {
        return 'item';
    }

    public function title(): string
    {
        return 'Objets';
    }

    public function render(): string
    {
        $exporter = (new ExporterRegistry())->exporterFor('item');
        if ($exporter === null) {
            return '(exporter des objets indisponible)';
        }

        $byType = [];
        foreach ($exporter->exportAll() as $item) {
            if (!empty($item['is_deprecated'])) {
                continue;
            }
            $type = trim((string) ($item['type'] ?? ''));
            $byType[$type !== '' ? $type : '(sans type)'][] = $item;
        }

        // Types connus d'abord, dans l'ordre du jeu, le reste ensuite.
        uksort($byType, static function (string $a, string $b): int {
            $ia = array_search($a, self::TYPE_ORDER, true);
            $ib = array_search($b, self::TYPE_ORDER, true);
            return [($ia === false ? PHP_INT_MAX : $ia), $a] <=> [($ib === false ? PHP_INT_MAX : $ib), $b];
        });

        $out = "====== Objets ======\n\n";
        $out .= "Fiche générée depuis le catalogue (admin → Wiki) — bonus et effets dérivés des colonnes réelles.\n\n";

        foreach ($byType as $type => $items) {
            usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            $out .= '===== ' . ucfirst($type) . " =====\n\n";
            $out .= "^ Objet ^ Emplacement ^ Bonus / Effet ^ Prix ^ Usure ^ Description ^\n";

            foreach ($items as $item) {
                $out .= '| {{https://age-of-olympia.net/img/items/' . $item['name'] . '_mini.webp?nolink|}} '
                    . $this->cell($this->displayName($item))
                    . ' | ' . $this->cell((string) ($item['emplacement'] ?? '') !== '' ? (string) $item['emplacement'] : '—')
                    . ' | ' . $this->cell($this->bonusLabel($item))
                    . ' | ' . $this->cell((string) (int) ($item['price'] ?? 0))
                    . ' | ' . $this->cell($this->wearLabel($item))
                    . ' | ' . $this->cell((string) ($item['text'] ?? ''))
                    . " |\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /** @param array<string, mixed> $item */
    private function displayName(array $item): string
    {
        $extra = $this->extraOf($item);

        return !empty($extra['name']) ? (string) $extra['name'] : (string) $item['name'];
    }

    /**
     * La clé extra du payload : l'exporter la livre DÉJÀ décodée en
     * tableau — mais on tolère la chaîne JSON brute (autre source).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function extraOf(array $item): array
    {
        $extra = $item['extra'] ?? null;
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }

        return is_array($extra) ? $extra : [];
    }

    /**
     * Caracs non nulles (équipement) et charge de consommation — le même
     * DONNÉ que le moteur applique (applyConsumablePayload, applyItemCaracs).
     *
     * @param array<string, mixed> $item
     */
    private function bonusLabel(array $item): string
    {
        $parts = [];

        foreach (array_keys(CARACS) as $key) {
            $value = (int) ($item[$key] ?? 0);
            if ($value !== 0) {
                $label = self::PAYLOAD_LABELS[$key] ?? strtoupper($key);
                $parts[] = sprintf('%+d %s', $value, $label);
            }
        }
        foreach (['pr', 'pf', 'malus'] as $key) {
            $value = (int) ($item[$key] ?? 0);
            if ($value !== 0) {
                $parts[] = sprintf('%+d %s', $value, self::PAYLOAD_LABELS[$key]);
            }
        }

        $extra = $this->extraOf($item);
        foreach ((array) ($extra['effet'] ?? []) as $effet) {
            $effet = (string) $effet;
            $parts[] = str_starts_with($effet, '-')
                ? 'dissipe ' . substr($effet, 1)
                : 'effet ' . $effet;
        }
        if ((string) ($item['spell'] ?? '') !== '') {
            $parts[] = 'sort : ' . (string) $item['spell'];
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /** @param array<string, mixed> $item */
    private function wearLabel(array $item): string
    {
        $rate = (int) ($item['wear_rate'] ?? 0);
        $triggers = trim((string) ($item['wear_triggers'] ?? ''));
        if ($rate <= 0 || $triggers === '') {
            return '—';
        }

        return '−' . $rate . '/tour (' . $triggers . '), durabilité ' . (int) ($item['durability_max'] ?? 100);
    }

    private function cell(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ['∣', ' ', ' '], strip_tags($value)));
    }
}
