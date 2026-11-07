<?php

namespace Tests\Various;

use Classes\Forum;
use Classes\Json;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * A hidden or deleted post must not stand as the topic's last reply in the
 * forum list (#340).
 */
class ForumRefreshLastTest extends TestCase
{
    use LegacyBootstrapTrait;

    private const FORUM_DIR = __DIR__ . '/../../datas/private/forum/';

    /** @var list<string> files written by the case, removed in tearDown */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testLastFallsBackToNewestStandingPost(): void
    {
        $this->bootstrapLegacyOrSkip();

        $base = (int) round(microtime(true) * 1000);
        $first = $this->writePost($base, 1, null);
        $hidden = $this->writePost($base + 1, 2, (object) ['time' => time(), 'hidden' => true]);
        $deleted = $this->writePost($base + 2, 3, (object) ['time' => time(), 'hidden' => false]);

        $topJson = (object) [
            'name' => 'refresh-last-' . $base,
            'last' => (object) ['author' => 3, 'time' => $deleted],
            'posts' => [(object) ['name' => $first], (object) ['name' => $hidden], (object) ['name' => $deleted]],
        ];
        $this->files[] = self::FORUM_DIR . 'topics/' . $topJson->name . '.json';

        Forum::refresh_last($topJson);

        $this->assertSame($first, $topJson->last->time);
        $this->assertSame(1, $topJson->last->author);
        $this->assertSame($first, json_decode((string) file_get_contents(end($this->files)))->last->time);
    }

    private function writePost(int $name, int $author, ?object $deleted): int
    {
        $post = ['name' => $name, 'author' => $author, 'text' => 'x'];
        if ($deleted !== null) {
            $post['deleted'] = $deleted;
        }
        $file = self::FORUM_DIR . 'posts/' . $name . '.json';
        file_put_contents($file, Json::encode($post));
        $this->files[] = $file;

        return $name;
    }
}
