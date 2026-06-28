<?php

namespace Tests\Action\Schema;

use App\Service\Action\TypeConfigWarning;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class TypeConfigWarningTest extends TestCase
{
    /** @var list<string> */
    private array $messages = [];

    /** @var (callable(string): void)|null */
    private $previousSink;

    protected function setUp(): void
    {
        TypeConfigWarning::reset();
        $this->messages = [];
        $this->previousSink = TypeConfigWarning::setSink(function (string $message): void {
            $this->messages[] = $message;
        });
    }

    protected function tearDown(): void
    {
        TypeConfigWarning::setSink($this->previousSink);
        TypeConfigWarning::reset();
    }

    public function testWarnsOncePerContextAndTypeKey(): void
    {
        TypeConfigWarning::once('XP', ['attack', 'technique']);
        TypeConfigWarning::once('XP', ['attack', 'technique']);

        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString('no XP config', $this->messages[0]);
        $this->assertStringContainsString('"attack"', $this->messages[0]);
        $this->assertStringContainsString('attack, technique', $this->messages[0]);
    }

    public function testDistinctContextsAndKeysWarnSeparately(): void
    {
        TypeConfigWarning::once('XP', ['attack']);
        TypeConfigWarning::once('log', ['attack']);
        TypeConfigWarning::once('XP', ['heal']);

        $this->assertCount(3, $this->messages);
    }

    public function testResetAllowsWarningAgain(): void
    {
        TypeConfigWarning::once('XP', ['attack']);
        TypeConfigWarning::reset();
        TypeConfigWarning::once('XP', ['attack']);

        $this->assertCount(2, $this->messages);
    }
}
