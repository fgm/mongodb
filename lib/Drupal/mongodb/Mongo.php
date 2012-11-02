<?php

namespace Drupal\mongodb;

/**
 * This class holds a MongoDB database object.
 */
class Mongo {

  /**
   * @var Mongo
   */
  protected $db;

  public function __construct(array $connection) {
    $this->connection = $connection;
  }

  /**
   * @return Mongo
   */
  protected function getDb() {
    if (!isset($this->db)) {
      $db = $this->connection['db'];
      $host = $this->connection['host'];
      $options = $this->connection['connection_options'] + array('connect' => TRUE);
      try {
        $mongo = new Mongo($host, $options);
        if (!empty($connection['slave_ok'])) {
          $mongo->setSlaveOkay(TRUE);
        }
        $mongo_objects[$host][$db] = $mongo->selectDB($db);
        $mongo_objects[$host][$db]->connection = $mongo;
      }
      catch (\MongoConnectionException $e) {
        $mongo_objects[$host][$db] = new mongoDummy;
        throw $e;
      }
    }
    return $this->db;
  }

  /**
   * @param $collection_name
   * @return MongoDB
   */
  public function get($collection_name) {
    return $this->getDb()->selectCollection($collection_name);
  }
}
