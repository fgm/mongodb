<?php

namespace Drupal\mongodb_storage;

/**
 * Class MongoDb contains constants usable by all modules using the storage.
 */
class Storage {
  const MODULE = 'mongodb_storage';

  const SERVICE_COMMANDS = 'drupal.mongodb_storage.commands';
  const SERVICE_KV = 'keyvalue.mongodb';
  const SERVICE_KVE = 'keyvalue.expirable.mongodb';

}
