<?php

namespace App\Service;

use Classes\Db;
use Classes\Json;

/**
 * The missive every new player is enrolled in at registration.
 *
 * Two admin_settings keys: the sender (whose missives can be chosen, and who
 * signs a newly written one) and the chosen missive's topic name. The text
 * itself stays in the forum JSON files, like any other missive.
 */
class WelcomeMissiveService
{
    public const AUTHOR_SETTING = 'welcome_missive_author';
    public const TOPIC_SETTING = 'welcome_missive';
    /** Gaïa. */
    public const AUTHOR_DEFAULT = -91;

    private AdminSettingsService $settings;

    public function __construct()
    {
        $this->settings = new AdminSettingsService();
    }

    public function authorId(): int
    {
        return (int) $this->settings->get(self::AUTHOR_SETTING, (string) self::AUTHOR_DEFAULT);
    }

    public function setAuthor(int $playerId): void
    {
        $res = (new Db())->exe('SELECT id FROM players WHERE id = ?', [$playerId]);
        if (!$res || $res->num_rows === 0) {
            throw new \RuntimeException("Aucun personnage avec le matricule {$playerId}.");
        }
        $this->settings->set(self::AUTHOR_SETTING, (string) $playerId);
    }

    /** The chosen missive's topic name, or '' when none is set or its file is gone. */
    public function topicName(): string
    {
        $name = $this->settings->get(self::TOPIC_SETTING);

        return $name !== '' && $this->topic($name) ? $name : '';
    }

    /** @return array<string, string> topic name => title, the sender's missives, newest first */
    public function authorMissives(): array
    {
        $res = (new Db())->exe(
            'SELECT DISTINCT name FROM players_forum_missives WHERE player_id = ? ORDER BY name DESC',
            [$this->authorId()]
        );
        $missives = [];
        while ($row = $res->fetch_object()) {
            $topic = $this->topic((string) $row->name);
            if ($topic) {
                $missives[(string) $row->name] = (string) $topic->title;
            }
        }

        return $missives;
    }

    public function choose(string $name): void
    {
        if (!array_key_exists($name, $this->authorMissives())) {
            throw new \RuntimeException('Cette missive n\'appartient pas à l\'expéditeur.');
        }
        $this->settings->set(self::TOPIC_SETTING, $name);
    }

    /** Writes a new missive signed by the sender and makes it the welcome one. */
    public function create(string $title, string $text): void
    {
        $name = (new MissiveService())->sendNewMissive($this->authorId(), [$this->authorId()], $title, $text);
        $this->settings->set(self::TOPIC_SETTING, $name);
    }

    /** @return array{title: string, text: string}|null */
    public function content(): ?array
    {
        [$topic, $post] = $this->chosen();

        return $post ? ['title' => (string) $topic->title, 'text' => (string) $post->text] : null;
    }

    /** Rewrites the title and the opening post of the chosen missive. */
    public function save(string $title, string $text): void
    {
        [$topic, $post] = $this->chosen();
        if (!$post) {
            throw new \RuntimeException('Aucune missive d\'accueil choisie.');
        }

        $topic->title = $title;
        Json::write_json('datas/private/forum/topics/' . $topic->name . '.json', Json::encode($topic));

        $post->text = $text;
        $post->last_update_date = time();
        Json::write_json('datas/private/forum/posts/' . $post->name . '.json', Json::encode($post));

        json()->forget('forum/topics', (string) $topic->name);
        json()->forget('forum/posts', (string) $post->name);
    }

    /** Adds a freshly registered player to the welcome missive, if one is set. */
    public function enroll(int $playerId): void
    {
        $name = $this->topicName();
        if ($name !== '') {
            (new Db())->insert('players_forum_missives', ['player_id' => $playerId, 'name' => $name]);
        }
    }

    private function topic(string $name): object|false
    {
        return json()->decode('forum/topics', $name);
    }

    /** @return array{0: ?object, 1: ?object} the chosen topic and its opening post */
    private function chosen(): array
    {
        $name = $this->topicName();
        $topic = $name !== '' ? ($this->topic($name) ?: null) : null;
        $first = $topic->posts[0]->name ?? null;
        $post = $first !== null ? (json()->decode('forum/posts', (string) $first) ?: null) : null;

        return [$topic, $post];
    }
}
