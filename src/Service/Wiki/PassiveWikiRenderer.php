<?php

namespace App\Service\Wiki;

use App\Interface\WikiSheetRendererInterface;
use App\Action\Combat\PassiveValueCalculator;
use App\Service\ImportExport\ExporterRegistry;

/**
 * Fiche wiki des PASSIFS (compétences passives) : tableaux DokuWiki par
 * catégorie, bonus DÉRIVÉ des colonnes du moteur — la base de calcul
 * (carac ou base spéciale de PassiveValueCalculator) multipliée par la
 * valeur, appliquée aux jets d'attaque/défense sur les traits couverts.
 * Données : PassiveExporter (une seule source de vérité, famille
 * bundle `passive`).
 */
final class PassiveWikiRenderer implements WikiSheetRendererInterface
{
    private const TYPE_LABELS = ['att' => 'Attaque', 'def' => 'Défense', 'mixte' => 'Mixte'];

    public function objectType(): string
    {
        return 'passive';
    }

    public function title(): string
    {
        return 'Passifs';
    }

    public function render(): string
    {
        $exporter = (new ExporterRegistry())->exporterFor('passive');
        if ($exporter === null) {
            return '(exporter des passifs indisponible)';
        }

        $byCategory = [];
        foreach ($exporter->exportAll() as $passive) {
            $category = trim((string) ($passive['category'] ?? ''));
            $byCategory[$category !== '' ? $category : '(sans catégorie)'][] = $passive;
        }
        ksort($byCategory);

        $out = "====== Passifs ======\n\n";
        $out .= "Fiche générée depuis le catalogue (admin → Wiki) — bonus dérivés des colonnes réelles.\n\n";

        if ($byCategory === []) {
            return $out . "(aucun passif au catalogue de cet environnement)\n";
        }

        foreach ($byCategory as $category => $passives) {
            usort($passives, fn (array $a, array $b): int =>
                strcmp($this->displayName($a), $this->displayName($b)));

            $out .= '===== ' . ucfirst($category) . " =====\n\n";
            $out .= "^ Passif ^ Type ^ S'applique à ^ Bonus ^ Niveau ^ Race ^ Prérequis ^ Description ^\n";

            foreach ($passives as $passive) {
                $out .= '| ' . $this->cell($this->displayName($passive))
                    . ' | ' . (self::TYPE_LABELS[(string) ($passive['type'] ?? '')] ?? (string) ($passive['type'] ?? '—'))
                    . ' | ' . $this->cell($this->traitsLabel($passive))
                    . ' | ' . $this->cell($this->bonusLabel($passive))
                    . ' | ' . (int) ($passive['level'] ?? 0)
                    . ' | ' . $this->cell((string) ($passive['race'] ?? '') !== '' ? (string) $passive['race'] : '—')
                    . ' | ' . $this->cell((string) ($passive['prerequisites'] ?? '') !== '' ? (string) $passive['prerequisites'] : '—')
                    . ' | ' . $this->cell((string) ($passive['text'] ?? ''))
                    . " |\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /** @param array<string, mixed> $passive */
    private function displayName(array $passive): string
    {
        $display = trim((string) ($passive['displayName'] ?? ''));

        return $display !== '' ? $display : (string) ($passive['name'] ?? '');
    }

    /**
     * Les traits couverts : les jets portant ces caracs/compétences
     * bénéficient du passif.
     *
     * @param array<string, mixed> $passive
     */
    private function traitsLabel(array $passive): string
    {
        $traits = array_map(
            static fn ($trait): string => CARACS_TXT[(string) $trait] ?? (string) $trait,
            (array) ($passive['traits'] ?? [])
        );

        return $traits === [] ? '—' : implode(', ', $traits);
    }

    /**
     * Le MÊME calcul que le moteur (PassiveValueCalculator) : base
     * (carac ou base spéciale) × valeur, arrondi bas.
     *
     * @param array<string, mixed> $passive
     */
    private function bonusLabel(array $passive): string
    {
        $carac = (string) ($passive['carac'] ?? '');
        $value = rtrim(rtrim(sprintf('%.2f', (float) ($passive['value'] ?? 0)), '0'), '.');

        if ($carac === 'fixed') {
            return '+' . $value;
        }
        if ($carac === 'advantage') {
            return PassiveValueCalculator::SPECIAL_CARACS['advantage'];
        }
        if (isset(PassiveValueCalculator::SPECIAL_CARACS[$carac])) {
            return PassiveValueCalculator::SPECIAL_CARACS[$carac] . ' × ' . $value;
        }

        return (CARACS_TXT[$carac] ?? strtoupper($carac)) . ' × ' . $value;
    }

    private function cell(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ['∣', ' ', ' '], strip_tags($value)));
    }
}
