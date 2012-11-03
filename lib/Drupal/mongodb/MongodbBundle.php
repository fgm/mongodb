<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongodbBundle.
 */

namespace Drupal\mongodb;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Reference;

class MongodbBundle extends Bundle {

  public function build(ContainerBuilder $container) {
    global $conf;
    $conf += array('mongodb_connections' => array(), 'mongodb_collections' => array());
    $conf['mongodb_connections'] += array('default' => array());
    foreach ($conf['mongodb_connections'] as $alias => $connection) {
      $connection += array('host' => 'localhost', 'db' => 'drupal', 'connection_options' => array());
      $container
        ->register("mongo.$alias", 'Drupal\mongodb\Mongo')
        ->addArgument($connection);
    }
    $container
      ->register('mongo', 'Drupal\mongodb\MongoCollectionFactory')
      ->addArgument(new Reference('service_container'))
      ->addArgument($conf['mongodb_collections']);
    $container
      ->register('file.usage', 'Drupal\mongodb\FileUsage')
      ->addArgument(new Reference('mongo'))
      ->addArgument('file_usage');
    $container
      ->register('keyvalue', 'Drupal\mongodb\KeyValueFactory')
      ->addArgument(new Reference('mongo'))
      ->addArgument('file_usage');
  }

}
