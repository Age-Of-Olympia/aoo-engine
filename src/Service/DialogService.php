<?php

namespace App\Service;

use Classes\Db;
use Classes\Dialog;
use Classes\Json;
use Classes\Player;
use RuntimeException;

/**
 * Passerelle unique des dialogues, jeu et tutoriel.
 *
 *  - Mode tutoriel : table `tutorial_dialogs` (versionnée par tutoriel) —
 *    inchangé.
 *  - Mode jeu : table `dialogs` d'abord (source de vérité depuis
 *    Version20260713150000_DialogsFromJson), REPLI sur les fichiers JSON
 *    legacy (datas/[public|private]/dialogs/*.json) tant que la ligne
 *    n'existe pas — le seed se lance depuis admin/dialog-seed.php. Le repli
 *    disparaîtra une fois toutes les lignes seedées.
 *
 * Le read model reste celui des fichiers : stdClass {id, name, type,
 * custom, dialog: [nœuds]} — les consommateurs (Classes\Dialog, Classes\Ui)
 * ne voient pas la différence. Cache par requête : un dialogue est lu deux
 * fois par affichage (test d'existence Ui puis rendu Dialog).
 *
 * Cas particulier : le dialogue `register` est RÉÉCRIT à chaud à chaque
 * inscription (options de races avec compteurs d'âmes/bonus) —
 * {@see refreshRegisterDialog()}.
 */
class DialogService
{
    /** Règle unique des codes de dialogue (fichier / dialogs.name). */
    public const DIALOG_NAME_PATTERN = '/^[a-z0-9_-]{1,100}$/';

    /** Dialogue réécrit par le code du jeu — jamais supprimable en admin. */
    public const REGISTER_DIALOG = 'register';

    /** @var array<string, object|null> cache par requête des dialogues de jeu */
    private static array $gameCache = [];

    private bool $isTutorialMode;
    private Db $db;

    public function __construct(bool $isTutorialMode = false)
    {
        $this->isTutorialMode = $isTutorialMode;
        $this->db = new Db();
    }

    /** À appeler après toute écriture hors instance (tests, seed). */
    public static function clearCache(): void
    {
        self::$gameCache = [];
    }

    /**
     * Load dialog by name
     *
     * @param string $dialogName Dialog identifier (e.g., 'gaia', 'marchand')
     * @param string $version Tutorial version (default: '1.0.0')
     * @return object|null Dialog data object
     */
    public function loadDialog(string $dialogName, string $version = '1.0.0'): ?object
    {
        if ($this->isTutorialMode) {
            return $this->loadTutorialDialog($dialogName, $version);
        }

        if (!array_key_exists($dialogName, self::$gameCache)) {
            self::$gameCache[$dialogName] = $this->loadGameDialogFromDatabase($dialogName)
                ?? $this->loadDialogFromFile($dialogName);
        }

        return self::$gameCache[$dialogName];
    }

    /**
     * Lignes de la table `dialogs` pour l'admin et l'export, par nom.
     *
     * @return array<string, array{name: string, npc_name: string, type: string, custom: string,
     *                             is_active: bool, nodes: array, updated_at: ?string}>
     */
    public function listGameDialogs(): array
    {
        $res = $this->db->exe('SELECT name, npc_name, type, custom, dialog_data, is_active, updated_at FROM dialogs ORDER BY name');

        $dialogs = [];
        while ($row = $res->fetch_assoc()) {
            $dialogs[$row['name']] = [
                'name'       => $row['name'],
                'npc_name'   => $row['npc_name'],
                'type'       => $row['type'],
                'custom'     => $row['custom'],
                'is_active'  => (bool) $row['is_active'],
                'nodes'      => (array) json_decode($row['dialog_data'], true),
                'updated_at' => $row['updated_at'],
            ];
        }

        return $dialogs;
    }

    /** La ligne `dialogs` existe-t-elle (active ou non) ? */
    public function gameDialogExists(string $name): bool
    {
        $res = $this->db->exe('SELECT 1 FROM dialogs WHERE name = ? LIMIT 1', array($name));

        return $res->fetch_row() !== null;
    }

    /**
     * Upsert d'un dialogue de jeu (admin, seed, import). Valide nom et
     * nœuds avant toute écriture.
     *
     * @param array{npc_name?: string, type?: string, custom?: string, is_active?: bool} $fields
     * @param array<int, mixed> $nodes les nœuds (clé "dialog" du read model)
     * @throws RuntimeException code 400
     */
    public function saveGameDialog(string $name, array $nodes, array $fields = []): void
    {
        $this->assertValidDialogName($name);
        $nodes = self::assertValidDialogData($nodes);

        $this->db->exe(
            'INSERT INTO dialogs (name, npc_name, type, custom, dialog_data, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                npc_name = VALUES(npc_name), type = VALUES(type), custom = VALUES(custom),
                dialog_data = VALUES(dialog_data), is_active = VALUES(is_active)',
            array(
                $name,
                (string) ($fields['npc_name'] ?? 'TARGET_NAME'),
                (string) ($fields['type'] ?? 'pnj'),
                (string) ($fields['custom'] ?? ''),
                json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int) ($fields['is_active'] ?? true),
            )
        );

        unset(self::$gameCache[$name]);
    }

    /**
     * Suppression d'un dialogue de jeu. Garde-fous : `register` (réécrit par
     * le code à chaque inscription) et les dialogues encore référencés par
     * des déclencheurs map_dialogs ou portés par des bâtiments.
     *
     * @throws RuntimeException code 404 ou 409
     */
    public function deleteGameDialog(string $name): void
    {
        if ($name === self::REGISTER_DIALOG) {
            throw new RuntimeException(
                'Le dialogue « register » est réécrit par le jeu à chaque inscription — désactivez-le plutôt.',
                409
            );
        }
        if (!$this->gameDialogExists($name)) {
            throw new RuntimeException('Dialogue introuvable : ' . $name, 404);
        }

        $references = $this->countMapDialogReferences($name);
        if ($references > 0) {
            throw new RuntimeException(
                'Suppression impossible : ' . $references . ' déclencheur(s) map_dialogs référencent « '
                    . $name . ' » — retirez-les d\'abord (éditeur Tiled).',
                409
            );
        }

        $buildingReferences = $this->countBuildingDialogReferences($name);
        if ($buildingReferences > 0) {
            throw new RuntimeException(
                'Suppression impossible : ' . $buildingReferences . ' bâtiment(s) portent « '
                    . $name . ' » — détachez-les d\'abord (admin → Bâtiments).',
                409
            );
        }

        $this->db->exe('DELETE FROM dialogs WHERE name = ?', array($name));
        unset(self::$gameCache[$name]);
    }

    /**
     * Déclencheurs map_dialogs par dialogue référencé. params est un CSV
     * « nom,avatar,dialogue » (ou une valeur unique répétée) — cf.
     * observe.php ; table petite, décodage en PHP.
     *
     * @return array<string, int> code de dialogue => nombre de déclencheurs
     */
    public function mapDialogReferenceCounts(): array
    {
        $res = $this->db->exe('SELECT params FROM map_dialogs');

        $counts = [];
        while ($row = $res->fetch_assoc()) {
            $params = (string) $row['params'];
            if ($params === '' || $params[0] === '"') {
                continue; // alerte brute, pas un dialogue
            }
            $parts = array_map('trim', explode(',', $params));
            $dialogId = $parts[2] ?? $parts[0];
            if ($dialogId !== '') {
                $counts[$dialogId] = ($counts[$dialogId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** Déclencheurs map_dialogs pointant CE dialogue. */
    public function countMapDialogReferences(string $name): int
    {
        return $this->mapDialogReferenceCounts()[$name] ?? 0;
    }

    /**
     * Bâtiments portant chaque dialogue (buildings.dialog) — deuxième
     * famille de références, protégée par le même garde de suppression
     * que les déclencheurs map_dialogs.
     *
     * @return array<string, int> code de dialogue => nombre de bâtiments
     */
    public function buildingDialogReferenceCounts(): array
    {
        $counts = [];
        $res = $this->db->exe("SELECT dialog, COUNT(*) AS n FROM buildings WHERE dialog != '' GROUP BY dialog");
        while ($row = $res->fetch_assoc()) {
            $counts[(string) $row['dialog']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Bâtiments portant CE dialogue — requête directe, pas de scan. */
    public function countBuildingDialogReferences(string $name): int
    {
        $res = $this->db->exe('SELECT COUNT(*) AS n FROM buildings WHERE dialog = ?', array($name));

        return (int) $res->fetch_object()->n;
    }

    /**
     * Régénère les options du dialogue d'inscription (races jouables avec
     * compteurs d'âmes et bonus de rattrapage) — reprend la logique
     * historique de Dialog::refresh_register_dialog(), qui délègue ici.
     *
     * Écrit la ligne `dialogs` quand elle existe (post-seed) ; sinon repli
     * historique : réécriture du fichier register.json.
     */
    public function refreshRegisterDialog(): void
    {
        $options = [];
        $raceService = new RaceService();
        foreach (Dialog::get_race_n() as $raceName => $label) {
            $race = $raceService->getRaceByName($raceName);
            if ($race) {
                $options[] = ['go' => $raceName, 'text' => $race->getLabel() . ' ' . $label];
            }
        }

        if ($this->gameDialogExists(self::REGISTER_DIALOG)) {
            $current = $this->loadGameDialogFromDatabase(self::REGISTER_DIALOG, false);
            if ($current === null || empty($current->dialog)) {
                return; // ligne vide/inactive et illisible : ne rien casser
            }

            $nodes = json_decode(json_encode($current->dialog), true);
            $nodes[0]['options'] = $options;

            $this->saveGameDialog(self::REGISTER_DIALOG, $nodes, [
                'npc_name'  => $current->name,
                'type'      => $current->type,
                'custom'    => (string) ($current->custom ?? ''),
                'is_active' => true,
            ]);

            return;
        }

        // Repli pré-seed : comportement historique sur le fichier
        $regJson = json()->decode('dialogs', self::REGISTER_DIALOG);
        if (!is_object($regJson) || empty($regJson->dialog)) {
            return;
        }
        $regJson->dialog[0]->options = json_decode(json_encode($options));
        Json::write_json('datas/public/dialogs/register.json', Json::encode($regJson));
        json()->forget('dialogs', self::REGISTER_DIALOG);
        unset(self::$gameCache[self::REGISTER_DIALOG]);
    }

    /**
     * Valide et normalise des nœuds de dialogue : liste non vide de nœuds
     * {id, text, options}, chaque option portant un texte et une cible
     * (go / url / set). Clés inconnues conservées (shuffle, avatar, type…).
     *
     * @param mixed $nodes
     * @return array<int, array<string, mixed>> nœuds normalisés en tableaux
     * @throws RuntimeException code 400, message français
     */
    public static function assertValidDialogData(mixed $nodes): array
    {
        // Normalise objets/tableaux mélangés (JSON décodé en objets ou assoc)
        $nodes = json_decode((string) json_encode($nodes), true);

        if (!is_array($nodes) || !array_is_list($nodes) || $nodes === []) {
            throw new RuntimeException('Les nœuds doivent être une liste non vide (clé "dialog" du fichier).', 400);
        }

        foreach ($nodes as $i => $node) {
            if (!is_array($node)) {
                throw new RuntimeException('Nœud #' . $i . ' invalide : objet attendu.', 400);
            }
            if (!isset($node['id']) || !is_string($node['id']) || trim($node['id']) === '') {
                throw new RuntimeException('Nœud #' . $i . ' : « id » manquant ou vide.', 400);
            }
            if (!isset($node['text']) || !is_string($node['text'])) {
                throw new RuntimeException('Nœud « ' . $node['id'] . ' » : « text » manquant.', 400);
            }
            $options = $node['options'] ?? [];
            if (!is_array($options) || !array_is_list($options)) {
                throw new RuntimeException('Nœud « ' . $node['id'] . ' » : « options » doit être une liste.', 400);
            }
            foreach ($options as $j => $option) {
                if (!is_array($option) || !isset($option['text']) || !is_string($option['text'])) {
                    throw new RuntimeException('Nœud « ' . $node['id'] . ' », option #' . $j . ' : « text » manquant.', 400);
                }
                if (empty($option['go']) && empty($option['url']) && empty($option['set'])) {
                    throw new RuntimeException(
                        'Nœud « ' . $node['id'] . ' », option #' . $j . ' : cible manquante (« go », « url » ou « set »).',
                        400
                    );
                }
            }
        }

        return $nodes;
    }

    /** @throws RuntimeException code 400 */
    private function assertValidDialogName(string $name): void
    {
        if (!preg_match(self::DIALOG_NAME_PATTERN, $name)) {
            throw new RuntimeException(
                'Code de dialogue invalide (minuscules, chiffres, _ ou -, 100 max) : ' . $name,
                400
            );
        }
    }

    /** Ligne `dialogs` → read model fichier {id, name, type, custom, dialog}. */
    private function loadGameDialogFromDatabase(string $name, bool $activeOnly = true): ?object
    {
        $res = $this->db->exe(
            'SELECT name, npc_name, type, custom, dialog_data, is_active FROM dialogs WHERE name = ?'
                . ($activeOnly ? ' AND is_active = 1' : ''),
            array($name)
        );
        $row = $res->fetch_assoc();
        if ($row === null) {
            return null;
        }

        $nodes = json_decode($row['dialog_data']);
        if (!is_array($nodes)) {
            return null;
        }

        return (object) [
            'id'     => $row['name'],
            'name'   => $row['npc_name'],
            'type'   => $row['type'],
            'custom' => $row['custom'],
            'dialog' => $nodes,
        ];
    }

    /**
     * Repli fichier legacy, via json() (chemins indépendants du cwd, parse
     * tolérant, cache) — même lecture que l'historique Classes\Dialog.
     */
    private function loadDialogFromFile(string $dialogName): ?object
    {
        $decoded = json()->decode('dialogs', $dialogName);

        return is_object($decoded) ? $decoded : null;
    }

    /** Load dialog from database (tutorial mode) — inchangé. */
    private function loadTutorialDialog(string $dialogId, string $version): ?object
    {
        $sql = 'SELECT dialog_data FROM tutorial_dialogs
                WHERE dialog_id = ? AND version = ? AND is_active = 1';

        $result = $this->db->exe($sql, [$dialogId, $version]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dialogData = json_decode($row['dialog_data']);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $dialogData;
            }
        }

        return null;
    }

    /**
     * Render dialog with legacy Dialog class
     */
    public function renderDialog(
        string $dialogName,
        ?Player $player = null,
        ?Player $target = null
    ): string {
        $dialogData = $this->loadDialog($dialogName);

        if (!$dialogData) {
            return '<p>Dialog not found: ' . htmlspecialchars($dialogName) . '</p>';
        }

        $dialog = new Dialog($dialogName, $player, $target);

        ob_start();
        echo $dialog->get_data();
        return ob_get_clean();
    }

    /**
     * Get dialog data without rendering (for API)
     *
     * @return array
     */
    public function getDialogData(
        string $dialogName,
        ?Player $player = null,
        ?Player $target = null,
        string $version = '1.0.0'
    ): array {
        $dialogJson = $this->loadDialog($dialogName, $version);

        if (!$dialogJson) {
            return [
                'success' => false,
                'error' => 'Dialog not found',
                'dialog_name' => $dialogName,
                'mode' => $this->isTutorialMode ? 'tutorial' : 'game'
            ];
        }

        return [
            'success' => true,
            'id' => $dialogJson->id ?? $dialogName,
            'name' => $this->replacePlaceholders(
                $dialogJson->name ?? '',
                $player,
                $target
            ),
            'type' => $dialogJson->type ?? 'pnj',
            'nodes' => $this->processDialogNodes(
                $dialogJson->dialog ?? [],
                $player,
                $target
            ),
            'mode' => $this->isTutorialMode ? 'tutorial' : 'game'
        ];
    }

    /**
     * Process dialog nodes
     */
    private function processDialogNodes(
        array $nodes,
        ?Player $player,
        ?Player $target
    ): array {
        $processed = [];

        foreach ($nodes as $node) {
            $processed[] = [
                'id' => $node->id ?? '',
                'text' => $this->replacePlaceholders(
                    $node->text ?? '',
                    $player,
                    $target
                ),
                'avatar' => $node->avatar ?? null,
                'type' => $node->type ?? null,
                'options' => $this->processOptions(
                    $node->options ?? [],
                    $player,
                    $target
                )
            ];
        }

        return $processed;
    }

    /**
     * Process dialog options
     */
    private function processOptions(
        array $options,
        ?Player $player,
        ?Player $target
    ): array {
        $processed = [];

        foreach ($options as $option) {
            $processed[] = [
                'text' => $this->replacePlaceholders(
                    $option->text ?? '',
                    $player,
                    $target
                ),
                'go' => $option->go ?? null,
                'url' => $option->url ?? null,
                'set' => $option->set ?? null
            ];
        }

        return $processed;
    }

    /**
     * Replace placeholders in text
     */
    private function replacePlaceholders(
        string $text,
        ?Player $player,
        ?Player $target
    ): string {
        if ($player) {
            $text = str_replace('PLAYER_ID', (string)$player->id, $text);
            $text = str_replace('PLAYER_NAME', $player->data->name ?? '', $text);
        }

        if ($target) {
            $text = str_replace('TARGET_ID', (string)$target->id, $text);
            $text = str_replace('TARGET_NAME', $target->data->name ?? '', $text);
        }

        return $text;
    }

    /**
     * Check if in tutorial mode
     */
    public function isTutorialMode(): bool
    {
        return $this->isTutorialMode;
    }

    /**
     * Save or update dialog in database — variante tutoriel historique.
     *
     * @param array $dialogData
     */
    public function saveDialog(
        string $dialogId,
        string $npcName,
        array $dialogData,
        string $version = '1.0.0'
    ): bool {
        if (!$this->isTutorialMode) {
            throw new \Exception('Can only save dialogs in tutorial mode');
        }

        $dialogJson = json_encode($dialogData);

        $sql = 'INSERT INTO tutorial_dialogs (dialog_id, npc_name, version, dialog_data, is_active)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    npc_name = VALUES(npc_name),
                    dialog_data = VALUES(dialog_data),
                    updated_at = CURRENT_TIMESTAMP';

        $result = $this->db->exe($sql, [$dialogId, $npcName, $version, $dialogJson]);

        return $result !== false;
    }
}
