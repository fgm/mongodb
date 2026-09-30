<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\mongodb\Install\Tools;
use Drupal\mongodb\MongoDb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Class CommandsTest.
 *
 * @covers \Drupal\mongodb\Install\Tools
 *
 * @group mongodb
 */
#[CoversClass(Tools::class)]
#[Group('mongodb')]
#[RunTestsInSeparateProcesses]
class ToolsTest extends MongoDbTestBase {

  /**
   * Tests the availability of the tools service.
   */
  public function testToolsService(): void {
    $tools = $this->container->get(MongoDb::SERVICE_TOOLS);
    $this->assertInstanceOf(Tools::class, $tools, "Tools service is available");
  }

  /**
   * Tests the settings returned by the tools service.
   */
  public function testToolsSettings(): void {
    $tools = $this->container->get(MongoDb::SERVICE_TOOLS);
    $actual = $tools->settings();
    $this->assertIsArray($actual);
    $expected = $this->getSettingsArray();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Tests finding documents with the tools service.
   */
  public function testFind(): void {
    /** @var \Drupal\mongodb\DatabaseFactory $dbFactory */
    $dbFactory = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
    /** @var \MongoDB\Database $database */
    $database = $dbFactory->get(MongoDb::DB_DEFAULT);

    $collectionName = $this->randomMachineName();
    $collection = $database->selectCollection($collectionName);
    $collection->drop();
    $documents = [
      ["foo" => "bar"],
      ["foo" => "baz"],
    ];
    $docCount = count($documents);
    $collection->insertMany($documents);
    // Just a sanity check.
    $this->assertEquals($docCount, $collection->countDocuments());

    $tools = $this->container->get(MongoDb::SERVICE_TOOLS);

    $expectations = [
      [[], 2],
      [["foo" => "baz"], 1],
      [["foo" => "qux"], 0],
    ];

    foreach ($expectations as $expectation) {
      // Current coding standards don't support foreach (foo as list()).
      [$selector, $count] = $expectation;

      $selectorString = json_encode($selector);
      $actual = $tools->find(MongoDb::DB_DEFAULT, $collectionName, $selectorString);
      $this->assertIsArray($actual);
      $this->assertEquals($count, count($actual));
    }
  }

}
