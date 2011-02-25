<?php

/**
 * @file
 * Queue functionality.
 */

class MongoDBQueue implements DrupalQueueInterface {
  protected $collection;

  public function __construct($name) {
    // @todo: make sure that $name is a valid collection name?
    $this->collection = 'queue.' . $name;
  }

  public function createItem($data) {
    $item = array(
      'data' => $data,
      'created' => time(),
      'expire' => 0,
    );
    return mongodb_collection($this->collection)
      ->insert($item);
  }

  public function numberOfItems() {
    return mongodb_collection($this->collection)
      ->count();
  }

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
    if (($result = mongodb_collection($this->collection)->db->command($cmd)) && $result['ok'] == 1) {
      return (object) $result['value'];
    }
  }

  public function releaseItem($item) {
    return mongodb_collection($this->collection)
      ->update(array('_id' => $item->_id), array('$set' => array('expire' => 0)));
  }

  public function deleteItem($item) {
    mongodb_collection($this->collection)
      ->remove(array('_id' => $item->_id));
  }

  public function createQueue() {
    // create the index.
    mongodb_collection($this->collection)
      ->ensureIndex(array('expire' => 1));
  }

  public function deleteQueue() {
    mongodb_collection($this->collection)->drop();
  }
}
