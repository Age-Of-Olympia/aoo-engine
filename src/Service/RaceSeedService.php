<?php

namespace App\Service;

use App\Entity\CharacterRace;
use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

/**
 * Seed des races depuis les JSON legacy (datas/[public|private]/races/*.json).
 *
 * Pendant prod du seed de la migration Version20260710120000_RacesFromJson :
 * le déploiement exécute les migrations depuis le checkout git, où datas/
 * (gitignoré) n'existe pas — la migration n'y trouve aucun JSON et ne seed
 * que des lignes à stats nulles pour les races nommées par le code. Ce
 * service tourne depuis la racine web (admin/race-seed.php), où datas/
 * existe, et applique les mêmes règles que la migration :
 *
 *  - sur une ligne existante : identité (code, name), drapeaux
 *    (playable/hidden), lore et compteurs de portraits sont PRÉSERVÉS
 *    (possiblement retouchés par un admin) ; libellé, couleurs, faction,
 *    plan, animateur et les 16 CARACS sont rafraîchis depuis le JSON ; la
 *    description ne se remplit que si elle est vide ;
 *  - création : drapeaux comme la migration (jouable si dans le snapshot
 *    RACES et JSON présent ; cachée si JSON privé) ;
 *  - listes (actions de départ, sorts) : remplacées uniquement quand le
 *    JSON en fournit — relancer ne vide jamais une liste éditée en admin.
 *
 * Idempotent : relançable sans effet de bord. Transactionnel : tout ou rien.
 */
class RaceSeedService
{
    /**
     * Snapshot de la constante RACES (races jouables à l'inscription),
     * copié de la migration — on ne modifie jamais une migration mergée.
     */
    private const PLAYABLE = ['nain', 'geant', 'olympien', 'hs', 'elfe'];

    private EntityManagerInterface $entityManager;
    private RaceService $races;
    private string $root;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?RaceService $races = null,
        ?string $root = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->races = $races ?? new RaceService();
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /**
     * Ce que seed() ferait, sans rien écrire.
     *
     * @return array{
     *   entries: list<array{name: string, file: string, private: bool, action: 'create'|'update',
     *                       starterActions: int, spells: int}>,
     *   unreadable: list<string>
     * }
     */
    public function preview(): array
    {
        ['races' => $found, 'unreadable' => $unreadable] = $this->collectJsonRaces();

        $entries = [];
        foreach ($found as $name => $race) {
            $entries[] = [
                'name'           => $name,
                'file'           => $race['file'],
                'private'        => $race['private'],
                'action'         => $this->races->getRaceByName($name) !== null ? 'update' : 'create',
                'starterActions' => count($race['actions']),
                'spells'         => count($race['spells']),
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
        ['races' => $found, 'unreadable' => $unreadable] = $this->collectJsonRaces();
        if ($found === []) {
            throw new RuntimeException('Aucun JSON de race lisible sous ' . $this->root . '/datas/*/races/.');
        }

        $created = [];
        $updated = [];

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($found as $name => $race) {
                $this->upsert($name, $race) ? $created[] = $name : $updated[] = $name;
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $this->entityManager->clear();
            throw $exception;
        }

        RaceService::clearCache();

        return ['created' => $created, 'updated' => $updated, 'unreadable' => $unreadable];
    }

    /**
     * Un JSON de race par fichier trouvé dans cet environnement.
     *
     * @return array{
     *   races: array<string, array{json: object, private: bool, file: string,
     *                              actions: list<string>, spells: list<string>}>,
     *   unreadable: list<string>
     * }
     */
    private function collectJsonRaces(): array
    {
        $races = [];
        $unreadable = [];

        foreach (['public', 'private'] as $visibility) {
            $dir = $this->root . '/datas/' . $visibility . '/races';
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $json = json_decode((string) file_get_contents($file));
                $relative = 'datas/' . $visibility . '/races/' . basename($file);
                if (!is_object($json)) {
                    $unreadable[] = $relative;
                    continue;
                }
                $races[basename($file, '.json')] = [
                    'json'    => $json,
                    'private' => $visibility === 'private',
                    'file'    => $relative,
                    'actions' => $this->nameList($json->actions ?? null),
                    'spells'  => $this->nameList($json->spells ?? null),
                ];
            }
        }
        ksort($races);

        return ['races' => $races, 'unreadable' => $unreadable];
    }

    /**
     * Crée ou met à jour une race depuis son JSON (règles de la migration).
     * Retourne true en création.
     *
     * @param array{json: object, private: bool, file: string, actions: list<string>, spells: list<string>} $entry
     */
    private function upsert(string $name, array $entry): bool
    {
        $json = $entry['json'];

        $race = $this->races->getRaceByName($name);
        $isNew = $race === null;

        if ($isNew) {
            /* Ce semis ne verse que des PEUPLES : il lit les JSON de races,
               d'où sortent des personnages et rien d'autre. */
            $race = new CharacterRace();
            $race->setName($name);
            $race->setCode(strtoupper($name));
            $race->setPlayable(in_array($name, self::PLAYABLE, true));
            $race->setHidden($entry['private']);
            $race->setDescription((string) ($json->text ?? ''));
        } elseif ($race->getDescription() === '') {
            // Comme la migration : la description ne fait que se remplir
            $race->setDescription((string) ($json->text ?? ''));
        }

        $race->setLabel((string) ($json->name ?? ucfirst($name)));
        $race->setBgColor($this->normalizeBgColor((string) ($json->bgColor ?? '#FFFFFF')));
        $race->setColor((string) ($json->color ?? 'black'));
        $race->setFaction((string) ($json->faction ?? ''));
        $race->setPlan((string) ($json->plan ?? ''));
        $race->setAnimateurId(isset($json->animateur) ? (int) $json->animateur : null);

        foreach (Race::CARAC_KEYS as $key) {
            $race->setCarac($key, (int) ($json->{$key} ?? 0));
        }

        $this->races->save($race);

        // Listes remplacées seulement si le JSON en fournit (jamais vidées)
        if ($entry['actions'] !== [] || $entry['spells'] !== []) {
            $this->races->replaceNameLists(
                $race,
                $entry['actions'] !== [] ? $entry['actions'] : $race->getStarterActionNames(),
                $entry['spells'] !== [] ? $entry['spells'] : $race->getSpellNames()
            );
        }

        return $isNew;
    }

    /**
     * bgColor alimente sscanf("#%02x%02x%02x") dans les couches de carte :
     * les noms CSS du JSON ('white') doivent devenir de l'hexadécimal.
     */
    private function normalizeBgColor(string $color): string
    {
        return ['white' => '#FFFFFF', 'black' => '#000000'][strtolower($color)] ?? $color;
    }

    /**
     * @return list<string>
     */
    private function nameList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $clean[] = trim((string) $value);
            }
        }

        return array_values(array_unique($clean));
    }
}
