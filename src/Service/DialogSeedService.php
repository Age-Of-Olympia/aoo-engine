<?php

namespace App\Service;

use Classes\Db;
use RuntimeException;
use Throwable;

/**
 * Seed des dialogues de jeu depuis les JSON legacy
 * (datas/[public|private]/dialogs/*.json) vers la table `dialogs`.
 *
 * Même raison d'être que RaceSeedService : le déploiement exécute les
 * migrations depuis le checkout git, où datas/ (gitignoré) n'existe pas —
 * la migration DialogsFromJson ne crée donc que la table. Ce service tourne
 * depuis la racine web (admin/dialog-seed.php), où datas/ existe.
 *
 * Règle de préservation, plus simple que celle des races : une ligne
 * existante est PRÉSERVÉE telle quelle (« conservée » au bilan) — le seed ne
 * fait que créer les manquantes. Relancer n'écrase donc jamais un dialogue
 * édité en admin, ni le dialogue `register` réécrit à chaque inscription.
 *
 * Idempotent ; transactionnel (tout ou rien).
 */
class DialogSeedService
{
    private Db $db;
    private DialogService $dialogs;
    private string $root;

    public function __construct(?Db $db = null, ?DialogService $dialogs = null, ?string $root = null)
    {
        $this->db = $db ?? new Db();
        $this->dialogs = $dialogs ?? new DialogService();
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /**
     * Ce que seed() ferait, sans rien écrire.
     *
     * @return array{
     *   entries: list<array{name: string, file: string, private: bool, action: 'create'|'keep', nodes: int}>,
     *   unreadable: list<string>
     * }
     */
    public function preview(): array
    {
        ['dialogs' => $found, 'unreadable' => $unreadable] = $this->collectJsonDialogs();

        $entries = [];
        foreach ($found as $name => $dialog) {
            $entries[] = [
                'name'    => $name,
                'file'    => $dialog['file'],
                'private' => $dialog['private'],
                'action'  => $this->dialogs->gameDialogExists($name) ? 'keep' : 'create',
                'nodes'   => count($dialog['nodes']),
            ];
        }

        return ['entries' => $entries, 'unreadable' => $unreadable];
    }

    /**
     * Crée les lignes manquantes depuis les JSON ; les existantes sont
     * conservées telles quelles.
     *
     * @return array{created: list<string>, kept: list<string>, unreadable: list<string>}
     */
    public function seed(): array
    {
        ['dialogs' => $found, 'unreadable' => $unreadable] = $this->collectJsonDialogs();

        $created = [];
        $kept = [];

        $this->db->beginTransaction();
        try {
            foreach ($found as $name => $dialog) {
                if ($this->dialogs->gameDialogExists($name)) {
                    $kept[] = $name;
                    continue;
                }

                $this->dialogs->saveGameDialog($name, $dialog['nodes'], [
                    'npc_name' => $dialog['npc_name'],
                    'type'     => $dialog['type'],
                    'custom'   => $dialog['custom'],
                ]);
                $created[] = $name;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        DialogService::clearCache();

        return ['created' => $created, 'kept' => $kept, 'unreadable' => $unreadable];
    }

    /**
     * Dialogues lisibles des deux répertoires legacy. Le privé prime sur le
     * public à nom égal — même précédence que la lecture json()/decode.
     *
     * @return array{
     *   dialogs: array<string, array{file: string, private: bool, npc_name: string, type: string, custom: string, nodes: array}>,
     *   unreadable: list<string>
     * }
     */
    private function collectJsonDialogs(): array
    {
        $found = [];
        $unreadable = [];

        // public d'abord : une entrée privée du même nom l'écrase ensuite
        foreach (['public' => false, 'private' => true] as $visibility => $isPrivate) {
            $dir = $this->root . '/datas/' . $visibility . '/dialogs';
            foreach (glob($dir . '/*.json') ?: [] as $path) {
                $name = basename($path, '.json');
                $relative = 'datas/' . $visibility . '/dialogs/' . basename($path);

                if (!preg_match(DialogService::DIALOG_NAME_PATTERN, $name)) {
                    $unreadable[] = $relative . ' (nom de fichier invalide)';
                    continue;
                }

                $decoded = json_decode((string) file_get_contents($path), true);
                if (!is_array($decoded)) {
                    $unreadable[] = $relative . ' (JSON invalide)';
                    continue;
                }

                try {
                    $nodes = DialogService::assertValidDialogData($decoded['dialog'] ?? null);
                } catch (RuntimeException $e) {
                    $unreadable[] = $relative . ' (' . $e->getMessage() . ')';
                    continue;
                }

                $found[$name] = [
                    'file'     => $relative,
                    'private'  => $isPrivate,
                    'npc_name' => (string) ($decoded['name'] ?? 'TARGET_NAME'),
                    'type'     => (string) ($decoded['type'] ?? 'pnj'),
                    'custom'   => is_scalar($decoded['custom'] ?? '') ? (string) ($decoded['custom'] ?? '') : '',
                    'nodes'    => $nodes,
                ];
            }
        }

        ksort($found);

        return ['dialogs' => $found, 'unreadable' => $unreadable];
    }
}
