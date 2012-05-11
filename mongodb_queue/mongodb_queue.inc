<?php

/**
 * @file
 * Queue functionality.
 */

class MongoDBQueue implements DrupalQueueInterface {
  protected $collection;

  /**
   * Start working with a queue.
   *
   * @param string $name
   *   The name of the queue
   */
  public function __construct($name) {
    // @todo: make sure that $name is a valid collection name?
    $this->collection = 'queue.' . $name;
  }

  /**
   * Add a queue item and store it directly to the queue.
   *
   * @param object $data
   *   Arbitrary data to be associated with the new task in the queue
   *
   * @return boolean
   *   If the item was successfully created and added to the queue.
   */
  public function createItem($data) {
    $item = array(
      'data' => $data,
      'created' => time(),
      'expire' => 0,
    );
    return mongodb_collection($this->collection)
      ->insert($item);
  }

  /**
   * Retrieve the number of items in the queue.
   *
   * @return integer
   *   An integer estimate of the number of items in the queue.
   */
  public function numberOfItems() {
    return mongodb_collection($this->collection)
      ->count();
  }

  /**
   * Claim an item in the queue for processing.
   *
   * @param string $lease_time
   *   How long the processing is expected to take in seconds,
   *
   * @return object/boolean
   *   On success we return an item object. If the queue is unable to claim
   *   an item it returns false.
   */
  public function claimItem($lease_time = 30) {
    $query = array(
      'expire' => 0,
    );
    $newobj = array(
      'expire' => time() + $lease_time,
    );
    $cmd = array(
      'findandmodify' => mongodb_collection_name($this->collection),
      'query' => $query,
      'update' => array('$set' => $newobj),
      'sort' => array('created' => 1),
    );
    if (($result = mongodb_collection($this->collection)->db->command($cmd)) && $result['ok'] == 1 && !empty($result['value'])) {
      return (object) $result['value'];
    }
  }

  /**
   * Release an item that the worker could not process
   *
   * @param object $item
   *   The item to release.
   */
  public function releaseItem($item) {
    return mongodb_collection($this->collection)
      ->update(array('_id' => $item->_id), array('$set' => array('expire' => 0)));
  }

  /**
   *  Delete a finished item from the queue.
   *
   * @param object $item
   *   The item to delete.
   */
  public function deleteItem($item) {
    mongodb_collection($this->collection)
      ->remove(array('_id' => $item->_id));
  }

  /**
   *  Create a queue.
   */
  public function createQueue() {
    // Create the index.
    mongodb_collection($this->collection)
      ->ensureIndex(array('expire' => 1, 'created' => 1));
  }

  /**
   *  Delete a queue and every item in the queue.
   */
  public function deleteQueue() {
    mongodb_collection($this->collection)->drop();
  }
}
