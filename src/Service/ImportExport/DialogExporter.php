<?php

namespace App\Service\ImportExport;

use App\Service\DialogService;
use InvalidArgumentException;

/**
 * Exporte les dialogues de jeu (table `dialogs`) en payloads à clé
 * naturelle : l'identité est le code du dialogue (`name`) — aucun id de
 * base, le bundle est portable entre environnements.
 */
final class DialogExporter implements ObjectExporter
{
    private ?DialogService $dialogs;

    public function __construct(?DialogService $dialogs = null)
    {
        // Lazy : l'instanciation ne doit pas ouvrir de connexion DB
        $this->dialogs = $dialogs;
    }

    public function objectType(): string
    {
        return 'dialog';
    }

    public function exportAll(): array
    {
        return array_values(array_map(
            fn(array $row): array => $this->rowToPayload($row),
            ($this->dialogs ??= new DialogService())->listGameDialogs()
        ));
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidArgumentException dialogue inconnu
     */
    public function exportOne(string $name): array
    {
        $row = ($this->dialogs ??= new DialogService())->listGameDialogs()[$name] ?? null;
        if ($row === null) {
            throw new InvalidArgumentException('Dialogue introuvable : ' . $name);
        }

        return $this->rowToPayload($row);
    }

    /**
     * Les dialogues n'ont pas d'entité Doctrine : l'export unitaire passe
     * par exportOne() (clé naturelle chaîne), pas par toArray().
     */
    public function toArray(object $entity): array
    {
        throw new InvalidArgumentException('DialogExporter : utiliser exportOne(string $name).');
    }

    /**
     * @param array{name: string, npc_name: string, type: string, custom: string, is_active: bool, nodes: array} $row
     * @return array<string, mixed>
     */
    private function rowToPayload(array $row): array
    {
        return [
            'name'    => $row['name'],
            'npcName' => $row['npc_name'],
            'type'    => $row['type'],
            'custom'  => $row['custom'],
            'active'  => $row['is_active'],
            'nodes'   => $row['nodes'],
        ];
    }
}
