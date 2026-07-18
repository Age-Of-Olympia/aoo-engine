<?php

namespace App\Service\ImportExport;

use App\Entity\Effect;
use App\Entity\EntityManagerFactory;
use App\Service\EffectService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports effect payloads produced by {@see EffectExporter}. Create-or-update
 * by `name` (players_effects et les paramètres d'actions y font référence :
 * jamais renommé). Les scalaires et les deux listes (annulations, matériaux)
 * sont écrasés par le bundle.
 *
 * Mêmes règles de validation que le formulaire (effects-save.php) : code en
 * minuscules, icône RPG-Awesome, caracs connues, chance de casse 0-100. Une
 * annulation vers un effet absent du catalogue ET du bundle est importée mais
 * signalée (le lot peut créer la cible plus loin dans le même import).
 */
final class EffectImporter extends AbstractObjectImporter
{
    private EffectService $effects;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?EffectService $effects = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->effects = $effects ?? new EffectService();
    }

    public function objectType(): string
    {
        return 'effect';
    }

    protected function accept(ImportReport $report, array &$seen, mixed $object, int $index): mixed
    {
        $name = $this->classify($report, $object, $index);
        if ($name === null || $this->isDuplicate($report, $seen, $name)) {
            return null;
        }

        /** @var array<string, mixed> $object */
        if ($this->entityManager->getRepository(Effect::class)->findOneBy(['name' => $name]) !== null) {
            $report->addUpdated($name);
        } else {
            $report->addCreated($name);
        }

        $unknown = array_filter(
            $this->nameList($object, 'controls'),
            fn (string $controlled): bool => !$this->effects->exists($controlled)
        );
        if ($unknown !== []) {
            $report->warn($name, 'Annule des effets inconnus du catalogue : '
                . implode(', ', $unknown) . ' (créés plus loin dans ce bundle ?).');
        }

        return $object;
    }

    protected function applyPlan(mixed $plan): void
    {
        /** @var array<string, mixed> $plan */
        $name = strtolower(trim((string) $plan['name']));

        $effect = $this->entityManager->getRepository(Effect::class)->findOneBy(['name' => $name])
            ?? new Effect($name);

        $label = trim((string) ($plan['label'] ?? ''));
        $effect->setLabel($label !== '' ? $label : ucfirst(strtr($name, '_', ' ')));
        $effect->setDescription((string) ($plan['description'] ?? ''));
        $effect->setIcon((string) $plan['icon']);
        $effect->setHidden((bool) ($plan['hidden'] ?? false));
        $effect->setMapMarker((bool) ($plan['isMapMarker'] ?? false));
        $effect->setBuffCarac(trim((string) ($plan['buffCarac'] ?? '')));
        $effect->setDebuffCarac(trim((string) ($plan['debuffCarac'] ?? '')));
        $effect->setRollAttackMod(max(-1, min(1, (int) ($plan['rollAttackMod'] ?? 0))));
        $effect->setRollDefenseMod(max(-1, min(1, (int) ($plan['rollDefenseMod'] ?? 0))));
        $effect->setDamageDealtMod(max(-1, min(1, (int) ($plan['damageDealtMod'] ?? 0))));
        $effect->setDamageTakenMod(max(-1, min(1, (int) ($plan['damageTakenMod'] ?? 0))));
        $effect->setPushAttackMod(max(-1, min(1, (int) ($plan['pushAttackMod'] ?? 0))));
        $effect->setPushDefenseMod(max(-1, min(1, (int) ($plan['pushDefenseMod'] ?? 0))));
        $factor = (float) ($plan['damageTakenFactor'] ?? 1);
        $effect->setDamageTakenFactor($factor > 0 ? $factor : 1.0);
        $effect->setBlockRecovery((string) ($plan['blockRecovery'] ?? ''));
        $effect->setTurnRegen((bool) ($plan['turnRegen'] ?? false));
        $effect->setTurnMvtMalus((bool) ($plan['turnMvtMalus'] ?? false));
        $effect->setCorruptionBreakChance(
            is_numeric($plan['corruptionBreakChance'] ?? null) ? (int) $plan['corruptionBreakChance'] : null
        );

        // Flush intermédiaire (dans la transaction du lot) : les listes
        // s'écrivent en SQL direct et exigent l'id de l'effet.
        $this->effects->save($effect);
        $this->effects->replaceControls($effect, $this->nameList($plan, 'controls'));
        $this->effects->replaceCorruptionMaterials($effect, $this->nameList($plan, 'corruptionMaterials'));
    }

    /**
     * Validate the object and return its effect name, or null (recording a
     * rejection) when it can't import.
     */
    private function classify(ImportReport $report, mixed $object, int $index): ?string
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return null;
        }

        $name = is_string($object['name'] ?? null) ? strtolower(trim($object['name'])) : '';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $report->reject($name !== '' ? $name : '#' . $index,
                'Code d\'effet manquant ou invalide (minuscules, chiffres, _).');
            return null;
        }

        if (!preg_match('/^ra-[a-z0-9-]+$/', (string) ($object['icon'] ?? ''))) {
            $report->reject($name, 'Icône invalide (classe RPG-Awesome attendue, ex : ra-small-fire).');
            return null;
        }

        foreach (['buffCarac', 'debuffCarac'] as $key) {
            $carac = trim((string) ($object[$key] ?? ''));
            if ($carac !== '' && !isset(CARACS[$carac])) {
                $report->reject($name, "Caractéristique inconnue : {$carac}.");
                return null;
            }
        }

        $breakChance = $object['corruptionBreakChance'] ?? null;
        if ($breakChance !== null && (!is_numeric($breakChance) || (int) $breakChance < 0 || (int) $breakChance > 100)) {
            $report->reject($name, 'Chance de casse invalide (0-100, ou absente).');
            return null;
        }

        foreach (['controls', 'corruptionMaterials'] as $listKey) {
            if (isset($object[$listKey]) && !is_array($object[$listKey])) {
                $report->reject($name, "Liste « {$listKey} » invalide (tableau de noms attendu).");
                return null;
            }
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $object
     * @return array<int, string>
     */
    private function nameList(array $object, string $key): array
    {
        $raw = is_array($object[$key] ?? null) ? $object[$key] : [];

        $clean = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $entry = strtolower(trim((string) $value));
            if ($entry !== '') {
                $clean[] = $entry;
            }
        }

        return array_values(array_unique($clean));
    }
}
