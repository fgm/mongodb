<?php

namespace Drupal\mongodb\Tests;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mongodb\ClientFactory;
use MongoDB\Driver\Exception\ConnectionTimeoutException;

/**
 * Class ClientFactoryTest.
 *
 * @group MongoDB
 */
class ClientFactoryTest extends KernelTestBase {
  const DEFAULT_URI = 'mongodb://localhost:27017';
  const BAD_ALIAS = 'bad';
  const TEST_ALIAS = 'test';

  public static $modules = ['mongodb'];

  /**
   * A test-specific instance of Settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;


  protected $uri;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // $_ENV if it comes from phpunit.xml <env>
    // $_SERVER if it comes from the phpunit command line environment
    $this->uri = $_ENV['MONGODB_URL'] ?? $_SERVER['MONGODB_URI'] ?? static::DEFAULT_URI;

    $this->settings = new Settings([
      'mongodb' => [
        'clients' => [
          static::BAD_ALIAS => [
            'uri' => 'mongodb://localhost:80',
            'uriOptions' => [],
            'driverOptions' => [],
          ],
          static::TEST_ALIAS => [
            'uri' => $this->uri,
            'uriOptions' => [],
            'driverOptions' => [],
          ],
        ],
        'databases' => [
          'default' => [static::TEST_ALIAS, $this->getDatabasePrefix()],
        ],
      ],
    ]);
  }

  /**
   * Test a normal client creation attempt.
   */
  public function testGetHappy() {
    $cf = new ClientFactory($this->settings);

    try {
      $client = $cf->get(static::TEST_ALIAS);
      // Force connection attempt by executing a command.
      $client->listDatabases();
    }
    catch (ConnectionTimeoutException $e) {
      $this->fail(new FormattableMarkup("Could not connect to server on @uri. Enable one on @default or specify one in MONGODB_URI.", [
        '@default' => static::DEFAULT_URI,
        '@uri' => $this->uri,
      ]));
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
  }

  /**
   * Test an existing alias pointing to an invalid server.
   */
  public function testGetSadBadAlias() {
    $cf = new ClientFactory($this->settings);

    try {
      $client = $cf->get(static::BAD_ALIAS);
      // Force connection attempt by executing a command.
      $client->listDatabases();
      $this->fail("Should not have been able to connect to a non-server.");
    }
    catch (ConnectionTimeoutException $e) {
      $this->pass("Cannot create a client to a non-server.");
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
  }

}
