<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_files\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_files\Files;
use Drupal\Tests\mongodb\Kernel\MongoDbTestBase;

/**
 * Tests the mongodb: stream wrapper.
 *
 * @coversDefaultClass
 *
 * @group MongoDB
 */
class StreamWrapperTest extends MongoDbTestBase {

  public const FIXTURE_NAME = "hello-world.txt";

  public const FIXTURE_PATH = __DIR__ . "/../../fixtures/" . self::FIXTURE_NAME;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    MongoDb::MODULE,
    Files::MODULE,
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function getSettingsArray(): array {
    $settings = parent::getSettingsArray();
    $settings['databases'][Files::DB_FILES] = [
      MongoDbTestBase::CLIENT_TEST_ALIAS,
      $this->getDatabasePrefix(),
    ];

    return $settings;
  }

  /**
   * Test file read.
   *
   */
  public function testPlainRead() {
    $dbf = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
    $db = $dbf->get(Files::DB_FILES);

    $bucket = $db->selectGridFSBucket();

    $path = Files::SCHEME . "://" . self::FIXTURE_NAME;
    $id = $bucket->uploadFromStream(self::FIXTURE_NAME, fopen(self::FIXTURE_PATH, "rb"));

    $expected = file_get_contents(self::FIXTURE_PATH);
    $f = fopen($path, "r");
    $actual = fread($f, 8192);
    $actual = file_get_contents($path);
    // We count bytes, not runes, so no mb_strlen().
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test file writes.
   */
  public function testPlainWrite() {
    $path = Files::SCHEME . "://hello_world.txt";
    $data = "hello world";
    $actual = file_put_contents($path, $data);
    // We count bytes, not runes, so no mb_strlen().
    $this->assertEquals(strlen($data), $actual);
  }

}
