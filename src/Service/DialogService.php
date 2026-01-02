<?php

namespace App\Service;

use Classes\Dialog;
use Classes\Player;
use Classes\Db;

/**
 * Reusable dialog service for both game and tutorial
 *
 * KEY DIFFERENCE:
 * - Tutorial mode: Loads dialogs from DATABASE (tutorial_dialogs table)
 * - Game mode: Loads dialogs from JSON files (existing behavior)
 *
 * This service provides a unified interface while maintaining
 * backward compatibility with the legacy Dialog class.
 */
class DialogService
{
    private bool $isTutorialMode;
    private Db $db;

    public function __construct(bool $isTutorialMode = false)
    {
        $this->isTutorialMode = $isTutorialMode;
        $this->db = new Db();
    }

    /**
     * Load dialog by name
     *
     * @param string $dialogName Dialog identifier (e.g., 'gaia_welcome', 'marchand')
     * @param string $version Tutorial version (default: '1.0.0')
     * @return object|null Dialog data object
     */
    public function loadDialog(string $dialogName, string $version = '1.0.0'): ?object
    {
        // Tutorial mode: Load from DATABASE
        if ($this->isTutorialMode) {
            return $this->loadDialogFromDatabase($dialogName, $version);
        }

        // Game mode: Load from JSON files (existing behavior)
        return $this->loadDialogFromFiles($dialogName);
    }

    /**
     * Load dialog from database (tutorial mode)
     */
    private function loadDialogFromDatabase(string $dialogId, string $version): ?object
    {
        $sql = 'SELECT dialog_data FROM tutorial_dialogs
                WHERE dialog_id = ? AND version = ? AND is_active = 1';

        $result = $this->db->exe($sql, [$dialogId, $version]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dialogData = json_decode($row['dialog_data']);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $dialogData;
            } else {
                error_log("Dialog JSON decode error for {$dialogId}: " . json_last_error_msg());
            }
        }

        return null;
    }

    /**
     * Load dialog from JSON files (game mode)
     */
    private function loadDialogFromFiles(string $dialogName): ?object
    {
        // Try private dialogs first
        $privatePath = 'datas/private/dialogs/' . $dialogName . '.json';
        if (file_exists($privatePath)) {
            return $this->loadDialogFromFile($privatePath);
        }

        // Try public dialogs
        $publicPath = 'datas/public/dialogs/' . $dialogName . '.json';
        if (file_exists($publicPath)) {
            return $this->loadDialogFromFile($publicPath);
        }

        return null;
    }

    /**
     * Load dialog from file
     */
    private function loadDialogFromFile(string $path): ?object
    {
        $content = file_get_contents($path);
        if (!$content) {
            return null;
        }

        $decoded = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Dialog JSON error in {$path}: " . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Render dialog with legacy Dialog class
     *
     * @param string $dialogName
     * @param Player|null $player
     * @param Player|null $target
     * @return string HTML output
     */
    public function renderDialog(
        string $dialogName,
        ?Player $player = null,
        ?Player $target = null
    ): string {
        // Load dialog data first
        $dialogData = $this->loadDialog($dialogName);

        if (!$dialogData) {
            return '<p>Dialog not found: ' . htmlspecialchars($dialogName) . '</p>';
        }

        // Use existing Dialog class for rendering (maintains compatibility)
        // We'll need to temporarily write this to a variable or pass it differently
        // For now, let's use the legacy system
        $dialog = new Dialog($dialogName, $player, $target);

        ob_start();
        echo $dialog->get_data();
        return ob_get_clean();
    }

    /**
     * Get dialog data without rendering (for API)
     *
     * @param string $dialogName
     * @param Player|null $player
     * @param Player|null $target
     * @param string $version Tutorial version
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
     * Save or update dialog in database (admin tool)
     *
     * @param string $dialogId
     * @param string $npcName
     * @param array $dialogData
     * @param string $version
     * @return bool
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
