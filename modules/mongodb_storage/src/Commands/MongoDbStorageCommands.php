<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Commands;

use Drupal\mongodb_storage\Install\SqlImport;
use Drush\Commands\DrushCommands;

/**
 * Drush 9 commands service for the mongodb_watchdog module.
 */
class MongoDbStorageCommands extends DrushCommands {

  /**
   * The mongodb.watchdog.sanity_check service.
   *
   * @var \Drupal\mongodb_storage\Install\SqlImport
   */
  protected $sqlImport;

  /**
   * MongodbWatchdogCommands constructor.
   *
   * @param \Drupal\mongodb_storage\Install\SqlImport $sqlImport
   *   The mongodb.storage.sql_import service.
   */
  public function __construct(SqlImport $sqlImport) {
    $this->sqlImport = $sqlImport;
  }

  /**
   * Import the database KeyValue contents from default keys to MongoDB.
   *
   * @usage mongodb:storage:import_keyvalue
   *   Import the database KeyValue contents from default keys to MongoDB.
   *
   * @command mongodb:storage:import_keyvalue
   * @aliases mdbsikv,most-ikv
   */
  public function import(): void {
    $this->sqlImport->import();
  }

}
