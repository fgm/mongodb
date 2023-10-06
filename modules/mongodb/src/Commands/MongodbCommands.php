<?php

declare(strict_types=1);

namespace Drupal\mongodb\Commands;

use Drupal\mongodb\Install\Tools;
use Drush\Commands\DrushCommands;

/**
 * Drush 9 commands service for the mongodb module.
 */
class MongodbCommands extends DrushCommands {

  /**
   * The mongodb.tools service.
   *
   * @var \Drupal\mongodb\Install\Tools
   */
  protected $tools;

  /**
   * MongodbCommands constructor.
   *
   * @param \Drupal\mongodb\Install\Tools $tools
   *   The mongodb.tools service.
   */
  public function __construct(Tools $tools) {
    $this->tools = $tools;
  }

  /**
   * Drop the Simpletest leftover collections.
   *
   * @command mongodb:clean-tests
   * @aliases mdct,mo-clean
   *
   * @usage drush mongodb:clean-tests
   *   Clean test results after "bash tests.bash".
   */
  public function mongodbCleanTests(): void {
    $dbs = array_keys($this->tools->settings()['databases']);
    foreach ($dbs as $dbAlias) {
      /** @var \MongoDB\Collection[] $collections */
      $collections = $this->tools->listCollections($dbAlias,
        "/^simpletest/");
      foreach ($collections as $collection) {
        $this->logger()->notice("Dropping {collectionName}", [
          'collectionName' => $collection->getCollectionName(),
        ]);
        $collection->drop();
      }
    }
  }

  /**
   * Execute a find() query against a collection.
   *
   * @param string $alias
   *   The database alias.
   * @param string $collection
   *   The collection name in the database.
   * @param string $selector
   *   A MongoDB find() selector in JSON format. Defaults to '{}'.
   * @param array<string,mixed> $options
   *   A Drush-magic parameter enabling Drush to choose the output format.
   *
   * @return array<int,array<mixed,mixed>>
   *   The matching documents, in array format.
   *
   * @usage drush mongodb:find <collection> <query>...
   *   <query> is a single JSON selector in single string format. Quote it.
   * @usage drush mongodb:find logger watchdog
   *   Get the logger/watchdog error-level templates
   * @usage drush mo-find logger watchdog '{ "severity": 3 }'
   *   Get all the logger/watchdog entries tracking rows.
   * @usage drush mdbf keyvalue kvp_state '{ "_id": "system.theme_engine.files" }'
   *   Get a specific State entry. Note how escaping needs to be performed in
   *   the shell.
   *
   * @command mongodb:find
   * @aliases mdbf,mo-find
   */
  public function mongodbFind(
    string $alias,
    string $collection,
    string $selector = '{}',
    array $options = ['format' => 'yaml']
  ) {
    return $this->tools->find($alias, $collection, $selector);
  }

  /**
   * Print MongoDB settings in Yaml format.
   *
   * @param array<string,mixed> $options
   *   A Drush-magic parameter enabling Drush to choose the output format.
   *
   * @return array{clients: array<string,array<string,mixed>>, databases: array<string,array{0:string,1:string}>}
   *   Refer to example.settings.local.php for details.
   *
   * @usage mongodb:settings
   *   Report on the settings as seen by the MongoDB module suite.
   *
   * The "unused" $options allows Drush to know the command should support the
   * --format option, with the chosen default.
   *
   * @command mongodb:settings
   * @aliases mdbs,mo-set
   */
  public function mongodbSettings($options = ['format' => 'yaml']): array {
    return $this->tools->settings();
  }

}
