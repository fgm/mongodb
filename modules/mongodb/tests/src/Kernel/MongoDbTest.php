<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Drupal\mongodb\MongoDb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the MongoDB main class.
 *
 * @covers \Drupal\mongodb\MongoDb
 *
 * @group mongodb
 */
#[CoversClass(MongoDb::class)]
#[Group('mongodb')]
#[RunTestsInSeparateProcesses]
class MongoDbTest extends MongoDbTestBase {

  /**
   * Tests the reported MongoDB library version.
   */
  public function testLibraryVersion(): void {
    $actual = MongoDb::libraryApiVersion();
    $this->assertNotSame('', $actual, 'The library version is known to Composer.');
    $this->assertTrue(
      InstalledVersions::satisfies(new VersionParser(), MongoDb::LIBRARY_PACKAGE, MongoDb::LIBRARY_CONSTRAINT),
      sprintf('Library version %s must satisfy %s.', $actual, MongoDb::LIBRARY_CONSTRAINT)
    );
  }

  /**
   * Tests the deprecated countCollection().
   */
  public function testCountCollection(): void {
    /** @var \Drupal\mongodb\DatabaseFactory $dbFactory */
    $dbFactory = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
    $database = $dbFactory->get(MongoDb::DB_DEFAULT);
    $collectionName = $this->getDatabasePrefix() . $this->randomMachineName();
    $collection = $database->selectCollection($collectionName);
    $collection->drop();

    // At least one document: insertMany() rejects an empty array.
    $expected = mt_rand(1, 100);
    $docs = [];
    for ($i = 0; $i < $expected; $i++) {
      $docs[] = [
        "index" => $i,
      ];
    }
    $collection->insertMany($docs);

    // Capture the deprecation directly: PHPUnit 9 and 11 expect deprecations
    // through different, incompatible APIs.
    $deprecations = [];
    set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
      $deprecations[] = $message;
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      // @phpstan-ignore staticMethod.deprecated
      $actual = MongoDb::countCollection($collection);
    }
    finally {
      restore_error_handler();
    }
    $this->assertEquals($expected, $actual,
      "countCollection finds the correct number of documents");
    $this->assertCount(1, $deprecations);
    $this->assertStringContainsString('countCollection() is deprecated in mongodb:8.x-2.2', $deprecations[0]);
  }

}
