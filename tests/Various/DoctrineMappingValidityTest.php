<?php

namespace Tests\Various;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Every Doctrine mapping is coherent.
 *
 * A bidirectional association whose two halves disagree does not fail loudly:
 * `ActionCondition::$action` named `inversedBy: "conditions"` while the inverse
 * side is the PROPERTY `Action::$actionConditions` (`getConditions()` is only
 * its accessor), and the mismatch surfaced as an "Undefined array key" warning
 * deep in a persister, on one code path, the day someone deleted a condition
 * from the workbench.
 *
 * SchemaValidator finds those in one pass. Pinning it here means the next
 * mismatch fails the suite instead of waiting for the page that trips it.
 */
class DoctrineMappingValidityTest extends TestCase
{
    public function testEveryMappingIsValid(): void
    {
        $em = $this->entityManagerOrSkip();

        $errors = (new SchemaValidator($em))->validateMapping();

        $flat = [];
        foreach ($errors as $class => $messages) {
            foreach ($messages as $message) {
                $flat[] = $class . ': ' . $message;
            }
        }

        $this->assertSame([], $flat, "Doctrine mappings disagree:\n" . implode("\n", $flat));
    }

    private function entityManagerOrSkip(): EntityManagerInterface
    {
        try {
            $em = \App\Factory\EntityManagerFactory::getEntityManager();
        } catch (\Throwable $e) {
            $this->markTestSkipped('EntityManager unavailable: ' . $e->getMessage());
        }

        return $em;
    }
}
