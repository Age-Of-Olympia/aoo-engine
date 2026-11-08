<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\GameLanguageService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GameLanguageServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        EntityManagerFactory::getEntityManager()->getConnection()
            ->executeStatement('DELETE FROM admin_settings WHERE name = ?', [GameLanguageService::SETTING]);
    }

    public function testFrenchUnlessSetAndOnlyAKnownLanguage(): void
    {
        $language = new GameLanguageService();
        $this->assertSame('fr', $language->current());

        $this->expectException(RuntimeException::class);
        $language->set('xx');
    }
}
