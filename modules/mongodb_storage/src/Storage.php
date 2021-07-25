<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage;

/**
 * Class Storage contains constants usable by all modules using the storage.
 */
class Storage {

  const MODULE = 'mongodb_storage';

  const SERVICE_KV = 'keyvalue.mongodb';

  const SERVICE_KVE = 'keyvalue.expirable.mongodb';

  const SERVICE_QUEUE = 'queue.mongodb';

  const SERVICE_SQL_IMPORT = 'mongodb.storage.sql_import';

}
