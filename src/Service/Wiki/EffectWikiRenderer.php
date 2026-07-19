<?php

namespace App\Service\Wiki;

use App\Entity\Effect;
use App\Service\EffectService;

/**
 * Fiche wiki des EFFETS — la page regles:effets. C'est le modèle
 * historique de toute la famille wiki (ex-admin/effects.php, migré au
 * registre le 2026-07-20) : la description rédigée PLUS une règle par
 * comportement configuré, dérivée du moteur — le wiki ne peut pas
 * mentir sur les mécaniques. Les fiches de personnage pointent les
 * ancres #nom_de_l_effet.
 */
final class EffectWikiRenderer implements WikiSheetRenderer
{
    public function objectType(): string
    {
        return 'effect';
    }

    public function title(): string
    {
        return 'Effets';
    }

    public function render(): string
    {
        $effects = array_filter(
            (new EffectService())->getAllEffects(),
            static fn (Effect $effect): bool => !$effect->isMapMarker()
        );
        usort($effects, static fn (Effect $a, Effect $b): int => strcoll($a->getLabel(), $b->getLabel()));

        $markup = "====== Effets ======\n";
        foreach ($effects as $effect) {
            $markup .= "\n===== " . $effect->getLabel() . " =====\n";
            if ($effect->getDescription() !== '') {
                $markup .= $effect->getDescription() . "\n";
            }
            foreach ($this->rules($effect) as $rule) {
                $markup .= '  * ' . $rule . "\n";
            }
        }

        return $markup;
    }

    /**
     * Une règle lisible par comportement configuré — dérivée des champs
     * moteur de l'effet, jamais rédigée à la main.
     *
     * @return list<string>
     */
    private function rules(Effect $effect): array
    {
        $rules = [];
        $carac = static fn (?string $key): string => CARACS_TXT[$key] ?? strtoupper((string) $key);

        if ($effect->getBuffCarac() !== null) {
            $rules[] = 'Augmente ' . $carac($effect->getBuffCarac()) . ' de la valeur portée.';
        }
        if ($effect->getDebuffCarac() !== null) {
            $rules[] = 'Diminue ' . $carac($effect->getDebuffCarac()) . ' de la valeur portée.';
        }
        foreach ([
            'getRollAttackMod' => 'au jet d\'attaque', 'getRollDefenseMod' => 'au jet de défense',
            'getDamageDealtMod' => 'aux dégâts infligés', 'getDamageTakenMod' => 'aux dégâts subis',
            'getPushAttackMod' => 'aux poussées portées', 'getPushDefenseMod' => 'à la résistance aux poussées',
        ] as $getter => $where) {
            $mod = $effect->{$getter}();
            if ($mod !== 0) {
                $rules[] = ($mod > 0 ? '+' : '−') . 'valeur portée ' . $where . '.';
            }
        }
        if ($effect->getDamageTakenFactor() != 1.0) {
            $rules[] = 'Dégâts subis ×' . $effect->getDamageTakenFactor() . ' (minimum 1).';
        }
        if ($effect->getBlockRecovery() !== '') {
            $rules[] = 'Bloque la récupération de ' . strtoupper($effect->getBlockRecovery())
                . ' au nouveau tour, puis expire.';
        }
        if ($effect->isTurnRegen()) {
            $rules[] = 'La récupération de PV du tour gagne +RM, puis expire.';
        }
        if ($effect->isTurnMvtMalus()) {
            $rules[] = 'Retire sa valeur en Mouvements au tour suivant.';
        }
        if ($effect->getDodgeScope() !== '') {
            $scopes = ['any' => 'toute attaque', 'physical' => 'toute attaque physique', 'spell' => 'tout sort offensif'];
            $rule = 'Posture : annule ' . ($scopes[$effect->getDodgeScope()] ?? $effect->getDodgeScope());
            if ($effect->getDodgeAttackerWeapon() === 'melee') {
                $rule .= ' d\'un attaquant armé en mêlée';
            }
            if ($effect->getDodgeDefenderWeapon() === 'melee') {
                $rule .= ' (arme de mêlée en main requise)';
            } elseif ($effect->getDodgeDefenderWeapon() === 'poing') {
                $rule .= ' (mains nues requises)';
            }
            $rule .= match ($effect->getDodgeReaction()) {
                'immobilize_attacker' => ' et immobilise l\'attaquant',
                'step_aside' => ' en se décalant d\'une case',
                'delete_double' => ' — c\'était un double',
                default => '',
            };
            $rules[] = $rule . '. Consommée au déclenchement.';
        }
        if ($effect->grantsFlight()) {
            $rules[] = 'Permet de voler : traverse les obstacles, ne laisse pas de traces.';
        }
        if ($effect->isCostMultiplier()) {
            $rules[] = 'Multiplie certains coûts d\'action par (valeur portée + 1).';
        }
        if ($effect->blocksTrading()) {
            $rules[] = 'Interdit de marchander et d\'apprendre aux écoles, des deux côtés.';
        }
        if ($effect->getControlNames() !== []) {
            $rules[] = 'Annule : ' . implode(', ', $effect->getControlNames()) . '.';
        }
        if ($effect->isBuildableOver()) {
            $rules[] = 'Au sol : n\'empêche ni construction ni aménagement de la case.';
        }
        if ($effect->getCorruptionBreakChance() !== null) {
            $rules[] = 'Corruption : fragilise le matériel contenant '
                . implode(', ', $effect->getCorruptionMaterialNames()) . '.';
        }

        return $rules;
    }
}
