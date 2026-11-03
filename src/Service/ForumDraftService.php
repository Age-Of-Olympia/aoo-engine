<?php

namespace App\Service;

use InvalidArgumentException;

/**
 * Server copy of the forum and missive drafts, so a draft started on one
 * device can be resumed on another. The browser keeps the live copy in
 * localStorage (js/autosave.js) and pushes it here less often; whichever
 * side has the newest `at` wins when a form is opened.
 *
 * One JSON file per character, mapping a draft key to {text, title, at}.
 * A sent or emptied draft is kept as a tombstone (empty text) so that a
 * device still holding the old local copy drops it instead of reviving it.
 */
class ForumDraftService
{
    public const MAX_AGE_MS = 30 * 24 * 3600 * 1000;
    public const MAX_TEXT = 65535;
    public const MAX_TITLE = 255;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? dirname(__DIR__, 2) . '/datas/private/players';
    }

    public static function replyKey(string $topicName): string
    {
        return 'reply:' . $topicName;
    }

    /** One draft per forum, and per recipient for a targeted missive. */
    public static function newTopicKey(string $forumName, ?int $targetId = null): string
    {
        return 'new:' . $forumName . ($targetId ? ':' . $targetId : '');
    }

    public static function editKey(string $postName): string
    {
        return 'edit:' . $postName;
    }

    public static function isValidKey(string $key): bool
    {
        return mb_strlen($key) <= 200 && preg_match('/^(reply|new|edit):[^\x00-\x1f]+$/u', $key) === 1;
    }

    /** @return array{text: string, title: string, at: int}|null */
    public function get(int $ownerId, string $key): ?array
    {
        $drafts = $this->read($ownerId);

        return $drafts[$key] ?? null;
    }

    /** Last writer wins: a write older than the stored one is dropped. */
    public function save(int $ownerId, string $key, string $text, string $title, int $at): void
    {
        if (!self::isValidKey($key)) {
            throw new InvalidArgumentException('Clé de brouillon invalide.');
        }
        if (mb_strlen($text) > self::MAX_TEXT || mb_strlen($title) > self::MAX_TITLE) {
            throw new InvalidArgumentException('Brouillon trop long.');
        }

        // empty drafts are stored as tombstones, never as stale text
        if (trim($text) === '' && trim($title) === '') {
            $text = '';
            $title = '';
        }

        $this->update($ownerId, function (array $drafts) use ($key, $text, $title, $at): array {
            if (isset($drafts[$key]) && $drafts[$key]['at'] > $at) {
                return $drafts;
            }
            $drafts[$key] = ['text' => $text, 'title' => $title, 'at' => $at];

            return $drafts;
        });
    }

    /**
     * Called once the message is sent: tombstone at server time. A draft
     * that cannot be written must never fail the post itself.
     */
    public function clear(int $ownerId, string $key): void
    {
        try {
            $this->save($ownerId, $key, '', '', self::now());
        } catch (\Throwable $e) {
            error_log('ForumDraftService::clear: ' . $e->getMessage());
        }
    }

    /** data-draft, data-draft-owner and data-draft-server for #forum-textarea. */
    public function dataAttributes(int $ownerId, string $key): string
    {
        $server = $this->get($ownerId, $key);

        return 'data-draft="' . htmlspecialchars($key) . '"'
            . ' data-draft-owner="' . $ownerId . '"'
            . ' data-draft-server="' . htmlspecialchars((string) json_encode($server)) . '"';
    }

    private static function now(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    private function path(int $ownerId): string
    {
        return $this->dir . '/' . $ownerId . '.drafts.json';
    }

    /** @return array<string, array{text: string, title: string, at: int}> */
    private function read(int $ownerId): array
    {
        $file = $this->path($ownerId);
        if (!is_file($file)) {
            return [];
        }

        return self::decode((string) file_get_contents($file));
    }

    /**
     * Read-modify-write under an exclusive lock, purging old entries.
     *
     * @param callable(array<string, array{text: string, title: string, at: int}>): array<string, array{text: string, title: string, at: int}> $change
     */
    private function update(int $ownerId, callable $change): void
    {
        $handle = fopen($this->path($ownerId), 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier de brouillons.');
        }

        try {
            flock($handle, LOCK_EX);
            $drafts = $change(self::decode((string) stream_get_contents($handle)));

            $limit = self::now() - self::MAX_AGE_MS;
            $drafts = array_filter($drafts, fn (array $draft): bool => $draft['at'] >= $limit);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($drafts, JSON_UNESCAPED_UNICODE));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string, array{text: string, title: string, at: int}> */
    private static function decode(string $json): array
    {
        $drafts = json_decode($json, true);
        if (!is_array($drafts)) {
            return [];
        }

        $valid = [];
        foreach ($drafts as $key => $draft) {
            if (is_array($draft) && isset($draft['text'], $draft['at'])) {
                $valid[(string) $key] = [
                    'text' => (string) $draft['text'],
                    'title' => (string) ($draft['title'] ?? ''),
                    'at' => (int) $draft['at'],
                ];
            }
        }

        return $valid;
    }
}
