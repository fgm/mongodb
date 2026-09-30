<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\mongodb\ClientFactory;
use Drupal\mongodb\MongoDb;
use MongoDB\Driver\Exception\ConnectionTimeoutException;

/**
 * Tests the ClientFactory.
 *
 * @coversDefaultClass \Drupal\mongodb\ClientFactory
 *
 * @group MongoDB
 */
class ClientFactoryTest extends MongoDbTestBase {

  /**
   * Test a normal client creation attempt.
   */
  public function testGetHappy(): void {
    $clientFactory = new ClientFactory($this->settings);
    $alias = static::CLIENT_TEST_ALIAS;

    try {
      $client = $clientFactory->get($alias);
      // Force connection attempt by executing a command.
      $result = $client->selectDatabase('admin')->command(['ping' => 1])->toArray();
    }
    catch (ConnectionTimeoutException $e) {
      $uri = $this->settings->get(MongoDb::MODULE)['clients'][$alias]['uri'] ?? '';
      $this->fail(sprintf(
        'Could not connect to server on %s: %s. Enable one on %s or specify one in MONGODB_URI.',
        $uri, $e->getMessage(), static::DEFAULT_URI,
      ));
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
    $this->assertEquals(1, $result[0]['ok'] ?? NULL, 'The server answers ping.');
  }

  /**
   * Test an existing alias pointing to an invalid server.
   */
  public function testGetSadBadAlias(): void {
    // Cannot create a client to a non-server.
    $this->expectException(ConnectionTimeoutException::class);
    $clientFactory = new ClientFactory($this->settings);
    $client = $clientFactory->get(static::CLIENT_BAD_ALIAS);
    // Force connection attempt by executing a command.
    $client->listDatabases();
  }

}
