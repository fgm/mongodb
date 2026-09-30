<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\mongodb\DatabaseFactory;
use MongoDB\Database;

/**
 * Defines the queue factory for the MongoDB backend.
 *
 * Regrettably there is currently no core QueueFactoryInterface.
 */
class QueueFactory {
  const DB_QUEUE = 'queue';
  const COLLECTION_PREFIX = 'q_';

  /**
   * The database in which the queues are created.
   */
  protected Database $database;

  /**
   * The datetime.time service.
   */
  protected TimeInterface $time;

  /**
   * QueueFactory constructor.
   *
   * @param \Drupal\mongodb\DatabaseFactory $databaseFactory
   *   The mongodb.database_factory service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(DatabaseFactory $databaseFactory, TimeInterface $time) {
    $this->database = $databaseFactory->get(static::DB_QUEUE);
    $this->time = $time;
  }

  /**
   * Constructs a new queue object for a given name.
   *
   * @param string $queueName
   *   The name of the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   A queue implementation the given $collection.
   */
  public function get(string $queueName): QueueInterface {
    $queueCollection = $this->database->selectCollection(static::COLLECTION_PREFIX . $queueName);
    $queue = new Queue($queueCollection, $this->time);
    return $queue;
  }

}
