<?php

namespace Tests\Action;

use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\TotoMock;

class ActionTest extends TestCase {

  public function testActionFactory() {
    $this->assertFalse(false);
  }

  public function testActionResults() {
    $mock = new TotoMock();
    $result = $mock->getTrue();
    $this->assertTrue($result);
  }

}
