<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Queue;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Queue\QueueInterface;
use Drupal\mongodb\MongoDb;
use MongoDB\Database;

/**
 * MongoDB queue implementation.
 *
 * @ingroup queue
 */
class MongoDBQueue implements QueueInterface {

  /* Fails tests otherwise, as $database and $collection embeds MongoDB\Driver\Manager */
  use DependencySerializationTrait;

  /**
   * The queue storage.
   *
   * @var \MongoDB\Database
   */
  protected Database $database;

  /**
   * The collection name for the queue.
   *
   * @var \MongoDB\Collection[]
   */
  protected $collection;

  /**
   * Constructs a \Drupal\mongodb\Queue\MongodbQueue object.
   *
   * @param string $name
   *   The name of the queue.
   * @param array $settings
   *   Array of Mongodb-related settings for this queue.
   * @param \MongoDB\Database $database
   *   The database object.
   */
  public function __construct($name, array $settings, Database $database) {
    $this->name = $name;
    $this->database = $database;
    $this->collection = $this->database
      ->selectCollection($name);
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    try {
      $id = $this->doCreateItem($data);
    }
    catch (\Exception $e) {
      throw $e;
    }

    return $id;
  }

  /**
   * Adds a queue item and store it directly to the queue.
   *
   * @param mixed $data
   *   Arbitrary data to be associated with the new task in the queue.
   *
   * @return mixed
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE. We don't guarantee the item was
   *   committed to disk etc, but as far as we know, the item is now in the
   *   queue.
   */
  protected function doCreateItem($data) {

    $item = [
      'name' => $this->name,
      'data' => $data,
      'expire' => 0,
      'created' => time(),
    ];

    $result = $this->collection->insertOne($item);

    return $result->getInsertedId();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function numberOfItems(): int {

    try {
      return MongoDb::countCollection($this->collection);
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    $newobj = [
      'expire' => time() + $lease_time,
    ];
    return $this->collection->findOneAndUpdate(
      [],
      ['$set' => $newobj],
      ['sort' => ['created' => 1]],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    return $this->collection
      ->updateOne(
        ['_id' => $item->_id],
        [
          '$set' =>
            [
              'expire' => 0,
            ],
        ]
      );
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    try {
      $this->collection
        ->deleteOne(
          [
            '_id' => $item->_id,
          ]
        );
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    // Create the index.
    $this->collection->createIndex([
      'expire' => 1,
      'created' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    try {
      $this->collection->drop();
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

}
