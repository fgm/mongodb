<?php

declare(strict_types=1);

namespace Drupal\mongodb\Install;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Site\Settings;
use Drupal\mongodb\DatabaseFactory;
use Drupal\mongodb\MongoDb;
use MongoDB\Exception\InvalidArgumentException;

/**
 * Class MongoDbCommands provides the Drush commands for the mongodb module.
 */
class Tools {

  /**
   * The mongobb.database_factory service.
   *
   * @var \Drupal\mongodb\DatabaseFactory
   */
  protected $dbFactory;

  /**
   * The settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The serialization.yaml service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $yaml;

  /**
   * MongoDbCommands constructor.
   *
   * @param \Drupal\mongodb\DatabaseFactory $databaseFactory
   *   The mongobb.database_factory service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   * @param \Drupal\Component\Serialization\SerializationInterface $yaml
   *   The serialization.yaml service.
   */
  public function __construct(
    DatabaseFactory $databaseFactory,
    Settings $settings,
    SerializationInterface $yaml
  ) {
    $this->dbFactory = $databaseFactory;
    $this->settings = $settings;
    $this->yaml = $yaml;
  }

  /**
   * Command callback for mongodb:mdbf.
   *
   * @param string $alias
   *   The alias of the database in which to perform the query.
   * @param string $collection
   *   The name of the collection in which to find.
   * @param string $selector
   *   The selector to apply to the query.
   *
   * @return array<int,array<scalar,mixed>>
   *   The query results.
   */
  public function find(string $alias, string $collection, string $selector = '{}'): array {
    /** @var \MongoDB\Database $database */
    $database = $this->dbFactory->get($alias);
    $jsonSelector = json_decode($selector);
    if ($jsonSelector === NULL) {
      throw new InvalidArgumentException("Your JSON selector could not be decoded. Here is how PHP received it: " . var_export($selector, TRUE));
    }
    $docs1 = $database->selectCollection($collection)
      ->find($jsonSelector, [
        'typeMap' => [
          'root' => 'array',
          'document' => 'array',
          'array' => 'array',
        ],
      ]);

    $docs2 = [];
    // Convert objects in result set to hashes.
    foreach ($docs1 as $doc1) {
      $docs2[] = json_decode(json_encode($doc1), TRUE);
    }
    return $docs2;
  }

  /**
   * List collections matching $regex in database $alias.
   *
   * @param string $alias
   *   The alias in which to list the collections.
   * @param string $regex
   *   The pattern collection names must match.
   *
   * @return \MongoDB\Collection[]
   *   An array of collection objects.
   */
  public function listCollections(string $alias, string $regex): array {
    /** @var \MongoDB\Database $database */
    $database = $this->dbFactory->get($alias);
    $collections = [];
    foreach ($database->listCollections() as $info) {
      $name = $info->getName();
      $collection = $database->selectCollection($name);
      if (preg_match($regex, $name)) {
        $collections[] = $collection;
      }
    }
    return $collections;
  }

  /**
   * Command callback for mongodb:mdbs.
   *
   * @return array{clients: array<string,array<string,mixed>>, databases: array<string,array{0:string,1:string}>}
   *   The MongoDB portion of the settings. Refer to example.settings.local.php.
   */
  public function settings(): array {
    return $this->settings->get(MongoDb::MODULE);
  }

}
