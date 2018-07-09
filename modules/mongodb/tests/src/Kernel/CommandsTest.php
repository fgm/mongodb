<?php

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\mongodb\Commands\MongoDbCommands;
use Drupal\mongodb\MongoDb;

/**
 * Class CommandsTest.
 *
 * @coversDefaultClass \Drupal\mongodb\Commands\MongoDbCommands
 *
 * @group MongoDB
 */
class CommandsTest extends MongoDbTestBase {

  /**
   * @covers ::create
   */
  public function testFactory() {
    $commands = MongoDbCommands::create($this->container);
    $this->assertInstanceOf(MongoDbCommands::class, $commands);
  }

  /**
   * @covers ::__construct
   */
  public function testCommandsService() {
    $commands = $this->container->get(MongoDb::SERVICE_COMMANDS);
    $this->assertInstanceOf(MongoDbCommands::class, $commands, "Commands service is available");
    $this->assertTrue(method_exists($commands, 'find'));
    $this->assertTrue(method_exists($commands, 'settings'));
  }

  /**
   * @covers ::settings
   */
  public function testCommandSettings() {
    /** @var \Drupal\Component\Serialization\SerializationInterface $yaml */
    $yaml = $this->container->get('serialization.yaml');
    $commands = $this->container->get(MongoDb::SERVICE_COMMANDS);
    $actualString = $commands->settings();
    $this->assertInternalType('string', $actualString);
    $actual = $yaml->decode($actualString);
    $expected = $this->getSettingsArray();
    $this->assertEquals($expected, $actual);
  }

  /**
   * @covers ::find
   */
  public function testFind() {
    /** @var \Drupal\Component\Serialization\SerializationInterface $yaml */
    $yaml = $this->container->get("serialization.yaml");
    /** @var \Drupal\mongodb\DatabaseFactory $dbFactory */
    $dbFactory = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
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
    $this->assertEquals($docCount, MongoDb::countCollection($collection));

    $commands = $this->container->get(MongoDb::SERVICE_COMMANDS);

    $expectations = [
      [[], 2],
      [["foo" => "baz"], 1],
      [["foo" => "qux"], 0],
    ];

    foreach ($expectations as $expectation) {
      // Current coding standards don't support foreach (foo as list()).
      list($selector, $count) = $expectation;

      $selectorString = json_encode($selector);
      $encoded = $commands->find(MongoDb::DB_DEFAULT, $collectionName, $selectorString);
      $decoded = $yaml->decode($encoded);
      $this->assertInternalType('array', $decoded);
      $this->assertEquals($count, count($decoded));
    }
  }

}
