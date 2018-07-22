<?php

namespace Drupal\mongodb\Commands;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Site\Settings;
use Drupal\mongodb\DatabaseFactory;
use Drupal\mongodb\MongoDb;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MongoDbCommands provides the Drush commands for the mongodb module.
 */
class MongoDbCommands implements ContainerInjectionInterface {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\mongodb\DatabaseFactory $dbFactory */
    $dbFactory = $container->get(MongoDb::SERVICE_DB_FACTORY);
    /** @var \Drupal\Core\Serialization\Yaml $yaml */
    $yaml = $container->get('serialization.yaml');
    /** @var \Drupal\Core\Site\Settings $settings */
    $settings = $container->get('settings');
    return new static($dbFactory, $settings, $yaml);
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
   * @return string
   *   The serialized version of the query results.
   */
  public function find(string $alias, string $collection, string $selector = '{}'): string {
    /** @var \MongoDB\Database $db */
    $db = $this->dbFactory->get($alias);
    $jsonSelector = json_decode($selector);
    $docs1 = $db->selectCollection($collection)
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
    return $this->yaml->encode($docs2);
  }

  /**
   * Command callback for mongodb:mdbs.
   *
   * @return string
   *   The serialized version of the MongoDB portion of the settings.
   */
  public function settings(): string {
    return $this->yaml->encode($this->settings->get(MongoDb::MODULE));
  }

}
