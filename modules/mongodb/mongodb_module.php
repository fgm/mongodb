<?php

/**
 * @file
 * Contains the main module connecting Drupal to MongoDB.
 */

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Implements hook_help().
 */
function mongodb_help($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    case 'help.page.mongodb':
      return '<p>' . t('The Drupal <a href=":project">MongoDB</a> project implements a generic interface to the <a href=":mongo">MongoDB</a> database server.', [
        ':project' => 'http://drupal.org/project/mongodb',
        ':mongo' => 'http://www.mongodb.org/',
      ]);
  }
}

/* ==== Broken below this line ============================================== */

/**
 * Returns an MongoDB object.
 *
 * @param string $alias
 *   The name of a MongoDB connection alias. If it is not passed, or if the
 *   alias does not match a connection definition, the function will fall back
 *   to the 'default' alias.
 *
 * @return \MongoDB|\MongoDummy
 *   A MongoDummy is returned in case a MongoConnectionException is thrown.
 *
 * @throws \InvalidArgumentException
 *   If the database cannot be selected.
 * @throws \MongoDB\Driver\Exception\ConnectionException
 *   If the connection cannot be established.
 *
 * @see mongoDummy
 */
function mongodb($alias = 'default') {
  static $mongo_objects;
  $connections = variable_get('mongodb_connections', array());
  if (!isset($connections[$alias])) {
    $alias = 'default';
  }
  $connection = $connections[$alias] ?? [];
  $connection += array(
    'host' => 'localhost',
    'db' => 'drupal',
    'connection_options' => array(),
  );
  $host = $connection['host'];
  $options = $connection['connection_options'] + array('connect' => TRUE);
  $db = $connection['db'];
  if (!isset($mongo_objects[$host][$db])) {
    try {
      // Use the 1.3 client if available.
      if (class_exists('MongoClient')) {
        $mongo = new MongoClient($host, $options);
        // Enable read preference and tags if provided. This can also be
        // controlled on a per query basis at the cursor level if more control
        // is required.
        if (!empty($connection['read_preference'])) {
          $tags = !empty($connection['read_preference']['tags']) ? $connection['read_preference']['tags'] : array();
          $mongo->setReadPreference($connection['read_preference']['preference'], $tags);
        }
      }
      else {
        $mongo = new Mongo($host, $options);
        if (!empty($connection['slave_ok'])) {
          $mongo->setSlaveOkay(TRUE);
        }
      }
      $mongo_objects[$host][$db] = $mongo->selectDB($db);
      $mongo_objects[$host][$db]->connection = $mongo;
    }
    catch (MongoConnectionException $e) {
      $mongo_objects[$host][$db] = new MongoDummy();
      throw $e;
    }
  }
  return $mongo_objects[$host][$db];
}

/**
 * Returns a MongoCollection object.
 *
 * @return \MongoCollection|\MongoDebugCollection|\MongoDummy
 *   Return a MongoCollection in normal situations, a MongoDebugCollection if
 *   mongodb_debug is enabled, or a MongoDummy if the MongoDB connection could
 *   not be established.
 *
 * @throws \InvalidArgumentException
 *   If the database cannot be selected.
 * @throws \MongoDB\Driver\Exception\ConnectionException
 *   If the connection cannot be established.
 */
function mongodb_collection() {
  $args = array_filter(func_get_args());
  if (is_array($args[0])) {
    list($collection_name, $prefixed) = $args[0];
    $prefixed .= $collection_name;
  }
  else {
    // Avoid something. collection names if NULLs are passed in.
    $collection_name = implode('.', array_filter($args));
    $prefixed = mongodb_collection_name($collection_name);
  }
  $collections = variable_get('mongodb_collections', array());
  if (isset($collections[$collection_name])) {
    // We might be dealing with an array or string because of need to preserve
    // backwards compatibility.
    $alias = is_array($collections[$collection_name]) && !empty($collections[$collection_name]['db_connection'])
      ? $collections[$collection_name]['db_connection']
      : $collections[$collection_name];
  }
  else {
    $alias = 'default';
  }
  // Prefix the collection name for simpletest. It will be in the same DB as the
  // non-prefixed version so it's enough to prefix after choosing the mongodb
  // object.
  $mongodb_object = mongodb($alias);
  $collection = $mongodb_object->selectCollection(mongodb_collection_name($collection_name));
  // Enable read preference and tags at a collection level if we have 1.3
  // client.
  if (!empty($collections[$alias]['read_preference']) && get_class($mongodb_object->connection) == 'MongoClient') {
    $tags = !empty($collections[$alias]['read_preference']['tags']) ? $collections[$alias]['read_preference']['tags'] : array();
    $collection->setReadPreference($collections[$alias]['read_preference']['preference'], $tags);
  }
  $collection->connection = $mongodb_object->connection;
  return variable_get('mongodb_debug', FALSE) ? new MongoDebugCollection($collection) : $collection;
}

/**
 * Class MongoDebugCollection is a debug decorator for MongoCollection.
 */
class MongoDebugCollection {

  protected $collection;

  /**
   * Constructor.
   *
   * @param string $collection
   *   The collection to decorate.
   */
  public function __construct($collection) {
    $this->collection = $collection;
  }

  /**
   * A decorator for the MongoCollection::find() method adding debug info.
   *
   * @param array $query
   *   A MongoCollection;:find()-compatible query array.
   * @param array $fields
   *   A MongoCollection;:find()-compatible fields array.
   *
   * @return \MongoDebugCursor
   *   A debug cursor wrapping the decorated find() results.
   */
  public function find($query = array(), $fields = array()) {
    debug('find');
    debug($query);
    debug($fields);
    return new MongoDebugCursor($this->collection->find($query, $fields));
  }

  /**
   * Decorates the standard __call() by debug()-ing its arguments.
   *
   * @param string $name
   *   The name of the called method.
   * @param array $arguments
   *   The arguments for the decorated __call().
   *
   * @return mixed
   *   The result of the decorated __call().
   */
  public function __call($name, array $arguments) {
    debug($name);
    debug($arguments);
    return call_user_func_array(array($this->collection, $name), $arguments);
  }

}

/**
 * Class MongoDebugCursor is a debug decorator for MongoCollection::find().
 */
class MongoDebugCursor {

  protected $collection;

  /**
   * Constructor.
   *
   * @param string $collection
   *   The name of the collection on which the cursor applies.
   *
   * @see mongoDebugCollection::find()
   */
  public function __construct($collection) {
    $this->collection = $collection;
  }

  /**
   * Decorates the standard __call() by debug()-ing its arguments.
   *
   * @param string $name
   *   The name of the called method.
   * @param array $arguments
   *   The arguments for the decorated __call().
   *
   * @return mixed
   *   The result of the decorated __call().
   */
  public function __call($name, array $arguments) {
    debug($name);
    debug($arguments);
    return call_user_func_array(array($this->collection, $name), $arguments);
  }

}

/**
 * Class MongoDummy is a fake object accepting any method and doing nothing.
 */
class MongoDummy {

  public $connection;

  /**
   * Pretend to return a collection.
   *
   * @return \MongoDummy
   *   The returned value is actually a new MongoDummy instance.
   */
  public function selectCollection() {
    return new MongoDummy();
  }

  /**
   * Magic __call accepting any method name and doing nothing.
   *
   * @param string $name
   *   Ignored.
   * @param array $arguments
   *   All arguments are ignored.
   */
  public function __call($name, array $arguments) {
  }

}

/**
 * Returns the name to use for the collection.
 *
 * Also works with prefixes and simpletest.
 */
function mongodb_collection_name($name) {
  global $db_prefix;
  static $simpletest_prefix;
  // We call this function earlier than the database is initalized so we would
  // read the parent collection without this.
  if (!isset($simpletest_prefix)) {
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/^(simpletest\d+);/", $_SERVER['HTTP_USER_AGENT'], $matches)) {
      $simpletest_prefix = $matches[1];
    }
    else {
      $simpletest_prefix = '';
    }
  }
  // However, once the test information is initialized, simpletest_prefix
  // is no longer needed.
  if (!empty($GLOBALS['drupal_test_info']['test_run_id'])) {
    $simpletest_prefix = $GLOBALS['drupal_test_info']['test_run_id'];
  }
  return "${simpletest_prefix}${name}";
}

/**
 * Implements hook_test_group_finished().
 *
 * @throws \InvalidArgumentException
 *   If the database cannot be selected.
 *
 * @throws \MongoDB\Driver\Exception\ConnectionException
 *   If the connection cannot be established.
 */
function mongodb_test_group_finished() {
  $aliases = variable_get('mongodb_connections', array());
  $aliases['default'] = TRUE;
  foreach (array_keys($aliases) as $alias) {
    $db = mongodb($alias);
    foreach ($db->listCollections() as $collection) {
      if (preg_match('/\.simpletest\d+/', $collection)) {
        $db->dropCollection($collection);
      }
    }
  }
}

/**
 * Allow for the database connection we are using to be changed.
 *
 * @param string $alias
 *   The alias that we want to change the connection for.
 * @param string $connection_name
 *   The name of the connection we will use.
 */
function mongodb_set_active_connection($alias, $connection_name = 'default') {
  // No need to check if the connection is valid as mongodb() does this.
  $alias_exists = isset($GLOBALS['conf']['mongodb_collections'][$alias]) && is_array($GLOBALS['conf']['mongodb_collections'][$alias]);
  if ($alias_exists & !empty($GLOBALS['conf']['mongodb_collections'][$alias]['db_connection'])) {
    $GLOBALS['conf']['mongodb_collections'][$alias]['db_connection'] = $connection_name;
  }
  else {
    $GLOBALS['conf']['mongodb_collections'][$alias] = $connection_name;
  }
}

/**
 * Return the next id in a sequence.
 *
 * @param string $name
 *   The name of the sequence.
 * @param int $existing_id
 *   An existing id.
 *
 * @return int
 *   The next id in the sequence.
 *
 * @throws \MongoConnectionException
 *   If the connection cannot be established.
 */
function mongodb_next_id($name, $existing_id = 0) {
  // Atomically get the next id in the sequence.
  $mongo = mongodb();
  $cmd = array(
    'findandmodify' => mongodb_collection_name('sequence'),
    'query' => array('_id' => $name),
    'update' => array('$inc' => array('value' => 1)),
    'new' => TRUE,
  );
  // It's very likely that this is not necessary as command returns an array
  // not an exception. The increment will, however, will fix the problem of
  // the sequence not existing. Still, better safe than sorry.
  try {
    $sequence = $mongo->command($cmd);
    $value = isset($sequence['value']['value']) ? $sequence['value']['value'] : 0;
  }
  catch (Exception $e) {
  }
  if (0 < $existing_id - $value + 1) {
    $cmd = array(
      'findandmodify' => mongodb_collection_name('sequence'),
      'query' => array('_id' => $name),
      'update' => array('$inc' => array('value' => $existing_id - $value + 1)),
      'upsert' => TRUE,
      'new' => TRUE,
    );
    $sequence = $mongo->command($cmd);
    $value = isset($sequence['value']['value']) ? $sequence['value']['value'] : 0;
  }
  return $value;
}

/**
 * Returns default options for MongoDB write operations.
 *
 * @param bool $safe
 *   Set it to FALSE for "fire and forget" write operation.
 *
 * @return array
 *   Default options for Mongo write operations.
 */
function mongodb_default_write_options($safe = TRUE) {
  if ($safe) {
    if (version_compare(phpversion('mongo'), '1.5.0') == -1) {
      return array('safe' => TRUE);
    }
    else {
      return variable_get('mongodb_write_safe_options', array('w' => 1));
    }
  }
  else {
    if (version_compare(phpversion('mongo'), '1.3.0') == -1) {
      return array();
    }
    else {
      return variable_get('mongodb_write_nonsafe_options', array('w' => 0));
    }
  }
}
