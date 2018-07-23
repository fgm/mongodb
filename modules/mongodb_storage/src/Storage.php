<?php

namespace Drupal\mongodb_storage;

use Drupal\mongodb_storage\Commands\MongoDbStorageCommands;

/**
 * Class MongoDb contains constants usable by all modules using the storage.
 */
class Storage {
  const MODULE = 'mongodb_storage';

  const SERVICE_COMMANDS = 'drupal.mongodb_storage.commands';
  const SERVICE_KV = 'keyvalue.mongodb';
  const SERVICE_KVE = 'keyvalue.expirable.mongodb';

  /**
   * Service helper for commands.
   *
   * @return \Drupal\mongodb_storage\Commands\MongoDbStorageCommands
   *   Return the commands service.
   */
  public static function commands(): MongoDbStorageCommands {
    return \Drupal::service(static::SERVICE_COMMANDS);
  }

}
