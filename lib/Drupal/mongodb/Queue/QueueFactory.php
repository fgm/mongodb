<?php

/**
 * @file
 * Contains \Drupal\mongodb\Queue\QueueFactory.
 */

namespace Drupal\mongodb\Queue;

use Drupal\mongodb\MongoCollectionFactory;

/**
 * Defines the queue factory for the MongoDB backend.
 */
class QueueFactory {

  /**
   * Mongo collection factory.
   *
   * @var \Drupal\mongodb\MongoCollectionFactory $database
   */
  protected $database;

  /**
   * Constructs this factory object.
   *
   * @param \Drupal\mongodb\MongoCollectionFactory $database
   *   Mongo collection factory.
   */
  function __construct(MongoCollectionFactory $database) {
    $this->database = $database;
  }

  /**
   * Constructs a new queue object for a given name.
   *
   * @param string $name
   *   The name of the collection holding key and value pairs.
   *
   * @return \Drupal\mongodb\Queue\Queue
   *   A queue implementation for the given queue.
   */
  public function get($name) {
    return new Queue($name, $this->connection);
  }
}
