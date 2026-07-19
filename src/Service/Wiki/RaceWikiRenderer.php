<?php

namespace App\Service\Wiki;

use App\Service\ImportExport\ExporterRegistry;

/**
 * Fiche wiki des RACES et des TYPES DE BÂTIMENTS — le même partage que
 * l'admin (Races vs Bâtiments → Types, décision du 2026-07-19) : les
 * peuples jouables avec leurs caracs de départ, puis les types de
 * structures avec PV et nature. Données : RaceExporter (une seule
 * source de vérité, famille bundle `race`).
 */
final class RaceWikiRenderer implements WikiSheetRenderer
{
    public function objectType(): string
    {
        return 'race';
    }

    public function title(): string
    {
        return 'Races & Types';
    }

    public function render(): string
    {
        $exporter = (new ExporterRegistry())->exporterFor('race');
        if ($exporter === null) {
            return '(exporter des races indisponible)';
        }

        $races = [];
        $types = [];
        foreach ($exporter->exportAll() as $race) {
            if (($race['kind'] ?? 'character') === 'structure') {
                $types[] = $race;
            } elseif (!empty($race['playable'])) {
                $races[] = $race;
            }
        }
        usort($races, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));
        usort($types, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        $out = "====== Races ======\n\n";
        $out .= "Fiche générée depuis le catalogue (admin → Wiki) — caracs de départ réelles.\n\n";

        foreach ($races as $race) {
            $out .= '===== ' . (string) $race['label'] . " =====\n";
            if ((string) ($race['description'] ?? '') !== '') {
                $out .= $this->text((string) $race['description']) . "\n";
            }
            $caracs = (array) ($race['caracs'] ?? []);
            $line = [];
            foreach (CARACS as $key => $short) {
                $value = (int) ($caracs[$key] ?? 0);
                if ($value !== 0) {
                    $line[] = $short . ' ' . $value;
                }
            }
            if ($line !== []) {
                $out .= '  * Caracs de départ : ' . implode(', ', $line) . "\n";
            }
            if ((string) ($race['faction'] ?? '') !== '') {
                $out .= '  * Faction de départ : ' . (string) $race['faction'] . "\n";
            }
            $out .= "\n";
        }

        $out .= "====== Types de bâtiments ======\n\n";
        $out .= "^ Type ^ Nature ^ PV ^ Bloque le passage ^ Bloque les tirs ^\n";
        foreach ($types as $type) {
            $caracs = (array) ($type['caracs'] ?? []);
            $out .= '| ' . $this->cell((string) $type['label'])
                . ' | ' . (($type['structureNature'] ?? 'edifice') === 'obstacle' ? 'Obstacle' : 'Édifice')
                . ' | ' . (int) ($caracs['pv'] ?? 0)
                . ' | ' . (!empty($type['blocks_passage']) ? 'oui' : 'non')
                . ' | ' . (!empty($type['blocks_projectiles']) ? 'oui' : 'non')
                . " |\n";
        }

        return $out;
    }

    private function cell(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ['∣', ' ', ' '], strip_tags($value)));
    }

    private function text(string $value): string
    {
        return trim(strip_tags($value));
    }
}
