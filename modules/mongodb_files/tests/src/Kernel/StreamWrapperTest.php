<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_files\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_files\Files;
use Drupal\Tests\mongodb\Kernel\MongoDbTestBase;

/**
 * Tests the mongodb: stream wrapper.
 *
 * @group MongoDB
 */
class StreamWrapperTest extends MongoDbTestBase {

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
   * Test callback.
   */
  public function testPlainWrite() {
    $gfs = $this->container->get(Files::SERVICE_WRAPPER);
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $swm */
    $swm = $this->container->get('stream_wrapper_manager');
    $uri = $gfs->getUri();
    $path = Files::SCHEME . "://hello_world.txt";
    $data = "hello world";
    $actual = file_put_contents($path, $data);
    // We count bytes, not runes, so no mb_strlen().
    $this->assertEquals(strlen($data), $actual);
  }

}
