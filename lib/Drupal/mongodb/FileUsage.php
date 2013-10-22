<?php

/**
 * @file
 * Definition of Drupal\mongodb\FileUsage.
 */

namespace Drupal\mongodb;

use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageBase;

/**
 * Defines the mongodb file usage backend.
 */
class FileUsage extends FileUsageBase {

  /**
   * The mongodb factory registered as a service.
   *
   * @var \Drupal\mongodb\MongoCollectionFactory
   */
  protected $database;

  /**
   * The name of the mongodb collection used to store file usage information.
   *
   * @var string
   */
  protected $collection;

  /**
   * Construct the DatabaseFileUsageBackend.
   *
   * @param \Drupal\mongodb\MongoCollectionFactory $database
   *   The database connection which will be used to store the file usage
   *   information.
   * @param string $collection
   *   (optional) The collection to store file usage info. Defaults to 'file_usage'.
   */
  public function __construct(MongoCollectionFactory $database, $collection = 'file_usage') {
    $this->database = $database;
    $this->collection = $collection;
  }

  /**
   * Implements Drupal\file\FileUsage\FileUsageInterface::add().
   */
  public function add(File $file, $module, $type, $id, $count = 1) {
    $key = array(
      'fid' => (int) $file->id(),
      'module' => $module,
      'type' => $type,
      'id' => (int) $id,
    );
    $this->database->get($this->collection)->update($key, array('$inc' => array('count' => $count)), array('upsert' => TRUE, 'safe' => TRUE));

    parent::add($file, $module, $type, $id, $count);
  }

  /**
   * Implements Drupal\file\FileUsage\FileUsageInterface::delete().
   */
  public function delete(File $file, $module, $type = NULL, $id = NULL, $count = 1) {
    $key = array(
      'fid' => (int) $file->id(),
      'module' => $module,
    );

    if ($type && $id) {
      $key['type'] = $type;
      $key['id'] = (int) $id;
    }

    if ($count) {
      $key['count']['$lte'] = $count;
    }

    // Delete entries that have a exact or less value to prevent empty rows.
    $record = $this->database->get($this->collection)->remove($key, array('safe' => TRUE));

    if (empty($record['n']) && $count > 0) {
      unset($key['count']);
      // Assume that we do not want to update if item is not in the collection.
      try {
        $this->database->get($this->collection)->update($key, array('$inc' => array('count' => -1 * $count)), array('safe' => TRUE));
      }
      catch (Exception $e) {
      }
    }

    parent::delete($file, $module, $type, $id, $count);
  }

  /**
   * Implements Drupal\file\FileUsage\FileUsageInterface::listUsage().
   */
  public function listUsage(File $file) {
    $key = array(
      'fid' => (int) $file->id(),
      'count' => array(
        '$gt' => 0,
      ),
    );
    $results = $this->database->get($this->collection)->find($key);
    $references = array();
    foreach ($results as $usage) {
      $references[$usage['module']][$usage['type']][$usage['id']] = $usage['count'];
    }
    return $references;
  }
}
