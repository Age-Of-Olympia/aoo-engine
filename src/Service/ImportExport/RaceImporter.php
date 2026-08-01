<?php

namespace App\Service\ImportExport;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use App\Service\ActionService;
use App\Service\FactionService;
use App\Service\RaceService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports race payloads produced by {@see RaceExporter}. Create-or-update by
 * `name` (players.race y fait référence : jamais renommé). Les scalaires, les
 * 16 CARACS et les deux listes de noms sont écrasés par le bundle ; les
 * compteurs de portraits/avatars de l'environnement sont préservés.
 *
 * Mêmes règles de validation que le formulaire (races-save.php) : code de
 * race en minuscules, bgColor hexadécimal (il alimente sscanf dans les
 * couches de carte). Les noms d'actions inconnus du jeu sont importés mais
 * signalés en avertissement, comme le fait le formulaire.
 */
final class RaceImporter extends AbstractObjectImporter
{
    private RaceService $races;
    private ActionService $actions;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?RaceService $races = null,
        ?ActionService $actions = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->races = $races ?? new RaceService();
        $this->actions = $actions ?? new ActionService();
    }

    public function objectType(): string
    {
        return 'race';
    }

    protected function accept(ImportReport $report, array &$seen, mixed $object, int $index): mixed
    {
        $name = $this->classify($report, $object, $index);
        if ($name === null || $this->isDuplicate($report, $seen, $name)) {
            return null;
        }

        /** @var array<string, mixed> $object */
        if ($this->entityManager->getRepository(Race::class)->findOneBy(['name' => $name]) !== null) {
            $report->addUpdated($name);
        } else {
            $report->addCreated($name);
        }

        $this->warnOnUnknownActionNames($report, $name, $object);
        $this->warnOnUnknownFaction($report, $name, $object);

        return $object;
    }

    protected function applyPlan(mixed $plan): void
    {
        /** @var array<string, mixed> $plan */
        $name = strtolower(trim((string) $plan['name']));

        $race = $this->entityManager->getRepository(Race::class)->findOneBy(['name' => $name]);
        if ($race === null) {
            /* La famille se fixe à la création, depuis ce que le bundle
               annonce : un type importé naît dans sa déclinaison, il n'y est
               pas déplacé après coup. */
            $race = Race::ofFamily(
                ($plan['kind'] ?? '') === 'structure' ? 'structure' : 'character',
                (string) ($plan['structureNature'] ?? '')
            );
            $race->setName($name);
            $race->setCode(strtoupper($name));
            $race->setPlayable(false);
            $race->setHidden(false);
        }

        $label = trim((string) ($plan['label'] ?? ''));
        $race->setLabel($label !== '' ? $label : ucfirst($name));
        $race->setDescription((string) ($plan['description'] ?? ''));
        $race->setPlayable((bool) ($plan['playable'] ?? $race->getPlayable()));
        $race->setHidden((bool) ($plan['hidden'] ?? $race->getHidden()));
        $race->setKind(($plan['kind'] ?? $race->getKind()) === 'structure' ? 'structure' : 'character');
        $race->setStructureNature(($plan['structureNature'] ?? $race->getStructureNature()) === 'obstacle' ? 'obstacle' : 'edifice');
        $race->setBleeds((string) ($plan['bleeds'] ?? $race->getBleeds()));
        $race->setWoundColor((string) ($plan['wound_color'] ?? $race->getWoundColor()));
        $race->setBlocksPassage((bool) ($plan['blocks_passage'] ?? $race->blocksPassage()));
        $race->setBlocksProjectiles((bool) ($plan['blocks_projectiles'] ?? $race->blocksProjectiles()));
        $race->setBgColor((string) $plan['bgColor']);
        $race->setColor(trim((string) ($plan['color'] ?? '')) !== '' ? (string) $plan['color'] : 'black');
        $race->setFaction((string) ($plan['faction'] ?? ''));
        $race->setPlan((string) ($plan['plan'] ?? ''));
        $race->setAnimateurId(is_numeric($plan['animateurId'] ?? null) ? (int) $plan['animateurId'] : null);

        $caracs = is_array($plan['caracs'] ?? null) ? $plan['caracs'] : [];
        foreach (Race::CARAC_KEYS as $key) {
            $race->setCarac($key, (int) ($caracs[$key] ?? 0));
        }

        // Flush intermédiaire (dans la transaction du lot) : les listes
        // s'écrivent en SQL direct et exigent l'id de la race.
        $this->races->save($race);
        $this->races->replaceNameLists(
            $race,
            $this->nameList($plan, 'starterActions'),
            $this->nameList($plan, 'spells')
        );
    }

    /**
     * Validate the object and return its race name, or null (recording a
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
                'Code de race manquant ou invalide (minuscules, chiffres, _).');
            return null;
        }

        // bgColor alimente sscanf("#%02x%02x%02x") dans les couches de carte
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($object['bgColor'] ?? ''))) {
            $report->reject($name, 'Couleur de fond invalide (format attendu : #RRGGBB).');
            return null;
        }

        foreach (['starterActions', 'spells'] as $listKey) {
            if (isset($object[$listKey]) && !is_array($object[$listKey])) {
                $report->reject($name, "Liste « {$listKey} » invalide (tableau de noms attendu).");
                return null;
            }
        }

        return $name;
    }

    /**
     * Même garde anti-typo que le formulaire : les noms absents du catalogue
     * d'actions sont importés quand même (l'action peut être configurée
     * après) mais signalés.
     *
     * @param array<string, mixed> $object
     */
    private function warnOnUnknownActionNames(ImportReport $report, string $name, array $object): void
    {
        $known = $this->actions->getKnownActionNames();
        $unknown = [];
        foreach (array_merge($this->nameList($object, 'starterActions'), $this->nameList($object, 'spells')) as $actionName) {
            if (!isset($known[$actionName])) {
                $unknown[] = $actionName;
            }
        }

        if ($unknown !== []) {
            $report->warn($name, 'Noms inconnus du jeu (vérifiez l\'orthographe) : '
                . implode(', ', array_unique($unknown)) . '.');
        }
    }

    /**
     * Compat ascendante : les bundles peuvent porter des codes de faction
     * antérieurs au catalogue (ou destinés à un autre environnement) — ils
     * sont importés tels quels mais signalés, comme le fait le formulaire
     * races-save.php.
     *
     * @param array<string, mixed> $object
     */
    private function warnOnUnknownFaction(ImportReport $report, string $name, array $object): void
    {
        $faction = strtolower(trim((string) ($object['faction'] ?? '')));
        if ($faction !== '' && (new FactionService())->getFactionByCode($faction) === null) {
            $report->warn($name, "Faction « {$faction} » inconnue du catalogue"
                . ' (créez-la dans admin/factions.php ou importez son bundle).');
        }
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
            $entry = trim((string) $value);
            if ($entry !== '') {
                $clean[] = $entry;
            }
        }

        return array_values(array_unique($clean));
    }
}
