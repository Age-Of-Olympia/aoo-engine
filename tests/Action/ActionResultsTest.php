<?php

namespace Tests\Action;

use App\Action\ActionResults;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

class ActionResultsTest extends TestCase
{
  #[Group('action')]
  public function testConstructorAndGetters()
  {
    // Arrange
    $success = true;
    $blocked = false;
    $conditionsResultsArray = ['condition1' => true];
    $effectsResultsArray = ['effect1' => 'applied'];
    $costsResultsArray = ['cost1' => 10];
    $xpResultsArray = ['actor' => 100, 'target' => 50];
    $logsArray = ['log1', 'log2'];

    // Act
    $actionResults = new ActionResults(
      $success,
      $blocked,
      $conditionsResultsArray,
      $effectsResultsArray,
      $costsResultsArray,
      $xpResultsArray,
      $logsArray
    );

    // Assert
    $this->assertTrue($actionResults->isSuccess());
    $this->assertFalse($actionResults->isBlocked());
    $this->assertEquals($conditionsResultsArray, $actionResults->getConditionsResultsArray());
    $this->assertEquals($effectsResultsArray, $actionResults->getOutcomesResultsArray());
    $this->assertEquals($costsResultsArray, $actionResults->getCostsResultsArray());
    $this->assertEquals($xpResultsArray, $actionResults->getXpResultsArray());
    $this->assertEquals($logsArray, $actionResults->getLogsArray());
  }

  #[Group('action')]
  public function testSetters()
  {
    // Arrange
    $actionResults = new ActionResults(
      false,
      true,
      [],
      [],
      [],
      [],
      []
    );

    // Act
    $actionResults->setSuccess(true);
    $actionResults->setBlocked(false);
    $actionResults->setConditionsResultsArray(['condition2' => false]);
    $actionResults->setOutcomesResultsArray(['effect2' => 'failed']);
    $actionResults->setCostsResultsArray(['cost2' => 20]);
    $actionResults->setXpResultsArray(['actor' => 200, 'target' => 100]);
    $actionResults->setLogsArray(['log3', 'log4']);

    // Assert
    $this->assertTrue($actionResults->isSuccess());
    $this->assertFalse($actionResults->isBlocked());
    $this->assertEquals(['condition2' => false], $actionResults->getConditionsResultsArray());
    $this->assertEquals(['effect2' => 'failed'], $actionResults->getOutcomesResultsArray());
    $this->assertEquals(['cost2' => 20], $actionResults->getCostsResultsArray());
    $this->assertEquals(['actor' => 200, 'target' => 100], $actionResults->getXpResultsArray());
    $this->assertEquals(['log3', 'log4'], $actionResults->getLogsArray());
  }

  // public function testConstructorWithInvalidData()
  // {
  //   // Arrange
  //   $success = "not a boolean"; // Mauvais type
  //   $blocked = null; // Mauvais type
  //   $conditionsResultsArray = "not an array"; // Mauvais type
  //   $effectsResultsArray = 123; // Mauvais type
  //   $costsResultsArray = null; // Mauvais type
  //   $xpResultsArray = "invalid"; // Mauvais type
  //   $logsArray = false; // Mauvais type

  //   // Act & Assert
  //   $this->expectException(\TypeError::class); // On s'attend à une erreur de type
  //   new ActionResults(
  //     $success,
  //     $blocked,
  //     $conditionsResultsArray,
  //     $effectsResultsArray,
  //     $costsResultsArray,
  //     $xpResultsArray,
  //     $logsArray
  //   );
  // }
}
