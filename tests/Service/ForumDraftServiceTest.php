<?php

namespace Tests\Service;

use App\Service\ForumDraftService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Server copy of the forum drafts: last writer wins, sent drafts leave a
 * tombstone, old entries are purged.
 */
class ForumDraftServiceTest extends TestCase
{
    private string $dir;
    private ForumDraftService $service;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/aoo-drafts-' . uniqid();
        mkdir($this->dir);
        $this->service = new ForumDraftService($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private static function now(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    public function testASavedDraftIsReadBackPerCharacterAndKey(): void
    {
        $at = self::now();
        $this->service->save(2, 'new:La Grande Assemblée', 'Bonjour', 'Titre', $at);

        $this->assertSame(
            ['text' => 'Bonjour', 'title' => 'Titre', 'at' => $at],
            $this->service->get(2, 'new:La Grande Assemblée')
        );
        $this->assertNull($this->service->get(2, 'reply:123'));
        $this->assertNull($this->service->get(-5, 'new:La Grande Assemblée'));
    }

    public function testAnOlderWriteDoesNotOverwriteANewerOne(): void
    {
        $at = self::now();
        $this->service->save(2, 'reply:123', 'récent', '', $at);
        $this->service->save(2, 'reply:123', 'ancien', '', $at - 1000);

        $this->assertSame('récent', $this->service->get(2, 'reply:123')['text']);
    }

    public function testASentDraftLeavesATombstone(): void
    {
        $this->service->save(2, 'reply:123', 'texte', '', self::now() - 1000);
        $this->service->clear(2, 'reply:123');

        $draft = $this->service->get(2, 'reply:123');
        $this->assertNotNull($draft);
        $this->assertSame('', $draft['text']);

        $this->service->save(2, 'edit:456', "  \n", ' ', self::now());
        $this->assertSame('', $this->service->get(2, 'edit:456')['text']);
    }

    public function testDraftsOlderThanThirtyDaysArePurgedOnWrite(): void
    {
        $this->service->save(2, 'reply:1', 'vieux', '', self::now() - ForumDraftService::MAX_AGE_MS - 1000);
        $this->service->save(2, 'reply:2', 'neuf', '', self::now());

        $this->assertNull($this->service->get(2, 'reply:1'));
        $this->assertNotNull($this->service->get(2, 'reply:2'));
    }

    public function testAnInvalidKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->save(2, 'autre:123', 'texte', '', self::now());
    }

    public function testKeysMatchTheFormContexts(): void
    {
        $this->assertSame('reply:123', ForumDraftService::replyKey('123'));
        $this->assertSame('new:Missives', ForumDraftService::newTopicKey('Missives'));
        $this->assertSame('new:Missives:7', ForumDraftService::newTopicKey('Missives', 7));
        $this->assertSame('edit:456', ForumDraftService::editKey('456'));
    }

    public function testDataAttributesCarryTheServerCopy(): void
    {
        $this->assertStringContainsString('data-draft-server="null"', $this->service->dataAttributes(2, 'reply:9'));

        $this->service->save(2, 'reply:9', '<b>"x"</b>', '', 42 + self::now());
        $attributes = $this->service->dataAttributes(2, 'reply:9');

        $this->assertStringContainsString('data-draft="reply:9"', $attributes);
        $this->assertStringContainsString('data-draft-owner="2"', $attributes);
        $this->assertStringNotContainsString('<b>', $attributes);
    }
}
