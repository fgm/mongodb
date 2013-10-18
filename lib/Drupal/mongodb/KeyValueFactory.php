<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongoKeyValueFactory.
 */

namespace Drupal\mongodb;

use \Drupal\Component\Utility\Settings;

class KeyValueFactory {

  /**
   * @var MongoCollectionFactory $mongo
   */
  protected $mongo;

  /**
   * The settings array.
   *
   * @var \Drupal\Component\Utility\Settings
   */
  protected $settings;

  /**
   * @param MongoCollectionFactory $mongo
   * @param \Drupal\Component\Utility\Settings $settings
   */
  function __construct(MongoCollectionFactory $mongo, Settings $settings) {
    $this->mongo = $mongo;
    $this->settings = $settings;
  }

  function get($collection) {
    $mongo_collection = "keyvalue.$collection";

    $settings = $this->settings->get('mongo');
    if (isset($settings['keyvalue']['ttl'])) {
      $ttl = $settings['keyvalue']['ttl'];
    }
    else {
      $ttl = 300;
    }
    $this->mongo->get($mongo_collection)->ensureIndex(array('expire' => 1), array('expireAfterSeconds' => $ttl));
    $this->mongo->get($mongo_collection)->ensureIndex(array('_id' => 1, 'expire' => 1));
    return new KeyValue($this->mongo, $collection);
  }

}
