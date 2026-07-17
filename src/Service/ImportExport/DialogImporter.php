<?php

namespace App\Service\ImportExport;

use App\Service\DialogService;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Importe des bundles de dialogues ({@see DialogExporter}) en
 * create-or-update par clé naturelle (`name`), tout-ou-rien
 * (squelette {@see AbstractDbalImporter} — Classes\Db enveloppe la
 * MÊME connexion native que DBAL, la transaction du lot couvre donc
 * les écritures de DialogService).
 */
final class DialogImporter extends AbstractDbalImporter
{
    private ?DialogService $dialogs;

    public function __construct(?Connection $connection = null, ?DialogService $dialogs = null)
    {
        parent::__construct($connection);
        // Lazy : l'instanciation ne doit pas ouvrir de connexion DB
        $this->dialogs = $dialogs;
    }

    public function objectType(): string
    {
        return 'dialog';
    }

    protected function apply(Connection $conn, array $payload): void
    {
        ($this->dialogs ??= new DialogService())->saveGameDialog($payload['name'], $payload['nodes'], [
            'npc_name'  => $payload['npcName'],
            'type'      => $payload['type'],
            'custom'    => $payload['custom'],
            'is_active' => $payload['active'],
        ]);
    }

    protected function afterImport(): void
    {
        DialogService::clearCache();
    }

    /**
     * Valide et classe chaque payload (create/update/reject) sans rien
     * écrire — même squelette que les autres importers Db.
     *
     * @param array<int, mixed> $objects
     * @return list<array{name: string, npcName: string, type: string, custom: string, active: bool, nodes: array}>
     */
    protected function collect(array $objects, ImportReport $report): array
    {
        $payloads = [];
        $seen = [];

        foreach ($objects as $index => $object) {
            $label = is_array($object) && is_string($object['name'] ?? null)
                ? $object['name']
                : 'objet #' . $index;

            try {
                $payload = $this->validate($object);
            } catch (RuntimeException $e) {
                $report->reject($label, $e->getMessage());
                continue;
            }

            if (isset($seen[$payload['name']])) {
                $report->reject($payload['name'], 'Doublon : « ' . $payload['name'] . ' » apparaît plusieurs fois dans le lot.');
                continue;
            }
            $seen[$payload['name']] = true;

            if (($this->dialogs ??= new DialogService())->gameDialogExists($payload['name'])) {
                $report->addUpdated($payload['name']);
            } else {
                $report->addCreated($payload['name']);
            }

            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @return array{name: string, npcName: string, type: string, custom: string, active: bool, nodes: array}
     * @throws RuntimeException message utilisateur (français)
     */
    private function validate(mixed $object): array
    {
        if (!is_array($object)) {
            throw new RuntimeException('Le payload doit être un objet.');
        }

        $name = $object['name'] ?? null;
        if (!is_string($name) || !preg_match(DialogService::DIALOG_NAME_PATTERN, $name)) {
            throw new RuntimeException('Code de dialogue invalide (minuscules, chiffres, _ ou -, 100 max).');
        }

        $nodes = DialogService::assertValidDialogData($object['nodes'] ?? null);

        return [
            'name'    => $name,
            'npcName' => is_scalar($object['npcName'] ?? null) ? (string) $object['npcName'] : 'TARGET_NAME',
            'type'    => is_scalar($object['type'] ?? null) ? (string) $object['type'] : 'pnj',
            'custom'  => is_scalar($object['custom'] ?? null) ? (string) $object['custom'] : '',
            'active'  => (bool) ($object['active'] ?? true),
            'nodes'   => $nodes,
        ];
    }
}
