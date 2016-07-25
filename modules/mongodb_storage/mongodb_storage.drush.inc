<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Implements hook_drush_command().
 */
function mongodb_storage_drush_command() {
  $file = preg_replace('/(inc|php)$/', 'yml', __FILE__);
  $config = Yaml::parse(file_get_contents($file));
  $items = $config['commands'];
  return $items;
}


function drush_mongodb_storage_import_keyvalue() {
  $dic = \Drupal::getContainer();
  /** @var \Drupal\Core\Database\Connection $db */
  $db = $dic->get('database');

  /** @var \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory $persistentDbFactory */
  $persistentDbFactory = $dic->get('keyvalue.database');
  /** @var \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory $expirableDbFactory */
  $expirableDbFactory = $dic->get('keyvalue.expirable.database');
  /** @var \Drupal\mongodb_storage\KeyValueFactory $persistentMongoDbFactory */
  $persistentMongoDbFactory = $dic->get('keyvalue.mongodb');
  /** @var \Drupal\mongodb_storage\KeyValueExpirableFactory $expirableMongoDbFactory */
  $expirableMongoDbFactory = $dic->get('keyvalue.expirable.mongodb');

  $stores = [
    'key_value' => [
      'db' => $persistentDbFactory,
      'mg' => $persistentMongoDbFactory,
    ],
    'key_value_expire' => [
      'db' => $expirableDbFactory,
      'mg' => $expirableMongoDbFactory,
    ],
  ];

  /** @var \Drupal\Core\KeyValueStore\DatabaseStorage $store */
  foreach ($stores as $tableName => $factories) {
    $cursor = $db->select($tableName, 's')
      ->distinct()
      ->fields('s', ['collection'])
      ->execute();
    echo "$tableName\n";

    foreach ($cursor as $name) {
      $collection = $name->collection;
      echo "  $collection\n";

      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $dbStore */
      $dbStore = $factories['db']->get($collection);
      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $mgStore */
      $mgStore = $factories['mg']->get($collection, FALSE);

      $mgStore->deleteAll();
      foreach ($dbStore->getAll() as $key => $value) {
        echo "    $key\n";
        // @FIXME Use setWithExpire with a meaningful expire instead.
        $mgStore->set($key, $value);
      }
    }
  }
}