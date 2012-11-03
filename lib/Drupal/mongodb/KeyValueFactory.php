<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongoKeyValueFactory.
 */

namespace Drupal\mongodb;

class KeyValueFactory {

  /**
   * @var MongoCollectionFactory $mongo
   */
  protected $mongo;

  /**
   * @param MongoCollectionFactory $mongo
   */
  function __construct(MongoCollectionFactory $mongo) {
    $this->mongo = $mongo;
  }

  function get($collection) {
    return new KeyValue($this->mongo, "keyvalue.$collection");
  }

}
