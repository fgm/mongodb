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
        $mongo = new \Mongo($host, $options);
        if (!empty($connection['slave_ok'])) {
          $mongo->setSlaveOkay(TRUE);
        }
        $this->db = $mongo->selectDB($db);
      }
      catch (\MongoConnectionException $e) {
        $this->db = new mongoDummy;
        throw $e;
      }
    }
    return $this->db;
  }

  /**
   * @param $collection_name
   * @return \MongoCollection
   */
  public function get($collection_name) {
    return $this->getDb()->selectCollection(str_replace('system.', 'system_.', $collection_name));
  }
}
