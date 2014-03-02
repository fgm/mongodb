<?php

/**
 * @file
 * Contains Drupal\mongodb\Path.
 */

namespace Drupal\mongodb;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;

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
   * @param string $mongo_collection
   *   Mongo collection name to use. Defaults to "url_alias".
   */
  public function __construct(MongoCollectionFactory $mongo, ModuleHandlerInterface $module_handler, $mongo_colleciton = 'url_alias') {
    $this->mongo = $mongo;
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
    // @TODO use separate collection for autoincrement keys and use findAndModify()?
    if (empty($pid)) {
      $result = $this->mongo->get($this->mongo_collection)
        ->find(array(), array('_id' => TRUE))
        ->sort(array('_id' => -1))
        ->limit(1)
        ->getNext();
      $pid = $result['_id'] + 1;
      $hook = 'path_insert';
    }

    $response = $this->mongo->get($this->mongo_collection)->update(array('_id' => $pid), array('$set' => $fields), array('upsert' => TRUE));

    if (empty($response['err'])) {
      $fields['pid'] = $pid;
      $this->module_handler->invokeAll($hook, $fields);
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

  /**
   * Preloads path alias information for a given list of source paths.
   *
   * @param $path
   *   The path to investigate for corresponding aliases.
   * @param $langcode
   *   Language code to search the path with. If there's no path defined for
   *   that language it will search paths without language.
   * @return array
   *   Source (keys) to alias (values) mapping.
   */
  public function preloadPathAlias($preloaded, $langcode) {
    $args = array(
      'source' => array( '$in' =>  $preloaded),
    );
    $select = array('source' => 1, 'alias' => 1);

    if ($langcode == Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = Language::LANGCODE_NOT_SPECIFIED;
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('_id' => 1));
    }
    elseif ($langcode < Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => 1, '_id' => 1));
    }
    else {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => -1, '_id' => 1));
    }

    $result = array();
    foreach ($cursor as $item) {
      $result[$item['source']] = $item['alias'];
    }

    return $result;
  }

  /**
   * Returns an alias of Drupal system URL.
   *
   * @param string $path
   *   The path to investigate for corresponding path aliases.
   * @param string $langcode
   *   Language code to search the path with. If there's no path defined for
   *   that language it will search paths without language.
   *
   * @return string|bool
   *   A path alias, or FALSE if no path was found.
   */
  public function lookupPathAlias($path, $langcode) {
    $args = array(
      'source' => $path,
    );
    $select = array('alias' => 1);

    if ($langcode == Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = Language::LANGCODE_NOT_SPECIFIED;
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('_id' => -1))->limit(1);
    }
    elseif ($langcode > Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => -1, '_id' => -1))->limit(1);
    }
    else {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => 1, '_id' => -1))->limit(1);
    }

    if ($alias = $cursor->getNext()) {
      return $alias['alias'];
    }

    return FALSE;
  }

  /**
   * Returns Drupal system URL of an alias.
   *
   * @param string $path
   *   The path to investigate for corresponding system URLs.
   * @param string $langcode
   *   Language code to search the path with. If there's no path defined for
   *   that language it will search paths without language.
   *
   * @return string|bool
   *   A Drupal system path, or FALSE if no path was found.
   */
  public function lookupPathSource($path, $langcode) {
    $args = array(
      'alias' => $path,
    );
    $select = array('source' => 1);

    if ($langcode == Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = Language::LANGCODE_NOT_SPECIFIED;
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('_id' => -1))->limit(1);
    }
    elseif ($langcode > Language::LANGCODE_NOT_SPECIFIED) {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => -1, '_id' => -1))->limit(1);
    }
    else {
      $args['langcode'] = array('$in' => array($langcode, Language::LANGCODE_NOT_SPECIFIED));
      $cursor = $this->mongo->get($this->mongo_collection)->find($args, $select)->sort(array('langcode' => 1, '_id' => -1))->limit(1);
    }

    if ($source = $cursor->getNext()) {
      return $source['source'];
    }

    return FALSE;
  }
}
