<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Faction;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

/**
 * Seed des factions depuis les JSON legacy (datas/[public|private]/factions/*.json).
 *
 * Pendant prod du seed de la migration Version20260713120000_FactionsFromJson :
 * le déploiement exécute les migrations depuis le checkout git, où datas/
 * (gitignoré) n'existe pas — la migration n'y trouve aucun JSON et ne crée
 * que des lignes minimales (snapshot + codes déjà référencés par players).
 * Ce service tourne depuis la racine web (admin/faction-seed.php), où datas/
 * existe, et applique les mêmes règles que la migration :
 *
 *  - sur une ligne existante : les drapeaux hidden/secret (possiblement
 *    retouchés par un admin) sont PRÉSERVÉS, le lore ne se remplit que s'il
 *    est vide ; nom, icône et plan de respawn sont rafraîchis depuis le JSON ;
 *  - création : hidden si JSON privé ou drapeau JSON, secret selon le JSON ;
 *  - rôles : remplacés uniquement quand le JSON en fournit — relancer ne
 *    vide jamais une liste éditée en admin.
 *
 * Idempotent : relançable sans effet de bord. Transactionnel : tout ou rien.
 */
class FactionSeedService
{
    private EntityManagerInterface $entityManager;
    private FactionService $factions;
    private string $root;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?FactionService $factions = null,
        ?string $root = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->factions = $factions ?? new FactionService();
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /**
     * Ce que seed() ferait, sans rien écrire.
     *
     * @return array{
     *   entries: list<array{code: string, file: string, private: bool, action: 'create'|'update', roles: int}>,
     *   unreadable: list<string>
     * }
     */
    public function preview(): array
    {
        ['factions' => $found, 'unreadable' => $unreadable] = $this->collectJsonFactions();

        $entries = [];
        foreach ($found as $code => $faction) {
            $entries[] = [
                'code' => $code,
                'file' => $faction['file'],
                'private' => $faction['private'],
                'action' => $this->factions->getFactionByCode($code) !== null ? 'update' : 'create',
                'roles' => count($faction['roles']),
            ];
        }

        return ['entries' => $entries, 'unreadable' => $unreadable];
    }

    /**
     * Applique le seed (tout ou rien) et retourne le bilan.
     *
     * @return array{created: list<string>, updated: list<string>, unreadable: list<string>}
     */
    public function seed(): array
    {
        ['factions' => $found, 'unreadable' => $unreadable] = $this->collectJsonFactions();
        if ($found === []) {
            throw new RuntimeException('Aucun JSON de faction lisible sous ' . $this->root . '/datas/*/factions/.');
        }

        $created = [];
        $updated = [];

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($found as $code => $faction) {
                $this->upsert($code, $faction) ? $created[] = $code : $updated[] = $code;
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $this->entityManager->clear();
            throw $exception;
        }

        FactionService::clearCache();

        return ['created' => $created, 'updated' => $updated, 'unreadable' => $unreadable];
    }

    /**
     * Un JSON de faction par fichier trouvé dans cet environnement.
     *
     * @return array{
     *   factions: array<string, array{json: object, private: bool, file: string,
     *                                 roles: list<array{name: string, flags: array<string, bool>}>}>,
     *   unreadable: list<string>
     * }
     */
    private function collectJsonFactions(): array
    {
        $factions = [];
        $unreadable = [];

        foreach (['public', 'private'] as $visibility) {
            $dir = $this->root . '/datas/' . $visibility . '/factions';
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $json = json_decode((string) file_get_contents($file));
                $relative = 'datas/' . $visibility . '/factions/' . basename($file);
                if (!is_object($json)) {
                    $unreadable[] = $relative;
                    continue;
                }
                $factions[basename($file, '.json')] = [
                    'json' => $json,
                    'private' => $visibility === 'private',
                    'file' => $relative,
                    'roles' => $this->roleList($json->role ?? null),
                ];
            }
        }
        ksort($factions);

        return ['factions' => $factions, 'unreadable' => $unreadable];
    }

    /**
     * Crée ou met à jour une faction depuis son JSON (règles de la
     * migration). Retourne true en création.
     *
     * @param array{json: object, private: bool, file: string,
     *              roles: list<array{name: string, flags: array<string, bool>}>} $entry
     */
    private function upsert(string $code, array $entry): bool
    {
        $json = $entry['json'];

        $faction = $this->factions->getFactionByCode($code);
        $isNew = $faction === null;

        if ($isNew) {
            $faction = new Faction();
            $faction->setCode($code);
            $faction->setHidden(!empty($json->hidden) || $entry['private']);
            $faction->setSecret(!empty($json->secret));
            $faction->setText((string) ($json->text ?? ''));
        } elseif ($faction->getText() === '') {
            // Comme la migration : le lore ne fait que se remplir
            $faction->setText((string) ($json->text ?? ''));
        }

        $faction->setName((string) ($json->name ?? ucwords(str_replace('_', ' ', $code))));
        $faction->setRaFont((string) ($json->raFont ?? ''));
        $faction->setRespawnPlan((string) ($json->respawnPlan ?? 'olympia'));

        $this->factions->save($faction);

        // Rôles remplacés seulement si le JSON en fournit (jamais vidés)
        if ($entry['roles'] !== []) {
            $this->factions->replaceRoles($faction, $entry['roles']);
        }

        return $isNew;
    }

    /**
     * @return list<array{name: string, flags: array<string, bool>}>
     */
    private function roleList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $roles = [];
        foreach ($raw as $role) {
            if (!is_object($role) || trim((string) ($role->name ?? '')) === '') {
                continue;
            }
            $flags = [];
            foreach (\App\Entity\FactionRole::FLAG_KEYS as $key) {
                $flags[$key] = !empty($role->{$key});
            }
            $roles[] = ['name' => trim((string) $role->name), 'flags' => $flags];
        }

        return $roles;
    }
}
