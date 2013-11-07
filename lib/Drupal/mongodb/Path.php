<?php

/**
 * @file
 * Contains Drupal\mongodb\Path.
 */

namespace Drupal\mongodb;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Path\AliasManager;

/**
 * Defines a class for CRUD operations on path aliases in MongoDB.
 */
class Path {

  /**
   * The object wrapping the MongoDB database object.
   *
   * @var MongoCollectionFactory
   */
  protected $mongo;

  /**
   * MongoDB collection name.
   *
   * @var string
   */
  protected $mongo_collection;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $module_handler;

  /**
   * Constructs a Path CRUD object.
   *
   * @param MongoCollectionFactory $mongo
   *   The object wrapping the MongoDB database object.
   *
   * @param \Drupal\Core\Path\AliasManager $alias_manager
   *   An alias manager with an internal cache of stored aliases.
   *
   * @param string $mongo_collection
   *   Mongo collection name to use. Defaults to "url_alias".
   *
   * @todo This class should not take an alias manager in its constructor. Once
   *   we move to firing an event for CRUD operations instead of invoking a
   *   hook, we can have a listener that calls cacheClear() on the alias manager.
   */
  public function __construct(MongoCollectionFactory $mongo, ModuleHandlerInterface $module_handler, AliasManager $alias_manager, $mongo_colleciton = 'url_alias') {
    $this->mongo = $mongo;
    $this->alias_manager = $alias_manager;
    $this->mongo_collection = $mongo_colleciton;
    $this->module_handler = $module_handler;
  }

  /**
   * Saves a path alias to the database.
   *
   * @param string $source
   *   The internal system path.
   *
   * @param string $alias
   *   The URL alias.
   *
   * @param string $langcode
   *   The language code of the alias.
   *
   * @param int $pid
   *   Unique path alias identifier.
   *
   * @return
   *   FALSE if the path could not be saved or an associative array containing
   *   the following keys:
   *   - source: The internal system path.
   *   - alias: The URL alias.
   *   - pid: Unique path alias identifier.
   *   - langcode: The language code of the alias.
   */
  public function save($source, $alias, $langcode = Language::LANGCODE_NOT_SPECIFIED, $pid = NULL) {

    $fields = array(
      'source' => $source,
      'alias' => $alias,
      'langcode' => $langcode,
    );

    $hook = 'path_update';
    if (empty($pid)) {
      $result = $this->mongo->get($this->mongo_collection)
        ->find(array(), array('_id' => TRUE))
        ->sort(array('_id' => -1))
        ->limit(1)
        ->getNext();
      $pid = $result['_id'] + 1;
      $hook = 'path_insert';
    }

    $response = $this->mongo->get('url_alias')->update(array('_id' => $pid), array('$set' => $fields), array('upsert' => TRUE));

    if (empty($response['err'])) {
      $fields['pid'] = $pid;
      // @todo Switch to using an event for this instead of a hook when core
      // does it with SQL implementation.
      $this->module_handler->invokeAll($hook, $fields);
      $this->alias_manager->cacheClear();
      return $fields;
    }
    return FALSE;
  }

  /**
   * Fetches a specific URL alias from the database.
   *
   * @param $conditions
   *   An array of query conditions.
   *
   * @return
   *   FALSE if no alias was found or an associative array containing the
   *   following keys:
   *   - source: The internal system path.
   *   - alias: The URL alias.
   *   - pid: Unique path alias identifier.
   *   - langcode: The language code of the alias.
   */
  public function load($conditions) {
    return $this->mongo->get($this->mongo_collection)->findOne($conditions);
  }

  /**
   * Deletes a URL alias.
   *
   * @param array $conditions
   *   An array of criteria.
   */
  public function delete($conditions) {
    $path = $this->load($conditions);
    $response = $this->mongo->get($this->mongo_collection)->remove($conditions);
    $this->module_handler->invokeAll('path_delete', $path);
    return $response['n'];
  }
}
