<?php
/**
 * @file
 * Contains State.php
 *
 * A D7 implementation of the D8 StateInterface.
 */

namespace Drupal\mongodb_path\Drupal8;

/**
 * Class State.
 *
 * A Drupal 7 implementation of the Drupal 8 StateInterface.
 *
 * @package Drupal\mongodb_path
 */
class State implements StateInterface {

  /**
   * Returns the stored value for a given key.
   *
   * @param string $key
   *   The key of the data to retrieve.
   * @param mixed $default
   *   The default value to use if the key is not found.
   *
   * @return mixed
   *   The stored value, or NULL if no value exists.
   */
  public function get($key, $default = NULL) {
    return variable_get($key, $default);
  }

  /**
   * Returns the stored key/value pairs for a given set of keys.
   *
   * @param array $keys
   *   A list of keys to retrieve.
   *
   * @return array
   *   An associative array of items successfully returned, indexed by key.
   */
  public function getMultiple(array $keys) {
    $ret = [];
    foreach ($keys as $key => $default) {
      $ret[$key] = variable_get($key, $default);
    }
    return $ret;
  }

  /**
   * Saves a value for a given key.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  public function set($key, $value) {
    variable_set($key, $value);
  }

  /**
   * Saves key/value pairs.
   *
   * @param array $data
   *   An associative array of key/value pairs.
   */
  public function setMultiple(array $data) {
    foreach ($data as $key => $value) {
      variable_set($key, $value);
    }
  }

  /**
   * Deletes an item.
   *
   * @param string $key
   *   The item name to delete.
   */
  public function delete($key) {
    variable_del($key);
  }

  /**
   * Deletes multiple items.
   *
   * @param array $keys
   *   A list of item names to delete.
   */
  public function deleteMultiple(array $keys) {
    foreach ($keys as $key) {
      variable_del($key);
    }
  }

  /**
   * Resets the static cache.
   *
   * This is mainly used in testing environments.
   */
  public function resetCache() {
    // This does not do anything with the D7 variable system.
  }
}
