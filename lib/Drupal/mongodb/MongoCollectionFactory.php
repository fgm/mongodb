<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongodbBundle.
 */

namespace Drupal\mongodb;

use Symfony\Component\DependencyInjection\ContainerInterface;

class MongoCollectionFactory {

  /**
   * var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;


  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  function __construct(ContainerInterface $container, array $collection_info) {
    $this->container = $container;
    $this->collectionInfo = $collection_info;
  }

  /**
   * @param string $collection_name
   * @return \MongoCollection
   */
  public function get($collection_name) {
    $args = array_filter(func_get_args());
    if (is_array($args[0])) {
      list($collection_name, $prefixed) = $args[0];
      $prefixed .= $collection_name;
    }
    else {
      // Avoid something. collection names if NULLs are passed in.
      $collection_name = implode('.', array_filter($args));
      $prefixed = $this->prefix() . $collection_name;
    }
    $service_name = $this->container->has("mongo.$collection_name") ? $collection_name : 'default';
    return $this->container->get("mongo.$service_name")->get($prefixed);
  }

  protected function prefix() {
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
    return $simpletest_prefix;
  }
}