<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use App\Entity\Faction;
use App\Entity\FactionRole;
use App\Service\FactionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports faction payloads produced by {@see FactionExporter}. Create-or-update
 * by `code` (players.faction y fait référence : jamais renommé). Les scalaires,
 * les drapeaux et la liste ordonnée des rôles sont écrasés par le bundle.
 *
 * Mêmes règles de validation que le formulaire (factions-save.php) : code en
 * minuscules, rôles = tableau d'objets à nom non vide. ⚠ L'ordre des rôles du
 * bundle devient les positions 0..n-1 : importer une liste réordonnée décale
 * players.factionRole des membres existants, comme une édition manuelle.
 */
final class FactionImporter extends AbstractObjectImporter
{
    private FactionService $factions;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?FactionService $factions = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->factions = $factions ?? new FactionService();
    }

    public function objectType(): string
    {
        return 'faction';
    }

    protected function accept(ImportReport $report, array &$seen, mixed $object, int $index): mixed
    {
        $code = $this->classify($report, $object, $index);
        if ($code === null || $this->isDuplicate($report, $seen, $code)) {
            return null;
        }

        /** @var array<string, mixed> $object */
        if ($this->entityManager->getRepository(Faction::class)->findOneBy(['code' => $code]) !== null) {
            $report->addUpdated($code);
        } else {
            $report->addCreated($code);
        }

        return $object;
    }

    protected function applyPlan(mixed $plan): void
    {
        /** @var array<string, mixed> $plan */
        $code = strtolower(trim((string) $plan['code']));

        $faction = $this->entityManager->getRepository(Faction::class)->findOneBy(['code' => $code]);
        if ($faction === null) {
            $faction = new Faction();
            $faction->setCode($code);
        }

        $name = trim((string) ($plan['name'] ?? ''));
        $faction->setName($name !== '' ? $name : ucwords(str_replace('_', ' ', $code)));
        $faction->setText((string) ($plan['text'] ?? ''));
        $faction->setRaFont((string) ($plan['raFont'] ?? ''));
        $respawnPlan = trim((string) ($plan['respawnPlan'] ?? ''));
        $faction->setRespawnPlan($respawnPlan !== '' ? $respawnPlan : 'olympia');
        $faction->setHidden((bool) ($plan['hidden'] ?? false));
        $faction->setSecret((bool) ($plan['secret'] ?? false));

        // Flush intermédiaire (dans la transaction du lot) : les rôles
        // s'écrivent en SQL direct et exigent l'id de la faction.
        $this->factions->save($faction);
        $this->factions->replaceRoles($faction, $this->roleList($plan));
    }

    /**
     * Validate the object and return its faction code, or null (recording a
     * rejection) when it can't import.
     */
    private function classify(ImportReport $report, mixed $object, int $index): ?string
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return null;
        }

        $code = is_string($object['code'] ?? null) ? strtolower(trim($object['code'])) : '';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            $report->reject($code !== '' ? $code : '#' . $index,
                'Code de faction manquant ou invalide (minuscules, chiffres, _).');
            return null;
        }

        if (isset($object['roles'])) {
            if (!is_array($object['roles'])) {
                $report->reject($code, 'Liste « roles » invalide (tableau de rôles attendu).');
                return null;
            }
            foreach ($object['roles'] as $role) {
                if (!is_array($role) || trim((string) ($role['name'] ?? '')) === '') {
                    $report->reject($code, 'Rôle invalide : chaque rôle doit être un objet avec un nom non vide.');
                    return null;
                }
            }
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $plan
     * @return list<array{name: string, flags: array<string, bool>}>
     */
    private function roleList(array $plan): array
    {
        $roles = [];
        foreach ((array) ($plan['roles'] ?? []) as $role) {
            if (!is_array($role)) {
                continue;
            }
            $flags = [];
            foreach (FactionRole::FLAG_KEYS as $key) {
                $flags[$key] = (bool) ($role[$key] ?? false);
            }
            $roles[] = ['name' => trim((string) $role['name']), 'flags' => $flags];
        }

        return $roles;
    }
}
