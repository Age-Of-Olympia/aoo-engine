<?php

namespace Tests\Various;

use App\Service\ViewService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ViewServicePlanGuardTest extends TestCase
{
    public function testAPlanNameThatIsNotAPlanNameIsRefusedBeforeAnyQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ViewService(null, 0, 0, 0, 0, "gaia' OR 1=1 -- ");
    }
}
